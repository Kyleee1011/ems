<?php
$pageTitle = 'Workforce Insights — Azzurro HR';
require BASE_PATH . '/partials/layout_head.php';
require BASE_PATH . '/partials/layout_topbar.php';
require BASE_PATH . '/partials/layout_sidebar.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-gauge-high"></i> Workforce Analytics</h1>
        <p class="page-sub">Real-time metrics on attendance and organizational health</p>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card c-red gsap-reveal" style="opacity:0; transform:translateY(20px);">
        <div class="stat-ico-wrap ico-red"><i class="fa-solid fa-user-slash"></i></div>
        <div class="stat-lbl">Highest Absenteeism</div>
        <?php if ($highestAbsentDept): ?>
            <div class="stat-val" style="color:var(--red);"><?php echo htmlspecialchars($highestAbsentDept['dept_name']); ?></div>
            <div class="stat-meta"><b><?php echo $highestAbsentDept['absent_count']; ?></b> Absences (30d)</div>
        <?php else: ?>
            <div class="stat-val" style="color:var(--ink-4);">No Data</div>
        <?php endif; ?>
    </div>

    <div class="stat-card c-amber gsap-reveal" style="opacity:0; transform:translateY(20px);">
        <div class="stat-ico-wrap ico-amber"><i class="fa-solid fa-hourglass-half"></i></div>
        <div class="stat-lbl">Frequent Lateness</div>
        <?php if ($highestLateDept): ?>
            <div class="stat-val" style="color:var(--amber);"><?php echo htmlspecialchars($highestLateDept['dept_name']); ?></div>
            <div class="stat-meta"><b><?php echo $highestLateDept['late_count']; ?></b> Occurrences (30d)</div>
        <?php else: ?>
            <div class="stat-val" style="color:var(--ink-4);">No Data</div>
        <?php endif; ?>
    </div>
</div>

<div class="grid-2 mt-20" style="grid-template-columns: 1.2fr 1fr; gap: 25px; align-items: start;">
    <!-- LATE WATCH -->
    <div class="card gsap-reveal" style="opacity:0; transform:translateY(20px);">
        <div class="card-head"><div class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> Late Watch (Current Week)</div></div>
        <div class="card-body" style="max-height: 500px; overflow-y: auto; padding: 15px;">
            <?php if (!empty($lateEmployees)): foreach($lateEmployees as $late): ?>
                <div class="flex-between mb-10" style="background: var(--bg-subtle); padding: 12px; border-radius: 12px; border-left: 4px solid var(--red);">
                    <div class="flex-row">
                        <div class="avatar" style="width:36px; height:36px; background:var(--red-bg); color:var(--red);">
                            <?php echo substr($late['name'], 0, 1); ?>
                        </div>
                        <div>
                            <div style="font-weight:700; font-size:13px;"><?php echo htmlspecialchars($late['name']); ?></div>
                            <div style="font-size:10px; color:var(--ink-4);"><?php echo htmlspecialchars($late['department']); ?></div>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <span class="tag tag-red" style="font-size:9px;"><?php echo $late['minutes_late']; ?>m LATE</span>
                        <div style="font-size:10px; font-family:'DM Mono'; font-weight:700; color:var(--ink-3); margin-top:4px;"><?php echo $late['actual_in']; ?></div>
                    </div>
                </div>
            <?php endforeach; else: ?>
                <div style="text-align:center; padding:50px; color:var(--ink-4);">
                    <i class="fa-solid fa-circle-check" style="font-size:30px; margin-bottom:10px; opacity:0.3; color:var(--green);"></i>
                    <p>No late arrivals recorded this week.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- EMPLOYEES ON LEAVE -->
    <div class="card gsap-reveal" style="opacity:0; transform:translateY(20px);">
        <div class="card-head">
            <div class="card-title"><i class="fa-solid fa-plane-departure"></i> Active Leaves</div>
            <span class="tag tag-blue"><?php echo count($employeesOnLeave); ?> On Leave</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <div class="table-wrap" style="border:none;">
                <table>
                    <thead>
                        <tr><th>Employee</th><th>Type</th><th style="text-align:right;">Duration</th></tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($employeesOnLeave)): foreach($employeesOnLeave as $lv): ?>
                        <tr>
                            <td style="font-weight:600;"><?php echo htmlspecialchars($lv['last_name']); ?></td>
                            <td><span class="tag tag-teal" style="font-size:8px;"><?php echo htmlspecialchars($lv['leave_type']); ?></span></td>
                            <td style="text-align:right; font-size:10px; color:var(--ink-4);"><?php echo date('M d', strtotime($lv['start_date'])); ?> - <?php echo date('M d', strtotime($lv['end_date'])); ?></td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="3" style="text-align:center; padding:40px; color:var(--ink-4);">No active leaves today.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        gsap.to(".gsap-reveal", {
            opacity: 1,
            y: 0,
            duration: 0.5,
            stagger: 0.1,
            ease: "power2.out"
        });
    });
</script>

<?php require BASE_PATH . '/partials/layout_footer.php'; ?>
