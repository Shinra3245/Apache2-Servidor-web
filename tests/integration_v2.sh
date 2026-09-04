#!/usr/bin/env bash

set -euo pipefail

base_url="${BASE_URL:-http://127.0.0.1:8082/api/v2}"
username="${API_TEST_USERNAME:-api_profesor}"
: "${API_TEST_PASSWORD:?Define API_TEST_PASSWORD para ejecutar las pruebas}"

response_file="$(mktemp /tmp/api-v2-response.XXXXXX)"
trap 'rm -f "$response_file"' EXIT

request() {
    local method="$1"
    local url="$2"
    local body="${3:-}"
    local token="${4:-}"
    local args=(-sS -o "$response_file" -w '%{http_code}' -X "$method")

    if [[ -n "$body" ]]; then
        args+=(-H 'Content-Type: application/json' --data "$body")
    fi

    if [[ -n "$token" ]]; then
        args+=(-H "Authorization: Bearer ${token}")
    fi

    curl "${args[@]}" "$url"
}

assert_status() {
    local description="$1"
    local expected="$2"
    local actual="$3"

    if [[ "$actual" != "$expected" ]]; then
        echo "FAIL: ${description}; HTTP esperado ${expected}, recibido ${actual}"
        cat "$response_file"
        exit 1
    fi

    echo "PASS: ${description} (HTTP ${actual})"
}

invalid_body="$(
    jq -nc --arg username "$username" \
        '{username: $username, password: "incorrecta"}'
)"
status="$(request POST "${base_url}/login" "$invalid_body")"
assert_status 'login fallido' 401 "$status"

status="$(request GET "${base_url}/products")"
assert_status 'recurso sin token' 401 "$status"

status="$(request GET "${base_url}/me" '' 'token-invalido')"
assert_status 'recurso con token inválido' 401 "$status"

login_body="$(
    jq -nc --arg username "$username" --arg password "$API_TEST_PASSWORD" \
        '{username: $username, password: $password}'
)"
status="$(request POST "${base_url}/login" "$login_body")"
assert_status 'login exitoso' 200 "$status"
first_token="$(jq -r '.access_token // empty' "$response_file")"

if [[ ${#first_token} -ne 64 ]]; then
    echo 'FAIL: el token no contiene los 32 bytes codificados en hexadecimal'
    exit 1
fi
echo 'PASS: token aleatorio de 32 bytes codificado en hexadecimal'

status="$(request GET "${base_url}/me" '' "$first_token")"
assert_status 'perfil con token válido' 200 "$status"

if jq -e 'has("password_hash")' "$response_file" >/dev/null; then
    echo 'FAIL: /me expuso password_hash'
    exit 1
fi
echo 'PASS: /me no expone password_hash'

status="$(request GET "${base_url}/products" '' "$first_token")"
assert_status 'productos con token válido' 200 "$status"

status="$(request GET "${base_url}/users" '' "$first_token")"
assert_status 'usuarios con token válido' 200 "$status"

status="$(request POST "${base_url}/login" "$login_body")"
assert_status 'segundo login exitoso' 200 "$status"
second_token="$(jq -r '.access_token // empty' "$response_file")"

status="$(request GET "${base_url}/me" '' "$first_token")"
assert_status 'token anterior invalidado' 401 "$status"

status="$(request POST "${base_url}/logout" '' "$second_token")"
assert_status 'logout' 200 "$status"

status="$(request GET "${base_url}/me" '' "$second_token")"
assert_status 'token revocado rechazado' 401 "$status"

echo 'Todas las pruebas de integración V2 finalizaron correctamente.'
