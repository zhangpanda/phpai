<?php

/**
 * Example: Multi-Agent Collaboration
 *
 * Two modes:
 * 1. Pipeline — chain agents sequentially (researcher → writer → reviewer)
 * 2. Router  — an orchestrator agent picks the right specialist
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Synapse\Agent\Agent;
use Synapse\Agent\Team;
use Synapse\Chat\Provider\DeepSeek;

$chat = new DeepSeek(apiKey: $_ENV['DEEPSEEK_API_KEY'] ?? getenv('DEEPSEEK_API_KEY') ?: throw new RuntimeException('Set DEEPSEEK_API_KEY'));

// --- Pipeline Mode ---
$team = Team::create()
    ->add('researcher', Agent::create()
        ->provider($chat)
        ->system('你是调研专家，给出关键事实和数据，用中文回答')
        ->maxIterations(3))
    ->add('writer', Agent::create()
        ->provider($chat)
        ->system('你是文案写手，根据调研内容写一段 200 字左右的介绍')
        ->maxIterations(3));

$result = $team->pipeline('PHP 8.3 有哪些新特性？');
echo "=== Pipeline Result ===\n";
echo $result->content . "\n\n";

// --- Router Mode ---
$team2 = Team::create()
    ->add('coder', Agent::create()
        ->provider($chat)
        ->system('你是 PHP 编程专家，给出完整代码示例')
        ->maxIterations(3))
    ->add('explainer', Agent::create()
        ->provider($chat)
        ->system('你是技术讲师，用通俗易懂的方式解释概念')
        ->maxIterations(3))
    ->router(Agent::create()
        ->provider($chat)
        ->system('你是路由器，根据用户问题选择最合适的专家。只回复专家名称：coder 或 explainer')
        ->maxIterations(1));

$result2 = $team2->route('写一个 PHP 单例模式的例子');
echo "=== Router Result ===\n";
echo "Routed to: {$result2->steps[0]->agent}\n";
echo $result2->content . "\n";
