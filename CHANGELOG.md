# 更新日志

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
