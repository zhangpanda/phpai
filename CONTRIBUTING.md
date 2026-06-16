# 贡献指南

感谢你对 Synapse 的关注！欢迎通过以下方式参与贡献。

## 开发环境

```bash
git clone https://github.com/zhangpanda/synapse.git
cd synapse
composer install
```

## 运行测试

```bash
vendor/bin/phpunit        # 单元测试
vendor/bin/phpstan analyse # 静态分析
```

## 提交规范

- 分支命名：`feature/xxx`、`fix/xxx`
- Commit 格式：`feat: 新增 xxx` / `fix: 修复 xxx` / `docs: 更新文档`
- 每个 PR 应包含测试

## 添加新 Provider

1. 在 `src/Chat/Provider/` 下创建新类
2. 实现 `ChatInterface` 接口
3. 添加对应的单元测试（用 Guzzle MockHandler）
4. 在 README 的 Provider 表格中添加一行

## 添加新 Tool

利用 `#[AsTool]` Attribute 即可，无需修改框架核心代码。

## 代码风格

- `declare(strict_types=1)`
- `final` 优先（除非有明确的继承需求）
- `readonly` 优先（值对象和配置类）
- 方法参数和返回值必须有类型声明

## 问题反馈

- Bug 报告请使用 GitHub Issues
- 功能建议请使用 Discussions
