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

    $pid = $_GET['pid'] ?? null; // Get product ID from URL

    // Redirect if PID is not set
    if ($pid === null) {
        // You might want a more user-friendly error page or redirect to product list
        header("Location: uploadProduct.php"); // Redirect to product list if no PID
        exit();
    }

    // --- PHP Logic for Handling Reactions (AJAX POST) ---
    // This block will execute if an AJAX request is sent to this file with action='react'
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'react') {
        header('Content-Type: application/json'); // Respond with JSON
        
        $review_id = dataFilter($_POST['review_id']);
        $reaction_type = dataFilter($_POST['reaction_type']);
        $user_id = $_SESSION['id'] ?? null; // Assuming user ID is in session

        // Basic validation
        if (empty($review_id) || empty($reaction_type) || $user_id === null) {
            echo json_encode(['success' => false, 'message' => 'Invalid request or not logged in.']);
            exit();
        }

        // Validate reaction type to prevent SQL injection for column names
        $allowed_reactions = ['love', 'wow', 'like'];
        if (!in_array($reaction_type, $allowed_reactions)) {
            echo json_encode(['success' => false, 'message' => 'Invalid reaction type.']);
            exit();
        }

        $column_name = $reaction_type . '_count'; // e.g., 'love_count'

        // IMPORTANT: For a more robust system, you would track individual user reactions
        // in a separate table (e.g., `review_reactions` with `review_id`, `user_id`, `reaction_type`)
        // to prevent multiple reactions from the same user to the same review.
        // For this implementation, we are simply incrementing a count on the review table itself.
        // This means a user can click multiple times and it will increment.

        $sql_update_reaction = "UPDATE review SET {$column_name} = {$column_name} + 1 WHERE rid = ?";
        $stmt_update_reaction = mysqli_prepare($conn, $sql_update_reaction);

        if ($stmt_update_reaction) {
            mysqli_stmt_bind_param($stmt_update_reaction, "i", $review_id);
            $success = mysqli_stmt_execute($stmt_update_reaction);

            if ($success) {
                // Fetch the new count to send back to client
                $sql_fetch_count = "SELECT {$column_name} FROM review WHERE rid = ?";
                $stmt_fetch_count = mysqli_prepare($conn, $sql_fetch_count);
                mysqli_stmt_bind_param($stmt_fetch_count, "i", $review_id);
                mysqli_stmt_execute($stmt_fetch_count);
                $result_fetch_count = mysqli_stmt_get_result($stmt_fetch_count);
                $row_count = mysqli_fetch_assoc($result_fetch_count);
                $new_count = $row_count[$column_name];
                mysqli_stmt_close($stmt_fetch_count);

                echo json_encode(['success' => true, 'new_count' => $new_count, 'reaction_type' => $reaction_type, 'review_id' => $review_id]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error updating reaction: ' . mysqli_stmt_error($stmt_update_reaction)]);
            }
            mysqli_stmt_close($stmt_update_reaction);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error preparing reaction update statement.']);
        }
        exit(); // Exit after AJAX response
    }

    // --- Fetch Product and Farmer Details for Display ---
    $product_details = null;
    $farmer_details = null;

    $sql_product="SELECT * FROM fproduct WHERE pid = ?";
    $stmt_product = mysqli_prepare($conn, $sql_product);
    if ($stmt_product) {
        mysqli_stmt_bind_param($stmt_product, "i", $pid);
        mysqli_stmt_execute($stmt_product);
        $result_product = mysqli_stmt_get_result($stmt_product);
        $product_details = mysqli_fetch_assoc($result_product);
        mysqli_stmt_close($stmt_product);
    }

    if ($product_details) {
        $fid = $product_details['fid'];
        $sql_farmer = "SELECT * FROM farmer WHERE fid = ?";
        $stmt_farmer = mysqli_prepare($conn, $sql_farmer);
        if ($stmt_farmer) {
            mysqli_stmt_bind_param($stmt_farmer, "i", $fid);
            mysqli_stmt_execute($stmt_farmer);
            $result_farmer = mysqli_stmt_get_result($stmt_farmer);
            $farmer_details = mysqli_fetch_assoc($result_farmer);
            mysqli_stmt_close($stmt_farmer);
        }
    } else {
        // Product not found, handle error or redirect
        $_SESSION['message'] = "Product not found!";
        header("Location: uploadProduct.php");
        exit();
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AgroCulture: Product Details</title>
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

        /* Sidebar Styling (consistent with other pages) */
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

        /* Product Details Section */
        .product-details-section {
            background-color: var(--card-bg);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border-left: 6px solid var(--primary-color);
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
        }

        .product-details-section .product-image-container {
            flex: 1 1 300px; /* Flex item, can grow/shrink, base width 300px */
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .product-details-section .product-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 10px;
        }

        .product-details-section .product-info {
            flex: 2 1 400px; /* Flex item, can grow/shrink, base width 400px */
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .product-details-section .product-info h1 {
            font-size: 2.8rem;
            font-weight: 800;
            color: var(--primary-dark);
            margin-bottom: 10px;
        }

        .product-details-section .product-info p {
            font-size: 1.1rem;
            color: var(--secondary-color);
            margin-bottom: 8px;
        }

        .product-details-section .product-info .price {
            font-size: 2rem;
            font-weight: 800;
            color: var(--accent-color);
            margin-top: 15px;
            margin-bottom: 20px;
        }

        .product-details-section .product-actions {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .product-details-section .btn-action {
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .product-details-section .btn-add-to-cart {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        .product-details-section .btn-add-to-cart:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
        }

        .product-details-section .btn-buy-now {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            color: white;
        }
        .product-details-section .btn-buy-now:hover {
            background-color: var(--accent-dark);
            border-color: var(--accent-dark);
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
        }

        /* Reviews Section */
        .reviews-section {
            background-color: var(--card-bg);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border-left: 6px solid var(--accent-color);
        }

        .reviews-section h3 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 25px;
            font-size: 1.75rem;
        }

        .review-card {
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            background-color: #f9f9f9;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .reviewer-info {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 1.1rem;
        }

        .review-rating {
            font-weight: 700;
            color: var(--accent-color);
            font-size: 1rem;
        }

        .review-comment {
            color: var(--secondary-color);
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .review-reactions {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #eee;
        }

        .reaction-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 5px;
            color: var(--secondary-color);
            transition: transform 0.2s ease, color 0.2s ease;
        }

        .reaction-btn:hover {
            transform: translateY(-3px);
            color: var(--primary-color);
        }

        .reaction-count {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--secondary-color);
        }

        /* Review Submission Form */
        .review-form-section {
            background-color: var(--card-bg);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border-left: 6px solid var(--primary-color);
        }

        .review-form-section h3 {
            color: var(--primary-color);
            font-weight: 700;
            margin-bottom: 25px;
            font-size: 1.75rem;
        }

        .review-form-section .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 8px;
        }

        .review-form-section .form-control {
            border-radius: 10px;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            background-color: var(--background-light);
        }
        .review-form-section .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
        }

        .review-form-section .btn-submit-review {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            font-weight: 600;
            border-radius: 10px;
            padding: 12px 25px;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .review-form-section .btn-submit-review:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* Star Rating CSS */
        .star-rating {
            display: inline-block;
            font-size: 2rem; /* Larger stars for better interaction */
            color: #ccc; /* Default grey for unselected stars */
            cursor: pointer;
        }
        .star-rating .fa-star {
            margin-right: 5px;
        }
        .star-rating .filled {
            color: #ffc107; /* Gold color for selected stars */
        }
        .star-rating .half-filled {
            position: relative;
            display: inline-block;
            width: 0.5em; /* Half the width of a full star */
            overflow: hidden;
            color: #ffc107; /* Gold for the filled half */
        }
        .star-rating .half-filled::before {
            content: "\f005"; /* Font Awesome star unicode */
            font-family: "Font Awesome 5 Free";
            font-weight: 900; /* Solid icon */
            position: absolute;
            width: 1em; /* Full width of the star */
            overflow: hidden;
            left: 0;
            color: #ffc107;
        }
        .star-rating .empty-half::before {
            content: "\f005";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            position: absolute;
            width: 1em;
            overflow: hidden;
            left: 0;
            color: #ccc; /* Grey for the empty half */
        }


        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            .main-header {
                flex-direction: column;
                align-items: flex-start;
                padding: 15px;
            }
            .main-header h2 {
                margin-bottom: 15px;
                font-size: 1.75rem;
            }
            .product-details-section {
                flex-direction: column;
                gap: 20px;
            }
            .product-details-section .product-image-container {
                flex: none;
                width: 100%;
            }
            .product-details-section .product-info h1 {
                font-size: 2rem;
            }
            .product-details-section .product-info .price {
                font-size: 1.5rem;
            }
            .product-details-section .product-actions {
                flex-direction: column;
                gap: 10px;
            }
            .product-details-section .btn-action {
                width: 100%;
            }
            .reviews-section h3, .review-form-section h3 {
                font-size: 1.5rem;
            }
            .star-rating {
                font-size: 1.5rem; /* Adjust star size for mobile */
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
                        <a class="nav-link" href="uploadProduct.php"><i class="fas fa-home"></i> Home</a>
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
                        <a class="nav-link" href="#"><i class="fas fa-user-circle"></i> Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </li>
                    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == true): ?>
                    <li class="nav-item mt-4">
                        <a href="uploadProduct.php#uploadProductBox" class="btn btn-success w-100 py-3 rounded-pill">
                            <i class="fas fa-cloud-upload-alt me-2"></i> Upload New Product
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content Area -->
    <div class="main-content" id="main-content-area">
        <div class="main-header">
            <h2>Product Details</h2>
        </div>

        <!-- Product Details Section -->
        <div class="product-details-section">
            <div class="product-image-container">
                <?php $picDestination = "images/productImages/" . htmlspecialchars($product_details['pimage']); ?>
                <img src="<?php echo $picDestination; ?>" alt="<?php echo htmlspecialchars($product_details['product']); ?>" onerror="this.onerror=null;this.src='https://placehold.co/600x400/E0E0E0/333333?text=No+Image';">
            </div>
            <div class="product-info">
                <h1><?php echo htmlspecialchars($product_details['product']); ?></h1>
                <p><strong>Product Owner:</strong> <?php echo htmlspecialchars($farmer_details['fname'] ?? 'N/A'); ?></p>
                <p class="price">BDT <?php echo htmlspecialchars($product_details['price']); ?> /-</p>
                <p class="product-description"><?php echo htmlspecialchars($product_details['pinfo']); ?></p>
                
                <div class="product-actions">
                    <button type="button" class="btn btn-action btn-add-to-cart"
                        data-product-id="<?php echo htmlspecialchars($product_details['pid']); ?>"
                        data-product-name="<?php echo htmlspecialchars($product_details['product']); ?>"
                        data-product-price="<?php echo htmlspecialchars($product_details['price']); ?>"
                        data-product-image="<?php echo htmlspecialchars($product_details['pimage']); ?>">
                        <i class="fas fa-cart-plus me-2"></i> Add to Cart
                    </button>
                    <a href="buyNow.php?pid=<?php echo htmlspecialchars($product_details['pid']); ?>" class="btn btn-action btn-buy-now">
                        <i class="fas fa-money-bill-wave me-2"></i> Buy Now
                    </a>
                </div>
            </div>
        </div>

        <!-- Reviews Section -->
        <div class="reviews-section">
            <h3>Product Reviews</h3>
            <div id="reviews-list">
                <?php
                    $sql_reviews = "SELECT * FROM review WHERE pid = ? ORDER BY rid DESC"; // Order by most recent
                    $stmt_reviews = mysqli_prepare($conn, $sql_reviews);
                    if ($stmt_reviews) {
                        mysqli_stmt_bind_param($stmt_reviews, "i", $pid);
                        mysqli_stmt_execute($stmt_reviews);
                        $result_reviews = mysqli_stmt_get_result($stmt_reviews);

                        if (mysqli_num_rows($result_reviews) > 0) {
                            while($review_row = mysqli_fetch_assoc($result_reviews)):
                                // Calculate 5-star rating from 0-10 scale
                                $five_star_rating = ($review_row['rating'] / 10) * 5;
                                $full_stars = floor($five_star_rating);
                                $half_star = ($five_star_rating - $full_stars) >= 0.5 ? 1 : 0;
                                $empty_stars = 5 - $full_stars - $half_star;
                ?>
                                <div class="review-card" id="review-<?php echo htmlspecialchars($review_row['rid']); ?>">
                                    <div class="review-header">
                                        <div class="reviewer-info">
                                            <span><?php echo htmlspecialchars($review_row['name'] ?? 'Anonymous User'); ?></span>
                                        </div>
                                        <div class="review-rating">
                                            <div class="star-rating" data-rating="<?php echo $five_star_rating; ?>">
                                                <?php for ($i = 0; $i < $full_stars; $i++): ?>
                                                    <i class="fas fa-star filled"></i>
                                                <?php endfor; ?>
                                                <?php if ($half_star): ?>
                                                    <i class="fas fa-star-half-alt filled"></i>
                                                <?php endif; ?>
                                                <?php for ($i = 0; $i < $empty_stars; $i++): ?>
                                                    <i class="far fa-star"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <span class="ms-2">(<?php echo htmlspecialchars($review_row['rating']); ?>/10)</span>
                                        </div>
                                    </div>
                                    <p class="review-comment"><?php echo htmlspecialchars($review_row['comment']); ?></p>
                                    <div class="review-reactions">
                                        <button type="button" class="reaction-btn" data-review-id="<?php echo htmlspecialchars($review_row['rid']); ?>" data-reaction-type="love">
                                            ❤️ <span class="reaction-count" id="love-count-<?php echo htmlspecialchars($review_row['rid']); ?>"><?php echo htmlspecialchars($review_row['love_count'] ?? 0); ?></span>
                                        </button>
                                        <button type="button" class="reaction-btn" data-review-id="<?php echo htmlspecialchars($review_row['rid']); ?>" data-reaction-type="wow">
                                            😮 <span class="reaction-count" id="wow-count-<?php echo htmlspecialchars($review_row['rid']); ?>"><?php echo htmlspecialchars($review_row['wow_count'] ?? 0); ?></span>
                                        </button>
                                        <button type="button" class="reaction-btn" data-review-id="<?php echo htmlspecialchars($review_row['rid']); ?>" data-reaction-type="like">
                                            👍 <span class="reaction-count" id="like-count-<?php echo htmlspecialchars($review_row['rid']); ?>"><?php echo htmlspecialchars($review_row['like_count'] ?? 0); ?></span>
                                        </button>
                                    </div>
                                </div>
                <?php
                            endwhile;
                        } else {
                            echo "<p class='text-center text-muted'>No reviews yet. Be the first to review this product!</p>";
                        }
                        mysqli_stmt_close($stmt_reviews);
                    } else {
                        echo "<p class='text-center text-danger'>Error fetching reviews.</p>";
                    }
                ?>
            </div>
        </div>

        <!-- Review Submission Form -->
        <div class="review-form-section">
            <h3>Write a Review</h3>
            <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == true): ?>
                <form method="POST" action="reviewInput.php?pid=<?php echo htmlspecialchars($pid); ?>">
                    <div class="mb-3">
                        <label for="comment" class="form-label">Your Comment</label>
                        <textarea class="form-control" id="comment" name="comment" rows="4" placeholder="Share your thoughts on this product..." required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="rating" class="form-label">Rating</label>
                        <div class="star-rating-input" data-current-rating="0">
                            <i class="far fa-star" data-rating="1"></i>
                            <i class="far fa-star" data-rating="2"></i>
                            <i class="far fa-star" data-rating="3"></i>
                            <i class="far fa-star" data-rating="4"></i>
                            <i class="far fa-star" data-rating="5"></i>
                            <input type="hidden" name="rating" id="hidden-rating-input" value="0">
                        </div>
                        <small class="form-text text-muted">Click a star to rate (1-5 stars).</small>
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-primary btn-lg btn-submit-review">Submit Review</button>
                    </div>
                </form>
            <?php else: ?>
                <p class="text-center text-muted">Please <a href="Login/login.php">log in</a> to write a review.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Your existing scripts (if any) -->
    <!-- Removed old skel/init for a cleaner modern setup -->
    <!-- <script src="js/jquery.min.js"></script> -->
    <!-- <script src="js/skel.min.js"></script> -->
    <!-- <script src="js/skel-layers.min.js"></script> -->
    <!-- <script src="js/init.js"></script> -->

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

            // Event listener for "Add to Cart" button
            const addToCartBtn = document.querySelector('.btn-add-to-cart');
            if (addToCartBtn) {
                addToCartBtn.addEventListener('click', function() {
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
                });
            }

            // Initial cart count update on page load
            updateCartCount();

            // --- Reaction Button Logic ---
            document.querySelectorAll('.reaction-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const reviewId = this.dataset.reviewId;
                    const reactionType = this.dataset.reactionType;
                    const reactionCountSpan = this.querySelector('.reaction-count');

                    // Send AJAX request to update reaction count
                    const formData = new FormData();
                    formData.append('action', 'react');
                    formData.append('review_id', reviewId);
                    formData.append('reaction_type', reactionType);

                    fetch('review.php?pid=<?php echo htmlspecialchars($pid); ?>', { // Post to the same file
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
                        if (data.success) {
                            reactionCountSpan.textContent = data.new_count; // Update count on success
                            showTemporaryMessage(`You reacted with ${reactionType}!`, 'success');
                        } else {
                            showTemporaryMessage(`Error reacting: ${data.message}`, 'danger');
                        }
                    })
                    .catch(error => {
                        console.error('Network error during reaction:', error);
                        showTemporaryMessage('Network error during reaction. Please try again.', 'danger');
                    });
                });
            });

            // --- Star Rating Input Logic ---
            const starRatingInput = document.querySelector('.star-rating-input');
            if (starRatingInput) {
                const stars = starRatingInput.querySelectorAll('.fa-star');
                const hiddenRatingInput = document.getElementById('hidden-rating-input');

                function updateStars(rating) {
                    stars.forEach(star => {
                        if (star.dataset.rating <= rating) {
                            star.classList.remove('far');
                            star.classList.add('fas', 'filled');
                        } else {
                            star.classList.remove('fas', 'filled');
                            star.classList.add('far');
                        }
                    });
                }

                stars.forEach(star => {
                    star.addEventListener('click', function() {
                        const rating = parseInt(this.dataset.rating);
                        // Convert 1-5 star rating to 0-10 scale for database storage
                        hiddenRatingInput.value = rating * 2; 
                        updateStars(rating);
                    });

                    star.addEventListener('mouseover', function() {
                        const rating = parseInt(this.dataset.rating);
                        updateStars(rating);
                    });

                    star.addEventListener('mouseout', function() {
                        const currentRating = parseInt(hiddenRatingInput.value) / 2; // Convert back to 1-5 for display
                        updateStars(currentRating);
                    });
                });

                // Initialize stars on page load based on hidden input value (converted from 0-10 to 1-5)
                updateStars(parseInt(hiddenRatingInput.value) / 2);
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
                    messageContainer.addEventListener('closed.bs.alert', function() {
                        messageContainer.remove();
                    });
                }, 3000);
            }
        });
    </script>
</body>
</html>
