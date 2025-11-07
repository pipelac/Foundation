# Changelog - Rss2Tlg Module

## [2025-11-07] INoT v1 Prompt Integration

### Added
- ✅ **INoT_v1.xml** — Production-ready промпт для глубокого AI-анализа новостей
  - 20-уровневая шкала важности (от спама до событий мирового масштаба)
  - Структурированные данные для дедупликации новостей
  - Multi-agent анализ (Translator, Validator_Accuracy, Validator_Russian, Analyzer)
  - Автоматический перевод иноязычных новостей с валидацией качества
  - Оптимизация кеширования для DeepSeek/Qwen моделей

### Changed
- ✅ **prompts/** перемещена из корня репозитория в `src/Rss2Tlg/prompts/`
  - Все промпты теперь являются частью модуля Rss2Tlg
  - Упрощена структура проекта
- ✅ **config/rss2tlg_e2e_v4_test.json** обновлена
  - Все feeds теперь используют `"prompt_id": "INoT_v1"`
  - Унификация анализа для русских и английских новостей
- ✅ **tests/tests_rss2tlg_e2e_v4.php** обновлен
  - Путь к промптам: `__DIR__ . '/../prompts'`
  - Динамическое определение prompt_id из FeedConfig
  - Удалена жесткая привязка к языку (ru/en)

### Documentation
- ✅ **prompts/README.md** — полная документация по AI-промптам
  - Описание структуры INoT_v1
  - Примеры конфигурации
  - Инструкции по разработке новых промптов

### Removed
- ❌ **/prompts/** — удалена корневая папка (перенесена в модуль)
- ❌ **MIGRATION_COMPLETED.md** — удален из корня
- ❌ **PRODUCTION_READINESS_REPORT.md** — удален из корня
- ❌ **src/UTM/docs/CHANGELOG_UTM_ACCOUNT.md** — удален (не относится к Rss2Tlg)

## [Previous Updates]

### V4.0 - E2E Testing & AI Fallback
- ✅ Полный E2E тестовый сценарий
- ✅ AI Fallback между моделями (бесплатные → платные)
- ✅ Публикация в Telegram Bot и Channel
- ✅ Метрики OpenRouter с кешированием

### V3.0 - AI Analysis Integration
- ✅ AIAnalysisService с multi-model support
- ✅ AIAnalysisRepository для хранения результатов
- ✅ PromptManager для управления промптами
- ✅ Интеграция с OpenRouter API

### V2.0 - Fetch Pipeline
- ✅ FetchRunner для опроса RSS/Atom лент
- ✅ Conditional GET (ETag, Last-Modified)
- ✅ Exponential Backoff
- ✅ FeedStateRepository

### V1.0 - Initial Release
- ✅ DTO классы (FeedConfig, FeedState, RawItem, FetchResult)
- ✅ ItemRepository для хранения новостей
- ✅ PublicationRepository для отслеживания публикаций
- ✅ ContentExtractorService для извлечения контента

---

## Migration Guide

### Upgrading to INoT_v1

Если вы использовали старые промпты `news_analysis_ru` или `news_analysis_en`:

1. Обновите конфигурацию feeds:
```json
{
  "feeds": [
    {
      "id": 1,
      "url": "https://example.com/rss",
      "language": "ru",
      "prompt_id": "INoT_v1"  // ← Измените здесь
    }
  ]
}
```

2. Обновите код инициализации PromptManager:
```php
// Старый путь (корневая папка)
$promptManager = new PromptManager(__DIR__ . '/prompts', $logger);

// Новый путь (модуль Rss2Tlg)
$promptManager = new PromptManager(__DIR__ . '/src/Rss2Tlg/prompts', $logger);
```

3. Обновите тесты:
```php
// В тестах используйте относительный путь
$promptManager = new PromptManager(__DIR__ . '/../prompts', $logger);
```

### Breaking Changes

- ⚠️ Папка `/prompts/` удалена из корня репозитория
- ⚠️ Старые промпты `news_analysis_ru` и `news_analysis_en` считаются legacy
- ⚠️ Рекомендуется миграция на `INoT_v1` для получения расширенных возможностей

### Benefits of INoT_v1

1. **Детальная калибровка важности** — 20 уровней вместо условного high/medium/low
2. **Дедупликация** — структурированные данные для сравнения похожих новостей
3. **Качественный перевод** — автоматическая валидация переводов с оценкой качества
4. **Кеширование промптов** — экономия токенов при работе с DeepSeek/Qwen
5. **Расширенная метаинформация** — impact_vector, quality_flags, semantic_fingerprint

---

**Версия:** 4.1  
**Дата обновления:** 2025-11-07  
**Статус:** Production Ready ✅
