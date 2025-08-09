<?php

namespace ShahGhasiAdil\LaravelApiVersioning\Examples;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\ApiVersion;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\Deprecated;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\MapToApiVersion;
use ShahGhasiAdil\LaravelApiVersioning\Traits\HasApiVersionAttributes;

#[ApiVersion(['1.0', '1.1'])]
#[Deprecated(
    message: 'This controller is deprecated. Use V2UserController instead.',
    sunsetDate: '2025-12-31',
    replacedBy: '2.0'
)]
class V1UserController extends Controller
{
    use HasApiVersionAttributes;

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                ['id' => 1, 'name' => 'John Doe'],
                ['id' => 2, 'name' => 'Jane Smith'],
            ],
            'version' => $this->getCurrentApiVersion(),
            'deprecated' => $this->isVersionDeprecated(),
        ]);
    }

    #[MapToApiVersion('1.1')]
    public function show(int $id): JsonResponse
    {
        return response()->json([
            'data' => [
                'id' => $id,
                'name' => 'John Doe',
                'email' => 'john@example.com', // Added in v1.1
            ],
            'version' => $this->getCurrentApiVersion(),
        ]);
    }
}
