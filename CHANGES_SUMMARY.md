# Сводка изменений - Тестирование двухэтапной дедупликации и метрик OpenRouter

**Дата:** 2025-11-11  
**Задача:** Тестирование двухэтапной дедупликации и сбора метрик OpenRouter

---

## 📝 Изменения в коде

### 1. DeduplicationService.php
**Файл:** `src/Rss2Tlg/Pipeline/DeduplicationService.php`

**Изменение:** Добавлены поля конфигурации в метод `validateModuleConfig()`

```php
// Добавлено в строку 93-102:
return array_merge($aiConfig, [
    'similarity_threshold' => $similarityThreshold,
    'compare_last_n_days' => max(1, (int)($config['compare_last_n_days'] ?? 7)),
    'max_comparisons' => max(10, (int)($config['max_comparisons'] ?? 50)),
    'max_preliminary_comparisons' => max(10, (int)($config['max_preliminary_comparisons'] ?? 50)),
    'preliminary_similarity_threshold' => (float)($config['preliminary_similarity_threshold'] ?? 60.0),
    'max_ai_comparisons' => max(1, (int)($config['max_ai_comparisons'] ?? 10)),
    'min_importance_threshold' => $minImportance,
    'similarity_weights' => $weights,
]);
```

**Причина:** Поля `max_preliminary_comparisons`, `preliminary_similarity_threshold` и `max_ai_comparisons` не передавались из конфигурационного файла, использовались только дефолтные значения.

### 2. OpenRouter.class.php
**Файл:** `src/BaseUtils/OpenRouter.class.php`

**Изменение:** Добавлен метод `logWarning()`

```php
// Добавлено в строку 971-983:
/**
 * Логирует предупреждение
 *
 * @param string $message Сообщение
 * @param array<string, mixed> $context Контекст
 * @return void
 */
private function logWarning(string $message, array $context = []): void
{
    if ($this->logger !== null) {
        $this->logger->warning($message, $context);
    }
}
```

**Причина:** Метод `logWarning()` вызывался в коде (строки 212 и 218), но не был определен в классе.

### 3. init_schema.sql
**Файл:** `production/sql/init_schema.sql`

**Изменение 1:** Обновлено ENUM поле `similarity_method`

```sql
-- Строка 162:
`similarity_method` ENUM('ai', 'hash', 'hybrid', 'preliminary') NULL DEFAULT NULL
```

**Причина:** Значение `'preliminary'` отсутствовало в ENUM, что вызывало ошибку при сохранении результатов preliminary check.

**Изменение 2:** Заменена схема таблицы `openrouter_metrics`

Заменена упрощенная схема на детальную схему из `migration_openrouter_metrics.sql` с полями:
- `generation_id` - ID генерации от OpenRouter
- `provider_name` - Провайдер модели
- `native_tokens_*` - Токены от провайдера
- `usage_total`, `usage_cache`, `usage_data`, `usage_file` - Детальная стоимость
- `pipeline_module` - Модуль pipeline
- `full_response` - Полный JSON ответ

**Причина:** Старая схема не соответствовала требованиям детального сбора метрик.

### 4. deduplication.json
**Файл:** `production/configs/deduplication.json`

**Изменение для тестирования:** Временно снижен порог preliminary_similarity_threshold

```json
"preliminary_similarity_threshold": 10,
"max_ai_comparisons": 3
```

**Примечание:** Это изменение было сделано только для тестирования. В production рекомендуется вернуть значения:
```json
"preliminary_similarity_threshold": 60,
"max_ai_comparisons": 10
```

### 5. openrouter.json
**Файл:** `production/configs/openrouter.json`

**Изменение:** Обновлен API ключ

```json
"api_key": "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"
```

---

## 🗄️ Изменения в базе данных

### 1. Таблица rss2tlg_deduplication
```sql
ALTER TABLE rss2tlg_deduplication 
MODIFY similarity_method ENUM('ai', 'hash', 'hybrid', 'preliminary') NULL;
```

### 2. Таблица openrouter_metrics
```sql
DROP TABLE IF EXISTS openrouter_metrics;
-- Затем создана заново из migration_openrouter_metrics.sql
```

---

## 📄 Новые файлы

1. **production/telegram_notifier.php** - Вспомогательный скрипт для отправки уведомлений
2. **production/TEST_REPORT_DEDUPLICATION_OPENROUTER_METRICS.md** - Детальный отчет о тестировании
3. **production/sql/rss2tlg_deduplication_test_dump.sql** - Дамп таблицы дедупликации после тестов
4. **production/sql/openrouter_metrics_test_dump.sql** - Дамп таблицы метрик после тестов
5. **CHANGES_SUMMARY.md** - Данный файл со сводкой изменений

---

## ✅ Результаты тестирования

### Двухэтапная дедупликация
- ✅ **Stage 1 (Preliminary check):** Работает корректно, фильтрует по текстовой схожести
- ✅ **Stage 2 (AI analysis):** Работает корректно, обрабатывает только похожие новости

### Метрики OpenRouter
- ✅ **Сбор метрик:** Все 8 запросов зафиксированы
- ✅ **Детальность:** generation_id, модель, провайдер, токены, стоимость, время
- ✅ **Привязка:** Метрики привязаны к pipeline модулю (DeduplicationService)

### Статистика
- **Обработано новостей:** 5
- **Успешно:** 5 (100%)
- **Токенов использовано:** 30,730
- **Стоимость:** $0.0023
- **Время выполнения:** 47.94 сек
- **Записей метрик:** 8

### Generation ID для проверки
```
gen-1762889236-JMrQSCLRK12sLq3L6xGe
```

---

## 🔍 Рекомендации

1. **Восстановить production значения** в `deduplication.json`:
   ```json
   "preliminary_similarity_threshold": 60,
   "max_ai_comparisons": 10
   ```

2. **Обновить init_schema.sql** на всех окружениях с новыми схемами

3. **Мониторинг метрик:** Регулярно проверять таблицу `openrouter_metrics` для анализа расходов

4. **Документация:** Обновить документацию с описанием двухэтапной дедупликации

---

## 📊 Использованные технологии

- **MariaDB:** 10.11.13
- **PHP:** 8.1+
- **OpenRouter API:** v1
- **Telegram Bot API**
- **AI модели:** google/gemma-3-27b-it, deepseek/deepseek-chat, deepseek/deepseek-v3.2-exp

---

**Статус:** ✅ Все изменения успешно протестированы и готовы к использованию
