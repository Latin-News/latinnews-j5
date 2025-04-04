<?php

function migrateFields() {
    global $source, $target, $sourcePrefix, $targetPrefix;

    echo "<pre>";
    echo "Migrating field groups...\n";

    $groupMap = [];
    $fieldMap = [];

    // Step 1: Field groups
    $res = $source->query("SELECT * FROM {$sourcePrefix}k2_extra_fields_groups ORDER BY id ASC");
    while ($group = $res->fetch_assoc()) {
        $title = $target->real_escape_string($group['name']);
        $context = 'com_content.article';
        $created = date('Y-m-d H:i:s');

        $check = $target->query("SELECT id FROM {$targetPrefix}fields_groups WHERE title = '$title' AND context = '$context'");
        if ($check->num_rows > 0) {
            $existing = $check->fetch_assoc();
            $groupMap[$group['id']] = $existing['id'];
            echo "Group already exists: $title (ID {$existing['id']})\n";
            continue;
        }

        $insert = "
            INSERT INTO {$targetPrefix}fields_groups (title, context, description, state, language, access, created, created_by, params)
            VALUES ('$title', '$context', '', 1, '*', 1, '$created', 0, '')
        ";

        if ($target->query($insert)) {
            $newId = $target->insert_id;
            $groupMap[$group['id']] = $newId;
            echo "Inserted group: $title (ID $newId)\n";
        }
    }

    echo "Migrating extra fields...\n";

    // Step 2: Fields with options
    $res = $source->query("SELECT * FROM {$sourcePrefix}k2_extra_fields ORDER BY `group`, `ordering` ASC");
    while ($field = $res->fetch_assoc()) {
        $title = $target->real_escape_string($field['name']);
        $name = generateSafeFieldName($field['name']);
        $type = mapK2TypeToJoomlaType($field['type']);
        $groupId = isset($groupMap[$field['group']]) ? $groupMap[$field['group']] : 0;
        $ordering = (int)$field['ordering'];
        $state = (int)$field['published'];
        $created = date('Y-m-d H:i:s');
        $context = 'com_content.article';

        $params = '';
        $fieldparams = '';

        // Field options for list/radio/checkbox
        if (in_array($type, ['list', 'radio', 'checkboxes'])) {
            $options = explode("||", $field['value']);
            $optionList = [];
            foreach ($options as $opt) {
                $optionList[] = ['name' => trim($opt), 'value' => trim($opt)];
            }
            $fieldparams = json_encode([
                'multiple' => in_array($type, ['checkboxes', 'list']) && $field['type'] === 'multipleSelect' ? '1' : '0',
                'options' => $optionList,
                'first' => '0',
                'readonly' => '0'
            ]);
        }

        $check = $target->query("SELECT id FROM {$targetPrefix}fields WHERE name = '$name' AND context = '$context'");
        if ($check->num_rows > 0) {
            $existing = $check->fetch_assoc();
            $fieldMap[$field['id']] = $existing['id'];
            echo "Field already exists: $title (ID {$existing['id']})\n";
            continue;
        }

        $insert = "
            INSERT INTO {$targetPrefix}fields
            (title, name, label, type, context, group_id, state, ordering, access,
             language, created_time, created_user_id, params, fieldparams, description)
            VALUES
            ('$title', '$name', '$title', '$type', '$context', $groupId, $state, $ordering, 1,
             '*', '$created', 0, '$params', '" . $target->real_escape_string($fieldparams) . "', '')
        ";

        if ($target->query($insert)) {
            $newId = $target->insert_id;
            $fieldMap[$field['id']] = $newId;
            echo "Inserted field: $title (Type: $type, Group ID: $groupId)\n";
        } else {
            echo "Failed to insert field: $title — " . $target->error . "\n";
        }
    }

    // Step 3: Migrate values FROM k2_items.extra_fields
    echo "Migrating field values from K2 items...\n";
    $res = $source->query("SELECT id, extra_fields FROM {$sourcePrefix}k2_items WHERE extra_fields IS NOT NULL AND extra_fields != ''");

    while ($row = $res->fetch_assoc()) {
        $itemId = $row['id'];
        $json = json_decode($row['extra_fields'], true);
        if (!is_array($json)) continue;

        foreach ($json as $fieldObj) {
            $k2FieldId = $fieldObj['id'];
            $value = $target->real_escape_string($fieldObj['value']);

            if (!isset($fieldMap[$k2FieldId])) continue;

            $joomlaFieldId = $fieldMap[$k2FieldId];

            // Prevent duplication
            $check = $target->query("SELECT * FROM {$targetPrefix}fields_values WHERE field_id = $joomlaFieldId AND item_id = '$itemId'");
            if ($check->num_rows > 0) continue;

            $insert = "
                INSERT INTO {$targetPrefix}fields_values (field_id, item_id, value)
                VALUES ($joomlaFieldId, '$itemId', '$value')
            ";

            if ($target->query($insert)) {
                echo "Inserted field value for Item $itemId, Field $joomlaFieldId\n";
            } else {
                echo "Error inserting value for Item $itemId: " . $target->error . "\n";
            }
        }
    }

    echo "Field group, field, and value migration complete.\n";
    echo "</pre>";
}

function generateSafeFieldName($input) {
    $safe = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $input));
    return substr($safe, 0, 100);
}

function mapK2TypeToJoomlaType($k2type) {
    switch ($k2type) {
        case 'textfield': return 'text';
        case 'textarea': return 'textarea';
        case 'link': return 'url';
        case 'date': return 'calendar';
        case 'select': return 'list';
        case 'multipleSelect': return 'list';
        case 'radio': return 'radio';
        case 'checkbox': return 'checkboxes';
        case 'email': return 'email';
        case 'number': return 'integer';
        default: return 'text';
    }
}
?>
