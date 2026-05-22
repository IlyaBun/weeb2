<?php
/**
 * Вспомогательные функции
 */

/**
 * Защита от XSS атак
 */
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Проверка валидности оценки (1-10)
 */
function isValidGrade($grade) {
    return is_numeric($grade) && $grade >= 1 && $grade <= 10;
}

/**
 * Форматирование даты для вывода
 */
function formatDate($date) {
    return date('d.m.Y', strtotime($date));
}

/**
 * Получение текстового представления роли
 */
function getRoleLabel($role) {
    $labels = [
        'admin' => 'Администратор',
        'teacher' => 'Преподаватель',
        'student' => 'Студент',
    ];

    return $labels[$role] ?? $role;
}

/**
 * Получение текстового представления оценки
 */
function getGradeText($grade) {
    if ($grade >= 9) return 'Отлично';
    if ($grade >= 7) return 'Хорошо';
    if ($grade >= 4) return 'Удовлетворительно';
    return 'Неудовлетворительно';
}

/**
 * Получение цвета для оценки
 */
function getGradeColor($grade) {
    if ($grade >= 9) return '#2ecc71';
    if ($grade >= 7) return '#3498db';
    if ($grade >= 4) return '#f1c40f';
    return '#e74c3c';
}

/**
 * Получение текстового представления типа контроля
 */
function getGradeTypeOptions() {
    return [
        'exam' => 'Экзамен',
        'practice' => 'Практика',
        'lab' => 'Лабораторная',
    ];
}

/**
 * Получение текстового представления типа контроля
 */
function getGradeTypeLabel($type) {
    $labels = getGradeTypeOptions();

    return $labels[$type] ?? $type;
}

/**
 * Устойчивая алфавитная сортировка по ФИО.
 */
function sortByFullName(array &$items, $field = 'full_name') {
    usort($items, function ($left, $right) use ($field) {
        $leftValue = trim((string)($left[$field] ?? ''));
        $rightValue = trim((string)($right[$field] ?? ''));

        if (function_exists('mb_strtolower')) {
            $leftValue = mb_strtolower($leftValue, 'UTF-8');
            $rightValue = mb_strtolower($rightValue, 'UTF-8');
        } else {
            $leftValue = strtolower($leftValue);
            $rightValue = strtolower($rightValue);
        }

        return $leftValue <=> $rightValue;
    });
}

/**
 * Расчет успеваемости (% студентов с оценкой >= 4)
 */
function calculateSuccessRate($pdo, $filters = []) {
    $where = ['g.grade >= 4'];
    $params = [];
    
    if (!empty($filters['group_id'])) {
        $where[] = 's.group_id = ?';
        $params[] = $filters['group_id'];
    }
    
    if (!empty($filters['discipline_id'])) {
        $where[] = 'g.discipline_id = ?';
        $params[] = $filters['discipline_id'];
    }
    
    $whereClause = implode(' AND ', $where);
    
    $sql = "SELECT COUNT(*) as total FROM grades g 
            JOIN students s ON g.student_id = s.id 
            WHERE $whereClause";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    $successSql = "SELECT COUNT(*) as success FROM grades g 
                   JOIN students s ON g.student_id = s.id 
                   WHERE $whereClause AND g.grade >= 4";
    
    $stmt = $pdo->prepare($successSql);
    $stmt->execute($params);
    $success = $stmt->fetch()['success'];
    
    return $total > 0 ? round(($success / $total) * 100, 2) : 0;
}

/**
 * Получение списка студентов группы риска.
 *
 * Студент попадает в группу риска, если средний балл хотя бы по одной
 * дисциплине меньше либо равен 4.0.
 */
function getRiskStudents($pdo, $limit = null) {
    $sql = "SELECT s.id,
                   s.full_name,
                   g.name as group_name,
                   MIN(risk_subjects.subject_avg_grade) as min_subject_avg_grade,
                   COUNT(risk_subjects.discipline_id) as risk_discipline_count
            FROM students s
            JOIN groups g ON s.group_id = g.id
            JOIN (
                SELECT gr.student_id,
                       gr.discipline_id,
                       AVG(gr.grade) as subject_avg_grade
                FROM grades gr
                GROUP BY gr.student_id, gr.discipline_id
                HAVING AVG(gr.grade) <= 4.0
            ) as risk_subjects ON risk_subjects.student_id = s.id
            WHERE s.status = 'active'
            GROUP BY s.id, s.full_name, g.name
            ORDER BY min_subject_avg_grade ASC, s.full_name ASC";

    if ($limit !== null) {
        $sql .= " LIMIT ?";
    }

    $stmt = $pdo->prepare($sql);

    if ($limit !== null) {
        $stmt->execute([$limit]);
    } else {
        $stmt->execute();
    }

    return $stmt->fetchAll();
}

/**
 * Получение отличников (средний балл >= 9.0).
 * Поддерживает старый вызов getExcellentStudents($pdo, 100)
 * и новый вызов getExcellentStudents($pdo, ['group_id' => 1], null).
 */
function getExcellentStudents($pdo, $filtersOrLimit = 10, $limit = null) {
    $filters = [];

    if (is_array($filtersOrLimit)) {
        $filters = $filtersOrLimit;
    } else {
        $limit = $filtersOrLimit;
    }

    if ($limit === null && !is_array($filtersOrLimit)) {
        $limit = 10;
    }

    $where = ["s.status = 'active'"];
    $params = [];

    if (!empty($filters['group_id'])) {
        $where[] = 's.group_id = ?';
        $params[] = $filters['group_id'];
    }

    if (!empty($filters['discipline_id'])) {
        $where[] = 'gr.discipline_id = ?';
        $params[] = $filters['discipline_id'];
    }

    $sql = "SELECT s.id, s.full_name, g.name as group_name, AVG(gr.grade) as avg_grade
            FROM students s
            JOIN groups g ON s.group_id = g.id
            JOIN grades gr ON s.id = gr.student_id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY s.id, s.full_name, g.name
            HAVING avg_grade >= 9.0
            ORDER BY avg_grade DESC, s.full_name ASC";

    if ($limit !== null) {
        $sql .= " LIMIT ?";
        $params[] = $limit;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * Получение последних добавленных оценок
 */
function getRecentGrades($pdo, $limit = 10) {
    $sql = "SELECT gr.id, gr.grade, gr.date, gr.type,
                   s.full_name as student_name,
                   d.name as discipline_name,
                   g.name as group_name
            FROM grades gr
            JOIN students s ON gr.student_id = s.id
            JOIN disciplines d ON gr.discipline_id = d.id
            JOIN groups g ON s.group_id = g.id
            ORDER BY gr.date DESC, gr.id DESC
            LIMIT ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$limit]);
    
    return $stmt->fetchAll();
}

/**
 * Распределение оценок по категориям для графика
 */
function getGradeDistribution($pdo) {
    $sql = "SELECT 
                SUM(CASE WHEN grade BETWEEN 1 AND 3 THEN 1 ELSE 0 END) as poor,
                SUM(CASE WHEN grade BETWEEN 4 AND 6 THEN 1 ELSE 0 END) as satisfactory,
                SUM(CASE WHEN grade BETWEEN 7 AND 8 THEN 1 ELSE 0 END) as good,
                SUM(CASE WHEN grade BETWEEN 9 AND 10 THEN 1 ELSE 0 END) as excellent
            FROM grades";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetch();
}

/**
 * Рейтинг групп по среднему баллу
 */
function getGroupRating($pdo, $limit = 5) {
    $sql = "SELECT g.id, g.name, g.course, AVG(gr.grade) as avg_grade
            FROM groups g
            JOIN students s ON g.id = s.group_id
            JOIN grades gr ON s.id = gr.student_id
            WHERE s.status = 'active'
            GROUP BY g.id
            ORDER BY avg_grade DESC
            LIMIT ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$limit]);
    
    return $stmt->fetchAll();
}

/**
 * Динамика успеваемости по месяцам.
 * Логика: сначала считаем средний балл каждого студента за месяц,
 * затем усредняем эти значения по студентам, чтобы один студент
 * с большим количеством оценок не искажал общую картину.
 */
function getMonthlyDynamics($pdo, $dateFrom = '', $dateTo = '') {
    $where = [];
    $params = [];

    if ($dateFrom !== '') {
        $where[] = 'gr.date >= ?';
        $params[] = $dateFrom;
    }

    if ($dateTo !== '') {
        $where[] = 'gr.date <= ?';
        $params[] = $dateTo;
    }

    $where[] = "s.status = 'active'";

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $baseSql = "SELECT monthly_student_avg.month,
                       ROUND(AVG(monthly_student_avg.student_avg_grade), 2) as avg_grade,
                       COUNT(*) as student_count,
                       SUM(monthly_student_avg.grade_count) as grade_count
                FROM (
                    SELECT strftime('%Y-%m', gr.date) as month,
                           gr.student_id,
                           AVG(gr.grade) as student_avg_grade,
                           COUNT(gr.id) as grade_count
                    FROM grades gr
                    JOIN students s ON s.id = gr.student_id
                    $whereClause
                    GROUP BY month, gr.student_id
                ) monthly_student_avg
                GROUP BY monthly_student_avg.month";

    if ($whereClause !== '') {
        $sql = $baseSql . " ORDER BY monthly_student_avg.month ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    $sql = "SELECT * FROM (
                " . $baseSql . "
                ORDER BY monthly_student_avg.month DESC
                LIMIT 6
            ) recent_months
            ORDER BY month ASC";

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

/**
 * Получение всех групп
 */
function getAllGroups($pdo) {
    $stmt = $pdo->query("SELECT id, name, course, specialty FROM groups ORDER BY course, name");
    return $stmt->fetchAll();
}

/**
 * Получение всех дисциплин
 */
function getAllDisciplines($pdo) {
    $stmt = $pdo->query("SELECT id, name, teacher, department, hours FROM disciplines ORDER BY name");
    return $stmt->fetchAll();
}

/**
 * Получение активных студентов для селектов
 */
function getActiveStudentsForSelect($pdo) {
    $sql = "SELECT s.id, s.full_name, g.name as group_name
            FROM students s
            JOIN groups g ON s.group_id = g.id
            WHERE s.status = 'active'
            ORDER BY s.full_name";

    $stmt = $pdo->query($sql);
    $students = $stmt->fetchAll();
    sortByFullName($students);

    return $students;
}

/**
 * Получение студента по ID
 */
function getStudentById($pdo, $studentId) {
    $sql = "SELECT s.*, g.name as group_name, g.course, g.specialty
            FROM students s
            JOIN groups g ON s.group_id = g.id
            WHERE s.id = ?";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$studentId]);

    return $stmt->fetch();
}

/**
 * Получение студентов группы
 */
function getStudentsByGroup($pdo, $groupId, $status = 'active') {
    $sql = "SELECT s.*, g.name as group_name, g.course
            FROM students s
            JOIN groups g ON s.group_id = g.id
            WHERE s.group_id = ? AND s.status = ?
            ORDER BY s.full_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$groupId, $status]);

    $students = $stmt->fetchAll();
    sortByFullName($students);

    return $students;
}

/**
 * Получение последней оценки каждого студента по группе, дисциплине, типу контроля и периоду.
 */
function getJournalGradesByGroupDiscipline($pdo, $groupId, $disciplineId, $type = '', $dateFrom = '', $dateTo = '') {
    $typeCondition = $type !== '' ? ' AND gr.type = ?' : '';
    $dateFromCondition = $dateFrom !== '' ? ' AND gr.date >= ?' : '';
    $dateToCondition = $dateTo !== '' ? ' AND gr.date <= ?' : '';
    $params = [$disciplineId];

    if ($type !== '') {
        $params[] = $type;
    }

    if ($dateFrom !== '') {
        $params[] = $dateFrom;
    }

    if ($dateTo !== '') {
        $params[] = $dateTo;
    }

    $params[] = $disciplineId;
    if ($type !== '') {
        $params[] = $type;
    }
    if ($dateFrom !== '') {
        $params[] = $dateFrom;
    }
    if ($dateTo !== '') {
        $params[] = $dateTo;
    }

    $params[] = $disciplineId;
    if ($type !== '') {
        $params[] = $type;
    }
    if ($dateFrom !== '') {
        $params[] = $dateFrom;
    }
    if ($dateTo !== '') {
        $params[] = $dateTo;
    }

    $params[] = $groupId;

    $sql = "SELECT s.id as student_id,
                   s.full_name,
                   g.name as group_name,
                   (
                       SELECT gr.grade
                       FROM grades gr
                       WHERE gr.student_id = s.id AND gr.discipline_id = ?" . $typeCondition . $dateFromCondition . $dateToCondition . "
                       ORDER BY gr.date DESC, gr.id DESC
                       LIMIT 1
                   ) as grade,
                   (
                       SELECT gr.date
                       FROM grades gr
                       WHERE gr.student_id = s.id AND gr.discipline_id = ?" . $typeCondition . $dateFromCondition . $dateToCondition . "
                       ORDER BY gr.date DESC, gr.id DESC
                       LIMIT 1
                   ) as grade_date,
                   (
                       SELECT gr.type
                       FROM grades gr
                       WHERE gr.student_id = s.id AND gr.discipline_id = ?" . $typeCondition . $dateFromCondition . $dateToCondition . "
                       ORDER BY gr.date DESC, gr.id DESC
                       LIMIT 1
                   ) as grade_type
            FROM students s
            JOIN groups g ON s.group_id = g.id
            WHERE s.group_id = ? AND s.status = 'active'
            ORDER BY s.full_name";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $journalRows = $stmt->fetchAll();
    sortByFullName($journalRows);

    return $journalRows;
}

/**
 * Получение оценок студента
 */
function getStudentGrades($pdo, $studentId) {
    $sql = "SELECT gr.*, d.name as discipline_name, d.teacher
            FROM grades gr
            JOIN disciplines d ON gr.discipline_id = d.id
            WHERE gr.student_id = ?
            ORDER BY gr.date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$studentId]);
    
    return $stmt->fetchAll();
}

/**
 * Получение оценок студента с фильтрами и сортировкой
 */
function getStudentGradesFiltered($pdo, $studentId, $filters = []) {
    $conditions = ['gr.student_id = ?'];
    $params = [$studentId];

    if (!empty($filters['discipline_id'])) {
        $conditions[] = 'gr.discipline_id = ?';
        $params[] = $filters['discipline_id'];
    }

    if (!empty($filters['type'])) {
        $conditions[] = 'gr.type = ?';
        $params[] = $filters['type'];
    }

    if (!empty($filters['date_from'])) {
        $conditions[] = 'gr.date >= ?';
        $params[] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $conditions[] = 'gr.date <= ?';
        $params[] = $filters['date_to'];
    }

    $sortMap = [
        'date' => 'gr.date',
        'discipline' => 'd.name',
    ];

    $sortField = $filters['sort'] ?? 'date';
    $orderField = $sortMap[$sortField] ?? 'gr.date';
    $orderDirection = strtolower($filters['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

    $sql = "SELECT gr.*, d.name as discipline_name, d.teacher
            FROM grades gr
            JOIN disciplines d ON gr.discipline_id = d.id
            WHERE " . implode(' AND ', $conditions) . "
            ORDER BY {$orderField} {$orderDirection}, gr.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * Расчет среднего балла студента с учетом фильтров
 */
function calculateStudentAverageFiltered($pdo, $studentId, $filters = []) {
    $conditions = ['student_id = ?'];
    $params = [$studentId];

    if (!empty($filters['discipline_id'])) {
        $conditions[] = 'discipline_id = ?';
        $params[] = $filters['discipline_id'];
    }

    if (!empty($filters['type'])) {
        $conditions[] = 'type = ?';
        $params[] = $filters['type'];
    }

    if (!empty($filters['date_from'])) {
        $conditions[] = 'date >= ?';
        $params[] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $conditions[] = 'date <= ?';
        $params[] = $filters['date_to'];
    }

    $sql = "SELECT AVG(grade) as avg_grade
            FROM grades
            WHERE " . implode(' AND ', $conditions);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();

    return $result['avg_grade'] ? round($result['avg_grade'], 2) : 0;
}

/**
 * Статистика по предметам студента с учетом текущих фильтров
 */
function getStudentSubjectStatsFiltered($pdo, $studentId, $filters = []) {
    $conditions = ['gr.student_id = ?'];
    $params = [$studentId];

    if (!empty($filters['discipline_id'])) {
        $conditions[] = 'gr.discipline_id = ?';
        $params[] = $filters['discipline_id'];
    }

    if (!empty($filters['type'])) {
        $conditions[] = 'gr.type = ?';
        $params[] = $filters['type'];
    }

    if (!empty($filters['date_from'])) {
        $conditions[] = 'gr.date >= ?';
        $params[] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
        $conditions[] = 'gr.date <= ?';
        $params[] = $filters['date_to'];
    }

    $sql = "SELECT gr.discipline_id,
                   d.name as discipline_name,
                   AVG(gr.grade) as avg_grade,
                   COUNT(gr.id) as grade_count
            FROM grades gr
            JOIN disciplines d ON gr.discipline_id = d.id
            WHERE " . implode(' AND ', $conditions) . "
            GROUP BY gr.discipline_id, d.name
            ORDER BY avg_grade DESC, d.name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

?>
