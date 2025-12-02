<?php
declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * EmailService
 * 
 * Handles email sending with PHPMailer and rate limiting
 */
class EmailService
{
    /**
     * Create configured PHPMailer instance
     */
    private function createMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = \env('SMTP_HOST') ?: \env('MAIL_HOST');
        
        // SMTP Auth (only if username/password provided)
        $username = \env('SMTP_USER') ?: \env('MAIL_USERNAME');
        $password = \env('SMTP_PASS') ?: \env('MAIL_PASSWORD');
        
        if (!empty($username) && !empty($password)) {
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
        } else {
            $mail->SMTPAuth = false;
        }
        
        // Encryption
        $encryption = \env('SMTP_ENCRYPTION');
        if ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }
        
        $mail->Port = (int)(\env('SMTP_PORT') ?: \env('MAIL_PORT', 587));
        
        // Default from address
        $fromAddress = \env('SMTP_FROM_EMAIL') ?: \env('MAIL_FROM_ADDRESS');
        $fromName = \env('SMTP_FROM_NAME') ?: \env('MAIL_FROM_NAME', \env('APP_NAME'));
        $mail->setFrom($fromAddress, $fromName);
        
        // Character set
        $mail->CharSet = 'UTF-8';
        
        return $mail;
    }
    
    /**
     * Send an email
     */
    public function send(array $data): bool
    {
        try {
            $mail = $this->createMailer();
            
            // Recipients
            if (isset($data['to'])) {
                if (is_array($data['to'])) {
                    foreach ($data['to'] as $email => $name) {
                        if (is_numeric($email)) {
                            $mail->addAddress($name);
                        } else {
                            $mail->addAddress($email, $name);
                        }
                    }
                } else {
                    $mail->addAddress($data['to']);
                }
            }
            
            // CC
            if (isset($data['cc'])) {
                if (is_array($data['cc'])) {
                    foreach ($data['cc'] as $email) {
                        $mail->addCC($email);
                    }
                } else {
                    $mail->addCC($data['cc']);
                }
            }
            
            // BCC
            if (isset($data['bcc'])) {
                if (is_array($data['bcc'])) {
                    foreach ($data['bcc'] as $email) {
                        $mail->addBCC($email);
                    }
                } else {
                    $mail->addBCC($data['bcc']);
                }
            }
            
            // Reply-To
            if (isset($data['reply_to'])) {
                $mail->addReplyTo($data['reply_to']);
            }
            
            // Content
            $mail->isHTML($data['is_html'] ?? true);
            $mail->Subject = $data['subject'];
            $mail->Body = $data['body'];
            
            if (isset($data['alt_body'])) {
                $mail->AltBody = $data['alt_body'];
            }
            
            // Attachments
            if (isset($data['attachments'])) {
                foreach ($data['attachments'] as $attachment) {
                    if (is_array($attachment)) {
                        $mail->addAttachment($attachment['path'], $attachment['name'] ?? '');
                    } else {
                        $mail->addAttachment($attachment);
                    }
                }
            }
            
            $mail->send();
            
            $toLog = is_array($data['to']) ? implode(', ', $data['to']) : $data['to'];
            \logMessage('Email sent to ' . \redactPII($toLog), 'INFO');
            
            return true;
        } catch (Exception $e) {
            \logMessage('Email send failed: ' . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Get admin email addresses from EMAIL_ALLOWLIST
     */
    private function getAdminEmails(): array
    {
        $allowlist = \env('EMAIL_ALLOWLIST', '');
        if (empty($allowlist)) {
            return [];
        }
        
        $emails = array_map('trim', explode(',', $allowlist));
        return array_filter($emails, fn($email) => !empty($email));
    }
    
    /**
     * Send notification email
     */
    public function sendNotification(string $recipientEmail, string $subject, string $message): bool
    {
        $content = '<p>' . nl2br(htmlspecialchars($message)) . '</p>';
        $body = $this->_renderEmailTemplate($subject, $content);
        $altBody = strip_tags($message);
        
        return $this->send([
            'to' => $recipientEmail,
            'subject' => $subject,
            'body' => $body,
            'alt_body' => $altBody,
            'is_html' => true,
        ]);
    }
    
    /**
     * Send notification email to admins
     */
    public function sendAdminNotification(string $subject, string $message): bool
    {
        $adminEmails = $this->getAdminEmails();
        
        if (empty($adminEmails)) {
            \logMessage('No admin emails configured for notification', 'WARNING');
            return false;
        }
        
        $content = '<p>' . nl2br(htmlspecialchars($message)) . '</p>';
        $body = $this->_renderEmailTemplate($subject, $content, true);
        $altBody = strip_tags($message);
        
        return $this->send([
            'to' => $adminEmails,
            'subject' => '[Admin] ' . $subject,
            'body' => $body,
            'alt_body' => $altBody,
            'is_html' => true,
        ]);
    }
    
    /**
     * Send welcome email
     */
    public function sendWelcomeEmail(string $recipientEmail, string $name): bool
    {
        $subject = 'Welcome to ' . \env('APP_NAME');
        
        $content = '
            <h3 style="margin-top: 0;">Welcome, ' . htmlspecialchars($name) . '!</h3>
            <p>Thank you for joining ' . htmlspecialchars(\env('APP_NAME')) . '.</p>
            <p>You can now access your personal calendar and task management system.</p>
            <p style="margin-top: 30px; text-align: center;">
                <a href="' . \env('APP_URL') . '/dashboard" 
                   style="background-color: #4285f4; color: white; padding: 12px 24px; 
                          text-decoration: none; border-radius: 5px; display: inline-block;">
                    Go to Dashboard
                </a>
            </p>
            <p style="color: #666; font-size: 12px; margin-top: 40px; text-align: center;">
                If you have any questions, please contact us at ' . 
                htmlspecialchars(\env('MAIL_FROM_ADDRESS')) . '
            </p>
        ';

        $body = $this->_renderEmailTemplate($subject, $content);
        
        $altBody = "Welcome, {$name}!\n\n" .
                   "Thank you for joining " . \env('APP_NAME') . ".\n" .
                   "You can now access your personal calendar and task management system.\n\n" .
                   "Visit: " . \env('APP_URL') . "/dashboard";
        
        return $this->send([
            'to' => $recipientEmail,
            'subject' => $subject,
            'body' => $body,
            'alt_body' => $altBody,
            'is_html' => true,
        ]);
    }
    
    /**
     * Render a consistent HTML email template
     */
    private function _renderEmailTemplate(string $subject, string $content, bool $isAdmin = false): string
    {
        $appName = htmlspecialchars(\env('APP_NAME', 'IrmaJosh'));
        $footerText = $isAdmin 
            ? "This is an automated admin notification from {$appName}."
            : "This is an automated message from {$appName}.";

        return '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 5px; overflow: hidden;">
                <div style="background-color: #f5f5f5; padding: 20px; border-bottom: 1px solid #e0e0e0;">
                    <h2 style="margin: 0; color: #333;">' . htmlspecialchars($subject) . '</h2>
                </div>
                <div style="padding: 20px; color: #555;">
                    ' . $content . '
                </div>
                <div style="background-color: #f5f5f5; padding: 15px 20px; text-align: center; border-top: 1px solid #e0e0e0;">
                    <p style="color: #666; font-size: 12px; margin: 0;">
                        ' . $footerText . '
                    </p>
                </div>
            </div>
        ';
    }

    /**
     * Check if email sending is configured
     */
    public function isConfigured(): bool
    {
        $host = \env('SMTP_HOST') ?: \env('MAIL_HOST');
        
        // Email is configured if we have a host
        // Username/password not required for localhost or trusted relays
        return !empty($host);
    }
    
    /**
     * Validate email address
     */
    public function validateEmail(string $email): bool
    {
        return PHPMailer::validateAddress($email);
    }
}
