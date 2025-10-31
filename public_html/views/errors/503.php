<!DOCTYPE html>
<html lang="<?= getAppLocale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>503 - <?= t('database_error') ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }
        .container {
            text-align: center;
            padding: 2rem;
            max-width: 600px;
            background: rgba(255,255,255,0.95);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            font-size: 5rem;
            margin-bottom: 1rem;
            color: #fa709a;
        }
        h2 {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            font-weight: 600;
            color: #333;
        }
        p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            color: #666;
            line-height: 1.6;
        }
        a {
            display: inline-block;
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
            padding: 1rem 2rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.2s;
        }
        a:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>503</h1>
        <h2><?= t('database_error') ?></h2>
        <p>We're experiencing database connectivity issues. Please try again in a few moments.</p>
        <a href="#" id="tryAgain">Try Again</a>
    </div>
    <script>
        document.getElementById('tryAgain').addEventListener('click', function(e) {
            e.preventDefault();
            location.reload();
        });
    </script>
</body>
</html>
