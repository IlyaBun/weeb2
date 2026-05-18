<?php
/**
 * Модуль "Пользователи" (только для администратора)
 */
session_start();

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

// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $login = trim($_POST['login'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $role = $_POST['role'] ?? 'teacher';
        
        if ($login && $password && $full_name) {
            try {
                $stmt = $pdo->prepare("INSERT INTO users (login, password, role, full_name) VALUES (?, ?, ?, ?)");
                $stmt->execute([$login, $password, $role, $full_name]);
                $message = 'success|Пользователь успешно добавлен';
            } catch (PDOException $e) {
                $message = 'error|Логин уже существует';
            }
        } else {
            $message = 'error|Заполните все обязательные поля';
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $full_name = trim($_POST['full_name'] ?? '');
        $role = $_POST['role'] ?? 'teacher';
        
        if ($id && $full_name) {
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, role = ? WHERE id = ?");
            $stmt->execute([$full_name, $role, $id]);
            $message = 'success|Данные обновлены';
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

$users = $pdo->query("SELECT * FROM users ORDER BY id")->fetchAll();
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
            confirmButtonColor: '#4e54c8'
        });
    </script>
    <?php endif; ?>
    
    <div class="actions-bar">
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
                            <?php else: ?>
                            <span class="badge badge-secondary">Преподаватель</span>
                            <?php endif; ?>
                        </td>
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
                <select id="role" name="role" required>
                    <option value="teacher">Преподаватель</option>
                    <option value="admin">Администратор</option>
                </select>
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
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Добавить пользователя';
    document.getElementById('formAction').value = 'add';
    document.getElementById('userId').value = '';
    document.getElementById('login').value = '';
    document.getElementById('password').value = '';
    document.getElementById('fullName').value = '';
    document.getElementById('role').value = 'teacher';
    document.getElementById('passwordGroup').style.display = 'block';
    document.getElementById('password').required = true;
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
    document.getElementById('passwordGroup').style.display = 'none';
    document.getElementById('password').required = false;
    document.getElementById('userModal').classList.add('show');
}

function closeModal() {
    document.getElementById('userModal').classList.remove('show');
    document.getElementById('login').readOnly = false;
}

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
