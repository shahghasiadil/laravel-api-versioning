<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\ApiVersion;
use ShahGhasiAdil\LaravelApiVersioning\OpenApi\Adapters\L5SwaggerApiVersionAdapter;
use ShahGhasiAdil\LaravelApiVersioning\OpenApi\Adapters\ScrambleApiVersionAdapter;
use ShahGhasiAdil\LaravelApiVersioning\OpenApi\ApiVersionDescriptionProvider;

#[ApiVersion('1.0', deprecated: true, sunset: '2026-06-30')]
#[ApiVersion('2.0')]
class AdapterDescribedController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([]);
    }
}

beforeEach(function () {
    $this->app['router']->prefix('api')->group(function () {
        $this->app['router']->get('adapter-described', [AdapterDescribedController::class, 'index']);
    });
});

test('ScrambleApiVersionAdapter reports itself unavailable when the package is not installed', function () {
    expect(ScrambleApiVersionAdapter::isAvailable())->toBeFalse();
});

test('ScrambleApiVersionAdapter::register() is a no-op when Scramble is not installed', function () {
    /** @var ApiVersionDescriptionProvider $provider */
    $provider = app(ApiVersionDescriptionProvider::class);

    expect(ScrambleApiVersionAdapter::register($provider))->toBe([]);
});

test('L5SwaggerApiVersionAdapter reports itself unavailable when the package is not installed', function () {
    expect(L5SwaggerApiVersionAdapter::isAvailable())->toBeFalse();
});

test('L5SwaggerApiVersionAdapter::documentationsConfig() builds one entry per version', function () {
    /** @var ApiVersionDescriptionProvider $provider */
    $provider = app(ApiVersionDescriptionProvider::class);

    $documentations = L5SwaggerApiVersionAdapter::documentationsConfig($provider);

    expect($documentations)->toHaveKey('api_v_1_0');
    expect($documentations)->toHaveKey('api_v_2_0');

    $v1 = $documentations['api_v_1_0'];
    expect($v1['api']['title'])->toBe('API Documentation v1.0 (Deprecated)');
    expect($v1['api']['description'])->toContain('deprecated');
    expect($v1['routes']['api'])->toBe('api/documentation/1.0');
    expect($v1['paths']['annotations'])->toBe([app_path('Http/Controllers')]);

    $v2 = $documentations['api_v_2_0'];
    expect($v2['api']['title'])->toBe('API Documentation v2.0');
    expect($v2['api'])->not->toHaveKey('description');
});

test('L5SwaggerApiVersionAdapter::documentationsConfig() honors custom annotation paths per version', function () {
    /** @var ApiVersionDescriptionProvider $provider */
    $provider = app(ApiVersionDescriptionProvider::class);

    $documentations = L5SwaggerApiVersionAdapter::documentationsConfig($provider, [
        '2.0' => ['/app/Http/Controllers/Api/V2'],
    ]);

    expect($documentations['api_v_2_0']['paths']['annotations'])->toBe(['/app/Http/Controllers/Api/V2']);
    expect($documentations['api_v_1_0']['paths']['annotations'])->toBe([app_path('Http/Controllers')]);
});
