# Admin Email Notifications

## Overview
The system now sends email notifications to both user and admin Gmail addresses for all major activities.

## Admin Email Configuration
Admin emails are configured via the `EMAIL_ALLOWLIST` environment variable:
```
EMAIL_ALLOWLIST=jgib0719@gmail.com,irmakusuma200@gmail.com
```

## Email Events

### 1. Shared Task Created
**User receives:**
- Subject: "New Shared Task Created"
- Contains: Task title, description, link to view

**Admins receive:**
- Subject: "[Admin] Shared Task Created"  
- Contains: User info (name, email), task title, description, link to view

### 2. Schedule Request Accepted
**User receives:**
- Subject: "Schedule Request Accepted"
- Contains: Request title, description, next steps

**Admins receive:**
- Subject: "[Admin] Schedule Request Accepted"
- Contains: User info, request title, description, link to view

### 3. Calendar Event Created
**User receives:**
- Subject: "Calendar Event Created"
- Contains: Event title, description, start/end times, link to calendar

**Admins receive:**
- Subject: "[Admin] Calendar Event Created"
- Contains: User info, event title, description, start/end times, link to calendar

## Testing

### Test Individual Email Addresses
```bash
# Test Josh's Gmail
php scripts/test_email.php jgib0719@gmail.com

# Test Irma's Gmail
php scripts/test_email.php irmakusuma200@gmail.com
```

### Test Admin Notification (sends to both)
```bash
php scripts/test_admin_email.php
```

This will send a test notification to both admin Gmail addresses.

## Implementation Details

### EmailService Methods
- `sendNotification($email, $subject, $message)` - Send to single user
- `sendAdminNotification($subject, $message)` - Send to all admin emails
- `getAdminEmails()` - Private method that parses EMAIL_ALLOWLIST

### Controller Updates
All three controllers now send both user and admin notifications:
- `TaskController::create()` - For shared tasks
- `ScheduleController::acceptRequest()` - For accepted schedule requests
- `CalendarController::createEvent()` - For new calendar events

### Email Format
All admin emails:
- Have "[Admin]" prefix in subject line
- Include user information (name and email)
- Are formatted as HTML with plain text fallback
- Are sent to all addresses in EMAIL_ALLOWLIST

## SMTP Configuration
Currently using localhost SMTP relay:
```
SMTP_HOST=localhost
SMTP_PORT=25
SMTP_FROM_EMAIL=admin@irmajosh.com
SMTP_FROM_NAME="IrmaJosh Calendar"
```

**Note:** For production with Gmail delivery, consider using:
- Gmail SMTP (requires app password)
- SendGrid
- Mailgun
- AWS SES

## Troubleshooting

### Check if emails were sent
```bash
tail -50 storage/logs/app.log | grep -i email
```

### Verify SMTP configuration
```bash
grep SMTP .env
```

### Check email allowlist
```bash
grep EMAIL_ALLOWLIST .env
```

### Check spam folders
Emails from localhost may be flagged as spam. Check spam/junk folders in Gmail.

### Verify localhost SMTP is running
```bash
sudo systemctl status postfix
# or
sudo netstat -tlnp | grep :25
```

## Security Notes
- Email addresses in logs are redacted using `redactPII()`
- All email content is properly escaped with `htmlspecialchars()`
- SMTP authentication is optional (not needed for localhost relay)
- For production, enable SMTP_ENCRYPTION and use authenticated SMTP
