<?php
/**
 * KITA Hub Assessment Form Processing Script
 */

ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');

function sendKitaJsonResponse($status, $message, $data = []) {
    ob_end_clean();
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $data));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    sendKitaJsonResponse('error', 'Method not allowed');
}

try {
    $configPath = dirname(__DIR__) . '/config.php';
    if (!file_exists($configPath)) {
        throw new Exception('Configuration file not found');
    }
    require_once $configPath;
} catch (Exception $e) {
    http_response_code(500);
    sendKitaJsonResponse('error', 'Configuration error: ' . $e->getMessage());
}

try {
    $db = Database::getInstance()->getConnection();

    // Create tables if they do not exist yet
    $db->exec("CREATE TABLE IF NOT EXISTS kita_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        school_name VARCHAR(255) NOT NULL,
        location_state VARCHAR(255) NOT NULL,
        kita_position VARCHAR(50) NOT NULL,
        staff_name VARCHAR(255) NOT NULL,
        phone_number VARCHAR(50) NOT NULL,
        has_functional_hub VARCHAR(3) NOT NULL,
        no_hub_reason TEXT,
        can_provide_space VARCHAR(3),
        hub_description TEXT,
        available_items TEXT,
        working_items TEXT,
        faulty_items TEXT,
        items_need_repair TEXT,
        items_need_replacement TEXT,
        hub_condition VARCHAR(20),
        infrastructure_issues TEXT,
        infrastructure_issues_other TEXT,
        damaged_furniture_nos VARCHAR(100),
        urgent_need_1 VARCHAR(500),
        urgent_need_2 VARCHAR(500),
        urgent_need_3 VARCHAR(500),
        immediate_support TEXT,
        recommendations TEXT,
        ip_address VARCHAR(45),
        submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->exec("CREATE TABLE IF NOT EXISTS kita_media_uploads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        submission_id INT NOT NULL,
        category VARCHAR(50) NOT NULL,
        file_type VARCHAR(20) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size BIGINT NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (submission_id) REFERENCES kita_submissions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Validate required fields
    $requiredFields = ['school_name', 'location_state', 'kita_position', 'staff_name', 'phone_number', 'has_functional_hub'];
    foreach ($requiredFields as $field) {
        if (empty(trim($_POST[$field] ?? ''))) {
            sendKitaJsonResponse('error', 'Please fill in all required fields.');
        }
    }

    // Sanitize all inputs
    $school_name           = sanitizeInput($_POST['school_name']);
    $location_state        = sanitizeInput($_POST['location_state']);
    $kita_position         = sanitizeInput($_POST['kita_position']);
    $staff_name            = sanitizeInput($_POST['staff_name']);
    $phone_number          = sanitizeInput($_POST['phone_number']);
    $has_functional_hub    = sanitizeInput($_POST['has_functional_hub']);
    $no_hub_reason         = !empty($_POST['no_hub_reason'])         ? sanitizeInput($_POST['no_hub_reason'])         : null;
    $can_provide_space     = !empty($_POST['can_provide_space'])     ? sanitizeInput($_POST['can_provide_space'])     : null;
    $hub_description       = !empty($_POST['hub_description'])       ? sanitizeInput($_POST['hub_description'])       : null;
    $available_items       = !empty($_POST['available_items'])       ? sanitizeInput($_POST['available_items'])       : null;
    $working_items         = !empty($_POST['working_items'])         ? sanitizeInput($_POST['working_items'])         : null;
    $faulty_items          = !empty($_POST['faulty_items'])          ? sanitizeInput($_POST['faulty_items'])          : null;
    $items_need_repair     = !empty($_POST['items_need_repair'])     ? sanitizeInput($_POST['items_need_repair'])     : null;
    $items_need_replacement= !empty($_POST['items_need_replacement'])? sanitizeInput($_POST['items_need_replacement']): null;
    $hub_condition         = !empty($_POST['hub_condition'])         ? sanitizeInput($_POST['hub_condition'])         : null;

    // Infrastructure issues checkboxes
    $issuesRaw = isset($_POST['infrastructure_issues']) && is_array($_POST['infrastructure_issues'])
        ? array_map('sanitizeInput', $_POST['infrastructure_issues'])
        : [];
    $infrastructure_issues        = !empty($issuesRaw) ? implode(', ', $issuesRaw) : null;
    $infrastructure_issues_other  = !empty($_POST['infrastructure_issues_other'])  ? sanitizeInput($_POST['infrastructure_issues_other'])  : null;
    $damaged_furniture_nos        = !empty($_POST['damaged_furniture_nos'])         ? sanitizeInput($_POST['damaged_furniture_nos'])         : null;

    $urgent_need_1   = !empty($_POST['urgent_need_1'])   ? sanitizeInput($_POST['urgent_need_1'])   : null;
    $urgent_need_2   = !empty($_POST['urgent_need_2'])   ? sanitizeInput($_POST['urgent_need_2'])   : null;
    $urgent_need_3   = !empty($_POST['urgent_need_3'])   ? sanitizeInput($_POST['urgent_need_3'])   : null;
    $immediate_support = !empty($_POST['immediate_support']) ? sanitizeInput($_POST['immediate_support']) : null;
    $recommendations   = !empty($_POST['recommendations'])   ? sanitizeInput($_POST['recommendations'])   : null;
    $ip_address        = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Begin database transaction
    $db->beginTransaction();

    $stmt = $db->prepare("INSERT INTO kita_submissions (
        school_name, location_state, kita_position, staff_name, phone_number,
        has_functional_hub, no_hub_reason, can_provide_space, hub_description,
        available_items, working_items, faulty_items, items_need_repair, items_need_replacement,
        hub_condition, infrastructure_issues, infrastructure_issues_other, damaged_furniture_nos,
        urgent_need_1, urgent_need_2, urgent_need_3, immediate_support, recommendations, ip_address
    ) VALUES (
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?
    )");

    $stmt->execute([
        $school_name, $location_state, $kita_position, $staff_name, $phone_number,
        $has_functional_hub, $no_hub_reason, $can_provide_space, $hub_description,
        $available_items, $working_items, $faulty_items, $items_need_repair, $items_need_replacement,
        $hub_condition, $infrastructure_issues, $infrastructure_issues_other, $damaged_furniture_nos,
        $urgent_need_1, $urgent_need_2, $urgent_need_3, $immediate_support, $recommendations, $ip_address
    ]);

    $submissionId = $db->lastInsertId();
    $filesUploaded = 0;

    // Prepare upload directories
    $kitaUploadBase = dirname(__DIR__) . '/' . UPLOAD_DIR . 'kita/';
    foreach (['images', 'videos'] as $subDir) {
        if (!file_exists($kitaUploadBase . $subDir)) {
            mkdir($kitaUploadBase . $subDir, 0755, true);
        }
    }

    $allowedImageTypes = explode(',', ALLOWED_IMAGE_TYPES);
    $allowedVideoTypes = explode(',', ALLOWED_VIDEO_TYPES);

    // File input definitions: [input_name => [type, category]]
    $fileInputs = [
        'hub_images'          => ['image', 'hub_image'],
        'hub_videos'          => ['video', 'hub_video'],
        'equipment_images'    => ['image', 'equipment_image'],
        'functional_images'   => ['image', 'functional_image'],
        'nonfunctional_images'=> ['image', 'nonfunctional_image'],
    ];

    $counter = 0;
    foreach ($fileInputs as $inputName => [$fileType, $category]) {
        if (!isset($_FILES[$inputName]) || empty($_FILES[$inputName]['name'][0])) {
            continue;
        }

        $files   = $_FILES[$inputName];
        $isMulti = is_array($files['name']);
        $count   = $isMulti ? count($files['name']) : 1;

        for ($i = 0; $i < $count; $i++) {
            $origName = $isMulti ? $files['name'][$i]    : $files['name'];
            $tmpName  = $isMulti ? $files['tmp_name'][$i]: $files['tmp_name'];
            $size     = $isMulti ? $files['size'][$i]    : $files['size'];
            $error    = $isMulti ? $files['error'][$i]   : $files['error'];

            if ($error !== UPLOAD_ERR_OK || empty($origName)) {
                continue;
            }

            $mimeType = mime_content_type($tmpName);
            $allowed  = ($fileType === 'image') ? $allowedImageTypes : $allowedVideoTypes;
            if (!in_array($mimeType, $allowed)) {
                continue;
            }

            $maxSize = ($fileType === 'image') ? MAX_IMAGE_SIZE : MAX_VIDEO_SIZE;
            if ($size > $maxSize) {
                continue;
            }

            $ext       = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $subDir    = ($fileType === 'image') ? 'images' : 'videos';
            $counter++;
            $newName   = $fileType . '_' . $submissionId . '_' . $counter . '_' . time() . '.' . $ext;
            $destPath  = $kitaUploadBase . $subDir . '/' . $newName;
            $relPath   = UPLOAD_DIR . 'kita/' . $subDir . '/' . $newName;

            if (move_uploaded_file($tmpName, $destPath)) {
                $mediaStmt = $db->prepare("INSERT INTO kita_media_uploads
                    (submission_id, category, file_type, file_name, file_path, file_size, mime_type)
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $mediaStmt->execute([$submissionId, $category, $fileType, $newName, $relPath, $size, $mimeType]);
                $filesUploaded++;
            }
        }
    }

    $db->commit();

    sendKitaJsonResponse('success', 'KITA Hub Assessment submitted successfully!', [
        'submission_id' => $submissionId,
        'files_uploaded' => $filesUploaded
    ]);

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("KITA form PDO error: " . $e->getMessage());
    sendKitaJsonResponse('error', 'Database error occurred. Please try again.');
} catch (Exception $e) {
    error_log("KITA form error: " . $e->getMessage());
    sendKitaJsonResponse('error', 'An error occurred. Please try again.');
}
