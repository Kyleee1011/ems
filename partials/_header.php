<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRCore | Employee Management</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['"Plus Jakarta Sans"', 'sans-serif'] },
                    colors: {
                        border: 'hsl(var(--border))',
                        background: 'hsl(var(--background))',
                        foreground: 'hsl(var(--foreground))',
                        primary: {
                            DEFAULT: '#a3e635',
                            foreground: '#1F1F1F',
                            50: '#f7fee7',
                            100: '#ecfccb',
                            500: '#84cc16',
                            600: '#65a30d',
                        },
                        muted: {
                            DEFAULT: '#f3f4f6',
                            foreground: '#6b7280'
                        },
                        card: {
                            DEFAULT: '#ffffff',
                            foreground: '#374151'
                        }
                    },
                    borderRadius: {
                        lg: 'var(--radius)',
                        md: 'calc(var(--radius) - 2px)',
                        sm: 'calc(var(--radius) - 4px)',
                    }
                }
            }
        }
    </script>
    <style>
        :root {
            --background: 0 0% 100%;
            --foreground: 0 0% 12.5%;
            --card: 0 0% 100%;
            --card-foreground: 0 0% 37%;
            --primary: 133 76% 59%;
            --primary-foreground: 0 0% 12.5%;
            --muted: 0 0% 96%;
            --muted-foreground: 0 0% 45%;
            --border: 0 0% 90%;
            --radius: 0.625rem;
        }
        .fade-in { animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .nav-link { color: #6b7280; font-weight: 500; padding: 0.5rem 1rem; border-radius: var(--radius); transition: all 0.2s; }
        .nav-link:hover { color: #111827; background-color: #f3f4f6; }
        .nav-link.active { background-color: rgba(163, 230, 53, 0.2); color: #1a2e05; font-weight: 700; }

        .stat-card {
            background-color: hsl(var(--card));
            border: 1px solid hsl(var(--border));
            border-radius: var(--radius);
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .input-field { width: 100%; border-radius: 0.5rem; border: 1px solid #d1d5db; padding: 0.5rem 0.75rem; font-size: 0.875rem; outline: none; transition: all; }
        .input-field:focus { border-color: #84cc16; box-shadow: 0 0 0 2px rgba(132, 204, 22, 0.2); }
        .label-text { display: block; font-size: 0.75rem; font-weight: 700; color: #4b5563; margin-bottom: 0.25rem; text-transform: uppercase; letter-spacing: 0.025em; }
        
        .subtab-btn { color: #6b7280; font-weight: 600; font-size: 0.875rem; padding: 0.5rem 1rem; border-radius: 0.5rem; transition: all 0.2s; border: 1px solid transparent; }
        .subtab-btn:hover { color: #111827; background-color: #f3f4f6; }
        .subtab-btn.active { background-color: white; color: #1a2e05; border-color: #e5e7eb; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
    </style>
</head>
<body class="bg-gray-50 text-slate-600 font-sans min-h-screen flex flex-col">

    <header class="bg-white/80 backdrop-blur-md border-b border-border sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <div class="flex items-center gap-12">
                <a href="dashboard.php" class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-primary text-primary-foreground flex items-center justify-center text-lg"><i class="fa-solid fa-layer-group"></i></div>
                    <span class="font-bold text-xl text-gray-900 tracking-tight">HRCore</span>
                </a>
                <nav class="hidden md:flex gap-2 text-sm">
                    <a href="home.php" class="flex items-center gap-2 text-gray-500 hover:text-gray-900 transition-colors font-medium text-sm">
                        <i class="fa-solid fa-arrow-left"></i> Return to Home
                    </a>
                    <?php $currentPage = basename($_SERVER['PHP_SELF']); ?>
                    <?php $activeTab = $_GET['tab'] ?? 'dashboard'; ?>
                    <a href="dashboard.php" class="nav-link <?php echo ($currentPage == 'dashboard.php' && $activeTab == 'dashboard') ? 'active' : ''; ?>">Overview</a>
                    <a href="employee.php" class="nav-link <?php echo ($currentPage == 'employee.php') ? 'active' : ''; ?>">Employees</a>
                    <a href="dashboard.php?tab=approvals" class="nav-link relative <?php echo ($currentPage == 'dashboard.php' && $activeTab == 'approvals') ? 'active' : ''; ?>">
                        Approvals <?php if(isset($totalPending) && $totalPending > 0): ?><span class="absolute top-0.5 right-0 w-2 h-2 bg-red-500 rounded-full"></span><?php endif; ?>
                    </a>
                    <a href="dashboard.php?tab=global_schedule" class="nav-link <?php echo ($currentPage == 'dashboard.php' && $activeTab == 'global_schedule') ? 'active' : ''; ?>">Global Schedule</a>
                    <a href="dashboard.php?tab=payroll" class="nav-link <?php echo ($currentPage == 'dashboard.php' && $activeTab == 'payroll') ? 'active' : ''; ?>">Payroll</a>
                    <a href="loan.php" class="nav-link <?php echo ($currentPage == 'loan.php') ? 'active' : ''; ?>">Loans</a>
                </nav>
            </div>
            <div class="flex items-center gap-4">
                <div class="flex flex-col text-right">
                    <span class="text-sm font-bold text-gray-900 leading-none"><?php echo htmlspecialchars($current_fullname); ?></span>
                    <span class="text-[10px] text-gray-500 font-medium uppercase"><?php echo substr($user_role,0,20); ?></span>
                </div>
                <div class="w-9 h-9 rounded-full bg-gray-100 border border-gray-200 text-gray-600 flex items-center justify-center text-xs font-bold">
                    <?php echo substr($current_fullname, 0, 1); ?>
                </div>
            </div>
        </div>
    </header>