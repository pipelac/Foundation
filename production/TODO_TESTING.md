# ✅ Чеклист для тестирования RSS Summarization

Пошаговая инструкция для проведения тестирования production скрипта `rss_summarization.php`.

---

## 📋 Предварительная подготовка

### 1. Проверка окружения

- [ ] MariaDB запущен
  ```bash
  sudo systemctl status mariadb
  # или
  pgrep -fa mariadbd
  ```

- [ ] База данных `rss2tlg` существует
  ```bash
  mysql -u rss2tlg_user -prss2tlg_password_2024 -e "SHOW DATABASES LIKE 'rss2tlg';"
  ```

- [ ] Схемы импортированы
  ```bash
  mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg -e "SHOW TABLES;"
  ```
  Ожидается: rss2tlg_items, rss2tlg_summarization, и др.

---

### 2. Проверка данных

- [ ] Данные в rss2tlg_items
  ```bash
  mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg \
    -e "SELECT COUNT(*) as total FROM rss2tlg_items;"
  ```
  Ожидается: 403 (или больше)

- [ ] Данные из дампа (если нужно)
  ```bash
  mysql -u rss2tlg_user -prss2tlg_password_2024 rss2tlg \
    < production/sql/rss2tlg_items_dump.sql
  ```

---

### 3. Проверка конфигов

- [ ] OpenRouter конфиг
  ```bash
  cat production/configs/openrouter.json | jq .
  ```

- [ ] Summarization конфиг
  ```bash
  cat production/configs/summarization.json | jq .
  ```

- [ ] Telegram конфиг
  ```bash
  cat production/configs/telegram.json | jq .
  ```

- [ ] Промпт файл существует
  ```bash
  ls -lh src/Rss2Tlg/prompts/summarization_prompt_v2.txt
  ```

---

## 🧪 Тестирование

### Тест 1: Проверка синтаксиса

- [ ] PHP синтаксис корректен
  ```bash
  php -l production/rss_summarization.php
  ```
  Ожидается: `No syntax errors detected`

---

### Тест 2: Dry run (без выполнения)

- [ ] Проверка autoload и классов
  ```bash
  php -r "
  require_once 'autoload.php';
  use App\Rss2Tlg\Pipeline\SummarizationService;
  use App\Component\OpenRouter;
  echo '✅ Все классы загружаются корректно' . PHP_EOL;
  "
  ```

---

### Тест 3: Запуск скрипта (TEST MODE)

- [ ] Запустить скрипт
  ```bash
  php production/rss_summarization.php
  ```

- [ ] Проверить консольный вывод
  - Заголовок скрипта отображается
  - Инициализация компонентов успешна
  - Обработка 3 новостей
  - Итоговая статистика
  - Нет критических ошибок

- [ ] Проверить Telegram уведомления
  - Уведомление о старте
  - Прогресс обработки
  - Финальный отчет

---

### Тест 4: Проверка результатов в БД

- [ ] Данные записаны в rss2tlg_summarization
  ```sql
  SELECT COUNT(*) FROM rss2tlg_summarization WHERE status = 'success';
  ```
  Ожидается: 3

- [ ] Все поля заполнены корректно
  ```sql
  SELECT 
      item_id,
      article_language,
      category_primary,
      importance_rating,
      headline,
      LEFT(summary, 50) as summary_preview,
      model_used,
      tokens_used
  FROM rss2tlg_summarization
  WHERE status = 'success'
  ORDER BY processed_at DESC
  LIMIT 3;
  ```

- [ ] JSON поля корректны (кириллица читаемая)
  ```sql
  SELECT 
      category_secondary,
      keywords,
      dedup_canonical_entities
  FROM rss2tlg_summarization
  WHERE status = 'success'
  LIMIT 1;
  ```

- [ ] Метрики токенов
  ```sql
  SELECT 
      SUM(tokens_used) as total_tokens,
      AVG(tokens_used) as avg_tokens,
      SUM(cache_hit) as cache_hits
  FROM rss2tlg_summarization
  WHERE status = 'success';
  ```

---

### Тест 5: Проверка логов

- [ ] Лог файл создан
  ```bash
  ls -lh logs/rss_summarization.log
  ```

- [ ] Логи структурированы (JSON)
  ```bash
  head -20 logs/rss_summarization.log
  ```

- [ ] Нет критических ошибок
  ```bash
  grep -i "error" logs/rss_summarization.log | grep -v "error_count"
  ```

- [ ] Метрики токенов в логах
  ```bash
  grep "total_tokens" logs/rss_summarization.log
  ```

---

### Тест 6: Повторный запуск (проверка идемпотентности)

- [ ] Запустить скрипт повторно
  ```bash
  php production/rss_summarization.php
  ```

- [ ] Проверить что новости не дублируются
  ```sql
  SELECT COUNT(*) FROM rss2tlg_summarization WHERE status = 'success';
  ```
  Ожидается: 3 (не 6!)

- [ ] Проверить сообщение о пропущенных
  В консоли: "Нет новостей для обработки" или обработка новых

---

### Тест 7: Ошибочные сценарии

- [ ] Отключить MariaDB и запустить скрипт
  ```bash
  sudo systemctl stop mariadb
  php production/rss_summarization.php
  ```
  Ожидается: ошибка подключения к БД

- [ ] Восстановить MariaDB
  ```bash
  sudo systemctl start mariadb
  ```

- [ ] Неверный API ключ OpenRouter (временно изменить в конфиге)
  ```bash
  # Изменить api_key в production/configs/openrouter.json
  php production/rss_summarization.php
  ```
  Ожидается: ошибка AI API

- [ ] Восстановить корректный API ключ

---

## 🎯 Production тестирование

### Тест 8: Production режим

- [ ] Отключить TEST_MODE
  ```bash
  vim production/rss_summarization.php
  # Изменить: const TEST_MODE = false;
  ```

- [ ] Очистить таблицу суммаризации (для чистого теста)
  ```sql
  TRUNCATE TABLE rss2tlg_summarization;
  ```

- [ ] Запустить скрипт
  ```bash
  php production/rss_summarization.php
  ```

- [ ] Проверить что обработаны ВСЕ новости
  ```sql
  SELECT COUNT(*) FROM rss2tlg_summarization WHERE status = 'success';
  ```
  Ожидается: 403 (или количество новостей в rss2tlg_items)

- [ ] Вернуть TEST_MODE = true

---

## 📊 Итоговая проверка

### Метрики успешности

- [ ] Успешность обработки > 90%
  ```sql
  SELECT 
      COUNT(*) as total,
      SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success,
      ROUND(SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as success_rate
  FROM rss2tlg_summarization;
  ```

- [ ] Cache rate > 50% (после первого запроса)
  ```sql
  SELECT 
      SUM(cache_hit) as cache_hits,
      COUNT(*) as total,
      ROUND(SUM(cache_hit) / COUNT(*) * 100, 2) as cache_rate
  FROM rss2tlg_summarization
  WHERE status = 'success';
  ```

- [ ] Средняя скорость обработки < 30 секунд на новость

- [ ] Все Telegram уведомления доставлены

---

## ✅ Критерии успеха

Тест считается успешным если:

1. ✅ Все 3 тестовые новости обработаны (TEST MODE)
2. ✅ Данные корректно записаны в БД
3. ✅ Кириллица сохраняется в читаемом виде (JSON_UNESCAPED_UNICODE)
4. ✅ Логи структурированы и полные
5. ✅ Telegram уведомления работают
6. ✅ Повторный запуск не создает дубликаты
7. ✅ Ошибки обрабатываются корректно
8. ✅ Метрики токенов корректны

---

## 🐛 Известные проблемы

Если обнаружены ошибки, задокументируйте:

1. **Ошибка:**
   ```
   [Описание ошибки]
   ```

2. **Воспроизведение:**
   ```
   [Шаги для воспроизведения]
   ```

3. **Ожидаемое поведение:**
   ```
   [Что должно было произойти]
   ```

4. **Фактическое поведение:**
   ```
   [Что произошло на самом деле]
   ```

5. **Решение:**
   ```
   [Как исправить]
   ```

---

## 📞 После тестирования

- [ ] Результаты задокументированы
- [ ] Ошибки исправлены (если были)
- [ ] Финальный отчет создан
- [ ] TEST_MODE установлен в false для production
- [ ] Скрипт готов к cron deployment

---

**Версия:** 1.0.0  
**Дата:** 2025-11-09
