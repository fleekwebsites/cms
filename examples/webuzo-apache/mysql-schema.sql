-- Optional: run in phpMyAdmin on Webuzo if you prefer creating tables yourself.
-- Set CMS_AUTHORS_TABLE / CMS_CATEGORIES_TABLE in cms-receiver-common.php to match.

CREATE TABLE IF NOT EXISTS cms_authors (
    id INT UNSIGNED NOT NULL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    credentials VARCHAR(255) NULL,
    bio TEXT NULL,
    idempotency_key VARCHAR(255) NULL,
    received_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cms_categories (
    id INT UNSIGNED NOT NULL PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    idempotency_key VARCHAR(255) NULL,
    received_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Articles published from the CMS reference author_id and site_category_id
-- using the same numeric ids stored above.
