#!/bin/bash
# Серия из 5 тестовых запусков RSS Ingest
# Запускается каждую минуту

echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║        RSS INGEST - СЕРИЯ ИЗ 5 ТЕСТОВЫХ ЗАПУСКОВ             ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""

for i in {1..5}; do
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "🔄 ЗАПУСК #$i из 5"
    echo "🕐 Время: $(date '+%Y-%m-%d %H:%M:%S')"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    
    # Запуск скрипта
    php /home/engine/project/production/rss_ingest.php
    
    echo ""
    echo "✅ Запуск #$i завершен"
    echo ""
    
    # Пауза перед следующим запуском (кроме последнего)
    if [ $i -lt 5 ]; then
        echo "⏳ Ожидание 60 секунд до следующего запуска..."
        echo ""
        sleep 60
    fi
done

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🎉 ВСЕ 5 ЗАПУСКОВ ЗАВЕРШЕНЫ!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "📊 Проверка итоговых данных в БД..."
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg -e "
SELECT 
    f.name as 'Источник',
    COUNT(i.id) as 'Всего записей',
    MAX(i.created_at) as 'Последняя запись'
FROM rss2tlg_feeds f
LEFT JOIN rss2tlg_items i ON f.id = i.feed_id
WHERE f.enabled = 1
GROUP BY f.id, f.name
ORDER BY f.id;
"

echo ""
echo "📊 Состояние источников..."
mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg -e "
SELECT 
    feed_id,
    last_status,
    error_count,
    fetched_at
FROM rss2tlg_feed_state
ORDER BY feed_id;
"

echo ""
echo "✅ Тестирование завершено!"
