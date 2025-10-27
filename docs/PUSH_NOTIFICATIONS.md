# Push Notifications

Web Push notification system using VAPID authentication and service workers.

## Architecture

**Protocol:** Web Push API  
**Library:** minishlink/web-push v9.0.2  
**Authentication:** VAPID (Voluntary Application Server Identification)

## Components

### Database
- **Table:** `push_subscriptions`
- **Columns:** user_id, endpoint, p256dh, auth, user_agent
- **Migration:** `012_create_push_subscriptions.sql`

### Backend

**Model:** `src/Models/PushSubscription.php`
- `subscribe()` - Save user subscription
- `unsubscribe()` - Remove subscription
- `getSubscriptionsByUser()` - Get all user subscriptions
- `removeExpired()` - Clean up invalid subscriptions

**Service:** `src/Services/NotificationService.php`
- `sendToUser()` - Send notification to user's devices
- `notifyTaskCreated()` - Helper for task notifications
- `notifyScheduleRequest()` - Helper for schedule notifications
- `notifyEventCreated()` - Helper for event notifications

**Controller:** `src/Controllers/NotificationController.php`
- `POST /notifications/subscribe` - Subscribe device
- `DELETE /notifications/unsubscribe` - Unsubscribe device
- `GET /notifications/status` - Get subscription count

### Frontend

**Manager:** `public_html/assets/js/push-notifications.js`
- Global `PushNotifications` object
- Methods: `init()`, `subscribe()`, `unsubscribe()`, `isSubscribed()`
- Handles browser permissions and VAPID key conversion

**Service Worker:** `public_html/service-worker.js`
- `push` event - Receives and displays notifications
- `notificationclick` event - Opens app on click

**UI:** Dashboard notification toggle
- Enable/disable button
- Real-time subscription status

## Integration

Notifications triggered in:
- **TaskController** - When shared tasks created
- **ScheduleController** - When schedule requests sent

## Configuration

Required `.env` variables:
```
VAPID_PUBLIC_KEY=<base64url-encoded-public-key>
VAPID_PRIVATE_KEY=<base64url-encoded-private-key>
VAPID_SUBJECT=mailto:admin@irmajosh.com
```

Generate new keys:
```bash
php -r "require 'vendor/autoload.php'; 
use Minishlink\WebPush\VAPID; 
\$keys = VAPID::createVapidKeys(); 
echo 'VAPID_PUBLIC_KEY=' . \$keys['publicKey'] . PHP_EOL;
echo 'VAPID_PRIVATE_KEY=' . \$keys['privateKey'] . PHP_EOL;"
```

## Usage

**User subscribes:**
1. Visit `/dashboard`
2. Click "Enable Notifications"
3. Accept browser permission
4. Subscription saved to database

**Send test notification:**
```bash
php scripts/test_notifications.php <user_id>
```

## Requirements

- **HTTPS required** (Web Push spec)
- Browser support: Chrome, Firefox, Safari 16.4+, Edge
- User must grant notification permission

## Security

- Routes protected with auth + CSRF + rate limiting
- VAPID keys stored in `.env` (not in repo)
- User ownership validated on all operations
- Expired subscriptions auto-removed on send failure
