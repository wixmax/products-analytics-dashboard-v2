<!doctype html>
<html lang="ar" dir="rtl" data-theme="dark">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= $this->renderSection('title') ?> | Overview Insights</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />

    <link rel="stylesheet" href="<?= base_url('index.css') ?>?v=1.6" />
    
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.15) 0%, transparent 40%),
                        radial-gradient(circle at 90% 80%, rgba(14, 165, 233, 0.15) 0%, transparent 40%),
                        var(--bg-app);
            padding: 20px;
        }
        
        .auth-container {
            width: 100%;
            max-width: 440px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 2.5rem 2rem;
            box-shadow: var(--shadow-lg);
            position: relative;
            backdrop-filter: blur(10px);
            transition: var(--transition-all);
        }
        
        .auth-container::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            height: 4px;
            background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .auth-logo {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            display: inline-block;
        }

        .auth-title {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(to left, var(--color-primary), var(--color-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .auth-subtitle {
            font-size: 0.85rem;
            color: var(--color-text-muted);
        }

        .alert {
            padding: 10px 15px;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
            border-right: 4px solid transparent;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--color-error);
            border-right-color: var(--color-error);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--color-success);
            border-right-color: var(--color-success);
        }
        
        .auth-footer {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.85rem;
            color: var(--color-text-muted);
        }
        
        .auth-footer a {
            color: var(--color-primary);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition-all);
        }
        
        .auth-footer a:hover {
            color: var(--color-primary-hover);
            text-decoration: underline;
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: var(--color-text-muted);
            font-size: 0.8rem;
            margin: 1.5rem 0;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid var(--border-color);
        }

        .divider:not(:empty)::before {
            margin-left: .5em;
        }

        .divider:not(:empty)::after {
            margin-right: .5em;
        }

        .btn-oauth {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            background: var(--bg-input);
            border: 1px solid var(--border-color);
            color: var(--color-text-main);
            padding: 0.75rem;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition-all);
            text-decoration: none;
        }

        .btn-oauth:hover {
            background: var(--bg-card-hover);
            border-color: var(--border-color-hover);
        }

        .btn-oauth img {
            width: 18px;
            height: 18px;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            user-select: none;
            font-size: 0.85rem;
            color: var(--color-text-muted);
        }

        .form-check input {
            cursor: pointer;
        }
    </style>
</head>
<body>

    <div class="auth-container">
        <?= $this->renderSection('main') ?>
    </div>

</body>
</html>
