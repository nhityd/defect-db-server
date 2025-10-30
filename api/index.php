<?php
/**
 * APIルーター - 不具合データベース v4.0.0
 */

session_start();
require_once '../config/config.php';
require_once '../classes/Database.php';
require_once '../classes/Auth.php';
require_once '../classes/MailSender.php';
require_once '../classes/ApiErrorHandler.php';
require_once '../classes/InputValidator.php';

// グローバルエラーハンドラーの登録
ApiErrorHandler::registerHandler();

// CORSヘッダー設定
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// プリフライトリクエスト処理
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// APIレスポンス関数
function apiResponse($data, $status = 200, $message = '') {
    http_response_code($status);
    echo json_encode([
        'status' => $status < 400 ? 'success' : 'error',
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function apiError($message, $status = 400) {
    apiResponse(null, $status, $message);
}

// 認証チェック関数
function requireAuth($requiredRole = null) {
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        apiError('認証が必要です', 401);
    }
    
    if ($requiredRole && !$auth->hasRole($requiredRole)) {
        apiError('権限が不足しています', 403);
    }
    
    return $auth;
}

// ログ関数
function apiLog($message) {
    error_log(date('[Y-m-d H:i:s] ') . 'API: ' . $message);
}

try {
    // URLパス解析
    $requestUri = $_SERVER['REQUEST_URI'];
    $path = parse_url($requestUri, PHP_URL_PATH);
    $path = str_replace(BASE_URL . '/api', '', $path);
    $path = trim($path, '/');
    $segments = explode('/', $path);
    
    $method = $_SERVER['REQUEST_METHOD'];
    $endpoint = $segments[0] ?? '';
    
    apiLog("{$method} /{$path}");
    
    // エンドポイントルーティング
    switch ($endpoint) {
        case 'auth':
            require_once 'endpoints/auth.php';
            break;
            
        case 'change-password':
            require_once 'endpoints/change-password.php';
            break;
            
        case 'defects':
            require_once 'endpoints/defects.php';
            break;
            
        case 'categories':
            require_once 'endpoints/categories.php';
            break;
            
        case 'processes':
            require_once 'endpoints/processes.php';
            break;
            
        case 'notifications':
            require_once 'endpoints/notifications.php';
            break;
            
        case 'users':
            require_once 'endpoints/users.php';
            break;
            
        case 'user-profile':
            require_once 'endpoints/user-profile.php';
            break;

        case 'notification-settings':
            require_once 'endpoints/notification-settings.php';
            break;

        case 'upload':
            require_once 'endpoints/upload.php';
            break;
            
        case 'images':
            require_once 'endpoints/images.php';
            break;
            
        case 'export':
            require_once 'endpoints/export.php';
            break;

        case 'reactions':
            require_once 'endpoints/reactions.php';
            break;

        case 'comments':
            require_once 'endpoints/comments.php';
            break;

        default:
            apiError('エンドポイントが見つかりません', 404);
    }
    
} catch (Exception $e) {
    apiLog('Error: ' . $e->getMessage());
    ApiErrorHandler::serverError($e, DEBUG_MODE);
}
?>