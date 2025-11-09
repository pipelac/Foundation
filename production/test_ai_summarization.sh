#!/bin/bash
###############################################################################
# Скрипт для тестирования AI Summarization
#
# Выполняет 3 запуска скрипта с интервалом 60 секунд
# Имитирует работу cron задачи
###############################################################################

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
PHP_BIN="/usr/bin/php"
SCRIPT_PATH="$SCRIPT_DIR/ai_summarization.php"
TEST_LOG="$PROJECT_ROOT/logs/test_ai_summarization.log"
TOTAL_RUNS=3
INTERVAL=60

echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║        AI SUMMARIZATION TEST SCRIPT                           ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""
echo "📋 Параметры теста:"
echo "   • Количество запусков: $TOTAL_RUNS"
echo "   • Интервал: $INTERVAL секунд"
echo "   • Скрипт: $SCRIPT_PATH"
echo "   • Лог: $TEST_LOG"
echo ""

# Создаем директорию для логов
mkdir -p "$PROJECT_ROOT/logs"

# Очищаем лог-файл
> "$TEST_LOG"

echo "🚀 Начало тестирования: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

for i in $(seq 1 $TOTAL_RUNS); do
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "🔄 Запуск #$i из $TOTAL_RUNS"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    
    RUN_START=$(date '+%Y-%m-%d %H:%M:%S')
    echo "⏱  Время запуска: $RUN_START"
    
    # Запускаем скрипт
    {
        echo "=========================================="
        echo "ЗАПУСК #$i - $RUN_START"
        echo "=========================================="
        $PHP_BIN "$SCRIPT_PATH"
        echo ""
    } >> "$TEST_LOG" 2>&1
    
    RUN_END=$(date '+%Y-%m-%d %H:%M:%S')
    echo "✅ Запуск завершен: $RUN_END"
    echo ""
    
    # Если это не последний запуск - ждем
    if [ $i -lt $TOTAL_RUNS ]; then
        echo "⏳ Ожидание $INTERVAL секунд до следующего запуска..."
        echo ""
        sleep $INTERVAL
    fi
done

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ Тестирование завершено: $(date '+%Y-%m-%d %H:%M:%S')"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "📊 Статистика:"
echo ""

# Подсчитываем статистику из лога
SUCCESS_COUNT=$(grep -c "✅ Успешно обработано" "$TEST_LOG" || echo "0")
ERROR_COUNT=$(grep -c "❌ Ошибка:" "$TEST_LOG" || echo "0")
SKIP_COUNT=$(grep -c "⏭️  Пропущено" "$TEST_LOG" || echo "0")

echo "   • Успешно обработано: $SUCCESS_COUNT"
echo "   • Ошибок: $ERROR_COUNT"
echo "   • Пропущено: $SKIP_COUNT"
echo ""

echo "📝 Полный лог доступен в:"
echo "   $TEST_LOG"
echo ""

echo "🔍 Просмотр лога:"
echo "   cat $TEST_LOG"
echo ""

echo "📊 Просмотр финальных отчетов:"
echo "   grep -A 20 'ФИНАЛЬНЫЙ ОТЧЕТ' $TEST_LOG"
echo ""
