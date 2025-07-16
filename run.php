<?php

require __DIR__ . '/config.php';
require __DIR__ . '/vendor/autoload.php';

use App\Parser;
use App\CsvWriter;
use App\Downloader;
use App\Utils;
use App\HtmlFetcher;

$htmlFetcher = new HtmlFetcher();
$downloader = new Downloader(__DIR__ . '/images');
$parser = new Parser($htmlFetcher, $downloader);
$csvWriter = new CsvWriter(DATA_DIR . '/output.csv');

$inputUrls = Utils::readInputUrls(__DIR__ . '/data/input_urls.csv');

$allReviews = [];
foreach ($inputUrls as $url) {
    echo "Processing URL: {$url}\n";
    try {
        $reviews = $parser->parseReviewsFromUrl($url);
        echo "Знайдено відгуків: " . count($reviews) . "\n";
        if (!empty($reviews)) {
            print_r($reviews[0]);
            $allReviews = array_merge($allReviews, $reviews);
        }
    } catch (\Exception $e) {
        echo "Error processing URL: {$url}: {$e->getMessage()}\n";
    }
}

// Після циклу — один запис у CSV
if (!empty($allReviews)) {
    if (!$csvWriter->write($allReviews, DATA_DIR . '/output.csv')) {
        echo "Помилка запису у CSV-файл!\n";
    } else {
        echo "Дані успішно записані у CSV-файл.\n";
    }
}