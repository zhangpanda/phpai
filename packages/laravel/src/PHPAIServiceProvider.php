<?php

declare(strict_types=1);

namespace PHPAI\Laravel;

use Illuminate\Support\ServiceProvider;
use PHPAI\Chat\ChatInterface;
use PHPAI\Chat\Provider\Anthropic;
use PHPAI\Chat\Provider\DeepSeek;
use PHPAI\Chat\Provider\Ollama;
use PHPAI\Chat\Provider\OpenAI;

final class PHPAIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/phpai.php', 'phpai');

        $this->app->singleton(ChatInterface::class, function ($app) {
            return $this->buildProvider($app['config']['phpai']);
        });

        $this->app->alias(ChatInterface::class, 'phpai.chat');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/phpai.php' => config_path('phpai.php'),
            ], 'phpai-config');
        }
    }

    private function buildProvider(array $config): ChatInterface
    {
        $name = $config['default'];
        $providerConfig = $config['providers'][$name] ?? [];

        return match ($name) {
            'openai' => new OpenAI(
                apiKey: $providerConfig['api_key'],
                model: $providerConfig['model'] ?? 'gpt-4o',
                baseUrl: $providerConfig['base_url'] ?? 'https://api.openai.com/v1',
            ),
            'anthropic' => new Anthropic(
                apiKey: $providerConfig['api_key'],
                model: $providerConfig['model'] ?? 'claude-sonnet-4-20250514',
            ),
            'deepseek' => new DeepSeek(
                apiKey: $providerConfig['api_key'],
                model: $providerConfig['model'] ?? 'deepseek-chat',
            ),
            'ollama' => new Ollama(
                model: $providerConfig['model'] ?? 'llama3',
                baseUrl: $providerConfig['base_url'] ?? 'http://localhost:11434',
            ),
            default => throw new \InvalidArgumentException("Unknown Synapse provider: {$name}"),
        };
    }
}
