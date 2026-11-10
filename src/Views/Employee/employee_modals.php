<style>
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 100; display: none; }
    .modal-container { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 90%; max-width: 900px; background: var(--bg-card); border-radius: 16px; border: 1px solid var(--border); box-shadow: var(--sh-md); z-index: 101; display: none; flex-direction: column; max-height: 90vh; }
    .modal-header { padding: 15px 20px; border-bottom: 1px solid var(--border-lt); display: flex; align-items: center; justify-content: space-between; background: var(--bg-raised); border-radius: 16px 16px 0 0; }
    .modal-body { padding: 20px; overflow-y: auto; flex: 1; }
    .modal-footer { padding: 15px 20px; border-top: 1px solid var(--border-lt); display: flex; justify-content: flex-end; gap: 10px; background: var(--bg-raised); border-radius: 0 0 16px 16px; }
    .modal-tabs { display: flex; gap: 10px; padding: 0 20px; background: var(--bg-raised); border-bottom: 1px solid var(--border-lt); }
    .modal-tab { padding: 10px 15px; font-size: 12px; font-weight: 600; color: var(--ink-3); cursor: pointer; border-bottom: 2px solid transparent; transition: all .15s; }
    .modal-tab.active { color: var(--teal); border-bottom-color: var(--teal); }
</style>

<div id="modalBackdrop" class="modal-overlay" onclick="handleBackdropClick()"></div>

<!-- EDIT MODAL -->
<div id="editModal" class="modal-container" style="max-width: 1000px;">
    <div class="modal-header">
        <h3 class="card-title"><i class="fa-solid fa-pen-to-square"></i> Edit Employee Profile</h3>
        <button onclick="closeModal()" class="icon-btn" style="border:none; background:none;"><i class="fa-solid fa-times"></i></button>
    </div>
    <form method="POST" action="" id="editForm" style="display:contents;" enctype="multipart/form-data">
        <?= csrfField() ?>
        <div class="modal-body">
            <input type="hidden" name="action" value="update_employee"><input type="hidden" name="emp_id" id="edit_emp_id">
            
            <div class="section-hd">
                <span class="section-hd-label">Personal Identity</span>
                <div class="section-hd-line"></div>
            </div>
            <div class="grid-3" style="grid-template-columns: repeat(4, 1fr); gap: 15px;">
                <div class="form-group"><label class="form-label">AC Number (Fixed)</label><input type="text" name="ac_no" id="edit_ac_no" readonly class="input-field" style="background:var(--bg-subtle); color:var(--ink-4);"></div>
                <div class="form-group"><label class="form-label">Last Name</label><input type="text" name="last_name" id="edit_last_name" class="input-field"></div>
                <div class="form-group"><label class="form-label">First Name</label><input type="text" name="first_name" id="edit_first_name" class="input-field"></div>
                <div class="form-group"><label class="form-label">Middle Name</label><input type="text" name="middle_name" id="edit_middle_name" class="input-field"></div>
                <div class="form-group"><label class="form-label">Date of Birth</label><input type="date" name="date_of_birth" id="edit_date_of_birth" class="input-field"></div>
                <div class="form-group"><label class="form-label">Gender</label><select name="gender" id="edit_gender" class="input-field"><option value="Male">Male</option><option value="Female">Female</option></select></div>
                <div class="form-group"><label class="form-label">Civil Status</label><select name="civil_status" id="edit_civil_status" class="input-field"><option value="Single">Single</option><option value="Married">Married</option><option value="Widowed">Widowed</option></select></div>
                <div class="form-group"><label class="form-label">Nationality</label><input type="text" name="nationality" id="edit_nationality" class="input-field"></div>
                <div class="form-group"><label class="form-label">Email Address</label><input type="email" name="email_address" id="edit_email_address" class="input-field"></div>
                <div class="form-group"><label class="form-label">Contact Number</label><input type="text" name="contact_number" id="edit_contact_number" class="input-field"></div>
                <div class="form-group" style="grid-column: span 2;"><label class="form-label">Home Address</label><input type="text" name="address" id="edit_address" class="input-field"></div>
            </div>

            <div class="section-hd">
                <span class="section-hd-label">Emergency Contact</span>
                <div class="section-hd-line"></div>
            </div>
            <div class="grid-3" style="gap: 15px;">
                <div class="form-group"><label class="form-label">Contact Name</label><input type="text" name="emergency_contact_name" id="edit_emergency_contact_name" class="input-field"></div>
                <div class="form-group"><label class="form-label">Contact Number</label><input type="text" name="emergency_contact_number" id="edit_emergency_contact_number" class="input-field"></div>
                <div class="form-group"><label class="form-label">Relationship</label><input type="text" name="emergency_contact_relationship" id="edit_emergency_contact_relationship" class="input-field"></div>
            </div>

            <div class="section-hd">
                <span class="section-hd-label">Employment & Access</span>
                <div class="section-hd-line"></div>
            </div>
            <div class="grid-3" style="gap: 15px;">
                <div class="form-group">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                        <label class="form-label" style="margin-bottom:0;">Department</label>
                        <?php if (in_array(strtoupper($user_role ?? ''), ['HR', 'CEO'])): ?>
                            <a href="javascript:void(0)" onclick="openCreateDeptModal('edit_dept_id')" style="font-size:11px; font-weight:600; color:var(--teal); text-decoration:none;">
                                <i class="fa-solid fa-plus"></i> New Dept
                            </a>
                        <?php endif; ?>
                    </div>
                    <select name="dept_id" id="edit_dept_id" class="input-field"><?php foreach($depts as $dept): ?><option value="<?php echo $dept['dept_id']; ?>"><?php echo htmlspecialchars($dept['dept_name']); ?></option><?php endforeach; ?></select>
                </div>
                <div class="form-group"><label class="form-label">Job Title</label><input type="text" name="job_title" id="edit_job_title" class="input-field"></div>
                <div class="form-group">
                    <label class="form-label">Job Level</label>
                    <select name="job_level" id="edit_job_level" class="input-field"><option value="Rank and File">Rank and File</option><option value="Supervisor">Supervisor</option><option value="Manager">Manager</option></select>
                </div>
                <div class="form-group"><label class="form-label">Status</label><select name="employment_status" id="edit_employment_status" class="input-field"><option value="Regular">Regular</option><option value="Probationary">Probationary</option><option value="Contractual">Contractual</option><option value="Intern">Intern</option></select></div>
                <div class="form-group"><label class="form-label">Employment Type</label><select name="employment_type" id="edit_employment_type" class="input-field"><option value="Full-time">Full-time</option><option value="Part-time">Part-time</option></select></div>
                <div class="form-group"><label class="form-label">Work Schedule</label><input type="text" name="work_schedule" id="edit_work_schedule" class="input-field"></div>
                <div class="form-group"><label class="form-label">Location</label><input type="text" name="location_assignment" id="edit_location_assignment" class="input-field"></div>
                <div class="form-group"><label class="form-label">Date Hired</label><input type="date" name="date_hired" id="edit_date_hired" class="input-field"></div>
                <div class="form-group" style="background:var(--teal-bg); padding:10px; border-radius:8px; border:1px solid var(--teal-border);">
                    <label class="form-label" style="color:var(--teal-deep);">System Role</label>
                    <select name="approval_role" id="edit_approval_role" class="input-field"><option value="Employee">Employee</option><option value="DeptHead">Dept Head</option><option value="HR">HR Admin</option><option value="CEO">CEO</option></select>
                </div>
            </div>

            <div class="section-hd">
                <span class="section-hd-label">Compensation & Payroll</span>
                <div class="section-hd-line"></div>
            </div>
            <div class="grid-3" style="grid-template-columns: repeat(4, 1fr); gap: 15px;">
                <div class="form-group"><label class="form-label">Monthly Salary</label><input type="number" step="0.01" name="salary_rate" id="edit_salary_rate" oninput="calculateRates(this, 'edit')" class="input-field"></div>
                <div class="form-group"><label class="form-label">Daily (Auto)</label><input type="number" step="0.01" name="daily_rate" id="edit_daily" class="input-field" style="background:var(--bg-subtle);" readonly></div>
                <div class="form-group"><label class="form-label">Hourly (Auto)</label><input type="number" step="0.01" name="hourly_rate" id="edit_hourly" class="input-field" style="background:var(--bg-subtle);" readonly></div>
                <div class="form-group"><label class="form-label" style="color:var(--teal); font-weight:700;">VL Credits (HR Set)</label><input type="number" step="0.5" name="vl_credits" id="edit_vl_credits" class="input-field"></div>
                <div class="form-group"><label class="form-label" style="color:var(--blue); font-weight:700;">SL Credits (HR Set)</label><input type="number" step="0.5" name="sl_credits" id="edit_sl_credits" class="input-field"></div>
                <div class="form-group"><label class="form-label">Solo Parent (Days)</label><input type="number" step="1" name="solo_parent_credits" id="edit_solo_parent_credits" class="input-field"></div>
                <div class="form-group"><label class="form-label">Paternity (Days)</label><input type="number" step="1" name="paternity_credits" id="edit_paternity_credits" class="input-field"></div>
                <div class="form-group"><label class="form-label">Maternity (Days)</label><input type="number" step="1" name="maternity_credits" id="edit_maternity_credits" class="input-field"></div>
                <div class="form-group"><label class="form-label">Bereavement (Days)</label><input type="number" step="1" name="bereavement_immediate_credits" id="edit_bereav_imm" class="input-field"></div>
                
                <div class="form-group"><label class="form-label">Payroll Group</label><select name="payroll_group" id="edit_payroll_group" class="input-field"><option value="Monthly">Monthly</option><option value="Semi-Monthly">Semi-Monthly</option></select></div>
                <div class="form-group"><label class="form-label">Bank Account</label><input type="text" name="bank_account_number" id="edit_bank_account_number" class="input-field"></div>
                <div class="form-group"><label class="form-label">TIN Number</label><input type="text" name="tin_number" id="edit_tin_number" class="input-field"></div>
                <div class="form-group"><label class="form-label">SSS Number</label><input type="text" name="sss_number" id="edit_sss_number" class="input-field"></div>
                <div class="form-group"><label class="form-label">PhilHealth</label><input type="text" name="philhealth_number" id="edit_philhealth_number" class="input-field"></div>
                <div class="form-group"><label class="form-label">Pag-IBIG</label><input type="text" name="pagibig_number" id="edit_pagibig_number" class="input-field"></div>
            </div>

            <div class="section-hd">
                <span class="section-hd-label">Attachments</span>
                <div class="section-hd-line"></div>
            </div>
            <div class="grid-2" style="gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Profile Photo</label>
                    <input type="file" name="profile_photo" accept="image/*" class="input-field">
                    <small style="color:var(--ink-4); font-size:10px;">Leave blank to keep current photo.</small>
                </div>
                <div class="form-group">
                    <label class="form-label">E-Signature</label>
                    <input type="file" name="signature_file" accept="image/*" class="input-field">
                    <small style="color:var(--ink-4); font-size:10px;">Upload a transparent PNG signature.</small>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" onclick="closeModal()" class="pill-btn">Cancel</button>
            <button type="submit" class="btn-primary">Save Changes</button>
        </div>
    </form>
</div>

<!-- VIEW MODAL -->
<div id="viewModal" class="modal-container" style="max-width: 900px;">
    <div class="modal-header">
        <h3 class="card-title"><i class="fa-solid fa-user-tie"></i> Employee Profile</h3>
        <button onclick="closeViewModal()" class="icon-btn" style="border:none; background:none;"><i class="fa-solid fa-times"></i></button>
    </div>
    
    <div class="modal-tabs">
        <div onclick="switchViewTab('profile')" id="btn-view-profile" class="modal-tab active">Overview</div>
        <div onclick="switchViewTab('salary')" id="btn-view-salary" class="modal-tab">Salary History</div>
        <div onclick="switchViewTab('loans')" id="btn-view-loans" class="modal-tab">Loans</div>
        <div onclick="switchViewTab('documents')" id="btn-view-documents" class="modal-tab">Documents</div>
        <div onclick="switchViewTab('timecard')" id="btn-view-timecard" class="modal-tab">Biometrics</div>
    </div>

    <div class="modal-body" id="viewModalBody">
        <div id="view-tab-profile" class="view-tab-content">
            <div id="viewEmployeeContent">Loading...</div>
        </div>

        <div id="view-tab-salary" class="view-tab-content" style="display:none;">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Date</th><th>Old Rate</th><th>New Rate</th><th>Difference</th></tr></thead>
                    <tbody id="salaryHistoryBody"></tbody>
                </table>
            </div>
        </div>

        <div id="view-tab-loans" class="view-tab-content" style="display:none;">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Type</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead>
                    <tbody id="loansHistoryBody"></tbody>
                </table>
            </div>
        </div>

        <div id="view-tab-documents" class="view-tab-content" style="display:none;">
            <div class="grid-2" id="documentsList"></div>
        </div>

        <div id="view-tab-timecard" class="view-tab-content" style="display:none;">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Check Time</th><th>Type</th><th>Device</th></tr></thead>
                    <tbody id="timecardBody"></tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="modal-footer">
        <button type="button" class="pill-btn" onclick="closeViewModal()">Close Profile</button>
    </div>
</div>

<!-- CREATE DEPARTMENT MODAL (HR / CEO ONLY) -->
<div id="createDeptModal" class="modal-container" style="max-width: 520px; z-index: 102;">
    <div class="modal-header">
        <h3 class="card-title" style="font-size:15px; font-weight:700;"><i class="fa-solid fa-building" style="color: var(--teal); margin-right:8px;"></i> Create New Department</h3>
        <button type="button" onclick="closeCreateDeptModal()" class="icon-btn" style="border:none; background:none; cursor:pointer; font-size:16px; color:var(--ink-4);"><i class="fa-solid fa-times"></i></button>
    </div>
    <form id="createDeptForm" onsubmit="submitCreateDeptForm(event)" style="display:flex; flex-direction:column; margin:0;">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create_department">
        <div class="modal-body" style="padding: 20px;">
            <div id="createDeptAlert" style="display:none; padding: 10px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; margin-bottom: 15px;"></div>
            
            <div class="form-group mb-15">
                <label class="form-label" style="font-weight: 600;">Department Name <span style="color:var(--red);">*</span></label>
                <input type="text" name="dept_name" id="new_dept_name" required placeholder="e.g. Quality Assurance" class="input-field" style="width:100%;">
            </div>

            <div class="form-group mb-15">
                <label class="form-label" style="font-weight: 600;">Short Code <span style="font-size:11px; color:var(--ink-4); font-weight:normal;">(Optional - auto-generated if blank)</span></label>
                <input type="text" name="dept_code" id="new_dept_code" placeholder="e.g. QA" maxlength="20" class="input-field" style="width:100%; text-transform:uppercase;">
            </div>

            <div class="form-group mb-15">
                <label class="form-label" style="font-weight: 600;">Department Color Theme</label>
                <div style="display:flex; align-items:center; gap:12px; margin-bottom:10px;">
                    <input type="color" id="new_dept_color_picker" value="#0F766E" onchange="syncDeptColor(this.value)" style="width:40px; height:36px; padding:2px; border-radius:6px; border:1px solid var(--border); cursor:pointer;">
                    <input type="text" name="color" id="new_dept_color" value="#0F766E" maxlength="7" class="input-field" style="width:110px; font-family:'DM Mono'; text-transform:uppercase;" oninput="syncDeptColor(this.value)">
                    <span id="new_dept_color_preview" style="font-size:12px; font-weight:600; padding:6px 14px; border-radius:20px; background:#CCFBF1; color:#0F766E; border:1px solid #0F766E;">Preview Badge</span>
                </div>
                <div style="font-size:11px; color:var(--ink-4); margin-bottom:6px;">Approved accessible palette presets:</div>
                <div style="display:flex; flex-wrap:wrap; gap:8px;">
                    <?php 
                    $presetColors = [
                        '#C2410C' => 'Orange (HR)',
                        '#1D4ED8' => 'Blue (MIS / Tech)',
                        '#0F766E' => 'Teal (Operations)',
                        '#6D28D9' => 'Violet (Finance)',
                        '#B45309' => 'Amber (Kitchen)',
                        '#047857' => 'Emerald (Housekeeping)',
                        '#BE123C' => 'Rose (F&B)',
                        '#4338CA' => 'Indigo (Front Office)',
                        '#0369A1' => 'Sky (Sales)',
                        '#A21CAF' => 'Fuchsia (Entertainment)',
                        '#374151' => 'Slate',
                        '#0F172A' => 'Dark Ink'
                    ];
                    foreach ($presetColors as $hex => $cName): ?>
                        <button type="button" onclick="syncDeptColor('<?= $hex ?>')" title="<?= $cName ?>" style="width:26px; height:26px; border-radius:50%; background:<?= $hex ?>; border:2px solid #fff; box-shadow:0 1px 3px rgba(0,0,0,0.25); cursor:pointer; padding:0; transition:transform 0.15s ease;" onmouseover="this.style.transform='scale(1.15)'" onmouseout="this.style.transform='scale(1)'"></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="pill-btn" onclick="closeCreateDeptModal()">Cancel</button>
            <button type="submit" id="btnSubmitCreateDept" class="btn-primary" style="display:inline-flex; align-items:center; gap:6px;">
                <i class="fa-solid fa-plus"></i> Create Department
            </button>
        </div>
    </form>
</div>

<script>
    let activeDeptTargetSelectId = null;

    function openCreateDeptModal(targetSelect = null) {
        activeDeptTargetSelectId = targetSelect;
        const modal = document.getElementById('createDeptModal');
        const backdrop = document.getElementById('modalBackdrop');
        if (!modal) return;
        
        document.getElementById('createDeptForm').reset();
        syncDeptColor('#0F766E');
        const alertBox = document.getElementById('createDeptAlert');
        if (alertBox) {
            alertBox.style.display = 'none';
            alertBox.textContent = '';
        }

        if (backdrop) {
            backdrop.style.display = 'block';
            backdrop.style.zIndex = '101';
        }
        modal.style.display = 'flex';
        document.getElementById('new_dept_name').focus();
    }

    function closeCreateDeptModal() {
        const modal = document.getElementById('createDeptModal');
        if (modal) modal.style.display = 'none';
        
        const editModal = document.getElementById('editModal');
        const viewModal = document.getElementById('viewModal');
        const editOpen = editModal && editModal.style.display === 'flex';
        const viewOpen = viewModal && viewModal.style.display === 'flex';
        
        const backdrop = document.getElementById('modalBackdrop');
        if (backdrop) {
            if (!editOpen && !viewOpen) {
                backdrop.style.display = 'none';
                backdrop.style.zIndex = '100';
            } else {
                backdrop.style.zIndex = '100';
            }
        }
    }

    function handleBackdropClick() {
        const createDeptModal = document.getElementById('createDeptModal');
        if (createDeptModal && createDeptModal.style.display === 'flex') {
            closeCreateDeptModal();
            return;
        }
        closeModal();
        closeViewModal();
    }

    function syncDeptColor(hex) {
        if (!hex) return;
        hex = hex.trim();
        if (!hex.startsWith('#')) hex = '#' + hex;
        const picker = document.getElementById('new_dept_color_picker');
        const txt = document.getElementById('new_dept_color');
        const preview = document.getElementById('new_dept_color_preview');
        if (picker && /^#[0-9A-Fa-f]{6}$/.test(hex)) picker.value = hex;
        if (txt) txt.value = hex.toUpperCase();
        if (preview) {
            preview.style.borderColor = hex;
            preview.style.color = hex;
            preview.style.backgroundColor = hex + '20';
        }
    }

    async function submitCreateDeptForm(e) {
        e.preventDefault();
        const form = document.getElementById('createDeptForm');
        const alertBox = document.getElementById('createDeptAlert');
        const submitBtn = document.getElementById('btnSubmitCreateDept');
        const originalBtnHtml = submitBtn.innerHTML;
        
        const formData = new FormData(form);
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Creating...';
        alertBox.style.display = 'none';

        try {
            const res = await fetch('<?= baseUrl("employee") ?>', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const data = await res.json();
            if (data.success) {
                // Dynamically update all department selects on the page
                const deptSelects = document.querySelectorAll('select[name="dept_id"], #deptFilter, #edit_dept_id, #dept_id_onboarding');
                deptSelects.forEach(select => {
                    const opt = document.createElement('option');
                    opt.value = data.dept_id;
                    opt.textContent = data.dept_name;
                    select.appendChild(opt);
                });

                // If a specific target select triggered this, auto-select the new department
                if (activeDeptTargetSelectId) {
                    const targetEl = document.getElementById(activeDeptTargetSelectId);
                    if (targetEl) targetEl.value = data.dept_id;
                }

                closeCreateDeptModal();
                alert(data.message);
            } else {
                alertBox.style.display = 'block';
                alertBox.style.background = 'var(--red-bg)';
                alertBox.style.color = 'var(--red)';
                alertBox.style.border = '1px solid var(--red-bdr)';
                alertBox.textContent = data.message || 'Error creating department.';
            }
        } catch (err) {
            alertBox.style.display = 'block';
            alertBox.style.background = 'var(--red-bg)';
            alertBox.style.color = 'var(--red)';
            alertBox.style.border = '1px solid var(--red-bdr)';
            alertBox.textContent = 'Server communication error: ' + err.message;
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalBtnHtml;
        }
    }

    // Overriding the previous switchViewTab to work with new modal structure
    function switchViewTab(tabName) {
        const contents = document.querySelectorAll('.view-tab-content');
        contents.forEach(c => c.style.display = 'none');
        document.getElementById('view-tab-' + tabName).style.display = 'block';
        
        const tabs = document.querySelectorAll('.modal-tab');
        tabs.forEach(t => t.classList.remove('active'));
        document.getElementById('btn-view-' + tabName).classList.add('active');
    }

    function closeModal() { 
        document.getElementById('editModal').style.display = 'none'; 
        document.getElementById('modalBackdrop').style.display = 'none'; 
    }
    
    function closeViewModal() { 
        document.getElementById('viewModal').style.display = 'none'; 
        document.getElementById('modalBackdrop').style.display = 'none'; 
    }
</script>
