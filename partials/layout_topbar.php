<header class="topbar">
    <a href="<?php echo baseUrl('home'); ?>" class="brand">
        <div class="brand-mark">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
            </svg>
        </div>
        <div class="brand-name">Azzurro<em>HR</em></div>
    </a>

    <div class="spacer"></div>

    <div class="topbar-right">
        <button class="theme-toggle" id="themeToggle">
            <i class="fa-solid fa-moon"></i>
            <span>Dark</span>
        </button>

        <div class="user-pill" onclick="window.location.href='<?php echo baseUrl('home'); ?>'">
            <div class="avatar">
                <?php 
                $initials = 'U';
                if (isset($_SESSION['full_name'])) {
                    $names = explode(' ', $_SESSION['full_name']);
                    $initials = substr($names[0], 0, 1) . (isset($names[1]) ? substr($names[1], 0, 1) : '');
                }
                echo strtoupper($initials);
                ?>
            </div>
            <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></div>
        </div>
    </div>
</header>
<div class="app">
