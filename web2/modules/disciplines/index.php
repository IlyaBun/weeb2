<?php
/**
 * Модуль "Дисциплины"
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
$searchQuery = trim($_GET['search'] ?? '');

// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $teacher = trim($_POST['teacher'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $hours = (int)($_POST['hours'] ?? 0);
        
        if ($name) {
            $stmt = $pdo->prepare("INSERT INTO disciplines (name, teacher, department, hours) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $teacher, $department, $hours]);
            $message = 'success|Дисциплина успешно добавлена';
        } else {
            $message = 'error|Введите название дисциплины';
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $teacher = trim($_POST['teacher'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $hours = (int)($_POST['hours'] ?? 0);
        
        if ($id && $name) {
            $stmt = $pdo->prepare("UPDATE disciplines SET name = ?, teacher = ?, department = ?, hours = ? WHERE id = ?");
            $stmt->execute([$name, $teacher, $department, $hours, $id]);
            $message = 'success|Данные обновлены';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM disciplines WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'success|Дисциплина удалена';
        }
    }
}

$sql = "SELECT * FROM disciplines";
$params = [];

if ($searchQuery !== '') {
    $sql .= " WHERE name LIKE ? OR teacher LIKE ? OR department LIKE ?";
    $likeQuery = '%' . $searchQuery . '%';
    $params = [$likeQuery, $likeQuery, $likeQuery];
}

$sql .= " ORDER BY name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$disciplines = $stmt->fetchAll();
$pageTitle = 'Дисциплины';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-book"></i> Дисциплины</h1>
        <p>Учебные предметы и курсы</p>
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
    
    <div class="actions-bar">
        <form method="GET" class="filter-form discipline-search-form">
            <input type="hidden" name="module" value="disciplines">
            <input type="text" name="search" placeholder="Поиск по предмету, преподавателю или кафедре" value="<?= escape($searchQuery) ?>">
            <button type="submit" class="btn btn-secondary">
                <i class="fas fa-search"></i> Найти
            </button>
            <a href="/index.php?module=disciplines" class="btn btn-secondary">
                <i class="fas fa-rotate-left"></i> Сбросить
            </a>
        </form>

        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Добавить дисциплину
        </button>
    </div>
    
    <div class="table-card">
        <div class="table-body">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>№</th>
                        <th>Название</th>
                        <th>Преподаватель</th>
                        <th>Кафедра</th>
                        <th>Часов</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($disciplines as $index => $discipline): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><strong><?= escape($discipline['name']) ?></strong></td>
                        <td><?= escape($discipline['teacher']) ?></td>
                        <td><?= escape($discipline['department']) ?></td>
                        <td><?= $discipline['hours'] ?> ч.</td>
                        <td class="actions">
                            <button class="btn-icon" onclick="editDiscipline(<?= htmlspecialchars(json_encode($discipline)) ?>)" title="Редактировать">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon btn-danger" onclick="deleteDiscipline(<?= $discipline['id'] ?>, '<?= escape($discipline['name']) ?>')" title="Удалить">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Модальное окно -->
<div id="disciplineModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Добавить дисциплину</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="disciplineForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="disciplineId">
            
            <div class="form-group">
                <label for="name">Название *</label>
                <input type="text" id="name" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="teacher">Преподаватель</label>
                <input type="text" id="teacher" name="teacher">
            </div>
            
            <div class="form-group">
                <label for="department">Кафедра</label>
                <input type="text" id="department" name="department">
            </div>
            
            <div class="form-group">
                <label for="hours">Количество часов</label>
                <input type="number" id="hours" name="hours" min="0">
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Отмена</button>
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Добавить дисциплину';
    document.getElementById('formAction').value = 'add';
    document.getElementById('disciplineId').value = '';
    document.getElementById('name').value = '';
    document.getElementById('teacher').value = '';
    document.getElementById('department').value = '';
    document.getElementById('hours').value = '';
    document.getElementById('disciplineModal').classList.add('show');
}

function editDiscipline(discipline) {
    document.getElementById('modalTitle').textContent = 'Редактировать дисциплину';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('disciplineId').value = discipline.id;
    document.getElementById('name').value = discipline.name;
    document.getElementById('teacher').value = discipline.teacher;
    document.getElementById('department').value = discipline.department;
    document.getElementById('hours').value = discipline.hours;
    document.getElementById('disciplineModal').classList.add('show');
}

function closeModal() {
    document.getElementById('disciplineModal').classList.remove('show');
}

function deleteDiscipline(id, name) {
    Swal.fire({
        title: 'Удаление дисциплины',
        text: `Вы действительно хотите удалить "${name}"?`,
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
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
