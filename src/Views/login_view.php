<!DOCTYPE html>
<html lang="en" data-mode="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Azzurro HR</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo baseUrl('css/style.css'); ?>">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-page);
            height: 100vh;
            overflow: hidden;
        }
        .login-card {
            width: 100%;
            max-width: 380px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            box-shadow: var(--sh-md);
            overflow: hidden;
            animation: slideUp 0.4s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-header {
            padding: 40px 30px 20px;
            text-align: center;
        }
        .login-body {
            padding: 0 30px 40px;
        }
        .login-logo {
            width: 48px;
            height: 48px;
            background: linear-gradient(140deg, var(--teal-deep), var(--teal));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 4px 12px var(--teal-glow);
        }
        .login-title {
            font-size: 22px;
            font-weight: 800;
            color: var(--ink-1);
            letter-spacing: -0.02em;
            margin-bottom: 8px;
        }
        .login-subtitle {
            font-size: 13px;
            color: var(--ink-3);
        }
        .alert {
            padding: 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-error {
            background: var(--red-bg);
            color: var(--red);
            border: 1px solid var(--red-bdr);
        }
        .alert-success {
            background: var(--green-bg);
            color: var(--green);
            border: 1px solid var(--green-bdr);
        }
        
        /* Floating Label Effect */
        .login-input-field {
            position: relative;
            border-bottom: 2px solid var(--border);
            margin: 20px 0;
            transition: border-color 0.2s;
        }

        .login-input-field:focus-within {
            border-bottom-color: var(--teal);
        }

        .login-input-field label {
            position: absolute;
            top: 50%;
            left: 0;
            transform: translateY(-50%);
            color: var(--ink-3);
            font-size: 14px;
            pointer-events: none;
            transition: 0.15s ease;
        }

        .login-input-field input {
            width: 100%;
            height: 40px;
            background: transparent;
            border: none;
            outline: none;
            font-size: 15px;
            color: var(--ink-1);
            padding: 0;
        }

        .login-input-field input:focus ~ label,
        .login-input-field input:valid ~ label {
            font-size: 11px;
            top: 0;
            transform: translateY(-120%);
            color: var(--teal);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <div class="login-logo">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                </svg>
            </div>
            <h1 class="login-title">Azzurro<em>HR</em></h1>
            <p class="login-subtitle">Sign in to your account</p>
        </div>

        <div class="login-body">
            <?php if(!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="<?php echo baseUrl('login'); ?>" method="POST">
                <div class="login-input-field" style="margin-top: 30px;">
                    <input type="text" name="ac_no" required autofocus autocomplete="off">
                    <label>AC Number</label>
                </div>
                
                <div class="login-input-field" style="margin-top: 35px;">
                    <input type="password" name="password" required>
                    <label>Password</label>
                    <a href="#" style="position: absolute; right: 0; top: -15px; font-size: 11px; color: var(--teal); text-decoration: none; font-weight: 600;">Forgot?</a>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; height: 42px; justify-content: center; font-size: 14px; margin-top: 20px;">
                    Sign In
                    <i class="fa-solid fa-arrow-right" style="font-size: 12px; margin-left: 5px;"></i>
                </button>
            </form>

            <div style="margin-top: 30px; text-align: center; font-size: 11px; color: var(--ink-4); border-top: 1px solid var(--border-lt); padding-top: 20px;">
                Secure Employee Portal &copy; <?php echo date('Y'); ?>
            </div>
        </div>
    </div>
</body>
</html>
