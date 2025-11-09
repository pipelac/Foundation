# 🚀 RSS Summarization - Quick Start Guide

## ✅ Что уже готово

- ✅ Скрипт: `production/rss_summarization.php`
- ✅ Промпт: `production/prompts/summarization_prompt_v2.txt`
- ✅ Конфиги: `production/configs/*.json`
- ✅ Тестирование: Пройдено (3/3 новости, 100% успех)
- ✅ Документация: `TEST_REPORT_SUMMARIZATION.md`

## ⚡ Быстрый запуск (5 минут)

### 1. Подготовка инфраструктуры

```bash
# Запуск MariaDB
sudo mkdir -p /var/run/mysqld && sudo chmod 777 /var/run/mysqld
sudo /usr/sbin/mariadbd --user=root > /tmp/mariadb.log 2>&1 &
sleep 3

# Создание БД и пользователя
sudo mysql -e "CREATE DATABASE IF NOT EXISTS rss2tlg CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER IF NOT EXISTS 'rss2tlg_user'@'localhost' IDENTIFIED BY 'rss2tlg_password_2024';"
sudo mysql -e "GRANT ALL PRIVILEGES ON rss2tlg.* TO 'rss2tlg_user'@'localhost'; FLUSH PRIVILEGES;"

# Импорт данных
cd /home/engine/project/production
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg < sql/rss2tlg_feeds_dump.sql
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg < sql/rss2tlg_items_dump.sql
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg < sql/rss2tlg_feed_state_dump.sql
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg < sql/rss2tlg_summarization_dump.sql

echo "✅ Инфраструктура готова!"
```

### 2. Проверка конфигов

```bash
# Проверяем что все конфиги на месте
ls -lh production/configs/
# Должно быть:
# - main.json
# - database.json
# - openrouter.json
# - telegram.json
# - summarization.json

# Проверяем промпт
ls -lh production/prompts/
# Должно быть: summarization_prompt_v2.txt (7.6K)
```

### 3. Запуск теста (3 новости)

```bash
cd /home/engine/project
php production/rss_summarization.php
```

**Ожидаемый результат:**
```
✅ Обработано: 3
✅ Успешно: 3
❌ Ошибок: 0
🎯 Успешность: 100%
```

### 4. Проверка результатов

```bash
# Проверяем БД
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg -e \
  "SELECT item_id, status, article_language, category_primary, importance_rating 
   FROM rss2tlg_summarization 
   ORDER BY item_id DESC 
   LIMIT 3;"

# Проверяем логи
tail -50 /home/engine/project/logs/rss_summarization.log
```

## 🔧 Переключение в Production режим

### Открыть файл конфигурации

```bash
nano production/rss_summarization.php
```

### Изменить строку 42

```php
// БЫЛО:
const TEST_MODE = true;

// СТАЛО:
const TEST_MODE = false;
```

### Запустить без ограничений

```bash
php production/rss_summarization.php
# Обработает все 403 непроцессированные новости
```

## 📅 Настройка cron (автоматический запуск)

### Каждые 5 минут

```bash
crontab -e
```

Добавить строку:

```cron
*/5 * * * * /usr/bin/php /home/engine/project/production/rss_summarization.php >> /home/engine/project/logs/cron_summarization.log 2>&1
```

### Проверка cron

```bash
# Список задач
crontab -l

# Логи cron
tail -f /home/engine/project/logs/cron_summarization.log
```

## 🎯 AI модели

### Текущие модели (по приоритету)

1. `deepseek/deepseek-v3.2-exp` (PRIMARY)
2. `google/gemma-3-27b-it` (FALLBACK #1)
3. `deepseek/deepseek-chat` (FALLBACK #2)

### Изменение моделей

Отредактировать `production/configs/summarization.json`:

```json
{
    "enabled": true,
    "models": [
        "deepseek/deepseek-v3.2-exp",
        "google/gemma-3-27b-it",
        "deepseek/deepseek-chat"
    ],
    "retry_count": 2,
    "timeout": 120,
    "fallback_strategy": "sequential",
    "prompt_file": "/home/engine/project/production/prompts/summarization_prompt_v2.txt"
}
```

## 🔍 Мониторинг

### Проверка статуса обработки

```bash
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg -e \
  "SELECT status, COUNT(*) as count 
   FROM rss2tlg_summarization 
   GROUP BY status;"
```

### Статистика по моделям

```bash
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg -e \
  "SELECT model_used, COUNT(*) as count, AVG(tokens_used) as avg_tokens 
   FROM rss2tlg_summarization 
   WHERE status = 'success' 
   GROUP BY model_used;"
```

### Средняя важность по категориям

```bash
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg -e \
  "SELECT category_primary, COUNT(*) as count, AVG(importance_rating) as avg_importance 
   FROM rss2tlg_summarization 
   WHERE status = 'success' 
   GROUP BY category_primary 
   ORDER BY avg_importance DESC;"
```

## 🐛 Устранение неполадок

### Проблема: Нет непроцессированных новостей

```bash
# Сбросить статус последних 3 новостей
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg -e \
  "DELETE FROM rss2tlg_summarization 
   WHERE item_id IN (
     SELECT id FROM rss2tlg_items 
     ORDER BY created_at DESC 
     LIMIT 3
   );"
```

### Проблема: MariaDB не запускается

```bash
# Проверить процесс
pgrep -fl mariadbd

# Убить и перезапустить
sudo pkill -9 mariadbd
sudo /usr/sbin/mariadbd --user=root > /tmp/mariadb.log 2>&1 &

# Проверить логи
tail -50 /tmp/mariadb.log
```

### Проблема: OpenRouter API ошибка

```bash
# Проверить API ключ
grep api_key production/configs/openrouter.json

# Проверить баланс (ручная команда)
curl -H "Authorization: Bearer YOUR_API_KEY" \
     https://openrouter.ai/api/v1/auth/key
```

### Проблема: Telegram не отправляет

```bash
# Проверить токен и chat_id
cat production/configs/telegram.json

# Тестовая отправка
php -r '
require_once "/home/engine/project/autoload.php";
use App\Component\Telegram;
use App\Component\Logger;

$config = json_decode(file_get_contents("/home/engine/project/production/configs/telegram.json"), true);
$logConfig = ["directory" => "/home/engine/project/logs", "file_name" => "test.log", "min_level" => "info"];
$logger = new Logger($logConfig);
$telegram = new Telegram($config, $logger);
$telegram->sendText($config["default_chat_id"], "🧪 Test message from Summarization", ["parse_mode" => "HTML"]);
echo "✅ Sent!\n";
'
```

## 📊 Ожидаемые метрики

### Production режим (403 новости)

- ⏱️ **Время:** ~5 часов (45 сек/новость)
- 💰 **Токены:** ~1,500,000 (~3,700 на новость)
- 💵 **Стоимость:** ~$0.15 (при $0.0001/1K токенов)

### Успешность

- 🎯 **Ожидаемая:** 95-98%
- 🔄 **Fallback:** 5-10% новостей
- ❌ **Ошибки:** < 2%

## 📚 Документация

- **Детальный отчет:** `TEST_REPORT_SUMMARIZATION.md`
- **Основная документация:** `README.md`
- **API Pipeline:** `docs/Rss2Tlg/Pipeline_Summarization_README.md`

## ✅ Checklist перед запуском

- [ ] MariaDB запущен
- [ ] БД `rss2tlg` создана
- [ ] Пользователь `rss2tlg_user` создан
- [ ] Данные импортированы (403 новости)
- [ ] Конфиги проверены
- [ ] Промпт на месте
- [ ] Telegram бот работает
- [ ] OpenRouter API ключ валиден
- [ ] Логи создаются
- [ ] Тест пройден (3 новости)

---

🎉 **Готово к запуску!**

Вопросы? Смотри `TEST_REPORT_SUMMARIZATION.md` или логи в `/home/engine/project/logs/`
