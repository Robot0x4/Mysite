<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

$data_dir = __DIR__ . '/data/';
$uploads_dir = $data_dir . 'uploads/';

if (!file_exists($data_dir)) mkdir($data_dir, 0777, true);
if (!file_exists($uploads_dir)) mkdir($uploads_dir, 0777, true);

$users_file = $data_dir . 'users.txt';
$progress_file = $data_dir . 'progress.txt';

$action = $_REQUEST['action'] ?? '';

if ($action === 'checkUsername' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $username = $_GET['username'] ?? '';
    $available = true;
    
    if (file_exists($users_file)) {
        $users = file($users_file, FILE_IGNORE_NEW_LINES);
        foreach ($users as $line) {
            $parts = explode('|', $line);
            if (isset($parts[0]) && $parts[0] === $username) {
                $available = false;
                break;
            }
        }
    }
    echo json_encode(['available' => $available]);
    exit;
}

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (file_exists($users_file)) {
        $users = file($users_file, FILE_IGNORE_NEW_LINES);
        foreach ($users as $line) {
            $parts = explode('|', $line);
            if ($parts[0] === $username && password_verify($password, $parts[1])) {
                $solved = [];
                if (file_exists($progress_file)) {
                    $progress = file($progress_file, FILE_IGNORE_NEW_LINES);
                    foreach ($progress as $p) {
                        $p_parts = explode('|', $p);
                        if ($p_parts[0] === $username) {
                            $solved[] = $p_parts[1];
                        }
                    }
                }
                echo json_encode([
                    'success' => true,
                    'user' => [
                        'username' => $parts[0],
                        'avatar' => $parts[2] ?? '',
                        'points' => intval($parts[3] ?? 0),
                        'level' => intval($parts[4] ?? 1)
                    ],
                    'solved' => $solved
                ]);
                exit;
            }
        }
    }
    echo json_encode(['success' => false]);
    exit;
}

if ($action === 'signup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'بيانات ناقصة']);
        exit;
    }
    
    if (file_exists($users_file)) {
        $users = file($users_file, FILE_IGNORE_NEW_LINES);
        foreach ($users as $line) {
            $parts = explode('|', $line);
            if ($parts[0] === $username) {
                echo json_encode(['success' => false, 'message' => 'اسم المستخدم موجود']);
                exit;
            }
        }
    }
    
    $avatar_path = '';
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
        $filename = $username . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['avatar']['tmp_name'], $uploads_dir . $filename);
        $avatar_path = 'data/uploads/' . $filename;
    }
    
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $user_line = $username . '|' . $hashed . '|' . $avatar_path . '|0|1' . PHP_EOL;
    file_put_contents($users_file, $user_line, FILE_APPEND);
    
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'getUser' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $username = $_GET['username'] ?? '';
    
    if (file_exists($users_file)) {
        $users = file($users_file, FILE_IGNORE_NEW_LINES);
        foreach ($users as $line) {
            $parts = explode('|', $line);
            if ($parts[0] === $username) {
                $solved = [];
                if (file_exists($progress_file)) {
                    $progress = file($progress_file, FILE_IGNORE_NEW_LINES);
                    foreach ($progress as $p) {
                        $p_parts = explode('|', $p);
                        if ($p_parts[0] === $username) {
                            $solved[] = $p_parts[1];
                        }
                    }
                }
                echo json_encode([
                    'success' => true,
                    'user' => [
                        'username' => $parts[0],
                        'avatar' => $parts[2] ?? '',
                        'points' => intval($parts[3] ?? 0),
                        'level' => intval($parts[4] ?? 1)
                    ],
                    'solved' => $solved
                ]);
                exit;
            }
        }
    }
    echo json_encode(['success' => false]);
    exit;
}

if ($action === 'saveResult' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $challengeId = $_POST['challengeId'] ?? '';
    $points = intval($_POST['points'] ?? 0);
    $isCorrect = $_POST['correct'] === '1';
    
    if (!$isCorrect) {
        echo json_encode(['success' => true, 'message' => 'إجابة خاطئة']);
        exit;
    }
    
    $alreadySolved = false;
    if (file_exists($progress_file)) {
        $progress = file($progress_file, FILE_IGNORE_NEW_LINES);
        foreach ($progress as $p) {
            if ($p === $username . '|' . $challengeId) {
                $alreadySolved = true;
                break;
            }
        }
    }
    
    if ($alreadySolved) {
        echo json_encode(['success' => false, 'message' => 'تم حل هذا التحدي مسبقاً']);
        exit;
    }
    
    file_put_contents($progress_file, $username . '|' . $challengeId . PHP_EOL, FILE_APPEND);
    
    $newPoints = 0;
    $newLevel = 1;
    $users = file($users_file, FILE_IGNORE_NEW_LINES);
    $newUsers = [];
    
    foreach ($users as $line) {
        $parts = explode('|', $line);
        if ($parts[0] === $username) {
            $newPoints = intval($parts[3]) + $points;
            $newLevel = floor($newPoints / 100) + 1;
            $parts[3] = $newPoints;
            $parts[4] = $newLevel;
            $line = implode('|', $parts);
        }
        $newUsers[] = $line;
    }
    
    file_put_contents($users_file, implode(PHP_EOL, $newUsers));
    
    echo json_encode([
        'success' => true,
        'newPoints' => $newPoints,
        'newLevel' => $newLevel
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'طلب غير صحيح']);
?>
