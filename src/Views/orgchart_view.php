<?php
$pageTitle = 'Organization Chart — Azzurro HR';
require BASE_PATH . '/partials/layout_head.php';
require BASE_PATH . '/partials/layout_topbar.php';
require BASE_PATH . '/partials/layout_sidebar.php';
?>

<div class="page-header" style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:20px;">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-sitemap"></i> Organization Chart</h1>
        <p class="page-sub">Workforce reporting hierarchy and departmental structure</p>
    </div>
    <div style="display:flex; gap:10px; align-items:center;">
        <div style="position:relative; width:240px;">
            <i class="fa-solid fa-search" style="position:absolute; left:10px; top:11px; font-size:11px; color:var(--ink-4);"></i>
            <input type="text" id="orgSearch" placeholder="Search employee or role..." 
                   class="input-field" style="padding-left:30px; height:36px; font-size:12px; width:100%;">
        </div>
        <button onclick="resetZoom()" class="btn btn-ghost" title="Reset Zoom" style="height:36px; padding:0 12px; border:1px solid var(--border-color, #ccc);">
            <i class="fa-solid fa-arrows-rotate"></i> Reset
        </button>
        <button onclick="zoomIn()" class="btn btn-ghost" title="Zoom In" style="height:36px; width:36px; padding:0; border:1px solid var(--border-color, #ccc);">
            <i class="fa-solid fa-magnifying-glass-plus"></i>
        </button>
        <button onclick="zoomOut()" class="btn btn-ghost" title="Zoom Out" style="height:36px; width:36px; padding:0; border:1px solid var(--border-color, #ccc);">
            <i class="fa-solid fa-magnifying-glass-minus"></i>
        </button>
        <button onclick="window.print()" class="btn btn-primary" style="height:36px; padding:0 16px; font-size:12px;">
            <i class="fa-solid fa-print"></i> Print Chart
        </button>
    </div>
</div>

<div class="card" style="padding:0; overflow:hidden; position:relative; min-height:650px; background:var(--bg-card);">
    <div id="chartViewport" style="overflow:auto; width:100%; height:700px; padding:40px; cursor:grab; user-select:none;">
        <div id="chartContainer" style="transform-origin: 50% 0%; transition: transform 0.15s ease; min-width:800px; display:flex; flex-direction:column; align-items:center;">
            <!-- Tree nodes dynamically rendered here -->
        </div>
    </div>
</div>

<!-- CHANGE SUPERVISOR MODAL (HR / CEO ONLY) -->
<?php if ($canEdit): ?>
<div id="reassignModalBackdrop" class="modal-overlay" style="position:fixed; inset:0; background:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:1000; display:none;" onclick="closeReassignModal()"></div>
<div id="reassignModal" class="modal-container" style="position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:460px; background:var(--bg-card, #fff); border-radius:16px; border:1px solid var(--border-color, #ccc); box-shadow:0 10px 30px rgba(0,0,0,0.2); z-index:1001; display:none; flex-direction:column;">
    <div style="padding:16px 20px; border-bottom:1px solid var(--border-color, #eee); display:flex; justify-content:space-between; align-items:center;">
        <h3 style="font-size:14px; font-weight:700; color:var(--text-color, #111);"><i class="fa-solid fa-user-pen" style="color:var(--teal, #008080); margin-right:8px;"></i>Reassign Reporting Manager</h3>
        <button onclick="closeReassignModal()" style="background:none; border:none; cursor:pointer; font-size:16px; color:#888;">&times;</button>
    </div>
    <form id="reassignForm" style="padding:20px;">
        <input type="hidden" id="reassignEmpId" name="emp_id">
        <div style="margin-bottom:14px;">
            <label style="font-size:11px; font-weight:700; color:var(--text-muted, #666); display:block; margin-bottom:4px;">EMPLOYEE</label>
            <div id="reassignEmpName" style="font-size:14px; font-weight:700; color:var(--text-color, #111);"></div>
            <div id="reassignEmpTitle" style="font-size:11.5px; color:var(--teal, #008080);"></div>
        </div>

        <div style="margin-bottom:20px;">
            <label style="font-size:11px; font-weight:700; color:var(--text-muted, #666); display:block; margin-bottom:6px;">NEW REPORTING SUPERVISOR</label>
            <select id="reassignSupervisorSelect" name="supervisor_id" class="input-field" style="width:100%; height:38px; font-size:13px;">
                <option value="">-- No Direct Supervisor (Reports to Board / Top Level) --</option>
                <?php foreach ($employees as $mgr): ?>
                    <option value="<?php echo $mgr['emp_id']; ?>">
                        <?php echo htmlspecialchars($mgr['full_name'] . ' (' . ($mgr['job_title'] ?? $mgr['dept_name']) . ')'); ?>
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

<style>
/* Org Chart Tree CSS */
.org-tree {
    display: flex;
    justify-content: center;
    position: relative;
    padding-top: 20px;
}
.org-node-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    padding: 0 10px;
}
.org-node-card {
    width: 210px;
    background: var(--bg-card, #ffffff);
    border: 1px solid var(--border-color, #e2e8f0);
    border-radius: 12px;
    padding: 12px 14px;
    box-shadow: 0 4px 10px rgba(0,0,0,0.04);
    transition: all 0.2s ease;
    text-align: center;
    position: relative;
    z-index: 2;
}
.org-node-card:hover {
    box-shadow: 0 8px 20px rgba(0,0,0,0.1);
    border-color: var(--teal, #008080);
    transform: translateY(-2px);
}
.org-node-card.highlight {
    border: 2px solid #007bff !important;
    background: rgba(0, 123, 255, 0.05);
}
.org-node-card.ceo-node {
    border-top: 4px solid var(--teal, #008080);
    background: linear-gradient(180deg, rgba(0, 128, 128, 0.03) 0%, transparent 100%);
}
.org-node-card.depthead-node {
    border-top: 4px solid #8b5cf6;
}
.org-children {
    display: flex;
    justify-content: center;
    position: relative;
    padding-top: 24px;
    margin-top: 10px;
}
.org-children::before {
    content: '';
    position: absolute;
    top: 0;
    left: 50%;
    width: 2px;
    height: 24px;
    background: #cbd5e1;
    transform: translateX(-50%);
}
.org-node-wrapper::before, .org-node-wrapper::after {
    content: '';
    position: absolute;
    top: -24px;
    width: 50%;
    height: 24px;
    border-top: 2px solid #cbd5e1;
}
.org-node-wrapper::before { left: 0; }
.org-node-wrapper::after { right: 0; border-left: 2px solid #cbd5e1; }
.org-node-wrapper:first-child::before { border: none; }
.org-node-wrapper:last-child::after { border: none; }
.org-node-wrapper:only-child::before, .org-node-wrapper:only-child::after { border: none; }
.org-node-wrapper:only-child { padding-top: 0; }

.reports-badge {
    background: #f1f5f9;
    color: #475569;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 99px;
    margin-top: 6px;
    display: inline-block;
}
</style>

<script>
const allEmployees = <?php echo $chartDataJson; ?>;
const canEditOrg = <?php echo $canEdit ? 'true' : 'false'; ?>;
let currentZoom = 1;

function zoomIn() {
    currentZoom = Math.min(1.6, currentZoom + 0.1);
    applyZoom();
}
function zoomOut() {
    currentZoom = Math.max(0.4, currentZoom - 0.1);
    applyZoom();
}
function resetZoom() {
    currentZoom = 1;
    applyZoom();
}
function applyZoom() {
    document.getElementById('chartContainer').style.transform = `scale(${currentZoom})`;
}

// Build hierarchy
function buildTree() {
    const empMap = {};
    const roots = [];

    allEmployees.forEach(e => {
        empMap[e.emp_id] = { ...e, children: [] };
    });

    allEmployees.forEach(e => {
        if (e.supervisor_id && empMap[e.supervisor_id]) {
            empMap[e.supervisor_id].children.push(empMap[e.emp_id]);
        } else {
            roots.push(empMap[e.emp_id]);
        }
    });

    return { roots, empMap };
}

function renderNodeHtml(node) {
    const isCeo = node.approval_role === 'CEO' || (node.job_title && node.job_title.toLowerCase().includes('ceo'));
    const isDeptHead = node.approval_role === 'DeptHead' || node.approval_role === 'Dept Head';
    const cardClass = isCeo ? 'ceo-node' : (isDeptHead ? 'depthead-node' : '');

    let avatarHtml = node.profile_picture ? 
        `<img src="<?php echo baseUrl(); ?>/${node.profile_picture}" style="width:38px; height:38px; border-radius:50%; object-fit:cover; margin:0 auto 8px auto; display:block;">` :
        `<div style="width:38px; height:38px; border-radius:50%; background:var(--teal, #008080); color:#fff; display:flex; align-items:center; justify-content:center; margin:0 auto 8px auto; font-weight:700; font-size:13px;">${(node.first_name?.[0]||'')}${(node.last_name?.[0]||'')}</div>`;

    let editBtn = canEditOrg ? 
        `<button type="button" onclick="openReassignModal(${node.emp_id}, '${escapeJs(node.full_name)}', '${escapeJs(node.job_title||'')}', ${node.supervisor_id || 'null'})" 
                 title="Reassign Supervisor" 
                 style="position:absolute; top:8px; right:8px; background:none; border:none; color:var(--teal, #008080); cursor:pointer; font-size:11px; padding:2px;">
            <i class="fa-solid fa-user-pen"></i>
        </button>` : '';

    let reportsBadge = node.children.length > 0 ? 
        `<div class="reports-badge"><i class="fa-solid fa-users" style="font-size:9px; margin-right:3px;"></i>${node.children.length} direct reports</div>` : '';

    let html = `
        <div class="org-node-wrapper" id="node-wrap-${node.emp_id}">
            <div class="org-node-card ${cardClass}" id="node-${node.emp_id}">
                ${editBtn}
                ${avatarHtml}
                <div style="font-size:12.5px; font-weight:700; color:var(--text-color, #111); overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHtml(node.full_name)}</div>
                <div style="font-size:10.5px; color:var(--teal, #008080); font-weight:600; margin-top:2px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${escapeHtml(node.job_title || 'Employee')}</div>
                <div style="font-size:9.5px; color:var(--text-muted, #777); margin-top:2px;">${escapeHtml(node.dept_name || 'Staff')}</div>
                ${reportsBadge}
            </div>
    `;

    if (node.children && node.children.length > 0) {
        html += '<div class="org-children">';
        node.children.forEach(child => {
            html += renderNodeHtml(child);
        });
        html += '</div>';
    }

    html += '</div>';
    return html;
}

function renderChart() {
    const { roots } = buildTree();
    const container = document.getElementById('chartContainer');
    
    if (roots.length === 0) {
        container.innerHTML = '<div style="padding:60px; color:#888;">No employees in organization chart.</div>';
        return;
    }

    let fullHtml = '<div class="org-tree">';
    roots.forEach(root => {
        fullHtml += renderNodeHtml(root);
    });
    fullHtml += '</div>';

    container.innerHTML = fullHtml;
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function escapeJs(str) {
    if (!str) return '';
    return String(str).replace(/'/g, "\\'").replace(/"/g, '\\"');
}

// Search & Highlight
document.getElementById('orgSearch').addEventListener('input', function(e) {
    const q = e.target.value.toLowerCase().trim();
    document.querySelectorAll('.org-node-card').forEach(c => c.classList.remove('highlight'));

    if (q.length < 2) return;

    let firstFound = null;
    allEmployees.forEach(emp => {
        if ((emp.full_name && emp.full_name.toLowerCase().includes(q)) || 
            (emp.job_title && emp.job_title.toLowerCase().includes(q)) ||
            (emp.dept_name && emp.dept_name.toLowerCase().includes(q))) {
            const el = document.getElementById('node-' + emp.emp_id);
            if (el) {
                el.classList.add('highlight');
                if (!firstFound) firstFound = el;
            }
        }
    });

    if (firstFound) {
        firstFound.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
    }
});

// Reassignment Modal
function openReassignModal(empId, name, title, currentSupId) {
    document.getElementById('reassignEmpId').value = empId;
    document.getElementById('reassignEmpName').textContent = name;
    document.getElementById('reassignEmpTitle').textContent = title;
    
    const select = document.getElementById('reassignSupervisorSelect');
    select.value = currentSupId ? String(currentSupId) : '';

    // Disable selecting self
    Array.from(select.options).forEach(opt => {
        opt.disabled = (opt.value === String(empId));
    });

    document.getElementById('reassignModal').style.display = 'flex';
    document.getElementById('reassignModalBackdrop').style.display = 'block';
}

function closeReassignModal() {
    document.getElementById('reassignModal').style.display = 'none';
    document.getElementById('reassignModalBackdrop').style.display = 'none';
}

<?php if ($canEdit): ?>
document.getElementById('reassignForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const empId = document.getElementById('reassignEmpId').value;
    const supId = document.getElementById('reassignSupervisorSelect').value;

    $.post('<?php echo baseUrl("orgchart"); ?>', {
        emp_id: empId,
        supervisor_id: supId
    }, function(res) {
        if (res && res.success) {
            showToast('Reporting manager updated successfully!', 'success');
            closeReassignModal();
            // Update local state and re-render
            const emp = allEmployees.find(x => x.emp_id == empId);
            if (emp) emp.supervisor_id = supId ? parseInt(supId) : null;
            renderChart();
        } else {
            showToast(res.message || 'Failed to reassign supervisor', 'error');
        }
    }).fail(function() {
        showToast('Network error while saving reporting line', 'error');
    });
});
<?php endif; ?>

// Drag-to-pan in viewport
(function() {
    const vp = document.getElementById('chartViewport');
    let isDown = false;
    let startX, startY, scrollLeft, scrollTop;

    vp.addEventListener('mousedown', (e) => {
        if (e.target.closest('.org-node-card') || e.target.closest('button')) return;
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

// Initial Render
$(document).ready(function() {
    renderChart();
});
</script>

<?php require BASE_PATH . '/partials/layout_footer.php'; ?>
