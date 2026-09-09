import * as React from 'react';

export interface PageHeaderStat {
  /** The emphasized number/value. */
  value: React.ReactNode;
  /** Trailing label, e.g. "abertas". */
  label?: string;
  /** Tints the value. */
  tone?: 'danger' | 'warn';
}

export interface PageHeaderProps {
  /** Page title (22/600). */
  title: string;
  /** Toned stat segments joined with " · " (alternative to subtitle). */
  stats?: PageHeaderStat[];
  /** Free-form subtitle node (rendered before stats). */
  subtitle?: React.ReactNode;
  /** Right-aligned actions (buttons). */
  actions?: React.ReactNode;
  /**
   * Marca de identidade antes do título — dot de área, ícone, avatar. Renderiza
   * DENTRO do h1, na linha de base do título. Espelha o slot homônimo do header
   * canon do repo (`Components/PageHeader`). Não é caixa: a caixa 40×40
   * `bg-primary/10` pertence ao `shared/PageHeader.tsx`, que está CONGELADO.
   */
  leading?: React.ReactNode;
  /** Linha de contexto acima do título — módulo, pai ou breadcrumb curto (mono/uppercase). */
  context?: React.ReactNode;
  /**
   * Pílula de frescor à direita do título. String usa `StatusBadge kind="frescor"`
   * (`recente` | `fresc` | `frio` | `distante`); um nó React é renderizado como veio.
   */
  freshness?: 'recente' | 'fresc' | 'frio' | 'distante' | React.ReactNode;
  /** Sufixo relativo do frescor, e.g. "há 1sem" (só quando `freshness` é string). */
  freshnessRel?: string;
}

/** Flat index/page header — slot 1 of the PT-01 list pattern (DS v4 canon). */
export declare function PageHeader(props: PageHeaderProps): JSX.Element;
