# 📖 Примеры использования Production Scripts

Практические примеры запуска и работы с production скриптами RSS2TLG.

---

## 🚀 Быстрый старт (Full Pipeline)

### 1. Сбор новостей из RSS

```bash
php production/rss_ingest.php
```

**Результат:**
- Новости собраны в `rss2tlg_items`
- Состояние источников обновлено в `rss2tlg_feed_state`
- Telegram уведомление отправлено
- Лог записан в `logs/rss_ingest.log`

---

### 2. AI Суммаризация (тестовый режим)

```bash
php production/rss_summarization.php
```

**Результат:**
- Обработаны последние 3 новости
- Данные сохранены в `rss2tlg_summarization`
- Telegram уведомления о прогрессе
- Лог записан в `logs/rss_summarization.log`

---

### 3. AI Суммаризация (production режим)

**Шаг 1:** Отключить TEST_MODE в скрипте

```bash
vim production/rss_summarization.php

# Изменить строку:
const TEST_MODE = true;  // →  const TEST_MODE = false;
```

**Шаг 2:** Запустить

```bash
php production/rss_summarization.php
```

**Результат:**
- Обработаны ВСЕ непроцессированные новости
- Данные сохранены в `rss2tlg_summarization`

---

## 📊 SQL запросы для мониторинга

### Проверка сырых новостей

```sql
-- Всего новостей
SELECT COUNT(*) FROM rss2tlg_items;

-- По источникам
SELECT 
    f.name,
    COUNT(i.id) as total
FROM rss2tlg_items i
JOIN rss2tlg_feeds f ON i.feed_id = f.id
GROUP BY f.id;

-- Последние 10 новостей
SELECT 
    f.name,
    i.title,
    i.created_at
FROM rss2tlg_items i
JOIN rss2tlg_feeds f ON i.feed_id = f.id
ORDER BY i.created_at DESC
LIMIT 10;
```

---

### Проверка суммаризации

```sql
-- Статус обработки
SELECT 
    status,
    COUNT(*) as count
FROM rss2tlg_summarization
GROUP BY status;

-- Результат:
-- +------------+-------+
-- | status     | count |
-- +------------+-------+
-- | success    | 3     |
-- | processing | 0     |
-- | failed     | 0     |
-- +------------+-------+

-- Суммаризованные новости с деталями
SELECT 
    i.title,
    s.article_language,
    s.category_primary,
    s.importance_rating,
    s.headline,
    LEFT(s.summary, 100) as summary_preview,
    s.processed_at
FROM rss2tlg_summarization s
JOIN rss2tlg_items i ON s.item_id = i.id
WHERE s.status = 'success'
ORDER BY s.processed_at DESC;

-- Использование токенов
SELECT 
    COUNT(*) as total_processed,
    SUM(tokens_used) as total_tokens,
    AVG(tokens_used) as avg_tokens,
    SUM(cache_hit) as cache_hits,
    ROUND(SUM(cache_hit) / COUNT(*) * 100, 2) as cache_rate_percent
FROM rss2tlg_summarization
WHERE status = 'success';
```

---

### Новости готовые к дедупликации

```sql
-- Новости с заполненными данными для дедупликации
SELECT 
    i.title,
    s.dedup_canonical_entities,
    s.dedup_core_event,
    s.dedup_numeric_facts
FROM rss2tlg_summarization s
JOIN rss2tlg_items i ON s.item_id = i.id
WHERE s.status = 'success'
AND s.dedup_canonical_entities IS NOT NULL;
```

---

## 📝 Работа с логами

### Просмотр логов RSS Ingest

```bash
# Последние 50 строк
tail -50 logs/rss_ingest.log

# В реальном времени
tail -f logs/rss_ingest.log

# Только ошибки
grep '"level":"error"' logs/rss_ingest.log | jq .

# Статистика по запускам
grep "Script completed" logs/rss_ingest.log | wc -l
```

---

### Просмотр логов Summarization

```bash
# Последние 50 строк
tail -50 logs/rss_summarization.log

# В реальном времени
tail -f logs/rss_summarization.log

# Только успешные обработки
grep "Item processed successfully" logs/rss_summarization.log

# Статистика токенов
grep "tokens_used" logs/rss_summarization.log | tail -10

# Ошибки AI
grep "AIAnalysisException" logs/rss_summarization.log
```

---

## 🔄 Настройка автоматического запуска

### Cron для RSS Ingest (каждые 2 минуты)

```bash
crontab -e

# Добавить:
*/2 * * * * /usr/bin/php /home/engine/project/production/rss_ingest.php >> /home/engine/project/logs/cron_rss_ingest.log 2>&1
```

---

### Cron для Summarization (каждые 5 минут)

```bash
crontab -e

# Добавить:
*/5 * * * * /usr/bin/php /home/engine/project/production/rss_summarization.php >> /home/engine/project/logs/cron_summarization.log 2>&1
```

**⚠️ Важно:** Не забудьте установить `TEST_MODE = false` для production!

---

### Проверка cron заданий

```bash
# Список всех cron заданий
crontab -l

# Логи cron
tail -f /var/log/syslog | grep CRON
```

---

## 🧪 Тестирование

### Быстрый тест (10 секунд между запусками)

```bash
./production/run_3_tests_fast.sh
```

**Результат:**
- 1 запуск: новые новости загружены
- 2 запуск: дубликаты отфильтрованы
- 3 запуск: возможно появились свежие новости

---

### Полный тест (2 минуты между запусками)

```bash
./production/run_3_tests.sh
```

**Результат:**
- Тестирование как в production режиме
- Финальный отчет в Telegram

---

## 🔧 Изменение конфигурации

### Добавление AI модели

```bash
vim production/configs/summarization.json

# Добавить модель в массив:
{
    "models": [
        "anthropic/claude-3.5-sonnet",
        "deepseek/deepseek-chat",
        "google/gemini-pro"  # ← новая модель
    ]
}
```

---

### Изменение количества тестовых новостей

```bash
vim production/rss_summarization.php

# Изменить константу:
const TEST_ITEMS_LIMIT = 5;  # было 3
```

---

### Изменение timeout для AI запросов

```bash
vim production/configs/summarization.json

# Изменить:
{
    "timeout": 180  # было 120 (секунд)
}
```

---

## 🐛 Отладка проблем

### Проблема: Нет новостей для суммаризации

**Проверка:**
```sql
SELECT COUNT(*) FROM rss2tlg_items;
```

**Решение:**
```bash
# Запустить RSS Ingest
php production/rss_ingest.php
```

---

### Проблема: AI возвращает ошибки

**Проверка логов:**
```bash
grep "error" logs/rss_summarization.log | tail -10
```

**Решение:**
- Проверить API ключ в `configs/openrouter.json`
- Проверить доступность OpenRouter API
- Попробовать fallback модель

---

### Проблема: Telegram уведомления не приходят

**Проверка конфига:**
```bash
cat production/configs/telegram.json
```

**Тест отправки:**
```bash
curl -X POST \
  "https://api.telegram.org/bot8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI/sendMessage" \
  -d "chat_id=366442475" \
  -d "text=Test message"
```

---

## 📈 Оптимизация производительности

### Увеличение скорости обработки

```bash
vim production/rss_summarization.php

# Уменьшить паузу между запросами:
usleep(500000);  # 0.5 сек →  usleep(250000);  # 0.25 сек
```

**⚠️ Предупреждение:** Слишком частые запросы могут привести к rate limiting.

---

### Экономия токенов

1. Используйте модель с prompt caching (Claude 3.5 Sonnet)
2. Убедитесь что промпт файл корректный
3. Проверьте cache rate в логах

```bash
grep "cache_hit" logs/rss_summarization.log
```

**Ожидаемый результат:** cache rate ~70-90% после первого запроса

---

## 📞 Поддержка

При возникновении проблем:

1. Проверьте логи: `tail -100 logs/*.log`
2. Проверьте БД: `mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg`
3. Проверьте конфиги: `cat production/configs/*.json`
4. Изучите документацию: `production/README.md`

---

**Версия:** 1.0.0  
**Дата:** 2025-11-09
