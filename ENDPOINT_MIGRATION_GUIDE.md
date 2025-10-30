# API エンドポイント統合ガイド

## 概要

本ガイドは、既存の API エンドポイントを新しいエラーハンドリングシステム（ApiErrorHandler と InputValidator）に統合する方法を説明します。

**完了済みエンドポイント:**
- ✅ `/api/defects` - defects.php (完全に更新)

**今後更新が必要なエンドポイント (21個):**
- auth.php
- users.php
- categories.php
- processes.php
- upload.php
- notifications.php
- comments.php
- reactions.php
- export.php
- change-password.php
- user-profile.php
- notification-settings.php
- images.php
- user-registrations.php
- assignments.php
- knowledge-base.php
- audit-logs.php
- backup.php
- dashboard.php
- excel.php
- templates.php

---

## 移行パターン

### パターン 1: シンプルな置き換え（最も多い）

**変更前:**
```php
apiError('エラーメッセージ', 400);
```

**変更後:**
```php
ApiErrorHandler::error(
    'エラーメッセージ',
    ApiErrorHandler::INVALID_INPUT_ERROR,
    ApiErrorHandler::HTTP_BAD_REQUEST
);
```

### パターン 2: 認証エラー

**変更前:**
```php
apiError('認証が必要です', 401);
```

**変更後:**
```php
ApiErrorHandler::authenticationError();
```

### パターン 3: 権限エラー

**変更前:**
```php
apiError('管理者権限が必要です', 403);
```

**変更後:**
```php
ApiErrorHandler::authorizationError('管理者権限が必要です');
```

### パターン 4: リソース未検出

**変更前:**
```php
apiError('ユーザーが見つかりません', 404);
```

**変更後:**
```php
ApiErrorHandler::notFoundError('ユーザー');
```

### パターン 5: データベースエラー

**変更前:**
```php
} catch (Exception $e) {
    apiError('操作に失敗しました: ' . $e->getMessage(), 500);
}
```

**変更後:**
```php
} catch (Exception $e) {
    ApiErrorHandler::databaseError($e, DEBUG_MODE);
}
```

### パターン 6: JSON入力解析

**変更前:**
```php
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    apiError('無効なJSONデータです');
}
```

**変更後:**
```php
$input = InputValidator::getJsonInput(true);
if (!$input) {
    ApiErrorHandler::error(
        'リクエストボディが空です',
        ApiErrorHandler::INVALID_INPUT_ERROR,
        ApiErrorHandler::HTTP_BAD_REQUEST
    );
}
```

### パターン 7: 入力値検証

**変更前:**
```php
if (!isset($input['email']) || !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    apiError('メールアドレスが無効です');
}
```

**変更後:**
```php
$validator = new InputValidator();
$validator->validateRequired($input, ['email']);
$validator->validateType($input['email'] ?? '', 'email', 'email');

if (!$validator->isValid()) {
    ApiErrorHandler::validationError($validator->getErrors());
}
```

---

## 高優先度エンドポイント（推奨順）

### 1. auth.php

関連する認証機能：
- ログイン：ユーザー名/パスワード検証
- 登録：メールアドレス型検証
- パスワード変更：現在のパスワード検証

```php
// 推奨変更
$validator = new InputValidator();
$validator->validateRequired($input, ['username', 'password']);
$validator->validateLength($input['username'] ?? '', 3, 50, 'username');
$validator->validateType($input['email'] ?? '', 'email', 'email');

if (!$validator->isValid()) {
    ApiErrorHandler::validationError($validator->getErrors());
}
```

### 2. upload.php

ファイルアップロード検証：

```php
// 推奨変更
if (!isset($_FILES['image'])) {
    ApiErrorHandler::error(
        'ファイルが指定されていません',
        ApiErrorHandler::INVALID_INPUT_ERROR,
        ApiErrorHandler::HTTP_BAD_REQUEST
    );
}

$validator = new InputValidator();
$validator->validateFile(
    $_FILES['image'],
    [
        'maxSize' => 5 * 1024 * 1024,
        'allowedMimes' => ['image/jpeg', 'image/png', 'image/gif']
    ],
    'image'
);

if (!$validator->isValid()) {
    ApiErrorHandler::validationError($validator->getErrors());
}
```

### 3. categories.php & processes.php

カテゴリ/プロセス管理：

```php
// 推奨変更
$validator = new InputValidator();
$validator->validateRequired($input, ['name']);
$validator->validateLength($input['name'] ?? '', 1, 100, 'name');

if (!$validator->isValid()) {
    ApiErrorHandler::validationError($validator->getErrors());
}
```

### 4. users.php

ユーザー管理：

```php
// 推奨変更
$validator = new InputValidator();
$validator->validateRequired($input, ['username', 'email', 'role']);
$validator->validateType($input['email'] ?? '', 'email', 'email');
$validator->validateAllowedValues(
    $input['role'] ?? '',
    ['admin', 'user', 'viewer'],
    'role'
);

if (!$validator->isValid()) {
    ApiErrorHandler::validationError($validator->getErrors());
}
```

---

## 段階的な移行手順

### ステップ 1: エンドポイント分析（5分）
```bash
# 各ファイルで apiError の出現回数をカウント
grep -n "apiError" api/endpoints/*.php | wc -l
```

### ステップ 2: 単純なエラーメッセージから置き換え（15分）
- 汎用エラー（pattern 1）
- 認証エラー（pattern 2）
- 権限エラー（pattern 3）

### ステップ 3: 複雑な検証ロジックを置き換え（20分）
- JSON入力解析（pattern 6）
- 入力値検証（pattern 7）
- ファイルアップロード

### ステップ 4: テストと検証（10分）
- 各エンドポイントの curl テスト
- エラーレスポンス形式の確認
- ログ記録の確認

---

## エラーコードマッピング表

| HTTP Status | エラーコード | 用途 |
|---|---|---|
| 400 | VALIDATION_ERROR | 入力値検証失敗 |
| 400 | INVALID_INPUT_ERROR | 無効なリクエスト |
| 401 | AUTHENTICATION_ERROR | 認証不足 |
| 403 | AUTHORIZATION_ERROR | 権限不足 |
| 404 | NOT_FOUND_ERROR | リソース未検出 |
| 500 | DATABASE_ERROR | DB操作エラー |
| 500 | FILE_ERROR | ファイル操作エラー |
| 500 | SERVER_ERROR | サーバーエラー |

---

## チェックリスト

各エンドポイント更新時に以下を確認：

- [ ] 全ての `apiError()` 呼び出しが置き換えられている
- [ ] JSON入力解析が `InputValidator::getJsonInput()` を使用
- [ ] 入力検証が `InputValidator` クラスを使用
- [ ] 例外処理が `ApiErrorHandler` メソッドを使用
- [ ] 認証チェックが `ApiErrorHandler::authenticationError()` を使用
- [ ] 権限チェックが `ApiErrorHandler::authorizationError()` を使用
- [ ] リソース未検出が `ApiErrorHandler::notFoundError()` を使用

---

## 参考資料

- `/API_ERROR_HANDLING_GUIDE.md` - 詳細な実装ガイド
- `/classes/ApiErrorHandler.php` - エラーハンドラー実装
- `/classes/InputValidator.php` - 入力値検証実装
- `/ISSUE_4_IMPLEMENTATION_SUMMARY.md` - Issue #4 完了レポート

---

## 更新予定

**フェーズ 1（推奨）:**
1. auth.php
2. users.php
3. upload.php

**フェーズ 2:**
4. categories.php
5. processes.php
6. notifications.php

**フェーズ 3:**
7. その他のエンドポイント

---

**作成日**: 2025-10-31
**最終更新**: 2025-10-31
