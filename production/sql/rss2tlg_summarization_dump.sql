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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Результаты AI суммаризации и категоризации новостей';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rss2tlg_summarization`
--

LOCK TABLES `rss2tlg_summarization` WRITE;
/*!40000 ALTER TABLE `rss2tlg_summarization` DISABLE KEYS */;
INSERT INTO `rss2tlg_summarization` VALUES
(1,927,3,'success','ru','science','[\"space\",\"environment\"]','На Солнце зафиксирована мощная вспышка класса Х','В понедельник на Солнце произошла мощная вспышка класса Х. Об этом сообщили в Институте прикладной геофизики.  Вспышка была зафиксирована и изучается специалистами.  Данные о последствиях вспышки пока не сообщаются.  Вспышки класса Х являются самыми мощными из известных солнечных вспышек и могут оказывать влияние на геомагнитную обстановку на Земле.','[\"солнечная вспышка\",\"солнце\",\"класс х\",\"геофизика\",\"космическая погода\"]',8,'[\"Солнце\",\"Институт прикладной геофизики\"]','Мощная солнечная вспышка класса Х была зафиксирована на Солнце в понедельник.','[\"класс Х\",\"понедельник\"]','google/gemma-3-27b-it',2395,2078,317,0,NULL,0,0,NULL,NULL,'2025-11-10 10:27:55','2025-11-10 10:27:03','2025-11-10 10:27:55'),
(2,928,3,'success','ru','politics','[\"transport\"]','Лукашенко поручил доставить литовских водителей грузовиков к границе','Президент Белоруссии Александр Лукашенко дал распоряжение обеспечить доставку водителей литовских грузовиков, застрявших на территории Белоруссии, к контрольно-пропускным пунктам на границе. Лукашенко заявил, что водителей не будут обижать, отметив, что люди мучились в сложившейся ситуации. Инцидент связан с задержанием грузового транспорта из Литвы на белорусской территории. Президент лично вмешался в ситуацию для разрешения конфликта.','[\"лукашенко\",\"литовские грузовики\",\"белоруссия\",\"граница\",\"водители\"]',8,'[\"Александр Лукашенко\",\"Белоруссия\",\"Литва\"]','Президент Белоруссии Александр Лукашенко дал поручение доставить водителей застрявших литовских грузовиков к контрольно-пропускным пунктам на границе, пообещав, что их не будут обижать','[]','deepseek/deepseek-v3.2-exp',2433,2053,380,0,NULL,0,0,NULL,NULL,'2025-11-10 10:28:43','2025-11-10 10:27:55','2025-11-10 10:28:43'),
(3,929,3,'success','ru','war','[\"military\"]','ВС РФ взяли под контроль 244 здания в Красноармейске за сутки','Российские вооруженные силы за последние сутки установили контроль над 244 зданиями в Красноармейске (Покровске). Штурмовые группы ВС РФ ведут активные наступательные действия в микрорайоне Динас, северо-западных и восточных кварталах Центрального района города. Также проводится зачистка территории западной промзоны. Информация о боевых действиях подтверждена Министерством обороны Российской Федерации. Город Красноармейск расположен в Донецкой Народной Республике.','[\"вс рф\",\"красноармейск\",\"покровск\",\"днр\",\"наступательные действия\"]',14,'[\"ВС РФ\",\"Минобороны РФ\",\"Красноармейск\",\"Донецкая Народная Республика\"]','Российские штурмовые группы за сутки взяли под контроль 244 здания в Красноармейске (Покровске) и продолжают наступательные действия в микрорайоне Динас и Центральном районе города','[\"244 здания\",\"1 сутки\"]','deepseek/deepseek-v3.2-exp',2492,2099,393,0,NULL,0,0,NULL,NULL,'2025-11-10 10:29:34','2025-11-10 10:28:44','2025-11-10 10:29:34'),
(4,952,5,'success','en','space','[\"technology\"]','Blue Origin delays New Glenn launch to November 12','Blue Origin postponed its second launch attempt of the New Glenn rocket. The launch, crucial for Jeff Bezos\' company, aims to demonstrate the reusability of its rockets and deliver the first commercial payloads to orbit. The company will now target November 12th for the launch. This launch is a key milestone for Blue Origin as it competes in the growing space launch market.','[\"blue origin\",\"new glenn\",\"space launch\",\"jeff bezos\",\"rocket reusability\"]',8,'[\"Blue Origin\",\"Jeff Bezos\",\"New Glenn\"]','Blue Origin delayed the second launch attempt of its New Glenn rocket, rescheduling for November 12th.','[\"November 12\"]','google/gemma-3-27b-it',2351,2077,274,0,NULL,0,0,NULL,NULL,'2025-11-10 10:30:43','2025-11-10 10:29:34','2025-11-10 10:30:43'),
(5,953,5,'success','en','business','[\"education\",\"social\"]','Slow Ventures hosts etiquette finishing school for startup founders','Slow Ventures organized a three-hour \'Etiquette Finishing School\' event this week to help startup founders develop professional skills. The program covered essential business etiquette topics including how to execute the perfect handshake, improve public speaking abilities, and understand proper office decorum. The venture capital firm aims to equip entrepreneurs with social and professional skills beyond just business acumen. The intensive session focused on practical skills that founders can immediately apply in business settings. This initiative reflects the growing recognition that interpersonal skills are crucial for entrepreneurial success. The finishing school approach represents an unconventional but valuable addition to traditional startup support services.','[\"slow ventures\",\"etiquette\",\"founders\",\"business skills\",\"professional development\"]',6,'[\"Slow Ventures\"]','Slow Ventures hosted a three-hour \'Etiquette Finishing School\' event teaching startup founders professional etiquette skills including handshakes, public speaking, and office decorum','[\"3 hours\"]','deepseek/deepseek-v3.2-exp',2296,1998,298,0,NULL,0,0,NULL,NULL,'2025-11-10 10:31:38','2025-11-10 10:30:44','2025-11-10 10:31:38'),
(6,954,5,'success','en','entertainment','[\"business\",\"technology\"]','YouTube TV offers $20 credit after Disney channel blackout','YouTube TV is providing a $20 credit to its subscribers due to a prolonged blackout of Disney-owned channels, including ESPN and ABC. The outage, lasting over a week, has caused dissatisfaction among users. The credit will be applied to customers’ next billing statement as compensation for the disruption. This move aims to retain subscribers affected by the lack of access to popular Disney content. The dispute between YouTube TV and Disney remains unresolved.','[\"youtube tv\",\"disney\",\"blackout\",\"espn\",\"abc\"]',7,'[\"YouTube TV\",\"Disney\",\"ESPN\"]','YouTube TV announced a $20 credit for subscribers affected by a week-long blackout of Disney-owned channels like ESPN and ABC.','[\"$20\",\"1 week\"]','google/gemma-3-27b-it',2370,2080,290,0,NULL,0,0,NULL,NULL,'2025-11-10 10:32:30','2025-11-10 10:31:39','2025-11-10 10:32:30');
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

-- Dump completed on 2025-11-10 10:47:09
