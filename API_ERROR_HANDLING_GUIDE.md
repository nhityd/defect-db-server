# API エラーハンドリング実装ガイド

## 概要

本ガイドは、不具合データベース v4.0.0 の API エンドポイントで統一的なエラーハンドリングを実装する方法を説明します。

---

## 新しいクラス

### 1. ApiErrorHandler クラス

**ファイル**: `/classes/ApiErrorHandler.php`

統一的なエラー応答とログ機能を提供します。

#### 主要メソッド

```php
// 汎用エラーレスポンス
ApiErrorHandler::error(
    $message,           // エラーメッセージ
    $code,              // エラーコード
    $httpStatus,        // HTTP ステータスコード
    $details            // 追加情報
);

// 検証エラー
ApiErrorHandler::validationError($validationErrors);

// 認証エラー
ApiErrorHandler::authenticationError($message);

// 権限エラー
ApiErrorHandler::authorizationError($message);

// リソース未検出エラー
ApiErrorHandler::notFoundError($resourceType);

// データベースエラー
ApiErrorHandler::databaseError($exception, $includeDetail);

// ファイルエラー
ApiErrorHandler::fileError($message);

// サーバーエラー
ApiErrorHandler::serverError($exception, $includeDetail);

// グローバルエラーハンドラー登録
ApiErrorHandler::registerHandler();
```

#### エラーコード定義

```php
const VALIDATION_ERROR = 'VALIDATION_ERROR';
const AUTHENTICATION_ERROR = 'AUTHENTICATION_ERROR';
const AUTHORIZATION_ERROR = 'AUTHORIZATION_ERROR';
const NOT_FOUND_ERROR = 'NOT_FOUND_ERROR';
const DATABASE_ERROR = 'DATABASE_ERROR';
const FILE_ERROR = 'FILE_ERROR';
const SERVER_ERROR = 'SERVER_ERROR';
const INVALID_INPUT_ERROR = 'INVALID_INPUT_ERROR';
```

#### HTTP ステータスコード

```php
const HTTP_OK = 200;
const HTTP_BAD_REQUEST = 400;
const HTTP_UNAUTHORIZED = 401;
const HTTP_FORBIDDEN = 403;
const HTTP_NOT_FOUND = 404;
const HTTP_SERVER_ERROR = 500;
```

---

### 2. InputValidator クラス

**ファイル**: `/classes/InputValidator.php`

入力値の検証とサニタイズ機能を提供します。

#### 主要メソッド

```php
$validator = new InputValidator();

// 必須フィールド検証
$validator->validateRequired($data, ['title', 'description', 'status']);

// 型検証
$validator->validateType($value, 'int', 'quantity');
$validator->validateType($email, 'email', 'email');
$validator->validateType($url, 'url', 'website');

// 長さ検証
$validator->validateLength($title, 1, 255, 'title');

// 範囲検証
$validator->validateRange($quantity, 1, 999, 'quantity');

// 許可された値検証
$validator->validateAllowedValues($status, [
    'unsolved',
    'in-progress-emergency',
    'done-emergency',
    'in-progress-permanent',
    'solved'
], 'status');

// 正規表現検証
$validator->validatePattern($value, '/^[0-9]{4}$/', 'yearField');

// ファイル検証
$validator->validateFile($_FILES['image'], [
    'maxSize' => 5 * 1024 * 1024,  // 5MB
    'allowedMimes' => ['image/jpeg', 'image/png', 'image/gif']
], 'image');

// エラー確認
if (!$validator->isValid()) {
    ApiErrorHandler::validationError($validator->getErrors());
}

// サニタイズ
$cleanData = InputValidator::sanitize($dirtyData);

// JSON 入力取得
$input = InputValidator::getJsonInput(true);
```

---

## 実装例

### 例 1: 基本的なバリデーション

```php
<?php
// /api/endpoints/defects.php

try {
    $auth = requireAuth();
    $db = Database::getInstance();
} catch (Exception $e) {
    ApiErrorHandler::authenticationError();
}

switch ($method) {
    case 'POST':
        // JSON 入力を取得
        $input = InputValidator::getJsonInput(true);

        if (empty($input)) {
            ApiErrorHandler::error(
                'リクエストボディが空です',
                ApiErrorHandler::INVALID_INPUT_ERROR,
                ApiErrorHandler::HTTP_BAD_REQUEST
            );
        }

        // 入力値を検証
        $validator = new InputValidator();

        $validator->validateRequired($input, [
            'title',
            'description',
            'status'
        ]);

        $validator->validateLength($input['title'] ?? '', 1, 255, 'title');
        $validator->validateAllowedValues(
            $input['status'] ?? '',
            [
                'unsolved',
                'in-progress-emergency',
                'done-emergency',
                'in-progress-permanent',
                'solved'
            ],
            'status'
        );

        if (!$validator->isValid()) {
            ApiErrorHandler::validationError($validator->getErrors());
        }

        // 入力データをサニタイズ
        $cleanData = InputValidator::sanitize($input);

        try {
            // データベース操作
            $id = $db->insert('defects', $cleanData);
            apiResponse(['id' => $id, 'message' => '不具合を作成しました']);
        } catch (Exception $e) {
            ApiErrorHandler::databaseError($e, DEBUG_MODE);
        }
        break;
}
```

### 例 2: ファイルアップロード検証

```php
<?php
// /api/endpoints/upload.php

try {
    $auth = requireAuth();
    $db = Database::getInstance();
} catch (Exception $e) {
    ApiErrorHandler::authenticationError();
}

if ($method === 'POST') {
    $validator = new InputValidator();

    // ファイル検証
    if (!isset($_FILES['image'])) {
        ApiErrorHandler::error(
            'ファイルが指定されていません',
            ApiErrorHandler::INVALID_INPUT_ERROR,
            ApiErrorHandler::HTTP_BAD_REQUEST
        );
    }

    $validator->validateFile(
        $_FILES['image'],
        [
            'maxSize' => 5 * 1024 * 1024,  // 5MB
            'allowedMimes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp']
        ],
        'image'
    );

    if (!$validator->isValid()) {
        ApiErrorHandler::validationError($validator->getErrors());
    }

    try {
        // ファイル処理...
        move_uploaded_file($_FILES['image']['tmp_name'], $targetPath);
        apiResponse(['filename' => $filename]);
    } catch (Exception $e) {
        ApiErrorHandler::fileError($e->getMessage());
    }
}
```

### 例 3: リソース取得エンドポイント

```php
<?php
// /api/endpoints/defects.php (GET エンドポイント)

try {
    $auth = requireAuth();
    $db = Database::getInstance();
} catch (Exception $e) {
    ApiErrorHandler::authenticationError();
}

if ($method === 'GET' && isset($segments[1])) {
    $id = intval($segments[1]);

    // ID の検証
    if ($id <= 0) {
        ApiErrorHandler::error(
            'ID が不正です',
            ApiErrorHandler::INVALID_INPUT_ERROR,
            ApiErrorHandler::HTTP_BAD_REQUEST
        );
    }

    try {
        $defect = $db->selectOne('SELECT * FROM defects WHERE id = ?', [$id]);

        if (!$defect) {
            ApiErrorHandler::notFoundError('不具合データ');
        }

        apiResponse($defect);
    } catch (Exception $e) {
        ApiErrorHandler::databaseError($e, DEBUG_MODE);
    }
}
```

---

## エラーレスポンス形式

### 成功レスポンス

```json
{
  "success": true,
  "message": "操作に成功しました",
  "data": {
    "id": 1,
    "title": "問題のタイトル"
  }
}
```

### バリデーションエラー

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "入力値のバリデーションに失敗しました",
    "details": {
      "fields": {
        "title": ["titleは必須です"],
        "status": ["statusは以下のいずれかである必要があります: unsolved, in-progress-emergency, done-emergency, in-progress-permanent, solved"]
      }
    },
    "timestamp": "2025-10-30 15:45:00"
  }
}
```

### 認証エラー

```json
{
  "success": false,
  "error": {
    "code": "AUTHENTICATION_ERROR",
    "message": "認証が必要です",
    "details": null,
    "timestamp": "2025-10-30 15:45:00"
  }
}
```

### リソース未検出エラー

```json
{
  "success": false,
  "error": {
    "code": "NOT_FOUND_ERROR",
    "message": "不具合データが見つかりません",
    "details": null,
    "timestamp": "2025-10-30 15:45:00"
  }
}
```

### データベースエラー

```json
{
  "success": false,
  "error": {
    "code": "DATABASE_ERROR",
    "message": "データベース操作中にエラーが発生しました",
    "details": null,
    "timestamp": "2025-10-30 15:45:00"
  }
}
```

（デバッグモード有効の場合、詳細情報が含まれます）

---

## ログ記録

すべてのエラーは自動的に以下の形式でログに記録されます：

```
[2025-10-30 15:45:00] ERROR: 入力値のバリデーションに失敗しました (Code: VALIDATION_ERROR) | URI: /api/defects | Method: POST | IP: 192.168.1.100 | Details: {"fields":{"title":["titleは必須です"]}}
```

ログファイルは PHP の `error_log` 設定に従って記録されます。

---

## セキュリティに関する注意

1. **エラーメッセージ**: 本番環境ではデバッグ情報を含めないようにしてください
2. **SQL インジェクション対策**: すべてのデータベースクエリでプリペアドステートメントを使用
3. **XSS 対策**: `InputValidator::sanitize()` を使用してユーザー入力をエスケープ
4. **ログ記録**: 個人情報をログに記録しないよう注意

---

## 推奨される実装パターン

```php
<?php
// API エンドポイントのテンプレート

try {
    // 1. 認証チェック
    $auth = requireAuth();
    $db = Database::getInstance();

} catch (Exception $e) {
    ApiErrorHandler::authenticationError();
}

switch ($method) {
    case 'GET':
        // 2. パラメータ取得と検証
        $id = isset($segments[1]) ? intval($segments[1]) : null;

        if (!$id) {
            ApiErrorHandler::error(
                'ID が指定されていません',
                ApiErrorHandler::INVALID_INPUT_ERROR,
                ApiErrorHandler::HTTP_BAD_REQUEST
            );
        }

        try {
            // 3. データベースクエリ
            $resource = $db->selectOne('SELECT * FROM table WHERE id = ?', [$id]);

            // 4. リソース確認
            if (!$resource) {
                ApiErrorHandler::notFoundError('リソース');
            }

            // 5. レスポンス返却
            apiResponse($resource);

        } catch (Exception $e) {
            ApiErrorHandler::databaseError($e, DEBUG_MODE);
        }
        break;

    case 'POST':
        // 2. リクエスト解析
        $input = InputValidator::getJsonInput(true);

        // 3. 入力値検証
        $validator = new InputValidator();
        $validator->validateRequired($input, ['field1', 'field2']);

        if (!$validator->isValid()) {
            ApiErrorHandler::validationError($validator->getErrors());
        }

        // 4. データサニタイズ
        $cleanData = InputValidator::sanitize($input);

        try {
            // 5. データベース操作
            $id = $db->insert('table', $cleanData);

            // 6. レスポンス返却
            apiResponse(['id' => $id]);

        } catch (Exception $e) {
            ApiErrorHandler::databaseError($e, DEBUG_MODE);
        }
        break;
}
?>
```

---

## テスト方法

### コマンドラインからのテスト

```bash
# バリデーションエラーテスト
curl -X POST http://localhost:8000/api/defects \
  -H "Content-Type: application/json" \
  -d '{"description": "テスト"}'

# 認証エラーテスト
curl http://localhost:8000/api/defects/1

# 成功レスポンステスト
curl -X POST http://localhost:8000/api/defects \
  -H "Content-Type: application/json" \
  -d '{
    "title": "バグレポート",
    "description": "詳細説明",
    "status": "unsolved"
  }'
```

---

## トラブルシューティング

### Q: エラーメッセージが表示されない

**A**: デバッグモード設定を確認してください
```php
// config/config.php
define('DEBUG_MODE', true);  // 開発環境
define('DEBUG_MODE', false); // 本番環境
```

### Q: ログが記録されない

**A**: error_log パス設定を確認してください
```php
// php.ini
error_log = /var/log/php-errors.log
```

### Q: バリデーションルールを追加したい

**A**: InputValidator クラスに新しいメソッドを追加してください

```php
public function validateCustom($value, $rule, $fieldName = '') {
    // 独自のバリデーション実装
}
```

---

## 関連するファイル

- `/classes/ApiErrorHandler.php` - エラーハンドリング
- `/classes/InputValidator.php` - 入力値検証
- `/api/index.php` - API ルーター
- `/api/endpoints/*.php` - 各エンドポイント実装

---

**バージョン**: 1.0.0
**最終更新**: 2025-10-30
