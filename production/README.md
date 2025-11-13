# 🚀 RSS2TLG PRODUCTION

Production-скрипты для автоматической обработки RSS-лент и публикации в Telegram.

---

## 📁 СТРУКТУРА

```
production/
├── sql/
│   └── init_schema.sql          # Полная схема БД с правильной кодировкой
├── config/
│   ├── rss2tlg_production.json  # Production конфигурация
│   └── feeds.json               # Список RSS-лент
├── rss_ingest.php               # Основной скрипт обработки
└── README.md                    # Эта документация
```

---

## ⚙️ УСТАНОВКА

### 1. Создание БД

```bash
mysql -u root -p
```

```sql
CREATE DATABASE rss2tlg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'rss2tlg_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON rss2tlg.* TO 'rss2tlg_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 2. Импорт схемы

#### Linux/macOS:

```bash
mysql -u rss2tlg_user -p rss2tlg < production/sql/init_schema.sql
```

#### Windows:

**⚠️ ВАЖНО:** Используйте флаг `--default-character-set=utf8mb4` для корректной обработки UTF-8:

```cmd
mysql --default-character-set=utf8mb4 -u rss2tlg_user -p rss2tlg < production/sql/init_schema.sql
```

**Альтернатива:** Настроить `my.ini`

```ini
[mysql]
default-character-set=utf8mb4

[client]
default-character-set=utf8mb4
```

### 3. Проверка таблиц

```bash
mysql -u rss2tlg_user -p rss2tlg -e "SHOW TABLES;"
```

**Ожидаемый результат:**

```
+------------------------+
| Tables_in_rss2tlg      |
+------------------------+
| openrouter_metrics     |
| rss2tlg_deduplication  |
| rss2tlg_feed_state     |
| rss2tlg_feeds          |
| rss2tlg_items          |
| rss2tlg_publications   |
| rss2tlg_summarization  |
+------------------------+
```

---

## 🔑 КОНФИГУРАЦИЯ

### 1. Скопировать конфиг

```bash
cp production/config/rss2tlg_production.json.example production/config/rss2tlg_production.json
```

### 2. Редактировать параметры

```json
{
  "database": {
    "host": "127.0.0.1",
    "port": 3306,
    "database": "rss2tlg",
    "username": "rss2tlg_user",
    "password": "YOUR_PASSWORD",
    "charset": "utf8mb4"
  },
  
  "openrouter": {
    "api_key": "YOUR_OPENROUTER_API_KEY",
    "app_name": "RSS2TLG-Production"
  },
  
  "telegram": {
    "bot_token": "YOUR_BOT_TOKEN",
    "chat_id": YOUR_CHAT_ID,
    "channel_id": "@your_channel"
  }
}
```

### 3. Настроить RSS-ленты

Редактировать `production/config/feeds.json`:

```json
[
  {
    "name": "BBC News",
    "feed_url": "https://feeds.bbci.co.uk/news/rss.xml",
    "website_url": "https://www.bbc.com/news",
    "enabled": true
  },
  {
    "name": "Reuters",
    "feed_url": "https://www.reutersagency.com/feed/",
    "website_url": "https://www.reuters.com",
    "enabled": true
  }
]
```

---

## 🏃 ЗАПУСК

### Ручной запуск

```bash
cd /path/to/project
php production/rss_ingest.php
```

### Запуск с логированием

```bash
php production/rss_ingest.php 2>&1 | tee logs/production_$(date +%Y%m%d_%H%M%S).log
```

### Настройка Cron (автоматический запуск)

```bash
crontab -e
```

**Добавить строку (каждые 15 минут):**

```cron
*/15 * * * * cd /path/to/project && php production/rss_ingest.php >> logs/production_cron.log 2>&1
```

**Другие варианты:**

```cron
# Каждый час
0 * * * * cd /path/to/project && php production/rss_ingest.php >> logs/production_cron.log 2>&1

# Каждые 30 минут
*/30 * * * * cd /path/to/project && php production/rss_ingest.php >> logs/production_cron.log 2>&1

# Каждый день в 08:00
0 8 * * * cd /path/to/project && php production/rss_ingest.php >> logs/production_cron.log 2>&1
```

---

## 📊 МОНИТОРИНГ

### Проверка логов

```bash
# Последние записи
tail -n 50 logs/rss2tlg.log

# В реальном времени
tail -f logs/rss2tlg.log

# Поиск ошибок
grep -i "error" logs/rss2tlg.log
```

### Статистика БД

```bash
mysql -u rss2tlg_user -p rss2tlg -e "
SELECT 
    'Всего новостей' as Metric,
    COUNT(*) as Value
FROM rss2tlg_items
UNION ALL
SELECT 
    'Обработано AI',
    COUNT(*)
FROM rss2tlg_summarization
WHERE status = 'success'
UNION ALL
SELECT 
    'Опубликовано',
    COUNT(DISTINCT item_id)
FROM rss2tlg_publications;
"
```

### Мониторинг performance

```bash
mysql -u rss2tlg_user -p rss2tlg -e "
SELECT 
    DATE(created_at) as date,
    COUNT(*) as total_items,
    SUM(CASE WHEN is_published = 1 THEN 1 ELSE 0 END) as published
FROM rss2tlg_items
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY DATE(created_at)
ORDER BY date DESC;
"
```

---

## 🐛 TROUBLESHOOTING

### Проблема: Кракозябры в БД (Windows)

**Причина:** MySQL-клиент использует неправильную кодировку при импорте.

**Быстрое решение:**

```cmd
# 1. Пересоздать БД
mysql -u root -p -e "DROP DATABASE IF EXISTS rss2tlg;"
mysql -u root -p -e "CREATE DATABASE rss2tlg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Импортировать с правильной кодировкой
mysql --default-character-set=utf8mb4 -u rss2tlg_user -p rss2tlg < production/sql/init_schema.sql
```

**📖 Подробное руководство:** [WINDOWS_ENCODING_FIX.md](sql/WINDOWS_ENCODING_FIX.md)

### Проблема: "Access denied"

```bash
# Проверить пользователя
mysql -u root -p -e "SELECT User, Host FROM mysql.user WHERE User = 'rss2tlg_user';"

# Пересоздать
mysql -u root -p -e "
DROP USER IF EXISTS 'rss2tlg_user'@'localhost';
CREATE USER 'rss2tlg_user'@'localhost' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON rss2tlg.* TO 'rss2tlg_user'@'localhost';
FLUSH PRIVILEGES;
"
```

### Проблема: "Connection refused"

```bash
# Проверить статус MySQL
sudo systemctl status mariadb
# или
sudo service mysql status

# Запустить
sudo systemctl start mariadb
```

### Проблема: Медленная обработка

**Оптимизация:**

1. Включить AI кеширование в конфиге
2. Увеличить batch size
3. Добавить индексы:

```sql
CREATE INDEX idx_items_created ON rss2tlg_items(created_at DESC);
CREATE INDEX idx_sum_status ON rss2tlg_summarization(status);
CREATE INDEX idx_dedup_status ON rss2tlg_deduplication(status);
```

---

## 📝 ЛОГИ И ОТЧЕТЫ

### Структура логов

```
logs/
├── rss2tlg.log              # Основной лог
├── production_cron.log      # Лог cron задачи
└── production_YYYYMMDD_HHMMSS.log  # Лог конкретного запуска
```

### Формат логов

```
[2025-01-13 10:30:45] [INFO] Starting RSS ingest...
[2025-01-13 10:30:46] [INFO] Feed: BBC News - 5 new items
[2025-01-13 10:30:47] [INFO] AI Summarization: 5 items processed
[2025-01-13 10:30:48] [INFO] Deduplication: 3 unique, 2 duplicates
[2025-01-13 10:30:49] [INFO] Published: 3 items to channel
[2025-01-13 10:30:50] [INFO] Tokens used: 4,500
[2025-01-13 10:30:51] [INFO] Cost: $0.015
```

---

## 🔒 БЕЗОПАСНОСТЬ

### Рекомендации:

1. **Не хранить пароли в конфиге**
   - Использовать переменные окружения
   - Или хранить конфиг вне репозитория

2. **Ограничить права MySQL пользователя**
   ```sql
   GRANT SELECT, INSERT, UPDATE, DELETE ON rss2tlg.* TO 'rss2tlg_user'@'localhost';
   ```

3. **Настроить ротацию логов**
   ```bash
   # /etc/logrotate.d/rss2tlg
   /path/to/project/logs/*.log {
       daily
       rotate 7
       compress
       missingok
       notifempty
   }
   ```

4. **Использовать HTTPS для RSS лент**
   - Проверять SSL сертификаты

---

## 📚 ДОПОЛНИТЕЛЬНО

**Полная документация:**
- [INSTALL.md](../docs/Rss2Tlg/INSTALL.md) - Детальная установка
- [README.md](../docs/Rss2Tlg/README.md) - Общая документация
- [API.md](../docs/Rss2Tlg/API.md) - Справочник API

**Поддержка:**
- GitHub Issues
- Email: support@example.com

---

**Версия:** 1.0  
**Дата:** 2025-01-13  
**Статус:** ✅ Production Ready
