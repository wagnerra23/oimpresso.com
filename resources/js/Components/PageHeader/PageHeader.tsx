import * as React from 'react';

/**
 * PageHeader — componente canon do BLOCO 1 (header) das Index do oimpresso.
 *
 * Pattern canon v3.8 (LEARNINGS Decisão #4 + amendments v3.4 polish + v3.8 spacing):
 *   - `border-b overflow-visible` (FLAT — sem bg, sem rounded, sem border full)
 *   - `borderBottomColor: 'oklch(0.93 0.004 90)'` inline (linha divisora warm)
 *   - `pt-6 px-6 pb-3.5` (24/24/14 espelha Vendas canon Cowork)
 *   - `min-h-[60px] flex items-center gap-4` (3 zonas L/C/R)
 *   - H1 `text-[22px] font-bold tracking-tight leading-snug` (peso Vendas)
 *   - Subtitle `text-xs text-muted-foreground tabular-nums`
 *
 * Histórico de iterações:
 *   v3.1 (PR #1457): card `bg-background border rounded-lg` + h1 16/600 + padding 16/16/14
 *   v3.2 (PR #1477): h1 22/700 (peso Vendas) + padding 24/24/14
 *   v3.2  (PR #1478): rounded-t-lg (bottom reta · conecta com BLOCO 2)
 *   v3.4 polish:       border-b warm `oklch(0.93 0.004 90)` separação visual
 *   v3.8 spacing:      header transparent (sem bg + sem border full + sem radius) · flat puro
 *                      tipo /sells Cowork · linha warm divisora abaixo
 *
 * Refs: ADR 0189 amendment v3.2-v3.8, ADR 0190 (primary roxo universal).
 *
 * Uso canon:
 *
 *   <PageHeader
 *     title="Clientes"
 *     subtitle={<>31 cadastrados · 4 ativos</>}
 *     subnav={<nav>tabs</nav>}
 *     actions={
 *       <>
 *         <DropdownMenu>⋮</DropdownMenu>
 *         <PageHeaderPrimary label="Novo cliente" href="/contacts/create" />
 *       </>
 *     }
 *   />
 */
export interface PageHeaderProps {
  /** Título principal · entidade da página. Ex: "Clientes", "Cobrança". */
  title: string;
  /** Sufixo cinza após o título · contexto. Ex: " · Boletos e PIX". Opcional. */
  suffix?: string;
  /** Subtítulo curto · métricas/contagem com `tabular-nums`. Pode ter `<strong>` semântico. */
  subtitle?: React.ReactNode;
  /** Zona C · subnav inline (tabs ou similar). Render entre Zona L e Zona R. Opcional. */
  subnav?: React.ReactNode;
  /** Zona R · actions (botões, overflow, primary). Render à direita com `ml-auto`. Opcional. */
  actions?: React.ReactNode;
  /** Mobile fallback nav (renderizado abaixo do flex inner, `md:hidden`). Opcional. */
  mobileNav?: React.ReactNode;
  /** Escape hatch · render livre dentro do flex inner (substitui subnav+actions). */
  children?: React.ReactNode;
  /** Classes extras pro `<header>` raiz. Use com parcimônia · canon override discouraged. */
  className?: string;
}

export function PageHeader({
  title,
  suffix,
  subtitle,
  subnav,
  actions,
  mobileNav,
  children,
  className = '',
}: PageHeaderProps) {
  return (
    <header
      className={`border-b overflow-visible ${className}`.trim()}
      role="banner"
      style={{ borderBottomColor: 'oklch(0.93 0.004 90)' }}
    >
      <div className="flex items-center gap-4 pt-6 px-6 pb-3.5 min-h-[60px]">
        {/* ZONA L · identidade */}
        <div className="flex-1 min-w-0">
          <h1 className="text-[22px] font-bold tracking-tight text-foreground leading-snug">
            {title}
            {suffix && (
              <span className="font-semibold text-muted-foreground">{suffix}</span>
            )}
          </h1>
          {subtitle && (
            <p className="text-xs text-muted-foreground mt-0.5 tabular-nums">
              {subtitle}
            </p>
          )}
        </div>

        {/* Escape hatch · children sobrescreve subnav+actions */}
        {children ? (
          children
        ) : (
          <>
            {/* ZONA C · subnav (opcional) */}
            {subnav}
            {/* ZONA R · actions (opcional) */}
            {actions && (
              <div className="flex-shrink-0 flex items-center gap-1.5">
                {actions}
              </div>
            )}
          </>
        )}
      </div>

      {/* Mobile fallback nav (renderizado se prop passada) */}
      {mobileNav}
    </header>
  );
}

export default PageHeader;
