<?php

namespace ShahGhasiAdil\LaravelApiVersioning\Examples;

use Illuminate\Http\Request;
use ShahGhasiAdil\LaravelApiVersioning\Http\Resources\VersionedJsonResource;

class UserResource extends VersionedJsonResource
{
    /**
     * Default transformation (latest version)
     */
    protected function toArrayDefault(Request $request): array
    {
        return $this->toArrayV21($request);
    }

    /**
     * Version 1.0 - Basic user info
     */
    protected function toArrayV1(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }

    /**
     * Version 1.1 - Added email
     */
    protected function toArrayV11(Request $request): array
    {
        return array_merge($this->toArrayV1($request), [
            'email' => $this->email,
        ]);
    }

    /**
     * Version 2.0 - Enhanced structure
     */
    protected function toArrayV2(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at->toISOString(),
            'profile' => [
                'avatar' => $this->avatar_url,
                'bio' => $this->bio,
            ],
        ];
    }

    /**
     * Version 2.1 - Added preferences and activity
     */
    protected function toArrayV21(Request $request): array
    {
        return array_merge($this->toArrayV2($request), [
            'updated_at' => $this->updated_at->toISOString(),
            'last_login' => $this->last_login?->toISOString(),
            'preferences' => [
                'theme' => $this->theme ?? 'light',
                'language' => $this->language ?? 'en',
                'notifications' => $this->notification_settings ?? [],
            ],
            'stats' => [
                'login_count' => $this->login_count ?? 0,
                'posts_count' => $this->posts_count ?? 0,
            ],
        ]);
    }
}
