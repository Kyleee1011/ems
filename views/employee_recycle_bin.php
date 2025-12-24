        <div id="tab-recycle" class="tab-content hidden fade-in">
             <div class="stat-card p-0 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-red-50/50 flex items-center gap-2"><i class="fa-solid fa-trash-can text-red-400"></i><h3 class="font-bold text-gray-800">Recycle Bin</h3></div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-500">
                        <thead class="bg-gray-50 text-xs uppercase font-semibold text-gray-400 border-b border-gray-200">
                            <tr><th class="px-6 py-3">Employee</th><th class="px-6 py-3">Job Title</th><th class="px-6 py-3 text-right">Actions</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach($recycleBinEmployees as $emp): ?>
                                <tr class="hover:bg-red-50/20"><td class="px-6 py-3 font-medium text-gray-700"><?php echo htmlspecialchars($emp['full_name']); ?></td><td class="px-6 py-3"><?php echo htmlspecialchars($emp['job_title']); ?></td><td class="px-6 py-3 text-right"><button onclick="restoreEmployee(<?php echo $emp['emp_id']; ?>)" class="text-green-600 hover:text-green-800 text-xs font-bold px-3 py-1 bg-green-50 rounded border border-green-200">Restore</button></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
             </div>
        </div>
