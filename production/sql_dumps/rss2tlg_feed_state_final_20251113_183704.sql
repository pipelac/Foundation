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
-- Table structure for table `rss2tlg_feed_state`
--

DROP TABLE IF EXISTS `rss2tlg_feed_state`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rss2tlg_feed_state` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
  `feed_id` int(10) unsigned NOT NULL COMMENT 'Идентификатор источника',
  `url` varchar(512) NOT NULL COMMENT 'URL RSS/Atom ленты',
  `etag` varchar(255) DEFAULT NULL COMMENT 'ETag из последнего успешного ответа',
  `last_modified` varchar(255) DEFAULT NULL COMMENT 'Last-Modified из последнего успешного ответа',
  `last_status` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'HTTP статус код последнего запроса',
  `last_error` text DEFAULT NULL COMMENT 'Текст последней ошибки',
  `error_count` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Счётчик последовательных ошибок',
  `backoff_until` datetime DEFAULT NULL COMMENT 'Время до которого запросы заблокированы',
  `fetched_at` datetime NOT NULL COMMENT 'Время последнего запроса',
  `updated_at` datetime NOT NULL COMMENT 'Время последнего обновления записи',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Время создания записи',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_feed_id` (`feed_id`),
  UNIQUE KEY `idx_url` (`url`),
  KEY `idx_backoff_until` (`backoff_until`),
  KEY `idx_error_count` (`error_count`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Состояние RSS/Atom источников';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rss2tlg_feed_state`
--

LOCK TABLES `rss2tlg_feed_state` WRITE;
/*!40000 ALTER TABLE `rss2tlg_feed_state` DISABLE KEYS */;
INSERT INTO `rss2tlg_feed_state` VALUES
(1,13,'https://ria.ru/export/rss2/index.xml?page_type=google_newsstand',NULL,NULL,200,NULL,0,NULL,'2025-11-13 18:36:20','2025-11-13 18:36:20','2025-11-13 18:36:20'),
(2,14,'https://www.kommersant.ru/rss/news.xml',NULL,NULL,200,NULL,0,NULL,'2025-11-13 18:36:21','2025-11-13 18:36:21','2025-11-13 18:36:21'),
(3,15,'https://www.interfax.ru/rss',NULL,NULL,200,NULL,0,NULL,'2025-11-13 18:36:21','2025-11-13 18:36:21','2025-11-13 18:36:21'),
(4,16,'https://techcrunch.com/feed/',NULL,NULL,200,NULL,0,NULL,'2025-11-13 18:36:53','2025-11-13 18:36:53','2025-11-13 18:36:53');
/*!40000 ALTER TABLE `rss2tlg_feed_state` ENABLE KEYS */;
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
