<?php
/**
 * Главная панель (Dashboard) + Роутер
 * Точка входа в систему
 */
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';

// Роутинг по модулям
$module = $_GET['module'] ?? 'dashboard';

if ($_SESSION['role'] === 'student') {
    $studentId = (int)($_SESSION['student_id'] ?? 0);
    $requestedStudentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

    if ($studentId <= 0) {
        session_destroy();
        header('Location: /login.php');
        exit;
    }

    if ($module !== 'students' || $requestedStudentId !== $studentId) {
        header('Location: /index.php?module=students&student_id=' . $studentId);
        exit;
    }
}

switch ($module) {
    case 'students':
        require __DIR__ . '/modules/students/index.php';
        break;
    case 'disciplines':
        require __DIR__ . '/modules/disciplines/index.php';
        break;
    case 'grades':
        require __DIR__ . '/modules/grades/index.php';
        break;
    case 'analytics':
        require __DIR__ . '/modules/analytics/index.php';
        break;
    case 'users':
        require __DIR__ . '/modules/users/index.php';
        break;
    case 'dashboard':
    default:
        // Код главной панели
        $pdo = getDB();

        // KPI показатели
        $totalStudents = $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'active'")->fetchColumn();
        $facultyAverage = calculateFacultyAverage($pdo);
        $excellentCount = count(getExcellentStudents($pdo, 100));
        $riskCount = count(getRiskStudents($pdo));

        // Данные для графиков
        $gradeDistribution = getGradeDistribution($pdo);
        $groupRating = getGroupRating($pdo, 5);
        $recentGrades = getRecentGrades($pdo, 10);

        $pageTitle = 'Главная панель';
        include __DIR__ . '/includes/header.php';
        include __DIR__ . '/includes/sidebar.php';
        ?>

        <main class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-chart-line"></i> Главная панель</h1>
                <p>Обзор успеваемости инженерного факультета</p>
            </div>
            
            <!-- KPI Карточки -->
            <div class="kpi-grid">
                <div class="kpi-card" data-tooltip="Количество студентов со статусом active, которые сейчас учитываются в системе и аналитике.">
                    <div class="kpi-icon" style="background: rgba(0, 74, 9, 0.1);">
                        <i class="fas fa-user-graduate" style="color: #004a09;"></i>
                    </div>
                    <div class="kpi-content">
                        <h3><?= $totalStudents ?></h3>
                        <p>Всего студентов</p>
                    </div>
                </div>
                
                <div class="kpi-card" data-tooltip="Среднее значение всех выставленных оценок по факультету. Показывает общий уровень успеваемости.">
                    <div class="kpi-icon" style="background: rgba(46, 204, 113, 0.1);">
                        <i class="fas fa-star" style="color: #2ecc71;"></i>
                    </div>
                    <div class="kpi-content">
                        <h3><?= number_format($facultyAverage, 2) ?></h3>
                        <p>Средний балл</p>
                    </div>
                </div>
                
                <div class="kpi-card" data-tooltip="Число студентов, у которых средний балл по всем оценкам не ниже 9.0.">
                    <div class="kpi-icon" style="background: rgba(241, 196, 15, 0.1);">
                        <i class="fas fa-medal" style="color: #f1c40f;"></i>
                    </div>
                    <div class="kpi-content">
                        <h3><?= $excellentCount ?></h3>
                        <p>Отличники</p>
                    </div>
                </div>
                
                <div class="kpi-card" data-tooltip="Студент попадает в группу риска, если хотя бы по одной дисциплине его средний балл меньше либо равен 4.0.">
                    <div class="kpi-icon" style="background: rgba(231, 76, 60, 0.1);">
                        <i class="fas fa-exclamation-triangle" style="color: #e74c3c;"></i>
                    </div>
                    <div class="kpi-content">
                        <h3><?= $riskCount ?></h3>
                        <p>Группа риска</p>
                    </div>
                </div>
            </div>
            
            <!-- Графики -->
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-pie"></i> Распределение оценок</h3>
                    </div>
                    <div class="chart-body">
                        <canvas id="gradePieChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-bar"></i> Рейтинг групп (Топ-5)</h3>
                    </div>
                    <div class="chart-body">
                        <canvas id="groupBarChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Последние оценки -->
            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-clock"></i> Последние добавленные оценки</h3>
                </div>
                <div class="table-body">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Студент</th>
                                <th>Группа</th>
                                <th>Предмет</th>
                                <th>Оценка</th>
                                <th>Тип</th>
                                <th>Дата</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentGrades as $grade): ?>
                            <tr>
                                <td><?= escape($grade['student_name']) ?></td>
                                <td><?= escape($grade['group_name']) ?></td>
                                <td><?= escape($grade['discipline_name']) ?></td>
                                <td>
                                    <span class="grade-badge" style="background: <?= getGradeColor($grade['grade']) ?>;">
                                        <?= $grade['grade'] ?>
                                    </span>
                                </td>
                                <td><?= escape(getGradeTypeLabel($grade['type'])) ?></td>
                                <td><?= formatDate($grade['date']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <?php
        $additionalScripts = '<script>
        const gradeDistribution = ' . json_encode($gradeDistribution) . ';
        const groupRating = ' . json_encode($groupRating) . ';
        </script>';
        include __DIR__ . '/includes/footer.php';
        break;
}
?>
