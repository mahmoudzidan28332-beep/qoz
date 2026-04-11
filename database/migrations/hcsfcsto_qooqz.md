-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 11, 2026 at 07:19 AM
-- Server version: 10.11.16-MariaDB-cll-lve-log
-- PHP Version: 8.4.19

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hcsfcsto_qooqz`
--

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `owner_type` enum('user','entity') NOT NULL,
  `owner_id` bigint(20) UNSIGNED NOT NULL,
  `address_line1` varchar(255) NOT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city_id` int(11) DEFAULT NULL,
  `country_id` int(11) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(11,7) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `primary_marker` varchar(100) GENERATED ALWAYS AS (case when `is_primary` = 1 then concat(`owner_type`,'-',`owner_id`,'-primary') else NULL end) VIRTUAL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `addresses`
--


-- --------------------------------------------------------

--
-- Table structure for table `ads`
--

CREATE TABLE `ads` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `campaign_id` bigint(20) UNSIGNED NOT NULL,
  `target_type` enum('url','product','category','entity','brand','auction','job','page') DEFAULT 'url' COMMENT 'Determines the click-through destination type',
  `target_value` varchar(500) DEFAULT NULL,
  `status` enum('active','paused','rejected') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ads`
--


-- --------------------------------------------------------

--
-- Table structure for table `ad_campaigns`
--

CREATE TABLE `ad_campaigns` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `budget` decimal(12,2) DEFAULT 0.00,
  `currency_id` smallint(5) UNSIGNED NOT NULL,
  `pricing_model` enum('fixed','cpm','cpc') DEFAULT 'fixed',
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `status` enum('draft','active','paused','completed') DEFAULT 'draft',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ad_campaigns`
--

-- --------------------------------------------------------

--
-- Table structure for table `ad_payments`
--

CREATE TABLE `ad_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `campaign_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(12,2) DEFAULT NULL,
  `currency_id` smallint(5) UNSIGNED NOT NULL,
  `status` enum('pending','paid','failed') DEFAULT 'pending',
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ad_placements`
--

CREATE TABLE `ad_placements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `code` varchar(100) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `placement_key` varchar(100) NOT NULL,
  `page` varchar(100) DEFAULT NULL,
  `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `max_ads` int(11) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `status` enum('active','inactive','draft') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ad_placements`
--


-- --------------------------------------------------------

--
-- Table structure for table `ad_placement_items`
--

CREATE TABLE `ad_placement_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `placement_id` bigint(20) UNSIGNED NOT NULL,
  `ad_id` bigint(20) UNSIGNED NOT NULL,
  `priority` int(11) DEFAULT 1,
  `weight` int(11) DEFAULT 1,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ad_placement_items`
--


-- --------------------------------------------------------

--
-- Table structure for table `ad_stats`
--

CREATE TABLE `ad_stats` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ad_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `views` int(11) DEFAULT 0,
  `clicks` int(11) DEFAULT 0,
  `event_type` enum('view','click') NOT NULL DEFAULT 'view',
  `date` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ad_stats`
--


-- --------------------------------------------------------

--
-- Table structure for table `ad_translations`
--

CREATE TABLE `ad_translations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ad_id` bigint(20) UNSIGNED NOT NULL,
  `language_code` varchar(8) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ad_translations`
--

-- --------------------------------------------------------

--
-- Table structure for table `ai_documents`
--

CREATE TABLE `ai_documents` (
  `id` char(36) NOT NULL,
  `knowledge_base_id` char(36) DEFAULT NULL,
  `file_id` char(36) DEFAULT NULL,
  `title` varchar(500) DEFAULT NULL,
  `source_url` text DEFAULT NULL,
  `language` varchar(8) DEFAULT 'ar',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '{}' CHECK (json_valid(`metadata`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ai_documents`
--


-- --------------------------------------------------------

--
-- Table structure for table `ai_document_chunks`
--

CREATE TABLE `ai_document_chunks` (
  `id` char(36) NOT NULL,
  `document_id` char(36) DEFAULT NULL,
  `chunk_index` int(11) NOT NULL,
  `content` text NOT NULL,
  `language` varchar(8) DEFAULT 'ar',
  `token_count` int(11) DEFAULT NULL,
  `embedding` blob DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '{}' CHECK (json_valid(`metadata`)),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ai_document_chunks`
--

-- --------------------------------------------------------

--
-- Table structure for table `ai_feedback`
--

CREATE TABLE `ai_feedback` (
  `id` char(36) NOT NULL,
  `message_id` char(36) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ai_feedback`
--

INSERT INTO `ai_feedback` (`id`, `message_id`, `rating`, `comment`, `created_at`) VALUES
('feedback-0001-uuid', 'msg-0002-uuid', 5, 'رد جيد وواضح', '2026-02-16 14:50:22');

-- --------------------------------------------------------

--
-- Table structure for table `ai_files`
--

CREATE TABLE `ai_files` (
  `id` char(36) NOT NULL,
  `filename` varchar(500) NOT NULL,
  `mime_type` varchar(255) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `file_path` text DEFAULT NULL,
  `extracted_text` text DEFAULT NULL,
  `structured_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`structured_data`)),
  `embedding_model` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ai_files`
--


-- --------------------------------------------------------

--
-- Table structure for table `ai_knowledge_bases`
--

CREATE TABLE `ai_knowledge_bases` (
  `id` char(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '{}' CHECK (json_valid(`metadata`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ai_knowledge_bases`
--

INSERT INTO `ai_knowledge_bases` (`id`, `name`, `description`, `is_public`, `metadata`, `created_at`, `updated_at`) VALUES
('kb-0001-uuid', 'عام', 'قاعدة معرفة عامة للاختبار', 1, '{}', '2026-02-16 14:50:22', '2026-02-16 14:50:22'),
('kb-001-uuid', 'قاعدة معرفة عامة', 'قاعدة معرفة للاختبار، تحتوي أسئلة وأجوبة حقيقية', 1, '{}', '2026-02-16 14:51:37', '2026-02-16 14:51:37');

-- --------------------------------------------------------

--
-- Table structure for table `ai_messages`
--

CREATE TABLE `ai_messages` (
  `id` char(36) NOT NULL,
  `thread_id` char(36) DEFAULT NULL,
  `role` varchar(20) NOT NULL,
  `content` text NOT NULL,
  `content_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`content_json`)),
  `model` varchar(100) DEFAULT NULL,
  `tokens` int(11) DEFAULT NULL,
  `latency_ms` int(11) DEFAULT NULL,
  `citations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`citations`)),
  `tool_calls` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tool_calls`)),
  `language` varchar(8) DEFAULT 'ar',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ai_messages`
--

--
-- Table structure for table `ai_message_files`
--

CREATE TABLE `ai_message_files` (
  `message_id` char(36) NOT NULL,
  `file_id` char(36) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ai_message_files`
--

INSERT INTO `ai_message_files` (`message_id`, `file_id`) VALUES
('msg-0002-uuid', 'file-0001-uuid');

-- --------------------------------------------------------

--
-- Table structure for table `ai_threads`
--

CREATE TABLE `ai_threads` (
  `id` char(36) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT '{}' CHECK (json_valid(`metadata`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ai_threads`
--

--
-- Table structure for table `ai_thread_memory`
--

CREATE TABLE `ai_thread_memory` (
  `thread_id` char(36) NOT NULL,
  `summary` text DEFAULT NULL,
  `key_facts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`key_facts`)),
  `last_updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ai_thread_memory`
--


--
-- Table structure for table `ai_usage_logs`
--

CREATE TABLE `ai_usage_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `thread_id` char(36) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `tokens_input` int(11) DEFAULT NULL,
  `tokens_output` int(11) DEFAULT NULL,
  `cost_usd` decimal(10,6) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
--
-- Table structure for table `ai_vision_analyses`
--

CREATE TABLE `ai_vision_analyses` (
  `id` char(36) NOT NULL,
  `file_id` char(36) DEFAULT NULL,
  `message_id` char(36) DEFAULT NULL,
  `extracted_text` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `structured_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`structured_data`)),
  `embedding` blob DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ai_vision_analyses`
--

INSERT INTO `ai_vision_analyses` (`id`, `file_id`, `message_id`, `extracted_text`, `description`, `structured_data`, `embedding`, `created_at`) VALUES
('vision-0001-uuid', 'file-0001-uuid', NULL, 'مقدمة النظام مستخرجة من PDF', 'OCR تجريبي', NULL, NULL, '2026-02-16 14:50:22');

-- --------------------------------------------------------

--
-- Table structure for table `api_tokens`
--

CREATE TABLE `api_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `token` varchar(128) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `scopes` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `api_tokens`
--

INSERT INTO `api_tokens` (`id`, `user_id`, `token`, `name`, `scopes`, `is_active`, `created_at`, `last_used_at`) VALUES
(1, 7, 'test-token-1234567890', 'local-test', NULL, 1, '2025-12-28 21:56:56', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `attribute_types`
--

CREATE TABLE `attribute_types` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `has_values` tinyint(1) DEFAULT 0,
  `is_multi` tinyint(1) DEFAULT 0,
  `is_visual` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `attribute_types`
--
-- --------------------------------------------------------

--
-- Table structure for table `auctions`
--

CREATE TABLE `auctions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `currency_id` smallint(5) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(500) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `auction_type` enum('normal','reserve','buy_now','dutch','sealed_bid') DEFAULT 'normal',
  `status` enum('draft','scheduled','active','paused','ended','cancelled','sold') DEFAULT 'draft',
  `starting_price` decimal(15,2) NOT NULL,
  `reserve_price` decimal(15,2) DEFAULT NULL,
  `current_price` decimal(15,2) NOT NULL,
  `buy_now_price` decimal(15,2) DEFAULT NULL,
  `bid_increment` decimal(15,2) NOT NULL DEFAULT 5.00,
  `auto_bid_enabled` tinyint(1) DEFAULT 1,
  `total_bids` int(11) DEFAULT 0,
  `total_bidders` int(11) DEFAULT 0,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `auto_extend` tinyint(1) DEFAULT 1,
  `extend_minutes` int(11) DEFAULT 5,
  `min_extend_bid_time` int(11) DEFAULT 5,
  `winner_user_id` int(11) DEFAULT NULL,
  `winner_bid_id` bigint(20) DEFAULT NULL,
  `winning_amount` decimal(15,2) DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `condition_type` enum('new','like_new','very_good','good','acceptable','for_parts') DEFAULT 'new',
  `quantity` int(11) DEFAULT 1,
  `shipping_cost` decimal(15,2) DEFAULT 0.00,
  `payment_deadline_hours` int(11) DEFAULT 48,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ended_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auctions`
--

INSERT INTO `auctions` (`id`, `currency_id`, `tenant_id`, `entity_id`, `product_id`, `title`, `slug`, `auction_type`, `status`, `starting_price`, `reserve_price`, `current_price`, `buy_now_price`, `bid_increment`, `auto_bid_enabled`, `total_bids`, `total_bidders`, `start_date`, `end_date`, `auto_extend`, `extend_minutes`, `min_extend_bid_time`, `winner_user_id`, `winner_bid_id`, `winning_amount`, `is_featured`, `condition_type`, `quantity`, `shipping_cost`, `payment_deadline_hours`, `notes`, `created_by`, `created_at`, `updated_at`, `ended_at`) VALUES
(2, 4, 1, 13, 44, 'uu', 'uu', 'normal', 'sold', 100.00, 100.00, 10.00, 10.00, 5.00, 1, 1, 1, '2026-03-05 12:05:00', '2026-03-19 12:05:00', 1, 5, 5, 1, 4, 10.00, 1, 'new', 1, 20.00, 48, NULL, 7, '2026-03-05 03:06:12', '2026-03-28 15:29:07', '2026-03-28 15:29:07');

-- --------------------------------------------------------

--
-- Table structure for table `auction_activity_log`
--

CREATE TABLE `auction_activity_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `auction_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `activity_type` enum('created','started','bid_placed','auto_bid_placed','outbid','extended','paused','resumed','ended','cancelled','winner_declared','payment_received','item_shipped') NOT NULL,
  `amount` decimal(15,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `auction_bids`
--

CREATE TABLE `auction_bids` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `auction_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `bid_amount` decimal(15,2) NOT NULL,
  `max_auto_bid` decimal(15,2) DEFAULT NULL,
  `bid_type` enum('manual','auto','buy_now') DEFAULT 'manual',
  `is_winning` tinyint(1) DEFAULT 0,
  `is_auto_outbid` tinyint(1) DEFAULT 0,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auction_bids`
--

INSERT INTO `auction_bids` (`id`, `auction_id`, `user_id`, `bid_amount`, `max_auto_bid`, `bid_type`, `is_winning`, `is_auto_outbid`, `ip_address`, `user_agent`, `created_at`) VALUES
(3, 2, 1, 105.00, NULL, 'manual', 0, 0, '5.195.235.133', NULL, '2026-03-05 03:07:49'),
(4, 2, 1, 10.00, NULL, 'buy_now', 1, 0, NULL, NULL, '2026-03-28 15:29:07');

-- --------------------------------------------------------

--
-- Table structure for table `auction_translations`
--

CREATE TABLE `auction_translations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `auction_id` bigint(20) UNSIGNED NOT NULL,
  `language_code` varchar(8) NOT NULL,
  `title` varchar(500) NOT NULL,
  `description` longtext DEFAULT NULL,
  `terms_conditions` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auction_translations`
--

INSERT INTO `auction_translations` (`id`, `auction_id`, `language_code`, `title`, `description`, `terms_conditions`) VALUES
(1, 1, 'ar', 'gg', 'gg', 'ggg');

-- --------------------------------------------------------

--
-- Table structure for table `auction_watchers`
--

CREATE TABLE `auction_watchers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `auction_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `notify_before_end` tinyint(1) DEFAULT 1,
  `notify_on_outbid` tinyint(1) DEFAULT 1,
  `notify_on_winner` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auction_watchers`
--

INSERT INTO `auction_watchers` (`id`, `auction_id`, `user_id`, `notify_before_end`, `notify_on_outbid`, `notify_on_winner`, `created_at`) VALUES
(4, 1, 7, 1, 1, 1, '2026-03-01 06:31:13'),
(5, 2, 1, 1, 1, 1, '2026-03-23 18:29:49');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `entity_type` varchar(64) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `entity_id` bigint(20) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `ip_address` varchar(45) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `old_values` longtext DEFAULT NULL COMMENT 'JSON snapshot of entity state BEFORE the change',
  `new_values` longtext DEFAULT NULL COMMENT 'JSON snapshot of entity state AFTER the change',
  `diff` longtext DEFAULT NULL COMMENT 'JSON array [{field,old,new}] showing exactly what changed',
  `metadata` longtext DEFAULT NULL COMMENT 'JSON object with arbitrary contextual metadata',
  `trace` longtext DEFAULT NULL COMMENT 'Optional debug stack trace / breadcrumb trail',
  `http_method` varchar(10) DEFAULT NULL COMMENT 'HTTP method of the originating request',
  `http_url` varchar(2048) DEFAULT NULL COMMENT 'Request path and query string',
  `session_id` varchar(128) DEFAULT NULL COMMENT 'PHP session identifier',
  `request_id` varchar(64) DEFAULT NULL COMMENT 'Unique request identifier for distributed tracing',
  `duration_ms` int(10) UNSIGNED DEFAULT NULL COMMENT 'Operation duration in milliseconds',
  `created_at` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'When the log entry was created'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

-- Table structure for table `auto_bid_settings`
--

CREATE TABLE `auto_bid_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `auction_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `max_bid_amount` decimal(15,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `total_auto_bids` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `auto_bid_settings`
--

INSERT INTO `auto_bid_settings` (`id`, `auction_id`, `user_id`, `max_bid_amount`, `is_active`, `total_auto_bids`, `created_at`, `updated_at`) VALUES
(4, 2, 1, 110.00, 1, 0, '2026-03-23 18:29:34', '2026-03-23 18:29:43');

-- --------------------------------------------------------

--
-- Table structure for table `bad_words`
--

CREATE TABLE `bad_words` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `word` varchar(255) NOT NULL,
  `severity` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `is_regex` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bad_words`
--

-- Table structure for table `bad_word_translations`
--

CREATE TABLE `bad_word_translations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `bad_word_id` bigint(20) UNSIGNED NOT NULL,
  `language_code` char(5) NOT NULL,
  `word` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bad_word_translations`
--

-- Table structure for table `bank_accounts`
--

CREATE TABLE `bank_accounts` (
  `id` bigint(20) NOT NULL,
  `owner_type` enum('user','vendor') CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `owner_id` bigint(20) NOT NULL,
  `bank_name` varchar(200) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `account_holder_name` varchar(200) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `account_number_masked` varchar(64) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `account_number_encrypted` varbinary(1024) DEFAULT NULL,
  `iban` varchar(64) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `bic_swift` varchar(32) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `currency` char(3) CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT 'USD',
  `is_primary` tinyint(1) DEFAULT 0,
  `verified` tinyint(1) DEFAULT 0,
  `verification_method` enum('micro_deposit','instant','manual') CHARACTER SET latin1 COLLATE latin1_swedish_ci DEFAULT NULL,
  `verification_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `banners`
--

CREATE TABLE `banners` (
  `id` bigint(20) NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `subtitle` varchar(500) DEFAULT NULL,
  `link_url` varchar(500) DEFAULT NULL,
  `link_text` varchar(100) DEFAULT NULL,
  `position` enum('homepage_main','homepage_secondary','category_top','product_sidebar','footer','popup','other') DEFAULT 'homepage_main',
  `theme_id` bigint(20) DEFAULT NULL,
  `background_color` varchar(7) DEFAULT '#FFFFFF',
  `text_color` varchar(7) DEFAULT '#000000',
  `button_style` varchar(100) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `banners`
--


-- --------------------------------------------------------

--
-- Table structure for table `banner_translations`
--

CREATE TABLE `banner_translations` (
  `id` bigint(20) NOT NULL,
  `banner_id` bigint(20) NOT NULL,
  `language_code` varchar(8) NOT NULL,
  `title` varchar(255) NOT NULL,
  `subtitle` varchar(500) DEFAULT NULL,
  `link_text` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `banner_translations`
--

INSERT INTO `banner_translations` (`id`, `banner_id`, `language_code`, `title`, `subtitle`, `link_text`) VALUES
(0, 4, 'en', 'hh', 'hh', 'hh'),
(12, 8, 'ar', 'ةة', 'بب', '22');

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `parent_entity_type` enum('tenant','vendor') NOT NULL,
  `parent_entity_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `branch_code` varchar(50) DEFAULT NULL,
  `status` enum('active','suspended') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `brands`
--

CREATE TABLE `brands` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `slug` varchar(255) NOT NULL,
  `website_url` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `brands`
--


--
-- Table structure for table `brand_translations`
--

CREATE TABLE `brand_translations` (
  `id` bigint(20) NOT NULL,
  `brand_id` bigint(20) NOT NULL,
  `language_code` varchar(8) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `brand_translations`
--

-- --------------------------------------------------------

--
-- Table structure for table `button_styles`
--

CREATE TABLE `button_styles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `theme_id` bigint(20) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `button_type` enum('primary','secondary','success','danger','warning','info','outline','link') NOT NULL,
  `background_color` varchar(200) NOT NULL,
  `text_color` varchar(200) NOT NULL,
  `border_color` varchar(200) DEFAULT NULL,
  `border_width` int(11) DEFAULT 0,
  `border_radius` int(11) DEFAULT 4,
  `padding` varchar(50) DEFAULT '10px 20px',
  `font_size` varchar(50) DEFAULT '14px',
  `font_weight` varchar(50) DEFAULT 'normal',
  `hover_background_color` varchar(200) DEFAULT NULL,
  `hover_text_color` varchar(200) DEFAULT NULL,
  `hover_border_color` varchar(200) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `button_styles`
--

-- Table structure for table `card_styles`
--

CREATE TABLE `card_styles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `theme_id` bigint(20) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `card_type` varchar(50) NOT NULL DEFAULT '',
  `background_color` varchar(200) DEFAULT NULL,
  `border_color` varchar(200) DEFAULT NULL,
  `text_color` varchar(200) DEFAULT NULL,
  `border_width` int(11) DEFAULT 1,
  `border_radius` int(11) DEFAULT 8,
  `shadow_style` varchar(100) DEFAULT 'none',
  `padding` varchar(50) DEFAULT '16px',
  `hover_effect` varchar(50) DEFAULT 'none',
  `text_align` varchar(10) DEFAULT 'left',
  `image_aspect_ratio` varchar(50) DEFAULT '1:1',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `card_styles`
--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL DEFAULT 1,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `device_id` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `total_items` int(11) DEFAULT 0,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `shipping_cost` decimal(15,2) DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `currency_code` varchar(8) DEFAULT 'SAR',
  `coupon_code` varchar(100) DEFAULT NULL,
  `discount_id` bigint(20) UNSIGNED DEFAULT NULL,
  `loyalty_points_used` int(11) DEFAULT 0,
  `status` enum('active','abandoned','converted','expired','locked') DEFAULT NULL,
  `last_activity_at` datetime DEFAULT current_timestamp(),
  `converted_to_order_id` bigint(20) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `carts`
-- Table structure for table `cart_events`
--

CREATE TABLE `cart_events` (
  `id` bigint(20) NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `cart_id` bigint(20) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `actor_type` enum('user','admin','system') NOT NULL,
  `actor_id` bigint(20) DEFAULT NULL,
  `related_item_id` bigint(20) DEFAULT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_value`)),
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_value`)),
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `cart_id` bigint(20) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `product_variant_id` bigint(20) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `product_name` varchar(500) NOT NULL,
  `sku` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(15,2) NOT NULL,
  `sale_price` decimal(15,2) DEFAULT NULL,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) NOT NULL,
  `total` decimal(15,2) NOT NULL,
  `currency_code` varchar(8) DEFAULT 'SAR',
  `selected_attributes` text DEFAULT NULL,
  `special_instructions` text DEFAULT NULL,
  `is_gift` tinyint(1) DEFAULT 0,
  `gift_message` text DEFAULT NULL,
  `added_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cart_items`
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `parent_id` bigint(20) DEFAULT NULL,
  `slug` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

-- Table structure for table `category_attributes`
--

CREATE TABLE `category_attributes` (
  `id` bigint(20) NOT NULL,
  `category_id` bigint(20) NOT NULL,
  `attribute_id` bigint(20) NOT NULL,
  `is_required` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `category_attribute_translations`
--

CREATE TABLE `category_attribute_translations` (
  `id` bigint(20) NOT NULL,
  `category_attribute_id` bigint(20) NOT NULL,
  `language_code` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `category_translations`
--

CREATE TABLE `category_translations` (
  `id` bigint(20) NOT NULL,
  `category_id` bigint(20) NOT NULL,
  `language_code` varchar(8) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `meta_keywords` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `category_translations`

-- Table structure for table `cities`
--

CREATE TABLE `cities` (
  `id` int(11) NOT NULL,
  `country_id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `state` varchar(200) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(11,7) DEFAULT NULL,
  `location` point NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cities`

--
-- Table structure for table `city_translations`
--

CREATE TABLE `city_translations` (
  `city_id` int(11) NOT NULL,
  `language_code` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `city_translations`

-- Table structure for table `color_settings`
--

CREATE TABLE `color_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `theme_id` bigint(20) DEFAULT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_name` varchar(255) NOT NULL,
  `color_value` varchar(200) NOT NULL,
  `category` varchar(50) DEFAULT 'other',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `color_settings`
--
-- Table structure for table `commission_credit_notes`
--

CREATE TABLE `commission_credit_notes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `credit_note_number` varchar(50) NOT NULL,
  `invoice_id` bigint(20) UNSIGNED NOT NULL,
  `related_transaction_id` bigint(20) UNSIGNED NOT NULL,
  `credit_amount` decimal(15,2) NOT NULL,
  `credit_commission` decimal(15,2) NOT NULL,
  `credit_vat` decimal(15,2) NOT NULL,
  `net_credit` decimal(15,2) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('draft','issued','cancelled') DEFAULT 'draft',
  `issued_at` datetime DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `issued_by` int(10) UNSIGNED DEFAULT NULL,
  `cancelled_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `commission_credit_notes`
--

INSERT INTO `commission_credit_notes` (`id`, `tenant_id`, `credit_note_number`, `invoice_id`, `related_transaction_id`, `credit_amount`, `credit_commission`, `credit_vat`, `net_credit`, `reason`, `status`, `issued_at`, `created_by`, `issued_by`, `cancelled_by`, `created_at`) VALUES
(9, 1, 'CCN-20260214-00001', 2, 2, 500.00, 2.00, 2.00, 2.00, NULL, 'draft', NULL, NULL, NULL, NULL, '2026-02-14 04:28:21');

-- --------------------------------------------------------

--
-- Table structure for table `commission_invoices`
--

CREATE TABLE `commission_invoices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `invoice_type` enum('monthly','quarterly','custom') NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `total_orders` int(11) DEFAULT 0,
  `total_orders_amount` decimal(15,2) DEFAULT 0.00,
  `total_commission` decimal(15,2) DEFAULT 0.00,
  `total_vat` decimal(15,2) DEFAULT 0.00,
  `grand_total` decimal(15,2) NOT NULL,
  `amount_paid` decimal(15,2) DEFAULT 0.00,
  `status` enum('draft','issued','partially_paid','paid','cancelled') DEFAULT 'draft',
  `issued_at` datetime DEFAULT NULL,
  `due_date` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `is_locked` tinyint(1) DEFAULT 0,
  `locked_at` datetime DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `issued_by` int(10) UNSIGNED DEFAULT NULL,
  `cancelled_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `commission_invoices`

--
-- Table structure for table `commission_invoice_items`
--

CREATE TABLE `commission_invoice_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_id` bigint(20) UNSIGNED NOT NULL,
  `transaction_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `order_date` datetime NOT NULL,
  `order_amount` decimal(15,2) NOT NULL,
  `commission_amount` decimal(15,2) NOT NULL,
  `vat_amount` decimal(15,2) DEFAULT 0.00,
  `net_commission` decimal(15,2) NOT NULL,
  `transaction_type` enum('sale','refund') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `commission_invoice_items`
--
-- Table structure for table `commission_payments`
--

CREATE TABLE `commission_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `commission_invoice_id` bigint(20) UNSIGNED NOT NULL,
  `payment_number` varchar(50) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `amount_paid` decimal(15,2) NOT NULL,
  `paid_at` datetime NOT NULL,
  `is_cancelled` tinyint(1) DEFAULT 0,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` varchar(255) DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `cancelled_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `commission_payments`
-- --------------------------------------------------------

--
-- Table structure for table `commission_transactions`
--

CREATE TABLE `commission_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `order_date` datetime NOT NULL,
  `transaction_type` enum('sale','refund') NOT NULL DEFAULT 'sale',
  `order_amount` decimal(15,2) NOT NULL,
  `commission_amount` decimal(15,2) NOT NULL,
  `vat_amount` decimal(15,2) DEFAULT 0.00,
  `net_commission` decimal(15,2) NOT NULL,
  `status` enum('pending','invoiced','paid','cancelled') DEFAULT 'pending',
  `is_locked` tinyint(1) DEFAULT 0,
  `locked_at` datetime DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `cancelled_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `commission_transactions`

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` enum('new','read','replied','closed') NOT NULL DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `tenant_id`, `user_id`, `name`, `email`, `subject`, `message`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 7, 'admin28332', 'zedanmahmoud1000@gmail.com', 'Urgent: Restore Deleted /api/v1/models/ Folder', 'ببب', 'new', '2026-03-27 19:03:05', '2026-03-27 19:03:05');

-- --------------------------------------------------------

--
-- Table structure for table `core_events`
--

CREATE TABLE `core_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `entity_type` enum('product','entity','brand','category','job','auction') NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `event_type` enum('view','click','favorite','contact','add_to_cart','purchase') NOT NULL,
  `value` decimal(10,2) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `core_events`
--
-- Table structure for table `countries`
--

CREATE TABLE `countries` (
  `id` int(11) NOT NULL,
  `iso2` char(2) DEFAULT NULL,
  `iso3` char(3) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `currency_code` varchar(8) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `countries`
-- Table structure for table `country_taxes`
--

CREATE TABLE `country_taxes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `country_id` int(11) NOT NULL,
  `tax_class_id` bigint(20) UNSIGNED NOT NULL,
  `tax_name` varchar(100) NOT NULL,
  `tax_type` enum('vat','gst','sales_tax','customs') DEFAULT 'vat',
  `tax_rate` decimal(5,2) NOT NULL,
  `is_inclusive` tinyint(1) DEFAULT 0,
  `min_sales` decimal(15,2) DEFAULT 0.00,
  `is_export_exempt` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `effective_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table structure for table `country_translations`
--

CREATE TABLE `country_translations` (
  `country_id` int(11) NOT NULL,
  `language_code` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `country_translations`

-- Table structure for table `coupons`
--

CREATE TABLE `coupons` (
  `id` bigint(20) NOT NULL,
  `code` varchar(50) NOT NULL,
  `promotion_id` bigint(20) DEFAULT NULL,
  `coupon_type` enum('percentage','fixed_amount','free_shipping','gift') NOT NULL,
  `discount_value` decimal(15,2) NOT NULL,
  `min_purchase_amount` decimal(15,2) DEFAULT NULL,
  `max_discount_amount` decimal(15,2) DEFAULT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `usage_per_customer` int(11) DEFAULT 1,
  `current_usage` int(11) DEFAULT 0,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `country_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_public` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coupon_usage`
--

CREATE TABLE `coupon_usage` (
  `id` bigint(20) NOT NULL,
  `coupon_id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_id` bigint(20) DEFAULT NULL,
  `discount_amount` decimal(15,2) NOT NULL,
  `currency_code` varchar(8) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `used_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `currencies`
--

CREATE TABLE `currencies` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `code` char(3) NOT NULL,
  `name` varchar(50) NOT NULL,
  `symbol` varchar(10) NOT NULL,
  `symbol_position` enum('before','after') DEFAULT 'before',
  `decimal_places` tinyint(3) UNSIGNED DEFAULT 2,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `currencies`
-- --------------------------------------------------------

--
-- Table structure for table `customer_loyalty_points`
--

CREATE TABLE `customer_loyalty_points` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_points` int(11) DEFAULT 0,
  `available_points` int(11) DEFAULT 0,
  `pending_points` int(11) DEFAULT 0,
  `used_points` int(11) DEFAULT 0,
  `expired_points` int(11) DEFAULT 0,
  `lifetime_points` int(11) DEFAULT 0,
  `current_tier_id` bigint(20) DEFAULT NULL,
  `tier_expiry_date` date DEFAULT NULL,
  `last_earned_at` datetime DEFAULT NULL,
  `last_redeemed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `delivery_orders`
--

CREATE TABLE `delivery_orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `provider_id` bigint(20) UNSIGNED DEFAULT NULL,
  `pickup_address_id` bigint(20) UNSIGNED NOT NULL,
  `dropoff_address_id` bigint(20) UNSIGNED NOT NULL,
  `delivery_zone_id` bigint(20) UNSIGNED DEFAULT NULL,
  `delivery_status` enum('pending','assigned','accepted','picked_up','on_the_way','delivered','cancelled') DEFAULT 'pending',
  `cancelled_by` enum('customer','provider','admin','system') DEFAULT NULL,
  `cancellation_reason` varchar(255) DEFAULT NULL,
  `delivery_fee` decimal(15,2) DEFAULT 0.00,
  `calculated_fee` decimal(15,2) NOT NULL DEFAULT 0.00,
  `provider_payout` decimal(15,2) NOT NULL DEFAULT 0.00,
  `assigned_at` datetime DEFAULT NULL,
  `rejection_count` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `picked_up_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `delivery_orders`
--
-- Table structure for table `delivery_providers`
--

CREATE TABLE `delivery_providers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `tenant_user_id` bigint(20) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `provider_type` enum('company','entity_driver','independent_driver') NOT NULL,
  `vehicle_type` enum('bike','car','van','truck') NOT NULL DEFAULT 'bike',
  `license_number` varchar(100) DEFAULT NULL,
  `is_online` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `rating` decimal(3,2) UNSIGNED NOT NULL DEFAULT 0.00,
  `total_deliveries` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `delivery_providers`
-- --------------------------------------------------------

--
-- Table structure for table `delivery_tracking`
--

CREATE TABLE `delivery_tracking` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `delivery_order_id` bigint(20) UNSIGNED NOT NULL,
  `provider_id` bigint(20) UNSIGNED DEFAULT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(11,7) NOT NULL,
  `status_note` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_zones`
--

CREATE TABLE `delivery_zones` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `provider_id` bigint(20) UNSIGNED NOT NULL,
  `zone_name` varchar(255) NOT NULL,
  `zone_type` enum('city','district','radius','polygon') NOT NULL,
  `city_id` int(11) DEFAULT NULL,
  `center_lat` decimal(10,7) DEFAULT NULL,
  `center_lng` decimal(11,7) DEFAULT NULL,
  `radius_km` decimal(6,2) DEFAULT NULL,
  `delivery_fee` decimal(15,2) NOT NULL,
  `free_delivery_over` decimal(15,2) DEFAULT NULL,
  `min_order_value` decimal(15,2) DEFAULT NULL,
  `estimated_minutes` int(10) UNSIGNED NOT NULL DEFAULT 45,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `zone_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `delivery_zones`
-- --------------------------------------------------------

--
-- Table structure for table `deposit_requests`
--

CREATE TABLE `deposit_requests` (
  `id` bigint(20) NOT NULL,
  `request_number` varchar(100) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency_code` varchar(8) DEFAULT 'SAR',
  `deposit_method` enum('bank_transfer','credit_card','cash','online_payment','other') NOT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `transfer_reference` varchar(255) DEFAULT NULL,
  `receipt_url` varchar(500) DEFAULT NULL,
  `status` enum('pending','verified','approved','rejected','cancelled') DEFAULT 'pending',
  `requested_at` datetime DEFAULT current_timestamp(),
  `verified_at` datetime DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `transaction_id` bigint(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `design_settings`
--

CREATE TABLE `design_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `theme_id` bigint(20) DEFAULT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_name` varchar(255) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` varchar(50) DEFAULT 'text',
  `category` varchar(50) DEFAULT 'other',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `design_settings`
--


-- Table structure for table `discounts`
--

CREATE TABLE `discounts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(50) NOT NULL,
  `code` varchar(100) DEFAULT NULL,
  `auto_apply` tinyint(1) NOT NULL DEFAULT 0,
  `priority` int(11) NOT NULL DEFAULT 0,
  `is_stackable` tinyint(1) NOT NULL DEFAULT 0,
  `currency_code` char(3) DEFAULT NULL,
  `max_redemptions` int(11) DEFAULT NULL,
  `max_redemptions_per_user` int(11) DEFAULT NULL,
  `current_redemptions` int(11) NOT NULL DEFAULT 0,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `discounts`
--
-- --------------------------------------------------------

--
-- Table structure for table `discount_actions`
--

CREATE TABLE `discount_actions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `discount_id` bigint(20) UNSIGNED NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `action_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`action_value`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `discount_actions`
--

INSERT INTO `discount_actions` (`id`, `discount_id`, `action_type`, `action_value`) VALUES
(1, 1, 'buy_x_get_y', '200'),
(6, 2, 'percentage', '100');

-- --------------------------------------------------------

--
-- Table structure for table `discount_conditions`
--

CREATE TABLE `discount_conditions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `discount_id` bigint(20) UNSIGNED NOT NULL,
  `condition_type` varchar(100) NOT NULL,
  `operator` varchar(20) NOT NULL DEFAULT '=',
  `condition_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`condition_value`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `discount_conditions`
--

INSERT INTO `discount_conditions` (`id`, `discount_id`, `condition_type`, `operator`, `condition_value`) VALUES
(1, 1, '6', '20', '500'),
(2, 2, 'min_cart_total', '>', '200');

-- --------------------------------------------------------

--
-- Table structure for table `discount_exclusions`
--

CREATE TABLE `discount_exclusions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `discount_id` bigint(20) UNSIGNED NOT NULL,
  `excluded_discount_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `discount_exclusions`
--

INSERT INTO `discount_exclusions` (`id`, `discount_id`, `excluded_discount_id`) VALUES
(1, 1, 2),
(2, 2, 1);

-- --------------------------------------------------------

--
-- Table structure for table `discount_redemptions`
--

CREATE TABLE `discount_redemptions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `discount_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `amount_discounted` decimal(15,2) NOT NULL DEFAULT 0.00,
  `currency_code` char(3) NOT NULL,
  `redeemed_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `discount_scopes`
--

CREATE TABLE `discount_scopes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `discount_id` bigint(20) UNSIGNED NOT NULL,
  `scope_type` varchar(50) NOT NULL,
  `scope_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `discount_scopes`
--

INSERT INTO `discount_scopes` (`id`, `discount_id`, `scope_type`, `scope_id`) VALUES
(4, 1, 'product', 1),
(8, 2, 'entity', 18);

-- --------------------------------------------------------

--
-- Table structure for table `discount_translations`
--

CREATE TABLE `discount_translations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `discount_id` bigint(20) UNSIGNED NOT NULL,
  `language_code` varchar(10) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `terms_conditions` text DEFAULT NULL,
  `marketing_badge` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `discount_translations`
--

INSERT INTO `discount_translations` (`id`, `discount_id`, `language_code`, `name`, `description`, `terms_conditions`, `marketing_badge`) VALUES
(1, 1, 'ar', 'اشتري 2 واحصل علي 1', 'اشتري 2 واحصل علي 1', 'اشتري 2 واحصل علي 1', 'اشتري 2 واحصل علي 1'),
(2, 2, 'ar', 'اا', 'اا', 'اا', 'ا'),
(3, 2, 'zh', 'اا', 'اا', 'اا', 'اا');

-- --------------------------------------------------------

--
-- Table structure for table `driver_documents`
--

CREATE TABLE `driver_documents` (
  `id` bigint(20) NOT NULL,
  `driver_id` bigint(20) NOT NULL,
  `document_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `file_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'pending',
  `uploaded_at` datetime DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `driver_locations`
--

CREATE TABLE `driver_locations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `provider_id` bigint(20) UNSIGNED NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(11,7) NOT NULL,
  `location` point NOT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `driver_locations`
--

INSERT INTO `driver_locations` (`id`, `provider_id`, `latitude`, `longitude`, `location`, `updated_at`) VALUES
(1, 1, 25.3646837, 55.4056545, 0x000000000101000000d47c957cecb34b4060e234e95b5d3940, '2026-03-05 03:15:18');

-- --------------------------------------------------------

--
-- Table structure for table `driver_tokens`
--

CREATE TABLE `driver_tokens` (
  `id` bigint(20) NOT NULL,
  `driver_id` bigint(20) NOT NULL,
  `token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `scopes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `revoked` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_templates`
--

CREATE TABLE `email_templates` (
  `id` bigint(20) NOT NULL,
  `template_key` varchar(100) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `template_name` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `template_name_ar` varchar(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `subject` varchar(500) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `subject_ar` varchar(500) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `body` text CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `body_ar` text CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `entities`
--

CREATE TABLE `entities` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `branch_code` varchar(50) DEFAULT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `store_name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `vendor_type` enum('product_seller','service_provider','both') DEFAULT 'product_seller',
  `store_type` enum('individual','company','brand') DEFAULT 'individual',
  `registration_number` varchar(100) DEFAULT NULL,
  `tax_number` varchar(100) DEFAULT NULL,
  `phone` varchar(45) NOT NULL,
  `mobile` varchar(45) DEFAULT NULL,
  `email` varchar(191) NOT NULL,
  `website_url` varchar(500) DEFAULT NULL,
  `timezone_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('pending','approved','suspended','rejected') DEFAULT 'pending',
  `suspension_reason` text DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `joined_at` datetime DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `entities`
--

-- --------------------------------------------------------

--
-- Table structure for table `entities_attributes`
--

CREATE TABLE `entities_attributes` (
  `id` bigint(20) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `attribute_type` enum('text','number','select','boolean') DEFAULT 'text',
  `is_required` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `entities_attributes`
--

INSERT INTO `entities_attributes` (`id`, `slug`, `attribute_type`, `is_required`, `sort_order`, `created_at`) VALUES
(1, 'number_of_branches', 'number', 0, 1, '2025-12-14 02:06:50'),
(2, 'has_physical_store', 'boolean', 0, 2, '2025-12-14 02:06:50'),
(3, 'store_size_sqm', 'number', 0, 3, '2025-12-14 02:06:50'),
(4, 'number_of_employees', 'number', 0, 4, '2025-12-14 02:06:50'),
(5, 'parking_spaces', 'number', 0, 5, '2025-12-14 02:06:50'),
(6, 'has_wheelchair_access', 'boolean', 0, 6, '2025-12-14 02:06:50');

-- --------------------------------------------------------

--
-- Table structure for table `entities_attribute_translations`
--

CREATE TABLE `entities_attribute_translations` (
  `id` bigint(20) NOT NULL,
  `attribute_id` bigint(20) NOT NULL,
  `language_code` varchar(8) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `entities_attribute_translations`
--

-- Table structure for table `entities_attribute_values`
--

CREATE TABLE `entities_attribute_values` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `attribute_id` bigint(20) NOT NULL,
  `value` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `entities_attribute_values`
--

INSERT INTO `entities_attribute_values` (`id`, `entity_id`, `attribute_id`, `value`) VALUES
(7, 3, 1, '5'),
(36, 4, 2, '1'),
(37, 4, 1, '5'),
(51, 1, 2, 'نعم'),
(52, 1, 4, '20'),
(53, 1, 1, '5'),
(54, 8, 1, '5');

-- --------------------------------------------------------

--
-- Table structure for table `entities_working_hours`
--

CREATE TABLE `entities_working_hours` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `day_of_week` tinyint(3) UNSIGNED NOT NULL COMMENT '0=Sunday ... 6=Saturday',
  `is_open` tinyint(1) NOT NULL DEFAULT 1,
  `open_time` time DEFAULT NULL,
  `close_time` time DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `entities_working_hours`

--
-- Table structure for table `entity_bank_accounts`
--

CREATE TABLE `entity_bank_accounts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `bank_name` varchar(255) NOT NULL,
  `account_holder_name` varchar(255) NOT NULL,
  `account_number` varbinary(255) NOT NULL,
  `iban` varbinary(255) DEFAULT NULL,
  `swift_code` varbinary(255) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `entity_bank_accounts`
--

--
-- Table structure for table `entity_categories`
--

CREATE TABLE `entity_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `entity_id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `entity_categories`
--

INSERT INTO `entity_categories` (`id`, `entity_id`, `category_id`, `tenant_id`, `is_active`, `created_at`) VALUES
(1, 1, 1, 1, 1, '2026-03-31 19:09:28'),
(2, 1, 100, 1, 1, '2026-03-31 19:11:42');

-- --------------------------------------------------------

--
-- Table structure for table `entity_delivery_relations`
--

CREATE TABLE `entity_delivery_relations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `delivery_entity_id` bigint(20) UNSIGNED NOT NULL,
  `target_entity_id` bigint(20) UNSIGNED NOT NULL,
  `commission_type` enum('fixed','percentage') DEFAULT 'percentage',
  `commission_value` decimal(10,2) DEFAULT 0.00,
  `priority` int(11) DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `entity_financial_balances`
--

CREATE TABLE `entity_financial_balances` (
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `total_transactions` int(11) DEFAULT 0,
  `total_sales_count` int(11) DEFAULT 0,
  `total_refunds_count` int(11) DEFAULT 0,
  `total_sales_amount` decimal(15,2) DEFAULT 0.00,
  `total_refunds_amount` decimal(15,2) DEFAULT 0.00,
  `net_sales` decimal(15,2) DEFAULT 0.00,
  `total_commission` decimal(15,2) DEFAULT 0.00,
  `total_vat` decimal(15,2) DEFAULT 0.00,
  `total_net_commission` decimal(15,2) DEFAULT 0.00,
  `total_invoiced` decimal(15,2) DEFAULT 0.00,
  `total_paid` decimal(15,2) DEFAULT 0.00,
  `total_balance` decimal(15,2) DEFAULT 0.00,
  `pending_balance` decimal(15,2) DEFAULT 0.00,
  `invoiced_balance` decimal(15,2) DEFAULT 0.00,
  `total_invoices` int(11) DEFAULT 0,
  `total_payments` int(11) DEFAULT 0,
  `total_credit_notes` int(11) DEFAULT 0,
  `last_transaction_date` datetime DEFAULT NULL,
  `last_invoice_date` datetime DEFAULT NULL,
  `last_payment_date` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `entity_financial_balances`
--
--
-- Table structure for table `entity_logs`
--

CREATE TABLE `entity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` enum('create','update','delete') NOT NULL,
  `changes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`changes`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `entity_logs`
-- Table structure for table `entity_payment_methods`
--

CREATE TABLE `entity_payment_methods` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `account_email` varbinary(191) DEFAULT NULL,
  `account_id` varbinary(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `payment_method_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `entity_payment_methods`
--

INSERT INTO `entity_payment_methods` (`id`, `entity_id`, `account_email`, `account_id`, `is_active`, `created_at`, `payment_method_id`) VALUES
(1, 1, 0x415745314e617178733757727977735851686658526c584e69333038796d2b415339615a5a776b6c6934354f6376412f6e57596c5a4d4c6a69514e70366b574e435566373865493d, 0x4151594544314949324c6552764a62543558456a37613461446d594c714539375756625a4f79335078333332386f74495053563848484f796e4132647142553d, 1, '2026-02-13 10:18:18', 5);

-- --------------------------------------------------------

--
-- Table structure for table `entity_pickup_points`
--

CREATE TABLE `entity_pickup_points` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(11,7) DEFAULT NULL,
  `city_id` int(11) DEFAULT NULL,
  `working_hours` varchar(255) DEFAULT NULL,
  `phone` varchar(45) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `entity_policies`
--

CREATE TABLE `entity_policies` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(50) NOT NULL COMMENT 'Policy type: refund, privacy, shipping, terms',
  `language_code` varchar(10) NOT NULL DEFAULT 'en',
  `title` varchar(255) NOT NULL,
  `content` text DEFAULT NULL COMMENT 'Policy content (HTML or plain text)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `entity_products`
--

CREATE TABLE `entity_products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `low_stock_threshold` int(11) DEFAULT 5,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `entity_products`

-- Table structure for table `entity_product_variants`
--

CREATE TABLE `entity_product_variants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `variant_id` bigint(20) UNSIGNED NOT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `low_stock_threshold` int(11) NOT NULL DEFAULT 5,
  `manage_stock` tinyint(1) NOT NULL DEFAULT 1,
  `stock_status` enum('in_stock','out_of_stock','unlimited') NOT NULL DEFAULT 'in_stock',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `entity_product_variants`
--

INSERT INTO `entity_product_variants` (`id`, `tenant_id`, `entity_id`, `product_id`, `variant_id`, `stock_quantity`, `low_stock_threshold`, `manage_stock`, `stock_status`, `is_active`, `is_featured`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 50, 9, 10, 5, 1, 'in_stock', 1, 1, '2026-03-29 16:55:27', '2026-03-29 16:55:27');

-- --------------------------------------------------------

--
-- Table structure for table `entity_ratings`
--

CREATE TABLE `entity_ratings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED DEFAULT NULL,
  `rating` decimal(3,2) NOT NULL,
  `review` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `entity_ratings`
--

INSERT INTO `entity_ratings` (`id`, `entity_id`, `user_id`, `rating`, `review`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 7, 5.00, 'يي', 1, '2026-03-01 07:37:08', '2026-03-28 17:02:22');

-- --------------------------------------------------------

--
-- Table structure for table `entity_settings`
--

CREATE TABLE `entity_settings` (
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `auto_accept_orders` tinyint(1) DEFAULT 0,
  `allow_cod` tinyint(1) DEFAULT 0,
  `min_order_amount` decimal(10,2) DEFAULT 0.00,
  `preparation_time_minutes` int(11) DEFAULT 0,
  `allow_online_booking` tinyint(1) DEFAULT 0,
  `booking_window_days` int(11) DEFAULT 0,
  `max_bookings_per_slot` int(11) DEFAULT 0,
  `booking_cancellation_allowed` tinyint(1) DEFAULT 1,
  `allow_preorders` tinyint(1) DEFAULT 0,
  `max_daily_orders` int(11) DEFAULT 0,
  `is_visible` tinyint(1) DEFAULT 1,
  `maintenance_mode` tinyint(1) DEFAULT 0,
  `show_reviews` tinyint(1) DEFAULT 1,
  `show_contact_info` tinyint(1) DEFAULT 1,
  `featured_in_app` tinyint(1) DEFAULT 0,
  `default_payment_method` varchar(50) DEFAULT NULL,
  `allow_multiple_payment_methods` tinyint(1) DEFAULT 1,
  `delivery_radius_km` int(11) DEFAULT 0,
  `free_delivery_min_order` decimal(10,2) DEFAULT 0.00,
  `notification_preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`notification_preferences`)),
  `additional_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`additional_settings`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `entity_settings`

-- Table structure for table `entity_translations`
--

CREATE TABLE `entity_translations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `language_code` varchar(8) NOT NULL,
  `store_name` varchar(255) NOT NULL,
  `branch_code` varchar(50) DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `entity_translations`


--
-- Table structure for table `entity_types`
--

CREATE TABLE `entity_types` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `entity_types`
--

-- Table structure for table `escrow_disputes`
--

CREATE TABLE `escrow_disputes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `dispute_number` varchar(100) DEFAULT NULL,
  `escrow_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `raised_by_entity_id` bigint(20) UNSIGNED NOT NULL,
  `dispute_type` enum('not_received','not_as_described','damaged','wrong_item','other') NOT NULL,
  `description` text NOT NULL,
  `status` enum('open','under_review','resolved_buyer','resolved_seller','resolved_partial','closed') DEFAULT 'open',
  `resolution_type` enum('refund_full','refund_partial','release_payment','cancelled') DEFAULT NULL,
  `refund_amount` decimal(15,2) DEFAULT NULL,
  `assigned_to` int(10) UNSIGNED DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `escrow_dispute_evidence`
--

CREATE TABLE `escrow_dispute_evidence` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `dispute_id` bigint(20) UNSIGNED NOT NULL,
  `file_url` varchar(500) NOT NULL,
  `uploaded_by_entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `escrow_ledger`
--

CREATE TABLE `escrow_ledger` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `escrow_id` bigint(20) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `transaction_type` enum('fund','fee','release','refund','partial_refund') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency_code` varchar(8) DEFAULT 'USD',
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `escrow_status_history`
--

CREATE TABLE `escrow_status_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `escrow_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('pending','funded','in_transit','delivered','released','disputed','refunded','cancelled') NOT NULL,
  `notes` text DEFAULT NULL,
  `changed_by_entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `escrow_transactions`
--

CREATE TABLE `escrow_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `escrow_number` varchar(100) NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `buyer_entity_id` bigint(20) UNSIGNED NOT NULL,
  `seller_entity_id` bigint(20) UNSIGNED NOT NULL,
  `buyer_entity_type_id` bigint(20) UNSIGNED NOT NULL,
  `seller_entity_type_id` bigint(20) UNSIGNED NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `escrow_fee` decimal(15,2) DEFAULT 0.00,
  `currency_id` smallint(5) UNSIGNED NOT NULL,
  `status` enum('pending','funded','in_transit','delivered','released','disputed','refunded','cancelled') DEFAULT 'pending',
  `auto_release_days` int(11) DEFAULT 7,
  `funded_at` datetime DEFAULT NULL,
  `shipped_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `disputed_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `escrow_transactions`
--

INSERT INTO `escrow_transactions` (`id`, `tenant_id`, `escrow_number`, `order_id`, `buyer_entity_id`, `seller_entity_id`, `buyer_entity_type_id`, `seller_entity_type_id`, `amount`, `escrow_fee`, `currency_id`, `status`, `auto_release_days`, `funded_at`, `shipped_at`, `delivered_at`, `released_at`, `disputed_at`, `resolved_at`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 'ESC-20260317-2295', 1, 18, 17, 23, 8, 100.00, 500.00, 5, 'pending', 7, NULL, NULL, NULL, NULL, NULL, NULL, '55', '2026-03-17 10:58:15', '2026-03-17 10:58:15');

-- --------------------------------------------------------

--
-- Table structure for table `financial_ledger`
--

CREATE TABLE `financial_ledger` (
  `id` bigint(20) NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) DEFAULT NULL,
  `payment_id` bigint(20) DEFAULT NULL,
  `refund_id` bigint(20) DEFAULT NULL,
  `entry_type` enum('sale','refund','commission','payout','fee','adjustment') NOT NULL,
  `transaction_direction` enum('credit','debit') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency_code` varchar(8) DEFAULT 'SAR',
  `description` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `flash_sales`
--

CREATE TABLE `flash_sales` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sale_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `discount_type` enum('percentage','fixed') DEFAULT 'percentage',
  `discount_value` decimal(10,2) NOT NULL,
  `max_discount_amount` decimal(15,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `banner_image` varchar(500) DEFAULT NULL,
  `total_products` int(11) DEFAULT 0,
  `total_sales` decimal(15,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `flash_sales`
--

INSERT INTO `flash_sales` (`id`, `entity_id`, `sale_name`, `description`, `start_date`, `end_date`, `discount_type`, `discount_value`, `max_discount_amount`, `is_active`, `banner_image`, `total_products`, `total_sales`, `created_at`, `updated_at`) VALUES
(1, NULL, '555', '55', '2026-02-12 23:24:00', '2026-02-13 23:27:00', 'fixed', 1.00, 5.00, 1, '555', 0, 0.00, '2026-02-12 14:25:00', '2026-02-12 14:31:45');

-- --------------------------------------------------------

--
-- Table structure for table `flash_sales_translations`
--

CREATE TABLE `flash_sales_translations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `flash_sale_id` bigint(20) UNSIGNED NOT NULL,
  `language_code` varchar(8) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `value` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `flash_sales_translations`
--

INSERT INTO `flash_sales_translations` (`id`, `flash_sale_id`, `language_code`, `field_name`, `value`, `created_at`, `updated_at`) VALUES
(1, 1, 'ar', 'sale_name', 'محمود', '2026-02-12 14:32:18', '2026-02-12 14:32:18');

-- --------------------------------------------------------

--
-- Table structure for table `flash_sale_products`
--

CREATE TABLE `flash_sale_products` (
  `id` bigint(20) NOT NULL,
  `flash_sale_id` bigint(20) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `original_price` decimal(15,2) NOT NULL,
  `sale_price` decimal(15,2) NOT NULL,
  `discount_percentage` decimal(5,2) DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `sold_quantity` int(11) DEFAULT 0,
  `max_quantity_per_user` int(11) DEFAULT 5,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `font_settings`
--

CREATE TABLE `font_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `theme_id` bigint(20) DEFAULT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_name` varchar(255) NOT NULL,
  `font_family` varchar(255) NOT NULL,
  `font_size` varchar(50) DEFAULT NULL,
  `font_weight` varchar(50) DEFAULT NULL,
  `line_height` varchar(50) DEFAULT NULL,
  `category` varchar(50) DEFAULT 'other',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `font_settings`
--

INSERT INTO `font_settings` (`id`, `theme_id`, `setting_key`, `setting_name`, `font_family`, `font_size`, `font_weight`, `line_height`, `category`, `is_active`, `sort_order`, `created_at`, `updated_at`, `tenant_id`) VALUES
(1, 1, 'footer_font', 'Footer Text', 'Open Sans, sans-serif', '14px', '400', NULL, '', 1, 6, '2026-03-03 16:26:57', '2026-03-03 16:26:57', 1),
(2, 1, 'form_font', 'Form Fields', 'Open Sans, sans-serif', '16px', '400', NULL, '', 1, 7, '2026-03-03 16:26:57', '2026-03-03 16:26:57', 1),
(3, 1, 'card_font', 'Card Text', 'Roboto, sans-serif', '16px', '400', NULL, '', 1, 8, '2026-03-03 16:26:57', '2026-03-03 16:26:57', 1),
(4, 1, 'promo_font', 'Promo Text', 'Poppins, sans-serif', '18px', '500', NULL, '', 1, 9, '2026-03-03 16:26:57', '2026-03-03 16:26:57', 1),
(5, 1, 'small_text_font', 'Small Text', 'Open Sans, sans-serif', '12px', '400', NULL, '', 1, 10, '2026-03-03 16:26:57', '2026-03-03 16:26:57', 1),
(6, 1, 'code_font', 'Code Text', 'Courier New, monospace', '14px', '400', NULL, '', 1, 11, '2026-03-03 16:26:57', '2026-03-03 16:26:57', 1),
(7, 1, 'alert_font', 'Alert / Notification Text', 'Open Sans, sans-serif', '16px', '600', NULL, '', 1, 12, '2026-03-03 16:26:57', '2026-03-03 16:26:57', 1),
(8, 2, 'body_font', 'Body Text', 'Inter, system-ui, sans-serif', '16px', '400', '1.5', 'text', 1, 1, '2026-03-28 15:08:21', '2026-03-28 15:08:21', 1),
(9, 2, 'heading_font', 'Headings', 'Poppins, Inter, system-ui, sans-serif', '2rem', '600', '1.2', 'text', 1, 2, '2026-03-28 15:08:21', '2026-03-28 15:08:21', 1),
(10, 2, 'footer_font', 'Footer Text', 'Inter, system-ui, sans-serif', '14px', '400', NULL, 'other', 1, 6, '2026-03-28 15:08:21', '2026-03-28 15:08:21', 1),
(11, 2, 'form_font', 'Form Fields', 'Inter, system-ui, sans-serif', '16px', '400', NULL, 'other', 1, 7, '2026-03-28 15:08:21', '2026-03-28 15:08:21', 1),
(12, 2, 'card_font', 'Card Text', 'Inter, system-ui, sans-serif', '16px', '400', NULL, 'other', 1, 8, '2026-03-28 15:08:21', '2026-03-28 15:08:21', 1),
(13, 2, 'promo_font', 'Promo Text', 'Poppins, Inter, sans-serif', '18px', '500', NULL, 'other', 1, 9, '2026-03-28 15:08:21', '2026-03-28 15:08:21', 1),
(14, 2, 'small_text_font', 'Small Text', 'Inter, system-ui, sans-serif', '12px', '400', NULL, 'other', 1, 10, '2026-03-28 15:08:21', '2026-03-28 15:08:21', 1),
(15, 2, 'code_font', 'Code Text', 'JetBrains Mono, monospace', '14px', '400', NULL, 'other', 1, 11, '2026-03-28 15:08:21', '2026-03-28 15:08:21', 1),
(16, 2, 'alert_font', 'Alert / Notification Text', 'Inter, system-ui, sans-serif', '16px', '500', NULL, 'other', 1, 12, '2026-03-28 15:08:21', '2026-03-28 15:08:21', 1),
(17, 3, 'body_font', 'Body Text', 'Inter, -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif', '16px', '400', '1.5', 'text', 1, 1, '2026-03-28 15:21:44', '2026-03-28 15:21:44', 1),
(18, 3, 'heading_font', 'Headings', 'Inter, -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif', '2rem', '600', '1.2', 'text', 1, 2, '2026-03-28 15:21:44', '2026-03-28 15:21:44', 1),
(19, 3, 'footer_font', 'Footer Text', 'Inter, -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif', '14px', '400', NULL, 'other', 1, 6, '2026-03-28 15:21:44', '2026-03-28 15:21:44', 1),
(20, 3, 'form_font', 'Form Fields', 'Inter, -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif', '16px', '400', NULL, 'other', 1, 7, '2026-03-28 15:21:44', '2026-03-28 15:21:44', 1),
(21, 3, 'card_font', 'Card Text', 'Inter, -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif', '16px', '400', NULL, 'other', 1, 8, '2026-03-28 15:21:44', '2026-03-28 15:21:44', 1),
(22, 3, 'promo_font', 'Promo Text', 'Inter, -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif', '18px', '500', NULL, 'other', 1, 9, '2026-03-28 15:21:44', '2026-03-28 15:21:44', 1),
(23, 3, 'small_text_font', 'Small Text', 'Inter, -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif', '12px', '400', NULL, 'other', 1, 10, '2026-03-28 15:21:44', '2026-03-28 15:21:44', 1),
(24, 3, 'code_font', 'Code Text', 'SF Mono, Menlo, Monaco, Consolas, monospace', '14px', '400', NULL, 'other', 1, 11, '2026-03-28 15:21:44', '2026-03-28 15:21:44', 1),
(25, 3, 'alert_font', 'Alert / Notification Text', 'Inter, -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif', '16px', '500', NULL, 'other', 1, 12, '2026-03-28 15:21:44', '2026-03-28 15:21:44', 1),
(26, 4, 'body_font', 'Body Text', 'Poppins, -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif', '16px', '400', '1.5', 'text', 1, 1, '2026-03-28 15:29:50', '2026-03-28 15:29:50', 1),
(27, 4, 'heading_font', 'Headings', 'Montserrat, Poppins, sans-serif', '2rem', '700', '1.2', 'text', 1, 2, '2026-03-28 15:29:50', '2026-03-28 15:29:50', 1),
(28, 4, 'footer_font', 'Footer Text', 'Poppins, sans-serif', '14px', '400', NULL, 'other', 1, 6, '2026-03-28 15:29:50', '2026-03-28 15:29:50', 1),
(29, 4, 'form_font', 'Form Fields', 'Poppins, sans-serif', '16px', '400', NULL, 'other', 1, 7, '2026-03-28 15:29:50', '2026-03-28 15:29:50', 1),
(30, 4, 'card_font', 'Card Text', 'Poppins, sans-serif', '16px', '400', NULL, 'other', 1, 8, '2026-03-28 15:29:50', '2026-03-28 15:29:50', 1),
(31, 4, 'promo_font', 'Promo Text', 'Montserrat, Poppins, sans-serif', '18px', '600', NULL, 'other', 1, 9, '2026-03-28 15:29:50', '2026-03-28 15:29:50', 1),
(32, 4, 'small_text_font', 'Small Text', 'Poppins, sans-serif', '12px', '400', NULL, 'other', 1, 10, '2026-03-28 15:29:50', '2026-03-28 15:29:50', 1),
(33, 4, 'code_font', 'Code Text', 'SF Mono, Menlo, monospace', '14px', '400', NULL, 'other', 1, 11, '2026-03-28 15:29:50', '2026-03-28 15:29:50', 1),
(34, 4, 'alert_font', 'Alert / Notification Text', 'Poppins, sans-serif', '16px', '500', NULL, 'other', 1, 12, '2026-03-28 15:29:50', '2026-03-28 15:29:50', 1),
(35, 5, 'body_font', 'Body Text', 'Space Grotesk, \"Segoe UI\", system-ui, sans-serif', '16px', '400', '1.5', 'text', 1, 1, '2026-03-28 16:23:24', '2026-03-28 16:23:24', 1),
(36, 5, 'heading_font', 'Headings', 'Orbitron, \"Space Grotesk\", sans-serif', '2rem', '700', '1.2', 'text', 1, 2, '2026-03-28 16:23:24', '2026-03-28 16:23:24', 1),
(37, 5, 'footer_font', 'Footer Text', 'Space Grotesk, sans-serif', '14px', '400', NULL, 'other', 1, 6, '2026-03-28 16:23:24', '2026-03-28 16:23:24', 1),
(38, 5, 'form_font', 'Form Fields', 'Space Grotesk, sans-serif', '16px', '400', NULL, 'other', 1, 7, '2026-03-28 16:23:24', '2026-03-28 16:23:24', 1),
(39, 5, 'card_font', 'Card Text', 'Space Grotesk, sans-serif', '16px', '400', NULL, 'other', 1, 8, '2026-03-28 16:23:24', '2026-03-28 16:23:24', 1),
(40, 5, 'promo_font', 'Promo Text', 'Orbitron, sans-serif', '18px', '600', NULL, 'other', 1, 9, '2026-03-28 16:23:24', '2026-03-28 16:23:24', 1),
(41, 5, 'small_text_font', 'Small Text', 'Space Grotesk, sans-serif', '12px', '400', NULL, 'other', 1, 10, '2026-03-28 16:23:24', '2026-03-28 16:23:24', 1),
(42, 5, 'code_font', 'Code Text', 'Fira Code, monospace', '14px', '400', NULL, 'other', 1, 11, '2026-03-28 16:23:24', '2026-03-28 16:23:24', 1),
(43, 5, 'alert_font', 'Alert / Notification Text', 'Space Grotesk, sans-serif', '16px', '500', NULL, 'other', 1, 12, '2026-03-28 16:23:24', '2026-03-28 16:23:24', 1),
(53, 6, 'body_font', 'Body Text', 'Inter, system-ui, -apple-system, sans-serif', '16px', '400', '1.65', 'text', 1, 1, '2026-03-28 16:43:20', '2026-03-28 16:43:20', 1),
(54, 6, 'heading_font', 'Headings', 'Inter, \"Poppins\", sans-serif', '2rem', '600', '1.25', 'text', 1, 2, '2026-03-28 16:43:20', '2026-03-28 16:43:20', 1),
(55, 6, 'footer_font', 'Footer Text', 'Inter, sans-serif', '14px', '400', NULL, 'other', 1, 6, '2026-03-28 16:43:20', '2026-03-28 16:43:20', 1),
(56, 6, 'form_font', 'Form Fields', 'Inter, sans-serif', '16px', '400', NULL, 'other', 1, 7, '2026-03-28 16:43:20', '2026-03-28 16:43:20', 1),
(57, 6, 'card_font', 'Card Text', 'Inter, sans-serif', '15.5px', '400', NULL, 'other', 1, 8, '2026-03-28 16:43:20', '2026-03-28 16:43:20', 1),
(58, 6, 'promo_font', 'Promo Text', 'Inter, sans-serif', '17.5px', '600', NULL, 'other', 1, 9, '2026-03-28 16:43:20', '2026-03-28 16:43:20', 1),
(59, 6, 'small_text_font', 'Small Text', 'Inter, sans-serif', '13px', '400', NULL, 'other', 1, 10, '2026-03-28 16:43:20', '2026-03-28 16:43:20', 1),
(60, 6, 'code_font', 'Code Text', 'SF Mono, Menlo, monospace', '14px', '400', NULL, 'other', 1, 11, '2026-03-28 16:43:20', '2026-03-28 16:43:20', 1),
(61, 6, 'alert_font', 'Alert / Notification Text', 'Inter, sans-serif', '15.5px', '500', NULL, 'other', 1, 12, '2026-03-28 16:43:20', '2026-03-28 16:43:20', 1);

-- --------------------------------------------------------

--
-- Table structure for table `homepage_sections`
--

CREATE TABLE `homepage_sections` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `theme_id` int(10) UNSIGNED DEFAULT NULL,
  `section_type` varchar(50) NOT NULL,
  `component` varchar(100) DEFAULT NULL COMMENT 'e.g. ad_products, ad_categories',
  `title` varchar(255) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `layout_type` varchar(50) DEFAULT 'grid',
  `layout_config` text DEFAULT NULL,
  `items_per_row` tinyint(3) UNSIGNED NOT NULL DEFAULT 4,
  `background_color` varchar(30) DEFAULT NULL,
  `text_color` varchar(30) DEFAULT NULL,
  `padding` varchar(50) DEFAULT NULL,
  `custom_css` text DEFAULT NULL,
  `custom_html` text DEFAULT NULL,
  `data_source` varchar(255) DEFAULT NULL COMMENT 'e.g. products:featured, categories, deals',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `homepage_sections`
--

INSERT INTO `homepage_sections` (`id`, `tenant_id`, `theme_id`, `section_type`, `component`, `title`, `subtitle`, `layout_type`, `layout_config`, `items_per_row`, `background_color`, `text_color`, `padding`, `custom_css`, `custom_html`, `data_source`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'ads', 'ad_ads', NULL, NULL, 'full', NULL, 1, NULL, NULL, NULL, NULL, NULL, 'ads', 1, 10, '2026-03-30 12:54:11', NULL),
(2, 1, NULL, 'search', 'ad_search', NULL, NULL, 'full', NULL, 1, 'var(--pub-surface)', 'var(--pub-text)', NULL, NULL, NULL, 'search', 1, 20, '2026-03-30 12:54:11', NULL),
(3, 1, NULL, 'categories', 'ad_categories', NULL, NULL, 'grid', NULL, 6, '#ffffff', '#ffffff', '', '', '', 'categories', 1, 30, '2026-03-30 12:54:11', '2026-03-30 13:11:27'),
(4, 1, NULL, 'deals', 'ad_deals', NULL, NULL, 'grid', NULL, 4, '#ffffff', '#ffffff', '', '', '', 'deals', 1, 40, '2026-03-30 12:54:11', '2026-03-30 14:22:17'),
(5, 1, NULL, 'products', 'ad_products', NULL, NULL, 'grid', NULL, 6, '#ffffff', '#ffffff', '', '', '', 'products:featured', 1, 50, '2026-03-30 12:54:11', '2026-04-05 13:26:29'),
(6, 1, NULL, 'products', 'ad_products', NULL, NULL, 'grid', NULL, 4, '#ffffff', 'var(--pub-text)', NULL, NULL, NULL, 'products:new', 1, 60, '2026-03-30 12:54:11', '2026-04-05 12:54:25'),
(7, 1, NULL, 'entities', 'ad_entities', NULL, NULL, 'grid', NULL, 4, 'var(--pub-bg)', 'var(--pub-text)', NULL, NULL, NULL, 'entities', 1, 70, '2026-03-30 12:54:11', NULL),
(8, 1, NULL, 'brands', 'ad_brands', NULL, NULL, 'grid', NULL, 6, 'var(--pub-surface)', 'var(--pub-text)', NULL, NULL, NULL, 'brands:featured', 1, 80, '2026-03-30 12:54:11', NULL),
(9, 1, NULL, 'auctions', 'ad_auctions', NULL, NULL, 'grid', NULL, 3, 'var(--pub-bg)', 'var(--pub-text)', NULL, NULL, NULL, 'auctions:active', 1, 90, '2026-03-30 12:54:11', NULL),
(10, 1, NULL, 'jobs', 'ad_jobs', NULL, NULL, 'grid', NULL, 3, 'var(--pub-surface)', 'var(--pub-text)', NULL, NULL, NULL, 'jobs:featured', 1, 100, '2026-03-30 12:54:11', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `homepage_section_translations`
--

CREATE TABLE `homepage_section_translations` (
  `id` int(10) UNSIGNED NOT NULL,
  `section_id` int(10) UNSIGNED NOT NULL,
  `language_code` varchar(10) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `subtitle` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `homepage_section_translations`
--


-- Table structure for table `images`
--

CREATE TABLE `images` (
  `id` bigint(20) NOT NULL,
  `owner_id` bigint(20) DEFAULT NULL COMMENT 'رقم السجل المرتبط بالمالك',
  `image_type_id` int(10) UNSIGNED DEFAULT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL COMMENT 'اسم الملف الأصلي',
  `url` varchar(500) DEFAULT NULL COMMENT 'الرابط الكامل للصورة',
  `thumb_url` varchar(500) DEFAULT NULL COMMENT 'رابط الصورة المصغرة',
  `mime_type` varchar(50) DEFAULT NULL COMMENT 'نوع MIME: image/jpeg, image/png, ...',
  `size` bigint(20) DEFAULT NULL COMMENT 'حجم الملف بالبايت',
  `visibility` enum('private','public') DEFAULT 'private' COMMENT 'الوصول: عام / خاص',
  `is_main` tinyint(1) DEFAULT 0 COMMENT 'هل الصورة الرئيسية للمالك',
  `sort_order` int(11) DEFAULT 0 COMMENT 'ترتيب العرض عند وجود أكثر من صورة',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='جدول موحد لكل الصور';

--
-- Dumping data for table `images`
--

-- Table structure for table `image_types`
--

CREATE TABLE `image_types` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `width` int(11) NOT NULL,
  `height` int(11) NOT NULL,
  `crop` enum('fit','fill','cover') DEFAULT 'cover',
  `quality` tinyint(4) DEFAULT 85,
  `format` enum('jpg','png','webp') DEFAULT 'webp',
  `is_thumbnail` tinyint(1) DEFAULT 0,
  `icon` varchar(100) NOT NULL DEFAULT 'fa-image' COMMENT 'FontAwesome class (e.g. fa-user, fa-box)',
  `color` varchar(30) NOT NULL DEFAULT '#6b7280' COMMENT 'CSS colour string (hex or named colour)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `independent_drivers`
--

CREATE TABLE `independent_drivers` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `phone` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `vehicle_type` enum('motorcycle','car','van','truck') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `vehicle_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `license_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `license_photo_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `id_photo_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `status` enum('active','inactive','busy','offline') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT 'active',
  `rating_average` decimal(3,2) DEFAULT 0.00,
  `rating_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` bigint(20) NOT NULL,
  `invoice_number` varchar(100) NOT NULL,
  `order_id` bigint(20) NOT NULL,
  `payment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `invoice_type` enum('order','refund','credit_note') DEFAULT 'order',
  `subtotal` decimal(15,2) NOT NULL,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `shipping_cost` decimal(15,2) DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL,
  `currency_code` varchar(8) DEFAULT 'SAR',
  `status` enum('draft','issued','paid','overdue','cancelled') DEFAULT 'draft',
  `due_date` date DEFAULT NULL,
  `issued_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `invoice_pdf_url` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `job_title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `job_type` enum('full_time','part_time','contract','temporary','internship','freelance','remote') NOT NULL,
  `employment_type` enum('permanent','temporary','seasonal') DEFAULT 'permanent',
  `application_form_type` enum('simple','custom','external') NOT NULL DEFAULT 'simple',
  `external_application_url` varchar(500) DEFAULT NULL,
  `experience_level` enum('entry','junior','mid','senior','executive','director') NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `positions_available` int(11) DEFAULT 1,
  `salary_min` decimal(15,2) DEFAULT NULL,
  `salary_max` decimal(15,2) DEFAULT NULL,
  `salary_currency` varchar(8) DEFAULT 'SAR',
  `salary_period` enum('hourly','daily','weekly','monthly','yearly') DEFAULT 'monthly',
  `salary_negotiable` tinyint(1) DEFAULT 0,
  `country_id` int(11) NOT NULL,
  `city_id` int(11) DEFAULT NULL,
  `work_location` varchar(255) DEFAULT NULL,
  `is_remote` tinyint(1) DEFAULT 0,
  `status` enum('draft','published','closed','filled','cancelled') DEFAULT 'draft',
  `application_deadline` datetime DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `views_count` int(11) DEFAULT 0,
  `applications_count` int(11) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `is_urgent` tinyint(1) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `published_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `job_alerts`
--

CREATE TABLE `job_alerts` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `alert_name` varchar(255) NOT NULL,
  `keywords` varchar(500) DEFAULT NULL,
  `job_type` varchar(100) DEFAULT NULL,
  `experience_level` varchar(100) DEFAULT NULL,
  `country_id` int(11) DEFAULT NULL,
  `city_id` int(11) DEFAULT NULL,
  `salary_min` decimal(15,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `frequency` enum('instant','daily','weekly') DEFAULT 'daily',
  `last_sent_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `job_applications`
--

CREATE TABLE `job_applications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `job_id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(191) NOT NULL,
  `phone` varchar(45) NOT NULL,
  `current_position` varchar(255) DEFAULT NULL,
  `current_company` varchar(255) DEFAULT NULL,
  `years_of_experience` int(11) DEFAULT NULL,
  `expected_salary` decimal(15,2) DEFAULT NULL,
  `currency_code` varchar(8) DEFAULT 'SAR',
  `notice_period` int(11) DEFAULT NULL,
  `cv_file_url` varchar(500) NOT NULL,
  `cover_letter` text DEFAULT NULL,
  `portfolio_url` varchar(500) DEFAULT NULL,
  `linkedin_url` varchar(500) DEFAULT NULL,
  `status` enum('submitted','under_review','shortlisted','interview_scheduled','interviewed','offered','accepted','rejected','withdrawn') DEFAULT 'submitted',
  `rating` tinyint(4) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `job_application_answers`
--

CREATE TABLE `job_application_answers` (
  `id` bigint(20) NOT NULL,
  `application_id` bigint(20) NOT NULL,
  `question_id` bigint(20) NOT NULL,
  `answer_text` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_application_questions`
--

CREATE TABLE `job_application_questions` (
  `id` bigint(20) NOT NULL,
  `job_id` bigint(20) NOT NULL,
  `question_text` text NOT NULL,
  `question_type` enum('text','textarea','select','multiselect','radio','checkbox','file','date','number') DEFAULT 'text',
  `options` text DEFAULT NULL,
  `is_required` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `job_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `parent_id` bigint(20) DEFAULT NULL,
  `slug` varchar(255) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `job_category_translations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) NOT NULL,
  `language_code` varchar(8) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `job_interviews` (
  `id` bigint(20) NOT NULL,
  `application_id` bigint(20) NOT NULL,
  `interview_type` enum('phone','video','in_person','technical','hr','final') NOT NULL,
  `interview_date` datetime NOT NULL,
  `interview_duration` int(11) DEFAULT 60,
  `location` varchar(500) DEFAULT NULL,
  `meeting_link` varchar(500) DEFAULT NULL,
  `interviewer_name` varchar(255) DEFAULT NULL,
  `interviewer_email` varchar(191) DEFAULT NULL,
  `status` enum('scheduled','confirmed','completed','cancelled','rescheduled','no_show') DEFAULT 'scheduled',
  `feedback` text DEFAULT NULL,
  `rating` tinyint(4) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_skills`
--

CREATE TABLE `job_skills` (
  `id` bigint(20) NOT NULL,
  `job_id` bigint(20) NOT NULL,
  `skill_name` varchar(100) NOT NULL,
  `proficiency_level` enum('basic','intermediate','advanced','expert') DEFAULT 'intermediate',
  `is_required` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `job_translations` (
  `id` bigint(20) NOT NULL,
  `job_id` bigint(20) NOT NULL,
  `language_code` varchar(8) NOT NULL,
  `job_title` varchar(255) NOT NULL,
  `description` longtext NOT NULL,
  `requirements` text DEFAULT NULL,
  `responsibilities` text DEFAULT NULL,
  `benefits` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `languages` (
  `code` varchar(8) NOT NULL,
  `name` varchar(100) NOT NULL,
  `direction` varchar(3) NOT NULL DEFAULT 'ltr'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `loyalty_program_settings`
--

CREATE TABLE `loyalty_program_settings` (
  `id` bigint(20) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `setting_type` enum('text','number','boolean','json') DEFAULT 'text',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `membership_tiers` (
  `id` bigint(20) NOT NULL,
  `tier_name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `tier_level` int(11) NOT NULL,
  `min_points_required` int(11) DEFAULT 0,
  `min_spending_required` decimal(15,2) DEFAULT 0.00,
  `icon_url` varchar(500) DEFAULT NULL,
  `badge_url` varchar(500) DEFAULT NULL,
  `color_code` varchar(7) DEFAULT '#000000',
  `points_multiplier` decimal(5,2) DEFAULT 1.00,
  `discount_percentage` decimal(5,2) DEFAULT 0.00,
  `free_shipping` tinyint(1) DEFAULT 0,
  `priority_support` tinyint(1) DEFAULT 0,
  `early_access` tinyint(1) DEFAULT 0,
  `birthday_bonus_points` int(11) DEFAULT 0,
  `validity_period_months` int(11) DEFAULT 12,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `membership_tier_history` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tier_id` bigint(20) NOT NULL,
  `previous_tier_id` bigint(20) DEFAULT NULL,
  `reason` enum('points_earned','spending_threshold','admin_upgrade','promotion','downgrade','expired') NOT NULL,
  `points_at_change` int(11) DEFAULT 0,
  `spending_at_change` decimal(15,2) DEFAULT 0.00,
  `changed_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `membership_tier_translations` (
  `id` bigint(20) NOT NULL,
  `tier_id` bigint(20) NOT NULL,
  `language_code` varchar(8) NOT NULL,
  `tier_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `benefits` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `sender_entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(500) NOT NULL,
  `message` mediumtext NOT NULL,
  `sent_at` timestamp NULL DEFAULT current_timestamp(),
  `data` longtext DEFAULT NULL,
  `notification_type_id` int(10) UNSIGNED DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `notification_channels`
--

CREATE TABLE `notification_channels` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_channels`
--


CREATE TABLE `notification_counters` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `recipient_type` enum('user','entity','tenant') NOT NULL,
  `recipient_id` bigint(20) UNSIGNED NOT NULL,
  `unread_count` int(11) DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_counters`
-- Table structure for table `notification_deliveries`
--

CREATE TABLE `notification_deliveries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `notification_id` bigint(20) UNSIGNED NOT NULL,
  `channel_id` int(10) UNSIGNED NOT NULL,
  `delivery_status` enum('pending','sent','failed') DEFAULT 'pending',
  `attempts` int(11) DEFAULT 0,
  `sent_at` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `notification_recipients` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `notification_id` bigint(20) UNSIGNED NOT NULL,
  `recipient_type` enum('user','entity','tenant') NOT NULL,
  `recipient_id` bigint(20) UNSIGNED NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `notification_types` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `default_template` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notification_types`

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `order_number` varchar(100) NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `cart_id` bigint(20) UNSIGNED DEFAULT NULL,
  `order_type` enum('online','pos','phone','wholesale') DEFAULT 'online',
  `status` enum('pending','confirmed','processing','shipped','out_for_delivery','delivered','completed','cancelled','refunded','failed') DEFAULT 'pending',
  `payment_status` enum('pending','paid','partial','failed','refunded') DEFAULT 'pending',
  `fulfillment_status` enum('unfulfilled','partial','fulfilled') DEFAULT 'unfulfilled',
  `subtotal` decimal(15,2) NOT NULL,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `shipping_cost` decimal(15,2) DEFAULT 0.00,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `coupon_discount` decimal(15,2) DEFAULT 0.00,
  `loyalty_points_discount` decimal(15,2) DEFAULT 0.00,
  `wallet_amount_used` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL,
  `grand_total` decimal(15,2) NOT NULL,
  `currency_code` varchar(8) DEFAULT 'SAR',
  `coupon_code` varchar(100) DEFAULT NULL,
  `loyalty_points_used` int(11) DEFAULT 0,
  `loyalty_points_earned` int(11) DEFAULT 0,
  `shipping_address_id` int(11) DEFAULT NULL,
  `billing_address_id` int(11) DEFAULT NULL,
  `delivery_company_id` bigint(20) DEFAULT NULL,
  `estimated_delivery_date` date DEFAULT NULL,
  `actual_delivery_date` datetime DEFAULT NULL,
  `customer_notes` text DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `is_gift` tinyint(1) DEFAULT 0,
  `gift_message` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `confirmed_at` datetime DEFAULT NULL,
  `shipped_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `assigned_driver_id` bigint(20) DEFAULT NULL,
  `pos_session_id` bigint(20) DEFAULT NULL,
  `cashier_user_id` int(11) DEFAULT NULL,
  `branch_entity_id` bigint(20) DEFAULT NULL,
  `sales_channel` enum('online','pos','mobile_app','call_center','marketplace') DEFAULT 'online',
  `payment_method` varchar(50) DEFAULT 'cash',
  `delivery_entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `delivery_zone_id` bigint(20) UNSIGNED DEFAULT NULL,
  `delivery_method` enum('merchant','courier','pickup') NOT NULL DEFAULT 'merchant',
  `pickup_point_id` bigint(20) UNSIGNED DEFAULT NULL,
  `auction_id` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `order_events` (
  `id` bigint(20) NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `actor_type` enum('user','admin','system','payment','driver') NOT NULL,
  `actor_id` bigint(20) DEFAULT NULL,
  `related_item_id` bigint(20) DEFAULT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_value`)),
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_value`)),
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `tenant_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `product_variant_id` bigint(20) DEFAULT NULL,
  `product_name` varchar(500) NOT NULL,
  `sku` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `sale_price` decimal(15,2) DEFAULT NULL,
  `discount_amount` decimal(15,2) DEFAULT 0.00,
  `tax_rate` decimal(5,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) NOT NULL,
  `total` decimal(15,2) NOT NULL,
  `currency_code` varchar(8) DEFAULT 'SAR',
  `commission_rate` decimal(5,2) DEFAULT 0.00,
  `commission_amount` decimal(15,2) DEFAULT 0.00,
  `selected_attributes` text DEFAULT NULL,
  `special_instructions` text DEFAULT NULL,
  `is_refunded` tinyint(1) DEFAULT 0,
  `refunded_quantity` int(11) DEFAULT 0,
  `refunded_amount` decimal(15,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp(),
  `inventory_entity_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Table structure for table `order_reviews`
--

CREATE TABLE `order_reviews` (
  `id` bigint(20) NOT NULL,
  `order_id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `vendor_id` bigint(20) NOT NULL,
  `delivery_company_id` bigint(20) DEFAULT NULL,
  `overall_rating` tinyint(4) NOT NULL,
  `product_quality_rating` tinyint(4) DEFAULT NULL,
  `delivery_rating` tinyint(4) DEFAULT NULL,
  `service_rating` tinyint(4) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `is_approved` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_status_history`
--

CREATE TABLE `order_status_history` (
  `id` bigint(20) NOT NULL,
  `order_id` bigint(20) NOT NULL,
  `status` enum('pending','confirmed','processing','shipped','out_for_delivery','delivered','completed','cancelled','refunded','failed') NOT NULL,
  `notes` text DEFAULT NULL,
  `notified_customer` tinyint(1) DEFAULT 0,
  `changed_by` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `payment_method_id` bigint(20) UNSIGNED DEFAULT NULL,
  `payment_number` varchar(100) NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `payment_method` varchar(100) NOT NULL,
  `payment_gateway` varchar(100) DEFAULT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency_code` varchar(8) DEFAULT 'SAR',
  `status` enum('pending','processing','completed','failed','cancelled','refunded') DEFAULT 'pending',
  `payment_type` enum('full','partial','deposit') DEFAULT 'full',
  `gateway_response` text DEFAULT NULL,
  `failure_reason` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `refunded_at` datetime DEFAULT NULL,
  `refund_amount` decimal(15,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Table structure for table `payment_attempts`
--

CREATE TABLE `payment_attempts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `payment_id` bigint(20) UNSIGNED NOT NULL,
  `provider_account_id` bigint(20) UNSIGNED NOT NULL,
  `attempt_reference` varchar(255) DEFAULT NULL,
  `status` enum('initiated','pending','authorized','failed','expired') DEFAULT 'initiated',
  `request_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`request_payload`)),
  `response_payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_payload`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_gateway_events`
--

CREATE TABLE `payment_gateway_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `provider_id` int(10) UNSIGNED NOT NULL,
  `provider_account_id` bigint(20) UNSIGNED DEFAULT NULL,
  `event_type` varchar(100) NOT NULL,
  `event_reference` varchar(255) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `is_processed` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `method_key` varchar(50) NOT NULL,
  `method_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `gateway_name` varchar(100) DEFAULT NULL,
  `icon_url` varchar(255) DEFAULT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `payment_providers` (
  `id` int(10) UNSIGNED NOT NULL,
  `key_name` varchar(100) NOT NULL,
  `display_name` varchar(150) NOT NULL,
  `provider_type` enum('card','bnpl','wallet','bank_transfer','cash_collection','crypto') NOT NULL DEFAULT 'card',
  `config_schema` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_providers`
--

INSERT INTO `payment_providers` (`id`, `key_name`, `display_name`, `provider_type`, `config_schema`) VALUES
(1, 'paypal', 'PayPal Gateway', 'card', '{\"client_id\":\"text\",\"secret\":\"text\"}'),
(2, 'stripe', 'Stripe Gateway', 'card', '{\"api_key\":\"text\"}'),
(3, 'mada', 'MADA Gateway', 'card', '{\"merchant_id\":\"text\",\"terminal_id\":\"text\"}');

-- --------------------------------------------------------

--
-- Table structure for table `payment_providers_accounts`
--

CREATE TABLE `payment_providers_accounts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `provider_id` int(11) NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `provider_account_id` varchar(255) DEFAULT NULL,
  `credentials_encrypted` varbinary(2048) DEFAULT NULL,
  `mode` enum('test','live') DEFAULT 'test',
  `is_active` tinyint(1) DEFAULT 1,
  `connection_status` enum('pending','requires_action','connected','restricted','rejected') DEFAULT 'pending',
  `capabilities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`capabilities`)),
  `currency` char(3) DEFAULT NULL,
  `country` char(2) DEFAULT NULL,
  `account_label` varchar(150) DEFAULT NULL,
  `last_verified_at` datetime DEFAULT NULL,
  `metadata` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `key_name` varchar(100) NOT NULL,
  `display_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `module` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `platform_report_stats`
--

CREATE TABLE `platform_report_stats` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'NULL = platform-wide stat',
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT 'NULL = all entities; set for entity-level stats',
  `report_type` varchar(100) NOT NULL,
  `period_type` enum('daily','weekly','monthly','yearly','custom') NOT NULL DEFAULT 'daily',
  `period_date` date NOT NULL COMMENT 'The date this stat covers',
  `period_start` datetime NOT NULL,
  `period_end` datetime NOT NULL,
  `metrics` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Aggregated metrics as JSON' CHECK (json_valid(`metrics`)),
  `generated_at` datetime DEFAULT current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `point_earning_rules`
--

CREATE TABLE `point_earning_rules` (
  `id` bigint(20) NOT NULL,
  `rule_name` varchar(255) NOT NULL,
  `rule_type` enum('purchase','review','referral','signup','birthday','social_share','other') NOT NULL,
  `points_per_action` int(11) DEFAULT NULL,
  `points_per_currency` decimal(10,2) DEFAULT NULL,
  `min_order_amount` decimal(15,2) DEFAULT NULL,
  `max_points_per_transaction` int(11) DEFAULT NULL,
  `applicable_categories` text DEFAULT NULL,
  `applicable_products` text DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `priority` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for table `point_redemptions`
--

CREATE TABLE `point_redemptions` (
  `id` bigint(20) NOT NULL,
  `redemption_number` varchar(100) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rule_id` bigint(20) DEFAULT NULL,
  `points_redeemed` int(11) NOT NULL,
  `discount_amount` decimal(15,2) DEFAULT NULL,
  `order_id` bigint(20) DEFAULT NULL,
  `status` enum('pending','applied','cancelled','expired') DEFAULT 'pending',
  `redeemed_at` datetime DEFAULT current_timestamp(),
  `applied_at` datetime DEFAULT NULL,
  `expiry_date` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `point_redemption_rules`
--

CREATE TABLE `point_redemption_rules` (
  `id` bigint(20) NOT NULL,
  `rule_name` varchar(255) NOT NULL,
  `redemption_type` enum('discount','product','free_shipping','gift','cashback') NOT NULL,
  `points_required` int(11) NOT NULL,
  `discount_amount` decimal(15,2) DEFAULT NULL,
  `discount_percentage` decimal(5,2) DEFAULT NULL,
  `product_id` bigint(20) DEFAULT NULL,
  `min_order_amount` decimal(15,2) DEFAULT NULL,
  `max_discount_amount` decimal(15,2) DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `times_used` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `point_transactions`
--

CREATE TABLE `point_transactions` (
  `id` bigint(20) NOT NULL,
  `transaction_number` varchar(100) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `transaction_type` enum('earned','redeemed','expired','adjusted','bonus','refunded','transferred','cancelled') NOT NULL,
  `points` int(11) NOT NULL,
  `points_before` int(11) NOT NULL,
  `points_after` int(11) NOT NULL,
  `status` enum('pending','completed','cancelled','expired') DEFAULT 'completed',
  `source_type` enum('order','review','referral','signup','birthday','promotion','admin_adjustment','other') NOT NULL,
  `source_id` bigint(20) DEFAULT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `source_order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `processed_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `pos_sessions`
--

CREATE TABLE `pos_sessions` (
  `id` bigint(20) NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `cashier_user_id` int(11) UNSIGNED DEFAULT NULL,
  `opened_at` datetime DEFAULT current_timestamp(),
  `closed_at` datetime DEFAULT NULL,
  `opening_balance` decimal(15,2) DEFAULT NULL,
  `closing_balance` decimal(15,2) DEFAULT NULL,
  `total_cash` decimal(15,2) DEFAULT 0.00,
  `total_card` decimal(15,2) DEFAULT 0.00,
  `status` enum('open','closed') DEFAULT 'open',
  `total_sales` decimal(15,2) GENERATED ALWAYS AS (`total_cash` + `total_card`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_type_id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `created_by_user_id` int(11) UNSIGNED DEFAULT NULL,
  `sku` varchar(100) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `brand_id` bigint(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `is_bestseller` tinyint(1) DEFAULT 0,
  `is_new` tinyint(1) DEFAULT 0,
  `stock_quantity` int(11) DEFAULT 0,
  `low_stock_threshold` int(11) DEFAULT 5,
  `stock_status` enum('in_stock','out_of_stock','on_backorder') DEFAULT 'in_stock',
  `manage_stock` tinyint(1) DEFAULT 1,
  `allow_backorder` tinyint(1) DEFAULT 0,
  `total_sales` int(11) DEFAULT 0,
  `rating_average` decimal(3,2) DEFAULT 0.00,
  `rating_count` int(11) DEFAULT 0,
  `views_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `published_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--
-- Table structure for table `product_answers`
--

CREATE TABLE `product_answers` (
  `id` bigint(20) NOT NULL,
  `question_id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `answer` text NOT NULL,
  `is_approved` tinyint(1) DEFAULT 0,
  `is_staff_answer` tinyint(1) DEFAULT 0,
  `helpful_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `product_attributes`
--

CREATE TABLE `product_attributes` (
  `id` bigint(20) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `attribute_type_id` int(10) UNSIGNED NOT NULL,
  `is_filterable` tinyint(1) DEFAULT 1,
  `is_visible` tinyint(1) DEFAULT 1,
  `is_required` tinyint(1) DEFAULT 0,
  `is_variation` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_global` tinyint(1) DEFAULT 1 COMMENT '1=تظهر في كل القوائم، 0=حسب category_attributes'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


--
-- Table structure for table `product_attribute_assignments`
--

CREATE TABLE `product_attribute_assignments` (
  `id` bigint(20) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `attribute_id` bigint(20) NOT NULL,
  `attribute_value_id` bigint(20) DEFAULT NULL,
  `custom_value` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_attribute_assignments`
--
--
-- Table structure for table `product_attribute_translations`
--

CREATE TABLE `product_attribute_translations` (
  `id` bigint(20) NOT NULL,
  `attribute_id` bigint(20) NOT NULL,
  `language_code` varchar(8) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_attribute_values` (
  `id` bigint(20) NOT NULL,
  `attribute_id` bigint(20) NOT NULL,
  `value` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `product_attribute_value_translations` (
  `id` bigint(20) NOT NULL,
  `attribute_value_id` bigint(20) NOT NULL,
  `language_code` varchar(8) NOT NULL,
  `label` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `product_bundles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `bundle_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `bundle_image` varchar(500) DEFAULT NULL,
  `original_total_price` decimal(15,2) NOT NULL,
  `bundle_price` decimal(15,2) NOT NULL,
  `discount_amount` decimal(15,2) NOT NULL,
  `discount_percentage` decimal(5,2) DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `sold_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_bundle_items`
--

CREATE TABLE `product_bundle_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `bundle_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `product_price` decimal(15,2) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `id` int(11) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `category_id` bigint(20) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `product_comparisons` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_comparisons`
--

INSERT INTO `product_comparisons` (`id`, `user_id`, `created_at`) VALUES
(1, 1, '2026-02-28 09:16:12');

-- --------------------------------------------------------

--
-- Table structure for table `product_comparison_items`
--

CREATE TABLE `product_comparison_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `comparison_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `added_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_comparison_items`
--

INSERT INTO `product_comparison_items` (`id`, `comparison_id`, `product_id`, `added_at`) VALUES
(1, 1, 41, '2026-02-28 09:16:12');

-- --------------------------------------------------------

--
-- Table structure for table `product_physical_attributes`
--

CREATE TABLE `product_physical_attributes` (
  `id` bigint(20) NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `weight` decimal(10,3) DEFAULT NULL,
  `length` decimal(10,2) DEFAULT NULL,
  `width` decimal(10,2) DEFAULT NULL,
  `height` decimal(10,2) DEFAULT NULL,
  `weight_unit` enum('kg','g','lb') NOT NULL DEFAULT 'kg',
  `dimension_unit` enum('cm','mm','in') NOT NULL DEFAULT 'cm',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `product_pricing` (
  `id` bigint(20) NOT NULL,
  `product_id` bigint(20) DEFAULT NULL,
  `variant_id` bigint(20) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `price` decimal(15,2) NOT NULL,
  `tax_rate` decimal(5,2) DEFAULT NULL,
  `cost_price` decimal(15,2) DEFAULT NULL,
  `compare_at_price` decimal(15,2) DEFAULT NULL,
  `currency_code` char(3) NOT NULL,
  `pricing_type` enum('fixed','discount','auction','service') DEFAULT 'fixed',
  `start_at` datetime DEFAULT NULL,
  `end_at` datetime DEFAULT NULL,
  `country_id` bigint(20) DEFAULT NULL,
  `city_id` bigint(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `product_questions` (
  `id` bigint(20) NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `question` text NOT NULL,
  `is_approved` tinyint(1) DEFAULT 0,
  `helpful_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `product_relations` (
  `id` bigint(20) NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `related_product_id` bigint(20) UNSIGNED NOT NULL,
  `relation_type` enum('related','upsell','cross_sell','alternative','accessory') NOT NULL,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `product_reviews` (
  `id` bigint(20) NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `is_verified_purchase` tinyint(1) DEFAULT 0,
  `is_approved` tinyint(1) DEFAULT 0,
  `helpful_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_reviews`
--

INSERT INTO `product_reviews` (`id`, `product_id`, `user_id`, `rating`, `title`, `comment`, `is_verified_purchase`, `is_approved`, `helpful_count`, `created_at`, `updated_at`) VALUES
(1, 41, 7, 5, 'ح', 'ح', 0, 0, 0, '2026-02-28 10:44:21', '2026-02-28 12:13:29');

-- --------------------------------------------------------

--
-- Table structure for table `product_stock_alerts`
--

CREATE TABLE `product_stock_alerts` (
  `id` bigint(20) NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `variant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(191) NOT NULL,
  `is_notified` tinyint(1) DEFAULT 0,
  `notified_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_stock_movements`
--

CREATE TABLE `product_stock_movements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `variant_id` bigint(20) DEFAULT NULL,
  `change_quantity` int(11) NOT NULL,
  `type` enum('restock','sale','return','adjustment') NOT NULL,
  `reference_id` bigint(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_stock_movements`
--

INSERT INTO `product_stock_movements` (`id`, `product_id`, `variant_id`, `change_quantity`, `type`, `reference_id`, `notes`, `created_at`) VALUES
(1, 1, NULL, 20, 'restock', NULL, NULL, '2026-02-13 07:11:09'),
(2, 1, NULL, 50, 'restock', NULL, NULL, '2026-02-13 07:13:28');

-- --------------------------------------------------------

--
-- Table structure for table `product_translations`
--

CREATE TABLE `product_translations` (
  `id` bigint(20) NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `language_code` varchar(8) NOT NULL,
  `name` varchar(500) NOT NULL,
  `short_description` text DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `specifications` longtext DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `meta_keywords` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `product_types` (
  `id` int(10) UNSIGNED NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_types`
--

INSERT INTO `product_types` (`id`, `code`, `name`, `description`, `is_active`) VALUES
(1, 'simple', 'منتج بسيط', 'منتج عادي بدون خيارات', 1),
(2, 'variable', 'منتج متعدد', 'منتج يحتوي على خصائص ومتغيرات', 1),
(3, 'digital', 'منتج رقمي', 'ملفات رقمية أو تحميل', 1),
(4, 'bundle', 'حزمة', 'مجموعة منتجات تباع معًا', 1),
(5, 'service', 'خدمة', 'خدمة غير ملموسة', 1),
(6, 'subscription', 'اشتراك', 'منتج بنظام اشتراك', 1);

-- --------------------------------------------------------

--
-- Table structure for table `product_variants`
--

CREATE TABLE `product_variants` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `low_stock_threshold` int(11) DEFAULT 5,
  `is_active` tinyint(1) DEFAULT 1,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `product_variant_attributes` (
  `id` bigint(20) NOT NULL,
  `variant_id` bigint(20) NOT NULL,
  `attribute_id` bigint(20) NOT NULL,
  `attribute_value_id` bigint(20) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_variant_attributes`
--

INSERT INTO `product_variant_attributes` (`id`, `variant_id`, `attribute_id`, `attribute_value_id`, `created_at`) VALUES
(1, 1, 3, 10, '2025-12-21 05:23:25'),
(2, 1, 4, 20, '2025-12-21 05:23:25'),
(3, 2, 3, 11, '2025-12-21 05:23:25'),
(4, 2, 4, 21, '2025-12-21 05:23:25');

-- --------------------------------------------------------

--
-- Table structure for table `product_variant_translations`
--

CREATE TABLE `product_variant_translations` (
  `id` bigint(20) NOT NULL,
  `variant_id` bigint(20) NOT NULL,
  `language_code` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `provider_zones`
--

CREATE TABLE `provider_zones` (
  `provider_id` bigint(20) UNSIGNED NOT NULL,
  `zone_id` bigint(20) UNSIGNED NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `queues`
--

CREATE TABLE `queues` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `job_type` varchar(100) DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `payload` longtext NOT NULL CHECK (json_valid(`payload`)),
  `status` tinyint(4) NOT NULL DEFAULT 0,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `error` mediumtext DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `available_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `queues`
--

INSERT INTO `queues` (`id`, `queue`, `entity_type`, `entity_id`, `job_type`, `priority`, `payload`, `status`, `attempts`, `error`, `created_at`, `available_at`, `updated_at`, `processed_at`) VALUES
(3, 'notifications', NULL, NULL, NULL, 'normal', '{\"user_id\":123,\"message\":\"You have a new message\"}', 0, 0, NULL, '2026-02-11 14:20:52', NULL, NULL, NULL),
(4, 'reports', NULL, NULL, NULL, 'normal', '{\"type\":\"daily_summary\",\"user_id\":456}', 0, 0, NULL, '2026-02-11 14:20:52', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `queues_archive`
--

CREATE TABLE `queues_archive` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(100) NOT NULL,
  `payload` longtext NOT NULL CHECK (json_valid(`payload`)),
  `status` tinyint(4) NOT NULL DEFAULT 0,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `error` mediumtext DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `available_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `job_type` varchar(100) DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `execution_time_ms` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `recently_viewed_products`
--

CREATE TABLE `recently_viewed_products` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `product_id` bigint(20) NOT NULL,
  `viewed_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `recently_viewed_products`
--

INSERT INTO `recently_viewed_products` (`id`, `user_id`, `session_id`, `product_id`, `viewed_at`) VALUES
(0, 7, 'dmnulid829dki2pfsgkmr0gtbv', 44, '2026-04-10 18:47:59'),
(1, 1, 'sess_123abc', 1, '2025-12-14 03:16:57'),
(2, 1, 'sess_123abc', 2, '2025-12-14 03:16:57'),
(3, 2, 'sess_456def', 1, '2025-12-14 03:16:57');

-- --------------------------------------------------------

--
-- Table structure for table `referrals`
--

CREATE TABLE `referrals` (
  `id` bigint(20) NOT NULL,
  `referral_code` varchar(50) NOT NULL,
  `referrer_user_id` int(11) NOT NULL,
  `referred_user_id` int(11) DEFAULT NULL,
  `referred_email` varchar(191) DEFAULT NULL,
  `referrer_reward_points` int(11) DEFAULT 0,
  `referred_reward_points` int(11) DEFAULT 0,
  `referrer_reward_amount` decimal(15,2) DEFAULT 0.00,
  `referred_reward_amount` decimal(15,2) DEFAULT 0.00,
  `status` enum('pending','registered','completed','expired') DEFAULT 'pending',
  `first_purchase_at` datetime DEFAULT NULL,
  `rewards_given_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `referrals`
--

INSERT INTO `referrals` (`id`, `referral_code`, `referrer_user_id`, `referred_user_id`, `referred_email`, `referrer_reward_points`, `referred_reward_points`, `referrer_reward_amount`, `referred_reward_amount`, `status`, `first_purchase_at`, `rewards_given_at`, `created_at`) VALUES
(1, 'REF-USER1-ABC123', 1, 2, NULL, 200, 100, 0.00, 0.00, 'completed', '2024-01-15 14:00:00', '2024-01-15 14:30:00', '2025-12-14 02:49:27');

-- --------------------------------------------------------

--
-- Table structure for table `regions`
--

CREATE TABLE `regions` (
  `id` int(11) NOT NULL,
  `country_id` int(11) NOT NULL,
  `city_id` int(11) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `type` enum('state','region','district','neighborhood','zone') DEFAULT 'region',
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(11,7) DEFAULT NULL,
  `location` point DEFAULT NULL,
  `metadata` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_exports`
--

CREATE TABLE `report_exports` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `report_type` varchar(100) NOT NULL,
  `export_format` enum('excel','pdf','csv') NOT NULL DEFAULT 'excel',
  `filters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Filters used when generating' CHECK (json_valid(`filters`)),
  `status` enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
  `file_path` varchar(500) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `requested_by` int(11) UNSIGNED DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `report_exports`
--

INSERT INTO `report_exports` (`id`, `tenant_id`, `report_type`, `export_format`, `filters`, `status`, `file_path`, `file_size`, `error_message`, `requested_by`, `completed_at`, `expires_at`, `created_at`) VALUES
(1, 0, 'revenue_profit', 'pdf', NULL, 'pending', NULL, NULL, NULL, 7, NULL, NULL, '2026-03-31 07:53:26'),
(2, 0, 'sales_overview', 'csv', NULL, 'pending', NULL, NULL, NULL, 7, NULL, NULL, '2026-03-31 08:11:34'),
(3, 0, 'sales_overview', 'excel', NULL, 'pending', NULL, NULL, NULL, 7, NULL, NULL, '2026-03-31 11:12:17'),
(4, 0, 'sales_overview', 'excel', NULL, 'pending', NULL, NULL, NULL, 7, NULL, NULL, '2026-03-31 12:26:07'),
(5, 0, 'sales_overview', 'pdf', NULL, 'pending', NULL, NULL, NULL, 7, NULL, NULL, '2026-03-31 12:26:25');

-- --------------------------------------------------------

--
-- Table structure for table `report_schedules`
--

CREATE TABLE `report_schedules` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `report_type` varchar(100) NOT NULL,
  `frequency` enum('daily','weekly','monthly') NOT NULL DEFAULT 'daily',
  `recipients_email` text DEFAULT NULL COMMENT 'Comma-separated email addresses',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_run_at` datetime DEFAULT NULL,
  `next_run_at` datetime DEFAULT NULL,
  `created_by` int(11) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_types`
--

CREATE TABLE `report_types` (
  `id` int(10) UNSIGNED NOT NULL,
  `type_key` varchar(100) NOT NULL COMMENT 'e.g. sales_overview, revenue_profit',
  `title_en` varchar(255) NOT NULL,
  `title_ar` varchar(255) DEFAULT NULL,
  `description_en` text DEFAULT NULL,
  `description_ar` text DEFAULT NULL,
  `category` enum('sales','finance','products','ads','logistics','customers','platform') NOT NULL DEFAULT 'sales',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `report_types`
--

INSERT INTO `report_types` (`id`, `type_key`, `title_en`, `title_ar`, `description_en`, `description_ar`, `category`, `is_active`, `sort_order`, `created_at`) VALUES
(1, 'sales_overview', 'Sales Overview', 'نظرة عامة على المبيعات', NULL, NULL, 'sales', 1, 1, '2026-03-30 22:25:58'),
(2, 'revenue_profit', 'Revenue & Profit', 'الإيرادات والأرباح', NULL, NULL, 'finance', 1, 2, '2026-03-30 22:25:58'),
(3, 'orders_performance', 'Orders Performance', 'أداء الطلبات', NULL, NULL, 'sales', 1, 3, '2026-03-30 22:25:58'),
(4, 'products_performance', 'Products Performance', 'أداء المنتجات', NULL, NULL, 'products', 1, 4, '2026-03-30 22:25:58'),
(5, 'ads_performance', 'Ads Performance', 'أداء الإعلانات', NULL, NULL, 'ads', 1, 5, '2026-03-30 22:25:58'),
(6, 'returns_complaints', 'Returns & Complaints', 'المرتجعات والشكاوى', NULL, NULL, 'logistics', 1, 6, '2026-03-30 22:25:58'),
(7, 'entities_performance', 'Entities Performance', 'أداء المتاجر', NULL, NULL, 'sales', 1, 7, '2026-03-30 22:25:58'),
(8, 'customer_behavior', 'Customer Behavior', 'سلوك العملاء', NULL, NULL, 'customers', 1, 8, '2026-03-30 22:25:58'),
(9, 'platform_health', 'Platform Health', 'صحة المنصة', NULL, NULL, 'platform', 1, 9, '2026-03-30 22:25:58');

-- --------------------------------------------------------

--
-- Table structure for table `resource_permissions`
--

CREATE TABLE `resource_permissions` (
  `id` int(11) NOT NULL,
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `resource_type` varchar(50) NOT NULL COMMENT 'users, products, orders, etc',
  `can_view_all` tinyint(1) NOT NULL DEFAULT 0,
  `can_view_own` tinyint(1) NOT NULL DEFAULT 0,
  `can_view_tenant` tinyint(1) NOT NULL DEFAULT 0,
  `can_create` tinyint(1) NOT NULL DEFAULT 0,
  `can_edit_all` tinyint(1) NOT NULL DEFAULT 0,
  `can_edit_own` tinyint(1) NOT NULL DEFAULT 0,
  `can_delete_all` tinyint(1) NOT NULL DEFAULT 0,
  `can_delete_own` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `tenant_key` int(10) UNSIGNED GENERATED ALWAYS AS (coalesce(`tenant_id`,0)) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `resource_permissions`
--

INSERT INTO `resource_permissions` (`id`, `permission_id`, `role_id`, `tenant_id`, `resource_type`, `can_view_all`, `can_view_own`, `can_view_tenant`, `can_create`, `can_edit_all`, `can_edit_own`, `can_delete_all`, `can_delete_own`, `created_at`) VALUES
(1, 1, 1, 1, 'products', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-15 05:02:32'),
(2, 2, 1, 1, 'orders', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-15 05:02:32'),
(3, 3, 1, 1, 'users', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-15 05:02:32'),
(4, 4, 1, 1, 'tenant_users', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-15 05:02:32'),
(5, 5, 1, 1, 'vendors', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-15 05:02:32'),
(6, 6, 1, 1, 'entities', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-15 05:02:32'),
(7, 7, 1, 1, 'entities', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-15 05:02:32'),
(8, 8, 1, 1, 'catalog', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-15 05:02:32'),
(9, 9, 1, 1, 'catalog', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-15 05:02:32'),
(10, 10, 1, 1, 'marketing', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-15 05:02:32'),
(11, 11, 1, 1, 'marketing', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-15 05:02:32'),
(12, 12, 1, 1, 'finance', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-15 05:02:32'),
(13, 13, 1, 1, 'finance', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-15 05:02:32'),
(14, 14, 1, 1, 'delivery', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-15 05:02:32'),
(15, 15, 1, 1, 'system', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-15 05:02:32'),
(16, 16, 1, 1, 'system', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-15 05:02:32'),
(17, 17, 1, 1, 'system', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-15 05:02:32'),
(18, 18, 1, 1, 'system', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-15 05:02:32'),
(19, 19, 1, 1, 'system', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-15 05:02:32'),
(20, 20, 1, 1, 'system', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-15 05:02:32'),
(21, 21, 1, 1, 'appearance', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-15 05:02:32'),
(22, 22, 1, 1, 'appearance', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-15 05:02:32'),
(23, 23, 1, 1, 'utility', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-15 05:02:32'),
(24, 24, 1, 1, 'moderation', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-15 05:02:32'),
(32, 25, 1, 1, 'job_categories', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-15 09:35:34'),
(34, 26, 1, 1, 'certificates_products', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-19 13:14:00'),
(36, 27, 1, 1, 'certificates_requests', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-20 04:55:32'),
(38, 28, 1, 1, 'notifications', 1, 1, 1, 1, 1, 1, 1, 1, '2026-02-27 06:46:37');

-- --------------------------------------------------------

--
-- Table structure for table `returns`
--

CREATE TABLE `returns` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `return_number` varchar(50) DEFAULT NULL,
  `status` enum('pending','approved','rejected','processing','completed','cancelled') DEFAULT 'pending',
  `reason` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `requested_at` datetime DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `returns`
--

INSERT INTO `returns` (`id`, `tenant_id`, `order_id`, `user_id`, `entity_id`, `return_number`, `status`, `reason`, `admin_notes`, `requested_at`, `processed_at`, `created_at`, `updated_at`) VALUES
(1, 1, 7, 7, NULL, 'RET-1-1773606504-343', 'pending', 'يي', NULL, '2026-03-15 16:28:24', NULL, '2026-03-15 16:28:24', '2026-03-15 16:28:24');

-- --------------------------------------------------------

--
-- Table structure for table `return_items`
--

CREATE TABLE `return_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `return_id` bigint(20) UNSIGNED NOT NULL,
  `order_item_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL,
  `reason` text DEFAULT NULL,
  `refund_amount` decimal(12,2) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `return_status_history`
--

CREATE TABLE `return_status_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `return_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(50) NOT NULL,
  `changed_by` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `review_queue`
--

CREATE TABLE `review_queue` (
  `id` bigint(20) NOT NULL,
  `entity_type` varchar(64) NOT NULL,
  `entity_id` bigint(20) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `priority` int(11) DEFAULT 0,
  `payload` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `key_name` varchar(100) NOT NULL,
  `display_name` varchar(150) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `tenant_id`, `key_name`, `display_name`, `created_at`) VALUES
(1, 1, 'super_admin', 'Platform Super Admin', '2026-02-15 09:23:57'),
(2, 1, 'platform_admin', 'Platform Admin', '2026-02-15 09:23:57'),
(3, 1, 'platform_support', 'Platform Support', '2026-02-15 09:23:57'),
(4, 1, 'tenant_owner', 'Tenant Owner', '2026-02-15 09:23:57'),
(5, 1, 'tenant_admin', 'Tenant Admin', '2026-02-15 09:23:57'),
(6, 1, 'tenant_manager', 'Tenant Manager', '2026-02-15 09:23:57'),
(7, 1, 'tenant_accountant', 'Tenant Accountant', '2026-02-15 09:23:57'),
(8, 1, 'tenant_marketer', 'Tenant Marketer', '2026-02-15 09:23:57'),
(9, 1, 'tenant_support', 'Tenant Support', '2026-02-15 09:23:57'),
(10, 1, 'entity_owner', 'Store Owner', '2026-02-15 09:23:57'),
(11, 1, 'entity_manager', 'Store Manager', '2026-02-15 09:23:57'),
(12, 1, 'entity_staff', 'Store Staff', '2026-02-15 09:23:57'),
(13, 1, 'entity_cashier', 'Cashier', '2026-02-15 09:23:57'),
(14, 1, 'entity_delivery', 'Delivery Staff', '2026-02-15 09:23:57'),
(15, 1, 'entity_inventory_manager', 'Inventory Manager', '2026-02-15 09:23:57');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` bigint(20) NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `tenant_id`, `role_id`, `permission_id`, `created_at`) VALUES
(1, 1, 1, 1, '2026-02-15 04:43:11'),
(2, 1, 1, 2, '2026-02-15 04:43:11'),
(3, 1, 1, 3, '2026-02-15 04:43:11'),
(4, 1, 1, 4, '2026-02-15 04:43:11'),
(5, 1, 1, 5, '2026-02-15 04:43:11'),
(6, 1, 1, 6, '2026-02-15 04:43:11'),
(7, 1, 1, 7, '2026-02-15 04:43:11'),
(8, 1, 1, 8, '2026-02-15 04:43:11'),
(9, 1, 1, 9, '2026-02-15 04:43:11'),
(10, 1, 1, 10, '2026-02-15 04:43:11'),
(11, 1, 1, 11, '2026-02-15 04:43:11'),
(12, 1, 1, 12, '2026-02-15 04:43:11'),
(13, 1, 1, 13, '2026-02-15 04:43:11'),
(14, 1, 1, 14, '2026-02-15 04:43:11'),
(15, 1, 1, 15, '2026-02-15 04:43:11'),
(16, 1, 1, 16, '2026-02-15 04:43:11'),
(17, 1, 1, 17, '2026-02-15 04:43:11'),
(18, 1, 1, 18, '2026-02-15 04:43:11'),
(19, 1, 1, 19, '2026-02-15 04:43:11'),
(20, 1, 1, 20, '2026-02-15 04:43:11'),
(21, 1, 1, 21, '2026-02-15 04:43:11'),
(22, 1, 1, 22, '2026-02-15 04:43:11'),
(23, 1, 1, 23, '2026-02-15 04:43:11'),
(24, 1, 1, 24, '2026-02-15 04:43:11'),
(25, 1, 1, 25, '2026-02-15 09:34:49'),
(26, 1, 1, 26, '2026-02-19 13:13:30'),
(27, 1, 1, 27, '2026-02-20 04:55:01'),
(28, 1, 1, 28, '2026-02-27 06:46:09');

-- --------------------------------------------------------

--
-- Table structure for table `saved_bank_accounts`
--

CREATE TABLE `saved_bank_accounts` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `vendor_id` bigint(20) DEFAULT NULL,
  `account_holder_name` varchar(255) NOT NULL,
  `bank_name` varchar(255) NOT NULL,
  `account_number` varchar(100) NOT NULL,
  `iban` varchar(50) DEFAULT NULL,
  `swift_code` varchar(20) DEFAULT NULL,
  `branch_name` varchar(255) DEFAULT NULL,
  `country_id` int(11) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `saved_bank_accounts`
--

INSERT INTO `saved_bank_accounts` (`id`, `user_id`, `vendor_id`, `account_holder_name`, `bank_name`, `account_number`, `iban`, `swift_code`, `branch_name`, `country_id`, `is_primary`, `is_verified`, `verified_at`, `created_at`, `updated_at`) VALUES
(1, 1, NULL, 'Ahmed Mohammed', 'Al Rajhi Bank', '123456789012', 'SA0380000000608010167519', NULL, NULL, 1, 1, 1, NULL, '2025-12-14 02:46:35', '2025-12-14 02:46:35'),
(2, 2, NULL, 'Sara Ali', 'Saudi National Bank', '234567890123', 'SA1234567890123456789012', NULL, NULL, 1, 1, 1, NULL, '2025-12-14 02:46:35', '2025-12-14 02:46:35'),
(3, NULL, 1, 'Tech Store SA', 'National Commercial Bank', '987654321098', 'SA4420000001234567891234', NULL, NULL, 1, 1, 1, NULL, '2025-12-14 02:46:44', '2025-12-14 02:46:44'),
(4, NULL, 2, 'Fashion Hub', 'Riyad Bank', '876543210987', 'SA5530000002345678912345', NULL, NULL, 1, 1, 1, NULL, '2025-12-14 02:46:44', '2025-12-14 02:46:44');

-- --------------------------------------------------------

--
-- Table structure for table `saved_carts`
--

CREATE TABLE `saved_carts` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `cart_name` varchar(255) NOT NULL,
  `total_items` int(11) DEFAULT 0,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `currency_code` varchar(8) DEFAULT 'SAR',
  `notes` text DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `saved_carts`
--

INSERT INTO `saved_carts` (`id`, `user_id`, `cart_name`, `total_items`, `total_amount`, `currency_code`, `notes`, `is_default`, `created_at`, `updated_at`) VALUES
(1, 1, 'Weekly Shopping', 3, 2500.00, 'SAR', NULL, 1, '2025-12-14 03:16:49', '2025-12-14 03:16:49');

-- --------------------------------------------------------

--
-- Table structure for table `saved_cart_items`
--

CREATE TABLE `saved_cart_items` (
  `id` bigint(20) NOT NULL,
  `saved_cart_id` bigint(20) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `product_variant_id` bigint(20) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `selected_attributes` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `added_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `saved_jobs`
--

CREATE TABLE `saved_jobs` (
  `id` bigint(20) NOT NULL,
  `job_id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `saved_jobs`
--

INSERT INTO `saved_jobs` (`id`, `job_id`, `user_id`, `notes`, `created_at`) VALUES
(1, 1, 1, 'Great opportunity, apply by end of month', '2025-12-14 02:25:33'),
(2, 2, 1, 'Interesting role, need to update CV first', '2025-12-14 02:25:33');

-- --------------------------------------------------------

--
-- Table structure for table `search_logs`
--

CREATE TABLE `search_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `query` varchar(255) NOT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `lang` varchar(10) NOT NULL DEFAULT 'ar',
  `count` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `last_searched_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `search_logs`
--

INSERT INTO `search_logs` (`id`, `query`, `tenant_id`, `user_id`, `entity_id`, `lang`, `count`, `last_searched_at`) VALUES
(1, 'Tr', 1, NULL, 1, 'en', 1, '2026-03-27 08:11:55'),
(2, 'Tr', 1, 1, 1, 'en', 1, '2026-03-27 08:11:55'),
(3, 'Tra', 1, NULL, 1, 'en', 1, '2026-03-27 08:11:57'),
(4, 'Tra', 1, 1, 1, 'en', 1, '2026-03-27 08:11:57'),
(5, 'Trav', 1, NULL, NULL, 'en', 1, '2026-03-27 08:11:58'),
(6, 'Trav', 1, 1, NULL, 'en', 1, '2026-03-27 08:11:58'),
(7, 'Travl', 1, NULL, NULL, 'en', 1, '2026-03-27 08:11:59'),
(8, 'Travl', 1, 1, NULL, 'en', 1, '2026-03-27 08:11:59'),
(9, 'UU', 1, NULL, NULL, 'en', 1, '2026-03-27 08:12:49'),
(10, 'UU', 1, 1, NULL, 'en', 1, '2026-03-27 08:12:49'),
(11, 'So', 1, NULL, 1, 'en', 1, '2026-03-27 08:13:17'),
(12, 'So', 1, 1, 1, 'en', 1, '2026-03-27 08:13:17'),
(13, 'Son', 1, NULL, 1, 'en', 1, '2026-03-27 08:13:19'),
(14, 'Son', 1, 1, 1, 'en', 1, '2026-03-27 08:13:19'),
(15, 'Sony', 1, NULL, 1, 'en', 1, '2026-03-27 08:13:21'),
(16, 'Sony', 1, 1, 1, 'en', 1, '2026-03-27 08:13:21'),
(17, 'أبل', 1, NULL, NULL, 'ar', 1, '2026-03-27 08:27:48'),
(18, 'أبل', 1, 7, NULL, 'ar', 1, '2026-03-27 08:27:48');

-- --------------------------------------------------------

--
-- Table structure for table `seo_meta`
--

CREATE TABLE `seo_meta` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` bigint(20) UNSIGNED DEFAULT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `canonical_url` varchar(255) DEFAULT NULL,
  `robots` varchar(50) DEFAULT NULL,
  `schema_markup` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `seo_meta_translations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `seo_meta_id` bigint(20) UNSIGNED NOT NULL,
  `language_code` varchar(8) NOT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `meta_keywords` varchar(255) DEFAULT NULL,
  `og_title` varchar(255) DEFAULT NULL,
  `og_description` text DEFAULT NULL,
  `og_image` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sms_templates` (
  `id` bigint(20) NOT NULL,
  `template_key` varchar(100) NOT NULL,
  `template_name` varchar(255) NOT NULL,
  `template_name_ar` varchar(255) NOT NULL,
  `message` mediumtext NOT NULL,
  `message_ar` mediumtext NOT NULL,
  `variables` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `store_pages`
--

CREATE TABLE `store_pages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'store' COMMENT 'Page type: store, landing, etc.',
  `slug` varchar(255) DEFAULT NULL COMMENT 'Optional custom slug override',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Page-level settings (theme, colors, etc.)' CHECK (json_valid(`settings`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `store_pages`
--

INSERT INTO `store_pages` (`id`, `tenant_id`, `entity_id`, `type`, `slug`, `is_active`, `settings`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'store', 'store-main', 1, NULL, '2026-03-28 20:15:43', '2026-03-28 20:15:43'),
(2, 1, 7, 'store', NULL, 1, NULL, '2026-03-30 16:36:27', '2026-03-30 16:36:27');

-- --------------------------------------------------------

--
-- Table structure for table `store_sections`
--

CREATE TABLE `store_sections` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `page_id` bigint(20) UNSIGNED NOT NULL,
  `type` varchar(50) NOT NULL COMMENT 'header, contact, tabs, products, info, hours, location, offers, reviews',
  `position` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Section-specific JSON settings' CHECK (json_valid(`settings`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `store_section_translations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `section_id` bigint(20) UNSIGNED NOT NULL,
  `language_code` varchar(10) NOT NULL DEFAULT 'en',
  `title` varchar(255) DEFAULT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Localized content as JSON' CHECK (json_valid(`content`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `subscriptions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `subscription_number` varchar(100) NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `plan_id` bigint(20) UNSIGNED NOT NULL,
  `status` enum('trial','active','paused','cancelled','expired','suspended') DEFAULT 'trial',
  `billing_period` enum('daily','weekly','monthly','quarterly','yearly','lifetime') NOT NULL,
  `price` decimal(15,2) NOT NULL,
  `currency_code` varchar(8) DEFAULT 'USD',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `trial_end_date` date DEFAULT NULL,
  `next_billing_date` date DEFAULT NULL,
  `auto_renew` tinyint(1) DEFAULT 1,
  `cancelled_at` datetime DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `suspended_at` datetime DEFAULT NULL,
  `suspension_reason` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `subscription_number`, `tenant_id`, `plan_id`, `status`, `billing_period`, `price`, `currency_code`, `start_date`, `end_date`, `trial_end_date`, `next_billing_date`, `auto_renew`, `cancelled_at`, `cancellation_reason`, `suspended_at`, `suspension_reason`, `created_at`, `updated_at`) VALUES
(1, 'SUB-20260214-45707', 1, 2, 'active', 'monthly', 5.00, 'USD', '2026-02-14', '2026-03-14', NULL, '2026-03-14', 1, NULL, NULL, NULL, NULL, '2026-02-14 01:10:43', '2026-02-14 01:10:43');

-- --------------------------------------------------------

--
-- Table structure for table `subscription_invoices`
--

CREATE TABLE `subscription_invoices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_number` varchar(100) NOT NULL,
  `subscription_id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL,
  `currency_code` varchar(8) DEFAULT 'SAR',
  `billing_period_start` date NOT NULL,
  `billing_period_end` date NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('pending','paid','overdue','cancelled','refunded') DEFAULT 'pending',
  `paid_at` datetime DEFAULT NULL,
  `payment_method` varchar(100) DEFAULT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscription_invoices`
--

INSERT INTO `subscription_invoices` (`id`, `invoice_number`, `subscription_id`, `tenant_id`, `amount`, `tax_amount`, `total_amount`, `currency_code`, `billing_period_start`, `billing_period_end`, `due_date`, `status`, `paid_at`, `payment_method`, `transaction_id`, `notes`, `created_at`) VALUES
(1, 'INV-20260214-03657', 1, 1, 5.00, 0.00, 5.00, 'USD', '2026-02-14', '2026-03-14', '2026-02-14', 'pending', NULL, NULL, NULL, NULL, '2026-02-14 01:10:43');

-- --------------------------------------------------------

--
-- Table structure for table `subscription_payments`
--

CREATE TABLE `subscription_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `payment_number` varchar(100) NOT NULL,
  `invoice_id` bigint(20) UNSIGNED NOT NULL,
  `subscription_id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency_code` varchar(8) DEFAULT 'SAR',
  `payment_gateway` varchar(100) NOT NULL,
  `gateway_transaction_id` varchar(255) DEFAULT NULL,
  `gateway_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gateway_response`)),
  `status` enum('pending','success','failed','refunded') DEFAULT 'pending',
  `paid_at` datetime DEFAULT NULL,
  `refunded_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscription_payments`
--

INSERT INTO `subscription_payments` (`id`, `payment_number`, `invoice_id`, `subscription_id`, `tenant_id`, `amount`, `currency_code`, `payment_gateway`, `gateway_transaction_id`, `gateway_response`, `status`, `paid_at`, `refunded_at`, `created_at`) VALUES
(1, 'PAY-20260214-63480', 1, 1, 1, 5.00, 'USD', '1', 'TXN-fdl2tgyjrv29', NULL, 'pending', NULL, NULL, '2026-02-14 01:10:43');

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plans`
--

CREATE TABLE `subscription_plans` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `plan_name` varchar(255) NOT NULL,
  `code` varchar(50) DEFAULT NULL,
  `plan_type` enum('vendor','delivery','supplier','premium_user') NOT NULL,
  `billing_period` enum('daily','weekly','monthly','quarterly','yearly','lifetime') NOT NULL,
  `price` decimal(15,2) NOT NULL,
  `currency_code` varchar(8) DEFAULT 'USD',
  `setup_fee` decimal(15,2) DEFAULT 0.00,
  `commission_rate` decimal(5,2) DEFAULT 0.00,
  `max_products` int(11) DEFAULT NULL,
  `max_branches` int(11) DEFAULT NULL,
  `max_orders_per_month` int(11) DEFAULT NULL,
  `max_staff` int(11) DEFAULT NULL,
  `analytics_access` tinyint(1) DEFAULT 0,
  `priority_support` tinyint(1) DEFAULT 0,
  `featured_listing` tinyint(1) DEFAULT 0,
  `custom_domain` tinyint(1) DEFAULT 0,
  `api_access` tinyint(1) DEFAULT 0,
  `trial_period_days` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscription_plans`
--

INSERT INTO `subscription_plans` (`id`, `plan_name`, `code`, `plan_type`, `billing_period`, `price`, `currency_code`, `setup_fee`, `commission_rate`, `max_products`, `max_branches`, `max_orders_per_month`, `max_staff`, `analytics_access`, `priority_support`, `featured_listing`, `custom_domain`, `api_access`, `trial_period_days`, `is_active`, `is_featured`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Free Plan', 'free', 'vendor', 'monthly', 0.00, 'USD', 0.00, 0.00, 2, 1, 50, 2, 0, 0, 0, 0, 0, 7, 1, 0, 1, '2026-02-13 11:42:11', '2026-02-13 11:43:20'),
(2, 'Basic Plan', 'basic', 'vendor', 'monthly', 5.00, 'USD', 0.00, 5.00, 50, 1, 200, 5, 1, 0, 0, 0, 0, 7, 1, 0, 2, '2026-02-13 11:42:11', '2026-02-13 11:42:11'),
(3, 'Standard Plan', 'standard', 'vendor', 'monthly', 15.00, 'USD', 0.00, 10.00, 150, 2, 500, 10, 1, 1, 0, 0, 0, 7, 1, 0, 3, '2026-02-13 11:42:11', '2026-02-13 11:42:11'),
(4, 'Pro Plan', 'pro', 'vendor', 'monthly', 20.00, 'USD', 0.00, 12.50, 300, 3, 800, 15, 1, 1, 1, 0, 0, 7, 1, 0, 4, '2026-02-13 11:42:11', '2026-02-13 11:42:11'),
(5, 'Premium Plan', 'premium', 'vendor', 'monthly', 50.00, 'USD', 0.00, 20.00, 500, 5, 1500, 20, 1, 1, 1, 1, 1, 7, 1, 1, 5, '2026-02-13 11:42:11', '2026-02-13 11:42:11');

-- --------------------------------------------------------

--
-- Table structure for table `subscription_plan_translations`
--

CREATE TABLE `subscription_plan_translations` (
  `id` bigint(20) NOT NULL,
  `plan_id` bigint(20) NOT NULL,
  `language_code` varchar(8) NOT NULL,
  `plan_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `features` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscription_plan_translations`
--

INSERT INTO `subscription_plan_translations` (`id`, `plan_id`, `language_code`, `plan_name`, `description`, `features`) VALUES
(1, 1, 'ar', 'الخطة المجانية', 'خطة مجانية للمبتدئين مع ميزات محدودة', 'إضافة حتى 2 منتجات, فرع واحد, 50 طلب شهريًا, 2 موظفين'),
(2, 2, 'ar', 'الخطة الأساسية', 'خطة بسيطة للمشاريع الصغيرة', 'إضافة حتى 50 منتج, فرع واحد, 200 طلب شهريًا, 5 موظفين, تحليلات أساسية'),
(3, 3, 'ar', 'الخطة القياسية', 'خطة متوسطة للشركات المتوسطة', 'إضافة حتى 150 منتج, فرع واحد, 500 طلب شهريًا, 10 موظفين, تحليلات كاملة, دعم أولوية'),
(4, 4, 'ar', 'خطة متقدمة', 'خطة كبيرة للشركات النامية', 'إضافة حتى 500 منتج, فرع واحد, 2000 طلب شهريًا, 25 موظفين, تحليلات كاملة, دعم أولوية, ميزات مميزة'),
(5, 5, 'ar', 'خطة احترافية', 'خطة للمؤسسات الكبرى', 'إضافة حتى 1000 منتج, عدة فروع, طلبات غير محدودة, موظفين غير محدودين, تحليلات كاملة, دعم أولوية, ميزات مميزة, دومين مخصص');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_product_translations`
--

CREATE TABLE `supplier_product_translations` (
  `id` bigint(20) NOT NULL,
  `supplier_product_id` bigint(20) NOT NULL,
  `language_code` varchar(8) NOT NULL,
  `product_name` varchar(500) NOT NULL,
  `description` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `ticket_number` varchar(100) DEFAULT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `order_id` bigint(20) UNSIGNED DEFAULT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `subject` varchar(500) NOT NULL,
  `description` mediumtext NOT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `status` enum('open','pending','awaiting_customer','awaiting_vendor','in_progress','resolved','closed','cancelled') DEFAULT 'open',
  `assigned_to` int(10) UNSIGNED DEFAULT NULL,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `support_tickets`
--

INSERT INTO `support_tickets` (`id`, `tenant_id`, `ticket_number`, `user_id`, `entity_id`, `order_id`, `category_id`, `subject`, `description`, `priority`, `status`, `assigned_to`, `attachments`, `created_at`, `updated_at`) VALUES
(1, 1, 'TKT-20260315-1979', 7, 16, 7, 1002, 'Test activations', 'dd', 'low', 'open', NULL, NULL, '2026-03-15 06:31:44', '2026-03-15 11:37:04'),
(2, 1, NULL, 7, NULL, NULL, 1, 'Test activationdd', 'ddd', 'normal', 'open', NULL, NULL, '2026-03-15 17:18:34', '2026-03-15 17:18:34'),
(3, 1, NULL, 1, NULL, NULL, 6, 'Test activation', 'ييي', 'low', 'open', NULL, NULL, '2026-03-23 15:18:14', '2026-03-23 15:18:14');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` bigint(20) NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','number','boolean','json','file','email') DEFAULT 'text',
  `category` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `is_editable` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `tenant_id`, `setting_key`, `setting_value`, `setting_type`, `category`, `description`, `is_public`, `is_editable`, `created_at`, `updated_at`) VALUES
(1, 1, 'site_name', 'Qooqz Marketplace', 'text', 'general', 'Website name', 1, 1, '2025-12-14 03:42:24', '2026-01-20 13:18:57'),
(2, 1, 'site_email', 'info@qooqz.com', 'email', 'general', 'Contact email', 1, 1, '2025-12-14 03:42:24', '2026-01-20 13:19:06'),
(3, 1, 'default_currency', 'ADE', 'text', 'general', 'Default currency', 1, 1, '2025-12-14 03:42:24', '2026-02-07 09:11:14'),
(4, 1, 'default_language', 'ar', 'text', 'general', 'Default language', 1, 1, '2025-12-14 03:42:24', '2026-01-20 13:19:58'),
(5, 1, 'tax_rate', '5%', 'number', 'financial', 'VAT tax rate percentage', 0, 1, '2025-12-14 03:42:24', '2026-02-07 09:12:02'),
(6, 1, 'commission_rate', '2%', 'number', 'financial', 'Default vendor commission rate', 0, 1, '2025-12-14 03:42:24', '2026-02-07 09:11:42'),
(7, 1, 'min_order_amount', '50', 'number', 'orders', 'Minimum order amount', 1, 1, '2025-12-14 03:42:24', '2026-01-20 13:19:58'),
(8, 1, 'free_shipping_threshold', '200', 'number', 'shipping', 'Free shipping above this amount', 1, 1, '2025-12-14 03:42:24', '2026-01-20 13:19:58');

-- --------------------------------------------------------

--
-- Table structure for table `tax_classes`
--

CREATE TABLE `tax_classes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tax_classes`
--

INSERT INTO `tax_classes` (`id`, `name`, `description`, `is_active`, `created_at`) VALUES
(1, 'General Goods', 'Standard tax for general products', 1, '2026-02-06 08:20:11'),
(2, 'Electronics', 'Tax class for electronic devices', 1, '2026-02-06 08:20:11'),
(3, 'Alcohol', 'Tax class for alcoholic beverages', 1, '2026-02-06 08:20:11'),
(4, 'Tobacco', 'Tax class for tobacco products', 1, '2026-02-06 08:20:11'),
(5, 'Food', 'Tax class for food products', 1, '2026-02-06 08:20:11');

-- --------------------------------------------------------

--
-- Table structure for table `tenants`
--

CREATE TABLE `tenants` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `owner_user_id` int(11) UNSIGNED NOT NULL,
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `domain` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tenants`
--

INSERT INTO `tenants` (`id`, `name`, `owner_user_id`, `status`, `created_at`, `updated_at`, `domain`) VALUES
(1, 'Global Tenant', 7, 'active', '2026-01-19 17:14:44', '2026-03-18 20:48:45', 'default.local'),
(2, 'mahmoud zidan', 1, 'active', '2026-01-25 08:01:41', '2026-02-14 08:12:29', 'default.local'),
(3, 'permissionsdjjhdd', 3, 'active', '2026-01-26 17:00:54', '2026-02-14 08:12:51', 'default.local'),
(5, 'womens-clot1hing', 1, 'active', '2026-02-27 20:36:22', '2026-03-18 19:59:47', 'default.local'),
(6, '6555', 6, 'active', '2026-04-07 15:58:44', '2026-04-07 15:58:44', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `tenant_categories`
--

CREATE TABLE `tenant_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `tenant_domains` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `domain` varchar(255) NOT NULL,
  `type` enum('primary','custom','subdomain','alias') NOT NULL DEFAULT 'custom',
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token` varchar(128) DEFAULT NULL COMMENT 'Used for DNS TXT / HTTP file challenge',
  `verified_at` datetime DEFAULT NULL,
  `ssl_status` enum('none','pending','active','failed') NOT NULL DEFAULT 'none',
  `ssl_expires_at` datetime DEFAULT NULL,
  `redirect_to_primary` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'When 1, HTTP 301 to the primary domain',
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Multi-domain registry for tenants. One tenant can own\r\n           many domains (primary, custom, subdomain, alias).';

--
-- Dumping data for table `tenant_domains`
--

INSERT INTO `tenant_domains` (`id`, `tenant_id`, `domain`, `type`, `is_verified`, `verification_token`, `verified_at`, `ssl_status`, `ssl_expires_at`, `redirect_to_primary`, `meta`, `created_at`, `updated_at`) VALUES
(1, 5, 'example.c1om', 'primary', 1, NULL, NULL, 'none', NULL, 0, NULL, '2026-03-18 01:14:02', '2026-03-18 20:01:37'),
(2, 3, 'example.com', 'custom', 0, '123', '2026-03-18 19:38:00', 'active', '2026-03-25 19:43:00', 1, NULL, '2026-03-18 15:41:33', NULL),
(3, 1, 'sub.example.com', 'custom', 1, NULL, '2026-03-18 14:16:00', 'none', NULL, 0, '1', '2026-03-18 18:15:33', '2026-03-18 20:49:01');

-- --------------------------------------------------------

--
-- Table structure for table `tenant_users`
--

CREATE TABLE `tenant_users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(11) UNSIGNED NOT NULL,
  `role_id` bigint(20) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `joined_at` datetime DEFAULT current_timestamp(),
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tenant_users`
--

INSERT INTO `tenant_users` (`id`, `tenant_id`, `user_id`, `role_id`, `entity_id`, `joined_at`, `is_active`, `updated_at`) VALUES
(1, 1, 7, 1, NULL, '2026-01-23 02:43:55', 1, '2026-01-24 17:38:25'),
(6, 1, 2, 1, NULL, '2026-01-24 11:45:48', 1, '2026-01-27 13:14:42'),
(12, 1, 3, 10, NULL, '2026-01-26 11:57:57', 1, '2026-01-27 12:51:27'),
(21, 1, 8, 1, NULL, '2026-01-27 08:37:18', 1, '2026-01-27 13:37:41'),
(23, 1, 1, 2, 1, '2026-02-13 04:41:10', 1, '2026-02-15 09:55:25'),
(24, 1, 6, 5, 1, '2026-02-15 07:41:37', 1, '2026-03-22 21:07:56'),
(25, 5, 1, 1, NULL, '2026-02-27 15:36:22', 1, '2026-02-27 20:36:22');

-- --------------------------------------------------------

--
-- Table structure for table `themes`
--

CREATE TABLE `themes` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `thumbnail_url` varchar(500) DEFAULT NULL,
  `preview_url` varchar(500) DEFAULT NULL,
  `version` varchar(50) DEFAULT '1.0.0',
  `author` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `themes`
--

INSERT INTO `themes` (`id`, `name`, `slug`, `description`, `thumbnail_url`, `preview_url`, `version`, `author`, `is_active`, `is_default`, `created_at`, `updated_at`, `tenant_id`) VALUES
(1, 'Default Theme', 'default', 'Clean and modern default theme', NULL, 'zz', '1.0.0', 'System', 1, 0, '2025-12-14 02:17:03', '2026-03-30 13:53:08', 1),
(2, 'Modern Theme', 'modern', 'A sleek, professional theme with a light background and vibrant accents.', NULL, NULL, '1.0.0', 'System', 0, 0, '2026-03-28 15:08:21', '2026-03-28 15:22:33', 1),
(3, 'Global Modern', 'global-modern', 'A clean, professional theme with a modern aesthetic inspired by top e‑commerce platforms.', NULL, NULL, '1.0.0', 'System', 0, 0, '2026-03-28 15:21:44', '2026-03-30 04:35:29', 1),
(4, 'Delivery Pulse', 'delivery-pulse', 'A dynamic theme with a striking gradient header and cards that each sport a unique border – perfect for delivery and service platforms.', NULL, NULL, '1.0.0', 'System', 0, 0, '2026-03-28 15:29:50', '2026-03-29 14:46:05', 1),
(5, 'Nexus Flow', 'nexus-flow', 'A distinctive theme that combines the energy of delivery platforms, the elegance of bookstores, and the futuristic vibe of a personal brand.', NULL, NULL, '1.0.0', 'System', 0, 1, '2026-03-28 16:23:23', '2026-03-29 14:45:26', 1),
(6, 'Calm Horizon', 'calm-horizon', 'ثيم فاتح هادئ وعصري مصمم خصيصاً للراحة البصرية والوضوح. ألوان ناعمة، تباين مريح، وواجهة نظيفة تناسب منصات التوصيل والمتاجر والخدمات.', NULL, NULL, '1.0.0', 'System Designer', 0, 0, '2026-03-28 16:43:20', '2026-03-29 15:53:02', 1);

-- --------------------------------------------------------

--
-- Table structure for table `ticket_categories`
--

CREATE TABLE `ticket_categories` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `priority_level` tinyint(4) DEFAULT 3,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `ticket_category_translations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `category_id` bigint(20) UNSIGNED NOT NULL,
  `language_code` varchar(10) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE `ticket_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `ticket_id` bigint(20) UNSIGNED NOT NULL,
  `sender_user_id` int(10) UNSIGNED NOT NULL,
  `message` text NOT NULL,
  `is_internal` tinyint(1) DEFAULT 0,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_messages`
--

INSERT INTO `ticket_messages` (`id`, `tenant_id`, `ticket_id`, `sender_user_id`, `message`, `is_internal`, `attachments`, `created_at`) VALUES
(1, 1, 1, 7, 'dddd', 1, NULL, '2026-03-15 06:32:07');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_status_history`
--

CREATE TABLE `ticket_status_history` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `ticket_id` bigint(20) UNSIGNED NOT NULL,
  `old_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) NOT NULL,
  `changed_by` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `timezones`
--

CREATE TABLE `timezones` (
  `id` int(10) UNSIGNED NOT NULL,
  `timezone` varchar(64) NOT NULL,
  `label` varchar(128) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `timezones`
--

INSERT INTO `timezones` (`id`, `timezone`, `label`) VALUES
(1, 'UTC', 'UTC'),
(2, 'Asia/Dubai', 'Dubai'),
(3, 'Asia/Riyadh', 'Riyadh'),
(4, 'Europe/London', 'London'),
(5, 'Europe/Paris', 'Paris'),
(6, 'America/New_York', 'New York'),
(7, 'America/Los_Angeles', 'Los Angeles'),
(8, 'Asia/Kolkata', 'Kolkata'),
(9, 'Asia/Tokyo', 'Tokyo'),
(10, 'Australia/Sydney', 'Sydney');

-- --------------------------------------------------------

--
-- Table structure for table `tokens_blacklist`
--

CREATE TABLE `tokens_blacklist` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `jti` varchar(64) NOT NULL COMMENT 'JWT ID unique identifier',
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('access','refresh','otp','password_reset') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `units`
--

CREATE TABLE `units` (
  `id` int(10) UNSIGNED NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `code` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `units`
--

INSERT INTO `units` (`id`, `tenant_id`, `code`, `created_at`, `updated_at`) VALUES
(1, NULL, 'kg', '2026-02-18 20:01:41', '2026-02-18 20:01:41'),
(2, NULL, 'g', '2026-02-18 20:01:41', '2026-02-18 20:01:41'),
(3, NULL, 'mg', '2026-02-18 20:01:41', '2026-02-18 20:01:41'),
(4, NULL, 'l', '2026-02-18 20:01:41', '2026-02-18 20:01:41'),
(5, NULL, 'ml', '2026-02-18 20:01:41', '2026-02-18 20:01:41'),
(6, NULL, 'pcs', '2026-02-18 20:01:41', '2026-02-18 20:01:41'),
(7, NULL, 'box', '2026-02-18 20:01:41', '2026-02-18 20:01:41'),
(8, NULL, 'pack', '2026-02-18 20:01:41', '2026-02-18 20:01:41'),
(9, NULL, 'balk', '2026-02-18 20:01:41', '2026-02-18 20:01:41');

-- --------------------------------------------------------

--
-- Table structure for table `units_translations`
--

CREATE TABLE `units_translations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `unit_id` int(10) UNSIGNED NOT NULL,
  `language_code` varchar(8) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `units_translations`
--

INSERT INTO `units_translations` (`id`, `unit_id`, `language_code`, `name`) VALUES
(1, 1, 'en', 'Kilogram'),
(2, 1, 'ar', 'كيلوغرام'),
(3, 2, 'en', 'Gram'),
(4, 2, 'ar', 'غرام'),
(5, 3, 'en', 'Milligram'),
(6, 3, 'ar', 'ملغرام'),
(7, 4, 'en', 'Liter'),
(8, 4, 'ar', 'لتر'),
(9, 5, 'en', 'Milliliter'),
(10, 5, 'ar', 'مليلتر'),
(11, 6, 'en', 'Piece'),
(12, 6, 'ar', 'قطعة'),
(13, 7, 'en', 'Box'),
(14, 7, 'ar', 'صندوق'),
(15, 8, 'en', 'Pack'),
(16, 8, 'ar', 'عبوة'),
(17, 9, 'en', 'Balk'),
(18, 9, 'ar', 'بالك');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(191) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `preferred_language` varchar(8) DEFAULT NULL,
  `phone` varchar(45) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `preferred_language`, `phone`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'mohd87', 'zedanmahmoud100@gmail.com', '$2y$10$UCD3G/JK22YPDO3YMALl9.SQ0U7uWf03nmvubDRmnln1OshoJsf.6', 'en', '+971565338013', 1, '2025-12-02 19:03:09', '2026-04-05 15:37:17'),
(2, 'miamen16@gmail.com', 'miamen16@gmail.com', '$2y$10$Vg9IKYppTmR4MfpRp4jUN.5Dm5muOwuBzyjQ0HbI6X1U0kd8t6ov6', 'en', '+201147265420', 1, '2025-12-02 19:12:24', '2026-01-11 03:19:37'),
(3, 'mz28332', 'zedanmahmoud9@gmail.com', '$2y$10$v0rkNyv4biT19Cd7gi/rTu/jZYsXYnoBgit78iQinWsQ4MJZ7nwgi', 'ar', '0565338013', 1, '2026-01-24 05:39:34', '2026-01-24 06:25:50'),
(4, 'tech_jeddah_branch', 'jeddah-branch@techstore.sa', '$2y$10$xXNBcGri5Ts95Oi.77COke3RU3Zz54RYr5/JQdtmUkoeSx9DoFkES', 'ar', '+966501234569', 1, '2025-12-14 02:38:38', '2026-01-11 14:27:55'),
(5, '28332111111', 'zedanmahmoud@gmail.com', '$2y$10$W7edfFSgNFV8EDr.xjDH9eR4141vWu9wfXkMpNY.wwSV.diUPPipG', 'ar', '0559740334', 1, '2026-01-24 07:01:35', '2026-01-24 07:05:20'),
(6, 'admin', 'zedanmahmoud110@gmail.com', '$2y$10$ONe/TZJV57gKO9BTX8JspuZwbizVNrk.5xaZ3jT9dfCVmvhkpqfwu', 'ar', '+971558740334', 1, '2025-12-15 18:55:15', '2026-03-23 17:09:50'),
(7, 'admin28332', 'zedanmahmoud99@gmail.com', '$2y$10$VAnwATjMe1FYDQTSwN9WD.1rYSpSltmQPLXu//ysTH3b/lToEjZla', 'ar', '+9715597403388', 1, '2025-12-15 18:56:20', '2026-04-05 13:57:06'),
(8, '28332999', 'z99@gmail.com', '$2y$10$UrbP0y4LX4aQDLdSPYKHx.D4e2N6I3.6P2rdmHphSEWgjMlqf05IG', 'ar', '055974063433', 1, '2026-01-24 07:09:07', '2026-03-22 15:10:54'),
(10, 'm7ood', 'z@gmail.com', '$2y$10$rgeeiKxvzP.u90zWr8IxM.Mv13ZZiswyW/88qDIzdc7MyqHMCgooC', 'en', '+971559740334', 1, '2026-02-27 09:49:52', '2026-03-22 11:43:12'),
(11, 'mlmmmm', 'mmmmmmmmahmommmm@gmail.com', '$2y$10$jY4DTIyvvH1N9UyxwMArX.VUvY1U/deFumdO4.4z6gVRQTxJ10ady', 'en', '+9715634', 1, '2026-02-28 15:12:35', '2026-03-22 15:10:45'),
(33, 'nvggffcxx', 'zedanmhccvhbahmoud99@gmail.com', '$2y$10$bmyDzARhrOvZEKfKXxNQIO6hUhzLAmA7.XCcOyUu8XY8SIdKotb/u', 'ar', '+971559740334', 1, '2026-03-23 14:31:27', '2026-03-23 14:33:12'),
(34, 'zedanmahmouduhgf', 'zedanmahdcvbbbmoud99@gmail.com', '$2y$10$MCZ2yTpHTQO/VlHyUPS7sOerFYmNNiC9crxZbdSdf273kLq9ia/bO', 'ar', '+971559740334', 1, '2026-03-23 15:25:04', '2026-03-23 15:26:12'),
(35, 'Mhamady', 'zedanmahmoud990@gmail.com', '$2y$10$CD0nNv9GWcX/hikgV/G4qemBtvA9rmy3UKHWHFtXY3H9GvvcLCnVi', 'ar', '+971559740534', 1, '2026-04-03 11:01:08', '2026-04-03 11:01:08'),
(36, 'Mohd5466', 'batotazedan2014@gmail.com', '$2y$10$I.QVmwPFAvQjtU/7B8FZ4uzYdhlWB4TuJJF.7B9ve22JuljDB9iMG', 'ar', '+971558703248', 1, '2026-04-04 13:45:28', '2026-04-04 13:46:43'),
(37, 'mahmoudzidan', 'mahmoudzidan28332@gmail.com', NULL, 'en', NULL, 1, '2026-04-10 06:30:26', '2026-04-10 06:30:26'),
(38, 'bot_tester', 'bot_tester@example.com', '$2y$10$U5UWs9R85y0UsGLaxaHNX.dYtbP4NcP/GcRuS3MpRknO6RmS1RvU6', 'en', '+971 50 123 4567', 1, '2026-04-10 17:54:38', '2026-04-10 17:55:23');

-- --------------------------------------------------------

--
-- Table structure for table `user_addresses`
--

CREATE TABLE `user_addresses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `label` varchar(100) DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `phone` varchar(45) DEFAULT NULL,
  `country_id` int(11) DEFAULT NULL,
  `city_id` int(11) DEFAULT NULL,
  `state` varchar(255) DEFAULT NULL,
  `postal_code` varchar(32) DEFAULT NULL,
  `street_address` text DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(11,7) DEFAULT NULL,
  `location` point NOT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_addresses`
--

INSERT INTO `user_addresses` (`id`, `user_id`, `vendor_id`, `label`, `full_name`, `phone`, `country_id`, `city_id`, `state`, `postal_code`, `street_address`, `latitude`, `longitude`, `location`, `is_default`, `created_at`) VALUES
(1, NULL, NULL, 'primary', 'mahmoud zidan', '0559740334', 8, 346, 'hgavrdm', '141', ' United Arab Emirates', 55.5828827, 25.3058951, 0x0000000001010000008a027d224f4e39407b32ffe89bca4b40, 0, '2025-12-01 16:13:44'),
(5, 2, NULL, 'Home', 'Mohammed Hassan', '+966507654321', 1, 1, NULL, NULL, 'Al Hamra District, Jeddah', 21.5433000, 39.1728000, 0x000000000101000000d95f764f1e964340b3ea73b5158b3540, 1, '2025-12-14 03:26:41');

-- --------------------------------------------------------

--
-- Table structure for table `user_auth_providers`
--

CREATE TABLE `user_auth_providers` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `provider` varchar(50) NOT NULL,
  `provider_user_id` varchar(255) NOT NULL,
  `provider_extra` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_auth_providers`
--

INSERT INTO `user_auth_providers` (`id`, `user_id`, `provider`, `provider_user_id`, `provider_extra`) VALUES
(2, 7, 'google', '111012939101491966742', '{\"email_verified\":true,\"name\":\"Mohd Mfmm\",\"picture\":\"https:\\/\\/lh3.googleusercontent.com\\/a\\/ACg8ocKXHhuYp2ykO2PqrHX-UuTYKwgsgGiNYPEz32yS5X_BP94n4uXUnw=s96-c\"}'),
(3, 37, 'google', '115955853410445034350', '{\"email_verified\":true,\"name\":\"Mahmoud Zidan\",\"picture\":\"https:\\/\\/lh3.googleusercontent.com\\/a\\/ACg8ocJ0PUKYrdGEITVmlv5wufyUUH9jrL1wX02DGrLIFXGuY-QHwg=s96-c\"}');

-- --------------------------------------------------------

--
-- Table structure for table `user_devices`
--

CREATE TABLE `user_devices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `anonymous_token` varchar(64) DEFAULT NULL,
  `fcm_token` text DEFAULT NULL,
  `device_type` varchar(20) NOT NULL DEFAULT 'web' COMMENT 'web, android, ios, other',
  `device_name` varchar(100) DEFAULT NULL COMMENT 'e.g. Chrome on Windows',
  `user_agent` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL COMMENT 'IPv4 or IPv6',
  `last_seen_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=active, 0=deregistered',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_devices`
--

INSERT INTO `user_devices` (`id`, `user_id`, `anonymous_token`, `fcm_token`, `device_type`, `device_name`, `user_agent`, `ip`, `last_seen_at`, `is_active`, `created_at`, `updated_at`) VALUES
(3, 7, NULL, 'fOGb8g1x6y23wLTtzLaotQ:APA91bFq_9lQj7i55wRG0Ja-jyQ8q9Z87kkyL4Zmdi3qetHFyfbHj4LtrSe_Fd1L8iFof1Q9WTOTI0tMaI2SlSrgIXgndphdRgCIZMoz4wleGAuPzVW0sNM', 'android', 'Chrome on Android', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '5.107.157.162', '2026-04-10 02:52:18', 1, '2026-04-05 14:04:09', '2026-04-10 02:52:18'),
(5, 7, 'e467ad7e43fcf29c1d61208760181445f474291450b7fb8dcc1ba84b39ff202c', NULL, 'web', 'Edge on Windows', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '5.107.157.162', '2026-04-10 18:41:52', 1, '2026-04-05 15:03:37', '2026-04-10 18:41:52'),
(6, 1, 'b1cdf26525baedefcaaa850860b2608a07566ff40d8c529a3dc4a56302a69990', NULL, 'web', 'Edge on Windows', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '5.107.176.14', '2026-04-05 15:04:17', 1, '2026-04-05 15:04:17', NULL),
(7, 1, 'a80c7d0ef11109c574f4e9374f03948e4391776c45446a2a1eeffb0b692f7562', 'cDyyPwAyXFYQjSty0uie_b:APA91bG4QyUkhwPu7Cmj0rHR7_NWgeKcc9QyZlBzpHRi2lDUss5VQZyj_QEG1CuoV_QQFr-XQ3zXVLbh0jWxBKwvEMzRODxVHRE9GhrU7fBeTFOBaLN5zg0', 'android', 'Chrome on Android', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '5.107.176.14', '2026-04-05 15:38:01', 1, '2026-04-05 15:37:54', '2026-04-05 15:38:01'),
(8, 7, NULL, 'dSqUsHunfXYKr91L2csKdR:APA91bFesCLPglP0G-0gFfdeb8QpfUGyOsMI1E9SK14jwJAZXnCb8Q1BERvzpU22eL1j7r-JGXeY1ebZtVn3ax0PP3EdL6NACmWU5TDP-g55xAL_xGf1QZ4', 'web', 'Chrome on Windows', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '5.107.157.162', '2026-04-11 03:21:37', 1, '2026-04-05 15:50:44', '2026-04-11 03:21:37'),
(9, 37, '07160f758fa66bd3f609dd82973c807f0b1addc363241fc7e10c2c0770b562b6', NULL, 'web', 'Chrome on Windows', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '5.107.157.162', '2026-04-10 06:30:26', 1, '2026-04-10 06:30:26', NULL),
(10, 38, 'fdfd2f6dcc1ab65c0c05c419c9f219e7a6a5fab7ad3e801b2a49778804235b45', NULL, 'web', 'Chrome on Windows', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '5.107.157.162', '2026-04-10 17:55:35', 1, '2026-04-10 17:55:35', NULL),
(11, 7, '884a12cec293e456795eb023ae66ca2d8b041ee0a1f54efbadd94a09b990c996', 'd7Xw44IxcgnspgS8NYxHsi:APA91bF1waCvRnQvoCkalvNNZC-XlB7bPWl89hjrLPOiPwTTkdVFwZihCNtKsfOQsThPu2BnDqdJc6he6aWJdKCUa7pA5zxTbmP3N6E7UhVQlUxqFeW0_e4', 'android', 'Chrome on Android', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Mobile Safari/537.36', '5.107.157.162', '2026-04-10 18:48:45', 1, '2026-04-10 18:46:10', '2026-04-10 18:48:45'),
(12, 1, '1677a17ec4351cd4ca843f83a6bf9b286e6cfe6779f170856aff6cb736f050a6', 'dDZ9rjRQVqOIqnTaJbWKnH:APA91bFM0C4fjOd1DOd8ICqJ3Yl-JZnZS76U3HHFVWe0aTk7q9_H_elElwUaQqdA7sJ3JxQypolQ_otC--i9v66B57tIy3QjnPdIZR2Iejuo1P7fTSFTJRE', 'web', 'Chrome on Windows', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '5.107.157.162', '2026-04-11 06:51:52', 1, '2026-04-11 02:27:36', '2026-04-11 06:51:52');

-- --------------------------------------------------------

--
-- Table structure for table `user_phone_verifications`
--

CREATE TABLE `user_phone_verifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL COMMENT 'SHA-256 hex of the raw token sent in the SMS link',
  `device_hash` char(64) NOT NULL COMMENT 'SHA-256 hex of the device_token cookie set during registration',
  `session_id` varchar(128) DEFAULT NULL COMMENT 'PHP session_id() at registration time; must match at verification time',
  `user_agent` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_phone_verifications`
--

INSERT INTO `user_phone_verifications` (`id`, `user_id`, `token_hash`, `device_hash`, `session_id`, `user_agent`, `ip`, `expires_at`, `used_at`, `created_at`) VALUES
(1, 30, '943c61fedc1e3f6d01e901d98c0d45fdb747b991d0589a3746e6f6282527c00a', 'ab77049535478066062906f6c375f667acc230ad15bcc8b4007cc6d64381d876', 'sggs8pi0n05j6rrk6bh59llk2v', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36 Edg/146.0.0.0', '5.107.159.234', '2026-03-24 11:39:19', '2026-03-23 11:39:38', '2026-03-23 11:39:19'),
(2, 31, '0806e23a1dfccc4170ab452fbf125c6b10fc523018154a188ff99aeac2822ccc', 'cc5e30e6e7e20a39d8f92576232b4a52641697d537b7929d793bb5b50b298df5', '97a4gb2734c6r93jnnafoj3jt5', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '5.107.159.234', '2026-03-24 11:42:14', '2026-03-23 11:43:03', '2026-03-23 11:42:14'),
(3, 32, 'd0ca65e61565abca3c73d25ae99848991512999e1b23573c649d0aea5e8e1577', 'e73da4c46dfc8ec0683fff5c07932881c3db62fd8f1441afeca369c57c7f2dec', 'h56fh3smh53gfeiju0cbkmr39a', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '5.107.159.234', '2026-03-24 13:22:00', NULL, '2026-03-23 13:22:00'),
(4, 32, 'ffeade941491a38c3ee7e16e2a4ccdd1206c034f67bbb1702c46ed8c3e28b43e', '016e112e0a3a9ccdd1b7a2f1a360dc5ec8c2992a5574dadb85836b929c9c2f53', 'h56fh3smh53gfeiju0cbkmr39a', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '5.107.159.234', '2026-03-24 13:27:00', '2026-03-23 13:27:07', '2026-03-23 13:27:00'),
(5, 33, '65b6fa500bb8bae3fe304e47ee1f48a493c14731d4b86de818d4036a0f5a5e20', '525ade3aa83aab924088ffb7f79ffe80f6b85fef243a9d7a754c4cbe81ff651b', 'k5lupokjuqoej7h06pl5pj11o8', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '5.107.159.234', '2026-03-24 14:31:27', NULL, '2026-03-23 14:31:27'),
(6, 33, '6b36fcc9cd8b142c13124a02c7dd8bdb133350ec7c05a56d480333f4ce6441b7', '6a06cf29622711d45866a95185997a30ac28e9a886992d58ac306e72f5e7d996', 'k5lupokjuqoej7h06pl5pj11o8', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '5.107.159.234', '2026-03-24 14:32:43', '2026-03-23 14:33:12', '2026-03-23 14:32:43'),
(7, 34, '44edb6e5e5d0cb6bc0d39c3661c10e4bb061a6a8bf9eb0ee049d6e3c7f6ca3d7', '27d4123fb1c53d661476d6ff349491148929392eaa79d62d7c89a2522a1a4289', 'pu72vp23esjojhudkmm28khcg6', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '5.107.159.234', '2026-03-24 15:25:04', NULL, '2026-03-23 15:25:04'),
(8, 34, '0f4ea13a78418b17fb6bb091ee6cdcd54e6208d95fadb4e7581087094e3cd384', '27313b369d6dae9b3f5b393dabdec0e16bed11498f2977fd4721497d8e7fe823', 'pu72vp23esjojhudkmm28khcg6', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '5.107.159.234', '2026-03-24 15:26:07', '2026-03-23 15:26:12', '2026-03-23 15:26:07'),
(9, 36, '210d61d3ca171cdefae0f9ab9c33c3e782781c7dbe7b72b23c61ec32b0eb2878', '9710e0bd73e6b1d1d1888f23e61c923479b7bfca682a56e0f49accac23affc21', 'sa99pqkhmvb9mes4brr809emkt', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '87.201.107.100', '2026-04-05 13:45:28', NULL, '2026-04-04 13:45:28'),
(10, 36, '5c0876b0acd5dc513dfe2390c05ce0033cc6efa26a4f7c123e0aaa8cbd6fbafa', 'ccc1bd1547f585f4d09f63ca4bc0c1bbaa3c191bd15954da279792507ded2bc1', 'sa99pqkhmvb9mes4brr809emkt', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '87.201.107.100', '2026-04-05 13:46:31', '2026-04-04 13:46:43', '2026-04-04 13:46:31'),
(11, 38, '79618b9bf45c526dfd1f7aa13bf99ba6f3b4d096596037fe69c7955c8928bb5e', '6ecf293e8b3c57039150150ce3f6a3d519090427dc25e6df08c0b0ea85d208d5', 'df9fai2fq0fkkadgbopb266lrr', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36', '5.107.157.162', '2026-04-11 17:54:38', NULL, '2026-04-10 17:54:38');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` char(64) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `revoked` tinyint(1) DEFAULT 0,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_term_access`
--

CREATE TABLE `user_term_access` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `term_taxonomy_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_wallets`
--

CREATE TABLE `user_wallets` (
  `id` bigint(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `wallet_number` varchar(50) NOT NULL,
  `balance` decimal(15,2) DEFAULT 0.00,
  `currency_code` varchar(8) DEFAULT 'SAR',
  `pending_balance` decimal(15,2) DEFAULT 0.00,
  `total_deposited` decimal(15,2) DEFAULT 0.00,
  `total_withdrawn` decimal(15,2) DEFAULT 0.00,
  `total_spent` decimal(15,2) DEFAULT 0.00,
  `total_earned` decimal(15,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `is_verified` tinyint(1) DEFAULT 0,
  `daily_limit` decimal(15,2) DEFAULT 10000.00,
  `monthly_limit` decimal(15,2) DEFAULT 50000.00,
  `min_withdrawal_amount` decimal(15,2) DEFAULT 50.00,
  `last_transaction_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_wallets`
--

INSERT INTO `user_wallets` (`id`, `user_id`, `wallet_number`, `balance`, `currency_code`, `pending_balance`, `total_deposited`, `total_withdrawn`, `total_spent`, `total_earned`, `is_active`, `is_verified`, `daily_limit`, `monthly_limit`, `min_withdrawal_amount`, `last_transaction_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'UW-2024-000001', 1500.00, 'SAR', 0.00, 2000.00, 0.00, 0.00, 0.00, 1, 1, 10000.00, 50000.00, 50.00, NULL, '2025-12-14 02:44:50', '2025-12-14 02:44:50'),
(2, 2, 'UW-2024-000002', 500.00, 'SAR', 0.00, 500.00, 0.00, 0.00, 0.00, 1, 1, 10000.00, 50000.00, 50.00, NULL, '2025-12-14 02:44:50', '2025-12-14 02:44:50');

-- --------------------------------------------------------

--
-- Table structure for table `verification_tokens`
--

CREATE TABLE `verification_tokens` (
  `id` bigint(20) NOT NULL,
  `jti` varchar(48) NOT NULL,
  `user_id` int(11) NOT NULL,
  `channel` varchar(20) NOT NULL,
  `token_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `expires_at_local` varchar(64) DEFAULT NULL,
  `used` tinyint(1) DEFAULT 0,
  `attempts` tinyint(3) DEFAULT 0,
  `ip` varbinary(16) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `phone` varchar(45) DEFAULT NULL,
  `issued_at` datetime DEFAULT NULL,
  `origin` varchar(128) DEFAULT NULL,
  `issuer_user_agent` mediumtext DEFAULT NULL,
  `issuer_geo` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wallet_balance_log`
--

CREATE TABLE `wallet_balance_log` (
  `id` bigint(20) NOT NULL,
  `wallet_type` enum('user','vendor') NOT NULL,
  `wallet_id` bigint(20) NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `balance` decimal(15,2) NOT NULL,
  `pending_balance` decimal(15,2) DEFAULT 0.00,
  `on_hold_balance` decimal(15,2) DEFAULT 0.00,
  `logged_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wallet_entity_balances`
--

CREATE TABLE `wallet_entity_balances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `wallet_id` bigint(20) NOT NULL,
  `tenant_id` int(10) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `pending_balance` decimal(15,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wallet_transactions`
--

CREATE TABLE `wallet_transactions` (
  `id` bigint(20) NOT NULL,
  `transaction_number` varchar(100) NOT NULL,
  `wallet_type` enum('user','vendor') NOT NULL,
  `wallet_id` bigint(20) NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `vendor_id` bigint(20) DEFAULT NULL,
  `transaction_type` enum('deposit','withdrawal','purchase','refund','commission','earning','transfer','bonus','penalty','reversal') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency_code` varchar(8) DEFAULT 'SAR',
  `balance_before` decimal(15,2) NOT NULL,
  `balance_after` decimal(15,2) NOT NULL,
  `status` enum('pending','completed','failed','cancelled','reversed') DEFAULT 'pending',
  `payment_method` enum('bank_transfer','credit_card','cash','online','wallet','other') DEFAULT NULL,
  `reference_type` enum('order','withdrawal','deposit','refund','transfer','admin_adjustment','other') DEFAULT NULL,
  `reference_id` bigint(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wallet_transfers`
--

CREATE TABLE `wallet_transfers` (
  `id` bigint(20) NOT NULL,
  `transfer_number` varchar(100) NOT NULL,
  `from_wallet_type` enum('user','vendor') NOT NULL,
  `from_wallet_id` bigint(20) NOT NULL,
  `to_wallet_type` enum('user','vendor') NOT NULL,
  `to_wallet_id` bigint(20) NOT NULL,
  `tenant_id` int(10) UNSIGNED DEFAULT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency_code` varchar(8) DEFAULT 'SAR',
  `transfer_fee` decimal(15,2) DEFAULT 0.00,
  `status` enum('pending','completed','failed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `from_transaction_id` bigint(20) DEFAULT NULL,
  `to_transaction_id` bigint(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `wishlists`
--

CREATE TABLE `wishlists` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(11) NOT NULL,
  `wishlist_name` varchar(255) DEFAULT 'My Wishlist',
  `is_public` tinyint(1) DEFAULT 0,
  `is_default` tinyint(1) DEFAULT 1,
  `total_items` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wishlists`
--

INSERT INTO `wishlists` (`id`, `user_id`, `wishlist_name`, `is_public`, `is_default`, `total_items`, `created_at`, `updated_at`, `tenant_id`, `entity_id`) VALUES
(1, 1, 'My Wishlist', 0, 1, 5, '2025-12-14 03:12:34', '2026-04-05 06:53:34', 0, 0),
(2, 2, 'Birthday Wishlist', 1, 1, 1, '2025-12-14 03:12:34', '2025-12-14 03:12:34', 0, 0),
(3, 7, 'قائمة مفضلتي', 0, 1, 1, '2026-02-28 03:07:49', '2026-04-10 16:13:18', 1, 1),
(4, 11, 'قائمة مفضلتي', 0, 1, 2, '2026-02-28 15:12:36', '2026-03-01 01:42:55', 1, 1),
(5, 13, 'قائمة مفضلتي', 0, 1, 0, '2026-03-22 15:41:04', '2026-03-22 15:41:04', 1, 1),
(6, 14, 'قائمة مفضلتي', 0, 1, 0, '2026-03-22 16:13:34', '2026-03-22 16:13:34', 1, 1),
(7, 30, 'قائمة مفضلتي', 0, 1, 0, '2026-03-23 11:40:35', '2026-03-23 11:40:35', 1, 1),
(8, 10, 'My Wishlist', 0, 1, 0, '2026-03-26 15:41:45', '2026-03-26 15:41:45', 1, 1),
(9, 36, 'قائمة مفضلتي', 0, 1, 1, '2026-04-04 13:46:46', '2026-04-04 13:49:09', 1, 1),
(10, 38, 'My Wishlist', 0, 1, 0, '2026-04-10 17:55:46', '2026-04-10 17:55:46', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `wishlist_items`
--

CREATE TABLE `wishlist_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `wishlist_id` bigint(20) UNSIGNED NOT NULL,
  `entity_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `product_variant_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `priority` tinyint(4) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `tenant_id` bigint(20) UNSIGNED NOT NULL,
  `removed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `wishlist_items`
--

INSERT INTO `wishlist_items` (`id`, `wishlist_id`, `entity_id`, `product_id`, `product_variant_id`, `priority`, `notes`, `tenant_id`, `removed_at`, `created_at`, `updated_at`) VALUES
(1, 3, 1, 42, 0, 0, NULL, 1, '2026-03-25 10:29:25', '2026-02-28 08:24:01', '2026-03-25 10:29:25'),
(2, 3, 1, 43, 0, 0, NULL, 1, '2026-03-22 17:21:52', '2026-02-28 08:26:14', '2026-03-22 17:21:52'),
(3, 3, 1, 34, 0, 0, NULL, 1, '2026-03-22 17:21:52', '2026-02-28 08:26:30', '2026-03-22 17:21:52'),
(4, 1, 1, 41, 0, 0, NULL, 1, NULL, '2026-02-28 09:16:18', '2026-02-28 09:16:18'),
(5, 4, 1, 42, 0, 0, NULL, 1, NULL, '2026-03-01 01:42:40', '2026-03-01 01:42:40'),
(6, 4, 1, 3, 0, 0, NULL, 1, NULL, '2026-03-01 01:42:55', '2026-03-01 01:42:55'),
(7, 3, 1, 3, 0, 0, NULL, 1, '2026-03-22 17:21:52', '2026-03-01 08:19:07', '2026-03-22 17:21:52'),
(8, 3, 1, 2, 0, 0, NULL, 1, '2026-03-22 17:21:52', '2026-03-04 10:28:55', '2026-03-22 17:21:52'),
(9, 3, 1, 41, 0, 0, NULL, 1, '2026-03-22 17:21:52', '2026-03-22 17:21:41', '2026-03-22 17:21:52'),
(10, 3, 1, 50, 0, 0, NULL, 1, '2026-03-25 10:29:44', '2026-03-25 10:29:32', '2026-03-25 10:29:44'),
(11, 9, 1, 52, 0, 0, NULL, 1, NULL, '2026-04-04 13:49:01', '2026-04-04 13:49:01'),
(12, 1, 1, 50, 0, 0, NULL, 1, NULL, '2026-04-05 05:47:01', '2026-04-05 05:47:01'),
(13, 1, 1, 43, 0, 0, NULL, 1, NULL, '2026-04-05 05:47:24', '2026-04-05 05:47:24'),
(14, 1, 1, 48, 0, 0, NULL, 1, NULL, '2026-04-05 05:48:23', '2026-04-05 05:48:23'),
(15, 1, 1, 47, 0, 0, NULL, 1, NULL, '2026-04-05 05:49:58', '2026-04-05 05:49:58'),
(16, 3, 1, 1, 0, 0, NULL, 1, NULL, '2026-04-10 16:13:18', '2026-04-10 16:13:18');

-- --------------------------------------------------------

--
-- Table structure for table `withdrawal_requests`
--

CREATE TABLE `withdrawal_requests` (
  `id` bigint(20) NOT NULL,
  `request_number` varchar(100) NOT NULL,
  `wallet_type` enum('user','vendor') NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `vendor_id` bigint(20) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency_code` varchar(8) DEFAULT 'SAR',
  `withdrawal_method` enum('bank_transfer','check','cash','paypal','other') NOT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `bank_account_name` varchar(255) DEFAULT NULL,
  `bank_account_number` varchar(100) DEFAULT NULL,
  `iban` varchar(50) DEFAULT NULL,
  `swift_code` varchar(20) DEFAULT NULL,
  `status` enum('pending','approved','processing','completed','rejected','cancelled') DEFAULT 'pending',
  `requested_at` datetime DEFAULT current_timestamp(),
  `approved_at` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `transaction_id` bigint(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_unique_primary_address` (`primary_marker`),
  ADD KEY `idx_addresses_owner` (`owner_type`,`owner_id`),
  ADD KEY `fk_addresses_city` (`city_id`),
  ADD KEY `fk_addresses_country` (`country_id`),
  ADD KEY `idx_owner_lookup` (`owner_type`,`owner_id`,`is_primary`);

--
-- Indexes for table `ads`
--
ALTER TABLE `ads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `campaign_id` (`campaign_id`);

--
-- Indexes for table `ad_campaigns`
--
ALTER TABLE `ad_campaigns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `entity_id` (`entity_id`),
  ADD KEY `currency_id` (`currency_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `ad_payments`
--
ALTER TABLE `ad_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `campaign_id` (`campaign_id`),
  ADD KEY `currency_id` (`currency_id`);

--
-- Indexes for table `ad_placements`
--
ALTER TABLE `ad_placements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_tenant_placement_key` (`tenant_id`,`placement_key`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_placement_key` (`placement_key`);

--
-- Indexes for table `ad_placement_items`
--
ALTER TABLE `ad_placement_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_ad_placement` (`placement_id`,`ad_id`),
  ADD KEY `ad_id` (`ad_id`);

--
-- Indexes for table `ad_stats`
--
ALTER TABLE `ad_stats`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_ad_date` (`ad_id`,`date`),
  ADD UNIQUE KEY `uq_ad_stats_ad_date` (`ad_id`,`date`),
  ADD KEY `idx_ad_event` (`ad_id`,`event_type`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_session` (`session_id`);

--
-- Indexes for table `ad_translations`
--
ALTER TABLE `ad_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_ad_lang` (`ad_id`,`language_code`),
  ADD KEY `language_code` (`language_code`);

--
-- Indexes for table `ai_documents`
--
ALTER TABLE `ai_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `knowledge_base_id` (`knowledge_base_id`);

--
-- Indexes for table `ai_document_chunks`
--
ALTER TABLE `ai_document_chunks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_document_chunk` (`document_id`,`chunk_index`);
ALTER TABLE `ai_document_chunks` ADD FULLTEXT KEY `ft_content` (`content`);

--
-- Indexes for table `ai_feedback`
--
ALTER TABLE `ai_feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `message_id` (`message_id`);

--
-- Indexes for table `ai_files`
--
ALTER TABLE `ai_files`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ai_knowledge_bases`
--
ALTER TABLE `ai_knowledge_bases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`(100)),
  ADD KEY `idx_public` (`is_public`);

--
-- Indexes for table `ai_messages`
--
ALTER TABLE `ai_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `thread_id` (`thread_id`);

--
-- Indexes for table `ai_message_files`
--
ALTER TABLE `ai_message_files`
  ADD PRIMARY KEY (`message_id`,`file_id`),
  ADD KEY `file_id` (`file_id`);

--
-- Indexes for table `ai_threads`
--
ALTER TABLE `ai_threads`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ai_thread_memory`
--
ALTER TABLE `ai_thread_memory`
  ADD PRIMARY KEY (`thread_id`);

--
-- Indexes for table `ai_usage_logs`
--
ALTER TABLE `ai_usage_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ai_vision_analyses`
--
ALTER TABLE `ai_vision_analyses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `file_id` (`file_id`),
  ADD KEY `message_id` (`message_id`);

--
-- Indexes for table `api_tokens`
--
ALTER TABLE `api_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_api_tokens_token` (`token`),
  ADD KEY `idx_api_tokens_user` (`user_id`);

--
-- Indexes for table `attribute_types`
--
ALTER TABLE `attribute_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `auctions`
--
ALTER TABLE `auctions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_tenant_entity` (`tenant_id`,`entity_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `fk_auctions_currency` (`currency_id`);

--
-- Indexes for table `auction_activity_log`
--
ALTER TABLE `auction_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_auction_id` (`auction_id`),
  ADD KEY `idx_activity_type` (`activity_type`);

--
-- Indexes for table `auction_bids`
--
ALTER TABLE `auction_bids`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_auction_user` (`auction_id`,`user_id`),
  ADD KEY `idx_auction_id` (`auction_id`);

--
-- Indexes for table `auction_translations`
--
ALTER TABLE `auction_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_auction_lang` (`auction_id`,`language_code`),
  ADD KEY `idx_auction_id` (`auction_id`);

--
-- Indexes for table `auction_watchers`
--
ALTER TABLE `auction_watchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_auction_user` (`auction_id`,`user_id`),
  ADD KEY `idx_auction_id` (`auction_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_logs_tenant_id` (`tenant_id`),
  ADD KEY `idx_al_entity` (`tenant_id`,`entity_type`,`entity_id`),
  ADD KEY `idx_al_action_ts` (`tenant_id`,`action`,`created_at`),
  ADD KEY `idx_al_request` (`request_id`),
  ADD KEY `idx_al_session` (`session_id`);

--
-- Indexes for table `auto_bid_settings`
--
ALTER TABLE `auto_bid_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_auction_user` (`auction_id`,`user_id`),
  ADD KEY `idx_auction_id` (`auction_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `bad_words`
--
ALTER TABLE `bad_words`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bad_words_word` (`word`),
  ADD KEY `idx_bad_words_severity` (`severity`),
  ADD KEY `idx_bad_words_is_active` (`is_active`);

--
-- Indexes for table `bad_word_translations`
--
ALTER TABLE `bad_word_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_bad_word_lang` (`bad_word_id`,`language_code`),
  ADD KEY `idx_translations_word` (`word`),
  ADD KEY `idx_translations_language` (`language_code`);

--
-- Indexes for table `banners`
--
ALTER TABLE `banners`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_position` (`position`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_dates` (`start_date`,`end_date`),
  ADD KEY `idx_banners_theme_id` (`theme_id`),
  ADD KEY `idx_banners_tenant` (`tenant_id`),
  ADD KEY `idx_banners_entity` (`entity_id`);

--
-- Indexes for table `banner_translations`
--
ALTER TABLE `banner_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_banner_language` (`banner_id`,`language_code`),
  ADD KEY `fk_banner_trans_language` (`language_code`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `brands`
--
ALTER TABLE `brands`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_brands_tenant` (`tenant_id`),
  ADD KEY `idx_brand_entity` (`entity_id`);

--
-- Indexes for table `brand_translations`
--
ALTER TABLE `brand_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_brand_language` (`brand_id`,`language_code`),
  ADD KEY `idx_language` (`language_code`);

--
-- Indexes for table `button_styles`
--
ALTER TABLE `button_styles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_theme_button_slug` (`theme_id`,`slug`),
  ADD UNIQUE KEY `uq_btn_tenant_theme_slug` (`tenant_id`,`theme_id`,`slug`),
  ADD KEY `idx_theme_id` (`theme_id`),
  ADD KEY `idx_button_type` (`button_type`),
  ADD KEY `idx_button_styles_tenant` (`tenant_id`);

--
-- Indexes for table `card_styles`
--
ALTER TABLE `card_styles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_theme_card_slug` (`theme_id`,`slug`),
  ADD UNIQUE KEY `uq_card_tenant_theme_slug` (`tenant_id`,`theme_id`,`slug`),
  ADD KEY `idx_theme_id` (`theme_id`),
  ADD KEY `idx_card_type` (`card_type`),
  ADD KEY `idx_card_styles_tenant` (`tenant_id`);

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_session_id` (`session_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_last_activity_at` (`last_activity_at`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `fk_cart_entity_fk` (`entity_id`),
  ADD KEY `fk_carts_discount` (`discount_id`);

--
-- Indexes for table `cart_events`
--
ALTER TABLE `cart_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cart_id` (`cart_id`),
  ADD KEY `event_type` (`event_type`),
  ADD KEY `actor_type` (`actor_type`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cart_id` (`cart_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_vendor_id` (`entity_id`),
  ADD KEY `idx_product_variant_id` (`product_variant_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_tenant_slug` (`tenant_id`,`slug`),
  ADD KEY `idx_parent_id` (`parent_id`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_sort_order` (`sort_order`),
  ADD KEY `idx_categories_tenant` (`tenant_id`);

--
-- Indexes for table `category_attributes`
--
ALTER TABLE `category_attributes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `category_attribute_unique` (`category_id`,`attribute_id`),
  ADD UNIQUE KEY `uq_category_attribute` (`category_id`,`attribute_id`),
  ADD KEY `attribute_id` (`attribute_id`);

--
-- Indexes for table `category_attribute_translations`
--
ALTER TABLE `category_attribute_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_cat_attr_lang` (`category_attribute_id`,`language_code`),
  ADD KEY `language_code` (`language_code`);

--
-- Indexes for table `category_translations`
--
ALTER TABLE `category_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_category_language` (`category_id`,`language_code`),
  ADD UNIQUE KEY `uq_category_lang` (`category_id`,`language_code`),
  ADD KEY `idx_language` (`language_code`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `cities`
--
ALTER TABLE `cities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cities_country` (`country_id`),
  ADD SPATIAL KEY `idx_cities_location` (`location`),
  ADD KEY `idx_cities_lat_lon` (`latitude`,`longitude`);

--
-- Indexes for table `city_translations`
--
ALTER TABLE `city_translations`
  ADD PRIMARY KEY (`city_id`,`language_code`);

--
-- Indexes for table `color_settings`
--
ALTER TABLE `color_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_theme_key` (`theme_id`,`setting_key`),
  ADD UNIQUE KEY `uq_color_tenant_theme_key` (`tenant_id`,`theme_id`,`setting_key`),
  ADD KEY `idx_theme_id` (`theme_id`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `commission_credit_notes`
--
ALTER TABLE `commission_credit_notes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `credit_note_number` (`credit_note_number`),
  ADD UNIQUE KEY `uniq_transaction_credit` (`related_transaction_id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `commission_invoices`
--
ALTER TABLE `commission_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD UNIQUE KEY `uniq_period` (`tenant_id`,`entity_id`,`period_start`,`period_end`),
  ADD KEY `entity_id` (`entity_id`);

--
-- Indexes for table `commission_invoice_items`
--
ALTER TABLE `commission_invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_transaction_invoice` (`transaction_id`),
  ADD KEY `invoice_id` (`invoice_id`);

--
-- Indexes for table `commission_payments`
--
ALTER TABLE `commission_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `entity_id` (`entity_id`),
  ADD KEY `commission_invoice_id` (`commission_invoice_id`);

--
-- Indexes for table `commission_transactions`
--
ALTER TABLE `commission_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entity_status` (`entity_id`,`status`),
  ADD KEY `idx_order_date` (`order_date`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contact_tenant` (`tenant_id`),
  ADD KEY `idx_contact_user` (`user_id`),
  ADD KEY `idx_contact_status` (`status`),
  ADD KEY `idx_contact_created` (`created_at`);

--
-- Indexes for table `core_events`
--
ALTER TABLE `core_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_event` (`event_type`),
  ADD KEY `idx_session` (`session_id`);

--
-- Indexes for table `countries`
--
ALTER TABLE `countries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_countries_iso2` (`iso2`);

--
-- Indexes for table `country_taxes`
--
ALTER TABLE `country_taxes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_country_id` (`country_id`),
  ADD KEY `idx_tax_class_id` (`tax_class_id`);

--
-- Indexes for table `country_translations`
--
ALTER TABLE `country_translations`
  ADD PRIMARY KEY (`country_id`,`language_code`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `fk_coupon_promotion` (`promotion_id`),
  ADD KEY `fk_coupon_country` (`country_id`),
  ADD KEY `fk_coupon_created_by` (`created_by`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_dates` (`start_date`,`end_date`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `coupon_usage`
--
ALTER TABLE `coupon_usage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_coupon_id` (`coupon_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_used_at` (`used_at`),
  ADD KEY `idx_currency_code` (`currency_code`);

--
-- Indexes for table `currencies`
--
ALTER TABLE `currencies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `customer_loyalty_points`
--
ALTER TABLE `customer_loyalty_points`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_points` (`user_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_available_points` (`available_points`);

--
-- Indexes for table `delivery_orders`
--
ALTER TABLE `delivery_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order` (`order_id`),
  ADD KEY `idx_provider` (`provider_id`),
  ADD KEY `fk_do_pickup` (`pickup_address_id`),
  ADD KEY `fk_do_dropoff` (`dropoff_address_id`),
  ADD KEY `idx_status` (`delivery_status`),
  ADD KEY `idx_provider_id` (`provider_id`),
  ADD KEY `idx_tenant_order` (`tenant_id`,`order_id`),
  ADD KEY `idx_zone` (`delivery_zone_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `delivery_providers`
--
ALTER TABLE `delivery_providers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_tenant_user` (`tenant_user_id`),
  ADD KEY `idx_entity` (`entity_id`),
  ADD KEY `idx_is_online` (`is_online`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_provider_type` (`provider_type`);

--
-- Indexes for table `delivery_tracking`
--
ALTER TABLE `delivery_tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_delivery` (`delivery_order_id`),
  ADD KEY `idx_order_provider` (`delivery_order_id`,`provider_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `delivery_zones`
--
ALTER TABLE `delivery_zones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_provider` (`provider_id`),
  ADD KEY `fk_dz_city` (`city_id`),
  ADD KEY `idx_tenant_provider` (`tenant_id`,`provider_id`),
  ADD KEY `idx_zone_type` (`zone_type`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `deposit_requests`
--
ALTER TABLE `deposit_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_number` (`request_number`),
  ADD KEY `fk_deposit_verified_by` (`verified_by`),
  ADD KEY `fk_deposit_approved_by` (`approved_by`),
  ADD KEY `fk_deposit_transaction` (`transaction_id`),
  ADD KEY `idx_request_number` (`request_number`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_requested_at` (`requested_at`);

--
-- Indexes for table `design_settings`
--
ALTER TABLE `design_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_theme_setting_key` (`theme_id`,`setting_key`),
  ADD KEY `idx_theme_id` (`theme_id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_design_settings_tenant` (`tenant_id`);

--
-- Indexes for table `discounts`
--
ALTER TABLE `discounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entity` (`entity_id`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_dates` (`starts_at`,`ends_at`);

--
-- Indexes for table `discount_actions`
--
ALTER TABLE `discount_actions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_action_discount` (`discount_id`);

--
-- Indexes for table `discount_conditions`
--
ALTER TABLE `discount_conditions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_condition_discount` (`discount_id`);

--
-- Indexes for table `discount_exclusions`
--
ALTER TABLE `discount_exclusions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_exclusion` (`discount_id`,`excluded_discount_id`);

--
-- Indexes for table `discount_redemptions`
--
ALTER TABLE `discount_redemptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_discount_usage` (`discount_id`,`user_id`,`order_id`),
  ADD KEY `idx_discount_user` (`discount_id`,`user_id`);

--
-- Indexes for table `discount_scopes`
--
ALTER TABLE `discount_scopes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_scope_lookup` (`scope_type`,`scope_id`),
  ADD KEY `fk_scope_discount` (`discount_id`);

--
-- Indexes for table `discount_translations`
--
ALTER TABLE `discount_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_discount_lang` (`discount_id`,`language_code`);

--
-- Indexes for table `driver_documents`
--
ALTER TABLE `driver_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `driver_id` (`driver_id`);

--
-- Indexes for table `driver_locations`
--
ALTER TABLE `driver_locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_provider` (`provider_id`),
  ADD SPATIAL KEY `idx_location` (`location`),
  ADD SPATIAL KEY `idx_location_spatial` (`location`);

--
-- Indexes for table `driver_tokens`
--
ALTER TABLE `driver_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `driver_id` (`driver_id`);

--
-- Indexes for table `entities`
--
ALTER TABLE `entities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_entities_parent` (`parent_id`),
  ADD KEY `fk_entities_timezone` (`timezone_id`);

--
-- Indexes for table `entities_attributes`
--
ALTER TABLE `entities_attributes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_slug` (`slug`);

--
-- Indexes for table `entities_attribute_translations`
--
ALTER TABLE `entities_attribute_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_vattr_language` (`attribute_id`,`language_code`),
  ADD KEY `fk_vattr_trans_language` (`language_code`);

--
-- Indexes for table `entities_attribute_values`
--
ALTER TABLE `entities_attribute_values`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_attribute_id` (`attribute_id`),
  ADD KEY `idx_entity_id` (`entity_id`);

--
-- Indexes for table `entities_working_hours`
--
ALTER TABLE `entities_working_hours`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_entity_day` (`entity_id`,`day_of_week`);

--
-- Indexes for table `entity_bank_accounts`
--
ALTER TABLE `entity_bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entity_id` (`entity_id`);

--
-- Indexes for table `entity_categories`
--
ALTER TABLE `entity_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_entity_category` (`entity_id`,`category_id`),
  ADD KEY `idx_entity_categories_entity` (`entity_id`),
  ADD KEY `idx_entity_categories_category` (`category_id`),
  ADD KEY `idx_entity_categories_tenant` (`tenant_id`);

--
-- Indexes for table `entity_delivery_relations`
--
ALTER TABLE `entity_delivery_relations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_delivery_relation` (`delivery_entity_id`,`target_entity_id`),
  ADD KEY `idx_delivery_entity` (`delivery_entity_id`),
  ADD KEY `idx_target_entity` (`target_entity_id`),
  ADD KEY `idx_tenant` (`tenant_id`);

--
-- Indexes for table `entity_financial_balances`
--
ALTER TABLE `entity_financial_balances`
  ADD PRIMARY KEY (`entity_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `entity_logs`
--
ALTER TABLE `entity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entity_type` (`entity_type`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_tenant_id` (`tenant_id`);

--
-- Indexes for table `entity_payment_methods`
--
ALTER TABLE `entity_payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entity_id` (`entity_id`),
  ADD KEY `fk_entity_payment_methods_method` (`payment_method_id`);

--
-- Indexes for table `entity_pickup_points`
--
ALTER TABLE `entity_pickup_points`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_entity` (`entity_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `entity_policies`
--
ALTER TABLE `entity_policies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_entity_policy_lang` (`entity_id`,`type`,`language_code`),
  ADD KEY `idx_entity_policies_tenant` (`tenant_id`),
  ADD KEY `idx_entity_policies_entity` (`entity_id`),
  ADD KEY `idx_entity_policies_type` (`type`);

--
-- Indexes for table `entity_products`
--
ALTER TABLE `entity_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_entity_product` (`entity_id`,`product_id`),
  ADD KEY `idx_tenant_entity_product` (`tenant_id`,`entity_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `entity_product_variants`
--
ALTER TABLE `entity_product_variants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_entity_variant` (`entity_id`,`variant_id`),
  ADD KEY `idx_tenant_entity` (`tenant_id`,`entity_id`),
  ADD KEY `idx_variant` (`variant_id`),
  ADD KEY `idx_product` (`product_id`);

--
-- Indexes for table `entity_ratings`
--
ALTER TABLE `entity_ratings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entity` (`entity_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_entity_active_id` (`entity_id`,`is_active`,`id` DESC);

--
-- Indexes for table `entity_settings`
--
ALTER TABLE `entity_settings`
  ADD PRIMARY KEY (`entity_id`);

--
-- Indexes for table `entity_translations`
--
ALTER TABLE `entity_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_entity_lang` (`entity_id`,`language_code`),
  ADD KEY `fk_entity_translations_language` (`language_code`);

--
-- Indexes for table `entity_types`
--
ALTER TABLE `entity_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `escrow_disputes`
--
ALTER TABLE `escrow_disputes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `dispute_number` (`dispute_number`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_escrow` (`escrow_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `escrow_dispute_evidence`
--
ALTER TABLE `escrow_dispute_evidence`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dispute` (`dispute_id`);

--
-- Indexes for table `escrow_ledger`
--
ALTER TABLE `escrow_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_escrow` (`escrow_id`),
  ADD KEY `idx_entity` (`entity_id`);

--
-- Indexes for table `escrow_status_history`
--
ALTER TABLE `escrow_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_escrow` (`escrow_id`),
  ADD KEY `idx_tenant` (`tenant_id`);

--
-- Indexes for table `escrow_transactions`
--
ALTER TABLE `escrow_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `escrow_number` (`escrow_number`),
  ADD KEY `fk_escrow_buyer` (`buyer_entity_id`),
  ADD KEY `fk_escrow_seller` (`seller_entity_id`),
  ADD KEY `fk_escrow_buyer_type` (`buyer_entity_type_id`),
  ADD KEY `fk_escrow_seller_type` (`seller_entity_type_id`),
  ADD KEY `fk_escrow_currency` (`currency_id`),
  ADD KEY `idx_escrow_order` (`order_id`),
  ADD KEY `idx_escrow_status` (`status`),
  ADD KEY `idx_escrow_tenant` (`tenant_id`);

--
-- Indexes for table `financial_ledger`
--
ALTER TABLE `financial_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `entity_id` (`entity_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `payment_id` (`payment_id`);

--
-- Indexes for table `flash_sales`
--
ALTER TABLE `flash_sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_dates` (`start_date`,`end_date`),
  ADD KEY `idx_flash_sales_entity` (`entity_id`);

--
-- Indexes for table `flash_sales_translations`
--
ALTER TABLE `flash_sales_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_flash_sale_translation` (`flash_sale_id`,`language_code`,`field_name`),
  ADD KEY `fk_language` (`language_code`);

--
-- Indexes for table `flash_sale_products`
--
ALTER TABLE `flash_sale_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_flash_product` (`flash_sale_id`,`product_id`),
  ADD KEY `idx_flash_sale_id` (`flash_sale_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `fk_flashsale_entity` (`entity_id`);

--
-- Indexes for table `font_settings`
--
ALTER TABLE `font_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_theme_font_key` (`theme_id`,`setting_key`),
  ADD UNIQUE KEY `uq_font_tenant_theme_key` (`tenant_id`,`theme_id`,`setting_key`),
  ADD KEY `idx_theme_id` (`theme_id`);

--
-- Indexes for table `homepage_sections`
--
ALTER TABLE `homepage_sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_active` (`tenant_id`,`is_active`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `homepage_section_translations`
--
ALTER TABLE `homepage_section_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_section_lang` (`section_id`,`language_code`),
  ADD KEY `idx_section` (`section_id`);

--
-- Indexes for table `images`
--
ALTER TABLE `images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_images_owner` (`owner_id`),
  ADD KEY `idx_images_image_type_id` (`image_type_id`),
  ADD KEY `idx_images_type_owner` (`image_type_id`,`owner_id`),
  ADD KEY `idx_images_tenant` (`tenant_id`),
  ADD KEY `idx_owner` (`image_type_id`,`owner_id`,`tenant_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `image_types`
--
ALTER TABLE `image_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `independent_drivers`
--
ALTER TABLE `independent_drivers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_driver_user` (`user_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `fk_invoice_user` (`user_id`),
  ADD KEY `idx_invoice_number` (`invoice_number`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_due_date` (`due_date`),
  ADD KEY `fk_invoices_tenant` (`tenant_id`),
  ADD KEY `fk_invoices_entity` (`entity_id`),
  ADD KEY `fk_invoices_payment` (`payment_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `fk_job_city` (`city_id`),
  ADD KEY `fk_job_created_by` (`created_by`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_job_type` (`job_type`),
  ADD KEY `idx_experience_level` (`experience_level`),
  ADD KEY `idx_country_city` (`country_id`,`city_id`),
  ADD KEY `idx_is_featured` (`is_featured`),
  ADD KEY `idx_deadline` (`application_deadline`),
  ADD KEY `entity_id` (`entity_id`);

--
-- Indexes for table `job_alerts`
--
ALTER TABLE `job_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_jobalert_country` (`country_id`),
  ADD KEY `fk_jobalert_city` (`city_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `job_applications`
--
ALTER TABLE `job_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_app_reviewed_by` (`reviewed_by`),
  ADD KEY `idx_job_id` (`job_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `job_application_answers`
--
ALTER TABLE `job_application_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_application_id` (`application_id`),
  ADD KEY `idx_question_id` (`question_id`);

--
-- Indexes for table `job_application_questions`
--
ALTER TABLE `job_application_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_job_id` (`job_id`);

--
-- Indexes for table `job_categories`
--
ALTER TABLE `job_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_parent_id` (`parent_id`),
  ADD KEY `idx_slug` (`slug`);

--
-- Indexes for table `job_category_translations`
--
ALTER TABLE `job_category_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_jcategory_language` (`category_id`,`language_code`),
  ADD KEY `fk_jcat_trans_language` (`language_code`);

--
-- Indexes for table `job_interviews`
--
ALTER TABLE `job_interviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_interview_created_by` (`created_by`),
  ADD KEY `idx_application_id` (`application_id`),
  ADD KEY `idx_interview_date` (`interview_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `job_skills`
--
ALTER TABLE `job_skills`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_job_id` (`job_id`),
  ADD KEY `idx_skill_name` (`skill_name`);

--
-- Indexes for table `job_translations`
--
ALTER TABLE `job_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_job_language` (`job_id`,`language_code`),
  ADD KEY `idx_language` (`language_code`);

--
-- Indexes for table `languages`
--
ALTER TABLE `languages`
  ADD PRIMARY KEY (`code`);

--
-- Indexes for table `loyalty_program_settings`
--
ALTER TABLE `loyalty_program_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_setting_key` (`setting_key`);

--
-- Indexes for table `membership_tiers`
--
ALTER TABLE `membership_tiers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_tier_level` (`tier_level`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `membership_tier_history`
--
ALTER TABLE `membership_tier_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tierhistory_previous` (`previous_tier_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_tier_id` (`tier_id`),
  ADD KEY `idx_changed_at` (`changed_at`);

--
-- Indexes for table `membership_tier_translations`
--
ALTER TABLE `membership_tier_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tier_language` (`tier_id`,`language_code`),
  ADD KEY `fk_tiertrans_language` (`language_code`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `entity_id` (`entity_id`),
  ADD KEY `fk_notification_type` (`notification_type_id`),
  ADD KEY `fk_notifications_tenant` (`tenant_id`),
  ADD KEY `fk_notifications_sender_entity` (`sender_entity_id`);

--
-- Indexes for table `notification_channels`
--
ALTER TABLE `notification_channels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `notification_counters`
--
ALTER TABLE `notification_counters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_counter` (`tenant_id`,`recipient_type`,`recipient_id`);

--
-- Indexes for table `notification_deliveries`
--
ALTER TABLE `notification_deliveries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `notification_id` (`notification_id`),
  ADD KEY `channel_id` (`channel_id`);

--
-- Indexes for table `notification_recipients`
--
ALTER TABLE `notification_recipients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_notification_recipient` (`notification_id`,`recipient_type`,`recipient_id`),
  ADD KEY `idx_recipient` (`recipient_type`,`recipient_id`),
  ADD KEY `idx_nr_lookup` (`recipient_type`,`recipient_id`,`is_read`),
  ADD KEY `idx_recipient_lookup` (`recipient_type`,`recipient_id`,`is_read`);

--
-- Indexes for table `notification_types`
--
ALTER TABLE `notification_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `fk_order_cart` (`cart_id`),
  ADD KEY `idx_order_number` (`order_number`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_shipping_address_id` (`shipping_address_id`),
  ADD KEY `idx_billing_address_id` (`billing_address_id`),
  ADD KEY `fk_order_driver` (`assigned_driver_id`),
  ADD KEY `idx_orders_tenant` (`tenant_id`),
  ADD KEY `idx_orders_entity` (`entity_id`),
  ADD KEY `idx_orders_branch` (`branch_entity_id`),
  ADD KEY `fk_orders_delivery_entity` (`delivery_entity_id`),
  ADD KEY `idx_cart_id` (`cart_id`),
  ADD KEY `fk_orders_auctions` (`auction_id`),
  ADD KEY `idx_pickup_point` (`pickup_point_id`);

--
-- Indexes for table `order_events`
--
ALTER TABLE `order_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `event_type` (`event_type`),
  ADD KEY `actor_type` (`actor_type`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_vendor_id` (`entity_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Indexes for table `order_reviews`
--
ALTER TABLE `order_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_orderreview_delivery` (`delivery_company_id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_vendor_id` (`vendor_id`),
  ADD KEY `idx_overall_rating` (`overall_rating`);

--
-- Indexes for table `order_status_history`
--
ALTER TABLE `order_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_orderstatus_user` (`changed_by`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_number` (`payment_number`),
  ADD KEY `fk_payment_user` (`user_id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_transaction_id` (`transaction_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_payments_entity` (`entity_id`),
  ADD KEY `fk_payments_method_id` (`payment_method_id`);

--
-- Indexes for table `payment_attempts`
--
ALTER TABLE `payment_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_id` (`payment_id`),
  ADD KEY `provider_account_id` (`provider_account_id`);

--
-- Indexes for table `payment_gateway_events`
--
ALTER TABLE `payment_gateway_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `event_reference` (`event_reference`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `method_key` (`method_key`);

--
-- Indexes for table `payment_providers`
--
ALTER TABLE `payment_providers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_provider_key` (`key_name`);

--
-- Indexes for table `payment_providers_accounts`
--
ALTER TABLE `payment_providers_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_provider_account_entity` (`entity_id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tenant_permission` (`tenant_id`,`key_name`),
  ADD KEY `idx_permissions_tenant` (`tenant_id`);

--
-- Indexes for table `platform_report_stats`
--
ALTER TABLE `platform_report_stats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_type_period` (`tenant_id`,`report_type`,`period_type`,`period_date`),
  ADD KEY `idx_entity_type_period` (`entity_id`,`report_type`,`period_type`,`period_date`),
  ADD KEY `idx_report_type` (`report_type`),
  ADD KEY `idx_period_date` (`period_date`),
  ADD KEY `idx_period_type_date` (`period_type`,`period_date`);

--
-- Indexes for table `point_earning_rules`
--
ALTER TABLE `point_earning_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rule_type` (`rule_type`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_dates` (`start_date`,`end_date`);

--
-- Indexes for table `point_redemptions`
--
ALTER TABLE `point_redemptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `redemption_number` (`redemption_number`),
  ADD KEY `fk_redemption_rule` (`rule_id`),
  ADD KEY `idx_redemption_number` (`redemption_number`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_redeemed_at` (`redeemed_at`);

--
-- Indexes for table `point_redemption_rules`
--
ALTER TABLE `point_redemption_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_redemption_product` (`product_id`),
  ADD KEY `idx_redemption_type` (`redemption_type`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_dates` (`start_date`,`end_date`);

--
-- Indexes for table `point_transactions`
--
ALTER TABLE `point_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_number` (`transaction_number`),
  ADD KEY `fk_pointtrans_processed_by` (`processed_by`),
  ADD KEY `idx_transaction_number` (`transaction_number`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_transaction_type` (`transaction_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_source` (`source_type`,`source_id`),
  ADD KEY `idx_expiry_date` (`expiry_date`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `fk_point_transactions_order` (`order_id`),
  ADD KEY `fk_point_transactions_tenant` (`tenant_id`),
  ADD KEY `fk_point_transactions_entity` (`entity_id`);

--
-- Indexes for table `pos_sessions`
--
ALTER TABLE `pos_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pos_sessions_tenant` (`tenant_id`),
  ADD KEY `fk_pos_sessions_entity` (`entity_id`),
  ADD KEY `fk_pos_sessions_cashier` (`cashier_user_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `idx_sku` (`sku`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_barcode` (`barcode`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_stock_status` (`stock_status`),
  ADD KEY `idx_brand_id` (`brand_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_rating` (`rating_average`,`rating_count`),
  ADD KEY `fk_products_product_type` (`product_type_id`),
  ADD KEY `fk_products_created_by_user` (`created_by_user_id`),
  ADD KEY `fk_products_tenant` (`tenant_id`);

--
-- Indexes for table `product_answers`
--
ALTER TABLE `product_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_question_id` (`question_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_approved` (`is_approved`);

--
-- Indexes for table `product_attributes`
--
ALTER TABLE `product_attributes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_is_filterable` (`is_filterable`),
  ADD KEY `idx_is_variation` (`is_variation`),
  ADD KEY `fk_product_attributes_type` (`attribute_type_id`);

--
-- Indexes for table `product_attribute_assignments`
--
ALTER TABLE `product_attribute_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `attribute_id` (`attribute_id`),
  ADD KEY `attribute_value_id` (`attribute_value_id`);

--
-- Indexes for table `product_attribute_translations`
--
ALTER TABLE `product_attribute_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attribute_language` (`attribute_id`,`language_code`),
  ADD KEY `idx_language` (`language_code`);

--
-- Indexes for table `product_attribute_values`
--
ALTER TABLE `product_attribute_values`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attribute_value` (`attribute_id`,`slug`),
  ADD KEY `idx_attribute_id` (`attribute_id`),
  ADD KEY `idx_slug` (`slug`);

--
-- Indexes for table `product_attribute_value_translations`
--
ALTER TABLE `product_attribute_value_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_value_language` (`attribute_value_id`,`language_code`),
  ADD KEY `idx_language` (`language_code`);

--
-- Indexes for table `product_bundles`
--
ALTER TABLE `product_bundles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_dates` (`start_date`,`end_date`),
  ADD KEY `idx_bundle_entity` (`entity_id`),
  ADD KEY `idx_tenant_id` (`tenant_id`);

--
-- Indexes for table `product_bundle_items`
--
ALTER TABLE `product_bundle_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bundle_id` (`bundle_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_tenant_id` (`tenant_id`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_product_category` (`product_id`,`category_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_category_id` (`category_id`),
  ADD KEY `idx_is_primary` (`is_primary`);

--
-- Indexes for table `product_comparisons`
--
ALTER TABLE `product_comparisons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `product_comparison_items`
--
ALTER TABLE `product_comparison_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_comparison_product` (`comparison_id`,`product_id`),
  ADD KEY `idx_comparison_id` (`comparison_id`),
  ADD KEY `idx_product_id` (`product_id`);

--
-- Indexes for table `product_physical_attributes`
--
ALTER TABLE `product_physical_attributes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_product_id` (`product_id`),
  ADD UNIQUE KEY `uniq_variant_id` (`variant_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_variant_id` (`variant_id`);

--
-- Indexes for table `product_pricing`
--
ALTER TABLE `product_pricing`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_pricing_entity` (`product_id`,`variant_id`,`entity_id`),
  ADD KEY `idx_price_product` (`product_id`),
  ADD KEY `idx_price_variant` (`variant_id`),
  ADD KEY `idx_price_active` (`is_active`),
  ADD KEY `fk_product_pricing_currency` (`currency_code`);

--
-- Indexes for table `product_questions`
--
ALTER TABLE `product_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_approved` (`is_approved`);

--
-- Indexes for table `product_relations`
--
ALTER TABLE `product_relations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_relation` (`product_id`,`related_product_id`,`relation_type`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_related_product_id` (`related_product_id`),
  ADD KEY `idx_relation_type` (`relation_type`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_rating` (`rating`),
  ADD KEY `idx_is_approved` (`is_approved`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `product_stock_alerts`
--
ALTER TABLE `product_stock_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_notified` (`is_notified`),
  ADD KEY `idx_variant_id` (`variant_id`);

--
-- Indexes for table `product_stock_movements`
--
ALTER TABLE `product_stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_variant` (`variant_id`);

--
-- Indexes for table `product_translations`
--
ALTER TABLE `product_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_product_language` (`product_id`,`language_code`),
  ADD KEY `idx_language` (`language_code`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `product_types`
--
ALTER TABLE `product_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD UNIQUE KEY `barcode` (`barcode`),
  ADD KEY `idx_variant_product` (`product_id`);

--
-- Indexes for table `product_variant_attributes`
--
ALTER TABLE `product_variant_attributes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_variant_attr` (`variant_id`,`attribute_id`),
  ADD KEY `attribute_id` (`attribute_id`),
  ADD KEY `attribute_value_id` (`attribute_value_id`);

--
-- Indexes for table `product_variant_translations`
--
ALTER TABLE `product_variant_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_variant_lang` (`variant_id`,`language_code`),
  ADD UNIQUE KEY `variant_lang_unique` (`variant_id`,`language_code`);

--
-- Indexes for table `provider_zones`
--
ALTER TABLE `provider_zones`
  ADD PRIMARY KEY (`provider_id`,`zone_id`),
  ADD KEY `zone_id` (`zone_id`);

--
-- Indexes for table `queues`
--
ALTER TABLE `queues`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_queue_status` (`queue`,`status`),
  ADD KEY `idx_status_queue` (`status`,`queue`),
  ADD KEY `idx_queue_status_available` (`queue`,`status`,`available_at`,`created_at`),
  ADD KEY `idx_stuck_jobs` (`queue`,`status`,`processed_at`),
  ADD KEY `idx_queue_stats` (`queue`,`status`,`updated_at`),
  ADD KEY `idx_cleanup` (`status`,`updated_at`),
  ADD KEY `idx_queue_status_created` (`queue`,`status`,`created_at`),
  ADD KEY `idx_status_created` (`status`,`created_at`),
  ADD KEY `idx_attempts` (`attempts`),
  ADD KEY `idx_queues_pop` (`status`,`queue`,`available_at`,`created_at`),
  ADD KEY `idx_queues_status` (`status`),
  ADD KEY `idx_queues_queue` (`queue`),
  ADD KEY `idx_queues_stuck` (`status`,`processed_at`),
  ADD KEY `idx_queues_archive` (`status`,`updated_at`),
  ADD KEY `idx_processed_at` (`processed_at`);
ALTER TABLE `queues` ADD FULLTEXT KEY `idx_queues_error_ft` (`error`);

--
-- Indexes for table `queues_archive`
--
ALTER TABLE `queues_archive`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_queue_status` (`queue`,`status`),
  ADD KEY `idx_status_queue` (`status`,`queue`),
  ADD KEY `idx_queue_status_available` (`queue`,`status`,`available_at`,`created_at`),
  ADD KEY `idx_stuck_jobs` (`queue`,`status`,`processed_at`),
  ADD KEY `idx_queue_stats` (`queue`,`status`,`updated_at`),
  ADD KEY `idx_cleanup` (`status`,`updated_at`),
  ADD KEY `idx_qa_status` (`status`),
  ADD KEY `idx_qa_queue` (`queue`),
  ADD KEY `idx_qa_created` (`created_at`);

--
-- Indexes for table `recently_viewed_products`
--
ALTER TABLE `recently_viewed_products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_session_id` (`session_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_viewed_at` (`viewed_at`);

--
-- Indexes for table `referrals`
--
ALTER TABLE `referrals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `referral_code` (`referral_code`),
  ADD KEY `idx_referral_code` (`referral_code`),
  ADD KEY `idx_referrer_user_id` (`referrer_user_id`),
  ADD KEY `idx_referred_user_id` (`referred_user_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `report_exports`
--
ALTER TABLE `report_exports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_status` (`tenant_id`,`status`),
  ADD KEY `idx_requested_by` (`requested_by`);

--
-- Indexes for table `report_schedules`
--
ALTER TABLE `report_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_active` (`tenant_id`,`is_active`),
  ADD KEY `idx_next_run` (`next_run_at`,`is_active`);

--
-- Indexes for table `report_types`
--
ALTER TABLE `report_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_type_key` (`type_key`);

--
-- Indexes for table `resource_permissions`
--
ALTER TABLE `resource_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_permission_resource_role_tenant` (`permission_id`,`resource_type`,`role_id`,`tenant_id`),
  ADD UNIQUE KEY `uniq_resource_perm` (`tenant_id`,`role_id`,`permission_id`,`resource_type`),
  ADD UNIQUE KEY `unique_tenant_role_resource_permission` (`tenant_key`,`role_id`,`resource_type`,`permission_id`);

--
-- Indexes for table `returns`
--
ALTER TABLE `returns`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `return_number` (`return_number`),
  ADD KEY `fk_returns_entity` (`entity_id`),
  ADD KEY `idx_returns_order` (`order_id`),
  ADD KEY `idx_returns_user` (`user_id`),
  ADD KEY `idx_returns_tenant` (`tenant_id`);

--
-- Indexes for table `return_items`
--
ALTER TABLE `return_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_return_items_return` (`return_id`),
  ADD KEY `fk_return_items_order_item` (`order_item_id`),
  ADD KEY `fk_return_items_product` (`product_id`);

--
-- Indexes for table `return_status_history`
--
ALTER TABLE `return_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_return_status_return` (`return_id`),
  ADD KEY `fk_return_status_user` (`changed_by`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_roles_tenant_key` (`tenant_id`,`key_name`),
  ADD KEY `idx_roles_tenant` (`tenant_id`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_role_permission` (`role_id`,`permission_id`),
  ADD UNIQUE KEY `uq_rp_tenant_role_permission` (`tenant_id`,`role_id`,`permission_id`),
  ADD KEY `fk_roleperm_permission` (`permission_id`),
  ADD KEY `idx_rp_tenant` (`tenant_id`),
  ADD KEY `idx_rp_role` (`role_id`),
  ADD KEY `idx_rp_permission` (`permission_id`);

--
-- Indexes for table `saved_bank_accounts`
--
ALTER TABLE `saved_bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_savedbank_country` (`country_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_vendor_id` (`vendor_id`),
  ADD KEY `idx_is_primary` (`is_primary`);

--
-- Indexes for table `saved_carts`
--
ALTER TABLE `saved_carts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_default` (`is_default`);

--
-- Indexes for table `saved_cart_items`
--
ALTER TABLE `saved_cart_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_saved_cart_id` (`saved_cart_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_product_variant_id` (`product_variant_id`);

--
-- Indexes for table `saved_jobs`
--
ALTER TABLE `saved_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_job_user` (`job_id`,`user_id`),
  ADD KEY `idx_job_id` (`job_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `search_logs`
--
ALTER TABLE `search_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_query_tenant_user_entity_lang` (`query`(100),`tenant_id`,`user_id`,`entity_id`,`lang`),
  ADD KEY `idx_tenant_lang_count` (`tenant_id`,`lang`,`count` DESC),
  ADD KEY `idx_global_lang_count` (`lang`,`count` DESC),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_entity_id` (`entity_id`);

--
-- Indexes for table `seo_meta`
--
ALTER TABLE `seo_meta`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_entity` (`entity_type`,`entity_id`,`tenant_id`);

--
-- Indexes for table `seo_meta_translations`
--
ALTER TABLE `seo_meta_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_seo_language` (`seo_meta_id`,`language_code`),
  ADD KEY `fk_language_code` (`language_code`);

--
-- Indexes for table `store_pages`
--
ALTER TABLE `store_pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_store_pages_entity` (`tenant_id`,`entity_id`,`type`),
  ADD KEY `idx_store_pages_tenant` (`tenant_id`),
  ADD KEY `idx_store_pages_entity` (`entity_id`);

--
-- Indexes for table `store_sections`
--
ALTER TABLE `store_sections`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_store_sections_page` (`page_id`,`position`),
  ADD KEY `idx_store_sections_type` (`type`);

--
-- Indexes for table `store_section_translations`
--
ALTER TABLE `store_section_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_section_translation` (`section_id`,`language_code`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subscription_number` (`subscription_number`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `plan_id` (`plan_id`);

--
-- Indexes for table `subscription_invoices`
--
ALTER TABLE `subscription_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `subscription_id` (`subscription_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payment_number` (`payment_number`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `subscription_id` (`subscription_id`),
  ADD KEY `idx_gateway_txn` (`gateway_transaction_id`);

--
-- Indexes for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `subscription_plan_translations`
--
ALTER TABLE `subscription_plan_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_plan_language` (`plan_id`,`language_code`),
  ADD KEY `fk_plantrans_language` (`language_code`);

--
-- Indexes for table `supplier_product_translations`
--
ALTER TABLE `supplier_product_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_supprod_language` (`supplier_product_id`,`language_code`),
  ADD KEY `fk_supprodtrans_language` (`language_code`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_number` (`ticket_number`),
  ADD KEY `fk_ticket_tenant` (`tenant_id`),
  ADD KEY `fk_ticket_user` (`user_id`),
  ADD KEY `fk_ticket_entity` (`entity_id`),
  ADD KEY `fk_ticket_category` (`category_id`),
  ADD KEY `fk_ticket_assigned` (`assigned_to`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tenant_setting` (`tenant_id`,`setting_key`),
  ADD KEY `idx_setting_key` (`setting_key`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_system_settings_tenant` (`tenant_id`);

--
-- Indexes for table `tax_classes`
--
ALTER TABLE `tax_classes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tenant_owner2` (`owner_user_id`);

--
-- Indexes for table `tenant_categories`
--
ALTER TABLE `tenant_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tenant_category` (`tenant_id`,`category_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `tenant_domains`
--
ALTER TABLE `tenant_domains`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_tenant_domains_domain` (`domain`),
  ADD KEY `idx_tenant_domains_tenant` (`tenant_id`),
  ADD KEY `idx_tenant_domains_type` (`type`),
  ADD KEY `idx_tenant_domains_ssl` (`ssl_status`);

--
-- Indexes for table `tenant_users`
--
ALTER TABLE `tenant_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_tenant_user_entity` (`tenant_id`,`user_id`,`entity_id`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `fk_tu_role` (`role_id`),
  ADD KEY `entity_id` (`entity_id`);

--
-- Indexes for table `themes`
--
ALTER TABLE `themes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_themes_tenant` (`tenant_id`);

--
-- Indexes for table `ticket_categories`
--
ALTER TABLE `ticket_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ticket_cat_parent` (`parent_id`),
  ADD KEY `fk_ticket_cat_tenant` (`tenant_id`);

--
-- Indexes for table `ticket_category_translations`
--
ALTER TABLE `ticket_category_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_category_language` (`category_id`,`language_code`);

--
-- Indexes for table `ticket_messages`
--
ALTER TABLE `ticket_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ticket_msg_ticket` (`ticket_id`),
  ADD KEY `fk_ticket_msg_user` (`sender_user_id`),
  ADD KEY `fk_ticket_msg_tenant` (`tenant_id`);

--
-- Indexes for table `ticket_status_history`
--
ALTER TABLE `ticket_status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ticket_status_ticket` (`ticket_id`),
  ADD KEY `fk_ticket_status_user` (`changed_by`);

--
-- Indexes for table `timezones`
--
ALTER TABLE `timezones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `timezone` (`timezone`);

--
-- Indexes for table `tokens_blacklist`
--
ALTER TABLE `tokens_blacklist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_jti` (`jti`);

--
-- Indexes for table `units`
--
ALTER TABLE `units`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_code` (`tenant_id`,`code`);

--
-- Indexes for table `units_translations`
--
ALTER TABLE `units_translations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_unit_translation` (`unit_id`,`language_code`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_preferred_language` (`preferred_language`);

--
-- Indexes for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `country_id` (`country_id`),
  ADD KEY `city_id` (`city_id`),
  ADD SPATIAL KEY `idx_addresses_location` (`location`),
  ADD KEY `idx_addresses_lat_lon` (`latitude`,`longitude`);

--
-- Indexes for table `user_auth_providers`
--
ALTER TABLE `user_auth_providers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_devices`
--
ALTER TABLE `user_devices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_anonymous_token` (`anonymous_token`),
  ADD UNIQUE KEY `uq_user_device_unique` (`user_id`,`fcm_token`(255)),
  ADD UNIQUE KEY `uniq_fcm_token` (`fcm_token`) USING HASH,
  ADD KEY `idx_user_devices_user_id` (`user_id`),
  ADD KEY `idx_user_devices_active` (`is_active`),
  ADD KEY `idx_user_devices_user_active` (`user_id`,`is_active`),
  ADD KEY `idx_anonymous_token` (`anonymous_token`);

--
-- Indexes for table `user_phone_verifications`
--
ALTER TABLE `user_phone_verifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_token_hash` (`token_hash`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_term_access`
--
ALTER TABLE `user_term_access`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `term_taxonomy_id` (`term_taxonomy_id`);

--
-- Indexes for table `user_wallets`
--
ALTER TABLE `user_wallets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `wallet_number` (`wallet_number`),
  ADD UNIQUE KEY `unique_user_wallet` (`user_id`),
  ADD KEY `idx_wallet_number` (`wallet_number`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `wallet_balance_log`
--
ALTER TABLE `wallet_balance_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wallet` (`wallet_type`,`wallet_id`),
  ADD KEY `idx_logged_at` (`logged_at`);

--
-- Indexes for table `wallet_entity_balances`
--
ALTER TABLE `wallet_entity_balances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_wallet_entity` (`wallet_id`,`entity_id`),
  ADD KEY `idx_wallet` (`wallet_id`),
  ADD KEY `idx_entity` (`entity_id`);

--
-- Indexes for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_number` (`transaction_number`),
  ADD KEY `fk_wtrans_processed_by` (`processed_by`),
  ADD KEY `idx_transaction_number` (`transaction_number`),
  ADD KEY `idx_wallet_type_id` (`wallet_type`,`wallet_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_vendor_id` (`vendor_id`),
  ADD KEY `idx_transaction_type` (`transaction_type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_reference` (`reference_type`,`reference_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `fk_wallet_tx_tenant` (`tenant_id`),
  ADD KEY `idx_wallet_tx_wallet` (`wallet_id`),
  ADD KEY `idx_wallet_tx_entity` (`entity_id`),
  ADD KEY `idx_wallet_tx_user` (`user_id`);

--
-- Indexes for table `wallet_transfers`
--
ALTER TABLE `wallet_transfers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transfer_number` (`transfer_number`),
  ADD KEY `fk_transfer_from_trans` (`from_transaction_id`),
  ADD KEY `fk_transfer_to_trans` (`to_transaction_id`),
  ADD KEY `idx_transfer_number` (`transfer_number`),
  ADD KEY `idx_from_wallet` (`from_wallet_type`,`from_wallet_id`),
  ADD KEY `idx_to_wallet` (`to_wallet_type`,`to_wallet_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `fk_transfer_tenant` (`tenant_id`),
  ADD KEY `fk_transfer_entity` (`entity_id`);

--
-- Indexes for table `wishlists`
--
ALTER TABLE `wishlists`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_default_wishlist` (`user_id`,`is_default`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_default` (`is_default`),
  ADD KEY `idx_scope_user` (`tenant_id`,`entity_id`,`user_id`);

--
-- Indexes for table `wishlist_items`
--
ALTER TABLE `wishlist_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_wishlist_product` (`wishlist_id`,`product_id`,`product_variant_id`),
  ADD KEY `idx_wishlist_id` (`wishlist_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_product_variant_id` (`product_variant_id`),
  ADD KEY `idx_scope_active` (`tenant_id`,`entity_id`,`wishlist_id`,`removed_at`);

--
-- Indexes for table `withdrawal_requests`
--
ALTER TABLE `withdrawal_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `request_number` (`request_number`),
  ADD KEY `fk_withdrawal_approved_by` (`approved_by`),
  ADD KEY `fk_withdrawal_processed_by` (`processed_by`),
  ADD KEY `fk_withdrawal_transaction` (`transaction_id`),
  ADD KEY `idx_request_number` (`request_number`),
  ADD KEY `idx_wallet_type` (`wallet_type`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_vendor_id` (`vendor_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_requested_at` (`requested_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `ads`
--
ALTER TABLE `ads`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `ad_campaigns`
--
ALTER TABLE `ad_campaigns`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `ad_payments`
--
ALTER TABLE `ad_payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ad_placements`
--
ALTER TABLE `ad_placements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `ad_placement_items`
--
ALTER TABLE `ad_placement_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `ad_stats`
--
ALTER TABLE `ad_stats`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=494;

--
-- AUTO_INCREMENT for table `ad_translations`
--
ALTER TABLE `ad_translations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `ai_usage_logs`
--
ALTER TABLE `ai_usage_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

--
-- AUTO_INCREMENT for table `api_tokens`
--
ALTER TABLE `api_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attribute_types`
--
ALTER TABLE `attribute_types`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `auctions`
--
ALTER TABLE `auctions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `auction_activity_log`
--
ALTER TABLE `auction_activity_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `auction_bids`
--
ALTER TABLE `auction_bids`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `auction_translations`
--
ALTER TABLE `auction_translations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `auction_watchers`
--
ALTER TABLE `auction_watchers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT for table `auto_bid_settings`
--
ALTER TABLE `auto_bid_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `bad_words`
--
ALTER TABLE `bad_words`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `bad_word_translations`
--
ALTER TABLE `bad_word_translations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `brands`
--
ALTER TABLE `brands`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `brand_translations`
--
ALTER TABLE `brand_translations`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `button_styles`
--
ALTER TABLE `button_styles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `card_styles`
--
ALTER TABLE `card_styles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=142;

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `cart_events`
--
ALTER TABLE `cart_events`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2020;

--
-- AUTO_INCREMENT for table `category_translations`
--
ALTER TABLE `category_translations`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5040;

--
-- AUTO_INCREMENT for table `color_settings`
--
ALTER TABLE `color_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=211;

--
-- AUTO_INCREMENT for table `commission_credit_notes`
--
ALTER TABLE `commission_credit_notes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `commission_invoices`
--
ALTER TABLE `commission_invoices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `commission_invoice_items`
--
ALTER TABLE `commission_invoice_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `commission_payments`
--
ALTER TABLE `commission_payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `commission_transactions`
--
ALTER TABLE `commission_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `core_events`
--
ALTER TABLE `core_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1917;

--
-- AUTO_INCREMENT for table `country_taxes`
--
ALTER TABLE `country_taxes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `currencies`
--
ALTER TABLE `currencies`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `delivery_orders`
--
ALTER TABLE `delivery_orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `delivery_providers`
--
ALTER TABLE `delivery_providers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `delivery_tracking`
--
ALTER TABLE `delivery_tracking`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `delivery_zones`
--
ALTER TABLE `delivery_zones`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `design_settings`
--
ALTER TABLE `design_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=201;

--
-- AUTO_INCREMENT for table `discounts`
--
ALTER TABLE `discounts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `discount_actions`
--
ALTER TABLE `discount_actions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `discount_conditions`
--
ALTER TABLE `discount_conditions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `discount_exclusions`
--
ALTER TABLE `discount_exclusions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `discount_redemptions`
--
ALTER TABLE `discount_redemptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `discount_scopes`
--
ALTER TABLE `discount_scopes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `discount_translations`
--
ALTER TABLE `discount_translations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `driver_locations`
--
ALTER TABLE `driver_locations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `entities`
--
ALTER TABLE `entities`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `entities_attribute_values`
--
ALTER TABLE `entities_attribute_values`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `entities_working_hours`
--
ALTER TABLE `entities_working_hours`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=523;

--
-- AUTO_INCREMENT for table `entity_bank_accounts`
--
ALTER TABLE `entity_bank_accounts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `entity_categories`
--
ALTER TABLE `entity_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `entity_delivery_relations`
--
ALTER TABLE `entity_delivery_relations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `entity_logs`
--
ALTER TABLE `entity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `entity_payment_methods`
--
ALTER TABLE `entity_payment_methods`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `entity_pickup_points`
--
ALTER TABLE `entity_pickup_points`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `entity_policies`
--
ALTER TABLE `entity_policies`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `entity_products`
--
ALTER TABLE `entity_products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `entity_product_variants`
--
ALTER TABLE `entity_product_variants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `entity_ratings`
--
ALTER TABLE `entity_ratings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `entity_translations`
--
ALTER TABLE `entity_translations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT for table `entity_types`
--
ALTER TABLE `entity_types`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `escrow_disputes`
--
ALTER TABLE `escrow_disputes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `escrow_dispute_evidence`
--
ALTER TABLE `escrow_dispute_evidence`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `escrow_ledger`
--
ALTER TABLE `escrow_ledger`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `escrow_status_history`
--
ALTER TABLE `escrow_status_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `escrow_transactions`
--
ALTER TABLE `escrow_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `financial_ledger`
--
ALTER TABLE `financial_ledger`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `flash_sales`
--
ALTER TABLE `flash_sales`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `flash_sales_translations`
--
ALTER TABLE `flash_sales_translations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `font_settings`
--
ALTER TABLE `font_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `homepage_sections`
--
ALTER TABLE `homepage_sections`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `homepage_section_translations`
--
ALTER TABLE `homepage_section_translations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- AUTO_INCREMENT for table `images`
--
ALTER TABLE `images`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=295;

--
-- AUTO_INCREMENT for table `image_types`
--
ALTER TABLE `image_types`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `job_alerts`
--
ALTER TABLE `job_alerts`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `job_applications`
--
ALTER TABLE `job_applications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `job_application_answers`
--
ALTER TABLE `job_application_answers`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `job_application_questions`
--
ALTER TABLE `job_application_questions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `job_categories`
--
ALTER TABLE `job_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `job_category_translations`
--
ALTER TABLE `job_category_translations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `job_interviews`
--
ALTER TABLE `job_interviews`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `notification_channels`
--
ALTER TABLE `notification_channels`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `notification_counters`
--
ALTER TABLE `notification_counters`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `notification_deliveries`
--
ALTER TABLE `notification_deliveries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `notification_recipients`
--
ALTER TABLE `notification_recipients`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `notification_types`
--
ALTER TABLE `notification_types`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `order_events`
--
ALTER TABLE `order_events`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `payment_attempts`
--
ALTER TABLE `payment_attempts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_gateway_events`
--
ALTER TABLE `payment_gateway_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `payment_providers`
--
ALTER TABLE `payment_providers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `payment_providers_accounts`
--
ALTER TABLE `payment_providers_accounts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `platform_report_stats`
--
ALTER TABLE `platform_report_stats`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pos_sessions`
--
ALTER TABLE `pos_sessions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT for table `product_attribute_assignments`
--
ALTER TABLE `product_attribute_assignments`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT for table `product_attribute_translations`
--
ALTER TABLE `product_attribute_translations`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `product_attribute_values`
--
ALTER TABLE `product_attribute_values`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `product_attribute_value_translations`
--
ALTER TABLE `product_attribute_value_translations`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `product_bundles`
--
ALTER TABLE `product_bundles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_bundle_items`
--
ALTER TABLE `product_bundle_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=273;

--
-- AUTO_INCREMENT for table `product_comparisons`
--
ALTER TABLE `product_comparisons`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `product_comparison_items`
--
ALTER TABLE `product_comparison_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `product_physical_attributes`
--
ALTER TABLE `product_physical_attributes`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `product_pricing`
--
ALTER TABLE `product_pricing`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `product_stock_movements`
--
ALTER TABLE `product_stock_movements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `product_translations`
--
ALTER TABLE `product_translations`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT for table `product_types`
--
ALTER TABLE `product_types`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `product_variants`
--
ALTER TABLE `product_variants`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `queues`
--
ALTER TABLE `queues`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `queues_archive`
--
ALTER TABLE `queues_archive`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_exports`
--
ALTER TABLE `report_exports`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `report_schedules`
--
ALTER TABLE `report_schedules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_types`
--
ALTER TABLE `report_types`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `resource_permissions`
--
ALTER TABLE `resource_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `returns`
--
ALTER TABLE `returns`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `return_items`
--
ALTER TABLE `return_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `return_status_history`
--
ALTER TABLE `return_status_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `search_logs`
--
ALTER TABLE `search_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `seo_meta`
--
ALTER TABLE `seo_meta`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=136;

--
-- AUTO_INCREMENT for table `seo_meta_translations`
--
ALTER TABLE `seo_meta_translations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=454;

--
-- AUTO_INCREMENT for table `store_pages`
--
ALTER TABLE `store_pages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `store_sections`
--
ALTER TABLE `store_sections`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `store_section_translations`
--
ALTER TABLE `store_section_translations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `subscription_invoices`
--
ALTER TABLE `subscription_invoices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `subscription_plans`
--
ALTER TABLE `subscription_plans`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tax_classes`
--
ALTER TABLE `tax_classes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tenant_categories`
--
ALTER TABLE `tenant_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=182;

--
-- AUTO_INCREMENT for table `tenant_domains`
--
ALTER TABLE `tenant_domains`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tenant_users`
--
ALTER TABLE `tenant_users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `ticket_categories`
--
ALTER TABLE `ticket_categories`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1005;

--
-- AUTO_INCREMENT for table `ticket_category_translations`
--
ALTER TABLE `ticket_category_translations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT for table `ticket_messages`
--
ALTER TABLE `ticket_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `ticket_status_history`
--
ALTER TABLE `ticket_status_history`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `units`
--
ALTER TABLE `units`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `units_translations`
--
ALTER TABLE `units_translations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `user_auth_providers`
--
ALTER TABLE `user_auth_providers`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `user_devices`
--
ALTER TABLE `user_devices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `user_phone_verifications`
--
ALTER TABLE `user_phone_verifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wallet_entity_balances`
--
ALTER TABLE `wallet_entity_balances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `wishlists`
--
ALTER TABLE `wishlists`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `wishlist_items`
--
ALTER TABLE `wishlist_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `addresses_ibfk_1` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`),
  ADD CONSTRAINT `addresses_ibfk_2` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  ADD CONSTRAINT `fk_addresses_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_addresses_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ads`
--
ALTER TABLE `ads`
  ADD CONSTRAINT `ads_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `ad_campaigns` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ad_campaigns`
--
ALTER TABLE `ad_campaigns`
  ADD CONSTRAINT `ad_campaigns_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ad_campaigns_ibfk_2` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ad_campaigns_ibfk_3` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  ADD CONSTRAINT `ad_campaigns_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `ad_payments`
--
ALTER TABLE `ad_payments`
  ADD CONSTRAINT `ad_payments_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `ad_campaigns` (`id`),
  ADD CONSTRAINT `ad_payments_ibfk_2` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`);

--
-- Constraints for table `ad_placements`
--
ALTER TABLE `ad_placements`
  ADD CONSTRAINT `fk_ad_placements_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ad_placement_items`
--
ALTER TABLE `ad_placement_items`
  ADD CONSTRAINT `ad_placement_items_ibfk_1` FOREIGN KEY (`placement_id`) REFERENCES `ad_placements` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ad_placement_items_ibfk_2` FOREIGN KEY (`ad_id`) REFERENCES `ads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ad_stats`
--
ALTER TABLE `ad_stats`
  ADD CONSTRAINT `ad_stats_ibfk_1` FOREIGN KEY (`ad_id`) REFERENCES `ads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ad_translations`
--
ALTER TABLE `ad_translations`
  ADD CONSTRAINT `ad_translations_ibfk_1` FOREIGN KEY (`ad_id`) REFERENCES `ads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ad_translations_ibfk_2` FOREIGN KEY (`language_code`) REFERENCES `languages` (`code`);

--
-- Constraints for table `ai_documents`
--
ALTER TABLE `ai_documents`
  ADD CONSTRAINT `ai_documents_ibfk_1` FOREIGN KEY (`knowledge_base_id`) REFERENCES `ai_knowledge_bases` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ai_document_chunks`
--
ALTER TABLE `ai_document_chunks`
  ADD CONSTRAINT `ai_document_chunks_ibfk_1` FOREIGN KEY (`document_id`) REFERENCES `ai_documents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ai_feedback`
--
ALTER TABLE `ai_feedback`
  ADD CONSTRAINT `ai_feedback_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `ai_messages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ai_messages`
--
ALTER TABLE `ai_messages`
  ADD CONSTRAINT `ai_messages_ibfk_1` FOREIGN KEY (`thread_id`) REFERENCES `ai_threads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ai_message_files`
--
ALTER TABLE `ai_message_files`
  ADD CONSTRAINT `ai_message_files_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `ai_messages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ai_message_files_ibfk_2` FOREIGN KEY (`file_id`) REFERENCES `ai_files` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ai_thread_memory`
--
ALTER TABLE `ai_thread_memory`
  ADD CONSTRAINT `ai_thread_memory_ibfk_1` FOREIGN KEY (`thread_id`) REFERENCES `ai_threads` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ai_vision_analyses`
--
ALTER TABLE `ai_vision_analyses`
  ADD CONSTRAINT `ai_vision_analyses_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `ai_files` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ai_vision_analyses_ibfk_2` FOREIGN KEY (`message_id`) REFERENCES `ai_messages` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `auctions`
--
ALTER TABLE `auctions`
  ADD CONSTRAINT `fk_auctions_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  ADD CONSTRAINT `fk_auctions_products` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `auction_bids`
--
ALTER TABLE `auction_bids`
  ADD CONSTRAINT `fk_bids_auctions` FOREIGN KEY (`auction_id`) REFERENCES `auctions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_logs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `auto_bid_settings`
--
ALTER TABLE `auto_bid_settings`
  ADD CONSTRAINT `fk_auto_bid_auction` FOREIGN KEY (`auction_id`) REFERENCES `auctions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_auto_bid_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `bad_word_translations`
--
ALTER TABLE `bad_word_translations`
  ADD CONSTRAINT `fk_bad_word` FOREIGN KEY (`bad_word_id`) REFERENCES `bad_words` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `banners`
--
ALTER TABLE `banners`
  ADD CONSTRAINT `fk_banners_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_banners_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `banner_translations`
--
ALTER TABLE `banner_translations`
  ADD CONSTRAINT `fk_banner_translations_banner` FOREIGN KEY (`banner_id`) REFERENCES `banners` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `brands`
--
ALTER TABLE `brands`
  ADD CONSTRAINT `fk_brand_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_brands_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `button_styles`
--
ALTER TABLE `button_styles`
  ADD CONSTRAINT `fk_button_styles_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `card_styles`
--
ALTER TABLE `card_styles`
  ADD CONSTRAINT `fk_card_styles_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `fk_cart_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_cart_entity_fk` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_carts_discount` FOREIGN KEY (`discount_id`) REFERENCES `discounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_carts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `fk_cart_items_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `fk_categories_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `color_settings`
--
ALTER TABLE `color_settings`
  ADD CONSTRAINT `fk_color_settings_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `commission_credit_notes`
--
ALTER TABLE `commission_credit_notes`
  ADD CONSTRAINT `commission_credit_notes_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `commission_credit_notes_ibfk_2` FOREIGN KEY (`invoice_id`) REFERENCES `commission_invoices` (`id`),
  ADD CONSTRAINT `commission_credit_notes_ibfk_3` FOREIGN KEY (`related_transaction_id`) REFERENCES `commission_transactions` (`id`);

--
-- Constraints for table `commission_invoices`
--
ALTER TABLE `commission_invoices`
  ADD CONSTRAINT `commission_invoices_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `commission_invoices_ibfk_2` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`);

--
-- Constraints for table `commission_invoice_items`
--
ALTER TABLE `commission_invoice_items`
  ADD CONSTRAINT `commission_invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `commission_invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `commission_invoice_items_ibfk_2` FOREIGN KEY (`transaction_id`) REFERENCES `commission_transactions` (`id`);

--
-- Constraints for table `commission_payments`
--
ALTER TABLE `commission_payments`
  ADD CONSTRAINT `commission_payments_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `commission_payments_ibfk_2` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`),
  ADD CONSTRAINT `commission_payments_ibfk_3` FOREIGN KEY (`commission_invoice_id`) REFERENCES `commission_invoices` (`id`);

--
-- Constraints for table `commission_transactions`
--
ALTER TABLE `commission_transactions`
  ADD CONSTRAINT `commission_transactions_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `commission_transactions_ibfk_2` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `country_taxes`
--
ALTER TABLE `country_taxes`
  ADD CONSTRAINT `country_taxes_ibfk_1` FOREIGN KEY (`tax_class_id`) REFERENCES `tax_classes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `delivery_orders`
--
ALTER TABLE `delivery_orders`
  ADD CONSTRAINT `delivery_orders_ibfk_1` FOREIGN KEY (`delivery_zone_id`) REFERENCES `delivery_zones` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_delivery_order_provider` FOREIGN KEY (`provider_id`) REFERENCES `delivery_providers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_do_dropoff` FOREIGN KEY (`dropoff_address_id`) REFERENCES `addresses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_do_pickup` FOREIGN KEY (`pickup_address_id`) REFERENCES `addresses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_do_provider` FOREIGN KEY (`provider_id`) REFERENCES `delivery_providers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_do_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `delivery_providers`
--
ALTER TABLE `delivery_providers`
  ADD CONSTRAINT `fk_dp_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dp_tenant_user` FOREIGN KEY (`tenant_user_id`) REFERENCES `tenant_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `delivery_tracking`
--
ALTER TABLE `delivery_tracking`
  ADD CONSTRAINT `delivery_tracking_ibfk_1` FOREIGN KEY (`delivery_order_id`) REFERENCES `delivery_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dt_order` FOREIGN KEY (`delivery_order_id`) REFERENCES `delivery_orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `delivery_zones`
--
ALTER TABLE `delivery_zones`
  ADD CONSTRAINT `fk_dz_city` FOREIGN KEY (`city_id`) REFERENCES `cities` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_dz_provider` FOREIGN KEY (`provider_id`) REFERENCES `delivery_providers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dz_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_zone_provider` FOREIGN KEY (`provider_id`) REFERENCES `delivery_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `design_settings`
--
ALTER TABLE `design_settings`
  ADD CONSTRAINT `fk_design_settings_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `discounts`
--
ALTER TABLE `discounts`
  ADD CONSTRAINT `fk_discounts_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `discount_actions`
--
ALTER TABLE `discount_actions`
  ADD CONSTRAINT `fk_action_discount` FOREIGN KEY (`discount_id`) REFERENCES `discounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `discount_conditions`
--
ALTER TABLE `discount_conditions`
  ADD CONSTRAINT `fk_condition_discount` FOREIGN KEY (`discount_id`) REFERENCES `discounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `discount_exclusions`
--
ALTER TABLE `discount_exclusions`
  ADD CONSTRAINT `fk_exclusion_discount` FOREIGN KEY (`discount_id`) REFERENCES `discounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `discount_redemptions`
--
ALTER TABLE `discount_redemptions`
  ADD CONSTRAINT `fk_redemption_discount` FOREIGN KEY (`discount_id`) REFERENCES `discounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `discount_scopes`
--
ALTER TABLE `discount_scopes`
  ADD CONSTRAINT `fk_scope_discount` FOREIGN KEY (`discount_id`) REFERENCES `discounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `discount_translations`
--
ALTER TABLE `discount_translations`
  ADD CONSTRAINT `fk_discount_translation` FOREIGN KEY (`discount_id`) REFERENCES `discounts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `driver_locations`
--
ALTER TABLE `driver_locations`
  ADD CONSTRAINT `driver_locations_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `delivery_providers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dl_provider` FOREIGN KEY (`provider_id`) REFERENCES `delivery_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `entities`
--
ALTER TABLE `entities`
  ADD CONSTRAINT `fk_entities_parent` FOREIGN KEY (`parent_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_entities_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_entities_timezone` FOREIGN KEY (`timezone_id`) REFERENCES `timezones` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `entities_working_hours`
--
ALTER TABLE `entities_working_hours`
  ADD CONSTRAINT `fk_entities_working_hours_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `entity_bank_accounts`
--
ALTER TABLE `entity_bank_accounts`
  ADD CONSTRAINT `fk_entity_bank_accounts_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `entity_delivery_relations`
--
ALTER TABLE `entity_delivery_relations`
  ADD CONSTRAINT `fk_edr_delivery_entity` FOREIGN KEY (`delivery_entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_edr_target_entity` FOREIGN KEY (`target_entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_edr_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `entity_financial_balances`
--
ALTER TABLE `entity_financial_balances`
  ADD CONSTRAINT `entity_financial_balances_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `entity_financial_balances_ibfk_2` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`);

--
-- Constraints for table `entity_logs`
--
ALTER TABLE `entity_logs`
  ADD CONSTRAINT `entity_logs_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_entity_logs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_entity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `entity_payment_methods`
--
ALTER TABLE `entity_payment_methods`
  ADD CONSTRAINT `fk_entity_payment_methods_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_entity_payment_methods_method` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `entity_pickup_points`
--
ALTER TABLE `entity_pickup_points`
  ADD CONSTRAINT `fk_epp_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `entity_products`
--
ALTER TABLE `entity_products`
  ADD CONSTRAINT `entity_products_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `entity_products_ibfk_2` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `entity_products_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `entity_product_variants`
--
ALTER TABLE `entity_product_variants`
  ADD CONSTRAINT `entity_product_variants_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `entity_product_variants_ibfk_2` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `entity_product_variants_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `entity_product_variants_ibfk_4` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `entity_ratings`
--
ALTER TABLE `entity_ratings`
  ADD CONSTRAINT `fk_entity_ratings_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_entity_ratings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `entity_settings`
--
ALTER TABLE `entity_settings`
  ADD CONSTRAINT `fk_entity_settings_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `entity_translations`
--
ALTER TABLE `entity_translations`
  ADD CONSTRAINT `fk_entity_translations_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_entity_translations_language` FOREIGN KEY (`language_code`) REFERENCES `languages` (`code`) ON DELETE CASCADE;

--
-- Constraints for table `escrow_transactions`
--
ALTER TABLE `escrow_transactions`
  ADD CONSTRAINT `fk_escrow_buyer` FOREIGN KEY (`buyer_entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_escrow_buyer_type` FOREIGN KEY (`buyer_entity_type_id`) REFERENCES `entity_types` (`id`),
  ADD CONSTRAINT `fk_escrow_currency` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  ADD CONSTRAINT `fk_escrow_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_escrow_seller` FOREIGN KEY (`seller_entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_escrow_seller_type` FOREIGN KEY (`seller_entity_type_id`) REFERENCES `entity_types` (`id`);

--
-- Constraints for table `flash_sales`
--
ALTER TABLE `flash_sales`
  ADD CONSTRAINT `fk_flash_sales_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `flash_sales_translations`
--
ALTER TABLE `flash_sales_translations`
  ADD CONSTRAINT `fk_flash_sale` FOREIGN KEY (`flash_sale_id`) REFERENCES `flash_sales` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_language` FOREIGN KEY (`language_code`) REFERENCES `languages` (`code`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `flash_sale_products`
--
ALTER TABLE `flash_sale_products`
  ADD CONSTRAINT `fk_flashsale_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `font_settings`
--
ALTER TABLE `font_settings`
  ADD CONSTRAINT `fk_font_settings_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `images`
--
ALTER TABLE `images`
  ADD CONSTRAINT `fk_images_image_type` FOREIGN KEY (`image_type_id`) REFERENCES `image_types` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `fk_invoices_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_invoices_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_invoices_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notification_type` FOREIGN KEY (`notification_type_id`) REFERENCES `notification_types` (`id`),
  ADD CONSTRAINT `fk_notifications_sender_entity` FOREIGN KEY (`sender_entity_id`) REFERENCES `entities` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_notifications_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`);

--
-- Constraints for table `notification_counters`
--
ALTER TABLE `notification_counters`
  ADD CONSTRAINT `fk_nc_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_recipients`
--
ALTER TABLE `notification_recipients`
  ADD CONSTRAINT `fk_notification_recipient` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_nr_notification` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_auctions` FOREIGN KEY (`auction_id`) REFERENCES `auctions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_cart` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_orders_delivery_entity` FOREIGN KEY (`delivery_entity_id`) REFERENCES `entities` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_orders_tenant_ref` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_order_items_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payments_entity_id_entities` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payments_method_id` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payment_providers_accounts`
--
ALTER TABLE `payment_providers_accounts`
  ADD CONSTRAINT `fk_provider_account_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `permissions`
--
ALTER TABLE `permissions`
  ADD CONSTRAINT `fk_permissions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `point_transactions`
--
ALTER TABLE `point_transactions`
  ADD CONSTRAINT `fk_point_transactions_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_point_transactions_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_point_transactions_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_point_transactions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_point_transactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pos_sessions`
--
ALTER TABLE `pos_sessions`
  ADD CONSTRAINT `fk_pos_sessions_cashier` FOREIGN KEY (`cashier_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pos_sessions_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pos_sessions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_created_by_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `tenant_users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_products_product_type` FOREIGN KEY (`product_type_id`) REFERENCES `product_types` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_products_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `product_attributes`
--
ALTER TABLE `product_attributes`
  ADD CONSTRAINT `fk_product_attributes_type` FOREIGN KEY (`attribute_type_id`) REFERENCES `attribute_types` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `product_attribute_translations`
--
ALTER TABLE `product_attribute_translations`
  ADD CONSTRAINT `fk_attr_translation_attribute` FOREIGN KEY (`attribute_id`) REFERENCES `product_attributes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_bundles`
--
ALTER TABLE `product_bundles`
  ADD CONSTRAINT `fk_bundle_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_bundle_items`
--
ALTER TABLE `product_bundle_items`
  ADD CONSTRAINT `fk_bundle_items_bundle` FOREIGN KEY (`bundle_id`) REFERENCES `product_bundles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bundle_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product_comparison_items`
--
ALTER TABLE `product_comparison_items`
  ADD CONSTRAINT `fk_comparison_items_comparison` FOREIGN KEY (`comparison_id`) REFERENCES `product_comparisons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_comparison_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product_physical_attributes`
--
ALTER TABLE `product_physical_attributes`
  ADD CONSTRAINT `fk_physical_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_physical_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product_pricing`
--
ALTER TABLE `product_pricing`
  ADD CONSTRAINT `fk_product_pricing_currency` FOREIGN KEY (`currency_code`) REFERENCES `currencies` (`code`) ON UPDATE CASCADE;

--
-- Constraints for table `product_questions`
--
ALTER TABLE `product_questions`
  ADD CONSTRAINT `fk_questions_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product_relations`
--
ALTER TABLE `product_relations`
  ADD CONSTRAINT `fk_relations_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_relations_related_product` FOREIGN KEY (`related_product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD CONSTRAINT `fk_review_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `product_translations`
--
ALTER TABLE `product_translations`
  ADD CONSTRAINT `fk_translation_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `provider_zones`
--
ALTER TABLE `provider_zones`
  ADD CONSTRAINT `provider_zones_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `delivery_providers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `provider_zones_ibfk_2` FOREIGN KEY (`zone_id`) REFERENCES `delivery_zones` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `resource_permissions`
--
ALTER TABLE `resource_permissions`
  ADD CONSTRAINT `resource_permissions_ibfk_1` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `returns`
--
ALTER TABLE `returns`
  ADD CONSTRAINT `fk_returns_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_returns_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_returns_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_returns_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `return_items`
--
ALTER TABLE `return_items`
  ADD CONSTRAINT `fk_return_items_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_return_items_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_return_items_return` FOREIGN KEY (`return_id`) REFERENCES `returns` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `return_status_history`
--
ALTER TABLE `return_status_history`
  ADD CONSTRAINT `fk_return_status_return` FOREIGN KEY (`return_id`) REFERENCES `returns` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_return_status_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `roles`
--
ALTER TABLE `roles`
  ADD CONSTRAINT `fk_roles_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `seo_meta_translations`
--
ALTER TABLE `seo_meta_translations`
  ADD CONSTRAINT `fk_language_code` FOREIGN KEY (`language_code`) REFERENCES `languages` (`code`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_seo_meta` FOREIGN KEY (`seo_meta_id`) REFERENCES `seo_meta` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `store_sections`
--
ALTER TABLE `store_sections`
  ADD CONSTRAINT `fk_store_sections_page` FOREIGN KEY (`page_id`) REFERENCES `store_pages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `store_section_translations`
--
ALTER TABLE `store_section_translations`
  ADD CONSTRAINT `fk_section_trans_section` FOREIGN KEY (`section_id`) REFERENCES `store_sections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`),
  ADD CONSTRAINT `subscriptions_ibfk_2` FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans` (`id`);

--
-- Constraints for table `subscription_invoices`
--
ALTER TABLE `subscription_invoices`
  ADD CONSTRAINT `subscription_invoices_ibfk_1` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`),
  ADD CONSTRAINT `subscription_invoices_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`);

--
-- Constraints for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  ADD CONSTRAINT `subscription_payments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `subscription_invoices` (`id`),
  ADD CONSTRAINT `subscription_payments_ibfk_2` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`);

--
-- Constraints for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD CONSTRAINT `fk_ticket_assigned` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ticket_category` FOREIGN KEY (`category_id`) REFERENCES `ticket_categories` (`id`),
  ADD CONSTRAINT `fk_ticket_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ticket_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ticket_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `fk_system_settings_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `tenants`
--
ALTER TABLE `tenants`
  ADD CONSTRAINT `fk_tenant_owner2` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tenant_categories`
--
ALTER TABLE `tenant_categories`
  ADD CONSTRAINT `tenant_categories_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tenant_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tenant_domains`
--
ALTER TABLE `tenant_domains`
  ADD CONSTRAINT `fk_tenant_domains_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `tenant_users`
--
ALTER TABLE `tenant_users`
  ADD CONSTRAINT `fk_tenant_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tenant_users_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tu_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `themes`
--
ALTER TABLE `themes`
  ADD CONSTRAINT `fk_themes_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `ticket_categories`
--
ALTER TABLE `ticket_categories`
  ADD CONSTRAINT `fk_ticket_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `ticket_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ticket_cat_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ticket_category_translations`
--
ALTER TABLE `ticket_category_translations`
  ADD CONSTRAINT `fk_ticket_category_translation` FOREIGN KEY (`category_id`) REFERENCES `ticket_categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ticket_messages`
--
ALTER TABLE `ticket_messages`
  ADD CONSTRAINT `fk_ticket_msg_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ticket_msg_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ticket_msg_user` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ticket_status_history`
--
ALTER TABLE `ticket_status_history`
  ADD CONSTRAINT `fk_ticket_status_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ticket_status_user` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `units_translations`
--
ALTER TABLE `units_translations`
  ADD CONSTRAINT `units_translations_ibfk_1` FOREIGN KEY (`unit_id`) REFERENCES `units` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_preferred_language` FOREIGN KEY (`preferred_language`) REFERENCES `languages` (`code`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `wallet_entity_balances`
--
ALTER TABLE `wallet_entity_balances`
  ADD CONSTRAINT `fk_web_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_web_wallet` FOREIGN KEY (`wallet_id`) REFERENCES `user_wallets` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `wallet_transactions`
--
ALTER TABLE `wallet_transactions`
  ADD CONSTRAINT `fk_wallet_tx_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_wallet_tx_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `wallet_transfers`
--
ALTER TABLE `wallet_transfers`
  ADD CONSTRAINT `fk_transfer_entity` FOREIGN KEY (`entity_id`) REFERENCES `entities` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_transfer_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `wishlist_items`
--
ALTER TABLE `wishlist_items`
  ADD CONSTRAINT `fk_wishlist_items_wishlist` FOREIGN KEY (`wishlist_id`) REFERENCES `wishlists` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
