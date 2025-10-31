# Changelog v1.0.0

## Рефакторинг структуры исключений

### Изменения в архитектуре

Все классы исключений перенесены в подпапки, соответствующие основным классам:

#### Структура до изменений:
```
src/Exception/
  ├── EmailException.php
  ├── EmailValidationException.php
  ├── HttpException.php
  └── ...
```

#### Структура после изменений:
```
src/Exception/
  ├── Email/
  │   ├── EmailException.php
  │   └── EmailValidationException.php
  ├── Http/
  │   ├── HttpException.php
  │   └── HttpValidationException.php
  ├── Logger/
  ├── MySQL/
  ├── NetworkUtil/
  ├── OpenAi/
  ├── OpenRouter/
  ├── ProxyPool/
  ├── Rss/
  ├── Snmp/
  ├── Telegram/
  ├── WebtExtractor/
  ├── htmlWebProxyList/
  └── Netmap/
```

### Обновление namespace

Все классы исключений теперь используют вложенные namespace:

**До:**
```php
namespace App\Component\Exception;
use App\Component\Exception\EmailException;
```

**После:**
```php
namespace App\Component\Exception\Email;
use App\Component\Exception\Email\EmailException;
```

### Очистка проекта

Удалены все тестовые и временные файлы:
- Тестовые скрипты: `test_*.php`, `demo_*.php`, `send_*.php`, `snmp_*.php`
- Тестовые папки: `logs_*`, `test_data_*`, `test_assets`, `test_media_real`
- Документация тестирования: `*_README.md`, `*_TEST_*.md`, `*_SUMMARY*.md`, `*_REPORT*.md`
- PHPUnit конфигурация: `phpunit.xml`, `.phpunit.cache`

### Обновление версии

Добавлена версия `1.0.0` в `composer.json`:
```json
{
    "name": "app/basic-utilities",
    "version": "1.0.0",
    ...
}
```

### Затронутые компоненты

Обновлены импорты исключений в следующих классах:
- `Email.class.php`
- `Http.class.php`
- `Logger.class.php`
- `MySQL.class.php`
- `MySQLConnectionFactory.class.php`
- `NetworkUtil.class.php`
- `OpenAi.class.php`
- `OpenRouter.class.php`
- `OpenRouterMetrics.class.php`
- `ProxyPool.class.php`
- `Rss.class.php`
- `Snmp.class.php`
- `SnmpOid.class.php`
- `Telegram.class.php`
- `WebtExtractor.class.php`
- `htmlWebProxyList.class.php`
- Все классы в `Netmap/`

### Обратная несовместимость

⚠️ **BREAKING CHANGE**: Изменены namespace всех классов исключений.

При использовании этих классов в внешнем коде необходимо обновить импорты:

```php
// Старый код
use App\Component\Exception\EmailException;

// Новый код
use App\Component\Exception\Email\EmailException;
```

### Тестирование

Все unit-тесты обновлены для работы с новой структурой исключений.

---

**Дата релиза:** 2024-10-31
**Тип релиза:** Major (Breaking Changes)
