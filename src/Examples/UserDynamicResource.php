<?php

namespace ShahGhasiAdil\LaravelApiVersioning\Examples;

use Illuminate\Http\Request;
use ShahGhasiAdil\LaravelApiVersioning\Http\Resources\VersionedJsonResource;

class DynamicUserResource extends VersionedJsonResource
{
    /**
     * Define version configurations directly in the resource
     */
    protected array $versionConfigs = [
        '1.0' => ['id', 'name'],
        '1.1' => ['id', 'name', 'email'],
        '2.0' => ['id', 'name', 'email', 'created_at', 'profile'],
        '2.1' => ['id', 'name', 'email', 'created_at', 'updated_at', 'profile', 'preferences', 'stats'],
    ];

    protected function toArrayDefault(Request $request): array
    {
        $version = $this->getCurrentApiVersion();
        $config = $this->versionConfigs[$version] ?? $this->versionConfigs['2.1'];

        $data = [];
        foreach ($config as $field) {
            $data[$field] = $this->getFieldValue($field);
        }

        return $data;
    }

    private function getFieldValue(string $field): mixed
    {
        return match ($field) {
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'profile' => [
                'avatar' => $this->avatar_url,
                'bio' => $this->bio,
            ],
            'preferences' => [
                'theme' => $this->theme ?? 'light',
                'language' => $this->language ?? 'en',
            ],
            'stats' => [
                'login_count' => $this->login_count ?? 0,
                'posts_count' => $this->posts_count ?? 0,
            ],
            default => $this->$field ?? null,
        };
    }
}
