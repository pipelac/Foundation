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
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-11-09 13:37:59
