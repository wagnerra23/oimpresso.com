import type { PageProps as InertiaPageProps } from '@inertiajs/core';

export type ThemeMode = 'light' | 'dark' | null; // null = segue sistema

export interface AuthenticatedUser {
  id: number;
  name: string;
  email: string;
  business_id: number;
  is_admin: boolean;
  ui_theme: ThemeMode;
  ui_sidebar_collapsed: boolean;
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

/**
 * Item de menu do AppShell. Quando `inertia=false`, link é `<a>` tradicional
 * (telas legadas AdminLTE). Quando `inertia=true`, vira `<Link>` SPA.
 */
export interface MenuItem {
  label: string;
  icon: string;
  href?: string;
  inertia?: boolean;
  badge?: number | string | null;
  children?: MenuItem[];
}

/**
 * TopNav declarativo por módulo (ADR arq/0009). Vem de
 * `Modules/<Nome>/Resources/menus/topnav.php`, filtrado por Spatie no backend.
 */
export interface ModuleTopNav {
  label: string;
  icon: string;
  items: MenuItem[];
}

export interface ShellProps {
  menu: MenuItem[];
  topnavs?: Record<string, ModuleTopNav>;
}

export interface SharedProps extends InertiaPageProps {
  auth: {
    user: AuthenticatedUser | null;
    can: Record<string, boolean>;
  };
  business: Business | null;
  ai: AiFlags;
  flash: FlashMessages;
  shell: ShellProps;
  locale: string;
  csrf_token: string;
}
