<?php
/**
 * Конфигурация базы данных SQLite
 * Автоматическое создание БД и генерация тестовых данных
 * Версия для PolessGU Rating System
 */

// Путь к файлу базы данных
define('DB_PATH', __DIR__ . '/../data/polessgu.db');

/**
 * Подключение к базе данных SQLite
 */
function getDB() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            // Создаем папку data если нет
            $dir = dirname(DB_PATH);
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }

            $pdo = new PDO('sqlite:' . DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Инициализация БД если файл новый
            initializeDatabase($pdo);
            
        } catch (PDOException $e) {
            die("Ошибка подключения к базе данных: " . $e->getMessage());
        }
    }
    
    return $pdo;
}

/**
 * Инициализация базы данных (создание таблиц и тестовых данных)
 */
function initializeDatabase($pdo) {
    // Проверяем, существует ли уже таблица users
    $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetch();
    
    if ($check) {
        return; // БД уже инициализирована
    }
    
    // SQL скрипт создания таблиц
    // ВНИМАНИЕ: ENUM заменен на TEXT, так как SQLite не поддерживает ENUM нативно
    $sql = "
    PRAGMA foreign_keys = ON;

    -- Таблица пользователей
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        login TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'teacher', -- 'admin' или 'teacher'
        full_name TEXT NOT NULL
    );
    
    -- Таблица групп
    CREATE TABLE IF NOT EXISTS groups (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        course INTEGER NOT NULL,
        specialty TEXT NOT NULL
    );
    
    -- Таблица студентов
    CREATE TABLE IF NOT EXISTS students (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        group_id INTEGER NOT NULL,
        full_name TEXT NOT NULL,
        birth_date TEXT,
        status TEXT DEFAULT 'active', -- 'active' или 'expelled'
        FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
    );
    
    -- Таблица дисциплин
    CREATE TABLE IF NOT EXISTS disciplines (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        teacher TEXT,
        department TEXT,
        hours INTEGER
    );
    
    -- Таблица оценок
    CREATE TABLE IF NOT EXISTS grades (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER NOT NULL,
        discipline_id INTEGER NOT NULL,
        grade INTEGER CHECK(grade >= 1 AND grade <= 10),
        date TEXT NOT NULL,
        type TEXT DEFAULT 'exam', -- 'exam' или 'credit'
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (discipline_id) REFERENCES disciplines(id) ON DELETE CASCADE
    );
    ";
    
    $pdo->exec($sql);
    
    // Генерация тестовых данных
    generateTestData($pdo);
}

/**
 * Генерация тестовых данных
 */
function generateTestData($pdo) {
    // Пользователи
    $users = [
        ['admin', 'admin123', 'admin', 'Администратор Системы'],
        ['petrov', 'petrov123', 'teacher', 'Петров Иван Сергеевич'],
        ['sidorova', 'sidorova123', 'teacher', 'Сидорова Анна Павловна']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO users (login, password, role, full_name) VALUES (?, ?, ?, ?)");
    foreach ($users as $user) {
        $stmt->execute($user);
    }
    
    // Группы
    $groups = [
        ['МП-21', 1, 'Информационные системы и технологии'],
        ['МП-22', 2, 'Информационные системы и технологии'],
        ['ЛИН-21', 1, 'Лингвистическое обеспечение межкультурной коммуникации'],
        ['ВБ-21', 3, 'Водные биоресурсы и аквакультура'],
        ['ЛП-22', 2, 'Ландшафтное проектирование и строительство']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO groups (name, course, specialty) VALUES (?, ?, ?)");
    foreach ($groups as $group) {
        $stmt->execute($group);
    }
    
    // Белорусские фамилии
    $lastNames = [
        'Иванов', 'Петров', 'Козлов', 'Новицкий', 'Волков', 'Сафронов', 
        'Бондарь', 'Гринь', 'Жук', 'Климович', 'Лукашенко', 'Тихановский',
        'Некляев', 'Статкевич', 'Лабазов', 'Белевич', 'Цепкало', 'Бабарико',
        'Каравай', 'Гончарик', 'Калякин', 'Лебедько', 'Ходасевич', 'Адамович',
        'Быков', 'Мележ', 'Шамякин', 'Брыль', 'Короткевич', 'Арлова',
        'Алексиевич', 'Бахаревич', 'Мартинович', 'Гринкевич', 'Янковский'
    ];
    
    $firstNamesMale = ['Александр', 'Дмитрий', 'Максим', 'Сергей', 'Андрей', 'Алексей', 'Артем', 'Илья', 'Кирилл', 'Михаил', 'Владислав', 'Игорь'];
    $firstNamesFemale = ['Елена', 'Ольга', 'Анна', 'Мария', 'Дарья', 'Алина', 'Наталья', 'Юлия', 'Татьяна', 'Светлана', 'Ксения', 'Виктория'];
    
    $patronymicsMale = ['Александрович', 'Дмитриевич', 'Максимович', 'Сергеевич', 'Андреевич', 'Алексеевич', 'Артемович', 'Ильич', 'Кириллович', 'Михайлович'];
    $patronymicsFemale = ['Александровна', 'Дмитриевна', 'Максимовна', 'Сергеевна', 'Андреевна', 'Алексеевна', 'Артемовна', 'Ильинична', 'Кирилловна', 'Михайловна'];
    
    // Генерируем 165 студентов
    $students = [];
    $groupIds = range(1, 5);
    
    for ($i = 0; $i < 165; $i++) {
        $groupId = $groupIds[$i % 5];
        $isMale = (rand(0, 1) === 1);
        
        $lastName = $lastNames[array_rand($lastNames)];
        if (!$isMale && (substr($lastName, -1) === 'в' || substr($lastName, -1) === 'н' || substr($lastName, -1) === 'й')) {
            $lastName .= 'а';
        } elseif (!$isMale && substr($lastName, -2) === 'ий') {
            $lastName = substr($lastName, 0, -2) . 'ая';
        }
        
        $firstName = $isMale ? $firstNamesMale[array_rand($firstNamesMale)] : $firstNamesFemale[array_rand($firstNamesFemale)];
        $patronymic = $isMale ? $patronymicsMale[array_rand($patronymicsMale)] : $patronymicsFemale[array_rand($patronymicsFemale)];
        
        $fullName = "$lastName $firstName $patronymic";
        
        $birthYear = rand(2000, 2005);
        $birthMonth = str_pad(rand(1, 12), 2, '0', STR_PAD_LEFT);
        $birthDay = str_pad(rand(1, 28), 2, '0', STR_PAD_LEFT);
        $birthDate = "$birthYear-$birthMonth-$birthDay";
        
        $students[] = [$groupId, $fullName, $birthDate, 'active'];
    }
    
    $stmt = $pdo->prepare("INSERT INTO students (group_id, full_name, birth_date, status) VALUES (?, ?, ?, ?)");
    foreach ($students as $student) {
        $stmt->execute($student);
    }
    
    // Дисциплины
    $disciplines = [
        ['Высшая математика', 'Петров И.С.', 'Кафедра математики', 180],
        ['Физика', 'Сидорова А.П.', 'Кафедра физики', 144],
        ['Сопротивление материалов', 'Козлов В.В.', 'Кафедра механики', 108],
        ['Начертательная геометрия', 'Новицкий Д.А.', 'Кафедра графики', 72],
        ['Информатика', 'Иванов А.С.', 'Кафедра ИТ', 144],
        ['История Беларуси', 'Волков С.П.', 'Кафедра истории', 72],
        ['Иностранный язык (англ.)', 'Сафронова Е.М.', 'Кафедра языков', 108],
        ['Философия', 'Бондарь Н.К.', 'Кафедра философии', 72],
        ['Экономика предприятия', 'Гринь О.Л.', 'Кафедра экономики', 90],
        ['Основы экологии', 'Жук П.Т.', 'Кафедра экологии', 54],
        ['Детали машин', 'Климович М.В.', 'Кафедра машиноведения', 108],
        ['Гидравлика', 'Лукашенко А.Г.', 'Кафедра гидравлики', 90],
        ['Термодинамика', 'Тихановский С.В.', 'Кафедра теплотехники', 72],
        ['Программирование', 'Некляев У.А.', 'Кафедра программирования', 144],
        ['Сети ЭВМ', 'Статкевич Н.М.', 'Кафедра сетей', 90]
    ];
    
    $stmt = $pdo->prepare("INSERT INTO disciplines (name, teacher, department, hours) VALUES (?, ?, ?, ?)");
    foreach ($disciplines as $discipline) {
        $stmt->execute($discipline);
    }
    
    // Генерация оценок
    $allStudents = $pdo->query("SELECT id FROM students")->fetchAll();
    $allDisciplines = $pdo->query("SELECT id FROM disciplines")->fetchAll();
    
    $totalStudents = count($allStudents);
    $excellentCount = floor($totalStudents * 0.10);
    $goodCount = floor($totalStudents * 0.65);
    $riskCount = floor($totalStudents * 0.20);
    
    $gradeRecords = 0;
    $startTimestamp = strtotime('2024-09-01');
    $endTimestamp = strtotime('2025-01-31');
    
    $stmt = $pdo->prepare("INSERT INTO grades (student_id, discipline_id, grade, date, type) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($allStudents as $index => $student) {
        $studentId = $student['id'];
        
        if ($index < $excellentCount) {
            $minGrade = 9; $maxGrade = 10; $subjectsCount = rand(8, 12);
        } elseif ($index < $excellentCount + $goodCount) {
            $minGrade = 6; $maxGrade = 8; $subjectsCount = rand(5, 10);
        } elseif ($index < $excellentCount + $goodCount + $riskCount) {
            $minGrade = 3; $maxGrade = 5; $subjectsCount = rand(3, 7);
        } else {
            $minGrade = 1; $maxGrade = 3; $subjectsCount = rand(2, 5);
        }
        
        $disciplineIds = array_column($allDisciplines, 'id');
        shuffle($disciplineIds);
        $selectedDisciplines = array_slice($disciplineIds, 0, min($subjectsCount, count($disciplineIds)));
        
        foreach ($selectedDisciplines as $disciplineId) {
            $grade = rand($minGrade, $maxGrade);
            $randomTimestamp = rand($startTimestamp, $endTimestamp);
            $date = date('Y-m-d', $randomTimestamp);
            $type = (rand(1, 10) > 3) ? 'exam' : 'credit';
            
            $stmt->execute([$studentId, $disciplineId, $grade, $date, $type]);
            $gradeRecords++;
        }
    }
    
    while ($gradeRecords < 450) {
        $studentId = rand(1, $totalStudents);
        $disciplineId = rand(1, 15);
        $grade = rand(1, 10);
        $randomTimestamp = rand($startTimestamp, $endTimestamp);
        $date = date('Y-m-d', $randomTimestamp);
        $type = (rand(1, 10) > 3) ? 'exam' : 'credit';
        
        $stmt->execute([$studentId, $disciplineId, $grade, $date, $type]);
        $gradeRecords++;
    }
}

/**
 * Проверка авторизации пользователя
 */
function checkAuth() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        // Корректный редирект для встроенного сервера
        header('Location: login.php');
        exit;
    }
    
    return $_SESSION;
}

/**
 * Проверка прав администратора
 */
function checkAdmin() {
    $user = checkAuth();
    
    if ($user['role'] !== 'admin') {
        header('Location: index.php?error=access_denied');
        exit;
    }
    
    return $user;
}

/**
 * Расчет среднего балла студента
 */
function calculateStudentAverage($studentId, $pdo) {
    $stmt = $pdo->prepare("SELECT AVG(grade) as avg_grade FROM grades WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $result = $stmt->fetch();
    
    return $result['avg_grade'] ? round($result['avg_grade'], 2) : 0;
}

/**
 * Расчет среднего балла группы
 */
function calculateGroupAverage($groupId, $pdo) {
    $stmt = $pdo->prepare("
        SELECT AVG(g.grade) as avg_grade 
        FROM grades g
        JOIN students s ON g.student_id = s.id
        WHERE s.group_id = ? AND s.status = 'active'
    ");
    $stmt->execute([$groupId]);
    $result = $stmt->fetch();
    
    return $result['avg_grade'] ? round($result['avg_grade'], 2) : 0;
}

/**
 * Расчет среднего балла по факультету
 */
function calculateFacultyAverage($pdo) {
    $stmt = $pdo->query("SELECT AVG(grade) as avg_grade FROM grades");
    $result = $stmt->fetch();
    
    return $result['avg_grade'] ? round($result['avg_grade'], 2) : 0;
}
?>