/**
 * Push Notification Manager
 * Handles subscription and management of push notifications
 */

const PushNotifications = {
    vapidPublicKey: null,
    
    /**
     * Initialize push notifications
     */
    async init(vapidPublicKey) {
        this.vapidPublicKey = vapidPublicKey;
        
        if (!('serviceWorker' in navigator)) {
            console.log('Service Worker not supported');
            return false;
        }
        
        if (!('PushManager' in window)) {
            console.log('Push notifications not supported');
            return false;
        }
        
        // Check if already subscribed
        const subscription = await this.getSubscription();
        if (subscription) {
            console.log('Already subscribed to push notifications');
            return true;
        }
        
        return false;
    },
    
    /**
     * Request permission and subscribe to push notifications
     */
    async subscribe() {
        try {
            const permission = await Notification.requestPermission();
            
            if (permission !== 'granted') {
                console.log('Notification permission denied');
                return false;
            }
            
            const registration = await navigator.serviceWorker.ready;
            
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.urlBase64ToUint8Array(this.vapidPublicKey)
            });
            
            // Send subscription to server
            const response = await fetch('/notifications/subscribe', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    subscription: subscription.toJSON()
                })
            });
            
            if (response.ok) {
                console.log('Successfully subscribed to push notifications');
                return true;
            } else {
                console.error('Failed to save subscription to server');
                return false;
            }
        } catch (error) {
            console.error('Error subscribing to push notifications:', error);
            return false;
        }
    },
    
    /**
     * Unsubscribe from push notifications
     */
    async unsubscribe() {
        try {
            const subscription = await this.getSubscription();
            
            if (!subscription) {
                console.log('Not subscribed to push notifications');
                return true;
            }
            
            // Unsubscribe from push manager
            await subscription.unsubscribe();
            
            // Remove from server
            await fetch('/notifications/unsubscribe', {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    endpoint: subscription.endpoint
                })
            });
            
            console.log('Successfully unsubscribed from push notifications');
            return true;
        } catch (error) {
            console.error('Error unsubscribing:', error);
            return false;
        }
    },
    
    /**
     * Get current push subscription
     */
    async getSubscription() {
        try {
            const registration = await navigator.serviceWorker.ready;
            return await registration.pushManager.getSubscription();
        } catch (error) {
            console.error('Error getting subscription:', error);
            return null;
        }
    },
    
    /**
     * Check if user is subscribed
     */
    async isSubscribed() {
        const subscription = await this.getSubscription();
        return subscription !== null;
    },
    
    /**
     * Convert base64 VAPID key to Uint8Array
     */
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/\-/g, '+')
            .replace(/_/g, '/');
        
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        
        return outputArray;
    }
};

// Make it globally available
window.PushNotifications = PushNotifications;
