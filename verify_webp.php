<?php
/**
 * Verify WebP Conversion
 * 
 * Script to verify if images are actually being converted
 */

// Include WordPress
require_once('../../../wp-config.php');

echo "=== WebP Conversion Verification ===\n\n";

// Check recent images (last 10)
global $wpdb;
$recent_images = $wpdb->get_results("
    SELECT ID, post_title, post_mime_type, post_date
    FROM {$wpdb->posts} 
    WHERE post_type = 'attachment' 
    AND post_mime_type IN ('image/jpeg', 'image/png')
    AND post_status = 'inherit'
    ORDER BY post_date DESC
    LIMIT 10
");

echo "Recent Images Analysis:\n";
echo "======================\n";

foreach ($recent_images as $image) {
    $file_path = get_attached_file($image->ID);
    $file_info = pathinfo($file_path);
    $webp_path = $file_info['dirname'] . '/' . $file_info['filename'] . '.webp';
    
    echo "ID: {$image->ID}\n";
    echo "Title: " . substr($image->post_title, 0, 50) . "...\n";
    echo "MIME: {$image->post_mime_type}\n";
    echo "Date: {$image->post_date}\n";
    echo "Original Path: {$file_path}\n";
    echo "Original Exists: " . (file_exists($file_path) ? 'YES' : 'NO') . "\n";
    echo "WebP Path: {$webp_path}\n";
    echo "WebP Exists: " . (file_exists($webp_path) ? 'YES' : 'NO') . "\n";
    
    if (file_exists($file_path)) {
        echo "Original Size: " . filesize($file_path) . " bytes\n";
    }
    if (file_exists($webp_path)) {
        echo "WebP Size: " . filesize($webp_path) . " bytes\n";
        if (file_exists($file_path)) {
            $savings = round((filesize($file_path) - filesize($webp_path)) / filesize($file_path) * 100, 1);
            echo "Savings: {$savings}%\n";
        }
    }
    echo "---\n";
}

// Count totals
$upload_dir = wp_upload_dir();
$upload_basedir = $upload_dir['basedir'];

echo "\nTotal Counts:\n";
echo "=============\n";

// Count JPEG files
$jpeg_count = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($upload_basedir, RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'jpg') {
        $jpeg_count++;
    }
}

// Count WebP files
$webp_count = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($upload_basedir, RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if ($file->isFile() && strtolower($file->getExtension()) === 'webp') {
        $webp_count++;
    }
}

echo "JPEG Files: {$jpeg_count}\n";
echo "WebP Files: {$webp_count}\n";
echo "Conversion Rate: " . ($jpeg_count > 0 ? round(($webp_count / $jpeg_count) * 100, 1) : 0) . "%\n";

echo "\n=== Verification Complete ===\n";

