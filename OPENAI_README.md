# OpenAI API - Документация

Полнофункциональный PHP 8.1+ класс для работы с OpenAI API с поддержкой всех основных возможностей.

## Оглавление

- [Возможности](#возможности)
- [Требования](#требования)
- [Установка](#установка)
- [Быстрый старт](#быстрый-старт)
- [Конфигурация](#конфигурация)
- [Методы](#методы)
  - [text2text](#text2text---текстовая-генерация)
  - [text2image](#text2image---генерация-изображений)
  - [image2text](#image2text---анализ-изображений)
  - [audio2text](#audio2text---транскрипция-аудио)
  - [textStream](#textstream---потоковая-передача)
  - [embeddings](#embeddings---создание-эмбеддингов)
  - [moderation](#moderation---модерация-контента)
- [Обработка ошибок](#обработка-ошибок)
- [Примеры использования](#примеры-использования)

## Возможности

- ✅ **Текстовая генерация** - GPT-4o, GPT-4o-mini, GPT-4-turbo
- ✅ **Генерация изображений** - DALL-E 3, DALL-E 2
- ✅ **Анализ изображений** - GPT-4 Vision (GPT-4o)
- ✅ **Транскрипция аудио** - Whisper, GPT-4o Audio
- ✅ **Потоковая передача** - Streaming для real-time ответов
- ✅ **Эмбеддинги** - text-embedding-3-small, text-embedding-3-large
- ✅ **Модерация контента** - Проверка на нарушения правил OpenAI
- ✅ **Строгая типизация** - PHP 8.1+ с полной типизацией
- ✅ **Обработка исключений** - Детальная обработка всех ошибок
- ✅ **Логирование** - Интеграция с Logger для отладки
- ✅ **Retry механизм** - Автоматические повторные попытки
- ✅ **PHPDoc на русском** - Полная документация кода

## Требования

- PHP 8.1 или выше
- Расширения: `json`, `mbstring`
- Composer для управления зависимостями
- OpenAI API ключ

## Установка

```bash
# Установка через Composer
composer require your-vendor/basic-utilities

# Или клонирование репозитория
git clone https://github.com/your-repo/basic-utilities.git
```

## Быстрый старт

```php
<?php

require_once 'autoload.php';

use App\Component\OpenAi;
use App\Component\Logger;

// Создание логгера (опционально)
$logger = new Logger([
    'directory' => __DIR__ . '/logs',
]);

// Конфигурация OpenAI
$config = [
    'api_key' => 'sk-proj-your-api-key',
    'organization' => 'org-123456', // Опционально
    'timeout' => 60,
    'retries' => 3,
];

// Инициализация
$openAi = new OpenAi($config, $logger);

// Простой запрос
$response = $openAi->text2text('Напиши короткое стихотворение о весне');
echo $response;
```

## Конфигурация

### Параметры конструктора

```php
$config = [
    'api_key' => 'sk-proj-...',      // Обязательно: OpenAI API ключ
    'organization' => 'org-...',      // Опционально: ID организации
    'timeout' => 60,                  // Опционально: Таймаут в секундах (по умолчанию 60)
    'retries' => 3,                   // Опционально: Количество повторных попыток (по умолчанию 0)
];

$openAi = new OpenAi($config, $logger);
```

### Получение API ключа

1. Зарегистрируйтесь на [platform.openai.com](https://platform.openai.com/)
2. Перейдите в раздел API keys
3. Создайте новый API ключ
4. Сохраните ключ в безопасном месте

## Методы

### text2text - Текстовая генерация

Генерирует текстовый ответ на основе запроса с использованием GPT моделей.

```php
public function text2text(
    string $prompt,
    string $model = 'gpt-4o-mini',
    array $options = []
): string
```

**Параметры:**
- `$prompt` (string) - Текстовый запрос для модели
- `$model` (string) - Модель: `gpt-4o`, `gpt-4o-mini`, `gpt-4-turbo`
- `$options` (array) - Дополнительные параметры:
  - `temperature` (float) - Температура генерации (0.0-2.0)
  - `max_tokens` (int) - Максимум токенов
  - `top_p` (float) - Top-p sampling
  - `frequency_penalty` (float) - Штраф за частоту (-2.0 до 2.0)
  - `presence_penalty` (float) - Штраф за присутствие (-2.0 до 2.0)
  - `system` (string) - Системное сообщение

**Пример:**

```php
$response = $openAi->text2text(
    prompt: 'Объясни квантовую физику простым языком',
    model: 'gpt-4o',
    options: [
        'temperature' => 0.7,
        'max_tokens' => 500,
        'system' => 'Ты - опытный преподаватель физики',
    ]
);
```

---

### text2image - Генерация изображений

Генерирует изображения на основе текстового описания с помощью DALL-E.

```php
public function text2image(
    string $prompt,
    string $model = 'dall-e-3',
    array $options = []
): string
```

**Параметры:**
- `$prompt` (string) - Описание изображения
- `$model` (string) - Модель: `dall-e-3`, `dall-e-2`
- `$options` (array) - Дополнительные параметры:
  - `size` (string) - Размер: `1024x1024`, `1792x1024`, `1024x1792`
  - `quality` (string) - Качество: `standard`, `hd`
  - `n` (int) - Количество изображений (1-10, только для dall-e-2)
  - `style` (string) - Стиль: `vivid`, `natural`

**Возвращает:** URL сгенерированного изображения

**Пример:**

```php
$imageUrl = $openAi->text2image(
    prompt: 'Закат над океаном в стиле импрессионизма',
    model: 'dall-e-3',
    options: [
        'size' => '1024x1024',
        'quality' => 'hd',
        'style' => 'vivid',
    ]
);

echo "Изображение: {$imageUrl}";
```

---

### image2text - Анализ изображений

Анализирует изображение и возвращает текстовое описание с помощью GPT-4 Vision.

```php
public function image2text(
    string $imageUrl,
    string $question = 'Опиши это изображение подробно',
    string $model = 'gpt-4o',
    array $options = []
): string
```

**Параметры:**
- `$imageUrl` (string) - URL изображения для анализа
- `$question` (string) - Вопрос к изображению
- `$model` (string) - Модель: `gpt-4o`, `gpt-4-turbo`
- `$options` (array) - Дополнительные параметры:
  - `max_tokens` (int) - Максимум токенов в ответе
  - `detail` (string) - Уровень детализации: `low`, `high`, `auto`

**Пример:**

```php
$description = $openAi->image2text(
    imageUrl: 'https://example.com/photo.jpg',
    question: 'Какие объекты изображены на этой фотографии?',
    model: 'gpt-4o',
    options: [
        'max_tokens' => 300,
        'detail' => 'high',
    ]
);
```

---

### audio2text - Транскрипция аудио

Преобразует аудиофайлы в текст с помощью Whisper или GPT-4o Audio.

```php
public function audio2text(
    string $audioUrl,
    string $model = 'whisper-1',
    array $options = []
): string
```

**Параметры:**
- `$audioUrl` (string) - URL аудиофайла
- `$model` (string) - Модель: `whisper-1`, `gpt-4o-audio-preview`
- `$options` (array) - Дополнительные параметры:
  - `language` (string) - Код языка ISO-639-1 (например, `ru`, `en`)
  - `prompt` (string) - Подсказка для улучшения точности
  - `temperature` (float) - Температура сэмплирования (0-1)
  - `format` (string) - Формат аудио: `mp3`, `wav`, `m4a`

**Пример:**

```php
$transcription = $openAi->audio2text(
    audioUrl: 'https://example.com/recording.mp3',
    model: 'whisper-1',
    options: [
        'language' => 'ru',
        'prompt' => 'Это интервью о технологиях и AI',
    ]
);
```

---

### textStream - Потоковая передача

Отправляет запрос с потоковой передачей ответа в реальном времени.

```php
public function textStream(
    string $prompt,
    callable $callback,
    string $model = 'gpt-4o-mini',
    array $options = []
): void
```

**Параметры:**
- `$prompt` (string) - Текстовый запрос
- `$callback` (callable) - Функция-обработчик частей ответа
- `$model` (string) - Модель ИИ
- `$options` (array) - Дополнительные параметры

**Пример:**

```php
$openAi->textStream(
    prompt: 'Напиши эссе о будущем AI',
    callback: function (string $chunk): void {
        echo $chunk;
        flush();
    },
    model: 'gpt-4o',
    options: [
        'temperature' => 0.7,
        'max_tokens' => 1000,
    ]
);
```

---

### embeddings - Создание эмбеддингов

Создает векторные представления для текстов.

```php
public function embeddings(
    string|array $input,
    string $model = 'text-embedding-3-small',
    array $options = []
): array
```

**Параметры:**
- `$input` (string|array) - Текст или массив текстов
- `$model` (string) - Модель: `text-embedding-3-small`, `text-embedding-3-large`, `text-embedding-ada-002`
- `$options` (array) - Дополнительные параметры:
  - `dimensions` (int) - Размерность векторов
  - `encoding_format` (string) - Формат: `float`, `base64`

**Возвращает:** Массив векторов эмбеддингов

**Пример:**

```php
// Одиночный текст
$embeddings = $openAi->embeddings(
    input: 'Машинное обучение - это здорово!',
    model: 'text-embedding-3-small'
);

// Множественные тексты
$embeddings = $openAi->embeddings(
    input: [
        'Первый документ',
        'Второй документ',
        'Третий документ',
    ],
    model: 'text-embedding-3-large',
    options: ['dimensions' => 1024]
);

echo "Размерность: " . count($embeddings[0]);
```

---

### moderation - Модерация контента

Проверяет текст на соответствие правилам модерации OpenAI.

```php
public function moderation(
    string $input,
    string $model = 'text-moderation-latest'
): array
```

**Параметры:**
- `$input` (string) - Текст для проверки
- `$model` (string) - Модель модерации

**Возвращает:** Массив с результатами:
- `flagged` (bool) - Найдены ли нарушения
- `categories` (array) - Категории нарушений
- `category_scores` (array) - Оценки по категориям

**Пример:**

```php
$result = $openAi->moderation('Проверяемый текст');

if ($result['flagged']) {
    echo "Найдены нарушения:\n";
    foreach ($result['categories'] as $category => $flagged) {
        if ($flagged) {
            $score = $result['category_scores'][$category];
            echo "- {$category}: {$score}\n";
        }
    }
} else {
    echo "Контент безопасен";
}
```

## Обработка ошибок

Класс использует иерархию исключений для детальной обработки ошибок:

```php
use App\Component\Exception\OpenAiException;
use App\Component\Exception\OpenAiValidationException;
use App\Component\Exception\OpenAiApiException;
use App\Component\Exception\OpenAiNetworkException;

try {
    $response = $openAi->text2text('Привет!');
} catch (OpenAiValidationException $e) {
    // Ошибки валидации входных данных
    echo "Ошибка валидации: {$e->getMessage()}";
} catch (OpenAiApiException $e) {
    // Ошибки от API (4xx, 5xx)
    echo "API ошибка [{$e->getStatusCode()}]: {$e->getMessage()}";
    echo "Ответ: {$e->getResponseBody()}";
} catch (OpenAiNetworkException $e) {
    // Сетевые ошибки (таймаут, недоступность)
    echo "Сетевая ошибка: {$e->getMessage()}";
} catch (OpenAiException $e) {
    // Прочие ошибки
    echo "Ошибка: {$e->getMessage()}";
}
```

### Типы исключений

- **OpenAiException** - Базовое исключение для всех ошибок
- **OpenAiValidationException** - Ошибки валидации параметров
- **OpenAiApiException** - Ошибки от API (с кодом и телом ответа)
- **OpenAiNetworkException** - Сетевые ошибки

## Примеры использования

### Пример 1: Чат-бот с контекстом

```php
$openAi = new OpenAi(['api_key' => 'sk-proj-...']);

$response = $openAi->text2text(
    prompt: 'Привет! Как мне начать изучать программирование?',
    model: 'gpt-4o-mini',
    options: [
        'system' => 'Ты - опытный наставник по программированию',
        'temperature' => 0.8,
        'max_tokens' => 500,
    ]
);

echo $response;
```

### Пример 2: Анализ настроения в тексте

```php
$text = 'Сегодня был прекрасный день, я очень доволен!';

$response = $openAi->text2text(
    prompt: "Определи настроение текста: \"{$text}\"",
    options: [
        'temperature' => 0.3,
        'max_tokens' => 50,
    ]
);

echo "Настроение: {$response}";
```

### Пример 3: Генерация маркетингового контента

```php
$product = 'Умные часы с AI ассистентом';

$description = $openAi->text2text(
    prompt: "Напиши креативное описание продукта: {$product}",
    model: 'gpt-4o',
    options: [
        'temperature' => 0.9,
        'max_tokens' => 200,
        'system' => 'Ты - профессиональный маркетолог',
    ]
);
```

### Пример 4: Создание логотипа

```php
$logo = $openAi->text2image(
    prompt: 'Минималистичный логотип IT компании, синий и белый цвета, 
             современный дизайн, профессиональный вид',
    model: 'dall-e-3',
    options: [
        'size' => '1024x1024',
        'quality' => 'hd',
    ]
);

// Скачивание изображения
file_put_contents('logo.png', file_get_contents($logo));
```

### Пример 5: Семантический поиск с эмбеддингами

```php
// База знаний
$documents = [
    'PHP - это язык программирования для веб-разработки',
    'Python популярен в машинном обучении',
    'JavaScript используется для фронтенда',
];

// Создаем эмбеддинги для документов
$docEmbeddings = $openAi->embeddings($documents);

// Запрос пользователя
$query = 'язык для AI и ML';
$queryEmbedding = $openAi->embeddings($query)[0];

// Вычисляем косинусное сходство (упрощенно)
function cosineSimilarity(array $a, array $b): float {
    $dotProduct = array_sum(array_map(fn($x, $y) => $x * $y, $a, $b));
    $magnitudeA = sqrt(array_sum(array_map(fn($x) => $x * $x, $a)));
    $magnitudeB = sqrt(array_sum(array_map(fn($x) => $x * $x, $b)));
    return $dotProduct / ($magnitudeA * $magnitudeB);
}

// Находим наиболее релевантный документ
$maxSimilarity = 0;
$bestDoc = '';

foreach ($docEmbeddings as $i => $docEmb) {
    $similarity = cosineSimilarity($queryEmbedding, $docEmb);
    if ($similarity > $maxSimilarity) {
        $maxSimilarity = $similarity;
        $bestDoc = $documents[$i];
    }
}

echo "Найден документ: {$bestDoc}";
```

### Пример 6: Автоматическая модерация комментариев

```php
$comments = [
    'Отличная статья, спасибо!',
    'Полезная информация',
    // ... другие комментарии
];

foreach ($comments as $comment) {
    $result = $openAi->moderation($comment);
    
    if ($result['flagged']) {
        echo "Комментарий заблокирован: {$comment}\n";
        // Отправить на дополнительную проверку
    } else {
        echo "Комментарий одобрен: {$comment}\n";
        // Опубликовать комментарий
    }
}
```

### Пример 7: Интерактивный чат с streaming

```php
echo "Чат-бот запущен. Введите сообщение:\n";

while (true) {
    $input = trim(fgets(STDIN));
    
    if ($input === 'exit') {
        break;
    }
    
    echo "Бот: ";
    $openAi->textStream(
        prompt: $input,
        callback: fn($chunk) => print($chunk),
        options: ['max_tokens' => 500]
    );
    echo "\n\n";
}
```

## Лучшие практики

1. **Безопасность API ключа**
   ```php
   // Используйте переменные окружения
   $config['api_key'] = getenv('OPENAI_API_KEY');
   
   // Никогда не коммитьте ключи в Git
   ```

2. **Обработка ошибок**
   ```php
   // Всегда оборачивайте вызовы в try-catch
   try {
       $response = $openAi->text2text($prompt);
   } catch (OpenAiException $e) {
       // Логирование и обработка
   }
   ```

3. **Оптимизация токенов**
   ```php
   // Устанавливайте лимиты для контроля расходов
   $options = [
       'max_tokens' => 500,
       'temperature' => 0.7,
   ];
   ```

4. **Логирование**
   ```php
   // Используйте логгер для отладки
   $logger = new Logger(['directory' => '/var/log']);
   $openAi = new OpenAi($config, $logger);
   ```

5. **Кэширование эмбеддингов**
   ```php
   // Кэшируйте эмбеддинги для повторного использования
   $cacheKey = md5($text);
   if (!$cache->has($cacheKey)) {
       $embedding = $openAi->embeddings($text);
       $cache->set($cacheKey, $embedding);
   }
   ```

## Ограничения и квоты

- **Rate limits**: OpenAI накладывает ограничения на количество запросов
- **Token limits**: Каждая модель имеет максимальное количество токенов
- **Стоимость**: Разные модели имеют разную цену за токен

См. [официальную документацию OpenAI](https://platform.openai.com/docs/guides/rate-limits) для актуальной информации.

## Поддержка

Для вопросов и проблем:
- Создайте issue в репозитории
- Проверьте [документацию OpenAI](https://platform.openai.com/docs)
- Изучите примеры в директории `/examples`

## Лицензия

Этот проект использует лицензию MIT. См. файл LICENSE для деталей.

---

**Версия:** 1.0.0  
**Дата обновления:** 2024  
**Автор:** Basic Utilities Team
