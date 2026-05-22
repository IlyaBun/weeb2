<?php
/**
 * Модуль "Пользователи" (только для администратора)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: /index.php?error=access_denied');
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

$pdo = getDB();
$message = '';
$studentsList = getActiveStudentsForSelect($pdo);
$searchQuery = trim($_GET['search'] ?? '');
$sortRole = trim($_GET['sort_role'] ?? 'default');
$studentSearchOptions = array_map(function ($student) {
    return [
        'value' => (string)$student['id'],
        'text' => $student['full_name'] . ' (' . $student['group_name'] . ')'
    ];
}, $studentsList);

// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $login = trim($_POST['login'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $role = $_POST['role'] ?? 'teacher';
        $studentId = (int)($_POST['student_id'] ?? 0);
        $studentId = $role === 'student' ? $studentId : null;
        
        if ($login && $password && $full_name && ($role !== 'student' || $studentId)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO users (login, password, role, full_name, student_id) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$login, $password, $role, $full_name, $studentId]);
                $message = 'success|Пользователь успешно добавлен';
            } catch (PDOException $e) {
                $message = 'error|Логин уже существует';
            }
        } else {
            $message = 'error|Заполните все обязательные поля и выберите студента для роли Student';
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $role = $_POST['role'] ?? 'teacher';
        $studentId = (int)($_POST['student_id'] ?? 0);
        $studentId = $role === 'student' ? $studentId : null;
        
        if ($id && $full_name && ($role !== 'student' || $studentId)) {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, role = ?, student_id = ? WHERE id = ?");
            $stmt->execute([$full_name, $role, $studentId, $id]);
            $message = 'success|Данные обновлены';
        } else {
            $message = 'error|Для роли Student нужно выбрать карточку студента';
        }
    } elseif ($action === 'change_password') {
        $id = (int)($_POST['id'] ?? 0);
        $password = trim($_POST['password'] ?? '');
        
        if ($id && $password) {
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$password, $id]);
            $message = 'success|Пароль изменен';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        
        // Нельзя удалить самого себя
        if ($id && $id !== $_SESSION['user_id']) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $message = 'success|Пользователь удален';
        } else {
            $message = 'error|Нельзя удалить текущего пользователя';
        }
    }
}

$sql = "SELECT u.*, s.full_name as linked_student_name
        FROM users u
        LEFT JOIN students s ON u.student_id = s.id";
$params = [];

if ($searchQuery !== '') {
    $sql .= " WHERE u.full_name LIKE ?";
    $params[] = '%' . $searchQuery . '%';
}

if ($sortRole === 'role') {
    $sql .= " ORDER BY CASE u.role
                    WHEN 'admin' THEN 1
                    WHEN 'teacher' THEN 2
                    WHEN 'student' THEN 3
                    ELSE 4
                END ASC, u.full_name ASC";
} else {
    $sql .= " ORDER BY u.id ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
$pageTitle = 'Пользователи';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-users-cog"></i> Пользователи</h1>
        <p>Управление пользователями системы</p>
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
        <form method="GET" class="filter-form users-filter-form">
            <input type="hidden" name="module" value="users">
            <input type="text" name="search" placeholder="Поиск по ФИО" value="<?= escape($searchQuery) ?>">
            <select name="sort_role">
                <option value="default" <?= $sortRole === 'default' ? 'selected' : '' ?>>Сортировка по умолчанию</option>
                <option value="role" <?= $sortRole === 'role' ? 'selected' : '' ?>>По ролям: Администратор, Преподаватель, Студент</option>
            </select>
            <button type="submit" class="btn btn-secondary">
                <i class="fas fa-search"></i> Применить
            </button>
            <a href="/index.php?module=users" class="btn btn-secondary">
                <i class="fas fa-rotate-left"></i> Сбросить
            </a>
        </form>

        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Добавить пользователя
        </button>
    </div>
    
    <div class="table-card">
        <div class="table-body">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>№</th>
                        <th>Логин</th>
                        <th>ФИО</th>
                        <th>Роль</th>
                        <th>Карточка</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $index => $user): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><code><?= escape($user['login']) ?></code></td>
                        <td><?= escape($user['full_name']) ?></td>
                        <td>
                            <?php if ($user['role'] === 'admin'): ?>
                            <span class="badge badge-primary">Администратор</span>
                            <?php elseif ($user['role'] === 'student'): ?>
                            <span class="badge badge-success">Студент</span>
                            <?php else: ?>
                            <span class="badge badge-secondary">Преподаватель</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $user['role'] === 'student' && $user['linked_student_name'] ? escape($user['linked_student_name']) : '—' ?></td>
                        <td class="actions">
                            <button class="btn-icon" onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)" title="Редактировать">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-icon" onclick="changePassword(<?= $user['id'] ?>, '<?= escape($user['login']) ?>')" title="Сменить пароль">
                                <i class="fas fa-key"></i>
                            </button>
                            <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                            <button class="btn-icon btn-danger" onclick="deleteUser(<?= $user['id'] ?>, '<?= escape($user['login']) ?>')" title="Удалить">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Модальное окно добавления/редактирования -->
<div id="userModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Добавить пользователя</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST" id="userForm">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="userId">
            
            <div class="form-group">
                <label for="login">Логин *</label>
                <input type="text" id="login" name="login" required>
            </div>
            
            <div class="form-group" id="passwordGroup">
                <label for="password">Пароль *</label>
                <input type="text" id="password" name="password">
            </div>
            
            <div class="form-group">
                <label for="fullName">ФИО *</label>
                <input type="text" id="fullName" name="full_name" required>
            </div>
            
            <div class="form-group">
                <label for="role">Роль *</label>
                <select id="role" name="role" required onchange="toggleStudentField()">
                    <option value="teacher">Преподаватель</option>
                    <option value="admin">Администратор</option>
                    <option value="student">Студент</option>
                </select>
            </div>

            <div class="form-group" id="studentLinkGroup" style="display: none;">
                <label for="studentLink">Карточка студента *</label>
                <div class="search-select">
                    <input type="hidden" id="studentLink" name="student_id" value="0">
                    <input
                        type="text"
                        id="studentSearch"
                        placeholder="Начните вводить ФИО студента"
                        autocomplete="off"
                        onfocus="openStudentDropdown()"
                        oninput="filterStudentOptions()"
                        onblur="closeStudentDropdownLater()"
                    >
                    <div id="studentDropdown" class="search-select-dropdown"></div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Отмена</button>
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно смены пароля -->
<div id="passwordModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Смена пароля</h3>
            <button class="modal-close" onclick="closePasswordModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="id" id="passwordUserId">
            
            <p style="margin-bottom: 15px; color: #666;">
                Пользователь: <strong id="passwordUserLogin"></strong>
            </p>
            
            <div class="form-group">
                <label for="newPassword">Новый пароль *</label>
                <input type="text" id="newPassword" name="password" required>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePasswordModal()">Отмена</button>
                <button type="submit" class="btn btn-primary">Изменить пароль</button>
            </div>
        </form>
    </div>
</div>

<script>
const studentOptions = <?= json_encode($studentSearchOptions, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Добавить пользователя';
    document.getElementById('formAction').value = 'add';
    document.getElementById('userId').value = '';
    document.getElementById('login').value = '';
    document.getElementById('password').value = '';
    document.getElementById('fullName').value = '';
    document.getElementById('role').value = 'teacher';
    document.getElementById('studentSearch').value = '';
    document.getElementById('studentLink').value = '0';
    document.getElementById('passwordGroup').style.display = 'block';
    document.getElementById('password').required = true;
    renderStudentOptions();
    toggleStudentField();
    document.getElementById('userModal').classList.add('show');
}

function editUser(user) {
    document.getElementById('modalTitle').textContent = 'Редактировать пользователя';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('userId').value = user.id;
    document.getElementById('login').value = user.login;
    document.getElementById('login').readOnly = true;
    document.getElementById('fullName').value = user.full_name;
    document.getElementById('role').value = user.role;
    document.getElementById('studentLink').value = user.student_id ?? '0';
    document.getElementById('studentSearch').value = getStudentOptionLabel(user.student_id ?? '0');
    document.getElementById('passwordGroup').style.display = 'none';
    document.getElementById('password').required = false;
    renderStudentOptions(user.student_id ?? '0');
    toggleStudentField();
    document.getElementById('userModal').classList.add('show');
}

function closeModal() {
    document.getElementById('userModal').classList.remove('show');
    document.getElementById('login').readOnly = false;
    closeStudentDropdown();
}

function renderStudentOptions(selectedValue = null) {
    const select = document.getElementById('studentLink');
    const dropdown = document.getElementById('studentDropdown');
    const searchValue = document.getElementById('studentSearch').value.trim().toLowerCase();
    const currentValue = selectedValue ?? select.value;

    const filteredOptions = studentOptions.filter((option) => {
        return option.text.toLowerCase().includes(searchValue);
    });

    dropdown.innerHTML = '';

    if (!filteredOptions.length) {
        dropdown.innerHTML = '<div class="search-select-empty">Ничего не найдено</div>';
        select.value = filteredOptions.some((option) => option.value === String(currentValue)) ? String(currentValue) : '0';
        return;
    }

    filteredOptions.forEach((optionData) => {
        const option = document.createElement('button');
        option.type = 'button';
        option.className = 'search-select-option';
        if (optionData.value === String(currentValue)) {
            option.classList.add('active');
        }
        option.textContent = optionData.text;
        option.onmousedown = function () {
            selectStudent(optionData.value);
            return false;
        };
        dropdown.appendChild(option);
    });
}

function filterStudentOptions() {
    document.getElementById('studentLink').value = '0';
    renderStudentOptions();
    openStudentDropdown();
}

function getStudentOptionLabel(value) {
    const match = studentOptions.find((option) => option.value === String(value));
    return match ? match.text : '';
}

function selectStudent(value) {
    document.getElementById('studentLink').value = String(value);
    document.getElementById('studentSearch').value = getStudentOptionLabel(value);
    renderStudentOptions(value);
    closeStudentDropdown();
}

function openStudentDropdown() {
    const dropdown = document.getElementById('studentDropdown');
    dropdown.classList.add('show');
    renderStudentOptions(document.getElementById('studentLink').value);
}

function closeStudentDropdown() {
    document.getElementById('studentDropdown').classList.remove('show');
}

function closeStudentDropdownLater() {
    window.setTimeout(closeStudentDropdown, 150);
}

function toggleStudentField() {
    const role = document.getElementById('role').value;
    const group = document.getElementById('studentLinkGroup');
    const select = document.getElementById('studentLink');
    const search = document.getElementById('studentSearch');

    if (role === 'student') {
        group.style.display = 'block';
        search.required = false;
        if (select.value !== '0') {
            search.value = getStudentOptionLabel(select.value);
        }
        renderStudentOptions(select.value);
    } else {
        group.style.display = 'none';
        select.value = '0';
        search.value = '';
        closeStudentDropdown();
    }
}

document.addEventListener('click', (event) => {
    const container = document.querySelector('#studentLinkGroup .search-select');
    if (container && !container.contains(event.target)) {
        closeStudentDropdown();
    }
});

function changePassword(id, login) {
    document.getElementById('passwordUserId').value = id;
    document.getElementById('passwordUserLogin').textContent = login;
    document.getElementById('newPassword').value = '';
    document.getElementById('passwordModal').classList.add('show');
}

function closePasswordModal() {
    document.getElementById('passwordModal').classList.remove('show');
}

function deleteUser(id, login) {
    Swal.fire({
        title: 'Удаление пользователя',
        text: `Вы действительно хотите удалить пользователя "${login}"?`,
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
