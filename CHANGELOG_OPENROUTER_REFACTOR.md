# Changelog - Рефакторинг OpenRouter (удаление сторонних интеграций)

## [Версия рефакторинга] - 2024

### Удалено

Удалены методы, которые не являются частью официального OpenRouter API:

- **text2image()** - Генерация изображений (использовал `/images/generations` - это OpenAI DALL-E API, не OpenRouter)
- **audio2text()** - Распознавание речи (использовал `/audio/transcriptions` - это OpenAI Whisper API, не OpenRouter)
- **text2audio()** - Синтез речи (использовал `/audio/speech` - это OpenAI TTS API, не OpenRouter)
- **pdf2text()** - Извлечение текста из PDF (использовал нестандартный document_url, не часть OpenRouter API)

### Удалены вспомогательные методы

Следующие приватные методы больше не нужны, так как использовались только удаленными методами:

- `prepareMultipartFile()` - Подготовка multipart запросов (для audio2text)
- `normalizeMultipartValue()` - Нормализация значений для multipart (для audio2text)
- `downloadFile()` - Загрузка файлов по URL (для audio2text, pdf2text)
- `deriveFileNameFromUrl()` - Получение имени файла из URL (для audio2text, pdf2text)
- `guessMimeTypeFromFileName()` - Определение MIME типа (для audio2text, pdf2text)
- `normalizeMediaReference()` - Нормализация ссылок на медиа (для pdf2text)

### Удалены константы

- `MAX_FILE_DOWNLOAD_SIZE` - Больше не нужна
- `STREAM_CHUNK_SIZE` - Больше не используется
- `DOWNLOAD_CHUNK_SIZE` - Больше не нужна

### Оставлено

Класс теперь содержит только методы официального OpenRouter Chat Completions API:

- ✅ **text2text()** - Текстовая генерация через `/chat/completions`
- ✅ **image2text()** - Анализ изображений через `/chat/completions` с vision моделями
- ✅ **textStream()** - Потоковая передача текста через `/chat/completions` с `stream=true`

### Обновлена документация

- `docs/OPENROUTER.md` - Удалены примеры и описания удаленных методов
- `examples/README_OPENROUTER.md` - Обновлены примеры использования
- `README.md` - Обновлено описание компонента OpenRouter

### Технические улучшения

- ✅ Строгая типизация всех параметров и возвращаемых значений
- ✅ PHPDoc документация на русском языке
- ✅ Обработка исключений на каждом уровне
- ✅ Соответствие официальной документации OpenRouter API
- ✅ Уменьшен размер класса с 775 строк до 330 строк (-57%)

### Причина изменений

OpenRouter предоставляет единый унифицированный API для работы с различными AI моделями через Chat Completions endpoint. 
Методы text2image, audio2text, text2audio и pdf2text использовали сторонние API (OpenAI Images, Whisper, TTS), 
которые не являются частью OpenRouter API и требуют прямого обращения к сервисам OpenAI.

Для использования этих функций рекомендуется:
1. Использовать прямую интеграцию с OpenAI API для генерации изображений, работы с аудио
2. Использовать OpenRouter для унифицированного доступа к текстовым моделям и vision моделям

### Совместимость

**BREAKING CHANGES:** Удаленные методы больше не доступны. 

Если ваш код использует удаленные методы, вам нужно:
1. Для text2image, audio2text, text2audio - использовать прямую интеграцию с OpenAI API
2. Для pdf2text - конвертировать PDF в изображения и использовать image2text

### Ссылки

- [OpenRouter API Documentation](https://openrouter.ai/docs/quickstart)
- [OpenRouter Chat Completions](https://openrouter.ai/docs/api-reference)
