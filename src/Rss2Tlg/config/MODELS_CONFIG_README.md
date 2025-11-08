# Конфигурация AI моделей для RSS2TLG Pipeline

## Описание

Конфигурационный файл `rss2tlg_models_config.json` содержит настройки для всех модулей AI Pipeline с оптимизированными моделями.

## Модели по умолчанию

### Модули: Суммаризация, Дедупликация, Перевод

**Primary модель:** `deepseek/deepseek-v3.2-exp`
- Высокая скорость обработки
- Отличное качество анализа
- Низкая стоимость
- Приоритет: 1

**Fallback модель:** `google/gemma-3-27b-it`
- Надежный fallback
- Хорошее качество
- Приоритет: 2

### Модуль: Генерация иллюстраций

**Модели в порядке приоритета:**

1. `google/gemini-2.5-flash-image` (приоритет 1)
   - Быстрая генерация
   - Современная модель
   
2. `google/gemini-2.5-flash-image-preview` (приоритет 2)
   - Preview версия
   - Новые функции
   
3. `google/gemini-1.5-pro-vision` (приоритет 3)
   - Проверенная модель
   - Стабильная работа
   
4. `openai/gpt-5-image` (приоритет 4)
   - Резервная модель
   - OpenAI качество

## Структура конфигурации

### Суммаризация (Summarization)

```json
{
  "enabled": true,
  "models": [
    {
      "model": "deepseek/deepseek-v3.2-exp",
      "priority": 1,
      "supports_caching": false,
      "max_tokens": 1500,
      "temperature": 0.2,
      "top_p": 0.9,
      "frequency_penalty": 0.3,
      "presence_penalty": 0.1
    },
    {
      "model": "google/gemma-3-27b-it",
      "priority": 2,
      "supports_caching": false,
      "max_tokens": 1500,
      "temperature": 0.2,
      "top_p": 0.9,
      "frequency_penalty": 0.3,
      "presence_penalty": 0.1
    }
  ],
  "retry_count": 2,
  "timeout": 120,
  "fallback_strategy": "sequential",
  "prompt_file": "/home/engine/project/src/Rss2Tlg/prompts/summarization_prompt_v2.txt"
}
```

**Параметры:**
- `temperature: 0.2` - Низкая случайность для точного анализа
- `top_p: 0.9` - Ограничение выборки токенов
- `frequency_penalty: 0.3` - Снижает повторы
- `presence_penalty: 0.1` - Стимулирует разнообразие

### Дедупликация (Deduplication)

```json
{
  "enabled": true,
  "models": [
    {
      "model": "deepseek/deepseek-v3.2-exp",
      "priority": 1,
      "supports_caching": false,
      "max_tokens": 1000,
      "temperature": 0.1,
      "top_p": 0.95
    },
    {
      "model": "google/gemma-3-27b-it",
      "priority": 2,
      "supports_caching": false,
      "max_tokens": 1000,
      "temperature": 0.1,
      "top_p": 0.95
    }
  ],
  "retry_count": 2,
  "timeout": 120,
  "fallback_strategy": "sequential",
  "prompt_file": "/home/engine/project/src/Rss2Tlg/prompts/deduplication_prompt_v2.txt",
  "comparison_window_hours": 168,
  "min_similarity_for_duplicate": 70.0
}
```

**Параметры:**
- `temperature: 0.1` - Максимально детерминированный вывод
- `top_p: 0.95` - Высокая точность
- `comparison_window_hours: 168` - Сравнение за последние 7 дней
- `min_similarity_for_duplicate: 70.0` - Порог схожести 70%

### Перевод (Translation)

```json
{
  "enabled": true,
  "models": [
    {
      "model": "deepseek/deepseek-v3.2-exp",
      "priority": 1,
      "supports_caching": false,
      "max_tokens": 2000,
      "temperature": 0.3,
      "top_p": 0.9,
      "frequency_penalty": 0.2,
      "presence_penalty": 0.2
    },
    {
      "model": "google/gemma-3-27b-it",
      "priority": 2,
      "supports_caching": false,
      "max_tokens": 2000,
      "temperature": 0.3,
      "top_p": 0.9,
      "frequency_penalty": 0.2,
      "presence_penalty": 0.2
    }
  ],
  "retry_count": 2,
  "timeout": 120,
  "fallback_strategy": "sequential",
  "prompt_file": "/home/engine/project/src/Rss2Tlg/prompts/translation_prompt_v2.txt",
  "target_languages": ["ru", "en", "uk", "es", "fr", "de"],
  "min_quality_score": 7.0
}
```

**Параметры:**
- `temperature: 0.3` - Баланс между точностью и креативностью
- `target_languages` - Целевые языки для перевода
- `min_quality_score: 7.0` - Минимальный балл качества (из 10)

### Иллюстрации (Illustration)

```json
{
  "enabled": true,
  "models": [
    {
      "model": "google/gemini-2.5-flash-image",
      "priority": 1,
      "supports_caching": false,
      "max_tokens": 2000,
      "temperature": 0.7
    },
    {
      "model": "google/gemini-2.5-flash-image-preview",
      "priority": 2,
      "supports_caching": false,
      "max_tokens": 2000,
      "temperature": 0.7
    },
    {
      "model": "google/gemini-1.5-pro-vision",
      "priority": 3,
      "supports_caching": false,
      "max_tokens": 2000,
      "temperature": 0.7
    },
    {
      "model": "openai/gpt-5-image",
      "priority": 4,
      "supports_caching": false,
      "max_tokens": 2000,
      "temperature": 0.7
    }
  ],
  "retry_count": 2,
  "timeout": 180,
  "fallback_strategy": "sequential",
  "prompt_file": "/home/engine/project/src/Rss2Tlg/prompts/illustration_generation_prompt_v1.txt",
  "aspect_ratio": "16:9",
  "image_path": "/home/engine/project/images/rss2tlg",
  "watermark_text": "RSS2TLG",
  "watermark_size": 24,
  "watermark_position": "bottom-right"
}
```

**Параметры:**
- `temperature: 0.7` - Повышенная креативность для генерации изображений
- `timeout: 180` - Увеличенный таймаут для генерации
- `aspect_ratio: "16:9"` - Формат изображения
- `watermark_*` - Настройки водяного знака

## RSS Ленты

Конфигурация включает 10 RSS лент:

### Русскоязычные (5 лент)
1. **РИА Новости** - https://ria.ru/export/rss2/archive/index.xml
2. **Лента.ру** - https://lenta.ru/rss/top7
3. **ТАСС** - https://tass.ru/rss/v2.xml
4. **Коммерсантъ** - https://www.kommersant.ru/rss/main
5. **Habr - PHP** - https://habr.com/ru/rss/hub/php/all/

### Англоязычные (5 лент)
6. **Ars Technica - AI** - https://arstechnica.com/ai/feed
7. **TechCrunch** - https://techcrunch.com/feed
8. **BBC Technology** - http://feeds.bbci.co.uk/news/technology/rss.xml
9. **The Verge** - https://www.theverge.com/rss/index.xml
10. **Wired** - https://www.wired.com/feed/rss

## OpenRouter API

**API Key:** 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

**Настройки:**
- Base URL: https://openrouter.ai/api/v1
- Default Model: deepseek/deepseek-v3.2-exp
- Timeout: 120 секунд
- Retries: 2

## Telegram Bot

**Настройки:**
- Bot Token: 8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI
- Chat ID: 366442475
- Channel: @kompasDaily
- Mode: polling (Long Polling)
- Notifications: enabled

## База данных

**MariaDB/MySQL:**
- Host: 127.0.0.1
- Port: 3306
- Database: rss2tlg
- User: rss2tlg_user
- Password: rss2tlg_password_2024
- Charset: utf8mb4

## Логирование

**Настройки:**
- Directory: /home/engine/project/logs/Rss2Tlg
- File: rss2tlg_models_config.log
- Level: debug
- Max files: 5
- Max file size: 10 MB

## Использование

### Запуск с новым конфигом

```bash
# Пример использования в скрипте
php your_script.php --config=/home/engine/project/src/Rss2Tlg/config/rss2tlg_models_config.json
```

### Программное использование

```php
<?php

use App\Config\ConfigLoader;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\OpenRouter;
use App\Rss2Tlg\Pipeline\SummarizationService;
use App\Rss2Tlg\Pipeline\DeduplicationService;
use App\Rss2Tlg\Pipeline\TranslationService;
use App\Rss2Tlg\Pipeline\IllustrationService;

// Загрузка конфига
$configFile = '/home/engine/project/src/Rss2Tlg/config/rss2tlg_models_config.json';
$config = ConfigLoader::loadFromJson($configFile);

// Инициализация компонентов
$logger = new Logger($config['logger']);
$db = new MySQL($config['database'], $logger);
$openRouter = new OpenRouter($config['openrouter'], $logger);

// Создание сервисов Pipeline
$summarization = new SummarizationService(
    $db,
    $openRouter,
    $config['pipeline']['summarization'],
    $logger
);

$deduplication = new DeduplicationService(
    $db,
    $openRouter,
    $config['pipeline']['deduplication'],
    $logger
);

$translation = new TranslationService(
    $db,
    $openRouter,
    $config['pipeline']['translation'],
    $logger
);

$illustration = new IllustrationService(
    $db,
    $openRouter,
    $config['pipeline']['illustration'],
    $logger
);

// Обработка новости
$itemId = 123;

if ($summarization->processItem($itemId)) {
    echo "✅ Суммаризация выполнена\n";
    
    if ($deduplication->processItem($itemId)) {
        echo "✅ Дедупликация выполнена\n";
        
        if ($translation->processItem($itemId)) {
            echo "✅ Перевод выполнен\n";
            
            if ($illustration->processItem($itemId)) {
                echo "✅ Иллюстрация сгенерирована\n";
            }
        }
    }
}
```

## Особенности конфигурации

### Fallback Strategy

Все модули используют `"fallback_strategy": "sequential"`:
- Модели пробуются по порядку приоритета
- При ошибке автоматически переключение на следующую модель
- Каждая модель пробуется `retry_count` раз

### Retry Mechanism

- **Summarization:** 2 попытки
- **Deduplication:** 2 попытки  
- **Translation:** 2 попытки
- **Illustration:** 2 попытки

### Timeouts

- **Summarization:** 120 секунд
- **Deduplication:** 120 секунд
- **Translation:** 120 секунд
- **Illustration:** 180 секунд (увеличенный для генерации изображений)

## Мониторинг и уведомления

Все операции логируются и отправляются уведомления в Telegram:
- Старт обработки
- Прогресс выполнения
- Завершение операций
- Ошибки и предупреждения

## Поддержка

Для вопросов и проблем обращайтесь к документации:
- `docs/Rss2Tlg/Pipeline_Summarization_README.md`
- `docs/Rss2Tlg/Pipeline_Deduplication_README.md`
- `docs/Rss2Tlg/Pipeline_Translation_README.md`
- `docs/Rss2Tlg/Pipeline_Illustration_README.md`

## Версия

**Config Version:** 1.0  
**Date:** 2025-01-12  
**Author:** RSS2TLG Team
