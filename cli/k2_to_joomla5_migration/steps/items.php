<?php
function migrateItems($offset = 0, $batchSize = 100) {
    global $source, $target, $sourcePrefix, $targetPrefix;

    echo "<pre>";
    echo "Starting K2 item migration from offset $offset...\n";

    // Step 1: Map categories
    $catMap = [];
    $res = $target->query("SELECT id, alias FROM {$targetPrefix}categories WHERE extension = 'com_content'");
    while ($row = $res->fetch_assoc()) {
        $catMap[$row['alias']] = $row['id'];
    }

    // Step 2: Map tags
    $tagMap = [];
    $res = $target->query("SELECT id, title FROM {$targetPrefix}tags");
    while ($row = $res->fetch_assoc()) {
        $tagMap[strtolower(trim($row['title']))] = $row['id'];
    }

    // Step 3: Get existing items
    $existingIds = [];
    $res = $target->query("SELECT id FROM {$targetPrefix}content");
    while ($row = $res->fetch_assoc()) {
        $existingIds[] = (int)$row['id'];
    }

    // Step 4: Fetch K2 items
    $res = $source->query("
        SELECT * FROM {$sourcePrefix}k2_items
        ORDER BY id
        LIMIT $batchSize OFFSET $offset
    ");

    $itemCount = 0;

    while ($item = $res->fetch_assoc()) {
        $id = (int)$item['id'];
        if (in_array($id, $existingIds)) {
            echo "Skipping existing article ID $id\n";
            continue;
        }

        $title = $target->real_escape_string($item['title']);
        $alias = $target->real_escape_string($item['alias'] ?: strtolower(preg_replace('/[^a-z0-9]+/', '-', $title)));
        $introtext = $target->real_escape_string($item['introtext']);
        $fulltext = $target->real_escape_string($item['fulltext']);
        $catid = (int)$item['catid'];
        $state = (int)$item['published'];
        $created = $item['created'];
        $created_by = (int)$item['created_by'];
        $created_by_alias = $target->real_escape_string($item['created_by_alias']);
        $modified = $item['modified'];
        $modified_by = (int)$item['modified_by'];
        $publish_up = $item['publish_up'];
        $publish_down = $item['publish_down'];
        $access = (int)$item['access'];
        $hits = (int)$item['hits'];
        $language = $target->real_escape_string($item['language']);
        $metakey = $target->real_escape_string($item['metakey']);
        $metadesc = $target->real_escape_string($item['metadesc']);
        $metadata = $target->real_escape_string($item['metadata']);

        $images = json_encode([
            'image_intro' => '',
            'float_intro' => '',
            'image_fulltext' => '',
            'float_fulltext' => '',
            'image_intro_alt' => '',
            'image_intro_caption' => $item['image_caption'],
            'image_fulltext_alt' => '',
            'image_fulltext_caption' => $item['image_caption']
        ]);

        $attribs = '';
        $urls = '';
        $version = 1;
        $note = '';
        $xreference = '';

        // Insert into Joomla content
        $insert = "
            INSERT INTO {$targetPrefix}content (
                id, asset_id, title, alias, introtext, fulltext, state, catid, created, created_by,
                created_by_alias, modified, modified_by, publish_up, publish_down,
                images, urls, attribs, version, ordering, metakey, metadesc, access, hits,
                metadata, featured, language, xreference, note
            ) VALUES (
                $id, 0, '$title', '$alias', '$introtext', '$fulltext', $state, $catid,
                '$created', $created_by, '$created_by_alias', '$modified', $modified_by,
                '$publish_up', '$publish_down',
                '$images', '$urls', '$attribs', $version, 0, '$metakey', '$metadesc',
                $access, $hits, '$metadata', 0, '$language', '$xreference', '$note'
            )
        ";

        if ($target->query($insert)) {
            echo "Inserted article: [$id] $title\n";
        } else {
            echo "Failed to insert article [$id]: " . $target->error . "\n";
            continue;
        }

        // Step 5: Insert tags
        $xrefRes = $source->query("SELECT tagID FROM {$sourcePrefix}k2_tags_xref WHERE itemID = $id");
        while ($xref = $xrefRes->fetch_assoc()) {
            $k2TagId = (int)$xref['tagID'];

            $tagRes = $source->query("SELECT name FROM {$sourcePrefix}k2_tags WHERE id = $k2TagId AND published = 1");
            if ($tagRes->num_rows === 0) continue;

            $tagName = strtolower(trim($tagRes->fetch_assoc()['name']));
            if (!isset($tagMap[$tagName])) {
                $tagInsert = "
                    INSERT INTO {$targetPrefix}tags (title, alias, published, access, extension, language)
                    VALUES ('$tagName', '$tagName', 1, 1, 'com_content.article', '*')
                ";
                if ($target->query($tagInsert)) {
                    $tagMap[$tagName] = $target->insert_id;
                    echo "Inserted new tag: $tagName\n";
                } else {
                    echo "Failed to insert tag: $tagName — " . $target->error . "\n";
                    continue;
                }
            }

            $tagId = $tagMap[$tagName];
            $typeAlias = 'com_content.article';
            $coreContentId = 0;
            $typeId = 1;

            $tagMapInsert = "
                INSERT IGNORE INTO {$targetPrefix}contentitem_tag_map
                (type_alias, core_content_id, content_item_id, tag_id, tag_date, type_id)
                VALUES ('$typeAlias', $coreContentId, $id, $tagId, NOW(), $typeId)
            ";

            if ($target->query($tagMapInsert)) {
                echo "Mapped tag $tagId to article $id\n";
            } else {
                echo "Tag mapping failed: " . $target->error . "\n";
            }
        }

        $itemCount++;
    }

    echo "Finished migrating $itemCount items in this batch.\n";
    echo "</pre>";
}
?>
