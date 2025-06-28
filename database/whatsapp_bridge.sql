-- ================================================
-- DATABASE STRUCTURE UNTUK WHATSAPP API BRIDGE
-- ================================================

CREATE DATABASE IF NOT EXISTS `whatsapp_bridge` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `whatsapp_bridge`;

-- ================================================
-- 1. TABEL USERS (Login System)
-- ================================================
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- 2. TABEL DEVICES (Device Management dengan Token)
-- ================================================
CREATE TABLE `devices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_name` varchar(100) NOT NULL,
  `session_id` varchar(100) NOT NULL,
  `device_token` varchar(255) NOT NULL,
  `api_key` varchar(255) NOT NULL,
  `webhook_url` varchar(500) NULL,
  `note` text NULL,
  `status` enum('active','inactive','connecting','connected','disconnected','banned') DEFAULT 'inactive',
  `phone_number` varchar(20) NULL,
  `qr_code` text NULL,
  `last_activity` timestamp NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_id` (`session_id`),
  UNIQUE KEY `device_token` (`device_token`),
  UNIQUE KEY `api_key` (`api_key`),
  KEY `created_by` (`created_by`),
  FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- 3. TABEL MESSAGES (Pesan Masuk & Keluar)
-- ================================================
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` int(11) NOT NULL,
  `message_id` varchar(100) NOT NULL,
  `session_id` varchar(100) NOT NULL,
  `direction` enum('incoming','outgoing') NOT NULL,
  `message_type` enum('text','image','document','audio','video','contact','location','reaction','button','list') NOT NULL,
  `from_number` varchar(50) NOT NULL,
  `to_number` varchar(50) NOT NULL,
  `group_id` varchar(100) NULL,
  `message_content` text NOT NULL,
  `media_url` varchar(500) NULL,
  `caption` text NULL,
  `quoted_message_id` varchar(100) NULL,
  `status` enum('pending','sent','delivered','read','failed') DEFAULT 'pending',
  `webhook_status` enum('pending','sent','failed') DEFAULT 'pending',
  `webhook_attempts` int(11) DEFAULT 0,
  `webhook_response` text NULL,
  `received_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` timestamp NULL,
  `delivered_at` timestamp NULL,
  `read_at` timestamp NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_message` (`device_id`, `message_id`),
  KEY `device_id` (`device_id`),
  KEY `direction` (`direction`),
  KEY `status` (`status`),
  KEY `webhook_status` (`webhook_status`),
  KEY `received_at` (`received_at`),
  FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- 4. TABEL CONTACTS (Kontak)
-- ================================================
CREATE TABLE `contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` int(11) NOT NULL,
  `phone_number` varchar(50) NOT NULL,
  `display_name` varchar(100) NULL,
  `profile_name` varchar(100) NULL,
  `profile_picture` varchar(500) NULL,
  `is_blocked` tinyint(1) DEFAULT 0,
  `is_business` tinyint(1) DEFAULT 0,
  `last_seen` timestamp NULL,
  `status_message` text NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_contact` (`device_id`, `phone_number`),
  KEY `device_id` (`device_id`),
  FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- 5. TABEL GROUPS (Grup WhatsApp)
-- ================================================
CREATE TABLE `groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` int(11) NOT NULL,
  `group_id` varchar(100) NOT NULL,
  `group_name` varchar(200) NOT NULL,
  `group_description` text NULL,
  `group_picture` varchar(500) NULL,
  `owner_number` varchar(50) NULL,
  `participant_count` int(11) DEFAULT 0,
  `is_admin` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_group` (`device_id`, `group_id`),
  KEY `device_id` (`device_id`),
  FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- 6. TABEL WEBHOOKS (Webhook Configuration)
-- ================================================
CREATE TABLE `webhooks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` int(11) NOT NULL,
  `webhook_url` varchar(500) NOT NULL,
  `events` text NOT NULL COMMENT 'JSON array of subscribed events',
  `secret_token` varchar(255) NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `retry_count` int(11) DEFAULT 3,
  `timeout` int(11) DEFAULT 30,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `device_id` (`device_id`),
  FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- 7. TABEL WEBHOOK_LOGS (Log Webhook Calls)
-- ================================================
CREATE TABLE `webhook_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` int(11) NOT NULL,
  `webhook_id` int(11) NOT NULL DEFAULT 1,
  `event_type` varchar(50) NOT NULL,
  `payload` text NOT NULL,
  `response_code` int(11) NULL,
  `response_body` text NULL,
  `execution_time` decimal(10,3) NULL,
  `status` enum('success','failed','timeout') NOT NULL,
  `error_message` text NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `device_id` (`device_id`),
  KEY `webhook_id` (`webhook_id`),
  KEY `event_type` (`event_type`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- 8. TABEL API_LOGS (Log API Calls)
-- ================================================
CREATE TABLE `api_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` int(11) NULL,
  `api_key` varchar(255) NULL,
  `endpoint` varchar(200) NOT NULL,
  `method` enum('GET','POST','PUT','DELETE') NOT NULL,
  `request_data` text NULL,
  `response_data` text NULL,
  `response_code` int(11) NOT NULL,
  `execution_time` decimal(10,3) NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `device_id` (`device_id`),
  KEY `endpoint` (`endpoint`),
  KEY `response_code` (`response_code`),
  KEY `created_at` (`created_at`),
  FOREIGN KEY (`device_id`) REFERENCES `devices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- 9. TABEL SETTINGS (Pengaturan Sistem)
-- ================================================
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `description` text NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================
-- INSERT DATA DEFAULT
-- ================================================

-- Insert default user (admin)
INSERT INTO `users` (`username`, `password`, `full_name`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator');
-- Password: password

-- Insert default settings
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('node_api_url', 'http://localhost:3000/api', 'URL Node.js WhatsApp API'),
('default_webhook_timeout', '30', 'Default webhook timeout in seconds'),
('max_retry_webhook', '3', 'Maximum webhook retry attempts'),
('api_rate_limit', '100', 'API rate limit per minute'),
('session_timeout', '3600', 'Session timeout in seconds'),
('enable_logging', '1', 'Enable API logging (1=yes, 0=no)'),
('auto_read_messages', '0', 'Auto read incoming messages (1=yes, 0=no)');

-- ================================================
-- INDEXES UNTUK PERFORMA
-- ================================================

-- Indexes untuk pencarian pesan
CREATE INDEX `idx_messages_search` ON `messages` (`device_id`, `direction`, `received_at`);
CREATE INDEX `idx_messages_phone` ON `messages` (`from_number`, `to_number`);
CREATE INDEX `idx_messages_content` ON `messages` (`message_content`(100));

-- Indexes untuk webhook logs
CREATE INDEX `idx_webhook_logs_date` ON `webhook_logs` (`created_at`, `status`);

-- Indexes untuk API logs
CREATE INDEX `idx_api_logs_date` ON `api_logs` (`created_at`, `response_code`);

-- ================================================
-- VIEWS UNTUK LAPORAN
-- ================================================

-- View untuk statistik pesan per device
CREATE VIEW `v_message_stats` AS
SELECT 
    d.id as device_id,
    d.device_name,
    d.session_id,
    COUNT(m.id) as total_messages,
    SUM(CASE WHEN m.direction = 'incoming' THEN 1 ELSE 0 END) as incoming_count,
    SUM(CASE WHEN m.direction = 'outgoing' THEN 1 ELSE 0 END) as outgoing_count,
    MAX(m.received_at) as last_message_at
FROM devices d
LEFT JOIN messages m ON d.id = m.device_id
GROUP BY d.id, d.device_name, d.session_id;

-- View untuk status webhook
CREATE VIEW `v_webhook_stats` AS
SELECT 
    d.id as device_id,
    d.device_name,
    d.webhook_url,
    COUNT(wl.id) as total_calls,
    SUM(CASE WHEN wl.status = 'success' THEN 1 ELSE 0 END) as success_count,
    SUM(CASE WHEN wl.status = 'failed' THEN 1 ELSE 0 END) as failed_count,
    AVG(wl.execution_time) as avg_execution_time
FROM devices d
LEFT JOIN webhook_logs wl ON d.id = wl.device_id
GROUP BY d.id, d.device_name, d.webhook_url;

-- ================================================
-- TRIGGERS UNTUK AUDIT
-- ================================================

-- Trigger untuk update last_activity pada devices
DELIMITER $$
CREATE TRIGGER `update_device_activity` 
AFTER INSERT ON `messages` 
FOR EACH ROW 
BEGIN
    UPDATE devices 
    SET last_activity = CURRENT_TIMESTAMP 
    WHERE id = NEW.device_id;
END$$
DELIMITER ;

-- ================================================
-- STORED PROCEDURES
-- ================================================

-- Procedure untuk cleanup old data
DELIMITER $$
CREATE PROCEDURE `CleanupOldData`(IN `days_to_keep` INT)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Hapus webhook logs lama
    DELETE FROM webhook_logs 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    
    -- Hapus API logs lama  
    DELETE FROM api_logs 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);
    
    -- Hapus pesan lama (kecuali yang penting)
    DELETE FROM messages 
    WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY)
    AND status IN ('delivered', 'read');
    
    COMMIT;
END$$
DELIMITER ;

-- ================================================
-- GRANT PERMISSIONS (untuk user terpisah)
-- ================================================

-- CREATE USER 'whatsapp_bridge'@'localhost' IDENTIFIED BY 'strong_password_here';
-- GRANT ALL PRIVILEGES ON whatsapp_bridge.* TO 'whatsapp_bridge'@'localhost';
-- FLUSH PRIVILEGES;