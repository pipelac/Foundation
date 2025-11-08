# 📢 Publication Service - Модуль публикаций новостей

**Версия:** 1.0  
**Статус:** ✅ Production Ready  
**Дата:** 2025-11-08

---

## 📋 Описание

`PublicationService` — финальный модуль AI Pipeline для публикации обработанных новостей в Telegram каналы и группы.

### Основные функции:

- 📤 Публикация новостей в множественные destinations (каналы/группы/боты)
- 🎯 Фильтрация по правилам (категории, важность, язык)
- 🖼️ Поддержка медиа-контента (изображения)
- 🔄 Retry механизм при ошибках
- 📊 Детальное журналирование всех публикаций
- ⚡ Batch обработка новостей

---

## 🏗️ Архитектура

### Место в AI Pipeline:

```
rss2tlg_items (Сырые данные)
    ↓
rss2tlg_summarization (Этап 1: Суммаризация)
    ↓
rss2tlg_deduplication (Этап 2: Дедупликация)
    ↓
rss2tlg_translation (Этап 3: Перевод)
    ↓
rss2tlg_illustration (Этап 4: Иллюстрации)
    ↓
📢 PUBLICATION SERVICE (Этап 5: Публикация) ← ВЫ ЗДЕСЬ
    ↓
rss2tlg_publications (Журнал публикаций)
```

### Схема БД:

**Таблица: `rss2tlg_publications`** (расширенная)
```sql
- id (INT) - PK
- item_id (INT) - FK -> rss2tlg_items
- feed_id (INT)
- destination_type (ENUM: bot, channel, group)
- destination_id (VARCHAR)
- message_id (INT) - ID сообщения в Telegram
- published_headline (VARCHAR)
- published_text (TEXT)
- published_language (VARCHAR)
- published_media (JSON)
- published_categories (JSON)
- importance_rating (TINYINT)
- publication_status (ENUM: pending, processing, published, failed, skipped)
- retry_count (TINYINT)
- error_message (TEXT)
- error_code (VARCHAR)
- published_at (DATETIME)
- created_at (DATETIME)
```

**Таблица: `rss2tlg_publication_rules`** (правила публикации)
```sql
- id (INT) - PK
- feed_id (INT) - ID источника RSS
- destination_type (ENUM: bot, channel, group)
- destination_id (VARCHAR) - ID чата/канала/группы
- enabled (TINYINT)
- categories (JSON) - разрешенные категории
- min_importance (TINYINT) - минимальная важность (1-20)
- languages (JSON) - разрешенные языки
- include_image (TINYINT)
- include_link (TINYINT)
- template (TEXT) - шаблон сообщения
- priority (TINYINT) - приоритет правила
- created_at (DATETIME)
- updated_at (DATETIME)
```

**VIEW: `v_rss2tlg_ready_to_publish`** (новости готовые к публикации)
```sql
SELECT 
    i.id AS item_id,
    i.feed_id,
    s.headline, s.summary, s.importance_rating, s.category_primary,
    t.translated_headline, t.translated_summary, t.target_language,
    il.image_path,
    d.can_be_published, d.is_duplicate
FROM rss2tlg_items i
INNER JOIN rss2tlg_summarization s ON i.id = s.item_id AND s.status = 'success'
INNER JOIN rss2tlg_deduplication d ON i.id = d.item_id 
    AND d.status = 'checked' 
    AND d.can_be_published = 1
    AND d.is_duplicate = 0
LEFT JOIN rss2tlg_translation t ON i.id = t.item_id
LEFT JOIN rss2tlg_illustration il ON i.id = il.item_id
WHERE i.is_published = 0
ORDER BY s.importance_rating DESC
```

---

## 🚀 Использование

### 1. Инициализация

```php
use App\Rss2Tlg\Pipeline\PublicationService;
use App\Component\MySQL;
use App\Component\Logger;

$config = [
    'enabled' => true,
    'telegram_bots' => [
        [
            'token' => 'BOT_TOKEN',
            'default_chat_id' => 'CHAT_ID',
            'timeout' => 30,
            'types' => ['bot', 'channel', 'group']
        ]
    ],
    'retry_count' => 2,
    'timeout' => 30,
    'batch_size' => 10
];

$publicationService = new PublicationService($db, $config, $logger);
```

### 2. Публикация одной новости

```php
$itemId = 123;
$success = $publicationService->processItem($itemId);

if ($success) {
    echo "Новость опубликована успешно!\n";
} else {
    echo "Ошибка публикации или новость не прошла фильтры\n";
}
```

### 3. Batch обработка

```php
$itemIds = [123, 124, 125];
$stats = $publicationService->processBatch($itemIds);

echo "Успешно: {$stats['success']}\n";
echo "Неудачно: {$stats['failed']}\n";
echo "Пропущено: {$stats['skipped']}\n";
```

### 4. Создание правил публикации

```php
// Вставка правила в БД
$sql = 'INSERT INTO rss2tlg_publication_rules (
            feed_id, destination_type, destination_id,
            enabled, categories, min_importance, languages,
            include_image, include_link, priority
        ) VALUES (
            :feed_id, :destination_type, :destination_id,
            :enabled, :categories, :min_importance, :languages,
            :include_image, :include_link, :priority
        )';

$db->execute($sql, [
    'feed_id' => 1,
    'destination_type' => 'channel',
    'destination_id' => '@myChannel',
    'enabled' => 1,
    'categories' => json_encode(['technology', 'science']),
    'min_importance' => 12,
    'languages' => json_encode(['ru', 'en']),
    'include_image' => 1,
    'include_link' => 1,
    'priority' => 90
]);
```

### 5. Получение статуса публикации

```php
$status = $publicationService->getStatus($itemId);
// Возвращает: 'pending', 'processing', 'published', 'failed', 'skipped' или null
```

---

## 📐 Фильтрация новостей

Модуль фильтрует новости по следующим параметрам:

### 1. Минимальная важность (min_importance)
```php
// В правиле указан min_importance = 12
// Новость с importance_rating = 15 ✅ ПРОЙДЕТ
// Новость с importance_rating = 10 ❌ НЕ ПРОЙДЕТ
```

### 2. Категории
```php
// В правиле указаны categories: ["technology", "science"]
// Новость с category_primary = "technology" ✅ ПРОЙДЕТ
// Новость с category_primary = "politics" ❌ НЕ ПРОЙДЕТ

// Специальное значение "all" пропускает все категории
categories: ["all"] // ✅ ПРОЙДУТ ВСЕ КАТЕГОРИИ
```

### 3. Языки
```php
// В правиле указаны languages: ["ru"]
// Новость с translation_language = "ru" ✅ ПРОЙДЕТ
// Новость с article_language = "en" (без перевода) ❌ НЕ ПРОЙДЕТ
```

---

## 🎨 Формат сообщений

### Стандартный формат:
```html
<b>{headline}</b>

{text}

🔗 <a href="{link}">Читать полностью</a>
```

### Кастомный шаблон:
```php
// В правиле можно указать template:
$template = "📰 {headline}\n\n{text}\n\n📊 Категория: {category}\n⭐ Важность: {importance}\n\n🔗 {link}";
```

---

## 📊 Метрики

```php
$metrics = $publicationService->getMetrics();

/*
[
    'total_processed' => 10,    // Обработано новостей
    'successful' => 7,          // Успешно опубликовано
    'failed' => 1,              // Ошибки публикации
    'skipped' => 2,             // Пропущено (не прошли фильтры)
    'by_destination' => [       // По каждому destination
        'channel:@myChannel' => 5,
        'bot:123456789' => 2
    ],
    'total_time_ms' => 15234    // Время обработки
]
*/
```

---

## 🔄 Retry механизм

При ошибке публикации модуль автоматически повторяет попытку:

1. **Первая попытка** → ошибка
2. ⏱️ Задержка 100ms
3. **Вторая попытка** → ошибка
4. ⏱️ Задержка 200ms
5. **Третья попытка** → ошибка
6. ❌ Сохранение неудачной публикации в БД

Количество повторов настраивается через `retry_count` в конфиге.

---

## ⚠️ Обработка ошибок

Модуль логирует все ошибки и сохраняет информацию в БД:

```php
// Типы ошибок:
// 1. Telegram API ошибки (код ошибки сохраняется)
// 2. Отсутствие message_id в ответе
// 3. Сетевые ошибки (timeout)
// 4. Ошибки прав доступа (bot не admin в канале)
```

---

## 🧪 Тестирование

Запуск production теста:

```bash
php tests/Rss2Tlg/publication_test.php
```

Тест выполняет:
1. ✅ Загрузку/создание тестовых новостей
2. ✅ Обработку через AI Pipeline (суммаризация, дедупликация, перевод, иллюстрации)
3. ✅ Публикацию в Telegram (с retry)
4. ✅ Проверку результатов в БД
5. ✅ Генерацию отчета

---

## 📝 Логирование

Все действия модуля логируются:

```
2025-11-08T21:06:47 INFO [PublicationService] Новость опубликована в destination {"item_id":123,"destination":"channel:@myChannel","message_id":456}
2025-11-08T21:06:47 WARNING [PublicationService] Новость не соответствует min_importance {"item_id":124,"importance":8,"required":12}
2025-11-08T21:06:48 ERROR [PublicationService] Ошибка публикации {"item_id":125,"error":"Не получен message_id от Telegram API"}
```

---

## 🎯 Production Checklist

- [x] Модуль реализован
- [x] Интерфейс PipelineModuleInterface имплементирован
- [x] Схема БД создана (publications + rules)
- [x] VIEW для готовых к публикации новостей
- [x] Фильтрация по правилам (категории, важность, язык)
- [x] Поддержка множественных destinations
- [x] Retry механизм
- [x] Логирование всех операций
- [x] Метрики производительности
- [x] Обработка ошибок
- [x] Production тесты
- [x] Документация

---

## 🔐 Требования

### Telegram Bot

1. Создать бота через @BotFather
2. Получить токен
3. Для публикации в каналы:
   - Добавить бота в канал
   - Сделать администратором
   - Дать права на публикацию сообщений

### База данных

```sql
-- Импорт схем
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg < src/Rss2Tlg/sql/rss2tlg_schema_clean.sql
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg < src/Rss2Tlg/sql/ai_pipeline_schema.sql
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg < src/Rss2Tlg/sql/publication_schema.sql
```

---

## 📚 См. также

- [API.md](API.md) - Полный справочник API
- [INSTALL.md](INSTALL.md) - Установка и настройка
- [Pipeline_Summarization_README.md](Pipeline_Summarization_README.md) - Модуль суммаризации
- [Pipeline_Deduplication_README.md](Pipeline_Deduplication_README.md) - Модуль дедупликации
- [Pipeline_Translation_README.md](Pipeline_Translation_README.md) - Модуль перевода
- [Pipeline_Illustration_README.md](Pipeline_Illustration_README.md) - Модуль иллюстраций

---

**Автор:** AI Pipeline Team  
**Лицензия:** MIT
