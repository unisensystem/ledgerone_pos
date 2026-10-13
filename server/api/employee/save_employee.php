<?php
require_once('config.php');

header('Content-Type: application/json');

try {
    $response = ['success' => false, 'message' => ''];
    
    // Handle file upload
    $photoPath = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['photo'];
        
        // Check if it's actually an image
        $check = getimagesize($file['tmp_name']);
        if ($check === false) {
            throw new Exception('File is not an image');
        }
        
        // Check file size (max 5MB)
        if ($file['size'] > 5000000) {
            throw new Exception('File is too large (max 5MB)');
        }
        
        // Allow certain file formats
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception('Only JPG, JPEG, PNG & GIF files are allowed');
        }
        
        // Create unique filename and set upload path
        $fileName = uniqid() . '_' . basename($file['name']);
        $uploadDir = 'uploads/employee_photos/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $photoPath = $uploadDir . $fileName;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $photoPath)) {
            throw new Exception('Failed to upload file');
        }
    }

    $pdo->beginTransaction();

    if (isset($_POST['id']) && !empty($_POST['id'])) {
        // Update existing employee
        $sql = "UPDATE employees SET 
                first_name = ?, 
                last_name = ?, 
                employee_id = ?, 
                department_id = ?, 
                position = ?, 
                email = ?, 
                phone = ?, 
                barcode = ?" .
                ($photoPath ? ", photo_path = ?" : "") .
                " WHERE id = ?";
        
        $params = [
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['employee_id'],
            $_POST['department_id'],
            $_POST['position'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['barcode'] ?: $_POST['employee_id']
        ];
        
        if ($photoPath) {
            // Delete old photo if exists
            $stmt = $pdo->prepare("SELECT photo_path FROM employees WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $oldPhoto = $stmt->fetchColumn();
            if ($oldPhoto && file_exists($oldPhoto) && strpos($oldPhoto, 'uploads/employee_photos/') === 0) {
                unlink($oldPhoto);
            }
            
            $params[] = $photoPath;
        }
        
        $params[] = $_POST['id'];
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $response['message'] = 'Employee updated successfully';
    } else {
        // Insert new employee
        $sql = "INSERT INTO employees (
                first_name, last_name, employee_id, department_id, 
                position, email, phone, barcode" .
                ($photoPath ? ", photo_path" : "") .
                ") VALUES (?, ?, ?, ?, ?, ?, ?, ?" .
                ($photoPath ? ", ?" : "") .
                ")";
        
        $params = [
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['employee_id'],
            $_POST['department_id'],
            $_POST['position'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['barcode'] ?: $_POST['employee_id']
        ];
        
        if ($photoPath) {
            $params[] = $photoPath;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $response['message'] = 'Employee added successfully';
    }

    $pdo->commit();
    $response['success'] = true;

} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    
    // Delete uploaded file if there was an error
    if (isset($photoPath) && file_exists($photoPath)) {
        unlink($photoPath);
    }
}

echo json_encode($response);
