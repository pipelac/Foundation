#!/bin/bash
###############################################################################
# Скрипт настройки cron для AI Summarization
#
# Настраивает запуск ai_summarization.php каждую 1 минуту
###############################################################################

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
PHP_BIN="/usr/bin/php"
SCRIPT_PATH="$SCRIPT_DIR/ai_summarization.php"
CRON_LOG="$PROJECT_ROOT/logs/cron_ai_summarization.log"

echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║        AI SUMMARIZATION CRON SETUP                            ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""

# Проверка наличия PHP
if [ ! -f "$PHP_BIN" ]; then
    echo "❌ PHP не найден: $PHP_BIN"
    exit 1
fi

echo "✅ PHP найден: $PHP_BIN"

# Проверка наличия скрипта
if [ ! -f "$SCRIPT_PATH" ]; then
    echo "❌ Скрипт не найден: $SCRIPT_PATH"
    exit 1
fi

echo "✅ Скрипт найден: $SCRIPT_PATH"

# Делаем скрипт исполняемым
chmod +x "$SCRIPT_PATH"
echo "✅ Права на выполнение установлены"

# Создаем директорию для логов если не существует
mkdir -p "$PROJECT_ROOT/logs"
echo "✅ Директория логов создана: $PROJECT_ROOT/logs"

# Формируем cron строку
CRON_ENTRY="* * * * * $PHP_BIN $SCRIPT_PATH >> $CRON_LOG 2>&1"

echo ""
echo "📝 Cron задача:"
echo "   $CRON_ENTRY"
echo ""

# Проверяем существующий crontab
if crontab -l >/dev/null 2>&1; then
    EXISTING_CRON=$(crontab -l)
    
    # Проверяем, есть ли уже такая задача
    if echo "$EXISTING_CRON" | grep -F "$SCRIPT_PATH" >/dev/null 2>&1; then
        echo "⚠️  Задача уже существует в crontab"
        echo ""
        echo "Хотите заменить существующую задачу? (y/n)"
        read -r REPLACE
        
        if [ "$REPLACE" != "y" ] && [ "$REPLACE" != "Y" ]; then
            echo "❌ Установка отменена"
            exit 0
        fi
        
        # Удаляем старую задачу
        UPDATED_CRON=$(echo "$EXISTING_CRON" | grep -v -F "$SCRIPT_PATH")
        echo "$UPDATED_CRON" | crontab -
        echo "✅ Старая задача удалена"
    fi
    
    # Добавляем новую задачу
    (crontab -l 2>/dev/null || true; echo "$CRON_ENTRY") | crontab -
else
    # Создаем новый crontab
    echo "$CRON_ENTRY" | crontab -
fi

echo "✅ Cron задача добавлена"
echo ""

# Показываем текущий crontab
echo "📋 Текущий crontab:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
crontab -l | grep -F "$SCRIPT_PATH" || echo "(пусто)"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

echo "🎉 Настройка завершена!"
echo ""
echo "📝 Логи записываются в:"
echo "   $CRON_LOG"
echo ""
echo "🔍 Просмотр логов в реальном времени:"
echo "   tail -f $CRON_LOG"
echo ""
echo "🗑️  Удаление задачи из cron:"
echo "   crontab -l | grep -v '$SCRIPT_PATH' | crontab -"
echo ""
