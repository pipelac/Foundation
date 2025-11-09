#!/bin/bash
# Скрипт для запуска 3 тестов с интервалом 2 минуты
# Симуляция работы cron

SCRIPT_PATH="/home/engine/project/production/rss_ingest.php"
INTERVAL=120  # 2 минуты в секундах

echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║       RSS INGEST: 3 ЗАПУСКА С ИНТЕРВАЛОМ 2 МИНУТЫ            ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""
echo "📋 Параметры теста:"
echo "   • Скрипт: $SCRIPT_PATH"
echo "   • Количество запусков: 3"
echo "   • Интервал: ${INTERVAL} сек (2 минуты)"
echo ""

for i in {1..3}; do
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "🚀 ЗАПУСК #$i из 3"
    echo "🕐 Время: $(date '+%Y-%m-%d %H:%M:%S')"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    
    php "$SCRIPT_PATH"
    
    if [ $i -lt 3 ]; then
        echo ""
        echo "⏳ Ожидание ${INTERVAL} секунд до следующего запуска..."
        echo "   ($(date '+%H:%M:%S') → $(date -d "+${INTERVAL} seconds" '+%H:%M:%S'))"
        echo ""
        sleep $INTERVAL
    fi
done

echo ""
echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║                    ТЕСТИРОВАНИЕ ЗАВЕРШЕНО                     ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""
echo "📊 Проверка данных в БД..."
echo ""

mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg << 'EOF'
SELECT 
    f.name AS 'Источник',
    COUNT(i.id) AS 'Записей',
    MAX(i.created_at) AS 'Последняя запись'
FROM rss2tlg_feeds f
LEFT JOIN rss2tlg_items i ON f.id = i.feed_id
GROUP BY f.id, f.name
ORDER BY f.id;

SELECT '---' AS '---';

SELECT 
    'Всего источников' AS 'Метрика',
    COUNT(*) AS 'Значение'
FROM rss2tlg_feeds
UNION ALL
SELECT 
    'Всего записей',
    COUNT(*)
FROM rss2tlg_items
UNION ALL
SELECT 
    'Активных источников',
    COUNT(*)
FROM rss2tlg_feeds WHERE enabled = 1;
EOF

echo ""
echo "✅ Тестирование завершено успешно!"
