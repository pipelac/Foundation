# Changelog - OpenRouterClient

## [1.0.0] - 2024

### Добавлено

#### Новый класс OpenRouterClient
Создан специализированный класс для работы с внутренней информацией OpenRouter API:

**Основной функционал:**
- ✅ Проверка валидности API ключа (`validateApiKey()`)
- ✅ Получение информации об аккаунте (`getKeyInfo()`)
- ✅ Получение текущего баланса (`getBalance()`)
- ✅ Детальная статистика использования (`getUsageStats()`)
- ✅ Информация о лимитах запросов (`getRateLimits()`)

**Работа с моделями:**
- ✅ Получение списка всех доступных моделей (`getModels()`)
- ✅ Получение информации о конкретной модели (`getModelInfo()`)
- ✅ Расчёт стоимости запроса (`calculateCost()`)

**Отслеживание генераций:**
- ✅ Получение детальной информации о генерации по ID (`getGenerationInfo()`)

#### Документация
- 📄 **OPENROUTER_CLIENT_README.md** - подробная документация с примерами
- 📝 Обновлён основной **README.md** с разделом об OpenRouterClient
- 💡 Создан **examples/openrouter_client_example.php** - рабочий пример использования

### Технические детали

**Архитектура:**
- Строгая типизация PHP 8.1+
- PHPDoc документация на русском языке
- Использование существующих исключений (OpenRouterException, OpenRouterApiException, etc.)
- Интеграция с классом Http для HTTP запросов
- Опциональное логирование через Logger

**Структура:**
```
src/
├── OpenRouterClient.class.php    # Новый класс
examples/
├── openrouter_client_example.php # Пример использования
OPENROUTER_CLIENT_README.md       # Подробная документация
```

**Endpoints OpenRouter API:**
- `GET /api/v1/auth/key` - информация об API ключе
- `GET /api/v1/models` - список моделей
- `GET /api/v1/generation?id={id}` - информация о генерации

### Примеры использования

```php
use App\Component\OpenRouterClient;
use App\Config\ConfigLoader;

$config = ConfigLoader::load(__DIR__ . '/config/openrouter.json');
$client = new OpenRouterClient($config, $logger);

// Проверка баланса
$balance = $client->getBalance();
echo "Баланс: $" . number_format($balance, 2);

// Статистика использования
$stats = $client->getUsageStats();
echo "Использовано: {$stats['usage_percentage']}%";

// Расчёт стоимости
$cost = $client->calculateCost('openai/gpt-3.5-turbo', 1000, 500);
echo "Стоимость: $" . $cost['total_cost_usd'];
```

### Отличие от класса OpenRouter

| OpenRouter | OpenRouterClient |
|-----------|------------------|
| Генерация текста, изображений | Информация о балансе, токенах |
| Работа с AI моделями | Мониторинг и статистика аккаунта |
| text2text, image2text, etc. | getBalance, getUsageStats, etc. |

### Совместимость

- PHP 8.1+
- Работает с существующим config/openrouter.json
- Не требует дополнительных зависимостей
- Полная совместимость с текущей архитектурой проекта

### Безопасность

- Валидация всех входных параметров
- Обработка всех возможных исключений
- Безопасная работа с API ключами
- Логирование критичных операций

---

**Автор:** AI Development Team  
**Дата:** 2024  
**Версия:** 1.0.0
