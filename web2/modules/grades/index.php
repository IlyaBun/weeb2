<?php
/**
 * Модуль "Журнал оценок"
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

function saveGradeRecord($pdo, $studentId, $disciplineId, $grade, $date, $type) {
    $stmt = $pdo->prepare("INSERT INTO grades (student_id, discipline_id, grade, date, type) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$studentId, $disciplineId, $grade, $date, $type]);
}

function buildGradesRedirectUrl($groupId, $disciplineId, $status, $text, $journalType = '', $dateFrom = '', $dateTo = '', $mode = 'entry') {
    $params = [
        'module' => 'grades',
        'group' => (int)$groupId,
        'discipline' => (int)$disciplineId,
        'flash' => $status . '|' . $text,
        'mode' => $mode,
    ];

    if ($journalType !== '') {
        $params['journal_type'] = $journalType;
    }

    if ($dateFrom !== '') {
        $params['date_from'] = $dateFrom;
    }

    if ($dateTo !== '') {
        $params['date_to'] = $dateTo;
    }

    return '/index.php?' . http_build_query($params);
}

// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_grades') {
        $groupId = (int)($_POST['group_id'] ?? 0);
        $disciplineId = (int)($_POST['discipline_id'] ?? 0);
        $journalType = trim($_POST['journal_type'] ?? '');
        $dateFromFilter = trim($_POST['date_from_filter'] ?? '');
        $dateToFilter = trim($_POST['date_to_filter'] ?? '');
        $viewMode = trim($_POST['mode'] ?? 'entry');
        $grades = $_POST['grades'] ?? [];
        
        if ($groupId && $disciplineId && !empty($grades)) {
            $pdo->beginTransaction();
            try {
                $savedCount = 0;

                foreach ($grades as $studentId => $gradeData) {
                    $rawGrade = trim((string)($gradeData['grade'] ?? ''));
                    $date = $gradeData['date'] ?? date('Y-m-d');
                    $type = $gradeData['type'] ?? 'exam';

                    if ($rawGrade === '') {
                        continue;
                    }

                    $grade = (int)$rawGrade;

                    if ($grade < 1 || $grade > 10) {
                        throw new InvalidArgumentException('Оценки должны быть от 1 до 10');
                    }

                    saveGradeRecord($pdo, (int)$studentId, $disciplineId, $grade, $date, $type);
                    $savedCount++;
                }

                if ($savedCount === 0) {
                    throw new InvalidArgumentException('Заполните хотя бы одну оценку для сохранения');
                }

                $pdo->commit();
                header('Location: ' . buildGradesRedirectUrl($groupId, $disciplineId, 'success', 'Сохранено оценок: ' . $savedCount, $journalType, $dateFromFilter, $dateToFilter, $viewMode));
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = 'error|Ошибка при сохранении: ' . $e->getMessage();
            }
        } else {
            $message = 'error|Выберите группу и дисциплину';
        }
    } elseif ($action === 'save_single') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $disciplineId = (int)($_POST['discipline_id'] ?? 0);
        $journalType = trim($_POST['journal_type'] ?? '');
        $dateFromFilter = trim($_POST['date_from_filter'] ?? '');
        $dateToFilter = trim($_POST['date_to_filter'] ?? '');
        $viewMode = trim($_POST['mode'] ?? 'entry');
        $gradeRaw = trim((string)($_POST['grade'] ?? ''));
        $date = $_POST['date'] ?? date('Y-m-d');
        $type = $_POST['type'] ?? 'exam';

        if (!$studentId || !$disciplineId) {
            $message = 'error|Не удалось определить студента или дисциплину';
        } elseif ($gradeRaw === '') {
            $message = 'error|Введите оценку для сохранения';
        } else {
            $grade = (int)$gradeRaw;

            if ($grade < 1 || $grade > 10) {
                $message = 'error|Оценка должна быть от 1 до 10';
            } else {
                try {
                    saveGradeRecord($pdo, $studentId, $disciplineId, $grade, $date, $type);
                    header('Location: ' . buildGradesRedirectUrl($_POST['group_id'] ?? 0, $disciplineId, 'success', 'Оценка студента успешно сохранена', $journalType, $dateFromFilter, $dateToFilter, $viewMode));
                    exit;
                } catch (Exception $e) {
                    $message = 'error|Ошибка при сохранении: ' . $e->getMessage();
                }
            }
        }
    }
}

$groups = getAllGroups($pdo);
$disciplines = getAllDisciplines($pdo);

if (isset($_GET['flash']) && !$message) {
    $message = $_GET['flash'];
}

$selectedGroup = isset($_GET['group']) ? (int)$_GET['group'] : 0;
$selectedDiscipline = isset($_GET['discipline']) ? (int)$_GET['discipline'] : 0;
$selectedJournalType = trim($_GET['journal_type'] ?? '');
$selectedDateFrom = trim($_GET['date_from'] ?? '');
$selectedDateTo = trim($_GET['date_to'] ?? '');
$selectedMode = trim($_GET['mode'] ?? 'entry');
$gradeTypes = getGradeTypeOptions();

$students = [];
$journalGrades = [];
if ($selectedGroup > 0) {
    $students = getStudentsByGroup($pdo, $selectedGroup);
}

if ($selectedGroup > 0 && $selectedDiscipline > 0) {
    $journalGrades = getJournalGradesByGroupDiscipline($pdo, $selectedGroup, $selectedDiscipline, $selectedJournalType, $selectedDateFrom, $selectedDateTo);
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
            confirmButtonColor: '#004a09',
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
                <input type="hidden" name="module" value="grades">
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

                <div class="form-group">
                    <label for="journal_type">Тип контроля</label>
                    <select name="journal_type" id="journal_type" onchange="this.form.submit()">
                        <option value="">Все типы</option>
                        <?php foreach ($gradeTypes as $typeValue => $typeLabel): ?>
                        <option value="<?= escape($typeValue) ?>" <?= $selectedJournalType === $typeValue ? 'selected' : '' ?>>
                            <?= escape($typeLabel) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="date_from">Дата с</label>
                    <input type="date" name="date_from" id="date_from" value="<?= escape($selectedDateFrom) ?>" onchange="this.form.submit()">
                </div>

                <div class="form-group">
                    <label for="date_to">Дата по</label>
                    <input type="date" name="date_to" id="date_to" value="<?= escape($selectedDateTo) ?>" onchange="this.form.submit()">
                </div>

                <div class="form-group">
                    <label for="mode">Режим</label>
                    <select name="mode" id="mode" onchange="this.form.submit()">
                        <option value="entry" <?= $selectedMode === 'entry' ? 'selected' : '' ?>>Вводить оценки</option>
                        <option value="view" <?= $selectedMode === 'view' ? 'selected' : '' ?>>Показать текущие</option>
                    </select>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($selectedGroup > 0 && $selectedDiscipline > 0 && !empty($students)): ?>
    <?php if ($selectedMode === 'entry'): ?>
    <!-- Форма ввода оценок -->
    <form method="POST" id="gradesForm">
        <input type="hidden" name="action" value="save_grades">
        <input type="hidden" name="group_id" value="<?= $selectedGroup ?>">
        <input type="hidden" name="discipline_id" value="<?= $selectedDiscipline ?>">
        <input type="hidden" name="journal_type" value="<?= escape($selectedJournalType) ?>">
        <input type="hidden" name="date_from_filter" value="<?= escape($selectedDateFrom) ?>">
        <input type="hidden" name="date_to_filter" value="<?= escape($selectedDateTo) ?>">
        <input type="hidden" name="mode" value="<?= escape($selectedMode) ?>">
        
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
                            <th>Действие</th>
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
                                       id="grade-<?= $student['id'] ?>"
                                       min="1" 
                                       max="10" 
                                       class="grade-input"
                                       placeholder="1-10">
                            </td>
                            <td>
                                <input type="date" 
                                       name="grades[<?= $student['id'] ?>][date]" 
                                       id="date-<?= $student['id'] ?>"
                                       value="<?= date('Y-m-d') ?>"
                                       class="date-input">
                            </td>
                            <td>
                                <select name="grades[<?= $student['id'] ?>][type]" id="type-<?= $student['id'] ?>" class="type-select">
                                    <option value="exam" <?= $selectedJournalType === 'exam' ? 'selected' : '' ?>>Экзамен</option>
                                    <option value="practice" <?= $selectedJournalType === 'practice' ? 'selected' : '' ?>>Практика</option>
                                    <option value="lab" <?= $selectedJournalType === 'lab' ? 'selected' : '' ?>>Лабораторная</option>
                                </select>
                            </td>
                            <td>
                                <button type="button" class="btn btn-secondary" onclick="saveSingleGrade(<?= $student['id'] ?>)">
                                    <i class="fas fa-save"></i> Сохранить
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    <?php else: ?>
    <div class="table-card" style="margin-top: 24px;">
        <div class="table-header">
            <h3><i class="fas fa-book-open"></i> Просмотр выставленных оценок</h3>
        </div>
        <div class="table-body">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>№</th>
                        <th>ФИО студента</th>
                        <th>Группа</th>
                        <th>Оценка</th>
                        <th>Тип контроля</th>
                        <th>Дата</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($journalGrades as $index => $journalRow): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= escape($journalRow['full_name']) ?></td>
                        <td><?= escape($journalRow['group_name']) ?></td>
                        <td>
                            <?php if ($journalRow['grade'] !== null): ?>
                            <span class="grade-badge" style="background: <?= getGradeColor($journalRow['grade']) ?>;">
                                <?= (int)$journalRow['grade'] ?>
                            </span>
                            <?php else: ?>
                            —
                            <?php endif; ?>
                        </td>
                        <td><?= $journalRow['grade_type'] ? escape(getGradeTypeLabel($journalRow['grade_type'])) : '—' ?></td>
                        <td><?= $journalRow['grade_date'] ? formatDate($journalRow['grade_date']) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
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
    let filledCount = 0;
    
    gradeInputs.forEach(input => {
        const rawValue = input.value.trim();

        if (rawValue === '') {
            input.style.borderColor = '#e0e0e0';
            return;
        }

        const value = parseInt(rawValue, 10);
        filledCount++;

        if (isNaN(value) || value < 1 || value > 10) {
            input.style.borderColor = '#e74c3c';
            hasError = true;
        } else {
            input.style.borderColor = '#e0e0e0';
        }
    });

    if (filledCount === 0) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Нет данных для сохранения',
            text: 'Заполните хотя бы одну оценку',
            confirmButtonColor: '#f1c40f'
        });
        return;
    }
    
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

function saveSingleGrade(studentId) {
    const gradeInput = document.getElementById(`grade-${studentId}`);
    const dateInput = document.getElementById(`date-${studentId}`);
    const typeInput = document.getElementById(`type-${studentId}`);
    const gradeRaw = gradeInput.value.trim();

    if (gradeRaw === '') {
        Swal.fire({
            icon: 'warning',
            title: 'Нет оценки',
            text: 'Введите оценку для выбранного студента',
            confirmButtonColor: '#f1c40f'
        });
        return;
    }

    const gradeValue = parseInt(gradeRaw, 10);

    if (isNaN(gradeValue) || gradeValue < 1 || gradeValue > 10) {
        gradeInput.style.borderColor = '#e74c3c';
        Swal.fire({
            icon: 'error',
            title: 'Ошибка валидации',
            text: 'Оценка должна быть от 1 до 10',
            confirmButtonColor: '#e74c3c'
        });
        return;
    }

    gradeInput.style.borderColor = '#e0e0e0';

    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <input type="hidden" name="action" value="save_single">
        <input type="hidden" name="student_id" value="${studentId}">
        <input type="hidden" name="group_id" value="<?= $selectedGroup ?>">
        <input type="hidden" name="discipline_id" value="<?= $selectedDiscipline ?>">
        <input type="hidden" name="journal_type" value="<?= escape($selectedJournalType) ?>">
        <input type="hidden" name="date_from_filter" value="<?= escape($selectedDateFrom) ?>">
        <input type="hidden" name="date_to_filter" value="<?= escape($selectedDateTo) ?>">
        <input type="hidden" name="mode" value="<?= escape($selectedMode) ?>">
        <input type="hidden" name="grade" value="${gradeValue}">
        <input type="hidden" name="date" value="${dateInput.value}">
        <input type="hidden" name="type" value="${typeInput.value}">
    `;

    document.body.appendChild(form);
    form.submit();
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
