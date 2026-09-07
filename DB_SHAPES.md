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

### 2.4 FAQs Table: `{$wpdb->prefix}gca_faqs` (Node N16)
Stores administrator-managed FAQs with category, active status, home display visibility, and sort order.

```sql
CREATE TABLE `{$wpdb->prefix}gca_faqs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `question` text NOT NULL,
  `answer` longtext NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `show_on_home` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_public_id` (`public_id`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_show_on_home` (`show_on_home`),
  KEY `idx_category` (`category`(100)),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

---

### 2.5 Knowledge Sources Table: `{$wpdb->prefix}gca_knowledge_sources` (Node N16)
Stores synchronized website knowledge sources (pages, posts, products, FAQs) and their content hashes.

```sql
CREATE TABLE `{$wpdb->prefix}gca_knowledge_sources` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` varchar(64) NOT NULL,
  `source_type` varchar(30) NOT NULL,
  `source_object_id` bigint(20) unsigned DEFAULT NULL,
  `source_public_id` varchar(64) DEFAULT NULL,
  `title` text NOT NULL,
  `url` text DEFAULT NULL,
  `content_hash` char(64) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'indexed',
  `indexed_at` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_public_id` (`public_id`),
  KEY `idx_source_type` (`source_type`),
  KEY `idx_source_object_id` (`source_object_id`),
  KEY `idx_source_public_id` (`source_public_id`),
  KEY `idx_content_hash` (`content_hash`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

---

### 2.6 Knowledge Chunks Table: `{$wpdb->prefix}gca_knowledge_chunks` (Node N16)
Stores normalized, bounded text chunks derived from knowledge sources for lexical candidate retrieval.

```sql
CREATE TABLE `{$wpdb->prefix}gca_knowledge_chunks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `source_id` bigint(20) unsigned NOT NULL,
  `chunk_index` int(10) unsigned NOT NULL,
  `content` longtext NOT NULL,
  `content_hash` char(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_source_id` (`source_id`),
  KEY `idx_chunk_index` (`chunk_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
```

---

## 3. WordPress Options Table Entries (`wp_options`)

| Option Name | Type | Description |
| :--- | :--- | :--- |
| `gca_settings` | `array` | Stores plugin configuration (model, active_profile_id, limits, privacy, widget settings, FAQ & RAG settings). |
| `gca_ai_profiles` | `array` | Stores up to 25 AI profiles keyed by UUID (`autoload = 'no'`). |
| `gca_db_version` | `string` | Semantic database migration version (e.g. `1.2.0`). |

### 3.1 AI Profile Data Shape (`gca_ai_profiles`)
Stored as an associative array keyed by Profile UUID:

```php
[
    'profile-uuid' => [
        'id'               => 'string (UUID v4)',
        'name'             => 'string (max 100)',
        'description'      => 'string (max 1000, admin metadata)',
        'role'             => 'string (max 2000, assistant role/persona)',
        'tone'             => 'string (professional|friendly|concise|helpful|conversational|formal)',
        'system_prompt'    => 'string (max 15000, instructions)',
        'rules'            => 'string (max 10000, behavioral rules)',
        'response_style'   => 'string (concise|balanced|detailed)',
        'fallback_message' => 'string (max 2000, fallback instruction)',
        'enabled'          => 'bool',
        'created_at'       => 'datetime (Y-m-d H:i:s)',
        'updated_at'       => 'datetime (Y-m-d H:i:s)',
    ],
]
```

> **NOTE ON API CREDENTIALS:**
> The Google Gemini API key is **NEVER** stored in any database table or option. It is resolved strictly from the environment variable (`GEMINI_API_KEY`) or the `GCA_GEMINI_API_KEY` constant in `wp-config.php`.

> The Gemini API key is **NOT** stored in `wp_options`. Credentials reside purely in server-side environment variables (`GEMINI_API_KEY`) or `wp-config.php` (`GCA_GEMINI_API_KEY`).
