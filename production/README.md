# 📦 Production Scripts - RSS2TLG

Production-ready скрипты для сбора и обработки новостей из RSS источников.

---

## 📁 Структура

```
production/
├── configs/                    # Конфигурационные файлы
│   ├── main.json              # Основные настройки
│   ├── database.json          # Подключение к БД
│   ├── telegram.json          # Telegram бот
│   └── feeds.json             # RSS источники (справочно)
├── sql/                        # SQL дампы
│   ├── rss2tlg_feeds_dump.sql
│   ├── rss2tlg_items_dump.sql
│   └── ... (9 файлов)
├── rss_ingest.php             # Основной скрипт сбора RSS
├── run_3_tests.sh             # Тест: 3 запуска с интервалом 2 мин
├── run_3_tests_fast.sh        # Быстрый тест: 3 запуска за 30 сек
├── setup_cron.sh              # Настройка cron
├── TEST_REPORT.md             # Отчет о тестировании
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

## 📡 Скрипт: rss_ingest.php

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

**Версия:** 1.0.0  
**Дата:** 2025-11-09  
**Статус:** ✅ Production Ready
