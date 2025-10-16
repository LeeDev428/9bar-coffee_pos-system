<?php
// If Composer autoload exists (PHPMailer was installed), load it so classes are available
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

function sendMail($toEmail, $toName, $subject, $bodyHtml, $bodyText = '') {
    // Load SMTP config
    $configPath = __DIR__ . '/smtp_config.php';
    $smtp = file_exists($configPath) ? require $configPath : null;

    // Prefer PHPMailer if available
    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            // Server settings
            if (!empty($smtp) && $smtp['enabled']) {
                $mail->isSMTP();
                $mail->Host = $smtp['host'];
                $mail->SMTPAuth = true;
                $mail->Username = $smtp['username'];
                $mail->Password = $smtp['password'];
                $mail->SMTPSecure = $smtp['secure'] ?: PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = $smtp['port'];
            }

            // Optional debug output into PHP error_log when enabled in smtp_config.php
            if (!empty($smtp) && !empty($smtp['debug'])) {
                // 0 = off, 1 = client messages, 2 = client and server
                $mail->SMTPDebug = is_int($smtp['debug']) ? $smtp['debug'] : 2;
                $mail->Debugoutput = function($str, $level) {
                    error_log('[PHPMailer DEBUG] ' . trim($str));
                };
            }

            $mail->setFrom($smtp['from_email'] ?? 'noreply@example.com', $smtp['from_name'] ?? 'App');
            $mail->addAddress($toEmail, $toName);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $bodyHtml;
            $mail->AltBody = $bodyText ?: strip_tags($bodyHtml);

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log('Mailer error: ' . $e->getMessage());
            return false;
        }
    }

    // Fallback to PHP mail()
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: ' . ($smtp['from_name'] ?? 'App') . ' <' . ($smtp['from_email'] ?? 'noreply@example.com') . '>' . "\r\n";

    $body = $bodyHtml;
    return mail($toEmail, $subject, $body, $headers);
}
