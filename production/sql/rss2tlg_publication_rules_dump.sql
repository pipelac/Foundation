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
-- Table structure for table `rss2tlg_publication_rules`
--

DROP TABLE IF EXISTS `rss2tlg_publication_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rss2tlg_publication_rules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
  `feed_id` int(10) unsigned NOT NULL COMMENT 'ID источника RSS',
  `destination_type` enum('bot','channel','group') NOT NULL COMMENT 'Тип назначения',
  `destination_id` varchar(255) NOT NULL COMMENT 'ID чата/канала/группы (username или numeric ID)',
  `enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Активно ли правило',
  `categories` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Массив разрешенных категорий (null = все)' CHECK (json_valid(`categories`)),
  `min_importance` tinyint(3) unsigned DEFAULT NULL COMMENT 'Минимальный рейтинг важности (1-20)',
  `languages` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Массив разрешенных языков (null = все)' CHECK (json_valid(`languages`)),
  `include_image` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Прикреплять иллюстрацию',
  `include_link` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Включать ссылку на источник',
  `template` text DEFAULT NULL COMMENT 'Шаблон сообщения (опционально)',
  `priority` tinyint(3) unsigned NOT NULL DEFAULT 10 COMMENT 'Приоритет правила (1-100, выше = важнее)',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Время создания',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Время обновления',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_feed_destination` (`feed_id`,`destination_type`,`destination_id`),
  KEY `idx_enabled` (`enabled`),
  KEY `idx_priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Правила публикации новостей в каналы и группы';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rss2tlg_publication_rules`
--

LOCK TABLES `rss2tlg_publication_rules` WRITE;
/*!40000 ALTER TABLE `rss2tlg_publication_rules` DISABLE KEYS */;
/*!40000 ALTER TABLE `rss2tlg_publication_rules` ENABLE KEYS */;
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
