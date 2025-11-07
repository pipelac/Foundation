<?php

declare(strict_types=1);

/**
 * Пример использования модульной системы TelegramBot
 * 
 * Демонстрирует работу всех компонентов:
 * - Обработка webhook
 * - Обработка сообщений, команд, callback
 * - Работа с клавиатурами
 * - Загрузка файлов
 */

require_once __DIR__ . '/../../../autoload.php';

use App\Component\Config\ConfigLoader;
use App\Component\Http;
use App\Component\Logger;
use App\Component\TelegramBot\Core\TelegramAPI;
use App\Component\TelegramBot\Core\WebhookHandler;
use App\Component\TelegramBot\Handlers\CallbackQueryHandler;
use App\Component\TelegramBot\Handlers\MessageHandler;
use App\Component\TelegramBot\Handlers\TextHandler;
use App\Component\TelegramBot\Handlers\MediaHandler;
use App\Component\TelegramBot\Keyboards\InlineKeyboardBuilder;
use App\Component\TelegramBot\Keyboards\ReplyKeyboardBuilder;
use App\Component\TelegramBot\Utils\FileDownloader;
use App\Component\TelegramBot\Utils\Parser;

// Загрузка конфигурации
$configLoader = new ConfigLoader(__DIR__ . '/../config');
$config = $configLoader->load('telegram.json');

// Инициализация логгера
$logger = new Logger([
    'directory' => __DIR__ . '/../logs',
    'fileName' => 'telegram_bot.log',
    'maxFiles' => 7,
    'maxFileSize' => 10 * 1024 * 1024,
]);

// Инициализация HTTP клиента
$http = new Http([
    'timeout' => 30,
    'connect_timeout' => 10,
], $logger);

// Инициализация TelegramAPI
$api = new TelegramAPI(
    token: $config['token'],
    http: $http,
    logger: $logger
);

// Инициализация загрузчика файлов
$fileDownloader = new FileDownloader(
    token: $config['token'],
    http: $http,
    logger: $logger
);

// Инициализация обработчиков
$messageHandler = new MessageHandler($api, $logger);
$textHandler = new TextHandler($api, $logger);
$callbackHandler = new CallbackQueryHandler($api, $logger);
$mediaHandler = new MediaHandler($api, $fileDownloader, $logger);

// Инициализация webhook обработчика
$webhookHandler = new WebhookHandler($logger);

// Установка секретного токена (опционально)
if (isset($config['webhook_secret'])) {
    $webhookHandler->setSecretToken($config['webhook_secret']);
}

// Проверка, что это webhook запрос
if (!WebhookHandler::isValidWebhookRequest()) {
    http_response_code(400);
    exit('Invalid request');
}

try {
    // Получение обновления
    $update = $webhookHandler->getUpdate();

    // === ОБРАБОТКА КОМАНД ===

    // Команда /start
    $textHandler->handleCommand($update, 'start', function ($message, $args) use ($api) {
        $keyboard = InlineKeyboardBuilder::makeSimple([
            '📚 Помощь' => 'help',
            '⚙️ Настройки' => 'settings',
            '📊 Статистика' => 'stats',
        ]);

        $api->sendMessage(
            $message->chat->id,
            "👋 Привет, {$message->from->firstName}!\n\n" .
            "Я продвинутый бот с модульной архитектурой.\n" .
            "Используй кнопки ниже для навигации:",
            ['reply_markup' => $keyboard]
        );
    });

    // Команда /help
    $textHandler->handleCommand($update, 'help', function ($message) use ($api) {
        $helpText = "📚 *Доступные команды:*\n\n" .
            "/start - Начать работу\n" .
            "/help - Показать помощь\n" .
            "/keyboard - Показать клавиатуру\n" .
            "/photo - Отправить фото\n" .
            "/poll - Создать опрос\n" .
            "/info - Информация о боте";

        $api->sendMessage(
            $message->chat->id,
            $helpText,
            ['parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN]
        );
    });

    // Команда /keyboard - показать reply клавиатуру
    $textHandler->handleCommand($update, 'keyboard', function ($message) use ($api) {
        $keyboard = ReplyKeyboardBuilder::makeGrid(
            ['📱 Контакт', '📍 Местоположение', '❌ Скрыть клавиатуру'],
            columns: 2,
            resize: true
        );

        $api->sendMessage(
            $message->chat->id,
            "Выберите действие:",
            ['reply_markup' => $keyboard]
        );
    });

    // Команда /photo - отправить фото
    $textHandler->handleCommand($update, 'photo', function ($message) use ($api) {
        $keyboard = (new InlineKeyboardBuilder())
            ->addCallbackButton('❤️ Нравится', 'like:photo')
            ->addCallbackButton('👎 Не нравится', 'dislike:photo')
            ->row()
            ->addUrlButton('🔗 Источник', 'https://example.com')
            ->build();

        $api->sendPhoto(
            $message->chat->id,
            'https://picsum.photos/800/600',
            [
                'caption' => '🖼️ Случайное изображение',
                'reply_markup' => $keyboard,
            ]
        );
    });

    // Команда /poll - создать опрос
    $textHandler->handleCommand($update, 'poll', function ($message) use ($api) {
        $api->sendPoll(
            $message->chat->id,
            'Какой язык программирования лучший?',
            ['PHP', 'Python', 'JavaScript', 'Go', 'Rust'],
            [
                'is_anonymous' => true,
                'allows_multiple_answers' => false,
            ]
        );
    });

    // Команда /info - информация о боте
    $textHandler->handleCommand($update, 'info', function ($message) use ($api) {
        $botInfo = $api->getMe();
        
        $infoText = "🤖 *Информация о боте:*\n\n" .
            "ID: `{$botInfo->id}`\n" .
            "Username: @{$botInfo->username}\n" .
            "Имя: {$botInfo->firstName}\n" .
            "Поддержка inline: " . ($botInfo->supportsInlineQueries ? 'Да' : 'Нет');

        $api->sendMessage(
            $message->chat->id,
            $infoText,
            ['parse_mode' => TelegramAPI::PARSE_MODE_MARKDOWN]
        );
    });

    // === ОБРАБОТКА CALLBACK QUERY ===

    // Callback: help
    $callbackHandler->handleAction($update, 'help', function ($query) use ($callbackHandler) {
        $helpText = "📚 Здесь будет подробная справка по боту.\n\n" .
            "Выберите раздел для получения дополнительной информации.";

        $callbackHandler->answerAndEdit($query, $helpText);
    });

    // Callback: settings
    $callbackHandler->handleAction($update, 'settings', function ($query) use ($callbackHandler) {
        $keyboard = (new InlineKeyboardBuilder())
            ->addCallbackButton('🔔 Уведомления', 'setting:notifications')
            ->addCallbackButton('🌍 Язык', 'setting:language')
            ->row()
            ->addCallbackButton('🔙 Назад', 'back:main')
            ->build();

        $callbackHandler->answerAndEdit(
            $query,
            "⚙️ Настройки бота:",
            ['reply_markup' => $keyboard]
        );
    });

    // Callback: stats
    $callbackHandler->handleAction($update, 'stats', function ($query) use ($callbackHandler) {
        $statsText = "📊 *Статистика:*\n\n" .
            "Сообщений обработано: 1,234\n" .
            "Пользователей: 567\n" .
            "Команд выполнено: 890";

        $callbackHandler->answerAndEdit($query, $statsText);
    });

    // Callback: like/dislike
    $callbackHandler->handleAction($update, 'like', function ($query, $params) use ($callbackHandler) {
        $callbackHandler->answerWithText($query, '❤️ Вам понравилось!');
    });

    $callbackHandler->handleAction($update, 'dislike', function ($query, $params) use ($callbackHandler) {
        $callbackHandler->answerWithText($query, '👎 Жаль, что не понравилось');
    });

    // === ОБРАБОТКА ТЕКСТОВЫХ СООБЩЕНИЙ ===

    // Обработка текста "Скрыть клавиатуру"
    $textHandler->handleContains($update, 'Скрыть клавиатуру', function ($message) use ($api) {
        $api->sendMessage(
            $message->chat->id,
            'Клавиатура скрыта',
            ['reply_markup' => ReplyKeyboardBuilder::remove()]
        );
    });

    // Обработка обычного текста
    $textHandler->handlePlainText($update, function ($message, $text) use ($api, $textHandler) {
        // Извлечение дополнительной информации
        $mentions = $textHandler->extractMentions($message);
        $hashtags = $textHandler->extractHashtags($message);
        $urls = $textHandler->extractUrls($message);

        $response = "Получил ваше сообщение!\n\n";
        
        if (!empty($mentions)) {
            $response .= "Упоминания: " . implode(', ', $mentions) . "\n";
        }
        
        if (!empty($hashtags)) {
            $response .= "Хештеги: #" . implode(', #', $hashtags) . "\n";
        }
        
        if (!empty($urls)) {
            $response .= "Ссылок найдено: " . count($urls) . "\n";
        }

        $api->sendMessage($message->chat->id, $response);
    });

    // === ОБРАБОТКА МЕДИА ===

    // Обработка фото
    $messageHandler->handlePhoto($update, function ($message) use ($api, $mediaHandler) {
        $photo = $mediaHandler->getBestPhoto($message);
        
        if ($photo) {
            $fileSize = $photo->getFileSizeInMB();
            $response = "📸 Получено фото!\n\n" .
                "Размер: {$fileSize} МБ\n" .
                "Разрешение: {$photo->width}x{$photo->height}";

            $api->sendMessage($message->chat->id, $response);
        }
    });

    // Обработка документов
    $messageHandler->handleDocument($update, function ($message) use ($api, $mediaHandler) {
        $document = $message->document;
        
        if ($document) {
            $response = "📄 Получен документ!\n\n" .
                "Имя файла: {$document->fileName}\n" .
                "Размер: {$document->getFileSizeInMB()} МБ\n" .
                "MIME: {$document->mimeType}";

            // Попытка скачать документ
            $savedPath = $mediaHandler->downloadDocument(
                $message,
                __DIR__ . '/../uploads/' . $document->fileName
            );

            if ($savedPath) {
                $response .= "\n\n✅ Файл сохранен: " . basename($savedPath);
            }

            $api->sendMessage($message->chat->id, $response);
        }
    });

    // Отправка ответа webhook
    $webhookHandler->sendResponse();

} catch (\Exception $e) {
    $logger->error('Ошибка обработки webhook', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);

    // Отправка ответа даже при ошибке
    $webhookHandler->sendResponse();
}
