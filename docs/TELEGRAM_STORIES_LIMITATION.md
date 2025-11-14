# Ограничения работы со Stories в Telegram Bot API

## Проблема

Telegram Stories (истории) **невозможно** использовать через стандартный Bot API.

## Причины

### 1. Bot API не поддерживает Stories

Официальная документация Telegram Bot API (https://core.telegram.org/bots/api) не содержит методов для работы со Stories:
- Нет метода `sendStory`
- Нет метода `editStory`
- Нет метода `deleteStory`
- Нет метода `getStories`

Stories доступны только в клиентском приложении Telegram и через Client API.

### 2. Stories требуют Client API (MTProto)

Stories работают только через:
- **Telegram Client API (MTProto protocol)**
- Требуется user session (номер телефона + код подтверждения)
- Нельзя использовать bot token

### 3. Архитектурные различия

| Характеристика | Bot API | Client API (для Stories) |
|----------------|---------|--------------------------|
| Авторизация | Bot token | Phone number + code |
| Протокол | HTTPS REST API | MTProto (binary) |
| Тип аккаунта | Bot account | User account |
| Stories | ❌ Не поддерживается | ✅ Поддерживается |
| Библиотека | Любой HTTP клиент | MadelineProto, Telethon и т.д. |

## Возможные решения

### Вариант 1: Client API через MadelineProto (PHP)

```php
// Требуется установка: composer require danog/madelinoproto

use danog\MadelineProto\API;

$MadelineProto = new API('session.madeline');
$MadelineProto->start();

// Публикация Story
$MadelineProto->stories->sendStory([
    'peer' => '@channel',
    'media' => [
        '_' => 'inputMediaUploadedPhoto',
        'file' => 'photo.jpg'
    ],
    'caption' => 'Story caption'
]);
```

**Недостатки:**
- Требуется user session (не bot token)
- Нужен номер телефона и SMS-код для каждой авторизации
- Риск блокировки аккаунта за "неправильное" использование
- Сложнее в поддержке и развертывании

### Вариант 2: Использовать обычные посты канала

Вместо Stories можно использовать обычные посты в канале:

```php
// Работает через Bot API
$api->sendPhoto('@channel', 'photo.jpg', [
    'caption' => 'Post caption'
]);
```

**Преимущества:**
- Работает через обычный Bot API
- Не требует user session
- Стабильно и надежно
- Официально поддерживается

**Недостатки:**
- Это не Stories (посты остаются навсегда, если не удалить)
- Нет автоматического исчезновения через 24 часа
- Другой UI/UX

## Рекомендации

Для корпоративного использования **рекомендуется использовать обычные посты** вместо Stories:

1. **Стабильность**: Bot API официально поддерживается
2. **Безопасность**: Нет риска блокировки аккаунта
3. **Простота**: Не требуется сложная инфраструктура
4. **Надежность**: Работает через обычный HTTP REST API

Если Stories критически важны, придется:
1. Использовать отдельный user account (не bot)
2. Установить MadelineProto
3. Реализовать авторизацию через телефон
4. Принять риски, связанные с использованием неофициального API

## Заключение

**Stories через Bot API реализовать невозможно.** Это ограничение самого Telegram, а не конкретной реализации.

Для работы с контентом каналов через бота используйте обычные методы Bot API:
- `sendMessage()`
- `sendPhoto()`
- `sendVideo()`
- `editMessageText()`
- `editMessageCaption()`
- `deleteMessage()`

## Ссылки

- [Telegram Bot API Documentation](https://core.telegram.org/bots/api)
- [Telegram Client API (MTProto)](https://core.telegram.org/api)
- [MadelineProto Library](https://docs.madelineproto.xyz/)
