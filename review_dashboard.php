<?php
require_once 'config.php';
requireLogin();

// Only reviewers can access this page (admins will stay in their dashboard)
if (!isReview()) {
    header('Location: admin_dashboard.php');
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    logActivity('review', $_SESSION['username'], 'LOGOUT', 'Reviewer logged out');
    session_destroy();
    header('Location: login.php');
    exit();
}

// Get database connection
$db = Database::getInstance()->getConnection();

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Search functionality
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$searchCondition = '';
$searchParams = [];

if (!empty($search)) {
    $searchCondition = " WHERE school_name LIKE ? OR contact_person LIKE ? OR contact_email LIKE ?";
    $searchTerm = "%$search%";
    $searchParams = [$searchTerm, $searchTerm, $searchTerm];
}

// Get total count
$countStmt = $db->prepare("SELECT COUNT(*) as total FROM submissions" . $searchCondition);
if (!empty($searchParams)) {
    $countStmt->execute($searchParams);
} else {
    $countStmt->execute();
}
$totalRecords = $countStmt->fetch()['total'];
$totalPages = ceil($totalRecords / $perPage);

// Get submissions
$stmt = $db->prepare("SELECT * FROM submissions" . $searchCondition . " ORDER BY submission_date DESC LIMIT ? OFFSET ?");
if (!empty($searchParams)) {
    $params = array_merge($searchParams, [$perPage, $offset]);
    $stmt->execute($params);
} else {
    $stmt->execute([$perPage, $offset]);
}
$submissions = $stmt->fetchAll();

// Get statistics
$statsStmt = $db->query("SELECT 
    COUNT(*) as total_submissions,
    COUNT(CASE WHEN DATE(submission_date) = CURDATE() THEN 1 END) as today_submissions,
    COUNT(CASE WHEN WEEK(submission_date) = WEEK(CURDATE()) THEN 1 END) as week_submissions
FROM submissions");
$stats = $statsStmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Dashboard - ICT Assessment Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .stat-card { transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-2px); }
    </style>
</head>
<body class="bg-slate-50">
    <!-- Header -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center gap-3">
                    <img src="https://i0.wp.com/thenigeriaeducationnews.com/wp-content/uploads/2025/03/TETFUND.jpg?fit=1053%2C343&ssl=1" alt="TETFUND" class="h-10">
                    <div class="border-l border-slate-300 pl-3">
                        <h1 class="text-lg font-bold text-slate-900">Review Dashboard</h1>
                        <p class="text-xs text-slate-500">ICT Assessment Portal</p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-slate-600">Welcome, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></span>
                    <a href="?logout=1" class="text-sm text-red-600 hover:text-red-700 font-medium">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Info Banner -->
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
            <div class="flex items-start gap-3">
                <span class="material-icons text-blue-600">info</span>
                <div>
                    <p class="text-sm font-medium text-blue-900">Review Access</p>
                    <p class="text-sm text-blue-700">You have read-only access to submission data. Media files (images/videos) are not accessible from this dashboard.</p>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="stat-card bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-600">Total Submissions</p>
                        <p class="text-3xl font-bold text-slate-900 mt-2"><?php echo number_format($stats['total_submissions']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <span class="material-icons text-blue-600">assessment</span>
                    </div>
                </div>
            </div>

            <div class="stat-card bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-600">Today</p>
                        <p class="text-3xl font-bold text-slate-900 mt-2"><?php echo number_format($stats['today_submissions']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <span class="material-icons text-green-600">today</span>
                    </div>
                </div>
            </div>

            <div class="stat-card bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-600">This Week</p>
                        <p class="text-3xl font-bold text-slate-900 mt-2"><?php echo number_format($stats['week_submissions']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <span class="material-icons text-purple-600">date_range</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
            <div class="flex flex-col md:flex-row gap-4 justify-between items-start md:items-center">
                <!-- Search -->
                <form method="GET" class="flex-1 max-w-md">
                    <div class="relative">
                        <span class="material-icons absolute left-3 top-3 text-slate-400">search</span>
                        <input 
                            type="text" 
                            name="search" 
                            value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Search by school, contact person, or email..." 
                            class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                    </div>
                </form>

                <!-- Export Button (Data Only) -->
                <div class="flex gap-3">
                    <a href="export_csv.php?type=data" class="inline-flex items-center gap-2 px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition">
                        <span class="material-icons text-sm">download</span>
                        Export Data CSV
                    </a>
                </div>
            </div>
        </div>

        <!-- Submissions Table -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">School Name</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Contact Person</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Phone</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Facility Type</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php if (empty($submissions)): ?>
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-slate-500">
                                    <span class="material-icons text-4xl mb-2">inbox</span>
                                    <p>No submissions found</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($submissions as $submission): ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-6 py-4 text-sm font-medium text-slate-900">#<?php echo $submission['id']; ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-900 font-medium"><?php echo htmlspecialchars($submission['school_name']); ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-700"><?php echo htmlspecialchars($submission['contact_person']); ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-700"><?php echo htmlspecialchars($submission['contact_email']); ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-700"><?php echo htmlspecialchars($submission['contact_phone']); ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-700"><?php echo htmlspecialchars($submission['facility_type']); ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-700"><?php echo date('M d, Y', strtotime($submission['submission_date'])); ?></td>
                                    <td class="px-6 py-4 text-sm">
                                        <a href="view_submission.php?id=<?php echo $submission['id']; ?>&review=1" class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-medium transition">
                                            <span class="material-icons" style="font-size: 14px;">visibility</span>
                                            View Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 border-t border-slate-200 flex items-center justify-between">
                    <div class="text-sm text-slate-600">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $perPage, $totalRecords); ?> of <?php echo $totalRecords; ?> results
                    </div>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-2 border border-slate-300 rounded-lg text-sm font-medium hover:bg-slate-50 transition">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-2 border <?php echo $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-300 hover:bg-slate-50'; ?> rounded-lg text-sm font-medium transition">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="px-3 py-2 border border-slate-300 rounded-lg text-sm font-medium hover:bg-slate-50 transition">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>