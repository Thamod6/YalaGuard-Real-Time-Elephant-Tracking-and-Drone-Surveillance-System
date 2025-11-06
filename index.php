<?php
/**
 * YalaGuard Web Application - Main Entry Point
 * 
 * This file serves as the main entry point for the YalaGuard web application.
 * It provides a landing page and navigation to different parts of the system.
 */

// Check if user is logged in (simple session check)
session_start();
$isLoggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YalaGuard - Elephant Monitoring System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
            <header class="home-header">
            <nav class="home-nav">
                <div class="home-logo">🐘 YalaGuard</div>
                <div class="home-nav-links">
                <?php if ($isLoggedIn): ?>
                    <a href="pages/dashboard.php">Dashboard</a>
                    <a href="api/auth/logout.php" class="btn">Logout</a>
                <?php else: ?>
                    <a href="pages/login.php" class="btn">Login</a>
                    <a href="pages/register.php" class="btn btn-primary">Register</a>
                <?php endif; ?>
            </div>
        </nav>
    </header>

    <main>
        <section class="hero">
            <h1>Protect Elephants, Protect Nature</h1>
            <p>YalaGuard is an advanced elephant monitoring and alert system designed to safeguard both wildlife and human communities. Monitor elephant movements, receive real-time alerts, and contribute to conservation efforts.</p>
            
            <div class="hero-buttons">
                <?php if ($isLoggedIn): ?>
                    <a href="pages/dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                <?php else: ?>
                    <a href="pages/register.php" class="home-btn home-btn-primary">Get Started</a>
                    <a href="pages/login.php" class="home-btn">Sign In</a>
                <?php endif; ?>
            </div>
        </section>

        <section class="features">
            <h2>System Features</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">🚨</div>
                    <h3>Real-Time Alerts</h3>
                    <p>Receive instant notifications when elephants are detected near human settlements or protected areas.</p>
                    <?php if ($isLoggedIn): ?>
                        <a href="pages/manual-alerts.php" class="feature-link">Send Alert Now</a>
                    <?php endif; ?>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🐘</div>
                    <h3>Elephant Tracking</h3>
                    <p>Monitor elephant movements with GPS tracking and historical movement patterns analysis.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3>Analytics Dashboard</h3>
                    <p>Comprehensive reporting and analytics to understand elephant behavior and migration patterns.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">👥</div>
                    <h3>Authority Management</h3>
                    <p>Manage alert recipients, configure notification preferences, and ensure timely response to incidents.</p>
                    <?php if ($isLoggedIn): ?>
                        <a href="pages/authority-management.php" class="feature-link">Manage Authorities</a>
                    <?php endif; ?>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🌍</div>
                    <h3>Geographic Mapping</h3>
                    <p>Visualize elephant locations and alert zones on interactive maps for better decision making.</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">⚡</div>
                    <h3>High Performance</h3>
                    <p>Built with modern PHP 8+ and MongoDB for fast, reliable, and scalable operations.</p>
                </div>
            </div>
        </section>
    </main>

    <footer class="footer">
        <p>&copy; 2024 YalaGuard. Protecting elephants, preserving nature. 🐘🌿</p>
    </footer>
    <script src="assets/js/main.js"></script>
</body>
</html>
