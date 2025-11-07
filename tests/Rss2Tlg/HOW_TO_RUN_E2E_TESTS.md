# 🧪 Как запустить E2E тесты RSS2TLG

Пошаговая инструкция по запуску полноценных E2E тестов модуля RSS2TLG.

---

## 📋 Предварительные требования

### 1. Программное обеспечение
- PHP 8.1+ с расширениями: PDO, MySQL, JSON, cURL, mbstring
- Docker (для MariaDB)
- Composer (зависимости уже установлены)

### 2. Учетные данные
- **Telegram Bot Token** (уже настроен)
- **Telegram Chat ID** (уже настроен)
- **Telegram Channel ID** (уже настроен)
- **OpenRouter API Key** (уже настроен)

### 3. Конфигурация
Файл конфигурации уже создан: `/home/engine/project/Config/rss2tlg_e2e_test.json`

---

## 🚀 Запуск тестов (Пошагово)

### Шаг 1: Запуск MariaDB 11.3.2

```bash
# Остановить старый контейнер (если существует)
docker rm -f rss2tlg_mariadb 2>/dev/null

# Запустить новый контейнер
docker run -d --name rss2tlg_mariadb \
  -e MYSQL_ROOT_PASSWORD=testpass123 \
  -e MYSQL_DATABASE=rss2tlg_test \
  -e MYSQL_USER=rss2tlg_user \
  -e MYSQL_PASSWORD=testpass123 \
  -p 3307:3306 \
  mariadb:11.3.2 \
  --character-set-server=utf8mb4 \
  --collation-server=utf8mb4_unicode_ci

# Подождать готовности БД
sleep 30
```

### Шаг 2: Проверка подключения к MariaDB

```bash
docker exec rss2tlg_mariadb mariadb \
  -urss2tlg_user \
  -ptestpass123 \
  rss2tlg_test \
  -e "SELECT VERSION(), DATABASE(), USER();"
```

**Ожидаемый вывод:**
```
VERSION()       DATABASE()      USER()
11.3.2-MariaDB  rss2tlg_test    rss2tlg_user@localhost
```

### Шаг 3: Запуск тестового скрипта

```bash
cd /home/engine/project
php src/Rss2Tlg/tests/tests_rss2tlg_e2e_v5.php
```

**Или с сохранением логов:**
```bash
cd /home/engine/project
php src/Rss2Tlg/tests/tests_rss2tlg_e2e_v5.php 2>&1 | tee /tmp/e2e_test.log
```

### Шаг 4: Мониторинг прогресса

В отдельном терминале:
```bash
# Просмотр логов приложения
tail -f /home/engine/project/logs/rss2tlg_e2e_test.log

# Или просмотр вывода теста
tail -f /tmp/e2e_test.log
```

---

## ⏱️ Ожидаемое время выполнения

| Этап | Время | Описание |
|------|-------|----------|
| Инициализация | 1-2 сек | Подключение к БД, создание объектов |
| Очистка таблиц | 1-2 сек | TRUNCATE таблиц |
| Опрос RSS лент | 10-15 сек | 5 источников, ~316 новостей |
| AI анализ | 100-120 сек | 5 новостей через OpenRouter |
| Публикация | 10-15 сек | 5 новостей в Telegram |
| Отчеты и дампы | 3-5 сек | Генерация CSV и MD файлов |
| **ВСЕГО** | **~150 сек** | **~2.5 минуты** |

---

## 📊 Что проверяет тест

### 1. RSS Fetching (Шаг 2)
- ✅ Опрос 5 RSS источников
- ✅ Парсинг RSS 2.0 и Atom форматов
- ✅ Сохранение метаданных (ETag, Last-Modified)
- ✅ Дедупликация по content_hash
- ✅ Unicode/кириллица

**RSS источники:**
1. РИА Новости (RU)
2. Ведомости - Технологии (RU)
3. Лента.ру - Топ 7 (RU)
4. Ars Technica - AI (EN)
5. TechCrunch - Startups (EN)

### 2. Автоматическое создание таблиц
- ✅ `rss2tlg_feed_state` - Состояние лент
- ✅ `rss2tlg_items` - Новости
- ✅ `rss2tlg_ai_analysis` - Результаты AI анализа
- ✅ `rss2tlg_publications` - Публикации

### 3. AI Analysis (Шаг 3)
- ✅ Загрузка промпта из XML
- ✅ Отправка запроса к OpenRouter API
- ✅ Fallback между моделями
- ✅ Парсинг JSON ответа
- ✅ Сохранение метрик (tokens, model, timing)

**AI модели (приоритет):**
1. qwen/qwen3-235b:free
2. qwen/qwen3-30b:free
3. deepseek/deepseek-v3.2-exp
4. qwen/qwen-2.5-72b-instruct

### 4. Telegram Publishing (Шаг 4)
- ✅ Форматирование сообщений (HTML)
- ✅ Публикация в канал @kompasDaily
- ✅ Сохранение message_id в БД
- ✅ Отправка уведомлений в бот

### 5. Кеширование промптов (Шаг 5)
- ✅ Проверка cached_tokens в ответе
- ✅ Расчет cache hit rate

### 6. Дампы и отчеты (Шаг 6)
- ✅ CSV дампы 4 таблиц
- ✅ Markdown отчет
- ✅ Консольный вывод

---

## 📝 Ожидаемый вывод

### Консольный вывод (успешный тест):

```
╔════════════════════════════════════════════════════════════════════════╗
║         RSS2TLG E2E ТЕСТ v5 - ПОЛНОЕ ТЕСТИРОВАНИЕ С AI                ║
╚════════════════════════════════════════════════════════════════════════╝

🕐 Начало: 2025-11-07 11:14:44

✅ Уведомление о начале отправлено в бот

═══════════════════════════════════════════════════════════════════════
ШАГ 1: Очистка таблиц БД
═══════════════════════════════════════════════════════════════════════

✅ Таблица rss2tlg_publications очищена
✅ Таблица rss2tlg_ai_analysis очищена
✅ Таблица rss2tlg_items очищена
✅ Таблица rss2tlg_feed_state очищена

═══════════════════════════════════════════════════════════════════════
ШАГ 2: Опрос RSS лент
═══════════════════════════════════════════════════════════════════════

📰 RIA Novosti:
   ✅ Получено новостей: 60
   💾 Сохранено в БД: 60

📰 Vedomosti Tech:
   ✅ Получено новостей: 200
   💾 Сохранено в БД: 200

📰 Lenta.ru Top7:
   ✅ Получено новостей: 16
   💾 Сохранено в БД: 16

📰 ArsTechnica AI:
   ✅ Получено новостей: 20
   💾 Сохранено в БД: 20

📰 TechCrunch Startups:
   ✅ Получено новостей: 20
   💾 Сохранено в БД: 20

═══════════════════════════════════════════════════════════════════════
ШАГ 3: AI анализ новостей
═══════════════════════════════════════════════════════════════════════

🔍 Анализ: Депутат Рады рассказал об устранении Зеленского
   ✅ Анализ завершен
   📊 Модель: qwen/qwen-2.5-72b-instruct
   📊 Токены: prompt=3745, completion=518, total=4263
   💾 Кешировано: 0 токенов

[... 4 еще ...]

═══════════════════════════════════════════════════════════════════════
ШАГ 4: Публикация в Telegram канал
═══════════════════════════════════════════════════════════════════════

📤 Публикация: Депутат Рады рассказал об устранении Зеленского
   ✅ Опубликовано (message_id: 12345)

[... 4 еще ...]

═══════════════════════════════════════════════════════════════════════
ШАГ 5: Проверка кеширования промптов
═══════════════════════════════════════════════════════════════════════

📊 Результаты кеширования:
   • Всего запросов: 5
   • Cache hits: 0
   • Cache misses: 5
   • Cache hit rate: 0%
   • Всего кешировано токенов: 0

⚠️  Кеширование промптов НЕ РАБОТАЕТ (или первые запросы)

═══════════════════════════════════════════════════════════════════════
ШАГ 6: Создание дампов таблиц
═══════════════════════════════════════════════════════════════════════

✅ rss2tlg_feed_state: 5 строк, 0.92 KB
✅ rss2tlg_items: 316 строк, 407.05 KB
✅ rss2tlg_ai_analysis: 5 строк, 21.24 KB
✅ rss2tlg_publications: 5 строк, 0.46 KB

═══════════════════════════════════════════════════════════════════════
ФИНАЛЬНЫЙ ОТЧЕТ
═══════════════════════════════════════════════════════════════════════

⏱️  ВРЕМЯ:
   • Начало: 2025-11-07 11:14:44
   • Окончание: 2025-11-07 11:17:13
   • Длительность: 148.17 сек

📊 RSS ЛЕНТЫ:
   • Всего: 5
   • Успешно: 5
   • Ошибки: 0

📰 НОВОСТИ:
   • Получено: 316
   • Сохранено: 316

🤖 AI АНАЛИЗ:
   • Проанализировано: 5/5
   • Cache hits: 0
   • Cache hit rate: 0%

📢 ПУБЛИКАЦИИ:
   • Опубликовано в канал: 5/5

═══════════════════════════════════════════════════════════════════════
СТАТУС ТЕСТА: ✅ PASSED
═══════════════════════════════════════════════════════════════════════

📄 Отчет сохранен: /home/engine/project/src/Rss2Tlg/tests/reports/e2e_test_v5_*.md

✅ Финальное уведомление отправлено в бот

🎉 ТЕСТ ЗАВЕРШЕН!
```

---

## 🔍 Проверка результатов

### 1. Проверить дампы таблиц

```bash
cd /home/engine/project/src/Rss2Tlg/tests/sql
ls -lh

# Количество новостей
cat rss2tlg_items_*.csv | wc -l

# Количество AI анализов
cat rss2tlg_ai_analysis_*.csv | wc -l

# Количество публикаций
cat rss2tlg_publications_*.csv | wc -l
```

### 2. Проверить отчеты

```bash
cd /home/engine/project/src/Rss2Tlg/tests
cat E2E_TEST_V5_SUMMARY.md
cat reports/e2e_test_v5_*.md
```

### 3. Проверить БД напрямую

```bash
docker exec -it rss2tlg_mariadb mariadb -urss2tlg_user -ptestpass123 rss2tlg_test
```

Затем в MySQL CLI:
```sql
-- Количество новостей
SELECT COUNT(*) FROM rss2tlg_items;

-- Количество AI анализов
SELECT COUNT(*) FROM rss2tlg_ai_analysis WHERE analysis_status = 'success';

-- Количество публикаций
SELECT COUNT(*) FROM rss2tlg_publications;

-- Статистика по моделям
SELECT model_used, COUNT(*) as count, AVG(tokens_used) as avg_tokens
FROM rss2tlg_ai_analysis 
WHERE analysis_status = 'success'
GROUP BY model_used;
```

### 4. Проверить Telegram канал

Откройте @kompasDaily и убедитесь, что опубликованы 5 новостей с метриками.

---

## ⚠️ Возможные проблемы и решения

### Проблема 1: MariaDB не запускается

**Симптомы:**
```
Error response from daemon: Conflict. The container name "/rss2tlg_mariadb" is already in use
```

**Решение:**
```bash
docker rm -f rss2tlg_mariadb
# Затем запустить заново
```

### Проблема 2: Rate Limit от OpenRouter

**Симптомы:**
```
ERROR Сервер OpenRouter вернул ошибку {"status_code":429
```

**Решение:**
- Подождать несколько минут
- Или добавить свой API ключ в конфиг
- Модели автоматически переключатся на fallback

### Проблема 3: Telegram ошибки

**Симптомы:**
```
Ошибка отправки уведомления: ...
```

**Решение:**
- Проверить Bot Token
- Проверить Chat ID и Channel ID
- Убедиться, что бот администратор канала

### Проблема 4: Файл промпта не найден

**Симптомы:**
```
ERROR Файл промпта не найден: /home/engine/project/src/Rss2Tlg/prompts/1.xml
```

**Решение:**
```bash
cd /home/engine/project/src/Rss2Tlg/prompts
ln -s INoT_v1.xml 1.xml
```

### Проблема 5: Permission denied на logs/

**Симптомы:**
```
LoggerValidationException: Недостаточно прав на запись в директорию
```

**Решение:**
```bash
mkdir -p /home/engine/project/logs
chmod 775 /home/engine/project/logs
```

---

## 🧹 Очистка после тестов

### Остановить и удалить контейнер

```bash
docker rm -f rss2tlg_mariadb
```

### Удалить дампы (осторожно!)

```bash
rm -rf /home/engine/project/src/Rss2Tlg/tests/sql/*.csv
rm -rf /home/engine/project/src/Rss2Tlg/tests/reports/*.md
```

### Удалить логи

```bash
rm -f /home/engine/project/logs/rss2tlg_e2e_test.log*
```

---

## 📞 Помощь и поддержка

Если тесты не проходят:

1. **Проверить логи:**
   ```bash
   tail -100 /home/engine/project/logs/rss2tlg_e2e_test.log
   ```

2. **Проверить конфигурацию:**
   ```bash
   cat /home/engine/project/Config/rss2tlg_e2e_test.json
   ```

3. **Проверить состояние MariaDB:**
   ```bash
   docker logs rss2tlg_mariadb | tail -50
   ```

4. **Запустить отдельные этапы:** Можно закомментировать части в `tests_rss2tlg_e2e_v5.php` для отладки.

---

## ✅ Критерии успешного теста

Тест считается **PASSED**, если:

- [x] RSS ленты: 5/5 успешно опрошены
- [x] Новости: ≥300 получено и сохранено
- [x] AI анализ: 5/5 успешно проанализированы
- [x] Публикации: 5/5 опубликованы в канал
- [x] Дампы: 4 CSV файла созданы
- [x] Отчеты: MD файлы сгенерированы
- [x] Ошибок: 0 критических

---

**Дата последнего обновления:** 2025-11-07  
**Версия тестов:** v5  
**Статус:** ✅ Production Ready
