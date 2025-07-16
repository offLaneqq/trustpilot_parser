<?php
// config.php

// Шлях до папки з CSV та іншими даними
define('DATA_DIR', __DIR__ . '/data');

// Шлях до папки для збереження аватарок
define('IMAGES_DIR', __DIR__ . '/images');

// HTTP‑клієнт: таймаут на з’єднання і на відповідь (в секундах)
define('HTTP_CONNECT_TIMEOUT', 10);
define('HTTP_TIMEOUT', 30);

// Ліміт сторінок пагінації
define('PAGE_LIMIT', 400);

// User‑Agent для запитів
define('DEFAULT_USER_AGENT', 'MyTrustpilotParser/1.0');

// (За бажанням) Логи в файл
define('LOG_FILE', __DIR__ . '/parser.log');
