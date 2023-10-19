<?php

namespace Iddev\PrivateHttpClient;

use Illuminate\Http\Client\PendingRequest;
use Predis\Client as RedisClient;

class PrivateHttpClient extends PendingRequest
{

    private RedisClient $redisClient;

    public function __construct($factory = null, $middleware = [])
    {
        parent::__construct($factory, $middleware);
        $this->redisClient = new RedisClient([
            'host' => env('REDIS_HOST'),
        ]);
    }
    
    public function withAuth(): PendingRequest
    {
        $tokenType = 'Bearer';
        $token = $this->getToken();
        $this->withToken($token, $tokenType);
        return $this;
    }

    public function getToken(): string
    {
        $token = $this->redisClient->get('token');
        
        if ($token) {
            return $token;
        }
        return $this->updateToken();
    }

    public function updateToken(): string
    {
        $http = new PendingRequest();

        $http->baseUrl = env('STS_ENDPOINT');

        $r = $http->asForm()->post('/beeon/connect/token', [
            'grant_type' => 'client_credentials',
            'scope' => 'sts.aos sts.config sts.users sts.employees profile roles openid email phone offline_access',
            'client_id' => env('STS_AUTH_NAME'),
            'client_secret' => env('STS_AUTH_SECRET')
        ]);
        $body = $r->json();

        
        $accessToken = $body['access_token'];
        $expires_in = $body['expires_in'];

        $existsToken = $this->redisClient->get('token');
        if ($existsToken) {
            return $existsToken;
        }
        $status = $this->redisClient->set('token', $accessToken, 'EX', $expires_in);
        if ($status) {
            return $accessToken;
        }
        throw new \Exception('can`t set token');
    }
}
