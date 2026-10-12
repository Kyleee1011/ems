<!-- Notification Bell Component -->
<div class="notif-dropdown-wrapper" style="position: relative; margin-right: 8px;">
    <button type="button" class="notif-bell-btn" id="notifBellBtn" title="Notifications"
            style="position: relative; background: transparent; border: 1px solid var(--border-color, rgba(0,0,0,0.1)); border-radius: 999px; width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text-color, #333); transition: all 0.2s ease;">
        <i class="fa-solid fa-bell" style="font-size: 15px;"></i>
        <span class="notif-badge" id="notifBadge" 
              style="display: none; position: absolute; top: -2px; right: -2px; background: #e63946; color: #fff; font-size: 10px; font-weight: 700; min-width: 17px; height: 17px; line-height: 17px; border-radius: 9px; text-align: center; padding: 0 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">0</span>
    </button>

    <!-- Dropdown Panel -->
    <div class="notif-panel" id="notifPanel"
         style="display: none; position: absolute; right: 0; top: 48px; width: 340px; background: var(--bg-card, #ffffff); border: 1px solid var(--border-color, #e0e0e0); border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); z-index: 1000; overflow: hidden; animation: notifFadeIn 0.15s ease-out;">
        
        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--border-color, #eee); background: var(--bg-subtle, #fafafa);">
            <div style="font-size: 13px; font-weight: 700; color: var(--text-color, #111);">
                <i class="fa-solid fa-bell" style="color: var(--teal, #008080); margin-right: 6px;"></i>Notifications
            </div>
            <button type="button" onclick="markAllNotifsRead()" 
                    style="background: none; border: none; font-size: 11px; color: var(--teal, #008080); cursor: pointer; font-weight: 600; padding: 0;">
                Mark all read
            </button>
        </div>

        <!-- Scrollable List -->
        <div id="notifList" style="max-height: 360px; overflow-y: auto; overscroll-behavior: contain;">
            <div style="padding: 24px; text-align: center; color: var(--text-muted, #888); font-size: 12px;">
                <i class="fa-solid fa-spinner fa-spin" style="margin-right: 6px;"></i> Loading...
            </div>
        </div>

        <!-- Footer -->
        <div style="padding: 10px 16px; border-top: 1px solid var(--border-color, #eee); text-align: center; background: var(--bg-subtle, #fafafa);">
            <a href="<?php echo baseUrl('notifications'); ?>" 
               style="font-size: 12px; font-weight: 600; color: var(--teal, #008080); text-decoration: none;">
                View all notifications <i class="fa-solid fa-arrow-right" style="font-size: 10px; margin-left: 4px;"></i>
            </a>
        </div>
    </div>
</div>

<style>
@keyframes notifFadeIn {
    from { opacity: 0; transform: translateY(-6px); }
    to { opacity: 1; transform: translateY(0); }
}
.notif-bell-btn:hover {
    background: var(--bg-subtle, rgba(0,0,0,0.04));
    transform: scale(1.03);
}
.notif-row-item {
    display: flex;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--border-color, #f0f0f0);
    text-decoration: none;
    color: inherit;
    transition: background 0.15s ease;
    cursor: pointer;
}
.notif-row-item:hover {
    background: var(--bg-subtle, rgba(0,0,0,0.03));
}
.notif-row-item.unread {
    background: rgba(30, 144, 255, 0.05);
}
</style>

<script>
(function() {
    const notifBtn = document.getElementById('notifBellBtn');
    const notifPanel = document.getElementById('notifPanel');
    const notifBadge = document.getElementById('notifBadge');
    const notifList = document.getElementById('notifList');
    let isPanelOpen = false;

    function formatTimeAgo(dateStr) {
        const d = new Date(dateStr);
        const now = new Date();
        const diffSec = Math.floor((now - d) / 1000);
        if (diffSec < 60) return 'Just now';
        if (diffSec < 3600) return Math.floor(diffSec / 60) + 'm ago';
        if (diffSec < 86400) return Math.floor(diffSec / 3600) + 'h ago';
        return Math.floor(diffSec / 86400) + 'd ago';
    }

    function updateBadge() {
        if (document.hidden) return; // Pause when tab is inactive
        $.get('<?php echo baseUrl("notifications"); ?>', { action: 'unread_count' }, function(res) {
            if (res && res.success) {
                const count = res.count || 0;
                if (count > 0) {
                    notifBadge.textContent = count > 99 ? '99+' : count;
                    notifBadge.style.display = 'inline-block';
                } else {
                    notifBadge.style.display = 'none';
                }
            }
        });
    }
    window.updateUnreadBadge = updateBadge;

    function loadDropdownList() {
        $.get('<?php echo baseUrl("notifications"); ?>', { action: 'list', limit: 7 }, function(res) {
            if (res && res.success && res.data) {
                const items = res.data.items || [];
                if (items.length === 0) {
                    notifList.innerHTML = '<div style="padding: 30px 16px; text-align: center; color: var(--text-muted, #888); font-size: 12px;"><i class="fa-regular fa-bell-slash" style="font-size: 24px; display: block; margin-bottom: 8px; opacity: 0.5;"></i>No notifications</div>';
                    return;
                }

                let html = '';
                items.forEach(function(item) {
                    let icon = 'fa-info-circle';
                    if (item.type.includes('SCHEDULE')) icon = 'fa-calendar-alt';
                    else if (item.type.includes('LEAVE')) icon = 'fa-person-walking-arrow-right';
                    else if (item.type.includes('OVERTIME')) icon = 'fa-business-time';
                    else if (item.type.includes('LOAN')) icon = 'fa-hand-holding-dollar';
                    else if (item.type.includes('DOCUMENT')) icon = 'fa-file-lines';
                    else if (item.type.includes('PAYSLIP')) icon = 'fa-money-check-dollar';

                    const unreadClass = item.is_read == 0 ? 'unread' : '';
                    const linkUrl = item.link ? '<?php echo baseUrl(); ?>/' + item.link.replace(/^\//, '') : 'javascript:void(0)';

                    html += `<div class="notif-row-item ${unreadClass}" onclick="handleNotifClick(${item.id}, '${linkUrl}')">
                        <div style="font-size: 15px; color: var(--teal, #008080); margin-top: 2px;">
                            <i class="fa-solid ${icon}"></i>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; justify-content: space-between; align-items: baseline; gap: 8px;">
                                <div style="font-size: 12px; font-weight: ${item.is_read == 0 ? '700' : '500'}; color: var(--text-color, #111); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    ${escapeHtml(item.title)}
                                </div>
                                <span style="font-size: 10px; color: var(--text-muted, #888); white-space: nowrap;">
                                    ${formatTimeAgo(item.created_at)}
                                </span>
                            </div>
                            ${item.body ? `<div style="font-size: 11px; color: var(--text-muted, #666); margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(item.body)}</div>` : ''}
                        </div>
                    </div>`;
                });
                notifList.innerHTML = html;
            }
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    window.handleNotifClick = function(id, link) {
        $.post('<?php echo baseUrl("notifications"); ?>', { action: 'mark_read', notification_id: id }, function() {
            updateBadge();
            if (link && link !== 'javascript:void(0)') {
                window.location.href = link;
            } else {
                loadDropdownList();
            }
        });
    };

    window.markAllNotifsRead = function() {
        $.post('<?php echo baseUrl("notifications"); ?>', { action: 'mark_all_read' }, function(res) {
            updateBadge();
            loadDropdownList();
        });
    };

    // Toggle dropdown
    notifBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        isPanelOpen = !isPanelOpen;
        if (isPanelOpen) {
            notifPanel.style.display = 'block';
            loadDropdownList();
        } else {
            notifPanel.style.display = 'none';
        }
    });

    // Close on outside click
    document.addEventListener('click', function(e) {
        if (isPanelOpen && !notifPanel.contains(e.target) && !notifBtn.contains(e.target)) {
            notifPanel.style.display = 'none';
            isPanelOpen = false;
        }
    });

    // Initial check & 30s polling
    updateBadge();
    setInterval(updateBadge, 30000);

    // Refresh on tab become visible
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) updateBadge();
    });
})();
</script>
