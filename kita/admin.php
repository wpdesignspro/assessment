<?php
session_start();
require_once dirname(__DIR__) . '/config.php';

// KITA admin only
if (!isset($_SESSION['kita_logged_in']) || $_SESSION['kita_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    logActivity('kita_admin', $_SESSION['kita_username'], 'KITA_LOGOUT', 'KITA admin logged out');
    session_destroy();
    header('Location: login.php');
    exit();
}

$db = Database::getInstance()->getConnection();

// Ensure tables exist (created on first form submission, but guard here too)
$kitaTableExists = false;
try {
    $check = $db->query("SHOW TABLES LIKE 'kita_submissions'");
    $kitaTableExists = $check->rowCount() > 0;
} catch (Exception $e) {}

// Pagination & search
$perPage = 20;
$page    = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset  = ($page - 1) * $perPage;

$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$whereClause = '';
$searchParams = [];
if (!empty($search)) {
    $whereClause  = " WHERE school_name LIKE ? OR staff_name LIKE ? OR location_state LIKE ?";
    $term         = "%$search%";
    $searchParams = [$term, $term, $term];
}

$totalRecords = 0;
$totalPages   = 0;
$submissions  = [];
$stats        = ['total' => 0, 'today' => 0, 'week' => 0, 'month' => 0];

if ($kitaTableExists) {
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM kita_submissions" . $whereClause);
    $countStmt->execute($searchParams ?: []);
    $totalRecords = $countStmt->fetch()['total'];
    $totalPages   = ceil($totalRecords / $perPage);

    $listStmt = $db->prepare("SELECT * FROM kita_submissions" . $whereClause . " ORDER BY submission_date DESC LIMIT ? OFFSET ?");
    $listStmt->execute(array_merge($searchParams, [$perPage, $offset]));
    $submissions = $listStmt->fetchAll();

    $statsStmt = $db->query("SELECT
        COUNT(*) as total,
        COUNT(CASE WHEN DATE(submission_date) = CURDATE() THEN 1 END) as today,
        COUNT(CASE WHEN WEEK(submission_date) = WEEK(CURDATE()) THEN 1 END) as week,
        COUNT(CASE WHEN MONTH(submission_date) = MONTH(CURDATE()) THEN 1 END) as month
    FROM kita_submissions");
    $stats = $statsStmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KITA Admin Dashboard</title>
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
                    <div class="w-9 h-9 bg-blue-700 rounded-lg flex items-center justify-center">
                        <span class="text-white font-extrabold text-base">K</span>
                    </div>
                    <div class="border-l border-slate-300 pl-3">
                        <h1 class="text-lg font-bold text-slate-900">KITA Admin Dashboard</h1>
                        <p class="text-xs text-slate-500">Krystal IT Academy — Hub Assessment</p>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-slate-600">Welcome, <strong><?php echo htmlspecialchars($_SESSION['kita_username']); ?></strong></span>
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
                        <p class="text-3xl font-bold text-slate-900 mt-2"><?php echo number_format($stats['total']); ?></p>
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
                        <p class="text-3xl font-bold text-slate-900 mt-2"><?php echo number_format($stats['today']); ?></p>
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
                        <p class="text-3xl font-bold text-slate-900 mt-2"><?php echo number_format($stats['week']); ?></p>
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
                        <p class="text-3xl font-bold text-slate-900 mt-2"><?php echo number_format($stats['month']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                        <span class="material-icons text-orange-600">calendar_month</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
            <form method="GET" class="max-w-md">
                <div class="relative">
                    <span class="material-icons absolute left-3 top-3 text-slate-400">search</span>
                    <input
                        type="text"
                        name="search"
                        value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Search by school name, staff name, or state..."
                        class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    >
                </div>
            </form>
        </div>

        <!-- Submissions Table -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <?php if (!$kitaTableExists): ?>
                <div class="px-6 py-16 text-center text-slate-500">
                    <span class="material-icons text-5xl mb-3 block text-slate-300">inbox</span>
                    <p class="font-medium">No submissions yet</p>
                    <p class="text-sm mt-1">KITA hub assessment submissions will appear here once received.</p>
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
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Condition</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php if (empty($submissions)): ?>
                            <tr>
                                <td colspan="9" class="px-6 py-12 text-center text-slate-500">
                                    <span class="material-icons text-4xl mb-2 block text-slate-300">search_off</span>
                                    <p>No submissions found<?php echo $search ? ' for "' . htmlspecialchars($search) . '"' : ''; ?></p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($submissions as $sub): ?>
                                <?php
                                $conditionColors = [
                                    'Excellent' => 'bg-green-100 text-green-700',
                                    'Good'      => 'bg-blue-100 text-blue-700',
                                    'Fair'      => 'bg-yellow-100 text-yellow-700',
                                    'Poor'      => 'bg-red-100 text-red-700',
                                ];
                                $condColor = $conditionColors[$sub['hub_condition'] ?? ''] ?? 'bg-slate-100 text-slate-600';

                                // Media count
                                $mediaCountStmt = $db->prepare("SELECT COUNT(*) FROM kita_media_uploads WHERE submission_id = ?");
                                $mediaCountStmt->execute([$sub['id']]);
                                $mediaCount = $mediaCountStmt->fetchColumn();
                                ?>
                                <tr class="hover:bg-slate-50 transition">
                                    <td class="px-6 py-4 text-sm font-medium text-slate-900">#<?php echo $sub['id']; ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-900 font-medium max-w-xs truncate"><?php echo htmlspecialchars($sub['school_name']); ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-700"><?php echo htmlspecialchars($sub['staff_name']); ?></td>
                                    <td class="px-6 py-4 text-sm text-slate-700"><?php echo htmlspecialchars($sub['location_state']); ?></td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"><?php echo htmlspecialchars($sub['kita_position']); ?></span>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $sub['has_functional_hub'] === 'Yes' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                                            <?php echo htmlspecialchars($sub['has_functional_hub']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <?php if (!empty($sub['hub_condition'])): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium <?php echo $condColor; ?>">
                                                <?php echo htmlspecialchars($sub['hub_condition']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-slate-400">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-700">
                                        <?php echo date('M d, Y', strtotime($sub['submission_date'])); ?>
                                        <?php if ($mediaCount > 0): ?>
                                            <br><span class="inline-flex items-center gap-0.5 text-xs text-slate-400 mt-0.5">
                                                <span class="material-icons" style="font-size:12px;">attach_file</span><?php echo $mediaCount; ?> file<?php echo $mediaCount > 1 ? 's' : ''; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-sm">
                                        <a href="view_submission.php?id=<?php echo $sub['id']; ?>" class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-medium transition">
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
            <?php endif; ?>
        </div>

    </div>
</body>
</html>
