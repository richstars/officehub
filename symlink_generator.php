<?php
$target = __DIR__ . '/storage/app/public';
$link = __DIR__ . '/public/storage';

if (file_exists($link)) {
    echo "Symlink already exists (or a file/directory with that name exists).";
} else {
    try {
        symlink($target, $link);
        echo "Symlink created successfully: $link -> $target";
    } catch (Exception $e) {
        echo "Error creating symlink: " . $e->getMessage();
    }
}
?>
