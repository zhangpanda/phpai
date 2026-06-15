<?php

declare(strict_types=1);

namespace Synapse\Agent;

use Synapse\Agent\Memory\MemoryInterface;
use Synapse\Agent\Middleware\MiddlewareInterface;
use Synapse\Chat\ChatInterface;
use Synapse\Chat\Message;
use Synapse\Chat\Role;
use Synapse\Chat\Usage;
use Synapse\Tools\ToolRegistry;

final class Agent
{
    private ?ChatInterface $provider = null;
    private string $systemPrompt = '';
    private ToolRegistry $tools;
    private ?MemoryInterface $memory = null;
    /** @var list<MiddlewareInterface> */
    private array $middleware = [];
    private int $maxIterations = 10;

    private function __construct()
    {
        $this->tools = new ToolRegistry();
    }

    public static function create(): self
    {
        return new self();
    }

    public function provider(ChatInterface $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function system(string $prompt): self
    {
        $this->systemPrompt = $prompt;
        return $this;
    }

    public function tools(array $toolObjects): self
    {
        $this->tools->register(...$toolObjects);
        return $this;
    }

    public function memory(MemoryInterface $memory): self
    {
        $this->memory = $memory;
        return $this;
    }

    /** @param list<MiddlewareInterface> $middleware */
    public function middleware(array $middleware): self
    {
        $this->middleware = $middleware;
        return $this;
    }

    public function maxIterations(int $max): self
    {
        $this->maxIterations = $max;
        return $this;
    }

    public function run(string $input): AgentResponse
    {
        $context = new AgentContext(input: $input);

        if ($this->middleware === []) {
            return $this->execute($context);
        }

        $pipeline = array_reduce(
            array_reverse($this->middleware),
            fn(callable $next, MiddlewareInterface $mw) => fn(AgentContext $ctx) => $mw->handle($ctx, $next),
            fn(AgentContext $ctx) => $this->execute($ctx),
        );

        return $pipeline($context);
    }

    private function execute(AgentContext $context): AgentResponse
    {
        if ($this->provider === null) {
            throw new \RuntimeException('Agent requires a provider. Call ->provider($chat) before ->run().');
        }

        $messages = [];
        if ($this->systemPrompt !== '') {
            $messages[] = Message::system($this->systemPrompt);
        }
        if ($this->memory) {
            $loaded = $this->memory->load();
            $messages = array_merge($messages, $this->sanitizeMessages($loaded));
        }
        $messages[] = Message::user($context->input);

        $options = [];
        if (!$this->tools->isEmpty()) {
            $options['tools'] = $this->tools->getDefinitions();
        }

        $steps = [];
        $totalPrompt = 0;
        $totalCompletion = 0;

        for ($i = 0; $i < $this->maxIterations; $i++) {
            $response = $this->provider->send($messages, $options);
            $totalPrompt += $response->usage->promptTokens;
            $totalCompletion += $response->usage->completionTokens;

            if (!$response->hasToolCalls()) {
                try {
                    $this->memory?->save([Message::user($context->input), Message::assistant($response->content)]);
                } catch (\Throwable $e) {
                    error_log("[Synapse] Memory save failed: " . $e->getMessage());
                }

                return new AgentResponse(
                    content: $response->content,
                    steps: $steps,
                    totalUsage: new Usage($totalPrompt, $totalCompletion, $totalPrompt + $totalCompletion),
                );
            }

            $messages[] = Message::assistant('', $response->toolCalls);
            foreach ($response->toolCalls as $toolCall) {
                $result = $this->tools->execute($toolCall->name, $toolCall->arguments);
                $messages[] = Message::tool($result, $toolCall->id);
                $steps[] = ['tool' => $toolCall->name, 'args' => $toolCall->arguments, 'result' => $result];
            }
        }

        return new AgentResponse(
            content: 'Max iterations reached.',
            steps: $steps,
            totalUsage: new Usage($totalPrompt, $totalCompletion, $totalPrompt + $totalCompletion),
        );
    }

    /**
     * Remove orphan tool messages that don't have a preceding assistant message with tool_calls.
     * OpenAI/Anthropic APIs reject such messages.
     */
    private function sanitizeMessages(array $messages): array
    {
        $result = [];
        $hasAssistantWithTools = false;

        foreach ($messages as $msg) {
            if ($msg->role === Role::Assistant && !empty($msg->toolCalls)) {
                $hasAssistantWithTools = true;
                $result[] = $msg;
            } elseif ($msg->role === Role::Tool) {
                if ($hasAssistantWithTools) {
                    $result[] = $msg;
                }
                // else: skip orphan tool message
            } else {
                $hasAssistantWithTools = false;
                $result[] = $msg;
            }
        }
        return $result;
    }
}
