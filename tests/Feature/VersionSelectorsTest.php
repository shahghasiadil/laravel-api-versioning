<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\AdvertiseApiVersions;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\ApiVersion;

#[ApiVersion(['1.0', '2.0'])]
class SelectorTestController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['version' => request()->apiVersion()]);
    }
}

#[ApiVersion(['1.0', '2.0-beta'])]
class SelectorPrereleaseTestController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['version' => request()->apiVersion()]);
    }
}

#[ApiVersion('2.0')]
#[AdvertiseApiVersions('3.0')]
class SelectorAdvertisedTestController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['version' => request()->apiVersion()]);
    }
}

beforeEach(function () {
    // No version-detection methods enabled: every request below is
    // genuinely unversioned, exercising the selector rather than any
    // detection method.
    config([
        'api-versioning.detection_methods.header.enabled' => false,
        'api-versioning.detection_methods.query.enabled' => false,
        'api-versioning.detection_methods.path.enabled' => false,
        'api-versioning.detection_methods.media_type.enabled' => false,
    ]);

    $this->app['router']->middleware(['api', 'api.version'])->prefix('api')->group(function () {
        $this->app['router']->get('selector', [SelectorTestController::class, 'index']);
        $this->app['router']->get('selector-prerelease', [SelectorPrereleaseTestController::class, 'index']);
        $this->app['router']->get('selector-advertised', [SelectorAdvertisedTestController::class, 'index']);
    });
});

test('default selector always uses default_version', function () {
    config(['api-versioning.version_selector' => 'default', 'api-versioning.default_version' => '1.0']);

    $response = test()->get('/api/selector');

    $response->assertStatus(200)->assertJson(['version' => '1.0']);
});

test('current selector picks the highest non-prerelease implemented version', function () {
    config(['api-versioning.version_selector' => 'current']);

    $response = test()->get('/api/selector');

    $response->assertStatus(200)->assertJson(['version' => '2.0']);
});

test('lowest selector picks the lowest non-prerelease implemented version', function () {
    config(['api-versioning.version_selector' => 'lowest']);

    $response = test()->get('/api/selector');

    $response->assertStatus(200)->assertJson(['version' => '1.0']);
});

test('current selector skips prerelease versions', function () {
    config(['api-versioning.version_selector' => 'current']);

    $response = test()->get('/api/selector-prerelease');

    // Only '1.0' is non-prerelease; '2.0-beta' is skipped even though it's "higher".
    $response->assertStatus(200)->assertJson(['version' => '1.0']);
});

test('current selector never picks a version that is only advertised, not implemented', function () {
    config(['api-versioning.version_selector' => 'current']);

    $response = test()->get('/api/selector-advertised');

    // '3.0' is advertised (implemented elsewhere) but not resolvable here;
    // the route only genuinely implements '2.0'.
    $response->assertStatus(200)->assertJson(['version' => '2.0']);
});

test('constant selector always uses the configured constant, ignoring the route', function () {
    config([
        'api-versioning.version_selector' => 'constant',
        'api-versioning.version_selector_constant' => '2.0',
    ]);

    $response = test()->get('/api/selector');

    $response->assertStatus(200)->assertJson(['version' => '2.0']);
});

test('assume_default_when_unspecified=false rejects an unversioned request', function () {
    config(['api-versioning.assume_default_when_unspecified' => false]);

    $response = test()->get('/api/selector');

    $response->assertStatus(400)->assertJson([
        'title' => 'Unspecified API Version',
        'code' => 'ApiVersionUnspecified',
    ]);
});
