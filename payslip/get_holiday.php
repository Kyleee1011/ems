<?php
// Determine Holiday Type from Database
// Requires $conn (PDO connection) to be passed
function getHolidayType($conn, $date) {
    $dateStr = date('Y-m-d', strtotime($date));

    // Check database first
    $stmt = $conn->prepare("SELECT holiday_type FROM Holidays WHERE holiday_date = ?");
    $stmt->execute([$dateStr]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        return $row['holiday_type']; // Returns 'REGULAR', 'SPECIAL', or 'DOUBLE'
    }
    
    return 'REGULAR_DAY';
}
?>