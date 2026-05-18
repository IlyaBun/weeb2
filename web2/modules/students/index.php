<?php
/**
 * Модуль "Студенты"
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
    }
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
            confirmButtonColor: '#4e54c8'
        });
    </script>
    <?php endif; ?>
    
    <!-- Фильтры и действия -->
    <div class="actions-bar">
        <div class="filters">
            <form method="GET" class="filter-form">
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
        
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Добавить студента
        </button>
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
                            <a href="#" onclick="viewStudent(<?= $student['id'] ?>); return false;" class="student-link">
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
                            <button class="btn-icon" onclick="editStudent(<?= htmlspecialchars(json_encode($student)) ?>)" title="Редактировать">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon btn-danger" onclick="deleteStudent(<?= $student['id'] ?>, '<?= escape($student['full_name']) ?>')" title="Удалить">
                                <i class="fas fa-trash"></i>
                            </button>
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
                        <td>${grade.type === 'exam' ? 'Экзамен' : 'Зачет'}</td>
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
