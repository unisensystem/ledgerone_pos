<?php
session_start();
require_once('config.php');
require_once('auth.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $employee_id = $_POST['employee_id'];
    $file = $_FILES['photo'];
    
    // Check if it's an actual image
    $check = getimagesize($file["tmp_name"]);
    if ($check === false) {
        echo json_encode(['success' => false, 'message' => 'File is not an image']);
        exit;
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = 'uploads/employee_photos/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $employee_id . '_' . uniqid() . '.' . $extension;
    $target_file = $upload_dir . $filename;
    
    // Upload file
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        // Update database
        $sql = "UPDATE employees SET photo_path = :photo_path WHERE employee_id = :employee_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'photo_path' => $target_file,
            'employee_id' => $employee_id
        ]);
        
        echo json_encode(['success' => true, 'photo_path' => $target_file]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
    }
    exit;
}
?>

<form id="photoUploadForm" class="photo-upload-form">
    <div class="preview-container">
        <img id="photoPreview" src="assets/default-avatar.png" alt="Photo Preview">
    </div>
    <div class="form-group">
        <label for="photo">Select Photo</label>
        <input type="file" class="form-control" id="photo" name="photo" accept="image/*" required>
    </div>
    <input type="hidden" name="employee_id" value="<?php echo $_GET['employee_id']; ?>">
    <button type="submit" class="btn btn-primary">Upload Photo</button>
</form>

<style>
.photo-upload-form {
    max-width: 300px;
    margin: 20px auto;
    text-align: center;
}

.preview-container {
    width: 200px;
    height: 200px;
    margin: 0 auto 20px;
    border-radius: 50%;
    overflow: hidden;
    border: 3px solid #eee;
}

#photoPreview {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
</style>

<script>
$(document).ready(function() {
    // Preview image before upload
    $('#photo').change(function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#photoPreview').attr('src', e.target.result);
            }
            reader.readAsDataURL(file);
        }
    });
    
    // Handle form submission
    $('#photoUploadForm').submit(function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        $.ajax({
            url: 'upload_photo.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                const result = JSON.parse(response);
                if (result.success) {
                    alert('Photo uploaded successfully!');
                    window.location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            },
            error: function() {
                alert('An error occurred while uploading the photo.');
            }
        });
    });
});
