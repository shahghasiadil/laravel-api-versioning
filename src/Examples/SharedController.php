<?php

namespace ShahGhasiAdil\LaravelApiVersioning\Examples;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\ApiVersionNeutral;
use ShahGhasiAdil\LaravelApiVersioning\Traits\HasApiVersionAttributes;

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
