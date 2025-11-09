# 🚀 Quick Start: AI Summarization Script

Быстрый старт production скрипта для AI суммаризации новостей.

---

## 📋 Предварительные требования

1. ✅ MariaDB/MySQL запущен
2. ✅ База данных `rss2tlg` создана
3. ✅ Схемы импортированы
4. ✅ RSS Ingest скрипт собрал новости (минимум 3 штуки для теста)
5. ✅ API ключ OpenRouter настроен

---

## ⚡ Быстрый старт (5 минут)

### Шаг 1: Проверка конфигурации

```bash
# Проверяем наличие конфигов
ls -la production/configs/
# Должны быть: ai_pipeline.json, database.json, telegram.json

# Проверяем API ключ в конфиге
grep api_key production/configs/ai_pipeline.json
```

### Шаг 2: Импорт схемы статистики

```bash
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg < production/sql/statistics_schema.sql
```

### Шаг 3: Проверка наличия данных

```bash
# Проверяем наличие необработанных новостей
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg -e "
SELECT COUNT(*) as unprocessed 
FROM rss2tlg_items i
LEFT JOIN rss2tlg_summarization s ON i.id = s.item_id
WHERE s.item_id IS NULL OR s.status IN ('pending', 'failed');
"
```

Должно быть **минимум 3 новости** для тестирования.

### Шаг 4: Первый тестовый запуск

```bash
# Ручной запуск (обработает последние 3 новости в TEST_MODE)
php production/ai_summarization.php
```

**Что должно произойти:**
- ✅ Скрипт запустится
- ✅ Найдет 3 новости для обработки
- ✅ Отправит уведомление в Telegram
- ✅ Обработает новости через AI
- ✅ Сохранит результаты в БД
- ✅ Отправит финальный отчет в Telegram
- ✅ Сохранит статистику в БД

### Шаг 5: Проверка результатов

```bash
# Смотрим лог
tail -50 logs/ai_summarization.log

# Смотрим результаты в БД
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg -e "
SELECT 
    i.title,
    s.category_primary,
    s.article_language,
    s.importance_rating,
    s.model_used,
    s.processed_at
FROM rss2tlg_summarization s
JOIN rss2tlg_items i ON s.item_id = i.id
WHERE s.status = 'success'
ORDER BY s.processed_at DESC
LIMIT 3;
"

# Смотрим статистику
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg -e "
SELECT * FROM rss2tlg_statistics 
WHERE script_name = 'AI Summarization' 
ORDER BY run_date DESC 
LIMIT 1;
"
```

### Шаг 6: Запуск теста (3 запуска)

```bash
# Запускаем тестовый скрипт (3 запуска с интервалом 1 минута)
./production/test_ai_summarization.sh
```

**Время выполнения:** ~3 минуты

### Шаг 7: Просмотр результатов теста

```bash
# Полный лог теста
cat logs/test_ai_summarization.log

# Только финальные отчеты
grep -A 20 "ФИНАЛЬНЫЙ ОТЧЕТ" logs/test_ai_summarization.log

# Статистика
tail -50 logs/test_ai_summarization.log
```

---

## 🔧 Переключение в Production режим

После успешного тестирования переключите скрипт в production режим:

### 1. Отключить TEST_MODE

Отредактируйте `production/ai_summarization.php`:

```bash
vim production/ai_summarization.php
```

Найдите и измените:

```php
// Было:
const TEST_MODE = true;
const TEST_LIMIT = 3;

// Стало:
const TEST_MODE = false;
// const TEST_LIMIT = 3; // Больше не используется в production
```

### 2. Настроить cron (каждую 1 минуту)

```bash
./production/setup_ai_summarization_cron.sh
```

Или вручную:

```bash
crontab -e

# Добавить строку:
* * * * * /usr/bin/php /home/engine/project/production/ai_summarization.php >> /home/engine/project/logs/cron_ai_summarization.log 2>&1
```

### 3. Проверить работу cron

```bash
# Подождать 1 минуту, затем проверить лог
tail -f logs/cron_ai_summarization.log
```

---

## 📊 Мониторинг

### В реальном времени

```bash
# Логи скрипта
tail -f logs/ai_summarization.log

# Логи cron
tail -f logs/cron_ai_summarization.log
```

### Статистика в БД

```sql
-- За сегодня
SELECT * FROM v_rss2tlg_statistics_daily 
WHERE date = CURDATE() 
  AND script_name = 'AI Summarization';

-- Последние обработанные новости
SELECT 
    i.title,
    s.category_primary,
    s.importance_rating,
    s.processed_at
FROM rss2tlg_summarization s
JOIN rss2tlg_items i ON s.item_id = i.id
WHERE s.status = 'success'
ORDER BY s.processed_at DESC
LIMIT 10;
```

---

## 🎯 Ежесуточная сводка

Автоматически отправляется в **00:00 MSK** (21:00 UTC) в ваш Telegram.

### Тестирование сводки вручную

Временно измените час отправки в `ai_summarization.php`:

```php
// Например, текущий час
const DAILY_SUMMARY_HOUR = 15; // Если сейчас 15:XX UTC
```

Запустите скрипт в этот час, и сводка отправится.

**Формат сводки:** см. `production/DAILY_SUMMARY_FORMAT.md`

---

## ❌ Устранение неполадок

### Проблема: "No unprocessed items found"

**Решение:** Запустите RSS Ingest для сбора новых новостей:

```bash
php production/rss_ingest.php
```

### Проблема: "OpenRouter API error"

**Решение:** Проверьте API ключ в конфиге:

```bash
cat production/configs/ai_pipeline.json | grep api_key
```

### Проблема: "Table 'rss2tlg_statistics' doesn't exist"

**Решение:** Импортируйте схему статистики:

```bash
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg < production/sql/statistics_schema.sql
```

### Проблема: "Telegram notifications not sent"

**Решение:** Проверьте Telegram конфиг:

```bash
cat production/configs/telegram.json
```

Проверьте доступность бота:

```bash
curl "https://api.telegram.org/bot8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI/getMe"
```

### Проблема: Высокая стоимость

**Решение:** 
1. Проверьте cache rate (должен быть >70%)
2. Используйте DeepSeek Chat чаще (дешевле в 10-50 раз)
3. Увеличьте интервал cron до 2-5 минут

---

## 📚 Дополнительная документация

- **Полная документация:** `production/README.md`
- **Формат ежесуточной сводки:** `production/DAILY_SUMMARY_FORMAT.md`
- **API SummarizationService:** `docs/Rss2Tlg/Pipeline_Summarization_README.md`

---

## ✅ Checklist готовности к production

- [ ] Схема статистики импортирована
- [ ] Конфиги настроены (ai_pipeline.json, database.json, telegram.json)
- [ ] RSS Ingest собрал новости (минимум 3 штуки)
- [ ] Тестовый запуск успешен
- [ ] Тест (3 запуска) успешен
- [ ] Результаты в БД корректны
- [ ] Telegram уведомления приходят
- [ ] TEST_MODE отключен
- [ ] Cron настроен
- [ ] Мониторинг настроен

---

**Время на полную настройку:** ~5-10 минут  
**Версия:** 1.0.0  
**Дата:** 2025-11-09
