    <div id="tab-directory" class="tab-content block fade-in space-y-6">
        <div class="stat-card overflow-hidden p-0">
            <div class="p-4 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gray-50/50">
                <div class="flex items-center gap-3">
                    <span class="bg-primary-100 text-primary-600 text-xs px-2.5 py-1 rounded-full font-bold uppercase tracking-wide"><?php echo $totalRecords; ?> Records</span>
                </div>
                <form method="GET" action="" id="searchForm" class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto items-center">
                    <select name="dept" id="deptFilter" onchange="this.form.submit()" class="bg-white border border-gray-300 text-sm rounded-md px-3 py-2 outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="">All Departments</option>
                        <?php foreach($depts as $d): ?>
                            <option value="<?php echo htmlspecialchars($d['dept_name']); ?>" <?php echo ($filterDept == $d['dept_name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d['dept_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="relative">
                        <i class="fa-solid fa-search absolute left-3 top-3 text-gray-400 text-xs"></i>
                        <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($searchTerm); ?>" class="pl-9 pr-4 py-2 border border-gray-300 rounded-md text-sm w-full sm:w-64 outline-none focus:ring-2 focus:ring-primary-500">
                    </div>
                    <button type="submit" class="bg-gray-900 text-white px-4 py-2 rounded-md text-sm font-bold hover:bg-black transition">Search</button>
                    
                    <button type="button" onclick="exportData()" class="bg-primary text-primary-foreground px-4 py-2 rounded-md text-sm font-bold hover:bg-primary-600 flex items-center gap-2 transition shadow-sm">
                        <i class="fa-solid fa-file-export"></i> Export CSV
                    </button>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-gray-600">
                    <thead class="bg-gray-50 text-xs text-gray-500 uppercase font-semibold border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-4">Employee</th>
                            <th class="px-6 py-4">Position</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Contact</th>
                            <th class="px-6 py-4">Hired Date</th>
                            <th class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <?php if (count($activeEmployees) > 0): ?>
                            <?php foreach($activeEmployees as $emp): ?>
                                <tr class="hover:bg-primary-50/20 transition-colors group">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-9 h-9 rounded-full bg-primary-100 text-primary-600 flex items-center justify-center font-bold text-xs border border-primary-100 overflow-hidden">
                                                <?php if(!empty($emp['profile_picture']) && file_exists($emp['profile_picture'])): ?>
                                                    <img src="<?php echo htmlspecialchars($emp['profile_picture']) . '?t=' . time(); ?>" class="w-full h-full object-cover">
                                                <?php else: ?>
                                                    <?php echo strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1)); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="font-bold text-gray-900 group-hover:text-primary-600 transition"><?php echo htmlspecialchars($emp['full_name']); ?></div>
                                                <div class="text-[11px] text-gray-400 font-mono"><?php echo htmlspecialchars($emp['ac_no']); ?></div>
                                            </div>
                                        </td>
                                    <td class="px-6 py-4">
                                        <div class="text-gray-900 font-medium"><?php echo htmlspecialchars($emp['job_title'] ?? 'N/A'); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($emp['dept_name'] ?? 'N/A'); ?></div>
                                    </td>
                                    <td class="px-6 py-4"><span class="px-2.5 py-0.5 rounded text-[10px] font-bold uppercase bg-green-50 text-green-600 border border-green-100">Active</span></td>
                                    <td class="px-6 py-4"><div class="text-xs font-mono text-gray-500"><?php echo htmlspecialchars($emp['contact_number'] ?? '-'); ?></div></td>
                                    <td class="px-6 py-4"><span class="text-xs text-gray-500"><?php echo $emp['date_hired'] ? date('M d, Y', strtotime($emp['date_hired'])) : '-'; ?></span></td>
                                    <td class="px-6 py-4 text-right">
                                        <div class="flex items-center justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <button onclick="viewEmployee(<?php echo $emp['emp_id']; ?>)" class="text-gray-400 hover:text-gray-900 p-2 rounded hover:bg-gray-100"><i class="fa-solid fa-eye"></i></button>
                                            <button onclick="editEmployee(<?php echo $emp['emp_id']; ?>)" class="text-gray-400 hover:text-primary-600 p-2 rounded hover:bg-primary-50"><i class="fa-solid fa-pen"></i></button>
                                            <button onclick="softDeleteEmployee(<?php echo $emp['emp_id']; ?>, '<?php echo htmlspecialchars($emp['full_name']); ?>')" class="text-gray-400 hover:text-red-600 p-2 rounded hover:bg-red-50"><i class="fa-solid fa-trash-arrow-up"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="px-6 py-12 text-center text-gray-400"><p>No employees matching criteria.</p></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
