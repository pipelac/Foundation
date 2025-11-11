/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.13-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: rss2tlg
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
-- Table structure for table `ai_analysis`
--

DROP TABLE IF EXISTS `ai_analysis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_analysis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `hash` varchar(64) NOT NULL,
  `raw_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`raw_data`)),
  `is_duplicate` tinyint(1) DEFAULT 0,
  `duplicate_of` int(11) DEFAULT NULL,
  `similarity` decimal(5,4) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `tokens_used` int(11) DEFAULT NULL,
  `analyzed_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_hash` (`hash`),
  KEY `idx_item_id` (`item_id`),
  KEY `idx_is_duplicate` (`is_duplicate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_analysis`
--

LOCK TABLES `ai_analysis` WRITE;
/*!40000 ALTER TABLE `ai_analysis` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_analysis` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `items`
--

DROP TABLE IF EXISTS `items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feed_id` int(11) NOT NULL,
  `guid` varchar(255) NOT NULL,
  `title` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `content` text DEFAULT NULL,
  `link` varchar(500) DEFAULT NULL,
  `pub_date` timestamp NULL DEFAULT NULL,
  `hash` varchar(64) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_guid` (`guid`),
  KEY `idx_feed_id` (`feed_id`),
  KEY `idx_hash` (`hash`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `items`
--

LOCK TABLES `items` WRITE;
/*!40000 ALTER TABLE `items` DISABLE KEYS */;
INSERT INTO `items` VALUES
(1,9999,'test-metrics-ee433d59bec44980842110e2485c2833-1762880107-0','Test News #1','Artificial intelligence revolutionizes medicine wi...','Artificial intelligence revolutionizes medicine with machine learning algorithms for early disease detection.','https://example.com/news/1','2025-11-11 16:55:07','ee433d59bec44980842110e2485c2833','2025-11-11 16:55:07'),
(2,9999,'test-metrics-29e4ef75061e3a8d9e68f141557b5857-1762880107-1','Test News #2','Quantum computers reach new milestone with 1000 qu...','Quantum computers reach new milestone with 1000 qubits enabling complex problem solving.','https://example.com/news/2','2025-11-11 16:55:07','29e4ef75061e3a8d9e68f141557b5857','2025-11-11 16:55:07'),
(3,9999,'test-metrics-de6d37431a7100c4eb796dbcca005156-1762880107-2','Test News #3','Climate change accelerates faster than expected ac...','Climate change accelerates faster than expected according to latest scientific research data.','https://example.com/news/3','2025-11-11 16:55:07','de6d37431a7100c4eb796dbcca005156','2025-11-11 16:55:07');
/*!40000 ALTER TABLE `items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `openrouter_metrics`
--

DROP TABLE IF EXISTS `openrouter_metrics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `openrouter_metrics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `generation_id` varchar(255) DEFAULT NULL,
  `model` varchar(255) NOT NULL,
  `provider_name` varchar(255) DEFAULT NULL,
  `created_at` bigint(20) DEFAULT NULL,
  `generation_time` int(11) DEFAULT NULL,
  `latency` int(11) DEFAULT NULL,
  `moderation_latency` int(11) DEFAULT NULL,
  `tokens_prompt` int(11) DEFAULT NULL,
  `tokens_completion` int(11) DEFAULT NULL,
  `native_tokens_prompt` int(11) DEFAULT NULL,
  `native_tokens_completion` int(11) DEFAULT NULL,
  `native_tokens_cached` int(11) DEFAULT NULL,
  `native_tokens_reasoning` int(11) DEFAULT NULL,
  `usage_total` decimal(10,8) DEFAULT NULL,
  `usage_cache` decimal(10,8) DEFAULT NULL,
  `usage_data` decimal(10,8) DEFAULT NULL,
  `usage_file` decimal(10,8) DEFAULT NULL,
  `finish_reason` varchar(50) DEFAULT NULL,
  `pipeline_module` varchar(100) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `task_context` text DEFAULT NULL,
  `full_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`full_response`)),
  `recorded_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_model` (`model`),
  KEY `idx_provider` (`provider_name`),
  KEY `idx_generation_id` (`generation_id`),
  KEY `idx_pipeline_module` (`pipeline_module`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_recorded_at` (`recorded_at`),
  KEY `idx_batch_id` (`batch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `openrouter_metrics`
--

LOCK TABLES `openrouter_metrics` WRITE;
/*!40000 ALTER TABLE `openrouter_metrics` DISABLE KEYS */;
INSERT INTO `openrouter_metrics` VALUES
(4,'gen_test_001','deepseek/deepseek-chat','DeepInfra',1762880452,2500,2800,NULL,500,300,500,300,200,NULL,0.00240000,0.00050000,NULL,NULL,'stop','SummarizationService',NULL,NULL,NULL,'2025-11-11 17:00:52'),
(5,'gen_test_002','deepseek/deepseek-chat','DeepInfra',1762880452,2100,2400,NULL,450,250,450,250,150,NULL,0.00200000,0.00030000,NULL,NULL,'stop','SummarizationService',NULL,NULL,NULL,'2025-11-11 17:00:52'),
(6,'gen_test_003','anthropic/claude-3.5-sonnet','Anthropic',1762880452,3200,3500,NULL,600,400,600,400,300,NULL,0.00350000,0.00080000,NULL,NULL,'stop','DeduplicationService',NULL,NULL,NULL,'2025-11-11 17:00:52');
/*!40000 ALTER TABLE `openrouter_metrics` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `publications`
--

DROP TABLE IF EXISTS `publications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `publications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `channel_id` varchar(100) NOT NULL,
  `message_id` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pending',
  `error` text DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_item_id` (`item_id`),
  KEY `idx_channel_id` (`channel_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `publications`
--

LOCK TABLES `publications` WRITE;
/*!40000 ALTER TABLE `publications` DISABLE KEYS */;
/*!40000 ALTER TABLE `publications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rss2tlg_deduplication`
--

DROP TABLE IF EXISTS `rss2tlg_deduplication`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rss2tlg_deduplication` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
  `item_id` int(10) unsigned NOT NULL COMMENT 'ID новости (FK -> rss2tlg_items.id)',
  `feed_id` int(10) unsigned NOT NULL COMMENT 'ID источника RSS',
  `status` enum('pending','processing','checked','failed','skipped') NOT NULL DEFAULT 'pending' COMMENT 'Статус проверки',
  `is_duplicate` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Является ли дубликатом (0/1)',
  `duplicate_of_item_id` int(10) unsigned DEFAULT NULL COMMENT 'ID оригинальной новости (FK)',
  `similarity_score` decimal(5,2) DEFAULT NULL COMMENT 'Оценка схожести (0.00-100.00)',
  `similarity_method` enum('ai','hash','hybrid') DEFAULT NULL COMMENT 'Метод определения схожести',
  `can_be_published` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Можно ли публиковать (0/1)',
  `matched_entities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Совпавшие сущности' CHECK (json_valid(`matched_entities`)),
  `matched_events` text DEFAULT NULL COMMENT 'Совпавшие события',
  `matched_facts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Совпавшие факты' CHECK (json_valid(`matched_facts`)),
  `model_used` varchar(150) DEFAULT NULL COMMENT 'Модель AI для проверки',
  `tokens_used` int(10) unsigned DEFAULT NULL COMMENT 'Количество использованных токенов',
  `processing_time_ms` int(10) unsigned DEFAULT NULL COMMENT 'Время обработки в миллисекундах',
  `items_compared` int(10) unsigned DEFAULT NULL COMMENT 'Количество новостей для сравнения',
  `retry_count` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Количество повторов при ошибках',
  `error_message` text DEFAULT NULL COMMENT 'Сообщение об ошибке',
  `error_code` varchar(50) DEFAULT NULL COMMENT 'Код ошибки',
  `checked_at` datetime DEFAULT NULL COMMENT 'Время успешной проверки',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Время создания записи',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Время последнего обновления',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_item_id` (`item_id`),
  KEY `idx_feed_id` (`feed_id`),
  KEY `idx_status` (`status`),
  KEY `idx_is_duplicate` (`is_duplicate`),
  KEY `idx_can_be_published` (`can_be_published`),
  KEY `idx_duplicate_of` (`duplicate_of_item_id`),
  KEY `idx_similarity_score` (`similarity_score`),
  KEY `idx_checked_at` (`checked_at`),
  KEY `idx_feed_status` (`feed_id`,`status`),
  KEY `idx_publish_ready` (`can_be_published`,`is_duplicate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Результаты проверки новостей на дубликаты';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rss2tlg_deduplication`
--

LOCK TABLES `rss2tlg_deduplication` WRITE;
/*!40000 ALTER TABLE `rss2tlg_deduplication` DISABLE KEYS */;
/*!40000 ALTER TABLE `rss2tlg_deduplication` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rss2tlg_illustration`
--

DROP TABLE IF EXISTS `rss2tlg_illustration`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rss2tlg_illustration` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
  `item_id` int(10) unsigned NOT NULL COMMENT 'ID новости (FK -> rss2tlg_items.id)',
  `feed_id` int(10) unsigned NOT NULL COMMENT 'ID источника RSS',
  `status` enum('pending','processing','success','failed','skipped') NOT NULL DEFAULT 'pending' COMMENT 'Статус генерации',
  `image_path` varchar(1024) DEFAULT NULL COMMENT 'Путь к файлу изображения',
  `image_url` varchar(1024) DEFAULT NULL COMMENT 'URL изображения (если загружено)',
  `image_width` smallint(5) unsigned DEFAULT NULL COMMENT 'Ширина изображения',
  `image_height` smallint(5) unsigned DEFAULT NULL COMMENT 'Высота изображения',
  `image_size_bytes` int(10) unsigned DEFAULT NULL COMMENT 'Размер файла в байтах',
  `image_format` varchar(20) DEFAULT NULL COMMENT 'Формат изображения (png, jpg, webp)',
  `prompt_used` text DEFAULT NULL COMMENT 'Промпт использованный для генерации',
  `model_used` varchar(150) DEFAULT NULL COMMENT 'Модель AI для генерации',
  `generation_time_ms` int(10) unsigned DEFAULT NULL COMMENT 'Время генерации в миллисекундах',
  `retry_count` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Количество повторов при ошибках',
  `error_message` text DEFAULT NULL COMMENT 'Сообщение об ошибке',
  `error_code` varchar(50) DEFAULT NULL COMMENT 'Код ошибки',
  `generated_at` datetime DEFAULT NULL COMMENT 'Время успешной генерации',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Время создания записи',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Время последнего обновления',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_item_id` (`item_id`),
  KEY `idx_feed_id` (`feed_id`),
  KEY `idx_status` (`status`),
  KEY `idx_image_path` (`image_path`(255)),
  KEY `idx_generated_at` (`generated_at`),
  KEY `idx_feed_status` (`feed_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Сгенерированные AI иллюстрации для новостей';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rss2tlg_illustration`
--

LOCK TABLES `rss2tlg_illustration` WRITE;
/*!40000 ALTER TABLE `rss2tlg_illustration` DISABLE KEYS */;
/*!40000 ALTER TABLE `rss2tlg_illustration` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rss2tlg_items`
--

DROP TABLE IF EXISTS `rss2tlg_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rss2tlg_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feed_id` int(11) NOT NULL,
  `content_hash` varchar(64) NOT NULL,
  `guid` varchar(500) NOT NULL,
  `title` text DEFAULT NULL,
  `link` varchar(1000) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `content` mediumtext DEFAULT NULL,
  `extracted_content` mediumtext DEFAULT NULL,
  `pub_date` timestamp NULL DEFAULT NULL,
  `author` varchar(255) DEFAULT NULL,
  `categories` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`categories`)),
  `enclosures` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`enclosures`)),
  `is_published` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `content_hash` (`content_hash`),
  KEY `idx_feed_id` (`feed_id`),
  KEY `idx_content_hash` (`content_hash`),
  KEY `idx_is_published` (`is_published`),
  KEY `idx_pub_date` (`pub_date`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rss2tlg_items`
--

LOCK TABLES `rss2tlg_items` WRITE;
/*!40000 ALTER TABLE `rss2tlg_items` DISABLE KEYS */;
INSERT INTO `rss2tlg_items` VALUES
(10,9999,'ee433d59bec44980842110e2485c2833','test-metrics-ee433d59bec44980842110e2485c2833-1762880349-0','Test News #1','https://example.com/news/1','Artificial intelligence revolutionizes medicine wi...','Artificial intelligence revolutionizes medicine with machine learning algorithms for early disease detection.',NULL,'2025-11-11 16:59:09',NULL,NULL,NULL,0,'2025-11-11 16:59:09','2025-11-11 16:59:09'),
(11,9999,'29e4ef75061e3a8d9e68f141557b5857','test-metrics-29e4ef75061e3a8d9e68f141557b5857-1762880349-1','Test News #2','https://example.com/news/2','Quantum computers reach new milestone with 1000 qu...','Quantum computers reach new milestone with 1000 qubits enabling complex problem solving.',NULL,'2025-11-11 16:59:09',NULL,NULL,NULL,0,'2025-11-11 16:59:09','2025-11-11 16:59:09'),
(12,9999,'de6d37431a7100c4eb796dbcca005156','test-metrics-de6d37431a7100c4eb796dbcca005156-1762880349-2','Test News #3','https://example.com/news/3','Climate change accelerates faster than expected ac...','Climate change accelerates faster than expected according to latest scientific research data.',NULL,'2025-11-11 16:59:09',NULL,NULL,NULL,0,'2025-11-11 16:59:09','2025-11-11 16:59:09');
/*!40000 ALTER TABLE `rss2tlg_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rss2tlg_summarization`
--

DROP TABLE IF EXISTS `rss2tlg_summarization`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rss2tlg_summarization` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
  `item_id` int(10) unsigned NOT NULL COMMENT 'ID новости (FK -> rss2tlg_items.id)',
  `feed_id` int(10) unsigned NOT NULL COMMENT 'ID источника RSS',
  `status` enum('pending','processing','success','failed','skipped') NOT NULL DEFAULT 'pending' COMMENT 'Статус суммаризации',
  `article_language` varchar(10) DEFAULT NULL COMMENT 'Язык статьи (en, ru, и т.д.)',
  `category_primary` varchar(100) DEFAULT NULL COMMENT 'Основная категория',
  `category_secondary` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Массив дополнительных категорий (до 2)' CHECK (json_valid(`category_secondary`)),
  `headline` varchar(500) NOT NULL DEFAULT '' COMMENT 'Заголовок новости',
  `summary` text DEFAULT NULL COMMENT 'Краткое содержание (суммаризация)',
  `keywords` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Массив ключевых слов (до 5)' CHECK (json_valid(`keywords`)),
  `importance_rating` tinyint(3) unsigned DEFAULT NULL COMMENT 'Рейтинг важности (1-20)',
  `dedup_canonical_entities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Ключевые сущности для дедупликации' CHECK (json_valid(`dedup_canonical_entities`)),
  `dedup_core_event` text DEFAULT NULL COMMENT 'Описание ключевого события (1-2 предложения)',
  `dedup_numeric_facts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Числовые факты и даты' CHECK (json_valid(`dedup_numeric_facts`)),
  `model_used` varchar(150) DEFAULT NULL COMMENT 'Модель AI использованная для анализа',
  `tokens_used` int(10) unsigned DEFAULT NULL COMMENT 'Количество использованных токенов',
  `tokens_prompt` int(10) unsigned DEFAULT NULL COMMENT 'Токены промпта',
  `tokens_completion` int(10) unsigned DEFAULT NULL COMMENT 'Токены completion',
  `tokens_cached` int(10) unsigned DEFAULT NULL COMMENT 'Токены из кеша',
  `processing_time_ms` int(10) unsigned DEFAULT NULL COMMENT 'Время обработки в миллисекундах',
  `cache_hit` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Был ли использован кеш',
  `retry_count` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Количество повторов при ошибках',
  `error_message` text DEFAULT NULL COMMENT 'Сообщение об ошибке',
  `error_code` varchar(50) DEFAULT NULL COMMENT 'Код ошибки',
  `processed_at` datetime DEFAULT NULL COMMENT 'Время успешной обработки',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Время создания записи',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Время последнего обновления',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_item_id` (`item_id`),
  KEY `idx_feed_id` (`feed_id`),
  KEY `idx_status` (`status`),
  KEY `idx_importance_rating` (`importance_rating`),
  KEY `idx_category_primary` (`category_primary`),
  KEY `idx_article_language` (`article_language`),
  KEY `idx_processed_at` (`processed_at`),
  KEY `idx_feed_status` (`feed_id`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Результаты AI суммаризации и категоризации новостей';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rss2tlg_summarization`
--

LOCK TABLES `rss2tlg_summarization` WRITE;
/*!40000 ALTER TABLE `rss2tlg_summarization` DISABLE KEYS */;
INSERT INTO `rss2tlg_summarization` VALUES
(1,10,9999,'failed',NULL,NULL,NULL,'',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0,'Не удалось получить результат от AI','0',NULL,'2025-11-11 16:59:10','2025-11-11 16:59:11'),
(3,11,9999,'failed',NULL,NULL,NULL,'',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0,'Не удалось получить результат от AI','0',NULL,'2025-11-11 16:59:11','2025-11-11 16:59:12'),
(5,12,9999,'failed',NULL,NULL,NULL,'',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,0,'Не удалось получить результат от AI','0',NULL,'2025-11-11 16:59:12','2025-11-11 16:59:13');
/*!40000 ALTER TABLE `rss2tlg_summarization` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rss2tlg_translation`
--

DROP TABLE IF EXISTS `rss2tlg_translation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rss2tlg_translation` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
  `item_id` int(10) unsigned NOT NULL COMMENT 'ID новости (FK -> rss2tlg_items.id)',
  `feed_id` int(10) unsigned NOT NULL COMMENT 'ID источника RSS',
  `status` enum('pending','processing','success','failed','skipped') NOT NULL DEFAULT 'pending' COMMENT 'Статус перевода',
  `source_language` varchar(10) NOT NULL COMMENT 'Исходный язык',
  `target_language` varchar(10) NOT NULL COMMENT 'Целевой язык',
  `translated_headline` varchar(500) DEFAULT NULL COMMENT 'Переведенный заголовок',
  `translated_summary` text DEFAULT NULL COMMENT 'Переведенное краткое содержание',
  `quality_score` tinyint(3) unsigned DEFAULT NULL COMMENT 'Оценка качества (1-10)',
  `quality_issues` text DEFAULT NULL COMMENT 'Проблемы качества перевода',
  `model_used` varchar(150) DEFAULT NULL COMMENT 'Модель AI для перевода',
  `tokens_used` int(10) unsigned DEFAULT NULL COMMENT 'Количество использованных токенов',
  `tokens_prompt` int(10) unsigned DEFAULT NULL COMMENT 'Токены промпта',
  `tokens_completion` int(10) unsigned DEFAULT NULL COMMENT 'Токены completion',
  `processing_time_ms` int(10) unsigned DEFAULT NULL COMMENT 'Время обработки в миллисекундах',
  `retry_count` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Количество повторов при ошибках',
  `error_message` text DEFAULT NULL COMMENT 'Сообщение об ошибке',
  `error_code` varchar(50) DEFAULT NULL COMMENT 'Код ошибки',
  `translated_at` datetime DEFAULT NULL COMMENT 'Время успешного перевода',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Время создания записи',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Время последнего обновления',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_item_lang` (`item_id`,`target_language`),
  KEY `idx_feed_id` (`feed_id`),
  KEY `idx_status` (`status`),
  KEY `idx_source_language` (`source_language`),
  KEY `idx_target_language` (`target_language`),
  KEY `idx_quality_score` (`quality_score`),
  KEY `idx_translated_at` (`translated_at`),
  KEY `idx_feed_status` (`feed_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Результаты AI перевода новостей';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rss2tlg_translation`
--

LOCK TABLES `rss2tlg_translation` WRITE;
/*!40000 ALTER TABLE `rss2tlg_translation` DISABLE KEYS */;
/*!40000 ALTER TABLE `rss2tlg_translation` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-11-11 17:01:11
