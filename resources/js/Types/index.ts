import type { PageProps as InertiaPageProps } from '@inertiajs/core';

export interface AuthenticatedUser {
  id: number;
  name: string;
  email: string;
  business_id: number;
  is_admin: boolean;
}

export interface Business {
  id: number;
  name: string;
  currency_symbol: string;
  default_sales_tax: number | null;
}

export interface AiFlags {
  enabled: boolean;
  classificacao_intercorrencia: boolean;
  explicacao_divergencia: boolean;
  geracao_justificativa: boolean;
}

export interface FlashMessages {
  success?: string | null;
  error?: string | null;
  info?: string | null;
}

export interface SharedProps extends InertiaPageProps {
  auth: {
    user: AuthenticatedUser | null;
    can: Record<string, boolean>;
  };
  business: Business | null;
  ai: AiFlags;
  flash: FlashMessages;
  locale: string;
  csrf_token: string;
}
