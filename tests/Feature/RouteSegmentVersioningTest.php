<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\ApiVersion;

#[ApiVersion(['1.0', '2.0'])]
class SegmentVersioningController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['version' => request()->apiVersion()]);
    }
}

test('a single route template (via Route::pattern) serves every declared version', function () {
    // No macro: exercises the plain Route::pattern('version', ...) +
    // {version} URI segment approach directly.
    Route::prefix('api/v{version}')->middleware(['api', 'api.version'])->group(function () {
        Route::get('segment-users', [SegmentVersioningController::class, 'index']);
    });

    test()->get('/api/v1.0/segment-users')->assertStatus(200)->assertJson(['version' => '1.0']);
    test()->get('/api/v2.0/segment-users')->assertStatus(200)->assertJson(['version' => '2.0']);

    // A version the controller doesn't implement: matches the route
    // (the segment is syntactically valid) but is rejected by attribute resolution.
    test()->get('/api/v3.0/segment-users')->assertStatus(400);
});

test('Route::apiVersion([...])->group() constrains the segment to exactly the given versions', function () {
    Route::prefix('api')->group(function () {
        Route::apiVersion(['1.0', '2.0'])->group(function () {
            Route::get('macro-users', [SegmentVersioningController::class, 'index']);
        });
    });

    test()->get('/api/v1.0/macro-users')->assertStatus(200)->assertJson(['version' => '1.0']);
    test()->get('/api/v2.0/macro-users')->assertStatus(200)->assertJson(['version' => '2.0']);

    // '3.0' isn't in the macro's version list at all, so the route itself
    // doesn't match (a genuine 404, not a 400 version-rejection).
    test()->get('/api/v3.0/macro-users')->assertStatus(404);
});

test('existing route registrations without the macro or {version} segment keep working unchanged', function () {
    Route::middleware(['api', 'api.version'])->prefix('api')->group(function () {
        Route::get('v1/legacy-users', [SegmentVersioningController::class, 'index']);
        Route::get('v2/legacy-users', [SegmentVersioningController::class, 'index']);
    });

    test()->withHeaders(['X-API-Version' => '1.0'])->get('/api/v1/legacy-users')
        ->assertStatus(200)->assertJson(['version' => '1.0']);
    test()->withHeaders(['X-API-Version' => '2.0'])->get('/api/v2/legacy-users')
        ->assertStatus(200)->assertJson(['version' => '2.0']);
});
