<?php
require_once 'helpers/db.php';
require_once 'steps/categories.php';
require_once 'steps/fields.php';
require_once 'steps/items.php';
require_once 'steps/attachments.php';

$step = $_GET['step'] ?? 'all';
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

switch ($step) {
    case 'categories': migrateCategories(); break;
    case 'fields': migrateFields(); break;
    case 'items': migrateItems($offset); break;
    case 'attachments': migrateAttachments(); break;
    default:
        migrateCategories();
        migrateFields();
        migrateItems($offset);
        migrateAttachments();
}
?>
