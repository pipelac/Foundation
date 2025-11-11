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
  `category_primary_en` varchar(100) DEFAULT NULL COMMENT 'Основная категория на английском',
  `category_secondary` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Массив дополнительных категорий (до 2)' CHECK (json_valid(`category_secondary`)),
  `category_secondary_en` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Массив дополнительных категорий на английском' CHECK (json_valid(`category_secondary_en`)),
  `headline` varchar(500) NOT NULL DEFAULT '' COMMENT 'Заголовок новости',
  `summary` text DEFAULT NULL COMMENT 'Краткое содержание (суммаризация)',
  `keywords` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Массив ключевых слов (до 5)' CHECK (json_valid(`keywords`)),
  `keywords_en` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Массив ключевых слов на английском' CHECK (json_valid(`keywords_en`)),
  `importance_rating` tinyint(3) unsigned DEFAULT NULL COMMENT 'Рейтинг важности (1-20)',
  `dedup_canonical_entities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Ключевые сущности для дедупликации' CHECK (json_valid(`dedup_canonical_entities`)),
  `dedup_canonical_entities_en` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Ключевые сущности на английском для дедупликации' CHECK (json_valid(`dedup_canonical_entities_en`)),
  `dedup_core_event` text DEFAULT NULL COMMENT 'Описание ключевого события',
  `dedup_core_event_en` text DEFAULT NULL COMMENT 'Описание ключевого события на английском',
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
  KEY `idx_feed_status` (`feed_id`,`status`),
  KEY `idx_category_primary_en` (`category_primary_en`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Результаты AI суммаризации и категоризации новостей';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rss2tlg_summarization`
--

LOCK TABLES `rss2tlg_summarization` WRITE;
/*!40000 ALTER TABLE `rss2tlg_summarization` DISABLE KEYS */;
INSERT INTO `rss2tlg_summarization` VALUES
(1,469,3,'success','ru','politics','politics','[\"military\"]','[\"military\"]','Песков объяснил сопровождение самолета Токаева истребителями как почетный караул','Пресс-секретарь президента России Дмитрий Песков заявил, что сопровождение российскими истребителями самолета президента Казахстана Касым-Жомарта Токаева, следовавшего в Москву, не связано с вопросами безопасности. По словам Пескова, такой почетный караул в рамках государственного визита является обычной практикой. Инцидент произошел во время официального визита казахстанского лидера в Россию. Российские власти подчеркивают, что это стандартный протокол для высокопоставленных иностранных гостей. Заявление было сделано для разъяснения ситуации и предотвращения неверных трактовок.','[\"песков\",\"токаев\",\"истребители\",\"почетный караул\",\"госвизит\"]','[\"peskov\",\"tokayev\",\"fighter jets\",\"honor guard\",\"state visit\"]',8,'[\"Дмитрий Песков\",\"Касым-Жомарт Токаев\",\"Россия\",\"Казахстан\",\"Москва\"]','[\"Dmitry Peskov\",\"Kassym-Jomart Tokayev\",\"Russia\",\"Kazakhstan\",\"Moscow\"]','Дмитрий Песков заявил, что сопровождение российскими истребителями самолета президента Казахстана Токаева является почетным караулом в рамках государственного визита, а не мерой безопасности','Dmitry Peskov stated that the escort of Kazakh President Tokayev\'s plane by Russian fighter jets was an honor guard during a state visit, not a security measure','[]','deepseek/deepseek-v3.2-exp',3763,3208,555,0,NULL,0,0,NULL,NULL,'2025-11-11 18:31:43','2025-11-11 18:31:26','2025-11-11 18:31:43'),
(2,470,3,'success','ru','politics','politics','[\"international_relations\"]','[\"international_relations\"]','Токаев заявил об отсутствии серьезных проблем между Россией и Казахстаном','Президент Казахстана Касым-Жомарт Токаев заявил, что между Россией и Казахстаном не существует серьезных проблем, а все возникающие вопросы оперативно решаются. Это заявление было сделано в начале неформальных переговоров в Кремле с президентом России Владимиром Путиным. Токаев охарактеризовал двустороннее сотрудничество как стратегическое партнерство и союзнические отношения. Встреча лидеров двух стран прошла в неформальной обстановке, что подчеркивает доверительный характер диалога. Президент Казахстана отметил эффективность механизмов решения текущих вопросов между государствами.','[\"токаев\",\"путин\",\"россия\",\"казахстан\",\"переговоры\"]','[\"tokayev\",\"putin\",\"russia\",\"kazakhstan\",\"negotiations\"]',11,'[\"Касым-Жомарт Токаев\",\"Владимир Путин\",\"Россия\",\"Казахстан\",\"Кремль\"]','[\"Kassym-Jomart Tokayev\",\"Vladimir Putin\",\"Russia\",\"Kazakhstan\",\"Kremlin\"]','Президент Казахстана Касым-Жомарт Токаев заявил на неформальных переговорах с Владимиром Путиным в Кремле, что между Россией и Казахстаном не существует серьезных проблем, а сотрудничество носит характер стратегического партнерства','Kazakh President Kassym-Jomart Tokayev stated during informal negotiations with Vladimir Putin in the Kremlin that there are no serious problems between Russia and Kazakhstan, and cooperation is of a strategic partnership nature','[]','deepseek/deepseek-v3.2-exp',3782,3221,561,0,NULL,0,0,NULL,NULL,'2025-11-11 18:32:00','2025-11-11 18:31:44','2025-11-11 18:32:00'),
(3,471,3,'success','ru','news','news','[\"disaster\",\"military\",\"sports\"]','[\"disaster\",\"military\",\"sports\"]','Обрушение в Красногорске, попытка угона истребителя и увольнение тренера Спартака','Во вторник, 11 ноября, произошло несколько значимых событий. В Красногорске случилось обрушение здания или сооружения. Украинская разведка предприняла попытку угона российского истребителя. Также футбольный клуб Спартак уволил своего тренера Станковича. Эти события охватывают различные сферы от чрезвычайных происшествий до военных инцидентов и спорта.','[\"красногорск\",\"обрушение\",\"украинская разведка\",\"истребитель\",\"спартак\"]','[\"krasnogorsk\",\"collapse\",\"ukrainian intelligence\",\"fighter jet\",\"spartak\"]',10,'[\"Красногорск\",\"Украинская разведка\",\"Спартак\",\"Станкович\"]','[\"Krasnogorsk\",\"Ukrainian Intelligence\",\"Spartak\",\"Stankovic\"]','11 ноября произошло обрушение в Красногорске, украинская разведка попыталась угнать истребитель, а футбольный клуб Спартак уволил тренера Станковича','On November 11, a collapse occurred in Krasnogorsk, Ukrainian intelligence attempted to hijack a fighter jet, and Spartak football club fired coach Stankovic','[\"2024-11-11\"]','deepseek/deepseek-v3.2-exp',3656,3153,503,0,NULL,0,0,NULL,NULL,'2025-11-11 18:32:15','2025-11-11 18:32:01','2025-11-11 18:32:15'),
(4,472,3,'success','ru','business','business','[\"economy\",\"politics\"]','[\"economy\",\"politics\"]','Минпромторг предложил создать институт саморегулирования для онлайн- и офлайн-ритейла','Министерство промышленности и торговли России выступило с инициативой создания института саморегулирования, который объединит как онлайн-, так и офлайн-ритейл. Данное предложение включено в перечень инициатив ведомства при формировании новой национальной модели торговли. Об этом заявил глава Минпромторга Антон Алиханов после конференции «День платформенной экономики». Инициатива направлена на разработку единых стандартов и правил для всей розничной торговли страны. Создание такого института позволит гармонизировать регулирование традиционной и цифровой торговли.','[\"минпромторг\",\"саморегулирование\",\"ритейл\",\"онлайн-торговля\",\"национальная модель торговли\"]','[\"ministry of industry and trade\",\"self-regulation\",\"retail\",\"online trade\",\"national trade model\"]',11,'[\"Минпромторг\",\"Антон Алиханов\",\"онлайн-ритейл\",\"офлайн-ритейл\",\"День платформенной экономики\"]','[\"Ministry of Industry and Trade\",\"Anton Alikhanov\",\"online retail\",\"offline retail\",\"Platform Economy Day\"]','Минпромторг предложил создать институт саморегулирования, объединяющий онлайн- и офлайн-ритейл, в рамках формирования новой национальной модели торговли','The Ministry of Industry and Trade proposed creating a self-regulation institute uniting online and offline retail as part of developing a new national trade model','[]','deepseek/deepseek-v3.2-exp',3768,3222,546,0,NULL,0,0,NULL,NULL,'2025-11-11 18:32:34','2025-11-11 18:32:16','2025-11-11 18:32:34'),
(5,473,3,'success','ru','politics','politics','[\"military\",\"war\"]','[\"military\",\"war\"]','Лидер Хезболлы заявил о сохранении оружия и продолжении борьбы против Израиля','Лидер движения Хезболла Наим Кассем заявил, что организация не намерена разоружаться и продолжит борьбу против Израиля. Он подчеркнул, что именно наличие оружия позволило достичь соглашения о прекращении огня с Израилем. Кассем также призвал ливанское правительство положить конец военному присутствию Израиля на юге Ливана. Заявление было сделано во вторник и передано агентством Мехр. Лидер Хезболлы отметил стратегическую важность сохранения военного потенциала для защиты интересов движения.','[\"наим кассем\",\"хезболла\",\"израиль\",\"разоружение\",\"ливан\"]','[\"naim qassem\",\"hezbollah\",\"israel\",\"disarmament\",\"lebanon\"]',13,'[\"Наим Кассем\",\"Хезболла\",\"Израиль\",\"Ливан\",\"Мехр\"]','[\"Naim Qassem\",\"Hezbollah\",\"Israel\",\"Lebanon\",\"Mehr News Agency\"]','Лидер движения Хезболла Наим Кассем заявил, что организация не сложит оружие и не прекратит борьбу, подчеркнув, что именно вооружение позволило достичь соглашения о прекращении огня с Израилем','Hezbollah leader Naim Qassem stated that the organization will not disarm or cease its struggle, emphasizing that weapons were what enabled them to reach a ceasefire agreement with Israel','[\"вторник\"]','deepseek/deepseek-v3.2-exp',3775,3239,536,0,NULL,0,0,NULL,NULL,'2025-11-11 18:32:50','2025-11-11 18:32:34','2025-11-11 18:32:50');
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

-- Dump completed on 2025-11-11 18:33:28
