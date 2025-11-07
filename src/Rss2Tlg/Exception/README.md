# Rss2Tlg Exception Classes

Иерархия специализированных исключений для модуля Rss2Tlg.

## Назначение

Классы исключений обеспечивают типизированную обработку ошибок в модуле Rss2Tlg, позволяя:
- **Точно идентифицировать** тип ошибки через instanceof
- **Логировать специфичные ошибки** с разным уровнем детализации
- **Graceful degradation** - использовать fallback логику при определенных ошибках
- **Catch по категориям** - отлавливать группы ошибок через базовые классы

## Структура

```
Exception/
├── Rss2TlgException.php              # Базовое для всего модуля
├── Feed/
│   ├── FeedConfigException.php       # Ошибки конфигурации фидов
│   └── FeedValidationException.php   # Валидация параметров фида
├── Prompt/
│   ├── PromptException.php           # Базовое для промптов
│   ├── PromptNotFoundException.php   # Промпт не найден
│   └── PromptLoadException.php       # Ошибка загрузки промпта
├── AI/
│   ├── AIAnalysisException.php       # Базовое для AI-анализа
│   ├── AIParsingException.php        # Парсинг JSON ответа
│   └── AIValidationException.php     # Валидация результата
└── Repository/
    ├── RepositoryException.php       # Базовое для репозиториев
    └── SaveException.php             # Ошибки сохранения в БД
```

## Иерархия

```
RuntimeException (PHP)
└── Rss2TlgException
    ├── FeedConfigException
    │   └── FeedValidationException
    ├── PromptException
    │   ├── PromptNotFoundException
    │   └── PromptLoadException
    ├── AIAnalysisException
    │   ├── AIParsingException
    │   └── AIValidationException
    └── RepositoryException
        └── SaveException
```

## Описание классов

### Rss2TlgException

**Namespace:** `App\Rss2Tlg\Exception`  
**Parent:** `RuntimeException`

Базовое исключение для всего модуля Rss2Tlg. Используется для отлова всех исключений модуля единым блоком.

```php
try {
    // Любая операция Rss2Tlg
} catch (Rss2TlgException $e) {
    // Ловим все ошибки модуля
}
```

---

### Feed исключения

#### FeedConfigException

**Namespace:** `App\Rss2Tlg\Exception\Feed`  
**Parent:** `Rss2TlgException`

Генерируется при проблемах с конфигурацией RSS/Atom фидов:
- Невалидный JSON конфигурации
- Отсутствие обязательных секций
- Ошибки загрузки конфигурационного файла

#### FeedValidationException

**Namespace:** `App\Rss2Tlg\Exception\Feed`  
**Parent:** `FeedConfigException`

Генерируется при валидации параметров конкретного фида:
- Отсутствуют обязательные поля (id, url, name)
- Невалидный URL
- Недопустимые значения (timeout < 0, retries < 0)

```php
use App\Rss2Tlg\Exception\Feed\FeedValidationException;

try {
    $config = FeedConfig::fromArray(['url' => 'invalid-url']);
} catch (FeedValidationException $e) {
    $logger->error("Feed validation failed: " . $e->getMessage());
}
```

---

### Prompt исключения

#### PromptException

**Namespace:** `App\Rss2Tlg\Exception\Prompt`  
**Parent:** `Rss2TlgException`

Базовое исключение для всех операций с промптами. Используется для отлова группы ошибок.

#### PromptNotFoundException

**Namespace:** `App\Rss2Tlg\Exception\Prompt`  
**Parent:** `PromptException`

Генерируется когда:
- Запрашиваемый файл промпта не существует
- Директория промптов не найдена

```php
use App\Rss2Tlg\Exception\Prompt\PromptNotFoundException;

try {
    $prompt = $promptManager->getSystemPrompt('NonExistent_v1');
} catch (PromptNotFoundException $e) {
    $logger->error("Prompt not found: " . $e->getMessage());
    // Fallback на дефолтный промпт
    $prompt = $promptManager->getSystemPrompt('INoT_v1');
}
```

#### PromptLoadException

**Namespace:** `App\Rss2Tlg\Exception\Prompt`  
**Parent:** `PromptException`

Генерируется когда файл промпта существует, но произошла ошибка при чтении:
- Нет прав доступа к файлу
- Ошибка чтения (disk full, I/O error)
- Некорректная кодировка файла

```php
use App\Rss2Tlg\Exception\Prompt\PromptLoadException;

try {
    $prompt = $promptManager->getSystemPrompt('INoT_v1');
} catch (PromptLoadException $e) {
    $logger->critical("Cannot load prompt file: " . $e->getMessage());
    throw $e; // Критическая ошибка, дальше работать нельзя
}
```

---

### AI исключения

#### AIAnalysisException

**Namespace:** `App\Rss2Tlg\Exception\AI`  
**Parent:** `Rss2TlgException`

Базовое исключение для всех операций AI-анализа:
- Сетевые ошибки при запросах к API
- Превышение лимитов API
- Таймауты

#### AIParsingException

**Namespace:** `App\Rss2Tlg\Exception\AI`  
**Parent:** `AIAnalysisException`

Генерируется при проблемах парсинга ответа от AI:
- Ответ не является валидным JSON
- JSON структура не соответствует ожидаемой схеме
- Отсутствуют обязательные поля

```php
use App\Rss2Tlg\Exception\AI\AIParsingException;

try {
    $analysis = $aiService->analyze($item);
} catch (AIParsingException $e) {
    $logger->warning("AI parsing failed: " . $e->getMessage());
    // Используем базовый анализатор без AI
    $analysis = $basicAnalyzer->analyze($item);
}
```

#### AIValidationException

**Namespace:** `App\Rss2Tlg\Exception\AI`  
**Parent:** `AIAnalysisException`

Генерируется когда JSON ответ корректен, но значения полей не соответствуют критериям:
- Пустые обязательные поля (category, sentiment)
- Некорректные значения (sentiment не в [positive, negative, neutral])
- Отсутствие минимально требуемых данных

```php
use App\Rss2Tlg\Exception\AI\AIValidationException;

try {
    $analysis = $aiService->analyze($item);
} catch (AIValidationException $e) {
    $logger->warning("AI result validation failed: " . $e->getMessage());
    // Используем дефолтные значения
    $analysis = ['category' => 'uncategorized', 'sentiment' => 'neutral'];
}
```

---

### Repository исключения

#### RepositoryException

**Namespace:** `App\Rss2Tlg\Exception\Repository`  
**Parent:** `Rss2TlgException`

Базовое исключение для всех операций с репозиториями (Items, Publications, AIAnalysis).

#### SaveException

**Namespace:** `App\Rss2Tlg\Exception\Repository`  
**Parent:** `RepositoryException`

Генерируется при ошибках сохранения данных в БД:
- Нарушение constraints (UNIQUE, FOREIGN KEY)
- Потеря подключения к БД
- Таймауты транзакций
- Ошибки валидации данных перед вставкой

```php
use App\Rss2Tlg\Exception\Repository\SaveException;

$maxRetries = 3;
$attempt = 0;

while ($attempt < $maxRetries) {
    try {
        $itemId = $itemRepository->save($feedId, $item);
        break; // Успех
    } catch (SaveException $e) {
        $attempt++;
        $logger->warning("Save attempt {$attempt} failed: " . $e->getMessage());
        
        if ($attempt >= $maxRetries) {
            $logger->error("Failed to save after {$maxRetries} attempts");
            throw $e;
        }
        
        sleep(1); // Задержка перед повтором
    }
}
```

---

## Паттерны использования

### 1. Специфичный catch

Ловите конкретные исключения для точной обработки:

```php
try {
    $analysis = $aiService->analyze($item);
} catch (AIParsingException $e) {
    // Специфичная обработка ошибки парсинга
    $logger->warning("AI parsing failed, using fallback");
    $analysis = $fallbackAnalyzer->analyze($item);
} catch (AIValidationException $e) {
    // Специфичная обработка ошибки валидации
    $logger->warning("AI validation failed, using defaults");
    $analysis = getDefaultAnalysis();
} catch (AIAnalysisException $e) {
    // Общая обработка всех AI ошибок
    $logger->error("AI analysis completely failed");
    throw $e;
}
```

### 2. Групповой catch

Ловите группу исключений через базовый класс:

```php
try {
    $prompt = $promptManager->getSystemPrompt('INoT_v1');
    $analysis = $aiService->analyze($item);
} catch (PromptException $e) {
    // Все ошибки промптов
    $logger->error("Prompt error: " . $e->getMessage());
} catch (AIAnalysisException $e) {
    // Все ошибки AI
    $logger->error("AI error: " . $e->getMessage());
}
```

### 3. Модульный catch

Ловите все исключения модуля единым блоком:

```php
try {
    $result = $someComplexOperation();
} catch (Rss2TlgException $e) {
    // Все исключения модуля Rss2Tlg
    $logger->error("Rss2Tlg module error: " . $e->getMessage());
    
    // Можно проверить конкретный тип
    if ($e instanceof AIParsingException) {
        // Специфичная обработка
    }
}
```

### 4. Graceful degradation

Используйте fallback при определенных ошибках:

```php
// AI анализ с fallback
try {
    $analysis = $aiService->analyze($item);
} catch (AIParsingException | AIValidationException $e) {
    $logger->warning("AI failed, using basic analyzer");
    $analysis = $basicAnalyzer->analyze($item);
}

// Промпт с fallback
try {
    $prompt = $promptManager->getSystemPrompt('Custom_v1');
} catch (PromptNotFoundException $e) {
    $logger->info("Custom prompt not found, using default");
    $prompt = $promptManager->getSystemPrompt('INoT_v1');
}
```

### 5. Retry pattern

Повторные попытки для транзиентных ошибок:

```php
function saveWithRetry(ItemRepository $repo, int $feedId, RawItem $item): ?int
{
    $maxRetries = 3;
    $backoff = 1; // Начальная задержка в секундах
    
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            return $repo->save($feedId, $item);
        } catch (SaveException $e) {
            if ($attempt >= $maxRetries) {
                throw $e;
            }
            
            $logger->warning("Save attempt {$attempt} failed, retrying in {$backoff}s");
            sleep($backoff);
            $backoff *= 2; // Exponential backoff
        }
    }
    
    return null;
}
```

---

## Best Practices

1. **Всегда используйте специфичные исключения** вместо общих RuntimeException
2. **Логируйте все исключения** с достаточным контекстом для отладки
3. **Используйте fallback логику** для некритичных ошибок (AI, промпты)
4. **Retry только транзиентные ошибки** (сеть, БД deadlock), не retry валидацию
5. **Не глушите исключения** - если не можете обработать, пробросьте выше
6. **Документируйте throws** в PHPDoc для всех методов, которые могут бросать исключения

---

## Интеграция с Logger

Рекомендуемые уровни логирования:

| Исключение | Уровень | Причина |
|------------|---------|---------|
| FeedValidationException | WARNING | Некорректная конфигурация, можно пропустить фид |
| PromptNotFoundException | ERROR | Нужен fallback на дефолтный промпт |
| PromptLoadException | CRITICAL | Критическая ошибка, работать дальше нельзя |
| AIParsingException | WARNING | Можно использовать fallback анализатор |
| AIValidationException | WARNING | Можно использовать дефолтные значения |
| AIAnalysisException | ERROR | Общая ошибка AI, нужно разбираться |
| SaveException | ERROR | Ошибка сохранения, нужен retry или escalation |

```php
try {
    $analysis = $aiService->analyze($item);
} catch (AIParsingException $e) {
    $logger->warning("AI parsing failed", [
        'item_id' => $item->guid,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    $analysis = $fallbackAnalyzer->analyze($item);
} catch (AIAnalysisException $e) {
    $logger->error("AI analysis failed", [
        'item_id' => $item->guid,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    throw $e;
}
```

---

## См. также

- [API.md](../docs/API.md) - Документация API с примерами использования исключений
- [README.md](../README.md) - Основная документация модуля
