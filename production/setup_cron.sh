#!/bin/bash
# Скрипт настройки cron для RSS Ingest
# Запуск каждые 2 минуты

SCRIPT_PATH="/home/engine/project/production/rss_ingest.php"
LOG_PATH="/home/engine/project/logs/cron_rss_ingest.log"

echo "🔧 Настройка cron для RSS Ingest..."
echo "   Скрипт: $SCRIPT_PATH"
echo "   Лог: $LOG_PATH"
echo "   Интервал: каждые 2 минуты"
echo ""

# Создаем временный crontab
CRON_ENTRY="*/2 * * * * /usr/bin/php $SCRIPT_PATH >> $LOG_PATH 2>&1"

# Проверяем существующий crontab
EXISTING_CRON=$(crontab -l 2>/dev/null | grep -v "rss_ingest.php" || true)

# Создаем новый crontab
{
    echo "$EXISTING_CRON"
    echo ""
    echo "# RSS Ingest - запуск каждые 2 минуты"
    echo "$CRON_ENTRY"
} | crontab -

echo "✅ Cron настроен!"
echo ""
echo "📋 Текущий crontab:"
crontab -l
echo ""
echo "💡 Для просмотра логов используйте:"
echo "   tail -f $LOG_PATH"
