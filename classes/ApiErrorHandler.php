<?php
/**
 * API エラーハンドリング クラス
 * 不具合データベース v4.0.0
 *
 * 統一的なエラー応答とログ機能を提供
 */

class ApiErrorHandler {

    // エラーコード定義
    const VALIDATION_ERROR = 'VALIDATION_ERROR';
    const AUTHENTICATION_ERROR = 'AUTHENTICATION_ERROR';
    const AUTHORIZATION_ERROR = 'AUTHORIZATION_ERROR';
    const NOT_FOUND_ERROR = 'NOT_FOUND_ERROR';
    const DATABASE_ERROR = 'DATABASE_ERROR';
    const FILE_ERROR = 'FILE_ERROR';
    const SERVER_ERROR = 'SERVER_ERROR';
    const INVALID_INPUT_ERROR = 'INVALID_INPUT_ERROR';

    // HTTP ステータスコード
    const HTTP_OK = 200;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_FORBIDDEN = 403;
    const HTTP_NOT_FOUND = 404;
    const HTTP_SERVER_ERROR = 500;

    /**
     * エラーレスポンスを送信
     *
     * @param string $message ユーザーフレンドリーなエラーメッセージ
     * @param string $code エラーコード
     * @param int $httpStatus HTTP ステータスコード
     * @param array $details 追加の詳細情報
     */
    public static function error($message, $code = self::SERVER_ERROR, $httpStatus = self::HTTP_BAD_REQUEST, $details = []) {
        self::logError($message, $code, $details);

        http_response_code($httpStatus);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => !empty($details) ? $details : null,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        exit;
    }

    /**
     * 検証エラーレスポンスを送信
     *
     * @param array $validationErrors バリデーションエラーの配列
     */
    public static function validationError($validationErrors) {
        self::error(
            '入力値のバリデーションに失敗しました',
            self::VALIDATION_ERROR,
            self::HTTP_BAD_REQUEST,
            ['fields' => $validationErrors]
        );
    }

    /**
     * 認証エラーレスポンスを送信
     *
     * @param string $message エラーメッセージ
     */
    public static function authenticationError($message = '認証が必要です') {
        self::error(
            $message,
            self::AUTHENTICATION_ERROR,
            self::HTTP_UNAUTHORIZED
        );
    }

    /**
     * 権限エラーレスポンスを送信
     *
     * @param string $message エラーメッセージ
     */
    public static function authorizationError($message = '権限が不足しています') {
        self::error(
            $message,
            self::AUTHORIZATION_ERROR,
            self::HTTP_FORBIDDEN
        );
    }

    /**
     * リソース未検出エラーレスポンスを送信
     *
     * @param string $resourceType リソースの種類
     */
    public static function notFoundError($resourceType = 'リソース') {
        self::error(
            "{$resourceType}が見つかりません",
            self::NOT_FOUND_ERROR,
            self::HTTP_NOT_FOUND
        );
    }

    /**
     * データベースエラーレスポンスを送信
     *
     * @param Exception $exception 例外オブジェクト
     * @param bool $includeDetail デバッグ情報を含めるか
     */
    public static function databaseError(Exception $exception, $includeDetail = false) {
        $details = $includeDetail ? ['error' => $exception->getMessage()] : [];

        self::error(
            'データベース操作中にエラーが発生しました',
            self::DATABASE_ERROR,
            self::HTTP_SERVER_ERROR,
            $details
        );
    }

    /**
     * ファイルエラーレスポンスを送信
     *
     * @param string $message エラーメッセージ
     */
    public static function fileError($message = 'ファイル操作中にエラーが発生しました') {
        self::error(
            $message,
            self::FILE_ERROR,
            self::HTTP_BAD_REQUEST
        );
    }

    /**
     * サーバーエラーレスポンスを送信
     *
     * @param Exception $exception 例外オブジェクト
     * @param bool $includeDetail デバッグ情報を含めるか
     */
    public static function serverError(Exception $exception = null, $includeDetail = false) {
        $details = [];
        if ($exception && $includeDetail) {
            $details = [
                'error' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine()
            ];
        }

        self::error(
            'サーバーエラーが発生しました',
            self::SERVER_ERROR,
            self::HTTP_SERVER_ERROR,
            $details
        );
    }

    /**
     * エラーをログに記録
     *
     * @param string $message エラーメッセージ
     * @param string $code エラーコード
     * @param array $details 追加情報
     */
    private static function logError($message, $code, $details = []) {
        $logMessage = sprintf(
            "[%s] ERROR: %s (Code: %s) | URI: %s | Method: %s | IP: %s",
            date('Y-m-d H:i:s'),
            $message,
            $code,
            $_SERVER['REQUEST_URI'] ?? 'unknown',
            $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            self::getClientIp()
        );

        if (!empty($details)) {
            $logMessage .= " | Details: " . json_encode($details, JSON_UNESCAPED_UNICODE);
        }

        error_log($logMessage);
    }

    /**
     * クライアント IP アドレスを取得
     *
     * @return string クライアント IP アドレス
     */
    private static function getClientIp() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        }

        return trim($ip);
    }

    /**
     * エラーハンドラーの登録
     */
    public static function registerHandler() {
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            error_log(sprintf(
                "[%s] PHP Error: %s in %s:%d",
                date('Y-m-d H:i:s'),
                $errstr,
                $errfile,
                $errline
            ));

            return true; // エラーの通常処理を抑制
        });

        set_exception_handler(function(Exception $e) {
            self::serverError($e, true);
        });
    }
}
?>
