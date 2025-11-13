/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.13-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: rss2tlg_test
-- ------------------------------------------------------
-- Server version	10.11.13-MariaDB-0ubuntu0.24.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `openrouter_metrics`
--

DROP TABLE IF EXISTS `openrouter_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `openrouter_metrics` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'Уникальный идентификатор записи',
  `generation_id` varchar(255) DEFAULT NULL COMMENT 'Уникальный ID генерации от OpenRouter',
  `model` varchar(255) NOT NULL COMMENT 'Название модели (например, deepseek/deepseek-chat)',
  `provider_name` varchar(255) DEFAULT NULL COMMENT 'Провайдер модели (DeepInfra, Anthropic, Google)',
  `created_at` bigint(20) DEFAULT NULL COMMENT 'Unix timestamp создания запроса от OpenRouter',
  `generation_time` int(11) DEFAULT NULL COMMENT 'Время генерации ответа в мс',
  `latency` int(11) DEFAULT NULL COMMENT 'Общая задержка запроса в мс',
  `moderation_latency` int(11) DEFAULT NULL COMMENT 'Время модерации контента в мс',
  `tokens_prompt` int(11) DEFAULT NULL COMMENT 'Количество токенов в промпте (OpenRouter подсчет)',
  `tokens_completion` int(11) DEFAULT NULL COMMENT 'Количество токенов в ответе (OpenRouter подсчет)',
  `native_tokens_prompt` int(11) DEFAULT NULL COMMENT 'Токены промпта по подсчету провайдера',
  `native_tokens_completion` int(11) DEFAULT NULL COMMENT 'Токены ответа по подсчету провайдера',
  `native_tokens_cached` int(11) DEFAULT NULL COMMENT 'Закешированные токены (prompt caching)',
  `native_tokens_reasoning` int(11) DEFAULT NULL COMMENT 'Токены рассуждений (для reasoning моделей)',
  `usage_total` decimal(10,8) DEFAULT NULL COMMENT 'Общая стоимость запроса в USD',
  `usage_cache` decimal(10,8) DEFAULT NULL COMMENT 'Стоимость использования кеша в USD',
  `usage_data` decimal(10,8) DEFAULT NULL COMMENT 'Стоимость веб-поиска/data retrieval в USD',
  `usage_web` decimal(10,8) DEFAULT NULL COMMENT 'Стоимость веб-поиска в USD',
  `usage_file` decimal(10,8) DEFAULT NULL COMMENT 'Стоимость обработки файлов в USD',
  `final_cost` decimal(10,8) DEFAULT NULL COMMENT 'Финальная стоимость после всех скидок (копия usage_total)',
  `finish_reason` varchar(50) DEFAULT NULL COMMENT 'Причина завершения (stop, length, content_filter)',
  `pipeline_module` varchar(100) DEFAULT NULL COMMENT 'Модуль pipeline (Summarization, Deduplication, Translation)',
  `batch_id` int(11) DEFAULT NULL COMMENT 'ID batch обработки (если применимо)',
  `task_context` text DEFAULT NULL COMMENT 'Дополнительный контекст задачи (JSON)',
  `full_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Полный JSON ответ от OpenRouter для будущего анализа' CHECK (json_valid(`full_response`)),
  `recorded_at` timestamp NULL DEFAULT current_timestamp() COMMENT 'Время записи метрик в БД',
  PRIMARY KEY (`id`),
  KEY `idx_model` (`model`),
  KEY `idx_provider` (`provider_name`),
  KEY `idx_generation_id` (`generation_id`),
  KEY `idx_pipeline_module` (`pipeline_module`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_recorded_at` (`recorded_at`),
  KEY `idx_batch_id` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Детальные метрики OpenRouter API';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `openrouter_metrics`
--

LOCK TABLES `openrouter_metrics` WRITE;
/*!40000 ALTER TABLE `openrouter_metrics` DISABLE KEYS */;
/*!40000 ALTER TABLE `openrouter_metrics` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-11-13 18:37:04
