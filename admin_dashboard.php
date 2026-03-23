<?php
require_once 'config.php';
requireLogin();

// Only admins can access this page
if (!isAdmin()) {
    header('Location: review_dashboard.php');
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    logActivity('admin', $_SESSION['username'], 'LOGOUT', 'Admin logged out');
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
    COUNT(CASE WHEN WEEK(submission_date) = WEEK(CURDATE()) THEN 1 END) as week_submissions,
    COUNT(CASE WHEN MONTH(submission_date) = MONTH(CURDATE()) THEN 1 END) as month_submissions
FROM submissions");
$stats = $statsStmt->fetch();

// ── KITA Submissions ──────────────────────────────────────────────────────────
$kitaPage = isset($_GET['kita_page']) ? max(1, intval($_GET['kita_page'])) : 1;
$kitaOffset = ($kitaPage - 1) * $perPage;

$kitaSearch = isset($_GET['kita_search']) ? sanitizeInput($_GET['kita_search']) : '';
$kitaSearchCondition = '';
$kitaSearchParams = [];
if (!empty($kitaSearch)) {
    $kitaSearchCondition = " WHERE school_name LIKE ? OR staff_name LIKE ? OR location_state LIKE ?";
    $kitaSearchTerm = "%$kitaSearch%";
    $kitaSearchParams = [$kitaSearchTerm, $kitaSearchTerm, $kitaSearchTerm];
}

// Check if kita_submissions table exists before querying
$kitaTableExists = false;
try {
    $checkStmt = $db->query("SHOW TABLES LIKE 'kita_submissions'");
    $kitaTableExists = $checkStmt->rowCount() > 0;
} catch (Exception $e) {}

$kitaTotalRecords = 0;
$kitaTotalPages   = 0;
$kitaSubmissions  = [];
$kitaStats        = ['total' => 0, 'today' => 0, 'week' => 0, 'month' => 0];

if ($kitaTableExists) {
    $kitaCountStmt = $db->prepare("SELECT COUNT(*) as total FROM kita_submissions" . $kitaSearchCondition);
    $kitaCountStmt->execute($kitaSearchParams ?: []);
    $kitaTotalRecords = $kitaCountStmt->fetch()['total'];
    $kitaTotalPages   = ceil($kitaTotalRecords / $perPage);

    $kitaStmt = $db->prepare("SELECT * FROM kita_submissions" . $kitaSearchCondition . " ORDER BY submission_date DESC LIMIT ? OFFSET ?");
    $kitaStmt->execute(array_merge($kitaSearchParams, [$perPage, $kitaOffset]));
    $kitaSubmissions = $kitaStmt->fetchAll();

    $kitaStatsStmt = $db->query("SELECT
        COUNT(*) as total,
        COUNT(CASE WHEN DATE(submission_date) = CURDATE() THEN 1 END) as today,
        COUNT(CASE WHEN WEEK(submission_date) = WEEK(CURDATE()) THEN 1 END) as week,
        COUNT(CASE WHEN MONTH(submission_date) = MONTH(CURDATE()) THEN 1 END) as month
    FROM kita_submissions");
    $kitaStats = $kitaStatsStmt->fetch();
}
// ─────────────────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ICT Assessment Portal</title>
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
                        <h1 class="text-lg font-bold text-slate-900">Admin Dashboard</h1>
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
        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
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

            <div class="stat-card bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-600">This Month</p>
                        <p class="text-3xl font-bold text-slate-900 mt-2"><?php echo number_format($stats['month_submissions']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <span class="material-icons text-orange-600">calendar_month</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="flex gap-2 mb-6 border-b border-slate-200">
            <button id="tabIct" onclick="switchTab('ict')" class="tab-btn px-5 py-2.5 text-sm font-semibold rounded-t-lg border-b-2 transition focus:outline-none">
                <span class="material-icons align-middle text-base mr-1">computer</span>ICT Submissions
            </button>
            <button id="tabKita" onclick="switchTab('kita')" class="tab-btn px-5 py-2.5 text-sm font-semibold rounded-t-lg border-b-2 transition focus:outline-none">
                <span class="material-icons align-middle text-base mr-1">hub</span>KITA Hub Submissions
            </button>
        </div>

        <!-- ═══════════════════════ ICT TAB ═══════════════════════ -->
        <div id="paneIct">

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

                <!-- Export Buttons -->
                <div class="flex gap-3">
                    <a href="export_csv.php?type=data" class="inline-flex items-center gap-2 px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition">
                        <span class="material-icons text-sm">download</span>
                        Export Data CSV
                    </a>
                    <a href="download_media.php" class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition">
                        <span class="material-icons text-sm">folder_zip</span>
                        Download All Media
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
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Media</th>
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
                                <?php
                                // Get media count for this submission
                                $mediaStmt = $db->prepare("SELECT COUNT(*) as count, file_type FROM media_uploads WHERE submission_id = ? GROUP BY file_type");
                                $mediaStmt->execute([$submission['id']]);
                                $mediaCount = $mediaStmt->fetchAll(PDO::FETCH_KEY_PAIR);
                                $imageCount = $mediaCount['image'] ?? 0;
                                $videoCount = $mediaCount['video'] ?? 0;
                                ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-6 py-4 text-sm font-medium text-slate-900">#<?php echo $submission['id']; ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-900 font-medium"><?php echo htmlspecialchars($submission['school_name']); ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-700"><?php echo htmlspecialchars($submission['contact_person']); ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-700"><?php echo htmlspecialchars($submission['contact_email']); ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-700"><?php echo htmlspecialchars($submission['contact_phone']); ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-700"><?php echo date('M d, Y', strtotime($submission['submission_date'])); ?></td>
                                    <td class="px-6 py-4 text-sm">
                                        <div class="flex gap-2">
                                            <?php if ($imageCount > 0): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-medium">
                                                    <span class="material-icons" style="font-size: 14px;">image</span>
                                                    <?php echo $imageCount; ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($videoCount > 0): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-purple-100 text-purple-700 rounded text-xs font-medium">
                                                    <span class="material-icons" style="font-size: 14px;">videocam</span>
                                                    <?php echo $videoCount; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <div class="flex gap-2">
                                            <a href="view_submission.php?id=<?php echo $submission['id']; ?>" class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-medium transition">
                                                <span class="material-icons" style="font-size: 14px;">visibility</span>
                                                View
                                            </a>
                                            <a href="download_submission.php?id=<?php echo $submission['id']; ?>" class="inline-flex items-center gap-1 px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white rounded-lg text-xs font-medium transition">
                                                <span class="material-icons" style="font-size: 14px;">download</span>
                                                Download
                                            </a>
                                        </div>
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

        </div><!-- end #paneIct -->

        <!-- ═══════════════════════ KITA TAB ═══════════════════════ -->
        <div id="paneKita" style="display:none;">

            <!-- KITA Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="stat-card bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-600">Total KITA Submissions</p>
                            <p class="text-3xl font-bold text-slate-900 mt-2"><?php echo number_format($kitaStats['total']); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <span class="material-icons text-blue-600">hub</span>
                        </div>
                    </div>
                </div>
                <div class="stat-card bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-600">Today</p>
                            <p class="text-3xl font-bold text-slate-900 mt-2"><?php echo number_format($kitaStats['today']); ?></p>
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
                            <p class="text-3xl font-bold text-slate-900 mt-2"><?php echo number_format($kitaStats['week']); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <span class="material-icons text-purple-600">date_range</span>
                        </div>
                    </div>
                </div>
                <div class="stat-card bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-600">This Month</p>
                            <p class="text-3xl font-bold text-slate-900 mt-2"><?php echo number_format($kitaStats['month']); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                            <span class="material-icons text-orange-600">calendar_month</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KITA Search -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
                <form method="GET" class="flex-1 max-w-md" onsubmit="return submitKitaSearch(event)">
                    <div class="relative">
                        <span class="material-icons absolute left-3 top-3 text-slate-400">search</span>
                        <input
                            type="text"
                            id="kitaSearchInput"
                            value="<?php echo htmlspecialchars($kitaSearch); ?>"
                            placeholder="Search by school, staff, or state..."
                            class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                    </div>
                </form>
            </div>

            <!-- KITA Submissions Table -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <?php if (!$kitaTableExists): ?>
                    <div class="px-6 py-12 text-center text-slate-500">
                        <span class="material-icons text-4xl mb-2">info</span>
                        <p>No KITA submissions yet. The table will be created when the first submission is received.</p>
                    </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">ID</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">School Name</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Staff Name</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">State</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Position</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Hub</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Date</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <?php if (empty($kitaSubmissions)): ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-12 text-center text-slate-500">
                                        <span class="material-icons text-4xl mb-2">inbox</span>
                                        <p>No KITA submissions found</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($kitaSubmissions as $ks): ?>
                                    <tr class="hover:bg-slate-50 transition">
                                        <td class="px-6 py-4 text-sm font-medium text-slate-900">#<?php echo $ks['id']; ?></td>
                                        <td class="px-6 py-4 text-sm text-slate-900 font-medium"><?php echo htmlspecialchars($ks['school_name']); ?></td>
                                        <td class="px-6 py-4 text-sm text-slate-700"><?php echo htmlspecialchars($ks['staff_name']); ?></td>
                                        <td class="px-6 py-4 text-sm text-slate-700"><?php echo htmlspecialchars($ks['location_state']); ?></td>
                                        <td class="px-6 py-4 text-sm text-slate-700">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"><?php echo htmlspecialchars($ks['kita_position']); ?></span>
                                        </td>
                                        <td class="px-6 py-4 text-sm">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $ks['has_functional_hub'] === 'Yes' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                                <?php echo htmlspecialchars($ks['has_functional_hub']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-slate-700"><?php echo date('M d, Y', strtotime($ks['submission_date'])); ?></td>
                                        <td class="px-6 py-4 text-sm">
                                            <a href="view_kita_submission.php?id=<?php echo $ks['id']; ?>" class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-medium transition">
                                                <span class="material-icons" style="font-size:14px;">visibility</span>
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- KITA Pagination -->
                <?php if ($kitaTotalPages > 1): ?>
                    <div class="px-6 py-4 border-t border-slate-200 flex items-center justify-between">
                        <div class="text-sm text-slate-600">
                            Showing <?php echo $kitaOffset + 1; ?> to <?php echo min($kitaOffset + $perPage, $kitaTotalRecords); ?> of <?php echo $kitaTotalRecords; ?> results
                        </div>
                        <div class="flex gap-2">
                            <?php if ($kitaPage > 1): ?>
                                <a href="javascript:void(0)" onclick="goKitaPage(<?php echo $kitaPage - 1; ?>)" class="px-3 py-2 border border-slate-300 rounded-lg text-sm font-medium hover:bg-slate-50 transition">Previous</a>
                            <?php endif; ?>
                            <?php for ($i = max(1, $kitaPage - 2); $i <= min($kitaTotalPages, $kitaPage + 2); $i++): ?>
                                <a href="javascript:void(0)" onclick="goKitaPage(<?php echo $i; ?>)" class="px-3 py-2 border <?php echo $i === $kitaPage ? 'bg-blue-600 text-white border-blue-600' : 'border-slate-300 hover:bg-slate-50'; ?> rounded-lg text-sm font-medium transition">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            <?php if ($kitaPage < $kitaTotalPages): ?>
                                <a href="javascript:void(0)" onclick="goKitaPage(<?php echo $kitaPage + 1; ?>)" class="px-3 py-2 border border-slate-300 rounded-lg text-sm font-medium hover:bg-slate-50 transition">Next</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

        </div><!-- end #paneKita -->

    </div>

    <script>
        // Determine initial active tab from URL or server-side (e.g., ?tab=kita)
        const urlParams = new URLSearchParams(window.location.search);
        const initTab = urlParams.get('tab') === 'kita' ? 'kita' : 'ict';

        function switchTab(tab) {
            const ictBtn   = document.getElementById('tabIct');
            const kitaBtn  = document.getElementById('tabKita');
            const ictPane  = document.getElementById('paneIct');
            const kitaPane = document.getElementById('paneKita');

            if (tab === 'ict') {
                ictPane.style.display  = '';
                kitaPane.style.display = 'none';
                ictBtn.classList.add('border-blue-600', 'text-blue-700');
                ictBtn.classList.remove('border-transparent', 'text-slate-500');
                kitaBtn.classList.add('border-transparent', 'text-slate-500');
                kitaBtn.classList.remove('border-blue-600', 'text-blue-700');
            } else {
                ictPane.style.display  = 'none';
                kitaPane.style.display = '';
                kitaBtn.classList.add('border-blue-600', 'text-blue-700');
                kitaBtn.classList.remove('border-transparent', 'text-slate-500');
                ictBtn.classList.add('border-transparent', 'text-slate-500');
                ictBtn.classList.remove('border-blue-600', 'text-blue-700');
            }
            // Update URL without reload
            const url = new URL(window.location.href);
            if (tab === 'kita') {
                url.searchParams.set('tab', 'kita');
            } else {
                url.searchParams.delete('tab');
            }
            history.replaceState(null, '', url.toString());
        }

        function submitKitaSearch(e) {
            e.preventDefault();
            const val = document.getElementById('kitaSearchInput').value;
            const url = new URL(window.location.href);
            url.searchParams.set('tab', 'kita');
            url.searchParams.set('kita_search', val);
            url.searchParams.delete('kita_page');
            window.location.href = url.toString();
        }

        function goKitaPage(page) {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', 'kita');
            url.searchParams.set('kita_page', page);
            window.location.href = url.toString();
        }

        // Activate initial tab on load
        switchTab(initTab);
    </script>
</body>
</html>