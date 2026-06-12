# Synapse Laravel

Laravel integration for the [Synapse](https://github.com/synapse-php/synapse) PHP AI framework.

## Installation

```bash
composer require synapse-php/laravel
```

The service provider is auto-discovered. Publish the config:

```bash
php artisan vendor:publish --tag=synapse-config
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
use Synapse\Chat\ChatInterface;
use Synapse\Chat\Message;

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
use Synapse\Laravel\Facades\Synapse;
use Synapse\Chat\Message;

$response = Synapse::send([Message::user('Hi')]);
```

### SSE Streaming

```php
use Synapse\Chat\ChatInterface;
use Synapse\Chat\Message;
use Synapse\Laravel\Http\SseStream;

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
