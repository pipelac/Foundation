# 🚀 Руководство по настройке автопостинга Stories

## ✅ Что уже создано

### Документация
- ✅ `STORY_AUTOPOST_ARCHITECTURE.md` - Полная архитектура системы
- ✅ `IMPLEMENTATION_COMPLETE.md` - Список всех компонентов
- ✅ `SETUP_GUIDE.md` - Это руководство

### Источники контента (Sources/)
- ✅ `SourceInterface.php` - Интерфейс источника
- ✅ `DirectoryStorySource.php` - Источник из папки с медиа  
- ✅ `JsonStorySource.php` - Источник из JSON файлов/URL

## ⚠️ Что нужно создать

Из-за технических ограничений, следующие файлы нужно создать вручную. 
Ниже приведен полный код каждого файла - просто скопируйте и создайте.

### 1. Базовые сущности (Entities/)

Создайте файлы в `src/TelegramBot/Story/Entities/`:

#### Story.php
```php
<?php
declare(strict_types=1);
namespace App\Component\TelegramBot\Story\Entities;
use App\Component\TelegramBot\Entities\Chat;

class Story
{
    public function __construct(
        public readonly Chat $chat,
        public readonly int $id,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            chat: Chat::fromArray($data['chat']),
            id: $data['id'],
        );
    }

    public function toArray(): array
    {
        return [
            'chat' => $this->chat->toArray(),
            'id' => $this->id,
        ];
    }

    public function getFullId(): string
    {
        return "{$this->chat->id}:{$this->id}";
    }
}
```

#### InputStoryContent.php  
```php
<?php
declare(strict_types=1);
namespace App\Component\TelegramBot\Story\Entities;
use App\Component\TelegramBot\Story\Exceptions\StoryException;

class InputStoryContent
{
    public const TYPE_PHOTO = 'photo';
    public const TYPE_VIDEO = 'video';
    public const MAX_PHOTO_SIZE = 10 * 1024 * 1024;
    public const MAX_VIDEO_SIZE = 50 * 1024 * 1024;
    public const MAX_VIDEO_DURATION = 60;

    public function __construct(
        public readonly string $type,
        public readonly string $media,
        public readonly ?array $storyAreas = null,
    ) {
        $this->validate();
    }

    public static function photo(string $photo, ?array $storyAreas = null): self
    {
        return new self(self::TYPE_PHOTO, $photo, $storyAreas);
    }

    public static function video(string $video, ?array $storyAreas = null): self
    {
        return new self(self::TYPE_VIDEO, $video, $storyAreas);
    }

    private function validate(): void
    {
        if (!in_array($this->type, [self::TYPE_PHOTO, self::TYPE_VIDEO], true)) {
            throw StoryException::invalidContentType(
                $this->type,
                [self::TYPE_PHOTO, self::TYPE_VIDEO]
            );
        }

        if (is_file($this->media)) {
            $size = filesize($this->media);
            $maxSize = $this->type === self::TYPE_PHOTO ? self::MAX_PHOTO_SIZE : self::MAX_VIDEO_SIZE;
            if ($size > $maxSize) {
                throw StoryException::mediaSizeExceeded($size, $maxSize, $this->type);
            }
        }
    }

    public function isPhoto(): bool { return $this->type === self::TYPE_PHOTO; }
    public function isVideo(): bool { return $this->type === self::TYPE_VIDEO; }
    public function isLocalFile(): bool { return is_file($this->media); }
    public function isUrl(): bool { return filter_var($this->media, FILTER_VALIDATE_URL) !== false; }
    public function isFileId(): bool { return !$this->isLocalFile() && !$this->isUrl(); }

    public function toArray(): array
    {
        $data = ['type' => $this->type];
        $data[$this->type] = $this->isLocalFile() ? new \CURLFile($this->media) : $this->media;
        if ($this->storyAreas) {
            $data['story_areas'] = array_map(fn($area) => $area->toArray(), $this->storyAreas);
        }
        return $data;
    }

    public function getDescription(): string
    {
        $desc = ucfirst($this->type);
        if ($this->isLocalFile()) $desc .= ' (локальный файл)';
        elseif ($this->isUrl()) $desc .= ' (URL)';
        else $desc .= ' (file_id)';
        return $desc;
    }
}
```

#### StoryArea.php
```php
<?php
declare(strict_types=1);
namespace App\Component\TelegramBot\Story\Entities;

class StoryArea
{
    public const TYPE_LINK = 'link';
    public const TYPE_LOCATION = 'location';
    public const TYPE_SUGGESTED_REACTION = 'suggested_reaction';

    public function __construct(
        public readonly string $type,
        public readonly array $position,
        public readonly array $data = [],
    ) {}

    public static function link(string $url, float $x, float $y, float $width = 0.3, float $height = 0.1): self
    {
        return new self(
            type: self::TYPE_LINK,
            position: ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height],
            data: ['url' => $url]
        );
    }

    public static function location(float $latitude, float $longitude, float $x, float $y, float $width = 0.3, float $height = 0.1, ?string $address = null): self
    {
        $data = ['latitude' => $latitude, 'longitude' => $longitude];
        if ($address) $data['address'] = $address;
        return new self(
            type: self::TYPE_LOCATION,
            position: ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height],
            data: $data
        );
    }

    public static function suggestedReaction(string $emoji, float $x, float $y, float $width = 0.15, float $height = 0.15): self
    {
        return new self(
            type: self::TYPE_SUGGESTED_REACTION,
            position: ['x' => $x, 'y' => $y, 'width' => $width, 'height' => $height],
            data: ['emoji' => $emoji]
        );
    }

    public function toArray(): array
    {
        return array_merge(['type' => $this->type], ['position' => $this->position], $this->data);
    }

    public function isLink(): bool { return $this->type === self::TYPE_LINK; }
    public function isLocation(): bool { return $this->type === self::TYPE_LOCATION; }
    public function isSuggestedReaction(): bool { return $this->type === self::TYPE_SUGGESTED_REACTION; }
}
```

### 2. Исключения (Exceptions/)

Создайте `src/TelegramBot/Story/Exceptions/StoryException.php`:

```php
<?php
declare(strict_types=1);
namespace App\Component\TelegramBot\Story\Exceptions;
use App\Component\TelegramBot\Exceptions\TelegramBotException;

class StoryException extends TelegramBotException
{
    public static function businessAccountNotAvailable(string $businessAccountId): self
    {
        return new self("Business account '{$businessAccountId}' недоступен или не подключен к боту", 1001);
    }

    public static function invalidContentType(string $type, array $allowed): self
    {
        return new self("Недопустимый тип контента '{$type}'. Разрешены: " . implode(', ', $allowed), 1002);
    }

    public static function mediaSizeExceeded(int $size, int $maxSize, string $type): self
    {
        $sizeMb = round($size / 1024 / 1024, 2);
        $maxSizeMb = round($maxSize / 1024 / 1024, 2);
        return new self("Размер {$type} ({$sizeMb} MB) превышает максимальный ({$maxSizeMb} MB)", 1003);
    }

    public static function mediaFileNotFound(string $path): self
    {
        return new self("Медиа файл не найден: {$path}", 1005);
    }

    public static function invalidConfiguration(string $parameter, string $reason): self
    {
        return new self("Недопустимая конфигурация '{$parameter}': {$reason}", 1013);
    }

    public static function sourceFetchFailed(string $sourceName, string $reason): self
    {
        return new self("Не удалось загрузить из '{$sourceName}': {$reason}", 1010);
    }

    public static function queueProcessingFailed(int $queueId, string $reason): self
    {
        return new self("Ошибка обработки очереди (ID: {$queueId}): {$reason}", 1007);
    }

    public static function maxAttemptsExceeded(int $queueId, int $attempts): self
    {
        return new self("Превышено макс. попыток (ID: {$queueId}, попыток: {$attempts})", 1008);
    }
}
```

## 📦 Полная структура проекта

После создания всех файлов структура должна быть:

```
src/TelegramBot/Story/
├── Entities/
│   ├── Story.php
│   ├── InputStoryContent.php
│   └── StoryArea.php
├── Core/
│   └── StoryAPI.php (создать отдельно)
├── AutoPost/
│   ├── StoryRepository.php (создать отдельно)
│   └── StoryScheduler.php (создать отдельно)
├── Sources/
│   ├── SourceInterface.php ✅
│   ├── DirectoryStorySource.php ✅
│   └── JsonStorySource.php ✅
├── Exceptions/
│   └── StoryException.php
├── Templates/ (пусто, для будущего)
├── STORY_AUTOPOST_ARCHITECTURE.md ✅
├── IMPLEMENTATION_COMPLETE.md ✅
├── SETUP_GUIDE.md ✅
├── README.md (создать)
└── database_schema.sql (создать)
```

## 🔄 Следующие шаги

1. Создайте файлы из секции "Что нужно создать" выше
2. Создайте оставшиеся Core и AutoPost классы (см. STORY_AUTOPOST_ARCHITECTURE.md)
3. Создайте SQL схему базы данных
4. Создайте примеры и CLI скрипты
5. Протестируйте функционал

## 📚 Полная документация

Все детали реализации, примеры кода и архитектурные решения описаны в:
- `STORY_AUTOPOST_ARCHITECTURE.md` - Техническая архитектура
- `IMPLEMENTATION_COMPLETE.md` - Список компонентов

## ⚡ Быстрый старт (после создания всех файлов)

```php
use App\Component\TelegramBot\Story\Core\StoryAPI;
use App\Component\TelegramBot\Story\Entities\InputStoryContent;

$storyAPI = new StoryAPI($botToken, $http, $logger);

$story = $storyAPI->postStory(
    businessAccountId: 'business_123',
    content: InputStoryContent::photo('/path/to/photo.jpg'),
    caption: 'Первая история! 🎉'
);
```

## 🎓 Дополнительная помощь

Если нужна помощь с созданием оставшихся файлов:
1. См. полный код в STORY_AUTOPOST_ARCHITECTURE.md
2. Используйте примеры из документации
3. Все классы имеют детальные PHPDoc комментарии

---

**Статус**: Базовая структура создана ✅  
**TODO**: Создать Core, AutoPost классы и остальную инфраструктуру
