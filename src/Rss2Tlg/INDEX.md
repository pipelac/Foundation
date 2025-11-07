# Rss2Tlg — Навигация по проекту

**RSS to Telegram Aggregator**  
**Версия**: 2.0.0  
**Статус**: PRODUCTION READY ✅

---

## 🚀 Быстрый старт

Новый пользователь? Начните здесь:

1. **[README.md](README.md)** — главный README проекта
2. **[INSTALL.md](INSTALL.md)** — установка и настройка
3. **[QUICKSTART.md](QUICKSTART.md)** — быстрый старт за 5 минут

---

## 📚 Документация

### Основная документация

- **[README.md](README.md)** — общее описание проекта
- **[PROJECT_STRUCTURE.md](PROJECT_STRUCTURE.md)** — полная структура проекта
- **[FILELIST.md](FILELIST.md)** — список всех файлов (65 файлов)
- **[INSTALL.md](INSTALL.md)** — инструкция по установке
- **[QUICKSTART.md](QUICKSTART.md)** — быстрый старт

### Техническая документация

- **[docs/API.md](docs/API.md)** — справочник по API классов
- **[docs/README.md](docs/README.md)** — подробная документация модуля
- **[REFACTORING.md](REFACTORING.md)** — история рефакторинга
- **[SUMMARY.md](SUMMARY.md)** — общая сводка проекта

### Документация по AI

- **[AI_FALLBACK_MODELS.md](AI_FALLBACK_MODELS.md)** — fallback модели AI
- **[docs/AI_FALLBACK_UPDATE.md](docs/AI_FALLBACK_UPDATE.md)** — обновление AI

### Документация по медиа

- **[docs/RSS2TLG_MEDIA_SUPPORT_UPDATE.md](docs/RSS2TLG_MEDIA_SUPPORT_UPDATE.md)** — поддержка медиа

### Миграция

- **[MIGRATION_GUIDE.md](MIGRATION_GUIDE.md)** — руководство по миграции
- **[MIGRATION_REPORT.md](MIGRATION_REPORT.md)** — отчет о миграции

---

## 💻 Код

### Основные классы

| Класс | Назначение | Файл |
|-------|-----------|------|
| **FetchRunner** | Опрос RSS источников | [FetchRunner.php](FetchRunner.php) |
| **ItemRepository** | Хранение новостей | [ItemRepository.php](ItemRepository.php) |
| **AIAnalysisService** | AI анализ новостей | [AIAnalysisService.php](AIAnalysisService.php) |
| **AIAnalysisRepository** | Хранение AI анализов | [AIAnalysisRepository.php](AIAnalysisRepository.php) |
| **ContentExtractorService** | Извлечение контента | [ContentExtractorService.php](ContentExtractorService.php) |
| **PublicationRepository** | Публикации в Telegram | [PublicationRepository.php](PublicationRepository.php) |
| **FeedStateRepository** | Состояние RSS лент | [FeedStateRepository.php](FeedStateRepository.php) |
| **PromptManager** | Управление промптами | [PromptManager.php](PromptManager.php) |

### DTO классы

| Класс | Назначение | Файл |
|-------|-----------|------|
| **FeedConfig** | Конфигурация источника | [DTO/FeedConfig.php](DTO/FeedConfig.php) |
| **FeedState** | Состояние источника | [DTO/FeedState.php](DTO/FeedState.php) |
| **RawItem** | Нормализованный элемент | [DTO/RawItem.php](DTO/RawItem.php) |
| **FetchResult** | Результат fetch операции | [DTO/FetchResult.php](DTO/FetchResult.php) |

---

## 🎯 Примеры использования

### Примеры кода

- **[examples/fetch_single.php](examples/fetch_single.php)** — опрос одного источника
- **[examples/fetch_example.php](examples/fetch_example.php)** — опрос всех источников
- **[examples/production_example.php](examples/production_example.php)** — production пример

### Конфигурация

- **[docs/config.example.json](docs/config.example.json)** — пример конфигурации
- **[config/rss2tlg_e2e_test.json](config/rss2tlg_e2e_test.json)** — конфигурация E2E тестов
- **[config/rss2tlg_e2e_v4_test.json](config/rss2tlg_e2e_v4_test.json)** — конфигурация E2E v4

---

## 🧪 Тестирование

### Запуск тестов

```bash
# Главный E2E тест с AI failover
php src/Rss2Tlg/tests/tests_rss2tlg_e2e_v4.php

# E2E тест Telegram + AI
php src/Rss2Tlg/tests/tests_telegram_ai_e2e.php

# Финальный интеграционный тест
php src/Rss2Tlg/tests/final_integration_test.php
```

### Тестовые файлы

- **[tests/tests_rss2tlg_e2e_v4.php](tests/tests_rss2tlg_e2e_v4.php)** — главный E2E тест ⭐
- **[tests/tests_telegram_ai_e2e.php](tests/tests_telegram_ai_e2e.php)** — Telegram + AI тест
- **[tests/final_integration_test.php](tests/final_integration_test.php)** — интеграционный тест

### Отчеты тестирования

Все отчеты находятся в **[tests/reports/](tests/reports/)**:

#### Главные отчеты

- **[tests/reports/INDEX.md](tests/reports/INDEX.md)** — навигация по отчетам ⭐
- **[tests/reports/E2E_FAILOVER_FINAL_SUMMARY.md](tests/reports/E2E_FAILOVER_FINAL_SUMMARY.md)** — финальный отчет AI failover
- **[tests/reports/E2E_V4_FINAL_REPORT.md](tests/reports/E2E_V4_FINAL_REPORT.md)** — финальный отчет E2E v4

#### Руководства

- **[tests/reports/E2E_TESTING_GUIDE.md](tests/reports/E2E_TESTING_GUIDE.md)** — руководство по тестированию
- **[tests/reports/HOW_TO_RUN_E2E_TESTS.md](tests/reports/HOW_TO_RUN_E2E_TESTS.md)** — как запустить тесты
- **[docs/HOW_TO_RUN_E2E_V4.txt](docs/HOW_TO_RUN_E2E_V4.txt)** — инструкция E2E v4

#### Результаты

- **[tests/reports/E2E_TEST_RESULTS.txt](tests/reports/E2E_TEST_RESULTS.txt)** — детальные результаты
- **[tests/reports/E2E_V4_SUMMARY.txt](tests/reports/E2E_V4_SUMMARY.txt)** — сводка v4
- **[tests/reports/TESTING_COMPLETE.md](tests/reports/TESTING_COMPLETE.md)** — завершение тестирования

### SQL дампы

Все CSV дампы находятся в **[tests/sql/](tests/sql/)**:

- **rss2tlg_items_v4_20251107094050.csv** — 316 новостей
- **rss2tlg_ai_analysis_v4_20251107094050.csv** — 5 AI анализов
- **rss2tlg_feed_state_v4_20251107094050.csv** — 5 источников
- **rss2tlg_publications_v4_20251107094050.csv** — 6 публикаций

---

## 🗄️ База данных

### SQL схемы

- **[sql/rss2tlg_schema.sql](sql/rss2tlg_schema.sql)** — полная схема БД ⭐
- **[sql/rss2tlg_schema_clean.sql](sql/rss2tlg_schema_clean.sql)** — чистая схема
- **[sql/schema_ai_analysis.sql](sql/schema_ai_analysis.sql)** — схема AI анализа
- **[docs/schema.sql](docs/schema.sql)** — базовая схема

### Установка схемы

```bash
# Полная схема (рекомендуется)
mysql -u root -p rss2tlg < src/Rss2Tlg/sql/rss2tlg_schema.sql

# Только базовые таблицы
mysql -u root -p rss2tlg < src/Rss2Tlg/docs/schema.sql
```

---

## 🤖 AI и промпты

### Промпты для AI анализа

- **[prompts/news_analysis_ru.xml](prompts/news_analysis_ru.xml)** — русский промпт
- **[prompts/news_analysis_en.xml](prompts/news_analysis_en.xml)** — английский промпт

### Документация по AI

- **[AI_FALLBACK_MODELS.md](AI_FALLBACK_MODELS.md)** — описание fallback моделей
- **[docs/AI_FALLBACK_UPDATE.md](docs/AI_FALLBACK_UPDATE.md)** — обновление fallback

### Использование

```php
use App\Rss2Tlg\PromptManager;

$promptManager = new PromptManager('src/Rss2Tlg/prompts');
$prompt = $promptManager->getPrompt('news_analysis_ru.xml');
```

---

## 📊 Статистика проекта

### Файлы

- **Всего файлов**: 65
- **PHP классы**: 12 (8 основных + 4 DTO)
- **Тесты**: 3
- **Примеры**: 3
- **Конфигурация**: 2
- **Документация**: 36
- **SQL схемы**: 3
- **CSV дампы**: 4
- **Промпты**: 2

### Строки кода

- **Основные классы**: ~2814 строк
- **DTO**: ~680 строк
- **Тесты**: ~1470 строк
- **Примеры**: ~680 строк
- **Всего**: ~5834 строки

---

## 🔧 Разработка

### Требования

- **PHP**: 8.1+
- **MySQL**: 5.7+ / MariaDB 10.3+
- **Composer**: 2.0+
- **Расширения PHP**: json, curl, pdo, pdo_mysql, dom, mbstring

### Установка

```bash
# 1. Клонирование
git clone <repo_url>
cd project

# 2. Установка зависимостей
composer install

# 3. Настройка БД
mysql -u root -p rss2tlg < src/Rss2Tlg/sql/rss2tlg_schema.sql

# 4. Конфигурация
cp src/Rss2Tlg/docs/config.example.json src/Rss2Tlg/config/production.json
nano src/Rss2Tlg/config/production.json

# 5. Запуск примера
php src/Rss2Tlg/examples/fetch_single.php
```

### Структура кода

```
src/Rss2Tlg/
├── Repositories (4) — работа с БД
├── Services (3) — бизнес-логика
├── Core (2) — основные компоненты
└── DTO (4) — Data Transfer Objects
```

---

## 🌐 Зависимости

### Внешние компоненты

Проект использует компоненты из `src/Component/`:

- **MySQL** — работа с БД
- **Logger** — логирование
- **Http** — HTTP клиент
- **Rss** — парсинг RSS/Atom
- **OpenRouter** — AI анализ
- **WebtExtractor** — извлечение контента
- **TelegramBot** — публикация в Telegram

### Composer пакеты

- `simplepie/simplepie` ^1.8
- `guzzlehttp/guzzle` ^7.8
- `fivefilters/readability.php` ^3.1

---

## 📖 Полезные ссылки

### Внутренняя документация

- [PROJECT_STRUCTURE.md](PROJECT_STRUCTURE.md) — структура проекта
- [FILELIST.md](FILELIST.md) — список всех файлов
- [MIGRATION_GUIDE.md](MIGRATION_GUIDE.md) — руководство по миграции
- [REFACTORING.md](REFACTORING.md) — история изменений

### Для начинающих

- [README.md](README.md) — начните здесь
- [QUICKSTART.md](QUICKSTART.md) — быстрый старт
- [INSTALL.md](INSTALL.md) — установка

### Для разработчиков

- [docs/API.md](docs/API.md) — API справочник
- [examples/](examples/) — примеры кода
- [docs/config.example.json](docs/config.example.json) — пример конфигурации

### Для тестировщиков

- [tests/reports/INDEX.md](tests/reports/INDEX.md) — навигация по отчетам
- [tests/tests_rss2tlg_e2e_v4.php](tests/tests_rss2tlg_e2e_v4.php) — главный тест
- [docs/HOW_TO_RUN_E2E_V4.txt](docs/HOW_TO_RUN_E2E_V4.txt) — как запустить

---

## ✅ Статус проекта

**PRODUCTION READY** ✅

- ✅ Все компоненты протестированы
- ✅ AI Failover работает на 100%
- ✅ Unicode Fix проверен
- ✅ 0 критических ошибок
- ✅ Документация полная
- ✅ Структура организована

---

## 📞 Помощь и поддержка

### Возникли вопросы?

1. Проверьте [README.md](README.md)
2. Посмотрите [примеры](examples/)
3. Прочитайте [документацию](docs/)
4. Изучите [API справочник](docs/API.md)

### Нашли баг?

1. Проверьте [отчеты тестирования](tests/reports/)
2. Запустите [E2E тест](tests/tests_rss2tlg_e2e_v4.php)
3. Создайте issue с подробным описанием

---

**Версия**: 2.0.0  
**Дата**: 2025-11-07  
**Автор**: Rss2Tlg Development Team

**© 2025 Rss2Tlg Project. All rights reserved.**
