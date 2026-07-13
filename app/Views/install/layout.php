<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'نظام التثبيت | Products Analytics Dashboard' ?></title>
    <!-- Google Fonts: Cairo for Arabic & Outfit for English/Numbers -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bg-color: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.7);
            --card-border: rgba(255, 255, 255, 0.08);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --primary: hsl(263, 85%, 64%);
            --primary-hover: hsl(263, 85%, 58%);
            --accent: hsl(190, 95%, 50%);
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Cairo', 'Outfit', sans-serif;
        }

        body {
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(at 10% 10%, rgba(139, 92, 246, 0.15) 0px, transparent 50%),
                radial-gradient(at 90% 90%, rgba(6, 182, 212, 0.15) 0px, transparent 50%);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem 1rem;
            overflow-x: hidden;
        }

        .container {
            width: 100%;
            max-width: 650px;
            z-index: 10;
        }

        .installer-card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--card-border);
            border-radius: 24px;
            padding: 3rem 2.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-area {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .logo-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 18px;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            font-size: 28px;
            color: white;
            font-weight: 800;
            box-shadow: 0 8px 24px rgba(139, 92, 246, 0.3);
            margin-bottom: 1rem;
        }

        .logo-area h1 {
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, #ffffff, #cbd5e1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .logo-area p {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        /* Progress Steps */
        .steps-indicator {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 3rem;
            position: relative;
            padding: 0 10px;
        }

        .steps-indicator::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 0;
            right: 0;
            height: 2px;
            background: rgba(255, 255, 255, 0.08);
            z-index: 1;
        }

        .steps-progress {
            position: absolute;
            top: 15px;
            right: 0;
            height: 2px;
            background: linear-gradient(to left, var(--primary), var(--accent));
            z-index: 2;
            transition: width 0.4s ease;
        }

        .step-dot {
            width: 32px;
            height: 32px;
            background: #1e293b;
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 12px;
            font-weight: 700;
            z-index: 3;
            color: var(--text-secondary);
            transition: all 0.3s ease;
            position: relative;
        }

        .step-dot.active {
            background: var(--bg-color);
            border-color: var(--primary);
            color: var(--primary);
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.4);
        }

        .step-dot.completed {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .step-label {
            position: absolute;
            top: 40px;
            font-size: 0.75rem;
            white-space: nowrap;
            color: var(--text-secondary);
            font-weight: 600;
        }

        .step-dot.active .step-label {
            color: var(--text-primary);
        }

        /* Form styling */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .input-control {
            width: 100%;
            padding: 0.9rem 1.25rem;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 0.95rem;
            transition: all 0.3s ease;
            direction: ltr;
        }
        
        .input-control[dir="rtl"] {
            direction: rtl;
        }

        .input-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.2);
            background: rgba(15, 23, 42, 0.8);
        }

        select.input-control {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394a3b8'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: left 1rem center;
            background-size: 1.2rem;
            padding-left: 2.5rem;
        }

        /* Buttons */
        .btn-group {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 2.5rem;
        }

        .btn {
            padding: 0.9rem 2rem;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            box-shadow: 0 4px 14px rgba(139, 92, 246, 0.3);
            flex-grow: 2;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(139, 92, 246, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-secondary);
            border: 1px solid var(--card-border);
            flex-grow: 1;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-primary);
        }

        /* Alert notifications */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 12px;
            font-size: 0.9rem;
            margin-bottom: 2rem;
            line-height: 1.5;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-danger {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #a7f3d0;
        }

        /* Requirements checklist */
        .req-list {
            list-style: none;
            margin-bottom: 2rem;
        }

        .req-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--card-border);
            border-radius: 12px;
            margin-bottom: 0.75rem;
        }

        .req-name {
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .req-status {
            font-size: 0.85rem;
            font-weight: 700;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
        }

        .status-ok {
            background: rgba(16, 185, 129, 0.2);
            color: var(--success);
        }

        .status-fail {
            background: rgba(239, 68, 68, 0.2);
            color: var(--danger);
        }

        /* Spinner for loading states */
        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 2rem auto;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .loading-text {
            text-align: center;
            color: var(--text-secondary);
            font-size: 1rem;
            margin-bottom: 1.5rem;
        }

        .step-desc {
            font-size: 0.95rem;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            line-height: 1.6;
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="installer-card">
            
            <div class="logo-area">
                <div class="logo-icon">📊</div>
                <h1>معالج تثبيت لوحة التحكم</h1>
                <p>تثبيت وإعداد Products Analytics Dashboard</p>
            </div>

            <!-- Steps Indicator -->
            <div class="steps-indicator">
                <?php 
                $currentStep = $step ?? 1;
                $progressWidth = (($currentStep - 1) / 4) * 100;
                ?>
                <div class="steps-progress" style="width: <?= $progressWidth ?>%; right: 0;"></div>
                
                <div class="step-dot <?= $currentStep >= 1 ? ($currentStep > 1 ? 'completed' : 'active') : '' ?>">
                    1
                    <span class="step-label">الفحص</span>
                </div>
                <div class="step-dot <?= $currentStep >= 2 ? ($currentStep > 2 ? 'completed' : 'active') : '' ?>">
                    2
                    <span class="step-label">قاعدة البيانات</span>
                </div>
                <div class="step-dot <?= $currentStep >= 3 ? ($currentStep > 3 ? 'completed' : 'active') : '' ?>">
                    3
                    <span class="step-label">المزامنة</span>
                </div>
                <div class="step-dot <?= $currentStep >= 4 ? ($currentStep > 4 ? 'completed' : 'active') : '' ?>">
                    4
                    <span class="step-label">المدير</span>
                </div>
                <div class="step-dot <?= $currentStep >= 5 ? 'completed active' : '' ?>">
                    5
                    <span class="step-label">الإنهاء</span>
                </div>
            </div>

            <!-- Content Area -->
            <?= $this->renderSection('content') ?>

        </div>
    </div>

</body>
</html>
