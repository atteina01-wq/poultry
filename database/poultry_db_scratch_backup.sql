-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: poultry_db_scratch
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

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
-- Table structure for table `activities`
--

DROP TABLE IF EXISTS `activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activities` (
  `activity_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) DEFAULT NULL,
  `type` enum('Feeding','Health','Cleaning','Supply') NOT NULL,
  `item_name` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT NULL,
  `date` date NOT NULL,
  `remarks` text DEFAULT NULL,
  PRIMARY KEY (`activity_id`),
  KEY `batch_id` (`batch_id`),
  CONSTRAINT `activities_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activities`
--

LOCK TABLES `activities` WRITE;
/*!40000 ALTER TABLE `activities` DISABLE KEYS */;
INSERT INTO `activities` VALUES (7,4,'Health','vitamin',2.00,'2026-09-04',''),(8,4,'Health','vitamin',2.00,'2026-09-04',''),(9,4,'Health','vitamin',2.00,'2026-09-04',''),(12,4,'','Debug Vaccine',2.00,'2026-09-04','Test notes'),(17,4,'','vitamin',3.00,'2026-09-04','for healthy');
/*!40000 ALTER TABLE `activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `log_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` enum('create','update','delete','login','logout') NOT NULL,
  `table_name` varchar(64) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `idx_audit_logs_user` (`user_id`),
  KEY `idx_audit_logs_table_record` (`table_name`,`record_id`),
  KEY `idx_audit_logs_created_at` (`created_at`),
  CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=483 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'login','users',1,NULL,NULL,NULL,'2026-08-18 03:04:17'),(2,1,'login','users',1,NULL,NULL,NULL,'2026-08-18 03:05:50'),(3,1,'login','users',1,NULL,NULL,NULL,'2026-08-18 03:07:25'),(4,1,'create','egg_production',1,NULL,'{\"batch_id\":4,\"production_date\":\"2026-08-18\",\"grade_id\":5,\"quantity_collected\":400,\"quantity_broken\":50,\"usable_eggs\":350}','::1','2026-08-18 03:15:29'),(5,1,'login','users',1,NULL,NULL,NULL,'2026-08-18 03:54:42'),(6,2,'login','users',2,NULL,NULL,NULL,'2026-08-18 03:54:42'),(7,1,'login','users',1,NULL,NULL,NULL,'2026-08-18 04:07:18'),(8,2,'login','users',2,NULL,NULL,NULL,'2026-08-18 04:07:18'),(9,1,'login','users',1,NULL,NULL,NULL,'2026-08-18 04:21:32'),(10,2,'login','users',2,NULL,NULL,NULL,'2026-08-18 04:21:32'),(11,1,'login','users',1,NULL,NULL,NULL,'2026-08-18 04:24:23'),(12,2,'login','users',2,NULL,NULL,NULL,'2026-08-18 04:24:23'),(13,1,'create','egg_production',2,NULL,'{\"batch_id\":4,\"production_date\":\"2026-08-18\",\"grade_id\":4,\"quantity_collected\":1000,\"quantity_broken\":50,\"usable_eggs\":950}','::1','2026-08-18 04:26:04'),(14,1,'delete','egg_production',1,'{\"production_id\":1,\"quantity_collected\":400,\"quantity_broken\":50,\"grade_id\":5,\"batch_id\":4,\"status\":\"active\"}','{\"status\":\"cancelled\",\"reason\":\"User cancelled the production record\"}','::1','2026-08-18 04:26:51'),(15,1,'create','egg_production',3,NULL,'{\"batch_id\":4,\"production_date\":\"2026-08-19\",\"grade_id\":5,\"quantity_collected\":1000,\"quantity_broken\":0,\"usable_eggs\":1000}','::1','2026-08-18 04:27:12'),(16,1,'create','egg_production',4,NULL,'{\"batch_id\":4,\"production_date\":\"2026-08-20\",\"grade_id\":6,\"quantity_collected\":1000,\"quantity_broken\":0,\"usable_eggs\":1000}','::1','2026-08-18 04:27:36'),(17,1,'create','egg_production',5,NULL,'{\"batch_id\":4,\"production_date\":\"2026-08-21\",\"grade_id\":7,\"quantity_collected\":2000,\"quantity_broken\":100,\"usable_eggs\":1900}','::1','2026-08-18 04:27:57'),(18,1,'create','orders',2,NULL,'{\"customer_id\":1,\"total_amount\":1425,\"items_count\":1}','::1','2026-08-18 04:29:53'),(19,1,'create','reservations',1,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-08-19\",\"items_count\":1}','::1','2026-08-18 04:30:46'),(20,1,'','orders',3,NULL,'{\"customer_id\":2,\"total_amount\":3975,\"status\":\"confirmed\",\"items_count\":2}','::1','2026-08-18 04:39:34'),(21,1,'login','users',1,NULL,NULL,NULL,'2026-08-18 04:48:41'),(22,2,'login','users',2,NULL,NULL,NULL,'2026-08-18 04:48:41'),(23,1,'','orders',4,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 04:48:41'),(24,1,'','orders',5,NULL,'{\"customer_id\":1,\"total_amount\":420,\"status\":\"confirmed\",\"items_count\":2}',NULL,'2026-08-18 04:48:41'),(25,1,'','orders',6,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 04:48:41'),(26,1,'','orders',7,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 04:48:42'),(27,1,'','orders',7,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"First cancel\"}',NULL,'2026-08-18 04:48:42'),(28,1,'','orders',8,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 04:48:42'),(29,1,'','orders',9,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 04:48:42'),(30,1,'create','reservations',2,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-08-21\",\"items_count\":1}',NULL,'2026-08-18 04:48:42'),(31,1,'','orders',10,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 04:49:47'),(32,1,'login','users',1,NULL,NULL,NULL,'2026-08-18 04:50:13'),(33,2,'login','users',2,NULL,NULL,NULL,'2026-08-18 04:50:14'),(34,1,'','orders',11,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 04:50:14'),(35,1,'','orders',12,NULL,'{\"customer_id\":1,\"total_amount\":420,\"status\":\"confirmed\",\"items_count\":2}',NULL,'2026-08-18 04:50:14'),(36,1,'','orders',13,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 04:50:14'),(37,1,'','orders',14,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 04:50:14'),(38,1,'','orders',14,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"First cancel\"}',NULL,'2026-08-18 04:50:14'),(39,1,'','orders',15,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 04:50:14'),(40,1,'','orders',15,NULL,'{\"return_number\":\"RET-20260818-001\",\"reason\":\"Customer returned undamaged eggs\",\"items_count\":1}',NULL,'2026-08-18 04:50:14'),(41,1,'','orders',16,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 04:50:14'),(42,1,'create','reservations',3,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-08-21\",\"items_count\":1}',NULL,'2026-08-18 04:50:14'),(43,1,'login','users',1,NULL,NULL,NULL,'2026-08-18 04:50:36'),(44,2,'login','users',2,NULL,NULL,NULL,'2026-08-18 04:50:36'),(45,1,'','orders',17,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 04:50:36'),(46,1,'','orders',18,NULL,'{\"customer_id\":1,\"total_amount\":420,\"status\":\"confirmed\",\"items_count\":2}',NULL,'2026-08-18 04:50:36'),(47,1,'','orders',19,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 04:50:36'),(48,1,'','orders',19,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"Test cancellation\"}',NULL,'2026-08-18 04:50:36'),(49,1,'','orders',20,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 04:50:36'),(50,1,'','orders',20,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"First cancel\"}',NULL,'2026-08-18 04:50:36'),(51,1,'','orders',21,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 04:50:36'),(52,1,'','orders',21,NULL,'{\"return_number\":\"RET-20260818-002\",\"reason\":\"Customer returned undamaged eggs\",\"items_count\":1}',NULL,'2026-08-18 04:50:36'),(53,1,'','orders',22,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 04:50:36'),(54,1,'create','reservations',4,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-08-21\",\"items_count\":1}',NULL,'2026-08-18 04:50:37'),(55,1,'','reservations',4,'{\"status\":\"active\"}','{\"status\":\"cancelled\"}',NULL,'2026-08-18 04:50:37'),(56,1,'','orders',23,NULL,'{\"customer_id\":2,\"total_amount\":4800,\"status\":\"confirmed\",\"items_count\":4}','::1','2026-08-18 04:51:56'),(57,1,'login','users',1,NULL,NULL,NULL,'2026-08-18 05:05:00'),(58,2,'login','users',2,NULL,NULL,NULL,'2026-08-18 05:05:00'),(59,1,'','orders',24,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 05:05:00'),(60,1,'','orders',25,NULL,'{\"customer_id\":1,\"total_amount\":420,\"status\":\"confirmed\",\"items_count\":2}',NULL,'2026-08-18 05:05:00'),(61,1,'','orders',26,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 05:05:00'),(62,1,'','orders',26,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"Test cancellation\"}',NULL,'2026-08-18 05:05:00'),(63,1,'','orders',27,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 05:05:00'),(64,1,'','orders',27,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"First cancel\"}',NULL,'2026-08-18 05:05:00'),(65,1,'','orders',28,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 05:05:00'),(66,1,'','orders',28,NULL,'{\"return_number\":\"RET-20260818-003\",\"reason\":\"Customer returned undamaged eggs\",\"items_count\":1}',NULL,'2026-08-18 05:05:01'),(67,1,'','orders',29,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 05:05:01'),(68,1,'create','reservations',5,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-08-21\",\"items_count\":1}',NULL,'2026-08-18 05:05:01'),(69,1,'','reservations',5,'{\"status\":\"active\"}','{\"status\":\"cancelled\"}',NULL,'2026-08-18 05:05:01'),(70,1,'login','users',1,NULL,NULL,NULL,'2026-08-18 05:16:12'),(71,2,'login','users',2,NULL,NULL,NULL,'2026-08-18 05:16:12'),(72,1,'','orders',30,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 05:16:12'),(73,1,'','orders',31,NULL,'{\"customer_id\":1,\"total_amount\":420,\"status\":\"confirmed\",\"items_count\":2}',NULL,'2026-08-18 05:16:12'),(74,1,'','orders',32,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 05:16:12'),(75,1,'','orders',32,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"Test cancellation\"}',NULL,'2026-08-18 05:16:12'),(76,1,'','orders',33,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 05:16:12'),(77,1,'','orders',33,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"First cancel\"}',NULL,'2026-08-18 05:16:12'),(78,1,'','orders',34,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 05:16:12'),(79,1,'','orders',34,NULL,'{\"return_number\":\"RET-20260818-004\",\"reason\":\"Customer returned undamaged eggs\",\"items_count\":1}',NULL,'2026-08-18 05:16:12'),(80,1,'','orders',35,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 05:16:12'),(81,1,'create','reservations',6,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-08-21\",\"items_count\":1}',NULL,'2026-08-18 05:16:12'),(82,1,'','reservations',6,'{\"status\":\"active\"}','{\"status\":\"cancelled\"}',NULL,'2026-08-18 05:16:12'),(83,1,'login','users',1,NULL,NULL,NULL,'2026-08-18 05:18:37'),(84,1,'','orders',36,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 05:19:58'),(85,1,'','orders',36,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"Verification test cancellation\"}',NULL,'2026-08-18 05:19:58'),(86,1,'login','users',1,NULL,NULL,NULL,'2026-08-18 05:19:58'),(87,2,'login','users',2,NULL,NULL,NULL,'2026-08-18 05:19:58'),(88,1,'login','users',1,NULL,NULL,NULL,'2026-08-18 05:20:04'),(89,2,'login','users',2,NULL,NULL,NULL,'2026-08-18 05:20:04'),(90,1,'','orders',37,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 05:20:04'),(91,1,'','orders',38,NULL,'{\"customer_id\":1,\"total_amount\":420,\"status\":\"confirmed\",\"items_count\":2}',NULL,'2026-08-18 05:20:04'),(92,1,'','orders',39,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 05:20:04'),(93,1,'','orders',39,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"Test cancellation\"}',NULL,'2026-08-18 05:20:05'),(94,1,'','orders',40,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 05:20:05'),(95,1,'','orders',40,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"First cancel\"}',NULL,'2026-08-18 05:20:05'),(96,1,'','orders',41,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 05:20:05'),(97,1,'','orders',41,NULL,'{\"return_number\":\"RET-20260818-005\",\"reason\":\"Customer returned undamaged eggs\",\"items_count\":1}',NULL,'2026-08-18 05:20:05'),(98,1,'','orders',42,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-08-18 05:20:05'),(99,1,'create','reservations',7,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-08-21\",\"items_count\":1}',NULL,'2026-08-18 05:20:05'),(100,1,'','reservations',7,'{\"status\":\"active\"}','{\"status\":\"cancelled\"}',NULL,'2026-08-18 05:20:05'),(101,1,'','orders',43,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 05:03:26'),(102,1,'','orders',43,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"Verification test cancellation\"}',NULL,'2026-09-04 05:03:26'),(103,1,'login','users',1,NULL,NULL,NULL,'2026-09-04 05:03:26'),(104,2,'login','users',2,NULL,NULL,NULL,'2026-09-04 05:03:26'),(105,1,'login','users',1,NULL,NULL,NULL,'2026-09-04 05:03:28'),(106,2,'login','users',2,NULL,NULL,NULL,'2026-09-04 05:03:28'),(107,1,'','orders',44,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 05:03:28'),(108,1,'','orders',45,NULL,'{\"customer_id\":1,\"total_amount\":420,\"status\":\"confirmed\",\"items_count\":2}',NULL,'2026-09-04 05:03:28'),(109,1,'','orders',46,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 05:03:28'),(110,1,'','orders',46,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"Test cancellation\"}',NULL,'2026-09-04 05:03:28'),(111,1,'','orders',47,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 05:03:28'),(112,1,'','orders',47,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"First cancel\"}',NULL,'2026-09-04 05:03:28'),(113,1,'','orders',48,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 05:03:28'),(114,1,'','orders',48,NULL,'{\"return_number\":\"RET-20260904-001\",\"reason\":\"Customer returned undamaged eggs\",\"items_count\":1}',NULL,'2026-09-04 05:03:28'),(116,1,'create','reservations',8,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-07\",\"items_count\":1}',NULL,'2026-09-04 05:03:29'),(117,1,'','reservations',8,'{\"status\":\"active\"}','{\"status\":\"cancelled\"}',NULL,'2026-09-04 05:03:29'),(120,1,'login','users',1,NULL,NULL,NULL,'2026-09-04 05:49:03'),(121,2,'login','users',2,NULL,NULL,NULL,'2026-09-04 05:49:03'),(122,1,'login','users',1,NULL,NULL,NULL,'2026-09-04 05:49:07'),(123,2,'login','users',2,NULL,NULL,NULL,'2026-09-04 05:49:07'),(133,1,'create','reservations',9,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-07\",\"items_count\":1}',NULL,'2026-09-04 05:49:07'),(134,1,'','reservations',9,'{\"status\":\"active\"}','{\"status\":\"cancelled\"}',NULL,'2026-09-04 05:49:07'),(137,1,'login','users',1,NULL,NULL,NULL,'2026-09-04 06:12:17'),(138,2,'login','users',2,NULL,NULL,NULL,'2026-09-04 06:12:17'),(139,1,'login','users',1,NULL,NULL,NULL,'2026-09-04 06:12:22'),(140,2,'login','users',2,NULL,NULL,NULL,'2026-09-04 06:12:22'),(142,1,'','orders',59,NULL,'{\"customer_id\":1,\"total_amount\":420,\"status\":\"confirmed\",\"items_count\":2}',NULL,'2026-09-04 06:12:22'),(143,1,'','orders',60,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 06:12:22'),(144,1,'','orders',60,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"Test cancellation\"}',NULL,'2026-09-04 06:12:22'),(145,1,'','orders',61,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 06:12:22'),(146,1,'','orders',61,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"First cancel\"}',NULL,'2026-09-04 06:12:22'),(147,1,'','orders',62,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 06:12:22'),(148,1,'','orders',62,NULL,'{\"return_number\":\"RET-20260904-003\",\"reason\":\"Customer returned undamaged eggs\",\"items_count\":1}',NULL,'2026-09-04 06:12:22'),(149,1,'','orders',63,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 06:12:22'),(150,1,'create','reservations',10,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-07\",\"items_count\":1}',NULL,'2026-09-04 06:12:22'),(151,1,'','reservations',10,'{\"status\":\"active\"}','{\"status\":\"cancelled\"}',NULL,'2026-09-04 06:12:22'),(152,1,'','orders',64,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 06:16:24'),(153,1,'','orders',64,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"Verification test cancellation\"}',NULL,'2026-09-04 06:16:24'),(154,1,'login','users',1,NULL,NULL,NULL,'2026-09-04 06:16:24'),(155,2,'login','users',2,NULL,NULL,NULL,'2026-09-04 06:16:25'),(156,1,'login','users',1,NULL,NULL,NULL,'2026-09-04 06:16:25'),(157,2,'login','users',2,NULL,NULL,NULL,'2026-09-04 06:16:25'),(158,1,'','orders',65,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 06:16:25'),(159,1,'','orders',66,NULL,'{\"customer_id\":1,\"total_amount\":420,\"status\":\"confirmed\",\"items_count\":2}',NULL,'2026-09-04 06:16:25'),(160,1,'','orders',67,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 06:16:25'),(161,1,'','orders',67,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"Test cancellation\"}',NULL,'2026-09-04 06:16:25'),(162,1,'','orders',68,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 06:16:25'),(163,1,'','orders',68,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"First cancel\"}',NULL,'2026-09-04 06:16:25'),(164,1,'','orders',69,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 06:16:25'),(165,1,'','orders',69,NULL,'{\"return_number\":\"RET-20260904-004\",\"reason\":\"Customer returned undamaged eggs\",\"items_count\":1}',NULL,'2026-09-04 06:16:25'),(166,1,'','orders',70,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 06:16:25'),(167,1,'create','reservations',11,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-07\",\"items_count\":1}',NULL,'2026-09-04 06:16:25'),(168,1,'','reservations',11,'{\"status\":\"active\"}','{\"status\":\"cancelled\"}',NULL,'2026-09-04 06:16:25'),(169,1,'','orders',71,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 06:17:48'),(170,1,'','orders',71,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"Verification test cancellation\"}',NULL,'2026-09-04 06:17:48'),(171,1,'login','users',1,NULL,NULL,NULL,'2026-09-04 06:17:48'),(172,2,'login','users',2,NULL,NULL,NULL,'2026-09-04 06:17:48'),(173,1,'login','users',1,NULL,NULL,NULL,'2026-09-04 06:17:48'),(174,2,'login','users',2,NULL,NULL,NULL,'2026-09-04 06:17:48'),(175,1,'','orders',72,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 06:17:48'),(176,1,'','orders',73,NULL,'{\"customer_id\":1,\"total_amount\":420,\"status\":\"confirmed\",\"items_count\":2}',NULL,'2026-09-04 06:17:48'),(177,1,'','orders',74,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 06:17:48'),(178,1,'','orders',74,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"Test cancellation\"}',NULL,'2026-09-04 06:17:48'),(179,1,'','orders',75,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 06:17:48'),(180,1,'','orders',75,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"First cancel\"}',NULL,'2026-09-04 06:17:48'),(181,1,'','orders',76,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 06:17:48'),(182,1,'','orders',76,NULL,'{\"return_number\":\"RET-20260904-005\",\"reason\":\"Customer returned undamaged eggs\",\"items_count\":1}',NULL,'2026-09-04 06:17:48'),(183,1,'','orders',77,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 06:17:48'),(184,1,'create','reservations',12,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-07\",\"items_count\":1}',NULL,'2026-09-04 06:17:48'),(185,1,'','reservations',12,'{\"status\":\"active\"}','{\"status\":\"cancelled\"}',NULL,'2026-09-04 06:17:48'),(186,1,'','orders',78,NULL,'{\"customer_id\":1,\"total_amount\":6735,\"status\":\"confirmed\",\"items_count\":4}','::1','2026-09-04 06:31:24'),(187,1,'login','users',1,NULL,NULL,NULL,'2026-09-04 07:01:21'),(188,2,'login','users',2,NULL,NULL,NULL,'2026-09-04 07:01:21'),(189,1,'login','users',1,NULL,NULL,NULL,'2026-09-04 07:01:47'),(190,2,'login','users',2,NULL,NULL,NULL,'2026-09-04 07:01:47'),(191,1,'','reservations',1,'{\"status\":\"active\"}','{\"status\":\"fulfilled\"}','::1','2026-09-04 07:05:32'),(192,1,'','reservations',3,'{\"status\":\"active\"}','{\"status\":\"cancelled\"}','::1','2026-09-04 07:06:02'),(193,1,'','orders',79,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 07:06:41'),(194,1,'','orders',79,'{\"status\":\"confirmed\"}','{\"status\":\"processing\"}',NULL,'2026-09-04 07:06:41'),(195,1,'','orders',79,'{\"status\":\"processing\"}','{\"status\":\"delivered\"}',NULL,'2026-09-04 07:06:41'),(196,1,'','orders',80,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 07:06:41'),(197,1,'','orders',80,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"E2E Test Cancellation\"}',NULL,'2026-09-04 07:06:41'),(198,1,'','orders',81,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 07:06:41'),(199,1,'','orders',81,'{\"status\":\"confirmed\"}','{\"status\":\"delivered\"}',NULL,'2026-09-04 07:06:41'),(200,1,'','orders',81,NULL,'{\"return_number\":\"RET-20260904-006\",\"reason\":\"E2E Return Test\",\"items_count\":2}',NULL,'2026-09-04 07:06:41'),(201,1,'create','reservations',13,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-09\",\"items_count\":1}',NULL,'2026-09-04 07:06:41'),(202,1,'','reservations',13,'{\"status\":\"active\"}','{\"status\":\"cancelled\"}',NULL,'2026-09-04 07:06:41'),(203,1,'create','reservations',14,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-07\",\"items_count\":1}',NULL,'2026-09-04 07:06:41'),(204,1,'','reservations',14,NULL,'{\"converted_order_id\":82}',NULL,'2026-09-04 07:06:41'),(205,1,'','waste_records',1,NULL,'{\"waste_type\":\"egg\",\"grade_id\":4,\"quantity\":5,\"unit\":\"pcs\",\"reason\":\"broken\",\"remarks\":\"E2E Broken egg test\",\"recorded_by\":1}',NULL,'2026-09-04 07:06:41'),(206,1,'','waste_records',2,NULL,'{\"waste_type\":\"supply\",\"inventory_id\":1,\"quantity\":2,\"unit\":\"units\",\"reason\":\"expired\",\"estimated_cost\":350,\"remarks\":\"E2E Supply Waste Test\",\"recorded_by\":1}',NULL,'2026-09-04 07:06:41'),(207,1,'create','inventory',8,NULL,'{\"item_name\":\"Agricultural Limestone 1788505601\",\"category\":\"limestone\",\"quantity\":50,\"unit\":\"bags\",\"unit_cost\":220,\"lot_number\":\"LOT-TEST-1788505601\",\"supplier\":\"Calcite Minerals Inc.\",\"received_date\":\"2026-09-04\",\"expiry_date\":\"2026-09-19\"}',NULL,'2026-09-04 07:06:41'),(208,1,'','orders',83,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 07:07:00'),(209,1,'','orders',83,'{\"status\":\"confirmed\"}','{\"status\":\"processing\"}',NULL,'2026-09-04 07:07:00'),(210,1,'','orders',83,'{\"status\":\"processing\"}','{\"status\":\"delivered\"}',NULL,'2026-09-04 07:07:00'),(211,1,'','orders',84,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 07:07:00'),(212,1,'','orders',84,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"E2E Test Cancellation\"}',NULL,'2026-09-04 07:07:00'),(213,1,'','orders',85,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 07:07:00'),(214,1,'','orders',85,'{\"status\":\"confirmed\"}','{\"status\":\"delivered\"}',NULL,'2026-09-04 07:07:00'),(215,1,'','orders',85,NULL,'{\"return_number\":\"RET-20260904-007\",\"reason\":\"E2E Return Test\",\"items_count\":2}',NULL,'2026-09-04 07:07:00'),(216,1,'create','reservations',15,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-09\",\"items_count\":1}',NULL,'2026-09-04 07:07:00'),(217,1,'','reservations',15,'{\"status\":\"active\"}','{\"status\":\"cancelled\"}',NULL,'2026-09-04 07:07:00'),(218,1,'create','reservations',16,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-07\",\"items_count\":1}',NULL,'2026-09-04 07:07:00'),(219,1,'','reservations',16,NULL,'{\"converted_order_id\":86}',NULL,'2026-09-04 07:07:00'),(220,1,'','waste_records',3,NULL,'{\"waste_type\":\"egg\",\"grade_id\":4,\"quantity\":5,\"unit\":\"pcs\",\"reason\":\"broken\",\"remarks\":\"E2E Broken egg test\",\"recorded_by\":1}',NULL,'2026-09-04 07:07:00'),(221,1,'','waste_records',4,NULL,'{\"waste_type\":\"supply\",\"inventory_id\":1,\"quantity\":2,\"unit\":\"units\",\"reason\":\"expired\",\"estimated_cost\":350,\"remarks\":\"E2E Supply Waste Test\",\"recorded_by\":1}',NULL,'2026-09-04 07:07:00'),(222,1,'create','inventory',9,NULL,'{\"item_name\":\"Agricultural Limestone 1788505620\",\"category\":\"limestone\",\"quantity\":50,\"unit\":\"bags\",\"unit_cost\":220,\"lot_number\":\"LOT-TEST-1788505620\",\"supplier\":\"Calcite Minerals Inc.\",\"received_date\":\"2026-09-04\",\"expiry_date\":\"2026-09-19\"}',NULL,'2026-09-04 07:07:00'),(223,1,'','orders',87,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 07:35:48'),(224,1,'','orders',87,'{\"status\":\"confirmed\"}','{\"status\":\"processing\"}',NULL,'2026-09-04 07:35:48'),(225,1,'','orders',87,'{\"status\":\"processing\"}','{\"status\":\"delivered\"}',NULL,'2026-09-04 07:35:48'),(226,1,'','orders',88,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 07:35:48'),(227,1,'','orders',88,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"E2E Test Cancellation\"}',NULL,'2026-09-04 07:35:48'),(228,1,'','orders',89,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 07:35:48'),(229,1,'','orders',89,'{\"status\":\"confirmed\"}','{\"status\":\"delivered\"}',NULL,'2026-09-04 07:35:48'),(230,1,'','orders',89,NULL,'{\"return_number\":\"RET-20260904-008\",\"reason\":\"E2E Return Test\",\"items_count\":2}',NULL,'2026-09-04 07:35:49'),(231,1,'create','reservations',17,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-09\",\"items_count\":1}',NULL,'2026-09-04 07:35:49'),(232,1,'','reservations',17,'{\"status\":\"active\"}','{\"status\":\"cancelled\"}',NULL,'2026-09-04 07:35:49'),(233,1,'create','reservations',18,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-07\",\"items_count\":1}',NULL,'2026-09-04 07:35:49'),(234,1,'','reservations',18,NULL,'{\"converted_order_id\":90}',NULL,'2026-09-04 07:35:49'),(235,1,'','waste_records',5,NULL,'{\"waste_type\":\"egg\",\"grade_id\":4,\"quantity\":5,\"unit\":\"pcs\",\"reason\":\"broken\",\"remarks\":\"E2E Broken egg test\",\"recorded_by\":1}',NULL,'2026-09-04 07:35:49'),(236,1,'','waste_records',6,NULL,'{\"waste_type\":\"supply\",\"inventory_id\":1,\"quantity\":2,\"unit\":\"units\",\"reason\":\"expired\",\"estimated_cost\":350,\"remarks\":\"E2E Supply Waste Test\",\"recorded_by\":1}',NULL,'2026-09-04 07:35:49'),(237,1,'create','inventory',10,NULL,'{\"item_name\":\"Agricultural Limestone 1788507349\",\"category\":\"limestone\",\"quantity\":50,\"unit\":\"bags\",\"unit_cost\":220,\"lot_number\":\"LOT-TEST-1788507349\",\"supplier\":\"Calcite Minerals Inc.\",\"received_date\":\"2026-09-04\",\"expiry_date\":\"2026-09-19\"}',NULL,'2026-09-04 07:35:49'),(238,1,'','orders',91,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 07:53:35'),(239,1,'','orders',91,'{\"status\":\"confirmed\"}','{\"status\":\"processing\"}',NULL,'2026-09-04 07:53:35'),(240,1,'','orders',91,'{\"status\":\"processing\"}','{\"status\":\"delivered\"}',NULL,'2026-09-04 07:53:35'),(241,1,'','orders',92,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 07:53:35'),(242,1,'','orders',92,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"E2E Test Cancellation\"}',NULL,'2026-09-04 07:53:35'),(243,1,'','orders',93,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 07:53:35'),(244,1,'','orders',93,'{\"status\":\"confirmed\"}','{\"status\":\"delivered\"}',NULL,'2026-09-04 07:53:35'),(245,1,'','orders',93,NULL,'{\"return_number\":\"RET-20260904-009\",\"reason\":\"E2E Return Test\",\"items_count\":2}',NULL,'2026-09-04 07:53:35'),(246,1,'create','reservations',19,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-09\",\"items_count\":1}',NULL,'2026-09-04 07:53:35'),(247,1,'','reservations',19,'{\"status\":\"active\"}','{\"status\":\"cancelled\"}',NULL,'2026-09-04 07:53:35'),(248,1,'create','reservations',20,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-07\",\"items_count\":1}',NULL,'2026-09-04 07:53:35'),(249,1,'','reservations',20,NULL,'{\"converted_order_id\":94}',NULL,'2026-09-04 07:53:35'),(250,1,'','waste_records',7,NULL,'{\"waste_type\":\"egg\",\"grade_id\":4,\"quantity\":5,\"unit\":\"pcs\",\"reason\":\"broken\",\"remarks\":\"E2E Broken egg test\",\"recorded_by\":1}',NULL,'2026-09-04 07:53:35'),(251,1,'','waste_records',8,NULL,'{\"waste_type\":\"supply\",\"inventory_id\":1,\"quantity\":2,\"unit\":\"units\",\"reason\":\"expired\",\"estimated_cost\":350,\"remarks\":\"E2E Supply Waste Test\",\"recorded_by\":1}',NULL,'2026-09-04 07:53:35'),(252,1,'create','inventory',11,NULL,'{\"item_name\":\"Agricultural Limestone 1788508415\",\"category\":\"limestone\",\"quantity\":50,\"unit\":\"bags\",\"unit_cost\":220,\"lot_number\":\"LOT-TEST-1788508415\",\"supplier\":\"Calcite Minerals Inc.\",\"received_date\":\"2026-09-04\",\"expiry_date\":\"2026-09-19\"}',NULL,'2026-09-04 07:53:35'),(253,1,'create','egg_production',6,NULL,'{\"batch_id\":4,\"production_date\":\"2026-09-04\",\"grade_id\":6,\"quantity_collected\":9999,\"quantity_broken\":5,\"usable_eggs\":9994}','::1','2026-09-04 07:59:08'),(254,1,'create','egg_production',7,NULL,'{\"batch_id\":4,\"production_date\":\"2026-09-04\",\"grade_id\":5,\"quantity_collected\":10000,\"quantity_broken\":7,\"usable_eggs\":9993}','::1','2026-09-04 07:59:25'),(255,1,'create','egg_production',8,NULL,'{\"batch_id\":4,\"production_date\":\"2026-09-04\",\"grade_id\":4,\"quantity_collected\":6000,\"quantity_broken\":6,\"usable_eggs\":5994}','::1','2026-09-04 08:00:08'),(256,1,'','orders',95,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 08:00:45'),(257,1,'','orders',95,'{\"status\":\"confirmed\"}','{\"status\":\"processing\"}',NULL,'2026-09-04 08:00:45'),(258,1,'','orders',95,'{\"status\":\"processing\"}','{\"status\":\"delivered\"}',NULL,'2026-09-04 08:00:45'),(259,1,'','orders',96,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 08:00:45'),(260,1,'','orders',96,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"E2E Test Cancellation\"}',NULL,'2026-09-04 08:00:45'),(261,1,'','orders',97,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 08:00:45'),(262,1,'','orders',97,'{\"status\":\"confirmed\"}','{\"status\":\"delivered\"}',NULL,'2026-09-04 08:00:45'),(263,1,'','orders',97,NULL,'{\"return_number\":\"RET-20260904-010\",\"reason\":\"E2E Return Test\",\"items_count\":2}',NULL,'2026-09-04 08:00:45'),(264,1,'create','reservations',21,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-09\",\"items_count\":1}',NULL,'2026-09-04 08:00:45'),(265,1,'','reservations',21,'{\"status\":\"active\"}','{\"status\":\"cancelled\"}',NULL,'2026-09-04 08:00:45'),(266,1,'create','reservations',22,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-07\",\"items_count\":1}',NULL,'2026-09-04 08:00:45'),(267,1,'','reservations',22,NULL,'{\"converted_order_id\":98}',NULL,'2026-09-04 08:00:45'),(268,1,'','waste_records',9,NULL,'{\"waste_type\":\"egg\",\"grade_id\":4,\"quantity\":5,\"unit\":\"pcs\",\"reason\":\"broken\",\"remarks\":\"E2E Broken egg test\",\"recorded_by\":1}',NULL,'2026-09-04 08:00:45'),(269,1,'','waste_records',10,NULL,'{\"waste_type\":\"supply\",\"inventory_id\":1,\"quantity\":2,\"unit\":\"units\",\"reason\":\"expired\",\"estimated_cost\":350,\"remarks\":\"E2E Supply Waste Test\",\"recorded_by\":1}',NULL,'2026-09-04 08:00:45'),(270,1,'create','inventory',12,NULL,'{\"item_name\":\"Agricultural Limestone 1788508845\",\"category\":\"limestone\",\"quantity\":50,\"unit\":\"bags\",\"unit_cost\":220,\"lot_number\":\"LOT-TEST-1788508845\",\"supplier\":\"Calcite Minerals Inc.\",\"received_date\":\"2026-09-04\",\"expiry_date\":\"2026-09-19\"}',NULL,'2026-09-04 08:00:45'),(271,1,'','orders',99,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 08:17:25'),(272,1,'','orders',99,'{\"status\":\"confirmed\"}','{\"status\":\"processing\"}',NULL,'2026-09-04 08:17:25'),(273,1,'','orders',99,'{\"status\":\"processing\"}','{\"status\":\"delivered\"}',NULL,'2026-09-04 08:17:25'),(274,1,'','orders',100,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 08:17:25'),(275,1,'','orders',100,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"E2E Test Cancellation\"}',NULL,'2026-09-04 08:17:25'),(276,1,'','orders',101,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 08:17:25'),(277,1,'','orders',101,'{\"status\":\"confirmed\"}','{\"status\":\"delivered\"}',NULL,'2026-09-04 08:17:25'),(278,1,'','orders',101,NULL,'{\"return_number\":\"RET-20260904-011\",\"reason\":\"E2E Return Test\",\"items_count\":2}',NULL,'2026-09-04 08:17:25'),(279,1,'create','reservations',23,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-09\",\"items_count\":1}',NULL,'2026-09-04 08:17:25'),(280,1,'','reservations',23,'{\"status\":\"active\"}','{\"status\":\"cancelled\"}',NULL,'2026-09-04 08:17:25'),(281,1,'create','reservations',24,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-07\",\"items_count\":1}',NULL,'2026-09-04 08:17:25'),(282,1,'','reservations',24,NULL,'{\"converted_order_id\":102}',NULL,'2026-09-04 08:17:25'),(283,1,'','waste_records',11,NULL,'{\"waste_type\":\"egg\",\"grade_id\":4,\"quantity\":5,\"unit\":\"pcs\",\"reason\":\"broken\",\"remarks\":\"E2E Broken egg test\",\"recorded_by\":1}',NULL,'2026-09-04 08:17:25'),(284,1,'','waste_records',12,NULL,'{\"waste_type\":\"supply\",\"inventory_id\":1,\"quantity\":2,\"unit\":\"units\",\"reason\":\"expired\",\"estimated_cost\":350,\"remarks\":\"E2E Supply Waste Test\",\"recorded_by\":1}',NULL,'2026-09-04 08:17:25'),(285,1,'create','inventory',13,NULL,'{\"item_name\":\"Agricultural Limestone 1788509845\",\"category\":\"limestone\",\"quantity\":50,\"unit\":\"bags\",\"unit_cost\":220,\"lot_number\":\"LOT-TEST-1788509845\",\"supplier\":\"Calcite Minerals Inc.\",\"received_date\":\"2026-09-04\",\"expiry_date\":\"2026-09-19\"}',NULL,'2026-09-04 08:17:25'),(286,1,'','orders',103,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 08:23:29'),(287,1,'','orders',103,'{\"status\":\"confirmed\"}','{\"status\":\"processing\"}',NULL,'2026-09-04 08:23:29'),(288,1,'','orders',103,'{\"status\":\"processing\"}','{\"status\":\"delivered\"}',NULL,'2026-09-04 08:23:29'),(289,1,'','orders',104,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 08:23:29'),(290,1,'','orders',104,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"E2E Test Cancellation\"}',NULL,'2026-09-04 08:23:29'),(291,1,'','orders',105,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 08:23:29'),(292,1,'','orders',105,'{\"status\":\"confirmed\"}','{\"status\":\"delivered\"}',NULL,'2026-09-04 08:23:29'),(293,1,'','orders',105,NULL,'{\"return_number\":\"RET-20260904-012\",\"reason\":\"E2E Return Test\",\"items_count\":2}',NULL,'2026-09-04 08:23:29'),(294,1,'create','reservations',25,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-09\",\"items_count\":1}',NULL,'2026-09-04 08:23:29'),(295,1,'','reservations',25,'{\"status\":\"active\"}','{\"status\":\"cancelled\"}',NULL,'2026-09-04 08:23:29'),(296,1,'create','reservations',26,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-07\",\"items_count\":1}',NULL,'2026-09-04 08:23:29'),(297,1,'','reservations',26,NULL,'{\"converted_order_id\":106}',NULL,'2026-09-04 08:23:29'),(298,1,'','waste_records',13,NULL,'{\"waste_type\":\"egg\",\"grade_id\":4,\"quantity\":5,\"unit\":\"pcs\",\"reason\":\"broken\",\"remarks\":\"E2E Broken egg test\",\"recorded_by\":1}',NULL,'2026-09-04 08:23:29'),(299,1,'','waste_records',14,NULL,'{\"waste_type\":\"supply\",\"inventory_id\":1,\"quantity\":2,\"unit\":\"units\",\"reason\":\"expired\",\"estimated_cost\":350,\"remarks\":\"E2E Supply Waste Test\",\"recorded_by\":1}',NULL,'2026-09-04 08:23:29'),(300,1,'create','inventory',14,NULL,'{\"item_name\":\"Agricultural Limestone 1788510209\",\"category\":\"limestone\",\"quantity\":50,\"unit\":\"bags\",\"unit_cost\":220,\"lot_number\":\"LOT-TEST-1788510209\",\"supplier\":\"Calcite Minerals Inc.\",\"received_date\":\"2026-09-04\",\"expiry_date\":\"2026-09-19\"}',NULL,'2026-09-04 08:23:29'),(301,1,'','orders',107,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 08:24:07'),(302,1,'','orders',107,'{\"status\":\"confirmed\"}','{\"status\":\"processing\"}',NULL,'2026-09-04 08:24:07'),(303,1,'','orders',107,'{\"status\":\"processing\"}','{\"status\":\"delivered\"}',NULL,'2026-09-04 08:24:07'),(304,1,'','orders',108,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 08:24:07'),(305,1,'','orders',108,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"E2E Test Cancellation\"}',NULL,'2026-09-04 08:24:07'),(306,1,'','orders',109,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 08:24:07'),(307,1,'','orders',109,'{\"status\":\"confirmed\"}','{\"status\":\"delivered\"}',NULL,'2026-09-04 08:24:07'),(308,1,'','orders',109,NULL,'{\"return_number\":\"RET-20260904-013\",\"reason\":\"E2E Return Test\",\"items_count\":2}',NULL,'2026-09-04 08:24:07'),(309,1,'create','reservations',27,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-09\",\"items_count\":1}',NULL,'2026-09-04 08:24:07'),(310,1,'','reservations',27,'{\"status\":\"active\"}','{\"status\":\"cancelled\"}',NULL,'2026-09-04 08:24:07'),(311,1,'create','reservations',28,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-07\",\"items_count\":1}',NULL,'2026-09-04 08:24:07'),(312,1,'','reservations',28,NULL,'{\"converted_order_id\":110}',NULL,'2026-09-04 08:24:07'),(313,1,'','waste_records',15,NULL,'{\"waste_type\":\"egg\",\"grade_id\":4,\"quantity\":5,\"unit\":\"pcs\",\"reason\":\"broken\",\"remarks\":\"E2E Broken egg test\",\"recorded_by\":1}',NULL,'2026-09-04 08:24:07'),(314,1,'','waste_records',16,NULL,'{\"waste_type\":\"supply\",\"inventory_id\":1,\"quantity\":2,\"unit\":\"units\",\"reason\":\"expired\",\"estimated_cost\":350,\"remarks\":\"E2E Supply Waste Test\",\"recorded_by\":1}',NULL,'2026-09-04 08:24:07'),(315,1,'create','inventory',15,NULL,'{\"item_name\":\"Agricultural Limestone 1788510247\",\"category\":\"limestone\",\"quantity\":50,\"unit\":\"bags\",\"unit_cost\":220,\"lot_number\":\"LOT-TEST-1788510247\",\"supplier\":\"Calcite Minerals Inc.\",\"received_date\":\"2026-09-04\",\"expiry_date\":\"2026-09-19\"}',NULL,'2026-09-04 08:24:07'),(316,2,'','reservations',2,'{\"status\":\"active\"}','{\"status\":\"fulfilled\"}','::1','2026-09-04 13:10:32'),(317,1,'','orders',111,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 13:28:09'),(318,1,'','orders',111,'{\"status\":\"confirmed\"}','{\"status\":\"processing\"}',NULL,'2026-09-04 13:28:09'),(319,1,'','orders',111,'{\"status\":\"processing\"}','{\"status\":\"delivered\"}',NULL,'2026-09-04 13:28:09'),(320,1,'','orders',112,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 13:28:09'),(321,1,'','orders',112,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"E2E Test Cancellation\"}',NULL,'2026-09-04 13:28:09'),(322,1,'','orders',113,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 13:28:09'),(323,1,'','orders',113,'{\"status\":\"confirmed\"}','{\"status\":\"delivered\"}',NULL,'2026-09-04 13:28:09'),(324,1,'','orders',113,NULL,'{\"return_number\":\"RET-20260904-014\",\"reason\":\"E2E Return Test\",\"items_count\":2}',NULL,'2026-09-04 13:28:09'),(325,1,'create','reservations',29,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-09\",\"items_count\":1}',NULL,'2026-09-04 13:28:09'),(326,1,'','reservations',29,'{\"status\":\"active\"}','{\"status\":\"cancelled\"}',NULL,'2026-09-04 13:28:09'),(327,1,'create','reservations',30,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-07\",\"items_count\":1}',NULL,'2026-09-04 13:28:09'),(328,1,'','reservations',30,NULL,'{\"converted_order_id\":114}',NULL,'2026-09-04 13:28:09'),(329,1,'','waste_records',17,NULL,'{\"waste_type\":\"egg\",\"grade_id\":4,\"quantity\":5,\"unit\":\"pcs\",\"reason\":\"broken\",\"remarks\":\"E2E Broken egg test\",\"recorded_by\":1}',NULL,'2026-09-04 13:28:09'),(330,1,'','waste_records',18,NULL,'{\"waste_type\":\"supply\",\"inventory_id\":1,\"quantity\":2,\"unit\":\"units\",\"reason\":\"expired\",\"estimated_cost\":350,\"remarks\":\"E2E Supply Waste Test\",\"recorded_by\":1}',NULL,'2026-09-04 13:28:09'),(331,1,'create','inventory',16,NULL,'{\"item_name\":\"Agricultural Limestone 1788528489\",\"category\":\"limestone\",\"quantity\":50,\"unit\":\"bags\",\"unit_cost\":220,\"lot_number\":\"LOT-TEST-1788528489\",\"supplier\":\"Calcite Minerals Inc.\",\"received_date\":\"2026-09-04\",\"expiry_date\":\"2026-09-19\"}',NULL,'2026-09-04 13:28:10'),(334,1,'create','health_records',6,NULL,'{\"batch_id\":4,\"medicine_name\":\"Debug Vaccine\",\"quantity_used\":2,\"type\":\"vaccination\",\"date\":\"2026-09-04\",\"notes\":\"Test notes\"}',NULL,'2026-09-04 13:46:05'),(339,1,'','orders',115,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 13:47:01'),(340,1,'','orders',115,'{\"status\":\"confirmed\"}','{\"status\":\"processing\"}',NULL,'2026-09-04 13:47:01'),(341,1,'','orders',115,'{\"status\":\"processing\"}','{\"status\":\"delivered\"}',NULL,'2026-09-04 13:47:01'),(342,1,'','orders',116,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 13:47:01'),(343,1,'','orders',116,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"E2E Test Cancellation\"}',NULL,'2026-09-04 13:47:01'),(344,1,'','orders',117,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-04 13:47:01'),(345,1,'','orders',117,'{\"status\":\"confirmed\"}','{\"status\":\"delivered\"}',NULL,'2026-09-04 13:47:01'),(346,1,'','orders',117,NULL,'{\"return_number\":\"RET-20260904-015\",\"reason\":\"E2E Return Test\",\"items_count\":2}',NULL,'2026-09-04 13:47:01'),(347,1,'create','reservations',31,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-09\",\"items_count\":1}',NULL,'2026-09-04 13:47:01'),(348,1,'','reservations',31,'{\"status\":\"active\"}','{\"status\":\"cancelled\"}',NULL,'2026-09-04 13:47:01'),(349,1,'create','reservations',32,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-07\",\"items_count\":1}',NULL,'2026-09-04 13:47:01'),(350,1,'','reservations',32,NULL,'{\"converted_order_id\":118}',NULL,'2026-09-04 13:47:01'),(351,1,'','waste_records',19,NULL,'{\"waste_type\":\"egg\",\"grade_id\":4,\"quantity\":5,\"unit\":\"pcs\",\"reason\":\"broken\",\"remarks\":\"E2E Broken egg test\",\"recorded_by\":1}',NULL,'2026-09-04 13:47:01'),(352,1,'','waste_records',20,NULL,'{\"waste_type\":\"supply\",\"inventory_id\":1,\"quantity\":2,\"unit\":\"units\",\"reason\":\"expired\",\"estimated_cost\":350,\"remarks\":\"E2E Supply Waste Test\",\"recorded_by\":1}',NULL,'2026-09-04 13:47:01'),(353,1,'create','inventory',17,NULL,'{\"item_name\":\"Agricultural Limestone 1788529621\",\"category\":\"limestone\",\"quantity\":50,\"unit\":\"bags\",\"unit_cost\":220,\"lot_number\":\"LOT-TEST-1788529621\",\"supplier\":\"Calcite Minerals Inc.\",\"received_date\":\"2026-09-04\",\"expiry_date\":\"2026-09-19\"}',NULL,'2026-09-04 13:47:01'),(354,2,'create','health_records',11,NULL,'{\"batch_id\":4,\"medicine_name\":\"vitamin\",\"quantity_used\":3,\"type\":\"vaccination\",\"date\":\"2026-09-04\",\"notes\":\"for healthy\"}','::1','2026-09-04 13:50:21'),(357,1,'','orders',119,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-05 05:49:58'),(358,1,'','orders',119,'{\"status\":\"confirmed\"}','{\"status\":\"processing\"}',NULL,'2026-09-05 05:49:58'),(359,1,'','orders',119,'{\"status\":\"processing\"}','{\"status\":\"delivered\"}',NULL,'2026-09-05 05:49:58'),(360,1,'','orders',120,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-05 05:49:58'),(361,1,'','orders',120,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"E2E Test Cancellation\"}',NULL,'2026-09-05 05:49:58'),(362,1,'','orders',121,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-05 05:49:58'),(363,1,'','orders',121,'{\"status\":\"confirmed\"}','{\"status\":\"delivered\"}',NULL,'2026-09-05 05:49:58'),(364,1,'','orders',121,NULL,'{\"return_number\":\"RET-20260905-001\",\"reason\":\"E2E Return Test\",\"items_count\":2}',NULL,'2026-09-05 05:49:58'),(365,1,'create','reservations',33,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-10\",\"items_count\":1}',NULL,'2026-09-05 05:49:58'),(366,1,'','reservations',33,'{\"status\":\"active\"}','{\"status\":\"cancelled\"}',NULL,'2026-09-05 05:49:58'),(367,1,'create','reservations',34,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-08\",\"items_count\":1}',NULL,'2026-09-05 05:49:58'),(368,1,'','reservations',34,NULL,'{\"converted_order_id\":122}',NULL,'2026-09-05 05:49:58'),(369,1,'','waste_records',21,NULL,'{\"waste_type\":\"egg\",\"grade_id\":4,\"quantity\":5,\"unit\":\"pcs\",\"reason\":\"broken\",\"remarks\":\"E2E Broken egg test\",\"recorded_by\":1}',NULL,'2026-09-05 05:49:59'),(370,1,'','waste_records',22,NULL,'{\"waste_type\":\"supply\",\"inventory_id\":1,\"quantity\":2,\"unit\":\"units\",\"reason\":\"expired\",\"estimated_cost\":350,\"remarks\":\"E2E Supply Waste Test\",\"recorded_by\":1}',NULL,'2026-09-05 05:49:59'),(371,1,'create','inventory',18,NULL,'{\"item_name\":\"Agricultural Limestone 1788587399\",\"category\":\"limestone\",\"quantity\":50,\"unit\":\"bags\",\"unit_cost\":220,\"lot_number\":\"LOT-TEST-1788587399\",\"supplier\":\"Calcite Minerals Inc.\",\"received_date\":\"2026-09-05\",\"expiry_date\":\"2026-09-20\"}',NULL,'2026-09-05 05:49:59'),(372,1,'','orders',122,'{\"status\":\"confirmed\"}','{\"status\":\"processing\"}','::1','2026-09-05 05:56:37'),(373,1,'','orders',122,'{\"status\":\"processing\"}','{\"status\":\"cancelled\",\"reason\":\"wala kwarta\"}','::1','2026-09-05 05:57:00'),(374,2,'','orders',123,NULL,'{\"customer_id\":1,\"total_amount\":960,\"status\":\"confirmed\",\"items_count\":4}','192.168.100.68','2026-09-05 10:05:38'),(375,1,'','orders',124,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-13 08:08:35'),(376,1,'','orders',124,'{\"status\":\"confirmed\"}','{\"status\":\"processing\"}',NULL,'2026-09-13 08:08:35'),(377,1,'','orders',124,'{\"status\":\"processing\"}','{\"status\":\"delivered\"}',NULL,'2026-09-13 08:08:35'),(378,1,'','orders',125,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-13 08:08:35'),(379,1,'','orders',125,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"E2E Test Cancellation\"}',NULL,'2026-09-13 08:08:35'),(380,1,'','orders',126,NULL,'{\"customer_id\":1,\"total_amount\":285,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-13 08:08:35'),(381,1,'','orders',126,'{\"status\":\"confirmed\"}','{\"status\":\"delivered\"}',NULL,'2026-09-13 08:08:35'),(382,1,'','orders',126,NULL,'{\"return_number\":\"RET-20260913-001\",\"reason\":\"E2E Return Test\",\"items_count\":2}',NULL,'2026-09-13 08:08:35'),(383,1,'create','reservations',35,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-18\",\"items_count\":1}',NULL,'2026-09-13 08:08:35'),(384,1,'','reservations',35,'{\"status\":\"active\"}','{\"status\":\"cancelled\"}',NULL,'2026-09-13 08:08:35'),(385,1,'create','reservations',36,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-16\",\"items_count\":1}',NULL,'2026-09-13 08:08:35'),(386,1,'','reservations',36,NULL,'{\"converted_order_id\":127}',NULL,'2026-09-13 08:08:35'),(387,1,'','waste_records',23,NULL,'{\"waste_type\":\"egg\",\"grade_id\":4,\"quantity\":5,\"unit\":\"pcs\",\"reason\":\"broken\",\"remarks\":\"E2E Broken egg test\",\"recorded_by\":1}',NULL,'2026-09-13 08:08:35'),(388,1,'','waste_records',24,NULL,'{\"waste_type\":\"supply\",\"inventory_id\":1,\"quantity\":2,\"unit\":\"units\",\"reason\":\"expired\",\"estimated_cost\":350,\"remarks\":\"E2E Supply Waste Test\",\"recorded_by\":1}',NULL,'2026-09-13 08:08:35'),(389,1,'create','inventory',19,NULL,'{\"item_name\":\"Agricultural Limestone 1789286915\",\"category\":\"limestone\",\"quantity\":50,\"unit\":\"bags\",\"unit_cost\":220,\"lot_number\":\"LOT-TEST-1789286915\",\"supplier\":\"Calcite Minerals Inc.\",\"received_date\":\"2026-09-13\",\"expiry_date\":\"2026-09-28\"}',NULL,'2026-09-13 08:08:35'),(392,1,'login','users',1,NULL,NULL,NULL,'2026-09-13 08:08:36'),(393,2,'login','users',2,NULL,NULL,NULL,'2026-09-13 08:08:36'),(394,1,'','orders',128,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-13 08:08:36'),(395,1,'','orders',129,NULL,'{\"customer_id\":1,\"total_amount\":420,\"status\":\"confirmed\",\"items_count\":2}',NULL,'2026-09-13 08:08:36'),(396,1,'','orders',130,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-13 08:08:36'),(397,1,'','orders',130,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"Test cancellation\"}',NULL,'2026-09-13 08:08:36'),(398,1,'','orders',131,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-13 08:08:36'),(399,1,'','orders',131,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"First cancel\"}',NULL,'2026-09-13 08:08:36'),(400,1,'','orders',132,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-13 08:08:36'),(401,1,'','orders',132,NULL,'{\"return_number\":\"RET-20260913-002\",\"reason\":\"Customer returned undamaged eggs\",\"items_count\":1}',NULL,'2026-09-13 08:08:36'),(402,1,'','orders',133,NULL,'{\"customer_id\":1,\"total_amount\":195,\"status\":\"confirmed\",\"items_count\":1}',NULL,'2026-09-13 08:08:36'),(403,1,'create','reservations',37,NULL,'{\"customer_id\":1,\"pickup_date\":\"2026-09-16\",\"items_count\":1}',NULL,'2026-09-13 08:08:36'),(404,1,'','reservations',37,'{\"status\":\"active\"}','{\"status\":\"cancelled\"}',NULL,'2026-09-13 08:08:36'),(439,1,'create','reservations',47,NULL,'{\"type\":\"broiler\",\"customer_id\":1,\"batch_id\":5,\"quantity_birds\":10,\"total_amount\":0,\"advance_payment\":0,\"pickup_date\":\"2026-09-16\"}',NULL,'2026-09-13 12:10:47'),(440,1,'','reservations',47,NULL,'{\"converted_order_id\":151}',NULL,'2026-09-13 12:10:47'),(443,1,'create','reservations',48,NULL,'{\"type\":\"broiler\",\"customer_id\":1,\"batch_id\":5,\"quantity_birds\":10,\"total_amount\":0,\"advance_payment\":0,\"pickup_date\":\"2026-09-16\"}',NULL,'2026-09-13 12:13:31'),(444,1,'','reservations',48,NULL,'{\"converted_order_id\":153}',NULL,'2026-09-13 12:13:31'),(471,1,'create','reservations',59,NULL,'{\"type\":\"broiler\",\"customer_id\":1,\"batch_id\":5,\"quantity_birds\":10,\"total_amount\":0,\"advance_payment\":0,\"pickup_date\":\"2026-09-16\"}',NULL,'2026-09-13 12:23:28'),(472,1,'','reservations',59,NULL,'{\"converted_order_id\":170}',NULL,'2026-09-13 12:23:28'),(473,1,'','orders',171,NULL,'{\"type\":\"broiler\",\"customer_id\":1,\"batch_id\":5,\"quantity_birds\":5,\"weight_kg\":null,\"total_amount\":0,\"advance_payment\":0,\"status\":\"confirmed\"}',NULL,'2026-09-13 12:23:28'),(474,1,'','orders',171,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"Blocker-check cancellation test\"}',NULL,'2026-09-13 12:23:28'),(475,1,'','orders',172,NULL,'{\"type\":\"egg\",\"customer_id\":1,\"total_amount\":1065,\"status\":\"confirmed\",\"items_count\":2}',NULL,'2026-09-13 12:23:28'),(476,1,'create','reservations',60,NULL,'{\"type\":\"egg\",\"customer_id\":1,\"pickup_date\":\"2026-09-15\",\"items_count\":1}',NULL,'2026-09-13 12:23:28'),(477,1,'create','reservations',61,NULL,'{\"type\":\"broiler\",\"customer_id\":1,\"batch_id\":5,\"quantity_birds\":10,\"total_amount\":0,\"advance_payment\":0,\"pickup_date\":\"2026-09-16\"}',NULL,'2026-09-13 12:30:24'),(478,1,'','reservations',61,NULL,'{\"converted_order_id\":173}',NULL,'2026-09-13 12:30:24'),(479,1,'','orders',174,NULL,'{\"type\":\"broiler\",\"customer_id\":1,\"batch_id\":5,\"quantity_birds\":5,\"weight_kg\":null,\"total_amount\":0,\"advance_payment\":0,\"status\":\"confirmed\"}',NULL,'2026-09-13 12:30:24'),(480,1,'','orders',174,'{\"status\":\"confirmed\"}','{\"status\":\"cancelled\",\"reason\":\"Blocker-check cancellation test\"}',NULL,'2026-09-13 12:30:24'),(481,1,'','orders',175,NULL,'{\"type\":\"egg\",\"customer_id\":1,\"total_amount\":1065,\"status\":\"confirmed\",\"items_count\":2}',NULL,'2026-09-13 12:30:24'),(482,1,'create','reservations',62,NULL,'{\"type\":\"egg\",\"customer_id\":1,\"pickup_date\":\"2026-09-15\",\"items_count\":1}',NULL,'2026-09-13 12:30:24');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `batches`
--

DROP TABLE IF EXISTS `batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `batches` (
  `batch_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_code` varchar(50) DEFAULT NULL,
  `arrival_date` date DEFAULT NULL,
  `initial_quantity` int(11) DEFAULT NULL,
  `current_quantity` int(11) DEFAULT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `status` enum('active','harvested','closed') DEFAULT 'active',
  `flock_type` enum('broiler','layer') NOT NULL DEFAULT 'broiler',
  `breed` varchar(100) DEFAULT NULL,
  `house_pen` varchar(100) DEFAULT NULL,
  `expected_harvest_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`batch_id`),
  UNIQUE KEY `batch_code` (`batch_code`),
  KEY `idx_batches_status_type` (`status`,`flock_type`),
  KEY `idx_batches_flock_type` (`flock_type`),
  KEY `fk_batches_created_by` (`created_by`),
  CONSTRAINT `fk_batches_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `batches`
--

LOCK TABLES `batches` WRITE;
/*!40000 ALTER TABLE `batches` DISABLE KEYS */;
INSERT INTO `batches` VALUES (4,'BATCH 1-NEW','2026-08-18',446,329,'TAMPAKAN','active','layer','GIRASA','PEN1','2026-09-15',NULL,1,'2026-08-18 03:14:50','2026-09-14 06:32:08'),(5,'B2026-05','2026-09-05',100,50,'Gerald','active','broiler','Netive','Pen3','2026-10-05',NULL,1,'2026-09-05 10:06:04','2026-09-13 06:11:44');
/*!40000 ALTER TABLE `batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cleaning_records`
--

DROP TABLE IF EXISTS `cleaning_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cleaning_records` (
  `cleaning_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `remarks` text DEFAULT NULL,
  PRIMARY KEY (`cleaning_id`),
  KEY `batch_id` (`batch_id`),
  CONSTRAINT `cleaning_records_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cleaning_records`
--

LOCK TABLES `cleaning_records` WRITE;
/*!40000 ALTER TABLE `cleaning_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `cleaning_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_code` varchar(30) NOT NULL,
  `name` varchar(150) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `customer_type` enum('retail','wholesale','institutional') NOT NULL DEFAULT 'retail',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `uk_customers_code` (`customer_code`),
  KEY `idx_customers_name` (`name`),
  KEY `idx_customers_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `customers`
--

LOCK TABLES `customers` WRITE;
/*!40000 ALTER TABLE `customers` DISABLE KEYS */;
INSERT INTO `customers` VALUES (1,'SHANE-143','Aj shane Campilan','Aj shane Campilan','09088184444','campilanajshane@gmail.com','MALIGO','retail',1,'2026-08-18 03:16:14',NULL),(2,'MISS-144','MISSAAA','Aj shane Campilan','09088184444','campilanajshane@gmail.com','TAMPAKS','wholesale',1,'2026-08-18 04:32:06',NULL);
/*!40000 ALTER TABLE `customers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `distribution_items`
--

DROP TABLE IF EXISTS `distribution_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `distribution_items` (
  `distribution_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `distribution_id` int(11) NOT NULL,
  `order_item_id` int(11) DEFAULT NULL,
  `grade_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  PRIMARY KEY (`distribution_item_id`),
  KEY `idx_distribution_items_distribution` (`distribution_id`),
  KEY `idx_distribution_items_order_item` (`order_item_id`),
  KEY `idx_distribution_items_grade` (`grade_id`),
  CONSTRAINT `fk_distribution_items_distribution` FOREIGN KEY (`distribution_id`) REFERENCES `distributions` (`distribution_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_distribution_items_grade` FOREIGN KEY (`grade_id`) REFERENCES `egg_grades` (`grade_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_distribution_items_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`order_item_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `distribution_items`
--

LOCK TABLES `distribution_items` WRITE;
/*!40000 ALTER TABLE `distribution_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `distribution_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `distributions`
--

DROP TABLE IF EXISTS `distributions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `distributions` (
  `distribution_id` int(11) NOT NULL AUTO_INCREMENT,
  `distribution_number` varchar(30) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `distribution_date` date NOT NULL,
  `status` enum('scheduled','in_transit','delivered','cancelled') NOT NULL DEFAULT 'scheduled',
  `driver_name` varchar(100) DEFAULT NULL,
  `vehicle` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `delivered_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`distribution_id`),
  UNIQUE KEY `uk_distributions_number` (`distribution_number`),
  KEY `idx_distributions_date_status` (`distribution_date`,`status`),
  KEY `idx_distributions_customer` (`customer_id`),
  KEY `idx_distributions_order` (`order_id`),
  KEY `fk_distributions_delivered_by` (`delivered_by`),
  CONSTRAINT `fk_distributions_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_distributions_delivered_by` FOREIGN KEY (`delivered_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_distributions_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `distributions`
--

LOCK TABLES `distributions` WRITE;
/*!40000 ALTER TABLE `distributions` DISABLE KEYS */;
/*!40000 ALTER TABLE `distributions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `egg_grades`
--

DROP TABLE IF EXISTS `egg_grades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `egg_grades` (
  `grade_id` int(11) NOT NULL AUTO_INCREMENT,
  `grade_code` varchar(20) NOT NULL,
  `grade_name` varchar(50) NOT NULL,
  `price_per_piece` decimal(10,2) NOT NULL DEFAULT 0.00,
  `pieces_per_tray` int(11) NOT NULL DEFAULT 30,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`grade_id`),
  UNIQUE KEY `uk_egg_grades_code` (`grade_code`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `egg_grades`
--

LOCK TABLES `egg_grades` WRITE;
/*!40000 ALTER TABLE `egg_grades` DISABLE KEYS */;
INSERT INTO `egg_grades` VALUES (1,'A','Grade A',8.00,30,1,0),(2,'B','Grade B',7.00,30,2,0),(3,'C','Grade C',6.00,30,3,0),(4,'XL','Extra Large',9.50,30,4,1),(5,'L','Large',8.50,30,3,1),(6,'M','Medium',7.50,30,2,1),(7,'S','Small',6.50,30,1,1);
/*!40000 ALTER TABLE `egg_grades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `egg_inventory_movements`
--

DROP TABLE IF EXISTS `egg_inventory_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `egg_inventory_movements` (
  `movement_id` int(11) NOT NULL AUTO_INCREMENT,
  `movement_date` datetime NOT NULL DEFAULT current_timestamp(),
  `grade_id` int(11) NOT NULL,
  `movement_type` enum('production_in','order_alloc','order_out','order_return','order_cancellation','reservation_hold','reservation_release','distribution_out','waste_out','adjustment_in','adjustment_out') NOT NULL,
  `quantity` int(11) NOT NULL,
  `reference_type` enum('production','order','reservation','distribution','waste','adjustment') DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  PRIMARY KEY (`movement_id`),
  KEY `idx_eim_grade_date` (`grade_id`,`movement_date`),
  KEY `idx_eim_reference` (`reference_type`,`reference_id`),
  KEY `idx_eim_batch` (`batch_id`),
  KEY `idx_eim_type` (`movement_type`),
  KEY `fk_eim_recorded_by` (`recorded_by`),
  CONSTRAINT `fk_eim_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_eim_grade` FOREIGN KEY (`grade_id`) REFERENCES `egg_grades` (`grade_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_eim_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=90 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `egg_inventory_movements`
--

LOCK TABLES `egg_inventory_movements` WRITE;
/*!40000 ALTER TABLE `egg_inventory_movements` DISABLE KEYS */;
INSERT INTO `egg_inventory_movements` VALUES (1,'2026-08-18 11:15:29',5,'production_in',350,'production',1,4,1,'Prod: SHANE'),(2,'2026-08-18 11:16:38',5,'order_alloc',200,'order',1,NULL,1,'Order ORD-20260818-001'),(3,'2026-08-18 12:26:04',4,'production_in',950,'production',2,4,1,'Prod: shanes'),(4,'2026-08-18 12:26:51',5,'adjustment_out',350,'production',1,4,1,'Production record cancelled'),(5,'2026-08-18 12:27:12',5,'production_in',1000,'production',3,4,1,'Prod: shaennes'),(6,'2026-08-18 12:27:36',6,'production_in',1000,'production',4,4,1,'Prod: shanesss'),(7,'2026-08-18 12:27:57',7,'production_in',1900,'production',5,4,1,'Prod: shaaaaanes'),(8,'2026-08-18 12:48:42',7,'distribution_out',30,'distribution',8,NULL,1,'Test delivery'),(9,'2026-08-18 12:50:14',7,'distribution_out',30,'distribution',15,NULL,1,'Test delivery'),(10,'2026-08-18 12:50:14',7,'order_return',30,'order',15,NULL,1,'Return #RET-20260818-001 (S): Customer returned undamaged eggs'),(11,'2026-08-18 12:50:36',7,'distribution_out',30,'distribution',21,NULL,1,'Test delivery'),(12,'2026-08-18 12:50:36',7,'order_return',30,'order',21,NULL,1,'Return #RET-20260818-002 (S): Customer returned undamaged eggs'),(13,'2026-08-18 13:05:00',7,'distribution_out',30,'distribution',28,NULL,1,'Test delivery'),(14,'2026-08-18 13:05:01',7,'order_return',30,'order',28,NULL,1,'Return #RET-20260818-003 (S): Customer returned undamaged eggs'),(15,'2026-08-18 13:16:12',7,'distribution_out',30,'distribution',34,NULL,1,'Test delivery'),(16,'2026-08-18 13:16:12',7,'order_return',30,'order',34,NULL,1,'Return #RET-20260818-004 (S): Customer returned undamaged eggs'),(17,'2026-08-18 13:20:05',7,'distribution_out',30,'distribution',41,NULL,1,'Test delivery'),(18,'2026-08-18 13:20:05',7,'order_return',30,'order',41,NULL,1,'Return #RET-20260818-005 (S): Customer returned undamaged eggs'),(19,'2026-09-04 13:03:28',7,'distribution_out',30,'distribution',48,NULL,1,'Test delivery'),(20,'2026-09-04 13:03:28',7,'order_return',30,'order',48,NULL,1,'Return #RET-20260904-001 (S): Customer returned undamaged eggs'),(21,'2026-09-04 13:49:07',7,'distribution_out',30,'distribution',55,NULL,1,'Test delivery'),(22,'2026-09-04 13:49:07',7,'order_return',30,'order',55,NULL,1,'Return #RET-20260904-002 (S): Customer returned undamaged eggs'),(23,'2026-09-04 14:12:22',7,'distribution_out',30,'distribution',62,NULL,1,'Test delivery'),(24,'2026-09-04 14:12:22',7,'order_return',30,'order',62,NULL,1,'Return #RET-20260904-003 (S): Customer returned undamaged eggs'),(25,'2026-09-04 14:16:25',7,'distribution_out',30,'distribution',69,NULL,1,'Test delivery'),(26,'2026-09-04 14:16:25',7,'order_return',30,'order',69,NULL,1,'Return #RET-20260904-004 (S): Customer returned undamaged eggs'),(27,'2026-09-04 14:17:48',7,'distribution_out',30,'distribution',76,NULL,1,'Test delivery'),(28,'2026-09-04 14:17:48',7,'order_return',30,'order',76,NULL,1,'Return #RET-20260904-005 (S): Customer returned undamaged eggs'),(29,'2026-09-04 15:06:41',4,'order_return',15,'order',81,NULL,1,'Return #RET-20260904-006 (XL): E2E Return Test'),(30,'2026-09-04 15:06:41',4,'waste_out',15,'waste',81,NULL,1,'Damaged return on Order #ORD-20260904-039: E2E Return Test'),(31,'2026-09-04 15:06:41',4,'waste_out',5,'waste',1,NULL,1,'Waste: broken'),(32,'2026-09-04 15:07:00',4,'order_return',15,'order',85,NULL,1,'Return #RET-20260904-007 (XL): E2E Return Test'),(33,'2026-09-04 15:07:00',4,'waste_out',15,'waste',85,NULL,1,'Damaged return on Order #ORD-20260904-043: E2E Return Test'),(34,'2026-09-04 15:07:00',4,'waste_out',5,'waste',3,NULL,1,'Waste: broken'),(35,'2026-09-04 15:35:49',4,'order_return',15,'order',89,NULL,1,'Return #RET-20260904-008 (XL): E2E Return Test'),(36,'2026-09-04 15:35:49',4,'waste_out',15,'waste',89,NULL,1,'Damaged return on Order #ORD-20260904-047: E2E Return Test'),(37,'2026-09-04 15:35:49',4,'waste_out',5,'waste',5,NULL,1,'Waste: broken'),(38,'2026-09-04 15:53:35',4,'order_return',15,'order',93,NULL,1,'Return #RET-20260904-009 (XL): E2E Return Test'),(39,'2026-09-04 15:53:35',4,'waste_out',15,'waste',93,NULL,1,'Damaged return on Order #ORD-20260904-051: E2E Return Test'),(40,'2026-09-04 15:53:35',4,'waste_out',5,'waste',7,NULL,1,'Waste: broken'),(41,'2026-09-04 15:59:08',6,'production_in',9994,'production',6,4,1,NULL),(42,'2026-09-04 15:59:25',5,'production_in',9993,'production',7,4,1,NULL),(43,'2026-09-04 16:00:08',4,'production_in',5994,'production',8,4,1,NULL),(44,'2026-09-04 16:00:45',4,'order_return',15,'order',97,NULL,1,'Return #RET-20260904-010 (XL): E2E Return Test'),(45,'2026-09-04 16:00:45',4,'waste_out',15,'waste',97,NULL,1,'Damaged return on Order #ORD-20260904-055: E2E Return Test'),(46,'2026-09-04 16:00:45',4,'waste_out',5,'waste',9,NULL,1,'Waste: broken'),(47,'2026-09-04 16:17:25',4,'order_return',15,'order',101,NULL,1,'Return #RET-20260904-011 (XL): E2E Return Test'),(48,'2026-09-04 16:17:25',4,'waste_out',15,'waste',101,NULL,1,'Damaged return on Order #ORD-20260904-059: E2E Return Test'),(49,'2026-09-04 16:17:25',4,'waste_out',5,'waste',11,NULL,1,'Waste: broken'),(50,'2026-09-04 16:23:29',4,'order_return',15,'order',105,NULL,1,'Return #RET-20260904-012 (XL): E2E Return Test'),(51,'2026-09-04 16:23:29',4,'waste_out',15,'waste',105,NULL,1,'Damaged return on Order #ORD-20260904-063: E2E Return Test'),(52,'2026-09-04 16:23:29',4,'waste_out',5,'waste',13,NULL,1,'Waste: broken'),(53,'2026-09-04 16:24:07',4,'order_return',15,'order',109,NULL,1,'Return #RET-20260904-013 (XL): E2E Return Test'),(54,'2026-09-04 16:24:07',4,'waste_out',15,'waste',109,NULL,1,'Damaged return on Order #ORD-20260904-067: E2E Return Test'),(55,'2026-09-04 16:24:07',4,'waste_out',5,'waste',15,NULL,1,'Waste: broken'),(56,'2026-09-04 21:28:09',4,'order_return',15,'order',113,NULL,1,'Return #RET-20260904-014 (XL): E2E Return Test'),(57,'2026-09-04 21:28:09',4,'waste_out',15,'waste',113,NULL,1,'Damaged return on Order #ORD-20260904-071: E2E Return Test'),(58,'2026-09-04 21:28:09',4,'waste_out',5,'waste',17,NULL,1,'Waste: broken'),(59,'2026-09-04 21:47:01',4,'order_return',15,'order',117,NULL,1,'Return #RET-20260904-015 (XL): E2E Return Test'),(60,'2026-09-04 21:47:01',4,'waste_out',15,'waste',117,NULL,1,'Damaged return on Order #ORD-20260904-075: E2E Return Test'),(61,'2026-09-04 21:47:01',4,'waste_out',5,'waste',19,NULL,1,'Waste: broken'),(62,'2026-09-05 13:49:58',4,'order_return',15,'order',121,NULL,1,'Return #RET-20260905-001 (XL): E2E Return Test'),(63,'2026-09-05 13:49:58',4,'waste_out',15,'waste',121,NULL,1,'Damaged return on Order #ORD-20260905-003: E2E Return Test'),(64,'2026-09-05 13:49:59',4,'waste_out',5,'waste',21,NULL,1,'Waste: broken'),(65,'2026-09-13 16:08:35',4,'order_return',15,'order',126,NULL,1,'Return #RET-20260913-001 (XL): E2E Return Test'),(66,'2026-09-13 16:08:35',4,'waste_out',15,'waste',126,NULL,1,'Damaged return on Order #ORD-20260913-003: E2E Return Test'),(67,'2026-09-13 16:08:35',4,'waste_out',5,'waste',23,NULL,1,'Waste: broken'),(68,'2026-09-13 16:08:36',7,'distribution_out',30,'distribution',132,NULL,1,'Test delivery'),(69,'2026-09-13 16:08:36',7,'order_return',30,'order',132,NULL,1,'Return #RET-20260913-002 (S): Customer returned undamaged eggs');
/*!40000 ALTER TABLE `egg_inventory_movements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `egg_production`
--

DROP TABLE IF EXISTS `egg_production`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `egg_production` (
  `production_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) NOT NULL,
  `production_date` date NOT NULL,
  `grade_id` int(11) NOT NULL,
  `quantity_collected` int(11) NOT NULL DEFAULT 0,
  `quantity_broken` int(11) NOT NULL DEFAULT 0,
  `recorded_by` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `status` enum('active','cancelled') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`production_id`),
  UNIQUE KEY `uk_egg_production_batch_date_grade` (`batch_id`,`production_date`,`grade_id`),
  KEY `idx_egg_production_date` (`production_date`),
  KEY `idx_egg_production_grade` (`grade_id`),
  KEY `fk_egg_production_recorded_by` (`recorded_by`),
  CONSTRAINT `fk_egg_production_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_egg_production_grade` FOREIGN KEY (`grade_id`) REFERENCES `egg_grades` (`grade_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_egg_production_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `egg_production`
--

LOCK TABLES `egg_production` WRITE;
/*!40000 ALTER TABLE `egg_production` DISABLE KEYS */;
INSERT INTO `egg_production` VALUES (1,4,'2026-08-18',5,400,50,1,'SHANE','cancelled','2026-08-18 03:15:29'),(2,4,'2026-08-18',4,1000,50,1,'shanes','active','2026-08-18 04:26:04'),(3,4,'2026-08-19',5,1000,0,1,'shaennes','active','2026-08-18 04:27:12'),(4,4,'2026-08-20',6,1000,0,1,'shanesss','active','2026-08-18 04:27:36'),(5,4,'2026-08-21',7,2000,100,1,'shaaaaanes','active','2026-08-18 04:27:57'),(6,4,'2026-09-04',6,9999,5,1,NULL,'active','2026-09-04 07:59:08'),(7,4,'2026-09-04',5,10000,7,1,NULL,'active','2026-09-04 07:59:25'),(8,4,'2026-09-04',4,6000,6,1,NULL,'active','2026-09-04 08:00:08');
/*!40000 ALTER TABLE `egg_production` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `expense_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) DEFAULT NULL,
  `category` enum('chick','feed','medicine','vaccine','supply','limestone','waste','labor','utilities','operational','other') DEFAULT 'other',
  `amount` decimal(14,2) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `source_type` enum('manual','feed_consumption','health_record','inventory_receipt','waste','order','distribution') DEFAULT NULL,
  `source_id` int(11) DEFAULT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`expense_id`),
  KEY `batch_id` (`batch_id`),
  KEY `idx_expenses_date` (`date`),
  KEY `idx_expenses_source` (`source_type`,`source_id`),
  KEY `idx_expenses_batch_date` (`batch_id`,`date`),
  KEY `fk_expenses_inventory` (`inventory_id`),
  KEY `fk_expenses_recorded_by` (`recorded_by`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`),
  CONSTRAINT `fk_expenses_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_expenses_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
INSERT INTO `expenses` VALUES (1,NULL,'waste',350.00,'Waste: 2 units (expired)','2026-09-04','waste',NULL,NULL,1,'2026-09-04 07:06:41'),(2,NULL,'waste',350.00,'Waste: 2 units (expired)','2026-09-04','waste',NULL,NULL,1,'2026-09-04 07:07:00'),(3,NULL,'waste',350.00,'Waste: 2 units (expired)','2026-09-04','waste',NULL,NULL,1,'2026-09-04 07:35:49'),(4,NULL,'waste',350.00,'Waste: 2 units (expired)','2026-09-04','waste',NULL,NULL,1,'2026-09-04 07:53:35'),(5,NULL,'waste',350.00,'Waste: 2 units (expired)','2026-09-04','waste',NULL,NULL,1,'2026-09-04 08:00:45'),(6,NULL,'waste',350.00,'Waste: 2 units (expired)','2026-09-04','waste',NULL,NULL,1,'2026-09-04 08:17:25'),(7,NULL,'waste',350.00,'Waste: 2 units (expired)','2026-09-04','waste',NULL,NULL,1,'2026-09-04 08:23:29'),(8,NULL,'waste',350.00,'Waste: 2 units (expired)','2026-09-04','waste',NULL,NULL,1,'2026-09-04 08:24:07'),(9,NULL,'waste',350.00,'Waste: 2 units (expired)','2026-09-04','waste',NULL,NULL,1,'2026-09-04 13:28:09'),(10,NULL,'waste',350.00,'Waste: 2 units (expired)','2026-09-04','waste',NULL,NULL,1,'2026-09-04 13:47:01'),(11,NULL,'waste',350.00,'Waste: 2 units (expired)','2026-09-05','waste',NULL,NULL,1,'2026-09-05 05:49:59'),(12,NULL,'waste',350.00,'Waste: 2 units (expired)','2026-09-13','waste',NULL,NULL,1,'2026-09-13 08:08:35');
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `feed_consumption`
--

DROP TABLE IF EXISTS `feed_consumption`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feed_consumption` (
  `consumption_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) DEFAULT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `quantity_used` decimal(10,2) DEFAULT NULL,
  `date` date NOT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `unit_cost_snapshot` decimal(14,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`consumption_id`),
  KEY `batch_id` (`batch_id`),
  KEY `inventory_id` (`inventory_id`),
  KEY `idx_feed_consumption_batch_date` (`batch_id`,`date`),
  KEY `fk_feed_consumption_recorded_by` (`recorded_by`),
  CONSTRAINT `feed_consumption_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`),
  CONSTRAINT `feed_consumption_ibfk_2` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`),
  CONSTRAINT `fk_feed_consumption_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `feed_consumption`
--

LOCK TABLES `feed_consumption` WRITE;
/*!40000 ALTER TABLE `feed_consumption` DISABLE KEYS */;
INSERT INTO `feed_consumption` VALUES (4,4,8,3.00,'2026-09-04',NULL,NULL,NULL,'2026-09-04 07:52:43'),(5,4,8,5.00,'2026-09-04',NULL,NULL,NULL,'2026-09-04 07:53:33'),(6,4,8,5.00,'2026-09-04',NULL,NULL,NULL,'2026-09-04 08:00:45'),(7,4,8,5.00,'2026-09-04',NULL,NULL,NULL,'2026-09-04 08:17:23'),(8,4,8,5.00,'2026-09-04',NULL,NULL,NULL,'2026-09-04 08:23:26'),(9,4,8,5.00,'2026-09-04',NULL,NULL,NULL,'2026-09-04 08:24:07'),(10,4,1,2.00,'2026-09-04',NULL,NULL,NULL,'2026-09-04 13:09:58'),(11,4,8,5.00,'2026-09-04',NULL,NULL,NULL,'2026-09-04 13:28:09'),(12,4,8,5.00,'2026-09-04',NULL,NULL,NULL,'2026-09-04 13:47:01'),(13,4,8,5.00,'2026-09-05',NULL,NULL,NULL,'2026-09-05 05:49:58'),(14,5,8,5.00,'2026-09-13',NULL,NULL,NULL,'2026-09-13 08:08:36'),(15,5,1,400.00,'2026-09-13',NULL,NULL,NULL,'2026-09-13 08:09:27'),(16,5,8,5.00,'2026-09-13',NULL,NULL,NULL,'2026-09-13 08:23:13'),(17,5,8,5.00,'2026-09-13',NULL,NULL,NULL,'2026-09-13 09:02:07');
/*!40000 ALTER TABLE `feed_consumption` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `health_records`
--

DROP TABLE IF EXISTS `health_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `health_records` (
  `health_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) DEFAULT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `type` enum('vaccination','treatment') DEFAULT NULL,
  `medicine_name` varchar(100) DEFAULT NULL,
  `quantity_used` int(11) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `next_due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`health_id`),
  KEY `batch_id` (`batch_id`),
  KEY `idx_health_records_batch_date` (`batch_id`,`date`),
  KEY `idx_health_records_next_due` (`next_due_date`),
  KEY `idx_health_records_inventory` (`inventory_id`),
  KEY `fk_health_records_recorded_by` (`recorded_by`),
  CONSTRAINT `fk_health_records_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_health_records_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `health_records_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `health_records`
--

LOCK TABLES `health_records` WRITE;
/*!40000 ALTER TABLE `health_records` DISABLE KEYS */;
INSERT INTO `health_records` VALUES (6,4,NULL,'vaccination','Debug Vaccine',2,'2026-09-04','Test notes',1,NULL,'2026-09-04 13:46:05'),(11,4,NULL,'vaccination','vitamin',3,'2026-09-04','for healthy',2,NULL,'2026-09-04 13:50:21');
/*!40000 ALTER TABLE `health_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory`
--

DROP TABLE IF EXISTS `inventory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory` (
  `inventory_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_name` varchar(100) NOT NULL,
  `category` enum('feed','medicine','supply','limestone') NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `previous_quantity` decimal(10,2) DEFAULT 0.00,
  `reorder_level` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sku` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`inventory_id`),
  UNIQUE KEY `uk_inventory_item_category` (`item_name`,`category`),
  KEY `idx_inventory_category` (`category`),
  KEY `idx_inventory_active_category` (`is_active`,`category`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory`
--

LOCK TABLES `inventory` WRITE;
/*!40000 ALTER TABLE `inventory` DISABLE KEYS */;
INSERT INTO `inventory` VALUES (1,'BroilerFeeds','feed',-383.00,'Bags',5000.00,17.00,NULL,1,NULL,NULL,'2026-08-18 02:22:33','2026-09-13 08:09:27'),(2,'ChicksFeeds','feed',40.00,'Bags',2300.00,0.00,NULL,1,NULL,NULL,'2026-08-18 02:22:33',NULL),(3,'Vitamins','supply',2000.00,'Bottles',45000.00,0.00,NULL,1,NULL,NULL,'2026-08-18 02:22:33',NULL),(4,'Anti-plema','medicine',7600.00,'Bottles',3400.00,0.00,NULL,1,NULL,NULL,'2026-08-18 02:22:33',NULL),(8,'Agricultural Limestone 1788505601','limestone',-8.00,'bags',220.00,-3.00,NULL,1,NULL,NULL,'2026-09-04 07:06:41','2026-09-13 09:02:07'),(9,'Agricultural Limestone 1788505620','limestone',50.00,'bags',220.00,0.00,NULL,1,NULL,NULL,'2026-09-04 07:07:00',NULL),(10,'Agricultural Limestone 1788507349','limestone',50.00,'bags',220.00,0.00,NULL,1,NULL,NULL,'2026-09-04 07:35:49',NULL),(11,'Agricultural Limestone 1788508415','limestone',50.00,'bags',220.00,0.00,NULL,1,NULL,NULL,'2026-09-04 07:53:35',NULL),(12,'Agricultural Limestone 1788508845','limestone',50.00,'bags',220.00,0.00,NULL,1,NULL,NULL,'2026-09-04 08:00:45',NULL),(13,'Agricultural Limestone 1788509845','limestone',50.00,'bags',220.00,0.00,NULL,1,NULL,NULL,'2026-09-04 08:17:25',NULL),(14,'Agricultural Limestone 1788510209','limestone',50.00,'bags',220.00,0.00,NULL,1,NULL,NULL,'2026-09-04 08:23:29',NULL),(15,'Agricultural Limestone 1788510247','limestone',50.00,'bags',220.00,0.00,NULL,1,NULL,NULL,'2026-09-04 08:24:07',NULL),(16,'Agricultural Limestone 1788528489','limestone',50.00,'bags',220.00,0.00,NULL,1,NULL,NULL,'2026-09-04 13:28:09',NULL),(17,'Agricultural Limestone 1788529621','limestone',50.00,'bags',220.00,0.00,NULL,1,NULL,NULL,'2026-09-04 13:47:01',NULL),(18,'Agricultural Limestone 1788587399','limestone',50.00,'bags',220.00,0.00,NULL,1,NULL,NULL,'2026-09-05 05:49:59',NULL),(19,'Agricultural Limestone 1789286915','limestone',50.00,'bags',220.00,0.00,NULL,1,NULL,NULL,'2026-09-13 08:08:35',NULL);
/*!40000 ALTER TABLE `inventory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_lots`
--

DROP TABLE IF EXISTS `inventory_lots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_lots` (
  `lot_id` int(11) NOT NULL AUTO_INCREMENT,
  `inventory_id` int(11) NOT NULL,
  `lot_number` varchar(50) NOT NULL,
  `quantity_received` decimal(10,2) NOT NULL,
  `quantity_remaining` decimal(10,2) NOT NULL,
  `unit_cost` decimal(14,2) DEFAULT NULL,
  `received_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`lot_id`),
  UNIQUE KEY `uk_inventory_lots_inventory_lot` (`inventory_id`,`lot_number`),
  KEY `idx_inventory_lots_inventory` (`inventory_id`),
  KEY `idx_inventory_lots_expiry` (`expiry_date`,`quantity_remaining`),
  CONSTRAINT `fk_inventory_lots_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_lots`
--

LOCK TABLES `inventory_lots` WRITE;
/*!40000 ALTER TABLE `inventory_lots` DISABLE KEYS */;
INSERT INTO `inventory_lots` VALUES (1,1,'LEGACY-1',43.00,43.00,5000.00,'2026-08-18',NULL,NULL,'2026-08-18 02:22:34'),(2,2,'LEGACY-2',40.00,40.00,2300.00,'2026-08-18',NULL,NULL,'2026-08-18 02:22:34'),(3,3,'LEGACY-3',2000.00,2000.00,45000.00,'2026-08-18',NULL,NULL,'2026-08-18 02:22:34'),(4,4,'LEGACY-4',7600.00,7600.00,3400.00,'2026-08-18',NULL,NULL,'2026-08-18 02:22:34'),(5,8,'LOT-TEST-1788505601',50.00,50.00,220.00,'2026-09-04','2026-09-19','Calcite Minerals Inc.','2026-09-04 07:06:41'),(6,9,'LOT-TEST-1788505620',50.00,50.00,220.00,'2026-09-04','2026-09-19','Calcite Minerals Inc.','2026-09-04 07:07:00'),(7,10,'LOT-TEST-1788507349',50.00,50.00,220.00,'2026-09-04','2026-09-19','Calcite Minerals Inc.','2026-09-04 07:35:49'),(8,11,'LOT-TEST-1788508415',50.00,50.00,220.00,'2026-09-04','2026-09-19','Calcite Minerals Inc.','2026-09-04 07:53:35'),(9,12,'LOT-TEST-1788508845',50.00,50.00,220.00,'2026-09-04','2026-09-19','Calcite Minerals Inc.','2026-09-04 08:00:45'),(10,13,'LOT-TEST-1788509845',50.00,50.00,220.00,'2026-09-04','2026-09-19','Calcite Minerals Inc.','2026-09-04 08:17:25'),(11,14,'LOT-TEST-1788510209',50.00,50.00,220.00,'2026-09-04','2026-09-19','Calcite Minerals Inc.','2026-09-04 08:23:29'),(12,15,'LOT-TEST-1788510247',50.00,50.00,220.00,'2026-09-04','2026-09-19','Calcite Minerals Inc.','2026-09-04 08:24:07'),(13,16,'LOT-TEST-1788528489',50.00,50.00,220.00,'2026-09-04','2026-09-19','Calcite Minerals Inc.','2026-09-04 13:28:10'),(14,17,'LOT-TEST-1788529621',50.00,50.00,220.00,'2026-09-04','2026-09-19','Calcite Minerals Inc.','2026-09-04 13:47:01'),(15,18,'LOT-TEST-1788587399',50.00,50.00,220.00,'2026-09-05','2026-09-20','Calcite Minerals Inc.','2026-09-05 05:49:59'),(16,19,'LOT-TEST-1789286915',50.00,50.00,220.00,'2026-09-13','2026-09-28','Calcite Minerals Inc.','2026-09-13 08:08:35');
/*!40000 ALTER TABLE `inventory_lots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mortality_records`
--

DROP TABLE IF EXISTS `mortality_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mortality_records` (
  `mortality_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `quantity_change` int(11) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`mortality_id`),
  KEY `batch_id` (`batch_id`),
  KEY `idx_mortality_records_batch_date` (`batch_id`,`date`),
  KEY `fk_mortality_records_recorded_by` (`recorded_by`),
  CONSTRAINT `fk_mortality_records_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `mortality_records_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mortality_records`
--

LOCK TABLES `mortality_records` WRITE;
/*!40000 ALTER TABLE `mortality_records` DISABLE KEYS */;
INSERT INTO `mortality_records` VALUES (12,4,'2026-09-04',-5,'heat',NULL,'2026-09-04 13:48:16'),(13,4,'2026-09-05',-10,'heat',NULL,'2026-09-05 08:04:06'),(14,5,'2026-09-13',-50,'worms',NULL,'2026-09-13 06:11:44'),(15,4,'2026-09-13',-100,'worms',NULL,'2026-09-13 06:23:17'),(16,4,'2026-09-14',-2,'heat',NULL,'2026-09-14 06:32:08');
/*!40000 ALTER TABLE `mortality_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `message` varchar(255) DEFAULT NULL,
  `type` enum('low_stock','vaccination','general','expiry','mortality','order_due','reservation_due','low_egg_stock','demand_supply_gap') DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `severity` enum('info','warning','critical') DEFAULT 'info',
  `status` enum('unread','read') DEFAULT 'unread',
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`notification_id`),
  KEY `idx_notifications_user_status` (`user_id`,`status`),
  KEY `idx_notifications_entity` (`entity_type`,`entity_id`),
  CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `grade_id` int(11) DEFAULT NULL,
  `quantity_ordered` int(11) NOT NULL,
  `quantity_delivered` int(11) NOT NULL DEFAULT 0,
  `unit_price` decimal(14,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`order_item_id`),
  KEY `idx_order_items_order` (`order_id`),
  KEY `idx_order_items_grade` (`grade_id`),
  CONSTRAINT `fk_order_items_grade` FOREIGN KEY (`grade_id`) REFERENCES `egg_grades` (`grade_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=178 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
INSERT INTO `order_items` VALUES (1,1,5,200,0,0.00,0.00),(2,2,4,150,0,9.50,1425.00),(3,3,6,360,0,7.50,2700.00),(4,3,5,150,0,8.50,1275.00),(5,4,7,30,0,6.50,195.00),(6,5,7,30,0,6.50,195.00),(7,5,6,30,0,7.50,225.00),(8,6,7,30,0,6.50,195.00),(9,7,7,30,0,6.50,195.00),(10,8,7,30,30,6.50,195.00),(11,9,7,30,0,6.50,195.00),(12,10,7,30,0,6.50,195.00),(13,11,7,30,0,6.50,195.00),(14,12,7,30,0,6.50,195.00),(15,12,6,30,0,7.50,225.00),(16,13,7,30,0,6.50,195.00),(17,14,7,30,0,6.50,195.00),(18,15,7,30,0,6.50,195.00),(19,16,7,30,0,6.50,195.00),(20,17,7,30,0,6.50,195.00),(21,18,7,30,0,6.50,195.00),(22,18,6,30,0,7.50,225.00),(23,19,7,30,0,6.50,195.00),(24,20,7,30,0,6.50,195.00),(25,21,7,30,0,6.50,195.00),(26,22,7,30,0,6.50,195.00),(27,23,7,150,0,6.50,975.00),(28,23,6,150,0,7.50,1125.00),(29,23,5,150,0,8.50,1275.00),(30,23,4,150,0,9.50,1425.00),(31,24,7,30,0,6.50,195.00),(32,25,7,30,0,6.50,195.00),(33,25,6,30,0,7.50,225.00),(34,26,7,30,0,6.50,195.00),(35,27,7,30,0,6.50,195.00),(36,28,7,30,0,6.50,195.00),(37,29,7,30,0,6.50,195.00),(38,30,7,30,0,6.50,195.00),(39,31,7,30,0,6.50,195.00),(40,31,6,30,0,7.50,225.00),(41,32,7,30,0,6.50,195.00),(42,33,7,30,0,6.50,195.00),(43,34,7,30,0,6.50,195.00),(44,35,7,30,0,6.50,195.00),(45,36,7,30,0,9.50,285.00),(46,37,7,30,0,6.50,195.00),(47,38,7,30,0,6.50,195.00),(48,38,6,30,0,7.50,225.00),(49,39,7,30,0,6.50,195.00),(50,40,7,30,0,6.50,195.00),(51,41,7,30,0,6.50,195.00),(52,42,7,30,0,6.50,195.00),(53,43,7,30,0,9.50,285.00),(54,44,7,30,0,6.50,195.00),(55,45,7,30,0,6.50,195.00),(56,45,6,30,0,7.50,225.00),(57,46,7,30,0,6.50,195.00),(58,47,7,30,0,6.50,195.00),(59,48,7,30,0,6.50,195.00),(60,49,7,30,0,6.50,195.00),(61,50,7,30,0,9.50,285.00),(62,51,7,30,0,6.50,195.00),(63,52,7,30,0,6.50,195.00),(64,52,6,30,0,7.50,225.00),(65,53,7,30,0,6.50,195.00),(66,54,7,30,0,6.50,195.00),(67,55,7,30,0,6.50,195.00),(68,56,7,30,0,6.50,195.00),(69,57,7,30,0,9.50,285.00),(70,58,7,30,0,6.50,195.00),(71,59,7,30,0,6.50,195.00),(72,59,6,30,0,7.50,225.00),(73,60,7,30,0,6.50,195.00),(74,61,7,30,0,6.50,195.00),(75,62,7,30,0,6.50,195.00),(76,63,7,30,0,6.50,195.00),(77,64,7,30,0,9.50,285.00),(78,65,7,30,0,6.50,195.00),(79,66,7,30,0,6.50,195.00),(80,66,6,30,0,7.50,225.00),(81,67,7,30,0,6.50,195.00),(82,68,7,30,0,6.50,195.00),(83,69,7,30,0,6.50,195.00),(84,70,7,30,0,6.50,195.00),(85,71,7,30,0,9.50,285.00),(86,72,7,30,0,6.50,195.00),(87,73,7,30,0,6.50,195.00),(88,73,6,30,0,7.50,225.00),(89,74,7,30,0,6.50,195.00),(90,75,7,30,0,6.50,195.00),(91,76,7,30,0,6.50,195.00),(92,77,7,30,0,6.50,195.00),(93,78,7,120,0,6.50,780.00),(94,78,6,150,0,7.50,1125.00),(95,78,5,300,0,8.50,2550.00),(96,78,4,240,0,9.50,2280.00),(97,79,4,30,30,9.50,285.00),(98,80,4,30,0,9.50,285.00),(99,81,4,30,0,9.50,285.00),(100,82,4,30,0,9.50,285.00),(101,83,4,30,30,9.50,285.00),(102,84,4,30,0,9.50,285.00),(103,85,4,30,0,9.50,285.00),(104,86,4,30,0,9.50,285.00),(105,87,4,30,30,9.50,285.00),(106,88,4,30,0,9.50,285.00),(107,89,4,30,0,9.50,285.00),(108,90,4,30,0,9.50,285.00),(109,91,4,30,30,9.50,285.00),(110,92,4,30,0,9.50,285.00),(111,93,4,30,0,9.50,285.00),(112,94,4,30,0,9.50,285.00),(113,95,4,30,30,9.50,285.00),(114,96,4,30,0,9.50,285.00),(115,97,4,30,0,9.50,285.00),(116,98,4,30,0,9.50,285.00),(117,99,4,30,30,9.50,285.00),(118,100,4,30,0,9.50,285.00),(119,101,4,30,0,9.50,285.00),(120,102,4,30,0,9.50,285.00),(121,103,4,30,30,9.50,285.00),(122,104,4,30,0,9.50,285.00),(123,105,4,30,0,9.50,285.00),(124,106,4,30,0,9.50,285.00),(125,107,4,30,30,9.50,285.00),(126,108,4,30,0,9.50,285.00),(127,109,4,30,0,9.50,285.00),(128,110,4,30,0,9.50,285.00),(129,111,4,30,30,9.50,285.00),(130,112,4,30,0,9.50,285.00),(131,113,4,30,0,9.50,285.00),(132,114,4,30,0,9.50,285.00),(133,115,4,30,30,9.50,285.00),(134,116,4,30,0,9.50,285.00),(135,117,4,30,0,9.50,285.00),(136,118,4,30,0,9.50,285.00),(137,119,4,30,30,9.50,285.00),(138,120,4,30,0,9.50,285.00),(139,121,4,30,0,9.50,285.00),(140,122,4,30,0,9.50,285.00),(141,123,7,30,0,6.50,195.00),(142,123,6,30,0,7.50,225.00),(143,123,5,30,0,8.50,255.00),(144,123,4,30,0,9.50,285.00),(145,124,4,30,30,9.50,285.00),(146,125,4,30,0,9.50,285.00),(147,126,4,30,0,9.50,285.00),(148,127,4,30,0,9.50,285.00),(149,128,7,30,0,6.50,195.00),(150,129,7,30,0,6.50,195.00),(151,129,6,30,0,7.50,225.00),(152,130,7,30,0,6.50,195.00),(153,131,7,30,0,6.50,195.00),(154,132,7,30,0,6.50,195.00),(155,133,7,30,0,6.50,195.00);
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_return_items`
--

DROP TABLE IF EXISTS `order_return_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_return_items` (
  `return_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `return_id` int(11) NOT NULL,
  `order_item_id` int(11) DEFAULT NULL,
  `grade_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `condition_status` enum('good','damaged','expired') NOT NULL DEFAULT 'good',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`return_item_id`),
  KEY `return_id` (`return_id`),
  KEY `grade_id` (`grade_id`),
  CONSTRAINT `order_return_items_ibfk_1` FOREIGN KEY (`return_id`) REFERENCES `order_returns` (`return_id`) ON DELETE CASCADE,
  CONSTRAINT `order_return_items_ibfk_2` FOREIGN KEY (`grade_id`) REFERENCES `egg_grades` (`grade_id`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_return_items`
--

LOCK TABLES `order_return_items` WRITE;
/*!40000 ALTER TABLE `order_return_items` DISABLE KEYS */;
INSERT INTO `order_return_items` VALUES (1,2,18,7,30,'good','2026-08-18 04:50:14'),(2,4,25,7,30,'good','2026-08-18 04:50:36'),(3,6,36,7,30,'good','2026-08-18 05:05:01'),(4,8,43,7,30,'good','2026-08-18 05:16:12'),(5,10,51,7,30,'good','2026-08-18 05:20:05'),(6,12,59,7,30,'good','2026-09-04 05:03:28'),(7,14,67,7,30,'good','2026-09-04 05:49:07'),(8,16,75,7,30,'good','2026-09-04 06:12:22'),(9,18,83,7,30,'good','2026-09-04 06:16:25'),(10,20,91,7,30,'good','2026-09-04 06:17:48'),(11,22,99,4,15,'good','2026-09-04 07:06:41'),(12,22,99,4,15,'damaged','2026-09-04 07:06:41'),(13,23,103,4,15,'good','2026-09-04 07:07:00'),(14,23,103,4,15,'damaged','2026-09-04 07:07:00'),(15,24,107,4,15,'good','2026-09-04 07:35:48'),(16,24,107,4,15,'damaged','2026-09-04 07:35:49'),(17,25,111,4,15,'good','2026-09-04 07:53:35'),(18,25,111,4,15,'damaged','2026-09-04 07:53:35'),(19,26,115,4,15,'good','2026-09-04 08:00:45'),(20,26,115,4,15,'damaged','2026-09-04 08:00:45'),(21,27,119,4,15,'good','2026-09-04 08:17:25'),(22,27,119,4,15,'damaged','2026-09-04 08:17:25'),(23,28,123,4,15,'good','2026-09-04 08:23:29'),(24,28,123,4,15,'damaged','2026-09-04 08:23:29'),(25,29,127,4,15,'good','2026-09-04 08:24:07'),(26,29,127,4,15,'damaged','2026-09-04 08:24:07'),(27,30,131,4,15,'good','2026-09-04 13:28:09'),(28,30,131,4,15,'damaged','2026-09-04 13:28:09'),(29,31,135,4,15,'good','2026-09-04 13:47:01'),(30,31,135,4,15,'damaged','2026-09-04 13:47:01'),(31,32,139,4,15,'good','2026-09-05 05:49:58'),(32,32,139,4,15,'damaged','2026-09-05 05:49:58'),(33,33,147,4,15,'good','2026-09-13 08:08:35'),(34,33,147,4,15,'damaged','2026-09-13 08:08:35'),(35,34,154,7,30,'good','2026-09-13 08:08:36');
/*!40000 ALTER TABLE `order_return_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_returns`
--

DROP TABLE IF EXISTS `order_returns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_returns` (
  `return_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `return_number` varchar(30) NOT NULL,
  `return_date` datetime NOT NULL DEFAULT current_timestamp(),
  `reason` varchar(255) NOT NULL,
  `returned_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`return_id`),
  UNIQUE KEY `return_number` (`return_number`),
  KEY `order_id` (`order_id`),
  KEY `returned_by` (`returned_by`),
  CONSTRAINT `order_returns_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  CONSTRAINT `order_returns_ibfk_2` FOREIGN KEY (`returned_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_returns`
--

LOCK TABLES `order_returns` WRITE;
/*!40000 ALTER TABLE `order_returns` DISABLE KEYS */;
INSERT INTO `order_returns` VALUES (2,15,'RET-20260818-001','2026-08-18 12:50:14','Customer returned undamaged eggs',1,'Return for order #ORD-20260818-015','2026-08-18 04:50:14'),(4,21,'RET-20260818-002','2026-08-18 12:50:36','Customer returned undamaged eggs',1,'Return for order #ORD-20260818-021','2026-08-18 04:50:36'),(6,28,'RET-20260818-003','2026-08-18 13:05:01','Customer returned undamaged eggs',1,'Return for order #ORD-20260818-028','2026-08-18 05:05:01'),(8,34,'RET-20260818-004','2026-08-18 13:16:12','Customer returned undamaged eggs',1,'Return for order #ORD-20260818-034','2026-08-18 05:16:12'),(10,41,'RET-20260818-005','2026-08-18 13:20:05','Customer returned undamaged eggs',1,'Return for order #ORD-20260818-041','2026-08-18 05:20:05'),(12,48,'RET-20260904-001','2026-09-04 13:03:28','Customer returned undamaged eggs',1,'Return for order #ORD-20260904-006','2026-09-04 05:03:28'),(14,55,'RET-20260904-002','2026-09-04 13:49:07','Customer returned undamaged eggs',1,'Return for order #ORD-20260904-013','2026-09-04 05:49:07'),(16,62,'RET-20260904-003','2026-09-04 14:12:22','Customer returned undamaged eggs',1,'Return for order #ORD-20260904-020','2026-09-04 06:12:22'),(18,69,'RET-20260904-004','2026-09-04 14:16:25','Customer returned undamaged eggs',1,'Return for order #ORD-20260904-027','2026-09-04 06:16:25'),(20,76,'RET-20260904-005','2026-09-04 14:17:48','Customer returned undamaged eggs',1,'Return for order #ORD-20260904-034','2026-09-04 06:17:48'),(22,81,'RET-20260904-006','2026-09-04 15:06:41','E2E Return Test',1,'Return for order #ORD-20260904-039','2026-09-04 07:06:41'),(23,85,'RET-20260904-007','2026-09-04 15:07:00','E2E Return Test',1,'Return for order #ORD-20260904-043','2026-09-04 07:07:00'),(24,89,'RET-20260904-008','2026-09-04 15:35:48','E2E Return Test',1,'Return for order #ORD-20260904-047','2026-09-04 07:35:48'),(25,93,'RET-20260904-009','2026-09-04 15:53:35','E2E Return Test',1,'Return for order #ORD-20260904-051','2026-09-04 07:53:35'),(26,97,'RET-20260904-010','2026-09-04 16:00:45','E2E Return Test',1,'Return for order #ORD-20260904-055','2026-09-04 08:00:45'),(27,101,'RET-20260904-011','2026-09-04 16:17:25','E2E Return Test',1,'Return for order #ORD-20260904-059','2026-09-04 08:17:25'),(28,105,'RET-20260904-012','2026-09-04 16:23:29','E2E Return Test',1,'Return for order #ORD-20260904-063','2026-09-04 08:23:29'),(29,109,'RET-20260904-013','2026-09-04 16:24:07','E2E Return Test',1,'Return for order #ORD-20260904-067','2026-09-04 08:24:07'),(30,113,'RET-20260904-014','2026-09-04 21:28:09','E2E Return Test',1,'Return for order #ORD-20260904-071','2026-09-04 13:28:09'),(31,117,'RET-20260904-015','2026-09-04 21:47:01','E2E Return Test',1,'Return for order #ORD-20260904-075','2026-09-04 13:47:01'),(32,121,'RET-20260905-001','2026-09-05 13:49:58','E2E Return Test',1,'Return for order #ORD-20260905-003','2026-09-05 05:49:58'),(33,126,'RET-20260913-001','2026-09-13 16:08:35','E2E Return Test',1,'Return for order #ORD-20260913-003','2026-09-13 08:08:35'),(34,132,'RET-20260913-002','2026-09-13 16:08:36','Customer returned undamaged eggs',1,'Return for order #ORD-20260913-009','2026-09-13 08:08:36');
/*!40000 ALTER TABLE `order_returns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(30) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `quantity_birds` int(11) DEFAULT NULL,
  `weight_kg` decimal(10,2) DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `order_date` date NOT NULL,
  `required_date` date DEFAULT NULL,
  `status` enum('draft','pending','confirmed','processing','partially_delivered','delivered','cancelled','returned') NOT NULL DEFAULT 'pending',
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `advance_payment` decimal(14,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`order_id`),
  UNIQUE KEY `uk_orders_number` (`order_number`),
  KEY `idx_orders_customer_status` (`customer_id`,`status`),
  KEY `idx_orders_required_date` (`required_date`),
  KEY `idx_orders_order_date` (`order_date`),
  KEY `fk_orders_created_by` (`created_by`),
  KEY `fk_orders_batch` (`batch_id`),
  CONSTRAINT `fk_orders_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_orders_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=176 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,'ORD-20260818-001',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'pending',0.00,0.00,NULL,1,'2026-08-18 03:16:38',NULL),(2,'ORD-20260818-002',1,NULL,NULL,NULL,NULL,'2026-08-18','2026-08-19','pending',1425.00,0.00,'sharp 10am',1,'2026-08-18 04:29:53',NULL),(3,'ORD-20260818-003',2,NULL,NULL,NULL,NULL,'2026-08-18','2026-08-19','confirmed',3975.00,0.00,'9',1,'2026-08-18 04:39:34',NULL),(4,'ORD-20260818-004',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'confirmed',195.00,0.00,'Automated test order confirmation',1,'2026-08-18 04:48:41',NULL),(5,'ORD-20260818-005',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'confirmed',420.00,0.00,'',1,'2026-08-18 04:48:41',NULL),(6,'ORD-20260818-006',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'confirmed',195.00,0.00,'',1,'2026-08-18 04:48:41',NULL),(7,'ORD-20260818-007',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'cancelled',195.00,0.00,' [Cancelled: First cancel]',1,'2026-08-18 04:48:42','2026-08-18 04:48:42'),(8,'ORD-20260818-008',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'delivered',195.00,0.00,'',1,'2026-08-18 04:48:42','2026-08-18 04:48:42'),(9,'ORD-20260818-009',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'confirmed',195.00,0.00,'',1,'2026-08-18 04:48:42',NULL),(10,'ORD-20260818-010',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'confirmed',195.00,0.00,'',1,'2026-08-18 04:49:47',NULL),(11,'ORD-20260818-011',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'confirmed',195.00,0.00,'Automated test order confirmation',1,'2026-08-18 04:50:14',NULL),(12,'ORD-20260818-012',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'confirmed',420.00,0.00,'',1,'2026-08-18 04:50:14',NULL),(13,'ORD-20260818-013',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'confirmed',195.00,0.00,'',1,'2026-08-18 04:50:14',NULL),(14,'ORD-20260818-014',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'cancelled',195.00,0.00,' [Cancelled: First cancel]',1,'2026-08-18 04:50:14','2026-08-18 04:50:14'),(15,'ORD-20260818-015',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'returned',195.00,0.00,'',1,'2026-08-18 04:50:14','2026-08-18 04:50:14'),(16,'ORD-20260818-016',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'confirmed',195.00,0.00,'',1,'2026-08-18 04:50:14',NULL),(17,'ORD-20260818-017',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'confirmed',195.00,0.00,'Automated test order confirmation',1,'2026-08-18 04:50:36',NULL),(18,'ORD-20260818-018',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'confirmed',420.00,0.00,'',1,'2026-08-18 04:50:36',NULL),(19,'ORD-20260818-019',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'cancelled',195.00,0.00,' [Cancelled: Test cancellation]',1,'2026-08-18 04:50:36','2026-08-18 04:50:36'),(20,'ORD-20260818-020',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'cancelled',195.00,0.00,' [Cancelled: First cancel]',1,'2026-08-18 04:50:36','2026-08-18 04:50:36'),(21,'ORD-20260818-021',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'returned',195.00,0.00,'',1,'2026-08-18 04:50:36','2026-08-18 04:50:36'),(22,'ORD-20260818-022',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'confirmed',195.00,0.00,'',1,'2026-08-18 04:50:36',NULL),(23,'ORD-20260818-023',2,NULL,NULL,NULL,NULL,'2026-08-18','2026-08-19','confirmed',4800.00,0.00,'8',1,'2026-08-18 04:51:56',NULL),(24,'ORD-20260818-024',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'confirmed',195.00,0.00,'Automated test order confirmation',1,'2026-08-18 05:05:00',NULL),(25,'ORD-20260818-025',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'confirmed',420.00,0.00,'',1,'2026-08-18 05:05:00',NULL),(26,'ORD-20260818-026',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'cancelled',195.00,0.00,' [Cancelled: Test cancellation]',1,'2026-08-18 05:05:00','2026-08-18 05:05:00'),(27,'ORD-20260818-027',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'cancelled',195.00,0.00,' [Cancelled: First cancel]',1,'2026-08-18 05:05:00','2026-08-18 05:05:00'),(28,'ORD-20260818-028',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'returned',195.00,0.00,'',1,'2026-08-18 05:05:00','2026-08-18 05:05:01'),(29,'ORD-20260818-029',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'confirmed',195.00,0.00,'',1,'2026-08-18 05:05:01',NULL),(30,'ORD-20260818-030',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'confirmed',195.00,0.00,'Automated test order confirmation',1,'2026-08-18 05:16:12',NULL),(31,'ORD-20260818-031',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'confirmed',420.00,0.00,'',1,'2026-08-18 05:16:12',NULL),(32,'ORD-20260818-032',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'cancelled',195.00,0.00,' [Cancelled: Test cancellation]',1,'2026-08-18 05:16:12','2026-08-18 05:16:12'),(33,'ORD-20260818-033',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'cancelled',195.00,0.00,' [Cancelled: First cancel]',1,'2026-08-18 05:16:12','2026-08-18 05:16:12'),(34,'ORD-20260818-034',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'returned',195.00,0.00,'',1,'2026-08-18 05:16:12','2026-08-18 05:16:12'),(35,'ORD-20260818-035',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'confirmed',195.00,0.00,'',1,'2026-08-18 05:16:12',NULL),(36,'ORD-20260818-036',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'cancelled',285.00,0.00,' [Cancelled: Verification test cancellation]',1,'2026-08-18 05:19:58','2026-08-18 05:19:58'),(37,'ORD-20260818-037',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'confirmed',195.00,0.00,'Automated test order confirmation',1,'2026-08-18 05:20:04',NULL),(38,'ORD-20260818-038',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'confirmed',420.00,0.00,'',1,'2026-08-18 05:20:04',NULL),(39,'ORD-20260818-039',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'cancelled',195.00,0.00,' [Cancelled: Test cancellation]',1,'2026-08-18 05:20:04','2026-08-18 05:20:05'),(40,'ORD-20260818-040',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'cancelled',195.00,0.00,' [Cancelled: First cancel]',1,'2026-08-18 05:20:05','2026-08-18 05:20:05'),(41,'ORD-20260818-041',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'returned',195.00,0.00,'',1,'2026-08-18 05:20:05','2026-08-18 05:20:05'),(42,'ORD-20260818-042',1,NULL,NULL,NULL,NULL,'2026-08-18',NULL,'confirmed',195.00,0.00,'',1,'2026-08-18 05:20:05',NULL),(43,'ORD-20260904-001',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'cancelled',285.00,0.00,' [Cancelled: Verification test cancellation]',1,'2026-09-04 05:03:25','2026-09-04 05:03:26'),(44,'ORD-20260904-002',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'confirmed',195.00,0.00,'Automated test order confirmation',1,'2026-09-04 05:03:28',NULL),(45,'ORD-20260904-003',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'confirmed',420.00,0.00,'',1,'2026-09-04 05:03:28',NULL),(46,'ORD-20260904-004',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'cancelled',195.00,0.00,' [Cancelled: Test cancellation]',1,'2026-09-04 05:03:28','2026-09-04 05:03:28'),(47,'ORD-20260904-005',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'cancelled',195.00,0.00,' [Cancelled: First cancel]',1,'2026-09-04 05:03:28','2026-09-04 05:03:28'),(48,'ORD-20260904-006',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'returned',195.00,0.00,'',1,'2026-09-04 05:03:28','2026-09-04 05:03:28'),(49,'ORD-20260904-007',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'confirmed',195.00,0.00,'',1,'2026-09-04 05:03:29',NULL),(50,'ORD-20260904-008',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'cancelled',285.00,0.00,' [Cancelled: Verification test cancellation]',1,'2026-09-04 05:49:03','2026-09-04 05:49:03'),(51,'ORD-20260904-009',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'confirmed',195.00,0.00,'Automated test order confirmation',1,'2026-09-04 05:49:07',NULL),(52,'ORD-20260904-010',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'confirmed',420.00,0.00,'',1,'2026-09-04 05:49:07',NULL),(53,'ORD-20260904-011',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'cancelled',195.00,0.00,' [Cancelled: Test cancellation]',1,'2026-09-04 05:49:07','2026-09-04 05:49:07'),(54,'ORD-20260904-012',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'cancelled',195.00,0.00,' [Cancelled: First cancel]',1,'2026-09-04 05:49:07','2026-09-04 05:49:07'),(55,'ORD-20260904-013',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'returned',195.00,0.00,'',1,'2026-09-04 05:49:07','2026-09-04 05:49:07'),(56,'ORD-20260904-014',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'confirmed',195.00,0.00,'',1,'2026-09-04 05:49:07',NULL),(57,'ORD-20260904-015',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'cancelled',285.00,0.00,' [Cancelled: Verification test cancellation]',1,'2026-09-04 06:12:17','2026-09-04 06:12:17'),(58,'ORD-20260904-016',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'confirmed',195.00,0.00,'Automated test order confirmation',1,'2026-09-04 06:12:22',NULL),(59,'ORD-20260904-017',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'confirmed',420.00,0.00,'',1,'2026-09-04 06:12:22',NULL),(60,'ORD-20260904-018',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'cancelled',195.00,0.00,' [Cancelled: Test cancellation]',1,'2026-09-04 06:12:22','2026-09-04 06:12:22'),(61,'ORD-20260904-019',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'cancelled',195.00,0.00,' [Cancelled: First cancel]',1,'2026-09-04 06:12:22','2026-09-04 06:12:22'),(62,'ORD-20260904-020',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'returned',195.00,0.00,'',1,'2026-09-04 06:12:22','2026-09-04 06:12:22'),(63,'ORD-20260904-021',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'confirmed',195.00,0.00,'',1,'2026-09-04 06:12:22',NULL),(64,'ORD-20260904-022',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'cancelled',285.00,0.00,' [Cancelled: Verification test cancellation]',1,'2026-09-04 06:16:24','2026-09-04 06:16:24'),(65,'ORD-20260904-023',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'confirmed',195.00,0.00,'Automated test order confirmation',1,'2026-09-04 06:16:25',NULL),(66,'ORD-20260904-024',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'confirmed',420.00,0.00,'',1,'2026-09-04 06:16:25',NULL),(67,'ORD-20260904-025',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'cancelled',195.00,0.00,' [Cancelled: Test cancellation]',1,'2026-09-04 06:16:25','2026-09-04 06:16:25'),(68,'ORD-20260904-026',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'cancelled',195.00,0.00,' [Cancelled: First cancel]',1,'2026-09-04 06:16:25','2026-09-04 06:16:25'),(69,'ORD-20260904-027',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'returned',195.00,0.00,'',1,'2026-09-04 06:16:25','2026-09-04 06:16:25'),(70,'ORD-20260904-028',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'confirmed',195.00,0.00,'',1,'2026-09-04 06:16:25',NULL),(71,'ORD-20260904-029',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'cancelled',285.00,0.00,' [Cancelled: Verification test cancellation]',1,'2026-09-04 06:17:48','2026-09-04 06:17:48'),(72,'ORD-20260904-030',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'confirmed',195.00,0.00,'Automated test order confirmation',1,'2026-09-04 06:17:48',NULL),(73,'ORD-20260904-031',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'confirmed',420.00,0.00,'',1,'2026-09-04 06:17:48',NULL),(74,'ORD-20260904-032',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'cancelled',195.00,0.00,' [Cancelled: Test cancellation]',1,'2026-09-04 06:17:48','2026-09-04 06:17:48'),(75,'ORD-20260904-033',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'cancelled',195.00,0.00,' [Cancelled: First cancel]',1,'2026-09-04 06:17:48','2026-09-04 06:17:48'),(76,'ORD-20260904-034',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'returned',195.00,0.00,'',1,'2026-09-04 06:17:48','2026-09-04 06:17:48'),(77,'ORD-20260904-035',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'confirmed',195.00,0.00,'',1,'2026-09-04 06:17:48',NULL),(78,'ORD-20260904-036',1,NULL,NULL,NULL,NULL,'2026-09-04','2026-09-05','confirmed',6735.00,0.00,'',1,'2026-09-04 06:31:24',NULL),(79,'ORD-20260904-037',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'delivered',285.00,0.00,'',1,'2026-09-04 07:06:41','2026-09-04 07:06:41'),(80,'ORD-20260904-038',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'cancelled',285.00,0.00,' [Cancelled: E2E Test Cancellation]',1,'2026-09-04 07:06:41','2026-09-04 07:06:41'),(81,'ORD-20260904-039',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'returned',285.00,0.00,'',1,'2026-09-04 07:06:41','2026-09-04 07:06:41'),(82,'ORD-20260904-040',1,NULL,NULL,NULL,NULL,'2026-09-04','2026-09-07','confirmed',285.00,0.00,'Converted from Reservation: RES-20260907-006',1,'2026-09-04 07:06:41',NULL),(83,'ORD-20260904-041',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'delivered',285.00,0.00,'',1,'2026-09-04 07:07:00','2026-09-04 07:07:00'),(84,'ORD-20260904-042',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'cancelled',285.00,0.00,' [Cancelled: E2E Test Cancellation]',1,'2026-09-04 07:07:00','2026-09-04 07:07:00'),(85,'ORD-20260904-043',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'returned',285.00,0.00,'',1,'2026-09-04 07:07:00','2026-09-04 07:07:00'),(86,'ORD-20260904-044',1,NULL,NULL,NULL,NULL,'2026-09-04','2026-09-07','confirmed',285.00,0.00,'Converted from Reservation: RES-20260907-007',1,'2026-09-04 07:07:00',NULL),(87,'ORD-20260904-045',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'delivered',285.00,0.00,'',1,'2026-09-04 07:35:48','2026-09-04 07:35:48'),(88,'ORD-20260904-046',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'cancelled',285.00,0.00,' [Cancelled: E2E Test Cancellation]',1,'2026-09-04 07:35:48','2026-09-04 07:35:48'),(89,'ORD-20260904-047',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'returned',285.00,0.00,'',1,'2026-09-04 07:35:48','2026-09-04 07:35:49'),(90,'ORD-20260904-048',1,NULL,NULL,NULL,NULL,'2026-09-04','2026-09-07','confirmed',285.00,0.00,'Converted from Reservation: RES-20260907-008',1,'2026-09-04 07:35:49',NULL),(91,'ORD-20260904-049',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'delivered',285.00,0.00,'',1,'2026-09-04 07:53:35','2026-09-04 07:53:35'),(92,'ORD-20260904-050',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'cancelled',285.00,0.00,' [Cancelled: E2E Test Cancellation]',1,'2026-09-04 07:53:35','2026-09-04 07:53:35'),(93,'ORD-20260904-051',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'returned',285.00,0.00,'',1,'2026-09-04 07:53:35','2026-09-04 07:53:35'),(94,'ORD-20260904-052',1,NULL,NULL,NULL,NULL,'2026-09-04','2026-09-07','confirmed',285.00,0.00,'Converted from Reservation: RES-20260907-009',1,'2026-09-04 07:53:35',NULL),(95,'ORD-20260904-053',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'delivered',285.00,0.00,'',1,'2026-09-04 08:00:45','2026-09-04 08:00:45'),(96,'ORD-20260904-054',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'cancelled',285.00,0.00,' [Cancelled: E2E Test Cancellation]',1,'2026-09-04 08:00:45','2026-09-04 08:00:45'),(97,'ORD-20260904-055',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'returned',285.00,0.00,'',1,'2026-09-04 08:00:45','2026-09-04 08:00:45'),(98,'ORD-20260904-056',1,NULL,NULL,NULL,NULL,'2026-09-04','2026-09-07','confirmed',285.00,0.00,'Converted from Reservation: RES-20260907-010',1,'2026-09-04 08:00:45',NULL),(99,'ORD-20260904-057',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'delivered',285.00,0.00,'',1,'2026-09-04 08:17:25','2026-09-04 08:17:25'),(100,'ORD-20260904-058',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'cancelled',285.00,0.00,' [Cancelled: E2E Test Cancellation]',1,'2026-09-04 08:17:25','2026-09-04 08:17:25'),(101,'ORD-20260904-059',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'returned',285.00,0.00,'',1,'2026-09-04 08:17:25','2026-09-04 08:17:25'),(102,'ORD-20260904-060',1,NULL,NULL,NULL,NULL,'2026-09-04','2026-09-07','confirmed',285.00,0.00,'Converted from Reservation: RES-20260907-011',1,'2026-09-04 08:17:25',NULL),(103,'ORD-20260904-061',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'delivered',285.00,0.00,'',1,'2026-09-04 08:23:29','2026-09-04 08:23:29'),(104,'ORD-20260904-062',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'cancelled',285.00,0.00,' [Cancelled: E2E Test Cancellation]',1,'2026-09-04 08:23:29','2026-09-04 08:23:29'),(105,'ORD-20260904-063',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'returned',285.00,0.00,'',1,'2026-09-04 08:23:29','2026-09-04 08:23:29'),(106,'ORD-20260904-064',1,NULL,NULL,NULL,NULL,'2026-09-04','2026-09-07','confirmed',285.00,0.00,'Converted from Reservation: RES-20260907-012',1,'2026-09-04 08:23:29',NULL),(107,'ORD-20260904-065',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'delivered',285.00,0.00,'',1,'2026-09-04 08:24:07','2026-09-04 08:24:07'),(108,'ORD-20260904-066',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'cancelled',285.00,0.00,' [Cancelled: E2E Test Cancellation]',1,'2026-09-04 08:24:07','2026-09-04 08:24:07'),(109,'ORD-20260904-067',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'returned',285.00,0.00,'',1,'2026-09-04 08:24:07','2026-09-04 08:24:07'),(110,'ORD-20260904-068',1,NULL,NULL,NULL,NULL,'2026-09-04','2026-09-07','confirmed',285.00,0.00,'Converted from Reservation: RES-20260907-013',1,'2026-09-04 08:24:07',NULL),(111,'ORD-20260904-069',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'delivered',285.00,0.00,'',1,'2026-09-04 13:28:09','2026-09-04 13:28:09'),(112,'ORD-20260904-070',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'cancelled',285.00,0.00,' [Cancelled: E2E Test Cancellation]',1,'2026-09-04 13:28:09','2026-09-04 13:28:09'),(113,'ORD-20260904-071',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'returned',285.00,0.00,'',1,'2026-09-04 13:28:09','2026-09-04 13:28:09'),(114,'ORD-20260904-072',1,NULL,NULL,NULL,NULL,'2026-09-04','2026-09-07','confirmed',285.00,0.00,'Converted from Reservation: RES-20260907-014',1,'2026-09-04 13:28:09',NULL),(115,'ORD-20260904-073',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'delivered',285.00,0.00,'',1,'2026-09-04 13:47:01','2026-09-04 13:47:01'),(116,'ORD-20260904-074',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'cancelled',285.00,0.00,' [Cancelled: E2E Test Cancellation]',1,'2026-09-04 13:47:01','2026-09-04 13:47:01'),(117,'ORD-20260904-075',1,NULL,NULL,NULL,NULL,'2026-09-04',NULL,'returned',285.00,0.00,'',1,'2026-09-04 13:47:01','2026-09-04 13:47:01'),(118,'ORD-20260904-076',1,NULL,NULL,NULL,NULL,'2026-09-04','2026-09-07','confirmed',285.00,0.00,'Converted from Reservation: RES-20260907-015',1,'2026-09-04 13:47:01',NULL),(119,'ORD-20260905-001',1,NULL,NULL,NULL,NULL,'2026-09-05',NULL,'delivered',285.00,0.00,'',1,'2026-09-05 05:49:58','2026-09-05 05:49:58'),(120,'ORD-20260905-002',1,NULL,NULL,NULL,NULL,'2026-09-05',NULL,'cancelled',285.00,0.00,' [Cancelled: E2E Test Cancellation]',1,'2026-09-05 05:49:58','2026-09-05 05:49:58'),(121,'ORD-20260905-003',1,NULL,NULL,NULL,NULL,'2026-09-05',NULL,'returned',285.00,0.00,'',1,'2026-09-05 05:49:58','2026-09-05 05:49:58'),(122,'ORD-20260905-004',1,NULL,NULL,NULL,NULL,'2026-09-05','2026-09-08','cancelled',285.00,0.00,'Converted from Reservation: RES-20260908-001 [Cancelled: wala kwarta]',1,'2026-09-05 05:49:58','2026-09-05 05:57:00'),(123,'ORD-20260905-005',1,NULL,NULL,NULL,NULL,'2026-09-05','2026-09-06','confirmed',960.00,0.00,'',2,'2026-09-05 10:05:38',NULL),(124,'ORD-20260913-001',1,NULL,NULL,NULL,NULL,'2026-09-13',NULL,'delivered',285.00,0.00,'',1,'2026-09-13 08:08:35','2026-09-13 08:08:35'),(125,'ORD-20260913-002',1,NULL,NULL,NULL,NULL,'2026-09-13',NULL,'cancelled',285.00,0.00,' [Cancelled: E2E Test Cancellation]',1,'2026-09-13 08:08:35','2026-09-13 08:08:35'),(126,'ORD-20260913-003',1,NULL,NULL,NULL,NULL,'2026-09-13',NULL,'returned',285.00,0.00,'',1,'2026-09-13 08:08:35','2026-09-13 08:08:35'),(127,'ORD-20260913-004',1,NULL,NULL,NULL,NULL,'2026-09-13','2026-09-16','confirmed',285.00,0.00,'Converted from Reservation: RES-20260916-001',1,'2026-09-13 08:08:35',NULL),(128,'ORD-20260913-005',1,NULL,NULL,NULL,NULL,'2026-09-13',NULL,'confirmed',195.00,0.00,'Automated test order confirmation',1,'2026-09-13 08:08:36',NULL),(129,'ORD-20260913-006',1,NULL,NULL,NULL,NULL,'2026-09-13',NULL,'confirmed',420.00,0.00,'',1,'2026-09-13 08:08:36',NULL),(130,'ORD-20260913-007',1,NULL,NULL,NULL,NULL,'2026-09-13',NULL,'cancelled',195.00,0.00,' [Cancelled: Test cancellation]',1,'2026-09-13 08:08:36','2026-09-13 08:08:36'),(131,'ORD-20260913-008',1,NULL,NULL,NULL,NULL,'2026-09-13',NULL,'cancelled',195.00,0.00,' [Cancelled: First cancel]',1,'2026-09-13 08:08:36','2026-09-13 08:08:36'),(132,'ORD-20260913-009',1,NULL,NULL,NULL,NULL,'2026-09-13',NULL,'returned',195.00,0.00,'',1,'2026-09-13 08:08:36','2026-09-13 08:08:36'),(133,'ORD-20260913-010',1,NULL,NULL,NULL,NULL,'2026-09-13',NULL,'confirmed',195.00,0.00,'',1,'2026-09-13 08:08:36',NULL);
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reservation_items`
--

DROP TABLE IF EXISTS `reservation_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reservation_items` (
  `reservation_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `reservation_id` int(11) NOT NULL,
  `grade_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `quantity_fulfilled` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`reservation_item_id`),
  KEY `idx_reservation_items_reservation` (`reservation_id`),
  KEY `idx_reservation_items_grade` (`grade_id`),
  CONSTRAINT `fk_reservation_items_grade` FOREIGN KEY (`grade_id`) REFERENCES `egg_grades` (`grade_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_reservation_items_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`reservation_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reservation_items`
--

LOCK TABLES `reservation_items` WRITE;
/*!40000 ALTER TABLE `reservation_items` DISABLE KEYS */;
INSERT INTO `reservation_items` VALUES (1,1,7,450,0),(2,2,7,30,0),(3,3,7,30,0),(4,4,7,30,0),(5,5,7,30,0),(6,6,7,30,0),(7,7,7,30,0),(8,8,7,30,0),(9,9,7,30,0),(10,10,7,30,0),(11,11,7,30,0),(12,12,7,30,0),(13,13,4,30,0),(14,14,4,30,30),(15,15,4,30,0),(16,16,4,30,30),(17,17,4,30,0),(18,18,4,30,30),(19,19,4,30,0),(20,20,4,30,30),(21,21,4,30,0),(22,22,4,30,30),(23,23,4,30,0),(24,24,4,30,30),(25,25,4,30,0),(26,26,4,30,30),(27,27,4,30,0),(28,28,4,30,30),(29,29,4,30,0),(30,30,4,30,30),(31,31,4,30,0),(32,32,4,30,30),(33,33,4,30,0),(34,34,4,30,30),(35,35,4,30,0),(36,36,4,30,30),(37,37,7,30,0);
/*!40000 ALTER TABLE `reservation_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reservations`
--

DROP TABLE IF EXISTS `reservations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reservations` (
  `reservation_id` int(11) NOT NULL AUTO_INCREMENT,
  `reservation_number` varchar(30) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `quantity_birds` int(11) DEFAULT NULL,
  `weight_kg` decimal(10,2) DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `advance_payment` decimal(14,2) NOT NULL DEFAULT 0.00,
  `pickup_date` date NOT NULL,
  `status` enum('active','fulfilled','cancelled','expired') NOT NULL DEFAULT 'active',
  `converted_order_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`reservation_id`),
  UNIQUE KEY `uk_reservations_number` (`reservation_number`),
  KEY `idx_reservations_pickup_status` (`pickup_date`,`status`),
  KEY `idx_reservations_customer` (`customer_id`),
  KEY `fk_reservations_order` (`converted_order_id`),
  KEY `fk_reservations_created_by` (`created_by`),
  KEY `fk_reservations_batch` (`batch_id`),
  CONSTRAINT `fk_reservations_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_reservations_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_reservations_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_reservations_order` FOREIGN KEY (`converted_order_id`) REFERENCES `orders` (`order_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reservations`
--

LOCK TABLES `reservations` WRITE;
/*!40000 ALTER TABLE `reservations` DISABLE KEYS */;
INSERT INTO `reservations` VALUES (1,'RES-20260819-001',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-08-19','fulfilled',NULL,'9am sharp',1,'2026-08-18 04:30:46','2026-09-04 07:05:32'),(2,'RES-20260821-001',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-08-21','fulfilled',NULL,'',1,'2026-08-18 04:48:42','2026-09-04 13:10:32'),(3,'RES-20260821-002',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-08-21','cancelled',NULL,'',1,'2026-08-18 04:50:14','2026-09-04 07:06:02'),(4,'RES-20260821-003',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-08-21','cancelled',NULL,'',1,'2026-08-18 04:50:37','2026-08-18 04:50:37'),(5,'RES-20260821-004',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-08-21','cancelled',NULL,'',1,'2026-08-18 05:05:01','2026-08-18 05:05:01'),(6,'RES-20260821-005',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-08-21','cancelled',NULL,'',1,'2026-08-18 05:16:12','2026-08-18 05:16:12'),(7,'RES-20260821-006',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-08-21','cancelled',NULL,'',1,'2026-08-18 05:20:05','2026-08-18 05:20:05'),(8,'RES-20260907-001',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-07','cancelled',NULL,'',1,'2026-09-04 05:03:29','2026-09-04 05:03:29'),(9,'RES-20260907-002',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-07','cancelled',NULL,'',1,'2026-09-04 05:49:07','2026-09-04 05:49:07'),(10,'RES-20260907-003',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-07','cancelled',NULL,'',1,'2026-09-04 06:12:22','2026-09-04 06:12:22'),(11,'RES-20260907-004',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-07','cancelled',NULL,'',1,'2026-09-04 06:16:25','2026-09-04 06:16:25'),(12,'RES-20260907-005',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-07','cancelled',NULL,'',1,'2026-09-04 06:17:48','2026-09-04 06:17:48'),(13,'RES-20260909-001',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-09','cancelled',NULL,'',1,'2026-09-04 07:06:41','2026-09-04 07:06:41'),(14,'RES-20260907-006',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-07','fulfilled',82,'',1,'2026-09-04 07:06:41','2026-09-04 07:06:41'),(15,'RES-20260909-002',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-09','cancelled',NULL,'',1,'2026-09-04 07:07:00','2026-09-04 07:07:00'),(16,'RES-20260907-007',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-07','fulfilled',86,'',1,'2026-09-04 07:07:00','2026-09-04 07:07:00'),(17,'RES-20260909-003',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-09','cancelled',NULL,'',1,'2026-09-04 07:35:49','2026-09-04 07:35:49'),(18,'RES-20260907-008',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-07','fulfilled',90,'',1,'2026-09-04 07:35:49','2026-09-04 07:35:49'),(19,'RES-20260909-004',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-09','cancelled',NULL,'',1,'2026-09-04 07:53:35','2026-09-04 07:53:35'),(20,'RES-20260907-009',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-07','fulfilled',94,'',1,'2026-09-04 07:53:35','2026-09-04 07:53:35'),(21,'RES-20260909-005',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-09','cancelled',NULL,'',1,'2026-09-04 08:00:45','2026-09-04 08:00:45'),(22,'RES-20260907-010',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-07','fulfilled',98,'',1,'2026-09-04 08:00:45','2026-09-04 08:00:45'),(23,'RES-20260909-006',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-09','cancelled',NULL,'',1,'2026-09-04 08:17:25','2026-09-04 08:17:25'),(24,'RES-20260907-011',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-07','fulfilled',102,'',1,'2026-09-04 08:17:25','2026-09-04 08:17:25'),(25,'RES-20260909-007',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-09','cancelled',NULL,'',1,'2026-09-04 08:23:29','2026-09-04 08:23:29'),(26,'RES-20260907-012',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-07','fulfilled',106,'',1,'2026-09-04 08:23:29','2026-09-04 08:23:29'),(27,'RES-20260909-008',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-09','cancelled',NULL,'',1,'2026-09-04 08:24:07','2026-09-04 08:24:07'),(28,'RES-20260907-013',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-07','fulfilled',110,'',1,'2026-09-04 08:24:07','2026-09-04 08:24:07'),(29,'RES-20260909-009',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-09','cancelled',NULL,'',1,'2026-09-04 13:28:09','2026-09-04 13:28:09'),(30,'RES-20260907-014',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-07','fulfilled',114,'',1,'2026-09-04 13:28:09','2026-09-04 13:28:09'),(31,'RES-20260909-010',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-09','cancelled',NULL,'',1,'2026-09-04 13:47:01','2026-09-04 13:47:01'),(32,'RES-20260907-015',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-07','fulfilled',118,'',1,'2026-09-04 13:47:01','2026-09-04 13:47:01'),(33,'RES-20260910-001',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-10','cancelled',NULL,'',1,'2026-09-05 05:49:58','2026-09-05 05:49:58'),(34,'RES-20260908-001',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-08','fulfilled',122,'',1,'2026-09-05 05:49:58','2026-09-05 05:49:58'),(35,'RES-20260918-001',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-18','cancelled',NULL,'',1,'2026-09-13 08:08:35','2026-09-13 08:08:35'),(36,'RES-20260916-001',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-16','fulfilled',127,'',1,'2026-09-13 08:08:35','2026-09-13 08:08:35'),(37,'RES-20260916-002',1,NULL,NULL,NULL,NULL,0.00,0.00,'2026-09-16','cancelled',NULL,'',1,'2026-09-13 08:08:36','2026-09-13 08:08:36');
/*!40000 ALTER TABLE `reservations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sales`
--

DROP TABLE IF EXISTS `sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales` (
  `sale_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `total_chickens` int(11) DEFAULT NULL,
  `total_weight` decimal(10,2) DEFAULT NULL,
  `price_per_kg` decimal(14,2) DEFAULT NULL,
  `total_revenue` decimal(14,2) DEFAULT NULL,
  PRIMARY KEY (`sale_id`),
  KEY `batch_id` (`batch_id`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sales`
--

LOCK TABLES `sales` WRITE;
/*!40000 ALTER TABLE `sales` DISABLE KEYS */;
/*!40000 ALTER TABLE `sales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schema_migrations`
--

DROP TABLE IF EXISTS `schema_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `schema_migrations` (
  `migration_id` int(11) NOT NULL AUTO_INCREMENT,
  `migration_name` varchar(255) NOT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`migration_id`),
  UNIQUE KEY `uk_schema_migrations_name` (`migration_name`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schema_migrations`
--

LOCK TABLES `schema_migrations` WRITE;
/*!40000 ALTER TABLE `schema_migrations` DISABLE KEYS */;
INSERT INTO `schema_migrations` VALUES (1,'integrated_poultry_upgrade','2026-08-18 02:22:37');
/*!40000 ALTER TABLE `schema_migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tasks`
--

DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasks` (
  `task_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `batch_id` int(11) DEFAULT NULL,
  `task_type` enum('daily','one_time') DEFAULT 'one_time',
  `last_reset_date` date DEFAULT NULL,
  PRIMARY KEY (`task_id`),
  KEY `assigned_to` (`assigned_to`),
  KEY `created_by` (`created_by`),
  KEY `batch_id` (`batch_id`),
  CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`),
  CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `tasks_ibfk_3` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tasks`
--

LOCK TABLES `tasks` WRITE;
/*!40000 ALTER TABLE `tasks` DISABLE KEYS */;
/*!40000 ALTER TABLE `tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_active_role` (`is_active`,`role`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$10$xvryFqI5T7nVIw2YFSRgbOb2fW1VtCC1.yQth2ABKF2DozeeMhzFG','System Admin',NULL,NULL,1,'2026-09-13 16:08:36','2026-08-18 02:22:35','2026-09-13 08:08:36','admin'),(2,'worker','$2y$10$0San3IbuemD/7pNCKSag8uGaRck84XFwuOQaaM6jLoo7Z1fYPmc5m','Farm Worker',NULL,NULL,1,'2026-09-13 16:08:36','2026-08-18 02:22:35','2026-09-13 08:08:36','staff');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `v_demand_summary`
--

DROP TABLE IF EXISTS `v_demand_summary`;
/*!50001 DROP VIEW IF EXISTS `v_demand_summary`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_demand_summary` AS SELECT
 1 AS `grade_id`,
  1 AS `grade_code`,
  1 AS `grade_name`,
  1 AS `order_demand_qty`,
  1 AS `reservation_demand_qty`,
  1 AS `total_demand_qty` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_demand_supply_gap`
--

DROP TABLE IF EXISTS `v_demand_supply_gap`;
/*!50001 DROP VIEW IF EXISTS `v_demand_supply_gap`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_demand_supply_gap` AS SELECT
 1 AS `grade_id`,
  1 AS `grade_code`,
  1 AS `grade_name`,
  1 AS `supply_on_hand_qty`,
  1 AS `demand_committed_qty`,
  1 AS `uncommitted_on_hand_qty`,
  1 AS `supply_demand_gap` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_egg_available_qty`
--

DROP TABLE IF EXISTS `v_egg_available_qty`;
/*!50001 DROP VIEW IF EXISTS `v_egg_available_qty`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_egg_available_qty` AS SELECT
 1 AS `grade_id`,
  1 AS `grade_code`,
  1 AS `grade_name`,
  1 AS `on_hand_qty`,
  1 AS `committed_qty`,
  1 AS `available_qty` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_egg_inventory_balance`
--

DROP TABLE IF EXISTS `v_egg_inventory_balance`;
/*!50001 DROP VIEW IF EXISTS `v_egg_inventory_balance`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_egg_inventory_balance` AS SELECT
 1 AS `grade_id`,
  1 AS `grade_code`,
  1 AS `grade_name`,
  1 AS `on_hand_qty` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_egg_order_commitment`
--

DROP TABLE IF EXISTS `v_egg_order_commitment`;
/*!50001 DROP VIEW IF EXISTS `v_egg_order_commitment`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_egg_order_commitment` AS SELECT
 1 AS `grade_id`,
  1 AS `committed_from_orders` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_egg_reserved_qty`
--

DROP TABLE IF EXISTS `v_egg_reserved_qty`;
/*!50001 DROP VIEW IF EXISTS `v_egg_reserved_qty`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_egg_reserved_qty` AS SELECT
 1 AS `grade_id`,
  1 AS `reserved_from_reservations` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_expiring_lots`
--

DROP TABLE IF EXISTS `v_expiring_lots`;
/*!50001 DROP VIEW IF EXISTS `v_expiring_lots`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_expiring_lots` AS SELECT
 1 AS `lot_id`,
  1 AS `inventory_id`,
  1 AS `item_name`,
  1 AS `category`,
  1 AS `lot_number`,
  1 AS `quantity_remaining`,
  1 AS `expiry_date`,
  1 AS `days_until_expiry` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_supply_summary`
--

DROP TABLE IF EXISTS `v_supply_summary`;
/*!50001 DROP VIEW IF EXISTS `v_supply_summary`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_supply_summary` AS SELECT
 1 AS `grade_id`,
  1 AS `grade_code`,
  1 AS `grade_name`,
  1 AS `on_hand_qty`,
  1 AS `avg_daily_production_7d`,
  1 AS `projected_production_7d` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `waste_records`
--

DROP TABLE IF EXISTS `waste_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `waste_records` (
  `waste_id` int(11) NOT NULL AUTO_INCREMENT,
  `waste_date` date NOT NULL,
  `waste_type` enum('egg','supply','medicine','other') NOT NULL,
  `grade_id` int(11) DEFAULT NULL,
  `inventory_id` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `reason` enum('broken','expired','spoiled','damaged','cull','other') NOT NULL DEFAULT 'other',
  `estimated_cost` decimal(14,2) DEFAULT NULL,
  `expense_id` int(11) DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`waste_id`),
  KEY `idx_waste_records_date` (`waste_date`),
  KEY `idx_waste_records_type` (`waste_type`),
  KEY `idx_waste_records_grade` (`grade_id`),
  KEY `idx_waste_records_inventory` (`inventory_id`),
  KEY `idx_waste_records_batch` (`batch_id`),
  KEY `fk_waste_records_expense` (`expense_id`),
  KEY `fk_waste_records_recorded_by` (`recorded_by`),
  CONSTRAINT `fk_waste_records_batch` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`batch_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_waste_records_expense` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`expense_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_waste_records_grade` FOREIGN KEY (`grade_id`) REFERENCES `egg_grades` (`grade_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_waste_records_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventory` (`inventory_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_waste_records_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `waste_records`
--

LOCK TABLES `waste_records` WRITE;
/*!40000 ALTER TABLE `waste_records` DISABLE KEYS */;
INSERT INTO `waste_records` VALUES (1,'2026-09-04','egg',4,NULL,NULL,5.00,'pcs','broken',NULL,NULL,1,'E2E Broken egg test','2026-09-04 07:06:41'),(2,'2026-09-04','supply',NULL,1,NULL,2.00,'units','expired',350.00,1,1,'E2E Supply Waste Test','2026-09-04 07:06:41'),(3,'2026-09-04','egg',4,NULL,NULL,5.00,'pcs','broken',NULL,NULL,1,'E2E Broken egg test','2026-09-04 07:07:00'),(4,'2026-09-04','supply',NULL,1,NULL,2.00,'units','expired',350.00,2,1,'E2E Supply Waste Test','2026-09-04 07:07:00'),(5,'2026-09-04','egg',4,NULL,NULL,5.00,'pcs','broken',NULL,NULL,1,'E2E Broken egg test','2026-09-04 07:35:49'),(6,'2026-09-04','supply',NULL,1,NULL,2.00,'units','expired',350.00,3,1,'E2E Supply Waste Test','2026-09-04 07:35:49'),(7,'2026-09-04','egg',4,NULL,NULL,5.00,'pcs','broken',NULL,NULL,1,'E2E Broken egg test','2026-09-04 07:53:35'),(8,'2026-09-04','supply',NULL,1,NULL,2.00,'units','expired',350.00,4,1,'E2E Supply Waste Test','2026-09-04 07:53:35'),(9,'2026-09-04','egg',4,NULL,NULL,5.00,'pcs','broken',NULL,NULL,1,'E2E Broken egg test','2026-09-04 08:00:45'),(10,'2026-09-04','supply',NULL,1,NULL,2.00,'units','expired',350.00,5,1,'E2E Supply Waste Test','2026-09-04 08:00:45'),(11,'2026-09-04','egg',4,NULL,NULL,5.00,'pcs','broken',NULL,NULL,1,'E2E Broken egg test','2026-09-04 08:17:25'),(12,'2026-09-04','supply',NULL,1,NULL,2.00,'units','expired',350.00,6,1,'E2E Supply Waste Test','2026-09-04 08:17:25'),(13,'2026-09-04','egg',4,NULL,NULL,5.00,'pcs','broken',NULL,NULL,1,'E2E Broken egg test','2026-09-04 08:23:29'),(14,'2026-09-04','supply',NULL,1,NULL,2.00,'units','expired',350.00,7,1,'E2E Supply Waste Test','2026-09-04 08:23:29'),(15,'2026-09-04','egg',4,NULL,NULL,5.00,'pcs','broken',NULL,NULL,1,'E2E Broken egg test','2026-09-04 08:24:07'),(16,'2026-09-04','supply',NULL,1,NULL,2.00,'units','expired',350.00,8,1,'E2E Supply Waste Test','2026-09-04 08:24:07'),(17,'2026-09-04','egg',4,NULL,NULL,5.00,'pcs','broken',NULL,NULL,1,'E2E Broken egg test','2026-09-04 13:28:09'),(18,'2026-09-04','supply',NULL,1,NULL,2.00,'units','expired',350.00,9,1,'E2E Supply Waste Test','2026-09-04 13:28:09'),(19,'2026-09-04','egg',4,NULL,NULL,5.00,'pcs','broken',NULL,NULL,1,'E2E Broken egg test','2026-09-04 13:47:01'),(20,'2026-09-04','supply',NULL,1,NULL,2.00,'units','expired',350.00,10,1,'E2E Supply Waste Test','2026-09-04 13:47:01'),(21,'2026-09-05','egg',4,NULL,NULL,5.00,'pcs','broken',NULL,NULL,1,'E2E Broken egg test','2026-09-05 05:49:59'),(22,'2026-09-05','supply',NULL,1,NULL,2.00,'units','expired',350.00,11,1,'E2E Supply Waste Test','2026-09-05 05:49:59'),(23,'2026-09-13','egg',4,NULL,NULL,5.00,'pcs','broken',NULL,NULL,1,'E2E Broken egg test','2026-09-13 08:08:35'),(24,'2026-09-13','supply',NULL,1,NULL,2.00,'units','expired',350.00,12,1,'E2E Supply Waste Test','2026-09-13 08:08:35');
/*!40000 ALTER TABLE `waste_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Final view structure for view `v_demand_summary`
--

/*!50001 DROP VIEW IF EXISTS `v_demand_summary`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_demand_summary` AS select `g`.`grade_id` AS `grade_id`,`g`.`grade_code` AS `grade_code`,`g`.`grade_name` AS `grade_name`,coalesce(`oc`.`committed_from_orders`,0) AS `order_demand_qty`,coalesce(`rr`.`reserved_from_reservations`,0) AS `reservation_demand_qty`,coalesce(`oc`.`committed_from_orders`,0) + coalesce(`rr`.`reserved_from_reservations`,0) AS `total_demand_qty` from ((`egg_grades` `g` left join `v_egg_order_commitment` `oc` on(`oc`.`grade_id` = `g`.`grade_id`)) left join `v_egg_reserved_qty` `rr` on(`rr`.`grade_id` = `g`.`grade_id`)) where `g`.`is_active` = 1 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_demand_supply_gap`
--

/*!50001 DROP VIEW IF EXISTS `v_demand_supply_gap`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_demand_supply_gap` AS select `s`.`grade_id` AS `grade_id`,`s`.`grade_code` AS `grade_code`,`s`.`grade_name` AS `grade_name`,`s`.`on_hand_qty` AS `supply_on_hand_qty`,`d`.`total_demand_qty` AS `demand_committed_qty`,`s`.`on_hand_qty` - `d`.`total_demand_qty` AS `uncommitted_on_hand_qty`,`s`.`on_hand_qty` - `d`.`total_demand_qty` AS `supply_demand_gap` from (`v_supply_summary` `s` join `v_demand_summary` `d` on(`d`.`grade_id` = `s`.`grade_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_egg_available_qty`
--

/*!50001 DROP VIEW IF EXISTS `v_egg_available_qty`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_egg_available_qty` AS select `b`.`grade_id` AS `grade_id`,`b`.`grade_code` AS `grade_code`,`b`.`grade_name` AS `grade_name`,`b`.`on_hand_qty` AS `on_hand_qty`,coalesce(`d`.`total_demand_qty`,0) AS `committed_qty`,`b`.`on_hand_qty` - coalesce(`d`.`total_demand_qty`,0) AS `available_qty` from (`v_egg_inventory_balance` `b` left join `v_demand_summary` `d` on(`d`.`grade_id` = `b`.`grade_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_egg_inventory_balance`
--

/*!50001 DROP VIEW IF EXISTS `v_egg_inventory_balance`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_egg_inventory_balance` AS select `g`.`grade_id` AS `grade_id`,`g`.`grade_code` AS `grade_code`,`g`.`grade_name` AS `grade_name`,coalesce(sum(case `eim`.`movement_type` when 'production_in' then `eim`.`quantity` when 'adjustment_in' then `eim`.`quantity` when 'reservation_release' then `eim`.`quantity` when 'order_alloc' then -`eim`.`quantity` when 'order_out' then -`eim`.`quantity` when 'reservation_hold' then -`eim`.`quantity` when 'distribution_out' then -`eim`.`quantity` when 'waste_out' then -`eim`.`quantity` when 'adjustment_out' then -`eim`.`quantity` else 0 end),0) AS `on_hand_qty` from (`egg_grades` `g` left join `egg_inventory_movements` `eim` on(`eim`.`grade_id` = `g`.`grade_id`)) where `g`.`is_active` = 1 group by `g`.`grade_id`,`g`.`grade_code`,`g`.`grade_name` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_egg_order_commitment`
--

/*!50001 DROP VIEW IF EXISTS `v_egg_order_commitment`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_egg_order_commitment` AS select `oi`.`grade_id` AS `grade_id`,coalesce(sum(case when `o`.`status` in ('pending','confirmed','processing','partially_delivered') then greatest(`oi`.`quantity_ordered` - `oi`.`quantity_delivered`,0) else 0 end),0) AS `committed_from_orders` from (`order_items` `oi` join `orders` `o` on(`o`.`order_id` = `oi`.`order_id`)) group by `oi`.`grade_id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_egg_reserved_qty`
--

/*!50001 DROP VIEW IF EXISTS `v_egg_reserved_qty`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_egg_reserved_qty` AS select `ri`.`grade_id` AS `grade_id`,coalesce(sum(case when `r`.`status` = 'active' then greatest(`ri`.`quantity` - `ri`.`quantity_fulfilled`,0) else 0 end),0) AS `reserved_from_reservations` from (`reservation_items` `ri` join `reservations` `r` on(`r`.`reservation_id` = `ri`.`reservation_id`)) group by `ri`.`grade_id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_expiring_lots`
--

/*!50001 DROP VIEW IF EXISTS `v_expiring_lots`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_expiring_lots` AS select `il`.`lot_id` AS `lot_id`,`il`.`inventory_id` AS `inventory_id`,`i`.`item_name` AS `item_name`,`i`.`category` AS `category`,`il`.`lot_number` AS `lot_number`,`il`.`quantity_remaining` AS `quantity_remaining`,`il`.`expiry_date` AS `expiry_date`,to_days(`il`.`expiry_date`) - to_days(curdate()) AS `days_until_expiry` from (`inventory_lots` `il` join `inventory` `i` on(`i`.`inventory_id` = `il`.`inventory_id`)) where `il`.`quantity_remaining` > 0 and `il`.`expiry_date` is not null */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_supply_summary`
--

/*!50001 DROP VIEW IF EXISTS `v_supply_summary`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_supply_summary` AS select `b`.`grade_id` AS `grade_id`,`b`.`grade_code` AS `grade_code`,`b`.`grade_name` AS `grade_name`,`b`.`on_hand_qty` AS `on_hand_qty`,coalesce(`p`.`avg_daily_production_7d`,0) AS `avg_daily_production_7d`,coalesce(`p`.`avg_daily_production_7d`,0) * 7 AS `projected_production_7d` from (`v_egg_inventory_balance` `b` left join (select `ep`.`grade_id` AS `grade_id`,round(sum(`ep`.`quantity_collected` - `ep`.`quantity_broken`) / 7,2) AS `avg_daily_production_7d` from `egg_production` `ep` where `ep`.`production_date` >= curdate() - interval 7 day group by `ep`.`grade_id`) `p` on(`p`.`grade_id` = `b`.`grade_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-19 23:11:12
