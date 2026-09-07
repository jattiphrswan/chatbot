# Database Shapes: Gemini Chat Assistant

## 1. Overview
Gemini Chat Assistant uses 3 dedicated tables prefixed with `{$wpdb->prefix}gca_` plus options stored in WordPress standard `wp_options` table.

---

## 2. Table Schemas

### 2.1 Conversations Table: `{$wpdb->prefix}gca_conversations`
Stores session metadata, user links (if logged in), IP hash, and conversation lifecycle info.

```sql
CREATE TABLE `{$wpdb->prefix}gca_conversations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` varchar(64) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT 0,
  `ip_hash` varchar(64) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `metadata` longtext DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_session_id` (`session_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

---

### 2.2 Messages Table: `{$wpdb->prefix}gca_messages`
Stores individual conversational messages (user prompts and AI responses).

```sql
CREATE TABLE `{$wpdb->prefix}gca_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `role` enum('user','model','system') NOT NULL,
  `content` longtext NOT NULL,
  `tokens` int(10) unsigned DEFAULT 0,
  `finish_reason` varchar(32) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conversation_id` (`conversation_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_gca_messages_conversation` FOREIGN KEY (`conversation_id`) 
    REFERENCES `{$wpdb->prefix}gca_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

---

### 2.3 Logs Table: `{$wpdb->prefix}gca_logs`
Stores audit trails, latency metrics, API response codes, and errors.

```sql
CREATE TABLE `{$wpdb->prefix}gca_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `level` varchar(20) NOT NULL DEFAULT 'info',
  `event` varchar(64) NOT NULL,
  `session_id` varchar(64) DEFAULT NULL,
  `message` text NOT NULL,
  `context_data` longtext DEFAULT NULL,
  `latency_ms` int(10) unsigned DEFAULT NULL,
  `http_status` smallint(5) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_level` (`level`),
  KEY `idx_event` (`event`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

---

## 3. WordPress Options Table Entries (`wp_options`)

| Option Name | Type | Description |
| :--- | :--- | :--- |
| `gca_settings` | `array` / `JSON` | Stores plugin configuration (model, max_tokens, temperature, system prompt, rate limits, UI themes). |
| `gca_api_key_encrypted` | `string` | AES-256-GCM encrypted Google Gemini API Key. |
| `gca_db_version` | `string` | Semantic database migration version (e.g. `1.0.0`). |
| `gca_encryption_salt` | `string` | Site-specific encryption salt derived from `wp-config.php` `AUTH_KEY` + `LOGGED_IN_KEY`. |
