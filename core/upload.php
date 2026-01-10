<?php
/**
 * CRC Upload Handler
 * Secure file upload validation and processing
 */

// Prevent direct access
if (!defined('CRC_LOADED')) {
    die('Direct access not permitted');
}

class Upload {
    private array $file;
    private array $errors = [];
    private ?string $savedPath = null;

    /**
     * Create upload handler for file
     */
    public function __construct(array $file) {
        $this->file = $file;
    }

    /**
     * Get uploaded file from request
     */
    public static function file(string $name): ?self {
        if (!isset($_FILES[$name]) || $_FILES[$name]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return new self($_FILES[$name]);
    }

    /**
     * Check if file has upload error
     */
    public function hasError(): bool {
        return $this->file['error'] !== UPLOAD_ERR_OK;
    }

    /**
     * Get upload error message
     */
    public function getUploadError(): ?string {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server limit',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form limit',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by extension'
        ];

        return $errors[$this->file['error']] ?? null;
    }

    /**
     * Validate file is image
     */
    public function validateImage(): self {
        if ($this->hasError()) {
            $this->errors[] = $this->getUploadError();
            return $this;
        }

        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $this->file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
            $this->errors[] = 'Invalid image type. Allowed: JPG, PNG, GIF, WebP';
        }

        // Check actual image
        $imageInfo = @getimagesize($this->file['tmp_name']);
        if (!$imageInfo) {
            $this->errors[] = 'File is not a valid image';
        }

        return $this;
    }

    /**
     * Validate file is PDF
     */
    public function validatePdf(): self {
        if ($this->hasError()) {
            $this->errors[] = $this->getUploadError();
            return $this;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $this->file['tmp_name']);
        finfo_close($finfo);

        if ($mimeType !== 'application/pdf') {
            $this->errors[] = 'File must be a PDF';
        }

        return $this;
    }

    /**
     * Validate file size
     */
    public function validateSize(int $maxBytes = null): self {
        $maxBytes = $maxBytes ?? UPLOAD_MAX_SIZE;

        if ($this->file['size'] > $maxBytes) {
            $maxMb = round($maxBytes / 1024 / 1024, 1);
            $this->errors[] = "File too large. Maximum size: {$maxMb}MB";
        }

        return $this;
    }

    /**
     * Validate specific MIME types
     */
    public function validateMimeTypes(array $allowed): self {
        if ($this->hasError()) {
            $this->errors[] = $this->getUploadError();
            return $this;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $this->file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowed)) {
            $this->errors[] = 'Invalid file type';
        }

        return $this;
    }

    /**
     * Check if validation passed
     */
    public function isValid(): bool {
        return empty($this->errors);
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array {
        return $this->errors;
    }

    /**
     * Save file to destination
     */
    public function save(string $directory, ?string $filename = null): ?string {
        if (!$this->isValid()) {
            return null;
        }

        // Ensure directory exists
        $fullDir = UPLOAD_PATH . '/' . trim($directory, '/');
        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }

        // Generate unique filename if not provided
        if (!$filename) {
            $ext = pathinfo($this->file['name'], PATHINFO_EXTENSION);
            $filename = bin2hex(random_bytes(16)) . '.' . strtolower($ext);
        }

        $destination = $fullDir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($this->file['tmp_name'], $destination)) {
            $this->errors[] = 'Failed to save file';
            return null;
        }

        // Return relative path for storage
        $this->savedPath = trim($directory, '/') . '/' . $filename;

        Logger::info('File uploaded', [
            'path' => $this->savedPath,
            'size' => $this->file['size'],
            'original' => $this->file['name']
        ]);

        return $this->savedPath;
    }

    /**
     * Save image with resize
     */
    public function saveImage(string $directory, int $maxWidth = 1920, int $maxHeight = 1080): ?string {
        if (!$this->isValid()) {
            return null;
        }

        // Get image info
        $imageInfo = getimagesize($this->file['tmp_name']);
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $type = $imageInfo[2];

        // Load image based on type
        switch ($type) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($this->file['tmp_name']);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($this->file['tmp_name']);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($this->file['tmp_name']);
                break;
            case IMAGETYPE_WEBP:
                $source = imagecreatefromwebp($this->file['tmp_name']);
                break;
            default:
                $this->errors[] = 'Unsupported image type';
                return null;
        }

        // Calculate new dimensions
        $ratio = min($maxWidth / $width, $maxHeight / $height, 1);
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);

        // Resize if needed
        if ($ratio < 1) {
            $resized = imagecreatetruecolor($newWidth, $newHeight);

            // Preserve transparency for PNG
            if ($type === IMAGETYPE_PNG) {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
            }

            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($source);
            $source = $resized;
        }

        // Ensure directory exists
        $fullDir = UPLOAD_PATH . '/' . trim($directory, '/');
        if (!is_dir($fullDir)) {
            mkdir($fullDir, 0755, true);
        }

        // Generate filename
        $filename = bin2hex(random_bytes(16)) . '.jpg';
        $destination = $fullDir . '/' . $filename;

        // Save as JPEG (good compression)
        imagejpeg($source, $destination, 85);
        imagedestroy($source);

        $this->savedPath = trim($directory, '/') . '/' . $filename;

        Logger::info('Image uploaded and processed', [
            'path' => $this->savedPath,
            'dimensions' => "{$newWidth}x{$newHeight}"
        ]);

        return $this->savedPath;
    }

    /**
     * Get original filename
     */
    public function getOriginalName(): string {
        return $this->file['name'];
    }

    /**
     * Get file size
     */
    public function getSize(): int {
        return $this->file['size'];
    }

    /**
     * Get MIME type
     */
    public function getMimeType(): string {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $this->file['tmp_name']);
        finfo_close($finfo);
        return $mimeType;
    }

    /**
     * Delete uploaded file
     */
    public static function delete(string $path): bool {
        $fullPath = UPLOAD_PATH . '/' . ltrim($path, '/');

        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }

        return false;
    }

    /**
     * Get URL for uploaded file
     */
    public static function url(string $path): string {
        return APP_URL . '/uploads/' . ltrim($path, '/');
    }
}
