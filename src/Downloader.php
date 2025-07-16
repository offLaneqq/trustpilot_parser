<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;

/**
 * Downloader — асинхронно завантажує аватарки користувачів, кешує url→filename.
 */
class Downloader
{
    protected string $imagesDir;
    protected array $urlCache = [];
    protected Client $client;

    /**
     * Downloader constructor.
     * @param string $imagesDir Директорія для збереження зображень
     */
    public function __construct(string $imagesDir = __DIR__ . '/../images')
    {
        $this->imagesDir = $imagesDir;
        $this->client = new Client();
        if (!is_dir($this->imagesDir)) {
            mkdir($this->imagesDir, 0777, true);
        }
    }

    /**
     * Асинхронно завантажує масив url=>name. Повертає масив url=>filename.
     * @param array $urlNameMap Масив url=>name
     * @return array Масив url=>filename
     */
    public function downloadImagesAsync(array $urlNameMap): array
    {
        $results = [];
        $requests = [];
        foreach ($urlNameMap as $url => $name) {
            if (empty($url) || empty($name)) continue;
            if (isset($this->urlCache[$url])) {
                $results[$url] = $this->urlCache[$url];
                continue;
            }
            $base = 'avatar';
            if ($name) {
                $base = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower($name));
            }
            $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
            $hash = substr(md5($url), 0, 8);
            $filename = $base . '_' . $hash . '.' . $ext;
            $savePath = rtrim($this->imagesDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
            if (empty($filename) || empty($savePath)) continue;
            if (file_exists($savePath)) {
                $this->urlCache[$url] = $filename;
                $results[$url] = $filename;
                continue;
            }
            $requests[] = [$url, new Request('GET', $url), $filename, $savePath];
        }
        if (empty($requests)) {
            return $results;
        }
        $pool = new Pool($this->client, array_map(fn($r) => $r[1], $requests), [
            'concurrency' => 8,
            'fulfilled' => function ($response, $idx) use (&$results, $requests) {
                [$url, $request, $filename, $savePath] = $requests[$idx];
                if (empty($url) || empty($savePath)) return;
                $body = $response->getBody()->getContents();
                file_put_contents($savePath, $body);
                $this->urlCache[$url] = $filename;
                $results[$url] = $filename;
            },
            'rejected' => function ($reason, $idx) use (&$results, $requests) {
                [$url] = $requests[$idx];
                if (empty($url)) return;
                $results[$url] = false;
            },
        ]);
        // Запускаємо асинхронно і чекаємо завершення
        $promise = $pool->promise();
        $promise->wait();
        return $results;
    }
}

