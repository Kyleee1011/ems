<?php
$pageTitle = 'Loan Management — Azzurro HR';
require BASE_PATH . '/partials/layout_head.php';
require BASE_PATH . '/partials/layout_topbar.php';
require BASE_PATH . '/partials/layout_sidebar.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-hand-holding-dollar"></i> Financial Ledger</h1>
        <p class="page-sub"><?php echo ($is_hr || $is_ceo) ? 'Employee loan administration and approvals' : 'My personal loan records and requests'; ?></p>
    </div>
    <div class="header-actions">
        <button onclick="openModal()" class="btn-primary"><i class="fa-solid fa-plus"></i> <?php echo ($is_hr || $is_ceo) ? 'Grant New Loan' : 'Request Loan'; ?></button>
    </div>
</div>

<?php if ($is_hr || $is_ceo): ?>
    <!-- ADMIN STATS -->
    <div class="stat-grid">
        <div class="stat-card c-teal">
            <div class="stat-ico-wrap ico-teal"><i class="fa-solid fa-piggy-bank"></i></div>
            <div class="stat-lbl">Active Ledger</div>
            <div class="stat-val teal"><?php echo number_format($totalActive); ?></div>
            <div class="stat-meta">Active employee loans</div>
        </div>
        <div class="stat-card c-amber">
            <div class="stat-ico-wrap ico-amber"><i class="fa-solid fa-hourglass-half"></i></div>
            <div class="stat-lbl">Pending Review</div>
            <div class="stat-val amber"><?php echo number_format($totalPending); ?></div>
            <div class="stat-meta">Awaiting your approval</div>
        </div>
        <div class="stat-card c-blue">
            <div class="stat-ico-wrap ico-blue"><i class="fa-solid fa-coins"></i></div>
            <div class="stat-lbl">Total Receivables</div>
            <div class="stat-val blue">₱<?php echo number_format($totalReceivable / 1000, 1); ?>k</div>
            <div class="stat-meta">Outstanding principal</div>
        </div>
    </div>

    <!-- ADMIN TABS -->
    <div class="flex-row mb-20 mt-20" style="border-bottom: 1px solid var(--border-lt); gap: 20px;">
        <a href="?tab=pending" class="nav-item <?php echo $active_tab === 'pending' ? 'active' : ''; ?>" style="padding: 10px 5px; border-bottom: 2px solid <?php echo $active_tab === 'pending' ? 'var(--teal)' : 'transparent'; ?>; background: none;">
            Approvals Queued <?php if(count($pendingLoans) > 0): ?><span class="tag tag-red" style="margin-left:5px;"><?php echo count($pendingLoans); ?></span><?php endif; ?>
        </a>
        <a href="?tab=all_loans" class="nav-item <?php echo $active_tab === 'all_loans' ? 'active' : ''; ?>" style="padding: 10px 5px; border-bottom: 2px solid <?php echo $active_tab === 'all_loans' ? 'var(--teal)' : 'transparent'; ?>; background: none;">Master Ledger</a>
    </div>
<?php else: ?>
    <!-- EMPLOYEE SUMMARY -->
    <?php $myBal = 0; foreach($myLoans as $ml) if($ml['status']=='Active') $myBal += $ml['remaining_balance']; ?>
    <div class="card mb-20" style="background: linear-gradient(135deg, var(--teal-deep), var(--teal)); border: none;">
        <div class="card-body flex-between" style="padding: 30px;">
            <div>
                <p style="color: var(--teal-bg); font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 1px;">Outstanding Balance</p>
                <h2 style="color: #fff; font-size: 32px; font-weight: 800; font-family: 'DM Mono';">₱ <?php echo number_format($myBal, 2); ?></h2>
                <p style="color: var(--teal-bg); font-size: 12px; margin-top: 5px;">Automatically deducted per pay period.</p>
            </div>
            <div style="background: rgba(255,255,255,0.1); width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                <i class="fa-solid fa-wallet" style="color: #fff; font-size: 24px;"></i>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="card mb-20" style="background: <?php echo $messageType == 'success' ? 'var(--green-bg)' : 'var(--red-bg)'; ?>;">
        <div class="card-body" style="color: <?php echo $messageType == 'success' ? 'var(--green)' : 'var(--red)'; ?>; font-weight: 600;">
            <i class="fa-solid <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-head">
        <div class="card-title">
            <?php echo ($active_tab === 'pending') ? 'Pending Requests' : (($is_hr || $is_ceo) ? 'Complete Loan History' : 'My Personal Ledger'); ?>
        </div>
        <?php if ($active_tab === 'all_loans'): ?>
            <form method="GET" class="flex-row">
                <input type="hidden" name="tab" value="all_loans">
                <select name="status" onchange="this.form.submit()" class="input-field" style="width: 150px; height: 30px; font-size: 11px;">
                    <option value="">All Statuses</option>
                    <option value="Active" <?php echo $filter_status == 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Paid" <?php echo $filter_status == 'Paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="Hold" <?php echo $filter_status == 'Hold' ? 'selected' : ''; ?>>Hold</option>
                    <option value="Rejected" <?php echo $filter_status == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </form>
        <?php endif; ?>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php 
            $loansToRender = ($is_hr || $is_ceo) ? ($active_tab === 'pending' ? $pendingLoans : $allLoans) : $myLoans;
            renderUnifiedLoanTable($loansToRender, $approval_role, ($active_tab === 'pending')); 
        ?>
    </div>
</div>

<!-- MODALS -->
<div id="modalBackdrop" class="modal-overlay" style="position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:100; display:none;" onclick="closeModal(); closeReview();"></div>

<div id="addLoanModal" class="modal-container" style="position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:450px; background:var(--bg-card); border-radius:16px; border:1px solid var(--border); box-shadow:var(--sh-md); z-index:101; display:none; flex-direction:column;">
    <div class="modal-header" style="padding:15px 20px; border-bottom:1px solid var(--border-lt); display:flex; align-items:center; justify-content:space-between; background:var(--bg-raised); border-radius:16px 16px 0 0;">
        <h3 class="card-title"><i class="fa-solid fa-plus-circle"></i> New Loan Record</h3>
        <button onclick="closeModal()" class="icon-btn" style="border:none; background:none;"><i class="fa-solid fa-times"></i></button>
    </div>
    <form method="POST" action="">
        <?= csrfField() ?>
        <div class="modal-body" style="padding:20px;">
            <input type="hidden" name="action" value="add_loan">
            <?php if ($is_hr || $is_ceo): ?>
                <div class="form-group">
                    <label class="form-label">Employee Selection</label>
                    <select name="emp_id" id="emp_selector" class="input-field" required style="width:100%;">
                        <?php foreach($employees as $e): ?>
                            <option value="<?php echo $e['emp_id']; ?>"><?php echo $e['last_name'] . ', ' . $e['first_name'] . ' (' . $e['ac_no'] . ')'; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Category</label><select name="loan_category" class="input-field"><option value="Salary Advance">Salary Advance</option><option value="Emergency Loan">Emergency Loan</option><option value="Gadget Loan">Gadget Loan</option><option value="SSS/Pag-IBIG">SSS/Pag-IBIG</option><option value="Company Loan">Company Loan</option></select></div>
                <div class="form-group"><label class="form-label">Frequency</label><select name="deduction_frequency" id="freq" class="input-field" onchange="calc()"><option value="Semi-monthly">Semi-monthly</option><option value="Monthly">Monthly</option></select></div>
            </div>
            <div class="form-group"><label class="form-label">Amount (Principal)</label><input type="number" step="0.01" name="principal_amount" id="principal" class="input-field" style="font-weight:700; font-family:'DM Mono';" required oninput="calc()"></div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Terms (Months)</label><input type="number" name="months_to_pay" id="months" value="1" class="input-field" required oninput="calc()"></div>
                <div class="form-group"><label class="form-label">Interest %</label><input type="number" step="0.01" name="interest_rate" id="rate" value="0" class="input-field" oninput="calc()"></div>
            </div>
            <div style="background:var(--teal-bg); padding:15px; border-radius:8px; border:1px solid var(--teal-border); margin-top:10px;">
                <div class="flex-between" style="font-size:11px; margin-bottom:5px;"><span>Total Payable:</span><span id="disp_total" style="font-weight:700;">₱ 0.00</span></div>
                <div class="flex-between" style="font-size:13px; color:var(--teal-deep); font-weight:800;"><span>Deduction/Period:</span><span id="disp_deduction">₱ 0.00</span></div>
            </div>
        </div>
        <div class="modal-footer" style="padding:15px 20px; border-top:1px solid var(--border-lt); display:flex; justify-content:flex-end; gap:10px; background:var(--bg-raised); border-radius:0 0 16px 16px;">
            <button type="button" onclick="closeModal()" class="pill-btn">Cancel</button>
            <button type="submit" class="btn-primary">Create Record</button>
        </div>
    </form>
</div>

<!-- REVIEW MODAL -->
<div id="reviewLoanModal" class="modal-container" style="position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:400px; background:var(--bg-card); border-radius:16px; border:1px solid var(--border); box-shadow:var(--sh-md); z-index:101; display:none; flex-direction:column;">
    <div class="modal-header">
        <h3 class="card-title">Review & Revise</h3>
        <button onclick="closeReview()" class="icon-btn" style="border:none; background:none;"><i class="fa-solid fa-times"></i></button>
    </div>
    <form method="POST" action="">
        <?= csrfField() ?>
        <div class="modal-body">
            <input type="hidden" name="action" value="revise_loan"><input type="hidden" name="loan_id" id="rev_loan_id">
            <div class="form-group"><label class="form-label">Principal Amount</label><input type="number" step="0.01" name="principal_amount" id="rev_principal" class="input-field" required></div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Terms (Mo)</label><input type="number" name="months_to_pay" id="rev_months" class="input-field" required></div>
                <div class="form-group"><label class="form-label">Frequency</label><select name="deduction_frequency" id="rev_freq" class="input-field"><option value="Semi-monthly">Semi-monthly</option><option value="Monthly">Monthly</option></select></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="submit" name="approve_now" value="1" class="btn-primary">Save & Approve</button>
            <button type="submit" name="approve_now" value="0" class="pill-btn">Just Save</button>
        </div>
    </form>
</div>

<script>
    function calc() {
        let P = parseFloat($('#principal').val()) || 0;
        let R = parseFloat($('#rate').val()) || 0;
        let T = parseFloat($('#months').val()) || 1;
        let f = $('#freq').val();
        let total = P + (P * (R / 100));
        let monthly = total / T;
        let ded = (f === 'Semi-monthly') ? (monthly / 2) : monthly;
        $('#disp_total').text('₱ ' + total.toLocaleString(undefined, {minimumFractionDigits:2}));
        $('#disp_deduction').text('₱ ' + ded.toLocaleString(undefined, {minimumFractionDigits:2}));
    }
    function openModal() { $('#addLoanModal').css('display', 'flex'); $('#modalBackdrop').show(); }
    function closeModal() { $('#addLoanModal').hide(); $('#modalBackdrop').hide(); }
    function openReview(id, p, r, m, f) {
        $('#rev_loan_id').val(id); $('#rev_principal').val(p); $('#rev_months').val(m); $('#rev_freq').val(f);
        $('#reviewLoanModal').css('display', 'flex'); $('#modalBackdrop').show();
    }
    function closeReview() { $('#reviewLoanModal').hide(); $('#modalBackdrop').hide(); }
</script>

<?php
function renderUnifiedLoanTable($loans, $role, $isPendingView) {
    if (empty($loans)) {
        echo '<div style="padding:50px; text-align:center; color:var(--ink-4);"><i class="fa-solid fa-ghost" style="font-size:30px; margin-bottom:10px; opacity:0.3;"></i><p>No loan records found.</p></div>';
        return;
    }
    ?>
    <div class="table-wrap" style="border:none; border-radius:0;">
        <table>
            <thead>
                <tr>
                    <th>Ref / Type</th>
                    <th>Employee</th>
                    <th style="text-align: right;">Principal</th>
                    <th style="text-align: right;">Balance</th>
                    <th style="text-align: center;">Status</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($loans as $l): 
                    $tag = match($l['status']) {
                        'Active' => 'tag-green',
                        'Pending HR', 'Pending CEO' => 'tag-amber',
                        'Paid' => 'tag-blue',
                        'Rejected' => 'tag-red',
                        default => 'tag-teal'
                    };
                ?>
                <tr>
                    <td>
                        <div style="font-family:'DM Mono'; font-size:10px; color:var(--ink-4);">#<?php echo str_pad($l['loan_id'], 5, '0', STR_PAD_LEFT); ?></div>
                        <div style="font-weight:700; font-size:12px;"><?php echo $l['loan_category']; ?></div>
                    </td>
                    <td>
                        <div style="font-weight:600;"><?php echo htmlspecialchars($l['last_name'].', '.$l['first_name']); ?></div>
                        <div style="font-size:10px; color:var(--ink-4);"><?php echo date('M d, Y', strtotime($l['created_at'])); ?></div>
                    </td>
                    <td style="text-align: right; font-family:'DM Mono';">₱<?php echo number_format($l['principal_amount'], 2); ?></td>
                    <td style="text-align: right;">
                        <div style="font-weight:700; font-family:'DM Mono';">₱<?php echo number_format($l['remaining_balance'], 2); ?></div>
                        <div style="font-size:9px; color:var(--red); font-weight:600;">-<?php echo number_format($l['per_cutoff_deduction'], 2); ?>/cut</div>
                    </td>
                    <td style="text-align: center;"><span class="tag <?php echo $tag; ?>"><?php echo $l['status']; ?></span></td>
                    <td style="text-align: right;">
                        <div class="flex-row" style="justify-content: flex-end; gap:4px;">
                            <?php if ($isPendingView): ?>
                                <?php if ($role == 'HR' && $l['status'] == 'Pending HR'): ?>
                                    <button onclick="openReview(<?php echo $l['loan_id']; ?>, <?php echo $l['principal_amount']; ?>, <?php echo $l['interest_rate']; ?>, <?php echo $l['months_to_pay']; ?>, '<?php echo $l['deduction_frequency']; ?>')" class="icon-btn"><i class="fa-solid fa-pen"></i></button>
                                    <form method="POST" style="display:inline;"><?= csrfField() ?><input type="hidden" name="action" value="status_update_hr"><input type="hidden" name="loan_id" value="<?php echo $l['loan_id']; ?>"><button name="status" value="Approved" class="icon-btn ico-green"><i class="fa-solid fa-check"></i></button></form>
                                <?php elseif ($role == 'CEO' && $l['status'] == 'Pending CEO'): ?>
                                    <form method="POST" style="display:inline;"><?= csrfField() ?><input type="hidden" name="action" value="status_update_ceo"><input type="hidden" name="loan_id" value="<?php echo $l['loan_id']; ?>"><button name="status" value="Approved" class="btn-primary" style="font-size:10px; height:26px; padding:0 10px;">Final Approve</button></form>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (($role == 'HR' || $role == 'CEO') && $l['status'] == 'Active'): ?>
                                    <form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="loan_id" value="<?php echo $l['loan_id']; ?>"><select name="new_status" onchange="this.form.submit()" class="input-field" style="width:80px; height:24px; font-size:9px;"><option value="">Action</option><option value="Hold">Hold</option><option value="Paid">Force Paid</option></select></form>
                                <?php else: ?>
                                    <span style="font-size:10px; color:var(--ink-4);">-</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>

<?php require BASE_PATH . '/partials/layout_footer.php'; ?>
