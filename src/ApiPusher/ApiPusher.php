<?php declare(strict_types=1);

namespace App\ApiPusher;

use App\Model\Value;
use GuzzleHttp\Client;
use JMS\Serializer\SerializerInterface;

class ApiPusher implements ApiPusherInterface
{
    protected SerializerInterface $serializer;
    protected Client $client;

    public function __construct(SerializerInterface $serializer)
    {
        $this->serializer = $serializer;

        $this->client = new Client([
            'base_uri' => 'https://127.0.0.1:8000/',
            'verify' => false,
        ]);
    }

    public function pushValue(Value $value): void
    {
        $this->client->put('/api/value', [
            'body' => $this->serializer->serialize($value, 'json'),
        ]);
    }
}
