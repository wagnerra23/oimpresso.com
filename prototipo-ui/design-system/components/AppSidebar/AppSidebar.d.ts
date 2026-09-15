export interface AppSidebarBusiness {
  id: number;
  nome: string;
  iniciais: string;
  ativa?: boolean;
}

export interface AppSidebarUser {
  nome: string;
  nomeCurto: string;
  email: string;
  cargo: string;
  iniciais: string;
}

export interface AppSidebarProps {
  /** Highlights the current item by its label (case-insensitive). e.g. "Clientes", "Notas Fiscais", "IA". */
  active?: string;
  /** Fallback company name when `businesses` is empty. */
  company?: string;
  /** Businesses for the switcher dropdown (one with `ativa: true` is current). */
  businesses?: AppSidebarBusiness[];
  /** Current user shown in the footer dropdown. */
  user?: AppSidebarUser;
}

/**
 * Operational shell nav rail (sidebar v3 / ADR 0180), premium dark-cockpit treatment.
 * CompanyPicker + top shortcuts (IA · Forja · Atendimento) + 8 canon collapsible
 * groups (CADASTRO → COMERCIAL → FINANÇAS → FISCAL → PRODUÇÃO → ESTOQUE → RH →
 * SISTEMA) + footer user dropdown. Dark-fixed via --sb-* tokens.
 */
export declare function AppSidebar(props: AppSidebarProps): JSX.Element;
