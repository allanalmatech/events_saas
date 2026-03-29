<?php
declare(strict_types=1);

function ensure_marketplace_social_tables(): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $ensured = true;
    $mysqli = db();

    $commentsSql = 'CREATE TABLE IF NOT EXISTS marketplace_listing_comments (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        listing_id INT UNSIGNED NOT NULL,
        tenant_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        comment_text VARCHAR(1000) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_marketplace_comments_listing (listing_id, created_at),
        KEY idx_marketplace_comments_user (user_id, created_at),
        CONSTRAINT fk_marketplace_comments_listing FOREIGN KEY (listing_id) REFERENCES marketplace_catalogue(id) ON DELETE CASCADE,
        CONSTRAINT fk_marketplace_comments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
        CONSTRAINT fk_marketplace_comments_user FOREIGN KEY (user_id) REFERENCES tenant_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    $likesSql = 'CREATE TABLE IF NOT EXISTS marketplace_listing_likes (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        listing_id INT UNSIGNED NOT NULL,
        tenant_id INT UNSIGNED NOT NULL,
        user_id INT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_marketplace_listing_like (listing_id, user_id),
        KEY idx_marketplace_likes_listing (listing_id, created_at),
        KEY idx_marketplace_likes_user (user_id, created_at),
        CONSTRAINT fk_marketplace_likes_listing FOREIGN KEY (listing_id) REFERENCES marketplace_catalogue(id) ON DELETE CASCADE,
        CONSTRAINT fk_marketplace_likes_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
        CONSTRAINT fk_marketplace_likes_user FOREIGN KEY (user_id) REFERENCES tenant_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    $viewsSql = 'CREATE TABLE IF NOT EXISTS marketplace_listing_views (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        listing_id INT UNSIGNED NOT NULL,
        viewer_tenant_id INT UNSIGNED NOT NULL,
        viewer_user_id INT UNSIGNED NOT NULL,
        first_viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_marketplace_listing_viewer (listing_id, viewer_user_id),
        KEY idx_marketplace_views_listing (listing_id, last_viewed_at),
        KEY idx_marketplace_views_user (viewer_user_id, last_viewed_at),
        CONSTRAINT fk_marketplace_views_listing FOREIGN KEY (listing_id) REFERENCES marketplace_catalogue(id) ON DELETE CASCADE,
        CONSTRAINT fk_marketplace_views_tenant FOREIGN KEY (viewer_tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
        CONSTRAINT fk_marketplace_views_user FOREIGN KEY (viewer_user_id) REFERENCES tenant_users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    $imagesSql = 'CREATE TABLE IF NOT EXISTS marketplace_listing_images (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        listing_id INT UNSIGNED NOT NULL,
        tenant_id INT UNSIGNED NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_marketplace_images_listing (listing_id, sort_order),
        CONSTRAINT fk_marketplace_images_listing FOREIGN KEY (listing_id) REFERENCES marketplace_catalogue(id) ON DELETE CASCADE,
        CONSTRAINT fk_marketplace_images_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    try {
        $mysqli->query($commentsSql);
        $mysqli->query($likesSql);
        $mysqli->query($viewsSql);
        $mysqli->query($imagesSql);
    } catch (Throwable $exception) {
    }
}

function marketplace_whatsapp_phone(string $raw): string
{
    return preg_replace('/[^0-9]/', '', $raw) ?? '';
}
