# Synapse 使用文档

## Chat — 多 Provider 对话

```php
use Synapse\Chat\Provider\{OpenAI, Anthropic, DeepSeek, Ollama};
use Synapse\Chat\Message;

// OpenAI
$chat = new OpenAI(apiKey: 'sk-xxx', model: 'gpt-4o');

// Anthropic Claude
$chat = new Anthropic(apiKey: 'sk-ant-xxx', model: 'claude-sonnet-4-20250514');

// DeepSeek（国内直连）
$chat = new DeepSeek(apiKey: 'sk-xxx');

// Ollama（本地）
$chat = new Ollama(model: 'llama3');

// 发送消息
$response = $chat->send([
    Message::system('你是助手'),
    Message::user('你好'),
]);

echo $response->content;
echo $response->usage->totalTokens;
```

## StructuredOutput — 类型化输出

```php
use Synapse\StructuredOutput\{AsOutput, Param};
use Synapse\Chat\Chat;

#[AsOutput(description: '商品信息')]
class Product
{
    #[Param(description: '名称')]
    public string $name;

    #[Param(description: '价格')]
    public float $price;
}

$product = Chat::structured($chat, [
    Message::user('iPhone 16 128G 5999元'),
], Product::class);

// $product->name === 'iPhone 16 128G'
// $product->price === 5999.0
```

## Tools — 工具定义

```php
use Synapse\Tools\{AsTool, Param};

class MyTool
{
    #[AsTool(description: '查询天气')]
    public function weather(
        #[Param(description: '城市')] string $city,
    ): string {
        return json_encode(['temp' => 25, 'city' => $city]);
    }
}
```

## Agent — 智能体

```php
use Synapse\Agent\Agent;
use Synapse\Agent\Memory\{BufferMemory, SummaryMemory};
use Synapse\Agent\Middleware\{CostTracker, Logger};

$agent = Agent::create()
    ->provider($chat)
    ->system('你是客服助手')
    ->tools([new MyTool()])
    ->memory(new BufferMemory(maxMessages: 30))
    ->middleware([new CostTracker()])
    ->maxIterations(10);

$response = $agent->run('北京天气怎么样？');
echo $response->content;
echo count($response->steps); // 工具调用步骤数
```

### Memory 策略

| 策略 | 说明 |
|------|------|
| `BufferMemory` | 保留最近 N 条消息 |
| `SummaryMemory` | 超过阈值时用 LLM 摘要旧消息 |

## MCP — Model Context Protocol

### 创建 MCP Server

```php
use Synapse\MCP\{McpServer, McpTool, McpResource};

class Tools {
    #[McpTool(description: '查数据库')]
    public function query(string $sql): string { /* ... */ }
}

#[McpResource(uri: 'app://status', description: '应用状态')]
class Status {
    public function read(): string { return '{"ok":true}'; }
}

McpServer::create('my-app')
    ->addTool(new Tools())
    ->addResource(new Status())
    ->serveStdio();
```

### 连接 MCP Server

```php
use Synapse\MCP\McpClient;

$client = McpClient::connectStdio('php', ['server.php']);
$client->initialize();

$tools = $client->listTools();
$result = $client->callTool('query', ['sql' => 'SELECT 1']);

$client->close();
```

## RAG — 检索增强生成

```php
use Synapse\RAG\{RagPipeline, TextFileLoader, RecursiveCharacterSplitter, OpenAIEmbedding, InMemoryStore};

$rag = new RagPipeline(
    loader: new TextFileLoader(),
    splitter: new RecursiveCharacterSplitter(chunkSize: 500, overlap: 50),
    embedding: new OpenAIEmbedding(apiKey: 'sk-xxx'),
    store: new InMemoryStore(),
);

// 索引
$rag->index('/path/to/docs/');

// 查询
$answer = $rag->query('如何配置？', $chat);
```

## Prompt — 模板

```php
use Synapse\Prompt\Template;

$prompt = Template::from('你是{{role}}，用{{lang}}回答：{{question}}')
    ->with('role', 'PHP 专家')
    ->with('lang', '中文')
    ->with('question', '什么是 DI？')
    ->render();
```

条件语法：
```
{{#if context}}参考：{{context}}{{/if}}
```

## Observability — 可观测性

```php
use Synapse\Observability\{Tracer, CostCalculator};
use Synapse\Observability\Exporter\{OtelExporter, LogExporter};

// 手动追踪
$tracer = new Tracer();
$span = $tracer->record('openai', 'gpt-4o', 1.2, 100, 50);
echo $tracer->getTotalCost(); // USD

// 费用计算
$calc = new CostCalculator();
echo $calc->calculate('gpt-4o', 1000, 500); // 0.0075 USD

// 导出到 OpenTelemetry
$exporter = new OtelExporter(endpoint: 'http://localhost:4318/v1/traces');
$exporter->export($span);
$exporter->flush();
```

## Streaming — 流式输出

所有 Provider 均支持流式输出（实现 `StreamableInterface`）。

### 纯 PHP（无框架）

```php
use Synapse\Chat\{SseWriter, Message};
use Synapse\Chat\Provider\DeepSeek;

$chat = new DeepSeek(apiKey: $_ENV['DEEPSEEK_API_KEY']);

// 一行代码：发送 SSE headers + 流式输出 + 关闭
SseWriter::stream($chat, [
    Message::user('介绍 PHP 8.3'),
]);
```

### 手动控制

```php
use Synapse\Chat\{Chat, Message, SseWriter};

SseWriter::start(); // 发送 SSE headers

$stream = Chat::stream($chat, [Message::user('Hello')]);
foreach ($stream as $chunk) {
    SseWriter::event($chunk);
}

SseWriter::done();
```

### Laravel 集成

```php
use Synapse\Laravel\Http\SseStream;
use Synapse\Chat\{ChatInterface, Message};

Route::get('/chat/stream', function (ChatInterface $chat) {
    return SseStream::response($chat, [
        Message::user(request('q')),
    ]);
});
```

### 前端 JavaScript

```javascript
const source = new EventSource('/chat/stream?q=Hello');
source.onmessage = (e) => {
    if (e.data === '[DONE]') { source.close(); return; }
    const { content } = JSON.parse(e.data);
    document.getElementById('output').textContent += content;
};
```

## Multi-Agent — 多智能体协作

`Team` 协调器支持两种模式：

### Pipeline 模式（链式）

多个 Agent 按顺序执行，每个 Agent 接收上一个的输出：

```php
use Synapse\Agent\{Agent, Team};

$team = Team::create()
    ->add('researcher', Agent::create()
        ->provider($chat)
        ->system('你是调研专家，给出关键事实')
        ->maxIterations(3))
    ->add('writer', Agent::create()
        ->provider($chat)
        ->system('根据调研内容写 200 字介绍')
        ->maxIterations(3));

$result = $team->pipeline('PHP 8.3 新特性');
echo $result->content;          // writer 的最终输出
echo count($result->steps);     // 2 步
```

可指定执行顺序：
```php
$result = $team->pipeline('input', ['writer', 'researcher']); // 自定义顺序
```

### Router 模式（路由分发）

一个 orchestrator Agent 根据用户输入选择最合适的 specialist：

```php
$team = Team::create()
    ->add('coder', Agent::create()
        ->provider($chat)->system('你是编程专家')->maxIterations(3))
    ->add('explainer', Agent::create()
        ->provider($chat)->system('你是技术讲师')->maxIterations(3))
    ->router(Agent::create()
        ->provider($chat)
        ->system('选择最合适的专家，只回复名称：coder 或 explainer')
        ->maxIterations(1));

$result = $team->route('写一个单例模式的例子');
echo $result->steps[0]->agent; // "coder"
echo $result->content;         // 代码示例
```

### TeamResult 结构

```php
$result->content;              // 最终输出文本
$result->steps;                // list<TeamStep>
$result->steps[0]->agent;     // Agent 名称
$result->steps[0]->input;     // 该 Agent 收到的输入
$result->steps[0]->response;  // AgentResponse
```

## RetryHandler — 自动重试

所有 Provider 已内置重试（默认 3 次），无需手动配置。自定义参数：

```php
use Synapse\Chat\Provider\OpenAI;
use Synapse\Chat\RetryHandler;

$chat = new OpenAI(
    apiKey: 'sk-xxx',
    retry: new RetryHandler(
        maxRetries: 5,        // 最多重试 5 次
        baseDelayMs: 1000,    // 首次重试延迟 1s
        multiplier: 2.0,      // 指数退避倍数
        maxDelayMs: 60000,    // 最大延迟 60s
    ),
);
```

自动重试的场景：
- HTTP 429（限流）— 尊重 `Retry-After` 头
- HTTP 500/502/503（服务器错误）
- 网络连接失败

不重试的场景：
- HTTP 401/403（认证失败）
- HTTP 400（请求格式错误）

## RateLimiter — 请求限流

控制对 API 的请求频率，避免触发限流：

```php
use Synapse\Chat\RateLimiter;

$limiter = new RateLimiter(maxRequests: 60, perSeconds: 60); // 60 rpm

// 方式 1：阻塞等待直到有配额
$limiter->wait();
$chat->send($messages);

// 方式 2：非阻塞尝试
if ($limiter->tryAcquire()) {
    $chat->send($messages);
} else {
    // 无配额，稍后重试或返回错误
}

// 查看剩余配额
echo $limiter->remaining(); // 还能发几个请求
```
