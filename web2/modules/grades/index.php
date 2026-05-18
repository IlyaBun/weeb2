<?php
/**
 * Модуль "Журнал оценок"
 */
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

$pdo = getDB();
$message = '';

// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_grades') {
        $groupId = (int)($_POST['group_id'] ?? 0);
        $disciplineId = (int)($_POST['discipline_id'] ?? 0);
        $grades = $_POST['grades'] ?? [];
        
        if ($groupId && $disciplineId && !empty($grades)) {
            $pdo->beginTransaction();
            try {
                foreach ($grades as $studentId => $gradeData) {
                    $grade = (int)($gradeData['grade'] ?? 0);
                    $date = $gradeData['date'] ?? date('Y-m-d');
                    $type = $gradeData['type'] ?? 'exam';
                    
                    if ($grade >= 1 && $grade <= 10) {
                        $stmt = $pdo->prepare("INSERT INTO grades (student_id, discipline_id, grade, date, type) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$studentId, $disciplineId, $grade, $date, $type]);
                    }
                }
                $pdo->commit();
                $message = 'success|Оценки успешно сохранены';
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = 'error|Ошибка при сохранении: ' . $e->getMessage();
            }
        } else {
            $message = 'error|Выберите группу и дисциплину';
        }
    }
}

$groups = getAllGroups($pdo);
$disciplines = getAllDisciplines($pdo);

$selectedGroup = isset($_GET['group']) ? (int)$_GET['group'] : 0;
$selectedDiscipline = isset($_GET['discipline']) ? (int)$_GET['discipline'] : 0;

$students = [];
if ($selectedGroup > 0) {
    $students = getStudentsByGroup($pdo, $selectedGroup);
}

$pageTitle = 'Журнал оценок';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-edit"></i> Журнал оценок</h1>
        <p>Ввод и редактирование оценок студентов</p>
    </div>
    
    <?php if ($message): ?>
    <script>
        const msg = '<?= escape($message) ?>'.split('|');
        Swal.fire({
            icon: msg[0] === 'success' ? 'success' : 'error',
            title: msg[0] === 'success' ? 'Успешно' : 'Ошибка',
            text: msg[1],
            confirmButtonColor: '#4e54c8',
            timer: msg[0] === 'success' ? 2000 : null
        });
    </script>
    <?php endif; ?>
    
    <!-- Выбор группы и дисциплины -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-filter"></i> Параметры журнала</h3>
        </div>
        <div class="card-body">
            <form method="GET" class="filter-form-inline">
                <div class="form-group">
                    <label for="group">Группа *</label>
                    <select name="group" id="group" required onchange="this.form.submit()">
                        <option value="0">Выберите группу</option>
                        <?php foreach ($groups as $group): ?>
                        <option value="<?= $group['id'] ?>" <?= $selectedGroup === $group['id'] ? 'selected' : '' ?>>
                            <?= escape($group['name']) ?> (<?= $group['course'] ?> курс)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="discipline">Дисциплина *</label>
                    <select name="discipline" id="discipline" required onchange="this.form.submit()">
                        <option value="0">Выберите дисциплину</option>
                        <?php foreach ($disciplines as $discipline): ?>
                        <option value="<?= $discipline['id'] ?>" <?= $selectedDiscipline === $discipline['id'] ? 'selected' : '' ?>>
                            <?= escape($discipline['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($selectedGroup > 0 && $selectedDiscipline > 0 && !empty($students)): ?>
    <!-- Форма ввода оценок -->
    <form method="POST" id="gradesForm">
        <input type="hidden" name="action" value="save_grades">
        <input type="hidden" name="group_id" value="<?= $selectedGroup ?>">
        <input type="hidden" name="discipline_id" value="<?= $selectedDiscipline ?>">
        
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Список студентов для оценки</h3>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Сохранить все оценки
                </button>
            </div>
            <div class="table-body">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>№</th>
                            <th>ФИО студента</th>
                            <th>Оценка (1-10)</th>
                            <th>Дата</th>
                            <th>Тип контроля</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $index => $student): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= escape($student['full_name']) ?></td>
                            <td>
                                <input type="number" 
                                       name="grades[<?= $student['id'] ?>][grade]" 
                                       min="1" 
                                       max="10" 
                                       class="grade-input"
                                       placeholder="1-10"
                                       required>
                            </td>
                            <td>
                                <input type="date" 
                                       name="grades[<?= $student['id'] ?>][date]" 
                                       value="<?= date('Y-m-d') ?>"
                                       class="date-input">
                            </td>
                            <td>
                                <select name="grades[<?= $student['id'] ?>][type]" class="type-select">
                                    <option value="exam">Экзамен</option>
                                    <option value="credit">Зачет</option>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>
    <?php elseif ($selectedGroup > 0 && $selectedDiscipline > 0): ?>
    <div class="card">
        <div class="card-body">
            <p class="empty-message">В выбранной группе нет активных студентов</p>
        </div>
    </div>
    <?php elseif ($selectedGroup > 0 || $selectedDiscipline > 0): ?>
    <div class="card">
        <div class="card-body">
            <p class="empty-message">Выберите группу и дисциплину для начала работы</p>
        </div>
    </div>
    <?php endif; ?>
</main>

<script>
// Валидация оценок перед отправкой
document.getElementById('gradesForm')?.addEventListener('submit', function(e) {
    const gradeInputs = document.querySelectorAll('.grade-input');
    let hasError = false;
    
    gradeInputs.forEach(input => {
        const value = parseInt(input.value);
        if (isNaN(value) || value < 1 || value > 10) {
            input.style.borderColor = '#e74c3c';
            hasError = true;
        } else {
            input.style.borderColor = '#e0e0e0';
        }
    });
    
    if (hasError) {
        e.preventDefault();
        Swal.fire({
            icon: 'error',
            title: 'Ошибка валидации',
            text: 'Оценки должны быть от 1 до 10',
            confirmButtonColor: '#e74c3c'
        });
    } else {
        Swal.fire({
            icon: 'success',
            title: 'Сохранение...',
            text: 'Оценки сохраняются в базу данных',
            timer: 1000,
            showConfirmButton: false
        });
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
