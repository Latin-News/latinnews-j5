<?php
// Example DB connection - update with your own credentials
$sourcePrefix = 'j3_';     // Prefix for Joomla 3 (K2) tables
$targetPrefix  = 'hlsai_'; // <-- your actual Joomla 5 table prefix

$source = new mysqli('localhost', 'root', '', 'latinnews_j3'); // K2 source
$target = new mysqli('mysql.gb.stackcp.com:62672', 'joomla-3530383570fe', '67b903230923', 'joomla-3530383570fe'); // Joomla 5 target

if ($source->connect_error || $target->connect_error) {
    die('Database connection failed: ' . ($source->connect_error ?: $target->connect_error));
}
?>
