# Руководство по миграции структуры проекта Rss2Tlg

**Дата миграции**: 2025-11-07  
**Версия**: 2.0.0

## 📦 Что изменилось

Все материалы, тесты и отчеты проекта **Rss2Tlg** были перенесены из корневых директорий в папку проекта `src/Rss2Tlg/`.

### ✅ Преимущества новой структуры

1. **Изоляция проекта** — все файлы в одном месте
2. **Удобная навигация** — логическая структура папок
3. **Простота развертывания** — одна папка для копирования
4. **Чистый корень репозитория** — нет разбросанных файлов
5. **Масштабируемость** — легко добавлять новые модули

## 🔄 Маппинг старых путей на новые

### Тесты

| Старый путь | Новый путь |
|-------------|------------|
| `tests_rss2tlg_e2e_v4.php` | `src/Rss2Tlg/tests/tests_rss2tlg_e2e_v4.php` |
| `tests_telegram_ai_e2e.php` | `src/Rss2Tlg/tests/tests_telegram_ai_e2e.php` |
| `final_integration_test.php` | `src/Rss2Tlg/tests/final_integration_test.php` |

### Конфигурация

| Старый путь | Новый путь |
|-------------|------------|
| `Config/rss2tlg_e2e_test.json` | `src/Rss2Tlg/config/rss2tlg_e2e_test.json` |
| `Config/rss2tlg_e2e_v4_test.json` | `src/Rss2Tlg/config/rss2tlg_e2e_v4_test.json` |

### Примеры

| Старый путь | Новый путь |
|-------------|------------|
| `examples/rss2tlg/fetch_example.php` | `src/Rss2Tlg/examples/fetch_example.php` |
| `examples/rss2tlg/fetch_single.php` | `src/Rss2Tlg/examples/fetch_single.php` |
| `examples/rss2tlg/production_example.php` | `src/Rss2Tlg/examples/production_example.php` |

### Промпты

| Старый путь | Новый путь |
|-------------|------------|
| `prompts/news_analysis_en.xml` | `src/Rss2Tlg/prompts/news_analysis_en.xml` |
| `prompts/news_analysis_ru.xml` | `src/Rss2Tlg/prompts/news_analysis_ru.xml` |

### SQL схемы

| Старый путь | Новый путь |
|-------------|------------|
| `rss2tlg_schema.sql` | `src/Rss2Tlg/sql/rss2tlg_schema.sql` |
| `rss2tlg_schema_clean.sql` | `src/Rss2Tlg/sql/rss2tlg_schema_clean.sql` |

### Документация

| Старый путь | Новый путь |
|-------------|------------|
| `RSS2TLG_MEDIA_SUPPORT_UPDATE.md` | `src/Rss2Tlg/docs/RSS2TLG_MEDIA_SUPPORT_UPDATE.md` |
| `E2E_V4_MASTER_REPORT.md` | `src/Rss2Tlg/docs/E2E_V4_MASTER_REPORT.md` |
| `FINAL_WORK_SUMMARY.md` | `src/Rss2Tlg/docs/FINAL_WORK_SUMMARY.md` |
| `HOW_TO_RUN_E2E_V4.txt` | `src/Rss2Tlg/docs/HOW_TO_RUN_E2E_V4.txt` |
| `AI_FALLBACK_UPDATE.md` | `src/Rss2Tlg/docs/AI_FALLBACK_UPDATE.md` |

### Отчеты тестирования

| Старый путь | Новый путь |
|-------------|------------|
| `tests/*.md` | `src/Rss2Tlg/tests/reports/*.md` |
| `tests/*.txt` | `src/Rss2Tlg/tests/reports/*.txt` |

### SQL дампы

| Старый путь | Новый путь |
|-------------|------------|
| `tests/sql/*.csv` | `src/Rss2Tlg/tests/sql/*.csv` |

## 🎯 Обновление скриптов и ссылок

### 1. Обновление путей к конфигурации

**Было:**
```php
$config = ConfigLoader::load('Config/rss2tlg_e2e_v4_test.json');
```

**Стало:**
```php
$config = ConfigLoader::load('src/Rss2Tlg/config/rss2tlg_e2e_v4_test.json');
```

### 2. Обновление путей к промптам

**Было:**
```php
$promptManager = new PromptManager('prompts');
```

**Стало:**
```php
$promptManager = new PromptManager('src/Rss2Tlg/prompts');
```

### 3. Запуск тестов

**Было:**
```bash
php tests_rss2tlg_e2e_v4.php
```

**Стало:**
```bash
php src/Rss2Tlg/tests/tests_rss2tlg_e2e_v4.php
```

### 4. Импорт SQL схемы

**Было:**
```bash
mysql -u root -p rss2tlg < rss2tlg_schema.sql
```

**Стало:**
```bash
mysql -u root -p rss2tlg < src/Rss2Tlg/sql/rss2tlg_schema.sql
```

## 📁 Новая структура проекта

```
src/Rss2Tlg/
├── 📄 PHP Классы (8 файлов)
│   ├── AIAnalysisRepository.php
│   ├── AIAnalysisService.php
│   ├── ContentExtractorService.php
│   ├── FeedStateRepository.php
│   ├── FetchRunner.php
│   ├── ItemRepository.php
│   ├── PromptManager.php
│   └── PublicationRepository.php
│
├── 📂 DTO/ (4 файла)
│   ├── FeedConfig.php
│   ├── FeedState.php
│   ├── FetchResult.php
│   └── RawItem.php
│
├── 📂 config/ (2 файла)
│   ├── rss2tlg_e2e_test.json
│   └── rss2tlg_e2e_v4_test.json
│
├── 📂 docs/ (9 файлов)
│   ├── AI_FALLBACK_UPDATE.md
│   ├── API.md
│   ├── E2E_V4_MASTER_REPORT.md
│   ├── FINAL_WORK_SUMMARY.md
│   ├── HOW_TO_RUN_E2E_V4.txt
│   ├── README.md
│   ├── RSS2TLG_MEDIA_SUPPORT_UPDATE.md
│   ├── config.example.json
│   └── schema.sql
│
├── 📂 examples/ (3 файла)
│   ├── fetch_example.php
│   ├── fetch_single.php
│   └── production_example.php
│
├── 📂 prompts/ (2 файла)
│   ├── news_analysis_en.xml
│   └── news_analysis_ru.xml
│
├── 📂 sql/ (3 файла)
│   ├── rss2tlg_schema.sql
│   ├── rss2tlg_schema_clean.sql
│   └── schema_ai_analysis.sql
│
├── 📂 tests/ (3 файла + подпапки)
│   ├── final_integration_test.php
│   ├── tests_rss2tlg_e2e_v4.php
│   ├── tests_telegram_ai_e2e.php
│   │
│   ├── 📂 reports/ (17 файлов)
│   │   ├── AUTO_TABLE_CREATION_REPORT.md
│   │   ├── E2E_FAILOVER_FINAL_SUMMARY.md
│   │   ├── E2E_TESTING_FINAL_SUMMARY.md
│   │   ├── E2E_TESTING_GUIDE.md
│   │   ├── E2E_TEST_RESULTS.txt
│   │   ├── E2E_V4_FINAL_REPORT.md
│   │   ├── E2E_V4_README.txt
│   │   ├── E2E_V4_REPORT_20251107094050.md
│   │   ├── E2E_V4_SUMMARY.txt
│   │   ├── HOW_TO_RUN_E2E_TESTS.md
│   │   ├── INDEX.md
│   │   ├── INDEX_V4.md
│   │   ├── README.md
│   │   ├── README.txt
│   │   ├── TELEGRAM_TEST_SUMMARY.txt
│   │   ├── TESTING_COMPLETE.md
│   │   └── TEST_EXECUTION_SUMMARY.txt
│   │
│   └── 📂 sql/ (4 файла)
│       ├── rss2tlg_ai_analysis_v4_20251107094050.csv
│       ├── rss2tlg_feed_state_v4_20251107094050.csv
│       ├── rss2tlg_items_v4_20251107094050.csv
│       └── rss2tlg_publications_v4_20251107094050.csv
│
└── 📄 Документация (8 файлов)
    ├── AI_FALLBACK_MODELS.md
    ├── FILELIST.md
    ├── INSTALL.md
    ├── MIGRATION_GUIDE.md (этот файл)
    ├── PROJECT_STRUCTURE.md
    ├── QUICKSTART.md
    ├── README.md
    ├── REFACTORING.md
    └── SUMMARY.md
```

## 🚀 Быстрый старт с новой структурой

### 1. Установка

```bash
cd /path/to/project
composer install
composer dump-autoload
```

### 2. Настройка БД

```bash
mysql -u root -p rss2tlg < src/Rss2Tlg/sql/rss2tlg_schema.sql
```

### 3. Конфигурация

```bash
cp src/Rss2Tlg/docs/config.example.json src/Rss2Tlg/config/production.json
nano src/Rss2Tlg/config/production.json
```

### 4. Запуск примера

```bash
php src/Rss2Tlg/examples/fetch_single.php
```

### 5. Запуск E2E теста

```bash
php src/Rss2Tlg/tests/tests_rss2tlg_e2e_v4.php
```

## 📝 Обновление документации

### Локальные ссылки в Markdown

Все локальные ссылки в документации были обновлены для работы с новой структурой.

**Пример:**

**Было:**
```markdown
См. [конфигурацию](../Config/rss2tlg_e2e_v4_test.json)
```

**Стало:**
```markdown
См. [конфигурацию](config/rss2tlg_e2e_v4_test.json)
```

## ✅ Проверка миграции

Выполните следующие команды для проверки:

```bash
# 1. Проверка структуры
ls -la src/Rss2Tlg/

# 2. Проверка конфигурации
ls -la src/Rss2Tlg/config/

# 3. Проверка тестов
ls -la src/Rss2Tlg/tests/

# 4. Проверка отчетов
ls -la src/Rss2Tlg/tests/reports/

# 5. Проверка SQL дампов
ls -la src/Rss2Tlg/tests/sql/

# 6. Проверка примеров
ls -la src/Rss2Tlg/examples/

# 7. Проверка промптов
ls -la src/Rss2Tlg/prompts/

# 8. Проверка SQL схем
ls -la src/Rss2Tlg/sql/
```

### Ожидаемые результаты

```
✅ src/Rss2Tlg/ — 8 PHP классов + документация
✅ src/Rss2Tlg/DTO/ — 4 DTO класса
✅ src/Rss2Tlg/config/ — 2 JSON конфигурации
✅ src/Rss2Tlg/docs/ — 9 документов
✅ src/Rss2Tlg/examples/ — 3 примера
✅ src/Rss2Tlg/prompts/ — 2 XML промпта
✅ src/Rss2Tlg/sql/ — 3 SQL схемы
✅ src/Rss2Tlg/tests/ — 3 теста
✅ src/Rss2Tlg/tests/reports/ — 17 отчетов
✅ src/Rss2Tlg/tests/sql/ — 4 CSV дампа
```

## 🔍 Удаленные директории

Следующие пустые директории были удалены после миграции:

- `tests/sql/` — пусто, удалено
- `tests/` — содержало только пустую папку sql
- `examples/rss2tlg/` — пусто, удалено

## 📚 Обновленная документация

### Главные документы

1. **[README.md](README.md)** — главный README проекта
2. **[PROJECT_STRUCTURE.md](PROJECT_STRUCTURE.md)** — полная структура проекта
3. **[FILELIST.md](FILELIST.md)** — список всех файлов с описанием
4. **[MIGRATION_GUIDE.md](MIGRATION_GUIDE.md)** — это руководство

### Для разработчиков

5. **[INSTALL.md](INSTALL.md)** — инструкция по установке
6. **[QUICKSTART.md](QUICKSTART.md)** — быстрый старт
7. **[docs/API.md](docs/API.md)** — справочник по API

### Для тестирования

8. **[tests/reports/INDEX.md](tests/reports/INDEX.md)** — навигация по отчетам
9. **[docs/HOW_TO_RUN_E2E_V4.txt](docs/HOW_TO_RUN_E2E_V4.txt)** — запуск E2E тестов

## 🎯 Рекомендации

### Для CI/CD

Обновите пути в CI/CD скриптах:

```yaml
# .gitlab-ci.yml или .github/workflows/*.yml
script:
  - php src/Rss2Tlg/tests/tests_rss2tlg_e2e_v4.php
```

### Для development окружения

Создайте симлинки для удобства (опционально):

```bash
# В корне проекта
ln -s src/Rss2Tlg/config config_rss2tlg
ln -s src/Rss2Tlg/tests tests_rss2tlg
```

### Для production

Копируйте только необходимое:

```bash
# Копирование только рабочих файлов (без тестов)
rsync -av --exclude='tests' --exclude='examples' \
  src/Rss2Tlg/ /var/www/production/src/Rss2Tlg/
```

## ⚠️ Breaking Changes

### Для существующих скриптов

Все скрипты, использующие старые пути, должны быть обновлены.

### Для autoload

Autoload не изменился — все классы по-прежнему в namespace `App\Rss2Tlg`.

### Для конфигураций

Пути к конфигурационным файлам должны быть обновлены.

## 💡 FAQ

### Вопрос: Нужно ли обновлять composer.json?

**Ответ:** Нет, autoload использует namespace, а не пути к файлам.

### Вопрос: Работают ли старые скрипты?

**Ответ:** Нет, пути к файлам изменились. Обновите пути согласно маппингу выше.

### Вопрос: Где теперь находятся SQL дампы?

**Ответ:** В `src/Rss2Tlg/tests/sql/`

### Вопрос: Как запустить тесты?

**Ответ:** `php src/Rss2Tlg/tests/tests_rss2tlg_e2e_v4.php`

### Вопрос: Можно ли откатить изменения?

**Ответ:** Да, используйте git:
```bash
git checkout HEAD~1
```

## 📞 Контакты

При возникновении проблем с миграцией см. основную документацию или создайте issue.

---

**Версия документа**: 1.0  
**Дата**: 2025-11-07  
**Автор**: Rss2Tlg Development Team  
**Статус**: PRODUCTION READY
