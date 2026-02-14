<?php
require_once __DIR__ . '/../config/database.php';

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function handleFileUpload($file, $uploadDir, $allowedTypes = []) {
    if ($file['error'] === UPLOAD_ERR_OK) {
        $fileName = basename($file['name']);
        $fileTmpName = $file['tmp_name'];
        $fileSize = $file['size'];
        
        // Check file type if allowed types are specified
        if (!empty($allowedTypes)) {
            $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($fileType, $allowedTypes)) {
                return false;
            }
        }
        
        // Create upload directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $destination = $uploadDir . $fileName;
        
        // Handle duplicate filenames
        $counter = 1;
        $originalDestination = $destination;
        while (file_exists($destination)) {
            $pathInfo = pathinfo($originalDestination);
            $destination = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_' . $counter . '.' . $pathInfo['extension'];
            $counter++;
        }
        
        if (move_uploaded_file($fileTmpName, $destination)) {
            return $destination;
        } else {
            return false;
        }
    }
    return false;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}