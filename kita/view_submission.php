<?php
session_start();
require_once dirname(__DIR__) . '/config.php';

// KITA admin only
if (!isset($_SESSION['kita_logged_in']) || $_SESSION['kita_logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header('Location: admin.php');
    exit();
}

$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("SELECT * FROM kita_submissions WHERE id = ?");
$stmt->execute([$id]);
$submission = $stmt->fetch();

if (!$submission) {
    header('Location: admin.php');
    exit();
}

$mediaStmt = $db->prepare("SELECT * FROM kita_media_uploads WHERE submission_id = ? ORDER BY category, upload_date");
$mediaStmt->execute([$id]);
$mediaFiles = $mediaStmt->fetchAll();

$categoryLabels = [
    'hub_image'            => 'Hub Pictures (Inside & Outside)',
    'hub_video'            => 'Hub Videos',
    'equipment_image'      => 'Equipment / Furniture Pictures',
    'functional_image'     => 'Functional Equipment Pictures',
    'nonfunctional_image'  => 'Non-Functional Equipment Pictures',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KITA Submission #<?php echo $id; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .detail-row { border-bottom: 1px solid #e2e8f0; }
        .detail-row:last-child { border-bottom: none; }
    </style>
</head>
<body class="bg-slate-50">

    <header class="bg-white border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center gap-3">
                    <a href="admin.php" class="text-slate-600 hover:text-slate-900">
                        <span class="material-icons">arrow_back</span>
                    </a>
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 bg-blue-700 rounded flex items-center justify-center">
                            <span class="text-white font-extrabold text-xs">K</span>
                        </div>
                        <h1 class="text-lg font-bold text-slate-900">KITA Submission #<?php echo $id; ?></h1>
                    </div>
                </div>
                <a href="admin.php" class="text-sm text-slate-600 hover:text-slate-900 font-medium">Back to Dashboard</a>
            </div>
        </div>
    </header>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <!-- Section A: General Information -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
            <h2 class="text-xl font-bold text-slate-900 mb-6 flex items-center gap-2">
                <span class="material-icons text-blue-600">info</span>
                Section A: General Information
            </h2>
            <div class="space-y-4">
                <div class="detail-row py-3">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-semibold text-slate-600">School Name</label>
                            <p class="text-base text-slate-900 mt-1"><?php echo htmlspecialchars($submission['school_name']); ?></p>
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-600">Location (State)</label>
                            <p class="text-base text-slate-900 mt-1"><?php echo htmlspecialchars($submission['location_state']); ?></p>
                        </div>
                    </div>
                </div>
                <div class="detail-row py-3">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="text-sm font-semibold text-slate-600">KITA Position</label>
                            <p class="text-base text-slate-900 mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <?php echo htmlspecialchars($submission['kita_position']); ?>
                                </span>
                            </p>
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-600">Name of Staff</label>
                            <p class="text-base text-slate-900 mt-1"><?php echo htmlspecialchars($submission['staff_name']); ?></p>
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-600">Phone Number</label>
                            <p class="text-base text-slate-900 mt-1"><?php echo htmlspecialchars($submission['phone_number']); ?></p>
                        </div>
                    </div>
                </div>
                <div class="py-3">
                    <label class="text-sm font-semibold text-slate-600">Submission Date</label>
                    <p class="text-base text-slate-900 mt-1"><?php echo date('F d, Y \a\t h:i A', strtotime($submission['submission_date'])); ?></p>
                </div>
            </div>
        </div>

        <!-- Section B: Hub Availability -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
            <h2 class="text-xl font-bold text-slate-900 mb-6 flex items-center gap-2">
                <span class="material-icons text-blue-600">home_work</span>
                Section B: Hub Availability
            </h2>
            <div class="space-y-4">
                <div class="detail-row py-3">
                    <label class="text-sm font-semibold text-slate-600">Has Functional KITA Hub</label>
                    <p class="text-base text-slate-900 mt-1">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $submission['has_functional_hub'] === 'Yes' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                            <?php echo htmlspecialchars($submission['has_functional_hub']); ?>
                        </span>
                    </p>
                </div>

                <?php if ($submission['has_functional_hub'] === 'No'): ?>
                    <?php if (!empty($submission['no_hub_reason'])): ?>
                        <div class="detail-row py-3">
                            <label class="text-sm font-semibold text-slate-600">Reason for No Hub Space</label>
                            <p class="text-base text-slate-900 mt-1 whitespace-pre-line"><?php echo htmlspecialchars($submission['no_hub_reason']); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($submission['can_provide_space'])): ?>
                        <div class="detail-row py-3">
                            <label class="text-sm font-semibold text-slate-600">Can School Provide Space</label>
                            <p class="text-base text-slate-900 mt-1">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $submission['can_provide_space'] === 'Yes' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                    <?php echo htmlspecialchars($submission['can_provide_space']); ?>
                                </span>
                            </p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!empty($submission['hub_description'])): ?>
                    <div class="py-3">
                        <label class="text-sm font-semibold text-slate-600">Hub Description</label>
                        <p class="text-base text-slate-900 mt-1 whitespace-pre-line"><?php echo htmlspecialchars($submission['hub_description']); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Section C: Hub Inventory -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
            <h2 class="text-xl font-bold text-slate-900 mb-6 flex items-center gap-2">
                <span class="material-icons text-blue-600">inventory_2</span>
                Section C: Hub Inventory
            </h2>
            <div class="space-y-4">
                <div class="detail-row py-3">
                    <label class="text-sm font-semibold text-slate-600">All Available Items (with quantity)</label>
                    <p class="text-base text-slate-900 mt-1 whitespace-pre-line"><?php echo htmlspecialchars($submission['available_items'] ?? 'Not specified'); ?></p>
                </div>
                <div class="detail-row py-3">
                    <label class="text-sm font-semibold text-slate-600">Working / Good Items</label>
                    <p class="text-base text-slate-900 mt-1 whitespace-pre-line"><?php echo htmlspecialchars($submission['working_items'] ?? 'Not specified'); ?></p>
                </div>
                <div class="detail-row py-3">
                    <label class="text-sm font-semibold text-slate-600">Faulty / Damaged Items</label>
                    <p class="text-base text-slate-900 mt-1 whitespace-pre-line"><?php echo htmlspecialchars($submission['faulty_items'] ?? 'Not specified'); ?></p>
                </div>
                <div class="detail-row py-3">
                    <label class="text-sm font-semibold text-slate-600">Items Needing Repair</label>
                    <p class="text-base text-slate-900 mt-1 whitespace-pre-line"><?php echo htmlspecialchars($submission['items_need_repair'] ?? 'Not specified'); ?></p>
                </div>
                <div class="py-3">
                    <label class="text-sm font-semibold text-slate-600">Items Needing Replacement</label>
                    <p class="text-base text-slate-900 mt-1 whitespace-pre-line"><?php echo htmlspecialchars($submission['items_need_replacement'] ?? 'Not specified'); ?></p>
                </div>
            </div>
        </div>

        <!-- Section D: Infrastructure Status -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
            <h2 class="text-xl font-bold text-slate-900 mb-6 flex items-center gap-2">
                <span class="material-icons text-blue-600">domain</span>
                Section D: Infrastructure Status
            </h2>
            <div class="space-y-4">
                <div class="detail-row py-3">
                    <label class="text-sm font-semibold text-slate-600">Hub Condition Rating</label>
                    <p class="text-base text-slate-900 mt-1">
                        <?php
                        $conditionColors = [
                            'Excellent' => 'bg-green-100 text-green-800',
                            'Good'      => 'bg-blue-100 text-blue-800',
                            'Fair'      => 'bg-yellow-100 text-yellow-800',
                            'Poor'      => 'bg-red-100 text-red-800',
                        ];
                        $condition  = $submission['hub_condition'] ?? null;
                        $colorClass = $conditionColors[$condition] ?? 'bg-slate-100 text-slate-800';
                        ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $colorClass; ?>">
                            <?php echo htmlspecialchars($condition ?? 'Not specified'); ?>
                        </span>
                    </p>
                </div>

                <div class="detail-row py-3">
                    <label class="text-sm font-semibold text-slate-600">Applicable Infrastructure Issues</label>
                    <p class="text-base text-slate-900 mt-1"><?php echo htmlspecialchars($submission['infrastructure_issues'] ?? 'None selected'); ?></p>
                    <?php if (!empty($submission['damaged_furniture_nos'])): ?>
                        <p class="text-sm text-slate-600 mt-1">Damaged furniture count/description: <span class="font-medium text-slate-800"><?php echo htmlspecialchars($submission['damaged_furniture_nos']); ?></span></p>
                    <?php endif; ?>
                    <?php if (!empty($submission['infrastructure_issues_other'])): ?>
                        <p class="text-sm text-slate-600 mt-1">Other issues: <span class="font-medium text-slate-800"><?php echo htmlspecialchars($submission['infrastructure_issues_other']); ?></span></p>
                    <?php endif; ?>
                </div>

                <div class="detail-row py-3">
                    <label class="text-sm font-semibold text-slate-600">Top 3 Urgent Needs (Priority Order)</label>
                    <ol class="mt-2 space-y-1">
                        <?php foreach ([1,2,3] as $n): ?>
                            <?php $need = $submission["urgent_need_$n"] ?? null; ?>
                            <?php if (!empty($need)): ?>
                                <li class="flex items-start gap-2">
                                    <span class="flex-shrink-0 w-6 h-6 bg-blue-600 text-white rounded-full flex items-center justify-center text-xs font-bold"><?php echo $n; ?></span>
                                    <span class="text-base text-slate-900"><?php echo htmlspecialchars($need); ?></span>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if (empty($submission['urgent_need_1']) && empty($submission['urgent_need_2']) && empty($submission['urgent_need_3'])): ?>
                            <li class="text-slate-500 italic">Not specified</li>
                        <?php endif; ?>
                    </ol>
                </div>

                <div class="detail-row py-3">
                    <label class="text-sm font-semibold text-slate-600">Immediate Support Required</label>
                    <p class="text-base text-slate-900 mt-1 whitespace-pre-line"><?php echo htmlspecialchars($submission['immediate_support'] ?? 'Not specified'); ?></p>
                </div>

                <div class="py-3">
                    <label class="text-sm font-semibold text-slate-600">Recommendations</label>
                    <p class="text-base text-slate-900 mt-1 whitespace-pre-line"><?php echo htmlspecialchars($submission['recommendations'] ?? 'None provided'); ?></p>
                </div>
            </div>
        </div>

        <!-- Media Files -->
        <?php
        $grouped = [];
        foreach ($mediaFiles as $file) {
            $grouped[$file['category']][] = $file;
        }
        ?>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
            <h2 class="text-xl font-bold text-slate-900 mb-6 flex items-center gap-2">
                <span class="material-icons text-blue-600">perm_media</span>
                Attached Media (<?php echo count($mediaFiles); ?> file<?php echo count($mediaFiles) !== 1 ? 's' : ''; ?>)
            </h2>

            <?php if (empty($mediaFiles)): ?>
                <p class="text-slate-500 italic">No media files were uploaded with this submission.</p>
            <?php else: ?>
                <?php foreach ($categoryLabels as $catKey => $catLabel): ?>
                    <?php if (empty($grouped[$catKey])) continue; ?>
                    <div class="mb-8">
                        <h3 class="text-base font-semibold text-slate-800 mb-4 flex items-center gap-2">
                            <span class="material-icons text-slate-400 text-lg"><?php echo $catKey === 'hub_video' ? 'videocam' : 'image'; ?></span>
                            <?php echo $catLabel; ?> (<?php echo count($grouped[$catKey]); ?>)
                        </h3>

                        <?php if ($catKey === 'hub_video'): ?>
                            <div class="space-y-3">
                                <?php foreach ($grouped[$catKey] as $video): ?>
                                    <div class="border border-slate-200 rounded-lg p-4 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <span class="material-icons text-purple-600">videocam</span>
                                            <div>
                                                <p class="text-sm font-medium text-slate-900"><?php echo htmlspecialchars($video['file_name']); ?></p>
                                                <p class="text-xs text-slate-500"><?php echo number_format($video['file_size'] / 1024 / 1024, 2); ?> MB</p>
                                            </div>
                                        </div>
                                        <a href="../../<?php echo htmlspecialchars($video['file_path']); ?>" download class="inline-flex items-center gap-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-medium transition">
                                            <span class="material-icons" style="font-size:14px;">download</span>
                                            Download
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <?php foreach ($grouped[$catKey] as $image): ?>
                                    <div class="border border-slate-200 rounded-lg p-2">
                                        <img src="../../<?php echo htmlspecialchars($image['file_path']); ?>" alt="Image" class="w-full h-32 object-cover rounded mb-2">
                                        <a href="../../<?php echo htmlspecialchars($image['file_path']); ?>" download class="text-xs text-blue-600 hover:text-blue-700 flex items-center gap-1">
                                            <span class="material-icons" style="font-size:14px;">download</span>
                                            Download
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>
</body>
</html>
