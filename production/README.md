# 📦 Production Scripts - RSS2TLG

Production-ready скрипты для сбора и обработки новостей из RSS источников.

**📖 [Примеры использования](USAGE_EXAMPLES.md)** | **📊 [Отчет о тестировании](TEST_REPORT.md)** | **📝 [История изменений](CHANGES.md)**

---

## 🔄 Pipeline Workflow

Система обработки новостей состоит из нескольких последовательных этапов:

```
┌─────────────────────┐
│  1. RSS Ingest      │  ← rss_ingest.php
│  Сбор из источников │
└──────────┬──────────┘
           │ rss2tlg_items
           ↓
┌─────────────────────┐
│  2. Summarization   │  ← rss_summarization.php ✨ НОВЫЙ
│  AI суммаризация    │
└──────────┬──────────┘
           │ rss2tlg_summarization
           ↓
┌─────────────────────┐
│  3. Deduplication   │  ← rss_deduplication.php ✅ ГОТОВ
│  Проверка дубликатов│
└──────────┬──────────┘
           │ rss2tlg_deduplication
           ↓
┌─────────────────────┐
│  4. Translation     │  (в разработке)
│  Перевод на языки   │
└──────────┬──────────┘
           │ rss2tlg_translation
           ↓
┌─────────────────────┐
│  5. Illustration    │  (в разработке)
│  Генерация картинок │
└──────────┬──────────┘
           │ rss2tlg_illustration
           ↓
┌─────────────────────┐
│  6. Publication     │  (в разработке)
│  Публикация в Tlg   │
└─────────────────────┘
```

**Статус:**
- ✅ **Этап 1: RSS Ingest** - Production Ready (v1.0.0)
- ✅ **Этап 2: Summarization** - Production Ready (v1.0.0)
- ✅ **Этап 3: Deduplication** - Production Ready (v1.0.0)
- ⏳ Этапы 4-6 - в разработке

---

## 📁 Структура

```
production/
├── configs/                    # Конфигурационные файлы
│   ├── main.json              # Основные настройки
│   ├── database.json          # Подключение к БД
│   ├── telegram.json          # Telegram бот
│   ├── openrouter.json        # OpenRouter API
│   ├── summarization.json     # Настройки суммаризации
│   ├── deduplication.json     # Настройки дедупликации
│   └── feeds.json             # RSS источники (справочно)
├── prompts/                    # AI промпты
│   ├── summarization_prompt_v2.txt
│   └── deduplication_prompt_v2.txt
├── sql/                        # SQL дампы
│   ├── rss2tlg_feeds_dump.sql
│   ├── rss2tlg_items_dump.sql
│   └── ... (9 файлов)
├── rss_ingest.php             # 1️⃣ Скрипт сбора RSS
├── rss_summarization.php      # 2️⃣ Скрипт AI суммаризации
├── rss_deduplication.php      # 3️⃣ Скрипт проверки дубликатов
├── TEST_REPORT.md             # Отчет: RSS Ingest
├── TEST_REPORT_SUMMARIZATION.md    # Отчет: Summarization
├── TEST_REPORT_DEDUPLICATION.md    # Отчет: Deduplication
├── QUICKSTART_SUMMARIZATION_TESTED.md   # Quickstart: Summarization
├── QUICKSTART_DEDUPLICATION.md          # Quickstart: Deduplication
└── README.md                  # Эта документация
```

---

## 🚀 Быстрый старт

### 1. Установка зависимостей

```bash
# MariaDB должен быть установлен и запущен
sudo apt-get install mariadb-server mariadb-client
```

### 2. Создание БД и пользователя

```bash
sudo mysql -u root << 'EOF'
CREATE DATABASE IF NOT EXISTS rss2tlg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'rss2tlg_user'@'localhost' IDENTIFIED BY 'rss2tlg_password_2024';
GRANT ALL PRIVILEGES ON rss2tlg.* TO 'rss2tlg_user'@'localhost';
FLUSH PRIVILEGES;
EOF
```

### 3. Импорт схем

```bash
cd /home/engine/project
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg < src/Rss2Tlg/sql/rss2tlg_schema_clean.sql
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg < src/Rss2Tlg/sql/ai_pipeline_schema.sql
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg < src/Rss2Tlg/sql/publication_schema.sql
```

### 4. Настройка конфигов

Конфиги уже настроены в папке `configs/`. При необходимости отредактируйте:

```bash
vim production/configs/database.json    # БД
vim production/configs/telegram.json    # Telegram
vim production/configs/main.json        # Основные настройки
```

### 5. Ручной запуск

```bash
php production/rss_ingest.php
```

### 6. Настройка cron (каждые 2 минуты)

```bash
./production/setup_cron.sh
```

Или вручную:

```bash
crontab -e

# Добавить строку:
*/2 * * * * /usr/bin/php /home/engine/project/production/rss_ingest.php >> /home/engine/project/logs/cron_rss_ingest.log 2>&1
```

---

## 📡 Скрипт 1: rss_ingest.php

### Описание

Основной production скрипт для сбора новостей из RSS источников.

### Функционал

- ✅ Сбор новостей из 4 RSS источников (РИА, Коммерсантъ, Интерфакс, Медуза)
- ✅ Парсинг RSS 2.0 и Atom форматов
- ✅ Дедупликация (MD5 hash на основе title + link)
- ✅ Сохранение в БД (таблица `rss2tlg_items`)
- ✅ Обновление состояния источников (таблица `rss2tlg_feed_state`)
- ✅ Telegram уведомления о ходе работы
- ✅ Структурированное логирование (JSON)
- ✅ Graceful error handling

### Производительность

- **Скорость:** ~4-6 сек на обработку 4 источников и ~400 элементов
- **Память:** ~10-15 MB
- **Точность дедупликации:** 100%

### Конфигурационные файлы

| Файл | Назначение |
|------|------------|
| `configs/main.json` | Пути логов, интервалы, таймауты |
| `configs/database.json` | Подключение к БД |
| `configs/telegram.json` | Telegram бот (token, chat_id) |
| `configs/feeds.json` | RSS источники (справочно, не используется скриптом) |

### Логи

- **Основной лог:** `/home/engine/project/logs/rss_ingest.log`
- **Cron лог:** `/home/engine/project/logs/cron_rss_ingest.log`

### Просмотр логов

```bash
# Последние записи
tail -100 logs/rss_ingest.log

# В реальном времени
tail -f logs/rss_ingest.log

# Только ошибки
grep ERROR logs/rss_ingest.log

# Статистика по запускам
grep "Script completed" logs/rss_ingest.log
```

---

## 🤖 Скрипт 2: rss_summarization.php

### Описание

Production скрипт для AI суммаризации и категоризации новостей из таблицы `rss2tlg_items`.

### Функционал

- ✅ AI суммаризация полного текста новости
- ✅ Категоризация (основная + 2 дополнительные категории)
- ✅ Определение языка статьи (en, ru, и др.)
- ✅ Оценка важности новости (1-20)
- ✅ Подготовка данных для дедупликации (сущности, события, факты)
- ✅ Fallback между моделями (Claude 3.5 Sonnet → DeepSeek Chat)
- ✅ Prompt caching для экономии токенов
- ✅ Telegram уведомления о прогрессе
- ✅ Детальное логирование

### Режимы работы

**TEST MODE (по умолчанию):**
- Обрабатывает только последние 3 новости
- Константа: `TEST_MODE = true`
- Лимит: `TEST_ITEMS_LIMIT = 3`

**PRODUCTION MODE:**
- Обрабатывает все непроцессированные новости
- Установите: `TEST_MODE = false`

### Конфигурационные файлы

| Файл | Назначение |
|------|------------|
| `configs/main.json` | Пути логов, интервалы, таймауты |
| `configs/database.json` | Подключение к БД |
| `configs/telegram.json` | Telegram бот (token, chat_id) |
| `configs/openrouter.json` | OpenRouter API (ключ, URL) |
| `configs/summarization.json` | Модели AI, промпт файл, retry логика |

### AI Модели

По умолчанию используются модели в порядке приоритета:

1. **Claude 3.5 Sonnet** - основная модель (высокое качество)
2. **DeepSeek Chat** - fallback модель (низкая стоимость)

Поддерживается automatic prompt caching для экономии до 75% стоимости.

### Ручной запуск

```bash
# Тестовый режим (3 новости)
php production/rss_summarization.php

# Production режим (отредактируйте константу TEST_MODE в скрипте)
php production/rss_summarization.php
```

### Логи

- **Основной лог:** `/home/engine/project/logs/rss_summarization.log`

### Просмотр логов

```bash
# Последние записи
tail -100 logs/rss_summarization.log

# В реальном времени
tail -f logs/rss_summarization.log

# Только ошибки
grep ERROR logs/rss_summarization.log

# Статистика токенов
grep "total_tokens" logs/rss_summarization.log
```

### Проверка обработанных новостей

```sql
-- Статистика суммаризации
SELECT 
    status,
    COUNT(*) as count
FROM rss2tlg_summarization
GROUP BY status;

-- Последние обработанные новости
SELECT 
    i.title,
    s.article_language,
    s.category_primary,
    s.importance_rating,
    s.processed_at
FROM rss2tlg_summarization s
JOIN rss2tlg_items i ON s.item_id = i.id
WHERE s.status = 'success'
ORDER BY s.processed_at DESC
LIMIT 10;

-- Использование токенов
SELECT 
    COUNT(*) as total_items,
    SUM(tokens_used) as total_tokens,
    AVG(tokens_used) as avg_tokens_per_item,
    SUM(cache_hit) as cache_hits
FROM rss2tlg_summarization
WHERE status = 'success';
```

---

## 🔍 Скрипт 3: rss_deduplication.php

### Описание

Production скрипт для проверки суммаризованных новостей на дубликаты с помощью AI анализа.

### Функционал

- ✅ AI проверка на дубликаты (семантический анализ)
- ✅ Сравнение сущностей, событий, фактов из новостей
- ✅ Определение процента схожести (0-100)
- ✅ Решение о публикуемости (can_be_published)
- ✅ Fallback между моделями (Gemma 3 → DeepSeek Chat → DeepSeek v3.2)
- ✅ Сравнение с новостями за последние 72 часа
- ✅ Telegram уведомления о дубликатах
- ✅ Детальное логирование

### Режимы работы

**PRODUCTION MODE (по умолчанию):**
- Обрабатывает все суммаризованные новости без ограничений
- Константа: `TEST_MODE = false`

**TEST MODE:**
- Обрабатывает только указанное количество новостей
- Установите: `TEST_MODE = true`
- Лимит: `TEST_ITEMS_LIMIT = 10`

### Конфигурационные файлы

| Файл | Назначение |
|------|------------|
| `configs/main.json` | Пути логов, интервалы, таймауты |
| `configs/database.json` | Подключение к БД |
| `configs/telegram.json` | Telegram бот (token, chat_id) |
| `configs/openrouter.json` | OpenRouter API (ключ, URL) |
| `configs/deduplication.json` | Модели AI, промпт файл, параметры дедупликации |

### AI Модели

По умолчанию используются модели в порядке приоритета:

1. **Google Gemma 3 27B** - основная модель (высокая точность)
2. **DeepSeek Chat** - fallback 1 (низкая стоимость)
3. **DeepSeek v3.2 Exp** - fallback 2 (экспериментальная)

### Параметры дедупликации

```json
{
    "lookback_hours": 72,           // Сравнивать с новостями за последние 72 часа
    "max_compare_items": 50,        // Максимум 50 новостей для сравнения
    "similarity_threshold": 70,     // Порог схожести для определения дубликата
    "retry_attempts": 3,            // Количество повторов при ошибке
    "retry_delay_ms": 1000          // Задержка между повторами
}
```

### Ручной запуск

```bash
# Production режим (все новости)
php production/rss_deduplication.php

# В background с логированием
php production/rss_deduplication.php > logs/dedup_$(date +%Y%m%d_%H%M%S).log 2>&1 &
```

### Логи

- **Основной лог:** `/home/engine/project/logs/rss_deduplication.log`

### Просмотр логов

```bash
# Последние записи
tail -100 logs/rss_deduplication.log

# В реальном времени
tail -f logs/rss_deduplication.log

# Только ошибки
grep ERROR logs/rss_deduplication.log

# Найденные дубликаты
grep "ДУБЛИКАТ" logs/rss_deduplication.log
```

### Проверка результатов

```sql
-- Статистика дедупликации
SELECT 
    is_duplicate,
    COUNT(*) as count,
    AVG(similarity_score) as avg_similarity
FROM rss2tlg_deduplication
GROUP BY is_duplicate;

-- Найденные дубликаты
SELECT 
    d.item_id,
    i.title,
    d.similarity_score,
    d.duplicate_of_item_id,
    d.checked_at
FROM rss2tlg_deduplication d
JOIN rss2tlg_items i ON d.item_id = i.id
WHERE d.is_duplicate = 1
ORDER BY d.checked_at DESC
LIMIT 10;

-- Уникальные новости готовые к публикации
SELECT 
    d.item_id,
    i.title,
    d.similarity_score,
    d.can_be_published
FROM rss2tlg_deduplication d
JOIN rss2tlg_items i ON d.item_id = i.id
WHERE d.is_duplicate = 0 AND d.can_be_published = 1
ORDER BY d.checked_at DESC
LIMIT 10;
```

### Настройка Cron

Рекомендуемый интервал: каждые 10 минут (после summarization)

```bash
crontab -e

# Добавить:
*/10 * * * * /usr/bin/php /home/engine/project/production/rss_deduplication.php >> /home/engine/project/logs/cron_deduplication.log 2>&1
```

---

## 🧪 Тестовые скрипты

### run_3_tests.sh

Запускает скрипт 3 раза с интервалом **2 минуты** (реальный cron интервал).

```bash
./production/run_3_tests.sh
```

**Время выполнения:** ~4 минуты

### run_3_tests_fast.sh

Запускает скрипт 3 раза с интервалом **10 секунд** (для быстрого тестирования).

```bash
./production/run_3_tests_fast.sh
```

**Время выполнения:** ~30 секунд

---

## 📊 SQL Дампы

Все дампы находятся в папке `sql/`. Созданы после успешного тестирования.

### Восстановление из дампов

```bash
# Все таблицы
for dump in production/sql/*.sql; do
  mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg < "$dump"
done

# Отдельная таблица
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg < production/sql/rss2tlg_items_dump.sql
```

### Размеры дампов

- `rss2tlg_items_dump.sql` - 505 KB (403 записи)
- Остальные таблицы - ~3-5 KB (структуры)

---

## 📈 Мониторинг

### Проверка состояния источников

```sql
SELECT 
    f.name,
    fs.last_status,
    fs.error_count,
    fs.fetched_at,
    fs.last_error
FROM rss2tlg_feeds f
JOIN rss2tlg_feed_state fs ON f.id = fs.feed_id
ORDER BY fs.fetched_at DESC;
```

### Статистика по источникам

```sql
SELECT 
    f.name AS 'Источник',
    COUNT(i.id) AS 'Записей',
    MAX(i.created_at) AS 'Последняя запись'
FROM rss2tlg_feeds f
LEFT JOIN rss2tlg_items i ON f.id = i.feed_id
GROUP BY f.id, f.name
ORDER BY f.id;
```

### Свежие новости

```sql
SELECT 
    f.name AS 'Источник',
    i.title AS 'Заголовок',
    i.created_at AS 'Добавлено'
FROM rss2tlg_items i
JOIN rss2tlg_feeds f ON i.feed_id = f.id
ORDER BY i.created_at DESC
LIMIT 20;
```

---

## 🔧 Настройка и обслуживание

### Добавление нового источника

1. Добавить в БД:

```sql
INSERT INTO rss2tlg_feeds (name, feed_url, website_url, enabled) 
VALUES ('Новый источник', 'https://example.com/rss', 'https://example.com', 1);
```

2. Проверить ручным запуском:

```bash
php production/rss_ingest.php
```

### Отключение источника

```sql
UPDATE rss2tlg_feeds SET enabled = 0 WHERE id = 1;
```

### Очистка старых данных

```sql
-- Удалить новости старше 30 дней
DELETE FROM rss2tlg_items 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

### Изменение интервала cron

```bash
crontab -e

# Каждую минуту
* * * * * /usr/bin/php /home/engine/project/production/rss_ingest.php >> /home/engine/project/logs/cron_rss_ingest.log 2>&1

# Каждые 5 минут
*/5 * * * * /usr/bin/php /home/engine/project/production/rss_ingest.php >> /home/engine/project/logs/cron_rss_ingest.log 2>&1

# Каждые 10 минут
*/10 * * * * /usr/bin/php /home/engine/project/production/rss_ingest.php >> /home/engine/project/logs/cron_rss_ingest.log 2>&1
```

---

## 🐛 Устранение неполадок

### Ошибка: "Config file not found"

Проверьте наличие конфигов:

```bash
ls -la production/configs/
```

### Ошибка: "Access denied for user"

Проверьте права пользователя БД:

```sql
GRANT ALL PRIVILEGES ON rss2tlg.* TO 'rss2tlg_user'@'localhost';
FLUSH PRIVILEGES;
```

### Ошибка: "Table doesn't exist"

Импортируйте схемы:

```bash
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg < src/Rss2Tlg/sql/rss2tlg_schema_clean.sql
```

### Telegram уведомления не приходят

Проверьте конфиг:

```bash
cat production/configs/telegram.json
```

Проверьте доступность бота:

```bash
curl "https://api.telegram.org/bot8327641497:AAFTHb3xSTpP3Q6Peg8-OK4nTWTfF7iMWfI/getMe"
```

---

## 📚 Дополнительные ресурсы

- **Основная документация:** `/docs/Rss2Tlg/README.md`
- **API классов:** `/docs/Rss2Tlg/API.md`
- **Установка:** `/docs/Rss2Tlg/INSTALL.md`
- **Отчет о тестировании:** `/production/TEST_REPORT.md`

---

## 📞 Поддержка

Если у вас возникли проблемы или вопросы, проверьте:

1. Логи: `tail -100 logs/rss_ingest.log`
2. База данных: `mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg`
3. Тестовый отчет: `production/TEST_REPORT.md`

---

## 📋 История версий

### v1.1.0 (2025-11-09)
- ✨ Добавлен скрипт `rss_summarization.php` - AI суммаризация новостей
- ✨ Добавлены конфиги: `openrouter.json`, `summarization.json`
- 📝 Обновлена документация с описанием pipeline workflow

### v1.0.0 (2025-11-09)
- ✅ Запущен скрипт `rss_ingest.php` - сбор RSS новостей
- ✅ Конфигурационные файлы перенесены в `production/configs/`
- ✅ Полное тестирование (3 прогона, 100% успех)
- ✅ SQL дампы экспортированы

---

**Версия:** 1.1.0  
**Дата:** 2025-11-09  
**Статус:** ✅ Production Ready (2 из 6 этапов)
