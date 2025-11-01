mysqldump: [Warning] Using a password on the command line interface can be insecure.
-- MySQL dump 10.13  Distrib 8.0.43, for Linux (x86_64)
--
-- Host: localhost    Database: telegram_bot_test
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
-- Table structure for table `telegram_bot_messages`
--

DROP TABLE IF EXISTS `telegram_bot_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `telegram_bot_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `direction` enum('incoming','outgoing') COLLATE utf8mb4_unicode_ci NOT NULL,
  `message_id` bigint unsigned DEFAULT NULL,
  `chat_id` bigint NOT NULL,
  `user_id` bigint DEFAULT NULL,
  `message_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `method_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `telegram_date` datetime DEFAULT NULL,
  `text` text COLLATE utf8mb4_unicode_ci,
  `caption` text COLLATE utf8mb4_unicode_ci,
  `file_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reply_to_message_id` bigint unsigned DEFAULT NULL,
  `file_size` int unsigned DEFAULT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `media_metadata` json DEFAULT NULL,
  `forward_from_chat_id` bigint DEFAULT NULL,
  `entities` json DEFAULT NULL,
  `reply_markup` json DEFAULT NULL,
  `options` json DEFAULT NULL,
  `raw_data` json DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT '1',
  `error_code` int DEFAULT NULL,
  `error_message` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_unique_message` (`direction`,`chat_id`,`message_id`),
  KEY `idx_chat_id` (`chat_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_direction_type` (`direction`,`message_type`),
  KEY `idx_message_id` (`message_id`),
  KEY `idx_telegram_date` (`telegram_date`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Хранилище входящих и исходящих сообщений Telegram бота';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `telegram_bot_messages`
--

LOCK TABLES `telegram_bot_messages` WRITE;
/*!40000 ALTER TABLE `telegram_bot_messages` DISABLE KEYS */;
INSERT INTO `telegram_bot_messages` VALUES (1,'outgoing',350,366442475,NULL,'text','sendMessage','2025-10-31 23:44:33','2025-10-31 23:44:32','🧪 **ТЕСТИРОВАНИЕ**\n\n🚀 **НАЧАЛО ТЕСТИРОВАНИЯ**\n\nЗапуск комплексного тестирования в режиме Polling.\n\nВсе действия логируются в MySQL.',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL),(2,'outgoing',351,366442475,NULL,'text','sendMessage','2025-10-31 23:44:33','2025-10-31 23:44:33','🧪 **ТЕСТИРОВАНИЕ**\n\n📋 **УРОВЕНЬ 1: Начальные операции**\n\nПроверка базовой отправки и приема сообщений',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL),(3,'outgoing',352,366442475,NULL,'text','sendMessage','2025-10-31 23:44:33','2025-10-31 23:44:33','🧪 Тест 1.1: Простое текстовое сообщение\n\nПривет! Это тестовое сообщение для проверки базовой функциональности.',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL),(4,'outgoing',353,366442475,NULL,'text','sendMessage','2025-10-31 23:44:34','2025-10-31 23:44:34','🧪 Тест 1.2: Сообщение с эмодзи\n\n😀 😎 🚀 💯 ✨ 🎉 🔥 ⭐ 💪 🏆',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL),(5,'outgoing',354,366442475,NULL,'text','sendMessage','2025-10-31 23:44:35','2025-10-31 23:44:35','🧪 **ТЕСТИРОВАНИЕ**\n\n⏳ **Требуется действие:**\n\nОтправьте любое текстовое сообщение в ответ на это уведомление.\n\n⏱ У вас 20 секунд.',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL),(6,'outgoing',356,366442475,NULL,'text','sendMessage','2025-10-31 23:44:37','2025-10-31 23:44:37','✅ Сообщение получено: 666666',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL),(7,'outgoing',357,366442475,NULL,'text','sendMessage','2025-10-31 23:44:40','2025-10-31 23:44:40','🧪 **ТЕСТИРОВАНИЕ**\n\n📋 **УРОВЕНЬ 2: Базовые операции с файлами**\n\nПроверка отправки и приема медиа-файлов',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL),(8,'outgoing',358,366442475,NULL,'text','sendMessage','2025-10-31 23:44:42','2025-10-31 23:44:42','🧪 **ТЕСТИРОВАНИЕ**\n\n⏳ **Требуется действие:**\n\nОтправьте любое **изображение** (фото) в чат.\n\n⏱ У вас 20 секунд.',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,NULL,NULL);
/*!40000 ALTER TABLE `telegram_bot_messages` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-10-31 23:45:27
