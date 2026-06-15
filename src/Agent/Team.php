<?php

declare(strict_types=1);

namespace Synapse\Agent;

/**
 * Multi-Agent orchestration.
 *
 * Supports two modes:
 * - Pipeline: agents run in sequence, each receiving the previous output
 * - Router: an orchestrator agent decides which specialist to delegate to
 */
final class Team
{
    /** @var array<string, Agent> */
    private array $agents = [];
    private ?Agent $router = null;

    private function __construct() {}

    public static function create(): self
    {
        return new self();
    }

    public function add(string $name, Agent $agent): self
    {
        $this->agents[$name] = $agent;
        return $this;
    }

    /**
     * Set a router agent that decides which specialist to use.
     * The router's system prompt should instruct it to respond with the agent name.
     */
    public function router(Agent $router): self
    {
        $this->router = $router;
        return $this;
    }

    /**
     * Pipeline mode: run agents in sequence.
     * @param list<string>|null $order  Agent names in order. Null = insertion order.
     */
    public function pipeline(string $input, ?array $order = null): TeamResult
    {
        $steps = [];
        $sequence = $order ?? array_keys($this->agents);
        $current = $input;

        foreach ($sequence as $name) {
            if (!isset($this->agents[$name])) {
                throw new \InvalidArgumentException("Agent '{$name}' not found in team.");
            }
            $response = $this->agents[$name]->run($current);
            $steps[] = new TeamStep($name, $current, $response);
            $current = $response->content;
        }

        return new TeamResult($current, $steps);
    }

    /**
     * Router mode: the router agent picks which specialist handles the input.
     */
    public function route(string $input, int $maxRounds = 1): TeamResult
    {
        if ($this->router === null) {
            throw new \RuntimeException('No router agent configured. Call ->router() first.');
        }

        $steps = [];
        $current = $input;
        $available = implode(', ', array_keys($this->agents));

        for ($i = 0; $i < $maxRounds; $i++) {
            $routerPrompt = "Available agents: [{$available}].\nUser request: {$current}\nRespond with ONLY the agent name to delegate to.";
            $routerResponse = $this->router->run($routerPrompt);
            $chosen = trim($routerResponse->content);

            // Match agent name (may contain extra text, try exact then partial)
            $agentName = $this->resolveAgentName($chosen);
            if ($agentName === null) {
                $steps[] = new TeamStep('router', $current, $routerResponse);
                return new TeamResult("Router could not resolve agent: {$chosen}", $steps);
            }

            $response = $this->agents[$agentName]->run($current);
            $steps[] = new TeamStep($agentName, $current, $response);
            $current = $response->content;
        }

        return new TeamResult($current, $steps);
    }

    private function resolveAgentName(string $raw): ?string
    {
        $trimmed = trim($raw);
        // Exact match first
        if (isset($this->agents[$trimmed])) {
            return $trimmed;
        }
        // Case-insensitive match
        $lower = strtolower($trimmed);
        foreach (array_keys($this->agents) as $name) {
            if (strtolower($name) === $lower || str_contains($lower, strtolower($name))) {
                return $name;
            }
        }
        return null;
    }
}
