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
                require BASE_PATH . '/views/employee_onboarding.php';
                break;
            case 'bulk_upload':
                require BASE_PATH . '/views/employee_bulk_upload.php';
                break;
            case 'recycle':
                require BASE_PATH . '/views/employee_recycle_bin.php';
                break;
            case 'directory':
            default:
                require BASE_PATH . '/views/employee_list.php';
                break;
        }
        ?>
    </div>
</div>

<?php include BASE_PATH . '/views/employee_modals.php'; ?>

<script>
    function exportData() {
        const dept = document.getElementById('deptFilter') ? document.getElementById('deptFilter').value : '';
        window.location.href = `<?php echo baseUrl('employee?action=export_employees&dept='); ?>${encodeURIComponent(dept)}`;
    }

    function switchViewTab(tabName) {
        document.querySelectorAll('.view-tab-content').forEach(el => el.classList.add('hidden'));
        document.getElementById('view-tab-' + tabName).classList.remove('hidden');
        ['profile', 'salary', 'loans', 'documents'].forEach(t => {
            const btn = document.getElementById('btn-view-' + t);
            if(btn) btn.className = "nav-item";
        });
        const activeBtn = document.getElementById('btn-view-' + tabName);
        if(activeBtn) activeBtn.className = "nav-item active";
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

    function viewEmployee(empId) {
        document.getElementById('viewModal').style.display = 'flex';
        document.getElementById('modalBackdrop').style.display = 'block';
        if (typeof switchViewTab === 'function') switchViewTab('profile');
        getEmployeeData(empId, (data) => {
            const emp = data.employee;
            if (!emp) return;
            const val = (v) => v ? v : '<span class="text-slate-400 italic">N/A</span>';
            let avatarHtml = emp.profile_picture ? `<img src="${emp.profile_picture}" class="avatar" style="width:80px; height:80px;">` : `<div class="avatar" style="width:80px; height:80px; font-size:30px;">${(emp.first_name?.[0]||'')}${(emp.last_name?.[0]||'')}</div>`;
            
            document.getElementById('viewEmployeeContent').innerHTML = `
                <div class="flex-row mb-20" style="gap: 20px;">
                    ${avatarHtml}
                    <div>
                        <h2 style="font-size: 24px; font-weight: 800;">${val(emp.full_name)}</h2>
                        <p style="color: var(--teal); font-weight: 600;">${val(emp.job_title)}</p>
                        <span class="tag tag-blue mt-10">AC: ${val(emp.ac_no)}</span>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="mb-10"><label class="form-label">Department</label><p style="font-weight:600;">${val(emp.dept_name)}</p></div>
                    <div class="mb-10"><label class="form-label">Email</label><p>${val(emp.email_address)}</p></div>
                    <div class="mb-10"><label class="form-label">Status</label><span class="tag tag-green">${val(emp.employment_status)}</span></div>
                    <div class="mb-10"><label class="form-label">Monthly Salary</label><p style="font-family:'DM Mono'; font-weight:700;">₱ ${val(emp.salary_rate)}</p></div>
                </div>
            `;
            const docIdEl = document.getElementById('doc_emp_id'); if(docIdEl) docIdEl.value = emp.emp_id;
            const docAcEl = document.getElementById('doc_ac_no'); if(docAcEl) docAcEl.value = emp.ac_no;
        });
    }

    function softDeleteEmployee(id) { if(confirm('Mark as Inactive?')) createPost(id, 'soft_delete'); }
    function restoreEmployee(id) { if(confirm('Set this employee as Active?')) createPost(id, 'restore'); }
    function hardDeleteEmployee(id) { if(confirm('PERMANENTLY DELETE?')) createPost(id, 'hard_delete'); }
    function createPost(id, action) { const f = document.createElement('form'); f.method = 'POST'; f.action = ''; f.innerHTML = `<?= csrfField() ?><input type="hidden" name="action" value="${action}"><input type="hidden" name="emp_id" value="${id}">`; document.body.appendChild(f); f.submit(); }
</script>

<?php require BASE_PATH . '/partials/layout_footer.php'; ?>
