# Rss2Tlg Exceptions — Quick Reference

Краткий справочник по исключениям модуля Rss2Tlg.

## 📁 Структура

```
Exception/
├── Rss2TlgException.php          # Базовое исключение
├── Feed/                         # Исключения фидов
├── Prompt/                       # Исключения промптов
├── AI/                           # Исключения AI-анализа
└── Repository/                   # Исключения репозиториев
```

## 🎯 Когда использовать

| Исключение | Когда бросать |
|-----------|---------------|
| **FeedValidationException** | Некорректные параметры фида (отсутствует id, url, невалидный timeout) |
| **PromptNotFoundException** | Файл промпта не существует |
| **PromptLoadException** | Ошибка чтения файла промпта (права доступа, I/O error) |
| **AIParsingException** | JSON ответ от AI невалидный или не соответствует схеме |
| **AIValidationException** | Результат анализа не прошел валидацию (пустые поля, некорректные значения) |
| **SaveException** | Ошибка сохранения в БД (constraint violation, connection lost) |

## 🚀 Примеры использования

### Специфичный catch

```php
use App\Rss2Tlg\Exception\AI\AIParsingException;

try {
    $analysis = $aiService->analyze($item);
} catch (AIParsingException $e) {
    $logger->warning("AI parsing failed, using fallback");
    $analysis = $fallbackAnalyzer->analyze($item);
}
```

### Групповой catch

```php
use App\Rss2Tlg\Exception\Prompt\PromptException;

try {
    $prompt = $promptManager->getSystemPrompt('INoT_v1');
} catch (PromptException $e) {
    // Все ошибки промптов (NotFoundException, LoadException)
    $logger->error("Prompt error: " . $e->getMessage());
}
```

### Модульный catch

```php
use App\Rss2Tlg\Exception\Rss2TlgException;

try {
    // Любая операция модуля Rss2Tlg
    $result = $someOperation();
} catch (Rss2TlgException $e) {
    // Все исключения модуля одним блоком
    $logger->error("Rss2Tlg error: " . $e->getMessage());
}
```

### Retry pattern

```php
use App\Rss2Tlg\Exception\Repository\SaveException;

$maxRetries = 3;
for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
    try {
        $itemId = $itemRepository->save($feedId, $item);
        break; // Успех
    } catch (SaveException $e) {
        if ($attempt >= $maxRetries) {
            throw $e;
        }
        sleep(1);
    }
}
```

## 🔍 Иерархия

```
RuntimeException
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

## 📚 Документация

- **[README.md](README.md)** — полная документация (422 строки)
- **[STRUCTURE.txt](STRUCTURE.txt)** — структура файлов и namespaces
- **[../docs/API.md](../docs/API.md)** — примеры в контексте API

## ✅ Проверка работоспособности

```bash
# Проверка загрузки классов
php -r "require_once 'autoload.php'; 
use App\Rss2Tlg\Exception\AI\AIParsingException; 
echo 'OK\n';"

# Проверка иерархии
php -r "require_once 'autoload.php';
use App\Rss2Tlg\Exception\AI\AIParsingException;
use App\Rss2Tlg\Exception\Rss2TlgException;
\$e = new AIParsingException('test');
echo \$e instanceof Rss2TlgException ? 'HIERARCHY OK' : 'FAIL';"
```

---

**Версия**: 1.0  
**Дата**: 2025-11-07  
**Модуль**: Rss2Tlg v2.1
