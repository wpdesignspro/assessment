<?php
session_start();
if (!isset($_SESSION['review_logged_in']) || $_SESSION['review_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

// Handle CSV export (without media paths)
if ($action === 'export_csv') {
    $stmt = $pdo->query("SELECT 
        id, school_name, contact_person, contact_phone, contact_email,
        dedicated_building, facility_type, status, health_state, floor_area,
        meets_min_area, total_size, num_floors, location, computer_system,
        num_computers, spec_meet, has_networking, internet_speed, num_exits,
        conveniences, convenience_attached, is_furnished, furniture_list,
        submission_date 
        FROM submissions 
        ORDER BY submission_date DESC");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($results) > 0) {
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="submissions_review_' . date('Y-m-d_H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM to handle UTF-8 characters properly in Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write the header row
        fputcsv($output, array_keys($results[0]));
        
        // Write the data rows
        foreach ($results as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit();
    }
}

// Fetch all submissions (without media paths for review)
$stmt = $pdo->query("SELECT 
    id, school_name, contact_person, contact_phone, contact_email,
    dedicated_building, facility_type, status, health_state, floor_area,
    meets_min_area, total_size, num_floors, location, computer_system,
    num_computers, spec_meet, has_networking, internet_speed, num_exits,
    conveniences, convenience_attached, is_furnished, furniture_list,
    submission_date 
    FROM submissions 
    ORDER BY submission_date DESC");
$submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8 flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-900">Review Dashboard</h1>
                <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-md transition">
                    Logout
                </a>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8">
            <!-- Export Button -->
            <div class="mb-6">
                <a href="?action=export_csv" class="bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-md transition flex items-center">
                    <i class="fas fa-file-export mr-2"></i> Export All to CSV
                </a>
            </div>

            <!-- Submissions Table -->
            <div class="bg-white shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">School Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact Person</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact Email</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (count($submissions) > 0): ?>
                            <?php foreach ($submissions as $submission): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($submission['school_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($submission['contact_person']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($submission['contact_email']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-500"><?php echo date('M j, Y g:i A', strtotime($submission['submission_date'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="view_submission.php?id=<?php echo $submission['id']; ?>" class="text-indigo-600 hover:text-indigo-900">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">
                                    No submissions found
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</body>
</html>