<?php
/**
 * 入力値検証ヘルパークラス
 * 不具合データベース v4.0.0
 *
 * API エンドポイントの入力値検証機能を提供
 */

class InputValidator {

    private $errors = [];

    /**
     * 必須フィールドを検証
     *
     * @param array $data データ配列
     * @param array $requiredFields 必須フィールド名の配列
     * @return bool
     */
    public function validateRequired(array $data, array $requiredFields) {
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
                $this->errors[$field][] = "{$field}は必須です";
            }
        }

        return empty($this->errors);
    }

    /**
     * フィールドの型を検証
     *
     * @param mixed $value 値
     * @param string $type 期待される型 (string, int, float, bool, array, email, url)
     * @param string $fieldName フィールド名
     * @return bool
     */
    public function validateType($value, $type, $fieldName = '') {
        if ($value === null || $value === '') {
            return true; // 空の場合はスキップ
        }

        $valid = false;

        switch ($type) {
            case 'int':
            case 'integer':
                $valid = is_numeric($value) && intval($value) == $value;
                break;

            case 'float':
            case 'number':
                $valid = is_numeric($value);
                break;

            case 'string':
                $valid = is_string($value);
                break;

            case 'bool':
            case 'boolean':
                $valid = is_bool($value) || in_array($value, ['true', 'false', '0', '1', 0, 1], true);
                break;

            case 'array':
                $valid = is_array($value);
                break;

            case 'email':
                $valid = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                break;

            case 'url':
                $valid = filter_var($value, FILTER_VALIDATE_URL) !== false;
                break;

            case 'date':
                $valid = strtotime($value) !== false;
                break;

            case 'datetime':
                $valid = strtotime($value) !== false;
                break;
        }

        if (!$valid && !empty($fieldName)) {
            $this->errors[$fieldName][] = "{$fieldName}は{$type}型である必要があります";
        }

        return $valid;
    }

    /**
     * 長さを検証
     *
     * @param string $value 値
     * @param int $minLength 最小長
     * @param int $maxLength 最大長
     * @param string $fieldName フィールド名
     * @return bool
     */
    public function validateLength($value, $minLength = 0, $maxLength = null, $fieldName = '') {
        if (is_null($value) || $value === '') {
            return true;
        }

        $length = strlen($value);

        if ($length < $minLength) {
            if (!empty($fieldName)) {
                $this->errors[$fieldName][] = "{$fieldName}は{$minLength}文字以上である必要があります";
            }
            return false;
        }

        if ($maxLength !== null && $length > $maxLength) {
            if (!empty($fieldName)) {
                $this->errors[$fieldName][] = "{$fieldName}は{$maxLength}文字以下である必要があります";
            }
            return false;
        }

        return true;
    }

    /**
     * 範囲を検証
     *
     * @param mixed $value 値
     * @param mixed $min 最小値
     * @param mixed $max 最大値
     * @param string $fieldName フィールド名
     * @return bool
     */
    public function validateRange($value, $min = null, $max = null, $fieldName = '') {
        if ($value === null || $value === '') {
            return true;
        }

        if ($min !== null && $value < $min) {
            if (!empty($fieldName)) {
                $this->errors[$fieldName][] = "{$fieldName}は{$min}以上である必要があります";
            }
            return false;
        }

        if ($max !== null && $value > $max) {
            if (!empty($fieldName)) {
                $this->errors[$fieldName][] = "{$fieldName}は{$max}以下である必要があります";
            }
            return false;
        }

        return true;
    }

    /**
     * 許可された値かを検証
     *
     * @param mixed $value 値
     * @param array $allowedValues 許可された値の配列
     * @param string $fieldName フィールド名
     * @return bool
     */
    public function validateAllowedValues($value, array $allowedValues, $fieldName = '') {
        if ($value === null || $value === '') {
            return true;
        }

        if (!in_array($value, $allowedValues, true)) {
            if (!empty($fieldName)) {
                $allowedStr = implode(', ', $allowedValues);
                $this->errors[$fieldName][] = "{$fieldName}は以下のいずれかである必要があります: {$allowedStr}";
            }
            return false;
        }

        return true;
    }

    /**
     * 正規表現でマッチするか検証
     *
     * @param string $value 値
     * @param string $pattern 正規表現パターン
     * @param string $fieldName フィールド名
     * @return bool
     */
    public function validatePattern($value, $pattern, $fieldName = '') {
        if ($value === null || $value === '') {
            return true;
        }

        if (!preg_match($pattern, $value)) {
            if (!empty($fieldName)) {
                $this->errors[$fieldName][] = "{$fieldName}の形式が不正です";
            }
            return false;
        }

        return true;
    }

    /**
     * ファイルをアップロード検証
     *
     * @param array $file $_FILES 要素
     * @param array $options 検証オプション (maxSize, allowedMimes など)
     * @param string $fieldName フィールド名
     * @return bool
     */
    public function validateFile(array $file, array $options = [], $fieldName = '') {
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            $this->errors[$fieldName][] = "ファイルアップロードエラー: " . $this->getUploadErrorMessage($file['error'] ?? 0);
            return false;
        }

        // ファイルサイズチェック
        $maxSize = $options['maxSize'] ?? 5 * 1024 * 1024; // デフォルト5MB
        if ($file['size'] > $maxSize) {
            $this->errors[$fieldName][] = "ファイルサイズが大きすぎます (最大: " . floor($maxSize / 1024 / 1024) . "MB)";
            return false;
        }

        // MIME タイプチェック
        if (isset($options['allowedMimes']) && is_array($options['allowedMimes'])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mimeType, $options['allowedMimes'])) {
                $this->errors[$fieldName][] = "ファイル形式が許可されていません";
                return false;
            }
        }

        return true;
    }

    /**
     * バリデーションエラーを取得
     *
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }

    /**
     * バリデーションに成功したか
     *
     * @return bool
     */
    public function isValid() {
        return empty($this->errors);
    }

    /**
     * エラーをクリア
     */
    public function clearErrors() {
        $this->errors = [];
    }

    /**
     * ファイルアップロードエラーメッセージを取得
     *
     * @param int $errorCode UPLOAD_ERR_* 定数
     * @return string
     */
    private function getUploadErrorMessage($errorCode) {
        $errors = [
            UPLOAD_ERR_OK => 'エラーなし',
            UPLOAD_ERR_INI_SIZE => 'ファイルサイズが php.ini の設定値を超えています',
            UPLOAD_ERR_FORM_SIZE => 'ファイルサイズがフォームの制限を超えています',
            UPLOAD_ERR_PARTIAL => 'ファイルが部分的にしかアップロードされていません',
            UPLOAD_ERR_NO_FILE => 'ファイルがアップロードされていません',
            UPLOAD_ERR_NO_TMP_DIR => 'テンポラリディレクトリが見つかりません',
            UPLOAD_ERR_CANT_WRITE => 'ファイルの書き込みに失敗しました',
            UPLOAD_ERR_EXTENSION => 'PHP 拡張機能によってファイルアップロードが停止されました'
        ];

        return $errors[$errorCode] ?? '不明なエラーが発生しました';
    }

    /**
     * 入力データをサニタイズ
     *
     * @param mixed $value 値
     * @return mixed
     */
    public static function sanitize($value) {
        if (is_array($value)) {
            return array_map([self::class, 'sanitize'], $value);
        }

        if (is_string($value)) {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }

        return $value;
    }

    /**
     * JSON 入力を取得
     *
     * @param bool $assoc 連想配列として返すか
     * @return array|null
     */
    public static function getJsonInput($assoc = true) {
        $json = file_get_contents('php://input');
        if (empty($json)) {
            return null;
        }

        return json_decode($json, $assoc);
    }
}
?>
