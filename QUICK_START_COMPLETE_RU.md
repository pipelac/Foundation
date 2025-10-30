# 🚀 Полное руководство: Все методы OpenRouter

## Все 6 методов работают! ✅

### 1. text2text() - Текстовая генерация

```php
$response = $openRouter->text2text(
    'openai/gpt-3.5-turbo',
    'Hello, how are you?',
    ['max_tokens' => 100]
);
```

### 2. textStream() - Потоковая передача

```php
$openRouter->textStream(
    'openai/gpt-3.5-turbo',
    'Tell me a story',
    function (string $chunk): void {
        echo $chunk;
    },
    ['max_tokens' => 500]
);
```

### 3. image2text() - Распознавание изображений

```php
$description = $openRouter->image2text(
    'openai/gpt-4o',
    'https://example.com/image.jpg',
    'What is in this image?',
    ['max_tokens' => 200]
);
```

### 4. text2image() - Генерация изображений ✅ ИСПРАВЛЕНО

```php
$imageData = $openRouter->text2image(
    'google/gemini-2.5-flash-image',
    'Draw a red circle',
    ['max_tokens' => 2000]
);

// $imageData содержит base64 или URL
file_put_contents('image.png', base64_decode($imageData));
```

### 5. pdf2text() - Извлечение текста из PDF ✅ ИСПРАВЛЕНО

```php
$text = $openRouter->pdf2text(
    'openai/gpt-4o',
    'https://bitcoin.org/bitcoin.pdf',
    'What is the title of this document?',
    ['max_tokens' => 500]
);

echo $text;
// Вывод: "The title of the document is 'Bitcoin: A Peer-to-Peer Electronic Cash System.'"
```

**Поддерживает:**
- PDF URL (напрямую)
- Base64 encoded PDF (для локальных файлов)

### 6. audio2text() - Распознавание речи ✅ ИСПРАВЛЕНО

```php
$transcription = $openRouter->audio2text(
    'google/gemini-2.5-flash',
    '/path/to/audio.wav',  // Локальный файл
    [
        'format' => 'wav',  // или 'mp3'
        'prompt' => 'Transcribe this audio',
    ]
);

echo $transcription;
```

**Поддерживает:**
- Локальные файлы (автоматическая конвертация в base64)
- Base64 encoded audio (если передать строку base64)
- Форматы: WAV, MP3

## Примеры с обработкой ошибок

```php
use App\Component\Exception\OpenRouterException;
use App\Component\Exception\OpenRouterApiException;
use App\Component\Exception\OpenRouterValidationException;

try {
    // PDF
    $pdfText = $openRouter->pdf2text(
        'openai/gpt-4o',
        'https://example.com/document.pdf',
        'Summarize this document'
    );
    
    // Audio
    $audioTranscription = $openRouter->audio2text(
        'google/gemini-2.5-flash',
        '/path/to/audio.mp3',
        ['format' => 'mp3']
    );
    
} catch (OpenRouterValidationException $e) {
    echo "Ошибка валидации: " . $e->getMessage();
} catch (OpenRouterApiException $e) {
    echo "Ошибка API: " . $e->getMessage();
} catch (OpenRouterException $e) {
    echo "Ошибка: " . $e->getMessage();
}
```

## Стоимость операций

| Операция | Примерная стоимость |
|----------|---------------------|
| text2text | ~$0.000003 |
| textStream | ~$0.000005 |
| image2text | ~$0.003 |
| **text2image** | **~$0.078** (дорого!) |
| pdf2text | ~$0.004 |
| audio2text | ~$0.002 |

⚠️ **ВАЖНО:** Генерация изображений - САМАЯ ДОРОГАЯ операция!

## Что было исправлено

### 1. text2image()
- ❌ Было: endpoint `/images/generations` (405 ошибка)
- ✅ Стало: endpoint `/chat/completions` с messages

### 2. pdf2text()
- ❌ Было: type `image_url`
- ✅ Стало: type `file` с `filename` и `file_data`
- 📚 Документация: https://openrouter.ai/docs/features/multimodal/pdfs

### 3. audio2text()
- ❌ Было: type `audio_url` с URL
- ✅ Стало: type `input_audio` с base64 `data` и `format`
- 📚 Документация: https://openrouter.ai/docs/features/multimodal/audio

## Полная документация

См. `/FINAL_COMPLETE_TEST_RESULTS.md` для:
- Детальных результатов тестирования
- Логов всех операций
- Финансовой статистики
- Рекомендаций для продакшена

## Запуск тестов

```bash
# Все методы
php tests/Integration/OpenRouterCompleteTest.php "YOUR_API_KEY"

# Unit-тесты
./vendor/bin/phpunit tests/Unit/OpenRouterTest.php
```

## ✅ Статус: ВСЕ 6 МЕТОДОВ РАБОТАЮТ!

- ✅ text2text
- ✅ textStream
- ✅ image2text
- ✅ text2image (исправлено)
- ✅ pdf2text (исправлено)
- ✅ audio2text (исправлено)

🎉 **ГОТОВО К ПРОДАКШЕНУ!**
