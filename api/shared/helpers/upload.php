<?php declare(strict_types=1);
require_once __DIR__ . '/../core/repositories/UploadRepository.php';
// htdocs/api/helpers/upload.php
// ملف دوال رفع الملفات (File Upload Helper)
// يدعم الصور، المستندات، والتحقق من الأمان
// تم التعديل لدعم تخزين معلومات الملفات في قاعدة البيانات عبر PDO

// ===========================================
// تحميل الملفات المطلوبة
// ===========================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';

// ===========================================
// Upload Class
// ===========================================

class Upload {
    
    private static ?PDO $pdo = null;
    
    /**
     * تعيين PDO instance
     * 
     * @param PDO $pdo
     */
    public static function setPDO(PDO $pdo) {
        self::$pdo = $pdo;
    }
    
    // ===========================================
    // 1️⃣ رفع صورة (Image Upload)
    // ===========================================
    
    /**
     * رفع صورة مع التحقق والضغط
     * 
     * @param array $file ملف من $_FILES
     * @param string $folder المجلد المستهدف (products, users, vendors)
     * @param int $maxWidth الحد الأقصى للعرض (��ختياري)
     * @param int $maxHeight الحد الأقصى للارتفاع (اختياري)
     * @param bool $createThumbnail إنشاء صورة مصغرة؟
     * @param int|null $userId معرف المستخدم (للتخزين في DB)
     * @return array ['success' => bool, 'file_path' => string, 'file_url' => string, 'thumbnail' => string]
     */
    public static function uploadImage($file, $folder, $maxWidth = null, $maxHeight = null, $createThumbnail = false, $userId = null) {
        try {
            // التحقق من وجود الملف
            if (!isset($file) || !is_array($file)) {
                return self::error('No file uploaded');
            }
            
            // التحقق من أخطاء الرفع
            if ($file['error'] !== UPLOAD_ERR_OK) {
                return self::error(self::getUploadErrorMessage($file['error']));
            }
            
            // التحقق من حجم الملف
            if ($file['size'] > MAX_IMAGE_SIZE) {
                return self::error('File size exceeds maximum allowed (' . formatBytes(MAX_IMAGE_SIZE) . ')');
            }
            
            // التحقق من نوع الملف
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
                return self::error('Invalid file type.  Allowed:  JPG, PNG, GIF, WEBP');
            }
            
            // التحقق من أن الملف صورة حقيقية
            $imageInfo = @getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                return self::error('File is not a valid image');
            }
            
            // إنشاء اسم ملف فريد
            $extension = self::getExtensionFromMime($mimeType);
            $fileName = self::generateUniqueFileName($extension);
            
            // المسار الكامل
            $uploadPath = UPLOAD_PATH . $folder . '/';
            $filePath = $uploadPath . $fileName;
            
            // إنشاء المجلد إذا لم يكن موجوداً
            if (!self::ensureDirectoryExists($uploadPath)) {
                return self::error('Failed to create upload directory');
            }
            
            // معالجة الصورة (تغيير الحجم والضغط)
            $processed = self::processImage(
                $file['tmp_name'],
                $filePath,
                $mimeType,
                $maxWidth,
                $maxHeight
            );
            
            if (! $processed) {
                return self::error('Failed to process image');
            }
            
            // إنشاء صورة مصغرة إذا طُلب ذلك
            $thumbnailUrl = null;
            $thumbnailPath = null;
            if ($createThumbnail) {
                $thumbnailName = 'thumb_' . $fileName;
                $thumbnailPath = $uploadPath . $thumbnailName;
                
                self::createThumbnail(
                    $filePath,
                    $thumbnailPath,
                    $mimeType,
                    PRODUCT_THUMBNAIL_WIDTH,
                    PRODUCT_THUMBNAIL_HEIGHT
                );
                
                $thumbnailUrl = UPLOAD_URL . $folder . '/' . $thumbnailName;
            }
            
            // تخزين معلومات الملف في قاعدة البيانات
            $fileId = null;
            if (self::$pdo) {
                $fileId = self::saveFileToDB($fileName, $filePath, $folder, 'image', $mimeType, filesize($filePath), $imageInfo[0], $imageInfo[1], $userId, $thumbnailPath);
            }
            
            // تسجيل العملية
            self::logUpload('image', $folder, $fileName, $file['size']);
            
            return [
                'success' => true,
                'file_id' => $fileId,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'file_url' => UPLOAD_URL . $folder . '/' . $fileName,
                'thumbnail_url' => $thumbnailUrl,
                'file_size' => filesize($filePath),
                'dimensions' => [
                    'width' => $imageInfo[0],
                    'height' => $imageInfo[1]
                ]
            ];
            
        } catch (Exception $e) {
            self::logError('Image upload failed:  ' . $e->getMessage());
            return self::error('Upload failed: ' . $e->getMessage());
        }
    }
    
    // ===========================================
    // 2️⃣ رفع مستند (Document Upload)
    // ===========================================
    
    /**
     * رفع مستند (PDF, Word, Excel)
     * 
     * @param array $file ملف من $_FILES
     * @param string $folder المجلد المستهدف
     * @param int|null $userId معرف المستخدم
     * @return array
     */
    public static function uploadDocument($file, $folder, $userId = null) {
        try {
            // التحقق من وجود الملف
            if (!isset($file) || !is_array($file)) {
                return self:: error('No file uploaded');
            }
            
            // التحقق من أخطاء الرفع
            if ($file['error'] !== UPLOAD_ERR_OK) {
                return self::error(self::getUploadErrorMessage($file['error']));
            }
            
            // التحقق من حجم الملف
            if ($file['size'] > MAX_DOCUMENT_SIZE) {
                return self::error('File size exceeds maximum allowed (' .  formatBytes(MAX_DOCUMENT_SIZE) . ')');
            }
            
            // التحقق من نوع الملف
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, ALLOWED_DOCUMENT_TYPES)) {
                return self::error('Invalid document type. Allowed: PDF, Word, Excel');
            }
            
            // إنشاء اسم ملف فريد
            $extension = self::getExtensionFromMime($mimeType);
            $fileName = self::generateUniqueFileName($extension);
            
            // المسار الكامل
            $uploadPath = UPLOAD_PATH . $folder . '/';
            $filePath = $uploadPath . $fileName;
            
            // إنشاء المجلد إذا لم يكن موجوداً
            if (!self::ensureDirectoryExists($uploadPath)) {
                return self:: error('Failed to create upload directory');
            }
            
            // نقل الملف
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                return self::error('Failed to move uploaded file');
            }
            
            // تعيين الصلاحيات
            chmod($filePath, 0644);
            
            // تخزين في DB
            $fileId = null;
            if (self::$pdo) {
                $fileId = self::saveFileToDB($fileName, $filePath, $folder, 'document', $mimeType, filesize($filePath), null, null, $userId);
            }
            
            // تسجيل العملية
            self::logUpload('document', $folder, $fileName, $file['size']);
            
            return [
                'success' => true,
                'file_id' => $fileId,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'file_url' => UPLOAD_URL . $folder . '/' . $fileName,
                'file_size' => filesize($filePath),
                'mime_type' => $mimeType
            ];
            
        } catch (Exception $e) {
            self::logError('Document upload failed: ' . $e->getMessage());
            return self::error('Upload failed: ' . $e->getMessage());
        }
    }
    
    // ===========================================
    // 3️⃣ رفع ��لفات متعددة (Multiple Upload)
    // ===========================================
    
    /**
     * رفع عدة ملفات مرة واحدة
     * 
     * @param array $files مصفوفة من $_FILES
     * @param string $folder المجلد المستهدف
     * @param string $type نوع الملفات (image أو document)
     * @param int|null $userId معرف المستخدم
     * @return array
     */
    public static function uploadMultiple($files, $folder, $type = 'image', $userId = null) {
        $results = [];
        $successCount = 0;
        $failCount = 0;
        
        // التحقق من البنية
        if (!isset($files['name']) || !is_array($files['name'])) {
            return self::error('Invalid files array');
        }
        
        $fileCount = count($files['name']);
        
        // معالجة كل ملف
        for ($i = 0; $i < $fileCount; $i++) {
            // إعادة بناء بنية الملف
            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];
            
            // رفع حسب النوع
            if ($type === 'image') {
                $result = self::uploadImage($file, $folder, null, null, false, $userId);
            } else {
                $result = self:: uploadDocument($file, $folder, $userId);
            }
            
            $results[] = $result;
            
            if ($result['success']) {
                $successCount++;
            } else {
                $failCount++;
            }
        }
        
        return [
            'success' => $successCount > 0,
            'total' => $fileCount,
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'results' => $results
        ];
    }
    
    // ===========================================
    // 4️⃣ حذف ملف (Delete File)
    // ===========================================
    
    /**
     * حذف ملف من السيرفر
     * 
     * @param string $filePath المسار الكامل للملف
     * @param int|null $fileId معرف الملف في DB
     * @return bool
     */
    public static function deleteFile($filePath, $fileId = null) {
        if (empty($filePath) || !file_exists($filePath)) {
            return false;
        }
        
        // التحقق من أن الملف داخل مجلد uploads
        $realPath = realpath($filePath);
        $uploadPath = realpath(UPLOAD_PATH);
        
        if (strpos($realPath, $uploadPath) !== 0) {
            self::logError('Attempted to delete file outside uploads directory:  ' . $filePath);
            return false;
        }
        
        try {
            if (unlink($filePath)) {
                // حذف من DB إذا كان fileId موجودًا
                if ($fileId && self::$pdo) {
                    self::deleteFileFromDB($fileId);
                }
                self::logUpload('delete', basename(dirname($filePath)), basename($filePath), 0);
                return true;
            }
        } catch (Exception $e) {
            self::logError('Failed to delete file: ' . $e->getMessage());
        }
        
        return false;
    }
    
    // ===========================================
    // 5️⃣ حذف ملفات متعددة
    // ===========================================
    
    /**
     * حذف عدة ملفات
     * 
     * @param array $filePaths مصفوفة مسارات الملفات
     * @param array|null $fileIds مصفوفة معرفات الملفات
     * @return array ['deleted' => int, 'failed' => int]
     */
    public static function deleteMultiple($filePaths, $fileIds = null) {
        $deleted = 0;
        $failed = 0;
        
        foreach ($filePaths as $index => $filePath) {
            $fileId = $fileIds[$index] ?? null;
            if (self::deleteFile($filePath, $fileId)) {
                $deleted++;
            } else {
                $failed++;
            }
        }
        
        return [
            'deleted' => $deleted,
            'failed' => $failed
        ];
    }
    
    // ===========================================
    // 🔧 دوال قاعدة البيانات (Database Functions)
    // ===========================================
    
    /**
     * حفظ معلومات الملف في قاعدة البيانات
     * 
     * @param string $fileName
     * @param string $filePath
     * @param string $folder
     * @param string $type
     * @param string $mimeType
     * @param int $size
     * @param int|null $width
     * @param int|null $height
     * @param int|null $userId
     * @param string|null $thumbnailPath
     * @return int|null file_id
     */
    private static function saveFileToDB($fileName, $filePath, $folder, $type, $mimeType, $size, $width = null, $height = null, $userId = null, $thumbnailPath = null) {
        if (!self::$pdo) return null;
        
        try {
            $repo = new UploadRepository(self::$pdo);
            return $repo->insertFile($fileName, $filePath, $folder, $type, $mimeType, $size, $width, $height, $userId, $thumbnailPath);
        } catch (PDOException $e) {
            self::logError('Failed to save file to DB: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * حذف ملف من قاعدة البيانات
     * 
     * @param int $fileId
     * @return bool
     */
    private static function deleteFileFromDB($fileId) {
        if (!self::$pdo) return false;
        
        try {
            $repo = new UploadRepository(self::$pdo);
            return $repo->deleteFile($fileId);
        } catch (PDOException $e) {
            self::logError('Failed to delete file from DB: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * الحصول على معلومات ملف من DB
     * 
     * @param int $fileId
     * @return array|null
     */
    public static function getFileFromDB($fileId) {
        if (!self::$pdo) return null;
        
        try {
            $repo = new UploadRepository(self::$pdo);
            return $repo->findFileById($fileId);
        } catch (PDOException $e) {
            self::logError('Failed to get file from DB: ' . $e->getMessage());
            return null;
        }
    }
    
    // ===========================================
    // 🔧 دوال المعالجة (Processing Functions)
    // ===========================================
    
    /**
     * معالجة الصورة (تغيير الحجم والضغط)
     * 
     * @param string $sourcePath مسار الملف المصدر
     * @param string $destPath مسار الحفظ
     * @param string $mimeType نوع الصورة
     * @param int|null $maxWidth
     * @param int|null $maxHeight
     * @return bool
     */
    private static function processImage($sourcePath, $destPath, $mimeType, $maxWidth = null, $maxHeight = null) {
        // الحصول على معلومات الصورة
        list($width, $height) = getimagesize($sourcePath);
        
        // حساب الأبعاد الجديدة
        $newDimensions = self::calculateDimensions($width, $height, $maxWidth, $maxHeight);
        $newWidth = $newDimensions['width'];
        $newHeight = $newDimensions['height'];
        
        // إنشاء صورة من المصدر
        $sourceImage = self::createImageFromFile($sourcePath, $mimeType);
        if ($sourceImage === false) {
            return false;
        }
        
        // إنشاء صورة جديدة
        $destImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // الحفاظ على الشفافية للـ PNG و GIF
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($destImage, false);
            imagesavealpha($destImage, true);
            $transparent = imagecolorallocatealpha($destImage, 0, 0, 0, 127);
            imagefilledrectangle($destImage, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // نسخ وتغيير الحجم
        imagecopyresampled(
            $destImage,
            $sourceImage,
            0, 0, 0, 0,
            $newWidth,
            $newHeight,
            $width,
            $height
        );
        
        // حفظ الصورة
        $saved = self::saveImage($destImage, $destPath, $mimeType);
        
        // تحرير الذاكرة
        imagedestroy($sourceImage);
        imagedestroy($destImage);
        
        return $saved;
    }
    
    /**
     * إنشاء صورة مصغرة (Thumbnail)
     * 
     * @param string $sourcePath
     * @param string $destPath
     * @param string $mimeType
     * @param int $width
     * @param int $height
     * @return bool
     */
    private static function createThumbnail($sourcePath, $destPath, $mimeType, $width, $height) {
        return self::processImage($sourcePath, $destPath, $mimeType, $width, $height);
    }
    
    /**
     * حساب الأبعاد الجديدة مع الحفاظ على النسبة
     * 
     * @param int $width
     * @param int $height
     * @param int|null $maxWidth
     * @param int|null $maxHeight
     * @return array
     */
    private static function calculateDimensions($width, $height, $maxWidth, $maxHeight) {
        // إذا لم تُحدد أبعاد قصوى، استخدم الأبعاد الأصلية
        if ($maxWidth === null && $maxHeight === null) {
            return ['width' => $width, 'height' => $height];
        }
        
        $ratio = $width / $height;
        
        // حساب الأبعاد الجديدة
        if ($maxWidth !== null && $maxHeight !== null) {
            // كلاهما محدد
            if ($width > $maxWidth || $height > $maxHeight) {
                if ($width / $maxWidth > $height / $maxHeight) {
                    $newWidth = $maxWidth;
                    $newHeight = floor($maxWidth / $ratio);
                } else {
                    $newHeight = $maxHeight;
                    $newWidth = floor($maxHeight * $ratio);
                }
            } else {
                $newWidth = $width;
                $newHeight = $height;
            }
        } elseif ($maxWidth !== null) {
            // العرض فقط محدد
            if ($width > $maxWidth) {
                $newWidth = $maxWidth;
                $newHeight = floor($maxWidth / $ratio);
            } else {
                $newWidth = $width;
                $newHeight = $height;
            }
        } else {
            // الارتفاع فقط محدد
            if ($height > $maxHeight) {
                $newHeight = $maxHeight;
                $newWidth = floor($maxHeight * $ratio);
            } else {
                $newWidth = $width;
                $newHeight = $height;
            }
        }
        
        return [
            'width' => (int)$newWidth,
            'height' => (int)$newHeight
        ];
    }
    
    /**
     * إنشاء resource صورة من ملف
     * 
     * @param string $filePath
     * @param string $mimeType
     * @return resource|false
     */
    private static function createImageFromFile($filePath, $mimeType) {
        switch ($mimeType) {
            case 'image/jpeg': 
            case 'image/jpg': 
                return @imagecreatefromjpeg($filePath);
            case 'image/png':
                return @imagecreatefrompng($filePath);
            case 'image/gif':
                return @imagecreatefromgif($filePath);
            case 'image/webp': 
                return @imagecreatefromwebp($filePath);
            default:
                return false;
        }
    }
    
    /**
     * حفظ الصورة
     * 
     * @param resource $image
     * @param string $filePath
     * @param string $mimeType
     * @return bool
     */
    private static function saveImage($image, $filePath, $mimeType) {
        switch ($mimeType) {
            case 'image/jpeg':
            case 'image/jpg':
                return imagejpeg($image, $filePath, 90); // جودة 90%
            case 'image/png':
                return imagepng($image, $filePath, 8); // ضغط 8
            case 'image/gif':
                return imagegif($image, $filePath);
            case 'image/webp':
                return imagewebp($image, $filePath, 90);
            default:
                return false;
        }
    }
    
    // ===========================================
    // 🔧 دوال مساعدة (Helper Functions)
    // ===========================================
    
    /**
     * إنشاء اسم ملف فريد
     * 
     * @param string $extension
     * @return string
     */
    private static function generateUniqueFileName($extension) {
        return time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    }
    
    /**
     * الحصول على الامتداد من MIME type
     * 
     * @param string $mimeType
     * @return string
     */
    private static function getExtensionFromMime($mimeType) {
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument. wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx'
        ];
        
        return $mimeMap[$mimeType] ?? 'bin';
    }
    
    /**
     * التأكد من وجود المجلد وإنشائه إذا لزم
     * 
     * @param string $path
     * @return bool
     */
    private static function ensureDirectoryExists($path) {
        if (is_dir($path)) {
            return true;
        }
        
        return mkdir($path, 0755, true);
    }
    
    /**
     * الحصول على رسالة خطأ الرفع
     * 
     * @param int $errorCode
     * @return string
     */
    private static function getUploadErrorMessage($errorCode) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
        ];
        
        return $errors[$errorCode] ?? 'Unknown upload error';
    }
    
    /**
     * إرجاع خطأ
     * 
     * @param string $message
     * @return array
     */
    private static function error($message) {
        return [
            'success' => false,
            'message' => $message
        ];
    }
    
    /**
     * تسجيل عملية رفع
     * 
     * @param string $action
     * @param string $folder
     * @param string $fileName
     * @param int $fileSize
     */
    private static function logUpload($action, $folder, $fileName, $fileSize) {
        if (LOG_ENABLED) {
            $message = sprintf(
                "[%s] Upload %s:  %s/%s (%s)\n",
                date('Y-m-d H:i:s'),
                $action,
                $folder,
                $fileName,
                formatBytes($fileSize)
            );
            
            error_log($message, 3, LOG_FILE_API);
        }
    }
    
    /**
     * تسجيل خطأ
     * 
     * @param string $message
     */
    private static function logError($message) {
        if (LOG_ENABLED) {
            error_log("[Upload Error] " . $message, 3, LOG_FILE_ERROR);
        }
    }
    
    /**
     * الحصول على معلومات الملف
     * 
     * @param string $filePath
     * @return array|false
     */
    public static function getFileInfo($filePath) {
        if (!file_exists($filePath)) {
            return false;
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);
        
        $info = [
            'name' => basename($filePath),
            'path' => $filePath,
            'size' => filesize($filePath),
            'mime_type' => $mimeType,
            'extension' => pathinfo($filePath, PATHINFO_EXTENSION),
            'modified' => filemtime($filePath)
        ];
        
        // إذا كانت صورة، احصل على الأبعاد
        if (strpos($mimeType, 'image/') === 0) {
            $imageInfo = @getimagesize($filePath);
            if ($imageInfo !== false) {
                $info['width'] = $imageInfo[0];
                $info['height'] = $imageInfo[1];
            }
        }
        
        return $info;
    }
}

// ===========================================
// ✅ تم تحميل Upload Helper بنجاح
// ===========================================

?>