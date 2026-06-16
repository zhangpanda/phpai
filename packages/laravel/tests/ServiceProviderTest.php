<?php

declare(strict_types=1);

namespace PHPAI\Laravel\Tests;

use Orchestra\Testbench\TestCase;
use PHPAI\Chat\ChatInterface;
use PHPAI\Laravel\PHPAIServiceProvider;

final class ServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PHPAIServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('phpai.default', 'ollama');
        $app['config']->set('phpai.providers.ollama', [
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
        $this->artisan('vendor:publish', ['--tag' => 'phpai-config'])
            ->assertExitCode(0);
    }
}
