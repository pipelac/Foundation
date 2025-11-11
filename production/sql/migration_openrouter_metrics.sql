-- ============================================================================
-- Миграция: Создание таблицы openrouter_metrics для детального хранения метрик
-- Версия: 1.0
-- Дата: 2025-01-11
-- Описание: Хранение полных метрик OpenRouter API для аналитики и отчетности
-- ============================================================================

CREATE TABLE IF NOT EXISTS openrouter_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'Уникальный идентификатор записи',
    
    -- Идентификация запроса
    generation_id VARCHAR(255) NULL COMMENT 'Уникальный ID генерации от OpenRouter',
    model VARCHAR(255) NOT NULL COMMENT 'Название модели (например, deepseek/deepseek-chat)',
    provider_name VARCHAR(255) NULL COMMENT 'Провайдер модели (DeepInfra, Anthropic, Google)',
    created_at BIGINT NULL COMMENT 'Unix timestamp создания запроса от OpenRouter',
    
    -- Временные метрики (миллисекунды)
    generation_time INT NULL COMMENT 'Время генерации ответа в мс',
    latency INT NULL COMMENT 'Общая задержка запроса в мс',
    moderation_latency INT NULL COMMENT 'Время модерации контента в мс',
    
    -- Токены (наши, OpenRouter)
    tokens_prompt INT NULL COMMENT 'Количество токенов в промпте (OpenRouter подсчет)',
    tokens_completion INT NULL COMMENT 'Количество токенов в ответе (OpenRouter подсчет)',
    
    -- Токены (native провайдера)
    native_tokens_prompt INT NULL COMMENT 'Токены промпта по подсчету провайдера',
    native_tokens_completion INT NULL COMMENT 'Токены ответа по подсчету провайдера',
    native_tokens_cached INT NULL COMMENT 'Закешированные токены (prompt caching)',
    native_tokens_reasoning INT NULL COMMENT 'Токены рассуждений (для reasoning моделей)',
    
    -- Стоимость (USD)
    usage_total DECIMAL(10, 8) NULL COMMENT 'Финальная стоимость запроса в USD (после всех скидок и компенсаций)',
    usage_cache DECIMAL(10, 8) NULL COMMENT 'Скидка за кеширование промптов в USD (отрицательная, только для информации)',
    usage_data DECIMAL(10, 8) NULL COMMENT 'Компенсация OpenRouter за обучение на промптах в USD (отрицательная, только для информации)',
    usage_web DECIMAL(10, 8) NULL COMMENT 'Стоимость веб-поиска в USD',
    usage_file DECIMAL(10, 8) NULL COMMENT 'Стоимость обработки файлов в USD',
    final_cost DECIMAL(10, 8) NULL COMMENT 'Копия usage_total для удобства (финальная стоимость)',
    
    -- Статус завершения
    finish_reason VARCHAR(50) NULL COMMENT 'Причина завершения (stop, length, content_filter)',
    
    -- Контекст использования
    pipeline_module VARCHAR(100) NULL COMMENT 'Модуль pipeline (Summarization, Deduplication, Translation)',
    batch_id INT NULL COMMENT 'ID batch обработки (если применимо)',
    task_context TEXT NULL COMMENT 'Дополнительный контекст задачи (JSON)',
    
    -- Полный ответ для расширения
    full_response JSON NULL COMMENT 'Полный JSON ответ от OpenRouter для будущего анализа',
    
    -- Timestamp записи
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Время записи метрик в БД',
    
    -- Индексы для оптимизации запросов
    INDEX idx_model (model),
    INDEX idx_provider (provider_name),
    INDEX idx_generation_id (generation_id),
    INDEX idx_pipeline_module (pipeline_module),
    INDEX idx_created_at (created_at),
    INDEX idx_recorded_at (recorded_at),
    INDEX idx_batch_id (batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Детальные метрики OpenRouter API';

-- ============================================================================
-- Конец миграции
-- ============================================================================
