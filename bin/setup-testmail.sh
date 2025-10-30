#!/bin/bash

# Скрипт настройки testmail.app для тестирования Email класса
# Использование: ./bin/setup-testmail.sh

set -e

echo "================================================"
echo "  Настройка testmail.app для Email класса"
echo "================================================"
echo ""

# Цвета для вывода
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Функция для проверки, установлена ли переменная окружения
check_env_var() {
    local var_name=$1
    if [ -z "${!var_name}" ]; then
        return 1
    else
        return 0
    fi
}

echo "Этот скрипт поможет вам настроить testmail.app для тестирования."
echo ""

# Проверяем, установлены ли уже переменные
if check_env_var "TESTMAIL_NAMESPACE" && check_env_var "TESTMAIL_API_KEY"; then
    echo -e "${GREEN}✓${NC} Переменные окружения уже установлены:"
    echo "  TESTMAIL_NAMESPACE: $TESTMAIL_NAMESPACE"
    echo "  TESTMAIL_API_KEY: ${TESTMAIL_API_KEY:0:10}..."
    echo ""
    read -p "Хотите переустановить их? (y/n): " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Настройка отменена."
        exit 0
    fi
fi

echo ""
echo "Шаг 1: Регистрация на testmail.app"
echo "-----------------------------------"
echo "Если у вас ещё нет аккаунта:"
echo "  1. Откройте: https://testmail.app"
echo "  2. Нажмите 'Sign Up' и зарегистрируйтесь"
echo "  3. Подтвердите email адрес"
echo ""
read -p "Нажмите Enter когда будете готовы продолжить..."

echo ""
echo "Шаг 2: Получение креденшиалов"
echo "------------------------------"
echo "В вашем аккаунте testmail.app:"
echo "  1. Откройте раздел 'Settings' или 'API'"
echo "  2. Найдите ваш Namespace (например, 'myproject123')"
echo "  3. Найдите или сгенерируйте API Key"
echo ""

# Запрашиваем namespace
read -p "Введите ваш TESTMAIL_NAMESPACE: " namespace
if [ -z "$namespace" ]; then
    echo -e "${RED}✗${NC} Ошибка: Namespace не может быть пустым"
    exit 1
fi

# Запрашиваем API key
read -p "Введите ваш TESTMAIL_API_KEY: " apikey
if [ -z "$apikey" ]; then
    echo -e "${RED}✗${NC} Ошибка: API Key не может быть пустым"
    exit 1
fi

echo ""
echo "Шаг 3: Сохранение переменных окружения"
echo "---------------------------------------"

# Определяем shell
SHELL_RC=""
if [ -n "$ZSH_VERSION" ]; then
    SHELL_RC="$HOME/.zshrc"
elif [ -n "$BASH_VERSION" ]; then
    SHELL_RC="$HOME/.bashrc"
else
    echo -e "${YELLOW}⚠${NC} Не удалось определить тип shell"
fi

echo "Переменные окружения для текущей сессии:"
echo ""
echo "export TESTMAIL_NAMESPACE=\"$namespace\""
echo "export TESTMAIL_API_KEY=\"$apikey\""
echo ""

# Устанавливаем переменные для текущей сессии
export TESTMAIL_NAMESPACE="$namespace"
export TESTMAIL_API_KEY="$apikey"

echo -e "${GREEN}✓${NC} Переменные установлены для текущей сессии"
echo ""

if [ -n "$SHELL_RC" ]; then
    read -p "Хотите добавить их в $SHELL_RC для постоянного использования? (y/n): " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        # Проверяем, есть ли уже эти переменные в файле
        if grep -q "TESTMAIL_NAMESPACE" "$SHELL_RC" 2>/dev/null; then
            echo -e "${YELLOW}⚠${NC} Переменные уже присутствуют в $SHELL_RC"
            echo "Пожалуйста, обновите их вручную при необходимости."
        else
            echo "" >> "$SHELL_RC"
            echo "# testmail.app credentials" >> "$SHELL_RC"
            echo "export TESTMAIL_NAMESPACE=\"$namespace\"" >> "$SHELL_RC"
            echo "export TESTMAIL_API_KEY=\"$apikey\"" >> "$SHELL_RC"
            echo -e "${GREEN}✓${NC} Переменные добавлены в $SHELL_RC"
            echo "Для применения выполните: source $SHELL_RC"
        fi
    fi
fi

echo ""
echo "Шаг 4: Проверка настройки"
echo "-------------------------"
echo "Запуск тестового скрипта..."
echo ""

# Проверяем, существует ли тестовый скрипт
if [ -f "examples/email_testmail_example.php" ]; then
    read -p "Хотите запустить тестовый пример? (y/n): " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo ""
        php examples/email_testmail_example.php
    else
        echo "Вы можете запустить тестовый пример позже:"
        echo "  php examples/email_testmail_example.php"
    fi
else
    echo -e "${YELLOW}⚠${NC} Файл examples/email_testmail_example.php не найден"
fi

echo ""
echo "================================================"
echo -e "${GREEN}✓ Настройка завершена!${NC}"
echo "================================================"
echo ""
echo "Следующие шаги:"
echo "  1. Запустите тесты: vendor/bin/phpunit tests/Integration/EmailTestmailTest.php"
echo "  2. Запустите пример: php examples/email_testmail_example.php"
echo "  3. Просмотрите письма: https://testmail.app"
echo ""
echo "Документация: docs/EMAIL_TESTMAIL_TESTING.md"
echo ""
