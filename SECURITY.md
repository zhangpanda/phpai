# Security Policy

## Supported Versions

| Version | Supported          |
|---------|--------------------|
| 0.2.x   | ✅ Current          |
| < 0.2   | ❌ No longer supported |

## Reporting a Vulnerability

If you discover a security vulnerability, please report it responsibly:

1. **Do NOT** open a public GitHub issue
2. Email: security@synapse-php.dev (or use GitHub Security Advisories)
3. Include: description, reproduction steps, and potential impact
4. We will acknowledge within 48 hours and provide a fix timeline within 7 days

## Security Considerations

### API Keys
- Never commit API keys to version control
- Use environment variables or `.env` files (excluded from git)
- Synapse does not log or store API keys beyond the request lifecycle

### SSE Streaming (SseWriter)
- Add authentication middleware before exposing SSE endpoints
- Set appropriate CORS headers for production
- Consider rate limiting streaming endpoints

### MCP Server
- MCP Stdio transport runs with the same permissions as the PHP process
- Validate and sanitize all tool inputs in your tool implementations
- Do not expose MCP servers to untrusted networks

### Data Handling
- Conversation data (Memory) is stored in-process by default
- If persisting conversations, encrypt sensitive data at rest
- Token usage data does not contain message content

## API Stability

This project follows [Semantic Versioning](https://semver.org/).

### Current Status: **Pre-1.0 (Unstable)**

Before v1.0, minor versions (0.x → 0.y) may include breaking changes. We document all breaking changes in CHANGELOG.md.

### Stability Guarantees

| Component | Stability |
|-----------|-----------|
| `ChatInterface` | 🟡 Stable (no planned changes) |
| `StreamableInterface` | 🟡 Stable |
| `Agent::create()` API | 🟡 Stable |
| `Team` (Multi-Agent) | 🟠 Experimental |
| `SseWriter` | 🟡 Stable |
| `RetryHandler` / `RateLimiter` | 🟡 Stable |
| MCP Server/Client | 🟠 Experimental |
| RAG Pipeline | 🟠 Experimental |
| Laravel Integration | 🟠 Experimental |

### Migration Guide

Breaking changes will include migration instructions in CHANGELOG.md. We aim to provide at least one minor version deprecation notice before removing any public API.
