<?php

namespace ShahGhasiAdil\LaravelApiVersioning\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use ShahGhasiAdil\LaravelApiVersioning\ApiVersioningServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Additional setup can be done here
        $this->setUpConfig();
    }

    protected function getPackageProviders($app)
    {
        return [
            ApiVersioningServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Define environment setup
        $app['config']->set('api-versioning', [
            'default_version' => '2.0',
            'supported_versions' => ['1.0', '1.1', '2.0', '2.1'],
            'detection_methods' => [
                'header' => [
                    'enabled' => true,
                    'header_name' => 'X-API-Version',
                ],
                'query' => [
                    'enabled' => true,
                    'parameter_name' => 'api-version',
                ],
                'path' => [
                    'enabled' => true,
                    'prefix' => 'api/v',
                ],
                'media_type' => [
                    'enabled' => false,
                    'format' => 'application/vnd.api+json;version=%s',
                ],
            ],
            'version_method_mapping' => [
                '1.0' => 'toArrayV1',
                '1.1' => 'toArrayV11',
                '2.0' => 'toArrayV2',
                '2.1' => 'toArrayV21',
            ],
            'version_inheritance' => [
                '1.1' => '1.0',
                '2.1' => '2.0',
            ],
            'default_method' => 'toArrayDefault',
        ]);
    }

    protected function setUpConfig()
    {
        // Any additional config setup
        config(['api-versioning.supported_versions' => ['1.0', '1.1', '2.0', '2.1']]);
    }
}
