<?php
    session_start();
    require 'db.php'; // Assuming db.php handles database connection

    // Redirect if not logged in
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] == 0) {
        $_SESSION['message'] = "You need to first login to access this page !!!";
        header("Location: Login/error.php");
        exit(); // Always exit after a header redirect
    }

    $buyer_id = $_SESSION['id']; // Get the current logged-in buyer's ID

    // SQL Query to join transaction and fproduct tables
    // Farmer table join and farmer-specific columns have been removed.
    $sql_orders = "
        SELECT
            t.tid,
            t.pid,
            t.quantity,
            t.name AS buyer_name,
            t.city AS buyer_city,
            t.mobile AS buyer_mobile,
            t.email AS buyer_email,
            t.pincode AS buyer_pincode,
            t.addr AS buyer_address,
            fp.product AS product_name,
            fp.price AS product_price,
            fp.pimage AS product_image,
            fp.pinfo AS product_info
        FROM
            transaction t
        JOIN
            fproduct fp ON t.pid = fp.pid
        WHERE
            t.bid = ?
        ORDER BY
            t.tid DESC, t.pid ASC;
    ";

    $stmt_orders = mysqli_prepare($conn, $sql_orders);
    $orders_data = [];

    if ($stmt_orders) {
        mysqli_stmt_bind_param($stmt_orders, "i", $buyer_id);
        mysqli_stmt_execute($stmt_orders);
        $result_orders = mysqli_stmt_get_result($stmt_orders);

        if ($result_orders) {
            while ($row = mysqli_fetch_assoc($result_orders)) {
                $orders_data[] = $row;
            }
        }
        mysqli_stmt_close($stmt_orders);
    } else {
        // Log the error for debugging, but don't show sensitive info to user
        error_log("Database error preparing orders statement: " . mysqli_error($conn));
        $_SESSION['message'] = "Could not retrieve your orders due to a database error. Please try again later.";
        // Optionally redirect to an error page or display a message on the current page
    }

    // Group orders by transaction ID (tid) if multiple items are part of one transaction
    $grouped_orders = [];
    foreach ($orders_data as $order_item) {
        $tid = $order_item['tid'];
        if (!isset($grouped_orders[$tid])) {
            $grouped_orders[$tid] = [
                'tid' => $tid,
                'buyer_name' => $order_item['buyer_name'],
                'buyer_city' => $order_item['buyer_city'],
                'buyer_mobile' => $order_item['buyer_mobile'],
                'buyer_email' => $order_item['buyer_email'], // Fixed: Removed extra ']'
                'buyer_pincode' => $order_item['buyer_pincode'], // Fixed: Removed extra ']'
                'buyer_address' => $order_item['buyer_address'], // Fixed: Removed extra ']'
                'items' => [],
                'total_transaction_price' => 0
            ];
        }
        $item_total = $order_item['product_price'] * $order_item['quantity'];
        $grouped_orders[$tid]['items'][] = $order_item;
        $grouped_orders[$tid]['total_transaction_price'] += $item_total;
    }

    // Function to format currency
    function formatCurrency($amount) {
        return 'BDT ' . number_format($amount, 2);
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AgroCulture: My Orders</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Custom CSS for consistent design -->
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
            --order-bg-pattern: url('data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%23dce7dce0" fill-opacity="0.5"%3E%3Cpath d="M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zm0-30V0H4v4H0v2h4v4h2V6H4z"%3E%3C/path%3E%3C/g%3E%3C/g%3E%3C/svg%3E');
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background-light);
            background-image: var(--order-bg-pattern);
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

        /* Order Details Specific Styles */
        .orders-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
        }

        .order-card {
            background-color: var(--card-bg);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border-left: 6px solid var(--accent-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .order-card h3 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--accent-dark);
            margin-bottom: 20px;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .order-card h3 i {
            color: var(--accent-color);
        }

        .order-item-list {
            margin-bottom: 20px;
        }

        .order-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px dashed #f0f0f0;
        }
        .order-item:last-child {
            border-bottom: none;
        }

        .order-item-image {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #ddd;
            flex-shrink: 0;
        }

        .order-item-details {
            flex-grow: 1;
        }

        .order-item-details h4 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 5px;
        }

        .order-item-details p {
            font-size: 0.95rem;
            color: var(--secondary-color);
            margin-bottom: 3px;
        }

        .order-item-price-quantity {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .delivery-details-only { /* New class for delivery details only layout */
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            justify-content: center; /* Center the single block */
        }

        .detail-block {
            flex: 1 1 100%; /* Take full width on all screens */
            max-width: 500px; /* Max width for readability */
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            border: 1px solid #eee;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.03);
        }
        .detail-block h5 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 10px;
            border-bottom: 1px dashed #ddd;
            padding-bottom: 5px;
        }
        .detail-block p {
            font-size: 0.9rem;
            margin-bottom: 5px;
            color: var(--text-dark);
        }
        .detail-block p strong {
            color: var(--secondary-color);
            margin-right: 5px;
        }

        .order-total-amount-card {
            background-color: var(--primary-color);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            text-align: right;
            font-size: 1.5rem;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .order-total-amount-card span {
            font-size: 2rem;
            font-weight: 800;
            margin-left: 10px;
        }

        .no-orders-message {
            text-align: center;
            padding: 50px;
            background-color: var(--card-bg);
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            color: var(--secondary-color);
        }
        .no-orders-message i {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 20px;
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
            .order-card {
                padding: 20px;
            }
            .order-card h3 {
                font-size: 1.5rem;
            }
            .order-item {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }
            .delivery-details-only {
                flex-direction: column;
                gap: 20px;
            }
            .detail-block {
                min-width: unset;
                width: 100%;
            }
            .order-total-amount-card {
                font-size: 1.2rem;
            }
            .order-total-amount-card span {
                font-size: 1.5rem;
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
                        <a class="nav-link active" aria-current="page" href="orderdetails.php"><i class="fas fa-clipboard-list"></i> My Orders</a>
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
            <h2>Your Order History</h2>
        </div>

        <div class="orders-container">
            <?php if (!empty($grouped_orders)): ?>
                <?php foreach ($grouped_orders as $order): ?>
                    <div class="order-card">
                        <h3><i class="fas fa-receipt"></i> Order ID: #<?php echo htmlspecialchars($order['tid']); ?></h3>
                        
                        <div class="order-item-list">
                            <?php foreach ($order['items'] as $item): ?>
                                <div class="order-item">
                                    <img src="<?php echo 'images/productImages/'.htmlspecialchars($item['product_image'] ?? 'placeholder.png'); ?>" 
                                         alt="<?php echo htmlspecialchars($item['product_name']); ?>" 
                                         class="order-item-image" 
                                         onerror="this.onerror=null;this.src='https://placehold.co/70x70/E0E0E0/333333?text=Product';" />
                                    <div class="order-item-details">
                                        <h4><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                        <p><?php echo htmlspecialchars($item['product_info']); ?></p>
                                        <p class="order-item-price-quantity">
                                            <?php echo formatCurrency($item['product_price']); ?> x <?php echo htmlspecialchars($item['quantity']); ?> 
                                            = <?php echo formatCurrency($item['product_price'] * $item['quantity']); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="order-total-amount-card">
                            Order Total: <span><?php echo formatCurrency($order['total_transaction_price']); ?></span>
                        </div>

                        <div class="delivery-details-only"> <!-- Changed class for single block -->
                            <div class="detail-block">
                                <h5><i class="fas fa-truck"></i> Delivery Details</h5>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($order['buyer_name']); ?></p>
                                <p><strong>Address:</strong> <?php echo htmlspecialchars($order['buyer_address']); ?>, <?php echo htmlspecialchars($order['buyer_city']); ?> - <?php echo htmlspecialchars($order['buyer_pincode']); ?></p>
                                <p><strong>Mobile:</strong> <?php echo htmlspecialchars($order['buyer_mobile']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($order['buyer_email']); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-orders-message">
                    <i class="fas fa-box-open"></i>
                    <p class="lead">You haven't placed any orders yet.</p>
                    <p>Start shopping now to see your order history here!</p>
                    <a href="uploadProduct.php" class="btn btn-primary mt-3">Browse Products</a>
                </div>
            <?php endif; ?>
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

            // --- Cart Item Count from localStorage (for sidebar) ---
            const cartItemCountSpan = document.getElementById('cart-item-count');
            let cart = JSON.parse(localStorage.getItem('agro_cart')) || [];
            cartItemCountSpan.textContent = cart.length;

            // --- General Temporary Message Function (if needed) ---
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
