<?php
$zip = new ZipArchive;
$zipFile = __DIR__ . '/tinymce.zip';
$extractTo = __DIR__ . '/tinymce';

if (!file_exists($zipFile)) {
    die("tinymce.zip not found\n");
}

if ($zip->open($zipFile) === TRUE) {
    if (!is_dir($extractTo)) mkdir($extractTo, 0755, true);
    $zip->extractTo($extractTo);
    $zip->close();
    echo "Extracted " . $zip->numFiles . " files to tinymce/\n";
    // Remove zip after extraction
    unlink($zipFile);
    echo "Deleted tinymce.zip\n";
    // Remove this script too
    unlink(__FILE__);
    echo "Done! Script self-deleted.\n";
} else {
    echo "Failed to open zip\n";
}
