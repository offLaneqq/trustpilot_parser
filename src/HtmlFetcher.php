<?php

namespace App;

use GuzzleHttp\Client;

class HtmlFetcher
{
    protected Client $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function get(string $url): string
    {
        $response = $this->client->request('GET', $url);
        return $response->getBody()->getContents();
    }
}