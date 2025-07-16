# Trustpilot Parser

Парсер для збору всіх відгуків з Trustpilot за списком URL, збереженням у CSV та завантаженням аватарок користувачів.

## Структура проєкту

- `src/` — основний код (Parser, Downloader, CsvWriter, Utils, HtmlFetcher)
- `images/` — збережені аватарки
- `data/` — CSV-файли та вхідні дані
- `logs/` — логи помилок та невалідних рядків

## Вимоги
- PHP 8.1+
- Composer
- Розширення: ext-curl, ext-mbstring

## Встановлення
1. Клонувати репозиторій
2. Встановити залежності:
   ```bash
   composer install
   ```
3. Додати список URL у `data/input_urls.csv` (по одному в рядку)

## Запуск
```bash
php run.php
```

## Результат
- Всі відгуки зберігаються у `data/output.csv`
- Аватарки — у папці `images/`
- Логи помилок — у `logs/errors.log`, невалідних рядків — у `logs/invalid_rows.log`

## Налаштування
- Ліміти, шляхи — у `config.php` або константах класів
