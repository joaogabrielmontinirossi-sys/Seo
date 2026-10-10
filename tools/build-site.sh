#!/usr/bin/env bash
# Monta o site estático do GitHub Pages em _site/ (ou no diretório passado),
# incluindo o plugin empacotado em seo-legibilidade.zip.
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
out="${1:-$root/_site}"
# Caminho absoluto: o zip roda depois de um cd para a pasta temporária.
mkdir -p "$out"
out="$(cd "$out" && pwd)"
tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

rm -rf "$out"
mkdir -p "$out"
cp -R "$root/web/." "$out/"

# O .zip contém só os arquivos do plugin, dentro da pasta seo-legibilidade/.
mkdir -p "$tmp/seo-legibilidade"
cp -R "$root/seo-legibilidade.php" "$root/uninstall.php" "$root/README.md" "$root/includes" "$root/assets" "$tmp/seo-legibilidade/"
(cd "$tmp" && zip -qr "$out/seo-legibilidade.zip" seo-legibilidade)

touch "$out/.nojekyll"
echo "Site gerado em $out"
