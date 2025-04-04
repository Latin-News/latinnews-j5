<?php
function migrateCategories($batchSize = 1000) {
    global $source, $target, $sourcePrefix, $targetPrefix;

    echo "<pre>";
    echo "Starting K2 category migration with nested paths...\n";

    $k2Categories = [];
    $res = $source->query("SELECT * FROM {$sourcePrefix}k2_categories ORDER BY parent, id ASC");
    while ($row = $res->fetch_assoc()) {
        $k2Categories[$row['id']] = $row;
    }

    $idMap = [];

    foreach ($k2Categories as $k2id => $cat) {
        $title = $target->real_escape_string($cat['name']);
        $alias = $target->real_escape_string($cat['alias']);
        $description = $target->real_escape_string($cat['description']);
        $access = (int)$cat['access'];
        $published = (int)$cat['published'];
        $language = $target->real_escape_string($cat['language']);
        $created_time = date('Y-m-d H:i:s');

        $check = $target->query("SELECT id FROM {$targetPrefix}categories WHERE alias = '$alias' AND extension = 'com_content'");
        if ($check->num_rows > 0) {
            $existing = $check->fetch_assoc();
            $idMap[$k2id] = $existing['id'];
            echo "Category already exists: [$existing[id]] $title\n";
            continue;
        }

        $insert = "
            INSERT INTO {$targetPrefix}categories (
                parent_id, title, alias, path, extension, description, access,
                published, language, created_time, created_user_id, modified_time,
                modified_user_id, hits, version, params, metadesc, metakey, metadata
            ) VALUES (
                0, '$title', '$alias', '', 'com_content', '$description',
                $access, $published, '$language', '$created_time', 0, '$created_time',
                0, 0, 1, '', '', '', ''
            )
        ";

        if ($target->query($insert)) {
            $newId = $target->insert_id;
            $idMap[$k2id] = $newId;
            echo "Inserted: [$newId] $title\n";
        } else {
            echo "Error inserting [$k2id] $title: " . $target->error . "\n";
        }
    }

    foreach ($k2Categories as $k2id => $cat) {
        $joomlaId = $idMap[$k2id] ?? null;
        if (!$joomlaId) continue;

        $parentK2Id = (int)$cat['parent'];
        $alias = $target->real_escape_string($cat['alias']);
        $parentId = $idMap[$parentK2Id] ?? 0;
        $path = buildCategoryPath($cat, $k2Categories);

        $update = "
            UPDATE {$targetPrefix}categories
            SET parent_id = $parentId, path = '$path'
            WHERE id = $joomlaId
        ";

        if ($target->query($update)) {
            echo "Updated path for category ID $joomlaId: $path\n";
        }
    }

    echo "Category migration complete.\n";
    echo "</pre>";
}

function buildCategoryPath($category, $allCategories) {
    $path = [$category['alias']];
    $parentId = (int)$category['parent'];

    while ($parentId > 0 && isset($allCategories[$parentId])) {
        $parent = $allCategories[$parentId];
        array_unshift($path, $parent['alias']);
        $parentId = (int)$parent['parent'];
    }

    return implode('/', $path);
}
?>
