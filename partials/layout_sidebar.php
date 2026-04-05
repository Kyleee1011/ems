<?php
$current_role = $_SESSION['role'] ?? 'Employee';
$approval_role = $_SESSION['approval_role'] ?? 'Employee';
$dept_id = $_SESSION['dept_id'] ?? 0;
$is_hr = (strcasecmp($approval_role, 'HR') === 0 || $dept_id == 5);
$is_ceo = (strcasecmp($approval_role, 'CEO') === 0);
$is_dept_head = (strcasecmp($approval_role, 'Dept Head') === 0 || strcasecmp($approval_role, 'DeptHead') === 0);

$current_page = basename($_SERVER['PHP_SELF'], '.php');
// In this system, URI-based routing is used, so we might need a better way to detect active page
$uri = $_SERVER['REQUEST_URI'];
?>
<aside class="sidebar">
    <div class="sidebar-head">
        <div class="sidebar-logo">Azzurro<em>HR</em></div>
        <div class="sidebar-tagline">Management System</div>
    </div>

    <div class="nav-group">
        <div class="nav-label">Core</div>
        <a href="<?php echo baseUrl('home'); ?>" class="nav-item <?php echo (strpos($uri, '/home') !== false) ? 'active' : ''; ?>" title="Home Feed">
            <div class="nav-ico"><i class="fa-solid fa-house"></i></div>
            <span>Home Feed</span>
        </a>
        
        <?php if ($is_hr || $is_ceo): ?>
        <a href="<?php echo baseUrl(getDashboardUrl()); ?>" class="nav-item <?php echo (strpos($uri, '/dashboard') !== false || strpos($uri, '/ceodashboard') !== false) ? 'active' : ''; ?>" title="Dashboard">
            <div class="nav-ico"><i class="fa-solid fa-chart-line"></i></div>
            <span><?php echo $is_ceo ? 'CEO Dashboard' : 'HR Dashboard'; ?></span>
        </a>
        <?php endif; ?>
    </div>

    <div class="nav-group">
        <div class="nav-label">Self Service</div>
        <a href="<?php echo baseUrl('timecard'); ?>" class="nav-item <?php echo (strpos($uri, '/timecard') !== false) ? 'active' : ''; ?>" title="My Timecard">
            <div class="nav-ico"><i class="fa-solid fa-clock-rotate-left"></i></div>
            <span>My Timecard</span>
        </a>
        <a href="<?php echo baseUrl('schedule'); ?>" class="nav-item <?php echo (strpos($uri, '/schedule') !== false && strpos($uri, 'dept_id') === false) ? 'active' : ''; ?>" title="My Schedule">
            <div class="nav-ico"><i class="fa-solid fa-calendar-days"></i></div>
            <span>My Schedule</span>
        </a>
        <a href="<?php echo baseUrl('leave'); ?>" class="nav-item <?php echo (strpos($uri, '/leave') !== false) ? 'active' : ''; ?>" title="Leave Request">
            <div class="nav-ico"><i class="fa-solid fa-calendar-plus"></i></div>
            <span>Leave Request</span>
        </a>
        <a href="<?php echo baseUrl('overtime'); ?>" class="nav-item <?php echo (strpos($uri, '/overtime') !== false) ? 'active' : ''; ?>" title="Overtime Request">
            <div class="nav-ico"><i class="fa-solid fa-business-time"></i></div>
            <span>Overtime Request</span>
        </a>
        <a href="<?php echo baseUrl('payslip'); ?>" class="nav-item <?php echo (strpos($uri, '/payslip') !== false) ? 'active' : ''; ?>" title="My Payslips">
            <div class="nav-ico"><i class="fa-solid fa-file-invoice-dollar"></i></div>
            <span>My Payslips</span>
        </a>
        <a href="<?php echo baseUrl('loan'); ?>" class="nav-item <?php echo (strpos($uri, '/loan') !== false) ? 'active' : ''; ?>" title="My Loans">
            <div class="nav-ico"><i class="fa-solid fa-hand-holding-dollar"></i></div>
            <span>My Loans</span>
        </a>
    </div>

    <?php if ($is_hr || $is_ceo): ?>
    <div class="nav-group">
        <div class="nav-label">Management</div>
        <a href="<?php echo baseUrl('employee'); ?>" class="nav-item <?php echo (strpos($uri, '/employee') !== false) ? 'active' : ''; ?>" title="Employee Directory">
            <div class="nav-ico"><i class="fa-solid fa-users"></i></div>
            <span>Employee Directory</span>
        </a>
        
        <div class="nav-item has-sub" onclick="this.nextElementSibling.classList.toggle('open')" title="Payroll & Benefits">
            <div class="nav-ico"><i class="fa-solid fa-coins"></i></div>
            <span>Payroll & Benefits</span>
            <i class="fa-solid fa-chevron-down nav-caret"></i>
        </div>
        <div class="nav-sub">
            <a href="<?php echo baseUrl('cutoff-setup'); ?>" class="nav-sub-item <?php echo (strpos($uri, '/cutoff-setup') !== false) ? 'active' : ''; ?>">Cutoff Setup</a>
            <a href="<?php echo baseUrl('payroll-calculator'); ?>" class="nav-sub-item <?php echo (strpos($uri, '/payroll-calculator') !== false) ? 'active' : ''; ?>">Payroll Calc</a>
            <a href="<?php echo baseUrl('allowances'); ?>" class="nav-sub-item <?php echo (strpos($uri, '/allowances') !== false) ? 'active' : ''; ?>">Allowances</a>
            <a href="<?php echo baseUrl('timecard?action=audit'); ?>" class="nav-sub-item <?php echo (strpos($uri, 'action=audit') !== false) ? 'active' : ''; ?>">Biometric Audit</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="sidebar-footer">
        <div class="avatar">
            <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)); ?>
        </div>
        <div class="sf-info">
            <div class="sf-name"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></div>
            <div class="sf-dept"><?php echo htmlspecialchars($current_role); ?></div>
        </div>
        <a href="<?php echo baseUrl('logout'); ?>" class="logout-btn" title="Sign Out">
            <i class="fa-solid fa-power-off"></i>
        </a>
    </div>
</aside>
<main class="main">
