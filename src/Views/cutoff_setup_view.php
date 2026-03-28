<?php
$pageTitle = 'Cutoff Configuration — Azzurro HR';
require BASE_PATH . '/partials/layout_head.php';
require BASE_PATH . '/partials/layout_topbar.php';
require BASE_PATH . '/partials/layout_sidebar.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-calendar-check"></i> Pay Period Lifecycle</h1>
        <p class="page-sub">Configure how the system segments monthly attendance</p>
    </div>
</div>

<div class="grid-2" style="grid-template-columns: 1fr 1.2fr; gap: 25px; align-items: start;">
    <div>
        <!-- CURRENT CONFIG -->
        <div class="card mb-20" style="background: linear-gradient(135deg, var(--teal-deep), var(--teal)); border: none; color: #fff;">
            <div class="card-body" style="padding: 25px;">
                <h3 style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; opacity: 0.8; margin-bottom: 20px;">Currently Active Policy</h3>
                <div class="grid-2">
                    <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1);">
                        <p style="font-size: 10px; font-weight: 700; opacity: 0.7; margin-bottom: 5px;">FIRST CUTOFF</p>
                        <p style="font-size: 20px; font-weight: 900; font-family: 'DM Mono';"><?php echo $currentConfig['cutoff_1_start']; ?> – <?php echo $currentConfig['cutoff_1_end']; ?></p>
                    </div>
                    <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.1);">
                        <p style="font-size: 10px; font-weight: 700; opacity: 0.7; margin-bottom: 5px;">SECOND CUTOFF</p>
                        <p style="font-size: 20px; font-weight: 900; font-family: 'DM Mono';"><?php echo $currentConfig['cutoff_2_start']; ?> – <?php echo $currentConfig['cutoff_2_end']; ?></p>
                    </div>
                </div>
                <p style="font-size: 11px; margin-top: 20px; opacity: 0.8;">
                    <i class="fa-solid fa-calendar-day"></i> Policy effective since: <b><?php echo $currentConfig['effective_date'] ? date('M d, Y', strtotime($currentConfig['effective_date'])) : 'System Default'; ?></b>
                </p>
            </div>
        </div>

        <!-- NEW CONFIG FORM -->
        <div class="card">
            <div class="card-head"><div class="card-title">Update Policy Configuration</div></div>
            <form id="configForm" class="card-body">
                <div class="section-hd"><span class="section-hd-label">Period Segmentation</span><div class="section-hd-line"></div></div>
                <div class="grid-2 mb-20">
                    <div class="form-group"><label class="form-label">Cutoff 1 Start (Day)</label><input type="number" name="cutoff_1_start" value="<?php echo $currentConfig['cutoff_1_start']; ?>" min="1" max="31" class="input-field"></div>
                    <div class="form-group"><label class="form-label">Cutoff 1 End (Day)</label><input type="number" name="cutoff_1_end" value="<?php echo $currentConfig['cutoff_1_end']; ?>" min="1" max="31" class="input-field"></div>
                    <div class="form-group"><label class="form-label">Cutoff 2 Start (Day)</label><input type="number" name="cutoff_2_start" value="<?php echo $currentConfig['cutoff_2_start']; ?>" min="1" max="31" class="input-field"></div>
                    <div class="form-group"><label class="form-label">Cutoff 2 End (Day)</label><input type="number" name="cutoff_2_end" value="<?php echo $currentConfig['cutoff_2_end']; ?>" min="1" max="31" class="input-field"></div>
                </div>
                
                <div class="form-group"><label class="form-label">Effective Deployment Date</label><input type="date" name="effective_date" value="<?php echo $nextMonth; ?>" class="input-field"></div>
                
                <div style="background: var(--amber-bg); border: 1px solid var(--amber-bdr); padding: 12px; border-radius: 8px; font-size: 11px; color: var(--amber); margin-bottom: 20px;">
                    <i class="fa-solid fa-triangle-exclamation"></i> <b>Wait!</b> Changes only apply to periods starting after the effective date. Past data is locked.
                </div>
                
                <button type="submit" class="btn-primary" style="width: 100%; justify-content: center; height: 40px;">Deploy New Policy</button>
            </form>
        </div>
    </div>

    <!-- HISTORY TABLE -->
    <div class="card">
        <div class="card-head"><div class="card-title">Policy Revision History</div></div>
        <div class="card-body" style="padding: 0;">
            <div class="table-wrap" style="border:none;">
                <table>
                    <thead>
                        <tr><th>Effective Date</th><th>Period 1</th><th>Period 2</th><th>Created</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history)): ?>
                            <tr><td colspan="4" style="text-align:center; padding:40px; color:var(--ink-4);">No historical revisions.</td></tr>
                        <?php else: foreach ($history as $h): ?>
                        <tr>
                            <td style="font-weight:700; color:var(--teal-deep);"><?php echo date('M d, Y', strtotime($h['effective_date'])); ?></td>
                            <td style="font-family:'DM Mono';"><?php echo $h['cutoff_1_start']; ?>–<?php echo $h['cutoff_1_end']; ?></td>
                            <td style="font-family:'DM Mono';"><?php echo $h['cutoff_2_start']; ?>–<?php echo $h['cutoff_2_end']; ?></td>
                            <td style="font-size:10px; color:var(--ink-4);"><?php echo date('M d, Y', strtotime($h['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$('#configForm').on('submit', function(e) {
    e.preventDefault();
    if (!confirm('Deploy new policy? This cannot be reversed for future periods.')) return;
    $.post('', $(this).serialize() + '&action=save', res => { if(res.success) location.reload(); else alert(res.message); }, 'json');
});
</script>

<?php require BASE_PATH . '/partials/layout_footer.php'; ?>
