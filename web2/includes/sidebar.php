<?php
/**
 * Боковое меню навигации
 */
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
if (isset($_GET['module'])) {
    $currentPage = $_GET['module'];
}
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-graduation-cap"></i>
            <span>ПолесГУ</span>
        </div>
        <p class="subtitle">Система оценки</p>
    </div>
    
    <nav class="sidebar-nav">
        <?php if ($_SESSION['role'] === 'student'): ?>
        <a href="/index.php?module=students&student_id=<?= (int)($_SESSION['student_id'] ?? 0) ?>" class="nav-item <?= $currentPage === 'students' ? 'active' : '' ?>">
            <i class="fas fa-id-card"></i>
            <span>Моя карточка</span>
        </a>
        <?php else: ?>
        <a href="/index.php" class="nav-item <?= $currentPage === 'index' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i>
            <span>Главная панель</span>
        </a>
        
        <a href="/index.php?module=students" class="nav-item <?= $currentPage === 'students' ? 'active' : '' ?>">
            <i class="fas fa-user-graduate"></i>
            <span>Студенты</span>
        </a>
        
        <a href="/index.php?module=disciplines" class="nav-item <?= $currentPage === 'disciplines' ? 'active' : '' ?>">
            <i class="fas fa-book"></i>
            <span>Дисциплины</span>
        </a>
        
        <a href="/index.php?module=grades" class="nav-item <?= $currentPage === 'grades' ? 'active' : '' ?>">
            <i class="fas fa-edit"></i>
            <span>Журнал оценок</span>
        </a>
        
        <a href="/index.php?module=analytics" class="nav-item <?= $currentPage === 'analytics' ? 'active' : '' ?>">
            <i class="fas fa-chart-pie"></i>
            <span>Аналитика</span>
        </a>
        
        <?php if ($_SESSION['role'] === 'admin'): ?>
        <a href="/index.php?module=users" class="nav-item <?= $currentPage === 'users' ? 'active' : '' ?>">
            <i class="fas fa-users-cog"></i>
            <span>Пользователи</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>
    </nav>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-details">
                <p class="name"><?= escape($_SESSION['full_name']) ?></p>
                <p class="role"><?= escape(getRoleLabel($_SESSION['role'])) ?></p>
            </div>
        </div>
        <a href="/logout.php" class="logout-btn" title="Выйти">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</aside>

<div class="mobile-menu-toggle" onclick="toggleMobileMenu()">
    <i class="fas fa-bars"></i>
</div>
