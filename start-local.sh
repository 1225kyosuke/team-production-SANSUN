#!/usr/bin/env bash
set -euo pipefail

project_dir="$(cd "$(dirname "$0")" && pwd)"

if ! command -v php >/dev/null || ! command -v npm >/dev/null; then
  echo "PHP または npm が見つかりません。PHP 8.2以上とNode.js 20以上をインストールしてください。"
  exit 1
fi

for port in 8888 3001; do
  if command -v lsof >/dev/null && lsof -nP -iTCP:"$port" -sTCP:LISTEN >/dev/null 2>&1; then
    echo "ポート $port はすでに使用中です。"
    echo "起動済みの成績管理システムを停止するか、READMEの手順で使用中プロセスを確認してください。"
    exit 1
  fi
done

cd "$project_dir/backend"
php artisan migrate --force
php artisan serve --host=127.0.0.1 --port=8888 &
api_pid=$!

cleanup() { kill "$api_pid" 2>/dev/null || true; }
trap cleanup EXIT INT TERM

cd "$project_dir/frontend"
echo "成績管理システムを http://localhost:3001 で起動しています"
npm run dev -- --port 3001
