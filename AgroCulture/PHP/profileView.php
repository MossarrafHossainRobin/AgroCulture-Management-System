<?php
    session_start();

    // Redirect if not logged in
    if(!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] != 1) {
        $_SESSION['message'] = "You need to login to view this page!";
        header("Location: Login/error.php");
        exit(); // Always exit after a header redirect
    }

    // Determine the user's role and set a default profile picture and color.
    // Assuming 'Role' is stored in the session.
    $userRole = $_SESSION['Role'] ?? 'Buyer'; // Default to 'Buyer' if not set.
    $profilePicPath = 'images/profileImages/' . htmlspecialchars($_SESSION['picName'] ?? 'default.png');
    $profilePicPath .= '?' . mt_rand(); // Prevent caching
    
    // Set role-specific styles and images
    $roleClass = '';
    $roleAccentColor = '';
    $roleProfileIcon = '';

    if ($userRole == 'Farmer') {
        $roleClass = 'farmer-role';
        $roleAccentColor = 'var(--primary-color)'; // Green for farmers
        $roleProfileIcon = '<i class="fas fa-seedling me-2"></i>';
    } else {
        // Buyer role
        $roleClass = 'buyer-role';
        $roleAccentColor = 'var(--info-color)'; // Blue for buyers
        $roleProfileIcon = '<i class="fas fa-shopping-basket me-2"></i>';
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Profile: <?php echo htmlspecialchars($_SESSION['Username'] ?? 'User'); ?></title>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <style>
        :root {
            --primary-color: #388e3c;
            --primary-dark: #2e7d32;
            --secondary-color: #757575;
            --accent-color: #00897b;
            --accent-dark: #00695c;
            --background-start: #e1f5fe;
            --background-end: #e8f5e9;
            --card-bg: rgba(255, 255, 255, 0.25);
            --border-color: rgba(255, 255, 255, 0.4);
            --text-dark: #212121;
            --sidebar-bg: #263238;
            --sidebar-text: #eceff1;
            --danger-color: #d32f2f;
            --info-color: #1976d2;
            --farmer-color: #388e3c;
            --buyer-color: #1976d2;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--background-start) 0%, var(--background-end) 100%);
            color: var(--text-dark);
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }
        
        /* Animated Background Shapes */
        .background-shapes { position: absolute; top: 0; left: 0; width: 100%; height: 100%; overflow: hidden; z-index: -1; }
        .shape { position: absolute; background: rgba(255, 255, 255, 0.1); border-radius: 50%; animation: moveShapes 15s infinite; }
        .shape:nth-child(1) { width: 200px; height: 200px; top: 10%; left: 15%; animation-duration: 12s; }
        .shape:nth-child(2) { width: 300px; height: 300px; top: 60%; left: 5%; animation-duration: 18s; }
        .shape:nth-child(3) { width: 150px; height: 150px; top: 30%; left: 70%; animation-duration: 14s; }
        .shape:nth-child(4) { width: 250px; height: 250px; top: 80%; left: 80%; animation-duration: 16s; }
        @keyframes moveShapes {
            0% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(50px, -50px) rotate(45deg); }
            50% { transform: translate(-50px, 50px) rotate(90deg); }
            75% { transform: translate(50px, -50px) rotate(135deg); }
            100% { transform: translate(0, 0) rotate(180deg); }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: var(--card-bg); border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: var(--primary-color); border-radius: 10px; border: 2px solid transparent; }
        ::-webkit-scrollbar-thumb:hover { background: var(--primary-dark); }
        html { scrollbar-width: thin; scrollbar-color: var(--primary-color) var(--card-bg); }

        /* Sidebar Styling */
        .sidebar {
            width: 250px;
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            padding: 20px;
            flex-shrink: 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 15px rgba(0,0,0,0.3);
            z-index: 1000;
            transition: transform 0.3s ease-in-out;
        }
        @media (max-width: 768px) {
            .sidebar { left: -250px; transform: translateX(0); }
            .sidebar.show { transform: translateX(250px); }
        }
        .sidebar .navbar-brand { font-weight: 800; font-size: 1.8rem; margin-bottom: 30px !important; color: var(--primary-color) !important; text-shadow: 1px 1px 3px rgba(0,0,0,0.4); }
        .sidebar .nav-link { color: var(--sidebar-text); padding: 14px 18px; border-radius: 10px; margin-bottom: 10px; transition: background-color 0.3s ease, color 0.3s ease, transform 0.2s ease, box-shadow 0.2s ease; display: flex; align-items: center; font-weight: 500; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: var(--sidebar-hover); color: var(--primary-color); transform: translateX(8px); box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        .sidebar .nav-link i { margin-right: 15px; font-size: 1.2rem; }

        /* Main Content Area */
        .main-container {
            margin-left: 250px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            transition: margin-left 0.3s ease-in-out;
            padding-bottom: 70px;
            position: relative;
        }
        @media (max-width: 768px) {
            .main-container { margin-left: 0; }
            .main-container.shifted { margin-left: 250px; }
        }
        .main-content {
            flex: 1;
            padding: 30px;
        }

        /* Profile Header with layered effect */
        .profile-page-header {
            position: relative;
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            text-align: center;
            overflow: hidden;
            z-index: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .profile-page-header::before {
            content: '';
            position: absolute;
            top: -20px;
            left: -20px;
            width: calc(100% + 40px);
            height: calc(100% + 40px);
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            z-index: -1;
            transform: rotate(-5deg);
        }
        .profile-page-header .profile-pic-container {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 20px;
            border: 4px solid <?php echo $roleAccentColor; ?>;
            box-shadow: 0 0 0 8px <?php echo ($userRole == 'Farmer') ? 'rgba(56, 142, 60, 0.2)' : 'rgba(25, 118, 210, 0.2)'; ?>;
            transition: transform 0.3s ease;
        }
        .profile-page-header .profile-pic-container:hover { transform: scale(1.05); }
        .profile-page-header .profile-pic-container img { width: 100%; height: 100%; object-fit: cover; }
        .profile-page-header h2 { font-size: 3rem; font-weight: 800; margin: 0; color: var(--text-dark); text-shadow: 1px 1px 2px rgba(0,0,0,0.1); }
        .profile-page-header h4 { font-size: 1.4rem; font-weight: 500; color: var(--secondary-color); margin: 5px 0 0 0; }
        
        .role-badge {
            font-size: 1rem;
            font-weight: 600;
            padding: 8px 20px;
            border-radius: 50px;
            margin-top: 15px;
            color: white;
            display: inline-block;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .farmer-role .role-badge { background-color: var(--farmer-color); }
        .buyer-role .role-badge { background-color: var(--buyer-color); }

        /* Dashboard Grid & Cards */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }
        .dashboard-card {
            background: var(--card-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }
        .dashboard-card:hover { transform: translateY(-8px); box-shadow: 0 16px 48px 0 rgba(31, 38, 135, 0.37); }
        .dashboard-card .card-header { display: flex; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid var(--primary-color); }
        .dashboard-card .card-header i { font-size: 1.8rem; color: var(--primary-color); margin-right: 15px; }
        .dashboard-card .card-header h3 { font-size: 1.5rem; font-weight: 700; color: var(--primary-color); margin: 0; }

        .details-list { list-style: none; padding: 0; margin: 0; }
        .details-list li { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; border-bottom: 1px solid rgba(0,0,0,0.1); }
        .details-list li:last-child { border-bottom: none; }
        .details-list .label { font-size: 1rem; font-weight: 500; color: var(--secondary-color); flex-grow: 1; }
        .details-list .value { font-size: 1.1rem; font-weight: 600; color: var(--text-dark); text-align: right; }

        .settings-card .card-header { border-bottom-color: var(--info-color); }
        .settings-card .card-header h3, .settings-card .card-header i { color: var(--info-color); }
        .settings-toggle { background: none; border: none; color: var(--info-color); font-size: 1.5rem; cursor: pointer; transition: transform 0.3s ease; margin-left: auto; }
        .settings-toggle:hover { transform: rotate(90deg); }
        .settings-toggle.active { transform: rotate(90deg); }
        .profile-actions-list { display: flex; flex-direction: column; gap: 15px; overflow: hidden; max-height: 0; opacity: 0; transition: max-height 0.4s ease-in-out, opacity 0.4s ease-in-out; }
        .profile-actions-list.show { max-height: 300px; opacity: 1; margin-top: 20px; }
        .profile-actions-list .btn { padding: 15px 20px; border-radius: 10px; font-weight: 600; text-align: left; transition: all 0.3s ease; box-shadow: 0 4px 10px rgba(0,0,0,0.05); display: flex; align-items: center; width: 100%; }
        .btn-action-primary { background-color: var(--primary-color); border-color: var(--primary-color); color: white; }
        .btn-action-primary:hover { background-color: var(--primary-dark); border-color: var(--primary-dark); }
        .btn-action-info { background-color: var(--info-color); border-color: var(--info-color); color: white; }
        .btn-action-info:hover { background-color: var(--info-dark); border-color: var(--info-dark); }
        .btn-action-danger { background-color: var(--danger-color); border-color: var(--danger-color); color: white; }
        .btn-action-danger:hover { background-color: var(--danger-dark); border-color: var(--danger-dark); }

        /* Fixed Footer Styling */
        .footer {
            background: #263238;
            color: #b0bec5;
            text-align: center;
            padding: 20px;
            width: 100%;
            position: fixed;
            bottom: 0;
            z-index: 999;
            margin-left: 250px;
            transition: margin-left 0.3s ease-in-out;
        }
        @media (max-width: 768px) {
            .footer { margin-left: 0; }
        }
    </style>
</head>

<body class="<?php echo $roleClass; ?>">
    <div class="background-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

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
                        <a class="nav-link" href="myCart.php"><i class="fas fa-shopping-cart"></i> My Cart (<span id="cart-item-count">0</span>)</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="market.php"><i class="fas fa-store"></i> Market</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="blogView.php"><i class="fas fa-newspaper"></i> Blog</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="orderdetails.php"><i class="fas fa-clipboard-list"></i> My Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="profileView.php"><i class="fas fa-user-circle"></i> Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="Login/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="main-container" id="main-container-area">
        <div class="main-content">
            <div class="profile-page-header">
                <div class="profile-pic-container">
                    <img src="<?php echo $profilePicPath; ?>" alt="Profile Picture" onerror="this.onerror=null;this.src='https://placehold.co/180x180/E0E0E0/333333?text=Profile';">
                </div>
                <h2><?php echo htmlspecialchars($_SESSION['Name'] ?? 'Guest User'); ?></h2>
                <h4>@<?php echo htmlspecialchars($_SESSION['Username'] ?? 'username'); ?></h4>
             
            </div>

            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="card-header">
                        <i class="fas fa-info-circle"></i>
                        <h3>User Details</h3>
                    </div>
                    <ul class="details-list">
                        <li>
                            <span class="label">Email:</span>
                            <span class="value"><?php echo htmlspecialchars($_SESSION['Email'] ?? 'N/A'); ?></span>
                        </li>
                        <li>
                            <span class="label">Mobile:</span>
                            <span class="value"><?php echo htmlspecialchars($_SESSION['Mobile'] ?? 'N/A'); ?></span>
                        </li>
                        <li>
                            <span class="label">Address:</span>
                            <span class="value"><?php echo htmlspecialchars($_SESSION['Addr'] ?? 'N/A'); ?></span>
                        </li>
                        <li>
                            <span class="label">Rating:</span>
                            <span class="value"><?php echo htmlspecialchars($_SESSION['Rating'] ?? 'N/A'); ?></span>
                        </li>
                    </ul>
                </div>
                
                <div class="dashboard-card settings-card">
                    <div class="card-header">
                        <i class="fas fa-cog"></i>
                        <h3>Account Settings</h3>
                        <button class="settings-toggle" id="settings-toggle-btn" aria-label="Toggle Account Settings" aria-expanded="false">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>
                    <div class="profile-actions-list" id="settings-list">
                        <a href="profileEdit.php" class="btn btn-action-primary">
                            <i class="fas fa-edit"></i> Edit Profile
                        </a>
                        <a href="changePassPage.php" class="btn btn-action-info">
                            <i class="fas fa-key"></i> Change Password
                        </a>
                        <a href="uploadProduct.php" class="btn btn-action-primary">
                            <i class="fas fa-cloud-upload-alt"></i> Upload Product
                        </a>
                        <a href="Login/logout.php" class="btn btn-action-danger">
                            <i class="fas fa-sign-out-alt"></i> LOG OUT</a>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="card-header">
                        <i class="fas fa-chart-line"></i>
                        <h3>Activity Overview</h3>
                    </div>
                    <p>Your recent activities will be displayed here.</p>
                </div>
            </div>
        </div>
        
        <footer class="footer" id="main-footer">
            © 2025 AgroCulture Ltd. All rights reserved.
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sidebar Toggle for Mobile
            const sidebar = document.getElementById('main-sidebar');
            const mainContainer = document.getElementById('main-container-area');
            const navbarToggler = document.querySelector('.navbar-toggler');

            navbarToggler.addEventListener('click', function() {
                sidebar.classList.toggle('show');
                mainContainer.classList.toggle('shifted');
            });

            // Interactive Account Settings Toggle
            const settingsToggleBtn = document.getElementById('settings-toggle-btn');
            const settingsList = document.getElementById('settings-list');

            settingsToggleBtn.addEventListener('click', function() {
                const isExpanded = settingsList.classList.toggle('show');
                this.classList.toggle('active', isExpanded);
                this.setAttribute('aria-expanded', isExpanded);
                this.querySelector('i').classList.toggle('fa-chevron-down', !isExpanded);
                this.querySelector('i').classList.toggle('fa-chevron-up', isExpanded);
            });

            // Cart Item Count from localStorage
            const cartItemCountSpan = document.getElementById('cart-item-count');
            let cart = JSON.parse(localStorage.getItem('agro_cart')) || [];
            cartItemCountSpan.textContent = cart.length;
        });
    </script>
</body>
</html>