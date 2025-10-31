# Notifications & Email Setup Guide

**Date:** October 31, 2025  
**Purpose:** Explain how push notifications and email alerts work

---

## Push Notifications (Web Push) 🔔

### Current Status: ✅ CONFIGURED & WORKING

Push notifications use the **Web Push API** with VAPID keys to send notifications to your browser/device.

### How It Works:

1. **User Must Subscribe First**
   - Go to Dashboard
   - Look for "Enable Notifications" or notification toggle button
   - Click to subscribe (browser will ask for permission)
   - Once subscribed, you'll receive push notifications

2. **When Notifications Are Sent:**
   - ✅ **Task Created** - When you create a new task
   - ✅ **Schedule Request Accepted** - When you accept a schedule request  
   - ✅ **Calendar Event Created** - When you create a calendar event
   - ❌ **Schedule Request Received** - NOT IMPLEMENTED (you create requests for yourself currently)
   - ❌ **Task Completed** - NOT IMPLEMENTED
   - ❌ **Event Reminders** - NOT IMPLEMENTED (future feature)

3. **Requirements:**
   - HTTPS connection (already configured)
   - Modern browser (Chrome, Firefox, Edge, Safari 16+)
   - Permission granted by user
   - User must be subscribed via the dashboard toggle

### Configuration (Already Done ✅):

```bash
# .env variables (already set):
VAPID_PUBLIC_KEY=BIgU7mJmsD7OcySgKcPN-pQG6Co17fQexkkD4QcpOPCv1ceZB_WClfuOJK1XQwykImOvu3XtNRpEueUE7Fw9U4Y
VAPID_PRIVATE_KEY=RHFuSWwkeEAKFl6xqR1KuehNFknEU635J4znagzcYns
VAPID_SUBJECT=mailto:admin@irmajosh.com
```

### Testing Push Notifications:

```bash
# Test sending a notification to a user
php scripts/test_notifications.php <user_id>
```

### How to Subscribe:

1. Open the Dashboard page
2. Look for the notification bell icon or "Enable Notifications" button
3. Click it and grant permission when browser prompts
4. You should see "Notifications enabled" message
5. Test by creating a task - you should get a notification

---

## Email Notifications 📧

### Current Status: ❌ NOT CONFIGURED

Email notifications are **not currently configured** because SMTP settings are incomplete.

### What's Missing:

The `.env` file has variables named `SMTP_*` but the `EmailService` class expects `MAIL_*` variables:

**Current (.env):**
```bash
SMTP_HOST=localhost
SMTP_PORT=25
SMTP_USER=
SMTP_PASS=
```

**Expected by EmailService:**
```bash
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-email@example.com
MAIL_PASSWORD=your-app-password
MAIL_FROM_ADDRESS=noreply@irmajosh.com
MAIL_FROM_NAME="IrmaJosh Calendar"
```

### To Enable Email Notifications:

#### Option 1: Use Gmail SMTP

1. **Create Google App Password:**
   - Go to Google Account → Security
   - Enable 2-Factor Authentication
   - Generate an App Password for "Mail"

2. **Update .env:**
```bash
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password-here
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="IrmaJosh Calendar"
```

#### Option 2: Use SendGrid

```bash
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-sendgrid-api-key
MAIL_FROM_ADDRESS=noreply@irmajosh.com
MAIL_FROM_NAME="IrmaJosh Calendar"
```

#### Option 3: Use Mailgun

```bash
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@your-domain.mailgun.org
MAIL_PASSWORD=your-mailgun-password
MAIL_FROM_ADDRESS=noreply@irmajosh.com
MAIL_FROM_NAME="IrmaJosh Calendar"
```

### After Configuring:

1. Restart PHP-FPM: `sudo systemctl reload php8.4-fpm`
2. Test email: `php scripts/test_email.php your-email@example.com`

### When Emails Would Be Sent (Once Configured):

Currently, the EmailService has these methods but **they're not being called**:

- `sendScheduleRequest()` - When someone sends you a schedule request (not implemented in controllers)
- `sendNotification()` - General notification emails (not implemented)
- `sendWelcomeEmail()` - When user first logs in (not implemented)

**To enable email notifications, you would need to:**

1. Configure SMTP settings in .env
2. Add email sending calls to controllers (like we added push notifications)

---

## Summary

### ✅ What's Working Now:

1. **Push Notifications** - Fully configured
   - User must click "Enable Notifications" on dashboard
   - Notifications sent for: tasks, schedule accepts, calendar events
   - Check subscription status at `/notifications/status`

### ❌ What's Not Working:

1. **Email Notifications** - Not configured
   - SMTP settings incomplete
   - No email sending calls in controllers
   - Requires MAIL_* environment variables

### 🔧 Quick Setup Guide:

**To get push notifications working RIGHT NOW:**

1. Open https://irmajosh.com/dashboard
2. Look for notification toggle/button
3. Click and grant browser permission
4. Create a task to test - you should get a notification

**To add email notifications:**

1. Choose an SMTP provider (Gmail, SendGrid, Mailgun, etc.)
2. Update .env with MAIL_* variables (see examples above)
3. Reload PHP-FPM: `sudo systemctl reload php8.4-fpm`
4. Test: `php scripts/test_email.php your@email.com`

---

## Troubleshooting

### Push Notifications Not Working?

1. **Check if subscribed:**
   ```bash
   mysql -u irmajosh_app -p irmajosh_db -e "SELECT * FROM push_subscriptions WHERE user_id=1;"
   ```

2. **Check browser console for errors** (F12 → Console tab)

3. **Verify VAPID keys are set:**
   ```bash
   grep VAPID /var/www/irmajosh.com/.env
   ```

4. **Test manually:**
   ```bash
   php scripts/test_notifications.php 1
   ```

### Emails Not Sending?

1. **Check if MAIL_* variables are set:**
   ```bash
   grep MAIL_ /var/www/irmajosh.com/.env
   ```

2. **Check EmailService configuration:**
   ```bash
   php -r "require 'vendor/autoload.php'; require 'src/bootstrap.php'; \$e = new \App\Services\EmailService(); var_dump(\$e->isConfigured());"
   ```

3. **Check SMTP connection:**
   ```bash
   telnet smtp.gmail.com 587
   ```

4. **Check logs:**
   ```bash
   tail -f storage/logs/app.log | grep -i email
   ```

---

**Last Updated:** October 31, 2025
