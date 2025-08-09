<?php

namespace ShahGhasiadil\LaravelApiVersioning\Examples;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use ShahGhasiadil\LaravelApiVersioning\Attributes\ApiVersionNeutral;
use ShahGhasiadil\LaravelApiVersioning\Traits\HasApiVersionAttributes;

class SharedController extends Controller
{
    use HasApiVersionAttributes;

    #[ApiVersionNeutral]
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'version' => $this->getCurrentApiVersion(),
        ]);
    }

    #[ApiVersionNeutral]
    public function info(): JsonResponse
    {
        return response()->json([
            'api_name' => 'My API',
            'current_version' => $this->getCurrentApiVersion(),
            'is_neutral' => $this->isVersionNeutral(),
        ]);
    }
}
