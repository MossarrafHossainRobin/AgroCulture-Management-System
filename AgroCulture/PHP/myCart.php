<?php
    session_start();
    require 'db.php'; // Assuming db.php handles database connection

    // Redirect if not logged in
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] == 0) {
        $_SESSION['message'] = "You need to first login to access this page !!!";
        header("Location: Login/error.php");
        exit(); // Always exit after a header redirect
    }

    // Function to sanitize user data (still useful for any future server-side interactions)
    function dataFilter($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AgroCulture: My Cart</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

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
            position: relative;
            padding-bottom: 70px; /* Space for the fixed footer */
        }

        /* Sidebar Styling (consistent with other pages) */
        .sidebar {
            width: 250px;
            background-color: var(--sidebar-bg);
            color: var(--sidebar-text);
            padding: 20px;
            flex-shrink: 0; /* Prevent sidebar from shrinking */
            position: fixed; /* Keep sidebar fixed */
            top: 0;
            left: 0;
            height: 100vh; /* Full height */
            overflow-y: auto; /* Enable scrolling for long content */
            box-shadow: 2px 0 15px rgba(0,0,0,0.3); /* Stronger, deeper shadow */
            z-index: 1000; /* Ensure sidebar is above content */
            transition: transform 0.3s ease-in-out; /* For mobile slide-in/out */
        }

        @media (max-width: 768px) {
            .sidebar {
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
        .main-container {
            flex-grow: 1; /* Allow main content to take remaining space */
            margin-left: 250px; /* Matches sidebar width */
            transition: margin-left 0.3s ease-in-out; /* Smooth transition for responsive margin */
            position: relative; /* For z-index of messages */
        }

        @media (max-width: 768px) {
            .main-container {
                margin-left: 0; 
                padding-top: 15px; /* Adjust padding for smaller screens */
            }
            .main-container.shifted {
                margin-left: 250px; /* Shift content when sidebar is open */
            }
        }
        
        .main-content {
            padding: 30px;
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

        /* Cart Specific Styles */
        .cart-container {
            background-color: var(--card-bg);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border-left: 6px solid var(--primary-color);
        }

        .cart-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
            gap: 15px;
        }
        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .cart-item-details {
            flex-grow: 1;
        }

        .cart-item-name {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .cart-item-price {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1rem;
        }

        .cart-item-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quantity-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.1rem;
            transition: background-color 0.2s ease;
        }
        .quantity-btn:hover {
            background-color: var(--primary-dark);
        }

        .item-quantity {
            width: 40px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 5px;
            font-weight: 500;
        }

        .btn-remove-item {
            background-color: var(--danger-color);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 8px 12px;
            font-size: 0.9rem;
            transition: background-color 0.2s ease;
        }
        .btn-remove-item:hover {
            background-color: var(--danger-dark);
        }

        .cart-summary {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px dashed #e0e0e0;
            text-align: right;
        }

        .cart-total-label {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .cart-total-amount {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary-dark);
            margin-left: 15px;
        }

        .cart-actions {
            margin-top: 25px;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }

        .btn-clear-cart {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            color: white;
            font-weight: 600;
            border-radius: 8px;
            padding: 12px 25px;
            transition: background-color 0.2s ease;
        }
        .btn-clear-cart:hover {
            background-color: #5a6268;
        }

        .btn-checkout {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            color: white;
            font-weight: 600;
            border-radius: 8px;
            padding: 12px 25px;
            transition: background-color 0.2s ease;
        }
        .btn-checkout:hover {
            background-color: var(--accent-dark);
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

        /* Footer Styling */
        .footer {
            background: #263238; /* Matches sidebar background */
            color: #b0bec5; /* Light gray text */
            text-align: center;
            padding: 20px;
            width: 100%;
            position: fixed;
            bottom: 0;
            z-index: 999;
            margin-left: 250px; /* Match main content margin */
            transition: margin-left 0.3s ease-in-out;
        }
        @media (max-width: 768px) {
            .footer {
                margin-left: 0;
            }
        }

        /* Responsive adjustments for mobile */
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
            .cart-item {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }
            .cart-item-controls {
                width: 100%;
                justify-content: space-between;
                margin-top: 10px;
            }
            .cart-actions {
                flex-direction: column;
                align-items: stretch;
            }
            .cart-total-amount {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>

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
                        <a class="nav-link" href="market.php"><i class="fas fa-store"></i> Market</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="myCart.php"><i class="fas fa-shopping-cart"></i> My Cart (<span id="cart-item-count">0</span>)</a>
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
                </ul>
            </div>
        </div>
    </nav>

    <div class="main-container" id="main-content-area">
        <div class="main-content">
            <div class="main-header">
                <h2>My Shopping Cart</h2>
            </div>

            <div class="cart-container">
                <h3>Items in your Cart</h3>
                <div id="cart-items-list">
                    <p class="text-center text-muted py-4" id="empty-cart-message">Your cart is empty.</p>
                </div>

                <div class="cart-summary">
                    <span class="cart-total-label">Total:</span>
                    <span class="cart-total-amount" id="cart-total-price">BDT 0.00</span>
                </div>

                <div class="cart-actions">
                    <button type="button" class="btn btn-clear-cart" id="clear-cart-btn">
                        <i class="fas fa-trash-alt me-2"></i> Clear Cart
                    </button>
                    <button type="button" class="btn btn-checkout" id="checkout-btn">
                        <i class="fas fa-money-check-alt me-2"></i> Proceed to Checkout
                    </button>
                </div>
            </div>
        </div>
        
        <footer class="footer" id="main-footer">
            <div class="container text-center">
                <small>© 2025 AgroCulture Ltd. All rights reserved.</small>
            </div>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Sidebar Toggle for Mobile ---
            const sidebar = document.getElementById('main-sidebar');
            const mainContainer = document.getElementById('main-content-area');
            const navbarToggler = document.querySelector('.navbar-toggler');
            const footer = document.getElementById('main-footer');

            navbarToggler.addEventListener('click', function() {
                sidebar.classList.toggle('show');
                mainContainer.classList.toggle('shifted');
                footer.classList.toggle('shifted'); // Shift the footer as well
            });

            // --- Cart Functionality (using localStorage) ---
            const cartItemCountSpan = document.getElementById('cart-item-count');
            const cartItemsList = document.getElementById('cart-items-list');
            const cartTotalPriceSpan = document.getElementById('cart-total-price');
            const emptyCartMessage = document.getElementById('empty-cart-message');
            const clearCartBtn = document.getElementById('clear-cart-btn');
            const checkoutBtn = document.getElementById('checkout-btn');

            let cart = JSON.parse(localStorage.getItem('agro_cart')) || [];

            function updateCartCount() {
                cartItemCountSpan.textContent = cart.length;
            }

            function saveCart() {
                localStorage.setItem('agro_cart', JSON.stringify(cart));
                updateCartCount();
                renderCartItems(); // Re-render cart after saving
            }

            function calculateCartTotal() {
                let total = 0;
                cart.forEach(item => {
                    total += item.price * item.quantity;
                });
                cartTotalPriceSpan.textContent = `BDT ${total.toFixed(2)}`;
            }

            function renderCartItems() {
                cartItemsList.innerHTML = ''; // Clear existing items

                if (cart.length === 0) {
                    emptyCartMessage.style.display = 'block';
                    clearCartBtn.disabled = true;
                    checkoutBtn.disabled = true;
                } else {
                    emptyCartMessage.style.display = 'none';
                    clearCartBtn.disabled = false;
                    checkoutBtn.disabled = false;

                    cart.forEach(item => {
                        const itemElement = document.createElement('div');
                        itemElement.className = 'cart-item';
                        itemElement.dataset.productId = item.id; // Store product ID for easy access

                        // Placeholder image for now, replace with actual image if available
                        const picDestination = item.pimage ? `images/productImages/${item.pimage}` : 'https://placehold.co/80x80/E0E0E0/333333?text=Product';

                        itemElement.innerHTML = `
                            <img src="${picDestination}" class="cart-item-image" alt="${item.name}" onerror="this.onerror=null;this.src='https://placehold.co/80x80/E0E0E0/333333?text=No+Image';">
                            <div class="cart-item-details">
                                <div class="cart-item-name">${item.name}</div>
                                <div class="cart-item-price">BDT ${item.price.toFixed(2)}</div>
                            </div>
                            <div class="cart-item-controls">
                                <button type="button" class="quantity-btn decrease-quantity" data-product-id="${item.id}">-</button>
                                <input type="text" class="item-quantity" value="${item.quantity}" readonly>
                                <button type="button" class="quantity-btn increase-quantity" data-product-id="${item.id}">+</button>
                                <button type="button" class="btn-remove-item" data-product-id="${item.id}">Remove</button>
                            </div>
                        `;
                        cartItemsList.appendChild(itemElement);
                    });

                    // Attach event listeners to newly rendered buttons
                    attachCartItemListeners();
                }
                calculateCartTotal();
            }

            function attachCartItemListeners() {
                document.querySelectorAll('.increase-quantity').forEach(button => {
                    button.onclick = function() {
                        const productId = this.dataset.productId;
                        const itemIndex = cart.findIndex(item => item.id === productId);
                        if (itemIndex > -1) {
                            cart[itemIndex].quantity++;
                            saveCart();
                            showTemporaryMessage(`Quantity for ${cart[itemIndex].name} increased!`, 'info');
                        }
                    };
                });

                document.querySelectorAll('.decrease-quantity').forEach(button => {
                    button.onclick = function() {
                        const productId = this.dataset.productId;
                        const itemIndex = cart.findIndex(item => item.id === productId);
                        if (itemIndex > -1) {
                            if (cart[itemIndex].quantity > 1) {
                                cart[itemIndex].quantity--;
                                saveCart();
                                showTemporaryMessage(`Quantity for ${cart[itemIndex].name} decreased!`, 'info');
                            } else {
                                // If quantity is 1, remove the item
                                const removedItemName = cart[itemIndex].name; // Get name before removing
                                cart.splice(itemIndex, 1);
                                saveCart();
                                showTemporaryMessage(`${removedItemName} removed from cart.`, 'warning');
                            }
                        }
                    };
                });

                document.querySelectorAll('.btn-remove-item').forEach(button => {
                    button.onclick = function() {
                        const productId = this.dataset.productId;
                        const itemIndex = cart.findIndex(item => item.id === productId);
                        if (itemIndex > -1) {
                            const removedItemName = cart[itemIndex].name;
                            cart.splice(itemIndex, 1);
                            saveCart();
                            showTemporaryMessage(`${removedItemName} removed from cart.`, 'warning');
                        }
                    };
                });
            }

            clearCartBtn.addEventListener('click', function() {
                cart = [];
                saveCart();
                showTemporaryMessage('Your cart has been cleared!', 'danger');
            });

            // --- PROCEED TO CHECKOUT BUTTON ---
            checkoutBtn.addEventListener('click', function() {
                if (cart.length > 0) {
                    // Redirect to buyNow.php. buyNow.php will read the cart from localStorage.
                    window.location.href = 'buyNow.php';
                } else {
                    showTemporaryMessage('Your cart is empty. Add some products first!', 'info');
                }
            });

            // Initial load:
            updateCartCount();
            renderCartItems(); // Render cart items on page load

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