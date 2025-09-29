<?php
    session_start();
    require 'db.php'; // Assuming db.php handles database connection

    // Redirect if not logged in
    if(!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] == 0) {
        $_SESSION['message'] = "You need to first login to access this page !!!";
        header("Location: Login/error.php");
        exit();
    }

    // Function to sanitize user data.
    function dataFilter($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }

    // --- Product Deletion Logic (AJAX POST) ---
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_pid'])) {
        $delete_pid = dataFilter($_POST['delete_pid']);
        $fid = $_SESSION['id'] ?? null;

        if ($fid === null) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
            exit();
        }

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

    // --- Product Upload Logic (AJAX POST) ---
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['upload_product'])) {
        header('Content-Type: application/json');
        try {
            if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] != true) {
                echo json_encode(['success' => false, 'message' => 'Please log in to upload a product.']);
                exit();
            }

            $productType = dataFilter($_POST['type']);
            $productName = dataFilter($_POST['pname']);
            $productInfo = dataFilter($_POST['pinfo']);
            $productPrice = dataFilter($_POST['price']);
            $fid = $_SESSION['id'];

            if (empty($productName) || empty($productInfo) || empty($productPrice) || empty($productType)) {
                echo json_encode(['success' => false, 'message' => 'All product fields are required.']);
                exit();
            }
            if (!is_numeric($productPrice) || $productPrice < 0) {
                echo json_encode(['success' => false, 'message' => 'Product price must be a non-negative number.']);
                exit();
            }

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

            $pid = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt_insert);

            $imageUploaded = false;
            $picNameNew = '';

            if (isset($_FILES['productPic']) && $_FILES['productPic']['error'] === UPLOAD_ERR_OK) {
                $pic = $_FILES['productPic'];
                $picName = $pic['name'];
                $picTmpName = $pic['tmp_name'];
                $picExt = pathinfo($picName, PATHINFO_EXTENSION);
                $picActualExt = strtolower($picExt);
                $allowed = ['jpg', 'jpeg', 'png', 'jfif'];

                if (in_array($picActualExt, $allowed)) {
                    $picNameNew = "product_" . $pid . "." . $picActualExt;
                    $picDestination = "images/productImages/" . $picNameNew;

                    if (!is_dir('images/productImages/')) {
                        if (!mkdir('images/productImages/', 0777, true)) {
                            echo json_encode(['success' => false, 'message' => "Failed to create image upload directory. Check server permissions."]);
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
                        error_log("Failed to move uploaded file: " . $picTmpName . " to " . $picDestination);
                        echo json_encode(['success' => false, 'message' => "Error moving uploaded image file. Check server logs and directory permissions."]);
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
                $sql_delete_product = "DELETE FROM fproduct WHERE pid = ?";
                $stmt_delete_product = mysqli_prepare($conn, $sql_delete_product);
                if ($stmt_delete_product) {
                    mysqli_stmt_bind_param($stmt_delete_product, "i", $pid);
                    mysqli_stmt_execute($stmt_delete_product);
                    mysqli_stmt_close($stmt_delete_product);
                }
                exit();
            }

            if ($imageUploaded) {
                $sql_update_image = "UPDATE fproduct SET picStatus=1, pimage=? WHERE pid=?";
                $stmt_update_image = mysqli_prepare($conn, $sql_update_image);
                if ($stmt_update_image) {
                    mysqli_stmt_bind_param($stmt_update_image, "si", $picNameNew, $pid);
                    $result_update_image = mysqli_stmt_execute($stmt_update_image);

                    if ($result_update_image) {
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
            error_log("Product upload script error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'An unexpected server error occurred during upload: ' . $e->getMessage()]);
        }
        exit();
    }

    // --- Product Display/Filter Logic (for initial page load) ---
    // Modified SQL query to include SUM of reaction counts from reviews
    $sql_products = "SELECT p.*, 
                            AVG(r.rating) AS avg_rating, 
                            MAX(r.comment) AS latest_comment_snippet, 
                            COUNT(r.rid) AS review_count,
                            SUM(r.love_count) AS total_love_count,
                            SUM(r.wow_count) AS total_wow_count,
                            SUM(r.like_count) AS total_like_count
                     FROM fproduct p
                     LEFT JOIN review r ON p.pid = r.pid";
    $filter_applied = false;
    $pcat_param = '';

    if(isset($_GET['type']) && $_GET['type'] != "all") {
        $pcat_param = dataFilter($_GET['type']);
        $sql_products .= " WHERE p.pcat = ?";
        $filter_applied = true;
    }
    
    $sql_products .= " GROUP BY p.pid ORDER BY p.pid DESC"; // Group by product and order by most recent product

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
    <title>AgroCulture - Digital Market</title>
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
            --star-color: #ffc107; /* Gold for stars */
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

        /* Star Rating Display in Cards */
        .product-card .star-rating-display {
            color: var(--star-color);
            font-size: 1.1rem;
            margin-top: 5px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .product-card .star-rating-display .fa-star,
        .product-card .star-rating-display .fa-star-half-alt,
        .product-card .star-rating-display .far.fa-star {
            margin-right: 2px;
        }
        .product-card .review-summary {
            font-size: 0.9rem;
            color: var(--secondary-color);
            text-align: center;
            margin-top: 5px;
            font-style: italic;
        }
        .product-card .review-count-text {
            font-size: 0.85rem;
            color: #999;
            margin-left: 5px;
        }

        /* Product Reactions Display */
        .product-reactions-display {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px dashed #eee;
        }
        .product-reactions-display .reaction-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 1rem;
            color: var(--secondary-color);
            cursor: pointer; /* Indicate clickability, even if it's just a message */
            transition: color 0.2s ease, transform 0.2s ease;
        }
        .product-reactions-display .reaction-item:hover {
            color: var(--primary-color);
            transform: translateY(-2px);
        }
        .product-reactions-display .reaction-item i {
            font-size: 1.2rem;
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
                        <a class="nav-link active" aria-current="page" href="market.php"><i class="fas fa-home"></i> Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="market.php?type=all"><i class="fas fa-shopping-basket"></i> All Products</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="market.php?type=fruit"><i class="fas fa-apple-alt"></i> Fruits</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="market.php?type=vegetable"><i class="fas fa-carrot"></i> Vegetables</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="market.php?type=grain"><i class="fas fa-wheat-awn"></i> Grains</a>
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
                        <a class="nav-link" href="blogView.php"><i class="fas fa-blog"></i> Blog</a> <!-- New Blog Link -->
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="Login/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                  
                 
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

      

        <!-- Loading Spinner -->
        <div class="loader" id="loader"></div>

        <!-- Filter Section -->
        <div class="mb-4 filter-form">
            <h3 class="mb-3">Filter Products</h3>
            <form method="GET" action="market.php" class="row g-3 align-items-center">
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
                if($result_products && mysqli_num_rows($result_products) > 0) {
                    while($row = mysqli_fetch_assoc($result_products)): // Use mysqli_fetch_assoc for easier access
                        $picDestination = "images/productImages/".htmlspecialchars($row['pimage']);
                        $avg_rating = round($row['avg_rating'] ?? 0, 1); // Get average rating, default to 0, round to 1 decimal
                        $five_star_rating = ($avg_rating / 10) * 5; // Convert 0-10 to 0-5 scale
                        $full_stars = floor($five_star_rating);
                        $half_star = ($five_star_rating - $full_stars) >= 0.5 ? 1 : 0;
                        $empty_stars = 5 - $full_stars - $half_star;
                        $latest_comment = htmlspecialchars($row['latest_comment_snippet'] ?? 'No comments yet.');
                        $review_count = htmlspecialchars($row['review_count'] ?? 0);

                        // Get reaction counts, defaulting to 0 if NULL
                        $total_love_count = htmlspecialchars($row['total_love_count'] ?? 0);
                        $total_wow_count = htmlspecialchars($row['total_wow_count'] ?? 0);
                        $total_like_count = htmlspecialchars($row['total_like_count'] ?? 0);
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
                            
                            <!-- Star Rating Display -->
                            <div class="star-rating-display">
                                <?php for ($i = 0; $i < $full_stars; $i++): ?>
                                    <i class="fas fa-star"></i>
                                <?php endfor; ?>
                                <?php if ($half_star): ?>
                                    <i class="fas fa-star-half-alt"></i>
                                <?php endif; ?>
                                <?php for ($i = 0; $i < $empty_stars; $i++): ?>
                                    <i class="far fa-star"></i>
                                <?php endfor; ?>
                                <span class="review-count-text">(<?php echo $review_count; ?> Reviews)</span>
                            </div>
                            <!-- Latest Comment Snippet -->
                            <p class="review-summary">"<?php echo substr($latest_comment, 0, 70); echo (strlen($latest_comment) > 70) ? '...' : ''; ?>"</p>

                            <!-- Product Reactions Display -->
                            <div class="product-reactions-display">
                                <span class="reaction-item" data-product-id="<?php echo htmlspecialchars($row['pid']); ?>" data-reaction-type="love">
                                    ❤️ <span class="reaction-count"><?php echo $total_love_count; ?></span>
                                </span>
                                <span class="reaction-item" data-product-id="<?php echo htmlspecialchars($row['pid']); ?>" data-reaction-type="wow">
                                    😮 <span class="reaction-count"><?php echo $total_wow_count; ?></span>
                                </span>
                                <span class="reaction-item" data-product-id="<?php echo htmlspecialchars($row['pid']); ?>" data-reaction-type="like">
                                    👍 <span class="reaction-count"><?php echo $total_like_count; ?></span>
                                </span>
                            </div>

                            <span class="price">BDT <?php echo htmlspecialchars($row['price']); ?> /-</span>
                            <div class="mt-auto text-center">
                                <button type="button" class="btn btn-add-to-cart"
                                    data-product-id="<?php echo htmlspecialchars($row['pid']); ?>"
                                    data-product-name="<?php echo htmlspecialchars($row['product']); ?>"
                                    data-product-price="<?php echo htmlspecialchars($row['price']); ?>"
                                    data-product-image="<?php echo htmlspecialchars($row['pimage']); ?>">
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
                if ($filter_applied && isset($stmt_products)) {
                    mysqli_stmt_close($stmt_products);
                }
            ?>
        </div>
         
    </div>



   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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

       

        // --- Main Product Search Functionality ---
        const mainProductSearchInput = document.getElementById('main-product-search-input');
        const productListContainer = document.getElementById('product-list');
        // Re-fetch all product items dynamically as they might change
        let allProductItems = productListContainer.querySelectorAll('.product-item');

        mainProductSearchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            // Update allProductItems in case new products were added
            allProductItems = productListContainer.querySelectorAll('.product-item'); 
            allProductItems.forEach(item => {
                const productName = item.dataset.productName;
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
                    const productImage = this.dataset.productImage; // Get image name

                    const existingItem = cart.find(item => item.id === productId);

                    if (existingItem) {
                        existingItem.quantity++;
                        showTemporaryMessage(`${productName} quantity updated in cart!`, 'info');
                    } else {
                        cart.push({
                            id: productId,
                            name: productName,
                            price: productPrice,
                            pimage: productImage, // Store image name
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
        
        // --- Removed Upload Product Logic ---
        // The following code block, including all related variables and event listeners,
        // has been removed to disable the upload functionality.
        /*
        function showLoader() {
            loader.style.display = 'block';
        }

        function hideLoader() {
            loader.style.display = 'none';
        }

        if (toggleUploadFormBtn && uploadProductBox) {
            toggleUploadFormBtn.addEventListener('click', function() {
                uploadProductBox.classList.toggle('show');
                if (uploadProductBox.classList.contains('show')) {
                    uploadProductBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        }

        if (uploadProductForm) {
            uploadProductForm.addEventListener('submit', function(event) {
                event.preventDefault();

                const formData = new FormData(this);

                showLoader();

                fetch('uploadProduct.php', {
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
                    setTimeout(() => {
                        hideLoader();
                        if (data.success) {
                            showTemporaryMessage(data.message, 'success');
                            uploadProductBox.classList.remove('show');
                            uploadProductForm.reset();

                            if (data.product) {
                                const newProduct = data.product;
                                const picDestination = newProduct.pimage ? `images/productImages/${newProduct.pimage}` : 'https://placehold.co/600x400/E0E0E0/333333?text=No+Image';
                                
                                let fiveStarRating = (newProduct.avg_rating / 10) * 5 || 0;
                                let fullStars = Math.floor(fiveStarRating);
                                let halfStar = (fiveStarRating - fullStars) >= 0.5 ? 1 : 0;
                                let emptyStars = 5 - fullStars - halfStar;
                                let starHtml = '';
                                for (let i = 0; i < fullStars; i++) starHtml += '<i class="fas fa-star"></i>';
                                if (halfStar) starHtml += '<i class="fas fa-star-half-alt"></i>';
                                for (let i = 0; i < emptyStars; i++) starHtml += '<i class="far fa-star"></i>';

                                const newCardHtml = `
                                    <div class="col product-item" id="product-${newProduct.pid}" data-product-name="${newProduct.product.toLowerCase()}">
                                        <div class="card product-card h-100">
                                            <div class="card-header">
                                                <h5 class="card-title">${newProduct.product}</h5>
                                                <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == true): ?>
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
                                                <div class="star-rating-display">
                                                    ${starHtml}
                                                    <span class="review-count-text">(0 Reviews)</span>
                                                </div>
                                                <p class="review-summary">"No comments yet."</p>
                                                <div class="product-reactions-display">
                                                    <span class="reaction-item" data-product-id="${newProduct.pid}" data-reaction-type="love">
                                                        ❤️ <span class="reaction-count">0</span>
                                                    </span>
                                                    <span class="reaction-item" data-product-id="${newProduct.pid}" data-reaction-type="wow">
                                                        😮 <span class="reaction-count">0</span>
                                                    </span>
                                                    <span class="reaction-item" data-product-id="${newProduct.pid}" data-reaction-type="like">
                                                        👍 <span class="reaction-count">0</span>
                                                    </span>
                                                </div>
                                                <span class="price">BDT ${newProduct.price} /-</span>
                                                <div class="mt-auto text-center">
                                                    <button type="button" class="btn btn-add-to-cart"
                                                        data-product-id="${newProduct.pid}"
                                                        data-product-name="${newProduct.product}"
                                                        data-product-price="${newProduct.price}"
                                                        data-product-image="${newProduct.pimage}">
                                                        <i class="fas fa-cart-plus me-2"></i> Add to Cart
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                `;
                                productListContainer.insertAdjacentHTML('afterbegin', newCardHtml);
                                attachDeleteListeners();
                                attachAddToCartListeners();
                                attachProductReactionListeners();
                            }
                        } else {
                            showTemporaryMessage(data.message, 'danger');
                        }
                    }, 500);
                })
                .catch(error => {
                    setTimeout(() => {
                        hideLoader();
                        console.error('Error uploading product:', error);
                        showTemporaryMessage('Network error during product upload. Please try again. Details: ' + error.message, 'danger');
                    }, 500);
                });
            });
        }
        */

        // --- Product Reaction Display Logic (Clicking shows message) ---
        function attachProductReactionListeners() {
            document.querySelectorAll('.product-reactions-display .reaction-item').forEach(item => {
                item.onclick = function() { // Use onclick to avoid duplicate listeners
                    const productId = this.dataset.productId;
                    const reactionType = this.dataset.reactionType;
                    showTemporaryMessage(`To react to this product, please visit its <a href="review.php?pid=${productId}" class="alert-link">product details page</a>.`, 'info');
                };
            });
        }
        attachProductReactionListeners(); // Attach on initial load


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
