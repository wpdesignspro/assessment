<?php
/**
 * Form Processing Script - ICT Assessment Portal
 * Handles form submissions with proper error handling
 */

// Prevent any output before JSON
ob_start();

// Error configuration
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set JSON header immediately
header('Content-Type: application/json; charset=utf-8');

// Function to send JSON response and exit
function sendJsonResponse($status, $message, $data = []) {
    ob_end_clean(); // Clear any previous output
    echo json_encode(array_merge([
        'status' => $status,
        'message' => $message
    ], $data));
    exit();
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    sendJsonResponse('error', 'Method not allowed');
}

// Try to load configuration
try {
    $configPath = dirname(__DIR__) . '/config.php';
    if (!file_exists($configPath)) {
        throw new Exception('Configuration file not found at: ' . $configPath);
    }
    require_once $configPath;
} catch (Exception $e) {
    http_response_code(500);
    sendJsonResponse('error', 'Configuration error: ' . $e->getMessage());
}

try {
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Validate required fields
    $requiredFields = [
        'school_name', 'contact_person', 'contact_phone', 'contact_email',
        'dedicated_building', 'facility_type', 'status', 'health_state',
        'floor_area', 'meets_min_area', 'num_floors', 'location',
        'computer_system', 'num_computers', 'spec_meet', 'has_networking',
        'internet_speed', 'num_exits', 'is_furnished'
    ];
    
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            sendJsonResponse('error', "Missing required field: $field");
        }
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Prepare data
    $data = [];
    foreach ($requiredFields as $field) {
        $data[$field] = sanitizeInput($_POST[$field]);
    }
    
    // Optional fields
    $data['total_size'] = isset($_POST['total_size']) && trim($_POST['total_size']) !== '' 
        ? sanitizeInput($_POST['total_size']) 
        : null;
    $data['furniture_list'] = isset($_POST['furniture_list']) 
        ? sanitizeInput($_POST['furniture_list']) 
        : '';
    
    // Handle conveniences checkboxes
    $conveniences = [];
    if (isset($_POST['conveniences']) && is_array($_POST['conveniences'])) {
        foreach ($_POST['conveniences'] as $convenience) {
            $conveniences[] = sanitizeInput($convenience);
        }
    }
    $data['conveniences'] = implode(', ', $conveniences);
    
    // Convenience attached
    $data['convenience_attached'] = isset($_POST['convenience_attached']) 
        ? sanitizeInput($_POST['convenience_attached']) 
        : '';
    
    // Validate email
    if (!filter_var($data['contact_email'], FILTER_VALIDATE_EMAIL)) {
        $db->rollBack();
        sendJsonResponse('error', 'Invalid email address');
    }
    
    // Insert submission
    $sql = "INSERT INTO submissions (
        school_name, contact_person, contact_phone, contact_email,
        dedicated_building, facility_type, status, health_state,
        floor_area, meets_min_area, total_size, num_floors, location,
        computer_system, num_computers, spec_meet, has_networking,
        internet_speed, num_exits, conveniences, convenience_attached,
        is_furnished, furniture_list, ip_address
    ) VALUES (
        :school_name, :contact_person, :contact_phone, :contact_email,
        :dedicated_building, :facility_type, :status, :health_state,
        :floor_area, :meets_min_area, :total_size, :num_floors, :location,
        :computer_system, :num_computers, :spec_meet, :has_networking,
        :internet_speed, :num_exits, :conveniences, :convenience_attached,
        :is_furnished, :furniture_list, :ip_address
    )";
    
    $stmt = $db->prepare($sql);
    $result = $stmt->execute([
        ':school_name' => $data['school_name'],
        ':contact_person' => $data['contact_person'],
        ':contact_phone' => $data['contact_phone'],
        ':contact_email' => $data['contact_email'],
        ':dedicated_building' => $data['dedicated_building'],
        ':facility_type' => $data['facility_type'],
        ':status' => $data['status'],
        ':health_state' => $data['health_state'],
        ':floor_area' => $data['floor_area'],
        ':meets_min_area' => $data['meets_min_area'],
        ':total_size' => $data['total_size'],
        ':num_floors' => $data['num_floors'],
        ':location' => $data['location'],
        ':computer_system' => $data['computer_system'],
        ':num_computers' => $data['num_computers'],
        ':spec_meet' => $data['spec_meet'],
        ':has_networking' => $data['has_networking'],
        ':internet_speed' => $data['internet_speed'],
        ':num_exits' => $data['num_exits'],
        ':conveniences' => $data['conveniences'],
        ':convenience_attached' => $data['convenience_attached'],
        ':is_furnished' => $data['is_furnished'],
        ':furniture_list' => $data['furniture_list'],
        ':ip_address' => $_SERVER['REMOTE_ADDR']
    ]);
    
    if (!$result) {
        $db->rollBack();
        sendJsonResponse('error', 'Failed to save submission');
    }
    
    $submissionId = $db->lastInsertId();
    
    // Handle file uploads
    $uploadedFiles = [];
    $uploadDir = dirname(__DIR__) . '/' . UPLOAD_DIR;
    
    // Ensure upload directories exist
    if (!is_dir($uploadDir . 'images')) {
        mkdir($uploadDir . 'images', 0755, true);
    }
    if (!is_dir($uploadDir . 'videos')) {
        mkdir($uploadDir . 'videos', 0755, true);
    }
    
    // Handle video upload
    if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        $video = $_FILES['video'];
        $allowedTypes = explode(',', ALLOWED_VIDEO_TYPES);
        
        if (in_array($video['type'], $allowedTypes) && $video['size'] <= MAX_VIDEO_SIZE) {
            $extension = strtolower(pathinfo($video['name'], PATHINFO_EXTENSION));
            $fileName = 'video_' . $submissionId . '_' . time() . '.' . $extension;
            $filePath = UPLOAD_DIR . 'videos/' . $fileName;
            $fullPath = $uploadDir . 'videos/' . $fileName;
            
            if (move_uploaded_file($video['tmp_name'], $fullPath)) {
                // Save to database
                $stmt = $db->prepare("INSERT INTO media_uploads (submission_id, file_type, file_name, file_path, file_size, mime_type) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$submissionId, 'video', $fileName, $filePath, $video['size'], $video['type']]);
                
                $uploadedFiles[] = [
                    'type' => 'video',
                    'name' => $fileName,
                    'url' => 'https://' . $_SERVER['HTTP_HOST'] . '/' . $filePath
                ];
            }
        }
    }
    
    // Handle image uploads
    if (isset($_FILES['images']) && is_array($_FILES['images']['name'])) {
        $imageCount = count($_FILES['images']['name']);
        $allowedTypes = explode(',', ALLOWED_IMAGE_TYPES);
        
        for ($i = 0; $i < $imageCount; $i++) {
            if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                $imageType = $_FILES['images']['type'][$i];
                $imageSize = $_FILES['images']['size'][$i];
                $imageTmpName = $_FILES['images']['tmp_name'][$i];
                $imageName = $_FILES['images']['name'][$i];
                
                if (in_array($imageType, $allowedTypes) && $imageSize <= MAX_IMAGE_SIZE) {
                    $extension = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
                    $fileName = 'image_' . $submissionId . '_' . $i . '_' . time() . '.' . $extension;
                    $filePath = UPLOAD_DIR . 'images/' . $fileName;
                    $fullPath = $uploadDir . 'images/' . $fileName;
                    
                    if (move_uploaded_file($imageTmpName, $fullPath)) {
                        // Save to database
                        $stmt = $db->prepare("INSERT INTO media_uploads (submission_id, file_type, file_name, file_path, file_size, mime_type) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$submissionId, 'image', $fileName, $filePath, $imageSize, $imageType]);
                        
                        $uploadedFiles[] = [
                            'type' => 'image',
                            'name' => $fileName,
                            'url' => 'https://' . $_SERVER['HTTP_HOST'] . '/' . $filePath
                        ];
                    }
                }
            }
        }
    }
    
    // Commit transaction
    $db->commit();
    
    // Send email notification
    try {
        sendEmailNotification($data, $uploadedFiles);
    } catch (Exception $e) {
        // Log but don't fail
        error_log("Email notification failed: " . $e->getMessage());
    }
    
    // Success response
    sendJsonResponse('success', 'Form submitted successfully', [
        'submission_id' => $submissionId,
        'files_uploaded' => count($uploadedFiles)
    ]);
    
} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    sendJsonResponse('error', 'Database error occurred');
    
} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Form submission error: " . $e->getMessage());
    http_response_code(500);
    sendJsonResponse('error', $e->getMessage());
}

function sendEmailNotification($data, $uploadedFiles) {
    $to = $data['contact_email'];
    $subject = 'ICT Assessment Submission Received - ' . $data['school_name'];
    
    $message = "Dear " . $data['contact_person'] . ",\n\n";
    $message .= "Thank you for submitting your ICT infrastructure assessment.\n\n";
    $message .= "Submission Details:\n";
    $message .= "School: " . $data['school_name'] . "\n";
    $message .= "Submission Date: " . date('Y-m-d H:i:s') . "\n\n";
    
    if (!empty($uploadedFiles)) {
        $message .= "Uploaded Files:\n";
        foreach ($uploadedFiles as $file) {
            $message .= "- " . $file['type'] . ": " . $file['url'] . "\n";
        }
    }
    
    $message .= "\nYour submission is being reviewed by our team.\n\n";
    $message .= "Best regards,\n";
    $message .= "ICT Assessment Portal Team";
    
    $headers = "From: ICT Assessment Portal <noreply@infraassessment.ng>\r\n";
    $headers .= "Reply-To: noreply@infraassessment.ng\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    @mail($to, $subject, $message, $headers);
}
?>