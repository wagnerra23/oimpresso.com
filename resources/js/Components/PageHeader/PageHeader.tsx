import * as React from 'react';

/**
 * PageHeader — componente canon do BLOCO 1 (header) das Index do oimpresso.
 *
 * Pattern canon v3.8 (LEARNINGS Decisão #4 + amendments v3.4 polish + v3.8 spacing):
 *   - `border-b overflow-visible` (FLAT — sem bg, sem rounded, sem border full)
 *   - `borderBottomColor: 'var(--border)'` inline (linha divisora dark-aware · token)
 *   - `pt-6 px-6 pb-3.5` (24/24/14 espelha Vendas canon Cowork)
 *   - `min-h-[60px] flex items-center gap-4` (3 zonas L/C/R)
 *   - H1 `text-[22px] font-bold tracking-tight leading-snug` (peso Vendas)
 *          + `color: var(--text, var(--foreground))` inline (token do DS · warm-aware)
 *   - Subtitle `text-xs text-muted-foreground tabular-nums`
 *
 * Histórico de iterações:
 *   v3.1 (PR #1457): card `bg-background border rounded-lg` + h1 16/600 + padding 16/16/14
 *   v3.2 (PR #1477): h1 22/700 (peso Vendas) + padding 24/24/14
 *   v3.2  (PR #1478): rounded-t-lg (bottom reta · conecta com BLOCO 2)
 *   v3.4 polish:       border-b warm separação visual (era `oklch(0.93 0.004 90)`)
 *   dark-aware:        borderBottomColor `var(--border)` (light 0.90 warm → dark 0.34) · corrige linha clara no dark
 *   v3.8 spacing:      header transparent (sem bg + sem border full + sem radius) · flat puro
 *                      tipo /sells Cowork · linha warm divisora abaixo
 *   h1 warm-aware:     título passa a consumir `--text` do DS em vez do `--foreground` do
 *                      shadcn. Era o ÚNICO ponto do header preso ao shadcn — a borda já
 *                      usava `var(--border)` desde o dark-aware acima. Medido em prod
 *                      (/ia, dark): h1 `oklch(0.965 0.004 240)` FRIO contra corpo de tela
 *                      warm (`--text` = `oklch(0.94 0.005 90)`; 339 elementos em hue 90
 *                      × 284 em hue 240 na mesma tela). Fallback p/ portal — ver o h1.
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
  /**
   * OPT-IN (2026-08-08): marca de identidade ANTES do título — dot de área,
   * ícone, avatar. Sem `leading`, nada muda: telas que não declaram renderizam
   * exatamente como antes. Mesmo padrão dos opt-in `icon`/`badge` do
   * `PageHeaderTabs`.
   *
   * Existe porque o `PT-04-Dashboard` **R6** descreve o header como
   * "ícone · título · descrição" e o componente não tinha onde pôr o ícone —
   * o `title` é `string`. A Jana perdeu o dot da área (hue 220) ao migrar pro
   * canon; em vez de hand-rolar um header fora do padrão, o slot entra aqui.
   */
  leading?: React.ReactNode;
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
  leading,
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
      style={{ borderBottomColor: 'var(--border)' }}
    >
      <div className="flex items-center gap-4 pt-6 px-6 pb-3.5 min-h-[60px]">
        {/* ZONA L · identidade */}
        <div className="flex-1 min-w-0">
          <h1
            className="text-[22px] font-bold tracking-tight text-foreground leading-snug"
            /* Cor pelo token do DS (`--text`), não pelo `--foreground` do shadcn.
               MEDIDO em prod (/ia, dark, computed style) antes da mudança:
                 h1  → oklch(0.965 0.004 240)   ← shadcn, branco FRIO
                 --text (.cockpit[data-theme=dark]) → oklch(0.94 0.005 90)  ← DS-v6, warm
               O título era o único ponto do header preso ao shadcn: o AppShellV2 já
               injeta `.cockpit` com os tokens warm ao redor, e o corpo da tela os usa
               (medido na mesma sonda: 339 elementos em hue 90 × 284 em hue 240).
               O `text-foreground` da className fica como 2ª rede — o `style` vence.

               FALLBACK OBRIGATÓRIO, não estética: `PageHeader` também renderiza em
               PORTAL (`ServiceOrderItemFormSheet.tsx` monta um Sheet Radix no <body>,
               FORA do `.cockpit`), onde `--text` não existe. Sem o 2º argumento o
               título herdaria cor de contexto — é a lápide §5 2026-07-10 (remover/não
               resolver token consumido dentro de wrapper que pode ir pro portal).

               NÃO escolhe cor: consome o token gerado (`tokens/_generated-cockpit-dark.css`
               via DTCG). Se o valor for retunado — a ADR UI-0020 pede hue 282 e o gerado
               hoje está em 90 — o título acompanha sem tocar neste arquivo. */
            style={{ color: 'var(--text, var(--foreground))' }}
          >
            {/* `leading` vive DENTRO do h1 pra acompanhar a linha de base do
                título — fora dele, um dot de 8px não alinha com 22px de texto. */}
            {leading}
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
