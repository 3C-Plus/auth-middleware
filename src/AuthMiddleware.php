<?php

namespace Dev3CPlus\Middleware;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use GuzzleHttp\Client as GuzzleClient;
use Predis\Client as RedisClient;

class AuthMiddleware implements MiddlewareInterface
{
    public function __construct()
    {
        $this->loadDotEnv();
    }

    /**
     * @throws \Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $apiToken = $request->getQueryParams()['api_token']
            ?? Arr::last(
                explode(
                    ' ',
                    Arr::first($request->getHeader('Authorization') ?: [''])
                )
            );

        if (is_null($apiToken) || !env('URL_APPLICATION_API')) {
            throw new \Exception('Unauthorized', 401);
        }

        $redisClient = new RedisClient([
            'scheme' => 'tcp',
            'host' => env('REDIS_CACHE_HOST'),
            'port' => env('REDIS_CACHE_PORT'),
        ]);

        $cachedUserData = $this->searchUserInCache($redisClient, $apiToken);

        if ($cachedUserData) {
            $request = $request->withAttribute('auth_user', $cachedUserData);
            return $handler->handle($request);
        }

        try {
            $guzzleClient = new GuzzleClient();

            $response = $guzzleClient->request(
                'GET',
                env('URL_APPLICATION_API') . '/me?include=company%2Cpermissions%2Cteams.instances', [
                'headers' => [
                    'Authorization' => "Bearer $apiToken",
                    'Accept' => 'application/json,',
                    'Referer' => 'https://app.3c.plus/',
                ]
            ]);

            $data = json_decode($response->getBody()->getContents());

            $redisClient->set($this->getCacheKey($apiToken), json_encode($data->data), 3600);
            
            $request = $request->withAttribute('auth_user', json_encode($data->data));
            return $handler->handle($request);
        } catch (GuzzleException $e) {
            throw new \Exception($e->getMessage(), 401);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage(), 500);
        }
    }

    private function searchUserInCache(RedisClient $redis, string $apiToken): ?string
    {
        return $redis->get($this->getCacheKey($apiToken));
    }

    private function getCacheKey(string $apiToken): string
    {
        return 'auth_user:' . md5($apiToken);
    }

    private function loadDotEnv(): void
    {
        $rootDir = $this->findProjectRoot();
        
        if (file_exists($rootDir . '/.env')) {
            if (class_exists('\\Dotenv\\Dotenv')) {
                $dotenv = \Dotenv\Dotenv::createImmutable($rootDir);
                $dotenv->load();
            }
        }
    }

    private function findProjectRoot(): string
    {
        $dir = dirname(__DIR__, 4);
        if (basename(dirname($dir, 1)) === 'vendor') {
            return dirname($dir, 2);
        }
        
        return getcwd();
    }
}