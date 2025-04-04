<?php
function logMessage($msg) {
    $timestamp = date('Y-m-d H:i:s');
    $line = "[$timestamp] $msg\n";

    echo $line;
    file_put_contents(__DIR__ . '/../logs/k2_migration.log', $line, FILE_APPEND);
}
?>
