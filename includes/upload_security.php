<?php
declare(strict_types=1);

/**
 * Stage 7.4: secure report image upload handler.
 *
 * Validates genuine image files (getimagesize + finfo MIME), enforces
 * size and dimension limits, generates random filenames, and stores
 * only the generated filename (not a full path) for the database.
 */

function storeSecureReportImage(array $file): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(
            'The image could not be uploaded. Please try again.'
        );
    }

    if (!isset($file['size']) || (int) $file['size'] > MAX_UPLOAD_SIZE) {
        throw new RuntimeException('The image must not exceed 5 MB.');
    }

    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid image upload.');
    }

    $imageInfo = @getimagesize($file['tmp_name']);

    if ($imageInfo === false) {
        throw new RuntimeException(
            'The uploaded file is not a valid image.'
        );
    }

    $finfo   = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedTypes[$mimeType]) || !in_array($mimeType, ALLOWED_IMAGE_TYPES, true)) {
        throw new RuntimeException(
            'Only JPG, PNG and WebP images are accepted.'
        );
    }

    $width  = (int) ($imageInfo[0] ?? 0);
    $height = (int) ($imageInfo[1] ?? 0);

    if ($width < 1 || $height < 1 || $width > 8000 || $height > 8000) {
        throw new RuntimeException(
            'The image dimensions are not supported.'
        );
    }

    $directory = UPLOAD_PATH . '/reports';

    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException(
            'The image storage directory is unavailable.'
        );
    }

    $filename    = bin2hex(random_bytes(20)) . '.' . $allowedTypes[$mimeType];
    $destination = $directory . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('The image could not be saved.');
    }

    return $filename;
}