<?php
require_once 'config.php';
requireLogin();

$isReview = isset($_GET['review']) && $_GET['review'] == '1';

// Get submission ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id <= 0) {
    header('Location: ' . (isAdmin() ? 'admin_dashboard.php' : 'review_dashboard.php'));
    exit();
}

// Get database connection
$db = Database::getInstance()->getConnection();

// Get submission details
$stmt = $db->prepare("SELECT * FROM submissions WHERE id = ?");
$stmt->execute([$id]);
$submission = $stmt->fetch();

if (!$submission) {
    header('Location: ' . (isAdmin() ? 'admin_dashboard.php' : 'review_dashboard.php'));
    exit();
}

// Get media files (only for admin)
$mediaFiles = [];
if (isAdmin()) {
    $mediaStmt = $db->prepare("SELECT * FROM media_uploads WHERE submission_id = ? ORDER BY file_type, upload_date");
    $mediaStmt->execute([$id]);
    $mediaFiles = $mediaStmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Submission #<?php echo $id; ?> - ICT Assessment Portal</title>
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
    <!-- Header -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center gap-3">
                    <a href="<?php echo isAdmin() ? 'admin_dashboard.php' : 'review_dashboard.php'; ?>" class="text-slate-600 hover:text-slate-900">
                        <span class="material-icons">arrow_back</span>
                    </a>
                    <h1 class="text-lg font-bold text-slate-900">Submission Details #<?php echo $id; ?></h1>
                </div>
                <div class="flex items-center gap-3">
                    <?php if (isAdmin()): ?>
                        <a href="download_submission.php?id=<?php echo $id; ?>" class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition text-sm">
                            <span class="material-icons text-sm">download</span>
                            Download All
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo isAdmin() ? 'admin_dashboard.php' : 'review_dashboard.php'; ?>" class="text-sm text-slate-600 hover:text-slate-900 font-medium">Back to Dashboard</a>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Basic Information -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
            <h2 class="text-xl font-bold text-slate-900 mb-6 flex items-center gap-2">
                <span class="material-icons text-blue-600">school</span>
                School Information
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="text-sm font-semibold text-slate-600">School Name</label>
                    <p class="text-base text-slate-900 mt-1"><?php echo htmlspecialchars($submission['school_name']); ?></p>
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-600">Submission Date</label>
                    <p class="text-base text-slate-900 mt-1"><?php echo date('F d, Y \a\t h:i A', strtotime($submission['submission_date'])); ?></p>
                </div>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
            <h2 class="text-xl font-bold text-slate-900 mb-6 flex items-center gap-2">
                <span class="material-icons text-blue-600">person</span>
                Contact Information
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="text-sm font-semibold text-slate-600">Contact Person</label>
                    <p class="text-base text-slate-900 mt-1"><?php echo htmlspecialchars($submission['contact_person']); ?></p>
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-600">Email</label>
                    <p class="text-base text-slate-900 mt-1"><?php echo htmlspecialchars($submission['contact_email']); ?></p>
                </div>
                <div>
                    <label class="text-sm font-semibold text-slate-600">Phone</label>
                    <p class="text-base text-slate-900 mt-1"><?php echo htmlspecialchars($submission['contact_phone']); ?></p>
                </div>
            </div>
        </div>

        <!-- Facility Information -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
            <h2 class="text-xl font-bold text-slate-900 mb-6 flex items-center gap-2">
                <span class="material-icons text-blue-600">business</span>
                Facility Details
            </h2>
            <div class="space-y-4">
                <div class="detail-row py-3">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-semibold text-slate-600">Dedicated Building</label>
                            <p class="text-base text-slate-900 mt-1"><?php echo htmlspecialchars($submission['dedicated_building']); ?></p>
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-600">Facility Type</label>
                            <p class="text-base text-slate-900 mt-1"><?php echo htmlspecialchars($submission['facility_type']); ?></p>
                        </div>
                    </div>
                </div>
                <div class="detail-row py-3">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-semibold text-slate-600">Status</label>
                            <p class="text-base text-slate-900 mt-1"><?php echo htmlspecialchars($submission['status']); ?></p>
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-600">Health State</label>
                            <p class="text-base text-slate-900 mt-1"><?php echo htmlspecialchars($submission['health_state']); ?></p>
                        </div>
                    </div>
                </div>
                <div class="detail-row py-3">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="text-sm font-semibold text-slate-600">Floor Area (Sqm)</label>
                            <p class="text-base text-slate-900 mt-1"><?php echo number_format($submission['floor_area'], 2); ?></p>
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-600">Meets Minimum Area</label>
                            <p class="text-base text-slate-900 mt-1"><?php echo htmlspecialchars($submission['meets_min_area']); ?></p>
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-600">Number of Floors</label>
                            <p class="text-base text-slate-900 mt-1"><?php echo $submission['num_floors']; ?></p>
                        </div>
                    </div>
                </div>
                <div class="detail-row py-3">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-semibold text-slate-600">Location</label>
                            <p class="text-base text-slate-900 mt-1"><?php echo htmlspecialchars($submission['location']); ?></p>
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-600">Number of Exits</label>
                            <p class="text-base text-slate-900 mt-1"><?php echo $submission['num_exits']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ICT Equipment -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
            <h2 class="text-xl font-bold text-slate-900 mb-6 flex items-center gap-2">
                <span class="material-icons text-blue-600">computer</span>
                ICT Equipment
            </h2>
            <div class="space-y-4">
                <div class="detail-row py-3">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-semibold text-slate-600">Computer System Type</label>
                            <p class="text-base text-slate-900 mt-1"><?php echo htmlspecialchars($submission['computer_system']); ?></p>
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-600">Number of Computers</label>
                            <p class="text-base text-slate-900 mt-1"><?php echo number_format($submission['num_computers']); ?></p>
                        </div>
                    </div>
                </div>
                <div class="detail-row py-3">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-semibold text-slate-600">Meets Specifications</label>
                            <p class="text-base text-slate-900 mt-1"><?php echo htmlspecialchars($submission['spec_meet']); ?></p>
                        </div>
                        <div>
                            <label class="text-sm font-semibold text-slate-600">Has Networking</label>
                            <p class="text-base text-slate-900 mt-1"><?php echo htmlspecialchars($submission['has_networking']); ?></p>
                        </div>
                    </div>
                </div>
                <div class="detail-row py-3">
                    <label class="text-sm font-semibold text-slate-600">Internet Speed</label>
                    <p class="text-base text-slate-900 mt-1"><?php echo htmlspecialchars($submission['internet_speed']); ?></p>
                </div>
            </div>
        </div>

        <!-- Amenities -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 mb-6">
            <h2 class="text-xl font-bold text-slate-900 mb-6 flex items-center gap-2">
                <span class="material-icons text-blue-600">store</span>
                Amenities & Furniture
            </h2>
            <div class="space-y-4">
                <div class="detail-row py-3">
                    <label class="text-sm font-semibold text-slate-600">Conveniences Available</label>
                    <p class="text-base text-slate-900 mt-1"><?php echo htmlspecialchars($submission['conveniences']) ?: 'None specified'; ?></p>
                </div>
                <div class="detail-row py-3">
                    <label class="text-sm font-semibold text-slate-600">Convenience Attachment</label>
                    <p class="text-base text-slate-900 mt-1"><?php echo htmlspecialchars($submission['convenience_attached']) ?: 'Not specified'; ?></p>
                </div>
                <div class="detail-row py-3">
                    <label class="text-sm font-semibold text-slate-600">Is Furnished</label>
                    <p class="text-base text-slate-900 mt-1"><?php echo htmlspecialchars($submission['is_furnished']); ?></p>
                </div>
                <div class="detail-row py-3">
                    <label class="text-sm font-semibold text-slate-600">Furniture List</label>
                    <p class="text-base text-slate-900 mt-1 whitespace-pre-line"><?php echo htmlspecialchars($submission['furniture_list']) ?: 'None specified'; ?></p>
                </div>
            </div>
        </div>

        <!-- Media Files (Admin Only) -->
        <?php if (isAdmin() && !empty($mediaFiles)): ?>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                <h2 class="text-xl font-bold text-slate-900 mb-6 flex items-center gap-2">
                    <span class="material-icons text-blue-600">perm_media</span>
                    Media Files
                </h2>
                
                <!-- Images -->
                <?php
                $images = array_filter($mediaFiles, function($file) { return $file['file_type'] === 'image'; });
                $videos = array_filter($mediaFiles, function($file) { return $file['file_type'] === 'video'; });
                ?>
                
                <?php if (!empty($images)): ?>
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-slate-800 mb-4">Images (<?php echo count($images); ?>)</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <?php foreach ($images as $image): ?>
                                <div class="border border-slate-200 rounded-lg p-2">
                                    <img src="../<?php echo htmlspecialchars($image['file_path']); ?>" alt="Image" class="w-full h-32 object-cover rounded mb-2">
                                    <a href="../<?php echo htmlspecialchars($image['file_path']); ?>" download class="text-xs text-blue-600 hover:text-blue-700 flex items-center gap-1">
                                        <span class="material-icons" style="font-size: 14px;">download</span>
                                        Download
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Videos -->
                <?php if (!empty($videos)): ?>
                    <div>
                        <h3 class="text-lg font-semibold text-slate-800 mb-4">Videos (<?php echo count($videos); ?>)</h3>
                        <div class="space-y-3">
                            <?php foreach ($videos as $video): ?>
                                <div class="border border-slate-200 rounded-lg p-4 flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <span class="material-icons text-purple-600">videocam</span>
                                        <div>
                                            <p class="text-sm font-medium text-slate-900"><?php echo htmlspecialchars($video['file_name']); ?></p>
                                            <p class="text-xs text-slate-500"><?php echo number_format($video['file_size'] / 1024 / 1024, 2); ?> MB</p>
                                        </div>
                                    </div>
                                    <a href="../<?php echo htmlspecialchars($video['file_path']); ?>" download class="inline-flex items-center gap-1 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-medium transition">
                                        <span class="material-icons" style="font-size: 14px;">download</span>
                                        Download
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>