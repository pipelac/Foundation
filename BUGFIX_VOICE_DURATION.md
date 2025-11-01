# 🐛 Исправление: Voice Message Duration

**Дата:** 01.11.2025  
**Приоритет:** LOW  
**Статус:** ✅ ИСПРАВЛЕНО  

---

## 📋 Описание проблемы

При тестировании TelegramBot (Level 2) было обнаружено, что при получении голосового сообщения (voice) поле `duration` не отображалось корректно.

**Вывод в лог:**
```
[Level 2] Получение voice: ✅ PASS | Duration: с
```

Ожидалось:
```
[Level 2] Получение voice: ✅ PASS | Duration: 5с
```

---

## 🔍 Анализ причины

### Найденные проблемы:

1. **В `Message.php` (строка 81):**
   ```php
   voice: isset($data['voice']) ? Media::fromDocument($data['voice']) : null,
   ```
   
   Voice обрабатывался как Document, используя метод `Media::fromDocument()`, который не извлекает поле `duration`.

2. **В `Media.php`:**
   - Отсутствовал специализированный метод `fromVoice()` для обработки голосовых сообщений
   - Отсутствовал метод проверки `isVoice()`

3. **В `Message.php`:**
   - Отсутствовал метод проверки `hasVoice()`

---

## ✅ Внесённые исправления

### 1. Добавлен метод `Media::fromVoice()`

**Файл:** `/src/TelegramBot/Entities/Media.php`

```php
/**
 * Создает объект Media из массива данных Telegram API для Voice
 *
 * @param array<string, mixed> $data Данные от Telegram API
 * @return self
 */
public static function fromVoice(array $data): self
{
    return new self(
        fileId: (string)$data['file_id'],
        fileUniqueId: (string)$data['file_unique_id'],
        type: self::TYPE_VOICE,
        fileSize: isset($data['file_size']) ? (int)$data['file_size'] : null,
        duration: isset($data['duration']) ? (int)$data['duration'] : null,
        mimeType: isset($data['mime_type']) ? (string)$data['mime_type'] : null,
    );
}
```

### 2. Добавлен метод `Media::isVoice()`

**Файл:** `/src/TelegramBot/Entities/Media.php`

```php
/**
 * Проверяет, является ли медиа голосовым сообщением
 */
public function isVoice(): bool
{
    return $this->type === self::TYPE_VOICE;
}
```

### 3. Исправлено использование в `Message::fromArray()`

**Файл:** `/src/TelegramBot/Entities/Message.php` (строка 81)

**Было:**
```php
voice: isset($data['voice']) ? Media::fromDocument($data['voice']) : null,
```

**Стало:**
```php
voice: isset($data['voice']) ? Media::fromVoice($data['voice']) : null,
```

### 4. Добавлен метод `Message::hasVoice()`

**Файл:** `/src/TelegramBot/Entities/Message.php`

```php
/**
 * Проверяет, содержит ли сообщение голосовое сообщение
 */
public function hasVoice(): bool
{
    return $this->voice !== null;
}
```

---

## 🧪 Проверка исправления

После внесения изменений:

1. ✅ Поле `duration` корректно извлекается из Telegram API response
2. ✅ Метод `$message->voice->duration` возвращает корректное значение в секундах
3. ✅ Метод `$message->hasVoice()` доступен для проверки наличия voice
4. ✅ Метод `$media->isVoice()` доступен для проверки типа медиа

---

## 📊 Влияние изменений

### Затронутые компоненты:
- ✅ `Media` entity - добавлены методы
- ✅ `Message` entity - исправлена обработка, добавлен метод

### Обратная совместимость:
- ✅ **Полная** - изменения не нарушают существующий функционал
- ✅ Только добавлены новые методы и исправлена некорректная обработка

### Регрессия:
- ❌ **Отсутствует** - существующий код не сломан
- ✅ Voice messages теперь обрабатываются корректно

---

## 📝 Рекомендации

1. ✅ При работе с voice messages использовать `$message->voice->duration` для получения длительности
2. ✅ Использовать `$message->hasVoice()` для проверки наличия голосового сообщения
3. ✅ Использовать `$media->isVoice()` для проверки типа медиа

### Пример использования:

```php
if ($message->hasVoice()) {
    $voice = $message->voice;
    $duration = $voice->duration; // Теперь корректно возвращает количество секунд
    $fileId = $voice->fileId;
    
    echo "Получено голосовое сообщение длительностью {$duration} секунд";
    
    // Отправка обратно
    $api->sendVoice($chatId, $fileId);
}
```

---

## 🎯 Итог

✅ **Проблема полностью исправлена**

Все тесты пройдены, voice messages теперь обрабатываются корректно с полным извлечением всех полей, включая `duration`.

---

**Автор исправления:** AI Agent (cto.new)  
**Код ревью:** ✅ Approved
