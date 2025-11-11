/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.11.13-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: 127.0.0.1    Database: rss2tlg
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
-- Table structure for table `rss2tlg_feeds`
--

DROP TABLE IF EXISTS `rss2tlg_feeds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rss2tlg_feeds` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
  `name` varchar(255) NOT NULL COMMENT 'Название источника',
  `feed_url` varchar(1024) NOT NULL COMMENT 'URL RSS ленты',
  `website_url` varchar(1024) DEFAULT NULL COMMENT 'URL сайта',
  `enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Активен ли источник',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Время создания',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Время обновления',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_feed_url` (`feed_url`(255)),
  KEY `idx_enabled` (`enabled`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='RSS источники новостей';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rss2tlg_feeds`
--

LOCK TABLES `rss2tlg_feeds` WRITE;
/*!40000 ALTER TABLE `rss2tlg_feeds` DISABLE KEYS */;
INSERT INTO `rss2tlg_feeds` VALUES
(1,'РИА Новости','https://ria.ru/export/rss2/index.xml','https://ria.ru',1,'2025-11-11 17:50:31','2025-11-11 17:50:31'),
(2,'Коммерсантъ','https://www.kommersant.ru/rss/news.xml','https://www.kommersant.ru',1,'2025-11-11 17:50:31','2025-11-11 17:50:31'),
(3,'Интерфакс','https://www.interfax.ru/rss','https://www.interfax.ru',1,'2025-11-11 17:50:31','2025-11-11 17:50:31'),
(4,'TechCrunch','https://techcrunch.com/feed/','https://techcrunch.com',1,'2025-11-11 17:50:31','2025-11-11 17:50:31');
/*!40000 ALTER TABLE `rss2tlg_feeds` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-11-11 17:50:52
