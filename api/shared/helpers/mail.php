<?php
require_once __DIR__ . '/../core/repositories/MailRepository.php';
// htdocs/api/helpers/mail.php
// ملف دوال إرسال البريد الإلكتروني (Email Helper)
// يدعم SMTP والقوالب، مع تخزين السجلات في DB عبر PDO

// ===========================================
// تحميل الملفات المطلوبة
// ===========================================

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';

// ===========================================
// Mail Class
// ===========================================

class Mail {
    
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
    // 1️⃣ إرسال بريد إلكتروني (Send Email)
    // ===========================================
    
    /**
     * إرسال بريد إلكتروني
     * 
     * @param string $to البريد المستلم
     * @param string $subject العنوان
     * @param string $body محتوى الرسالة (HTML)
     * @param string|null $fromName اسم المرسل (اختياري)
     * @param string|null $replyTo بريد الرد (اختياري)
     * @param string $lang لغة البريد (ar, en, etc.)
     * @return bool
     */
    public static function send($to, $subject, $body, $fromName = null, $replyTo = null, $lang = 'ar') {
        // التحقق من تفعيل البريد
        if (!MAIL_ENABLED) {
            self::logMail('disabled', $to, $subject);
            // تخزين في DB حتى لو لم يُرسل
            self::saveEmailLog($to, $subject, $body, 'disabled', $lang);
            return true; // نرجع true في بيئة التطوير
        }
        
        try {
            // استخدام PHPMailer إذا كان متاحاً، وإلا mail() العادية
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                $sent = self::sendWithPHPMailer($to, $subject, $body, $fromName, $replyTo);
            } else {
                $sent = self::sendWithMailFunction($to, $subject, $body, $fromName, $replyTo);
            }
            
            // تخزين السجل في DB
            self::saveEmailLog($to, $subject, $body, $sent ? 'sent' : 'failed', $lang);
            
            return $sent;
            
        } catch (Exception $e) {
            self::logError('Email send failed: ' . $e->getMessage());
            self::saveEmailLog($to, $subject, $body, 'error', $lang);
            return false;
        }
    }
    
    // ===========================================
    // 2️⃣ إرسال باستخدام PHPMailer (SMTP)
    // ===========================================
    
    /**
     * إرسال بريد باستخدام PHPMailer و SMTP
     * 
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param string|null $fromName
     * @param string|null $replyTo
     * @return bool
     */
    private static function sendWithPHPMailer($to, $subject, $body, $fromName, $replyTo) {
        require_once __DIR__ . '/../../vendor/autoload.php'; // إذا كنت تستخدم Composer
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // إعدادات SMTP
            $mail->isSMTP();
            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->SMTPSecure = MAIL_ENCRYPTION; // tls or ssl
            $mail->Port = MAIL_PORT;
            $mail->CharSet = 'UTF-8';
            
            // المرسل
            $mail->setFrom(
                MAIL_FROM_ADDRESS,
                $fromName ??  MAIL_FROM_NAME
            );
            
            // المستلم
            $mail->addAddress($to);
            
            // بريد الرد
            if ($replyTo) {
                $mail->addReplyTo($replyTo);
            }
            
            // المحتوى
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body); // نسخة نصية
            
            // إرسال
            $sent = $mail->send();
            
            if ($sent) {
                self:: logMail('sent', $to, $subject);
            }
            
            return $sent;
            
        } catch (Exception $e) {
            self::logError('PHPMailer Error: ' . $mail->ErrorInfo);
            return false;
        }
    }
    
    // ===========================================
    // 3️⃣ إرسال باستخدام mail() العادية
    // ===========================================
    
    /**
     * إرسال بريد باستخدام دالة mail() العادية
     * 
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param string|null $fromName
     * @param string|null $replyTo
     * @return bool
     */
    private static function sendWithMailFunction($to, $subject, $body, $fromName, $replyTo) {
        $from = $fromName ??  MAIL_FROM_NAME;
        
        $headers = [
            'MIME-Version:  1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from .  ' <' . MAIL_FROM_ADDRESS . '>',
        ];
        
        if ($replyTo) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        
        $headers[] = 'X-Mailer: PHP/' . phpversion();
        
        $sent = mail($to, $subject, $body, implode("\r\n", $headers));
        
        if ($sent) {
            self::logMail('sent', $to, $subject);
        } else {
            self::logError('mail() function failed for: ' . $to);
        }
        
        return $sent;
    }
    
    // ===========================================
    // 4️⃣ إرسال بريد ترحيبي (Welcome Email)
    // ===========================================
    
    /**
     * إرسال بريد ترحيبي للمستخدم الجديد
     * 
     * @param string $email
     * @param string $name
     * @param string $username
     * @param string $lang
     * @return bool
     */
    public static function sendWelcomeEmail($email, $name, $username, $lang = 'ar') {
        $subject = self::getLocalizedSubject('welcome', $lang);
        
        $body = self::getTemplate('welcome', [
            'name' => $name,
            'username' => $username,
            'app_name' => APP_NAME,
            'app_url' => APP_URL
        ], $lang);
        
        return self::send($email, $subject, $body, null, null, $lang);
    }
    
    // ===========================================
    // 5️⃣ إرسال رمز التحقق OTP
    // ===========================================
    
    /**
     * إرسال رمز التحقق OTP
     * 
     * @param string $email
     * @param string $name
     * @param string $otp
     * @param string $lang
     * @return bool
     */
    public static function sendOTP($email, $name, $otp, $lang = 'ar') {
        $subject = self::getLocalizedSubject('otp', $lang);
        
        $body = self::getTemplate('otp', [
            'name' => $name,
            'otp' => $otp,
            'expiry' => OTP_EXPIRY / 60, // دقائق
            'app_name' => APP_NAME
        ], $lang);
        
        return self::send($email, $subject, $body, null, null, $lang);
    }
    
    // ===========================================
    // 6️⃣ إرسال بريد إعادة تعيين كلمة المرور
    // ===========================================
    
    /**
     * إرسال رابط إعادة تعيين كلمة المرور
     * 
     * @param string $email
     * @param string $name
     * @param string $resetToken
     * @param string $lang
     * @return bool
     */
    public static function sendPasswordReset($email, $name, $resetToken, $lang = 'ar') {
        $subject = self::getLocalizedSubject('password_reset', $lang);
        
        $resetLink = APP_URL . '/reset-password? token=' . $resetToken;
        
        $body = self:: getTemplate('password_reset', [
            'name' => $name,
            'reset_link' => $resetLink,
            'expiry' => 60, // دقيقة
            'app_name' => APP_NAME
        ], $lang);
        
        return self:: send($email, $subject, $body, null, null, $lang);
    }
    
    // ===========================================
    // 7️⃣ إرسال تأكيد طلب (Order Confirmation)
    // ===========================================
    
    /**
     * إرسال تأكيد الطلب
     * 
     * @param string $email
     * @param string $name
     * @param array $order بيانات الطلب
     * @param string $lang
     * @return bool
     */
    public static function sendOrderConfirmation($email, $name, $order, $lang = 'ar') {
        $subject = self::getLocalizedSubject('order_confirmation', $lang) . ' #' . $order['order_number'];
        
        $body = self::getTemplate('order_confirmation', [
            'name' => $name,
            'order_number' => $order['order_number'],
            'order_date' => $order['created_at'],
            'total' => $order['grand_total'],
            'currency' => DEFAULT_CURRENCY_SYMBOL,
            'order_url' => APP_URL . '/orders/' . $order['id'],
            'app_name' => APP_NAME
        ], $lang);
        
        return self::send($email, $subject, $body, null, null, $lang);
    }
    
    // ===========================================
    // 8️⃣ إرسال تحديث حالة الطلب
    // ===========================================
    
    /**
     * إرسال تحديث حالة الطلب
     * 
     * @param string $email
     * @param string $name
     * @param string $orderNumber
     * @param string $status
     * @param string|null $trackingNumber
     * @param string $lang
     * @return bool
     */
    public static function sendOrderStatusUpdate($email, $name, $orderNumber, $status, $trackingNumber = null, $lang = 'ar') {
        $statusTexts = [
            'confirmed' => self::getLocalizedText('order_confirmed', $lang),
            'processing' => self::getLocalizedText('order_processing', $lang),
            'shipped' => self::getLocalizedText('order_shipped', $lang),
            'delivered' => self::getLocalizedText('order_delivered', $lang),
            'cancelled' => self::getLocalizedText('order_cancelled', $lang)
        ];
        
        $subject = $statusTexts[$status] ?? self::getLocalizedText('order_update', $lang);
        
        $body = self::getTemplate('order_status', [
            'name' => $name,
            'order_number' => $orderNumber,
            'status' => $status,
            'tracking_number' => $trackingNumber,
            'app_name' => APP_NAME
        ], $lang);
        
        return self::send($email, $subject, $body, null, null, $lang);
    }
    
    // ===========================================
    // 🔧 دوال قاعدة البيانات (Database Functions)
    // ===========================================
    
    /**
     * حفظ سجل البريد في قاعدة البيانات
     * 
     * @param string $to
     * @param string $subject
     * @param string $body
     * @param string $status
     * @param string $lang
     * @return bool
     */
    private static function saveEmailLog($to, $subject, $body, $status, $lang) {
        if (!self::$pdo) return false;
        
        try {
            $repo = new MailRepository(self::$pdo);
            return $repo->insertEmailLog($to, $subject, $body, $status, $lang);
        } catch (PDOException $e) {
            self::logError('Failed to save email log: ' . $e->getMessage());
            return false;
        }
    }
    
    // ===========================================
    // 🔧 دوال القوالب واللغات (Template & Language Functions)
    // ===========================================
    
    /**
     * الحصول على عنوان مترجم
     * 
     * @param string $key
     * @param string $lang
     * @return string
     */
    private static function getLocalizedSubject($key, $lang) {
        $subjects = [
            'ar' => [
                'welcome' => 'مرحباً بك في ' . APP_NAME,
                'otp' => 'رمز التحقق - ' . APP_NAME,
                'password_reset' => 'إعادة تعيين كلمة المرور - ' . APP_NAME,
                'order_confirmation' => 'تأكيد الطلب',
            ],
            'en' => [
                'welcome' => 'Welcome to ' . APP_NAME,
                'otp' => 'Verification Code - ' . APP_NAME,
                'password_reset' => 'Reset Password - ' . APP_NAME,
                'order_confirmation' => 'Order Confirmation',
            ],
            // أضف لغات إضافية
        ];
        
        return $subjects[$lang][$key] ?? $subjects['en'][$key] ?? $key;
    }
    
    /**
     * الحصول على نص مترجم
     * 
     * @param string $key
     * @param string $lang
     * @return string
     */
    private static function getLocalizedText($key, $lang) {
        $texts = [
            'ar' => [
                'order_confirmed' => 'تم تأكيد طلبك - Order Confirmed',
                'order_processing' => 'جاري تجهيز طلبك - Order Processing',
                'order_shipped' => 'تم شحن طلبك - Order Shipped',
                'order_delivered' => 'تم توصيل طلبك - Order Delivered',
                'order_cancelled' => 'تم إلغاء طلبك - Order Cancelled',
                'order_update' => 'تحديث الطلب - Order Update',
            ],
            'en' => [
                'order_confirmed' => 'Order Confirmed',
                'order_processing' => 'Order Processing',
                'order_shipped' => 'Order Shipped',
                'order_delivered' => 'Order Delivered',
                'order_cancelled' => 'Order Cancelled',
                'order_update' => 'Order Update',
            ],
        ];
        
        return $texts[$lang][$key] ?? $texts['en'][$key] ?? $key;
    }
    
    /**
     * الحصول على قالب بريد إلكتروني
     * 
     * @param string $templateName اسم القالب
     * @param array $variables المتغيرات
     * @param string $lang
     * @return string
     */
    private static function getTemplate($templateName, $variables = [], $lang = 'ar') {
        // محاولة تحميل قالب مخصص باللغة
        $templatePath = __DIR__ . '/../templates/emails/' . $lang . '/' . $templateName . '.php';
        
        if (!file_exists($templatePath)) {
            // جرب اللغة الإنجليزية كبديل
            $templatePath = __DIR__ . '/../templates/emails/en/' . $templateName . '.php';
        }
        
        if (!file_exists($templatePath)) {
            // جرب المجلد العام
            $templatePath = __DIR__ . '/../templates/emails/' . $templateName . '.php';
        }
        
        if (file_exists($templatePath)) {
            // استخراج المتغيرات
            extract($variables);
            
            // بدء output buffering
            ob_start();
            include $templatePath;
            $content = ob_get_clean();
            
            // تطبيق القالب الأساسي
            return self::applyLayout($content, $variables, $lang);
        }
        
        // إذا لم يوجد قالب، استخدم قالب افتراضي
        return self::getDefaultTemplate($templateName, $variables, $lang);
    }
    
    /**
     * تطبيق القالب الأساسي (Layout)
     * 
     * @param string $content
     * @param array $variables
     * @param string $lang
     * @return string
     */
    private static function applyLayout($content, $variables, $lang = 'ar') {
        $appName = APP_NAME;
        $appUrl = APP_URL;
        $year = date('Y');
        $direction = $lang === 'ar' ? 'rtl' : 'ltr';
        $langCode = $lang;
        $styles = self::getEmailStyles($direction);
        
        return <<<HTML
<!DOCTYPE html>
<html dir="{$direction}" lang="{$langCode}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$appName}</title>
    <style>{$styles}</style>
</head>
<body>
    <div class="container">
        <div class="header"><h1>{$appName}</h1></div>
        <div class="content">{$content}</div>
        <div class="footer">
            <p>&copy; {$year} {$appName}. جميع الحقوق محفوظة - All rights reserved.</p>
            <p>
                <a href="{$appUrl}" style="color: #667eea; text-decoration: none;">زيارة الموقع</a> | 
                <a href="{$appUrl}/support" style="color: #667eea; text-decoration: none;">الدعم الفني</a>
            </p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private static function getEmailStyles(string $direction): string
    {
        return "
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; direction: {$direction}; }
        .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; color: white; }
        .header h1 { margin: 0; font-size: 28px; }
        .content { padding: 30px; color: #333; line-height: 1.6; }
        .button { display: inline-block; padding: 12px 30px; background-color: #667eea; color: white !important; text-decoration: none; border-radius: 5px; margin: 20px 0; }
        .footer { background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #eee; }
        .otp-code { font-size: 32px; font-weight: bold; color: #667eea; letter-spacing: 5px; padding: 20px; background-color: #f0f0f0; border-radius: 5px; text-align: center; margin: 20px 0; }
        ";
    }
    
    /**
     * الحصول على قالب افتراضي
     * 
     * @param string $templateName
     * @param array $variables
     * @param string $lang
     * @return string
     */
    private static function getDefaultTemplate($templateName, $variables, $lang = 'ar') {
        extract($variables);
        
        $isArabic = $lang === 'ar';
        
        switch ($templateName) {
            case 'welcome':
                if ($isArabic) {
                    $content = <<<HTML
                    <h2>مرحباً {$name}!</h2>
                    <p>نشكرك على التسجيل في {$app_name}. </p>
                    <p>اسم المستخدم: <strong>{$username}</strong></p>
                    <p>يمكنك الآن تسجيل الدخول والبدء في التسوق.</p>
                    <a href="{$app_url}" class="button">تسوق الآن</a>
HTML;
                } else {
                    $content = <<<HTML
                    <h2>Welcome {$name}!</h2>
                    <p>Thank you for registering with {$app_name}.</p>
                    <p>Username: <strong>{$username}</strong></p>
                    <p>You can now log in and start shopping.</p>
                    <a href="{$app_url}" class="button">Shop Now</a>
HTML;
                }
                break;
                
            case 'otp': 
                if ($isArabic) {
                    $content = <<<HTML
                    <h2>رمز التحقق</h2>
                    <p>مرحباً {$name},</p>
                    <p>رمز التحقق الخاص بك: </p>
                    <div class="otp-code">{$otp}</div>
                    <p>هذا الرمز صالح لمدة {$expiry} دقائق.</p>
                    <p><strong>تحذير:</strong> لا تشارك هذا الرمز مع أي شخص. </p>
HTML;
                } else {
                    $content = <<<HTML
                    <h2>Verification Code</h2>
                    <p>Hello {$name},</p>
                    <p>Your verification code is: </p>
                    <div class="otp-code">{$otp}</div>
                    <p>This code is valid for {$expiry} minutes.</p>
                    <p><strong>Warning:</strong> Do not share this code with anyone.</p>
HTML;
                }
                break;
                
            default:
                $content = '<p>' . ($isArabic ? 'محتوى البريد الإلكتروني.' : 'Email content.') . '</p>';
        }
        
        return self::applyLayout($content, $variables, $lang);
    }
    
    // ===========================================
    // 🔧 دوال مساعدة (Helper Functions)
    // ===========================================
    
    /**
     * التحقق من صحة البريد الإلكتروني
     * 
     * @param string $email
     * @return bool
     */
    public static function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * تسجيل عملية إرسال بريد
     * 
     * @param string $status
     * @param string $to
     * @param string $subject
     */
    private static function logMail($status, $to, $subject) {
        if (LOG_ENABLED) {
            $message = sprintf(
                "[%s] Email %s: To=%s, Subject=%s\n",
                date('Y-m-d H:i:s'),
                $status,
                $to,
                $subject
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
            error_log("[Mail Error] " . $message, 3, LOG_FILE_ERROR);
        }
        
        if (DEBUG_MODE) {
            error_log("[Mail Debug] " . $message);
        }
    }
}

// ===========================================
// ✅ تم تحميل Mail Helper بنجاح
// ===========================================

?>