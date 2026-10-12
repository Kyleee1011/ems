<?php
$pageTitle = 'System Audit Trail — Azzurro HR';
require BASE_PATH . '/partials/layout_head.php';
require BASE_PATH . '/partials/layout_topbar.php';
require BASE_PATH . '/partials/layout_sidebar.php';
?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:20px;">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-shield-halved"></i> System Audit Trail</h1>
        <p class="page-sub">Comprehensive immutable log of system transactions, authentication, and state changes</p>
    </div>
    <div style="display:flex; gap:10px;">
        <a href="<?php echo baseUrl('audit-logs?action=export_csv'); ?>" class="btn btn-secondary" style="font-size:12px; height:36px; padding:0 14px; display:inline-flex; align-items:center; gap:6px;">
            <i class="fa-solid fa-file-csv" style="color:var(--teal);"></i> Export CSV
        </a>
    </div>
</div>

<!-- FILTER CARD -->
<div class="card mb-20" style="padding:16px 20px;">
    <form method="GET" action="<?php echo baseUrl('audit-logs'); ?>" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)) 120px; gap:12px; align-items:flex-end;">
        <div>
            <label class="form-label" style="font-size:10px;">SEARCH KEYWORD</label>
            <input type="text" name="search" value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>" placeholder="Action, Entity, ID..." class="input-field" style="height:34px; font-size:12px;">
        </div>
        <div>
            <label class="form-label" style="font-size:10px;">ACTION TYPE</label>
            <select name="filter_action" class="input-field" style="height:34px; font-size:12px;">
                <option value="">All Actions</option>
                <?php foreach ($distinctActions as $act): ?>
                    <option value="<?php echo htmlspecialchars($act); ?>" <?php echo ($filters['action'] === $act) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($act); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" style="font-size:10px;">ENTITY TYPE</label>
            <select name="filter_entity" class="input-field" style="height:34px; font-size:12px;">
                <option value="">All Entities</option>
                <?php foreach ($distinctEntities as $ent): ?>
                    <option value="<?php echo htmlspecialchars($ent); ?>" <?php echo ($filters['entity_type'] === $ent) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($ent); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="form-label" style="font-size:10px;">FROM DATE</label>
            <input type="date" name="date_from" value="<?php echo htmlspecialchars($filters['date_from'] ?? ''); ?>" class="input-field" style="height:34px; font-size:12px;">
        </div>
        <div>
            <label class="form-label" style="font-size:10px;">TO DATE</label>
            <input type="date" name="date_to" value="<?php echo htmlspecialchars($filters['date_to'] ?? ''); ?>" class="input-field" style="height:34px; font-size:12px;">
        </div>
        <div style="display:flex; gap:6px;">
            <button type="submit" class="btn btn-primary" style="height:34px; flex:1; font-size:12px;">Filter</button>
            <a href="<?php echo baseUrl('audit-logs'); ?>" class="btn btn-ghost" title="Reset Filters" style="height:34px; padding:0 10px; display:inline-flex; align-items:center; justify-content:center; border:1px solid var(--border-color, #ccc);">
                <i class="fa-solid fa-rotate-left"></i>
            </a>
        </div>
    </form>
</div>

<!-- AUDIT LOG TABLE -->
<div class="card">
    <div class="card-head" style="padding:14px 20px; border-bottom:1px solid var(--border-color, #eee); display:flex; justify-content:space-between; align-items:center;">
        <div style="font-size:13px; font-weight:700; color:var(--text-color, #111);">
            <i class="fa-solid fa-list-check" style="color:var(--teal); margin-right:6px;"></i> Audit Events (<?php echo number_format($totalLogs); ?> total)
        </div>
        <div style="font-size:11px; color:var(--text-muted, #777);">
            Page <?php echo $page; ?> of <?php echo max(1, $totalPages); ?>
        </div>
    </div>

    <div class="table-wrap" style="border:none; margin:0;">
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="background:var(--bg-subtle, #f8fafc); border-bottom:1px solid var(--border-color, #eee);">
                    <th style="padding:10px 16px; font-size:10.5px; text-transform:uppercase; text-align:left;">Timestamp</th>
                    <th style="padding:10px 16px; font-size:10.5px; text-transform:uppercase; text-align:left;">Actor</th>
                    <th style="padding:10px 16px; font-size:10.5px; text-transform:uppercase; text-align:left;">Action</th>
                    <th style="padding:10px 16px; font-size:10.5px; text-transform:uppercase; text-align:left;">Entity</th>
                    <th style="padding:10px 16px; font-size:10.5px; text-transform:uppercase; text-align:left;">Client Details</th>
                    <th style="padding:10px 16px; font-size:10.5px; text-transform:uppercase; text-align:right;">Diff / Payload</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:50px; color:var(--text-muted, #888);">
                            <i class="fa-solid fa-shield-virus" style="font-size:32px; display:block; margin-bottom:10px; opacity:0.3;"></i>
                            No audit log records match the selected filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr style="border-bottom:1px solid var(--border-color, #f1f5f9);">
                            <td style="padding:12px 16px; white-space:nowrap;">
                                <div style="font-weight:600; font-size:12px; color:var(--text-color, #111);">
                                    <?php echo date('M d, Y', strtotime($log['timestamp'])); ?>
                                </div>
                                <div style="font-size:10.5px; color:var(--text-muted, #888); font-family:'DM Mono', monospace;">
                                    <?php echo date('h:i:s A', strtotime($log['timestamp'])); ?>
                                </div>
                            </td>

                            <td style="padding:12px 16px;">
                                <div style="font-weight:700; font-size:12px; color:var(--text-color, #111);">
                                    <?php echo htmlspecialchars($log['actor_name'] ?? 'System / Anonymous'); ?>
                                </div>
                                <div style="display:flex; gap:6px; margin-top:2px; align-items:center;">
                                    <?php if (!empty($log['actor_ac_no'])): ?>
                                        <span class="tag tag-blue" style="font-size:9px; padding:1px 5px; font-family:'DM Mono';">
                                            AC: <?php echo htmlspecialchars($log['actor_ac_no']); ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="tag tag-purple" style="font-size:9px; padding:1px 5px;">
                                        <?php echo htmlspecialchars($log['actor_role'] ?? 'Guest'); ?>
                                    </span>
                                </div>
                            </td>

                            <td style="padding:12px 16px;">
                                <?php 
                                $act = $log['action'];
                                $badgeClass = 'tag-blue';
                                if (strpos($act, 'DELETE') !== false || strpos($act, 'FAILED') !== false || strpos($act, 'REJECT') !== false) $badgeClass = 'tag-red';
                                elseif (strpos($act, 'CREATE') !== false || strpos($act, 'SUCCESS') !== false || strpos($act, 'APPROV') !== false) $badgeClass = 'tag-green';
                                elseif (strpos($act, 'UPDATE') !== false || strpos($act, 'SUBMIT') !== false) $badgeClass = 'tag-amber';
                                ?>
                                <span class="tag <?php echo $badgeClass; ?>" style="font-weight:700; font-size:10px; padding:3px 8px;">
                                    <?php echo htmlspecialchars($act); ?>
                                </span>
                            </td>

                            <td style="padding:12px 16px;">
                                <div style="font-size:11px; font-weight:600; text-transform:uppercase; color:var(--teal, #008080);">
                                    <?php echo htmlspecialchars($log['entity_type']); ?>
                                </div>
                                <div style="font-size:10.5px; color:var(--text-muted, #666); font-family:'DM Mono';">
                                    ID: <?php echo htmlspecialchars($log['entity_id'] ?? '-'); ?>
                                </div>
                            </td>

                            <td style="padding:12px 16px; font-size:11px; color:var(--text-muted, #666);">
                                <div style="font-family:'DM Mono';">IP: <?php echo htmlspecialchars($log['ip_address'] ?? '127.0.0.1'); ?></div>
                                <div style="font-size:9.5px; color:var(--ink-4); overflow:hidden; text-overflow:ellipsis; max-width:180px; white-space:nowrap;" title="<?php echo htmlspecialchars($log['user_agent'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($log['user_agent'] ?? 'Unknown'); ?>
                                </div>
                            </td>

                            <td style="padding:12px 16px; text-align:right;">
                                <?php if (!empty($log['before_data']) || !empty($log['after_data'])): ?>
                                    <button type="button" 
                                            class="btn btn-sm btn-ghost" 
                                            onclick='openDiffModal(<?php echo json_encode($log, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'
                                            style="padding:4px 10px; font-size:11px; border:1px solid var(--border-color, #ccc);">
                                        <i class="fa-solid fa-code-compare"></i> View Payload
                                    </button>
                                <?php else: ?>
                                    <span style="color:var(--text-muted, #aaa); font-size:11px;">No payload</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($totalPages > 1): ?>
        <div style="padding:14px 20px; border-top:1px solid var(--border-color, #eee); display:flex; justify-content:center; gap:6px;">
            <?php 
            $queryBase = $_GET;
            for ($p = 1; $p <= $totalPages; $p++):
                $queryBase['page'] = $p;
                $url = baseUrl('audit-logs?' . http_build_query($queryBase));
            ?>
                <a href="<?php echo $url; ?>" class="btn btn-sm <?php echo $p == $page ? 'btn-primary' : 'btn-ghost'; ?>" style="min-width:32px; text-align:center;">
                    <?php echo $p; ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>

<!-- PAYLOAD DIFF MODAL -->
<div id="diffModalBackdrop" class="modal-overlay" style="position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:1000; display:none;" onclick="closeDiffModal()"></div>
<div id="diffModal" class="modal-container" style="position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:680px; max-width:95vw; background:var(--bg-card, #fff); border-radius:16px; border:1px solid var(--border-color, #ccc); box-shadow:0 12px 36px rgba(0,0,0,0.25); z-index:1001; display:none; flex-direction:column; max-height:85vh;">
    <div style="padding:16px 20px; border-bottom:1px solid var(--border-color, #eee); display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h3 style="font-size:14px; font-weight:700; color:var(--text-color, #111);" id="modalTitle">Audit Payload Details</h3>
            <span style="font-size:11px; color:var(--text-muted, #777);" id="modalSubtitle"></span>
        </div>
        <button onclick="closeDiffModal()" style="background:none; border:none; cursor:pointer; font-size:18px; color:#888;">&times;</button>
    </div>
    
    <div style="padding:20px; overflow-y:auto; flex:1;">
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div>
                <div style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--red); margin-bottom:6px;">
                    <i class="fa-solid fa-arrow-left"></i> Before State
                </div>
                <pre id="beforeJson" style="background:#1e293b; color:#e2e8f0; padding:12px; border-radius:8px; font-size:11px; font-family:'DM Mono', monospace; overflow:auto; max-height:360px; min-height:120px;"></pre>
            </div>
            <div>
                <div style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--teal); margin-bottom:6px;">
                    <i class="fa-solid fa-arrow-right"></i> After / New State
                </div>
                <pre id="afterJson" style="background:#1e293b; color:#e2e8f0; padding:12px; border-radius:8px; font-size:11px; font-family:'DM Mono', monospace; overflow:auto; max-height:360px; min-height:120px;"></pre>
            </div>
        </div>
    </div>

    <div style="padding:12px 20px; border-top:1px solid var(--border-color, #eee); display:flex; justify-content:flex-end;">
        <button onclick="closeDiffModal()" class="btn btn-primary" style="font-size:12px; height:32px; padding:0 16px;">Close</button>
    </div>
</div>

<script>
function openDiffModal(log) {
    document.getElementById('modalTitle').textContent = `Action: ${log.action}`;
    document.getElementById('modalSubtitle').textContent = `Entity: ${log.entity_type} #${log.entity_id || '-'} | Request: ${log.request_id || '-'}`;
    
    let beforeObj = null;
    let afterObj = null;
    try { if (log.before_data) beforeObj = JSON.parse(log.before_data); } catch(e) {}
    try { if (log.after_data) afterObj = JSON.parse(log.after_data); } catch(e) {}

    document.getElementById('beforeJson').textContent = beforeObj ? JSON.stringify(beforeObj, null, 2) : '(none)';
    document.getElementById('afterJson').textContent = afterObj ? JSON.stringify(afterObj, null, 2) : '(none)';

    document.getElementById('diffModal').style.display = 'flex';
    document.getElementById('diffModalBackdrop').style.display = 'block';
}

function closeDiffModal() {
    document.getElementById('diffModal').style.display = 'none';
    document.getElementById('diffModalBackdrop').style.display = 'none';
}
</script>

<?php require BASE_PATH . '/partials/layout_footer.php'; ?>
