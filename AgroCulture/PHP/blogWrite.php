<?php
    session_start();

    // Redirect if not logged in
    if(!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] == 0) {
        $_SESSION['message'] = "You need to login to write a blog post!";
        header("Location: Login/error.php");
        exit(); // Always exit after a header redirect
    }
    // No database connection needed directly on this page for display,
    // but blogSubmit.php will need it.
    // require 'db.php'; 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>AgroCulture : Write a Blog</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- CKEditor CDN -->
    <script src="https://cdn.ckeditor.com/4.8.0/full/ckeditor.js"></script>

    <!-- Custom CSS for distinct blog write design -->
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
            --blog-write-bg-pattern: url('data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%23dce7dce0" fill-opacity="0.6"%3E%3Cpath d="M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zm0-30V0H4v4H0v2h4v4h2V6H4z"%3E%3C/path%3E%3C/g%3E%3C/g%3E%3C/svg%3E'); /* Slightly stronger pattern for writing page */
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background-light);
            background-image: var(--blog-write-bg-pattern); /* Apply the pattern */
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

        /* Blog Write Form Styling */
        .blog-write-container {
            max-width: 900px; /* Limit width for readability */
            margin: 0 auto;
            padding: 20px;
        }

        .blog-write-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            background-color: var(--card-bg);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            border-left: 6px solid var(--accent-color);
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
        }

        .blog-write-header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-dark);
            margin-bottom: 0;
        }

        .btn-view-blogs {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
            color: white;
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .btn-view-blogs:hover {
            background-color: var(--accent-dark);
            border-color: var(--accent-dark);
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
        }

        .blog-form-card {
            background-color: var(--card-bg);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border-left: 6px solid var(--primary-color);
        }

        .blog-form-card .form-control {
            border-radius: 10px;
            padding: 12px 18px;
            border: 1px solid #ced4da;
            background-color: var(--background-light);
            font-size: 1.1rem;
            color: var(--text-dark);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .blog-form-card .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
        }

        /* CKEditor specific styling adjustments */
        .cke_chrome {
            border-radius: 10px !important;
            border: 1px solid #ced4da !important;
            box-shadow: none !important; /* Remove CKEditor default shadow */
        }
        .cke_top {
            background: linear-gradient(to right, #f0f4f0, #e8f5e9) !important; /* Lighter gradient for toolbar */
            border-bottom: 1px solid #e0e0e0 !important;
            border-top-left-radius: 10px !important;
            border-top-right-radius: 10px !important;
        }
        .cke_bottom {
            background-color: #f0f4f0 !important;
            border-top: 1px solid #e0e0e0 !important;
            border-bottom-left-radius: 10px !important;
            border-bottom-right-radius: 10px !important;
        }
        .cke_contents {
            background-color: var(--background-light) !important;
            color: var(--text-dark) !important;
        }

        .btn-submit-blog {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            font-weight: 600;
            border-radius: 10px;
            padding: 12px 25px;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .btn-submit-blog:hover {
            background-color: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
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
            .blog-write-header {
                flex-direction: column;
                align-items: flex-start;
                padding: 20px;
            }
            .blog-write-header h1 {
                font-size: 2rem;
                margin-bottom: 20px;
            }
            .btn-view-blogs {
                width: 100%;
            }
            .blog-form-card {
                padding: 20px;
            }
            .blog-form-card .form-control {
                font-size: 1rem;
            }
            .btn-submit-blog {
                width: 100%;
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
            <h2>Write a New Blog Post</h2>
        </div>

        <div class="blog-write-container">
            <div class="blog-write-header">
                <h1>Create Your Article</h1>
                <a href="blogView.php" class="btn btn-view-blogs">
                    <i class="fas fa-eye me-2"></i> View Blogs
                </a>
            </div>

            <div class="blog-form-card">
                <form method="post" action="Blog/blogSubmit.php">
                    <div class="mb-4">
                        <label for="blogTitle" class="form-label visually-hidden">Blog Title</label>
                        <input type="text" name="blogTitle" id="blogTitle" class="form-control" placeholder="Enter your blog title here..." required />
                    </div>
                    <div class="mb-4">
                        <label for="blogContent" class="form-label visually-hidden">Blog Content</label>
                        <textarea name="blogContent" id="blogContent" rows="12" placeholder="Start writing your blog content..."></textarea>
                    </div>
                    <div class="d-grid mt-4">
                        <button type="submit" name="submit" class="btn btn-submit-blog btn-lg">
                            <i class="fas fa-paper-plane me-2"></i> Publish Blog Post
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

            // --- Cart Item Count from localStorage (for sidebar) ---
            const cartItemCountSpan = document.getElementById('cart-item-count');
            let cart = JSON.parse(localStorage.getItem('agro_cart')) || [];
            cartItemCountSpan.textContent = cart.length;

            // Initialize CKEditor
            // Ensure the textarea with id 'blogContent' exists before replacing
            if (document.getElementById('blogContent')) {
                CKEDITOR.replace('blogContent');
            } else {
                console.error("CKEditor target element 'blogContent' not found.");
            }
        });

        // --- General Temporary Message Function (if needed for form submission feedback) ---
        // This function is included for consistency, though blogSubmit.php would typically handle redirects/messages.
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
    </script>

</body>
</html>
