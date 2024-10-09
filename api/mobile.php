<?php
/**
 * Mobile API Endpoint for Food Chef Cafe
 * Provides mobile app functionality
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../config/config.php';
require_once '../libs/Db.php';
require_once '../libs/CacheManager.php';

$db = new Db();
$cacheManager = new CacheManager();

$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';

try {
    switch ($method) {
        case 'GET':
            handleGet($path, $db, $cacheManager);
            break;
        case 'POST':
            handlePost($path, $db);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

function handleGet($path, $db, $cacheManager) {
    switch ($path) {
        case 'menu':
            $categoryId = $_GET['category'] ?? null;
            $menu = $cacheManager->get('menu_' . ($categoryId ?? 'all'));
            
            if ($menu === false) {
                $menu = $cacheManager->cacheMenu($categoryId);
            }
            
            echo json_encode(['success' => true, 'data' => $menu]);
            break;
            
        case 'popular':
            $limit = $_GET['limit'] ?? 10;
            $popular = $cacheManager->get('popular_items_' . $limit);
            
            if ($popular === false) {
                $popular = $cacheManager->cachePopularItems($limit);
            }
            
            echo json_encode(['success' => true, 'data' => $popular]);
            break;
            
        case 'categories':
            $categories = $db->query("SELECT * FROM menu_categories WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
            echo json_encode(['success' => true, 'data' => $categories]);
            break;
            
        case 'reservations':
            $email = $_GET['email'] ?? '';
            if ($email) {
                $reservations = $db->query(
                    "SELECT * FROM reservations WHERE email = ? ORDER BY reservation_date DESC",
                    [$email]
                )->fetchAll();
                echo json_encode(['success' => true, 'data' => $reservations]);
            } else {
                echo json_encode(['error' => 'Email required']);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}

function handlePost($path, $db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($path) {
        case 'reservation':
            if (empty($input['name']) || empty($input['email']) || empty($input['date']) || empty($input['time'])) {
                echo json_encode(['error' => 'Missing required fields']);
                return;
            }
            
            $stmt = $db->query(
                "INSERT INTO reservations (name, email, phone, reservation_date, reservation_time, guests, message, status) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')",
                [
                    $input['name'],
                    $input['email'],
                    $input['phone'] ?? '',
                    $input['date'],
                    $input['time'],
                    $input['guests'] ?? 1,
                    $input['message'] ?? ''
                ]
            );
            
            if ($stmt) {
                echo json_encode(['success' => true, 'message' => 'Reservation created successfully']);
            } else {
                echo json_encode(['error' => 'Failed to create reservation']);
            }
            break;
            
        case 'feedback':
            if (empty($input['name']) || empty($input['email']) || empty($input['rating'])) {
                echo json_encode(['error' => 'Missing required fields']);
                return;
            }
            
            $stmt = $db->query(
                "INSERT INTO customer_feedback (customer_name, customer_email, rating, feedback_type, message) 
                 VALUES (?, ?, ?, ?, ?)",
                [
                    $input['name'],
                    $input['email'],
                    $input['rating'],
                    $input['type'] ?? 'general',
                    $input['message'] ?? ''
                ]
            );
            
            if ($stmt) {
                echo json_encode(['success' => true, 'message' => 'Feedback submitted successfully']);
            } else {
                echo json_encode(['error' => 'Failed to submit feedback']);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
    }
}
?>
