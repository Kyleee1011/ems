<?php
$pageTitle = 'Employee Management — Azzurro HR';
require BASE_PATH . '/partials/layout_head.php';
require BASE_PATH . '/partials/layout_topbar.php';
require BASE_PATH . '/partials/layout_sidebar.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Workforce Directory</h1>
        <p class="page-sub">Manage employee records, onboarding, and documentation</p>
    </div>
    <div class="header-actions">
        <button class="pill-btn" onclick="exportData()"><i class="fa-solid fa-file-export"></i> Export CSV</button>
        <a href="?tab=onboarding" class="btn-primary"><i class="fa-solid fa-user-plus"></i> New Employee</a>
    </div>
</div>

<!-- TABS NAVIGATION -->
<div class="flex-row mb-20" style="border-bottom: 1px solid var(--border-lt); gap: 20px;">
    <a href="?tab=directory" class="nav-item <?php echo ($activeTab == 'directory' || $activeTab == '') ? 'active' : ''; ?>" style="padding: 10px 5px; border-bottom: 2px solid <?php echo ($activeTab == 'directory' || $activeTab == '') ? 'var(--teal)' : 'transparent'; ?>; background: none;">Directory</a>
    <a href="?tab=onboarding" class="nav-item <?php echo $activeTab === 'onboarding' ? 'active' : ''; ?>" style="padding: 10px 5px; border-bottom: 2px solid <?php echo $activeTab === 'onboarding' ? 'var(--teal)' : 'transparent'; ?>; background: none;">Onboarding</a>
    <a href="?tab=bulk_upload" class="nav-item <?php echo $activeTab === 'bulk_upload' ? 'active' : ''; ?>" style="padding: 10px 5px; border-bottom: 2px solid <?php echo $activeTab === 'bulk_upload' ? 'var(--teal)' : 'transparent'; ?>; background: none;">Bulk Upload</a>
    <a href="?tab=recycle" class="nav-item <?php echo $activeTab === 'recycle' ? 'active' : ''; ?>" style="padding: 10px 5px; border-bottom: 2px solid <?php echo $activeTab === 'recycle' ? 'var(--red)' : 'transparent'; ?>; background: none; color: <?php echo $activeTab === 'recycle' ? 'var(--red)' : 'var(--ink-4)'; ?>;">
        <i class="fa-solid fa-user-slash"></i> Inactive Employees
    </a>
</div>

<?php if ($message): ?>
    <div class="card mb-20" style="background: <?php echo $messageType == 'success' ? 'var(--green-bg)' : 'var(--red-bg)'; ?>; border-color: <?php echo $messageType == 'success' ? 'var(--green-bdr)' : 'var(--red-bdr)'; ?>;">
        <div class="card-body" style="color: <?php echo $messageType == 'success' ? 'var(--green)' : 'var(--red)'; ?>; font-weight: 600; display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php 
        switch ($activeTab) {
            case 'onboarding':
                require BASE_PATH . '/src/Views/Employee/employee_onboarding.php';
                break;
            case 'bulk_upload':
                require BASE_PATH . '/src/Views/Employee/employee_bulk_upload.php';
                break;
            case 'recycle':
                require BASE_PATH . '/src/Views/Employee/employee_recycle_bin.php';
                break;
            case 'directory':
            default:
                require BASE_PATH . '/src/Views/Employee/employee_list.php';
                break;
        }
        ?>
    </div>
</div>

<?php include BASE_PATH . '/src/Views/Employee/employee_modals.php'; ?>

<script>
    function exportData() {
        const dept = document.getElementById('deptFilter') ? document.getElementById('deptFilter').value : '';
        window.location.href = `<?php echo baseUrl('employee?action=export_employees&dept='); ?>${encodeURIComponent(dept)}`;
    }

    function closeModal() { 
        document.getElementById('editModal').style.display = 'none'; 
        document.getElementById('modalBackdrop').style.display = 'none'; 
    }
    
    function closeViewModal() { 
        document.getElementById('viewModal').style.display = 'none'; 
        document.getElementById('modalBackdrop').style.display = 'none'; 
    }

    function getEmployeeData(empId, callback) {
        fetch('<?php echo baseUrl('employee?action=get_employee_json&emp_id='); ?>' + empId).then(res => res.json()).then(data => { if (data.success) callback(data); else alert('Error: ' + data.message); }).catch(error => alert('Error fetching data.'));
    }

    function calculateRates(input, prefix) {
        const monthly = parseFloat(input.value) || 0;
        const daily = (monthly / (313/12)).toFixed(2);
        const hourly = (daily / 8).toFixed(2);
        const dEl = document.getElementById(prefix + '_daily');
        const hEl = document.getElementById(prefix + '_hourly');
        if(dEl) dEl.value = daily;
        if(hEl) hEl.value = hourly;
    }

    function editEmployee(empId) {
        document.getElementById('editModal').style.display = 'flex';
        document.getElementById('modalBackdrop').style.display = 'block';
        getEmployeeData(empId, (data) => {
            const emp = data.employee;
            if (!emp) return;
            const setVal = (id, val) => { const el = document.getElementById(id); if(el) el.value = (val === null || val === undefined) ? '' : val; };
            
            // Identity
            setVal('edit_emp_id', emp.emp_id); setVal('edit_ac_no', emp.ac_no); 
            setVal('edit_first_name', emp.first_name); setVal('edit_last_name', emp.last_name); setVal('edit_middle_name', emp.middle_name);
            setVal('edit_date_of_birth', emp.date_of_birth); setVal('edit_gender', emp.gender); 
            setVal('edit_civil_status', emp.civil_status); setVal('edit_nationality', emp.nationality);
            
            // Contact
            setVal('edit_address', emp.address); setVal('edit_contact_number', emp.contact_number); setVal('edit_email_address', emp.email_address);
            
            // Emergency
            setVal('edit_emergency_contact_name', emp.emergency_contact_name);
            setVal('edit_emergency_contact_number', emp.emergency_contact_number);
            setVal('edit_emergency_contact_relationship', emp.emergency_contact_relationship);

            // Job
            setVal('edit_dept_id', emp.dept_id); setVal('edit_job_title', emp.job_title); setVal('edit_job_level', emp.job_level); 
            setVal('edit_employment_status', emp.employment_status); setVal('edit_employment_type', emp.employment_type);
            setVal('edit_location_assignment', emp.location_assignment); setVal('edit_work_schedule', emp.work_schedule);
            setVal('edit_date_hired', emp.date_hired); setVal('edit_approval_role', emp.approval_role); 
            
            // Payroll
            setVal('edit_salary_rate', emp.salary_rate); 
            setVal('edit_sil_credits', emp.sil_credits);
            setVal('edit_payroll_group', emp.payroll_group);
            setVal('edit_bank_account_number', emp.bank_account_number);
            setVal('edit_tin_number', emp.tin_number);
            setVal('edit_sss_number', emp.sss_number);
            setVal('edit_philhealth_number', emp.philhealth_number);
            setVal('edit_pagibig_number', emp.pagibig_number);
            
            // Trigger calculation for edit modal
            const salaryInput = document.getElementById('edit_salary_rate');
            if(salaryInput) calculateRates(salaryInput, 'edit');
        });
    }

    let currentViewingEmpId = null;

    function viewEmployee(empId, targetTab = 'profile') {
        currentViewingEmpId = empId;
        document.getElementById('viewModal').style.display = 'flex';
        document.getElementById('modalBackdrop').style.display = 'block';
        
        // Update URL query param without full reload
        const url = new URL(window.location);
        url.searchParams.set('view_id', empId);
        window.history.replaceState({}, '', url);

        if (typeof switchViewTab === 'function') switchViewTab(targetTab);

        // Show loading state
        document.getElementById('viewEmployeeContent').innerHTML = '<div style="padding:40px; text-align:center; color:var(--ink-4);"><i class="fa-solid fa-spinner fa-spin" style="font-size:24px; margin-bottom:10px; display:block;"></i>Loading employee details...</div>';
        document.getElementById('salaryHistoryBody').innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px; color:var(--ink-4);">Loading salary history...</td></tr>';
        document.getElementById('loansHistoryBody').innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px; color:var(--ink-4);">Loading loans...</td></tr>';
        document.getElementById('documentsList').innerHTML = '<div style="padding:20px; text-align:center; color:var(--ink-4);">Loading documents...</div>';
        document.getElementById('timecardBody').innerHTML = '<tr><td colspan="3" style="text-align:center; padding:20px; color:var(--ink-4);">Loading biometrics...</td></tr>';

        getEmployeeData(empId, (data) => {
            const emp = data.employee;
            if (!emp) return;

            const val = (v) => (v !== null && v !== undefined && v !== '') ? v : '<span style="color:var(--ink-4); font-style:italic;">N/A</span>';
            const money = (v) => {
                const n = parseFloat(v);
                return isNaN(n) ? '0.00' : n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            };

            let avatarHtml = emp.profile_picture ? 
                `<img src="<?php echo baseUrl(); ?>/${emp.profile_picture}?t=${Date.now()}" class="avatar" style="width:80px; height:80px; object-fit:cover; border-radius:50%; border:2px solid var(--teal-border);">` : 
                `<div class="avatar" style="width:80px; height:80px; font-size:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg, var(--teal-deep), var(--teal)); color:#fff;">${(emp.first_name?.[0]||'')}${(emp.last_name?.[0]||'')}</div>`;

            // 1. OVERVIEW TAB
            document.getElementById('viewEmployeeContent').innerHTML = `
                <div class="flex-row mb-20" style="gap: 20px; align-items: center; padding-bottom: 20px; border-bottom: 1px solid var(--border-lt);">
                    ${avatarHtml}
                    <div>
                        <h2 style="font-size: 22px; font-weight: 800; color:var(--ink-1); margin-bottom:4px;">${val(emp.full_name || (emp.first_name + ' ' + emp.last_name))}</h2>
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <span style="color: var(--teal); font-weight: 600; font-size:13px;">${val(emp.job_title)}</span>
                            <span class="tag tag-teal">${val(emp.dept_name || 'Department ' + emp.dept_id)}</span>
                            <span class="tag tag-blue" style="font-family:'DM Mono';">AC: ${val(emp.ac_no)}</span>
                            <span class="tag ${emp.employee_status === 'Active' ? 'tag-green' : 'tag-red'}">${val(emp.employee_status || 'Active')}</span>
                        </div>
                    </div>
                </div>

                <div class="grid-2" style="gap:20px; margin-bottom:20px;">
                    <div>
                        <h4 style="font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:var(--teal); font-weight:700; margin-bottom:12px;">Employment Info</h4>
                        <div style="display:grid; grid-template-columns: 120px 1fr; gap:8px; font-size:12.5px;">
                            <span style="color:var(--ink-3);">Job Level:</span> <b>${val(emp.job_level)}</b>
                            <span style="color:var(--ink-3);">Employment:</span> <b>${val(emp.employment_status)} (${val(emp.employment_type)})</b>
                            <span style="color:var(--ink-3);">Location:</span> <b>${val(emp.location_assignment)}</b>
                            <span style="color:var(--ink-3);">Supervisor:</span> <b>${val(emp.supervisor_name || 'None')}</b>
                            <span style="color:var(--ink-3);">Date Hired:</span> <b>${val(emp.date_hired)}</b>
                            <span style="color:var(--ink-3);">Regularized:</span> <b>${val(emp.date_regularized)}</b>
                            <span style="color:var(--ink-3);">System Role:</span> <b><span class="tag tag-purple">${val(emp.approval_role)}</span></b>
                        </div>
                    </div>

                    <div>
                        <h4 style="font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:var(--teal); font-weight:700; margin-bottom:12px;">Contact & Personal</h4>
                        <div style="display:grid; grid-template-columns: 120px 1fr; gap:8px; font-size:12.5px;">
                            <span style="color:var(--ink-3);">Email:</span> <b>${val(emp.email_address)}</b>
                            <span style="color:var(--ink-3);">Contact No:</span> <b>${val(emp.contact_number)}</b>
                            <span style="color:var(--ink-3);">Birth Date:</span> <b>${val(emp.date_of_birth)} (${val(emp.gender)})</b>
                            <span style="color:var(--ink-3);">Civil Status:</span> <b>${val(emp.civil_status)}</b>
                            <span style="color:var(--ink-3);">Address:</span> <b>${val(emp.address)}</b>
                            <span style="color:var(--ink-3);">Emergency:</span> <b>${val(emp.emergency_contact_name)} (${val(emp.emergency_contact_relationship)}) - ${val(emp.emergency_contact_number)}</b>
                        </div>
                    </div>
                </div>

                <div style="background:var(--bg-raised); border-radius:12px; padding:15px; border:1px solid var(--border-lt);">
                    <h4 style="font-size:11px; text-transform:uppercase; letter-spacing:0.05em; color:var(--teal); font-weight:700; margin-bottom:12px;">Payroll & Compensation</h4>
                    <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:12px; font-size:12px;">
                        <div><div style="color:var(--ink-4); font-size:10px;">BASIC SALARY</div><b style="font-family:'DM Mono'; font-size:15px; color:var(--teal);">₱ ${money(emp.salary_rate)}</b></div>
                        <div><div style="color:var(--ink-4); font-size:10px;">DAILY RATE</div><b style="font-family:'DM Mono'; font-size:13px;">₱ ${money(emp.daily_rate)}</b></div>
                        <div><div style="color:var(--ink-4); font-size:10px;">HOURLY RATE</div><b style="font-family:'DM Mono'; font-size:13px;">₱ ${money(emp.hourly_rate)}</b></div>
                        <div><div style="color:var(--ink-4); font-size:10px;">SIL CREDITS</div><b style="font-family:'DM Mono'; font-size:13px; color:var(--amber);">${val(emp.sil_credits)} days</b></div>
                    </div>
                    <div style="display:grid; grid-template-columns: repeat(4, 1fr); gap:12px; font-size:11px; margin-top:12px; padding-top:12px; border-top:1px dashed var(--border-lt);">
                        <div><span style="color:var(--ink-4);">SSS:</span> <b>${val(emp.sss_number)}</b></div>
                        <div><span style="color:var(--ink-4);">PhilHealth:</span> <b>${val(emp.philhealth_number)}</b></div>
                        <div><span style="color:var(--ink-4);">Pag-IBIG:</span> <b>${val(emp.pagibig_number)}</b></div>
                        <div><span style="color:var(--ink-4);">TIN:</span> <b>${val(emp.tin_number)}</b></div>
                    </div>
                </div>
            `;

            // 2. SALARY HISTORY TAB
            const salaryHistory = data.salary_history || [];
            if (salaryHistory.length === 0) {
                document.getElementById('salaryHistoryBody').innerHTML = '<tr><td colspan="4" style="text-align:center; padding:30px; color:var(--ink-4);"><i class="fa-solid fa-coins" style="font-size:24px; display:block; margin-bottom:8px; opacity:0.4;"></i>No salary changes on record.</td></tr>';
            } else {
                let sHtml = '';
                salaryHistory.forEach(s => {
                    const diff = parseFloat(s.new_salary) - parseFloat(s.old_salary);
                    const diffBadge = diff >= 0 ? 
                        `<span class="tag tag-green">+₱ ${money(diff)}</span>` : 
                        `<span class="tag tag-red">-₱ ${money(Math.abs(diff))}</span>`;
                    sHtml += `
                        <tr>
                            <td><span style="font-family:'DM Mono'; font-size:11px;">${new Date(s.change_date).toLocaleDateString('en-US', { year:'numeric', month:'short', day:'numeric' })}</span></td>
                            <td style="font-family:'DM Mono';">₱ ${money(s.old_salary)}</td>
                            <td style="font-family:'DM Mono'; font-weight:700; color:var(--teal);">₱ ${money(s.new_salary)}</td>
                            <td>${diffBadge}</td>
                        </tr>
                    `;
                });
                document.getElementById('salaryHistoryBody').innerHTML = sHtml;
            }

            // 3. LOANS TAB
            const loans = data.loans || [];
            if (loans.length === 0) {
                document.getElementById('loansHistoryBody').innerHTML = '<tr><td colspan="5" style="text-align:center; padding:30px; color:var(--ink-4);"><i class="fa-solid fa-hand-holding-dollar" style="font-size:24px; display:block; margin-bottom:8px; opacity:0.4;"></i>No loan records found.</td></tr>';
            } else {
                let lHtml = '';
                loans.forEach(l => {
                    const statusClass = l.status === 'Active' ? 'tag-green' : (l.status === 'Paid' ? 'tag-teal' : 'tag-amber');
                    lHtml += `
                        <tr>
                            <td><b>${escapeHtml(l.loan_category)}</b><br><small style="color:var(--ink-4); font-size:10px;">${escapeHtml(l.description || '')}</small></td>
                            <td style="font-family:'DM Mono';">₱ ${money(l.total_payable)}</td>
                            <td style="font-family:'DM Mono'; color:var(--green);">₱ ${money(l.paid_amount || (l.total_payable - l.remaining_balance))}</td>
                            <td style="font-family:'DM Mono'; font-weight:700; color:var(--amber);">₱ ${money(l.remaining_balance)}</td>
                            <td><span class="tag ${statusClass}">${escapeHtml(l.status)}</span></td>
                        </tr>
                    `;
                });
                document.getElementById('loansHistoryBody').innerHTML = lHtml;
            }

            // 4. DOCUMENTS TAB
            const docs = data.documents || [];
            let dHtml = `
                <div style="grid-column:span 2; margin-bottom:15px; padding:15px; background:var(--bg-raised); border-radius:12px; border:1px solid var(--border-lt);">
                    <form method="POST" enctype="multipart/form-data" action="<?php echo baseUrl('employee'); ?>" style="display:flex; gap:10px; align-items:flex-end;">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="upload_document">
                        <input type="hidden" name="emp_id" value="${emp.emp_id}">
                        <input type="hidden" name="ac_no" value="${emp.ac_no}">
                        <div style="flex:1;">
                            <label class="form-label" style="font-size:11px;">Document Label</label>
                            <input type="text" name="doc_name" placeholder="e.g. Contract, TOR, Medical Certificate" required class="input-field" style="height:34px; font-size:12px;">
                        </div>
                        <div style="flex:1;">
                            <label class="form-label" style="font-size:11px;">File (PDF, PNG, JPG, DOCX)</label>
                            <input type="file" name="doc_file" required class="input-field" style="height:34px; font-size:11px; padding:4px;">
                        </div>
                        <button type="submit" class="btn-primary" style="height:34px; padding:0 16px; font-size:12px; white-space:nowrap;">
                            <i class="fa-solid fa-cloud-arrow-up"></i> Upload
                        </button>
                    </form>
                </div>
            `;

            if (docs.length === 0) {
                dHtml += '<div style="grid-column:span 2; text-align:center; padding:30px; color:var(--ink-4);"><i class="fa-regular fa-file-lines" style="font-size:24px; display:block; margin-bottom:8px; opacity:0.4;"></i>No documents uploaded yet.</div>';
            } else {
                docs.forEach(doc => {
                    const filePath = '<?php echo baseUrl(); ?>/' + doc.file_path.replace(/^\//, '');
                    dHtml += `
                        <div class="card" style="padding:12px 16px; margin:0; display:flex; align-items:center; justify-content:space-between; border:1px solid var(--border-lt); border-radius:10px;">
                            <div style="display:flex; align-items:center; gap:12px; min-width:0;">
                                <div style="width:36px; height:36px; border-radius:8px; background:var(--teal-bg); color:var(--teal); display:flex; align-items:center; justify-content:center; font-size:16px;">
                                    <i class="fa-solid fa-file-pdf"></i>
                                </div>
                                <div style="min-width:0;">
                                    <div style="font-weight:700; font-size:13px; color:var(--ink-1); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                        ${escapeHtml(doc.doc_name)}
                                    </div>
                                    <div style="font-size:10px; color:var(--ink-4);">
                                        Uploaded ${new Date(doc.uploaded_at).toLocaleDateString('en-US', { year:'numeric', month:'short', day:'numeric' })}
                                    </div>
                                </div>
                            </div>
                            <div style="display:flex; gap:6px;">
                                <a href="${filePath}" target="_blank" class="icon-btn" title="View / Download" style="color:var(--teal);">
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                </a>
                                <button type="button" onclick="deleteDocumentConfirm(${doc.doc_id})" class="icon-btn" title="Delete" style="color:var(--red);">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                });
            }
            document.getElementById('documentsList').innerHTML = dHtml;

            // 5. BIOMETRICS TAB
            const logs = data.attendance_logs || [];
            if (logs.length === 0) {
                document.getElementById('timecardBody').innerHTML = '<tr><td colspan="3" style="text-align:center; padding:30px; color:var(--ink-4);"><i class="fa-regular fa-clock" style="font-size:24px; display:block; margin-bottom:8px; opacity:0.4;"></i>No biometric records found for this employee.</td></tr>';
            } else {
                let bHtml = '';
                logs.slice(0, 50).forEach(b => {
                    const typeLabel = (b.CHECKTYPE === 'I' || b.VERIFYCODE == 0) ? 
                        '<span class="tag tag-green">CHECK-IN</span>' : 
                        '<span class="tag tag-blue">CHECK-OUT</span>';
                    bHtml += `
                        <tr>
                            <td style="font-family:'DM Mono'; font-size:12px;">${escapeHtml(b.CHECKTIME || b.check_time)}</td>
                            <td>${typeLabel}</td>
                            <td style="color:var(--ink-4); font-size:11px;">${escapeHtml(b.sn || b.device || 'Biometric Scanner')}</td>
                        </tr>
                    `;
                });
                document.getElementById('timecardBody').innerHTML = bHtml;
            }
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function deleteDocumentConfirm(docId) {
        if (confirm('Are you sure you want to delete this document?')) {
            const f = document.createElement('form');
            f.method = 'POST';
            f.action = '<?php echo baseUrl("employee"); ?>';
            f.innerHTML = `<?= csrfField() ?><input type="hidden" name="action" value="delete_document"><input type="hidden" name="doc_id" value="${docId}">`;
            document.body.appendChild(f);
            f.submit();
        }
    }

    // Auto-open modal on page load if ?view_id is set
    $(document).ready(function() {
        const urlParams = new URLSearchParams(window.location.search);
        const viewId = urlParams.get('view_id');
        if (viewId) {
            viewEmployee(viewId);
        }
    });

    // Clean up URL query when closing modal
    const originalCloseViewModal = closeViewModal;
    closeViewModal = function() {
        document.getElementById('viewModal').style.display = 'none';
        document.getElementById('modalBackdrop').style.display = 'none';
        const url = new URL(window.location);
        url.searchParams.delete('view_id');
        window.history.replaceState({}, '', url);
    };

    function softDeleteEmployee(id) { if(confirm('Mark as Inactive?')) createPost(id, 'soft_delete'); }
    function restoreEmployee(id) { if(confirm('Set this employee as Active?')) createPost(id, 'restore'); }
    function hardDeleteEmployee(id) { if(confirm('PERMANENTLY DELETE?')) createPost(id, 'hard_delete'); }
    function createPost(id, action) { const f = document.createElement('form'); f.method = 'POST'; f.action = ''; f.innerHTML = `<?= csrfField() ?><input type="hidden" name="action" value="${action}"><input type="hidden" name="emp_id" value="${id}">`; document.body.appendChild(f); f.submit(); }
</script>

<?php require BASE_PATH . '/partials/layout_footer.php'; ?>
