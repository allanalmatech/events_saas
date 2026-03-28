<?php
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = 'Item Categories';
$moduleKey = 'categories';
$modulePermission = 'items.view';
$moduleDescription = 'Configure inventory category and subcategory hierarchy.';

$contentRenderer = function (): void {
    $tenantId = (int) current_tenant_id();
    $rows = [];
    if ($tenantId > 0 && ($mysqli = db_try())) {
        $stmt = $mysqli->prepare('SELECT c.id, c.category_name, c.description, p.category_name AS parent_name FROM item_categories c LEFT JOIN item_categories p ON p.id = c.parent_id WHERE c.tenant_id = ? ORDER BY c.id DESC');
        $stmt->bind_param('i', $tenantId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    ?>
    <section class="grid cols-2">
        <article class="card">
            <h3 style="margin-top:0;">Add Category</h3>
            <form method="post" action="<?php echo e(app_url('actions/save_category.php')); ?>">
                <?php echo csrf_input(); ?>
                <div class="field"><label>Category Name</label><input name="category_name" required></div>
                <div class="field"><label>Parent Category ID (optional)</label><input type="number" name="parent_id" value="0"></div>
                <div class="field"><label>Description</label><textarea name="description"></textarea></div>
                <button class="btn btn-primary" type="submit">Save Category</button>
            </form>
        </article>
        <article class="card">
            <h3 style="margin-top:0;">Category List</h3>
            <table class="table"><thead><tr><th>Name</th><th>Parent</th><th>Description</th></tr></thead><tbody>
            <?php foreach ($rows as $row): ?>
                <tr><td><?php echo e($row['category_name']); ?></td><td><?php echo e($row['parent_name']); ?></td><td><?php echo e($row['description']); ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="3" class="muted">No categories available.</td></tr><?php endif; ?>
            </tbody></table>
        </article>
    </section>
    <?php
};

include __DIR__ . '/../../templates/module_page.php';
