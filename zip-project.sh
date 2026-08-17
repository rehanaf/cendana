#!/usr/bin/env bash
set -euo pipefail

# Usage: ./zip-project.sh [default|all|update]
#   default (default) : ikuti .gitignore (hanya file yang di-track git)
#   all               : semua file ikut, termasuk node_modules & vendor
#   update            : ikuti .gitignore TAPI public/build tetap diikutsertakan

MODE="${1:-default}"
TMPDIR="$(mktemp -d)"
trap 'rm -rf "$TMPDIR"' EXIT

case "$MODE" in
  default)
    OUT="cendana-$(date +%Y%m%d-%H%M%S).zip"
    git ls-files -z | xargs -0 -I{} cp --parents {} "$TMPDIR/"
    ;;
  all)
    OUT="cendana-full-$(date +%Y%m%d-%H%M%S).zip"
    rsync -a --exclude='.git/' ./ "$TMPDIR/project/"
    ;;
  update)
    OUT="cendana-update-$(date +%Y%m%d-%H%M%S).zip"
    git ls-files -z | xargs -0 -I{} cp --parents {} "$TMPDIR/"
    if [ -d public/build ]; then
      cp -r --parents public/build "$TMPDIR/"
    fi
    ;;
  *)
    echo "Argumen tidak dikenal: $MODE" >&2
    echo "Gunakan: $0 [default|all|update]" >&2
    exit 1
    ;;
esac

if [ "$MODE" = "all" ]; then
  (cd "$TMPDIR/project" && zip -qr "$OLDPWD/$OUT" .)
else
  (cd "$TMPDIR" && zip -qr "$OLDPWD/$OUT" .)
fi

echo "Berhasil: $OUT ($(du -h "$OUT" | cut -f1))"