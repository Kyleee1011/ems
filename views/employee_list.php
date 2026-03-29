<div class="card" style="border:none; box-shadow:none; margin-bottom:0;">
    <div class="card-head" style="background:var(--bg-card); border-bottom:1px solid var(--border-lt); padding:15px 20px;">
        <div class="flex-row">
            <span class="tag tag-teal"><?php echo $totalRecords; ?> Total</span>
        </div>
        <form method="GET" action="" id="searchForm" class="flex-row">
            <input type="hidden" name="tab" value="directory">
            <select name="dept" id="deptFilter" onchange="this.form.submit()" class="input-field" style="width:180px; height:32px; font-size:11px;">
                <option value="">All Departments</option>
                <?php foreach($depts as $d): ?>
                    <option value="<?php echo $d['dept_id']; ?>" <?php echo ($filterDept == $d['dept_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($d['dept_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div style="position:relative;">
                <i class="fa-solid fa-search" style="position:absolute; left:10px; top:10px; font-size:10px; color:var(--ink-4);"></i>
                <input type="text" name="search" placeholder="Search name or AC..." value="<?php echo htmlspecialchars($searchTerm); ?>" class="input-field" style="padding-left:30px; width:220px; height:32px; font-size:11px;">
            </div>
            <button type="submit" class="btn-primary" style="height:32px; padding:0 15px;">Search</button>
        </form>
    </div>

    <div class="table-wrap" style="border:none; border-radius:0;">
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Position & Dept</th>
                    <th>Status</th>
                    <th>Hired Date</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($activeEmployees) > 0): ?>
                    <?php foreach($activeEmployees as $emp): ?>
                        <tr>
                            <td>
                                <div class="flex-row">
                                    <div class="avatar" style="width:32px; height:32px;">
                                        <?php if(!empty($emp['profile_picture']) && file_exists($emp['profile_picture'])): ?>
                                            <img src="<?php echo htmlspecialchars($emp['profile_picture']) . '?t=' . time(); ?>">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($emp['first_name'], 0, 1) . substr($emp['last_name'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div style="font-weight:700; color:var(--ink-1); font-size:13px;"><?php echo htmlspecialchars($emp['full_name']); ?></div>
                                        <div style="font-size:10px; color:var(--ink-4); font-family:'DM Mono';">AC: <?php echo htmlspecialchars($emp['ac_no']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight:600; font-size:12px;"><?php echo htmlspecialchars($emp['job_title'] ?? 'N/A'); ?></div>
                                <div style="font-size:10px; color:var(--ink-4);"><?php echo htmlspecialchars($emp['dept_name'] ?? 'N/A'); ?></div>
                            </td>
                            <td><span class="tag tag-green">Active</span></td>
                            <td><span style="font-size:11px; color:var(--ink-3);"><?php echo $emp['date_hired'] ? date('M d, Y', strtotime($emp['date_hired'])) : '-'; ?></span></td>
                            <td style="text-align: right;">
                                <div class="flex-row" style="justify-content: flex-end; gap:4px;">
                                    <button onclick="viewEmployee(<?php echo $emp['emp_id']; ?>)" class="icon-btn" title="View"><i class="fa-solid fa-eye" style="font-size:11px;"></i></button>
                                    <button onclick="editEmployee(<?php echo $emp['emp_id']; ?>)" class="icon-btn" title="Edit"><i class="fa-solid fa-pen" style="font-size:11px;"></i></button>
                                    <button onclick="softDeleteEmployee(<?php echo $emp['emp_id']; ?>)" class="icon-btn" style="color:var(--red);" title="Mark as Inactive"><i class="fa-solid fa-user-slash" style="font-size:11px;"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; padding:50px; color:var(--ink-4);">No employees matching criteria.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
        
    <?php if ($totalPages > 1): ?>
    <div class="card-footer" style="padding:10px 20px;">
        <div class="flex-between">
            <div style="font-size:11px; color:var(--ink-4);">
                Showing <?php echo ($page-1)*$limit + 1; ?> to <?php echo min($page*$limit, $totalRecords); ?> of <?php echo $totalRecords; ?>
            </div>
            <div class="flex-row" style="gap:5px;">
                <?php for($i=1; $i<=$totalPages; $i++): ?>
                    <a href="?tab=directory&page=<?php echo $i; ?>&dept=<?php echo $filterDept; ?>&search=<?php echo $searchTerm; ?>" 
                       class="pill-btn" style="<?php echo ($i==$page) ? 'background:var(--teal); color:#fff; border-color:var(--teal-deep);' : ''; ?> padding:2px 10px; font-size:11px;">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
