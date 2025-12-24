        <div id="tab-onboarding" class="tab-content hidden fade-in">
            <div class="stat-card p-0 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50"><h3 class="font-bold text-gray-900">Onboard Employee</h3></div>
                <form method="POST" action="" id="onboardingForm" class="p-8 space-y-8" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_employee">
                    
                    <div>
                        <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">Personal Information</h4>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
                            <div><label class="label-text">AC Number <span class="text-red-500">*</span></label><input type="text" name="ac_no" required class="input-field"></div>
                            <div><label class="label-text">Last Name <span class="text-red-500">*</span></label><input type="text" name="last_name" required class="input-field"></div>
                            <div><label class="label-text">First Name <span class="text-red-500">*</span></label><input type="text" name="first_name" required class="input-field"></div>
                            <div><label class="label-text">Middle Name</label><input type="text" name="middle_name" class="input-field"></div>
                            <div><label class="label-text">DOB</label><input type="date" name="date_of_birth" class="input-field"></div>
                            <div><label class="label-text">Gender</label><select name="gender" class="input-field bg-white"><option value="">Select</option><option value="Male">Male</option><option value="Female">Female</option></select></div>
                            <div><label class="label-text">Civil Status</label><select name="civil_status" class="input-field bg-white"><option value="">Select</option><option value="Single">Single</option><option value="Married">Married</option></select></div>
                            <div><label class="label-text">Nationality</label><input type="text" name="nationality" value="Filipino" class="input-field"></div>
                            <div class="md:col-span-2"><label class="label-text">Address</label><input type="text" name="address" class="input-field"></div>
                            <div><label class="label-text">Contact No.</label><input type="text" name="contact_number" class="input-field" placeholder="+63 9XX XXX XXXX" oninput="formatContact(this)"></div>
                            <div><label class="label-text">Email</label><input type="email" name="email_address" class="input-field"></div>
                            
                            <div class="md:col-span-1 border border-dashed border-gray-300 rounded p-2 bg-gray-50">
                                <label class="label-text text-xs">Profile Picture (Saved as AC_No.png)</label>
                                <input type="file" name="profile_photo" accept="image/png, image/jpeg" class="input-field text-xs border-0 p-0 bg-transparent">
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">Job Details</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <div>
                                <label class="label-text">Department</label>
                                <select name="dept_id" class="input-field bg-white">
                                    <option value="">Select Department</option>
                                    <?php foreach($depts as $dept): ?>
                                        <option value="<?php echo $dept['dept_id']; ?>"><?php echo htmlspecialchars($dept['dept_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div><label class="label-text">Job Title</label><input type="text" name="job_title" class="input-field"></div>
                            
                            <div>
                                <label class="label-text text-gray-900">Job Level</label>
                                <select name="job_level" class="input-field bg-white">
                                    <option value="Rank and File">Rank and File</option>
                                    <option value="Supervisor">Supervisor</option>
                                    <option value="Manager">Manager</option>
                                </select>
                            </div>

                            <div class="bg-primary-50/50 p-2 rounded border border-primary-100">
                                <label class="label-text text-primary-700">System Role</label>
                                <select name="approval_role" class="input-field border-primary-200 text-gray-900 bg-white">
                                    <option value="Employee">Employee</option><option value="DeptHead">Department Head</option><option value="HR">HR Admin</option><option value="CEO">CEO</option>
                                </select>
                            </div>
                            <div><label class="label-text">Emp. Status</label><select name="employment_status" class="input-field bg-white"><option value="Regular">Regular</option><option value="Probationary">Probationary</option><option value="Contractual">Contractual</option><option value="Intern">Intern</option></select></div>
                            <div><label class="label-text">Location</label><input type="text" name="location_assignment" class="input-field"></div>
                            <div>
                                <label class="label-text">Supervisor</label>
                                <select name="supervisor_id" class="input-field bg-white">
                                    <option value="">Select Supervisor</option>
                                    <?php foreach($supervisors as $sup): ?>
                                        <option value="<?php echo $sup['emp_id']; ?>"><?php echo htmlspecialchars($sup['first_name'] . ' ' . $sup['last_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div><label class="label-text">Type</label><select name="employment_type" class="input-field bg-white"><option value="Full-time">Full-time</option><option value="Part-time">Part-time</option><option value="Seasonal">Seasonal</option><option value="Fixed">Fixed</option></select></div>
                            <div><label class="label-text">Schedule</label><select name="work_schedule" class="input-field bg-white"><option value="Office Based">Office Based</option><option value="Operations">Operations</option></select></div>
                            <div><label class="label-text">Date Hired</label><input type="date" name="date_hired" class="input-field"></div>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 border-b border-gray-100 pb-2">Payroll Info & Signature</h4>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-5">
                            <div><label class="label-text">Basic Monthly Salary</label><input type="number" step="0.01" name="salary_rate" id="add_salary" oninput="calculateRates(this, 'add')" class="input-field" placeholder="0.00" required></div>
                            <div class="bg-gray-100 rounded p-1"><label class="label-text text-gray-500 text-[10px] pl-1">Daily Rate</label><input type="number" step="0.01" name="daily_rate" id="add_daily" oninput="calculateRates(this, 'add')" class="input-field border-none bg-transparent font-bold text-gray-700" placeholder="0.00"></div>
                            <div class="bg-gray-100 rounded p-1"><label class="label-text text-gray-500 text-[10px] pl-1">Hourly Rate</label><input type="number" step="0.01" name="hourly_rate" id="add_hourly" oninput="calculateRates(this, 'add')" class="input-field border-none bg-transparent font-bold text-gray-700" placeholder="0.00"></div>
                            <div><label class="label-text">Payroll Group</label><select name="payroll_group" class="input-field bg-white"><option value="Direct Employee">Direct Employee</option><option value="Indirect">Indirect</option></select></div>
                            <div><label class="label-text">Bank Acct</label><input type="text" name="bank_account_number" class="input-field"></div>
                            <div><label class="label-text">TIN (XXX-XXX-XXX)</label><input type="text" name="tin_number" class="input-field" maxlength="11" oninput="formatID(this, '3-3-3')"></div>
                            <div><label class="label-text">SSS (XX-XXXXXXX-X)</label><input type="text" name="sss_number" class="input-field" maxlength="12" oninput="formatID(this, '2-7-1')"></div>
                            <div><label class="label-text">PhilHealth (4-4-4)</label><input type="text" name="philhealth_number" class="input-field" maxlength="14" oninput="formatID(this, '4-4-4')"></div>
                            <div><label class="label-text">Pag-IBIG (4-4-4)</label><input type="text" name="pagibig_number" class="input-field" maxlength="14" oninput="formatID(this, '4-4-4')"></div>
                            
                            <div class="bg-yellow-50 p-2 rounded border border-yellow-200">
                                <label class="label-text text-yellow-800">SIL Credits</label>
                                <input type="number" step="0.5" name="sil_credits" class="input-field border-yellow-300" placeholder="0.0">
                            </div>

                            <div class="md:col-span-3"><label class="label-text font-bold text-gray-900">Upload E-Signature</label><input type="file" name="signature_file" accept="image/png, image/jpeg" class="input-field bg-white"><p class="text-xs text-gray-400 mt-1">Recommended: PNG with transparent background.</p></div>
                        </div>
                    </div>

                    <div class="pt-4 flex justify-end gap-3 border-t border-gray-100">
                         <button type="submit" class="px-6 py-2.5 bg-primary text-primary-foreground rounded-lg hover:bg-primary-600 font-bold shadow-sm transition">Submit Onboarding</button>
                    </div>
                </form>
            </div>
        </div>
