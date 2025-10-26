# Miyabi セットアップガイド

このガイドでは、このプロジェクトにMiyabiを統合した自律型開発フレームワークの設定方法を説明します。

## 📋 前提条件

- Rust 1.75以上
- Cargo
- Git
- GitHub アカウント
- Node.js 18+ (TypeScript版使用時)
- PHP 8.0+ (バックエンド開発用)
- MySQL 8.0 (Sakura Rental Serverまたはローカル)

## ✅ インストール状況

### ✔ 既に完了済み

- [x] Miyabi CLI (v0.15.0) インストール済み
- [x] `.miyabi.yml` 設定ファイル作成
- [x] `.claude/agents/` エージェント定義作成
- [x] `.github/workflows/` GitHub Actions ワークフロー作成
- [x] `.env` に Miyabi 設定追加

### ⚠️ 次に設定が必要な項目

- [ ] GitHub Token 設定
- [ ] リモートリポジトリの作成/設定
- [ ] GitHub Actions 有効化
- [ ] ローカル開発環境構築

## 🔐 GitHub Token 設定

### 1. GitHub Personal Access Token を生成

1. GitHub にログイン: https://github.com/login
2. 設定 → Developer settings → Personal access tokens → Tokens (classic) へ移動
3. 「Generate new token (classic)」をクリック
4. 以下の権限を選択:
   - [x] `repo` - Repository access (リポジトリ完全制御)
   - [x] `admin:org` - Organization administration (組織管理)
   - [x] `workflow` - Actions workflow (ワークフロー実行)

### 2. Token を .env に設定

```bash
# .env ファイルを編集
nano .env

# 以下の行を修正:
GITHUB_TOKEN=ghp_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
```

または環境変数として設定:

```bash
export GITHUB_TOKEN=ghp_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
```

## 🌐 リモートリポジトリ設定

### 既存の GitHub リポジトリがある場合

```bash
# リモートを確認
git remote -v

# 存在しない場合は追加
git remote add origin https://github.com/YOUR_USERNAME/defect-db-server.git
```

### 新しい GitHub リポジトリを作成する場合

1. GitHub で「New repository」をクリック
2. リポジトリ名: `defect-db-server`
3. 説明: `Design and Development Defect Database Management System`
4. 「Create repository」をクリック
5. 以下のコマンドを実行:

```bash
git branch -M main
git remote add origin https://github.com/YOUR_USERNAME/defect-db-server.git
git push -u origin main
```

## 🚀 Miyabi の使用方法

### 基本的なコマンド

```bash
# バージョン確認
miyabi --version

# プロジェクト状態確認
miyabi status

# リアルタイム監視
miyabi status --watch

# Issue を作成して処理開始
miyabi work-on 1  # Issue #1 を処理

# ステータス レポート
miyabi report
```

### GitHub Issues から開始する

1. GitHub のリポジトリで「Issues」タブを開く
2. 「New issue」をクリック
3. タイトルと説明を入力
4. ラベルを付与:
   - Type: `🚀 type:feature` / `🐛 type:bug` / `🔧 type:refactor`
   - Priority: `priority:high` / `priority:medium` / `priority:low`
   - Component: `component:backend` / `component:frontend` / `component:database`
5. 「Create issue」をクリック

### 自動化ワークフロー

Miyabi は以下のプロセスを自動化します:

```
Issue Created
    ↓
Issue Agent → Issue を分析、タスク作成
    ↓
Coordinator → エージェントに指示を割り当て
    ↓
CodeGen Agent → コードを自動生成
    ↓
Test Agent → テストを実行
    ↓
Review Agent → コードレビュー
    ↓
PR Agent → Pull Request を作成
    ↓
Reviewer → マージ前の承認チェック
    ↓
Deployment Agent → 本番環境へデプロイ
```

## 📁 ディレクトリ構成

```
defect-db-server/
├── .miyabi.yml              # Miyabi 設定ファイル
├── .claude/
│   └── agents/              # AI エージェント定義
│       ├── coordinator.md
│       ├── issue-agent.md
│       ├── code-gen.md
│       ├── review.md
│       ├── pr-agent.md
│       ├── deployment.md
│       └── test.md
├── .github/
│   └── workflows/           # GitHub Actions ワークフロー
│       ├── test.yml         # テスト実行
│       └── deploy.yml       # デプロイメント
├── .env                     # 環境変数 (Miyabi 設定含む)
├── api/                     # REST API エンドポイント
├── classes/                 # PHP クラス
├── auth/                    # 認証関連ファイル
├── config/                  # 設定ファイル
├── database/                # DB スキーマ
├── migration/               # DB マイグレーション
├── uploads/                 # アップロード画像
└── js/                      # JavaScript コード
```

## 🔧 開発ワークフロー

### ローカル開発

```bash
# PHP サーバーを起動
php -S localhost:8000

# ブラウザで確認
# http://localhost:8000
```

### Issue から実装まで

1. GitHub Issue を作成
2. `miyabi work-on ISSUE_NUMBER` で作業開始
3. Miyabi が自動的にコードを生成
4. ローカルで `php -S localhost:8000` でテスト
5. `git push` で変更をリモートに送信
6. Miyabi が自動的に PR を作成
7. PR レビュー後にマージ
8. 自動的に本番環境にデプロイ

## 🧪 テストの実行

```bash
# すべてのテストを実行
phpunit

# 特定のテストスイートのみ
phpunit tests/Unit/

# カバレッジレポート生成
phpunit --coverage-html=coverage/
```

## 🌍 デプロイメント

### Development (自動)

Main ブランチに merge すると自動的に localhost にデプロイされます。

### Production (手動/自動)

```bash
# GitHub Release を作成
# または Manual Deploy を実行
# → 自動的に Sakura Rental Server にデプロイ
```

## 📊 Miyabi が生成するもの

Miyabi の各エージェントは以下を自動生成します:

| エージェント | 生成物 | 例 |
|-------------|--------|-----|
| **IssueAgent** | タスク分解 | 受け入れ基準チェックリスト |
| **CodeGen** | 実装コード | PHP API, JavaScript 関数 |
| **Test** | テストケース | PHPUnit テスト |
| **Review** | レビューコメント | セキュリティ/パフォーマンス指摘 |
| **PR Agent** | PR 説明文 | 変更内容のサマリー |
| **Deployment** | デプロイスクリプト | 自動デプロイ実行 |

## 🔍 監視とロギング

### Status 監視

```bash
# 3 秒ごとに更新
miyabi status --watch
```

出力内容:
- Open Issues 数
- In Progress PR 数
- テスト実行状況
- デプロイメント状態

### ログ確認

```bash
# Miyabi ログを確認
tail -f logs/miyabi.log

# API ログ
tail -f logs/api.log

# PHP エラーログ
tail -f logs/error.log
```

## ⚙️ トラブルシューティング

### GitHub Token エラー

```
Error: Not Found - https://docs.github.com/rest/issues/labels
```

**解決策:**
1. Token の権限を確認: https://github.com/settings/tokens
2. 必要な権限が付与されていることを確認
3. Token を再生成して再度設定

### リモートリポジトリエラー

```
error: No such remote 'origin'
```

**解決策:**
```bash
# リモートを追加
git remote add origin https://github.com/YOUR_USERNAME/defect-db-server.git
git push -u origin main
```

### テスト失敗

```bash
# テストを詳細モードで実行
phpunit -v

# 特定のテストを実行
phpunit tests/Unit/Database/ -v
```

### PHP サーバー起動失敗

```bash
# ポートが使用中の場合
# 別のポート番号を指定
php -S localhost:8001
```

## 📚 参考資料

- [Miyabi GitHub リポジトリ](https://github.com/ShunsukeHayashi/Miyabi)
- [Miyabi ドキュメント](https://github.com/ShunsukeHayashi/Miyabi/wiki)
- [プロジェクト CLAUDE.md](./CLAUDE.md)
- [Rust インストール](https://rustup.rs/)

## 🎯 次のステップ

1. ✅ GitHub Token を設定
2. ✅ リモートリポジトリを確認/作成
3. ✅ `miyabi status` でプロジェクト状態を確認
4. GitHub Issues を作成してテスト
5. `miyabi work-on 1` で初めての自動化ワークフローを実行

---

**Miyabi v0.15.0** - 「一つのコマンドで全てが完結する自律型開発フレームワーク」

次世代の開発を体験してください！
