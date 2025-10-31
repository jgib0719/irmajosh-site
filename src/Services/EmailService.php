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
     * Send schedule request email
     */
    public function sendScheduleRequest(string $recipientEmail, array $slots, string $subject, ?string $message = null): bool
    {
        $slotsHtml = '<ul>';
        foreach ($slots as $slot) {
            $start = date('F j, Y g:i A', strtotime($slot['start_time']));
            $end = date('g:i A', strtotime($slot['end_time']));
            $slotsHtml .= "<li>{$start} - {$end}</li>";
        }
        $slotsHtml .= '</ul>';
        
        $body = '<h2>' . htmlspecialchars($subject) . '</h2>';
        
        if ($message) {
            $body .= '<p>' . nl2br(htmlspecialchars($message)) . '</p>';
        }
        
        $body .= '<h3>Available Time Slots:</h3>';
        $body .= $slotsHtml;
        $body .= '<p>Please select a time slot that works for you.</p>';
        
        // Plain text version
        $altBody = strip_tags($body);
        
        return $this->send([
            'to' => $recipientEmail,
            'subject' => $subject,
            'body' => $body,
            'alt_body' => $altBody,
            'is_html' => true,
        ]);
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
        $body = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <h2>' . htmlspecialchars($subject) . '</h2>
                <div style="padding: 20px; background-color: #f5f5f5; border-radius: 5px;">
                    ' . nl2br(htmlspecialchars($message)) . '
                </div>
                <p style="color: #666; font-size: 12px; margin-top: 20px;">
                    This is an automated message from ' . htmlspecialchars(\env('APP_NAME')) . '.
                </p>
            </div>
        ';
        
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
        
        $body = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <h2>' . htmlspecialchars($subject) . '</h2>
                <div style="padding: 20px; background-color: #f5f5f5; border-radius: 5px;">
                    ' . nl2br(htmlspecialchars($message)) . '
                </div>
                <p style="color: #666; font-size: 12px; margin-top: 20px;">
                    This is an automated admin notification from ' . htmlspecialchars(\env('APP_NAME')) . '.
                </p>
            </div>
        ';
        
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
        
        $body = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
                <h2>Welcome, ' . htmlspecialchars($name) . '!</h2>
                <p>Thank you for joining ' . htmlspecialchars(\env('APP_NAME')) . '.</p>
                <p>You can now access your personal calendar and task management system.</p>
                <p style="margin-top: 30px;">
                    <a href="' . \env('APP_URL') . '/dashboard" 
                       style="background-color: #4285f4; color: white; padding: 10px 20px; 
                              text-decoration: none; border-radius: 5px; display: inline-block;">
                        Go to Dashboard
                    </a>
                </p>
                <p style="color: #666; font-size: 12px; margin-top: 40px;">
                    If you have any questions, please contact us at ' . 
                    htmlspecialchars(\env('MAIL_FROM_ADDRESS')) . '
                </p>
            </div>
        ';
        
        $altBody = "Welcome, {$name}!\nn" .
                   "Thank you for joining " . \env('APP_NAME') . ".\n" .
                   "You can now access your personal calendar and task management system.\nn" .
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
