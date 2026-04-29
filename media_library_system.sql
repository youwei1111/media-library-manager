-- Media Library System - Database Schema
-- Optimized for GitHub Deployment

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Table structure for table `media_items`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `media_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL COMMENT 'Media Title',
  `type` enum('movie','tv','show','manga','anime','book') DEFAULT NULL COMMENT 'Category',
  `status` enum('想看','在看','已看','追平') DEFAULT '想看' COMMENT 'Watching Status',
  `rating` float DEFAULT 0 COMMENT 'Rating from 0.0 to 10.0',
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `poster_url` text DEFAULT NULL,
  `folder_name` varchar(50) DEFAULT 'Uncategorized',
  `remarks` varchar(255) DEFAULT '',
  `current_ep` int(11) DEFAULT 0,
  `total_eps` int(11) DEFAULT 0,
  `update_day` int(11) DEFAULT -1 COMMENT 'Day of week for updates (0-6)',
  `link` text DEFAULT NULL COMMENT 'External link to content',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Reset Auto-Increment to start fresh
ALTER TABLE `media_items` AUTO_INCREMENT = 1;

COMMIT;
