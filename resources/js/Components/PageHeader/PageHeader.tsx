import * as React from 'react';

/**
 * PageHeader — componente canon do BLOCO 1 (header) das Index do oimpresso.
 *
 * Pattern canon v3.8 (LEARNINGS Decisão #4 + amendments v3.4 polish + v3.8 spacing):
 *   - `border-b overflow-visible` (FLAT — sem bg, sem rounded, sem border full)
 *   - `borderBottomColor: 'var(--border)'` inline (linha divisora dark-aware · token)
 *   - `pt-6 px-6 pb-3.5` (24/24/14 espelha Vendas canon Cowork)
 *   - `min-h-[60px] flex items-center gap-4` (3 zonas L/C/R)
 *   - H1 `text-[22px] font-semibold tracking-[-0.015em] leading-snug` (600 · token do DS)
 *          + `color: var(--text, var(--foreground))` inline (token do DS · warm-aware)
 *          `titleWeight="bold"` (700) é opt-in — ver a prop.
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
 *   h1 600 (2026-09-23): default do peso passa de 700 para 600 e `tracking-tight`
 *                      (-0.025em) vira `-0.015em` — decisão [W] D-PH-0923, que supersede
 *                      o "peso Vendas" do v3.2. Protótipo já atualizado (`.os-page-h-l`).
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
  /**
   * Peso do `<h1>`. Default `'semibold'` (600, token do DS `colors_and_type.css`
   * `h1 { font-weight: 600 }`) desde 2026-09-23 — decisão [W] D-PH-0923 ("h1 600"),
   * registrada em `prototipo-ui/cowork/Wagner/cowork-inbox/pageheader/`.
   * `'bold'` (700) segue disponível como opt-in explícito.
   *
   * ⚠️ O texto abaixo é o registro DATADO de 2026-09-21, quando a prop nasceu como
   * opt-in com default `'bold'`. Ele fica como histórico; o argumento "mudar o
   * default reverteria a decisão do #1477" foi resolvido por quem tinha a soberania:
   * [W] decidiu o 600 para todas as telas em 2026-09-23.
   *
   * (2026-09-21) POR QUE existia, e por que então NÃO virou mudança do default: as
   * duas âncoras DISCORDAVAM entre si, e este componente só podia servir uma delas.
   *
   *   - Vendas (`vendas-page.jsx` §`.os-head-l h1`) declara **700** em duas
   *     regras, e a que vence por especificidade é a de `financeiro.css:1727`
   *     (`.vendas-aplus .vd-head-clean .os-head-l h1`, 0-3-1, contra 0-1-1 de
   *     `styles.css:4772`). O comentário de `styles.css:4765` diz, textual,
   *     "mesmo CANON do PageHeader" — o protótipo foi escrito PARA casar com
   *     este arquivo. Medido em 2026-09-21, no espelho fresco (`ancora.mjs`
   *     verificou o Cowork vivo às 10:43Z do mesmo dia).
   *   - Jana (`jana-merge.jsx` → `CliPageHead`) NÃO declara peso e herda o token
   *     do DS: `colors_and_type.css:373` `h1 { font-weight: 600 }`, `--fs-7: 22px`
   *     (`:148`). Passou a herdar no #7224 (2026-09-11), que fez o `JanaHeader`
   *     delegar ao `CliPageHead`; a regra `.jc-id h1` de 19px/700 que valia antes
   *     ficou órfã no arquivo (0 nós no DOM).
   *
   * O 700 do default é decisão [W] DATADA E AINDA VÁLIDA — PR #1477 (2026-05-25),
   * textual: *"prefiro o mesmo peso do sells, pode criar v3.2"*, com `/sells` do
   * Cowork como referência. A referência foi re-medida hoje e **continua 700**,
   * então a premissa não caducou (diferente do 19px da Jana, que caducou).
   * Mudar o default alinharia as 42 telas ao peso que a Jana quer e REVERTERIA
   * essa decisão — é a forma de uma tela se impondo às outras, que a
   * `Index.casos.md:976` já barrou para as abas ("componente compartilhado não
   * impõe a forma de uma tela às outras: o caminho é réplica local", ADR 0388
   * §D-1, como o `JanaKpiCard` fez).
   *
   * (2026-09-21) Na época: quem herdava o token do DS passava `'semibold'`; quem
   * seguia a âncora de Vendas não passava nada. Desde 2026-09-23 é o inverso: o
   * padrão é 600 e quem quiser 700 passa `titleWeight="bold"`.
   */
  titleWeight?: 'bold' | 'semibold';
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
  /**
   * Faixa PRÓPRIA abaixo da linha título/ações, dentro do `<header>` (herda o sticky
   * e a `border-b`). É a posição canônica da barra de abas de topo — protótipo
   * `.cli-moduletopnav` / `jm-tabs` ("header em cima, abas abaixo") e
   * `Pages/Cliente/Index.tsx` (que hand-rola o header e põe `<PageHeaderTabs>`
   * logo após a linha do título). Antes desta prop, quem usava o canon só tinha o
   * slot `subnav` INLINE (Zona C) — e a Jana ficou com as abas espremidas à direita
   * do título, divergindo do protótipo em todas as 6 telas da área (medido em
   * 2026-09-03: tablist a `left=1654px` numa viewport de 2560, no mesmo `top` do
   * h1; na âncora a barra ocupa a largura toda, 14px abaixo do header).
   */
  below?: React.ReactNode;
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
  below,
  children,
  className = '',
  titleWeight = 'semibold',
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
            /* As duas classes ficam LITERAIS no fonte de propósito: o Tailwind
               detecta por varredura de texto, e `font-${titleWeight}` montado por
               interpolação não geraria nenhuma das duas no CSS final. */
            className={`text-[22px] ${
              titleWeight === 'bold' ? 'font-bold' : 'font-semibold'
            } tracking-[-0.015em] text-foreground leading-snug`}
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
               via DTCG). Se o valor for retunado, o título acompanha sem tocar neste
               arquivo. Qual é o valor vigente é pergunta pro token, não pra este comentário
               — a lei está na ADR UI-0027 (dark: superfícies hue 240, textos hue 90), que
               supersede o hue 282 da UI-0020. */
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

      {/* Faixa própria abaixo do título (barra de abas de topo, canon) */}
      {below}

      {/* Mobile fallback nav (renderizado se prop passada) */}
      {mobileNav}
    </header>
  );
}

export default PageHeader;
