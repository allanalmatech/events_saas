<?php
require_once __DIR__ . '/_bootstrap.php';

action_require_post();
require_tenant_user();
require_permission('items.create');

$tenantId = (int) current_tenant_id();
$name = post_str('category_name');
$parentId = (int) post_str('parent_id', '0');
$description = post_str('description');

if ($name === '') {
    flash('error', 'Category name is required.');
    action_redirect_back('modules/categories/index.php');
}

$mysqli = db();
$parent = $parentId > 0 ? $parentId : null;
$stmt = $mysqli->prepare('INSERT INTO item_categories (tenant_id, parent_id, category_name, description, created_at) VALUES (?, ?, ?, ?, NOW())');
$stmt->bind_param('iiss', $tenantId, $parent, $name, $description);
$stmt->execute();
$id = (int) $stmt->insert_id;
$stmt->close();

audit_log('categories', 'create', 'item_categories', $id, null, ['category_name' => $name]);
flash('success', 'Category created.');
action_redirect_back('modules/categories/index.php');
