#!/usr/bin/env php
<?php

/**
 * generate_jwt_keys.php
 *
 * سكريبت لإنشاء مفاتيح RSA للـ JWT (RS256)
 * شغّله مرة واحدة فقط على السيرفر
 *
 * الاستخدام:
 *   php api/scripts/generate_jwt_keys.php
 *
 * ثم أضف في .env أو config.php:
 *   JWT_PRIVATE_KEY_PATH=/path/to/private/jwt_private.pem
 *   JWT_PUBLIC_KEY_PATH=/path/to/private/jwt_public.pem
 */

declare(strict_types=1);

// يجب تشغيل هذا السكريبت من سطر الأوامر فقط
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo json_encode(['error' => 'CLI only']);
    exit(1);
}

// المسار الذي ستُحفظ فيه المفاتيح (خارج الـ webroot!)
$outputDir = dirname(__DIR__) . '/private/jwt_keys';

if (!is_dir($outputDir)) {
    if (!mkdir($outputDir, 0700, true)) {
        echo "❌ فشل إنشاء المجلد: $outputDir\n";
        exit(1);
    }
}

$privateKeyPath = $outputDir . '/jwt_private.pem';
$publicKeyPath  = $outputDir . '/jwt_public.pem';

// تحذير إذا كانت المفاتيح موجودة بالفعل
if (file_exists($privateKeyPath) || file_exists($publicKeyPath)) {
    echo "⚠️  المفاتيح موجودة بالفعل:\n";
    echo "   $privateKeyPath\n";
    echo "   $publicKeyPath\n";
    echo "هل تريد الكتابة فوقها؟ [y/N]: ";
    $answer = trim(fgets(STDIN));
    if (strtolower($answer) !== 'y') {
        echo "تم الإلغاء.\n";
        exit(0);
    }
}

echo "⚙️  جاري إنشاء مفاتيح RSA-2048...\n";

// إنشاء المفتاح الخاص
$config = [
    'digest_alg'       => 'sha256',
    'private_key_bits' => 2048,
    'private_key_type' => OPENSSL_KEYTYPE_RSA,
];

$resource = openssl_pkey_new($config);

if ($resource === false) {
    echo "❌ فشل إنشاء المفتاح: " . openssl_error_string() . "\n";
    exit(1);
}

// تصدير المفتاح الخاص
openssl_pkey_export($resource, $privateKey);
file_put_contents($privateKeyPath, $privateKey);
chmod($privateKeyPath, 0600);   // قراءة للـ owner فقط

// تصدير المفتاح العام
$details   = openssl_pkey_get_details($resource);
$publicKey = $details['key'];
file_put_contents($publicKeyPath, $publicKey);
chmod($publicKeyPath, 0644);

echo "✅ تم إنشاء المفاتيح بنجاح:\n";
echo "   Private: $privateKeyPath  (chmod 600)\n";
echo "   Public:  $publicKeyPath   (chmod 644)\n\n";
echo "⚠️  تأكد أن مجلد 'private' خارج الـ webroot تماماً!\n";
echo "📄 أضف في .env:\n";
echo "   JWT_PRIVATE_KEY_PATH=$privateKeyPath\n";
echo "   JWT_PUBLIC_KEY_PATH=$publicKeyPath\n";
