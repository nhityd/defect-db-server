<?php
/**
 * 不具合データAPI エンドポイント
 */

try {
    $auth = requireAuth();
    $db = Database::getInstance();
} catch (Exception $e) {
    apiLog("Initialization error: " . $e->getMessage());
    ApiErrorHandler::serverError($e, DEBUG_MODE);
}

switch ($method) {
    case 'GET':
        if (isset($segments[1]) && is_numeric($segments[1])) {
            $id = intval($segments[1]);

            if (isset($segments[2]) && $segments[2] === 'qr') {
                // QRコード生成
                try {
                    $defect = getDefectById($db, $id);
                    if (!$defect) {
                        ApiErrorHandler::notFoundError('不具合データ');
                    }

                    // 不具合詳細ページへのURLを生成
                    $defectUrl = BASE_URL . '/defect.html?id=' . $id; // 仮のフロントエンドURL

                    // QRコード生成ライブラリの利用
                    // Composerでインストールされていることを前提とします
                    // composer require chillerlan/php-qrcode
                    $qrcode = new chillerlan\QRCode\QRCode();
                    header('Content-Type: image/png');
                    echo $qrcode->render($defectUrl);
                    exit;

                } catch (Exception $e) {
                    apiLog("QR code generation error: " . $e->getMessage());
                    ApiErrorHandler::serverError($e, DEBUG_MODE);
                }
            } else {
                // 個別不具合取得
                try {
                    $defect = getDefectById($db, $id);

                    if (!$defect) {
                        ApiErrorHandler::notFoundError('不具合データ');
                    }

                    apiResponse($defect);
                } catch (Exception $e) {
                    apiLog("Get defect by ID error: " . $e->getMessage());
                    ApiErrorHandler::databaseError($e, DEBUG_MODE);
                }
            }
        } else {
            // 不具合一覧取得
            try {
                apiLog("Getting all defects...");
                $defects = getAllDefects($db);
                apiLog("Successfully retrieved " . count($defects) . " defects");
                apiResponse($defects);
            } catch (Exception $e) {
                apiLog("Get all defects error: " . $e->getMessage());
                apiLog("Stack trace: " . $e->getTraceAsString());
                ApiErrorHandler::databaseError($e, DEBUG_MODE);
            }
        }
        break;
        
    case 'POST':
        if (isset($segments[1]) && $segments[1] === 'from-template' && isset($segments[2]) && is_numeric($segments[2])) {
            // テンプレートから新規不具合作成
            $templateId = intval($segments[2]);
            $input = json_decode(file_get_contents('php://input'), true); // テンプレートデータに上書きする追加データ

            try {
                $template = $db->selectOne('SELECT * FROM defect_templates WHERE id = ?', [$templateId]);
                if (!$template) {
                    ApiErrorHandler::notFoundError('テンプレート');
                }

                $templateData = json_decode($template['template_data'], true);

                // テンプレートデータとリクエストボディのデータをマージ（リクエストボディが優先）
                $mergedData = array_merge($templateData, $input);

                $defectData = validateDefectData($mergedData);
                $defectData['created_by'] = $_SESSION['user_id'];
                $defectData['created_at'] = date('Y-m-d H:i:s'); // 作成日時を明示的に設定

                $defectId = $db->insert('defects', $defectData);

                $newDefect = getDefectById($db, $defectId);

                // 通知送信（現在は無効化）
                // try {
                //     $notificationService->notifyDefectCreated($newDefect);
                // } catch (Exception $e) {
                //     error_log("Notification error: " . $e->getMessage());
                //     // 通知エラーは処理を停止しない
                // }

                apiResponse($newDefect, 201, 'テンプレートから不具合データを作成しました');

            } catch (Exception $e) {
                error_log("Defect creation from template error: " . $e->getMessage());
                ApiErrorHandler::databaseError($e, DEBUG_MODE);
            }

        } else {
            // 通常の新規不具合作成
            $input = InputValidator::getJsonInput(true);

            if (!$input) {
                ApiErrorHandler::error(
                    'リクエストボディが空です',
                    ApiErrorHandler::INVALID_INPUT_ERROR,
                    ApiErrorHandler::HTTP_BAD_REQUEST
                );
            }

            try {
                $defectData = validateDefectData($input);
                $defectData['created_by'] = $_SESSION['user_id'];
                $defectData['created_at'] = date('Y-m-d H:i:s');

                error_log("Inserting defect data: " . json_encode($defectData));

                $defectId = $db->insert('defects', $defectData);

                // 画像がある場合は関連付け
                if (!empty($input['images'])) {
                    foreach ($input['images'] as $filename) {
                        $db->insert('images', [
                            'defect_id' => $defectId,
                            'filename' => $filename,
                            'original_filename' => $filename
                        ]);
                    }
                }

                $newDefect = getDefectById($db, $defectId);
                apiResponse($newDefect, 201, '不具合データを作成しました');

            } catch (Exception $e) {
                error_log("Defect creation error: " . $e->getMessage());
                error_log("Stack trace: " . $e->getTraceAsString());
                ApiErrorHandler::databaseError($e, DEBUG_MODE);
            }
        }
        break;
        
    case 'PUT':
        if (isset($segments[1]) && is_numeric($segments[1])) {
            $id = intval($segments[1]);
            $input = InputValidator::getJsonInput(true);

            if (!$input) {
                ApiErrorHandler::error(
                    'リクエストボディが空です',
                    ApiErrorHandler::INVALID_INPUT_ERROR,
                    ApiErrorHandler::HTTP_BAD_REQUEST
                );
            }

            if (isset($segments[2]) && $segments[2] === 'rating') {
                // 対策評価の更新
                $validator = new InputValidator();
                $validator->validateType($input['rating'] ?? null, 'int', 'rating');
                $validator->validateRange($input['rating'] ?? null, 0, 5, 'rating');

                if (!$validator->isValid()) {
                    ApiErrorHandler::validationError($validator->getErrors());
                }

                $rating = intval($input['rating']);

                try {
                    // 不具合が存在するか確認
                    $existingDefect = $db->selectOne('SELECT id FROM defects WHERE id = ?', [$id]);
                    if (!$existingDefect) {
                        ApiErrorHandler::notFoundError('不具合データ');
                    }

                    // knowledge_baseにエントリがあるか確認
                    $kbEntry = $db->selectOne('SELECT id FROM knowledge_base WHERE defect_id = ?', [$id]);

                    if ($kbEntry) {
                        // 既存のエントリを更新
                        $db->update(
                            'knowledge_base',
                            ['solution_rating' => $rating, 'updated_at' => date('Y-m-d H:i:s')],
                            'defect_id = ?',
                            [$id]
                        );
                    } else {
                        // 新しいエントリを作成
                        $db->insert(
                            'knowledge_base',
                            ['defect_id' => $id, 'solution_rating' => $rating, 'created_at' => date('Y-m-d H:i:s')]
                        );
                    }

                    apiResponse(['defect_id' => $id, 'solution_rating' => $rating], 200, '対策評価を更新しました');

                } catch (Exception $e) {
                    apiLog("Update solution rating error: " . $e->getMessage());
                    ApiErrorHandler::databaseError($e, DEBUG_MODE);
                }

            } else {
                // 通常の不具合更新
                try {
                    $defectData = validateDefectData($input, false);

                    // 更新前にレコードが存在するか確認
                    $existing = $db->selectOne('SELECT id FROM defects WHERE id = ?', [$id]);
                    if (!$existing) {
                        ApiErrorHandler::notFoundError('不具合データ');
                    }

                    $rowCount = $db->update('defects', $defectData, 'id = ?', [$id]);

                    // 画像更新
                    if (isset($input['images'])) {
                        // 既存の画像を削除
                        $db->delete('images', 'defect_id = ?', [$id]);

                        // 新しい画像を追加
                        foreach ($input['images'] as $filename) {
                            $db->insert('images', [
                                'defect_id' => $id,
                                'filename' => $filename,
                                'original_filename' => $filename
                            ]);
                        }
                    }

                    $updatedDefect = getDefectById($db, $id);
                    apiResponse($updatedDefect, 200, '不具合データを更新しました');

                } catch (Exception $e) {
                    ApiErrorHandler::databaseError($e, DEBUG_MODE);
                }
            }
        } else {
            ApiErrorHandler::error(
                '不具合IDが必要です',
                ApiErrorHandler::INVALID_INPUT_ERROR,
                ApiErrorHandler::HTTP_BAD_REQUEST
            );
        }
        break;
        
    case 'DELETE':
        // 管理者権限チェック
        if ($auth->getCurrentUser()['role'] !== 'admin') {
            ApiErrorHandler::authorizationError('管理者権限が必要です');
        }

        if (!isset($segments[1]) || !is_numeric($segments[1])) {
            ApiErrorHandler::error(
                '不具合IDが必要です',
                ApiErrorHandler::INVALID_INPUT_ERROR,
                ApiErrorHandler::HTTP_BAD_REQUEST
            );
        }

        $id = intval($segments[1]);

        try {
            // 削除前にデータを取得（通知用）
            $defectForNotification = getDefectById($db, $id);

            // 関連する画像ファイルを削除
            $images = $db->select('SELECT filename FROM images WHERE defect_id = ?', [$id]);
            foreach ($images as $image) {
                $imagePath = UPLOAD_DIR . $image['filename'];
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            // データベースから削除（外部キー制約により画像レコードも自動削除）
            $rowCount = $db->delete('defects', 'id = ?', [$id]);

            if ($rowCount === 0) {
                ApiErrorHandler::notFoundError('不具合データ');
            }

            apiResponse(null, 204, '不具合データを削除しました');

        } catch (Exception $e) {
            ApiErrorHandler::databaseError($e, DEBUG_MODE);
        }
        break;

    default:
        ApiErrorHandler::error(
            'サポートされていないメソッドです',
            ApiErrorHandler::INVALID_INPUT_ERROR,
            ApiErrorHandler::HTTP_BAD_REQUEST
        );
}

/**
 * 不具合データバリデーション
 */
function validateDefectData($input, $requireAll = true) {
    $data = [];

    
    // 必須フィールド
    if ($requireAll) {
        if (!isset($input['title']) || trim($input['title']) === '') {
            throw new Exception('タイトルは必須です');
        }
        if (!isset($input['reporter']) || trim($input['reporter']) === '') {
            throw new Exception('記入者は必須です');
        }
        if (!isset($input['reportDate']) || trim($input['reportDate']) === '') {
            throw new Exception('記入日は必須です');
        }
    }
    
    // フィールドマッピング
    $fields = [
        'title' => 'title',
        'description' => 'description',
        'project' => 'project',
        'supplier' => 'supplier',
        'quantity' => 'quantity',
        'status' => 'status',
        'emergencyAction' => 'emergency_action',
        'emergencyContact' => 'emergency_contact',
        'cause' => 'cause',
        'permanentAction' => 'permanent_action',
        'prevention' => 'prevention',
        'reporter' => 'reporter',
        'reportDate' => 'report_date'
    ];
    
    foreach ($fields as $inputKey => $dbKey) {
        if (isset($input[$inputKey])) {
            $value = trim($input[$inputKey]);
            // 空文字列の場合はnullまたはデフォルト値を設定
            if ($value === '') {
                if ($dbKey === 'quantity') {
                    $data[$dbKey] = 1; // デフォルト値
                } else {
                    $data[$dbKey] = null;
                }
            } else {
                // quantityフィールドは整数に変換
                if ($dbKey === 'quantity') {
                    $data[$dbKey] = intval($value);
                } else {
                    $data[$dbKey] = $value;
                }
            }
        }
    }
    
    // カテゴリーとプロセスは外部キーのみ保存（名前は保存しない）
    
    // 外部キー処理
    if (isset($input['category']) && !empty(trim($input['category']))) {
        $dbWrapper = Database::getInstance();
        $category = $dbWrapper->selectOne('SELECT id FROM categories WHERE name = ?', [trim($input['category'])]);
        if ($category) {
            $data['category_id'] = $category['id'];
        } else {
            error_log("Category not found: " . trim($input['category']));
            // カテゴリーが見つからない場合は作成
            $categoryId = $dbWrapper->insert('categories', [
                'name' => trim($input['category']),
                'display_order' => 999
            ]);
            $data['category_id'] = $categoryId;
        }
    }

    if (isset($input['process']) && !empty(trim($input['process']))) {
        $dbWrapper = Database::getInstance();
        $process = $dbWrapper->selectOne('SELECT id FROM processes WHERE name = ?', [trim($input['process'])]);
        if ($process) {
            $data['process_id'] = $process['id'];
        } else {
            error_log("Process not found: " . trim($input['process']));
            // プロセスが見つからない場合は作成
            $processId = $dbWrapper->insert('processes', [
                'name' => trim($input['process']),
                'display_order' => 999
            ]);
            $data['process_id'] = $processId;
        }
    }

    // 更新の場合は常にupdated_atを設定
    if (!$requireAll) {
        $data['updated_at'] = date('Y-m-d H:i:s');
    }


    return $data;
}

/**
 * 不具合データ取得（画像付き）
 */
function getDefectById($db, $id) {
    $sql = "
        SELECT d.*, 
               c.name as category, 
               p.name as process,
               u.username as created_by_name
        FROM defects d
        LEFT JOIN categories c ON d.category_id = c.id
        LEFT JOIN processes p ON d.process_id = p.id
        LEFT JOIN users u ON d.created_by = u.id
        WHERE d.id = ?
    ";
    
    $defect = $db->selectOne($sql, [$id]);
    
    if ($defect) {
        // 画像取得
        $images = $db->select('SELECT filename FROM images WHERE defect_id = ?', [$id]);
        $defect['images'] = array_column($images, 'filename');
        
        // フロントエンド用フィールド名に変換
        $defect['emergencyAction'] = $defect['emergency_action'];
        $defect['emergencyContact'] = $defect['emergency_contact'];
        $defect['permanentAction'] = $defect['permanent_action'];
        $defect['reportDate'] = $defect['report_date'];
    }
    
    return $defect;
}

/**
 * 全不具合データ取得（N+1クエリ問題解消版）
 */
function getAllDefects($db) {
    // メインクエリ: 画像を GROUP_CONCAT で一括取得
    $sql = "
        SELECT d.*,
               c.name as category,
               p.name as process,
               u.username as created_by_name,
               GROUP_CONCAT(i.filename ORDER BY i.id SEPARATOR ',') as images_concat
        FROM defects d
        LEFT JOIN categories c ON d.category_id = c.id
        LEFT JOIN processes p ON d.process_id = p.id
        LEFT JOIN users u ON d.created_by = u.id
        LEFT JOIN images i ON d.id = i.defect_id
        GROUP BY d.id
        ORDER BY d.report_date DESC, d.id DESC
    ";

    $defects = $db->select($sql);

    // 各不具合のデータ整形
    foreach ($defects as &$defect) {
        // 画像を配列に変換
        if (!empty($defect['images_concat'])) {
            $defect['images'] = explode(',', $defect['images_concat']);
        } else {
            $defect['images'] = [];
        }
        unset($defect['images_concat']); // 不要なフィールド削除

        // フロントエンド用フィールド名に変換
        $defect['emergencyAction'] = $defect['emergency_action'];
        $defect['emergencyContact'] = $defect['emergency_contact'];
        $defect['permanentAction'] = $defect['permanent_action'];
        $defect['reportDate'] = $defect['report_date'];
    }

    return $defects;
}
?>