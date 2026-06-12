# Synapse — PHP AI 框架设计文档

## 1. 项目概览

| 项目 | 信息 |
|------|------|
| 名称 | Synapse |
| 定位 | PHP 生态中最完整的 AI 应用开发框架 |
| 一句话 | Attribute 驱动、类型安全、可观测的 PHP AI 框架，让构建智能应用如同写 Laravel 一样优雅 |
| 目标用户 | PHP/Laravel/Symfony 开发者，需要集成 LLM 能力的项目 |
| 技术要求 | PHP >= 8.3, Composer 2.x |
| License | MIT |

### 为什么需要 Synapse？

| 特性 | Synapse | LLPhant | Neuron AI | openai-php |
|------|---------|---------|-----------|------------|
| MCP 原生支持 | ✅ Server + Client | ❌ | ❌ | ❌ |
| PHP 8 Attribute 驱动 | ✅ 零配置 | ❌ | 部分 | ❌ |
| 结构化输出类型安全 | ✅ Attribute → Schema → Object | 基础 | 基础 | ❌ |
| 可观测性 (OpenTelemetry) | ✅ 内置 | ❌ | 依赖 Inspector | ❌ |
| Agent 中间件 | ✅ PSR 风格 | ❌ | ✅ | ❌ |
| Memory 系统 | ✅ 多策略 | ❌ | ✅ | ❌ |
| 框架无关 | ✅ | ✅ | ✅ | ✅ |

---

## 2. 核心架构

```
┌─────────────────────────────────────────────────────────────┐
│                    Application Layer                         │
│  (用户代码: Agent 定义、Tool 类、结构化输出类)                 │
├─────────────────────────────────────────────────────────────┤
│                   Orchestration Layer                        │
│  ┌─────────┐  ┌─────────┐  ┌──────────┐  ┌──────────────┐ │
│  │  Agent  │  │   RAG   │  │  Prompt  │  │Observability │ │
│  └─────────┘  └─────────┘  └──────────┘  └──────────────┘ │
├─────────────────────────────────────────────────────────────┤
│                      Core Layer                              │
│  ┌─────────┐  ┌───────────────────┐  ┌──────────┐         │
│  │  Chat   │  │StructuredOutput   │  │  Tools   │         │
│  └─────────┘  └───────────────────┘  └──────────┘         │
│  ┌─────────────────────────────────────────────────────┐   │
│  │                     MCP                             │   │
│  └─────────────────────────────────────────────────────┘   │
├─────────────────────────────────────────────────────────────┤
│                    Provider Layer                            │
│  ┌────────┐ ┌──────────┐ ┌────────┐ ┌────────┐ ┌───────┐ │
│  │ OpenAI │ │Anthropic │ │ Ollama │ │DeepSeek│ │ Azure │ │
│  └────────┘ └──────────┘ └────────┘ └────────┘ └───────┘ │
├─────────────────────────────────────────────────────────────┤
│                  Infrastructure Layer                        │
│  HTTP Client (Guzzle) | Event Dispatcher | Logger | Cache   │
└─────────────────────────────────────────────────────────────┘
```

### 设计原则

1. **Attribute-First** — 用 PHP 8 Attribute 声明一切，零 YAML/XML 配置
2. **Type-Safe** — 结构化输出直接映射为强类型 PHP 对象
3. **Provider Agnostic** — 统一接口，一行代码切换 LLM
4. **Observable by Default** — 每次调用自动追踪 token/延迟/成本
5. **Composable** — 模块独立可用，按需组合
6. **Laravel-style DX** — 流畅 API，让开发者觉得"就该这么写"

---

## 3. 模块详细设计

### 3.1 Chat — 统一对话接口

**职责**: 提供统一的多 Provider 对话 API，支持同步/流式响应。

```php
<?php

namespace Synapse\Chat;

use Synapse\Chat\Message\Message;
use Synapse\Chat\Message\Role;

interface ChatInterface
{
    public function send(array $messages, array $options = []): Response;
    public function stream(array $messages, array $options = []): StreamResponse;
}

// 统一的 Message 值对象
final readonly class Message
{
    public function __construct(
        public Role $role,
        public string $content,
        public ?array $toolCalls = null,
        public ?string $toolCallId = null,
    ) {}

    public static function system(string $content): self
    {
        return new self(Role::System, $content);
    }

    public static function user(string $content): self
    {
        return new self(Role::User, $content);
    }
}

enum Role: string
{
    case System = 'system';
    case User = 'user';
    case Assistant = 'assistant';
    case Tool = 'tool';
}

// Response 值对象
final readonly class Response
{
    public function __construct(
        public string $content,
        public ?array $toolCalls,
        public Usage $usage,
        public string $model,
        public string $finishReason,
    ) {}
}

final readonly class Usage
{
    public function __construct(
        public int $promptTokens,
        public int $completionTokens,
        public int $totalTokens,
    ) {}
}
```

**Provider 实现示例:**

```php
<?php

namespace Synapse\Chat\Provider;

use Synapse\Chat\ChatInterface;
use Synapse\Chat\Response;

final class OpenAI implements ChatInterface
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = 'gpt-4o',
        private readonly string $baseUrl = 'https://api.openai.com/v1',
    ) {}

    public function send(array $messages, array $options = []): Response
    {
        // 实现 HTTP 调用
    }

    public function stream(array $messages, array $options = []): StreamResponse
    {
        // 实现 SSE 流式
    }
}
```

**使用示例:**

```php
use Synapse\Chat\Provider\OpenAI;
use Synapse\Chat\Message\Message;

$chat = new OpenAI(apiKey: $_ENV['OPENAI_API_KEY']);

$response = $chat->send([
    Message::system('你是一个友好的助手'),
    Message::user('用一句话解释什么是 PHP'),
]);

echo $response->content;
echo "消耗 {$response->usage->totalTokens} tokens";
```

---

### 3.2 Structured Output — 类型安全的结构化输出

**职责**: 利用 PHP 8 Attribute 定义输出 Schema，自动将 LLM 的 JSON 响应映射为强类型对象。

```php
<?php

namespace Synapse\StructuredOutput;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsOutput
{
    public function __construct(
        public string $description = '',
    ) {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Param
{
    public function __construct(
        public string $description = '',
        public bool $required = true,
        public ?array $enum = null,
    ) {}
}

// Schema 提取器 - 从 Attribute 生成 JSON Schema
final class SchemaExtractor
{
    public function extract(string $className): array
    {
        // 通过反射读取 Attribute，生成 JSON Schema
    }
}

// 反序列化器 - JSON → PHP 对象
final class Deserializer
{
    public function deserialize(string $json, string $className): object
    {
        // JSON 解码并映射到 PHP 对象
    }
}

// 便捷 trait - 可以混入到 ChatInterface
interface StructuredChatInterface
{
    /**
     * @template T of object
     * @param class-string<T> $outputClass
     * @return T
     */
    public function sendStructured(array $messages, string $outputClass): object;
}
```

**使用示例:**

```php
<?php

use Synapse\StructuredOutput\AsOutput;
use Synapse\StructuredOutput\Param;

#[AsOutput(description: '代码审查结果')]
final class CodeReview
{
    #[Param(description: '代码质量评分 1-10')]
    public int $score;

    #[Param(description: '发现的问题列表')]
    public array $issues;

    #[Param(description: '改进建议')]
    public string $suggestion;

    #[Param(description: '安全风险等级', enum: ['low', 'medium', 'high'])]
    public string $securityLevel;
}

// 使用
$review = $chat->sendStructured(
    messages: [Message::user("审查这段代码: {$code}")],
    outputClass: CodeReview::class,
);

// $review 是强类型的 CodeReview 对象
echo "评分: {$review->score}";
echo "安全等级: {$review->securityLevel}";
foreach ($review->issues as $issue) {
    echo "- {$issue}";
}
```

---

### 3.3 Tools — Attribute 驱动的工具系统

**职责**: 通过 PHP 8 Attribute 声明可被 LLM 调用的工具函数，自动注册和执行。

```php
<?php

namespace Synapse\Tools;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class AsTool
{
    public function __construct(
        public string $description,
        public ?string $name = null, // 默认用方法名
    ) {}
}

#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Param
{
    public function __construct(
        public string $description,
        public ?array $enum = null,
    ) {}
}

// 工具注册表
final class ToolRegistry
{
    private array $tools = [];

    public function register(object ...$toolObjects): self
    {
        foreach ($toolObjects as $object) {
            // 反射扫描 #[AsTool] 方法，提取 schema
            $this->extractTools($object);
        }
        return $this;
    }

    /** 获取所有工具的 JSON Schema 定义（传给 LLM） */
    public function getDefinitions(): array { /* ... */ }

    /** 执行工具调用 */
    public function execute(string $name, array $arguments): string { /* ... */ }
}
```

**使用示例:**

```php
<?php

use Synapse\Tools\AsTool;
use Synapse\Tools\Param;

final class WeatherTool
{
    #[AsTool(description: '获取指定城市的实时天气')]
    public function getWeather(
        #[Param(description: '城市名称，如"北京"')] string $city,
        #[Param(description: '温度单位', enum: ['celsius', 'fahrenheit'])] string $unit = 'celsius',
    ): string {
        // 调用天气 API
        return json_encode(['city' => $city, 'temp' => 25, 'unit' => $unit]);
    }
}

final class CalculatorTool
{
    #[AsTool(description: '执行数学计算')]
    public function calculate(
        #[Param(description: '数学表达式，如 "2+2"')] string $expression,
    ): string {
        return (string) eval("return {$expression};");
    }
}
```

---

### 3.4 Agent — 智能体

**职责**: ReAct 模式的 AI Agent，支持 Memory、中间件管道、工具调用循环。

```php
<?php

namespace Synapse\Agent;

use Synapse\Chat\ChatInterface;
use Synapse\Tools\ToolRegistry;
use Synapse\Agent\Memory\MemoryInterface;
use Synapse\Agent\Middleware\MiddlewareInterface;

final class Agent
{
    private ChatInterface $provider;
    private string $systemPrompt = '';
    private ToolRegistry $tools;
    private ?MemoryInterface $memory = null;
    private array $middleware = [];
    private int $maxIterations = 10;

    public static function create(): self
    {
        return new self();
    }

    public function provider(ChatInterface $provider): self { /* ... */ }
    public function system(string $prompt): self { /* ... */ }
    public function tools(array $toolObjects): self { /* ... */ }
    public function memory(MemoryInterface $memory): self { /* ... */ }
    public function middleware(array $middleware): self { /* ... */ }
    public function maxIterations(int $max): self { /* ... */ }

    public function run(string $input): AgentResponse
    {
        // ReAct 循环:
        // 1. 构建消息 (system + memory + user input)
        // 2. 调用 LLM
        // 3. 如果有 tool_calls → 执行工具 → 追加结果 → 回到 2
        // 4. 如果无 tool_calls → 返回最终响应
        // 5. 更新 memory
    }
}

// Memory 接口
namespace Synapse\Agent\Memory;

interface MemoryInterface
{
    public function load(): array; // 返回历史消息
    public function save(array $messages): void;
    public function clear(): void;
}

final class BufferMemory implements MemoryInterface
{
    public function __construct(private int $maxMessages = 50) {}
    // 保留最近 N 条消息
}

final class SummaryMemory implements MemoryInterface
{
    public function __construct(private ChatInterface $summarizer) {}
    // 超过阈值时用 LLM 摘要旧消息
}

// 中间件接口
namespace Synapse\Agent\Middleware;

interface MiddlewareInterface
{
    public function handle(AgentContext $context, callable $next): AgentResponse;
}

// 内置中间件
final class CostTracker implements MiddlewareInterface { /* 追踪累计 token 和费用 */ }
final class RateLimiter implements MiddlewareInterface { /* 限制请求速率 */ }
final class Logger implements MiddlewareInterface { /* 记录每步推理过程 */ }
```

**使用示例:**

```php
<?php

use Synapse\Agent\Agent;
use Synapse\Chat\Provider\OpenAI;
use Synapse\Agent\Memory\BufferMemory;
use Synapse\Agent\Middleware\CostTracker;

$agent = Agent::create()
    ->provider(new OpenAI(apiKey: $_ENV['OPENAI_API_KEY'], model: 'gpt-4o'))
    ->system('你是一个电商客服助手，帮助用户查询订单和商品信息。')
    ->tools([new OrderTool(), new ProductSearchTool()])
    ->memory(new BufferMemory(maxMessages: 30))
    ->middleware([new CostTracker(), new RateLimiter(rpm: 60)]);

$response = $agent->run('我的订单 #12345 到哪了？');

echo $response->content;   // "您的订单 #12345 目前在配送中..."
echo $response->steps;     // 查看推理步骤
echo $response->totalCost; // 本次对话花费
```

---

### 3.5 MCP — Model Context Protocol

**职责**: 原生支持 MCP 协议，可以将 PHP 应用暴露为 MCP Server（供 Claude Desktop 等使用），也可以作为 MCP Client 连接外部工具。

```php
<?php

namespace Synapse\MCP;

use Attribute;

// ===== MCP Server =====

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class McpTool
{
    public function __construct(
        public string $description,
        public ?string $name = null,
    ) {}
}

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class McpResource
{
    public function __construct(
        public string $uri,
        public string $description,
        public ?string $mimeType = 'application/json',
    ) {}
}

final class McpServer
{
    private array $tools = [];
    private array $resources = [];
    private string $name;
    private string $version;

    public static function create(string $name, string $version = '1.0.0'): self
    {
        $server = new self();
        $server->name = $name;
        $server->version = $version;
        return $server;
    }

    public function addTool(object $tool): self { /* 注册 #[McpTool] 方法 */ }
    public function addResource(object $resource): self { /* 注册 #[McpResource] */ }

    public function serveStdio(): never { /* stdio 传输 */ }
    public function serveHttp(int $port = 3000): never { /* HTTP SSE 传输 */ }
}

// ===== MCP Client =====

final class McpClient
{
    public static function connect(string $command, array $args = []): self { /* stdio */ }
    public static function connectHttp(string $url): self { /* HTTP SSE */ }

    public function listTools(): array { /* ... */ }
    public function callTool(string $name, array $args): string { /* ... */ }
    public function listResources(): array { /* ... */ }
    public function readResource(string $uri): string { /* ... */ }
}
```

**使用示例 — MCP Server:**

```php
<?php
// bin/mcp-server.php — 暴露电商系统为 MCP Server

use Synapse\MCP\McpServer;
use Synapse\MCP\McpTool;
use Synapse\MCP\McpResource;

final class ShopTools
{
    #[McpTool(description: '查询订单状态')]
    public function queryOrder(string $orderId): string
    {
        $order = Order::find($orderId);
        return json_encode([
            'id' => $order->id,
            'status' => $order->status,
            'items' => $order->items->toArray(),
        ]);
    }

    #[McpTool(description: '搜索商品')]
    public function searchProducts(string $keyword, int $limit = 10): string
    {
        $products = Product::where('name', 'like', "%{$keyword}%")->limit($limit)->get();
        return json_encode($products->toArray());
    }
}

#[McpResource(uri: 'shop://stats/daily', description: '今日销售统计')]
final class DailyStatsResource
{
    public function read(): string
    {
        return json_encode([
            'orders' => Order::whereToday()->count(),
            'revenue' => Order::whereToday()->sum('amount'),
        ]);
    }
}

McpServer::create('shopxo-mcp', '1.0.0')
    ->addTool(new ShopTools())
    ->addResource(new DailyStatsResource())
    ->serveStdio();
```

---

### 3.6 RAG — 检索增强生成

**职责**: 完整的 RAG 管线：文档加载 → 切分 → Embedding → 向量存储 → 检索 → 增强生成。

```php
<?php

namespace Synapse\RAG;

// 文档加载器接口
interface LoaderInterface
{
    public function load(string $source): array; // 返回 Document[]
}

// 内置加载器
final class PdfLoader implements LoaderInterface { /* ... */ }
final class MarkdownLoader implements LoaderInterface { /* ... */ }
final class DirectoryLoader implements LoaderInterface { /* ... */ }

// 文本切分器
interface SplitterInterface
{
    public function split(Document $document): array; // 返回 Chunk[]
}

final class RecursiveCharacterSplitter implements SplitterInterface
{
    public function __construct(
        private int $chunkSize = 1000,
        private int $overlap = 200,
    ) {}
}

// Embedding 接口
interface EmbeddingInterface
{
    public function embed(string $text): array; // 返回 float[]
    public function embedBatch(array $texts): array; // 返回 float[][]
}

// 向量存储接口
interface VectorStoreInterface
{
    public function upsert(array $chunks): void;
    public function search(array $embedding, int $topK = 5): array;
    public function delete(string $id): void;
}

// 内置存储
final class PgVectorStore implements VectorStoreInterface { /* ... */ }
final class QdrantStore implements VectorStoreInterface { /* ... */ }
final class InMemoryStore implements VectorStoreInterface { /* 开发测试用 */ }

// RAG Pipeline
final class RagPipeline
{
    public function __construct(
        private LoaderInterface $loader,
        private SplitterInterface $splitter,
        private EmbeddingInterface $embedding,
        private VectorStoreInterface $store,
    ) {}

    /** 索引文档 */
    public function index(string $source): int { /* 返回索引的 chunk 数 */ }

    /** 检索并生成 */
    public function query(string $question, ChatInterface $chat, int $topK = 5): string
    {
        $queryEmbedding = $this->embedding->embed($question);
        $chunks = $this->store->search($queryEmbedding, $topK);
        $context = implode("\n\n", array_map(fn($c) => $c->content, $chunks));

        return $chat->send([
            Message::system("根据以下资料回答问题:\n{$context}"),
            Message::user($question),
        ])->content;
    }
}
```

---

### 3.7 Prompt — 模板引擎

**职责**: Prompt 模板管理，支持变量替换、条件逻辑、链式组合。

```php
<?php

namespace Synapse\Prompt;

final class Template
{
    public static function from(string $template): self { return new self($template); }
    public static function load(string $path): self { /* 从文件加载 */ }

    public function with(string $key, mixed $value): self { /* ... */ }
    public function withIf(bool $condition, string $key, mixed $value): self { /* ... */ }

    public function render(): string { /* 渲染模板 */ }
}

// 链式 Prompt
final class Chain
{
    public static function of(Template ...$templates): self { /* ... */ }
    public function pipe(callable $transform): self { /* ... */ }
    public function render(): string { /* ... */ }
}
```

**使用示例:**

```php
$prompt = Template::from('你是一个 {{role}}，请用 {{language}} 回答。\n\n{{#if context}}参考资料：{{context}}{{/if}}\n\n问题：{{question}}')
    ->with('role', '资深 PHP 架构师')
    ->with('language', '中文')
    ->withIf($hasContext, 'context', $contextText)
    ->with('question', '如何设计一个 MCP Server？');
```

---

### 3.8 Observability — 可观测性

**职责**: 自动追踪每次 LLM 调用的 token 消耗、延迟、成本，支持导出到 OpenTelemetry。

```php
<?php

namespace Synapse\Observability;

final readonly class Span
{
    public function __construct(
        public string $id,
        public string $provider,
        public string $model,
        public float $duration,   // 秒
        public int $promptTokens,
        public int $completionTokens,
        public float $cost,       // USD
        public \DateTimeImmutable $timestamp,
        public ?string $parentId = null,
    ) {}
}

interface ExporterInterface
{
    public function export(Span $span): void;
}

final class OtelExporter implements ExporterInterface { /* OpenTelemetry OTLP */ }
final class LogExporter implements ExporterInterface { /* PSR-3 Logger */ }
final class InMemoryExporter implements ExporterInterface { /* 测试用 */ }

final class CostCalculator
{
    // 内置各模型定价
    private const PRICING = [
        'gpt-4o' => ['input' => 2.5, 'output' => 10.0], // per 1M tokens
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.6],
        'claude-sonnet-4-20250514' => ['input' => 3.0, 'output' => 15.0],
        'deepseek-chat' => ['input' => 0.14, 'output' => 0.28],
    ];

    public function calculate(string $model, int $promptTokens, int $completionTokens): float
    {
        $pricing = self::PRICING[$model] ?? ['input' => 0, 'output' => 0];
        return ($promptTokens * $pricing['input'] + $completionTokens * $pricing['output']) / 1_000_000;
    }
}
```

---

## 4. 目录结构

```
synapse/
├── src/
│   ├── Chat/
│   │   ├── ChatInterface.php
│   │   ├── Response.php
│   │   ├── StreamResponse.php
│   │   ├── Usage.php
│   │   ├── Message/
│   │   │   ├── Message.php
│   │   │   └── Role.php
│   │   └── Provider/
│   │       ├── OpenAI.php
│   │       ├── Anthropic.php
│   │       ├── DeepSeek.php
│   │       ├── Ollama.php
│   │       └── Azure.php
│   ├── StructuredOutput/
│   │   ├── AsOutput.php            # Attribute
│   │   ├── Param.php               # Attribute
│   │   ├── SchemaExtractor.php
│   │   └── Deserializer.php
│   ├── Tools/
│   │   ├── AsTool.php              # Attribute
│   │   ├── Param.php               # Attribute
│   │   ├── ToolRegistry.php
│   │   └── ToolExecutor.php
│   ├── Agent/
│   │   ├── Agent.php
│   │   ├── AgentResponse.php
│   │   ├── Memory/
│   │   │   ├── MemoryInterface.php
│   │   │   ├── BufferMemory.php
│   │   │   ├── SummaryMemory.php
│   │   │   └── RedisMemory.php
│   │   └── Middleware/
│   │       ├── MiddlewareInterface.php
│   │       ├── CostTracker.php
│   │       ├── RateLimiter.php
│   │       └── Logger.php
│   ├── MCP/
│   │   ├── McpTool.php             # Attribute
│   │   ├── McpResource.php         # Attribute
│   │   ├── McpServer.php
│   │   ├── McpClient.php
│   │   └── Transport/
│   │       ├── TransportInterface.php
│   │       ├── StdioTransport.php
│   │       └── HttpSseTransport.php
│   ├── RAG/
│   │   ├── RagPipeline.php
│   │   ├── Document.php
│   │   ├── Chunk.php
│   │   ├── Loader/
│   │   │   ├── LoaderInterface.php
│   │   │   ├── PdfLoader.php
│   │   │   ├── MarkdownLoader.php
│   │   │   └── DirectoryLoader.php
│   │   ├── Splitter/
│   │   │   ├── SplitterInterface.php
│   │   │   └── RecursiveCharacterSplitter.php
│   │   ├── Embedding/
│   │   │   ├── EmbeddingInterface.php
│   │   │   └── OpenAIEmbedding.php
│   │   └── VectorStore/
│   │       ├── VectorStoreInterface.php
│   │       ├── PgVectorStore.php
│   │       ├── QdrantStore.php
│   │       └── InMemoryStore.php
│   ├── Prompt/
│   │   ├── Template.php
│   │   └── Chain.php
│   └── Observability/
│       ├── Span.php
│       ├── Tracer.php
│       ├── CostCalculator.php
│       └── Exporter/
│           ├── ExporterInterface.php
│           ├── OtelExporter.php
│           ├── LogExporter.php
│           └── InMemoryExporter.php
├── tests/
│   ├── Unit/
│   └── Integration/
├── examples/
│   ├── 01-simple-chat.php
│   ├── 02-structured-output.php
│   ├── 03-agent-with-tools.php
│   ├── 04-mcp-server.php
│   └── 05-rag-pipeline.php
├── composer.json
├── phpunit.xml.dist
├── phpstan.neon.dist
├── README.md
└── LICENSE
```

---

## 5. 依赖关系

```json
{
    "name": "synapse/synapse",
    "description": "Attribute-driven, type-safe PHP AI framework with MCP support",
    "require": {
        "php": "^8.3",
        "guzzlehttp/guzzle": "^7.9",
        "psr/log": "^3.0",
        "psr/http-message": "^2.0",
        "psr/event-dispatcher": "^1.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0",
        "phpstan/phpstan": "^1.12",
        "friendsofphp/php-cs-fixer": "^3.64"
    },
    "suggest": {
        "open-telemetry/sdk": "For OpenTelemetry observability export",
        "smalot/pdfparser": "For PDF document loading in RAG",
        "predis/predis": "For Redis-backed memory",
        "ext-pgsql": "For PgVector store"
    },
    "autoload": {
        "psr-4": {
            "Synapse\\": "src/"
        }
    }
}
```

---

## 6. MVP 路线图

### Phase 1 — 基础能力（Week 1-3）

| 任务 | 说明 |
|------|------|
| Chat 模块 | OpenAI + Anthropic + Ollama Provider |
| StructuredOutput | Attribute 定义 + Schema 提取 + 反序列化 |
| Tools | Attribute 注册 + 自动 Schema 生成 + 执行器 |
| Prompt | 基础模板引擎 |
| Observability | InMemory + Log Exporter |
| CI | GitHub Actions + PHPUnit + PHPStan |

### Phase 2 — 智能体 + MCP（Week 4-6）

| 任务 | 说明 |
|------|------|
| Agent | ReAct 循环 + BufferMemory + 中间件管道 |
| MCP Server | Stdio 传输 + Tool/Resource 注册 |
| MCP Client | 连接外部 MCP Server |
| 更多 Provider | DeepSeek + Azure + 自定义 |
| OtelExporter | OpenTelemetry OTLP 导出 |

### Phase 3 — RAG + 生态（Week 7-9）

| 任务 | 说明 |
|------|------|
| RAG Pipeline | 全流程: Loader → Splitter → Embedding → Store → Query |
| SummaryMemory | LLM 驱动的记忆摘要 |
| HTTP SSE Transport | MCP 的 HTTP 传输模式 |
| Laravel 集成包 | synapse/laravel ServiceProvider |
| 文档站 | ReadTheDocs 或 VitePress |

---

## 7. 完整使用示例

### 示例 1: 对话 + 结构化输出

```php
<?php

require 'vendor/autoload.php';

use Synapse\Chat\Provider\OpenAI;
use Synapse\Chat\Message\Message;
use Synapse\StructuredOutput\AsOutput;
use Synapse\StructuredOutput\Param;

#[AsOutput(description: '从文本中提取的联系人信息')]
class Contact
{
    #[Param(description: '姓名')]
    public string $name;

    #[Param(description: '邮箱地址')]
    public string $email;

    #[Param(description: '电话号码', required: false)]
    public ?string $phone = null;

    #[Param(description: '公司名称', required: false)]
    public ?string $company = null;
}

$chat = new OpenAI(apiKey: $_ENV['OPENAI_API_KEY']);

$contact = $chat->sendStructured(
    messages: [
        Message::user('提取联系人: 张三，来自腾讯，邮箱 zhangsan@tencent.com，手机 138-0000-1234'),
    ],
    outputClass: Contact::class,
);

echo $contact->name;    // "张三"
echo $contact->email;   // "zhangsan@tencent.com"
echo $contact->company; // "腾讯"
```

### 示例 2: Agent + 工具调用

```php
<?php

use Synapse\Agent\Agent;
use Synapse\Chat\Provider\OpenAI;
use Synapse\Tools\AsTool;
use Synapse\Tools\Param;

class ProductTool
{
    #[AsTool(description: '根据关键词搜索商品')]
    public function search(
        #[Param(description: '搜索关键词')] string $keyword,
        #[Param(description: '价格上限（元）')] ?float $maxPrice = null,
    ): string {
        // 模拟数据库查询
        return json_encode([
            ['name' => 'iPhone 16', 'price' => 5999],
            ['name' => 'iPhone 16 Pro', 'price' => 7999],
        ]);
    }

    #[AsTool(description: '获取商品详情')]
    public function getDetail(
        #[Param(description: '商品ID')] int $productId,
    ): string {
        return json_encode(['id' => $productId, 'name' => 'iPhone 16', 'stock' => 42]);
    }
}

$agent = Agent::create()
    ->provider(new OpenAI(apiKey: $_ENV['OPENAI_API_KEY'], model: 'gpt-4o'))
    ->system('你是一个电商助手，帮用户查找和推荐商品。')
    ->tools([new ProductTool()]);

$response = $agent->run('帮我找一个 6000 元以内的手机');

echo $response->content;
// "我为您找到了以下 6000 元以内的手机：
//  1. iPhone 16 - ¥5999
//  推荐这款，性价比很高！"
```

### 示例 3: MCP Server 暴露 PHP 应用

```php
<?php
// bin/mcp-server.php
// 用法: 在 Claude Desktop 配置中添加此命令

require __DIR__ . '/../vendor/autoload.php';

use Synapse\MCP\McpServer;
use Synapse\MCP\McpTool;
use Synapse\MCP\McpResource;

class DatabaseTool
{
    #[McpTool(description: '执行只读 SQL 查询')]
    public function query(string $sql): string
    {
        if (!str_starts_with(strtoupper(trim($sql)), 'SELECT')) {
            return json_encode(['error' => '只允许 SELECT 查询']);
        }
        $result = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        return json_encode($result);
    }
}

#[McpResource(uri: 'app://schema/tables', description: '数据库表结构')]
class SchemaResource
{
    public function read(): string
    {
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        return json_encode($tables);
    }
}

McpServer::create('my-app-mcp')
    ->addTool(new DatabaseTool())
    ->addResource(new SchemaResource())
    ->serveStdio();
```

Claude Desktop `claude_desktop_config.json`:
```json
{
  "mcpServers": {
    "my-app": {
      "command": "php",
      "args": ["/path/to/bin/mcp-server.php"]
    }
  }
}
```

---

## 8. 后续扩展方向

- **Multi-Agent**: Agent 之间的协作编排（类似 CrewAI）
- **Evaluation**: 内置 AI 输出质量评估框架
- **Fine-tuning Pipeline**: 数据准备 → 微调 → 部署的管线
- **Plugin System**: 社区贡献的 Provider/Tool/VectorStore 插件
