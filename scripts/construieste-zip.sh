#!/usr/bin/env bash
# Construiește dist/p45-carti-digitale.zip, arhiva care se încarcă în WordPress
# (Module → Adaugă nou → Încarcă modul). Pune în arhivă doar fișierele pluginului:
# fără README, capturi de ecran sau scripturi.
#
# Folosire:  bash scripts/construieste-zip.sh          arhiva în dist/
#            bash scripts/construieste-zip.sh --demo   și o copie în demo/, pentru demo-ul online
set -euo pipefail

NUME="p45-carti-digitale"
RADACINA="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DEST="$RADACINA/dist"
ARHIVA="$DEST/$NUME.zip"
CONTINUT=("$NUME.php" "CITESTE-MA.txt" "LICENSE" "includes" "admin" "templates" "assets")

cd "$RADACINA"
VERSIUNE="$(sed -n 's/^ \* Version: *//p' "$NUME.php" | tr -d '[:space:]')"

for f in "${CONTINUT[@]}"; do
	[ -e "$f" ] || { echo "Lipsește: $f" >&2; exit 1; }
done

LUCRU="$(mktemp -d)"
trap 'rm -rf "$LUCRU"' EXIT
mkdir -p "$LUCRU/$NUME" "$DEST"
cp -R "${CONTINUT[@]}" "$LUCRU/$NUME/"
find "$LUCRU" \( -name '.DS_Store' -o -name 'Thumbs.db' -o -name '*.map' \) -delete
rm -f "$ARHIVA"

if command -v zip >/dev/null 2>&1; then
	(cd "$LUCRU" && zip -rqX "$ARHIVA" "$NUME")
else
	# Fără „zip” (de exemplu în Git Bash pe Windows): folosim Python.
	PY="$(command -v python3 || command -v python || true)"
	[ -n "$PY" ] || { echo "Am nevoie de „zip” sau de Python 3 ca să fac arhiva." >&2; exit 1; }
	"$PY" - "$LUCRU" "$NUME" "$ARHIVA" <<'PY'
import os, sys, zipfile
baza, nume, arhiva = sys.argv[1], sys.argv[2], sys.argv[3]
with zipfile.ZipFile(arhiva, "w", zipfile.ZIP_DEFLATED, compresslevel=9) as z:
    for dosar, _, fisiere in os.walk(os.path.join(baza, nume)):
        for f in sorted(fisiere):
            cale = os.path.join(dosar, f)
            z.write(cale, os.path.relpath(cale, baza).replace(os.sep, "/"))
PY
fi

echo "Gata: dist/$NUME.zip (versiunea $VERSIUNE, $(du -h "$ARHIVA" | cut -f1))"

if [ "${1:-}" = "--demo" ]; then
	cp "$ARHIVA" "$RADACINA/demo/$NUME.zip"
	echo "Copiată și în demo/$NUME.zip"
fi
