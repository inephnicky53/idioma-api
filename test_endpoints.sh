#!/bin/bash

# Script de test des endpoints Idioma API v2.0.0
# Utilisation: bash test_endpoints.sh

BASE_URL="http://localhost:8000"
RESULTS_FILE="test_results.txt"

# Couleurs
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
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
    
    echo -e "${YELLOW}Test: $description${NC}"
    
    if [ -z "$data" ]; then
        response=$(curl -s -w "\n%{http_code}" -X $method "$BASE_URL$endpoint" \
            -H "Content-Type: application/json")
    else
        response=$(curl -s -w "\n%{http_code}" -X $method "$BASE_URL$endpoint" \
            -H "Content-Type: application/json" \
            -d "$data")
    fi
    
    http_code=$(echo "$response" | tail -n1)
    body=$(echo "$response" | head -n-1)
    
    if [ "$http_code" = "$expected_code" ]; then
        echo -e "${GREEN}✓ PASS${NC} - HTTP $http_code"
        echo "✓ $description - HTTP $http_code" >> $RESULTS_FILE
    else
        echo -e "${RED}✗ FAIL${NC} - Expected $expected_code, got $http_code"
        echo "✗ $description - Expected $expected_code, got $http_code" >> $RESULTS_FILE
    fi
    
    echo "Response: $body" >> $RESULTS_FILE
    echo "" >> $RESULTS_FILE
}

echo "=== DÉMARRAGE DES TESTS ==="
echo ""

# Test 1: Vérifier que l'API répond
echo -e "${YELLOW}Test 1: Vérifier que l'API répond${NC}"
response=$(curl -s -w "\n%{http_code}" "$BASE_URL/api")
http_code=$(echo "$response" | tail -n1)
if [ "$http_code" = "200" ] || [ "$http_code" = "404" ]; then
    echo -e "${GREEN}✓ API répond${NC}"
else
    echo -e "${RED}✗ API ne répond pas (HTTP $http_code)${NC}"
    echo "Assurez-vous que le serveur est démarré: symfony server:start"
    exit 1
fi
echo ""

# Test 2: Authentification - Register
echo -e "${YELLOW}Test 2: Authentification - Register${NC}"
register_data='{
    "email": "test@example.com",
    "password": "Test123!@",
    "firstName": "Test",
    "lastName": "User"
}'
test_endpoint "POST" "/api/auth/register" "$register_data" "201" "Register - Créer un nouvel utilisateur"
echo ""

# Test 3: Authentification - Login
echo -e "${YELLOW}Test 3: Authentification - Login${NC}"
login_data='{
    "email": "test@example.com",
    "password": "Test123!@"
}'
response=$(curl -s -X POST "$BASE_URL/api/auth/login" \
    -H "Content-Type: application/json" \
    -d "$login_data")
JWT_TOKEN=$(echo "$response" | grep -o '"token":"[^"]*' | cut -d'"' -f4)
REFRESH_TOKEN=$(echo "$response" | grep -o '"refreshToken":"[^"]*' | cut -d'"' -f4)

if [ -n "$JWT_TOKEN" ]; then
    echo -e "${GREEN}✓ Login réussi${NC}"
    echo "JWT Token obtenu: ${JWT_TOKEN:0:20}..."
    echo "Refresh Token obtenu: ${REFRESH_TOKEN:0:20}..."
else
    echo -e "${RED}✗ Login échoué${NC}"
    echo "Response: $response"
fi
echo ""

# Test 4: Dashboard - Profile
echo -e "${YELLOW}Test 4: Dashboard - Profile${NC}"
if [ -n "$JWT_TOKEN" ]; then
    response=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/api/dashboard/profile" \
        -H "Authorization: Bearer $JWT_TOKEN")
    http_code=$(echo "$response" | tail -n1)
    if [ "$http_code" = "200" ]; then
        echo -e "${GREEN}✓ Dashboard Profile${NC}"
    else
        echo -e "${RED}✗ Dashboard Profile - HTTP $http_code${NC}"
    fi
else
    echo -e "${RED}✗ Impossible de tester (pas de JWT token)${NC}"
fi
echo ""

# Test 5: Dashboard - Subscription
echo -e "${YELLOW}Test 5: Dashboard - Subscription${NC}"
if [ -n "$JWT_TOKEN" ]; then
    response=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/api/dashboard/subscription" \
        -H "Authorization: Bearer $JWT_TOKEN")
    http_code=$(echo "$response" | tail -n1)
    if [ "$http_code" = "200" ] || [ "$http_code" = "404" ]; then
        echo -e "${GREEN}✓ Dashboard Subscription${NC}"
    else
        echo -e "${RED}✗ Dashboard Subscription - HTTP $http_code${NC}"
    fi
else
    echo -e "${RED}✗ Impossible de tester (pas de JWT token)${NC}"
fi
echo ""

# Test 6: Dashboard - Payments
echo -e "${YELLOW}Test 6: Dashboard - Payments${NC}"
if [ -n "$JWT_TOKEN" ]; then
    response=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/api/dashboard/payments" \
        -H "Authorization: Bearer $JWT_TOKEN")
    http_code=$(echo "$response" | tail -n1)
    if [ "$http_code" = "200" ]; then
        echo -e "${GREEN}✓ Dashboard Payments${NC}"
    else
        echo -e "${RED}✗ Dashboard Payments - HTTP $http_code${NC}"
    fi
else
    echo -e "${RED}✗ Impossible de tester (pas de JWT token)${NC}"
fi
echo ""

# Test 7: Dashboard - QR Code
echo -e "${YELLOW}Test 7: Dashboard - QR Code${NC}"
if [ -n "$JWT_TOKEN" ]; then
    response=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/api/dashboard/qr-code" \
        -H "Authorization: Bearer $JWT_TOKEN")
    http_code=$(echo "$response" | tail -n1)
    if [ "$http_code" = "200" ]; then
        echo -e "${GREEN}✓ Dashboard QR Code${NC}"
    else
        echo -e "${RED}✗ Dashboard QR Code - HTTP $http_code${NC}"
    fi
else
    echo -e "${RED}✗ Impossible de tester (pas de JWT token)${NC}"
fi
echo ""

# Test 8: Check-in - Create
echo -e "${YELLOW}Test 8: Check-in - Create${NC}"
if [ -n "$JWT_TOKEN" ]; then
    checkin_data='{
        "location": "Salle 1",
        "notes": "Test check-in"
    }'
    response=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/api/check-in" \
        -H "Authorization: Bearer $JWT_TOKEN" \
        -H "Content-Type: application/json" \
        -d "$checkin_data")
    http_code=$(echo "$response" | tail -n1)
    if [ "$http_code" = "201" ] || [ "$http_code" = "400" ]; then
        echo -e "${GREEN}✓ Check-in Create${NC}"
    else
        echo -e "${RED}✗ Check-in Create - HTTP $http_code${NC}"
    fi
else
    echo -e "${RED}✗ Impossible de tester (pas de JWT token)${NC}"
fi
echo ""

# Test 9: Check-in - Today
echo -e "${YELLOW}Test 9: Check-in - Today${NC}"
if [ -n "$JWT_TOKEN" ]; then
    response=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/api/check-in/today" \
        -H "Authorization: Bearer $JWT_TOKEN")
    http_code=$(echo "$response" | tail -n1)
    if [ "$http_code" = "200" ]; then
        echo -e "${GREEN}✓ Check-in Today${NC}"
    else
        echo -e "${RED}✗ Check-in Today - HTTP $http_code${NC}"
    fi
else
    echo -e "${RED}✗ Impossible de tester (pas de JWT token)${NC}"
fi
echo ""

# Test 10: Check-in - History
echo -e "${YELLOW}Test 10: Check-in - History${NC}"
if [ -n "$JWT_TOKEN" ]; then
    response=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/api/check-in/history" \
        -H "Authorization: Bearer $JWT_TOKEN")
    http_code=$(echo "$response" | tail -n1)
    if [ "$http_code" = "200" ]; then
        echo -e "${GREEN}✓ Check-in History${NC}"
    else
        echo -e "${RED}✗ Check-in History - HTTP $http_code${NC}"
    fi
else
    echo -e "${RED}✗ Impossible de tester (pas de JWT token)${NC}"
fi
echo ""

# Test 11: Admin - EasyAdmin
echo -e "${YELLOW}Test 11: Admin - EasyAdmin${NC}"
response=$(curl -s -w "\n%{http_code}" -X GET "$BASE_URL/admin")
http_code=$(echo "$response" | tail -n1)
if [ "$http_code" = "302" ] || [ "$http_code" = "200" ]; then
    echo -e "${GREEN}✓ EasyAdmin accessible${NC}"
else
    echo -e "${RED}✗ EasyAdmin - HTTP $http_code${NC}"
fi
echo ""

echo "=== TESTS TERMINÉS ==="
echo ""
echo "Résultats sauvegardés dans: $RESULTS_FILE"
echo ""
echo "Résumé:"
echo "- Tous les endpoints ont été testés"
echo "- Vérifiez $RESULTS_FILE pour les détails"
echo ""

