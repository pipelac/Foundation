# Руководство по миграции: Базовые классы в BaseUtils

## 📅 Дата: 2025-11-07

## 🎯 Цель миграции

Централизация всех базовых классов в единой директории `src/BaseUtils/` для:
- Улучшения организации кода
- Упрощения зависимостей между модулями
- Обеспечения единой точки управления базовой функциональностью

## 📦 Что было перенесено

### Базовые классы (из `src/` → `src/BaseUtils/`)

```
Email.class.php                → src/BaseUtils/Email.class.php
Http.class.php                 → src/BaseUtils/Http.class.php
Logger.class.php               → src/BaseUtils/Logger.class.php
MySQL.class.php                → src/BaseUtils/MySQL.class.php
MySQLConnectionFactory.class.php → src/BaseUtils/MySQLConnectionFactory.class.php
NetworkUtil.class.php          → src/BaseUtils/NetworkUtil.class.php
OpenAi.class.php               → src/BaseUtils/OpenAi.class.php
OpenRouter.class.php           → src/BaseUtils/OpenRouter.class.php
OpenRouterMetrics.class.php    → src/BaseUtils/OpenRouterMetrics.class.php
ProxyPool.class.php            → src/BaseUtils/ProxyPool.class.php
Rss.class.php                  → src/BaseUtils/Rss.class.php
Snmp.class.php                 → src/BaseUtils/Snmp.class.php
SnmpOid.class.php              → src/BaseUtils/SnmpOid.class.php
Telegram.class.php             → src/BaseUtils/Telegram.class.php
WebtExtractor.class.php        → src/BaseUtils/WebtExtractor.class.php
htmlWebProxyList.class.php     → src/BaseUtils/htmlWebProxyList.class.php
```

### Директории (из `src/` → `src/BaseUtils/`)

```
Cache/      → src/BaseUtils/Cache/
Config/     → src/BaseUtils/Config/
Exception/  → src/BaseUtils/Exception/
Netmap/     → src/BaseUtils/Netmap/
```

### Документация и примеры

```
examples/*.php    → src/BaseUtils/examples/
examples/*.md     → src/BaseUtils/docs/
```

## 🔄 Изменения в импортах

### До миграции

```php
use App\Component\Config\ConfigLoader;
```

### После миграции

```php
use App\Component\Config\ConfigLoader;
```

## ✅ Что было обновлено автоматически

### 1. autoload.php

```php
// До:
'App\\Component\\' => __DIR__ . '/src/',

// После:
'App\\Component\\' => __DIR__ . '/src/BaseUtils/',
```

### 2. Все примеры в проектах

- ✅ `src/TelegramBot/bin/*.php` - обновлены импорты ConfigLoader
- ✅ `src/TelegramBot/examples/*.php` - обновлены импорты и пути к autoload.php
- ✅ `src/Rss2Tlg/examples/*.php` - обновлены импорты ConfigLoader
- ✅ `src/Rss2Tlg/tests/*.php` - обновлены импорты ConfigLoader
- ✅ `src/UTM/examples/*.php` - обновлены импорты ConfigLoader
- ✅ `examples/*.php` - обновлены импорты ConfigLoader
- ✅ `src/BaseUtils/examples/*.php` - обновлены пути к autoload.php

### 3. Базовые классы

- ✅ `ProxyPool.class.php` - обновлен импорт ConfigLoader
- ✅ `htmlWebProxyList.class.php` - обновлен импорт ConfigLoader

## 📝 Что НЕ требует изменений

### Namespace базовых классов

Все классы остались в namespace `App\Component\*`:

```php
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\Http;
// и т.д.
```

**Важно:** Физическое расположение файлов изменилось, но namespace остался прежним!

### Использование классов в коде

Все существующие use-statements для базовых классов продолжают работать:

```php
// Это всё еще работает!
use App\Component\Logger;
use App\Component\MySQL;
use App\Component\OpenRouter;
```

### Подключение autoload.php

Все относительные пути к `autoload.php` продолжают работать:

```php
// Из src/TelegramBot/examples/
require_once __DIR__ . '/../../../autoload.php';

// Из src/Rss2Tlg/examples/
require_once __DIR__ . '/../../../vendor/autoload.php';
```

## 🧪 Тестирование после миграции

### Проверьте работу примеров:

```bash
# BaseUtils примеры
php src/BaseUtils/examples/logger_example.php
php src/BaseUtils/examples/mysql_example.php

# TelegramBot
php src/TelegramBot/examples/telegram_bot_polling_example.php

# Rss2Tlg
php src/Rss2Tlg/examples/fetch_example.php

# UTM
php src/UTM/examples/utm_account_example.php
```

### Проверьте E2E тесты:

```bash
# Rss2Tlg E2E тест
php src/Rss2Tlg/tests/tests_rss2tlg_e2e_v5.php
```

## 🔍 Поиск проблем

Если возникли ошибки типа "Class not found", проверьте:

1. **Импорт ConfigLoader:**
   ```bash
   grep -r "use App\\Config\\ConfigLoader" src/
   ```
   
   Должно вернуть 0 результатов (все должны быть `App\Component\Config\ConfigLoader`)

2. **Пути к autoload.php:**
   ```bash
   grep -r "__DIR__ . '/../autoload.php'" src/
   ```
   
   В `src/*/examples/` должны быть `/../../../autoload.php`

3. **Проверка autoload.php:**
   ```bash
   cat autoload.php | grep "App\\\\Component"
   ```
   
   Должно показать путь к `src/BaseUtils/`

## 📚 Новая структура проекта

```
/home/engine/project/
├── autoload.php                    # ✅ Обновлен
├── src/
│   ├── BaseUtils/                  # 🆕 Новая директория
│   │   ├── Cache/
│   │   ├── Config/
│   │   ├── Exception/
│   │   ├── Netmap/
│   │   ├── docs/
│   │   ├── examples/               # Примеры использования базовых классов
│   │   ├── tests/
│   │   ├── *.class.php             # Все базовые классы
│   │   ├── README.md
│   │   └── MIGRATION_GUIDE.md
│   ├── TelegramBot/
│   │   ├── bin/                    # ✅ Обновлены импорты
│   │   ├── examples/               # ✅ Обновлены импорты
│   │   └── ...
│   ├── Rss2Tlg/
│   │   ├── examples/               # ✅ Обновлены импорты
│   │   ├── tests/                  # ✅ Обновлены импорты
│   │   └── ...
│   └── UTM/
│       ├── examples/               # ✅ Обновлены импорты
│       └── ...
└── examples/                       # ✅ Обновлены импорты
```

## ⚠️ Важные замечания

1. **Старые файлы не удалены автоматически**
   - Файлы в `src/Cache/`, `src/Config/`, `src/Exception/`, `src/Netmap/` сохранены
   - Удалите их вручную после полного тестирования

2. **Config директория в корне**
   - `/home/engine/project/Config/` сохранена (содержит конфигурации проектов)
   - Не путать с `src/BaseUtils/Config/` (содержит класс ConfigLoader)

3. **Примеры в корне**
   - `/home/engine/project/examples/` сохранены и обновлены
   - Копии созданы в `src/BaseUtils/examples/`

## 🎉 Преимущества новой структуры

✅ Все базовые классы в одном месте  
✅ Упрощена навигация по проекту  
✅ Улучшена модульность  
✅ Легче поддерживать зависимости  
✅ Проще тестировать базовую функциональность  
✅ Четкое разделение между базовыми классами и проектными модулями  

## 📞 Поддержка

При возникновении проблем проверьте:
1. Этот файл (MIGRATION_GUIDE.md)
2. src/BaseUtils/README.md
3. Лог файлы проекта

---

**Версия:** 2.0  
**Дата:** 2025-11-07  
**Автор:** AI DevOps Agent
