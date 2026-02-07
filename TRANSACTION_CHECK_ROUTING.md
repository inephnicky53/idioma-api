# Transaction Check Routing - Refactoring v2.0.18

## 📋 Résumé

Refactoring du système de vérification des transactions de paiement. Création d'un endpoint API dédié pour vérifier le statut d'une transaction auprès du provider de paiement.

## 🎯 Objectif

Séparer la logique de vérification des transactions de la validation manuelle des paiements en créant un endpoint dédié accessible aux utilisateurs et administrateurs.

## 📝 Changements Effectués

### 1. Nouveau Processor: CheckTransactionProcessor
**Fichier**: `src/State/Processor/CheckTransactionProcessor.php`

- Implémente `ProcessorInterface` d'API Platform
- Vérifie que l'utilisateur est propriétaire du paiement ou administrateur
- Appelle `PaymentManager::check()` pour interroger le provider
- Retourne le paiement mis à jour avec le statut actuel

### 2. Nouvelle Opération API Platform
**Fichier**: `src/Entity/Payment.php`

Ajout d'une opération PATCH:
```
PATCH /payments/{id}/check-transaction
```

**Sécurité**:
- Utilisateur connecté ET propriétaire du paiement OU administrateur
- Empêche l'accès non autorisé aux paiements d'autres utilisateurs

**Réponse**: Entité Payment avec le statut mis à jour

### 3. Tests Unitaires
**Fichier**: `tests/State/Processor/CheckTransactionProcessorTest.php`

3 tests créés (100% passing):
- ✅ `testProcessWithValidPayment()` - Vérification réussie
- ✅ `testProcessWithoutUser()` - Rejet sans utilisateur
- ✅ `testProcessWithUnauthorizedUser()` - Rejet utilisateur non autorisé

## 🔄 Flux de Vérification

```
Client (Frontend)
    ↓
PATCH /payments/{id}/check-transaction
    ↓
CheckTransactionProcessor
    ↓
Vérification sécurité (propriétaire ou admin)
    ↓
PaymentManager::check()
    ↓
FlexPayProvider::checkTransaction()
    ↓
Mise à jour du statut
    ↓
Retour Payment avec nouveau statut
```

## 🔐 Sécurité

- ✅ Authentification JWT requise
- ✅ Vérification propriétaire du paiement
- ✅ Accès admin sans restriction
- ✅ Validation des données

## 📊 Endpoints Disponibles

| Méthode | Route | Description | Sécurité |
|---------|-------|-------------|----------|
| POST | `/payments` | Créer un paiement | ROLE_USER |
| GET | `/payments` | Lister les paiements | ROLE_USER |
| GET | `/payments/{id}` | Détails du paiement | Propriétaire ou ROLE_ADMIN |
| PATCH | `/payments/{id}/validate` | Valider (admin) | ROLE_ADMIN |
| **PATCH** | **`/payments/{id}/check-transaction`** | **Vérifier statut** | **Propriétaire ou ROLE_ADMIN** |

## 🚀 Utilisation

### Exemple cURL
```bash
curl -X PATCH http://localhost:8000/api/payments/1/check-transaction \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -H "Content-Type: application/json"
```

### Réponse
```json
{
  "id": 1,
  "status": "Complété",
  "transactionId": "TXN123456",
  "paidAt": "2026-02-07T10:30:00Z",
  "responsedAt": "2026-02-07T10:35:00Z",
  "notes": "FlexPay: Transaction approuvée"
}
```

## ✅ Validation

- ✅ Syntaxe PHP vérifiée
- ✅ Container Symfony validé
- ✅ Tests unitaires (3/3 passing)
- ✅ Lint YAML réussi
- ✅ Commit créé et pushé

## 📦 Version

**Idioma API v2.0.18** - Transaction Check Routing

## 🔗 Fichiers Modifiés

- `src/State/Processor/CheckTransactionProcessor.php` (NEW)
- `src/Entity/Payment.php` (MODIFIED)
- `tests/State/Processor/CheckTransactionProcessorTest.php` (NEW)

## 📌 Notes

- La méthode `PaymentManager::check()` existante est réutilisée
- Pas de modification du CallbackController (reste pour les callbacks FlexPay)
- Pas de modification du ValidatePaymentProcessor (reste pour validation admin)
- Endpoint accessible aux utilisateurs pour vérifier leurs propres paiements

