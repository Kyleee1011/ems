<?php
$pageTitle = 'Organization Chart — Azzurro HR';
require BASE_PATH . '/partials/layout_head.php';
require BASE_PATH . '/partials/layout_topbar.php';
require BASE_PATH . '/partials/layout_sidebar.php';

$ceo = array_filter($employees, fn($e) => $e['approval_role'] === 'CEO');
$ceoEmployee = !empty($ceo) ? reset($ceo) : null;
$ceoName = $ceoEmployee ? $ceoEmployee['full_name'] : 'Executive Board';
?>

<!-- NOT FOUND ERROR STATE -->
<?php if (!empty($departmentNotFound)): ?>
<div class="card" style="padding:60px 20px; text-align:center; max-width:600px; margin:40px auto; border-radius:16px;">
    <div style="width:64px; height:64px; border-radius:50%; background:#fef3c7; color:#d97706; display:flex; align-items:center; justify-content:center; font-size:28px; margin:0 auto 16px auto;">
        <i class="fa-solid fa-triangle-exclamation"></i>
    </div>
    <h2 style="font-size:20px; font-weight:800; color:var(--text-color, #0f172a); margin-bottom:8px;">Department Not Found</h2>
    <p style="color:var(--text-muted, #64748b); font-size:13.5px; line-height:1.5; margin-bottom:24px;">
        The requested department (ID: <?php echo htmlspecialchars((string)($selectedDeptId ?? '')); ?>) does not exist or has been removed from the system.
    </p>
    <a href="<?php echo baseUrl('orgchart'); ?>" class="btn btn-primary" style="display:inline-flex; align-items:center; gap:8px; padding:10px 20px; font-size:13px;">
        <i class="fa-solid fa-arrow-left"></i> Return to Full Organization Chart
    </a>
</div>
<?php require BASE_PATH . '/partials/layout_footer.php'; exit; endif; ?>

<!-- PAGE HEADER -->
<div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:16px; flex-wrap:wrap; gap:12px;">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-sitemap" style="color:var(--teal, #008080); margin-right:8px;"></i> Organization Chart</h1>
        <p class="page-sub">Workforce reporting hierarchy with department color zones and drill-downs</p>
    </div>
    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <!-- Show Zones Toggle -->
        <label style="display:inline-flex; align-items:center; gap:8px; font-size:12px; font-weight:600; color:var(--text-color, #334155); cursor:pointer; background:var(--bg-card, #fff); border:1px solid var(--border-color, #cbd5e1); padding:0 12px; height:36px; border-radius:8px; user-select:none;">
            <input type="checkbox" id="toggleZonesCheck" checked onchange="toggleDepartmentZones(this.checked)" style="accent-color:var(--teal, #008080); cursor:pointer;">
            <span><i class="fa-solid fa-layer-group" style="margin-right:4px; color:var(--teal, #008080);"></i> Department Zones</span>
        </label>

        <!-- Search Input -->
        <div style="position:relative; width:220px;">
            <i class="fa-solid fa-search" style="position:absolute; left:10px; top:11px; font-size:11px; color:var(--ink-4, #94a3b8);"></i>
            <input type="text" id="orgSearch" placeholder="Search employee, role..." 
                   class="input-field" style="padding-left:30px; height:36px; font-size:12px; width:100%;">
        </div>

        <!-- Zoom Controls -->
        <div id="treeControls" style="display:flex; gap:6px;">
            <button onclick="resetZoom()" class="btn btn-ghost" title="Reset View & Center" style="height:36px; padding:0 12px; border:1px solid var(--border-color, #ccc); font-size:12px;">
                <i class="fa-solid fa-arrows-rotate"></i> Reset
            </button>
            <button onclick="zoomIn()" class="btn btn-ghost" title="Zoom In" style="height:36px; width:36px; padding:0; border:1px solid var(--border-color, #ccc);">
                <i class="fa-solid fa-magnifying-glass-plus"></i>
            </button>
            <button onclick="zoomOut()" class="btn btn-ghost" title="Zoom Out" style="height:36px; width:36px; padding:0; border:1px solid var(--border-color, #ccc);">
                <i class="fa-solid fa-magnifying-glass-minus"></i>
            </button>
        </div>

        <!-- Export Dropdown / Actions -->
        <div style="display:flex; gap:6px;">
            <button onclick="exportChartAsPng()" class="btn btn-ghost" title="Export as PNG" style="height:36px; padding:0 12px; border:1px solid var(--border-color, #ccc); font-size:12px;">
                <i class="fa-solid fa-image"></i> PNG
            </button>
            <button onclick="window.print()" class="btn btn-primary" style="height:36px; padding:0 14px; font-size:12px;">
                <i class="fa-solid fa-print"></i> Print / PDF
            </button>
        </div>
    </div>
</div>

<!-- CROSS-DEPARTMENT SEARCH NOTIFICATION BANNER -->
<div id="crossDeptBanner" style="display:none; background:#eff6ff; border:1px solid #bfdbfe; border-radius:10px; padding:10px 16px; margin-bottom:14px; justify-content:space-between; align-items:center;">
    <div style="font-size:12px; color:#1e40af; display:flex; align-items:center; gap:8px;">
        <i class="fa-solid fa-circle-info"></i>
        <span id="crossDeptText">Employee found in another department.</span>
    </div>
    <button id="crossDeptBtn" class="btn btn-sm" style="background:#2563eb; color:#fff; font-size:11px; padding:3px 12px; border-radius:6px; border:none; cursor:pointer;">
        View in Department
    </button>
</div>

<!-- DEPARTMENT SELECTOR CHIP BAR -->
<div class="card" style="padding:10px 14px; margin-bottom:14px; overflow-x:auto; white-space:nowrap; display:flex; gap:8px; align-items:center; border-radius:12px;">
    <span style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--text-muted, #64748b); margin-right:4px;">
        <i class="fa-solid fa-filter"></i> View:
    </span>
    <button type="button" class="dept-chip active" id="chip-dept-0" onclick="selectDepartment(0)">
        <span class="chip-swatch" style="background:var(--teal, #008080);"></span>
        All Departments (<?php echo count($employees); ?>)
    </button>
    <?php foreach ($departments as $dept): 
        $dId = (int)$dept['dept_id'];
        $dCount = count(array_filter($employees, fn($e) => (int)$e['dept_id'] === $dId));
    ?>
    <button type="button" class="dept-chip" id="chip-dept-<?php echo $dId; ?>" onclick="selectDepartment(<?php echo $dId; ?>)">
        <span class="chip-swatch" style="background:<?php echo htmlspecialchars($dept['color']); ?>;"></span>
        <?php echo htmlspecialchars($dept['dept_name']); ?> (<?php echo $dCount; ?>)
    </button>
    <?php endforeach; ?>
</div>

<!-- REPORTING LINE BREADCRUMB (Visible in Department View) -->
<div id="deptBreadcrumbBanner" class="card" style="display:none; padding:12px 18px; margin-bottom:14px; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; border-left:4px solid var(--teal, #008080);">
    <div style="display:flex; align-items:center; gap:8px; font-size:12.5px;">
        <a href="javascript:void(0)" onclick="selectDepartment(0)" style="color:var(--text-muted, #64748b); text-decoration:none; font-weight:600;">
            <i class="fa-solid fa-house" style="font-size:11px;"></i> All Organization
        </a>
        <i class="fa-solid fa-chevron-right" style="font-size:10px; color:#cbd5e1;"></i>
        <span style="color:var(--text-muted, #64748b);">Reports to: <strong id="bcCeoName" style="color:var(--text-color, #1e293b);"><?php echo htmlspecialchars($ceoName); ?></strong> (CEO)</span>
        <i class="fa-solid fa-chevron-right" style="font-size:10px; color:#cbd5e1;"></i>
        <span id="bcDeptName" style="font-weight:700; color:var(--teal, #008080);">Department</span>
        <span id="bcMemberBadge" style="font-size:11px; padding:2px 8px; border-radius:99px; background:#f1f5f9; font-weight:700;">0 Members</span>
    </div>
    <div style="display:flex; gap:8px; align-items:center;">
        <?php if (!empty($canEditColors)): ?>
        <button type="button" id="bcChangeColorBtn" onclick="openCurrentDeptColorModal()" class="btn btn-sm btn-ghost" style="font-size:11px; height:28px; padding:0 10px; border:1px solid #cbd5e1;">
            <i class="fa-solid fa-palette" style="margin-right:4px;"></i> Department Color
        </button>
        <?php endif; ?>
        <button type="button" onclick="selectDepartment(0)" class="btn btn-sm btn-ghost" style="font-size:11px; height:28px; padding:0 10px;">
            <i class="fa-solid fa-compress" style="margin-right:4px;"></i> Show Full Company View
        </button>
    </div>
</div>

<!-- INTERACTIVE CANVAS VIEWPORT -->
<div id="treeViewContainer" class="card" style="padding:0; overflow:hidden; position:relative; min-height:680px; background:var(--bg-card, #ffffff);">
    <div style="position:absolute; top:12px; left:16px; z-index:10; background:rgba(255,255,255,0.85); backdrop-filter:blur(4px); padding:4px 10px; border-radius:8px; border:1px solid rgba(0,0,0,0.06); font-size:11px; color:var(--text-muted, #666);">
        <i class="fa-solid fa-hand-pointer" style="margin-right:4px;"></i> Drag to pan canvas &bull; Scroll to zoom
    </div>

    <div id="chartViewport" style="overflow:auto; width:100%; height:720px; padding:40px; cursor:grab; user-select:none; background:radial-gradient(circle, #cbd5e1 1.2px, transparent 1.2px); background-size:16px 16px;">
        <div id="chartContainer" style="transform-origin: top center; transition: transform 0.15s ease; width:max-content; min-width:100%; display:inline-flex; flex-direction:column; align-items:center; padding:30px 60px; margin:0 auto;">
            <!-- Tree nodes dynamically rendered here -->
        </div>
    </div>
</div>

<!-- DEPARTMENT COLOR LEGEND BAR -->
<div class="card" style="padding:14px 18px; margin-top:14px; border-radius:12px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; flex-wrap:wrap; gap:8px;">
        <div style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--text-muted, #64748b); letter-spacing:0.4px;">
            <i class="fa-solid fa-palette" style="margin-right:5px; color:var(--teal, #008080);"></i> Department Color Zones Legend
        </div>
        <?php if (!empty($canEditColors)): ?>
        <span style="font-size:11px; color:var(--teal, #008080); font-weight:600;">
            <i class="fa-solid fa-shield-halved"></i> HR / CEO Mode: Click any swatch to customize palette color
        </span>
        <?php endif; ?>
    </div>
    <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
        <!-- Neutral Executive Swatch -->
        <div class="legend-pill" title="Executive Board / CEO">
            <span class="legend-swatch" style="background:#0F172A;"></span>
            <span>Executive / Owner</span>
        </div>
        <!-- Department Swatches -->
        <?php foreach ($departments as $d): ?>
        <div class="legend-pill <?php echo !empty($canEditColors) ? 'clickable' : ''; ?>" 
             onclick="<?php echo !empty($canEditColors) ? "openColorPickerModal({$d['dept_id']}, '" . addslashes($d['dept_name']) . "', '{$d['color']}')" : ''; ?>"
             title="<?php echo htmlspecialchars($d['dept_name']); ?><?php echo !empty($canEditColors) ? ' (Click to change color)' : ''; ?>">
            <span class="legend-swatch" id="legend-swatch-<?php echo $d['dept_id']; ?>" style="background:<?php echo htmlspecialchars($d['color']); ?>;"></span>
            <span><?php echo htmlspecialchars($d['dept_name']); ?></span>
            <?php if (!empty($canEditColors)): ?>
            <i class="fa-solid fa-pen" style="font-size:8px; opacity:0.5; margin-left:3px;"></i>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <!-- Open Position Swatch -->
        <div class="legend-pill" title="Future headcount vacancy">
            <span class="legend-swatch" style="background:#fff; border:1.5px dashed #94a3b8;"></span>
            <span>Open Position</span>
        </div>
    </div>
</div>

<!-- CHANGE SUPERVISOR MODAL (HR / CEO / DEPT HEAD ONLY) -->
<?php if (!empty($canEditAll) || !empty($isDeptHead)): ?>
<div id="reassignModalBackdrop" class="modal-overlay" style="position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:1000; display:none;" onclick="closeReassignModal()"></div>
<div id="reassignModal" class="modal-container" style="position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:460px; background:var(--bg-card, #fff); border-radius:16px; border:1px solid var(--border-color, #ccc); box-shadow:0 10px 30px rgba(0,0,0,0.2); z-index:1001; display:none; flex-direction:column;">
    <div style="padding:16px 20px; border-bottom:1px solid var(--border-color, #eee); display:flex; justify-content:space-between; align-items:center;">
        <h3 style="font-size:14px; font-weight:700; color:var(--text-color, #111);"><i class="fa-solid fa-user-pen" style="color:var(--teal, #008080); margin-right:8px;"></i>Reassign Reporting Manager</h3>
        <button onclick="closeReassignModal()" style="background:none; border:none; cursor:pointer; font-size:16px; color:#888;">&times;</button>
    </div>
    <form id="reassignForm" style="padding:20px;">
        <input type="hidden" id="reassignEmpId" name="emp_id">
        <?php echo csrfField(); ?>

        <div style="margin-bottom:14px;">
            <label style="font-size:11px; font-weight:700; color:var(--text-muted, #666); display:block; margin-bottom:4px;">EMPLOYEE</label>
            <div id="reassignEmpName" style="font-size:14px; font-weight:700; color:var(--text-color, #111);"></div>
            <div id="reassignEmpTitle" style="font-size:11.5px; color:var(--teal, #008080);"></div>
            <div id="reassignEmpDept" style="font-size:11px; color:var(--text-muted, #666); margin-top:2px;"></div>
        </div>

        <div style="margin-bottom:20px;">
            <label style="font-size:11px; font-weight:700; color:var(--text-muted, #666); display:block; margin-bottom:6px;">NEW REPORTING SUPERVISOR</label>
            <select id="reassignSupervisorSelect" name="supervisor_id" class="input-field" style="width:100%; height:38px; font-size:13px;">
                <option value="">-- Reports Directly to Owner / CEO --</option>
                <?php foreach ($employees as $mgr): ?>
                    <option value="<?php echo $mgr['emp_id']; ?>" data-dept="<?php echo (int)$mgr['dept_id']; ?>">
                        <?php echo htmlspecialchars($mgr['full_name'] . ' (' . ($mgr['job_title'] ?? $mgr['dept_name']) . ' - ' . ($mgr['dept_name'] ?? 'Staff') . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px;">
            <button type="button" onclick="closeReassignModal()" class="btn btn-ghost" style="font-size:12px; height:34px;">Cancel</button>
            <button type="submit" class="btn btn-primary" style="font-size:12px; height:34px; padding:0 18px;">
                <i class="fa-solid fa-check"></i> Save Reassignment
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- EDIT DEPARTMENT COLOR MODAL (HR / CEO ONLY) -->
<?php if (!empty($canEditColors)): ?>
<div id="colorModalBackdrop" class="modal-overlay" style="position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:1000; display:none;" onclick="closeColorPickerModal()"></div>
<div id="colorModal" class="modal-container" style="position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:440px; background:var(--bg-card, #fff); border-radius:16px; border:1px solid var(--border-color, #ccc); box-shadow:0 10px 30px rgba(0,0,0,0.2); z-index:1001; display:none; flex-direction:column;">
    <div style="padding:16px 20px; border-bottom:1px solid var(--border-color, #eee); display:flex; justify-content:space-between; align-items:center;">
        <h3 style="font-size:14px; font-weight:700; color:var(--text-color, #111);">
            <i class="fa-solid fa-palette" style="color:var(--teal, #008080); margin-right:8px;"></i>
            Change Department Color
        </h3>
        <button onclick="closeColorPickerModal()" style="background:none; border:none; cursor:pointer; font-size:16px; color:#888;">&times;</button>
    </div>
    <form id="deptColorForm" style="padding:20px;">
        <input type="hidden" id="colorDeptId" name="dept_id">
        <?php echo csrfField(); ?>

        <div style="margin-bottom:14px;">
            <label style="font-size:11px; font-weight:700; color:var(--text-muted, #666); display:block; margin-bottom:4px;">DEPARTMENT</label>
            <div id="colorDeptName" style="font-size:14px; font-weight:800; color:var(--text-color, #111);"></div>
        </div>

        <div style="margin-bottom:18px;">
            <label style="font-size:11px; font-weight:700; color:var(--text-muted, #666); display:block; margin-bottom:8px;">
                SELECT ACCESSIBLE PALETTE COLOR (WCAG AA Contrast Compliant)
            </label>
            <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:10px;" id="paletteOptionsGrid">
                <?php foreach (App\Controllers\OrgChartController::APPROVED_PALETTE as $hex => $pInfo): ?>
                <label style="display:flex; flex-direction:column; align-items:center; gap:4px; cursor:pointer; padding:6px; border-radius:8px; border:2px solid transparent;" class="palette-swatch-label" id="palette-label-<?php echo substr($hex, 1); ?>">
                    <input type="radio" name="color" value="<?php echo $hex; ?>" style="display:none;" onchange="previewPaletteSelection('<?php echo $hex; ?>')">
                    <span style="width:34px; height:34px; border-radius:50%; background:<?php echo $hex; ?>; display:flex; align-items:center; justify-content:center; color:#fff; font-size:12px; box-shadow:0 2px 4px rgba(0,0,0,0.15);">
                        <i class="fa-solid fa-check check-icon" style="display:none;"></i>
                    </span>
                    <span style="font-size:9.5px; font-weight:600; color:var(--text-color, #333); text-align:center;"><?php echo htmlspecialchars($pInfo['name']); ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Live Zone Preview -->
        <div style="margin-bottom:20px; padding:12px 14px; border-radius:10px; border:1.5px solid #0F766E; background:#CCFBF1;" id="zonePreviewBox">
            <span style="font-size:10px; font-weight:700; text-transform:uppercase; color:#134E4A;" id="zonePreviewText">
                Zone Preview: Soft Tint Background & Thin Border
            </span>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px;">
            <button type="button" onclick="closeColorPickerModal()" class="btn btn-ghost" style="font-size:12px; height:34px;">Cancel</button>
            <button type="submit" class="btn btn-primary" style="font-size:12px; height:34px; padding:0 18px;">
                <i class="fa-solid fa-check"></i> Apply Color
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<style>
/* Department Filter Chips */
.dept-chip {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 5px 12px;
    border-radius: 99px;
    font-size: 11.5px;
    font-weight: 600;
    color: var(--text-color, #334155);
    background: var(--bg-card, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
}
.dept-chip:hover {
    border-color: var(--teal, #008080);
    background: #f8fafc;
}
.dept-chip.active {
    background: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
    box-shadow: 0 2px 6px rgba(0,0,0,0.12);
}
.chip-swatch {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}

/* Legend items */
.legend-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 600;
    color: var(--text-color, #475569);
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 3px 9px;
    border-radius: 99px;
}
.legend-pill.clickable {
    cursor: pointer;
}
.legend-pill.clickable:hover {
    background: #f1f5f9;
    border-color: var(--teal, #008080);
}
.legend-swatch {
    width: 11px;
    height: 11px;
    border-radius: 3px;
    display: inline-block;
}

/* Packet Tracer Department Zone Styles */
.dept-zone {
    border-radius: 18px;
    padding: 26px 20px 20px 20px;
    margin: 20px;
    position: relative;
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    transition: background-color 0.2s ease, border-color 0.2s ease;
    box-sizing: border-box;
}
.dept-zone-tab {
    position: absolute;
    top: -13px;
    left: 20px;
    color: #ffffff;
    font-weight: 700;
    font-size: 11px;
    padding: 3px 12px;
    border-radius: 6px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.12);
    letter-spacing: 0.3px;
    z-index: 5;
    user-select: none;
}
.dept-zone.no-zones {
    background: transparent !important;
    border-color: transparent !important;
}
.dept-zone.no-zones .dept-zone-tab {
    display: none !important;
}

/* Tree Spine & Connector Lines */
.org-tree {
    display: inline-flex;
    justify-content: center;
    position: relative;
    padding-top: 15px;
    width: max-content;
}
.org-node-wrapper {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    padding: 0 10px;
}
.org-node-card {
    width: 220px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px 14px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.04);
    transition: transform 0.15s ease, border-color 0.15s ease;
    text-align: center;
    position: relative;
    z-index: 2;
    box-sizing: border-box;
}
.org-node-card:hover {
    box-shadow: 0 8px 18px rgba(0,0,0,0.09);
    transform: translateY(-2px);
}
.org-node-card.highlight {
    border: 2px solid #12141A !important;
    background: #fafafa !important;
    box-shadow: 0 0 0 3px rgba(18, 20, 26, 0.2) !important;
}
.org-node-card.ceo-node {
    border: 2px solid #0F172A;
    border-top: 5px solid #0F172A;
    background: #ffffff;
}

.org-children {
    display: inline-flex;
    justify-content: center;
    position: relative;
    padding-top: 24px;
    margin-top: 10px;
    width: max-content;
}
.org-children::before {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    width: 2px;
    height: 24px;
    background: #94a3b8;
    transform: translateX(-50%);
}
.org-node-wrapper::before, .org-node-wrapper::after {
    content: '';
    position: absolute;
    top: -24px;
    width: 50%;
    height: 24px;
    border-top: 2px solid #94a3b8;
}
.org-node-wrapper::before { left: 0; }
.org-node-wrapper::after { right: 0; border-left: 2px solid #94a3b8; }
.org-node-wrapper:first-child::before { border: none; }
.org-node-wrapper:last-child::after { border: none; }
.org-node-wrapper:only-child::before, .org-node-wrapper:only-child::after { border: none; }
.org-node-wrapper:only-child { padding-top: 0; }

/* Inter-Zone Connector Lines (From CEO to Department Zones) */
.interzone-branches {
    display: inline-flex;
    justify-content: center;
    position: relative;
    padding-top: 32px;
    margin-top: 14px;
    width: max-content;
}
.interzone-branches::before {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    width: 3px;
    height: 32px;
    background: #64748b;
    transform: translateX(-50%);
}
.zone-column {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    position: relative;
}
.zone-column::before, .zone-column::after {
    content: '';
    position: absolute;
    top: -32px;
    width: 50%;
    height: 32px;
    border-top: 3px solid #64748b;
}
.zone-column::before { left: 0; }
.zone-column::after { right: 0; border-left: 3px solid #64748b; }
.zone-column:first-child::before { border: none; }
.zone-column:last-child::after { border: none; }
.zone-column:only-child::before, .zone-column:only-child::after { border: none; }

.role-badge {
    font-size: 9.5px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 99px;
    display: inline-block;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.dept-pill {
    font-size: 9.5px;
    color: #475569;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 1px 5px;
    display: inline-block;
    margin-top: 3px;
    max-width: 100%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.reports-badge {
    background: #f1f5f9;
    color: #334155;
    font-size: 9.5px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 99px;
    margin-top: 6px;
    display: inline-block;
    border: 1px solid #e2e8f0;
}

/* Palette swatches selection */
.palette-swatch-label.selected {
    border-color: #0f172a !important;
    background: #f1f5f9;
}
.palette-swatch-label.selected .check-icon {
    display: block !important;
}

@media print {
    #treeControls, .page-header button, #orgSearch, .modal-overlay, .modal-container, .dept-chip, .page-header, .card:not(#treeViewContainer) {
        display: none !important;
    }
    #chartViewport {
        height: auto !important;
        overflow: visible !important;
        background: none !important;
        padding: 0 !important;
    }
    #chartContainer {
        transform: scale(0.7) !important;
        transform-origin: top left !important;
    }
}
</style>

<script>
const allEmployees = <?php echo $chartDataJson; ?>;
const allDepartments = <?php echo $deptsJson; ?>;
const approvedPalette = <?php echo $paletteJson; ?>;
const canEditAll = <?php echo !empty($canEditAll) ? 'true' : 'false'; ?>;
const isDeptHead = <?php echo !empty($isDeptHead) ? 'true' : 'false'; ?>;
const userDeptId = <?php echo (int)($userDeptId ?? 0); ?>;
const canEditColors = <?php echo !empty($canEditColors) ? 'true' : 'false'; ?>;

let currentZoom = 1;
let currentDeptFilter = <?php echo (int)($selectedDeptId ?? 0); ?>;
let showZones = localStorage.getItem('ems_org_show_zones') !== 'false';

// Initialize toggle checkbox from localStorage
document.getElementById('toggleZonesCheck').checked = showZones;

// Zoom controls
function zoomIn() {
    currentZoom = Math.min(1.8, currentZoom + 0.1);
    applyZoom();
}
function zoomOut() {
    currentZoom = Math.max(0.35, currentZoom - 0.1);
    applyZoom();
}
function resetZoom() {
    currentZoom = 1;
    applyZoom();
    centerChartOnRoot();
}
function applyZoom() {
    document.getElementById('chartContainer').style.transform = `scale(${currentZoom})`;
}

function centerChartOnRoot() {
    const vp = document.getElementById('chartViewport');
    const container = document.getElementById('chartContainer');
    if (!vp || !container) return;
    
    const rootNode = document.querySelector('.ceo-node') || document.querySelector('.depthead-node') || document.querySelector('.org-node-card');
    if (rootNode) {
        rootNode.scrollIntoView({ inline: 'center', block: 'start', behavior: 'smooth' });
    } else {
        vp.scrollLeft = (container.scrollWidth - vp.clientWidth) / 2;
    }
}

// Toggle Department Zones
function toggleDepartmentZones(checked) {
    showZones = checked;
    localStorage.setItem('ems_org_show_zones', checked ? 'true' : 'false');
    document.querySelectorAll('.dept-zone').forEach(z => {
        if (checked) {
            z.classList.remove('no-zones');
        } else {
            z.classList.add('no-zones');
        }
    });
}

// Select Department (Filter Switcher & Deep-Linking)
function selectDepartment(deptId, pushHistory = true) {
    currentDeptFilter = parseInt(deptId);
    
    // Update Active Chip
    document.querySelectorAll('.dept-chip').forEach(c => c.classList.remove('active'));
    const activeChip = document.getElementById('chip-dept-' + currentDeptFilter);
    if (activeChip) activeChip.classList.add('active');

    // Update URL without full page reload
    if (pushHistory) {
        let newUrl = '<?php echo baseUrl("orgchart"); ?>';
        if (currentDeptFilter > 0) {
            newUrl = '<?php echo baseUrl("orgchart/department"); ?>/' + currentDeptFilter;
        }
        window.history.pushState({ dept_id: currentDeptFilter }, '', newUrl);
    }

    // Toggle Breadcrumb Banner
    const bcBanner = document.getElementById('deptBreadcrumbBanner');
    if (currentDeptFilter > 0) {
        const deptObj = allDepartments.find(d => d.dept_id === currentDeptFilter);
        const deptEmps = allEmployees.filter(e => e.dept_id === currentDeptFilter);
        
        if (deptObj) {
            document.getElementById('bcDeptName').textContent = deptObj.dept_name;
            document.getElementById('bcDeptName').style.color = deptObj.color;
            document.getElementById('bcMemberBadge').textContent = deptEmps.length + ' Member' + (deptEmps.length !== 1 ? 's' : '');
            bcBanner.style.borderLeftColor = deptObj.color;
        }
        bcBanner.style.display = 'flex';
    } else {
        bcBanner.style.display = 'none';
    }

    // Clear search
    document.getElementById('orgSearch').value = '';
    document.getElementById('crossDeptBanner').style.display = 'none';

    renderChart();
    setTimeout(centerChartOnRoot, 50);
}

// Listen to browser Back/Forward navigation
window.onpopstate = function(event) {
    const deptId = event.state && event.state.dept_id ? event.state.dept_id : 0;
    selectDepartment(deptId, false);
};

// Render Organizational Chart
function renderChart() {
    const container = document.getElementById('chartContainer');
    
    if (currentDeptFilter > 0) {
        // RENDER SINGLE DEPARTMENT VIEW (Feature B)
        renderSingleDepartmentView(container, currentDeptFilter);
    } else {
        // RENDER FULL COMPANY VIEW WITH PACKET TRACER ZONES (Feature A)
        renderCompanyZonesView(container);
    }

    // Apply zone toggle state
    if (!showZones) {
        document.querySelectorAll('.dept-zone').forEach(z => z.classList.add('no-zones'));
    }
}

// Feature A: Full Company View with Packet Tracer Zones
function renderCompanyZonesView(container) {
    const ceo = allEmployees.find(e => e.approval_role === 'CEO') || allEmployees.find(e => !e.supervisor_id);

    // Group employees by department
    const deptsMap = {};
    allDepartments.forEach(d => {
        deptsMap[d.dept_id] = {
            ...d,
            heads: [],
            staff: []
        };
    });

    // Handle Unassigned (dept_id = 0)
    deptsMap[0] = {
        dept_id: 0,
        dept_name: 'Unassigned',
        color: '#475569',
        tint: '#F1F5F9',
        text: '#0F172A',
        heads: [],
        staff: []
    };

    allEmployees.forEach(e => {
        if (e.approval_role === 'CEO') return; // CEO is executive root
        const dId = e.dept_id || 0;
        if (!deptsMap[dId]) {
            deptsMap[dId] = {
                dept_id: dId,
                dept_name: e.dept_name || 'Department ' + dId,
                color: '#0F766E',
                tint: '#CCFBF1',
                text: '#134E4A',
                heads: [],
                staff: []
            };
        }
        if (e.approval_role === 'DeptHead' || e.approval_role === 'Dept Head') {
            deptsMap[dId].heads.push(e);
        } else {
            deptsMap[dId].staff.push(e);
        }
    });

    let html = '';

    // 1. CEO Executive Node
    if (ceo) {
        html += `
            <div class="org-node-wrapper" id="node-wrap-${ceo.emp_id}">
                ${renderNodeCardHtml(ceo, '#0F172A')}
            </div>
        `;
    }

    // 2. Connector line and Branches out to Department Zones
    const activeDeptIds = Object.keys(deptsMap).filter(id => {
        const d = deptsMap[id];
        return (d.heads.length > 0 || d.staff.length > 0) && d.dept_id !== 10; // Exclude Owner dept
    });

    if (activeDeptIds.length > 0) {
        html += '<div class="interzone-branches">';
        activeDeptIds.forEach(dId => {
            const d = deptsMap[dId];
            const memberCount = d.heads.length + d.staff.length;

            html += `
                <div class="zone-column">
                    <div class="dept-zone" id="zone-dept-${d.dept_id}" style="background:${d.tint}; border:1.5px solid ${d.color};">
                        <div class="dept-zone-tab" style="background:${d.color};">
                            <i class="fa-solid fa-building" style="font-size:9.5px;"></i>
                            <span>${escapeHtml(d.dept_name)}</span>
                            <span style="background:rgba(255,255,255,0.25); padding:1px 6px; border-radius:99px; font-size:9.5px;">${memberCount}</span>
                            ${canEditColors && d.dept_id > 0 ? `
                                <button type="button" onclick="openColorPickerModal(${d.dept_id}, '${escapeJs(d.dept_name)}', '${d.color}')" title="Change Color" style="background:none; border:none; color:#fff; cursor:pointer; padding:0 2px; margin-left:3px; font-size:10px;">
                                    <i class="fa-solid fa-palette"></i>
                                </button>
                            ` : ''}
                        </div>
                        ${renderDepartmentZoneSubtree(d)}
                    </div>
                </div>
            `;
        });
        html += '</div>';
    }

    container.innerHTML = html;
}

// Render the internal tree inside a department zone
function renderDepartmentZoneSubtree(dept) {
    if (dept.heads.length === 0 && dept.staff.length === 0) {
        return '<div style="font-size:11px; color:#94a3b8; padding:10px;">Empty Department</div>';
    }

    // If department has heads:
    let html = '';
    if (dept.heads.length > 0) {
        if (dept.heads.length === 1) {
            // Single head
            const head = dept.heads[0];
            const subTree = buildSubtreeForEmployee(head.emp_id, dept.staff);
            head.children = subTree;
            html += `<div class="org-tree">${renderRecursiveNodeHtml(head, dept.color)}</div>`;
        } else {
            // Multiple heads (Co-Heads side-by-side)
            html += '<div class="org-tree">';
            dept.heads.forEach(h => {
                const subTree = buildSubtreeForEmployee(h.emp_id, dept.staff);
                h.children = subTree;
                html += renderRecursiveNodeHtml(h, dept.color);
            });
            html += '</div>';
        }
    } else {
        // No head, direct staff under zone
        html += '<div class="org-tree">';
        dept.staff.forEach(s => {
            html += `
                <div class="org-node-wrapper" id="node-wrap-${s.emp_id}">
                    ${renderNodeCardHtml(s, dept.color)}
                </div>
            `;
        });
        html += '</div>';
    }

    return html;
}

// Feature B: Render Single Department Subtree
function renderSingleDepartmentView(container, deptId) {
    const deptObj = allDepartments.find(d => d.dept_id === deptId);
    if (!deptObj) {
        container.innerHTML = '<div style="padding:60px; color:#888;">Department not found.</div>';
        return;
    }

    const deptEmps = allEmployees.filter(e => e.dept_id === deptId);
    if (deptEmps.length === 0) {
        container.innerHTML = `
            <div style="padding:60px 20px; text-align:center;">
                <div style="width:52px; height:52px; border-radius:50%; background:#f1f5f9; color:#94a3b8; display:flex; align-items:center; justify-content:center; font-size:22px; margin:0 auto 12px auto;">
                    <i class="fa-solid fa-users-slash"></i>
                </div>
                <h3 style="font-size:16px; font-weight:700; color:var(--text-color, #1e293b); margin-bottom:6px;">This department has no members yet</h3>
                <p style="font-size:12px; color:var(--text-muted, #64748b); margin-bottom:18px;">No active personnel currently assigned to ${escapeHtml(deptObj.dept_name)}.</p>
                <button onclick="selectDepartment(0)" class="btn btn-primary" style="font-size:12px;">
                    <i class="fa-solid fa-arrow-left"></i> View All Departments
                </button>
            </div>
        `;
        return;
    }

    // Find root nodes of this department:
    // 1. DeptHead(s)
    let heads = deptEmps.filter(e => e.approval_role === 'DeptHead' || e.approval_role === 'Dept Head');
    let staff = deptEmps.filter(e => e.approval_role !== 'DeptHead' && e.approval_role !== 'Dept Head');

    if (heads.length === 0) {
        // If no DeptHead, root nodes are those whose supervisor is not in this department
        heads = deptEmps.filter(e => !e.supervisor_id || !deptEmps.some(other => other.emp_id === e.supervisor_id));
        staff = deptEmps.filter(e => !heads.includes(e));
    }

    let fullHtml = `
        <div class="dept-zone" id="zone-dept-${deptObj.dept_id}" style="background:${deptObj.tint}; border:2px solid ${deptObj.color}; min-width:400px; padding-top:30px;">
            <div class="dept-zone-tab" style="background:${deptObj.color};">
                <i class="fa-solid fa-building" style="font-size:9.5px;"></i>
                <span>${escapeHtml(deptObj.dept_name)}</span>
                <span style="background:rgba(255,255,255,0.25); padding:1px 6px; border-radius:99px; font-size:9.5px;">${deptEmps.length}</span>
            </div>
            <div class="org-tree">
    `;

    heads.forEach(h => {
        h.children = buildSubtreeForEmployee(h.emp_id, deptEmps);
        fullHtml += renderRecursiveNodeHtml(h, deptObj.color);
    });

    fullHtml += '</div></div>';
    container.innerHTML = fullHtml;
}

// Build recursive subtree for any manager within a candidate list
function buildSubtreeForEmployee(managerId, candidates) {
    const children = candidates.filter(e => e.supervisor_id === managerId && e.emp_id !== managerId);
    children.forEach(c => {
        c.children = buildSubtreeForEmployee(c.emp_id, candidates);
    });
    return children;
}

// Recursive Node Renderer
function renderRecursiveNodeHtml(node, accentColor) {
    let html = `
        <div class="org-node-wrapper" id="node-wrap-${node.emp_id}">
            ${renderNodeCardHtml(node, accentColor)}
    `;

    if (node.children && node.children.length > 0) {
        html += '<div class="org-children">';
        node.children.forEach(child => {
            html += renderRecursiveNodeHtml(child, accentColor);
        });
        html += '</div>';
    }

    html += '</div>';
    return html;
}

// Node Card HTML
function renderNodeCardHtml(node, accentColor) {
    const isCeo = node.approval_role === 'CEO' || (node.job_title && node.job_title.toLowerCase().includes('ceo'));
    const isDeptHead = node.approval_role === 'DeptHead' || node.approval_role === 'Dept Head';
    const isHr = node.dept_id == 5 || node.approval_role === 'HR';

    let cardClass = isCeo ? 'ceo-node' : (isDeptHead ? 'depthead-node' : '');
    let badgeHtml = '';
    if (isCeo) {
        badgeHtml = '<div class="role-badge" style="background:#0F172A; color:#fff;"><i class="fa-solid fa-crown" style="margin-right:2px;"></i> Executive / CEO</div>';
    } else if (isDeptHead) {
        badgeHtml = `<div class="role-badge" style="background:${accentColor}; color:#fff;"><i class="fa-solid fa-user-tie" style="margin-right:2px;"></i> Dept Head</div>`;
    } else if (isHr) {
        badgeHtml = '<div class="role-badge" style="background:#0284c7; color:#fff;"><i class="fa-solid fa-user-shield" style="margin-right:2px;"></i> HR</div>';
    } else {
        badgeHtml = '<div class="role-badge" style="background:#f1f5f9; color:#475569;">Staff</div>';
    }

    const initials = ((node.first_name?.[0] || '') + (node.last_name?.[0] || '')).toUpperCase();
    let avatarHtml = node.profile_picture ? 
        `<img src="<?php echo baseUrl(); ?>/${node.profile_picture}" style="width:38px; height:38px; border-radius:50%; object-fit:cover; margin:0 auto 6px auto; display:block; border:2px solid #fff; box-shadow:0 2px 4px rgba(0,0,0,0.1);">` :
        `<div style="width:38px; height:38px; border-radius:50%; background:${accentColor || 'var(--teal, #008080)'}; color:#fff; display:flex; align-items:center; justify-content:center; margin:0 auto 6px auto; font-weight:700; font-size:12px;">${initials}</div>`;

    // Edit permission check:
    // HR/CEO can edit anyone. DeptHead can edit ONLY their own department.
    let userCanEditThisNode = canEditAll || (isDeptHead && userDeptId === node.dept_id);

    let editBtn = userCanEditThisNode ? 
        `<button type="button" onclick="openReassignModal(${node.emp_id}, '${escapeJs(node.full_name)}', '${escapeJs(node.job_title||'')}', '${escapeJs(node.dept_name||'')}', ${node.supervisor_id || 'null'}, ${node.dept_id})" 
                 title="Reassign Supervisor" 
                 style="position:absolute; top:8px; right:8px; background:none; border:none; color:var(--teal, #008080); cursor:pointer; font-size:11px; padding:2px;">
            <i class="fa-solid fa-user-pen"></i>
        </button>` : '';

    let directReportsCount = node.direct_reports || (node.children ? node.children.length : 0);
    let reportsBadge = directReportsCount > 0 ? 
        `<div class="reports-badge"><i class="fa-solid fa-users" style="font-size:9px; margin-right:3px;"></i>${directReportsCount} direct report${directReportsCount > 1 ? 's' : ''}</div>` : '';

    return `
        <div class="org-node-card ${cardClass}" id="node-${node.emp_id}">
            ${editBtn}
            ${badgeHtml}
            ${avatarHtml}
            <div style="font-size:12.5px; font-weight:700; color:var(--text-color, #0f172a); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${escapeHtml(node.full_name)}">${escapeHtml(node.full_name)}</div>
            <div style="font-size:10.5px; color:${accentColor}; font-weight:600; margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${escapeHtml(node.job_title || 'Staff')}">${escapeHtml(node.job_title || 'Staff')}</div>
            <div class="dept-pill" title="${escapeHtml(node.dept_name || 'General')}"><i class="fa-regular fa-building" style="margin-right:2px; font-size:8.5px;"></i>${escapeHtml(node.dept_name || 'General')}</div>
            ${reportsBadge}
        </div>
    `;
}

// Search & Cross-Department Querying
document.getElementById('orgSearch').addEventListener('input', function(e) {
    const q = e.target.value.toLowerCase().trim();
    document.querySelectorAll('.org-node-card').forEach(c => c.classList.remove('highlight'));
    const crossBanner = document.getElementById('crossDeptBanner');
    crossBanner.style.display = 'none';

    if (q.length < 2) return;

    // Search in current view
    let firstFoundInView = null;
    let foundOutsideDept = null;

    allEmployees.forEach(emp => {
        const matches = (emp.full_name && emp.full_name.toLowerCase().includes(q)) || 
                        (emp.job_title && emp.job_title.toLowerCase().includes(q));
        if (matches) {
            const el = document.getElementById('node-' + emp.emp_id);
            if (el) {
                el.classList.add('highlight');
                if (!firstFoundInView) firstFoundInView = el;
            } else if (currentDeptFilter > 0 && emp.dept_id !== currentDeptFilter && !foundOutsideDept) {
                foundOutsideDept = emp;
            }
        }
    });

    if (firstFoundInView) {
        firstFoundInView.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
    } else if (foundOutsideDept) {
        // Cross-department match notification
        document.getElementById('crossDeptText').innerHTML = `Found <strong>${escapeHtml(foundOutsideDept.full_name)}</strong> in <strong>${escapeHtml(foundOutsideDept.dept_name)}</strong>.`;
        const btn = document.getElementById('crossDeptBtn');
        btn.onclick = function() {
            selectDepartment(foundOutsideDept.dept_id);
            setTimeout(() => {
                const targetNode = document.getElementById('node-' + foundOutsideDept.emp_id);
                if (targetNode) {
                    targetNode.classList.add('highlight');
                    targetNode.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
                }
            }, 100);
        };
        crossBanner.style.display = 'flex';
    }
});

// Reassignment Modal Handling
function openReassignModal(empId, name, title, deptName, currentSupId, deptId) {
    document.getElementById('reassignEmpId').value = empId;
    document.getElementById('reassignEmpName').textContent = name;
    document.getElementById('reassignEmpTitle').textContent = title;
    document.getElementById('reassignEmpDept').textContent = deptName;
    
    const select = document.getElementById('reassignSupervisorSelect');
    select.value = currentSupId ? String(currentSupId) : '';

    // Disable self and filter options if user is a Department Head
    Array.from(select.options).forEach(opt => {
        const optEmpId = opt.value;
        const optDept = parseInt(opt.getAttribute('data-dept') || '0');
        opt.disabled = (optEmpId === String(empId));

        if (!canEditAll && isDeptHead) {
            // Dept heads can only assign supervisors within their own department or CEO
            if (optEmpId !== '' && optDept !== userDeptId) {
                opt.style.display = 'none';
            } else {
                opt.style.display = '';
            }
        } else {
            opt.style.display = '';
        }
    });

    document.getElementById('reassignModal').style.display = 'flex';
    document.getElementById('reassignModalBackdrop').style.display = 'block';
}

function closeReassignModal() {
    document.getElementById('reassignModal').style.display = 'none';
    document.getElementById('reassignModalBackdrop').style.display = 'none';
}

<?php if (!empty($canEditAll) || !empty($isDeptHead)): ?>
document.getElementById('reassignForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const empId = parseInt(document.getElementById('reassignEmpId').value);
    const supId = document.getElementById('reassignSupervisorSelect').value;

    $.post('<?php echo baseUrl("orgchart"); ?>', $(this).serialize(), function(res) {
        if (res && res.success) {
            showToast('Reporting manager updated successfully!', 'success');
            closeReassignModal();
            
            // Update local memory and re-render
            const emp = allEmployees.find(x => x.emp_id == empId);
            if (emp) emp.supervisor_id = supId ? parseInt(supId) : null;
            renderChart();
        } else {
            showToast(res.message || 'Failed to reassign supervisor', 'error');
        }
    }).fail(function(xhr) {
        const err = xhr.responseJSON ? xhr.responseJSON.message : 'Network error';
        showToast(err, 'error');
    });
});
<?php endif; ?>

// Department Color Picker Modal Handling (HR / CEO Only)
<?php if (!empty($canEditColors)): ?>
function openCurrentDeptColorModal() {
    if (currentDeptFilter > 0) {
        const d = allDepartments.find(x => x.dept_id === currentDeptFilter);
        if (d) openColorPickerModal(d.dept_id, d.dept_name, d.color);
    }
}

function openColorPickerModal(deptId, deptName, currentColor) {
    document.getElementById('colorDeptId').value = deptId;
    document.getElementById('colorDeptName').textContent = deptName;

    // Pre-select current color radio
    const cleanHex = currentColor.toUpperCase();
    previewPaletteSelection(cleanHex);

    document.getElementById('colorModal').style.display = 'flex';
    document.getElementById('colorModalBackdrop').style.display = 'block';
}

function closeColorPickerModal() {
    document.getElementById('colorModal').style.display = 'none';
    document.getElementById('colorModalBackdrop').style.display = 'none';
}

function previewPaletteSelection(hex) {
    document.querySelectorAll('.palette-swatch-label').forEach(lbl => lbl.classList.remove('selected'));
    const targetLabel = document.getElementById('palette-label-' + hex.replace('#', ''));
    if (targetLabel) {
        targetLabel.classList.add('selected');
        const radio = targetLabel.querySelector('input[type="radio"]');
        if (radio) radio.checked = true;
    }

    const p = approvedPalette[hex] || approvedPalette['#0F766E'];
    const previewBox = document.getElementById('zonePreviewBox');
    const previewText = document.getElementById('zonePreviewText');
    if (previewBox && p) {
        previewBox.style.backgroundColor = p.tint;
        previewBox.style.borderColor = hex;
        previewText.style.color = p.text;
    }
}

document.getElementById('deptColorForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const deptId = parseInt(document.getElementById('colorDeptId').value);
    const selectedRadio = document.querySelector('input[name="color"]:checked');
    if (!selectedRadio) return;
    const newColor = selectedRadio.value;

    $.post('<?php echo baseUrl("orgchart/color"); ?>', $(this).serialize(), function(res) {
        if (res && res.success) {
            showToast(res.message || 'Department color updated!', 'success');
            closeColorPickerModal();

            // Update in-memory models
            const deptObj = allDepartments.find(d => d.dept_id === deptId);
            if (deptObj) {
                deptObj.color = res.color;
                deptObj.tint = res.tint;
                deptObj.text = res.text;
            }

            allEmployees.forEach(emp => {
                if (emp.dept_id === deptId) {
                    emp.dept_color = res.color;
                }
            });

            // Update swatches in DOM
            const swatch = document.getElementById('legend-swatch-' + deptId);
            if (swatch) swatch.style.background = res.color;
            const chipSwatch = document.querySelector('#chip-dept-' + deptId + ' .chip-swatch');
            if (chipSwatch) chipSwatch.style.background = res.color;

            // Re-render chart immediately without reload
            renderChart();
        } else {
            showToast(res.message || 'Failed to update department color', 'error');
        }
    }).fail(function(xhr) {
        const err = xhr.responseJSON ? xhr.responseJSON.message : 'Error updating color';
        showToast(err, 'error');
    });
});
<?php endif; ?>

// Export as PNG
function exportChartAsPng() {
    showToast('Preparing chart for printing / image export...', 'info');
    window.print();
}

// Drag-to-pan in viewport
(function() {
    const vp = document.getElementById('chartViewport');
    let isDown = false;
    let startX, startY, scrollLeft, scrollTop;

    vp.addEventListener('mousedown', (e) => {
        if (e.target.closest('.org-node-card') || e.target.closest('button') || e.target.closest('input')) return;
        isDown = true;
        vp.style.cursor = 'grabbing';
        startX = e.pageX - vp.offsetLeft;
        startY = e.pageY - vp.offsetTop;
        scrollLeft = vp.scrollLeft;
        scrollTop = vp.scrollTop;
    });

    vp.addEventListener('mouseleave', () => { isDown = false; vp.style.cursor = 'grab'; });
    vp.addEventListener('mouseup', () => { isDown = false; vp.style.cursor = 'grab'; });

    vp.addEventListener('mousemove', (e) => {
        if (!isDown) return;
        e.preventDefault();
        const x = e.pageX - vp.offsetLeft;
        const y = e.pageY - vp.offsetTop;
        const walkX = (x - startX) * 1.5;
        const walkY = (y - startY) * 1.5;
        vp.scrollLeft = scrollLeft - walkX;
        vp.scrollTop = scrollTop - walkY;
    });
})();

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function escapeJs(str) {
    if (!str) return '';
    return String(str).replace(/'/g, "\\'").replace(/"/g, '\\"');
}

// Initial Page Load
$(document).ready(function() {
    selectDepartment(currentDeptFilter, false);
});
</script>

<?php require BASE_PATH . '/partials/layout_footer.php'; ?>
