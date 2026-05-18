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
 * Расчет качества знаний (% оценок 8-10)
 */
function calculateQualityPercentage($pdo, $filters = []) {
    $where = ['g.grade >= 8'];
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
    
    $qualitySql = "SELECT COUNT(*) as quality FROM grades g 
                   JOIN students s ON g.student_id = s.id 
                   WHERE $whereClause AND g.grade >= 8";
    
    $stmt = $pdo->prepare($qualitySql);
    $stmt->execute($params);
    $quality = $stmt->fetch()['quality'];
    
    return $total > 0 ? round(($quality / $total) * 100, 2) : 0;
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
 * Получение списка студентов группы риска (средний балл < 4.0)
 */
function getRiskStudents($pdo, $limit = 10) {
    $sql = "SELECT s.id, s.full_name, g.name as group_name, AVG(gr.grade) as avg_grade
            FROM students s
            JOIN groups g ON s.group_id = g.id
            JOIN grades gr ON s.id = gr.student_id
            WHERE s.status = 'active'
            GROUP BY s.id
            HAVING avg_grade < 4.0
            ORDER BY avg_grade ASC
            LIMIT ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$limit]);
    
    return $stmt->fetchAll();
}

/**
 * Получение отличников (средний балл >= 9.0)
 */
function getExcellentStudents($pdo, $limit = 10) {
    $sql = "SELECT s.id, s.full_name, g.name as group_name, AVG(gr.grade) as avg_grade
            FROM students s
            JOIN groups g ON s.group_id = g.id
            JOIN grades gr ON s.id = gr.student_id
            WHERE s.status = 'active'
            GROUP BY s.id
            HAVING avg_grade >= 9.0
            ORDER BY avg_grade DESC
            LIMIT ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$limit]);
    
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
 * Динамика успеваемости по месяцам
 */
function getMonthlyDynamics($pdo) {
    $sql = "SELECT strftime('%Y-%m', date) as month, AVG(grade) as avg_grade
            FROM grades
            GROUP BY month
            ORDER BY month ASC
            LIMIT 6";
    
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
    $stmt = $pdo->query("SELECT id, name, teacher, department FROM disciplines ORDER BY name");
    return $stmt->fetchAll();
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
    
    return $stmt->fetchAll();
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

?>
