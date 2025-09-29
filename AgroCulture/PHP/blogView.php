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

    // --- AJAX Handler for Comments and Reactions ---
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        header('Content-Type: application/json'); // Respond with JSON

        $action = dataFilter($_POST['action'] ?? '');
        $blogId = dataFilter($_POST['blogId'] ?? '');
        $userId = $_SESSION['id'] ?? null; // Assuming user ID is in session

        if ($userId === null) {
            echo json_encode(['success' => false, 'message' => 'Not logged in. Please log in to perform this action.']);
            exit();
        }

        if ($action === 'comment' && isset($_POST['comment']) && $_POST['comment'] != "") {
            $comment = dataFilter($_POST['comment']);
            $commentUser = $_SESSION['Username'] ?? "Anonymous";
            $commentPic = $_SESSION['picName'] ?? "profile0.png";

            $sql = "INSERT INTO blogfeedback (blogId, comment, commentUser, commentPic) VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "isss", $blogId, $comment, $commentUser, $commentPic);
                $success = mysqli_stmt_execute($stmt);
                if ($success) {
                    echo json_encode(['success' => true, 'message' => 'Comment added successfully!', 'commentData' => [
                        'comment' => $comment,
                        'commentUser' => $commentUser,
                        'commentPic' => $commentPic,
                        'commentTime' => date('Y-m-d H:i:s') // Current time for display
                    ]]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error adding comment: ' . mysqli_stmt_error($stmt)]);
                }
                mysqli_stmt_close($stmt);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error preparing comment statement.']);
            }
            exit();
        } elseif ($action === 'react' && isset($_POST['reaction_type'])) {
            $reaction_type = dataFilter($_POST['reaction_type']);
            $allowed_reactions = ['love', 'wow', 'like'];

            if (!in_array($reaction_type, $allowed_reactions)) {
                echo json_encode(['success' => false, 'message' => 'Invalid reaction type.']);
                exit();
            }

            $column_name = $reaction_type . '_count';

            // Check if user has already reacted to this blog post with this type
            // For a more robust system, you'd use a dedicated `blog_reactions` table
            // to track individual user reactions. For simplicity, we'll use session
            // to prevent multiple clicks in the same session, but it's not foolproof.
            $session_key = 'blog_reacted_' . $blogId . '_' . $reaction_type;
            if (isset($_SESSION[$session_key]) && $_SESSION[$session_key] == 1) {
                echo json_encode(['success' => false, 'message' => 'You have already reacted to this blog post with this emoji.']);
                exit();
            }

            $sql_update_reaction = "UPDATE blogdata SET {$column_name} = {$column_name} + 1 WHERE blogId = ?";
            $stmt_update_reaction = mysqli_prepare($conn, $sql_update_reaction);

            if ($stmt_update_reaction) {
                mysqli_stmt_bind_param($stmt_update_reaction, "i", $blogId);
                $success = mysqli_stmt_execute($stmt_update_reaction);

                if ($success) {
                    $_SESSION[$session_key] = 1; // Mark as reacted in session

                    // Fetch the new count to send back to client
                    $sql_fetch_count = "SELECT {$column_name} FROM blogdata WHERE blogId = ?";
                    $stmt_fetch_count = mysqli_prepare($conn, $sql_fetch_count);
                    mysqli_stmt_bind_param($stmt_fetch_count, "i", $blogId);
                    mysqli_stmt_execute($stmt_fetch_count);
                    $result_fetch_count = mysqli_stmt_get_result($stmt_fetch_count);
                    $row_count = mysqli_fetch_assoc($result_fetch_count);
                    $new_count = $row_count[$column_name];
                    mysqli_stmt_close($stmt_fetch_count);

                    echo json_encode(['success' => true, 'new_count' => $new_count, 'reaction_type' => $reaction_type, 'blogId' => $blogId]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Database error updating reaction: ' . mysqli_stmt_error($stmt_update_reaction)]);
                }
                mysqli_stmt_close($stmt_update_reaction);
            } else {
                echo json_encode(['success' => false, 'message' => 'Database error preparing reaction update statement.']);
            }
            exit();
        }
    }

    // --- Fetch Blog Posts for Display ---
    $sql = "SELECT * FROM blogdata ORDER BY blogId DESC";
    $result = mysqli_query($conn, $sql);

    function formatDate($date) {
        return date('F j, Y, g:i a', strtotime($date)); // More readable date format
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>AgroCulture : Blogs</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Custom CSS for distinct blog design -->
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
            --blog-bg-pattern: url('data:image/svg+xml,%3Csvg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"%3E%3Cg fill="none" fill-rule="evenodd"%3E%3Cg fill="%23dce7dce0" fill-opacity="0.4"%3E%3Cpath d="M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zm0-30V0H4v4H0v2h4v4h2V6H4z"%3E%3C/path%3E%3C/g%3E%3C/g%3E%3C/svg%3E'); /* Subtle geometric pattern */
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--background-light);
            background-image: var(--blog-bg-pattern); /* Apply the pattern */
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

        /* Blog Specific Styles */
        .blog-container {
            max-width: 900px; /* Limit width for readability */
            margin: 0 auto;
            padding: 20px;
        }

        .blog-header-section {
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

        .blog-header-section h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary-dark);
            margin-bottom: 0;
        }

        .btn-write-blog {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .btn-write-blog:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.2);
        }

        .blog-post-card {
            background-color: var(--card-bg);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            border-left: 6px solid var(--primary-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .blog-post-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .blog-post-card h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 10px;
        }

        .blog-post-meta {
            font-size: 0.95rem;
            color: var(--secondary-color);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .blog-post-meta span i {
            margin-right: 5px;
            color: var(--accent-color);
        }

        .blog-content-snippet {
            font-size: 1.1rem;
            line-height: 1.7;
            color: var(--text-dark);
            margin-bottom: 20px;
        }

        .blog-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 20px;
            border-top: 1px dashed #eee;
            margin-top: 20px;
        }

        .blog-reactions {
            display: flex;
            gap: 15px;
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

        .comment-toggle-btn {
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 8px 18px;
            font-size: 0.9rem;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .comment-toggle-btn:hover {
            background-color: var(--accent-dark);
            transform: translateY(-2px);
        }

        /* Comments Section */
        .comments-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
            display: none; /* Hidden by default */
        }
        .comments-section.show {
            display: block;
        }

        .comments-list {
            max-height: 300px; /* Limit height for scrollable comments */
            overflow-y: auto;
            padding-right: 10px; /* Space for scrollbar */
            margin-bottom: 20px;
        }

        .comment-card {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            padding: 15px;
            background-color: #f0f4f0; /* Lighter background for comments */
            border-radius: 10px;
            box-shadow: inset 0 1px 5px rgba(0,0,0,0.05);
        }

        .comment-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
            border: 2px solid var(--primary-color);
        }

        .comment-content {
            flex-grow: 1;
        }

        .comment-author-time {
            font-size: 0.9rem;
            color: var(--secondary-color);
            margin-bottom: 5px;
        }
        .comment-author-time strong {
            color: var(--text-dark);
            font-weight: 600;
        }

        .comment-text {
            font-size: 1rem;
            color: var(--text-dark);
            line-height: 1.5;
        }

        .comment-form .form-control {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #ced4da;
            background-color: #f8f8f8;
        }
        .comment-form .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
        }
        .comment-form .btn-submit-comment {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            font-weight: 600;
            border-radius: 8px;
            padding: 10px 20px;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }
        .comment-form .btn-submit-comment:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
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
            .blog-header-section {
                flex-direction: column;
                align-items: flex-start;
                padding: 20px;
            }
            .blog-header-section h1 {
                font-size: 2rem;
                margin-bottom: 20px;
            }
            .btn-write-blog {
                width: 100%;
            }
            .blog-post-card {
                padding: 20px;
            }
            .blog-post-card h2 {
                font-size: 1.5rem;
            }
            .blog-post-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
            .blog-actions {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            .blog-reactions {
                width: 100%;
                justify-content: space-around;
            }
            .comment-toggle-btn {
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
                        <a class="nav-link active" aria-current="page" href="blogView.php"><i class="fas fa-blog"></i> Blog</a>
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
            <h2>AgroCulture Blog</h2>
        </div>

        <div class="blog-container">
            <div class="blog-header-section">
                <h1>Latest Articles & News</h1>
                <a href="blogWrite.php" class="btn btn-write-blog">
                    <i class="fas fa-pencil-alt me-2"></i> Write a Blog
                </a>
            </div>

            <?php
                if ($result && mysqli_num_rows($result) > 0) :
                    while($row = $result->fetch_array()) :
                        $blogId = $row['blogId'];
                        $sql_comments = "SELECT * FROM blogfeedback WHERE blogId = ? ORDER BY commentTime ASC";
                        $stmt_comments = mysqli_prepare($conn, $sql_comments);
                        $numComment = 0;
                        $comments_data = [];

                        if ($stmt_comments) {
                            mysqli_stmt_bind_param($stmt_comments, "i", $blogId);
                            mysqli_stmt_execute($stmt_comments);
                            $result_comments = mysqli_stmt_get_result($stmt_comments);
                            $numComment = mysqli_num_rows($result_comments);
                            while($comment_row = mysqli_fetch_assoc($result_comments)) {
                                $comments_data[] = $comment_row;
                            }
                            mysqli_stmt_close($stmt_comments);
                        }
            ?>
            <div class="blog-post-card" id="blog-post-<?php echo htmlspecialchars($blogId); ?>">
                <h2><?php echo htmlspecialchars($row['blogTitle']); ?></h2>
                <div class="blog-post-meta">
                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($row['blogUser']); ?></span>
                    <span><i class="fas fa-calendar-alt"></i> <?php echo formatDate($row['blogTime']); ?></span>
                </div>
                <p class="blog-content-snippet">
                    <?php echo nl2br(htmlspecialchars(substr($row['blogContent'], 0, 300))); ?>
                    <?php if (strlen($row['blogContent']) > 300): ?>
                        ... <a href="#" class="read-more-link" data-blog-id="<?php echo htmlspecialchars($blogId); ?>">Read More</a>
                    <?php endif; ?>
                </p>

                <div class="blog-actions">
                    <div class="blog-reactions">
                        <button type="button" class="reaction-btn" data-blog-id="<?php echo htmlspecialchars($blogId); ?>" data-reaction-type="love">
                            ❤️ <span class="reaction-count" id="love-count-<?php echo htmlspecialchars($blogId); ?>"><?php echo htmlspecialchars($row['love_count'] ?? 0); ?></span>
                        </button>
                        <button type="button" class="reaction-btn" data-blog-id="<?php echo htmlspecialchars($blogId); ?>" data-reaction-type="wow">
                            😮 <span class="reaction-count" id="wow-count-<?php echo htmlspecialchars($blogId); ?>"><?php echo htmlspecialchars($row['wow_count'] ?? 0); ?></span>
                        </button>
                        <button type="button" class="reaction-btn" data-blog-id="<?php echo htmlspecialchars($blogId); ?>" data-reaction-type="like">
                            👍 <span class="reaction-count" id="like-count-<?php echo htmlspecialchars($blogId); ?>"><?php echo htmlspecialchars($row['like_count'] ?? 0); ?></span>
                        </button>
                    </div>
                    <button type="button" class="comment-toggle-btn" data-blog-id="<?php echo htmlspecialchars($blogId); ?>">
                        <i class="fas fa-comments me-2"></i> Comments (<?php echo $numComment; ?>)
                    </button>
                </div>

                <div class="comments-section" id="comments-section-<?php echo htmlspecialchars($blogId); ?>">
                    <h5 class="mb-3">All Comments</h5>
                    <div class="comments-list" id="comments-list-<?php echo htmlspecialchars($blogId); ?>">
                        <?php if (!empty($comments_data)): ?>
                            <?php foreach ($comments_data as $comment_row): ?>
                                <div class="comment-card">
                                    <img src="<?php echo 'images/profileImages/'.htmlspecialchars($comment_row['commentPic'] ?? 'profile0.png'); ?>" alt="Avatar" class="comment-avatar" onerror="this.onerror=null;this.src='https://placehold.co/40x40/E0E0E0/333333?text=User';">
                                    <div class="comment-content">
                                        <div class="comment-author-time">
                                            <strong><?php echo htmlspecialchars($comment_row['commentUser']); ?></strong> at <?php echo formatDate($comment_row['commentTime']); ?>
                                        </div>
                                        <p class="comment-text"><?php echo htmlspecialchars($comment_row['comment']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center">No comments yet. Be the first!</p>
                        <?php endif; ?>
                    </div>

                    <form class="comment-form mt-4" data-blog-id="<?php echo htmlspecialchars($blogId); ?>">
                        <div class="mb-3">
                            <textarea class="form-control" name="comment" rows="2" placeholder="Write your comment here..." required></textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-submit-comment">Submit Comment</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php
                    endwhile;
                else :
            ?>
                <div class="text-center py-5">
                    <p class="lead">No blog posts found. Be the first to write one!</p>
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

            // --- Blog Comment Toggle ---
            document.querySelectorAll('.comment-toggle-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const blogId = this.dataset.blogId;
                    const commentsSection = document.getElementById(`comments-section-${blogId}`);
                    commentsSection.classList.toggle('show');
                    if (commentsSection.classList.contains('show')) {
                        this.innerHTML = '<i class="fas fa-comments me-2"></i> Hide Comments';
                    } else {
                        this.innerHTML = `<i class="fas fa-comments me-2"></i> Comments (${this.textContent.match(/\((\d+)\)/)?.[1] || 0})`;
                    }
                });
            });

            // --- Blog Post Reaction Logic (AJAX) ---
            document.querySelectorAll('.blog-reactions .reaction-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const blogId = this.dataset.blogId;
                    const reactionType = this.dataset.reactionType;
                    const reactionCountSpan = this.querySelector('.reaction-count');

                    const formData = new FormData();
                    formData.append('action', 'react');
                    formData.append('blogId', blogId);
                    formData.append('reaction_type', reactionType);

                    fetch('blogView.php', { // Post to the same file
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
                            showTemporaryMessage(`You reacted with ${reactionType.charAt(0).toUpperCase() + reactionType.slice(1)}!`, 'success');
                            // Optionally disable the button for this session to prevent multiple clicks
                            // this.disabled = true; 
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

            // --- Comment Submission Logic (AJAX) ---
            document.querySelectorAll('.comment-form').forEach(form => {
                form.addEventListener('submit', function(event) {
                    event.preventDefault(); // Prevent default form submission

                    const blogId = this.dataset.blogId;
                    const commentTextarea = this.querySelector('textarea[name="comment"]');
                    const commentContent = commentTextarea.value.trim();

                    if (commentContent === "") {
                        showTemporaryMessage('Comment cannot be empty.', 'warning');
                        return;
                    }

                    const formData = new FormData();
                    formData.append('action', 'comment');
                    formData.append('blogId', blogId);
                    formData.append('comment', commentContent);

                    fetch('blogView.php', { // Post to the same file
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
                            showTemporaryMessage(data.message, 'success');
                            commentTextarea.value = ''; // Clear the textarea

                            // Dynamically add the new comment to the list
                            const commentsList = document.getElementById(`comments-list-${blogId}`);
                            const newCommentHtml = `
                                <div class="comment-card">
                                    <img src="images/profileImages/<?php echo htmlspecialchars($_SESSION['picName'] ?? 'profile0.png'); ?>" alt="Avatar" class="comment-avatar" onerror="this.onerror=null;this.src='https://placehold.co/40x40/E0E0E0/333333?text=User';">
                                    <div class="comment-content">
                                        <div class="comment-author-time">
                                            <strong>${data.commentData.commentUser}</strong> at ${formatDate(data.commentData.commentTime)}
                                        </div>
                                        <p class="comment-text">${data.commentData.comment}</p>
                                    </div>
                                </div>
                            `;
                            commentsList.insertAdjacentHTML('beforeend', newCommentHtml);

                            // Update comment count on the toggle button
                            const commentToggleButton = document.querySelector(`.comment-toggle-btn[data-blog-id="${blogId}"]`);
                            let currentCount = parseInt(commentToggleButton.textContent.match(/\((\d+)\)/)?.[1] || 0);
                            commentToggleButton.innerHTML = `<i class="fas fa-comments me-2"></i> Comments (${currentCount + 1})`;

                            // Scroll to the new comment
                            commentsList.scrollTop = commentsList.scrollHeight;

                        } else {
                            showTemporaryMessage(`Error submitting comment: ${data.message}`, 'danger');
                        }
                    })
                    .catch(error => {
                        console.error('Network error during comment submission:', error);
                        showTemporaryMessage('Network error during comment submission. Please try again.', 'danger');
                    });
                });
            });

            // --- Read More Link Functionality (for blog content snippets) ---
            document.querySelectorAll('.read-more-link').forEach(link => {
                link.addEventListener('click', function(event) {
                    event.preventDefault();
                    const blogId = this.dataset.blogId;
                    // In a real application, you would fetch the full content via AJAX
                    // and replace the snippet. For now, it's a placeholder.
                    showTemporaryMessage(`Full blog content for Blog ID ${blogId} would load here.`, 'info');
                    // You could also redirect to a single blog post page:
                    // window.location.href = `singleBlog.php?blogId=${blogId}`;
                });
            });

            // Helper function to format date/time for dynamically added comments
            function formatDate(dateString) {
                const options = { year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true };
                return new Date(dateString).toLocaleString('en-US', options);
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
