<div class="card" style="border:none; box-shadow:none; margin-bottom:0;">
    <div class="card-head" style="background:var(--bg-card); border-bottom:1px solid var(--border-lt); padding:15px 20px;">
        <div class="card-title"><i class="fa-solid fa-user-plus"></i> Onboard New Employee</div>
        <span style="font-size:10px; color:var(--ink-4); font-weight:600; text-transform:uppercase; letter-spacing:0.05em;">New Hire Protocol</span>
    </div>
    
    <form method="POST" action="" id="onboardingForm" class="card-body p-20" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add_employee">
        
        <!-- SECTION 1: PERSONAL -->
        <div class="section-hd">
            <span class="section-hd-label">Personal Information</span>
            <div class="section-hd-line"></div>
        </div>
        <div class="grid-3" style="grid-template-columns: repeat(4, 1fr); gap: 15px;">
            <div class="form-group"><label class="form-label">AC Number *</label><input type="text" name="ac_no" required class="input-field"></div>
            <div class="form-group"><label class="form-label">Last Name *</label><input type="text" name="last_name" required class="input-field"></div>
            <div class="form-group"><label class="form-label">First Name *</label><input type="text" name="first_name" required class="input-field"></div>
            <div class="form-group"><label class="form-label">Middle Name</label><input type="text" name="middle_name" class="input-field"></div>
            <div class="form-group"><label class="form-label">DOB</label><input type="date" name="date_of_birth" class="input-field"></div>
            <div class="form-group"><label class="form-label">Gender</label><select name="gender" class="input-field"><option value="">Select</option><option value="Male">Male</option><option value="Female">Female</option></select></div>
            <div class="form-group"><label class="form-label">Civil Status</label><select name="civil_status" class="input-field"><option value="">Select</option><option value="Single">Single</option><option value="Married">Married</option></select></div>
            <div class="form-group"><label class="form-label">Nationality</label><input type="text" name="nationality" value="Filipino" class="input-field"></div>
            <div class="form-group" style="grid-column: span 2;"><label class="form-label">Address</label><input type="text" name="address" class="input-field"></div>
            <div class="form-group"><label class="form-label">Contact No.</label><input type="text" name="contact_number" class="input-field" placeholder="+63 9XX XXX XXXX" oninput="formatContact(this)"></div>
            <div class="form-group"><label class="form-label">Email</label><input type="email" name="email_address" class="input-field"></div>
        </div>

        <div class="form-group mb-20" style="background:var(--bg-subtle); padding:15px; border-radius:8px; border:1px dashed var(--border);">
            <label class="form-label">Profile Picture (Recommended: AC_No.png)</label>
            <input type="file" name="profile_photo" accept="image/png, image/jpeg" class="input-field" style="border:none; background:transparent; padding:0; height:auto;">
        </div>

        <!-- SECTION 2: JOB DETAILS -->
        <div class="section-hd">
            <span class="section-hd-label">Job Details</span>
            <div class="section-hd-line"></div>
        </div>
        <div class="grid-3" style="gap: 15px;">
            <div class="form-group">
                <label class="form-label">Department</label>
                <select name="dept_id" class="input-field">
                    <option value="">Select Department</option>
                    <?php if(!empty($depts)): foreach($depts as $dept): ?>
                        <option value="<?php echo $dept['dept_id']; ?>"><?php echo htmlspecialchars($dept['dept_name']); ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Job Title</label><input type="text" name="job_title" class="input-field"></div>
            <div class="form-group">
                <label class="form-label">Job Level</label>
                <select name="job_level" class="input-field">
                    <option value="Rank and File">Rank and File</option><option value="Supervisor">Supervisor</option><option value="Manager">Manager</option>
                </select>
            </div>
            <div class="form-group" style="background:var(--teal-bg); padding:10px; border-radius:8px; border:1px solid var(--teal-border);">
                <label class="form-label" style="color:var(--teal-deep);">System Access Role</label>
                <select name="approval_role" class="input-field">
                    <option value="Employee">Employee</option><option value="DeptHead">Department Head</option><option value="HR">HR Admin</option><option value="CEO">CEO</option>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Employment Status</label><select name="employment_status" class="input-field"><option value="Regular">Regular</option><option value="Probationary">Probationary</option><option value="Contractual">Contractual</option><option value="Intern">Intern</option></select></div>
            <div class="form-group"><label class="form-label">Work Location</label><input type="text" name="location_assignment" class="input-field"></div>
            <div class="form-group">
                <label class="form-label">Supervisor</label>
                <select name="supervisor_id" class="input-field">
                    <option value="">Select Supervisor</option>
                    <?php if(!empty($supervisors)): foreach($supervisors as $sup): ?>
                        <option value="<?php echo $sup['emp_id']; ?>"><?php echo htmlspecialchars($sup['first_name'] . ' ' . $sup['last_name']); ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div class="form-group"><label class="form-label">Schedule Type</label><select name="work_schedule" class="input-field"><option value="Office Based">Office Based</option><option value="Operations">Operations</option></select></div>
            <div class="form-group"><label class="form-label">Date Hired</label><input type="date" name="date_hired" class="input-field"></div>
        </div>

        <!-- SECTION 3: PAYROLL -->
        <div class="section-hd">
            <span class="section-hd-label">Payroll Configuration</span>
            <div class="section-hd-line"></div>
        </div>
        <div class="grid-3" style="grid-template-columns: repeat(4, 1fr); gap: 15px;">
            <div class="form-group"><label class="form-label">Basic Monthly Salary</label><input type="number" step="0.01" name="salary_rate" id="add_salary" oninput="calculateRates(this, 'add')" class="input-field" placeholder="0.00" required></div>
            <div class="form-group"><label class="form-label">Daily Rate (Auto)</label><input type="number" step="0.01" id="add_daily" class="input-field" style="background:var(--bg-subtle); font-family:'DM Mono'; font-weight:600;" readonly></div>
            <div class="form-group"><label class="form-label">Hourly Rate (Auto)</label><input type="number" step="0.01" id="add_hourly" class="input-field" style="background:var(--bg-subtle); font-family:'DM Mono'; font-weight:600;" readonly></div>
            <div class="form-group">
                <label class="form-label" style="color:var(--amber);">SIL Credits</label>
                <input type="number" step="0.5" name="sil_credits" class="input-field" style="border-color:var(--amber-bdr); background:var(--amber-bg);" placeholder="0.0">
            </div>
            <div class="form-group"><label class="form-label">Bank Account</label><input type="text" name="bank_account_number" class="input-field"></div>
            <div class="form-group"><label class="form-label">SSS (XX-XXXXXXX-X)</label><input type="text" name="sss_number" class="input-field" maxlength="12" oninput="formatID(this, '2-7-1')"></div>
            <div class="form-group"><label class="form-label">PhilHealth (4-4-4)</label><input type="text" name="philhealth_number" class="input-field" maxlength="14" oninput="formatID(this, '4-4-4')"></div>
            <div class="form-group"><label class="form-label">Pag-IBIG (4-4-4)</label><input type="text" name="pagibig_number" class="input-field" maxlength="14" oninput="formatID(this, '4-4-4')"></div>
        </div>

        <div class="pt-20 flex-between" style="border-top:1px solid var(--border-lt); margin-top:20px;">
            <p style="font-size:11px; color:var(--ink-4);"><i class="fa-solid fa-circle-info"></i> All marked fields (*) are mandatory for system compliance.</p>
            <button type="submit" class="btn-primary" style="padding:10px 30px; font-size:14px;">Complete Onboarding</button>
        </div>
    </form>
</div>
