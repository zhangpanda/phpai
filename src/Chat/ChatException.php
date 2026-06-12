<?php

declare(strict_types=1);

namespace Synapse\Chat;

/**
 * Thrown when an LLM API call fails.
 */
class ChatException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly ?string $provider = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public static function fromGuzzle(\GuzzleHttp\Exception\GuzzleException $e, string $provider): self
    {
        $statusCode = 0;
        $body = '';

        if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
            $statusCode = $e->getResponse()->getStatusCode();
            $body = $e->getResponse()->getBody()->getContents();
        }

        $message = match ($statusCode) {
            401 => "认证失败：API Key 无效或已过期",
            403 => "权限不足：无法访问该模型",
            429 => "请求过于频繁，请稍后重试",
            500, 502, 503 => "服务暂时不可用，请稍后重试",
            default => $e instanceof \GuzzleHttp\Exception\ConnectException
                ? "网络连接失败：无法连接到 {$provider} API"
                : "API 调用失败: {$e->getMessage()}",
        };

        // 尝试从响应体提取错误详情
        if ($body) {
            $data = json_decode($body, true);
            $detail = $data['error']['message'] ?? null;
            if ($detail) {
                $message .= " ({$detail})";
            }
        }

        return new self($message, $statusCode, $provider, $e);
    }
}
