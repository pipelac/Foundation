mysqldump: [Warning] Using a password on the command line interface can be insecure.
-- MySQL dump 10.13  Distrib 8.0.43, for Linux (x86_64)
--
-- Host: 127.0.0.1    Database: rss2tlg
-- ------------------------------------------------------
-- Server version	8.0.43-0ubuntu0.24.04.2

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
mysqldump: Error: 'Access denied; you need (at least one of) the PROCESS privilege(s) for this operation' when trying to dump tablespaces

--
-- Table structure for table `rss2tlg_feed_state`
--

DROP TABLE IF EXISTS `rss2tlg_feed_state`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rss2tlg_feed_state` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
  `feed_id` int unsigned NOT NULL COMMENT 'Идентификатор источника (из конфигурации)',
  `url` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL RSS/Atom ленты',
  `etag` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'ETag из последнего успешного ответа',
  `last_modified` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Last-Modified из последнего успешного ответа',
  `last_status` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'HTTP статус код последнего запроса (0 = сетевая ошибка)',
  `error_count` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Счётчик последовательных ошибок',
  `backoff_until` datetime DEFAULT NULL COMMENT 'Время до которого запросы заблокированы (exponential backoff)',
  `fetched_at` datetime NOT NULL COMMENT 'Время последнего запроса',
  `updated_at` datetime NOT NULL COMMENT 'Время последнего обновления записи',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время создания записи',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_feed_id` (`feed_id`),
  UNIQUE KEY `idx_url` (`url`),
  KEY `idx_backoff_until` (`backoff_until`),
  KEY `idx_error_count` (`error_count`)
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Состояние RSS/Atom источников для модуля fetch';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rss2tlg_items`
--

DROP TABLE IF EXISTS `rss2tlg_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rss2tlg_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
  `feed_id` int unsigned NOT NULL COMMENT 'Идентификатор источника',
  `content_hash` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'MD5 хеш контента для дедупликации',
  `guid` varchar(512) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'GUID элемента из RSS',
  `title` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Заголовок новости',
  `link` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ссылка на новость',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT 'Краткое описание',
  `content` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'Полный контент',
  `pub_date` datetime DEFAULT NULL COMMENT 'Дата публикации в источнике',
  `author` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Автор',
  `categories` json DEFAULT NULL COMMENT 'Категории (массив)',
  `enclosures` json DEFAULT NULL COMMENT 'Вложения: изображения, аудио, видео',
  `extracted_content` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'Текст статьи, извлеченный с веб-страницы',
  `extracted_images` json DEFAULT NULL COMMENT 'Массив изображений из статьи',
  `extracted_metadata` json DEFAULT NULL COMMENT 'Мета-данные страницы (Open Graph, Twitter Cards)',
  `extraction_status` enum('pending','success','failed','skipped') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'Статус извлечения контента',
  `extraction_error` text COLLATE utf8mb4_unicode_ci COMMENT 'Сообщение об ошибке при извлечении',
  `extracted_at` datetime DEFAULT NULL COMMENT 'Дата и время извлечения контента',
  `is_published` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Флаг публикации в Telegram',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время создания записи',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Время последнего обновления',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_content_hash` (`content_hash`),
  KEY `idx_feed_id` (`feed_id`),
  KEY `idx_is_published` (`is_published`),
  KEY `idx_pub_date` (`pub_date`),
  KEY `idx_feed_published` (`feed_id`,`is_published`),
  KEY `idx_extraction_status` (`extraction_status`)
) ENGINE=InnoDB AUTO_INCREMENT=791 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Новости из RSS/Atom источников с извлеченным контентом';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `rss2tlg_publications`
--

DROP TABLE IF EXISTS `rss2tlg_publications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rss2tlg_publications` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'Первичный ключ',
  `item_id` int unsigned NOT NULL COMMENT 'ID новости (FK -> rss2tlg_items)',
  `feed_id` int unsigned NOT NULL COMMENT 'ID источника',
  `destination_type` enum('bot','channel') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Тип назначения',
  `destination_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ID чата или канала',
  `message_id` int unsigned NOT NULL COMMENT 'ID сообщения в Telegram',
  `published_at` datetime NOT NULL COMMENT 'Время публикации',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Время создания записи',
  PRIMARY KEY (`id`),
  KEY `idx_item_id` (`item_id`),
  KEY `idx_feed_id` (`feed_id`),
  KEY `idx_destination` (`destination_type`,`destination_id`),
  KEY `idx_published_at` (`published_at`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Журнал публикаций новостей в Telegram';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-11-03  9:31:56
