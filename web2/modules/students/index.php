<?php
/**
 * Модуль "Студенты"
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
$message = '';
$studentPageId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$canManageStudentData = in_array($_SESSION['role'], ['admin', 'teacher'], true);
$currentUserStudentId = (int)($_SESSION['student_id'] ?? 0);

function buildStudentPageUrl($studentId, $filters = [], $flash = null) {
    $params = array_filter([
        'module' => 'students',
        'student_id' => $studentId,
        'discipline' => $filters['discipline'] ?? null,
        'type' => $filters['type'] ?? null,
        'date_from' => $filters['date_from'] ?? null,
        'date_to' => $filters['date_to'] ?? null,
        'sort' => $filters['sort'] ?? null,
        'order' => $filters['order'] ?? null,
        'flash' => $flash,
        'export' => $filters['export'] ?? null,
    ], function ($value) {
        return $value !== null && $value !== '' && $value !== 0;
    });

    return '/index.php?' . http_build_query($params);
}

// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!$canManageStudentData && in_array($action, ['add', 'edit', 'delete', 'delete_grade'], true)) {
        header('Location: /index.php?module=students&student_id=' . $currentUserStudentId);
        exit;
    }
    
    if ($action === 'add') {
        $full_name = trim($_POST['full_name'] ?? '');
        $group_id = (int)($_POST['group_id'] ?? 0);
        $birth_date = $_POST['birth_date'] ?? '';
        
        if ($full_name && $group_id) {
            $stmt = $pdo->prepare("INSERT INTO students (group_id, full_name, birth_date, status) VALUES (?, ?, ?, 'active')");
            $stmt->execute([$group_id, $full_name, $birth_date]);
            $message = 'success|Студент успешно добавлен';
        } else {
            $message = 'error|Заполните обязательные поля';
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $group_id = (int)($_POST['group_id'] ?? 0);
        $birth_date = $_POST['birth_date'] ?? '';
        
        if ($id && $full_name && $group_id) {
            $stmt = $pdo->prepare("UPDATE students SET group_id = ?, full_name = ?, birth_date = ? WHERE id = ?");
            $stmt->execute([$group_id, $full_name, $birth_date, $id]);
            $message = 'success|Данные студента обновлены';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            // Мягкое удаление - установка статуса expelled
            $stmt = $pdo->prepare("UPDATE students SET status = 'expelled' WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'success|Студент удален';
        }
    } elseif ($action === 'delete_grade') {
        $gradeId = (int)($_POST['grade_id'] ?? 0);
        $studentId = (int)($_POST['student_id'] ?? 0);
        $filters = [
            'discipline' => (int)($_POST['discipline'] ?? 0),
            'type' => trim($_POST['type'] ?? ''),
            'date_from' => trim($_POST['date_from'] ?? ''),
            'date_to' => trim($_POST['date_to'] ?? ''),
            'sort' => $_POST['sort'] ?? 'date',
            'order' => $_POST['order'] ?? 'desc',
        ];

        if ($gradeId && $studentId) {
            $stmt = $pdo->prepare("DELETE FROM grades WHERE id = ? AND student_id = ?");
            $stmt->execute([$gradeId, $studentId]);
            header('Location: ' . buildStudentPageUrl($studentId, $filters, 'success|Оценка удалена'));
            exit;
        }
    }
}

if (isset($_GET['flash']) && !$message) {
    $message = $_GET['flash'];
}

if ($_SESSION['role'] === 'student' && $studentPageId !== $currentUserStudentId) {
    header('Location: /index.php?module=students&student_id=' . $currentUserStudentId);
    exit;
}

if ($studentPageId > 0) {
    $student = getStudentById($pdo, $studentPageId);

    if (!$student) {
        header('Location: /index.php?module=students&error=student_not_found');
        exit;
    }

    $selectedDiscipline = isset($_GET['discipline']) ? (int)$_GET['discipline'] : 0;
    $selectedType = trim($_GET['type'] ?? '');
    $dateFrom = trim($_GET['date_from'] ?? '');
    $dateTo = trim($_GET['date_to'] ?? '');
    $sortField = $_GET['sort'] ?? 'date';
    $sortOrder = $_GET['order'] ?? 'desc';
    $exportFormat = $_GET['export'] ?? '';

    $gradeFilters = [
        'discipline_id' => $selectedDiscipline,
        'type' => $selectedType,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'sort' => $sortField,
        'order' => $sortOrder,
    ];

    $filteredGrades = getStudentGradesFiltered($pdo, $studentPageId, $gradeFilters);
    $filteredAverage = calculateStudentAverageFiltered($pdo, $studentPageId, $gradeFilters);
    $disciplines = getAllDisciplines($pdo);
    $gradeTypes = getGradeTypeOptions();
    $subjectStats = getStudentSubjectStatsFiltered($pdo, $studentPageId, $gradeFilters);
    $bestSubject = $subjectStats[0] ?? null;
    $worstSubject = !empty($subjectStats) ? $subjectStats[count($subjectStats) - 1] : null;

    if ($exportFormat === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="student-' . $studentPageId . '-grades.xls"');
        echo "\xEF\xBB\xBF";
        ?>
        <table border="1">
            <thead>
                <tr>
                    <th>Предмет</th>
                    <th>Оценка</th>
                    <th>Тип</th>
                    <th>Дата</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filteredGrades as $grade): ?>
                <tr>
                    <td><?= escape($grade['discipline_name']) ?></td>
                    <td><?= (int)$grade['grade'] ?></td>
                    <td><?= escape(getGradeTypeLabel($grade['type'])) ?></td>
                    <td style="mso-number-format:'\@';"><?= escape(formatDate($grade['date'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        exit;
    }

    $pageTitle = 'Страница студента';

    include __DIR__ . '/../../includes/header.php';
    include __DIR__ . '/../../includes/sidebar.php';
    ?>

    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-id-card"></i> Страница студента</h1>
            <p><?= escape($student['full_name']) ?></p>
        </div>

        <div class="actions-bar">
            <?php if ($canManageStudentData): ?>
            <a href="/index.php?module=students" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Назад к списку
            </a>
            <?php endif; ?>
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <a href="<?= escape(buildStudentPageUrl($studentPageId, [
                    'discipline' => $selectedDiscipline,
                    'type' => $selectedType,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'sort' => $sortField,
                    'order' => $sortOrder,
                    'export' => 'excel',
                ])) ?>" class="btn btn-secondary">
                    <i class="fas fa-file-excel"></i> Экспорт Excel
                </a>
                <button type="button" class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> Печать
                </button>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-icon" style="background: rgba(0, 74, 9, 0.1);">
                    <i class="fas fa-user-graduate" style="color: #004a09;"></i>
                </div>
                <div class="kpi-content">
                    <h3><?= number_format($filteredAverage, 2) ?></h3>
                    <p>Средний балл по текущему фильтру</p>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon" style="background: rgba(52, 152, 219, 0.1);">
                    <i class="fas fa-layer-group" style="color: #3498db;"></i>
                </div>
                <div class="kpi-content">
                    <h3><?= count($filteredGrades) ?></h3>
                    <p>Оценок в выборке</p>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon" style="background: rgba(46, 204, 113, 0.1);">
                    <i class="fas fa-trophy" style="color: #2ecc71;"></i>
                </div>
                <div class="kpi-content">
                    <h3><?= $bestSubject ? number_format($bestSubject['avg_grade'], 2) : '-' ?></h3>
                    <p>Лучший предмет</p>
                    <small><?= $bestSubject ? escape($bestSubject['discipline_name']) : 'Нет данных' ?></small>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon" style="background: rgba(231, 76, 60, 0.1);">
                    <i class="fas fa-chart-line-down" style="color: #e74c3c;"></i>
                </div>
                <div class="kpi-content">
                    <h3><?= $worstSubject ? number_format($worstSubject['avg_grade'], 2) : '-' ?></h3>
                    <p>Слабый предмет</p>
                    <small><?= $worstSubject ? escape($worstSubject['discipline_name']) : 'Нет данных' ?></small>
                </div>
            </div>
        </div>

        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h3><i class="fas fa-user"></i> Информация о студенте</h3>
            </div>
            <div class="card-body">
                <p><strong>ФИО:</strong> <?= escape($student['full_name']) ?></p>
                <p><strong>Группа:</strong> <?= escape($student['group_name']) ?></p>
                <p><strong>Курс:</strong> <?= (int)$student['course'] ?></p>
                <p><strong>Специальность:</strong> <?= escape($student['specialty']) ?></p>
                <p><strong>Дата рождения:</strong> <?= $student['birth_date'] ? formatDate($student['birth_date']) : 'Не указана' ?></p>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Фильтры оценок</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="filter-form-inline">
                    <input type="hidden" name="module" value="students">
                    <input type="hidden" name="student_id" value="<?= $studentPageId ?>">

                    <div class="form-group">
                        <label for="discipline">Предмет</label>
                        <select name="discipline" id="discipline">
                            <option value="0">Все предметы</option>
                            <?php foreach ($disciplines as $discipline): ?>
                            <option value="<?= $discipline['id'] ?>" <?= $selectedDiscipline === (int)$discipline['id'] ? 'selected' : '' ?>>
                                <?= escape($discipline['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="type">Тип контроля</label>
                        <select name="type" id="type">
                            <option value="">Все типы</option>
                            <?php foreach ($gradeTypes as $typeValue => $typeLabel): ?>
                            <option value="<?= escape($typeValue) ?>" <?= $selectedType === $typeValue ? 'selected' : '' ?>>
                                <?= escape($typeLabel) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="date_from">Дата с</label>
                        <input type="date" name="date_from" id="date_from" value="<?= escape($dateFrom) ?>">
                    </div>

                    <div class="form-group">
                        <label for="date_to">Дата по</label>
                        <input type="date" name="date_to" id="date_to" value="<?= escape($dateTo) ?>">
                    </div>

                    <div class="form-group">
                        <label for="sort">Сортировать по</label>
                        <select name="sort" id="sort">
                            <option value="date" <?= $sortField === 'date' ? 'selected' : '' ?>>Дате</option>
                            <option value="discipline" <?= $sortField === 'discipline' ? 'selected' : '' ?>>Предмету</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="order">Порядок</label>
                        <select name="order" id="order">
                            <option value="desc" <?= $sortOrder === 'desc' ? 'selected' : '' ?>>По убыванию</option>
                            <option value="asc" <?= $sortOrder === 'asc' ? 'selected' : '' ?>>По возрастанию</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sync"></i> Применить
                    </button>
                    <a href="/index.php?module=students&student_id=<?= $studentPageId ?>" class="btn btn-secondary">
                        <i class="fas fa-rotate-left"></i> Сбросить
                    </a>
                </form>
            </div>
        </div>

        <div class="table-card" style="margin-top: 24px;">
            <div class="table-header">
                <h3><i class="fas fa-list"></i> Оценки студента</h3>
            </div>
            <div class="table-body">
                <?php if (!empty($filteredGrades)): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Предмет</th>
                            <th>Оценка</th>
                            <th>Тип</th>
                            <th>Дата</th>
                            <?php if ($canManageStudentData): ?>
                            <th>Действие</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filteredGrades as $grade): ?>
                        <tr>
                            <td><?= escape($grade['discipline_name']) ?></td>
                            <td>
                                <span class="grade-badge" style="background: <?= getGradeColor($grade['grade']) ?>;">
                                    <?= (int)$grade['grade'] ?>
                                </span>
                            </td>
                            <td><?= escape(getGradeTypeLabel($grade['type'])) ?></td>
                            <td><?= formatDate($grade['date']) ?></td>
                            <?php if ($canManageStudentData): ?>
                            <td class="actions">
                                <button class="btn-icon btn-danger" onclick="deleteGrade(<?= (int)$grade['id'] ?>, '<?= escape($grade['discipline_name']) ?>', <?= $studentPageId ?>)" title="Удалить оценку">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p class="empty-message">По выбранным фильтрам оценки не найдены</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
    function deleteGrade(gradeId, disciplineName, studentId) {
        Swal.fire({
            title: 'Удаление оценки',
            text: `Удалить оценку по предмету "${disciplineName}"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e74c3c',
            cancelButtonColor: '#95a5a6',
            confirmButtonText: 'Да, удалить',
            cancelButtonText: 'Отмена'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_grade">
                    <input type="hidden" name="grade_id" value="${gradeId}">
                    <input type="hidden" name="student_id" value="${studentId}">
                    <input type="hidden" name="discipline" value="<?= (int)$selectedDiscipline ?>">
                    <input type="hidden" name="type" value="<?= escape($selectedType) ?>">
                    <input type="hidden" name="date_from" value="<?= escape($dateFrom) ?>">
                    <input type="hidden" name="date_to" value="<?= escape($dateTo) ?>">
                    <input type="hidden" name="sort" value="<?= escape($sortField) ?>">
                    <input type="hidden" name="order" value="<?= escape($sortOrder) ?>">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
    </script>

    <?php
    include __DIR__ . '/../../includes/footer.php';
    return;
}

// Получение параметров фильтрации
$filterGroup = isset($_GET['group']) ? (int)$_GET['group'] : 0;
$searchQuery = trim($_GET['search'] ?? '');

// Формирование SQL запроса
$sql = "SELECT s.*, g.name as group_name, g.course 
        FROM students s 
        JOIN groups g ON s.group_id = g.id 
        WHERE s.status = 'active'";
$params = [];

if ($filterGroup > 0) {
    $sql .= " AND s.group_id = ?";
    $params[] = $filterGroup;
}

if ($searchQuery) {
    $sql .= " AND s.full_name LIKE ?";
    $params[] = "%$searchQuery%";
}

$sql .= " ORDER BY s.full_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Расчет среднего балла для каждого студента
foreach ($students as &$student) {
    $student['avg_grade'] = calculateStudentAverage($student['id'], $pdo);
}

$groups = getAllGroups($pdo);
$pageTitle = 'Студенты';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-user-graduate"></i> Студенты</h1>
        <p>Управление списком студентов</p>
    </div>
    
    <?php if ($message): ?>
    <script>
        const msg = '<?= escape($message) ?>'.split('|');
        Swal.fire({
            icon: msg[0] === 'success' ? 'success' : 'error',
            title: msg[0] === 'success' ? 'Успешно' : 'Ошибка',
            text: msg[1],
            confirmButtonColor: '#004a09'
        });
    </script>
    <?php endif; ?>
    
    <!-- Фильтры и действия -->
    <div class="actions-bar">
        <div class="filters">
            <form method="GET" class="filter-form">
                <input type="hidden" name="module" value="students">
                <select name="group" onchange="this.form.submit()">
                    <option value="0">Все группы</option>
                    <?php foreach ($groups as $group): ?>
                    <option value="<?= $group['id'] ?>" <?= $filterGroup === $group['id'] ? 'selected' : '' ?>>
                        <?= escape($group['name']) ?> (<?= $group['course'] ?> курс)
                    </option>
                    <?php endforeach; ?>
                </select>
                
                <input type="text" name="search" placeholder="Поиск по ФИО" value="<?= escape($searchQuery) ?>">
                <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i></button>
            </form>
        </div>
        
        <?php if ($canManageStudentData): ?>
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Добавить студента
        </button>
        <?php endif; ?>
    </div>
    
    <!-- Таблица студентов -->
    <div class="table-card">
        <div class="table-body">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>№</th>
                        <th>ФИО</th>
                        <th>Группа</th>
                        <th>Курс</th>
                        <th>Средний балл</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $index => $student): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td>
                            <a href="/index.php?module=students&student_id=<?= $student['id'] ?>" class="student-link">
                                <?= escape($student['full_name']) ?>
                            </a>
                        </td>
                        <td><?= escape($student['group_name']) ?></td>
                        <td><?= $student['course'] ?></td>
                        <td>
                            <span class="grade-badge" style="background: <?= getGradeColor($student['avg_grade']) ?>;">
                                <?= number_format($student['avg_grade'], 2) ?>
                            </span>
                        </td>
                        <td class="actions">
                            <?php if ($canManageStudentData): ?>
                            <button class="btn-icon" onclick="viewStudent(<?= $student['id'] ?>); return false;" title="Быстрый просмотр">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn-icon" onclick="editStudent(<?= htmlspecialchars(json_encode($student)) ?>)" title="Редактировать">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon btn-danger" onclick="deleteStudent(<?= $student['id'] ?>, '<?= escape($student['full_name']) ?>')" title="Удалить">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($students)): ?>
            <p class="empty-message">Студенты не найдены</p>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Модальное окно добавления/редактирования -->
<div id="studentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Добавить студента</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="studentForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="studentId">
            
            <div class="form-group">
                <label for="fullName">ФИО *</label>
                <input type="text" id="fullName" name="full_name" required>
            </div>
            
            <div class="form-group">
                <label for="groupId">Группа *</label>
                <select id="groupId" name="group_id" required>
                    <option value="">Выберите группу</option>
                    <?php foreach ($groups as $group): ?>
                    <option value="<?= $group['id'] ?>"><?= escape($group['name']) ?> (<?= $group['course'] ?> курс)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="birthDate">Дата рождения</label>
                <input type="date" id="birthDate" name="birth_date">
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Отмена</button>
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно просмотра студента -->
<div id="viewModal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h3>Карточка студента</h3>
            <button class="modal-close" onclick="closeViewModal()">&times;</button>
        </div>
        <div id="viewContent"></div>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Добавить студента';
    document.getElementById('formAction').value = 'add';
    document.getElementById('studentId').value = '';
    document.getElementById('fullName').value = '';
    document.getElementById('groupId').value = '';
    document.getElementById('birthDate').value = '';
    document.getElementById('studentModal').classList.add('show');
}

function editStudent(student) {
    document.getElementById('modalTitle').textContent = 'Редактировать студента';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('studentId').value = student.id;
    document.getElementById('fullName').value = student.full_name;
    document.getElementById('groupId').value = student.group_id;
    document.getElementById('birthDate').value = student.birth_date;
    document.getElementById('studentModal').classList.add('show');
}

function closeModal() {
    document.getElementById('studentModal').classList.remove('show');
}

function deleteStudent(id, name) {
    Swal.fire({
        title: 'Удаление студента',
        text: `Вы действительно хотите удалить студента "${name}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e74c3c',
        cancelButtonColor: '#95a5a6',
        confirmButtonText: 'Да, удалить',
        cancelButtonText: 'Отмена'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="${id}">`;
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function viewStudent(id) {
    fetch(`/api/student.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            let gradesHtml = '';
            data.grades.forEach(grade => {
                gradesHtml += `
                    <tr>
                        <td>${escapeHtml(grade.discipline_name)}</td>
                        <td><span class="grade-badge" style="background: ${getGradeColor(grade.grade)}">${grade.grade}</span></td>
                        <td>${getGradeTypeLabel(grade.type)}</td>
                        <td>${formatDate(grade.date)}</td>
                    </tr>
                `;
            });
            
            document.getElementById('viewContent').innerHTML = `
                <div class="student-card">
                    <h4>${escapeHtml(data.student.full_name)}</h4>
                    <p><strong>Группа:</strong> ${escapeHtml(data.student.group_name)}</p>
                    <p><strong>Курс:</strong> ${data.student.course}</p>
                    <p><strong>Дата рождения:</strong> ${formatDate(data.student.birth_date)}</p>
                    <p><strong>Средний балл:</strong> <span class="grade-badge" style="background: ${getGradeColor(data.avg_grade)}">${data.avg_grade.toFixed(2)}</span></p>
                    <p style="margin: 15px 0;">
                        <a class="btn btn-secondary" href="/index.php?module=students&student_id=${data.student.id}">
                            <i class="fas fa-external-link-alt"></i> Открыть отдельную страницу
                        </a>
                    </p>
                    
                    <h5>Оценки</h5>
                    <table class="data-table">
                        <thead>
                            <tr><th>Предмет</th><th>Оценка</th><th>Тип</th><th>Дата</th></tr>
                        </thead>
                        <tbody>${gradesHtml}</tbody>
                    </table>
                </div>
            `;
            document.getElementById('viewModal').classList.add('show');
        });
}

function closeViewModal() {
    document.getElementById('viewModal').classList.remove('show');
}

function getGradeTypeLabel(type) {
    const labels = {
        exam: 'Экзамен',
        practice: 'Практика',
        lab: 'Лабораторная'
    };

    return labels[type] || type;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('ru-RU');
}

function getGradeColor(grade) {
    if (grade >= 9) return '#2ecc71';
    if (grade >= 7) return '#3498db';
    if (grade >= 4) return '#f1c40f';
    return '#e74c3c';
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
