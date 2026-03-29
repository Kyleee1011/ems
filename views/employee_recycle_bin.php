             <div class="stat-card p-0 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-red-50/50 flex items-center gap-2"><i class="fa-solid fa-user-slash text-red-400"></i><h3 class="font-bold text-gray-800">Inactive Employees List</h3></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-500">
                        <thead class="bg-gray-50 text-xs uppercase font-semibold text-gray-400 border-b border-gray-200">
                            <tr><th class="px-6 py-3">Employee</th><th class="px-6 py-3">Job Title</th><th class="px-6 py-3 text-right">Actions</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach($inactiveEmployees as $emp): ?>
                                <tr class="hover:bg-red-50/20">
                                    <td class="px-6 py-3 font-medium text-gray-700"><?php echo htmlspecialchars($emp['full_name']); ?></td>
                                    <td class="px-6 py-3"><?php echo htmlspecialchars($emp['job_title']); ?></td>
                                    <td class="px-6 py-3 text-right">
                                        <div class="flex-row" style="justify-content: flex-end; gap: 8px;">
                                            <button onclick="restoreEmployee(<?php echo $emp['emp_id']; ?>)" class="pill-btn" style="background: var(--green-bg); color: var(--green); border-color: var(--green-bdr);">
                                                <i class="fa-solid fa-user-check"></i> Set Active
                                            </button>
                                            <button onclick="hardDeleteEmployee(<?php echo $emp['emp_id']; ?>)" class="pill-btn" style="background: var(--red-bg); color: var(--red); border-color: var(--red-bdr);">
                                                <i class="fa-solid fa-trash-can"></i> Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
             </div>
