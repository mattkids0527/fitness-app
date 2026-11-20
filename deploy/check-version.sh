#!/usr/bin/env bash
#
# 檢查要發布的版號，GitHub Actions 部署前執行；也可以在本地先試：deploy/check-version.sh v1.3.0
#
# 規則（任一條不符就以非 0 結束）：
#   1. 格式必須是 vMAJOR.MINOR.PATCH，例如 v1.3.0
#   2. 同名的 git tag 不可已存在
#   3. 必須大於目前最大的 v* tag（以 sort -V 比較）
#
# 只看本機已有的 tag，執行前要先 git fetch --tags（Actions 的 checkout 已設定抓取全部 tag）。

set -euo pipefail

# 印出錯誤後結束；在 GitHub Actions 上以 ::error:: 顯示在執行摘要
fail() {
    if [ -n "${GITHUB_ACTIONS:-}" ]; then
        echo "::error::$1"
    else
        echo "錯誤：$1" >&2
    fi
    exit 1
}

version="${1:-}"

if [[ ! "$version" =~ ^v(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$ ]]; then
    fail "版號格式錯誤：「${version}」，應為 v數字.數字.數字，例如 v1.3.0"
fi

if git rev-parse -q --verify "refs/tags/${version}" >/dev/null; then
    fail "版號 ${version} 已經用過了（tag 已存在），請改用更大的版號"
fi

latest="$(git tag -l 'v*' | grep -E '^v[0-9]+\.[0-9]+\.[0-9]+$' | sort -V | tail -n 1 || true)"
if [ -n "$latest" ] && [ "$(printf '%s\n%s\n' "$latest" "$version" | sort -V | tail -n 1)" != "$version" ]; then
    fail "版號 ${version} 比目前最新的 ${latest} 小，請改用更大的版號"
fi

echo "版號 ${version} 可以使用（目前最新：${latest:-尚無}）"
