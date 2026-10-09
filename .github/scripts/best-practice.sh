#!/usr/bin/env bash
# Static best-practice checks for the package source (no vendor/ needed).
set -uo pipefail

SRC="${1:-src}"
fail=0

php_files() {
    find "$SRC" -name '*.php' ! -name '*.blade.php' -print0
}

echo "::group::PHP lint (syntax + deprecations)"
lint_out=$(php_files | xargs -0 -n1 php -d display_errors=stderr -d error_reporting=E_ALL -l 2>&1 | grep -v '^No syntax errors detected')
if [ -n "$lint_out" ]; then
    echo "$lint_out"
    echo "::error::PHP lint reported errors or deprecations"
    fail=1
fi
echo "::endgroup::"

# Matches only active calls: lines whose first non-space chars are not a comment
# check <level: error|warning> <title> <regex>
check() {
    local level="$1" title="$2" pattern="$3"
    local hits
    hits=$(php_files | xargs -0 grep -HnE "$pattern" 2>/dev/null | grep -vE '^[^:]+:[0-9]+:\s*(//|#|\*|/\*)')
    if [ -n "$hits" ]; then
        echo "::group::$title"
        while IFS= read -r line; do
            file="${line%%:*}"; rest="${line#*:}"; lineno="${rest%%:*}"
            echo "::$level file=$file,line=$lineno::$title"
            echo "$line"
        done <<< "$hits"
        echo "::endgroup::"
        [ "$level" = "error" ] && fail=1
    fi
}

check error "Debug call left in code (dd/dump/var_dump/print_r/die)" '(^|[^A-Za-z0-9_>:$])(dd|dump|var_dump|print_r|die)\s*\('
check error "env() used outside config files (returns null after config:cache)" "(^|[^A-Za-z0-9_>:\$])env\s*\(\s*'"
# first argument (the SQL string) contains request(): bound parameters after the comma are fine
check error "Request input concatenated into raw SQL" '(whereRaw|selectRaw|orderByRaw|havingRaw|DB::raw|DB::select)\s*\([^,]*request\('
check warning "TLS verification disabled" 'CURLOPT_SSL_VERIFY(PEER|HOST)\s*(=>|,)\s*(false|0)'
check error "Hard-coded Google/FCM API key" 'AIza[0-9A-Za-z_-]{35}'

if [ "$fail" -ne 0 ]; then
    echo "Best-practice checks failed"
    exit 1
fi
echo "All best-practice checks passed"
