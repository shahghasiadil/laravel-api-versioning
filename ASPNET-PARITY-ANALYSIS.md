# Parity analysis: `laravel-api-versioning` vs `dotnet/aspnet-api-versioning`

Comparison performed against `dotnet/aspnet-api-versioning` @ `ace56db`, reading the actual
source under `src/Common`, `src/Abstractions`, and `src/AspNetCore` rather than the docs site.

The package already covers the core of what aspnet-api-versioning does — attribute-driven
declaration (`ApiVersion`, `MapToApiVersion`, `ApiVersionNeutral`), multi-source version
detection, deprecation metadata, and RFC 7807 errors. What follows is what is missing,
what is wrong, and what is worth porting.

---

## Feature matrix

| aspnet-api-versioning concept | Status here | Section |
| --- | --- | --- |
| `[ApiVersion]` / `[MapToApiVersion]` / `[ApiVersionNeutral]` | Present | — |
| `[ApiVersion(Deprecated = true)]` (per-version deprecation) | **Done** | [C2](#c2-per-version-deprecation) |
| `[AdvertiseApiVersions]` | **Done** | [C3](#c3-advertiseapiversions) |
| `ApiVersion` type: group date, major, minor, status | **Done** — `ValueObjects\ApiVersion`, `VersionComparator` delegates to it | [C1](#c1-a-real-apiversion-value-object) |
| `IApiVersionReader` + `ApiVersionReader.Combine` | **Done** — `Services\VersionReaders\*`, `detection_methods` kept as a shim | [C4](#c4-version-readers-as-strategy-objects) |
| Ambiguous version detection (`AmbiguousApiVersion`) | **Done** | [A7](#a7-conflicting-version-sources-are-silently-resolved-first-wins) |
| `IApiVersionSelector` (default / current / lowest / constant) | **Done** — `Services\VersionSelectors\*` | [C5](#c5-version-selectors) |
| `AssumeDefaultVersionWhenUnspecified` | **Done** — defaults `true`; `require_explicit_version` kept as an alias | [C5](#c5-version-selectors) |
| `ReportApiVersions` → `api-supported-versions` / `api-deprecated-versions` | **Done** | [B1](#b1-standard-reporting-headers) |
| RFC 8594 `Sunset` + RFC 8288 `Link; rel="sunset"` | **Done** | [C6](#c6-rfc-8594-sunset-policies) |
| Problem types: unsupported / unspecified / invalid / ambiguous | **Done** | [B2](#b2-a-complete-problem-details-vocabulary) |
| `UnsupportedApiVersionStatusCode` (400 / 404 / 501) | **Missing** — still hardcoded 400 | [B2](#b2-a-complete-problem-details-vocabulary) |
| Conventions API (version without touching the class) | **Done** — `ApiVersioning::conventions()` | [C7](#c7-conventions-api) |
| `ApiVersionRouteConstraint` / url-segment routing | **Done** — `Route::pattern('version', ...)` + `Route::apiVersion([...])->group()` | [C8](#c8-route-level-url-segment-versioning) |
| API explorer / versioned OpenAPI documents | **Done** (core provider) — `OpenApi\ApiVersionDescriptionProvider`; Scramble/L5-Swagger adapters not built | [C9](#c9-versioned-openapi--description-provider) |
| `ValidateApiVersioningOptions` at startup | **Done** — `--strict` on `api:version:health`, opt-in `validate_on_boot` | [B5](#b5-make-the-health-check-enforceable) |

---

## Part A — Bugs

Ordered by severity. Each is independently fixable and worth a patch release ahead of any
new feature work.

### A1. Cyclic `version_inheritance` hangs the request (infinite loop)

`VersionedJsonResource::callVersionMethod()` (`src/Http/Resources/VersionedJsonResource.php:61-71`)
and `VersionedResourceCollection::callVersionMethod()` (`src/Http/Resources/VersionedResourceCollection.php:64-74`)
walk the inheritance chain with an unguarded loop:

```php
$currentVersion = $version;
while (isset($inheritance[$currentVersion])) {
    $parentVersion = $inheritance[$currentVersion];
    // ...
    $currentVersion = $parentVersion;
}
```

Given `'version_inheritance' => ['1.0' => '1.1', '1.1' => '1.0']` and no matching mapped
method, this spins forever and hangs the worker. `VersionConfigService::getInheritanceChain()`
(`src/Services/VersionConfigService.php:44-71`) already implements the cycle guard correctly —
the resource classes just don't use it.

**Fix:** delete both copies of the chain walk and delegate to `VersionConfigService`. That
resolves A1, A2, and the duplication in one change.

### A2. `VersionedResourceCollection` ignores the `default_method` config

`src/Http/Resources/VersionedResourceCollection.php:48` hardcodes:

```php
$defaultMethod = 'toArrayDefault';
```

while `VersionedJsonResource.php:44-45` correctly reads `config('api-versioning.default_method')`.
Anyone who renames `default_method` gets working resources and silently broken collections.

### A3. `api:cache:clear` reports success while doing nothing

`AttributeCacheService::flush()` (`src/Services/AttributeCacheService.php:55-66`) is a
deliberate no-op on stores that don't support tags — which includes `file`, Laravel's
default. `ApiCacheClearCommand` then prints *"API versioning cache cleared successfully."*
regardless. After changing a controller's attributes on a file-cache deployment, stale
version resolution persists until the TTL expires, with no signal that anything is wrong.

**Fix:** maintain a key index (a single `api_versioning:keys` entry) so untagged stores can
be flushed for real; failing that, have the command detect the untagged case and warn
loudly instead of claiming success.

### A4. The in-process memory cache is never invalidated

`AttributeVersionResolver::$memoryCache` (`src/Services/AttributeVersionResolver.php:23`) is
`static` and only reset by `resetMemoryCache()`, which nothing but tests calls. Under
Octane / Swoole / RoadRunner it survives across requests, so `api:cache:clear` cannot reach
it and a config change mid-process is invisible. Note `phpstan.neon.dist` already sets
`checkOctaneCompatibility: true` — this is exactly the class of bug that flag exists for.

**Fix:** hook `resetMemoryCache()` into `AttributeCacheService::flush()` and into Octane's
`RequestReceived` / `RequestTerminated` listeners when `laravel/octane` is present.

### A5. Cache keys don't cover the config they depend on

For a version-neutral route, `createVersionInfo()` stores
`routeVersions: $this->versionManager->getSupportedVersions()`
(`src/Services/AttributeVersionResolver.php:59`) — a snapshot of `supported_versions` at
write time. The cache key (`generateRouteKey`) is only `controller@method:version`, so
adding `'3.0'` to `supported_versions` leaves neutral routes advertising the old list for
the whole TTL.

**Fix:** fold a short hash of the resolved config (`supported_versions`, `default_version`,
`version_inheritance`) into the cache key.

### A6. Two different error shapes for the same condition

`UnsupportedVersionException::render()` (`src/Exceptions/UnsupportedVersionException.php:27-45`)
returns `{"error": ..., "message": ..., "supported_versions": [...]}` as plain
`application/json`, while the middleware catches the same exception and returns RFC 7807
`application/problem+json` via `ProblemDetailsResponse` (`src/Middleware/AttributeApiVersionMiddleware.php:97-108`).
Clients see one shape or the other depending on whether the exception escaped the
middleware. The `render()` body is effectively dead code on the happy path and a
contract violation everywhere else.

**Fix:** have `render()` return the same `ProblemDetailsResponse` the middleware builds.

### A7. Conflicting version sources are silently resolved first-wins

`VersionManager::detectVersionFromRequest()` (`src/Services/VersionManager.php:28-46`) breaks
out of the loop on the first non-empty match. A request with `X-API-Version: 1.0` **and**
`?api-version=2.0` gets 1.0 with no indication the query string was ignored.

aspnet-api-versioning's `CombinedApiVersionReader` collects values from *every* reader and
returns a 400 `AmbiguousApiVersion` when they disagree
(`src/Common/src/Common/ApiVersionReader.cs`). That is the safer default: a client sending
two versions has a bug, and silently picking one hides it.

### A8. `~` constraints are wrong for single-segment versions

`VersionComparator::satisfies()` (`src/Services/VersionComparator.php:135-144`) computes the
upper bound from `parts[1] ?? '0'`, so `~2` becomes `>=2 <2.1`:

```
satisfies('2.5', '~2')  => false   // should be true
satisfies('3.0', '~2')  => false   // correct, but by accident
```

`~2` should mean `>=2.0 <3.0`. `~2.1` (currently `>=2.1 <2.2`) is correct.

### A9. Routes without a controller always 400

`AttributeVersionResolver::resolveVersionForRoute()` returns `null` when
`$route->getController()` is null (`src/Services/AttributeVersionResolver.php:35-37`), and the
middleware turns `null` into an unsupported-version 400
(`src/Middleware/AttributeApiVersionMiddleware.php:43-49`). Any closure route inside a group
carrying `api.version` is therefore unreachable — there is no way to mark it neutral,
because there is no class to attribute. aspnet-api-versioning explicitly supports Minimal
APIs here.

**Fix:** add `'closure_routes' => 'neutral' | 'reject'` (defaulting to `neutral`), and pair it
with the fluent conventions API in [C7](#c7-conventions-api) so closures can opt into real
versions.

### A10. `VersionConfigService` freezes config at construction

It reads `config('api-versioning')` in its constructor
(`src/Services/VersionConfigService.php:14-19`) and is bound as a singleton
(`src/ApiVersioningServiceProvider.php:60-62`). Any `config()->set()` after first resolution
is ignored — which bites tests, runtime reconfiguration, and anything resolving the service
during boot before all providers have registered. Read lazily instead.

### A11. Smaller items

- `VersionInfo::toArray()` (`src/ValueObjects/VersionInfo.php:23-33`) omits `routeVersions`,
  so serialized version info silently loses a field the object carries.
- `ApiVersionsCommand` filters routes with `str_contains($route->uri(), 'api/')` — misses
  APIs not mounted under `api/`, and matches unrelated URIs like `docs/api/guide`. Use the
  configured path prefix, with an `--all` escape hatch.
- `ApiVersionsCommand` swallows resolution failures in an empty `catch (\Exception $e) {}`.
  A route whose attributes fail to resolve is reported as healthy.
- `VersionComparator::getHighest()`, `getLowest()`, and `sort()` (lines 73, 87, 103) declare
  bare `array` with no `@param string[]`; `ProblemDetailsResponse` has the same gap on
  `$extensions`, `$supportedVersions`, and `$endpointVersions`. These are almost certainly
  part of the 35-entry `phpstan-baseline.neon`.
- The generated controller stub imports `MapToApiVersion` and `Deprecated`
  (`src/Console/stubs/versioned-controller.stub`) but only uses them conditionally, so every
  non-deprecated generated controller starts life with unused imports that Pint will flag.
- `src/Examples/` ships inside the distributed package. Those controllers and resources land
  in every consumer's `vendor/` and are visible to any reflection-based tooling. Move them to
  a non-autoloaded `examples/` directory or exclude them from the archive.

---

## Part B — Improvements to what already exists

### B1. Standard reporting headers

The package emits `X-API-Version`, `X-API-Supported-Versions`, `X-API-Route-Versions`,
`X-API-Deprecated`, `X-API-Deprecation-Message`, `X-API-Sunset`, `X-API-Replaced-By`.
aspnet-api-versioning emits exactly two, un-prefixed
(`src/Common/src/Common/DefaultApiVersionReporter.cs`):

```
api-supported-versions: 1.0, 1.1, 2.0
api-deprecated-versions: 1.0
```

Three things to change:

1. **Adopt the standard names.** `X-` prefixes have been deprecated since RFC 6648, and
   these two header names are what .NET clients, API gateways, and the
   `Asp.Versioning.Http.Client` package already look for.
2. **Report per-endpoint, not global.** `AttributeApiVersionMiddleware::addVersionHeaders()`
   (`src/Middleware/AttributeApiVersionMiddleware.php:71-72`) sets
   `X-API-Supported-Versions` from `VersionManager::getSupportedVersions()` — the
   application-wide config. A client hitting `/api/v1/users` is told the endpoint supports
   `1.0, 1.1, 2.0, 2.1` even when the controller only declares `1.0`. The correct value is
   already computed and sitting in `$versionInfo->routeVersions`.
3. **Add `api-deprecated-versions`.** There is currently no way to express "this endpoint
   supports 1.0 and 2.0, and 1.0 is deprecated" — see [C2](#c2-per-version-deprecation).

Make it a config toggle (`'report_api_versions' => true`) with a `'legacy_headers' => false`
opt-in, so existing consumers can migrate.

### B2. A complete problem-details vocabulary

`ProblemDetailsResponse` covers unsupported-version and route-not-found. aspnet defines four
distinct problems (`src/Common/src/Common.ProblemDetails/ProblemDetailsDefaults.cs`), each
with a stable `type` URI and an error code:

| Problem | Code | When |
| --- | --- | --- |
| Unsupported API version | `UnsupportedApiVersion` | Version is well-formed but not served here |
| Unspecified API version | `ApiVersionUnspecified` | No version given and none may be assumed |
| Invalid API version | `InvalidApiVersion` | The value isn't a parseable version at all |
| Ambiguous API version | `AmbiguousApiVersion` | Two sources disagree ([A7](#a7-conflicting-version-sources-are-silently-resolved-first-wins)) |

Today `?api-version=banana` and `?api-version=9.0` produce the identical 400 — the client
can't tell a typo from an unsupported release. Add the missing three, expose an error `code`
extension member, and make the status configurable:

```php
'unsupported_version_status_code' => 400,   // 400 | 404 | 501
'problem_type_base_url' => 'https://docs.example.com/problems',
```

with an `ErrorResponseFactory` contract so applications can reshape the body without
forking the middleware.

### B3. Media-type detection is too narrow

`VersionManager::extractVersionFromMediaType()` (`src/Services/VersionManager.php:90-105`)
matches a single `sprintf` pattern against `Accept` only. It misses:

- `Content-Type` — relevant on POST/PUT, which aspnet's `MediaTypeApiVersionReader` reads.
- Quoted parameters: `application/json; version="2.0"`.
- Multiple media types with q-values: `Accept: application/json;version=2.0;q=0.9, text/html;q=0.8`.
- Parameter order: `application/vnd.api+json; charset=utf-8; version=2.0`.

Parse the header properly (split on `,`, then on `;`, then look up the configured parameter
name) instead of regex-matching a format string.

### B4. Version detection should be order-configurable and per-source disableable at runtime

Detection order is currently whatever order the `detection_methods` array happens to be in —
which works, but is implicit and undocumented. Once readers become objects
([C4](#c4-version-readers-as-strategy-objects)) the order becomes an explicit list.

### B5. Make the health check enforceable

`api:version:health` is a good command and has no aspnet equivalent worth copying — aspnet
instead validates options at startup (`ValidateApiVersioningOptions.cs`) and fails fast.
Two additions:

- A `--strict` flag that treats warnings as failures, so CI can gate on it.
- Optional boot-time validation outside production (`'validate_on_boot' => env('APP_DEBUG')`)
  for the checks that are cheap: default version present in supported versions, no route
  declaring an unsupported version, no cycle in `version_inheritance`.

Also worth adding as a check: `version_method_mapping` keys that aren't in
`supported_versions`, and cycles in `version_inheritance` (currently only defended against
at read time, never reported).

---

## Part C — New features worth porting

### C1. A real `ApiVersion` value object

This is the foundational gap; several other items depend on it. Versions here are bare
strings compared with `version_compare()`, which produces:

```
'2' === '2.0'                    => false   // aspnet treats these as equal
compare('2024-01-01', '2024-02-01')          // works by luck, not design
```

The `'2' vs '2.0'` case is a live problem: `extractVersionFromPath()` pulls `2` out of
`/api/v2/users`, and `isSupportedVersion('2')` then fails against a config listing `'2.0'` —
so the most natural URL form in Laravel returns a 400 unless every consumer remembers to
write `/api/v2.0/users`.

aspnet's `ApiVersion` (`src/Abstractions/src/Asp.Versioning.Abstractions/ApiVersion.cs`) is
`GroupVersion` (a date) + `MajorVersion` + `MinorVersion` + `Status`, with:

- an implied minor version, so `2` and `2.0` compare equal;
- ordering by group date → major → minor → status, where a **null status sorts after any
  status**, so `1.0-beta < 1.0`;
- status validated against `[A-Za-z][A-Za-z0-9]*`.

Proposed:

```php
final readonly class ApiVersion implements Stringable
{
    public static function parse(string $text): self;      // 1 | 1.0 | 1.0-beta | 2024-11-01 | 2024-11-01.1-rc
    public static function tryParse(string $text): ?self;

    public ?CarbonImmutable $groupVersion;
    public ?int $major;
    public ?int $minor;
    public ?string $status;

    public function compareTo(self $other): int;
    public function equals(self $other): bool;
    public function isPrerelease(): bool;
}
```

`VersionComparator` then delegates to it and keeps its current string-in / bool-out surface
for backwards compatibility. This fixes A8 and the `2` ≡ `2.0` normalization at once, and
unlocks date-based group versioning (`#[ApiVersion('2024-11-01')]`), which is what
date-versioned APIs like Stripe's use and which the package cannot express today.

### C2. Per-version deprecation

Today `#[Deprecated]` targets a class or method, so a controller declaring
`#[ApiVersion(['1.0', '2.0'])]` can only be deprecated in full. aspnet attaches deprecation
to the *version*:

```csharp
[ApiVersion("1.0", Deprecated = true)]
[ApiVersion("2.0")]
public class OrdersController : ControllerBase
```

Proposed equivalent:

```php
#[ApiVersion('1.0', deprecated: true, sunset: '2026-06-30', replacedBy: '2.0')]
#[ApiVersion('2.0')]
class OrderController extends Controller {}
```

`#[Deprecated]` stays as the coarse-grained shorthand. This is what makes
`api-deprecated-versions` ([B1](#b1-standard-reporting-headers)) expressible at all, and it
is probably the single highest-value addition after the bug fixes: the common real-world
shape is one controller serving several versions where only the old ones are sunsetting.

### C3. `#[AdvertiseApiVersions]`

`src/Abstractions/src/Asp.Versioning.Abstractions/AdvertiseApiVersionsAttribute.cs` — declares
versions that exist but are implemented elsewhere (another service, another package, a
gateway route). They appear in `api-supported-versions` without the controller claiming to
serve them. Necessary for any API split across deployables, which is precisely when clients
most need accurate discovery headers.

### C4. Version readers as strategy objects

Replace the `match ($method)` in `VersionManager::detectVersionFromRequest()`
(`src/Services/VersionManager.php:34-40`) with a contract:

```php
interface ApiVersionReader
{
    /** @return string[] every version this reader found in the request */
    public function read(Request $request): array;
}
```

with `HeaderApiVersionReader`, `QueryStringApiVersionReader`, `UrlSegmentApiVersionReader`,
`MediaTypeApiVersionReader`, and a combining reader. Config gains a list form:

```php
'readers' => [
    Header::class => ['name' => 'X-API-Version'],
    QueryString::class => ['name' => 'api-version'],
    UrlSegment::class => ['prefix' => 'api/v'],
],
```

The existing `detection_methods` array keeps working as a shim. Returning *all* matches
rather than the first is what makes ambiguity detection ([A7](#a7-conflicting-version-sources-are-silently-resolved-first-wins))
possible, and a public contract lets applications add readers (subdomain, JWT claim,
API-key tier) without forking `VersionManager`.

### C5. Version selectors

When a client sends no version, the package always falls back to `default_version`
(`src/Services/VersionManager.php:49-51`). aspnet has four strategies plus a switch for
whether to assume anything at all:

| Selector | Behavior |
| --- | --- |
| `DefaultApiVersionSelector` | Always the configured default (current behavior) |
| `CurrentImplementationApiVersionSelector` | Highest **non-prerelease** version the matched route implements |
| `LowestImplementedApiVersionSelector` | Lowest non-prerelease version the route implements |
| `ConstantApiVersionSelector` | A fixed version, ignoring config |

Note both `Current` and `Lowest` filter out prerelease versions
(`.Where(v => v.Status == null)`), so an unversioned client never lands on a beta —
a detail worth copying exactly, and one that needs [C1](#c1-a-real-apiversion-value-object)
to express.

Equally important is `AssumeDefaultVersionWhenUnspecified`, which defaults to **false** in
aspnet: an unversioned request is an error, not a silent default. The package has no way to
require an explicit version, which is the stricter and generally better posture for a new
API.

```php
'version_selector' => 'current',              // default | current | lowest | constant
'assume_default_when_unspecified' => true,    // false => 400 ApiVersionUnspecified
```

### C6. RFC 8594 sunset policies

The package carries a sunset date on `#[Deprecated]` and emits it as `X-API-Sunset` with no
defined format (the examples use `2025-12-31`, a bare date). RFC 8594 defines a real
`Sunset` header — an HTTP-date — and pairs it with RFC 8288 web links:

```
Sunset: Wed, 30 Jun 2026 23:59:59 GMT
Link: <https://docs.example.com/versioning>; rel="sunset"; type="text/html"
Deprecation: true
```

aspnet models this as a `SunsetPolicy` (date + links) resolvable per API/version through
`ISunsetPolicyManager`, with a fluent builder
(`src/Common/src/Common/SunsetPolicyBuilder.cs`). Worth porting whole, including
config-declared policies so a version can be sunset without editing every controller:

```php
'sunset_policies' => [
    '1.0' => ['date' => '2026-06-30', 'link' => 'https://docs.example.com/migrate-to-v2'],
],
```

Keep emitting `X-API-Sunset` alongside for one minor release, then drop it.

### C7. Conventions API

Everything here is attribute-driven, which means a controller you don't own — from a
package, or generated — cannot be versioned at all. aspnet's answer is a fluent convention
builder registered at startup
(`src/Abstractions/src/Asp.Versioning.Abstractions/Conventions/`). Laravel equivalent:

```php
// AppServiceProvider::boot()
ApiVersioning::conventions(function (ConventionBuilder $convention): void {
    $convention->controller(PassportTokenController::class)
        ->hasApiVersion('1.0')
        ->hasDeprecatedApiVersion('0.9');

    $convention->controller(OrderController::class)
        ->action('legacyIndex')->mapToApiVersion('1.0');

    $convention->route('api/webhooks/*')->isApiVersionNeutral();
});
```

Conventions merge with attributes, attributes winning on conflict. The `route()` form also
solves closure routes ([A9](#a9-routes-without-a-controller-always-400)).

### C8. Route-level URL-segment versioning

Path versioning currently requires the version to be baked into each route definition, so
serving `1.0`, `1.1`, and `2.0` from one controller means three literal route registrations —
compare `tests/Feature/AttributeVersioningTest.php`, which registers `v1/users` and
`v2/users` separately. aspnet has an `ApiVersionRouteConstraint` and a configurable
`RouteConstraintName` (default `apiVersion`) so one template covers every version:

```csharp
[Route("api/v{version:apiVersion}/orders")]
```

Laravel equivalent — a route constraint plus a group macro:

```php
Route::pattern('version', ApiVersionRouteConstraint::PATTERN);

Route::prefix('api/v{version}')->middleware('api.version')->group(function () {
    Route::apiResource('users', UserController::class);   // serves every declared version
});

// or, declaring the versions a group serves:
Route::apiVersion(['1.0', '2.0'])->group(base_path('routes/api.php'));
```

This is a large ergonomics win and removes the class of mistake where a route is added for
`v2` but forgotten for `v1`.

### C9. Versioned OpenAPI / description provider

aspnet's API explorer is arguably its most-used feature: it exposes
`IApiVersionDescriptionProvider`, and Swashbuckle generates one OpenAPI document per API
version with deprecated versions flagged and sunset policies attached
(`VersionedApiDescriptionProvider`). Nothing here does that — `api:versions --json` is the
closest, and it emits table rows rather than a model.

Proposed:

```php
interface ApiVersionDescriptionProvider
{
    /** @return ApiVersionDescription[] version, isDeprecated, sunsetPolicy, routes */
    public function describe(): array;
}
```

plus thin adapters for Scramble and L5-Swagger that register one document per version. This
turns the package from "routing concern" into the thing that also documents the API, which
is what drives adoption of the .NET original.

### C10. Version-aware container bindings and request macros

Minor but cheap ergonomics. Version info is reachable only via
`HasApiVersionAttributes`, which reads `request()->attributes` — so anything not using the
trait must dig into request attributes by string key.

```php
// scoped bindings, resolvable via constructor or method injection
public function index(ApiVersion $version) { ... }

// request macro
$request->apiVersion();          // ApiVersion
$request->apiVersionInfo();      // VersionInfo
```

### C11. Events for deprecation telemetry

No aspnet analogue, but it pairs naturally with sunset policies and is idiomatic Laravel:
dispatch `ApiVersionResolved` and `DeprecatedApiVersionUsed` so applications can measure who
is still on a version before its sunset date arrives. Without this, a sunset date is a
promise nobody can verify is safe to keep.

---

## Suggested sequencing

**Patch — bug fixes, no API change**
A1 (infinite loop), A2 (`default_method`), A6 (error-shape divergence), A8 (`~` constraint),
A10 (frozen config), A11 (assorted).

**Minor — additive, opt-in behind config**
A3/A4/A5 (cache correctness), A7 + B2 (ambiguity + full problem vocabulary),
A9 (closure routes), B1 (standard headers, `legacy_headers` shim), B3 (media type parsing),
B5 (`--strict` health check), C2 (per-version deprecation), C3 (`AdvertiseApiVersions`),
C6 (sunset policies), C10 (bindings/macros), C11 (events).

**Major — new abstractions**
C1 (`ApiVersion` value object) first, since C4, C5, and C6 all lean on it; then C4 (readers),
C5 (selectors), C7 (conventions), C8 (route constraint), C9 (OpenAPI provider).

The two changes that would most change how the package feels in use are **C2** (per-version
deprecation, because that is the shape real APIs have) and **C8** (route-level versioning,
because duplicating route definitions per version is the friction users hit first).
**C1** is the prerequisite that makes the rest coherent.
