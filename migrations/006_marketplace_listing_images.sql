CREATE TABLE IF NOT EXISTS marketplace_listing_images (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_marketplace_images_listing (listing_id, sort_order),
    CONSTRAINT fk_marketplace_images_listing FOREIGN KEY (listing_id) REFERENCES marketplace_catalogue(id) ON DELETE CASCADE,
    CONSTRAINT fk_marketplace_images_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
