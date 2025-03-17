# 3CPlus Auth Middleware

A PHP middleware for authentication using API tokens, with Redis caching support.

## Installation

```bash
composer require 3cplus/auth-middleware
```

## Description

This middleware authenticates requests by validating API tokens against a remote authentication service. It supports token extraction from both query parameters and Authorization headers. For performance optimization, authenticated user data is cached in Redis.

## Requirements

- PHP 7.2 or higher
- Laravel/Illuminate Support
- GuzzleHTTP
- Predis

## Environment Variables

The following environment variables are **required**:

| Variable | Description |
|----------|-------------|
| `URL_APPLICATION_API` | URL of the authentication API endpoint |
| `REDIS_CACHE_HOST` | Redis server hostname |
| `REDIS_CACHE_PORT` | Redis server port |

## Usage

### Basic Implementation

```php
use Dev3CPlus\Middleware\AuthMiddleware;

// Create middleware instance
$authMiddleware = new AuthMiddleware();

// Process the request
$response = $authMiddleware->process($request, $handler);
```

### Token Extraction

The middleware extracts the API token in the following order:
1. From query parameter: `?api_token=your-token`
2. From Authorization header: `Authorization: Bearer your-token`

### Error Handling

The middleware throws exceptions with appropriate HTTP status codes:
- `401 Unauthorized`: When the API token is missing or invalid
- `500 Internal Server Error`: For other processing errors

### Caching

Successfully authenticated user data is cached in Redis for 1 hour (3600 seconds) to minimize API calls.

## License

[MIT](LICENSE.md)