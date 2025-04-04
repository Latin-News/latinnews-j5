<?php
function migrateAttachments() {
    global $source, $target, $sourcePrefix, $targetPrefix;

    echo "<pre>";
    echo "Starting attachment migration...\n";

    // Step 1: Ensure the attachment field exists
    $fieldName = 'attachments';
    $context = 'com_content.article';

    $res = $target->query("SELECT id FROM {$targetPrefix}fields WHERE name = '$fieldName' AND context = '$context'");
    if ($res->num_rows === 0) {
        echo "Creating new Joomla field for attachments...\n";

        $created = date('Y-m-d H:i:s');
        $target->query("
            INSERT INTO {$targetPrefix}fields
            (title, name, label, type, context, group_id, state, ordering, access,
             language, created_time, created_user_id, params, fieldparams, description)
            VALUES
            ('Attachments', '$fieldName', 'Attachments', 'media', '$context', 0, 1, 0, 1,
             '*', '$created', 0, '', '', 'Migrated from K2')
        ");
        $fieldId = $target->insert_id;
    } else {
        $fieldId = $res->fetch_assoc()['id'];
    }

    // Step 2: Migrate attachments
    $res = $source->query("SELECT * FROM {$sourcePrefix}k2_attachments ORDER BY itemID ASC");
    $count = 0;

    while ($attachment = $res->fetch_assoc()) {
        $itemId = (int)$attachment['itemID'];
        $filename = $target->real_escape_string($attachment['filename']);
        $filepath = 'attachments/' . $filename;

        $check = $target->query("
            SELECT * FROM {$targetPrefix}fields_values
            WHERE field_id = $fieldId AND item_id = '$itemId'
        ");

        if ($check->num_rows === 0) {
            $target->query("
                INSERT INTO {$targetPrefix}fields_values (field_id, item_id, value)
                VALUES ($fieldId, '$itemId', '$filepath')
            ");
            echo "Mapped attachment to article $itemId: $filepath\n";
            $count++;
        }
    }

    echo "Finished migrating $count attachments.\n";
    echo "</pre>";
}
?>
