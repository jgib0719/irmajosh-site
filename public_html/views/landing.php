<?php
/**
 * Landing Page
 * 
 * Public landing page with Google OAuth login
 */
?>
<!DOCTYPE html>
<html lang="<?= getAppLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#4285f4">
    <title><?= env('APP_NAME', 'IrmaJosh') ?> - <?= t('welcome') ?></title>
    
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/icons/icon-192x192.png">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            padding: 1rem;
        }
        
        .landing-container {
            text-align: center;
            max-width: 600px;
            padding: 3rem 2rem;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .logo {
            font-size: 5rem;
            margin-bottom: 1rem;
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        p {
            font-size: 1.2rem;
            margin-bottom: 3rem;
            opacity: 0.95;
            line-height: 1.6;
        }
        
        .login-button {
            display: inline-flex;
            align-items: center;
            gap: 1rem;
            background: white;
            color: #667eea;
            padding: 1rem 2.5rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        
        .login-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.3);
        }
        
        .google-icon {
            width: 24px;
            height: 24px;
        }
        
        .features {
            margin-top: 3rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1.5rem;
        }
        
        .feature {
            background: rgba(255, 255, 255, 0.1);
            padding: 1.5rem;
            border-radius: 15px;
        }
        
        .feature-icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .feature-text {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        <?php if (hasFlash('error')): ?>
        .alert {
            background: rgba(239, 68, 68, 0.9);
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        <?php endif; ?>
    </style>
</head>
<body>
    <div class="landing-container">
        <?php if (hasFlash('error')): ?>
            <div class="alert">
                <span>⚠</span>
                <span><?= htmlspecialchars(getFlash('error')) ?></span>
            </div>
        <?php endif; ?>
        
        <div class="logo">📅</div>
        <h1><?= env('APP_NAME', 'IrmaJosh') ?></h1>
        <p><?= t('login_description') ?></p>
        
        <a href="/auth/login" class="login-button">
            <svg class="google-icon" viewBox="0 0 24 24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
            </svg>
            <span><?= t('login_with_google') ?></span>
        </a>
        
        <div class="features">
            <div class="feature">
                <div class="feature-icon">📅</div>
                <div class="feature-text">Calendar Sync</div>
            </div>
            <div class="feature">
                <div class="feature-icon">✓</div>
                <div class="feature-text">Task Management</div>
            </div>
            <div class="feature">
                <div class="feature-icon">📧</div>
                <div class="feature-text">Schedule Requests</div>
            </div>
        </div>
    </div>
</body>
</html>
