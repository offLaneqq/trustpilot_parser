<?php

namespace App;

/**
 * CsvWriter — записує масив відгуків у CSV-файл з валідацією.
 */
class CsvWriter
{
    /**
     * Конструктор CsvWriter
     * @param string $csvPath Шлях до CSV-файлу
     */
    protected string $csvPath;

    public function __construct(string $csvPath) {
        $this->csvPath = $csvPath;
    }

    /**
     * Записує масив відгуків у CSV-файл з заголовками та валідацією.
     * @param array $data Масив відгуків
     * @param string $filePath Шлях до файлу
     * @return bool true, якщо запис успішний
     */
    public function write(array $data, string $filePath): bool
    {
        if (empty($data)) {
            return false;
        }
        // Валідація даних
        $validData = array_filter($data, function($row) {
            return $this->validateRow($row);
        });
        $invalidCount = count($data) - count($validData);
        if ($invalidCount > 0) {
            // Логування невалідних рядків (опціонально)
            $this->logInvalidRows($invalidCount);
        }
        if (empty($validData)) {
            return false;
        }
        // Створюємо директорію, якщо потрібно
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $fp = fopen($filePath, 'w');
        if ($fp === false) {
            return false;
        }
        $headers = array_keys(array_values($validData)[0]);
        fputcsv($fp, $headers);
        foreach ($validData as $row) {
            $line = [];
            foreach ($headers as $h) {
                $line[] = $row[$h] ?? '';
            }
            fputcsv($fp, $line);
        }
        fclose($fp);
        return true;
    }

    /**
     * Валідує рядок відгуку.
     * @param array $row Рядок відгуку
     * @return bool true, якщо рядок валідний
     */
    protected function validateRow(array $row): bool
    {
        // name — не порожнє
        if (empty($row['name'])) return false;
        // rating — число 1-5
        if (empty($row['rating']) || !is_numeric($row['rating']) || $row['rating'] < 1 || $row['rating'] > 5) return false;
        // date, date_of_experience — dd-mm-yyyy
        foreach (['date', 'date_of_experience'] as $field) {
            if (!empty($row[$field]) && !preg_match('/^\d{2}-\d{2}-\d{4}$/', $row[$field])) return false;
        }
        // avatar_file — існує файл або images/no_avatar.png
        if (empty($row['avatar_file'])) return false;
        if ($row['avatar_file'] !== 'images/no_avatar.png' && !file_exists($row['avatar_file'])) return false;
        return true;
    }

    /**
     * Логування кількості невалідних рядків.
     * @param int $count Кількість невалідних рядків
     * @return void
     */
    protected function logInvalidRows(int $count): void
    {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . '/invalid_rows.log';
        $date = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$date] Invalid rows skipped: $count\n", FILE_APPEND);
    }
}