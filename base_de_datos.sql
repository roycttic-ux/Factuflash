-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: fy_facturacion
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
-- Table structure for table `fy_cache`
--

DROP TABLE IF EXISTS `fy_cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fy_cache` (
  `key` varchar(191) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `fy_cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fy_cache`
--

LOCK TABLES `fy_cache` WRITE;
/*!40000 ALTER TABLE `fy_cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `fy_cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fy_cache_locks`
--

DROP TABLE IF EXISTS `fy_cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fy_cache_locks` (
  `key` varchar(191) NOT NULL,
  `owner` varchar(191) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `fy_cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fy_cache_locks`
--

LOCK TABLES `fy_cache_locks` WRITE;
/*!40000 ALTER TABLE `fy_cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `fy_cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fy_failed_jobs`
--

DROP TABLE IF EXISTS `fy_failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fy_failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(191) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `fy_failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fy_failed_jobs`
--

LOCK TABLES `fy_failed_jobs` WRITE;
/*!40000 ALTER TABLE `fy_failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `fy_failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fy_fy_migrations`
--

DROP TABLE IF EXISTS `fy_fy_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fy_fy_migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fy_fy_migrations`
--

LOCK TABLES `fy_fy_migrations` WRITE;
/*!40000 ALTER TABLE `fy_fy_migrations` DISABLE KEYS */;
INSERT INTO `fy_fy_migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_04_08_000001_create_products_table',1),(5,'2026_04_08_000002_create_recipients_table',1),(6,'2026_04_08_000003_create_invoices_table',1),(7,'2026_04_08_000004_create_invoice_items_table',1),(8,'2026_04_08_000005_create_user_settings_table',1),(9,'2026_04_08_000006_create_subscriptions_table',1),(10,'2026_04_12_005242_make_recipient_id_nullable_in_invoices',1),(11,'2026_04_15_000001_add_missing_fields_to_user_settings',1),(12,'2026_04_15_000002_create_personal_access_tokens_table',1),(13,'2026_04_15_000003_add_missing_fields_to_invoices',1),(14,'2026_04_16_030119_add_cashier_features_to_users_and_invoices',1),(15,'2026_04_20_000001_add_extra_fields_to_user_settings',1),(16,'2026_04_21_000001_add_slug_to_user_settings',1),(17,'2026_04_21_000002_add_category_and_unlimited_stock_to_products',1),(18,'2026_04_25_000000_make_ulises_admin',1),(19,'2026_04_26_000001_add_catalog_personalization_to_user_settings',1),(20,'2026_04_26_000002_add_catalog_grid_columns_to_user_settings',1),(21,'2026_05_04_000001_add_advanced_fields_to_products',1),(22,'2026_05_04_000002_add_afip_fields_to_invoices',1),(23,'2026_05_04_000003_add_variant_index_to_invoice_items',1),(24,'2026_05_07_002957_add_extra_social_to_user_settings',1),(25,'2026_05_08_180000_create_invoice_payments_table',1),(26,'2026_05_08_214500_create_pos_devices_table',1),(27,'2026_05_09_005500_add_variant_index_to_invoice_items',1),(28,'2026_05_10_000000_add_api_token_to_users_table',1),(29,'2026_05_19_000000_add_arca_pto_vta_to_user_settings',1),(30,'2026_05_19_172351_add_bank_details_to_user_settings',1),(31,'2026_05_29_175520_add_catalog_video_url_to_user_settings_table',1),(32,'2026_05_31_000001_add_annulled_to_invoices_status',1),(33,'2026_06_01_230106_add_pos_providers_to_user_settings_table',1);
/*!40000 ALTER TABLE `fy_fy_migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fy_invoice_items`
--

DROP TABLE IF EXISTS `fy_invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fy_invoice_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `variant_index` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price_usd` decimal(12,4) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fy_invoice_items_invoice_id_foreign` (`invoice_id`),
  KEY `fy_invoice_items_product_id_foreign` (`product_id`),
  CONSTRAINT `fy_invoice_items_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `fy_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fy_invoice_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `fy_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fy_invoice_items`
--

LOCK TABLES `fy_invoice_items` WRITE;
/*!40000 ALTER TABLE `fy_invoice_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `fy_invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fy_invoice_payments`
--

DROP TABLE IF EXISTS `fy_invoice_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fy_invoice_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `payment_method` varchar(191) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `details` varchar(191) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fy_invoice_payments_invoice_id_foreign` (`invoice_id`),
  CONSTRAINT `fy_invoice_payments_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `fy_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fy_invoice_payments`
--

LOCK TABLES `fy_invoice_payments` WRITE;
/*!40000 ALTER TABLE `fy_invoice_payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `fy_invoice_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fy_invoices`
--

DROP TABLE IF EXISTS `fy_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fy_invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `recipient_id` bigint(20) unsigned DEFAULT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `nc_number` varchar(191) DEFAULT NULL,
  `cbte_tipo` int(11) DEFAULT NULL,
  `pto_vta` int(11) DEFAULT NULL,
  `cae` varchar(50) DEFAULT NULL,
  `nc_cae` varchar(191) DEFAULT NULL,
  `cae_expiry` date DEFAULT NULL,
  `dollar_rate` decimal(12,4) NOT NULL,
  `total_usd` decimal(12,4) NOT NULL,
  `total_local` decimal(12,2) NOT NULL,
  `status` enum('paid','pending','canceled','annulled') NOT NULL DEFAULT 'paid',
  `notify_afip` tinyint(1) NOT NULL DEFAULT 0,
  `payment_method` varchar(50) DEFAULT NULL,
  `is_multiple_payment` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `cashier_id` bigint(20) unsigned DEFAULT NULL,
  `pos_device_id` bigint(20) unsigned DEFAULT NULL,
  `payment_transaction_id` varchar(191) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fy_invoices_invoice_number_unique` (`invoice_number`),
  KEY `fy_invoices_user_id_foreign` (`user_id`),
  KEY `fy_invoices_recipient_id_foreign` (`recipient_id`),
  KEY `fy_invoices_cashier_id_foreign` (`cashier_id`),
  KEY `fy_invoices_pos_device_id_foreign` (`pos_device_id`),
  CONSTRAINT `fy_invoices_cashier_id_foreign` FOREIGN KEY (`cashier_id`) REFERENCES `fy_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fy_invoices_pos_device_id_foreign` FOREIGN KEY (`pos_device_id`) REFERENCES `fy_pos_devices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fy_invoices_recipient_id_foreign` FOREIGN KEY (`recipient_id`) REFERENCES `fy_recipients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fy_invoices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `fy_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fy_invoices`
--

LOCK TABLES `fy_invoices` WRITE;
/*!40000 ALTER TABLE `fy_invoices` DISABLE KEYS */;
/*!40000 ALTER TABLE `fy_invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fy_job_batches`
--

DROP TABLE IF EXISTS `fy_job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fy_job_batches` (
  `id` varchar(191) NOT NULL,
  `name` varchar(191) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fy_job_batches`
--

LOCK TABLES `fy_job_batches` WRITE;
/*!40000 ALTER TABLE `fy_job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `fy_job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fy_jobs`
--

DROP TABLE IF EXISTS `fy_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fy_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(191) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fy_jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fy_jobs`
--

LOCK TABLES `fy_jobs` WRITE;
/*!40000 ALTER TABLE `fy_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `fy_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fy_password_reset_tokens`
--

DROP TABLE IF EXISTS `fy_password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fy_password_reset_tokens` (
  `email` varchar(191) NOT NULL,
  `token` varchar(191) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fy_password_reset_tokens`
--

LOCK TABLES `fy_password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `fy_password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `fy_password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fy_personal_access_tokens`
--

DROP TABLE IF EXISTS `fy_personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fy_personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(191) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` varchar(191) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fy_personal_access_tokens_token_unique` (`token`),
  KEY `fy_personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fy_personal_access_tokens`
--

LOCK TABLES `fy_personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `fy_personal_access_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `fy_personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fy_pos_devices`
--

DROP TABLE IF EXISTS `fy_pos_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fy_pos_devices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `name` varchar(191) NOT NULL,
  `type` enum('mercadopago_point','traditional_pos') NOT NULL DEFAULT 'mercadopago_point',
  `external_id` varchar(191) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fy_pos_devices_external_id_unique` (`external_id`),
  KEY `fy_pos_devices_user_id_foreign` (`user_id`),
  CONSTRAINT `fy_pos_devices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `fy_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fy_pos_devices`
--

LOCK TABLES `fy_pos_devices` WRITE;
/*!40000 ALTER TABLE `fy_pos_devices` DISABLE KEYS */;
/*!40000 ALTER TABLE `fy_pos_devices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fy_products`
--

DROP TABLE IF EXISTS `fy_products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fy_products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `cost_usd` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `margin_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `recommended_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `stock` int(11) NOT NULL DEFAULT 0,
  `unlimited_stock` tinyint(1) NOT NULL DEFAULT 0,
  `min_stock_alert` int(11) NOT NULL DEFAULT 5,
  `category` varchar(50) DEFAULT NULL,
  `tags` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`images`)),
  `variants` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variants`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fy_products_user_id_foreign` (`user_id`),
  CONSTRAINT `fy_products_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `fy_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fy_products`
--

LOCK TABLES `fy_products` WRITE;
/*!40000 ALTER TABLE `fy_products` DISABLE KEYS */;
/*!40000 ALTER TABLE `fy_products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fy_recipients`
--

DROP TABLE IF EXISTS `fy_recipients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fy_recipients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `document_id` varchar(50) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fy_recipients_user_id_foreign` (`user_id`),
  CONSTRAINT `fy_recipients_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `fy_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fy_recipients`
--

LOCK TABLES `fy_recipients` WRITE;
/*!40000 ALTER TABLE `fy_recipients` DISABLE KEYS */;
/*!40000 ALTER TABLE `fy_recipients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fy_sessions`
--

DROP TABLE IF EXISTS `fy_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fy_sessions` (
  `id` varchar(191) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fy_sessions_user_id_index` (`user_id`),
  KEY `fy_sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fy_sessions`
--

LOCK TABLES `fy_sessions` WRITE;
/*!40000 ALTER TABLE `fy_sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `fy_sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fy_subscriptions`
--

DROP TABLE IF EXISTS `fy_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fy_subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `mercadopago_id` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `next_payment_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fy_subscriptions_user_id_foreign` (`user_id`),
  CONSTRAINT `fy_subscriptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `fy_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fy_subscriptions`
--

LOCK TABLES `fy_subscriptions` WRITE;
/*!40000 ALTER TABLE `fy_subscriptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `fy_subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fy_user_settings`
--

DROP TABLE IF EXISTS `fy_user_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fy_user_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `slug` varchar(191) DEFAULT NULL,
  `company_name` varchar(150) DEFAULT NULL,
  `company_logo` varchar(255) DEFAULT NULL,
  `company_address` varchar(255) DEFAULT NULL,
  `company_phone` varchar(20) DEFAULT NULL,
  `whatsapp_number` varchar(20) DEFAULT NULL,
  `show_whatsapp_button` tinyint(1) NOT NULL DEFAULT 0,
  `company_email` varchar(150) DEFAULT NULL,
  `company_cuit` varchar(20) DEFAULT NULL,
  `invoice_prefix` varchar(10) NOT NULL DEFAULT 'F',
  `tax_condition` varchar(50) NOT NULL DEFAULT 'Responsable Inscripto',
  `arca_cuit` varchar(20) DEFAULT NULL,
  `arca_cert_path` varchar(255) DEFAULT NULL,
  `arca_key_path` varchar(255) DEFAULT NULL,
  `arca_connected` tinyint(1) NOT NULL DEFAULT 0,
  `arca_pto_vta` int(11) NOT NULL DEFAULT 1,
  `dollar_rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `dollar_rate_mode` varchar(20) NOT NULL DEFAULT 'manual',
  `dollar_rate_manual` decimal(12,2) NOT NULL DEFAULT 0.00,
  `inflation_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `mp_access_token` varchar(255) DEFAULT NULL,
  `mp_public_key` varchar(255) DEFAULT NULL,
  `catalog_theme` varchar(20) NOT NULL DEFAULT 'white',
  `catalog_title` varchar(191) DEFAULT NULL,
  `catalog_description` text DEFAULT NULL,
  `catalog_instagram` varchar(191) DEFAULT NULL,
  `catalog_facebook` varchar(191) DEFAULT NULL,
  `catalog_tiktok` varchar(191) DEFAULT NULL,
  `catalog_show_stock` tinyint(1) NOT NULL DEFAULT 1,
  `catalog_show_price` tinyint(1) NOT NULL DEFAULT 1,
  `catalog_grid_columns` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `bank_name` varchar(191) DEFAULT NULL,
  `bank_cbu` varchar(191) DEFAULT NULL,
  `bank_alias` varchar(191) DEFAULT NULL,
  `bank_holder` varchar(191) DEFAULT NULL,
  `catalog_video_url` varchar(191) DEFAULT NULL,
  `pos_provider` varchar(191) DEFAULT 'none',
  `pos_clover_merchant_id` varchar(191) DEFAULT NULL,
  `pos_clover_access_token` varchar(191) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fy_user_settings_user_id_unique` (`user_id`),
  UNIQUE KEY `fy_user_settings_slug_unique` (`slug`),
  CONSTRAINT `fy_user_settings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `fy_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fy_user_settings`
--

LOCK TABLES `fy_user_settings` WRITE;
/*!40000 ALTER TABLE `fy_user_settings` DISABLE KEYS */;
INSERT INTO `fy_user_settings` VALUES (1,2,NULL,'Escuela Da Vinci',NULL,NULL,NULL,NULL,0,NULL,NULL,'F','Responsable Inscripto',NULL,NULL,NULL,0,1,0.00,'manual',0.00,0.00,NULL,NULL,'white',NULL,NULL,NULL,NULL,NULL,1,1,1,'2026-06-21 21:35:15','2026-06-21 21:35:15',NULL,NULL,NULL,NULL,NULL,'none',NULL,NULL);
/*!40000 ALTER TABLE `fy_user_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fy_users`
--

DROP TABLE IF EXISTS `fy_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fy_users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) NOT NULL,
  `email` varchar(191) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(191) NOT NULL,
  `company_name` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `cuit` varchar(20) DEFAULT NULL,
  `cert_path` varchar(255) DEFAULT NULL,
  `key_path` varchar(255) DEFAULT NULL,
  `pos_number` int(11) NOT NULL DEFAULT 1,
  `role` enum('admin','owner','cashier','user') NOT NULL DEFAULT 'user',
  `logo_path` varchar(255) DEFAULT NULL,
  `plan_status` enum('trial','active','expired','canceled') NOT NULL DEFAULT 'trial',
  `trial_expires_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `api_token` varchar(80) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `owner_id` bigint(20) unsigned DEFAULT NULL,
  `cash_register_name` varchar(191) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `fy_users_email_unique` (`email`),
  UNIQUE KEY `fy_users_api_token_unique` (`api_token`),
  KEY `fy_users_owner_id_foreign` (`owner_id`),
  CONSTRAINT `fy_users_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `fy_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fy_users`
--

LOCK TABLES `fy_users` WRITE;
/*!40000 ALTER TABLE `fy_users` DISABLE KEYS */;
INSERT INTO `fy_users` VALUES (1,'Test User','test@example.com','2026-06-22 00:31:30','$2y$12$UOFaSa7BuEUWN95JVp98m.g1uM7S.rP4/fwqj74dmWxeJ5mlfjY7G',NULL,NULL,NULL,NULL,NULL,1,'user',NULL,'active',NULL,'rklUejhdWt','wWtQsHBrP1OusTc1z9YRC3PO4Q38xDM8v1zED3j7r4dWaDiECBybHKcPBtaW','2026-06-22 00:31:30','2026-06-22 00:31:30',NULL,NULL),(2,'Profesor','profe@factuflash.com',NULL,'$2y$10$zLT09R1vt1CbSCgiEmXdy.Hjbn5W/ToGskvIEvWD9BdpFD0kDvEvO',NULL,NULL,NULL,NULL,NULL,1,'admin',NULL,'active',NULL,NULL,NULL,'2026-06-21 21:35:15','2026-06-21 21:35:15',NULL,NULL);
/*!40000 ALTER TABLE `fy_users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-21 18:35:37
