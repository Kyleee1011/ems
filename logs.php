<?php
// --- CONFIGURATION ---
$db_host = '192.168.21.55';
$db_name = 'biometric_logs';
$db_user = 'dev';
$db_pass = 'Azzurro2025';

// --- DATABASE CONNECTION ---
try {
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die('<div style="color:red; padding:20px;">Database Connection Failed: ' . htmlspecialchars($e->getMessage()) . '</div>');
}

// --- HANDLE SEARCH & FILTERS ---
$where = ["1=1"];
$params = [];

// Default date range: This Week (Monday to Sunday)
$monday = date('Y-m-d', strtotime('monday this week'));
$sunday = date('Y-m-d', strtotime('sunday this week'));
$fromDate = $_GET['dateFrom'] ?? $monday;
$toDate = $_GET['dateTo'] ?? $sunday;
$searchTerm = $_GET['search'] ?? '';

// Date Filter (targeting checkinout table)
if ($fromDate && $toDate) {
    $where[] = "DATE(checkinout.CHECKTIME) BETWEEN ? AND ?";
    $params[] = $fromDate;
    $params[] = $toDate;
}

// Text Search (targeting userinfo table)
if ($searchTerm) {
    // Allows searching by Name, AC Number (Badge), ID, or a combination like "Name 123"
    $where[] = "(CONCAT(userinfo.NAME, ' ', userinfo.BADGENUMBER) LIKE ? OR userinfo.NAME LIKE ? OR userinfo.BADGENUMBER LIKE ? OR userinfo.USERID LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}

// --- FETCH DATA ---
$whereSQL = implode(' AND ', $where);

// 1. Fetch Main Table Data (FIXED: JOINING checkinout AND userinfo)
// We map the raw DB columns to the names the HTML below expects (TYPE, VERIFICATION, Device_SN)
$sql = "SELECT 
            checkinout.CHECKTIME,
            checkinout.VERIFYCODE AS VERIFICATION,
            checkinout.sn AS Device_SN,
            userinfo.NAME,
            userinfo.BADGENUMBER,
            /* Map common biometric CHECKTYPE codes (0/I/O) to Readable Text */
            CASE 
                WHEN checkinout.CHECKTYPE IN ('I', '0', 'in', 'In') THEN 'CHECK-IN'
                ELSE 'CHECK-OUT'
            END AS TYPE
        FROM checkinout 
        LEFT JOIN userinfo ON checkinout.USERID = userinfo.USERID 
        WHERE $whereSQL 
        ORDER BY checkinout.CHECKTIME DESC 
        LIMIT 1000";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// 2. Calculate Weekly Stats (For Dashboard Cards)
$total_logs = count($logs);
$unique_employees = count(array_unique(array_column($logs, 'BADGENUMBER')));

// --- HANDLE EXPORT ---
if (isset($_GET['export']) && $_GET['export'] === 'txt') {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename=InOutData' . date('mdY') . '.txt');
    $output = fopen('php://output', 'w');
    
    // Output headers
    $headerLine = sprintf("%-31s%-15s%-21s%-20s%-17s%-12s%-21s%-19s%-21s",
        "Department", "Name", "No.", "Date/Time", "Status", "Location ID", "ID Number", "VerifyCode", "CardNo");
    fwrite($output, $headerLine . "\r\n");
    
    // Output data rows
    foreach ($logs as $row) {
        $dateObj = new DateTime($row['CHECKTIME']);
        $formattedDateTime = $dateObj->format('m/d/y g:i A');
        $status = ($row['TYPE'] === 'CHECK-IN') ? 'C/In' : 'C/Out';
        
        $name = $row['NAME'] ?: $row['BADGENUMBER'];
        
        // Map verification codes commonly found in ZK devices to standard text
        $vc = $row['VERIFICATION'];
        if ($vc == '1' || $vc == '15' || $vc === 'FP') { $vc = 'FP'; }
        else if ($vc == '3') { $vc = 'PW'; }
        else if ($vc == '4') { $vc = 'RF'; }
        else { $vc = 'FP'; }
        
        $line = sprintf("%-31s%-15s%-21s%-20s%-17s%-12s%-21s%-19s%-21s",
            "OUR COMPANY",
            $name,
            $row['BADGENUMBER'],
            $formattedDateTime,
            $status,
            "1",
            "",
            $vc,
            ""
        );
        fwrite($output, $line . "\r\n");
    }
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biometric Attendance Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .card-stat {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s;
        }

        .card-stat:hover {
            transform: translateY(-3px);
        }

        .icon-box {
            font-size: 2rem;
            opacity: 0.8;
        }

        .table-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .status-dot {
            height: 10px;
            width: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }

        .dot-in {
            background-color: #198754;
            box-shadow: 0 0 5px #198754;
        }

        .dot-out {
            background-color: #dc3545;
            box-shadow: 0 0 5px #dc3545;
        }

        .header-gradient {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            padding: 20px 0;
            margin-bottom: 30px;
        }
    </style>
</head>

<body>

    <div class="header-gradient">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0"><i class="fas fa-fingerprint me-2"></i>Attendance Dashboard</h2>
                    <small class="opacity-75">Real-time data from ZK Biometric Devices</small>
                </div>
                <div class="text-end">
                    <div class="mt-1"><small>Database: MySQL</small></div>
                </div>
            </div>
        </div>
    </div>

    <div class="container pb-5">

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card card-stat bg-white h-100 p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total Logs (Range)</h6>
                            <h3 class="mb-0 fw-bold">
                                <?php echo number_format($total_logs); ?>
                            </h3>
                        </div>
                        <div class="icon-box text-primary"><i class="fas fa-list-alt"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card card-stat bg-white h-100 p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Active Employees</h6>
                            <h3 class="mb-0 fw-bold">
                                <?php echo number_format($unique_employees); ?>
                            </h3>
                        </div>
                        <div class="icon-box text-info"><i class="fas fa-users"></i></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card table-card mb-4">
            <div class="card-body bg-light">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label text-muted small fw-bold">Search Employee</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control" placeholder="Name, ID or Badge No..."
                                value="<?php echo htmlspecialchars($searchTerm); ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small fw-bold">Date From</label>
                        <input type="date" name="dateFrom" class="form-control"
                            value="<?php echo htmlspecialchars($fromDate); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small fw-bold">Date To</label>
                        <input type="date" name="dateTo" class="form-control"
                            value="<?php echo htmlspecialchars($toDate); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small fw-bold d-none d-md-block">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary w-100 fw-medium"><i class="fas fa-filter me-1"></i> Filter</button>
                            <button type="submit" name="export" value="txt" class="btn btn-success w-100 fw-medium"><i class="fas fa-file-export me-1"></i> Export text</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card table-card">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title mb-0 text-dark"><i class="fas fa-table me-2 text-muted"></i>Attendance Log</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Employee info</th>
                            <th>Date & Time</th>
                            <th>Type</th>
                            <th>Verification</th>
                            <th>Device SN</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($logs) > 0): ?>
                            <?php foreach ($logs as $row):
                                $isCheckIn = ($row['TYPE'] === 'CHECK-IN');
                                $dateObj = new DateTime($row['CHECKTIME']);
                                $formattedDate = $dateObj->format('M d, Y');
                                $formattedTime = $dateObj->format('h:i:s A');
                                ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-light rounded-circle p-2 me-2 text-primary">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div>
                                                <span class="fw-medium text-dark d-block">
                                                    <?php echo htmlspecialchars($row['NAME'] ?: 'Unknown User'); ?>
                                                </span>
                                                <span class="badge bg-light text-dark border mt-1">AC No: <?php echo htmlspecialchars($row['BADGENUMBER']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small fw-bold text-dark">
                                            <?php echo $formattedTime; ?>
                                        </div>
                                        <div class="small text-muted">
                                            <?php echo $formattedDate; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($isCheckIn): ?>
                                            <span class="text-success fw-bold"><span class="status-dot dot-in"></span>IN</span>
                                        <?php else: ?>
                                            <span class="text-danger fw-bold"><span class="status-dot dot-out"></span>OUT</span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span class="small text-muted"><i class="fas fa-fingerprint me-1"></i>
                                            <?php echo htmlspecialchars($row['VERIFICATION']); ?>
                                        </span>
                                    </td>
                                    <td class="text-muted small">
                                        <?php echo htmlspecialchars($row['Device_SN']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-inbox fa-3x mb-3 opacity-50"></i>
                                        <p>No attendance records found for this period.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="text-center mt-4 text-muted small">
            &copy;
            <?php echo date('Y'); ?> Attendance System • Auto-refresh every 5 min
        </div>

    </div>

    <script>
        setTimeout(function () {
            window.location.reload(1);
        }, 300000); // 300,000ms = 5 minutes
    </script>

</body>

</html>