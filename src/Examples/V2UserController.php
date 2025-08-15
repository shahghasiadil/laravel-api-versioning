<?php

namespace ShahGhasiAdil\LaravelApiVersioning\Examples;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use ShahGhasiAdil\LaravelApiVersioning\Attributes\ApiVersion;
use ShahGhasiAdil\LaravelApiVersioning\Traits\HasApiVersionAttributes;

#[ApiVersion(['2.0', '2.1'])]
class V2UserController extends Controller
{
    use HasApiVersionAttributes;

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                [
                    'id' => 1,
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                    'created_at' => '2024-01-01T00:00:00Z',
                    'profile' => ['avatar' => 'avatar1.jpg'],
                ],
                [
                    'id' => 2,
                    'name' => 'Jane Smith',
                    'email' => 'jane@example.com',
                    'created_at' => '2024-01-02T00:00:00Z',
                    'profile' => ['avatar' => 'avatar2.jpg'],
                ],
            ],
            'version' => $this->getCurrentApiVersion(),
            'meta' => [
                'total' => 2,
                'per_page' => 10,
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $userData = [
            'id' => $id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'created_at' => '2024-01-01T00:00:00Z',
            'profile' => ['avatar' => 'avatar1.jpg'],
        ];

        // Add additional fields for v2.1
        if ($this->getCurrentApiVersion() === '2.1') {
            $userData['last_login'] = '2024-01-15T10:30:00Z';
            $userData['preferences'] = ['theme' => 'dark', 'language' => 'en'];
        }

        return response()->json([
            'data' => $userData,
            'version' => $this->getCurrentApiVersion(),
        ]);
    }
}
