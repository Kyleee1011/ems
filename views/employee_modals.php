    <div id="modalBackdrop" class="fixed inset-0 bg-black/50 backdrop-blur-sm hidden z-50 transition-opacity"></div>
    <div id="editModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">
                <div class="bg-white px-6 py-4 border-b border-gray-200 flex justify-between items-center"><h3 class="text-lg font-bold text-gray-900">Edit Employee</h3><button onclick="closeModal()" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-times text-xl"></i></button></div>
                <form method="POST" action="" id="editForm" class="max-h-[80vh] overflow-y-auto p-6" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_employee"><input type="hidden" name="emp_id" id="edit_emp_id">
                    
                    <div class="space-y-6">
                        <div>
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 border-b border-gray-100 pb-1">Personal Information</h4>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div><label class="label-text">AC No (Read Only)</label><input type="text" name="ac_no" id="edit_ac_no" readonly class="input-field bg-gray-100 cursor-not-allowed"></div>
                                <div><label class="label-text">Last Name</label><input type="text" name="last_name" id="edit_last_name" class="input-field"></div>
                                <div><label class="label-text">First Name</label><input type="text" name="first_name" id="edit_first_name" class="input-field"></div>
                                <div><label class="label-text">Middle Name</label><input type="text" name="middle_name" id="edit_middle_name" class="input-field"></div>
                                
                                <div><label class="label-text">Date of Birth</label><input type="date" name="date_of_birth" id="edit_date_of_birth" class="input-field"></div>
                                <div><label class="label-text">Gender</label><select name="gender" id="edit_gender" class="input-field bg-white"><option value="">Select</option><option value="Male">Male</option><option value="Female">Female</option></select></div>
                                <div><label class="label-text">Civil Status</label><select name="civil_status" id="edit_civil_status" class="input-field bg-white"><option value="">Select</option><option value="Single">Single</option><option value="Married">Married</option><option value="Widowed">Widowed</option></select></div>
                                <div><label class="label-text">Nationality</label><input type="text" name="nationality" id="edit_nationality" class="input-field"></div>
                                
                                <div class="md:col-span-2"><label class="label-text">Address</label><input type="text" name="address" id="edit_address" class="input-field"></div>
                                <div><label class="label-text">Contact No</label><input type="text" name="contact_number" id="edit_contact_number" class="input-field" placeholder="+63 9XX XXX XXXX" oninput="formatContact(this)"></div>
                                <div><label class="label-text">Email</label><input type="email" name="email_address" id="edit_email_address" class="input-field"></div>
                                
                                <div class="md:col-span-1 border border-dashed border-gray-300 rounded p-2 bg-gray-50">
                                    <label class="label-text">Update Profile Photo</label>
                                    <input type="file" name="profile_photo" accept="image/png, image/jpeg" class="input-field text-xs bg-transparent border-0 p-0">
                                </div>
                            </div>
                        </div>

                        <div>
                             <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 border-b border-gray-100 pb-1">Emergency Contact</h4>
                             <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div><label class="label-text">Contact Name</label><input type="text" name="emergency_contact_name" id="edit_emergency_contact_name" class="input-field"></div>
                                <div><label class="label-text">Contact Number</label><input type="text" name="emergency_contact_number" id="edit_emergency_contact_number" class="input-field" placeholder="+63 9XX XXX XXXX" oninput="formatContact(this)"></div>
                                <div><label class="label-text">Relationship</label><input type="text" name="emergency_contact_relationship" id="edit_emergency_contact_relationship" class="input-field"></div>
                             </div>
                        </div>

                        <div>
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 border-b border-gray-100 pb-1">Employment Details</h4>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div><label class="label-text">Department</label><select name="dept_id" id="edit_dept_id" class="input-field bg-white"><option value="">Select</option><?php foreach($depts as $dept): ?><option value="<?php echo $dept['dept_id']; ?>"><?php echo htmlspecialchars($dept['dept_name']); ?></option><?php endforeach; ?></select></div>
                                <div><label class="label-text">Job Title</label><input type="text" name="job_title" id="edit_job_title" class="input-field"></div>
                                
                                <div>
                                    <label class="label-text text-gray-900">Job Level</label>
                                    <select name="job_level" id="edit_job_level" class="input-field bg-white">
                                        <option value="Rank and File">Rank and File</option>
                                        <option value="Supervisor">Supervisor</option>
                                        <option value="Manager">Manager</option>
                                    </select>
                                </div>

                                <div><label class="label-text text-primary-600 font-bold">System Role</label><select name="approval_role" id="edit_approval_role" class="input-field border-primary-200 bg-primary-50 text-gray-900"><option value="Employee">Employee</option><option value="DeptHead">Dept Head</option><option value="HR">HR Admin</option><option value="CEO">CEO</option></select></div>
                                <div><label class="label-text">Supervisor</label><select name="supervisor_id" id="edit_supervisor_id" class="input-field bg-white"><option value="">Select</option><?php foreach($supervisors as $sup): ?><option value="<?php echo $sup['emp_id']; ?>"><?php echo htmlspecialchars($sup['first_name'] . ' ' . $sup['last_name']); ?></option><?php endforeach; ?></select></div>
                                
                                <div><label class="label-text">Emp Status</label><select name="employment_status" id="edit_employment_status" class="input-field bg-white"><option value="Regular">Regular</option><option value="Probationary">Probationary</option><option value="Contractual">Contractual</option><option value="Intern">Intern</option></select></div>
                                <div><label class="label-text">Emp Type</label><select name="employment_type" id="edit_employment_type" class="input-field bg-white"><option value="Full-time">Full-time</option><option value="Part-time">Part-time</option><option value="Seasonal">Seasonal</option><option value="Fixed">Fixed</option></select></div>
                                <div><label class="label-text">Location</label><input type="text" name="location_assignment" id="edit_location_assignment" class="input-field"></div>
                                <div><label class="label-text">Schedule</label><select name="work_schedule" id="edit_work_schedule" class="input-field bg-white"><option value="Office Based">Office Based</option><option value="Operations">Operations</option></select></div>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 border-b border-gray-100 pb-1">Important Dates</h4>
                            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                                <div><label class="label-text">Date Hired</label><input type="date" name="date_hired" id="edit_date_hired" class="input-field"></div>
                                <div><label class="label-text">Date Deployed</label><input type="date" name="date_deployed" id="edit_date_deployed" class="input-field"></div>
                                <div><label class="label-text">Regularized</label><input type="date" name="date_regularized" id="edit_date_regularized" class="input-field"></div>
                                <div><label class="label-text">Contract Start</label><input type="date" name="contract_start_date" id="edit_contract_start_date" class="input-field"></div>
                                <div><label class="label-text">Contract End</label><input type="date" name="contract_end_date" id="edit_contract_end_date" class="input-field"></div>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 border-b border-gray-100 pb-1">Payroll & Government & Signature</h4>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div><label class="label-text">Basic Monthly Salary</label><input type="number" step="0.01" name="salary_rate" id="edit_salary_rate" oninput="calculateRates(this, 'edit')" class="input-field"></div>
                                <div class="bg-gray-100 rounded p-1"><label class="label-text text-gray-500 text-[10px]">Daily Rate</label><input type="number" step="0.01" name="daily_rate" id="edit_daily" oninput="calculateRates(this, 'edit')" class="input-field border-none bg-transparent font-bold text-gray-700"></div>
                                <div class="bg-gray-100 rounded p-1"><label class="label-text text-gray-500 text-[10px]">Hourly Rate</label><input type="number" step="0.01" name="hourly_rate" id="edit_hourly" oninput="calculateRates(this, 'edit')" class="input-field border-none bg-transparent font-bold text-gray-700"></div>
                                <div><label class="label-text">Payroll Group</label><select name="payroll_group" id="edit_payroll_group" class="input-field bg-white"><option value="Direct Employee">Direct Employee</option><option value="Indirect">Indirect</option></select></div>
                                <div><label class="label-text">Bank Acct No</label><input type="text" name="bank_account_number" id="edit_bank_account_number" class="input-field"></div>
                                <div><label class="label-text">TIN (XXX-XXX-XXX)</label><input type="text" name="tin_number" id="edit_tin_number" maxlength="11" oninput="formatID(this, '3-3-3')" class="input-field"></div>
                                <div><label class="label-text">SSS (XX-XXXXXXX-X)</label><input type="text" name="sss_number" id="edit_sss_number" maxlength="12" oninput="formatID(this, '2-7-1')" class="input-field"></div>
                                <div><label class="label-text">PhilHealth (4-4-4)</label><input type="text" name="philhealth_number" id="edit_philhealth_number" maxlength="14" oninput="formatID(this, '4-4-4')" class="input-field"></div>
                                <div><label class="label-text">Pag-IBIG (4-4-4)</label><input type="text" name="pagibig_number" id="edit_pagibig_number" maxlength="14" oninput="formatID(this, '4-4-4')" class="input-field"></div>
                                
                                <div class="bg-yellow-50 p-2 rounded border border-yellow-200">
                                    <label class="label-text text-yellow-800">SIL Credits</label>
                                    <input type="number" step="0.5" name="sil_credits" id="edit_sil_credits" class="input-field border-yellow-300 bg-yellow-50" placeholder="0.0">
                                </div>

                                <div class="md:col-span-3"><label class="label-text font-bold text-gray-900">Update E-Signature</label><input type="file" name="signature_file" accept="image/png, image/jpeg" class="input-field bg-white"><p class="text-xs text-gray-400 mt-1">Upload to replace existing signature.</p></div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-6 border-t border-gray-100 mt-6 sticky bottom-0 bg-white z-10">
                        <button type="button" onclick="closeModal()" class="px-5 py-2.5 bg-white text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm font-bold">Cancel</button>
                        <button type="submit" class="px-5 py-2.5 bg-primary text-primary-foreground rounded-lg hover:bg-primary-600 text-sm font-bold shadow-md transition">Save All Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="viewModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-4xl sm:w-full">
                <div class="bg-gray-900 px-6 py-4 flex justify-between items-center">
                    <h3 class="text-lg font-bold text-white">Employee Profile</h3>
                    <button onclick="closeViewModal()" class="text-gray-400 hover:text-white"><i class="fa-solid fa-times text-xl"></i></button>
                </div>
                
                <div class="border-b border-gray-200 px-6 py-2 bg-gray-50 flex gap-4 overflow-x-auto">
                    <button onclick="switchViewTab('profile')" id="btn-view-profile" class="text-sm font-bold text-gray-900 border-b-2 border-primary pb-2 px-1 whitespace-nowrap transition-all">Profile Overview</button>
                    <button onclick="switchViewTab('salary')" id="btn-view-salary" class="text-sm font-medium text-gray-500 hover:text-gray-900 pb-2 px-1 border-b-2 border-transparent hover:border-gray-300 whitespace-nowrap transition-all">Salary History</button>
                    <button onclick="switchViewTab('loans')" id="btn-view-loans" class="text-sm font-medium text-gray-500 hover:text-gray-900 pb-2 px-1 border-b-2 border-transparent hover:border-gray-300 whitespace-nowrap transition-all">Loan Records</button>
                    <button onclick="switchViewTab('documents')" id="btn-view-documents" class="text-sm font-medium text-gray-500 hover:text-gray-900 pb-2 px-1 border-b-2 border-transparent hover:border-gray-300 whitespace-nowrap transition-all">Documents</button>
                </div>

                <div class="p-8 max-h-[80vh] overflow-y-auto bg-white min-h-[400px]">
                    
                    <div id="view-tab-profile" class="view-tab-content block animate-fade-in">
                        <div id="viewEmployeeContent">Loading...</div>
                    </div>

                    <div id="view-tab-salary" class="view-tab-content hidden animate-fade-in">
                        <h4 class="text-lg font-bold text-gray-800 mb-4">Salary Raise Records</h4>
                        <div class="overflow-hidden rounded-lg border border-gray-200">
                            <table class="w-full text-left text-sm" id="salaryHistoryTable">
                                <thead class="bg-gray-50 text-gray-500 font-bold text-xs uppercase border-b"><tr><th class="px-4 py-3">Date</th><th class="px-4 py-3">Prev</th><th class="px-4 py-3">New</th><th class="px-4 py-3">Diff</th></tr></thead>
                                <tbody class="divide-y divide-gray-100 text-gray-700" id="salaryHistoryBody"></tbody>
                            </table>
                        </div>
                    </div>

                    <div id="view-tab-loans" class="view-tab-content hidden animate-fade-in">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="text-lg font-bold text-gray-800">Financial History</h4>
                            <span class="text-xs text-gray-400">Tracked payments & active balances</span>
                        </div>
                        <div class="overflow-hidden rounded-lg border border-gray-200 mb-6">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-gray-50 text-gray-500 font-semibold border-b text-xs uppercase">
                                    <tr>
                                        <th class="px-4 py-3">Loan Type</th>
                                        <th class="px-4 py-3 text-right">Total</th>
                                        <th class="px-4 py-3 text-right">Paid</th>
                                        <th class="px-4 py-3 text-right">Balance</th>
                                        <th class="px-4 py-3 text-center">Status</th>
                                        <th class="px-4 py-3">Progress</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-gray-700" id="loansHistoryBody">
                                    </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-8">
                             <h4 class="text-md font-bold text-gray-700 mb-3 uppercase tracking-wide border-b border-gray-100 pb-2">Recent Payment History</h4>
                             <div class="overflow-hidden rounded-lg border border-gray-100 bg-gray-50">
                                <table class="w-full text-left text-sm">
                                    <thead class="bg-gray-100 text-gray-500 font-semibold border-b text-xs uppercase">
                                        <tr>
                                            <th class="px-4 py-2">Date</th>
                                            <th class="px-4 py-2">Loan Category</th>
                                            <th class="px-4 py-2 text-right">Amount Paid</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 text-gray-600" id="paymentHistoryBody">
                                        <tr><td colspan="3" class="px-4 py-4 text-center italic text-gray-400">No payment records found.</td></tr>
                                    </tbody>
                                </table>
                             </div>
                        </div>
                    </div>

                    <div id="view-tab-documents" class="view-tab-content hidden animate-fade-in">
                        <div class="flex justify-between items-center mb-4">
                            <h4 class="text-lg font-bold text-gray-800">Employee Documents</h4>
                            
                            <form method="POST" enctype="multipart/form-data" class="flex gap-2 items-center">
                                <input type="hidden" name="action" value="upload_document">
                                <input type="hidden" name="emp_id" id="doc_emp_id">
                                <input type="hidden" name="ac_no" id="doc_ac_no">
                                <input type="text" name="doc_name" placeholder="Document Name" class="border border-gray-300 rounded text-xs p-2 w-32 focus:ring-1 focus:ring-primary-500 outline-none" required>
                                <input type="file" name="doc_file" class="text-xs text-gray-500" required>
                                <button type="submit" class="bg-gray-900 text-white px-3 py-1.5 rounded text-xs font-bold hover:bg-black">Upload</button>
                            </form>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="documentsList">
                            </div>
                    </div>

                </div>
                
                <div class="bg-gray-100 px-6 py-3 flex justify-end">
                    <button type="button" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-bold" onclick="closeViewModal()">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function exportData() {
            const dept = document.getElementById('deptFilter').value;
            window.location.href = `employee.php?action=export_employees&dept=${encodeURIComponent(dept)}`;
        }

        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.getElementById('tab-' + tabName).classList.remove('hidden');
            document.querySelectorAll('.subtab-btn.active').forEach(el => el.classList.remove('active'));
            const btn = document.getElementById('btn-' + tabName);
            if(btn) btn.classList.add('active');
        }

        function switchViewTab(tabName) {
            document.querySelectorAll('.view-tab-content').forEach(el => el.classList.add('hidden'));
            document.getElementById('view-tab-' + tabName).classList.remove('hidden');
            
            // Reset Styles
            const tabs = ['profile', 'salary', 'loans', 'documents'];
            tabs.forEach(t => {
                document.getElementById('btn-view-' + t).className = "text-sm font-medium text-gray-500 hover:text-gray-900 pb-2 px-1 border-b-2 border-transparent hover:border-gray-300 whitespace-nowrap transition-all";
            });
            // Set Active
            document.getElementById('btn-view-' + tabName).className = "text-sm font-bold text-gray-900 border-b-2 border-primary pb-2 px-1 whitespace-nowrap transition-all";
        }

        function closeModal() { document.getElementById('editModal').classList.add('hidden'); document.getElementById('modalBackdrop').classList.add('hidden'); }
        function closeViewModal() { document.getElementById('viewModal').classList.add('hidden'); document.getElementById('modalBackdrop').classList.add('hidden'); }

        function formatContact(input) {
            let val = input.value.replace(/\D/g, ''); 
            if(val.length === 0) { input.value = ''; return; }
            if (!input.value.startsWith('+63')) { if(val.startsWith('0')) val = val.substring(1); if(val.startsWith('63')) val = val.substring(2); } 
            else { if(val.startsWith('63')) val = val.substring(2); }
            let formatted = '+63';
            if (val.length > 0) formatted += ' ' + val.substring(0, 3);
            if (val.length > 3) formatted += ' ' + val.substring(3, 6);
            if (val.length > 6) formatted += ' ' + val.substring(6, 10);
            input.value = formatted;
        }

        function getEmployeeData(empId, callback) {
            fetch('employee.php?action=get_employee_json&emp_id=' + empId)
                .then(response => response.json())
                .then(data => { if (data.success) callback(data); else alert('Error: ' + data.message); })
                .catch(error => alert('Error fetching data.'));
        }

        function editEmployee(empId) {
            getEmployeeData(empId, (data) => {
                const emp = data.employee;
                const setVal = (id, val) => { const el = document.getElementById(id); if(el) el.value = (val === null || val === undefined) ? '' : val; };
                
                // Populate Identity
                setVal('edit_emp_id', emp.emp_id); setVal('edit_ac_no', emp.ac_no); setVal('edit_first_name', emp.first_name); setVal('edit_last_name', emp.last_name); setVal('edit_middle_name', emp.middle_name);
                setVal('edit_date_of_birth', emp.date_of_birth); setVal('edit_gender', emp.gender); setVal('edit_civil_status', emp.civil_status);
                setVal('edit_nationality', emp.nationality); setVal('edit_address', emp.address); setVal('edit_contact_number', emp.contact_number);
                setVal('edit_email_address', emp.email_address);
                setVal('edit_emergency_contact_name', emp.emergency_contact_name); setVal('edit_emergency_contact_number', emp.emergency_contact_number);
                setVal('edit_emergency_contact_relationship', emp.emergency_contact_relationship);
                setVal('edit_dept_id', emp.dept_id); 
                setVal('edit_job_title', emp.job_title); 
                setVal('edit_job_level', emp.job_level); // Set Job Level
                setVal('edit_approval_role', emp.approval_role || 'Employee');
                setVal('edit_supervisor_id', emp.supervisor_id); setVal('edit_employment_status', emp.employment_status);
                setVal('edit_employment_type', emp.employment_type); setVal('edit_location_assignment', emp.location_assignment); setVal('edit_work_schedule', emp.work_schedule);
                setVal('edit_date_hired', emp.date_hired); setVal('edit_date_deployed', emp.date_deployed); setVal('edit_date_regularized', emp.date_regularized);
                setVal('edit_contract_start_date', emp.contract_start_date); setVal('edit_contract_end_date', emp.contract_end_date);
                setVal('edit_salary_rate', emp.salary_rate); setVal('edit_daily', emp.daily_rate); setVal('edit_hourly', emp.hourly_rate);
                setVal('edit_payroll_group', emp.payroll_group); setVal('edit_bank_account_number', emp.bank_account_number);
                setVal('edit_tin_number', emp.tin_number); setVal('edit_sss_number', emp.sss_number); setVal('edit_philhealth_number', emp.philhealth_number); setVal('edit_pagibig_number', emp.pagibig_number);
                
                setVal('edit_sil_credits', emp.sil_credits);

                document.getElementById('editModal').classList.remove('hidden');
                document.getElementById('modalBackdrop').classList.remove('hidden');
            });
        }

        function viewEmployee(empId) {
            getEmployeeData(empId, (data) => {
                const emp = data.employee;
                const history = data.salary_history;
                const loans = data.loans;
                const payments = data.loan_payments; // New Data
                const docs = data.documents;
                const val = (v) => v ? v : '<span class="text-gray-400 italic">N/A</span>';
                
                // Logic for avatar display
                let avatarHtml = '';
                if (emp.profile_picture && emp.profile_picture.trim() !== '') {
                    // Force a timestamp query to prevent browser caching if image changed
                    avatarHtml = `<img src="${emp.profile_picture}?t=${new Date().getTime()}" class="w-20 h-20 rounded-full object-cover border-4 border-white shadow-sm">`;
                } else {
                     avatarHtml = `
                        <div class="w-20 h-20 rounded-full bg-primary-100 text-primary-600 flex items-center justify-center font-bold text-3xl border-4 border-white shadow-sm">
                            ${(emp.first_name?.[0] || '')}${(emp.last_name?.[0] || '')}
                        </div>`;
                }

                // 1. Set Hidden inputs for Doc Upload
                document.getElementById('doc_emp_id').value = emp.emp_id;
                document.getElementById('doc_ac_no').value = emp.ac_no;

                // 2. Profile Tab
                let html = `
                    <div class="flex items-center gap-6 mb-8">
                        ${avatarHtml}
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800">${val(emp.full_name)}</h2>
                            <p class="text-primary-600 font-semibold">${val(emp.job_title)} <span class="text-gray-400 font-normal">| ${val(emp.job_level)}</span></p>
                            <div class="flex gap-2 mt-2">
                                <span class="text-xs bg-gray-200 px-2 py-1 rounded text-gray-600 font-mono">${val(emp.ac_no)}</span>
                                <span class="text-xs bg-primary-100 text-primary-700 px-2 py-1 rounded font-bold uppercase">${emp.approval_role || 'Employee'}</span>
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-y-6 gap-x-12">
                        <div><h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Department</h4><p class="font-medium">${val(emp.dept_name)}</p></div>
                        <div><h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Supervisor</h4><p class="font-medium">${val(emp.supervisor_name)}</p></div>
                        <div><h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Email</h4><p class="font-medium">${val(emp.email_address)}</p></div>
                        <div><h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Mobile</h4><p class="font-medium font-mono">${val(emp.contact_number)}</p></div>
                        <div><h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Date Hired</h4><p class="font-medium">${val(emp.date_hired)}</p></div>
                        <div><h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Current Salary</h4><p class="font-bold text-green-700">P ${val(emp.salary_rate)}</p></div>
                        <div class="col-span-2 bg-yellow-50 p-2 rounded border border-yellow-100">
                            <h4 class="text-xs font-bold text-yellow-700 uppercase tracking-wider mb-1">Service Incentive Leave</h4>
                            <p class="font-bold text-yellow-900">${val(emp.sil_credits)} Credits Available</p>
                        </div>
                    </div>
                `;
                document.getElementById('viewEmployeeContent').innerHTML = html;

                // 3. Salary History Tab
                const salaryBody = document.getElementById('salaryHistoryBody');
                salaryBody.innerHTML = '';
                if (history && history.length > 0) {
                    history.forEach(rec => {
                        const diff = parseFloat(rec.new_salary) - parseFloat(rec.old_salary);
                        const diffClass = diff >= 0 ? 'text-green-600' : 'text-red-600';
                        const dateObj = new Date(rec.change_date.date || rec.change_date);
                        salaryBody.innerHTML += `
                            <tr class="hover:bg-gray-50 border-b border-gray-100">
                                <td class="px-4 py-3 text-gray-600">${dateObj.toLocaleDateString()}</td>
                                <td class="px-4 py-3 font-mono text-gray-500">P ${parseFloat(rec.old_salary).toFixed(2)}</td>
                                <td class="px-4 py-3 font-mono font-bold text-gray-800">P ${parseFloat(rec.new_salary).toFixed(2)}</td>
                                <td class="px-4 py-3 font-mono font-bold ${diffClass}">${diff >= 0 ? '+' : ''}${diff.toFixed(2)}</td>
                            </tr>`;
                    });
                } else { salaryBody.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-gray-400 italic">No salary history.</td></tr>'; }

                // 4. Loans Tab
                const loansBody = document.getElementById('loansHistoryBody');
                loansBody.innerHTML = '';
                if (loans && loans.length > 0) {
                    loans.forEach(l => {
                        const total = parseFloat(l.total_payable);
                        const paid = parseFloat(l.paid_amount);
                        const percent = total > 0 ? Math.round((paid / total) * 100) : 0;
                        const statusClass = l.status === 'Active' ? 'bg-green-100 text-green-700' : (l.status === 'Paid' ? 'bg-gray-100 text-gray-500' : 'bg-yellow-100 text-yellow-700');
                        
                        loansBody.innerHTML += `
                            <tr class="hover:bg-gray-50 border-b border-gray-100">
                                <td class="px-4 py-3">
                                    <div class="font-bold text-gray-800">${l.loan_category}</div>
                                    <div class="text-xs text-gray-400">${l.description || '-'}</div>
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-gray-600">P ${total.toFixed(2)}</td>
                                <td class="px-4 py-3 text-right font-mono text-green-600">P ${paid.toFixed(2)}</td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-gray-800">P ${parseFloat(l.remaining_balance).toFixed(2)}</td>
                                <td class="px-4 py-3 text-center"><span class="px-2 py-1 rounded text-[10px] font-bold uppercase ${statusClass}">${l.status}</span></td>
                                <td class="px-4 py-3 align-middle">
                                    <div class="w-full bg-gray-200 rounded-full h-1.5">
                                        <div class="bg-primary-500 h-1.5 rounded-full" style="width: ${percent}%"></div>
                                    </div>
                                    <span class="text-[10px] text-gray-400">${percent}%</span>
                                </td>
                            </tr>`;
                    });
                } else { loansBody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 italic">No loan records found.</td></tr>'; }

                // 4b. Payment History
                const paymentsBody = document.getElementById('paymentHistoryBody');
                paymentsBody.innerHTML = '';
                if (payments && payments.length > 0) {
                    payments.forEach(p => {
                        const dateObj = new Date(p.payment_date.date || p.payment_date);
                        paymentsBody.innerHTML += `
                            <tr class="hover:bg-gray-50 border-b border-gray-100">
                                <td class="px-4 py-3 text-gray-600">${dateObj.toLocaleDateString()}</td>
                                <td class="px-4 py-3 font-medium text-gray-700">${p.loan_category}</td>
                                <td class="px-4 py-3 text-right font-mono text-green-600 font-bold">P ${parseFloat(p.amount_paid).toFixed(2)}</td>
                            </tr>`;
                    });
                } else { 
                    paymentsBody.innerHTML = '<tr><td colspan="3" class="px-4 py-8 text-center text-gray-400 italic">No payment history available.</td></tr>'; 
                }

                // 5. Documents Tab
                const docsList = document.getElementById('documentsList');
                docsList.innerHTML = '';
                if(docs && docs.length > 0) {
                    docs.forEach(d => {
                        docsList.innerHTML += `
                            <div class="flex items-center justify-between p-3 border border-gray-200 rounded-lg bg-gray-50">
                                <div class="flex items-center gap-3">
                                    <div class="text-red-500 text-xl"><i class="fa-solid fa-file-pdf"></i></div>
                                    <div><div class="text-sm font-bold text-gray-700">${d.doc_name}</div><div class="text-xs text-gray-400">${new Date(d.uploaded_at.date || d.uploaded_at).toLocaleDateString()}</div></div>
                                </div>
                                <div class="flex gap-2">
                                    <a href="${d.file_path}" target="_blank" class="text-blue-600 hover:text-blue-800 text-xs font-bold"><i class="fa-solid fa-eye"></i></a>
                                    <form method="POST" onsubmit="return confirm('Delete this document?')">
                                        <input type="hidden" name="action" value="delete_document">
                                        <input type="hidden" name="doc_id" value="${d.doc_id}">
                                        <button type="submit" class="text-red-500 hover:text-red-700 text-xs"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        `;
                    });
                } else { docsList.innerHTML = '<p class="col-span-2 text-center text-gray-400 italic py-4">No documents uploaded.</p>'; }

                // Reset to Profile
                switchViewTab('profile');
                document.getElementById('viewModal').classList.remove('hidden');
                document.getElementById('modalBackdrop').classList.remove('hidden');
            });
        }

        function calculateRates(element, prefix) {
            let monthlyInput = document.getElementById(prefix === 'add' ? 'add_salary' : 'edit_salary_rate');
            let dailyInput = document.getElementById(prefix === 'add' ? 'add_daily' : 'edit_daily');
            let hourlyInput = document.getElementById(prefix === 'add' ? 'add_hourly' : 'edit_hourly');
            let val = parseFloat(element.value);
            if (isNaN(val) || val === 0) return; 
            const workdaysInMonth = 313 / 12;
            if (element === monthlyInput) {
                let daily = val / workdaysInMonth; let hourly = daily / 8;
                dailyInput.value = daily.toFixed(2); hourlyInput.value = hourly.toFixed(2);
            } 
        }

        function softDeleteEmployee(id, name) { if(confirm('Move ' + name + ' to Recycle Bin?')) createPost(id, 'soft_delete'); }
        function restoreEmployee(id) { if(confirm('Restore employee?')) createPost(id, 'restore'); }
        function hardDeleteEmployee(id, name) { if(confirm('PERMANENTLY DELETE ' + name + '? This cannot be undone.')) createPost(id, 'hard_delete'); }
        
        function createPost(id, action) {
            const f = document.createElement('form'); f.method = 'POST'; f.action = '';
            f.innerHTML = `<input type="hidden" name="action" value="${action}"><input type="hidden" name="emp_id" value="${id}">`;
            document.body.appendChild(f); f.submit();
        }
    </script>