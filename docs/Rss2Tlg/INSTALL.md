# 🚀 УСТАНОВКА И ЗАПУСК AI PIPELINE

**Версия:** 1.0  
**Дата:** 2025-11-08  
**Статус:** Production Ready

---

## 📋 ТРЕБОВАНИЯ

### Системные требования:
- PHP 8.1+
- MariaDB 10.11+ или MySQL 8.0+
- Composer
- 512 MB RAM минимум
- 1 GB свободного места на диске

### PHP расширения:
```bash
php -m | grep -E "pdo|pdo_mysql|curl|json|mbstring|openssl"
```

Должны быть установлены:
- ✅ pdo
- ✅ pdo_mysql
- ✅ curl
- ✅ json
- ✅ mbstring
- ✅ openssl

---

## ⚙️ УСТАНОВКА

### 1. Клонирование репозитория

```bash
git clone <repository-url>
cd project
```

### 2. Установка зависимостей

```bash
composer install
```

### 3. Создание БД и пользователя

**MariaDB/MySQL:**

```bash
# Запуск MariaDB (если не запущен)
sudo systemctl start mariadb
# или
sudo mysqld_safe &

# Подключение к БД
mysql -u root -p
```

**SQL команды:**

```sql
-- Создание БД
CREATE DATABASE rss2tlg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Создание пользователя
CREATE USER 'rss2tlg_user'@'localhost' IDENTIFIED BY 'your_secure_password';
CREATE USER 'rss2tlg_user'@'127.0.0.1' IDENTIFIED BY 'your_secure_password';

-- Права доступа
GRANT ALL PRIVILEGES ON rss2tlg.* TO 'rss2tlg_user'@'localhost';
GRANT ALL PRIVILEGES ON rss2tlg.* TO 'rss2tlg_user'@'127.0.0.1';

FLUSH PRIVILEGES;
EXIT;
```

### 4. Импорт схем БД

```bash
# Базовые таблицы (rss2tlg_items, rss2tlg_feed_state, rss2tlg_publications)
mysql -u rss2tlg_user -p rss2tlg < src/Rss2Tlg/sql/rss2tlg_schema_clean.sql

# AI Pipeline таблицы
mysql -u rss2tlg_user -p rss2tlg < src/Rss2Tlg/sql/ai_pipeline_schema.sql
```

**Проверка:**

```bash
mysql -u rss2tlg_user -p rss2tlg -e "SHOW TABLES;"
```

Должны быть созданы:
- rss2tlg_items
- rss2tlg_feed_state
- rss2tlg_publications
- rss2tlg_summarization
- rss2tlg_deduplication
- rss2tlg_translation
- rss2tlg_illustration
- v_rss2tlg_full_pipeline (VIEW)
- v_rss2tlg_ready_to_publish (VIEW)

### 5. Создание директорий

```bash
# Логи
mkdir -p logs
chmod 755 logs

# Кеш
mkdir -p Cache/rss2tlg
chmod 755 Cache/rss2tlg

# Изображения (если используется IllustrationService)
mkdir -p images
chmod 755 images
```

---

## 🔑 КОНФИГУРАЦИЯ

### 1. Создание конфигурационного файла

```bash
cp src/Rss2Tlg/config/rss2tlg_production_test.json src/Rss2Tlg/config/rss2tlg.json
```

### 2. Редактирование конфигурации

**Откройте файл:**

```bash
nano src/Rss2Tlg/config/rss2tlg.json
```

**Настройте параметры:**

```json
{
  "database": {
    "host": "127.0.0.1",
    "port": 3306,
    "database": "rss2tlg",
    "username": "rss2tlg_user",
    "password": "YOUR_SECURE_PASSWORD",
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
  },
  
  "pipeline": {
    "summarization": {
      "enabled": true,
      "models": [
        "anthropic/claude-3.5-sonnet",
        "deepseek/deepseek-chat"
      ]
    }
  }
}
```

**Получение ключей:**

1. **OpenRouter API Key:**
   - Зарегистрируйтесь на https://openrouter.ai
   - Создайте API ключ в разделе Keys
   - Пополните баланс (минимум $5)

2. **Telegram Bot Token:**
   - Напишите @BotFather в Telegram
   - Создайте бота командой `/newbot`
   - Скопируйте токен

3. **Telegram Chat ID:**
   - Напишите @userinfobot
   - Скопируйте ваш ID

---

## 🧪 ТЕСТИРОВАНИЕ

### Базовый тест подключения к БД

```bash
php -r "
\$pdo = new PDO('mysql:host=127.0.0.1;dbname=rss2tlg', 'rss2tlg_user', 'PASSWORD');
echo 'DB Connection: OK' . PHP_EOL;
"
```

### Тест OpenRouter API

```bash
php -r "
require 'autoload.php';
use App\Component\OpenRouter;
\$config = ['api_key' => 'YOUR_API_KEY'];
\$or = new OpenRouter(\$config);
echo 'OpenRouter: OK' . PHP_EOL;
"
```

### Тест Telegram Bot

```bash
php -r "
require 'autoload.php';
use App\Component\Telegram;
\$config = ['token' => 'YOUR_BOT_TOKEN'];
\$tg = new Telegram(\$config);
\$tg->sendText('YOUR_CHAT_ID', 'Test message');
echo 'Telegram: OK' . PHP_EOL;
"
```

### Полный production тест

```bash
php tests/Rss2Tlg/production_pipeline_test.php
```

**Ожидаемый результат:**
```
✅ Все тесты пройдены успешно!
📊 Обработано: 5 новостей
💰 Токенов: ~6,500
⏱️ Время: ~45 секунд
```

---

## 🔄 ЗАПУСК В PRODUCTION

### 1. Создание скрипта запуска

**Файл:** `bin/process_rss.php`

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use App\Config\ConfigLoader;
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\OpenRouter;
use App\Rss2Tlg\FetchRunner;
use App\Rss2Tlg\Pipeline\SummarizationService;
use App\Rss2Tlg\DTO\FeedConfig;

// Загрузка конфигурации
$config = ConfigLoader::load(__DIR__ . '/../src/Rss2Tlg/config/rss2tlg.json');

// Инициализация компонентов
$logger = new Logger($config['logger']);
$db = new MySQL($config['database'], $logger);
$openRouter = new OpenRouter($config['openrouter'], $logger);

// ЭТАП 1: Загрузка новостей из RSS
$cacheDir = $config['cache']['cache_dir'];
$fetchRunner = new FetchRunner($db, $cacheDir, $logger);

$feedConfigs = [];
foreach ($config['feeds'] as $feedArray) {
    $feedConfigs[] = FeedConfig::fromArray($feedArray);
}

$results = $fetchRunner->runForAllFeeds($feedConfigs);

echo "✅ Загружено новостей: " . array_sum(array_map(fn($r) => $r->newItems, $results)) . "\n";

// ЭТАП 2: AI Суммаризация
$summarizationService = new SummarizationService(
    $db,
    $openRouter,
    $config['pipeline']['summarization'],
    $logger
);

// Получаем необработанные новости
$query = "
    SELECT i.id
    FROM rss2tlg_items i
    LEFT JOIN rss2tlg_summarization s ON i.id = s.item_id
    WHERE s.id IS NULL
    ORDER BY i.created_at DESC
    LIMIT 10
";

$items = $db->query($query, []);

foreach ($items as $item) {
    $summarizationService->processItem((int)$item['id']);
}

echo "✅ Обработано: " . count($items) . " новостей\n";

// Метрики
$metrics = $summarizationService->getMetrics();
echo "💰 Токенов: " . $metrics['total_tokens'] . "\n";
echo "✨ Готово!\n";
```

**Сделать исполняемым:**

```bash
chmod +x bin/process_rss.php
```

### 2. Настройка Cron

```bash
# Открыть crontab
crontab -e

# Добавить задачи (пример: каждые 15 минут)
*/15 * * * * cd /path/to/project && php bin/process_rss.php >> logs/cron.log 2>&1
```

### 3. Мониторинг

**Проверка логов:**

```bash
tail -f logs/rss2tlg.log
```

**Проверка БД:**

```bash
mysql -u rss2tlg_user -p rss2tlg -e "
SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
FROM rss2tlg_summarization;
"
```

---

## 🔧 TROUBLESHOOTING

### Проблема: "Access denied for user"

**Решение:**

```sql
-- Проверить пользователя
SELECT User, Host FROM mysql.user WHERE User = 'rss2tlg_user';

-- Пересоздать пользователя
DROP USER IF EXISTS 'rss2tlg_user'@'localhost';
CREATE USER 'rss2tlg_user'@'localhost' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON rss2tlg.* TO 'rss2tlg_user'@'localhost';
FLUSH PRIVILEGES;
```

### Проблема: "Table doesn't exist"

**Решение:**

```bash
# Проверить таблицы
mysql -u rss2tlg_user -p rss2tlg -e "SHOW TABLES;"

# Переимпортировать схемы
mysql -u rss2tlg_user -p rss2tlg < src/Rss2Tlg/sql/rss2tlg_schema_clean.sql
mysql -u rss2tlg_user -p rss2tlg < src/Rss2Tlg/sql/ai_pipeline_schema.sql
```

### Проблема: "OpenRouter API error"

**Решение:**

1. Проверить баланс на https://openrouter.ai
2. Проверить правильность API ключа
3. Проверить лимиты запросов

### Проблема: "Telegram bot not responding"

**Решение:**

```bash
# Проверить токен
curl -X GET "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getMe"

# Должен вернуть информацию о боте
```

### Проблема: "Slow processing"

**Оптимизация:**

1. Включить prompt caching:
   ```json
   {
     "pipeline": {
       "summarization": {
         "cache_enabled": true
       }
     }
   }
   ```

2. Использовать batch обработку:
   ```php
   $summarizationService->processBatch($itemIds);
   ```

3. Добавить индексы:
   ```sql
   CREATE INDEX idx_items_created ON rss2tlg_items(created_at DESC);
   ```

---

## 📚 ДОПОЛНИТЕЛЬНЫЕ РЕСУРСЫ

**Документация:**
- `README.md` - Основная документация
- `API.md` - Справочник по API
- `ARCHITECTURE_REVIEW.md` - Анализ архитектуры
- `FINAL_ANALYSIS_REPORT.md` - Полный отчет

**Примеры:**
- `examples/` - Примеры использования
- `tests/` - Тестовые скрипты

**Поддержка:**
- GitHub Issues: <repository-url>/issues
- Email: support@example.com

---

## ✅ ЧЕКЛИСТ ГОТОВНОСТИ К PRODUCTION

- [ ] MariaDB установлен и запущен
- [ ] БД и пользователь созданы
- [ ] Схемы импортированы
- [ ] Директории созданы и права настроены
- [ ] Конфигурация настроена
- [ ] OpenRouter API ключ получен и баланс пополнен
- [ ] Telegram бот создан и токен получен
- [ ] Базовые тесты пройдены
- [ ] Production тест успешно выполнен
- [ ] Cron задачи настроены
- [ ] Мониторинг настроен

---

**Документ подготовлен:** AI Assistant  
**Версия:** 1.0  
**Последнее обновление:** 2025-11-08

🚀 **Готово к запуску!**
