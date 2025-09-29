<?php
    session_start();
    require 'db.php'; // Assuming db.php handles database connection

    // Function to sanitize user data.
    function dataFilter($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }

    // --- Product Deletion Logic ---
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_pid'])) {
        $delete_pid = dataFilter($_POST['delete_pid']);
        $fid = $_SESSION['id'] ?? null; // Use null coalescing operator for safety

        // Ensure user is logged in and authorized to delete
        if ($fid === null) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            exit();
        }

        // Use a prepared statement to prevent SQL injection and ensure ownership
        $sql = "DELETE FROM fproduct WHERE pid = ? AND fid = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "ii", $delete_pid, $fid);
            $success = mysqli_stmt_execute($stmt);

            header('Content-Type: application/json');
            if ($success) {
                echo json_encode(['success' => true, 'message' => 'Product deleted successfully.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error deleting product: ' . mysqli_stmt_error($stmt)]);
            }
            mysqli_stmt_close($stmt);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database error preparing statement.']);
        }
        exit();
    }

    // --- Product Upload Logic ---
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_product'])) {
        header('Content-Type: application/json'); // Always send JSON header first
        try {
            // Ensure user is logged in (and perhaps is a farmer role, if you have roles)
            if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] != true) {
                echo json_encode(['success' => false, 'message' => 'Please log in to upload a product.']);
                exit();
            }

            $productType = dataFilter($_POST['type']);
            $productName = dataFilter($_POST['pname']);
            $productInfo = dataFilter($_POST['pinfo']);
            $productPrice = dataFilter($_POST['price']);
            $fid = $_SESSION['id']; // Farmer ID from session

            // Validate inputs
            if (empty($productName) || empty($productInfo) || empty($productPrice) || empty($productType)) {
                echo json_encode(['success' => false, 'message' => 'All product fields are required.']);
                exit();
            }
            if (!is_numeric($productPrice) || $productPrice < 0) {
                echo json_encode(['success' => false, 'message' => 'Product price must be a non-negative number.']);
                exit();
            }

            // 1. Insert product details first to get a product ID (pid)
            // Ensure picStatus is 0 and pimage is empty initially, will update after image upload
            $sql_insert = "INSERT INTO fproduct (fid, product, pcat, pinfo, price, picStatus, pimage) VALUES (?, ?, ?, ?, ?, 0, '')";
            $stmt_insert = mysqli_prepare($conn, $sql_insert);
            
            if (!$stmt_insert) {
                echo json_encode(['success' => false, 'message' => "Database error preparing product insert statement: " . mysqli_error($conn)]);
                exit();
            }
            mysqli_stmt_bind_param($stmt_insert, "issis", $fid, $productName, $productType, $productInfo, $productPrice);
            $result_insert = mysqli_stmt_execute($stmt_insert);

            if (!$result_insert) {
                echo json_encode(['success' => false, 'message' => "Unable to upload Product details! " . mysqli_stmt_error($stmt_insert)]);
                mysqli_stmt_close($stmt_insert);
                exit();
            }

            $pid = mysqli_insert_id($conn); // Get the newly generated product ID
            mysqli_stmt_close($stmt_insert);

            $imageUploaded = false;
            $picNameNew = '';

            // 2. Handle image upload and update the product record
            if (isset($_FILES['productPic']) && $_FILES['productPic']['error'] === UPLOAD_ERR_OK) {
                $pic = $_FILES['productPic'];
                $picName = $pic['name'];
                $picTmpName = $pic['tmp_name'];
                $picError = $pic['error'];
                $picExt = pathinfo($picName, PATHINFO_EXTENSION); // More robust way to get extension
                $picActualExt = strtolower($picExt);
                $allowed = ['jpg', 'jpeg', 'png', 'jfif'];

                if (in_array($picActualExt, $allowed)) {
                    $picNameNew = "product_" . $pid . "." . $picActualExt; // Unique name using product ID
                    $picDestination = "images/productImages/" . $picNameNew;

                    // Ensure the directory exists and is writable
                    if (!is_dir('images/productImages/')) {
                        // Attempt to create the directory with full permissions
                        if (!mkdir('images/productImages/', 0777, true)) {
                            echo json_encode(['success' => false, 'message' => "Failed to create image upload directory. Check server permissions."]);
                            // Delete the product entry if directory creation fails
                            $sql_delete_product = "DELETE FROM fproduct WHERE pid = ?";
                            $stmt_delete_product = mysqli_prepare($conn, $sql_delete_product);
                            if ($stmt_delete_product) {
                                mysqli_stmt_bind_param($stmt_delete_product, "i", $pid);
                                mysqli_stmt_execute($stmt_delete_product);
                                mysqli_stmt_close($stmt_delete_product);
                            }
                            exit();
                        }
                    }

                    if (move_uploaded_file($picTmpName, $picDestination)) {
                        $imageUploaded = true;
                    } else {
                        // Log error if move_uploaded_file fails
                        error_log("Failed to move uploaded file: " . $picTmpName . " to " . $picDestination);
                        echo json_encode(['success' => false, 'message' => "Error moving uploaded image file. Check server logs and directory permissions."]);
                        // Delete the product entry if image upload fails
                        $sql_delete_product = "DELETE FROM fproduct WHERE pid = ?";
                        $stmt_delete_product = mysqli_prepare($conn, $sql_delete_product);
                        if ($stmt_delete_product) {
                            mysqli_stmt_bind_param($stmt_delete_product, "i", $pid);
                            mysqli_stmt_execute($stmt_delete_product);
                            mysqli_stmt_close($stmt_delete_product);
                        }
                        exit();
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => "You cannot upload files of this type (only JPG, JPEG, PNG, JFIF are allowed)."]);
                    // Delete the product entry if image type is invalid
                    $sql_delete_product = "DELETE FROM fproduct WHERE pid = ?";
                    $stmt_delete_product = mysqli_prepare($conn, $sql_delete_product);
                    if ($stmt_delete_product) {
                        mysqli_stmt_bind_param($stmt_delete_product, "i", $pid);
                        mysqli_stmt_execute($stmt_delete_product);
                        mysqli_stmt_close($stmt_delete_product);
                    }
                    exit();
                }
            } else if ($_FILES['productPic']['error'] !== UPLOAD_ERR_NO_FILE) {
                // Specific error messages for upload issues other than no file
                $phpFileUploadErrors = array(
                    UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
                    UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
                    UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                    UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
                );
                $errorMessage = $phpFileUploadErrors[$_FILES['productPic']['error']] ?? 'Unknown upload error.';
                echo json_encode(['success' => false, 'message' => "Image upload error: " . $errorMessage]);
                // Delete the product entry if image upload fails
                $sql_delete_product = "DELETE FROM fproduct WHERE pid = ?";
                $stmt_delete_product = mysqli_prepare($conn, $sql_delete_product);
                if ($stmt_delete_product) {
                    mysqli_stmt_bind_param($stmt_delete_product, "i", $pid);
                    mysqli_stmt_execute($stmt_delete_product);
                    mysqli_stmt_close($stmt_delete_product);
                }
                exit();
            }

            // Update product record with image info if uploaded
            if ($imageUploaded) {
                $sql_update_image = "UPDATE fproduct SET picStatus=1, pimage=? WHERE pid=?";
                $stmt_update_image = mysqli_prepare($conn, $sql_update_image);
                if ($stmt_update_image) {
                    mysqli_stmt_bind_param($stmt_update_image, "si", $picNameNew, $pid);
                    $result_update_image = mysqli_stmt_execute($stmt_update_image);

                    if ($result_update_image) {
                        // Return the newly uploaded product's data for dynamic addition
                        $sql_fetch_new = "SELECT * FROM fproduct WHERE pid = ?";
                        $stmt_fetch_new = mysqli_prepare($conn, $sql_fetch_new);
                        mysqli_stmt_bind_param($stmt_fetch_new, "i", $pid);
                        mysqli_stmt_execute($stmt_fetch_new);
                        $result_fetch_new = mysqli_stmt_get_result($stmt_fetch_new);
                        $new_product_data = mysqli_fetch_assoc($result_fetch_new);
                        mysqli_stmt_close($stmt_fetch_new);

                        echo json_encode(['success' => true, 'message' => "Product uploaded successfully!", 'product' => $new_product_data]);
                    } else {
                        echo json_encode(['success' => false, 'message' => "Product uploaded, but error updating image link: " . mysqli_stmt_error($stmt_update_image)]);
                    }
                    mysqli_stmt_close($stmt_update_image);
                } else {
                    echo json_encode(['success' => false, 'message' => "Database error preparing image update statement."]);
                }
            } else {
                // If product details were inserted but no image was provided or had no file error
                // Return the newly uploaded product's data even without image
                $sql_fetch_new = "SELECT * FROM fproduct WHERE pid = ?";
                $stmt_fetch_new = mysqli_prepare($conn, $sql_fetch_new);
                mysqli_stmt_bind_param($stmt_fetch_new, "i", $pid);
                mysqli_stmt_execute($stmt_fetch_new);
                $result_fetch_new = mysqli_stmt_get_result($stmt_fetch_new);
                $new_product_data = mysqli_fetch_assoc($result_fetch_new);
                mysqli_stmt_close($stmt_fetch_new);

                echo json_encode(['success' => true, 'message' => "Product details uploaded. No image provided or image upload skipped.", 'product' => $new_product_data]);
            }

        } catch (Exception $e) {
            // Catch any unexpected PHP errors
            error_log("Product upload script error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'An unexpected server error occurred during upload: ' . $e->getMessage()]);
        }
        exit();
    }

    // --- Product Display/Filter Logic (for initial page load) ---
    // This part will now be executed when uploadProduct.php is accessed normally (GET request)
    $sql_products = "SELECT * FROM fproduct"; // Base query
    $filter_applied = false;
    $pcat_param = '';

    if(isset($_GET['type']) && $_GET['type'] != "all") {
        $pcat_param = dataFilter($_GET['type']);
        $sql_products .= " WHERE pcat = ?";
        $filter_applied = true;
    }
    
    if ($filter_applied) {
        $stmt_products = mysqli_prepare($conn, $sql_products);
        mysqli_stmt_bind_param($stmt_products, "s", $pcat_param);
        mysqli_stmt_execute($stmt_products);
        $result_products = mysqli_stmt_get_result($stmt_products);
    } else {
        $result_products = mysqli_query($conn, $sql_products);
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AgroCulture - Online Market</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Custom CSS for complex design and colors -->
    <style>
        :root {
            --primary-color: #388e3c; /* A slightly darker, richer green */
            --primary-dark: #2e7d32; /* Even darker green */
            --secondary-color: #757575; /* Medium Gray */
            --accent-color: #00897b; /* Teal */
            --accent-dark: #00695c; /* Darker Teal */
            --background-light: #e8f5e9; /* Very light green background */
            --text-dark: #212121; /* Darkest Gray */
            --card-bg: #ffffff; /* White */
            --sidebar-bg: #263238; /* Dark Blue-Gray for sidebar */
            --sidebar-text: #eceff1; /* Light text for sidebar */
            --sidebar-hover: #37474f; /* Slightly lighter blue-gray on hover */
            --danger-color: #d32f2f; /* Red for delete */
            --danger-dark: #b71c1c; /* Darker Red */
            --info-color: #1976d2; /* Blue for info/cart */
            --info-dark: #1565c0; /* Darker Blue */
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background-light);
            color: var(--text-dark);
            display: flex; /* Use flexbox for layout */
            min-height: 100vh; /* Ensure full viewport height */
            overflow-x: hidden; /* Prevent horizontal scroll */
        }

        /* Sidebar Styling */
        .sidebar {
            width: 250px;
            background-color: var(--sidebar-bg);
            color: var(--sidebar-text);
            padding: 20px;
            flex-shrink: 0; /* Prevent sidebar from shrinking */
            position: sticky; /* Keep sidebar fixed on scroll */
            top: 0;
            height: 100vh; /* Full height */
            overflow-y: auto; /* Enable scrolling for long content */
            box-shadow: 2px 0 15px rgba(0,0,0,0.3); /* Stronger, deeper shadow */
            z-index: 1000; /* Ensure sidebar is above content */
            transition: transform 0.3s ease-in-out; /* For mobile slide-in/out */
        }

        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -250px; /* Hide off-screen */
                transform: translateX(0);
            }
            .sidebar.show {
                transform: translateX(250px); /* Slide in */
            }
        }

        .sidebar .navbar-brand {
            font-weight: 800;
            font-size: 1.8rem;
            margin-bottom: 30px !important;
            color: var(--primary-color) !important; /* Brand color */
            text-shadow: 1px 1px 3px rgba(0,0,0,0.4);
        }

        .sidebar .nav-link {
            color: var(--sidebar-text);
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 10px;
            transition: background-color 0.3s ease, color 0.3s ease, transform 0.2s ease, box-shadow 0.2s ease;
            display: flex;
            align-items: center;
            font-weight: 500;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: var(--sidebar-hover);
            color: var(--primary-color); /* Highlight active/hovered link */
            transform: translateX(8px); /* More pronounced slide effect on hover */
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        .sidebar .nav-link i {
            margin-right: 15px;
            font-size: 1.2rem;
        }

        /* Main Content Area */
        .main-content {
            flex-grow: 1; /* Allow main content to take remaining space */
            padding: 30px;
            margin-left: 250px; /* Matches sidebar width */
            transition: margin-left 0.3s ease-in-out; /* Smooth transition for responsive margin */
            position: relative; /* For z-index of messages */
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0; 
                padding: 15px; /* Adjust padding for smaller screens */
            }
            .main-content.shifted {
                margin-left: 250px; /* Shift content when sidebar is open */
            }
        }

        /* Header for main content */
        .main-header {
            background-color: #fff;
            padding: 25px 35px;
            margin-bottom: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 6px solid var(--primary-color); /* Accent border */
        }

        .main-header h2 {
            margin: 0;
            color: var(--primary-color);
            font-weight: 800;
            font-size: 2.2rem;
            letter-spacing: -0.5px;
        }

        .main-header .form-control {
            border-radius: 30px;
            border: 1px solid #ddd;
            padding: 10px 20px;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .main-header .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
        }

        .main-header .btn-outline-success {
            border-radius: 30px;
            border-color: var(--primary-color);
            color: var(--primary-color);
            transition: all 0.3s ease;
            font-weight: 600;
            padding: 10px 20px;
        }

        .main-header .btn-outline-success:hover {
            background-color: var(--primary-color);
            color: white;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: -50px;
            left: -50px;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: rotate(45deg);
            z-index: 0;
        }

        .hero-section::after {
            content: '';
            position: absolute;
            bottom: -50px;
            right: -50px;
            width: 150px;
            height: 150px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: rotate(-30deg);
            z-index: 0;
        }

        .hero-section h1 {
            font-weight: 800;
            font-size: 3rem;
            margin-bottom: 10px;
            z-index: 1;
            position: relative;
        }

        .hero-section p {
            font-size: 1.2rem;
            opacity: 0.9;
            z-index: 1;
            position: relative;
        }

        /* Filter form styling */
        .filter-form {
            background-color: var(--card-bg);
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border-left: 4px solid var(--accent-color);
        }

        .filter-form h3 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 20px;
            font-size: 1.75rem;
        }

        .filter-form .form-select {
            background-color: var(--background-light);
            color: var(--text-dark);
            border: 1px solid #ced4da;
            border-radius: 10px;
            padding: 12px 18px;
            transition: all 0.3s ease;
        }

        .filter-form .form-select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(23, 162, 184, 0.25);
        }

        .filter-form .btn-go {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            font-weight: 600;
            border-radius: 10px;
            padding: 12px 25px;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 10px rgba(40, 167, 69, 0.2);
        }

        .filter-form .btn-go:hover {
            background-color: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(40, 167, 69, 0.3);
        }

        /* Product Card Styling */
        .product-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 35px rgba(0,0,0,0.15);
            transition: transform 0.4s cubic-bezier(0.25, 0.8, 0.25, 1), box-shadow 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
            background-color: var(--card-bg);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            transform: translateY(-12px); /* More pronounced lift */
            box-shadow: 0 20px 50px rgba(0,0,0,0.25); /* Stronger shadow on hover */
        }

        .product-card .card-header {
            background: linear-gradient(to right, var(--primary-color), var(--primary-dark)); /* Gradient header */
            color: white;
            padding: 18px 25px;
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
            border-bottom: none;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .product-card .card-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: white; /* Title color for header */
            margin-bottom: 0;
            text-align: center;
            flex-grow: 1; /* Allow title to take space */
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }

        .product-card .card-img-top {
            height: 250px; /* Slightly adjusted height */
            object-fit: cover;
            width: 100%;
            border-radius: 0; /* Remove border-radius here as it's part of the card */
        }

        .product-card .card-body {
            padding: 25px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .product-card .card-text {
            font-size: 1rem;
            color: var(--secondary-color);
            margin-bottom: 12px;
            line-height: 1.6;
            flex-grow: 1; /* Allows text to take available space */
        }

        .product-card .price {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-top: 15px;
            display: block;
            text-align: center;
            letter-spacing: -0.5px;
        }

        .btn-add-to-cart {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            color: white;
            font-weight: 600;
            border-radius: 50px; /* Pill shape */
            padding: 12px 30px;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
            font-size: 1.05rem;
        }

        .btn-add-to-cart:hover {
            background-color: var(--accent-dark);
            border-color: var(--accent-dark);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.25);
        }

        .btn-delete-product {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
            color: white;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: background-color 0.3s ease, transform 0.2s ease, opacity 0.3s ease;
            opacity: 0.9;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .btn-delete-product:hover {
            background-color: var(--danger-dark);
            border-color: var(--danger-dark);
            transform: scale(1.08);
            opacity: 1;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        /* Confirmation Modal Styling */
        .modal-content {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            border: none;
            overflow: hidden; /* For rounded corners on header/footer */
        }
        .modal-header {
            background: linear-gradient(to right, var(--info-color), var(--info-dark));
            color: white;
            padding: 20px;
            border-bottom: none;
            position: relative;
            z-index: 1;
        }
        .modal-header .modal-title {
            font-weight: 700;
            font-size: 1.8rem;
        }
        .modal-header .btn-close {
            filter: invert(1); /* Make close button white */
            opacity: 0.8;
            transition: opacity 0.2s ease;
        }
        .modal-header .btn-close:hover {
            opacity: 1;
        }
        .modal-body {
            padding: 25px;
            background-color: var(--background-light);
            color: var(--text-dark);
        }
        .modal-footer {
            background-color: #f8f9fa;
            border-top: 1px solid #e9ecef;
            padding: 15px 25px;
            border-bottom-left-radius: 15px;
            border-bottom-right-radius: 15px;
        }
        .modal-footer .btn-secondary {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .modal-footer .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #545b62;
            transform: translateY(-1px);
        }
        .modal-footer .btn-danger {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .modal-footer .btn-danger:hover {
            background-color: var(--danger-dark);
            border-color: var(--danger-dark);
            transform: translateY(-1px);
        }
        
        /* Temporary Message (Toast) */
        .alert.fixed-top {
            width: fit-content;
            min-width: 250px;
            max-width: 90%;
            left: 50%;
            transform: translateX(-50%);
            z-index: 2000;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            border-radius: 10px;
            font-weight: 500;
        }

        /* Upload Product Box Specific Styles */
        .upload-product-box {
            background-color: var(--card-bg);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border-left: 6px solid var(--primary-color); /* Accent border */
            display: none; /* Hidden by default */
        }
        .upload-product-box.show {
            display: block; /* Show when toggled */
        }
        .upload-product-box h3 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 25px;
            font-size: 1.75rem;
        }
        .upload-product-box .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 8px;
        }
        .upload-product-box .form-control,
        .upload-product-box .form-select {
            border-radius: 10px;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            background-color: var(--background-light);
        }
        .upload-product-box .form-control:focus,
        .upload-product-box .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
        }
        .upload-product-box .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            font-weight: 600;
            border-radius: 10px;
            padding: 12px 25px;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .upload-product-box .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* Loading Spinner */
        .loader {
            border: 4px solid #f3f3f3; /* Light grey */
            border-top: 4px solid var(--primary-color); /* Green */
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            display: none; /* Hidden by default */
            margin: 20px auto; /* Center it */
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .main-header {
                flex-direction: column;
                align-items: flex-start;
                padding: 15px;
            }
            .main-header h2 {
                margin-bottom: 15px;
                font-size: 1.75rem;
            }
            .main-header .d-flex {
                width: 100%;
            }
            .hero-section h1 {
                font-size: 2rem;
            }
            .hero-section p {
                font-size: 1rem;
            }
            .filter-form h3 {
                font-size: 1.5rem;
            }
            .product-card .card-title {
                font-size: 1.2rem;
            }
            .product-card .card-img-top {
                height: 180px;
            }
            .product-card .price {
                font-size: 1.2rem;
            }
            .btn-add-to-cart {
                padding: 10px 20px;
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <nav class="sidebar navbar navbar-expand-md" id="main-sidebar">
        <div class="container-fluid flex-md-column">
            <a class="navbar-brand text-white mb-3" href="#">AgroCulture</a>
            <button class="navbar-toggler d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarNavbar" aria-controls="sidebarNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon text-white"><i class="fas fa-bars"></i> Menu</span>
            </button>
            <div class="collapse navbar-collapse" id="sidebarNavbar">
                <ul class="navbar-nav flex-column w-100">
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="uploadProduct.php"><i class="fas fa-home"></i> Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="uploadProduct.php?type=all"><i class="fas fa-shopping-basket"></i> All Products</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="uploadProduct.php?type=fruit"><i class="fas fa-apple-alt"></i> Fruits</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="uploadProduct.php?type=vegetable"><i class="fas fa-carrot"></i> Vegetables</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="uploadProduct.php?type=grain"><i class="fas fa-wheat-awn"></i> Grains</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="myCart.php"><i class="fas fa-shopping-cart"></i> My Cart (<span id="cart-item-count">0</span>)</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="fas fa-clipboard-list"></i> My Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profileView.php"><i class="fas fa-user-circle"></i> Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="Login/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </li>
                    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == true): // Assuming only logged-in users can upload ?>
                    <li class="nav-item mt-4">
                        <button type="button" class="btn btn-success w-100 py-3 rounded-pill" id="toggleUploadFormBtn">
                            <i class="fas fa-cloud-upload-alt me-2"></i> Upload New Product
                        </button>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <div class="main-content" id="main-content-area">
        <div class="main-header">
            <h2>Our Products</h2>
            <form class="d-flex" role="search" id="main-product-search-form">
                <input class="form-control me-2" type="search" placeholder="Search products..." aria-label="Search" id="main-product-search-input">
                <button class="btn btn-outline-success" type="submit"><i class="fas fa-search"></i></button>
            </form>
        </div>

        <div class="hero-section">
            <h1>Fresh Produce, Delivered to Your Door!</h1>
            <p>Explore a wide variety of organic fruits, vegetables, and grains directly from local farmers.</p>
        </div>

        <!-- Upload Product Form Box -->
        <div class="upload-product-box" id="uploadProductBox">
            <h3 class="mb-3">Upload New Product</h3>
            <form id="uploadProductForm" action="uploadProduct.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="upload_product" value="1">
                <div class="mb-3">
                    <label for="pname" class="form-label">Product Name</label>
                    <input type="text" class="form-control" id="pname" name="pname" required>
                </div>
                <div class="mb-3">
                    <label for="pinfo" class="form-label">Product Description</label>
                    <textarea class="form-control" id="pinfo" name="pinfo" rows="3" required></textarea>
                </div>
                <div class="mb-3">
                    <label for="price" class="form-label">Price (BDT)</label>
                    <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required>
                </div>
                <div class="mb-3">
                    <label for="type" class="form-label">Category</label>
                    <select class="form-select" id="type" name="type" required>
                        <option value="">Select Category</option>
                        <option value="fruit">Fruit</option>
                        <option value="vegetable">Vegetable</option>
                        <option value="grain">Grain</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="productPic" class="form-label">Product Image</label>
                    <input type="file" class="form-control" id="productPic" name="productPic" accept="image/jpeg, image/png, image/jfif" required>
                    <small class="form-text text-muted">Accepted formats: JPG, JPEG, PNG, JFIF.</small>
                </div>
                <div class="d-grid gap-2 mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">Upload Product</button>
                </div>
            </form>
        </div>

        <!-- Loading Spinner -->
        <div class="loader" id="loader"></div>

        <!-- Filter Section -->
        <div class="mb-4 filter-form">
            <h3 class="mb-3">Filter Products</h3>
            <form method="GET" action="uploadProduct.php" class="row g-3 align-items-center">
                <div class="col-md-4 col-lg-3">
                    <label for="type" class="visually-hidden">Category</label>
                    <select name="type" id="type" class="form-select" required>
                        <option value="all" <?php echo (!isset($_GET['type']) || $_GET['type'] == 'all') ? 'selected' : ''; ?>>List All</option>
                        <option value="fruit" <?php echo (isset($_GET['type']) && $_GET['type'] == 'fruit') ? 'selected' : ''; ?>>Fruit</option>
                        <option value="vegetable" <?php echo (isset($_GET['type']) && $_GET['type'] == 'vegetable') ? 'selected' : ''; ?>>Vegetable</option>
                        <option value="grain" <?php echo (isset($_GET['type']) && $_GET['type'] == 'grain') ? 'selected' : ''; ?>>Grains</option>
                    </select>
                </div>
                <div class="col-md-2 col-lg-1">
                    <button type="submit" class="btn btn-primary btn-go w-100">Go!</button>
                </div>
            </form>
        </div>

        <!-- Product Display Section -->
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" id="product-list">
            <?php
                // PHP logic for fetching and displaying products based on filters
                if($result_products && mysqli_num_rows($result_products) > 0) {
                    while($row = $result_products->fetch_array()):
                        $picDestination = "images/productImages/".htmlspecialchars($row['pimage']);
            ?>
                <div class="col product-item" id="product-<?php echo htmlspecialchars($row['pid']); ?>" data-product-name="<?php echo htmlspecialchars(strtolower($row['product'])); ?>">
                    <div class="card product-card h-100">
                        <div class="card-header">
                            <h5 class="card-title"><?php echo htmlspecialchars($row['product']); ?></h5>
                            <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == true && $_SESSION['id'] == $row['fid']): ?>
                                <button type="button" class="btn btn-sm btn-danger btn-delete-product" data-product-id="<?php echo htmlspecialchars($row['pid']); ?>" data-product-name="<?php echo htmlspecialchars($row['product']); ?>">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                        <a href="review.php?pid=<?php echo htmlspecialchars($row['pid']); ?>" class="text-decoration-none">
                            <img src="<?php echo $picDestination; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($row['product']); ?>" onerror="this.onerror=null;this.src='https://placehold.co/600x400/E0E0E0/333333?text=No+Image';">
                        </a>
                        <div class="card-body d-flex flex-column">
                            <p class="card-text flex-grow-1"><?php echo htmlspecialchars($row['pinfo']); ?></p>
                            <p class="card-text"><strong>Category:</strong> <?php echo htmlspecialchars($row['pcat']); ?></p>
                            <span class="price">BDT <?php echo htmlspecialchars($row['price']); ?> /-</span>
                            <div class="mt-auto text-center">
                                <button type="button" class="btn btn-add-to-cart"
                                    data-product-id="<?php echo htmlspecialchars($row['pid']); ?>"
                                    data-product-name="<?php echo htmlspecialchars($row['product']); ?>"
                                    data-product-price="<?php echo htmlspecialchars($row['price']); ?>">
                                    <i class="fas fa-cart-plus me-2"></i> Add to Cart
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php
                    endwhile;
                } else {
                    echo "<div class='col-12 text-center py-5'><p class='lead'>No products found in this category.</p></div>";
                }
                if ($filter_applied) {
                    mysqli_stmt_close($stmt_products);
                }
            ?>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteConfirmationModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete "<strong id="productToDeleteName"></strong>"? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Your existing scripts (if any) -->
    <!-- Assuming jquery.min.js, skel.min.js, skel-layers.min.js, init.js are for older design/functionality.
         For a fully modern Bootstrap 5 app, these might not be needed or could conflict.
         I'm keeping them as per your original request but modern JS handles most of this. -->
    <script src="js/jquery.min.js"></script>
    <script src="js/skel.min.js"></script>
    <script src="js/skel-layers.min.js"></script>
    <script src="js/init.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Sidebar Toggle for Mobile ---
            const sidebar = document.getElementById('main-sidebar');
            const mainContent = document.getElementById('main-content-area');
            const navbarToggler = document.querySelector('.navbar-toggler');

            navbarToggler.addEventListener('click', function() {
                sidebar.classList.toggle('show');
                mainContent.classList.toggle('shifted');
            });

            // --- Product Deletion Logic ---
            const deleteButtons = document.querySelectorAll('.btn-delete-product');
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmationModal'));
            const productToDeleteNameSpan = document.getElementById('productToDeleteName');
            const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
            let productIdToDelete = null;
            let productCardToDelete = null;

            // Function to attach delete listeners (used for initial load and new products)
            function attachDeleteListeners() {
                document.querySelectorAll('.btn-delete-product').forEach(button => {
                    button.onclick = function() { // Use onclick to avoid duplicate listeners on re-attachment
                        productIdToDelete = this.dataset.productId;
                        const productName = this.dataset.productName;
                        productCardToDelete = this.closest('.col');

                        productToDeleteNameSpan.textContent = productName;
                        deleteModal.show();
                    };
                });
            }
            attachDeleteListeners(); // Attach on initial load

            confirmDeleteBtn.addEventListener('click', function() {
                if (productIdToDelete) {
                    const formData = new FormData();
                    formData.append('delete_pid', productIdToDelete);

                    showLoader(); // Show loader on delete start

                    fetch(window.location.href, { // Send to the same PHP file for deletion
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        // --- TEMPORARY: Added delay for loader visibility ---
                        setTimeout(() => {
                            hideLoader(); // Hide loader on delete end
                            if (data.success) {
                                if (productCardToDelete) {
                                    productCardToDelete.remove();
                                    showTemporaryMessage('Product deleted successfully!', 'success');
                                }
                            } else {
                                console.error('Error deleting product:', data.message);
                                showTemporaryMessage('Error deleting product: ' + data.message, 'danger');
                            }
                            deleteModal.hide();
                        }, 500); // 500ms delay
                    })
                    .catch(error => {
                        // --- TEMPORARY: Added delay for loader visibility ---
                        setTimeout(() => {
                            hideLoader(); // Hide loader on error
                            console.error('Network error during deletion:', error);
                            showTemporaryMessage('Network error during deletion.', 'danger');
                            deleteModal.hide();
                        }, 500); // 500ms delay
                    });
                }
            });

            // --- Main Product Search Functionality ---
            const mainProductSearchInput = document.getElementById('main-product-search-input');
            const productListContainer = document.getElementById('product-list');
            const allProductItems = productListContainer.querySelectorAll('.product-item');

            mainProductSearchInput.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                allProductItems.forEach(item => {
                    const productName = item.dataset.product-name;
                    if (productName.includes(searchTerm)) {
                        item.style.display = ''; // Show item
                    } else {
                        item.style.display = 'none'; // Hide item
                    }
                });
            });

            // Prevent default form submission for main search (since we're doing client-side filtering)
            document.getElementById('main-product-search-form').addEventListener('submit', function(event) {
                event.preventDefault();
            });

            // --- Cart Functionality (using localStorage) ---
            const cartItemCountSpan = document.getElementById('cart-item-count');

            // Initialize cart from localStorage
            let cart = JSON.parse(localStorage.getItem('agro_cart')) || [];

            function updateCartCount() {
                cartItemCountSpan.textContent = cart.length;
            }

            function saveCart() {
                localStorage.setItem('agro_cart', JSON.stringify(cart));
                updateCartCount();
            }

            // Function to attach add to cart listeners (used for initial load and new products)
            function attachAddToCartListeners() {
                document.querySelectorAll('.btn-add-to-cart').forEach(button => {
                    button.onclick = function() { // Use onclick to avoid duplicate listeners on re-attachment
                        const productId = this.dataset.productId;
                        const productName = this.dataset.productName;
                        const productPrice = parseFloat(this.dataset.productPrice);

                        const existingItem = cart.find(item => item.id === productId);

                        if (existingItem) {
                            existingItem.quantity++;
                            showTemporaryMessage(`${productName} quantity updated in cart!`, 'info');
                        } else {
                            cart.push({
                                id: productId,
                                name: productName,
                                price: productPrice,
                                quantity: 1
                            });
                            showTemporaryMessage(`${productName} added to cart!`, 'success');
                        }
                        saveCart();
                    };
                });
            }
            attachAddToCartListeners(); // Attach on initial load

            // Initial cart count update on page load
            updateCartCount();

            // --- Upload Product Form Toggle and Submission (AJAX) ---
            const toggleUploadFormBtn = document.getElementById('toggleUploadFormBtn');
            const uploadProductBox = document.getElementById('uploadProductBox');
            const uploadProductForm = document.getElementById('uploadProductForm');
            const loader = document.getElementById('loader');

            function showLoader() {
                loader.style.display = 'block';
            }

            function hideLoader() {
                loader.style.display = 'none';
            }

            if (toggleUploadFormBtn && uploadProductBox) {
                toggleUploadFormBtn.addEventListener('click', function() {
                    uploadProductBox.classList.toggle('show'); // Toggle visibility
                    // Scroll to the form when it appears
                    if (uploadProductBox.classList.contains('show')) {
                        uploadProductBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            }

            if (uploadProductForm) {
                uploadProductForm.addEventListener('submit', function(event) {
                    event.preventDefault(); // Prevent default form submission

                    const formData = new FormData(this); // 'this' refers to the form element

                    showLoader(); // Show loader on upload start

                    fetch('uploadProduct.php', { // Action points to the same file
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            return response.text().then(text => { throw new Error(text) });
                        }
                        return response.json();
                    })
                    .then(data => {
                        // --- TEMPORARY: Added delay for loader visibility ---
                        setTimeout(() => {
                            hideLoader(); // Hide loader on upload end
                            if (data.success) {
                                showTemporaryMessage(data.message, 'success');
                                uploadProductBox.classList.remove('show'); // Hide the form on success
                                uploadProductForm.reset(); // Clear the form

                                // Dynamically add the new product to the list
                                if (data.product) {
                                    const newProduct = data.product;
                                    const picDestination = newProduct.pimage ? `images/productImages/${newProduct.pimage}` : 'https://placehold.co/600x400/E0E0E0/333333?text=No+Image';
                                    
                                    const newCardHtml = `
                                        <div class="col product-item" id="product-${newProduct.pid}" data-product-name="${newProduct.product.toLowerCase()}">
                                            <div class="card product-card h-100">
                                                <div class="card-header">
                                                    <h5 class="card-title">${newProduct.product}</h5>
                                                    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == true): // Only show delete for logged-in users ?>
                                                    <button type="button" class="btn btn-sm btn-danger btn-delete-product" data-product-id="${newProduct.pid}" data-product-name="${newProduct.product}">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                                <a href="review.php?pid=${newProduct.pid}" class="text-decoration-none">
                                                    <img src="${picDestination}" class="card-img-top" alt="${newProduct.product}" onerror="this.onerror=null;this.src='https://placehold.co/600x400/E0E0E0/333333?text=No+Image';">
                                                </a>
                                                <div class="card-body d-flex flex-column">
                                                    <p class="card-text flex-grow-1">${newProduct.pinfo}</p>
                                                    <p class="card-text"><strong>Category:</strong> ${newProduct.pcat}</p>
                                                    <span class="price">BDT ${newProduct.price} /-</span>
                                                    <div class="mt-auto text-center">
                                                        <button type="button" class="btn btn-add-to-cart"
                                                            data-product-id="${newProduct.pid}"
                                                            data-product-name="${newProduct.product}"
                                                            data-product-price="${newProduct.price}">
                                                            <i class="fas fa-cart-plus me-2"></i> Add to Cart
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                    productListContainer.insertAdjacentHTML('afterbegin', newCardHtml); // Add to the beginning
                                    // Re-attach event listeners to the new card's buttons
                                    attachDeleteListeners();
                                    attachAddToCartListeners();
                                }
                            } else {
                                showTemporaryMessage(data.message, 'danger');
                            }
                        }, 500); // 500ms delay
                    })
                    .catch(error => {
                        // --- TEMPORARY: Added delay for loader visibility ---
                        setTimeout(() => {
                            hideLoader(); // Hide loader on error
                            console.error('Error uploading product:', error);
                            showTemporaryMessage('Network error during product upload. Please try again. Details: ' + error.message, 'danger');
                        }, 500); // 500ms delay
                    });
                });
            }


            // --- General Temporary Message Function ---
            function showTemporaryMessage(message, type) {
                const messageContainer = document.createElement('div');
                messageContainer.className = `alert alert-${type} alert-dismissible fade show fixed-top mx-auto mt-3 text-center w-75 w-md-50 w-lg-25`;
                messageContainer.style.zIndex = '2000';
                messageContainer.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                `;
                document.body.appendChild(messageContainer);

                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(messageContainer);
                    bsAlert.close();
                    // Remove from DOM after transition
                    messageContainer.addEventListener('closed.bs.alert', function() {
                        messageContainer.remove();
                    });
                }, 3000);
            }
        });
    </script>
</body>
</html>
