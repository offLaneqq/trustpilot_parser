<?php

namespace App;

/**
 * Utils — допоміжні утиліти для парсера (наприклад, читання списку URL).
 */
class Utils
{
       public static function readInputUrls(string $filePath): array {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: $filePath");
        }

        $urls = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            while (($data = fgetcsv($handle)) !== false) {
                // Припускаємо, що URL у першій колонці
                if (!empty($data[0])) {
                    $urls[] = trim($data[0]);
                }
            }
            fclose($handle);
        }

        return $urls;
    }
}