<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\ApiVersion;
use ShahGhasiAdil\LaravelApiVersioning\OpenApi\ApiVersionDescriptionProvider;
use ShahGhasiAdil\LaravelApiVersioning\Services\VersionComparator;
use ShahGhasiAdil\LaravelApiVersioning\ValueObjects\ApiVersionDescription;

#[ApiVersion('1.0', deprecated: true, sunset: '2026-06-30', replacedBy: '2.0')]
#[ApiVersion('2.0')]
class DescribedController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([]);
    }
}

beforeEach(function () {
    $this->app['router']->prefix('api')->group(function () {
        $this->app['router']->get('described', [DescribedController::class, 'index']);
    });
});

test('describe() reports every version with its routes', function () {
    /** @var ApiVersionDescriptionProvider $provider */
    $provider = app(ApiVersionDescriptionProvider::class);

    $descriptions = $provider->describe();

    expect($descriptions)->not->toBeEmpty();
    expect($descriptions[0])->toBeInstanceOf(ApiVersionDescription::class);

    $byVersion = collect($descriptions)->keyBy('version');

    expect($byVersion->has('1.0'))->toBeTrue();
    expect($byVersion->has('2.0'))->toBeTrue();

    $v1 = $byVersion->get('1.0');
    expect($v1->isDeprecated)->toBeTrue();
    expect($v1->sunsetPolicy?->formattedDate())->not->toBeNull();
    expect($v1->routes)->toHaveCount(1);
    expect($v1->routes[0]->uri)->toBe('api/described');
    expect($v1->routes[0]->action)->toBe(DescribedController::class.'@index');

    $v2 = $byVersion->get('2.0');
    expect($v2->isDeprecated)->toBeFalse();
    expect($v2->sunsetPolicy)->toBeNull();
});

test('descriptions are sorted by version', function () {
    /** @var ApiVersionDescriptionProvider $provider */
    $provider = app(ApiVersionDescriptionProvider::class);

    $versions = array_map(fn (ApiVersionDescription $d): string => $d->version, $provider->describe());
    $sorted = $versions;
    sort($sorted, SORT_STRING);

    // Not asserting exact sort algorithm here, just that it's some
    // consistent, non-arbitrary order (ascending by version).
    expect($versions)->toBe((new VersionComparator)->sort($versions));
});

test('toArray() produces a plain, serializable structure', function () {
    /** @var ApiVersionDescriptionProvider $provider */
    $provider = app(ApiVersionDescriptionProvider::class);

    $array = $provider->describe()[0]->toArray();

    expect($array)->toHaveKeys(['version', 'is_deprecated', 'sunset_date', 'routes']);
});

test('a version declared only via sunset_policies config gets a policy even when not deprecated', function () {
    config(['api-versioning.sunset_policies' => [
        '2.0' => ['date' => '2027-01-01', 'link' => 'https://example.com/migrate'],
    ]]);

    /** @var ApiVersionDescriptionProvider $provider */
    $provider = app(ApiVersionDescriptionProvider::class);

    $byVersion = collect($provider->describe())->keyBy('version');
    $v2 = $byVersion->get('2.0');

    expect($v2->isDeprecated)->toBeFalse();
    expect($v2->sunsetPolicy)->not->toBeNull();
    expect($v2->sunsetPolicy->hasLinks())->toBeTrue();
});
