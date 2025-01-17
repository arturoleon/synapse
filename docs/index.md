# 🧠 Synapse

## AI Agents for All!

Synapse allows you to easily create and manage AI agents in your Laravel application. Inspired by Langchain and Saloon, this package simplifies AI integration and enables scalability.

## Installation

> **Requires [PHP 8.1+](https://php.net/releases/)**

Install via [Composer](https://getcomposer.org/):

```bash
composer require use-the-fork/synapse
```

Then, run the installation command:

```bash
php artisan synapse:install
```

If you're not using `DatabaseMemory`, there's no need to publish the migrations.

## Get Started

### 1. Set up your `.env` file with the following settings (omit any you don't need):

```dotenv
OPENAI_API_KEY=
OPENAI_API_CHAT_MODEL=gpt-4-turbo
OPENAI_API_EMBEDDING_MODEL=text-embedding-ada-002

ANTHROPIC_API_KEY=
ANTHROPIC_API_CHAT_MODEL=claude-3-5-sonnet-20240620

OLLAMA_BASE_URL=https://foo.bar:1234
OLLAMA_API_CHAT_MODEL=llama3.2
OLLAMA_API_EMBEDDING_MODEL=llama3.2

SERPAPI_API_KEY=
SERPER_API_KEY=
CLEARBIT_API_KEY=
CRUNCHBASE_API_KEY=
FIRECRAWL_API_KEY=
```

### 2. Add the service provider if packages aren't autoloaded:

For **Laravel 10**:

```php
//config/app.php
'providers' => [
    ...
    UseTheFork\Synapse\SynapseServiceProvider::class, // [!code highlight]
    ...
];
```

For **Laravel 11**:

```php
//bootstrap/providers.php
<?php
return [
    App\Providers\AppServiceProvider::class,
    UseTheFork\Synapse\SynapseServiceProvider::class, // [!code highlight]
];
```

### 3. If you are not using OpenAI as your default integration:
Open the `config/synapse.php` file and modify the default integration to the one you prefer:

```php
    'integrations' => [
        'default' => UseTheFork\Synapse\Integrations\OpenAIIntegration::class, // [!code highlight]
        'openai' => [
            'key' => env('OPENAI_API_KEY'),
            'chat_model' => env('OPENAI_API_CHAT_MODEL', 'gpt-4-turbo'),
            'embedding_model' => env('OPENAI_API_EMBEDDING_MODEL', 'text-embedding-ada-002'),
        ],
        'claude' => [
            'key' => env('ANTHROPIC_API_KEY'),
            'chat_model' => env('ANTHROPIC_API_CHAT_MODEL', 'claude-3-5-sonnet-20240620'),
        ],
        'ollama' => [
            'base_url' => env('OLLAMA_BASE_URL'),
            'chat_model' => env('OLLAMA_API_CHAT_MODEL', 'llama3.2'),
            'embedding_model' => env('OLLAMA_API_CHAT_MODEL', 'llama3.2'),
        ],
    ],
```

You're now ready to use Synapse.
