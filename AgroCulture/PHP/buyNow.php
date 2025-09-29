<?php
    session_start();
    require 'db.php'; // Assuming db.php handles database connection

    // Redirect if not logged in
    if(!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] == 0) {
        $_SESSION['message'] = "You need to login to access this page !!!";
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

    // --- Transaction Submission Logic (AJAX POST) ---
    if ($_SERVER['REQUEST_METHOD'] == "POST") {
        header('Content-Type: application/json'); // Respond with JSON

        $name = dataFilter($_POST['name'] ?? '');
        $city = dataFilter($_POST['city'] ?? '');
        $mobile = dataFilter($_POST['mobile'] ?? '');
        $email = dataFilter($_POST['email'] ?? '');
        $pincode = dataFilter($_POST['pincode'] ?? '');
        $addr = dataFilter($_POST['addr'] ?? '');
        $cart_items_json = $_POST['cart_items'] ?? '[]'; // Get cart items as JSON string
        $bid = $_SESSION['id'] ?? null; // Buyer ID from session

        if ($bid === null) {
            echo json_encode(['success' => false, 'message' => 'User not logged in.']);
            exit();
        }

        if (empty($name) || empty($city) || empty($mobile) || empty($email) || empty($pincode) || empty($addr)) {
            echo json_encode(['success' => false, 'message' => 'All delivery information fields are required.']);
            exit();
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
            exit();
        }
        if (!preg_match("/^[0-9]{10,15}$/", $mobile)) { 
            echo json_encode(['success' => false, 'message' => 'Invalid mobile number format.']);
            exit();
        }
        if (!preg_match("/^[0-9]{4,10}$/", $pincode)) {
            echo json_encode(['success' => false, 'message' => 'Invalid pincode format.']);
            exit();
        }

        $cart_items = json_decode($cart_items_json, true); // Decode JSON string to PHP array

        if (empty($cart_items) || !is_array($cart_items)) {
            echo json_encode(['success' => false, 'message' => 'No items found in cart to process.']);
            exit();
        }

        $all_transactions_successful = true;
        // Prepare the insert statement once outside the loop for efficiency
        $stmt_insert = mysqli_prepare($conn, "INSERT INTO transaction (bid, pid, quantity, name, city, mobile, email, pincode, addr) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt_insert) {
            echo json_encode(['success' => false, 'message' => "Database error preparing transaction statement: " . mysqli_error($conn)]);
            exit();
        }

        foreach ($cart_items as $item) {
            $pid = dataFilter($item['id'] ?? '');
            $quantity = dataFilter($item['quantity'] ?? 1);

            // Ensure quantity is an integer and positive
            $quantity = max(1, (int)$quantity);

            // Execute the insert statement for each item
            mysqli_stmt_bind_param($stmt_insert, "iiissssss", $bid, $pid, $quantity, $name, $city, $mobile, $email, $pincode, $addr);
            $success = mysqli_stmt_execute($stmt_insert);

            if (!$success) {
                $all_transactions_successful = false;
                error_log("Failed to insert transaction for PID: {$pid}. Error: " . mysqli_stmt_error($stmt_insert));
                // In a real application, you might want to log this error and continue,
                // or roll back all transactions if any fail. For simplicity, we'll
                // just mark as failed and report a generic error.
            }
        }
        mysqli_stmt_close($stmt_insert);

        if ($all_transactions_successful) {
            echo json_encode(['success' => true, 'message' => "Order Successfully placed! Thanks for shopping with us!!!"]);
        } else {
            echo json_encode(['success' => false, 'message' => "Some orders could not be placed. Please contact support. (Check server logs for details)"]);
        }
        exit();
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>AgroCulture: Confirm Order</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Custom CSS for distinct buy now design -->
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
            --buy-now-bg-pattern: url('data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%23dce7dce0" fill-opacity="0.8"%3E%3Cpath d="M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zm0-30V0H4v4H0v2h4v4h2V6H4z"%3E%3C/path%3E%3C/g%3E%3C/g%3E%3C/svg%3E'); /* Stronger pattern for checkout page */
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background-light);
            background-image: var(--buy-now-bg-pattern); /* Apply the pattern */
            background-repeat: repeat;
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

        /* Buy Now Form Styling */
        .buy-now-container {
            max-width: 700px; /* Limit width for readability */
            margin: 0 auto;
            padding: 20px;
        }

        .transaction-card {
            background-color: var(--card-bg);
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border-left: 6px solid var(--accent-color);
            text-align: center;
        }

        .transaction-card h2 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-dark);
            margin-bottom: 30px;
        }

        .transaction-form .form-label {
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 8px;
            text-align: left;
            display: block;
        }

        .transaction-form .form-control {
            border-radius: 10px;
            padding: 12px 18px;
            border: 1px solid #ced4da;
            background-color: var(--background-light);
            font-size: 1.1rem;
            color: var(--text-dark);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .transaction-form .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
        }

        .btn-confirm-order {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            font-weight: 600;
            border-radius: 50px; /* Pill shape */
            padding: 15px 40px;
            font-size: 1.2rem;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            margin-top: 30px;
        }
        .btn-confirm-order:hover {
            background-color: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }

        /* Product Summary Card */
        .product-summary-card {
            background-color: #f8f9fa; /* Lighter background for summary */
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px; /* Adjusted margin */
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: left; /* Override parent center alignment */
        }

        .product-summary-card img {
            width: 80px; /* Smaller image for list */
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #ddd;
            flex-shrink: 0;
        }

        .product-summary-details {
            flex-grow: 1;
        }

        .product-summary-details h4 {
            margin-bottom: 5px;
            color: var(--primary-dark);
            font-weight: 700;
            font-size: 1.2rem; /* Smaller title for list */
        }

        .product-summary-details p {
            margin-bottom: 3px; /* Smaller margin */
            color: var(--secondary-color);
            font-size: 0.9rem;
        }

        .product-summary-details .price {
            font-size: 1.1rem; /* Smaller price font */
            font-weight: 800;
            color: var(--accent-color);
        }
        .product-summary-details .quantity {
            font-size: 0.95rem;
            color: var(--text-dark);
            font-weight: 500;
        }

        .order-summary-section {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f0f4f0; /* Lighter background for summary */
            border-radius: 10px;
            border: 1px solid #e0e0e0;
            box-shadow: inset 0 1px 5px rgba(0,0,0,0.05);
        }

        .order-summary-section h3 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 20px;
            text-align: left;
        }

        .order-total-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px dashed #ccc;
            margin-top: 15px;
        }

        .order-total-line .label {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .order-total-line .amount {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--primary-color);
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

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            .main-header h2 {
                font-size: 1.75rem;
            }
            .transaction-card {
                padding: 25px;
            }
            .transaction-card h2 {
                font-size: 2rem;
            }
            .transaction-form .form-control {
                font-size: 1rem;
            }
            .btn-confirm-order {
                width: 100%;
                padding: 12px 25px;
                font-size: 1.1rem;
            }
            .product-summary-card {
                flex-direction: column;
                text-align: center;
                align-items: center;
            }
            .order-summary-section h3 {
                font-size: 1.5rem;
            }
            .order-total-line .label {
                font-size: 1.1rem;
            }
            .order-total-line .amount {
                font-size: 1.3rem;
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
                        <a class="nav-link" href="profileView.php"><i class="fas fa-user-circle"></i> Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="blogView.php"><i class="fas fa-blog"></i> Blog</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="Login/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
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
            <h2>Complete Your Order</h2>
        </div>

        <div class="buy-now-container">
            <div class="transaction-card">
                <h2>Confirm Your Purchase</h2>

                <!-- Order Summary Section -->
                <div class="order-summary-section">
                    <h3>Order Summary</h3>
                    <div id="order-items-list">
                        <!-- Cart items will be dynamically loaded here by JavaScript -->
                        <p class="text-center text-muted py-4" id="empty-cart-message">Your cart is empty. Please go back to cart to add items.</p>
                    </div>
                    <div class="order-total-line">
                        <span class="label">Total:</span>
                        <span class="amount" id="order-total-price">BDT 0.00</span>
                    </div>
                </div>

                <!-- Transaction Form -->
                <form method="post" action="buyNow.php" class="transaction-form" id="transactionForm">
                    <h3 class="mb-4 text-start" style="color: var(--primary-dark);">Delivery Information</h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" name="name" id="name" class="form-control" placeholder="Your Name" required/>
                        </div>
                        <div class="col-md-6">
                            <label for="city" class="form-label">City</label>
                            <input type="text" name="city" id="city" class="form-control" placeholder="Your City" required/>
                        </div>
                        <div class="col-md-6">
                            <label for="mobile" class="form-label">Mobile Number</label>
                            <input type="text" name="mobile" id="mobile" class="form-control" placeholder="Mobile Number" required/>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" name="email" id="email" class="form-control" placeholder="Email Address" required/>
                        </div>
                        <div class="col-md-4">
                            <label for="pincode" class="form-label">Pincode</label>
                            <input type="text" name="pincode" id="pincode" class="form-control" placeholder="Pincode" required/>
                        </div>
                        <div class="col-md-8">
                            <label for="addr" class="form-label">Delivery Address</label>
                            <input type="text" name="addr" id="addr" class="form-control" placeholder="Full Delivery Address" required/>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-confirm-order">
                            <i class="fas fa-check-circle me-2"></i> Confirm Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
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

            // --- Function to update cart item count in sidebar ---
            function updateCartCount() {
                const cartItemCountSpan = document.getElementById('cart-item-count');
                let cart = JSON.parse(localStorage.getItem('agro_cart')) || [];
                cartItemCountSpan.textContent = cart.length;
            }

            // Initial call to update cart count on page load
            updateCartCount(); 

            // --- Buy Now Page Specifics: Display Cart Items and Handle Checkout ---
            const orderItemsList = document.getElementById('order-items-list');
            const orderTotalPriceSpan = document.getElementById('order-total-price');
            const emptyCartMessage = document.getElementById('empty-cart-message');
            const transactionForm = document.getElementById('transactionForm');

            function renderOrderSummary() {
                // Ensure 'cart' is always the latest from localStorage for rendering
                let cart = JSON.parse(localStorage.getItem('agro_cart')) || []; 
                orderItemsList.innerHTML = ''; // Clear existing items
                let totalOrderPrice = 0;

                if (cart.length === 0) {
                    emptyCartMessage.style.display = 'block';
                    transactionForm.style.display = 'none'; // Hide form if cart is empty
                } else {
                    emptyCartMessage.style.display = 'none';
                    transactionForm.style.display = 'block'; // Show form if cart has items

                    cart.forEach(item => {
                        const itemElement = document.createElement('div');
                        itemElement.className = 'product-summary-card'; // Reusing style for individual items
                        
                        const picDestination = item.pimage ? `images/productImages/${item.pimage}` : 'https://placehold.co/80x80/E0E0E0/333333?text=Product';

                        itemElement.innerHTML = `
                            <img src="${picDestination}" alt="${item.name}" onerror="this.onerror=null;this.src='https://placehold.co/80x80/E0E0E0/333333?text=No+Image';" />
                            <div class="product-summary-details">
                                <h4>${item.name}</h4>
                                <p class="quantity">Quantity: ${item.quantity}</p>
                                <p class="price">BDT ${(item.price * item.quantity).toFixed(2)}</p>
                            </div>
                        `;
                        orderItemsList.appendChild(itemElement);
                        totalOrderPrice += item.price * item.quantity;
                    });
                }
                orderTotalPriceSpan.textContent = `BDT ${totalOrderPrice.toFixed(2)}`;
            }

            // Initial render of order summary on page load
            renderOrderSummary();

            // --- Form Submission with AJAX for multiple items ---
            if (transactionForm) {
                transactionForm.addEventListener('submit', function(event) {
                    event.preventDefault(); // Prevent default form submission

                    // Re-fetch cart just before submission to ensure it's the latest
                    let cart = JSON.parse(localStorage.getItem('agro_cart')) || []; 

                    if (cart.length === 0) {
                        showTemporaryMessage('Your cart is empty. Cannot place an order.', 'danger');
                        return;
                    }

                    const formData = new FormData(this); // Get form data for delivery details
                    formData.append('cart_items', JSON.stringify(cart)); // Append the entire cart as a JSON string

                    fetch(this.action, { // Submit to the form's action URL (buyNow.php)
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            return response.text().then(text => { throw new Error(text) });
                        }
                        return response.json(); // Parse JSON response
                    })
                    .then(data => {
                        if (data.success) {
                            showTemporaryMessage(data.message, 'success');
                            // Clear cart from localStorage after successful order
                            localStorage.removeItem('agro_cart');
                            // After clearing, update the local cart variable and re-render
                            cart = []; 
                            updateCartCount(); // Update sidebar cart count
                            renderOrderSummary(); // Re-render summary (will show empty cart message)

                            // Optionally redirect after a short delay for user to read message
                            setTimeout(() => {
                                window.location.href = 'uploadProduct.php'; // Redirect to home/market page
                            }, 2000); 
                        } else {
                            showTemporaryMessage(data.message, 'danger');
                        }
                    })
                    .catch(error => {
                        console.error('Error during transaction:', error);
                        showTemporaryMessage('An unexpected error occurred. Please try again. Details: ' + error.message, 'danger');
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
