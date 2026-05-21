<?php
/**
 * Модуль "Аналитика"
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

$pdo = getDB();

// Параметры фильтрации
$filterGroup = isset($_GET['group']) ? (int)$_GET['group'] : 0;
$filterDiscipline = isset($_GET['discipline']) ? (int)$_GET['discipline'] : 0;

$groups = getAllGroups($pdo);
$disciplines = getAllDisciplines($pdo);

// Фильтры для запросов
$filters = [];
if ($filterGroup > 0) $filters['group_id'] = $filterGroup;
if ($filterDiscipline > 0) $filters['discipline_id'] = $filterDiscipline;

// Количество отличников с учетом фильтров
$excellentStudents = getExcellentStudents($pdo, $filters);

// Расчет среднего балла с учетом фильтров
$where = [];
$params = [];
if ($filterGroup > 0) {
    $where[] = 's.group_id = ?';
    $params[] = $filterGroup;
}
if ($filterDiscipline > 0) {
    $where[] = 'gr.discipline_id = ?';
    $params[] = $filterDiscipline;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "SELECT AVG(gr.grade) as avg_grade 
    FROM grades gr 
    JOIN students s ON gr.student_id = s.id 
        $whereClause";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$avgGrade = $stmt->fetch()['avg_grade'] ?? 0;

// Список должников (студенты с оценками 1-3)
$debtorsSql = "SELECT DISTINCT s.id, s.full_name, g.name as group_name, 
                      MIN(gr.grade) as min_grade, COUNT(gr.id) as fail_count
               FROM students s
               JOIN groups g ON s.group_id = g.id
               JOIN grades gr ON s.id = gr.student_id
               WHERE gr.grade <= 3 AND s.status = 'active'";

if ($filterGroup > 0) {
    $debtorsSql .= " AND s.group_id = $filterGroup";
}
if ($filterDiscipline > 0) {
    $debtorsSql .= " AND gr.discipline_id = $filterDiscipline";
}

$debtorsSql .= " GROUP BY s.id ORDER BY fail_count DESC, min_grade ASC LIMIT 20";

$debtors = $pdo->query($debtorsSql)->fetchAll();

// Распределение оценок по группе/дисциплине
$distributionSql = "SELECT 
                        SUM(CASE WHEN gr.grade BETWEEN 1 AND 3 THEN 1 ELSE 0 END) as poor,
                        SUM(CASE WHEN gr.grade BETWEEN 4 AND 6 THEN 1 ELSE 0 END) as satisfactory,
                        SUM(CASE WHEN gr.grade BETWEEN 7 AND 8 THEN 1 ELSE 0 END) as good,
                        SUM(CASE WHEN gr.grade BETWEEN 9 AND 10 THEN 1 ELSE 0 END) as excellent
                    FROM grades gr
                    JOIN students s ON gr.student_id = s.id
                    $whereClause";

$stmt = $pdo->prepare($distributionSql);
$stmt->execute($params);
$distribution = $stmt->fetch();

$pageTitle = 'Аналитика';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-chart-bar"></i> Аналитика</h1>
        <p>Расширенные отчеты и аналитические данные</p>
    </div>
    
    <!-- Фильтры -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-filter"></i> Фильтры отчета</h3>
        </div>
        <div class="card-body">
            <form method="GET" class="filter-form-inline analytics-filter-form">
                <input type="hidden" name="module" value="analytics">
                <div class="form-group">
                    <label for="group">Группа</label>
                    <select name="group" id="group">
                        <option value="0">Все группы</option>
                        <?php foreach ($groups as $group): ?>
                        <option value="<?= $group['id'] ?>" <?= $filterGroup === $group['id'] ? 'selected' : '' ?>>
                            <?= escape($group['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="discipline">Дисциплина</label>
                    <select name="discipline" id="discipline">
                        <option value="0">Все дисциплины</option>
                        <?php foreach ($disciplines as $discipline): ?>
                        <option value="<?= $discipline['id'] ?>" <?= $filterDiscipline === $discipline['id'] ? 'selected' : '' ?>>
                            <?= escape($discipline['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sync"></i> Сформировать отчет
                </button>
                
                <button type="button" class="btn btn-secondary" onclick="window.print()">
                    <i class="fas fa-print"></i> Печать
                </button>

                <a href="/index.php?module=analytics" class="btn btn-secondary">
                    <i class="fas fa-rotate-left"></i> Сбросить
                </a>
            </form>
        </div>
    </div>
    
    <!-- Показатели -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon" style="background: rgba(46, 204, 113, 0.1);">
                <i class="fas fa-star" style="color: #2ecc71;"></i>
            </div>
            <div class="kpi-content">
                <h3><?= count($excellentStudents) ?></h3>
                <p>Отличников</p>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon" style="background: rgba(241, 196, 15, 0.1);">
                <i class="fas fa-calculator" style="color: #f1c40f;"></i>
            </div>
            <div class="kpi-content">
                <h3><?= number_format($avgGrade, 2) ?></h3>
                <p>Средний балл</p>
            </div>
        </div>
        
        <div class="kpi-card">
            <div class="kpi-icon" style="background: rgba(231, 76, 60, 0.1);">
                <i class="fas fa-exclamation-triangle" style="color: #e74c3c;"></i>
            </div>
            <div class="kpi-content">
                <h3><?= count($debtors) ?></h3>
                <p>Должников</p>
            </div>
        </div>
    </div>
    
    <!-- График распределения -->
    <div class="charts-grid">
        <div class="chart-card full-width">
            <div class="chart-header">
                <h3><i class="fas fa-chart-pie"></i> Распределение оценок</h3>
            </div>
            <div class="chart-body">
                <canvas id="analyticsPieChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Таблица должников -->
    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-user-times"></i> Список должников (группа риска)</h3>
        </div>
        <div class="table-body">
            <?php if (!empty($debtors)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>№</th>
                        <th>ФИО студента</th>
                        <th>Группа</th>
                        <th>Мин. оценка</th>
                        <th>Кол-во неудов</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($debtors as $index => $debtor): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= escape($debtor['full_name']) ?></td>
                        <td><?= escape($debtor['group_name']) ?></td>
                        <td>
                            <span class="grade-badge" style="background: #e74c3c;">
                                <?= $debtor['min_grade'] ?>
                            </span>
                        </td>
                        <td><?= $debtor['fail_count'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="empty-message">Должников не найдено</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="table-card">
        <div class="table-header">
            <h3><i class="fas fa-user-graduate"></i> Список отличников</h3>
        </div>
        <div class="table-body">
            <?php if (!empty($excellentStudents)): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>№</th>
                        <th>ФИО студента</th>
                        <th>Группа</th>
                        <th>Средний балл</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($excellentStudents as $index => $student): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= escape($student['full_name']) ?></td>
                        <td><?= escape($student['group_name']) ?></td>
                        <td>
                            <span class="grade-badge" style="background: #2ecc71; min-width: 56px; border-radius: 18px; padding: 0 12px;">
                                <?= number_format($student['avg_grade'], 2) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="empty-message">Отличники не найдены</p>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
const distributionData = <?= json_encode($distribution) ?>;
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
