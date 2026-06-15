# Synapse — PHP AI 框架

> Attribute 驱动、类型安全、可观测的 PHP AI 应用开发框架

[![PHP](https://img.shields.io/badge/PHP-8.3+-blue.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-Apache%202.0-green.svg)](LICENSE)

## 为什么选择 Synapse？

PHP 生态在 AI 应用开发上严重落后于 Python（LangChain）和 JavaScript（Vercel AI SDK）。Synapse 让 PHP 开发者用最熟悉的方式构建 AI 应用：

- **Attribute 零配置** — 用 `#[AsTool]`、`#[AsOutput]` 声明一切，无需 YAML/XML
- **类型安全** — LLM 返回的 JSON 自动映射为 PHP 强类型对象
- **多模型支持** — OpenAI、Anthropic Claude、DeepSeek 一行切换
- **MCP 原生** — 把你的 PHP 应用直接暴露为 MCP Server（Claude Desktop 可调用）
- **可观测** — 自动追踪每次调用的 token 消耗和费用

## 安装

```bash
composer require synapse-php/synapse
```

## 快速上手

### 基础对话

```php
use Synapse\Chat\Provider\OpenAI;
use Synapse\Chat\Message;

$chat = new OpenAI(apiKey: $_ENV['OPENAI_API_KEY']);

$response = $chat->send([
    Message::system('你是一个 PHP 专家'),
    Message::user('PHP 8.3 最重要的新特性是什么？'),
]);

echo $response->content;
echo "消耗 {$response->usage->totalTokens} tokens";
```

### 使用 DeepSeek（国内推荐）

```php
use Synapse\Chat\Provider\DeepSeek;

$chat = new DeepSeek(apiKey: $_ENV['DEEPSEEK_API_KEY']);

$response = $chat->send([
    Message::user('用中文解释什么是 MCP 协议'),
]);
```

### 结构化输出

```php
use Synapse\StructuredOutput\AsOutput;
use Synapse\StructuredOutput\Param;
use Synapse\Chat\Chat;

#[AsOutput(description: '商品信息提取结果')]
class ProductInfo
{
    #[Param(description: '商品名称')]
    public string $name;

    #[Param(description: '价格（元）')]
    public float $price;

    #[Param(description: '商品分类', enum: ['电子', '服饰', '食品', '其他'])]
    public string $category;
}

// 自动返回强类型对象
$product = Chat::structured($chat, [
    Message::user('提取商品信息：iPhone 16 Pro 128G 国行 7999元'),
], ProductInfo::class);

echo $product->name;     // "iPhone 16 Pro 128G 国行"
echo $product->price;    // 7999.0
echo $product->category; // "电子"
```

### Agent + 工具调用

```php
use Synapse\Agent\Agent;
use Synapse\Tools\AsTool;
use Synapse\Tools\Param;

class OrderTool
{
    #[AsTool(description: '查询订单状态')]
    public function query(
        #[Param(description: '订单号')] string $orderId,
    ): string {
        // 查数据库
        return json_encode(['order_id' => $orderId, 'status' => '已发货']);
    }
}

$agent = Agent::create()
    ->provider(new DeepSeek(apiKey: $_ENV['DEEPSEEK_API_KEY']))
    ->system('你是电商客服助手')
    ->tools([new OrderTool()])
    ->maxIterations(5);

$response = $agent->run('帮我查一下订单 SH20240101001 的状态');
echo $response->content; // "您的订单 SH20240101001 已发货..."
```

### MCP Server（让 Claude Desktop 调用你的 PHP 应用）

```php
use Synapse\MCP\McpServer;
use Synapse\MCP\McpTool;

class MyTools
{
    #[McpTool(description: '查询数据库')]
    public function queryDB(string $sql): string
    {
        // 执行查询...
        return json_encode($result);
    }
}

McpServer::create('my-php-app')
    ->addTool(new MyTools())
    ->serveStdio();
```

在 Claude Desktop 的 `claude_desktop_config.json` 中配置：
```json
{
  "mcpServers": {
    "my-php-app": {
      "command": "php",
      "args": ["/path/to/mcp-server.php"]
    }
  }
}
```

### RAG（检索增强生成）

```php
use Synapse\RAG\RagPipeline;
use Synapse\RAG\TextFileLoader;
use Synapse\RAG\RecursiveCharacterSplitter;
use Synapse\RAG\OpenAIEmbedding;
use Synapse\RAG\InMemoryStore;

$rag = new RagPipeline(
    loader: new TextFileLoader(),
    splitter: new RecursiveCharacterSplitter(chunkSize: 500, overlap: 50),
    embedding: new OpenAIEmbedding(apiKey: $_ENV['OPENAI_API_KEY']),
    store: new InMemoryStore(),
);

// 索引文档
$rag->index('/path/to/docs/');

// 基于文档回答问题
$answer = $rag->query('如何配置数据库连接？', $chat);
```

## 模块总览

| 模块 | 说明 |
|------|------|
| `Chat` | 统一对话接口，支持 OpenAI / Anthropic / DeepSeek / Ollama |
| `StructuredOutput` | PHP Attribute 定义输出 Schema，自动映射为类型化对象 |
| `Tools` | `#[AsTool]` 零配置注册工具函数 |
| `Agent` | ReAct 模式智能体 + Memory + 中间件管道 |
| `MCP` | Model Context Protocol Server + Client |
| `RAG` | 文档加载 → 切分 → Embedding → 向量检索 → 增强生成 |
| `Prompt` | 模板引擎（变量替换 + 条件逻辑） |
| `Observability` | Token / 延迟 / 费用追踪 |

## 支持的模型

| Provider | 模型 | 说明 |
|----------|------|------|
| OpenAI | gpt-4o, gpt-4o-mini | 需翻墙或使用代理 |
| Anthropic | claude-sonnet-4-20250514 | 需翻墙或使用代理 |
| **DeepSeek** | deepseek-chat | 🇨🇳 国内直连，性价比高 |
| Ollama | 任意本地模型 | 本地部署，无需网络 |

> 💡 国内用户推荐使用 DeepSeek 或 Ollama，无需翻墙。

## 目录结构

```
src/
├── Chat/               # 对话接口 + 多 Provider
├── StructuredOutput/   # 结构化输出
├── Tools/              # 工具系统
├── Agent/              # 智能体 + Memory + Middleware
├── MCP/                # MCP 协议
├── RAG/                # 检索增强生成
├── Prompt/             # 模板引擎
└── Observability/      # 可观测性
```

## 开发

```bash
# 安装依赖
composer install

# 运行测试
vendor/bin/phpunit

# 静态分析
vendor/bin/phpstan analyse
```

## 路线图

- [x] Chat 多 Provider 支持
- [x] 结构化输出 (Attribute 驱动)
- [x] Tool 调用
- [x] Agent (ReAct)
- [x] MCP Server + Client
- [x] RAG Pipeline
- [x] Laravel 集成包
- [x] 流式输出 Web 集成
- [x] Multi-Agent 协作
- [ ] 向量数据库支持（PgVector、Qdrant）

## 参与贡献

欢迎提交 Issue 和 Pull Request。

## 开源协议

[Apache 2.0](LICENSE)
