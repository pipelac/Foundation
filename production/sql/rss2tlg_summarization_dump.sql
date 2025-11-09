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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Результаты AI суммаризации и категоризации новостей';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rss2tlg_summarization`
--

LOCK TABLES `rss2tlg_summarization` WRITE;
/*!40000 ALTER TABLE `rss2tlg_summarization` DISABLE KEYS */;
/*!40000 ALTER TABLE `rss2tlg_summarization` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-11-09 13:37:59
