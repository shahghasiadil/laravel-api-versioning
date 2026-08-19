<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use ShahGhasiAdil\LaravelApiVersioning\ApiVersioning;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\ApiVersion;
use ShahGhasiAdil\LaravelApiVersioning\Conventions\ConventionBuilder;
use ShahGhasiAdil\LaravelApiVersioning\Conventions\ConventionRegistry;

// A controller with no version attributes at all -- the case the
// conventions API exists for (vendor/generated controllers you don't own).
class UnattributedConventionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['version' => request()->apiVersion()]);
    }

    public function legacy(): JsonResponse
    {
        return response()->json(['version' => request()->apiVersion()]);
    }
}

// A controller that DOES carry an attribute: attributes must win over any
// convention registered for the same class.
#[ApiVersion('5.0')]
class AttributedConventionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['version' => request()->apiVersion()]);
    }
}

beforeEach(function () {
    app(ConventionRegistry::class)->flush();

    // Conventions can declare versions outside the app-wide
    // 'supported_versions' allowlist (that check happens independently,
    // in VersionManager); widen it here so these tests exercise
    // convention resolution itself rather than that unrelated guard.
    config(['api-versioning.supported_versions' => ['0.9', '1.0', '1.1', '2.0', '2.1', '5.0', '9.9']]);

    $this->app['router']->middleware(['api', 'api.version'])->prefix('api')->group(function () {
        $this->app['router']->get('convention/index', [UnattributedConventionController::class, 'index']);
        $this->app['router']->get('convention/legacy', [UnattributedConventionController::class, 'legacy']);
        $this->app['router']->get('convention/attributed', [AttributedConventionController::class, 'index']);
        $this->app['router']->get('convention/webhooks/stripe', fn () => response()->json(['ok' => true]));
        $this->app['router']->get('convention/closure-versioned', fn () => response()->json(['ok' => true]));
    });
});

test('a controller convention versions an unattributed controller', function () {
    ApiVersioning::conventions(function (ConventionBuilder $c): void {
        $c->controller(UnattributedConventionController::class)->hasApiVersion('1.0');
    });

    $response = test()->withHeaders(['X-API-Version' => '1.0'])->get('/api/convention/index');

    $response->assertStatus(200)->assertJson(['version' => '1.0']);
});

test('an unsupported convention-declared version is rejected on that controller', function () {
    ApiVersioning::conventions(function (ConventionBuilder $c): void {
        $c->controller(UnattributedConventionController::class)->hasApiVersion('1.0');
    });

    $response = test()->withHeaders(['X-API-Version' => '2.0'])->get('/api/convention/index');

    $response->assertStatus(400);
});

test('an action-level convention overrides the controller-level convention', function () {
    ApiVersioning::conventions(function (ConventionBuilder $c): void {
        $convention = $c->controller(UnattributedConventionController::class)->hasApiVersion('1.0');
        $convention->action('legacy')->mapToApiVersion('0.9');
    });

    test()->withHeaders(['X-API-Version' => '0.9'])->get('/api/convention/legacy')
        ->assertStatus(200)->assertJson(['version' => '0.9']);

    // '1.0' (the controller-level convention) does not apply to this action.
    test()->withHeaders(['X-API-Version' => '1.0'])->get('/api/convention/legacy')
        ->assertStatus(400);
});

test('hasDeprecatedApiVersion marks a convention version deprecated', function () {
    ApiVersioning::conventions(function (ConventionBuilder $c): void {
        $c->controller(UnattributedConventionController::class)
            ->hasDeprecatedApiVersion('1.0', sunset: '2026-01-01', replacedBy: '2.0');
    });

    $response = test()->withHeaders(['X-API-Version' => '1.0'])->get('/api/convention/index');

    $response->assertStatus(200)
        ->assertHeader('api-deprecated-versions', '1.0');
});

test('isApiVersionNeutral makes a controller respond to every version', function () {
    ApiVersioning::conventions(function (ConventionBuilder $c): void {
        $c->controller(UnattributedConventionController::class)->isApiVersionNeutral();
    });

    test()->withHeaders(['X-API-Version' => '1.0'])->get('/api/convention/index')->assertStatus(200);
    test()->withHeaders(['X-API-Version' => '2.1'])->get('/api/convention/index')->assertStatus(200);
});

test('attributes win over a conflicting controller convention', function () {
    ApiVersioning::conventions(function (ConventionBuilder $c): void {
        // This controller already has #[ApiVersion('5.0')]; the convention
        // below must be entirely ignored since attributes are present.
        $c->controller(AttributedConventionController::class)->hasApiVersion('1.0');
    });

    test()->withHeaders(['X-API-Version' => '5.0'])->get('/api/convention/attributed')
        ->assertStatus(200)->assertJson(['version' => '5.0']);

    test()->withHeaders(['X-API-Version' => '1.0'])->get('/api/convention/attributed')
        ->assertStatus(400);
});

test('a route() convention makes a closure route version-neutral', function () {
    ApiVersioning::conventions(function (ConventionBuilder $c): void {
        $c->route('api/convention/webhooks/*')->isApiVersionNeutral();
    });

    test()->withHeaders(['X-API-Version' => '1.0'])->get('/api/convention/webhooks/stripe')->assertStatus(200);
    test()->withHeaders(['X-API-Version' => '9.9'])->get('/api/convention/webhooks/stripe')->assertStatus(200);
});

test('a route() convention gives a closure route real, non-neutral versions', function () {
    ApiVersioning::conventions(function (ConventionBuilder $c): void {
        $c->route('api/convention/closure-versioned')->hasApiVersion(['1.0']);
    });

    test()->withHeaders(['X-API-Version' => '1.0'])->get('/api/convention/closure-versioned')->assertStatus(200);
    test()->withHeaders(['X-API-Version' => '2.0'])->get('/api/convention/closure-versioned')->assertStatus(400);
});
