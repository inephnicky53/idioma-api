/**
 * Types pour la gestion des taux de change
 * Synchronisés avec: src/Entity/Rate.php du backend
 */

import { Currency } from './enums';

// ============================================
// RATE ENTITY
// ============================================

export interface Rate {
  id: number;
  fromCurrency: Currency;
  toCurrency: Currency;
  rate: string; // Decimal stocké comme string pour la précision
  isActive: boolean;
  createdAt: string;
  updatedAt: string | null;
}

// ============================================
// API REQUESTS
// ============================================

export interface CreateRateRequest {
  fromCurrency: Currency;
  toCurrency: Currency;
  rate: string;
  isActive?: boolean;
}

export interface UpdateRateRequest {
  fromCurrency?: Currency;
  toCurrency?: Currency;
  rate?: string;
  isActive?: boolean;
}

// ============================================
// CONVERSION
// ============================================

export interface ConvertRequest {
  amount: number;
  from: Currency;
  to: Currency;
}

export interface ConversionResult {
  originalAmount: number;
  originalCurrency: Currency;
  convertedAmount: number;
  targetCurrency: Currency;
  rate: number;
}

// ============================================
// HELPERS
// ============================================

/**
 * Formater un montant avec le symbole de devise
 */
export const formatCurrency = (amount: number, currency: Currency): string => {
  const formatted = new Intl.NumberFormat('fr-FR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);

  switch (currency) {
    case Currency.USD:
      return `$${formatted}`;
    case Currency.CDF:
      return `${formatted} FC`;
    default:
      return formatted;
  }
};

/**
 * Obtenir le symbole de la devise
 */
export const getCurrencySymbol = (currency: Currency): string => {
  switch (currency) {
    case Currency.USD:
      return '$';
    case Currency.CDF:
      return 'FC';
    default:
      return '';
  }
};

