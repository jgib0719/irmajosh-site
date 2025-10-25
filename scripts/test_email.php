<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

if ($argc !== 2) {
    echo "Usage: php test_email.php <recipient@email.com>\n";
    exit(1);
}

$to = $argv[1];

try {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $_ENV['SMTP_HOST'] ?? 'localhost';
    $mail->SMTPAuth = !empty($_ENV['SMTP_USER']);
    
    if ($mail->SMTPAuth) {
        $mail->Username = $_ENV['SMTP_USER'];
        $mail->Password = $_ENV['SMTP_PASS'];
    }
    
    $mail->SMTPSecure = $_ENV['SMTP_ENCRYPTION'] ?? 'tls';
    $mail->Port = $_ENV['SMTP_PORT'] ?? 587;
    
    $fromEmail = $_ENV['SMTP_FROM_EMAIL'] ?? 'admin@irmajosh.com';
    $fromName = $_ENV['SMTP_FROM_NAME'] ?? 'IrmaJosh';
    
    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($to);
    $mail->Subject = 'IrmaJosh Email Test - ' . date('Y-m-d H:i:s');
    $mail->Body = "This is a test email from IrmaJosh deployment.\n\nIf you received this, SMTP is configured correctly.\n\nServer: " . ($_ENV['SMTP_HOST'] ?? 'localhost') . "\nPort: " . ($_ENV['SMTP_PORT'] ?? 587);
    
    $mail->send();
    echo "✓ Email sent successfully to {$to}\n";
    echo "Check inbox (and spam folder) to verify delivery.\n";
} catch (Exception $e) {
    echo "✗ Email failed: {$mail->ErrorInfo}\n";
    exit(1);
}
