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
- `Chat::structured()` 新增 `$options` 参数（可传 temperature/model 等）

### 生产加固

- **RetryHandler** — 指数退避重试（支持 Retry-After 头、429/5xx 自动重试）
- **RateLimiter** — 令牌桶限流（`wait()` 阻塞 / `tryAcquire()` 非阻塞）
- **StreamableInterface** — 四个 Provider 统一流式接口
- Anthropic streamRaw：HTTP 状态码检查 + error 事件抛异常
- Team：clone Agent 防止共享状态突变
- Tracer：maxSpans 上限防内存泄漏
- CostCalculator：改为可扩展（`addModel()` 运行时注册）
- MCP Server：工具异常返回 `isError: true` 符合协议规范
- ProcessTransport：`stream_set_timeout` + `fwrite` 返回值检查 + double-close 防护
- Agent：sanitizeMessages 修复多 tool 序列丢失、memory save 失败记日志
- ToolRegistry：重名工具抛异常、缺少必需参数返回错误
- RecursiveCharacterSplitter：校验 overlap < chunkSize
- InMemoryStore：向量维度不匹配返回 0
- BufferMemory/SummaryMemory：构造函数参数校验
- Template：拒绝非标量变量、清除未替换占位符
- Examples：eval 白名单校验、SSE 输入长度限制、env 变量兜底
- SECURITY.md：漏洞报告流程 + API 稳定性声明

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
