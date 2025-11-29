import { apiSlice } from './api';
import type { Rate, ConversionResult, ConvertRequest } from '@/types/rate';
import { Currency } from '@/types/enums';

// Type pour la réponse API Platform (hydra)
interface HydraResponse<T> {
  'hydra:member': T[];
  'hydra:totalItems': number;
}

export const rateApi = apiSlice.injectEndpoints({
  endpoints: (builder) => ({
    // Récupérer tous les taux actifs
    getRates: builder.query<Rate[], void>({
      query: () => '/rates?isActive=true',
      transformResponse: (response: HydraResponse<Rate> | Rate[]) => {
        if (Array.isArray(response)) {
          return response;
        }
        return response['hydra:member'] || [];
      },
      providesTags: ['Rate'],
    }),

    // Récupérer un taux spécifique
    getRate: builder.query<Rate, number>({
      query: (id) => `/rates/${id}`,
      providesTags: (_result, _error, id) => [{ type: 'Rate', id }],
    }),

    // Récupérer le taux entre deux devises
    getRateByCurrencies: builder.query<Rate | null, { from: Currency; to: Currency }>({
      query: ({ from, to }) => `/rates?fromCurrency=${from}&toCurrency=${to}&isActive=true`,
      transformResponse: (response: HydraResponse<Rate> | Rate[]) => {
        const rates = Array.isArray(response) ? response : response['hydra:member'] || [];
        return rates.length > 0 ? rates[0] : null;
      },
      providesTags: ['Rate'],
    }),

    // Créer un nouveau taux (admin)
    createRate: builder.mutation<Rate, Partial<Rate>>({
      query: (data) => ({
        url: '/rates',
        method: 'POST',
        body: data,
      }),
      invalidatesTags: ['Rate'],
    }),

    // Mettre à jour un taux (admin)
    updateRate: builder.mutation<Rate, { id: number; data: Partial<Rate> }>({
      query: ({ id, data }) => ({
        url: `/rates/${id}`,
        method: 'PATCH',
        body: data,
      }),
      invalidatesTags: (_result, _error, { id }) => [{ type: 'Rate', id }, 'Rate'],
    }),

    // Supprimer un taux (admin)
    deleteRate: builder.mutation<void, number>({
      query: (id) => ({
        url: `/rates/${id}`,
        method: 'DELETE',
      }),
      invalidatesTags: ['Rate'],
    }),
  }),
});

// Hook personnalisé pour la conversion de devises
export const useConvertCurrency = () => {
  const { data: rates, isLoading } = rateApi.useGetRatesQuery();

  const convert = (request: ConvertRequest): ConversionResult | null => {
    if (!rates || request.from === request.to) {
      return {
        originalAmount: request.amount,
        originalCurrency: request.from,
        convertedAmount: request.amount,
        targetCurrency: request.to,
        rate: 1,
      };
    }

    // Chercher le taux direct
    const directRate = rates.find(
      (r) => r.fromCurrency === request.from && r.toCurrency === request.to
    );

    if (directRate) {
      const convertedAmount = request.amount * parseFloat(directRate.rate);
      return {
        originalAmount: request.amount,
        originalCurrency: request.from,
        convertedAmount: Math.round(convertedAmount * 100) / 100,
        targetCurrency: request.to,
        rate: parseFloat(directRate.rate),
      };
    }

    // Chercher le taux inverse
    const reverseRate = rates.find(
      (r) => r.fromCurrency === request.to && r.toCurrency === request.from
    );

    if (reverseRate && parseFloat(reverseRate.rate) !== 0) {
      const rate = 1 / parseFloat(reverseRate.rate);
      const convertedAmount = request.amount * rate;
      return {
        originalAmount: request.amount,
        originalCurrency: request.from,
        convertedAmount: Math.round(convertedAmount * 100) / 100,
        targetCurrency: request.to,
        rate: rate,
      };
    }

    return null;
  };

  return { convert, isLoading, rates };
};

export const {
  useGetRatesQuery,
  useGetRateQuery,
  useGetRateByCurrenciesQuery,
  useCreateRateMutation,
  useUpdateRateMutation,
  useDeleteRateMutation,
} = rateApi;

