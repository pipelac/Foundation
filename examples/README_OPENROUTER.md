# Примеры использования OpenRouter API

Этот каталог содержит примеры работы с классом OpenRouter для интеграции с AI моделями через OpenRouter API.

## Файлы с примерами

### openrouter_example.php

Полный набор примеров использования всех методов класса OpenRouter:

1. **text2text** - Текстовая генерация с AI моделями
2. **image2text** - Распознавание и описание изображений
3. **textStream** - Потоковая передача текста

**Запуск:**
```bash
export OPENROUTER_API_KEY="your-api-key-here"
php examples/openrouter_example.php
```

## Требования

- PHP 8.1 или выше
- Composer зависимости установлены
- API ключ OpenRouter

## Получение API ключа

1. Зарегистрируйтесь на [OpenRouter](https://openrouter.ai)
2. Перейдите в раздел API Keys
3. Создайте новый ключ
4. Экспортируйте его в переменную окружения:
   ```bash
   export OPENROUTER_API_KEY="sk-or-v1-..."
   ```

## Структура примеров

Каждый пример следует единому паттерну:

```php
try {
    // Инициализация
    $openRouter = new OpenRouter([
        'api_key' => getenv('OPENROUTER_API_KEY'),
        'app_name' => 'MyApp',
    ], $logger);
    
    // Вызов метода
    $result = $openRouter->someMethod(...);
    
    // Обработка результата
    echo "✓ Успешно!\n";
    
} catch (OpenRouterException $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
}
```

## Обработка ошибок

Примеры демонстрируют правильную обработку всех типов исключений:

```php
use App\Component\Exception\OpenRouterException;
use App\Component\Exception\OpenRouterApiException;
use App\Component\Exception\OpenRouterValidationException;
use App\Component\Exception\OpenRouterNetworkException;

try {
    $result = $openRouter->text2audio(...);
} catch (OpenRouterValidationException $e) {
    // Ошибки валидации параметров
} catch (OpenRouterApiException $e) {
    // Ошибки API (код доступен через $e->getStatusCode())
} catch (OpenRouterNetworkException $e) {
    // Сетевые ошибки
} catch (OpenRouterException $e) {
    // Общие ошибки
}
```

## Полезные ссылки

- [Основной README проекта](../README.md)
- [OpenRouter API Docs](https://openrouter.ai/docs)

## Советы

1. **Используйте переменные окружения** для API ключей
2. **Кэшируйте результаты** для экономии средств и времени
3. **Логируйте все операции** для отладки
4. **Обрабатывайте все исключения** для надежности
5. **Используйте streaming** для длинных ответов

## Тестирование без API ключа

Большинство примеров требуют реальный API ключ. Для проверки синтаксиса без выполнения запросов:

```bash
php -l examples/openrouter_example.php
```

## Производительность

**Примерное время выполнения:**
- text2text: 1-5 секунд (зависит от модели и длины ответа)
- image2text: 2-10 секунд (зависит от размера изображения)
- textStream: переменное (зависит от длины генерируемого текста)

## Поддержка

Если вы столкнулись с проблемами:

1. Проверьте наличие API ключа
2. Убедитесь в корректности параметров
3. Посмотрите логи в `/logs/`
4. Проверьте баланс на OpenRouter

---

**Последнее обновление:** 2024
**Версия PHP:** 8.1+
