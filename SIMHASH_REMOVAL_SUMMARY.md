# Удаление библиотеки swatchion/simhash из проекта

## Дата выполнения
2024-11-04

## Причина
Библиотека swatchion/simhash оказалась неэффективной для задачи дедупликации новостей из различных RSS источников.

## Выполненные изменения

### 1. Удалены файлы
- `src/Rss2Tlg/SimhashService.php` - сервис для работы с Simhash
- `tests/Rss2Tlg/SimhashDeduplicationTest.php` - тесты Simhash дедупликации
- `config/rss2tlg_simhash_test.json` - конфигурация для тестирования Simhash
- `docs/RSS2TLG_SIMHASH_DEDUP_REPORT.md` - документация и отчет о тестировании

### 2. Обновлен composer.json
Удалена зависимость:
```json
"swatchion/simhash": "^1.0"
```

Выполнено: `composer update --no-interaction`

Результат: библиотека успешно удалена из vendor/.

### 3. Обновлен ItemRepository.php

#### Удалено:
- Свойство `private ?SimhashService $simhashService`
- Метод `setSimhashService(SimhashService $simhashService): void`
- Метод `getDuplicates(): array`
- Параметры `deduplicationHours` и `similarityThreshold` из метода `save()`
- Вся логика вычисления Simhash и поиска похожих новостей в методе `save()`
- Поля `simhash`, `is_duplicate`, `duplicate_of_id`, `hamming_distance` из SQL запросов
- Подсчет статистики simhash/duplicates в методе `getStats()`

#### Обновлено:
- **Документация класса**: изменена с "дедупликация по content_hash и Simhash" на "дедупликация по content_hash"
- **Метод save()**: упрощен до проверки только по content_hash (MD5)
- **Схема создания таблицы**: удалены колонки:
  - `simhash VARCHAR(64)`
  - `is_duplicate TINYINT(1)`
  - `duplicate_of_id INT UNSIGNED`
  - `hamming_distance INT`
- **Индексы**: удалены:
  - `idx_simhash`
  - `idx_is_duplicate`
  - `idx_duplicate_of_id`
- **COMMENT таблицы**: изменен с "с Simhash дедупликацией" на "с извлеченным контентом"

### 4. Результаты проверки

✅ Класс ItemRepository загружен корректно  
✅ Метод setSimhashService удален  
✅ Метод getDuplicates удален  
✅ Метод save() имеет 2 параметра (feedId, item)  
✅ Класс SimhashService удален  
✅ Библиотека swatchion/simhash удалена из vendor/  
✅ Нет упоминаний simhash/Simhash/SimHash в коде проекта  

## Текущая дедупликация

Система дедупликации теперь работает **только на основе content_hash (MD5)**:

- При сохранении новости вычисляется MD5 от контента
- Проверяется уникальность по полю `content_hash` (UNIQUE индекс)
- Если новость с таким хешем уже существует - возвращается ID существующей записи
- Новые новости сохраняются с новым ID

### Преимущества текущего подхода:
- ✅ Быстрая проверка через UNIQUE индекс
- ✅ Простота реализации
- ✅ Отсутствие ложноположительных срабатываний
- ✅ Нет зависимостей от внешних библиотек

### Ограничения:
- ❌ Не обнаруживает похожие (но не идентичные) новости
- ❌ Не работает для новостей с незначительными различиями

## Миграция существующей БД

Если у вас уже есть таблица `rss2tlg_items` с полями simhash, выполните:

```sql
-- Удаление полей Simhash
ALTER TABLE rss2tlg_items 
  DROP COLUMN simhash,
  DROP COLUMN is_duplicate,
  DROP COLUMN duplicate_of_id,
  DROP COLUMN hamming_distance;

-- Удаление индексов
ALTER TABLE rss2tlg_items
  DROP INDEX idx_simhash,
  DROP INDEX idx_is_duplicate,
  DROP INDEX idx_duplicate_of_id;
```

**Важно**: Если таблица создается заново (через `ItemRepository::createTableIfNotExist()`), миграция не требуется.

## Git status

```
M composer.json
D config/rss2tlg_simhash_test.json
D docs/RSS2TLG_SIMHASH_DEDUP_REPORT.md
M src/Rss2Tlg/ItemRepository.php
D src/Rss2Tlg/SimhashService.php
D tests/Rss2Tlg/SimhashDeduplicationTest.php
```

## Рекомендации для будущего

Если потребуется более продвинутая дедупликация, рассмотрите:

1. **Elasticsearch с fuzzy search** - для поиска похожих текстов
2. **MinHash/LSH** - более эффективный алгоритм для больших объемов
3. **TF-IDF + cosine similarity** - для семантической близости
4. **Встроенные решения MySQL** (FULLTEXT индексы с MATCH AGAINST)
5. **Векторные базы данных** (Milvus, Qdrant) для семантического поиска

---
*Документ создан автоматически при удалении библиотеки simhash*
