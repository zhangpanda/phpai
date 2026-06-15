# 更新日志

## v0.2.0 (2026-06-15)

### 新功能

**流式输出 Web 集成：**
- 新增 `StreamableInterface` 接口，规范 Provider 流式能力
- Anthropic Provider 新增 `streamRaw()` 支持（SSE `content_block_delta` 事件）
- 所有 4 个 Provider（OpenAI / Anthropic / DeepSeek / Ollama）均实现 StreamableInterface
- 新增 `SseWriter` — 框架无关的纯 PHP SSE helper
- `Chat::stream()` 改为基于接口检测，不再硬编码 Provider 判断

**Multi-Agent 协作：**
- 新增 `Team` 协调器，支持两种模式：
  - Pipeline — 链式顺序执行，上一个 Agent 输出作为下一个输入
  - Router — orchestrator Agent 决定委派给哪个 specialist
- 新增 `TeamStep` / `TeamResult` 值对象

### 改进

- `Chat::stream()` 不再要求传递 `stream => true` option（自动处理）

## v0.1.0 (2026-06-10)

### 首个版本

**Chat 模块：**
- 统一 ChatInterface 接口
- OpenAI Provider（含流式输出）
- Anthropic Claude Provider
- DeepSeek Provider（国内直连）
- Message / Response / Usage 值对象

**结构化输出：**
- `#[AsOutput]` / `#[Param]` Attribute
- SchemaExtractor（Attribute → JSON Schema）
- Deserializer（JSON → PHP 对象）
- `Chat::structured()` 便捷 API

**工具系统：**
- `#[AsTool]` / `#[Param]` Attribute
- ToolRegistry（自动注册 + Schema 生成）

**Agent：**
- ReAct 模式智能体
- BufferMemory
- Middleware 管道（CostTracker / Logger）

**MCP 协议：**
- McpServer（Stdio 传输，支持 tools/resources）
- McpClient（Process 传输）
- `#[McpTool]` / `#[McpResource]` Attribute

**RAG：**
- TextFileLoader
- RecursiveCharacterSplitter
- OpenAIEmbedding
- InMemoryStore（余弦相似度）
- RagPipeline（索引 + 查询）

**Prompt：**
- Template 模板引擎（变量 + 条件）

**可观测性：**
- Span / Tracer
- CostCalculator（内置多模型定价）
