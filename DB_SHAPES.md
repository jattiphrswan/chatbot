# Database Shapes: Gemini Chat Assistant

## 1. Overview
Gemini Chat Assistant uses 3 dedicated tables prefixed with `{$wpdb->prefix}gca_` plus options stored in WordPress standard `wp_options` table.

---

## 2. Table Schemas

### 2.1 Conversations Table: `{$wpdb->prefix}gca_conversations`
Stores session metadata, public UUID, user links, title, status, and message metrics.

```sql
CREATE TABLE `{$wpdb->prefix}gca_conversations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT 0,
  `session_hash` varchar(64) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `interaction_id` varchar(64) DEFAULT NULL,
  `message_count` int(10) unsigned DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_message_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_public_id` (`public_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_session_hash` (`session_hash`),
  KEY `idx_status` (`status`),
  KEY `idx_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

---

### 2.2 Messages Table: `{$wpdb->prefix}gca_messages`
Stores individual conversation messages (user, assistant, system) with token and latency metrics.

```sql
CREATE TABLE `{$wpdb->prefix}gca_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `role` varchar(20) NOT NULL,
  `content` longtext NOT NULL,
  `model` varchar(64) DEFAULT NULL,
  `input_tokens` int(10) unsigned DEFAULT 0,
  `output_tokens` int(10) unsigned DEFAULT 0,
  `latency_ms` int(10) unsigned DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conversation_id` (`conversation_id`),
  KEY `idx_created_at` (`created_at`)
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
| `gca_settings` | `array` | Stores plugin configuration (model, general toggles, limits, privacy, widget settings). |
| `gca_db_version` | `string` | Semantic database migration version (e.g. `1.0.0`). |

> **NOTE ON API CREDENTIALS:**
> The Gemini API key is **NOT** stored in `wp_options`. Credentials reside purely in server-side environment variables (`GEMINI_API_KEY`) or `wp-config.php` (`GCA_GEMINI_API_KEY`).
