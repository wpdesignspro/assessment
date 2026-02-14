<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/utils/functions.php';

// Set upload directory from environment variable or default
$uploadDir = getenv('UPLOAD_DIR') ?: './uploads/';
$uploadDir = rtrim($uploadDir, '/') . '/';

// Create uploads directory if it doesn't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Initialize response array
$response = ['status' => 'error', 'message' => ''];

try {
    // Sanitize and validate inputs
    $schoolName = isset($_POST['school_name']) ? sanitizeInput($_POST['school_name']) : '';
    $contactPerson = isset($_POST['contact_person']) ? sanitizeInput($_POST['contact_person']) : '';
    $contactPhone = isset($_POST['contact_phone']) ? sanitizeInput($_POST['contact_phone']) : '';
    $contactEmail = isset($_POST['contact_email']) ? sanitizeInput($_POST['contact_email']) : '';
    
    // Validate email
    if (!validateEmail($contactEmail)) {
        throw new Exception('Invalid email address');
    }

    // Get other form fields
    $dedicatedBuilding = isset($_POST['dedicated_building']) ? sanitizeInput($_POST['dedicated_building']) : '';
    $facilityType = isset($_POST['facility_type']) ? sanitizeInput($_POST['facility_type']) : '';
    $status = isset($_POST['status']) ? sanitizeInput($_POST['status']) : '';
    $healthState = isset($_POST['health_state']) ? sanitizeInput($_POST['health_state']) : '';
    $floorArea = isset($_POST['floor_area']) ? sanitizeInput($_POST['floor_area']) : '';
    $meetsMinArea = isset($_POST['meets_min_area']) ? sanitizeInput($_POST['meets_min_area']) : '';
    $totalSize = isset($_POST['total_size']) ? sanitizeInput($_POST['total_size']) : '';
    $numFloors = isset($_POST['num_floors']) ? sanitizeInput($_POST['num_floors']) : '';
    $location = isset($_POST['location']) ? sanitizeInput($_POST['location']) : '';
    $computerSystem = isset($_POST['computer_system']) ? sanitizeInput($_POST['computer_system']) : '';
    $numComputers = isset($_POST['num_computers']) ? sanitizeInput($_POST['num_computers']) : '';
    $specMeet = isset($_POST['spec_meet']) ? sanitizeInput($_POST['spec_meet']) : '';
    $hasNetworking = isset($_POST['has_networking']) ? sanitizeInput($_POST['has_networking']) : '';
    $internetSpeed = isset($_POST['internet_speed']) ? sanitizeInput($_POST['internet_speed']) : '';
    $numExits = isset($_POST['num_exits']) ? sanitizeInput($_POST['num_exits']) : '';
    $conveniences = isset($_POST['conveniences']) ? $_POST['conveniences'] : [];
    $convenienceAttached = isset($_POST['convenience_attached']) ? sanitizeInput($_POST['convenience_attached']) : '';
    $isFurnished = isset($_POST['is_furnished']) ? sanitizeInput($_POST['is_furnished']) : '';
    $furnitureList = isset($_POST['furniture_list']) ? sanitizeInput($_POST['furniture_list']) : '';

    // Process convenience checkboxes
    $convenienceStr = is_array($conveniences) ? implode(', ', $conveniences) : '';

    // Handle file uploads
    $imagePaths = [];
    $videoPath = '';
    
    // Process image uploads
    if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $imageFiles = $_FILES['images'];
        $allowedImageTypes = ['jpg', 'jpeg', 'png', 'gif'];
        
        for ($i = 0; $i < count($imageFiles['name']); $i++) {
            $singleFile = [
                'name' => $imageFiles['name'][$i],
                'type' => $imageFiles['type'][$i],
                'tmp_name' => $imageFiles['tmp_name'][$i],
                'error' => $imageFiles['error'][$i],
                'size' => $imageFiles['size'][$i]
            ];
            
            $uploadedPath = handleFileUpload($singleFile, $uploadDir, $allowedImageTypes);
            if ($uploadedPath) {
                $imagePaths[] = $uploadedPath;
            }
        }
    }
    
    // Process video upload
    if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        $allowedVideoTypes = ['mp4', 'mov', 'avi', 'mkv', 'wmv'];
        $videoPath = handleFileUpload($_FILES['video'], $uploadDir, $allowedVideoTypes);
    }

    // Prepare SQL statement
    $stmt = $pdo->prepare("INSERT INTO submissions (
        school_name, contact_person, contact_phone, contact_email,
        dedicated_building, facility_type, status, health_state, floor_area,
        meets_min_area, total_size, num_floors, location, computer_system,
        num_computers, spec_meet, has_networking, internet_speed, num_exits,
        conveniences, convenience_attached, is_furnished, furniture_list,
        image_paths, video_path, submission_date
    ) VALUES (
        ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, NOW()
    )");

    // Convert image paths to JSON string
    $imagePathsJson = json_encode($imagePaths);

    // Execute the statement
    $stmt->execute([
        $schoolName, $contactPerson, $contactPhone, $contactEmail,
        $dedicatedBuilding, $facilityType, $status, $healthState, $floorArea,
        $meetsMinArea, $totalSize, $numFloors, $location, $computerSystem,
        $numComputers, $specMeet, $hasNetworking, $internetSpeed, $numExits,
        $convenienceStr, $convenienceAttached, $isFurnished, $furnitureList,
        $imagePathsJson, $videoPath
    ]);

    $response = ['status' => 'success', 'message' => 'Form submitted successfully!'];
} catch (Exception $e) {
    $response = ['status' => 'error', 'message' => $e->getMessage()];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);