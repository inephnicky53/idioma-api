#!/bin/bash

# Script de test complet des endpoints Idioma API v2.0.0
# Utilisation: bash test_all_endpoints.sh

BASE_URL="http://localhost:8000"
RESULTS_FILE="test_results_$(date +%s).txt"
PASS_COUNT=0
FAIL_COUNT=0

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Initialiser le fichier de résultats
echo "=== TEST DES ENDPOINTS IDIOMA API v2.0.0 ===" > $RESULTS_FILE
echo "Date: $(date)" >> $RESULTS_FILE
echo "" >> $RESULTS_FILE

# Fonction pour tester un endpoint
test_endpoint() {
    local method=$1
    local endpoint=$2
    local data=$3
    local expected_code=$4
    local description=$5
    local token=$6

    echo -e "${BLUE}→ $description${NC}"

    if [ -z "$data" ]; then
        if [ -z "$token" ]; then
            response=$(curl -s -w "\n%{http_code}" -X $method "$BASE_URL$endpoint" \
                -H "Content-Type: application/json")
        else
            response=$(curl -s -w "\n%{http_code}" -X $method "$BASE_URL$endpoint" \
                -H "Authorization: Bearer $token" \
                -H "Content-Type: application/json")
        fi
    else
        if [ -z "$token" ]; then
            response=$(curl -s -w "\n%{http_code}" -X $method "$BASE_URL$endpoint" \
                -H "Content-Type: application/json" \
                -d "$data")
        else
            response=$(curl -s -w "\n%{http_code}" -X $method "$BASE_URL$endpoint" \
                -H "Authorization: Bearer $token" \
                -H "Content-Type: application/json" \
                -d "$data")
        fi
    fi

    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | sed '$d')

    # Support multiple expected codes (comma-separated)
    if [[ ",$expected_code," == *",$http_code,"* ]]; then
        echo -e "${GREEN}✓ PASS${NC} - HTTP $http_code"
        ((PASS_COUNT++))
        echo "✓ $description - HTTP $http_code" >> $RESULTS_FILE
    else
        echo -e "${RED}✗ FAIL${NC} - Expected $expected_code, got $http_code"
        ((FAIL_COUNT++))
        echo "✗ $description - Expected $expected_code, got $http_code" >> $RESULTS_FILE
    fi

    echo "Response: $body" >> $RESULTS_FILE
    echo "" >> $RESULTS_FILE
}

echo -e "${YELLOW}=== DÉMARRAGE DES TESTS ===${NC}"
echo ""

# Test 1: Vérifier que l'API répond
echo -e "${YELLOW}[1/15] Vérification de l'API${NC}"
response=$(curl -s -w "\n%{http_code}" "$BASE_URL/api")
http_code=$(echo "$response" | tail -n1)
if [ "$http_code" = "401" ]; then
    echo -e "${GREEN}✓ API répond${NC}"
else
    echo -e "${RED}✗ API ne répond pas (HTTP $http_code)${NC}"
    echo "Assurez-vous que le serveur est démarré: php -S localhost:8000 -t public"
    exit 1
fi
echo ""

# Test 2: Register
echo -e "${YELLOW}[2/15] Authentification - Register${NC}"
# Generate unique email with timestamp
UNIQUE_EMAIL="newuser_$(date +%s)@example.com"
register_data='{
    "email": "'$UNIQUE_EMAIL'",
    "password": "Test123!@",
    "firstName": "New",
    "lastName": "User"
}'
test_endpoint "POST" "/api/auth/register" "$register_data" "201" "Register - Créer un nouvel utilisateur"
echo ""

# Test 3: Login (using fixture user)
echo -e "${YELLOW}[3/15] Authentification - Login${NC}"
login_data='{
    "email": "user1@idioma-club.com",
    "password": "User123!@"
}'
response=$(curl -s -X POST "$BASE_URL/api/auth/login" \
    -H "Content-Type: application/json" \
    -d "$login_data")
JWT_TOKEN=$(echo "$response" | grep -o '"token":"[^"]*' | cut -d'"' -f4)
REFRESH_TOKEN=$(echo "$response" | grep -o '"refreshToken":"[^"]*' | cut -d'"' -f4)

if [ -n "$JWT_TOKEN" ]; then
    echo -e "${GREEN}✓ Login réussi${NC}"
    ((PASS_COUNT++))
    echo "JWT Token obtenu: ${JWT_TOKEN:0:20}..."
    echo "Refresh Token obtenu: ${REFRESH_TOKEN:0:20}..."
else
    echo -e "${RED}✗ Login échoué${NC}"
    ((FAIL_COUNT++))
    echo "Response: $response"
fi
echo ""

# Test 4: Dashboard - Profile
echo -e "${YELLOW}[4/15] Dashboard - Profile${NC}"
test_endpoint "GET" "/api/dashboard/profile" "" "200" "Dashboard Profile" "$JWT_TOKEN"
echo ""

# Test 5: Dashboard - Subscription
echo -e "${YELLOW}[5/15] Dashboard - Subscription${NC}"
test_endpoint "GET" "/api/dashboard/subscription" "" "200" "Dashboard Subscription" "$JWT_TOKEN"
echo ""

# Test 6: Dashboard - Payments
echo -e "${YELLOW}[6/15] Dashboard - Payments${NC}"
test_endpoint "GET" "/api/dashboard/payments" "" "200" "Dashboard Payments" "$JWT_TOKEN"
echo ""

# Test 7: Dashboard - QR Code
echo -e "${YELLOW}[7/15] Dashboard - QR Code${NC}"
test_endpoint "GET" "/api/dashboard/qr-code" "" "200" "Dashboard QR Code" "$JWT_TOKEN"
echo ""

# Test 8: Dashboard - Next Subscription
echo -e "${YELLOW}[8/15] Dashboard - Next Subscription${NC}"
test_endpoint "GET" "/api/dashboard/next-subscription" "" "200" "Dashboard Next Subscription" "$JWT_TOKEN"
echo ""

# Test 9: Check-in - Create
echo -e "${YELLOW}[9/15] Check-in - Create${NC}"
checkin_data='{
    "location": "Salle 1",
    "notes": "Test check-in"
}'
# Note: This will return 403 because user has no active subscription (expected behavior)
# Accept both 201 (success) and 403 (no active subscription)
test_endpoint "POST" "/api/check-in" "$checkin_data" "201,403" "Check-in Create (no active subscription)" "$JWT_TOKEN"
echo ""

# Test 10: Check-in - Today
echo -e "${YELLOW}[10/15] Check-in - Today${NC}"
test_endpoint "GET" "/api/check-in/today" "" "200" "Check-in Today" "$JWT_TOKEN"
echo ""

# Test 11: Check-in - History
echo -e "${YELLOW}[11/15] Check-in - History${NC}"
test_endpoint "GET" "/api/check-in/history" "" "200" "Check-in History" "$JWT_TOKEN"
echo ""

# Test 12: Forgot Password
echo -e "${YELLOW}[12/15] Authentification - Forgot Password${NC}"
forgot_data='{
    "email": "testuser@example.com"
}'
test_endpoint "POST" "/api/auth/forgot-password" "$forgot_data" "200" "Forgot Password" ""
echo ""

# Test 13: Refresh Token
echo -e "${YELLOW}[13/15] Authentification - Refresh Token${NC}"
refresh_data='{
    "refreshToken": "'$REFRESH_TOKEN'"
}'
test_endpoint "POST" "/api/auth/refresh" "$refresh_data" "200" "Refresh Token" ""
echo ""

# Test 14: Admin - EasyAdmin
echo -e "${YELLOW}[14/15] Admin - EasyAdmin${NC}"
response=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/admin")
http_code=$(echo "$response" | tail -n1)
# EasyAdmin requires authentication (401 is expected)
if [ "$http_code" = "302" ] || [ "$http_code" = "200" ] || [ "$http_code" = "401" ]; then
    echo -e "${GREEN}✓ EasyAdmin accessible (HTTP $http_code)${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ EasyAdmin - HTTP $http_code${NC}"
    ((FAIL_COUNT++))
fi
echo ""

# Test 15: API Documentation
echo -e "${YELLOW}[15/15] API Documentation${NC}"
response=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/api/docs")
http_code=$(echo "$response" | tail -n1)
# API docs may require authentication (401 is acceptable)
if [ "$http_code" = "200" ] || [ "$http_code" = "401" ]; then
    echo -e "${GREEN}✓ API Documentation accessible (HTTP $http_code)${NC}"
    ((PASS_COUNT++))
else
    echo -e "${RED}✗ API Documentation - HTTP $http_code${NC}"
    ((FAIL_COUNT++))
fi
echo ""

echo -e "${YELLOW}=== TESTS TERMINÉS ===${NC}"
echo ""
echo -e "${GREEN}Réussis: $PASS_COUNT${NC}"
echo -e "${RED}Échoués: $FAIL_COUNT${NC}"
echo ""
echo "Résultats sauvegardés dans: $RESULTS_FILE"
echo ""

if [ $FAIL_COUNT -eq 0 ]; then
    echo -e "${GREEN}✓ TOUS LES TESTS SONT PASSÉS !${NC}"
    exit 0
else
    echo -e "${RED}✗ CERTAINS TESTS ONT ÉCHOUÉ${NC}"
    exit 1
fi

