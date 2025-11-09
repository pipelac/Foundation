# ⚡ Quick Start: RSS Summarization

Быстрый запуск AI суммаризации для обработки уже собранных RSS новостей.

---

## ✅ Предварительные требования

1. ✅ MariaDB сервер запущен
2. ✅ База данных `rss2tlg` создана
3. ✅ Схемы импортированы:
   - `src/Rss2Tlg/sql/rss2tlg_schema_clean.sql`
   - `src/Rss2Tlg/sql/ai_pipeline_schema.sql`
4. ✅ Данные загружены из дампов в `production/sql/`
5. ✅ OpenRouter API ключ настроен в `production/configs/openrouter.json`

---

## 🚀 Запуск за 3 шага

### Шаг 1: Проверьте наличие данных

```bash
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg -e "SELECT COUNT(*) as total FROM rss2tlg_items;"
```

**Ожидаемый результат:** `total: 403` (или больше)

---

### Шаг 2: Запустите скрипт (тестовый режим)

```bash
php production/rss_summarization.php
```

**Что произойдет:**
- ✅ Загрузятся последние 3 непроцессированные новости
- ✅ AI обработает каждую новость (суммаризация, категоризация)
- ✅ Результаты сохранятся в `rss2tlg_summarization`
- ✅ Telegram уведомления будут отправлены
- ✅ Логи запишутся в `logs/rss_summarization.log`

**Ожидаемое время:** ~30-60 секунд (зависит от модели и скорости API)

---

### Шаг 3: Проверьте результаты

```bash
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg << 'EOF'
SELECT 
    i.title,
    s.article_language,
    s.category_primary,
    s.importance_rating,
    s.tokens_used,
    s.processed_at
FROM rss2tlg_summarization s
JOIN rss2tlg_items i ON s.item_id = i.id
WHERE s.status = 'success'
ORDER BY s.processed_at DESC
LIMIT 3;
EOF
```

**Ожидаемый результат:**
- 3 новости с заполненными полями
- `article_language`: ru или en
- `category_primary`: politics, technology, business, и т.д.
- `importance_rating`: 1-20
- `tokens_used`: ~1000-2000 токенов на новость

---

## 🎯 Production режим

### Отключить TEST MODE

```bash
vim production/rss_summarization.php

# Найти строку (около 38):
const TEST_MODE = true;

# Изменить на:
const TEST_MODE = false;
```

### Запустить

```bash
php production/rss_summarization.php
```

**⚠️ Внимание:** Будут обработаны ВСЕ непроцессированные новости!

---

## 📊 Мониторинг

### Просмотр логов в реальном времени

```bash
tail -f logs/rss_summarization.log
```

### Проверка статистики

```sql
SELECT 
    status,
    COUNT(*) as count,
    SUM(tokens_used) as total_tokens
FROM rss2tlg_summarization
GROUP BY status;
```

### Просмотр последних обработанных новостей

```sql
SELECT 
    i.title,
    s.headline,
    LEFT(s.summary, 100) as summary_preview
FROM rss2tlg_summarization s
JOIN rss2tlg_items i ON s.item_id = i.id
WHERE s.status = 'success'
ORDER BY s.processed_at DESC
LIMIT 5;
```

---

## 🐛 Проблемы и решения

### Проблема: "No unprocessed items found"

**Причина:** Все новости уже обработаны или таблица `rss2tlg_items` пуста

**Решение:**
```bash
# Проверить количество новостей
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg -e "SELECT COUNT(*) FROM rss2tlg_items;"

# Если 0 - загрузить из дампа
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg < production/sql/rss2tlg_items_dump.sql

# Или запустить RSS Ingest
php production/rss_ingest.php
```

---

### Проблема: "OpenRouter API error"

**Причина:** Неверный API ключ или проблемы с доступом

**Решение:**
```bash
# Проверить конфиг
cat production/configs/openrouter.json

# Проверить доступность API
curl -H "Authorization: Bearer sk-or-v1-..." https://openrouter.ai/api/v1/models
```

---

### Проблема: "Table 'rss2tlg_summarization' doesn't exist"

**Причина:** Схема AI Pipeline не импортирована

**Решение:**
```bash
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg < src/Rss2Tlg/sql/ai_pipeline_schema.sql
```

---

## 📞 Дополнительная информация

- **Полная документация:** `production/README.md`
- **Примеры использования:** `production/USAGE_EXAMPLES.md`
- **Отчет о тестировании:** `production/TEST_REPORT.md`

---

## 🎉 Готово!

После успешного запуска у вас будут:
- ✅ Суммаризованные новости в `rss2tlg_summarization`
- ✅ Категории и теги для каждой новости
- ✅ Оценка важности (importance rating)
- ✅ Данные для дедупликации (entities, events, facts)
- ✅ Готовность к следующему этапу pipeline (deduplication)

**Следующий шаг:** Дедупликация (в разработке)

---

**Версия:** 1.0.0  
**Дата:** 2025-11-09
