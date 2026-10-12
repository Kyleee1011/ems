<?php
$pageTitle = 'Notifications — Azzurro HR';
require BASE_PATH . '/partials/layout_head.php';
require BASE_PATH . '/partials/layout_topbar.php';
require BASE_PATH . '/partials/layout_sidebar.php';
?>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px;">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-bell"></i> Notifications</h1>
        <p class="page-sub">System updates, approval alerts, and schedule notices</p>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
        <a href="<?php echo baseUrl('notifications'); ?>" class="btn <?php echo empty($_GET['filter']) ? 'btn-primary' : 'btn-ghost'; ?>" style="font-size: 13px;">
            All (<?php echo $totalCount; ?>)
        </a>
        <a href="<?php echo baseUrl('notifications?filter=unread'); ?>" class="btn <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'unread') ? 'btn-primary' : 'btn-ghost'; ?>" style="font-size: 13px;">
            Unread (<?php echo $unreadCount; ?>)
        </a>
        <?php if ($unreadCount > 0): ?>
            <button class="btn btn-secondary" onclick="markAllNotificationsRead()" style="font-size: 13px;">
                <i class="fa-solid fa-check-double"></i> Mark all read
            </button>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (empty($notifications)): ?>
            <div style="text-align: center; padding: 60px 20px; color: var(--text-muted, #888);">
                <i class="fa-regular fa-bell-slash" style="font-size: 40px; opacity: 0.4; margin-bottom: 12px; display: block;"></i>
                <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 6px;">No notifications</h3>
                <p style="font-size: 13px;">You are all caught up!</p>
            </div>
        <?php else: ?>
            <div class="notification-list">
                <?php foreach ($notifications as $n): ?>
                    <div class="notif-item <?php echo $n['is_read'] ? 'read' : 'unread'; ?>" 
                         id="notif-row-<?php echo $n['id']; ?>"
                         style="display: flex; align-items: flex-start; gap: 15px; padding: 16px 20px; border-bottom: 1px solid var(--border-color, #eee); transition: background 0.15s ease; <?php echo !$n['is_read'] ? 'background: rgba(30, 144, 255, 0.04);' : ''; ?>">
                        
                        <div style="font-size: 18px; margin-top: 2px; color: var(--teal, #008080); width: 24px; text-align: center;">
                            <?php 
                            $icon = 'fa-info-circle';
                            if (strpos($n['type'], 'SCHEDULE') !== false) $icon = 'fa-calendar-alt';
                            elseif (strpos($n['type'], 'LEAVE') !== false) $icon = 'fa-person-walking-arrow-right';
                            elseif (strpos($n['type'], 'OVERTIME') !== false) $icon = 'fa-business-time';
                            elseif (strpos($n['type'], 'LOAN') !== false) $icon = 'fa-hand-holding-dollar';
                            elseif (strpos($n['type'], 'DOCUMENT') !== false) $icon = 'fa-file-lines';
                            elseif (strpos($n['type'], 'PAYSLIP') !== false) $icon = 'fa-money-check-dollar';
                            ?>
                            <i class="fa-solid <?php echo $icon; ?>"></i>
                        </div>

                        <div style="flex: 1;">
                            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 4px;">
                                <div style="font-size: 14px; font-weight: <?php echo $n['is_read'] ? '500' : '700'; ?>; color: var(--text-color, #111);">
                                    <?php echo htmlspecialchars($n['title']); ?>
                                    <?php if (!$n['is_read']): ?>
                                        <span style="display: inline-block; width: 7px; height: 7px; border-radius: 50%; background: #007bff; margin-left: 6px; vertical-align: middle;"></span>
                                    <?php endif; ?>
                                </div>
                                <span style="font-size: 11px; color: var(--text-muted, #888); font-family: 'DM Mono', monospace;">
                                    <?php echo date('M d, Y h:i A', strtotime($n['created_at'])); ?>
                                </span>
                            </div>

                            <?php if (!empty($n['body'])): ?>
                                <p style="font-size: 13px; color: var(--text-muted, #555); margin-bottom: 8px; line-height: 1.4;">
                                    <?php echo nl2br(htmlspecialchars($n['body'])); ?>
                                </p>
                            <?php endif; ?>

                            <div style="display: flex; gap: 12px; align-items: center; margin-top: 6px;">
                                <?php if (!empty($n['link'])): ?>
                                    <a href="<?php echo htmlspecialchars(baseUrl(ltrim($n['link'], '/'))); ?>" 
                                       onclick="markNotificationRead(<?php echo $n['id']; ?>)"
                                       class="btn btn-sm btn-ghost" style="padding: 4px 10px; font-size: 12px;">
                                        View details <i class="fa-solid fa-arrow-right" style="font-size: 10px; margin-left: 4px;"></i>
                                    </a>
                                <?php endif; ?>

                                <?php if (!$n['is_read']): ?>
                                    <button onclick="markNotificationRead(<?php echo $n['id']; ?>)" 
                                            class="btn btn-sm btn-ghost" style="padding: 4px 8px; font-size: 11px; opacity: 0.7;">
                                        <i class="fa-solid fa-check"></i> Mark read
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div style="display: flex; justify-content: center; gap: 8px; padding: 18px 20px;">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a href="<?php echo baseUrl('notifications?page=' . $p . (!empty($_GET['filter']) ? '&filter=' . urlencode($_GET['filter']) : '')); ?>" 
                           class="btn btn-sm <?php echo $p == $page ? 'btn-primary' : 'btn-ghost'; ?>" style="min-width: 34px; text-align: center;">
                            <?php echo $p; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
function markNotificationRead(id) {
    $.post('<?php echo baseUrl("notifications"); ?>', {
        action: 'mark_read',
        notification_id: id
    }, function(res) {
        if (res && res.success) {
            $('#notif-row-' + id).css('background', 'transparent').removeClass('unread').addClass('read');
            $('#notif-row-' + id + ' span[style*="border-radius: 50%"]').remove();
            if (typeof updateUnreadBadge === 'function') {
                updateUnreadBadge();
            }
        }
    });
}

function markAllNotificationsRead() {
    $.post('<?php echo baseUrl("notifications"); ?>', {
        action: 'mark_all_read'
    }, function(res) {
        if (res && res.success) {
            location.reload();
        }
    });
}
</script>

<?php require BASE_PATH . '/partials/layout_footer.php'; ?>
