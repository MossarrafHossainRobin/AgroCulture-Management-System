<?php
session_start();
require 'db.php';

// Check if the user is logged in
if (!isset($_SESSION['Username'])) {
    header('Location: login.php');
    exit();
}

$username = $_SESSION['Username'];
$farmer = [];
$message = '';
$alert_type = '';

// --- Handle Profile Picture Upload/Removal ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['upload']) || isset($_POST['remove']))) {
    if (isset($_POST['upload']) && isset($_FILES['profilePic']) && $_FILES['profilePic']['error'] == 0) {
        $target_dir = "images/profileImages/";
        $picExt = strtolower(pathinfo($_FILES["profilePic"]["name"], PATHINFO_EXTENSION));
        $target_file = $target_dir . $username . "." . $picExt;
        $uploadOk = 1;

        // Check file size and type
        if ($_FILES["profilePic"]["size"] > 500000) {
            $message = "Sorry, your file is too large.";
            $alert_type = "danger";
            $uploadOk = 0;
        }
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'jfif'];
        if (!in_array($picExt, $allowed_types)) {
            $message = "Sorry, only JPG, JPEG, PNG, GIF & JFIF files are allowed.";
            $alert_type = "danger";
            $uploadOk = 0;
        }

        if ($uploadOk) {
            // Remove old picture if it exists
            if (isset($_SESSION['picExt']) && file_exists($target_dir . $username . "." . $_SESSION['picExt'])) {
                unlink($target_dir . $username . "." . $_SESSION['picExt']);
            }
            // Move new file
            if (move_uploaded_file($_FILES["profilePic"]["tmp_name"], $target_file)) {
                $update_pic_query = "UPDATE farmer SET picExt = ? WHERE fusername = ?";
                $stmt = $conn->prepare($update_pic_query);
                $stmt->bind_param("ss", $picExt, $username);
                $stmt->execute();
                $stmt->close();

                $_SESSION['picExt'] = $picExt;
                $message = "Profile picture updated successfully!";
                $alert_type = "success";
            } else {
                $message = "Sorry, there was an error uploading your file.";
                $alert_type = "danger";
            }
        }
    } elseif (isset($_POST['remove'])) {
        $target_dir = "images/profileImages/";
        if (isset($_SESSION['picExt']) && file_exists($target_dir . $username . "." . $_SESSION['picExt'])) {
            unlink($target_dir . $username . "." . $_SESSION['picExt']);
        }
        $update_pic_query = "UPDATE farmer SET picExt = NULL WHERE fusername = ?";
        $stmt = $conn->prepare($update_pic_query);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->close();
        
        unset($_SESSION['picExt']);
        $message = "Profile picture removed successfully!";
        $alert_type = "success";
    }
}

// --- Fetch Farmer Data ---
$fetch_query = "SELECT * FROM farmer WHERE fusername = ?";
$stmt = $conn->prepare($fetch_query);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $farmer = $result->fetch_assoc();
    // Update session data
    foreach ($farmer as $key => $value) {
        $_SESSION[$key] = $value;
    }
} else {
    session_destroy();
    header('Location: login.php');
    exit();
}
$stmt->close();

// --- Handle Profile Update ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $fname = $_POST['fname'];
    $fmobile = $_POST['fmobile'];
    $femail = $_POST['femail'];
    $faddress = $_POST['faddress'];

    $update_query = "UPDATE farmer SET fname = ?, fmobile = ?, femail = ?, faddress = ? WHERE fusername = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("sssss", $fname, $fmobile, $femail, $faddress, $username);

    if ($stmt->execute()) {
        $message = "Profile updated successfully!";
        $alert_type = "success";
    } else {
        $message = "Error updating profile: " . $conn->error;
        $alert_type = "danger";
    }
    $stmt->close();
    header("Refresh:0");
}

// --- Handle Account Deletion ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_account'])) {
    // Delete profile picture file
    if (isset($_SESSION['picExt'])) {
        $target_dir = "images/profileImages/";
        $picPath = $target_dir . $username . "." . $_SESSION['picExt'];
        if (file_exists($picPath)) {
            unlink($picPath);
        }
    }

    $delete_query = "DELETE FROM farmer WHERE fusername = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("s", $username);

    if ($stmt->execute()) {
        session_destroy();
        header('Location: index.php');
        exit();
    } else {
        $message = "Error deleting account: " . $conn->error;
        $alert_type = "danger";
    }
    $stmt->close();
}
$conn->close();

// Set a default profile image URL
$profile_image_path = 'images/profileImages/' . htmlspecialchars($username) . '.' . ($_SESSION['picExt'] ?? '');
if (!isset($_SESSION['picExt']) || !file_exists($profile_image_path)) {
    $profile_image_url = 'https://via.placeholder.com/150.png?text=No+Image';
} else {
    $profile_image_url = $profile_image_path . '?' . mt_rand();
}
?>

<!DOCTYPE HTML>
<html lang="en">
<head>
    <title>Edit Profile: <?php echo htmlspecialchars($username); ?></title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #e0f2f1, #b2dfdb);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .profile-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 1.5rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 3rem;
            max-width: 900px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .profile-pic-container {
            width: 150px;
            height: 150px;
            margin: 0 auto 1rem;
            border-radius: 50%;
            overflow: hidden;
            border: 5px solid #26a69a;
            box-shadow: 0 0 15px rgba(38, 166, 154, 0.4);
            transition: transform 0.3s ease;
        }
        .profile-pic-container:hover {
            transform: scale(1.05);
        }
        .profile-pic-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .form-control, .btn {
            border-radius: 0.75rem;
        }
        .btn-primary {
            background-color: #00796b;
            border-color: #00796b;
        }
        .btn-primary:hover {
            background-color: #004d40;
            border-color: #004d40;
        }
        .btn-secondary {
            background-color: #757575;
            border-color: #757575;
        }
        .btn-secondary:hover {
            background-color: #494949;
            border-color: #494949;
        }
        .btn-danger {
            background-color: #e53935;
            border-color: #e53935;
        }
        .btn-danger:hover {
            background-color: #c62828;
            border-color: #c62828;
        }
    </style>
</head>
<body>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="profile-card">
                <div class="text-end mb-3">
                    <a href="profileView.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Profile
                    </a>
                </div>
                <div class="profile-header">
                    <div class="profile-pic-container">
                        <img src="<?php echo htmlspecialchars($profile_image_url); ?>" alt="Profile Picture">
                    </div>
                    <h2 class="fw-bold"><?php echo htmlspecialchars($farmer['fname'] ?? ''); ?></h2>
                    <p class="text-muted">@<?php echo htmlspecialchars($farmer['fusername'] ?? ''); ?></p>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $alert_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="profileEdit.php" enctype="multipart/form-data" class="mb-5">
                    <h4 class="mb-3">Update Profile Picture</h4>
                    <div class="input-group mb-3">
                        <input type="file" class="form-control" name="profilePic" id="profilePic">
                        <button class="btn btn-primary" type="submit" name="upload">Upload</button>
                        <button class="btn btn-secondary" type="submit" name="remove">Remove</button>
                    </div>
                </form>

                <hr class="my-5">

                <form method="post" action="profileEdit.php" class="row g-4">
                    <h4 class="mb-3">Edit Personal Details</h4>
                    <input type="hidden" name="update_profile" value="1">
                    <div class="col-md-6">
                        <label for="fname" class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="fname" id="fname" value="<?php echo htmlspecialchars($farmer['fname'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="fmobile" class="form-label">Mobile No</label>
                        <input type="text" class="form-control" name="fmobile" id="fmobile" value="<?php echo htmlspecialchars($farmer['fmobile'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="femail" class="form-label">Email</label>
                        <input type="email" class="form-control" name="femail" id="femail" value="<?php echo htmlspecialchars($farmer['femail'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="faddress" class="form-label">Address</label>
                        <input type="text" class="form-control" name="faddress" id="faddress" value="<?php echo htmlspecialchars($farmer['faddress'] ?? ''); ?>" required>
                    </div>
                    <div class="col-12 text-end">
                        <button type="submit" class="btn btn-success btn-lg">Save Changes</button>
                    </div>
                </form>

                <hr class="my-5">

                <div class="border-top pt-4">
                    <h4 class="text-danger">Danger Zone</h4>
                    <p class="text-muted">Permanently delete your account. This action cannot be undone.</p>
                    <form method="post" action="profileEdit.php" onsubmit="return confirm('Are you sure you want to delete your account? This action is permanent.');">
                        <input type="hidden" name="delete_account" value="1">
                        <button type="submit" class="btn btn-danger">Delete Account</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>