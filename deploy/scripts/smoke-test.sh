#!/usr/bin/env bash
# ==============================================================================
# Post-Deployment Smoke Test Script: PALCOM AI Scheduler
# Verifikasi kesehatan HTTP, aset statis, dan konektivitas database
# ==============================================================================
set -euo pipefail

BASE_URL="${1:-http://127.0.0.1:8000}"
ERRORS=0

echo "======================================================================"
echo "    POST-DEPLOYMENT SMOKE TEST: PALCOM AI SCHEDULER                  "
echo "    Target URL: ${BASE_URL}                                          "
echo "======================================================================"

test_url() {
    local url="$1"
    local expected_code="$2"
    local desc="$3"

    echo -n "• Menguji [${desc}] -> ${url} ... "
    local http_code
    http_code="$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 5 "$url" || echo "000")"

    if [ "$http_code" = "$expected_code" ]; then
        echo "PASS (HTTP ${http_code})"
    else
        echo "FAIL (Diharapkan HTTP ${expected_code}, didapat HTTP ${http_code})"
        ERRORS=$((ERRORS + 1))
    fi
}

# 1. Endpoint Kesehatan Laravel & Halaman Utama / Login
test_url "${BASE_URL}/up" "200" "Laravel Health Check Route"
test_url "${BASE_URL}/" "200" "Halaman Beranda / Welcome"
test_url "${BASE_URL}/login" "200" "Halaman Login"

# 2. Halaman Modul Terproteksi Autentikasi (Harus Redirect 302 untuk Guest)
test_url "${BASE_URL}/schedules" "302" "Redirect Auth Jadwal Mengajar"
test_url "${BASE_URL}/instructors" "302" "Redirect Auth Instruktur"
test_url "${BASE_URL}/rooms" "302" "Redirect Auth Ruangan"
test_url "${BASE_URL}/activity-log" "302" "Redirect Auth Activity Log"
test_url "${BASE_URL}/ai-scheduler" "302" "Redirect Auth AI Scheduler"

# 3. Uji Keberadaan File Asset Ter-bundle
test_url "${BASE_URL}/build/manifest.json" "200" "Vite Build Manifest"

# 4. Uji Blokir Keamanan File Sensitif (Harus 403 atau 404)
echo -n "• Menguji Proteksi Keamanan [.env file] ... "
sec_code="$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 5 "${BASE_URL}/.env" || echo "000")"
if [ "$sec_code" = "404" ] || [ "$sec_code" = "403" ]; then
    echo "PASS (Ditolak dengan aman HTTP ${sec_code})"
else
    echo "FAIL (File sensitif terbuka! HTTP ${sec_code})"
    ERRORS=$((ERRORS + 1))
fi

echo "======================================================================"
if [ "$ERRORS" -eq 0 ]; then
    echo "✓ SEMUA SMOKE TEST LULUS DENGAN SUKSES! APLIKASI SEHAT."
    exit 0
else
    echo "✗ TERDAPAT ${ERRORS} KEGAGALAN SMOKE TEST! PERIKSA LOG SERVER."
    exit 1
fi
