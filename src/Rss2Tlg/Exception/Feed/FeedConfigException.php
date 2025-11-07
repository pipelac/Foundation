<?php

declare(strict_types=1);

namespace App\Rss2Tlg\Exception\Feed;

use App\Rss2Tlg\Exception\Rss2TlgException;

/**
 * Исключение для ошибок конфигурации RSS/Atom фидов
 * 
 * Генерируется при проблемах с загрузкой, парсингом или валидацией
 * конфигурационных файлов фидов.
 */
class FeedConfigException extends Rss2TlgException
{
}
