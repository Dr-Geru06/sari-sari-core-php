<?php

function save_product_image(?array $upload): ?string
{
    if ($upload === null) {
        return null;
    }

    $error = filter_var($upload['error'] ?? null, FILTER_VALIDATE_INT);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('The image upload failed. Choose an image under 5 MB and try again.');
    }

    $temporaryPath = $upload['tmp_name'] ?? null;
    if (!is_string($temporaryPath) || !is_uploaded_file($temporaryPath)) {
        throw new InvalidArgumentException('Choose a valid product image and try again.');
    }

    $fileSize = filesize($temporaryPath);
    if ($fileSize === false || $fileSize < 1 || $fileSize > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Product images must be smaller than 5 MB.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!is_string($mime) || !isset($extensions[$mime])) {
        throw new InvalidArgumentException('Use a JPG, PNG, or WebP image.');
    }

    $imageInfo = @getimagesize($temporaryPath);
    if (!is_array($imageInfo) || ($imageInfo['mime'] ?? null) !== $mime) {
        throw new InvalidArgumentException('The uploaded file is not a valid image.');
    }

    $width = (int) $imageInfo[0];
    $height = (int) $imageInfo[1];
    if ($width < 1 || $height < 1 || $width > 6000 || $height > 6000 || $width * $height > 20000000) {
        throw new InvalidArgumentException('Images must be at most 6000 pixels per side and 20 megapixels.');
    }

    $uploadDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('Unable to create the product image folder.');
    }

    $fileName = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($temporaryPath, $uploadDirectory . DIRECTORY_SEPARATOR . $fileName)) {
        throw new RuntimeException('Unable to save the uploaded product image.');
    }

    return 'uploads/products/' . $fileName;
}

function remove_product_image(?string $imagePath): void
{
    if ($imagePath === null || !preg_match('#\Auploads/products/[a-f0-9]{32}\.(?:jpg|png|webp)\z#', $imagePath)) {
        return;
    }

    $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR . basename($imagePath);
    if (is_file($filePath) && !unlink($filePath)) {
        error_log('Could not remove product image: ' . $filePath);
    }
}
