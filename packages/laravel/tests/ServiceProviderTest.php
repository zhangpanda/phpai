<?php

declare(strict_types=1);

namespace Synapse\Laravel\Tests;

use Orchestra\Testbench\TestCase;
use Synapse\Chat\ChatInterface;
use Synapse\Laravel\SynapseServiceProvider;

final class ServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SynapseServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('synapse.default', 'ollama');
        $app['config']->set('synapse.providers.ollama', [
            'model' => 'llama3',
            'base_url' => 'http://localhost:11434',
        ]);
    }

    public function test_resolves_chat_interface(): void
    {
        $chat = $this->app->make(ChatInterface::class);
        $this->assertInstanceOf(ChatInterface::class, $chat);
    }

    public function test_config_is_published(): void
    {
        $this->artisan('vendor:publish', ['--tag' => 'synapse-config'])
            ->assertExitCode(0);
    }
}
