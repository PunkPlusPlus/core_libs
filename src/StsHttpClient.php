<?php

namespace Iddev\PrivateHttpClient;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\RequestOptions;
use Predis\Client as RedisClient;

class StsHttpClient extends HttpClient
{
    private RedisClient $redisClient;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->redisClient = new RedisClient([
            'host' => env('REDIS_HOST'),
        ]);
    }

    public function request(string $method, $uri='', array $options = [])
    {
        $options[RequestOptions::SYNCHRONOUS] = true;

        $options[RequestOptions::HEADERS] = [
            'Authorization' => $this->getToken(),
        ];

        return $this->requestAsync($method, $uri, $options)->wait();
    }

    public function getToken(): string
    {
        $access_token = $this->redisClient->get('token');
        
        return !is_null($access_token) ? $access_token : $this->updateToken();
    }

    public function updateToken(): string
    {
        $stsTokenUrl = env('STS_ENDPOINT') . '/beeon/connect/token';
        $response = parent::request('POST', $stsTokenUrl, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            RequestOptions::FORM_PARAMS => [
                'grant_type' => 'client_credentials',
                'scope' => 'sts.aos sts.config sts.users sts.employees profile roles openid email phone offline_access',
                'client_id' => env('STS_AUTH_NAME'),
                'client_secret' => env('STS_AUTH_SECRET'),
            ]
        ]);
        $rawBody = (string)$response->getBody();
        $body = json_decode($rawBody, true);
        
        $access_token = $body['access_token'];

        $status = $this->redisClient->set('sts-token', $access_token, 'EX', $body['expires_in']);
        if ($status) {
            return $access_token;
        }
        throw new \Exception('can`t set token in redis');
    }
}
