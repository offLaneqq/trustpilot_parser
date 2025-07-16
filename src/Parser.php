<?php

namespace App;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Parser — парсить відгуки з Trustpilot, обробляє сторінки, форматує дані, логує помилки.
 */
class Parser
{
    protected HtmlFetcher $htmlFetcher;
    protected Downloader $downloader;
    protected int $pageLimit;

    /**
     * Parser constructor.
     * @param HtmlFetcher $htmlFetcher Клас для отримання HTML
     * @param Downloader $downloader Клас для завантаження аватарок
     * @param int $pageLimit Ліміт сторінок для парсингу
     */
    public function __construct(HtmlFetcher $htmlFetcher, Downloader $downloader, int $pageLimit = PAGE_LIMIT)
    {
        $this->htmlFetcher = $htmlFetcher;
        $this->downloader = $downloader;
        $this->pageLimit = $pageLimit;
    }

    /**
     * Парсить всі відгуки з усіх сторінок за вказаним URL (з урахуванням пагінації)
     * @param string $url URL сторінки Trustpilot
     * @return array Масив відгуків
     */
    public function parseReviewsFromUrl(string $url): array
    {
        $allReviews = [];
        $avatarMap = [];
        $page = 1;
        $baseUrl = $url;

        while ($page <= $this->pageLimit) {
            $pageUrl = $baseUrl;
            if ($page > 1) {
                $pageUrl = $baseUrl . (str_contains($baseUrl, '?') ? '&' : '?') . 'page=' . $page;
            }
            try {
                $html = $this->htmlFetcher->get($pageUrl);
            } catch (\Exception $e) {
                // Логування помилки або пропуск сторінки
                break;
            }
            $crawler = new Crawler($html);
            // Знаходимо всі звичайні відгуки (НЕ featured)
            $reviews = $crawler->filter('article[data-service-review-card-paper="true"]');

            $reviews->each(function (Crawler $node) use (&$allReviews, &$avatarMap) {
                $avatarUrl = $node->filter('div.avatar_imageWrapper__9hWrp img')->count()
                    ? $node->filter('div.avatar_imageWrapper__9hWrp img')->attr('src')
                    : '';

                // Ім'я користувача
                $name = $node->filter('.styles_consumerName__xKr9c')->count()
                    ? $node->filter('.styles_consumerName__xKr9c')->text()
                    : '';

                // Кількість відгуків
                $reviewsCount = '';
                if ($node->filter('span[data-consumer-reviews-count-typography="true"]')->count()) {
                    $text = $node->filter('span[data-consumer-reviews-count-typography="true"]')->text();
                    if (preg_match('/(\\d+)/', $text, $m)) {
                        $reviewsCount = $m[1];
                    }
                }

                // Рейтинг
                $rating = $node->filter('div[data-service-review-rating]')->count()
                    ? $node->filter('div[data-service-review-rating]')->attr('data-service-review-rating')
                    : (
                        $node->filter('img[alt^="Rated"]')->count()
                        ? (preg_match('/Rated (\\d+) out of \\d+ stars/', $node->filter('img[alt^="Rated"]')->attr('alt'), $m) ? $m[1] : '')
                        : ''
                    );

                // Заголовок
                $title = $node->filter('h2[data-service-review-title-typography="true"]')->count()
                    ? $node->filter('h2[data-service-review-title-typography="true"]')->text()
                    : '';

                // Текст відгуку
                $text = $node->filter('.styles_reviewContent__tuXiN p')->count()
                    ? $node->filter('.styles_reviewContent__tuXiN p')->text()
                    : '';

                // Дата
                $date = '';
                try {
                    if ($node->filter('time[data-service-review-date-time-ago]')->count()) {
                        $isoDate = $node->filter('time[data-service-review-date-time-ago]')->attr('datetime');
                        $dt = \DateTime::createFromFormat(DATE_ATOM, $isoDate);
                        if (!$dt) {
                            // fallback для ISO
                            $dt = date_create($isoDate);
                        }
                        $date = $dt ? $dt->format('d-m-Y') : '';
                    }
                } catch (\Exception $e) {
                    $date = '';
                    $this->logError("Date parse error: " . $e->getMessage());
                }

                // Date of experience
                $dateOfExp = '';
                try {
                    if ($node->filter('p[data-service-review-date-of-experience-typography="true"] span')->count()) {
                        $rawDate = $node->filter('p[data-service-review-date-of-experience-typography="true"] span')->text();
                        // Спробуємо розпізнати дату у форматі (наприклад, 'September 11, 2023')
                        $dt = date_create($rawDate);
                        $dateOfExp = $dt ? $dt->format('d-m-Y') : $rawDate;
                    }
                } catch (\Exception $e) {
                    $dateOfExp = '';
                    $this->logError("Date of experience parse error: " . $e->getMessage());
                }

                // Країна
                $country = $node->filter('span[data-consumer-country-typography="true"]')->count()
                    ? $node->filter('span[data-consumer-country-typography="true"]')->text()
                    : '';

                // Збираємо унікальні avatar_url => name
                if (!empty($avatarUrl) && !empty($name)) {
                    $avatarMap[$avatarUrl] = $name;
                }

                $reviewId = '';
                if ($node->filter('a[data-review-title-typography]')->count()) {
                    $href = $node->filter('a[data-review-title-typography]')->attr('href');
                    if (preg_match('#/reviews/([a-z0-9]+)#i', $href, $m)) {
                        $reviewId = $m[1];
                    }
                }
                if ($reviewId && !isset($allReviews[$reviewId])) {
                    $allReviews[$reviewId] = [
                        'avatar_url' => $avatarUrl, // тільки для підстановки, видалимо перед поверненням
                        'name' => $name,
                        'reviews_count' => $reviewsCount,
                        'rating' => $rating,
                        'title' => $title,
                        'text' => $text,
                        'date' => $date,
                        'date_of_experience' => $dateOfExp,
                        'country' => $country,
                        // 'avatar_file' => буде додано після async
                    ];
                }
            });
            $page++;
        }
        // Асинхронно завантажуємо всі аватарки
        $avatarFiles = $this->downloader->downloadImagesAsync($avatarMap);
        // Підставляємо avatar_file у результати
        foreach ($allReviews as &$review) {
            $file = $avatarFiles[$review['avatar_url']] ?? '';
            $review['avatar_file'] = $file ? ('images/' . $file) : 'images/no_avatar.png';
            unset($review['avatar_url']); // видаляємо перед поверненням
        }
        unset($review);
        return array_values($allReviews);
    }

    /**
     * Логування помилок у файл logs/errors.log
     * @param string $message Текст помилки
     * @return void
     */
    private function logError(string $message): void
    {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . '/errors.log';
        $date = date('Y-m-d H:i:s');
        file_put_contents($logFile, "[$date] $message\n", FILE_APPEND);
    }
}

?>