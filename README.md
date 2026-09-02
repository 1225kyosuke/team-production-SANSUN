# SANSUN学園 成績管理システム（プロトタイプ）

## 起動方法

Laravel APIとNext.jsフロントエンドに分離しています。プロジェクト直下で以下を実行すると、両方をまとめて起動できます。

```bash
bash start-local.sh
```

ブラウザで `http://localhost:3001` を開いてください。個別に起動する場合は、以下をそれぞれ別ターミナルで実行します。

```bash
cd backend && php artisan migrate --force && php artisan serve --host=127.0.0.1 --port=8888
cd frontend && npm run dev -- --port 3001
```

終了するときは、起動したターミナルで `Ctrl+C` を押してください。

### 初回だけ行う準備

画面確認用データを最初から作り直す場合のみ、次を実行します。このコマンドは既存データをすべて削除するため、通常起動には使用しないでください。

```bash
cd backend
php artisan migrate:fresh --seed
```

### `Address already in use` と表示された場合

同じサーバーがすでに起動しています。まず `http://localhost:3001` を開き、アプリが表示されるか確認してください。停止する場合は、起動したターミナルで `Ctrl+C` を押します。どのプロセスが使用しているかは次のコマンドで確認できます。

```bash
lsof -nP -iTCP:3001 -sTCP:LISTEN
lsof -nP -iTCP:8888 -sTCP:LISTEN
```

表示されたPIDを確認して、そのプロセスを起動したターミナルから停止してください。

API URLを変える場合は、`frontend/.env.local` に `NEXT_PUBLIC_API_URL` を設定します。

デモログイン：専任職員 `staff@sansun.test / Staff123`、講師 `teacher@sansun.test / Teacher123`

`frontend/.env.local.example` をコピーして利用できます。LaravelはSQLiteを標準設定として利用します。

## 実装済み

- 講師ビューでの学生別成績入力、入力範囲チェック、下書き保存
- 出席率・授業態度・提出課題の重みによる総合点・評定の自動計算
- 重みの合計100%チェックと保存時の一括再計算
- 未入力件数の可視化
- 専任職員ビュー（閲覧専用）と未入力時の確定ブロック
- Laravel API・SQLiteへの成績データ保存

- ログイン、役割別権限、10分間の無操作ログアウト、同時ログイン防止
- 学生・講師・専任職員・科目の管理とCSV一括取込
- 科目と講師、学生の履修紐づけ
- 年度・学期別の成績確定、確定解除、編集ロック
- 学生別成績履歴、検索、CSV／PDF出力
