#!/bin/bash

# Idioma API v2.0.0 - cURL Examples
# Base URL
BASE_URL="http://localhost:8000"
API_URL="$BASE_URL/api"

# Admin credentials
ADMIN_EMAIL="admin@idioma-club.com"
ADMIN_PASSWORD="Admin123!@"

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Idioma API v2.0.0 - cURL Examples${NC}"
echo -e "${BLUE}========================================${NC}\n"

# ============================================
# 🔐 AUTHENTIFICATION
# ============================================
echo -e "${YELLOW}🔐 AUTHENTIFICATION${NC}\n"

# 1. Register
echo -e "${GREEN}1. Register - Créer un compte${NC}"
curl -X POST "$API_URL/auth/register" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "Test123!@",
    "firstName": "John",
    "lastName": "Doe",
    "phone": "+33612345678"
  }' | jq .
echo ""

# 2. Login
echo -e "${GREEN}2. Login - Se connecter${NC}"
TOKEN=$(curl -s -X POST "$API_URL/login_check" \
  -H "Content-Type: application/json" \
  -d "{
    \"email\": \"$ADMIN_EMAIL\",
    \"password\": \"$ADMIN_PASSWORD\"
  }" | jq -r '.token')

echo "Token: $TOKEN"
echo ""

# ============================================
# 📊 DASHBOARD
# ============================================
echo -e "${YELLOW}📊 DASHBOARD${NC}\n"

# 1. Get Profile
echo -e "${GREEN}1. Get Profile - Mon profil${NC}"
curl -X GET "$API_URL/dashboard/profile" \
  -H "Authorization: Bearer $TOKEN" | jq .
echo ""

# 2. Get Subscription
echo -e "${GREEN}2. Get Subscription - Mon abonnement${NC}"
curl -X GET "$API_URL/dashboard/subscription" \
  -H "Authorization: Bearer $TOKEN" | jq .
echo ""

# 3. Get Payments
echo -e "${GREEN}3. Get Payments - Mes paiements${NC}"
curl -X GET "$API_URL/dashboard/payments" \
  -H "Authorization: Bearer $TOKEN" | jq .
echo ""

# 4. Get QR Code
echo -e "${GREEN}4. Get QR Code - Mon QR code${NC}"
curl -X GET "$API_URL/dashboard/qr-code" \
  -H "Authorization: Bearer $TOKEN" | jq .
echo ""

# 5. Get Next Subscription
echo -e "${GREEN}5. Get Next Subscription - Prochain abonnement${NC}"
curl -X GET "$API_URL/dashboard/next-subscription" \
  -H "Authorization: Bearer $TOKEN" | jq .
echo ""

# ============================================
# ✅ CHECK-IN
# ============================================
echo -e "${YELLOW}✅ CHECK-IN${NC}\n"

# 1. Create Check-in
echo -e "${GREEN}1. Create Check-in - Arrivée${NC}"
CHECKIN_ID=$(curl -s -X POST "$API_URL/check-in" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "location": "Paris"
  }' | jq -r '.checkIn.id')

echo "Check-in ID: $CHECKIN_ID"
echo ""

# 2. Get Today Check-ins
echo -e "${GREEN}2. Get Today Check-ins - Check-ins du jour${NC}"
curl -X GET "$API_URL/check-in/today" \
  -H "Authorization: Bearer $TOKEN" | jq .
echo ""

# 3. Checkout
echo -e "${GREEN}3. Checkout - Départ${NC}"
curl -X PATCH "$API_URL/check-in/$CHECKIN_ID/checkout" \
  -H "Authorization: Bearer $TOKEN" | jq .
echo ""

# 4. Get Check-in History
echo -e "${GREEN}4. Get Check-in History - Historique${NC}"
curl -X GET "$API_URL/check-in/history" \
  -H "Authorization: Bearer $TOKEN" | jq .
echo ""

# ============================================
# ⚙️ API PLATFORM CRUD
# ============================================
echo -e "${YELLOW}⚙️ API PLATFORM CRUD${NC}\n"

# 1. List Subscription Plans
echo -e "${GREEN}1. List Subscription Plans${NC}"
curl -X GET "$API_URL/subscription_plans" \
  -H "Authorization: Bearer $TOKEN" | jq .
echo ""

# 2. List Subscriptions
echo -e "${GREEN}2. List Subscriptions${NC}"
curl -X GET "$API_URL/subscriptions" \
  -H "Authorization: Bearer $TOKEN" | jq .
echo ""

# 3. List Payments
echo -e "${GREEN}3. List Payments${NC}"
curl -X GET "$API_URL/payments" \
  -H "Authorization: Bearer $TOKEN" | jq .
echo ""

# 4. List Check-ins
echo -e "${GREEN}4. List Check-ins${NC}"
curl -X GET "$API_URL/check_ins" \
  -H "Authorization: Bearer $TOKEN" | jq .
echo ""

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}Tests terminés${NC}"
echo -e "${BLUE}========================================${NC}"

