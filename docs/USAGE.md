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
