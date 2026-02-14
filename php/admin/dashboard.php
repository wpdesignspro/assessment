<?php
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

// Handle CSV export
if ($action === 'export_csv') {
    $stmt = $pdo->query("SELECT * FROM submissions ORDER BY submission_date DESC");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($results) > 0) {
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="submissions_' . date('Y-m-d_H-i-s') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM to handle UTF-8 characters properly in Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Write the header row
        fputcsv($output, array_keys($results[0]));
        
        // Write the data rows
        foreach ($results as $row) {
            // Format image_paths and video_path for CSV
            $formatted_row = $row;
            $formatted_row['image_paths'] = is_null($row['image_paths']) ? '' : json_decode($row['image_paths'], true);
            $formatted_row['image_paths'] = is_array($formatted_row['image_paths']) ? implode('|', $formatted_row['image_paths']) : $formatted_row['image_paths'];
            fputcsv($output, $formatted_row);
        }
        
        fclose($output);
        exit();
    }
}

// Handle deletion of a submission
if ($action === 'delete' && isset($_GET['id'])) {
    $id = $_GET['id'];
    // Get the submission to delete file references
    $stmt = $pdo->prepare("SELECT image_paths, video_path FROM submissions WHERE id = ?");
    $stmt->execute([$id]);
    $submission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($submission) {
        // Delete associated files
        if ($submission['image_paths']) {
            $imagePaths = json_decode($submission['image_paths'], true);
            if (is_array($imagePaths)) {
                foreach ($imagePaths as $path) {
                    if (file_exists($path)) {
                        unlink($path);
                    }
                }
            }
        }
        
        if ($submission['video_path'] && file_exists($submission['video_path'])) {
            unlink($submission['video_path']);
        }
        
        // Delete the record from database
        $stmt = $pdo->prepare("DELETE FROM submissions WHERE id = ?");
        $stmt->execute([$id]);
    }
    
    header("Location: dashboard.php");
    exit();
}

// Fetch all submissions
$stmt = $pdo->query("SELECT * FROM submissions ORDER BY submission_date DESC");
$submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8 flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-900">Admin Dashboard</h1>
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
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Media</th>
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
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php
                                        $imageCount = 0;
                                        $videoCount = 0;
                                        
                                        if ($submission['image_paths']) {
                                            $images = json_decode($submission['image_paths'], true);
                                            $imageCount = is_array($images) ? count($images) : 0;
                                        }
                                        
                                        if ($submission['video_path']) {
                                            $videoCount = 1;
                                        }
                                        
                                        echo "Images: {$imageCount}, Videos: {$videoCount}";
                                        ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="view_submission.php?id=<?php echo $submission['id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="?action=delete&id=<?php echo $submission['id']; ?>" 
                                           onclick="return confirm('Are you sure you want to delete this submission?')" 
                                           class="text-red-600 hover:text-red-900">
                                           <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">
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