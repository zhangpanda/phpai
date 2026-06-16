# PHPAI Laravel

Laravel integration for the [PHPAI](https://github.com/zhangpanda/phpai) PHP AI framework.

## Installation

在 Laravel 项目的 `composer.json` 中添加本地路径仓库：

```json
{
    "repositories": [
        {"type": "path", "url": "/path/to/phpai/packages/laravel"}
    ]
}
```

然后安装：
```bash
composer require zhangpanda/phpai-laravel:@dev
```

The service provider is auto-discovered. Publish the config:

```bash
php artisan vendor:publish --tag=phpai-config
```

## Configuration

Set your `.env`:

```env
SYNAPSE_PROVIDER=deepseek
DEEPSEEK_API_KEY=sk-xxx
```

## Usage

### Dependency Injection

```php
use PHPAI\Chat\ChatInterface;
use PHPAI\Chat\Message;

class ChatController extends Controller
{
    public function ask(ChatInterface $chat)
    {
        $response = $chat->send([
            Message::user('Hello!'),
        ]);

        return response()->json(['reply' => $response->content]);
    }
}
```

### Facade

```php
use PHPAI\Laravel\Facades\PHPAI;
use PHPAI\Chat\Message;

$response = PHPAI::send([Message::user('Hi')]);
```

### SSE Streaming

```php
use PHPAI\Chat\ChatInterface;
use PHPAI\Chat\Message;
use PHPAI\Laravel\Http\SseStream;

class StreamController extends Controller
{
    public function stream(ChatInterface $chat)
    {
        return SseStream::response($chat, [
            Message::system('You are a helpful assistant.'),
            Message::user(request('message')),
        ]);
    }
}
```

Frontend consumption:

```javascript
const source = new EventSource('/api/chat/stream?message=Hello');
source.onmessage = (event) => {
    if (event.data === '[DONE]') {
        source.close();
        return;
    }
    const { content } = JSON.parse(event.data);
    document.getElementById('output').textContent += content;
};
```
