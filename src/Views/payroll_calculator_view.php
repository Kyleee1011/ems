<?php
$pageTitle = 'Payroll Computation Worksheet — Azzurro HR';
require BASE_PATH . '/partials/layout_head.php';
require BASE_PATH . '/partials/layout_topbar.php';
require BASE_PATH . '/partials/layout_sidebar.php';
?>

<style>
    .calc-row:hover { background-color: var(--bg-hover) !important; }
    .formula-cell { font-family: 'DM Mono', monospace; font-size: 10px; color: var(--ink-4); }
    .value-cell { font-family: 'DM Mono', monospace; font-weight: 700; }
    .section-header { background: var(--bg-raised); color: var(--ink-1); border-bottom: 1px solid var(--border); }
    .subtotal-row { background-color: var(--teal-bg) !important; font-weight: 800; }
    .grand-total { background: var(--ink-1); color: #fff; }
    
    @media print {
        .no-print { display: none !important; }
        body { background: white; }
        .main { padding: 0; animation: none; }
        .sidebar, .topbar { display: none !important; }
        .app { display: block; }
    }
</style>

<div class="page-header no-print">
    <div>
        <h1 class="page-title"><i class="fa-solid fa-calculator"></i> Payroll Simulator</h1>
        <p class="page-sub">Interactive calculation worksheet for manual verification</p>
    </div>
    <div class="header-actions">
        <button onclick="window.print()" class="pill-btn"><i class="fa-solid fa-print"></i> Print Sheet</button>
    </div>
</div>

<div class="grid-2" style="grid-template-columns: 320px 1fr; gap: 25px; align-items: start;">
    <!-- INPUT PANEL -->
    <div class="card no-print">
        <div class="card-head"><div class="card-title">Simulation Inputs</div></div>
        <div class="card-body">
            <div class="form-group"><label class="form-label">Monthly Base Salary</label><input type="number" id="inputSalary" value="35000" class="input-field" oninput="recalculate()"></div>
            <div class="form-group">
                <label class="form-label">Pay Class</label>
                <select id="inputPayType" class="input-field" onchange="recalculate()"><option value="monthly">Monthly Paid</option><option value="daily">Daily Paid</option></select>
            </div>
            <div class="form-group">
                <label class="form-label">Annual Divisor</label>
                <select id="inputDivisor" class="input-field" onchange="recalculate()"><option value="313">313 (6 days/week)</option><option value="261">261 (5 days/week)</option></select>
            </div>
            
            <div class="section-hd"><span class="section-hd-label">Cutoff Specifics</span><div class="section-hd-line"></div></div>
            <div class="form-group">
                <label class="form-label">Period Type</label>
                <select id="inputCutoffType" class="input-field" onchange="recalculate()"><option value="sss">1st (SSS/Tax)</option><option value="philhealth">2nd (PH/PI/Tax)</option></select>
            </div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Absents (Days)</label><input type="number" id="inputAbsences" value="0" class="input-field" oninput="recalculate()"></div>
                <div class="form-group"><label class="form-label">Present (Days)</label><input type="number" id="inputDaysPresent" value="13" class="input-field" oninput="recalculate()"></div>
            </div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">Late (Mins)</label><input type="number" id="inputLateMins" value="0" class="input-field" oninput="recalculate()"></div>
                <div class="form-group"><label class="form-label">UT (Mins)</label><input type="number" id="inputUtMins" value="0" class="input-field" oninput="recalculate()"></div>
            </div>
            
            <div class="section-hd"><span class="section-hd-label">Premiums</span><div class="section-hd-line"></div></div>
            <div class="grid-2">
                <div class="form-group"><label class="form-label">OT (Hrs)</label><input type="number" id="inputOtHrs" value="0" step="0.5" class="input-field" oninput="recalculate()"></div>
                <div class="form-group"><label class="form-label">ND (Hrs)</label><input type="number" id="inputNdHrs" value="0" step="0.5" class="input-field" oninput="recalculate()"></div>
            </div>
            <div class="form-group"><label class="form-label">Total Allowances</label><input type="number" id="inputAllowances" value="0" class="input-field" oninput="recalculate()"></div>
        </div>
    </div>

    <!-- WORKSHEET -->
    <div class="card">
        <div class="card-head"><div class="card-title">Computation Worksheet (Step-by-Step)</div></div>
        <div class="card-body" style="padding: 0;">
            <div class="table-wrap" style="border:none;">
                <table>
                    <thead>
                        <tr><th style="width:40px;">#</th><th>Description</th><th>Logic / Formula</th><th style="text-align:right; width:150px;">Amount</th></tr>
                    </thead>
                    <tbody id="calcBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const SSS_TABLE = <?php echo json_encode($sssTable); ?>;
const PH_CONFIG = <?php echo json_encode($philhealthTable); ?>;
const PAGIBIG_AMT = 200;
const TAX_TABLE = <?php echo json_encode($taxTable); ?>;
const SETTINGS = <?php echo json_encode($settings); ?>;

const ND_RATE = 0.10;
const OT_MULTIPLIER = parseFloat(SETTINGS['ot_regular_rate'] || '1.25');
const REG_HOLIDAY_MULTIPLIER = 2.00;
const SPC_HOLIDAY_MULTIPLIER = 1.30;

function lookupSSS(sal) {
    for (let i = 0; i < SSS_TABLE.length; i++) {
        if (sal >= parseFloat(SSS_TABLE[i].min_salary) && sal <= parseFloat(SSS_TABLE[i].max_salary)) return parseFloat(SSS_TABLE[i].ee_share);
    }
    return parseFloat(SSS_TABLE[SSS_TABLE.length - 1].ee_share);
}

function lookupPhilHealth(sal) {
    const r = parseFloat(PH_CONFIG.rate || 0.05);
    const min = parseFloat(PH_CONFIG.min_salary || 10000);
    const max = parseFloat(PH_CONFIG.max_salary || 100000);
    return (Math.max(min, Math.min(max, sal)) * r) / 2;
}

function lookupTax(taxable) {
    if (taxable <= 0) return 0;
    for (let i = 0; i < TAX_TABLE.length; i++) {
        const min = parseFloat(TAX_TABLE[i].min_salary);
        const max = parseFloat(TAX_TABLE[i].max_salary);
        if (taxable >= min && taxable <= max) return parseFloat(TAX_TABLE[i].base_tax) + ((taxable - min) * parseFloat(TAX_TABLE[i].excess_rate));
    }
    return 0;
}

function fmt(v) { return '₱ ' + Math.abs(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

function recalculate() {
    const salary = parseFloat($('#inputSalary').val()) || 0;
    const divisor = parseInt($('#inputDivisor').val()) || 313;
    const payType = $('#inputPayType').val();
    const cutoffType = $('#inputCutoffType').val();
    const absences = parseFloat($('#inputAbsences').val()) || 0;
    const daysPresent = parseFloat($('#inputDaysPresent').val()) || 0;
    const lateMins = parseFloat($('#inputLateMins').val()) || 0;
    const utMins = parseFloat($('#inputUtMins').val()) || 0;
    const otHrs = parseFloat($('#inputOtHrs').val()) || 0;
    const ndHrs = parseFloat($('#inputNdHrs').val()) || 0;
    const allowances = parseFloat($('#inputAllowances').val()) || 0;

    const daysPerMonth = divisor / 12;
    const dailyRate = salary / daysPerMonth;
    const hourlyRate = dailyRate / 8;
    const minuteRate = hourlyRate / 60;

    let basePay = (payType === 'monthly') ? salary / 2 : daysPresent * dailyRate;
    const absenceDeduction = (payType === 'monthly') ? absences * dailyRate : 0;
    const lateDed = lateMins * minuteRate;
    const utDed = utMins * minuteRate;
    const otPay = otHrs * hourlyRate * OT_MULTIPLIER;
    const ndPay = ndHrs * hourlyRate * ND_RATE;
    
    const grossPay = basePay - absenceDeduction + otPay + ndPay + allowances;

    const fullSSS = lookupSSS(salary);
    const fullPH = lookupPhilHealth(salary);
    let gSSS = (cutoffType === 'sss') ? fullSSS : 0;
    let gPH = (cutoffType === 'philhealth') ? fullPH : 0;
    let gPI = (cutoffType === 'philhealth') ? PAGIBIG_AMT : 0;
    const totalGov = gSSS + gPH + gPI;

    const taxable = Math.max(0, grossPay - totalGov);
    const semiMonthlyTax = lookupTax(taxable * 2) / 2;
    const netPay = grossPay - (totalGov + semiMonthlyTax + lateDed + utDed);

    const rows = []; let s = 0;
    rows.push({ section: 'RATE CALCULATION' });
    rows.push({ n: ++s, d: 'Daily Rate', f: `Monthly / ${daysPerMonth.toFixed(2)}`, v: dailyRate });
    rows.push({ n: ++s, d: 'Hourly Rate', f: 'Daily / 8', v: hourlyRate });

    rows.push({ section: 'EARNINGS' });
    rows.push({ n: ++s, d: 'Base Salary', f: payType === 'monthly' ? 'Monthly / 2' : 'Days × Daily', v: basePay });
    if(absenceDeduction > 0) rows.push({ n: ++s, d: 'Absences', f: `${absences}d × Daily`, v: -absenceDeduction, cls: 'tag-red' });
    if(otPay > 0) rows.push({ n: ++s, d: 'Overtime', f: `${otHrs}h × Hr × ${OT_MULTIPLIER}`, v: otPay });
    if(allowances > 0) rows.push({ n: ++s, d: 'Allowances', f: 'Input', v: allowances });
    rows.push({ subtotal: true, d: 'GROSS PAY', v: grossPay });

    rows.push({ section: 'STATUTORY DEDUCTIONS' });
    if(gSSS > 0) rows.push({ n: ++s, d: 'SSS EE Share', f: 'Table Lookup', v: -gSSS });
    if(gPH > 0) rows.push({ n: ++s, d: 'PhilHealth EE', f: 'Salary × 5% / 2', v: -gPH });
    if(gPI > 0) rows.push({ n: ++s, d: 'Pag-IBIG EE', f: 'Fixed', v: -gPI });
    rows.push({ n: ++s, d: 'Withholding Tax', f: 'BIR Table (Semi-Mo)', v: -semiMonthlyTax });

    rows.push({ section: 'ATTENDANCE PENALTIES' });
    if(lateDed > 0) rows.push({ n: ++s, d: 'Lateness', f: `${lateMins}m × MinRate`, v: -lateDed });
    if(utDed > 0) rows.push({ n: ++s, d: 'Undertime', f: `${utMins}m × MinRate`, v: -utDed });

    rows.push({ grand: true, d: 'NET TAKE HOME PAY', v: netPay });

    let html = '';
    rows.forEach(r => {
        if(r.section) html += `<tr class="section-header"><td colspan="4" style="font-size:9px; font-weight:800; padding:8px 15px; letter-spacing:1px; opacity:0.7;">${r.section}</td></tr>`;
        else if(r.subtotal) html += `<tr class="subtotal-row"><td></td><td colspan="2" style="font-weight:800;">${r.d}</td><td style="text-align:right; font-family:'DM Mono'; font-weight:800; font-size:14px;">${fmt(r.v)}</td></tr>`;
        else if(r.grand) html += `<tr class="grand-total"><td></td><td colspan="2" style="font-weight:900; font-size:16px; padding:15px;">${r.d}</td><td style="text-align:right; font-family:'DM Mono'; font-weight:900; font-size:20px; padding:15px;">${fmt(r.v)}</td></tr>`;
        else html += `<tr class="calc-row"><td>${r.n}</td><td style="font-weight:600;">${r.d}</td><td class="formula-cell">${r.f}</td><td style="text-align:right;" class="value-cell ${r.v < 0 ? 'tag-red' : ''}">${r.v < 0 ? '-' : ''}${fmt(Math.abs(r.v))}</td></tr>`;
    });
    $('#calcBody').html(html);
}

$(document).ready(recalculate);
</script>

<?php require BASE_PATH . '/partials/layout_footer.php'; ?>
