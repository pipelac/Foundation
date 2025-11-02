# Список файлов модуля Rss2Tlg Fetch

Полный список созданных файлов и их назначение.

## Основные классы (src/Rss2Tlg/)

### DTO классы (src/Rss2Tlg/DTO/)

| Файл | Описание | Строк кода |
|------|----------|-----------|
| `FeedConfig.php` | Конфигурация RSS/Atom источника с валидацией | ~120 |
| `FeedState.php` | Состояние источника (ETag, Last-Modified, backoff) | ~170 |
| `RawItem.php` | Нормализованный элемент ленты с content hash | ~250 |
| `FetchResult.php` | Результат операции fetch с метриками | ~140 |

**Итого DTO:** ~680 строк

### Основные классы (src/Rss2Tlg/)

| Файл | Описание | Строк кода |
|------|----------|-----------|
| `FetchRunner.php` | Основной класс опроса с Conditional GET | ~650 |
| `FeedStateRepository.php` | Репозиторий для работы с БД состояния | ~240 |

**Итого основные классы:** ~890 строк

## Документация (src/Rss2Tlg/docs/)

| Файл | Описание | Размер |
|------|----------|--------|
| `README.md` | Полная документация модуля | ~350 строк |
| `API.md` | Справочник по API всех классов | ~550 строк |
| `schema.sql` | SQL схема таблицы feed_state | ~60 строк |
| `config.example.json` | Пример конфигурации источников | ~60 строк |

**Итого документация:** ~1020 строк

## Руководства (src/Rss2Tlg/)

| Файл | Описание | Размер |
|------|----------|--------|
| `README.md` | Главный README модуля с обзором | ~400 строк |
| `INSTALL.md` | Подробная инструкция по установке | ~350 строк |
| `QUICKSTART.md` | Быстрый старт за 5 минут | ~250 строк |
| `FILELIST.md` | Этот файл — список всех файлов | ~80 строк |

**Итого руководства:** ~1080 строк

## Примеры (examples/rss2tlg/)

| Файл | Описание | Строк кода |
|------|----------|-----------|
| `quick_test.php` | Быстрый тест DTO классов без БД | ~100 |
| `parse_rss_demo.php` | Демонстрация парсинга RSS без БД | ~200 |
| `fetch_single.php` | Опрос одного источника с детальным выводом | ~200 |
| `fetch_example.php` | Опрос всех источников из конфига | ~180 |

**Итого примеры:** ~680 строк

## Unit тесты (tests/Rss2Tlg/DTO/)

| Файл | Описание | Строк кода |
|------|----------|-----------|
| `FeedConfigTest.php` | Тесты для FeedConfig | ~140 |
| `FeedStateTest.php` | Тесты для FeedState | ~190 |

**Итого тесты:** ~330 строк

---

## Общая статистика

| Категория | Файлов | Строк кода/текста |
|-----------|--------|-------------------|
| DTO классы | 4 | ~680 |
| Основные классы | 2 | ~890 |
| Документация | 4 | ~1020 |
| Руководства | 4 | ~1080 |
| Примеры | 4 | ~680 |
| Unit тесты | 2 | ~330 |
| **ИТОГО** | **20** | **~4680** |

## Структура директорий

```
src/Rss2Tlg/
├── DTO/
│   ├── FeedConfig.php
│   ├── FeedState.php
│   ├── RawItem.php
│   └── FetchResult.php
├── FetchRunner.php
├── FeedStateRepository.php
├── README.md
├── INSTALL.md
├── QUICKSTART.md
├── FILELIST.md
└── docs/
    ├── README.md
    ├── API.md
    ├── schema.sql
    └── config.example.json

examples/rss2tlg/
├── quick_test.php
├── parse_rss_demo.php
├── fetch_single.php
└── fetch_example.php

tests/Rss2Tlg/DTO/
├── FeedConfigTest.php
└── FeedStateTest.php
```

## Зависимости

### Внешние библиотеки
- `simplepie/simplepie` ^1.8 — RSS/Atom парсер
- `guzzlehttp/guzzle` ^7.8 — HTTP клиент
- `fivefilters/readability.php` ^3.1 — Извлечение контента

### Внутренние компоненты проекта
- `App\Component\MySQL` — Обёртка над PDO для работы с БД
- `App\Component\Logger` — Логирование событий
- `App\Component\Http` — HTTP клиент с retry
- `App\Config\ConfigLoader` — Загрузчик JSON конфигов

## Требования к системе

### Минимальные
- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.3+
- 256 MB RAM
- 50 MB свободного места на диске

### Рекомендуемые
- PHP 8.2+
- MySQL 8.0+ / MariaDB 10.6+
- 512 MB RAM
- 100 MB свободного места

### Расширения PHP
- `json` — Работа с JSON конфигами
- `curl` — HTTP запросы
- `pdo` — Работа с БД
- `pdo_mysql` — MySQL драйвер
- `dom` — Парсинг XML/RSS
- `mbstring` — Работа с UTF-8

## База данных

### Таблицы
- `rss2tlg_feed_state` — Состояние источников

### Размер данных
- ~200 байт на источник
- Для 100 источников: ~20 KB
- Для 1000 источников: ~200 KB

### Индексы
- PRIMARY KEY на `id`
- UNIQUE KEY на `feed_id`
- UNIQUE KEY на `url`
- KEY на `backoff_until`
- KEY на `error_count`

## Производительность

### Типичное время выполнения
- Fetch 200 OK: 1-3 секунды
- Fetch 304 Not Modified: 0.1-0.3 секунды
- Parse RSS (30 items): 0.5-1.5 секунды

### Память
- Базовый overhead: ~10 MB
- На источник: ~1-2 MB (зависит от размера ленты)
- Рекомендуемый memory_limit: 128 MB

### Трафик
- Первый запрос: Полный размер RSS (обычно 10-100 KB)
- Последующие при 304: ~500 байт (только заголовки)
- Экономия: ~90% при использовании Conditional GET

## Лицензия

Proprietary — часть проекта Rss2Tlg

## Версия

**1.0.0** — Первый релиз модуля fetch

## Changelog

### v1.0.0 (2024-11-02)
- ✅ Реализован FetchRunner с Conditional GET
- ✅ Добавлены DTO: FeedConfig, FeedState, RawItem, FetchResult
- ✅ Реализован FeedStateRepository для работы с БД
- ✅ Добавлен Exponential Backoff при ошибках
- ✅ Реализован Content Hash для дедупликации
- ✅ Добавлены метрики операций
- ✅ Написана полная документация (README, INSTALL, API)
- ✅ Созданы примеры использования
- ✅ Добавлены unit тесты для DTO

## Автор

Создано для проекта **Rss2Tlg** — агрегатор RSS/Atom лент с публикацией в Telegram.

---

**Следующая версия (v1.1.0) — планируется:**
- Поддержка webhook уведомлений о новых элементах
- Расширенная фильтрация элементов
- Поддержка аутентификации для закрытых лент
- Экспорт метрик в Prometheus
- WebUI для мониторинга источников
