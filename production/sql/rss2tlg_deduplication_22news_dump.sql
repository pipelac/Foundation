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
) ENGINE=InnoDB AUTO_INCREMENT=46 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Результаты проверки новостей на дубликаты';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rss2tlg_deduplication`
--

LOCK TABLES `rss2tlg_deduplication` WRITE;
/*!40000 ALTER TABLE `rss2tlg_deduplication` DISABLE KEYS */;
INSERT INTO `rss2tlg_deduplication` VALUES
(1,805,2,'checked',0,NULL,35.00,'ai',1,'[]',NULL,'[]','google/gemma-3-27b-it',12465,0,29,0,NULL,NULL,'2025-11-09 22:45:53','2025-11-09 22:44:17','2025-11-09 22:45:53'),
(3,775,4,'checked',0,NULL,55.00,'ai',1,'[]',NULL,'[]','google/gemma-3-27b-it',12462,0,29,0,NULL,NULL,'2025-11-09 22:46:03','2025-11-09 22:45:54','2025-11-09 22:46:03'),
(5,776,4,'checked',0,NULL,55.20,'ai',1,'[]',NULL,'[]','google/gemma-3-27b-it',12583,0,29,0,NULL,NULL,'2025-11-09 22:46:46','2025-11-09 22:46:03','2025-11-09 22:46:46'),
(7,777,4,'checked',0,NULL,35.00,'ai',1,'[]',NULL,'[]','google/gemma-3-27b-it',12489,0,29,0,NULL,NULL,'2025-11-09 22:47:03','2025-11-09 22:46:47','2025-11-09 22:47:03'),
(9,778,4,'checked',0,NULL,55.00,'ai',1,'[]',NULL,'[]','google/gemma-3-27b-it',12537,0,29,0,NULL,NULL,'2025-11-09 22:47:21','2025-11-09 22:47:04','2025-11-09 22:47:21'),
(11,779,4,'checked',0,NULL,35.00,'ai',1,'[]',NULL,'[]','google/gemma-3-27b-it',12463,0,29,0,NULL,NULL,'2025-11-09 22:47:32','2025-11-09 22:47:22','2025-11-09 22:47:32'),
(13,780,4,'checked',0,NULL,35.00,'ai',1,'[]',NULL,'[]','google/gemma-3-27b-it',12514,0,29,0,NULL,NULL,'2025-11-09 22:47:47','2025-11-09 22:47:33','2025-11-09 22:47:47'),
(15,781,4,'checked',0,NULL,55.00,'ai',1,'[]',NULL,'[]','google/gemma-3-27b-it',12476,0,29,0,NULL,NULL,'2025-11-09 22:48:02','2025-11-09 22:47:48','2025-11-09 22:48:02'),
(17,782,4,'checked',0,NULL,35.00,'ai',1,'[]',NULL,'[]','google/gemma-3-27b-it',12487,0,29,0,NULL,NULL,'2025-11-09 22:48:15','2025-11-09 22:48:03','2025-11-09 22:48:15'),
(19,783,4,'checked',0,NULL,35.00,'ai',1,'[]',NULL,'[]','google/gemma-3-27b-it',12480,0,29,0,NULL,NULL,'2025-11-09 22:48:27','2025-11-09 22:48:16','2025-11-09 22:48:27'),
(21,784,4,'checked',0,NULL,42.50,'ai',1,'[]',NULL,'[]','google/gemma-3-27b-it',12522,0,29,0,NULL,NULL,'2025-11-09 22:48:38','2025-11-09 22:48:28','2025-11-09 22:48:38'),
(23,785,4,'checked',0,NULL,35.20,'ai',1,'[]',NULL,'[]','google/gemma-3-27b-it',12493,0,29,0,NULL,NULL,'2025-11-09 22:48:49','2025-11-09 22:48:39','2025-11-09 22:48:49'),
(25,786,4,'checked',1,NULL,72.50,'ai',0,'[]',NULL,'[]','google/gemma-3-27b-it',12528,0,29,0,NULL,NULL,'2025-11-09 22:48:59','2025-11-09 22:48:49','2025-11-09 22:48:59'),
(27,787,4,'checked',0,NULL,35.00,'ai',1,'[]',NULL,'[]','google/gemma-3-27b-it',12501,0,29,0,NULL,NULL,'2025-11-09 22:49:09','2025-11-09 22:49:00','2025-11-09 22:49:09'),
(29,788,4,'checked',0,NULL,35.20,'ai',1,'[]',NULL,'[]','google/gemma-3-27b-it',12480,0,29,0,NULL,NULL,'2025-11-09 22:49:20','2025-11-09 22:49:09','2025-11-09 22:49:20'),
(31,789,4,'checked',1,786,85.20,'ai',0,'[]',NULL,'[]','google/gemma-3-27b-it',12565,0,29,0,NULL,NULL,'2025-11-09 22:49:31','2025-11-09 22:49:20','2025-11-09 22:49:31'),
(33,790,4,'checked',0,NULL,35.20,'ai',1,'[]',NULL,'[]','google/gemma-3-27b-it',12521,0,29,0,NULL,NULL,'2025-11-09 22:49:41','2025-11-09 22:49:32','2025-11-09 22:49:41'),
(35,791,4,'checked',0,NULL,42.00,'ai',1,'[]',NULL,'[]','google/gemma-3-27b-it',12487,0,29,0,NULL,NULL,'2025-11-09 22:49:51','2025-11-09 22:49:42','2025-11-09 22:49:51'),
(37,792,4,'checked',0,NULL,42.50,'ai',1,'[]',NULL,'[]','google/gemma-3-27b-it',12570,0,29,0,NULL,NULL,'2025-11-09 22:50:02','2025-11-09 22:49:51','2025-11-09 22:50:02'),
(39,793,4,'checked',0,NULL,42.50,'ai',1,'[]',NULL,'[]','google/gemma-3-27b-it',12507,0,29,0,NULL,NULL,'2025-11-09 22:50:11','2025-11-09 22:50:02','2025-11-09 22:50:11'),
(41,794,4,'checked',0,NULL,42.50,'ai',1,'[]',NULL,'[]','google/gemma-3-27b-it',12513,0,29,0,NULL,NULL,'2025-11-09 22:50:20','2025-11-09 22:50:12','2025-11-09 22:50:20'),
(43,795,4,'checked',0,NULL,68.50,'ai',1,'[]',NULL,'[]','google/gemma-3-27b-it',12509,0,29,0,NULL,NULL,'2025-11-09 22:50:29','2025-11-09 22:50:20','2025-11-09 22:50:29');
/*!40000 ALTER TABLE `rss2tlg_deduplication` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-11-09 22:57:18
