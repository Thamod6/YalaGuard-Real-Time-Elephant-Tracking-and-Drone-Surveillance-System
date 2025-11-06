<?php
/**
 * YalaGuard Sidebar Navigation Component
 * 
 * This file contains the sidebar navigation that can be included in all pages
 */

// Get current page for active navigation highlighting
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// Get user info from session
$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'User';
$user_initial = strtoupper(substr($user_name, 0, 1));
?>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar Navigation -->
<aside class="sidebar" id="sidebar">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <div class="sidebar-logo">🐘 YalaGuard</div>
        <div class="sidebar-subtitle">Wildlife Protection System</div>
    </div>

    <!-- Sidebar Navigation -->
    <nav class="sidebar-nav">
        <!-- Main Navigation Section -->
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Main Navigation</div>
            <ul class="sidebar-nav-list">
                <li class="sidebar-nav-item">
                    <a href="dashboard.php" class="sidebar-nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                        <span class="sidebar-nav-icon">📊</span>
                        <span class="sidebar-nav-text">Dashboard</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="elephants.php" class="sidebar-nav-link <?php echo $current_page === 'elephants' ? 'active' : ''; ?>">
                        <span class="sidebar-nav-icon">🐘</span>
                        <span class="sidebar-nav-text">Elephants</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="geofencing.php" class="sidebar-nav-link <?php echo $current_page === 'geofencing' ? 'active' : ''; ?>">
                        <span class="sidebar-nav-icon">🗺️</span>
                        <span class="sidebar-nav-text">Geofencing</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="gps-collar-management.php" class="sidebar-nav-link <?php echo $current_page === 'gps-collar-management' ? 'active' : ''; ?>">
                        <span class="sidebar-nav-icon">📡</span>
                        <span class="sidebar-nav-text">GPS Collars</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Management Section -->
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Management</div>
            <ul class="sidebar-nav-list">
                <li class="sidebar-nav-item">
                    <a href="add-elephant.php" class="sidebar-nav-link <?php echo $current_page === 'add-elephant' ? 'active' : ''; ?>">
                        <span class="sidebar-nav-icon">➕</span>
                        <span class="sidebar-nav-text">Add Elephant</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="add-geofence.php" class="sidebar-nav-link <?php echo $current_page === 'add-geofence' ? 'active' : ''; ?>">
                        <span class="sidebar-nav-icon">🔒</span>
                        <span class="sidebar-nav-text">Add Geofence</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="camera.php" class="sidebar-nav-link <?php echo $current_page === 'camera' ? 'active' : ''; ?>">
                        <span class="sidebar-nav-icon">📷</span>
                        <span class="sidebar-nav-text">Camera System</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Alert System Section -->
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">🚨 Alert System</div>
            <ul class="sidebar-nav-list">
                <li class="sidebar-nav-item">
                    <a href="manual-alerts.php" class="sidebar-nav-link <?php echo $current_page === 'manual-alerts' ? 'active' : ''; ?>">
                        <span class="sidebar-nav-icon">📢</span>
                        <span class="sidebar-nav-text">Send Manual Alert</span>
                    </a>
                </li>
                <li class="sidebar-nav-item">
                    <a href="authority-management.php" class="sidebar-nav-link <?php echo $current_page === 'authority-management' ? 'active' : ''; ?>">
                        <span class="sidebar-nav-icon">👥</span>
                        <span class="sidebar-nav-text">Manage Authorities</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Quick Actions Section -->
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">Quick Actions</div>
            <div class="sidebar-quick-actions">
                <a href="add-elephant.php" class="quick-action-btn">
                    <span class="icon">🐘</span>
                    <span>Add Elephant</span>
                </a>
                <a href="manual-alerts.php" class="quick-action-btn alert-quick">
                    <span class="icon">🚨</span>
                    <span>Send Alert</span>
                </a>
            </div>
        </div>

        <!-- Divider -->
        <div class="sidebar-divider"></div>

        <!-- System Section -->
        <div class="sidebar-nav-section">
            <div class="sidebar-nav-title">System</div>
            <ul class="sidebar-nav-list">
                <li class="sidebar-nav-item">
                    <a href="../api/auth/logout.php" class="sidebar-nav-link">
                        <span class="sidebar-nav-icon">🚪</span>
                        <span class="sidebar-nav-text">Logout</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Sidebar Footer with User Profile -->
    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar">
                <?php echo $user_initial; ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                <div class="user-role"><?php echo ucfirst(htmlspecialchars($user_role)); ?></div>
            </div>
        </div>
    </div>
</aside>

<!-- Sidebar Toggle Button for Mobile -->
<button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
    <span>☰</span>
</button>

<script>
// Sidebar functionality
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const mainContent = document.querySelector('.main-content');
    
    // Toggle sidebar on mobile
    function toggleSidebar() {
        sidebar.classList.toggle('open');
        sidebarOverlay.classList.toggle('open');
        document.body.classList.toggle('sidebar-open');
    }
    
    // Close sidebar when clicking overlay
    sidebarOverlay.addEventListener('click', function() {
        sidebar.classList.remove('open');
        sidebarOverlay.classList.remove('open');
        document.body.classList.remove('sidebar-open');
    });
    
    // Toggle sidebar button
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }
    
    // Close sidebar on window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 1024) {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('open');
            document.body.classList.remove('sidebar-open');
        }
    });
    
    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            toggleSidebar();
        }
    });
    
    // Add active class to current page navigation
    const currentPage = '<?php echo $current_page; ?>';
    const navLinks = document.querySelectorAll('.sidebar-nav-link');
    
    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && href.includes(currentPage)) {
            link.classList.add('active');
        }
    });
    
    // Sidebar collapse functionality (optional)
    let isCollapsed = false;
    
    function toggleSidebarCollapse() {
        isCollapsed = !isCollapsed;
        sidebar.classList.toggle('collapsed', isCollapsed);
        mainContent.classList.toggle('sidebar-collapsed', isCollapsed);
        
        // Save state to localStorage
        localStorage.setItem('sidebarCollapsed', isCollapsed);
    }
    
    // Load saved state
    const savedState = localStorage.getItem('sidebarCollapsed');
    if (savedState === 'true') {
        isCollapsed = true;
        sidebar.classList.add('collapsed');
        mainContent.classList.add('sidebar-collapsed');
    }
    
    // Add double-click to collapse (optional feature)
    sidebar.addEventListener('dblclick', function(e) {
        if (e.target.closest('.sidebar-nav-link') || e.target.closest('.sidebar-header')) {
            return; // Don't collapse when clicking navigation items
        }
        toggleSidebarCollapse();
    });
});
</script>
