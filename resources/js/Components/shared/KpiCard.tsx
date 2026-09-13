import * as React from 'react';
import { cva, type VariantProps } from 'class-variance-authority';
import { ArrowDownRight, ArrowUpRight, Minus } from 'lucide-react';
import { Icon } from '@/Components/Icon';
import { cn } from '@/Lib/utils';

/**
 * KpiCard — card de indicador semântico, reutilizável em qualquer dashboard.
 *
 * Tom visual por `tone`:
 *   default  → cinza neutro (KPI de contagem)
 *   success  → verde (métricas positivas, presentes)
 *   warning  → âmbar (atencao, atrasos)
 *   danger   → vermelho (faltas, erros)
 *   info     → azul (informativo, banco de horas positivo)
 *
 * Compacto (`compact`) pra grids de 4+ colunas.
 * `delta` mostra variação +/- com seta e cor automática.
 *
 * Uso:
 *   <KpiCard
 *     label="Colaboradores presentes"
 *     value={42}
 *     icon="users"
 *     tone="success"
 *     delta={{ value: 3, label: 'vs ontem' }}
 *   />
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * `variant="filter"` — o tile CLICÁVEL usado como filtro (2026-09-13)
 *
 * FORMA, não semântica: a caixa vira linha (ícone à esquerda, texto à direita),
 * `rounded-lg`/`p-3` em vez de `rounded-xl`/`p-4`, e o valor cai um degrau da ramp
 * (`--fs-6` 18px, contra `--fs-7` 22px do default).
 *
 * A ÂNCORA é `prototipo-ui/design-system/components/KpiCard/KpiCard.jsx :: KpiFilterTile`
 * (o `KpiFilterCard` é alias dela — "fusão 2026-08"). O consumo real está em
 * `prototipo-ui/cowork/Wagner/ponto-ui.jsx :: Kpi`, que DELEGA a este contrato:
 *   <KpiCard variant="filter" label value sub icon tone={TOM_KPI_FILTRO[...]} selected onClick />
 *
 * `filterTone` é EIXO PRÓPRIO — não remapeia nem amplia `tone`. A âncora reusa o mesmo
 * prop `tone` para os dois vocabulários (e o `.d.ts` do DS declara os dois enums contra o
 * MESMO campo: `KpiCard.d.ts` = default|success|warning|danger|info ×
 * `KpiFilterCard.d.ts` = primary|amber|rose|emerald|violet). Aqui eles ficam separados:
 * ampliar `tone` mudaria o tipo público para os 40 consumidores medidos.
 *
 * ⚠️ `violet` e `primary` renderizam IGUAIS — não é descuido. A âncora define os dois em
 * hue 295 (`--color-primary` = oklch(0.55 0.15 295), ADR 0190; violet = oklch(0.60 0.18 295)),
 * e o repo não tem token de roxo secundário no `@theme`. `--stage-violet` (288) existe, mas
 * vive escopado em `.cockpit` (`_generated-cockpit-*.css`) e sumiria em portal Radix —
 * a lápide de 2026-07-10 é exatamente sobre isso. Separá-los exige token novo = decisão [W].
 *
 * ⚠️ O LABEL sob `filter` NÃO muda: segue o canon da ADR 0110 (11px/600 uppercase muted)
 * documentado abaixo. O playbook que pediu esta variante descrevia o alvo como "13.3px/400
 * em accent" e abria um conflito `D-KPI-LABEL` contra a ADR — medido em 2026-09-13, NENHUMA
 * das duas fontes no repo produz isso: a âncora usa `--fs-1`/600/uppercase/muted (= a ADR), e o
 * fallback CSS legado (`ponto-page.css:50 .pt-kpi small`) usa 9.5px uppercase `--text-dim`.
 * O "accent" também colidiria com `cockpit.css:42-45`, que registra que `--accent` é
 * REESCRITO pelo `AppShellV2` e não é fonte confiável de roxo. O conflito não existe.
 */
const kpiCardVariants = cva(
  'flex flex-col gap-2 rounded-xl border bg-card p-4 shadow-sm transition-colors',
  {
    variants: {
      tone: {
        default: 'border-border',
        success: 'border-success/20 bg-success/5',
        warning: 'border-warning/20 bg-warning/5',
        danger: 'border-destructive/20 bg-destructive/5',
        info: 'border-info/20 bg-info/5',
      },
      size: {
        default: 'p-4 gap-2',
        compact: 'p-3 gap-1',
        large: 'p-6 gap-3',
      },
      // Declarado POR ÚLTIMO de propósito: o `cva` concatena as variants na ordem das chaves
      // e o `cn` deste arquivo é `twMerge` — então `variant` vence `size`/base nos eixos em
      // conflito (flex-col→flex-row, rounded-xl→rounded-lg, p-4→p-3), sem precisar remover
      // nada do base. `gap-3` = 12px, o gap da âncora.
      variant: {
        default: '',
        filter: 'flex-row items-center gap-3 rounded-lg p-3 text-left',
      },
    },
    defaultVariants: {
      tone: 'default',
      size: 'default',
      // `variant` FICA DE FORA — é metade da guarda, não esquecimento: sem entrada aqui o
      // valor ausente resolve `undefined` e o `cva` não aplica classe nenhuma. A outra metade
      // é `default: ''` acima. Medido por mutação (2026-09-13): quebrar UMA das duas é inerte
      // (pôr `variant: 'default'` aqui não muda nada enquanto a string for vazia; encher a
      // string não muda nada enquanto ninguém a selecionar) — quebrar AS DUAS derruba 7 casos
      // de `tests/js/kpicard-variant-filter.test.tsx`. Quem mexer numa, olhe a outra.
    },
  },
);

const iconContainerVariants = cva(
  'flex items-center justify-center rounded-lg shrink-0',
  {
    variants: {
      tone: {
        default: 'bg-muted text-muted-foreground',
        success: 'bg-success/10 text-success',
        warning: 'bg-warning/10 text-warning',
        danger: 'bg-destructive/10 text-destructive',
        info: 'bg-info/10 text-info',
      },
      size: {
        default: 'h-9 w-9',
        compact: 'h-7 w-7',
        large: 'h-11 w-11',
      },
    },
    defaultVariants: { tone: 'default', size: 'default' },
  },
);

/**
 * Placa do ícone sob `variant="filter"` — 36×36 (`h-9 w-9`), igual à âncora.
 *
 * Os 5 tons são o vocabulário do bundle, mapeados nos tokens do `@theme` por HUE medido
 * (2026-09-13), sem cor crua e sem token novo:
 *   primary → `--color-primary`     295  ·  âncora `var(--color-primary)`      295  (Δ0)
 *   amber   → `--color-warning`      75  ·  âncora oklch(0.72 0.15 70)          70  (Δ5)
 *   rose    → `--color-destructive`  18  ·  âncora oklch(0.65 0.20 20)          20  (Δ2)
 *   emerald → `--color-success`     162  ·  âncora oklch(0.65 0.14 155)        155  (Δ7)
 *   violet  → `--color-primary`     295  ·  âncora oklch(0.60 0.18 295)        295  (Δ0 — colapsa, ver docblock)
 *
 * A âncora pinta a placa com 16–18% de fundo; `/15` é o degrau de opacidade que o resto
 * deste arquivo já usa (`bg-success/10`… ), então fica na mesma família em vez de inventar um.
 */
const filterIconVariants = cva(
  'grid place-items-center rounded-lg shrink-0 h-9 w-9',
  {
    variants: {
      filterTone: {
        primary: 'bg-primary/15 text-primary',
        amber: 'bg-warning/15 text-warning',
        rose: 'bg-destructive/15 text-destructive',
        emerald: 'bg-success/15 text-success',
        violet: 'bg-primary/15 text-primary',
      },
    },
    defaultVariants: { filterTone: 'primary' },
  },
);

interface Props
  extends VariantProps<typeof kpiCardVariants>,
    VariantProps<typeof filterIconVariants> {
  label: string;
  value: string | number;
  icon?: string;
  description?: string;
  delta?: { value: number; label?: string; direction?: 'up' | 'down' | 'neutral' };
  deltaIsGood?: boolean; // se true, up=verde, down=vermelho. Se false, inverte.
  action?: React.ReactNode;
  className?: string;
  /** Se passado, o card vira botão clicável (útil como filtro toggle). */
  onClick?: () => void;
  /** Visual de "selecionado" quando card é clicável e representa filtro ativo. */
  selected?: boolean;
}

export default function KpiCard({
  label,
  value,
  icon,
  description,
  delta,
  deltaIsGood = true,
  action,
  tone,
  size,
  variant,
  filterTone,
  className,
  onClick,
  selected,
}: Props) {
  const isFilter = variant === 'filter';
  const iconSize = size === 'compact' ? 14 : size === 'large' ? 22 : 18;
  // ADR 0110 §Tipografia canon: KPI value = font-semibold (NÃO font-bold).
  // size=default = o degrau "KPI médio" da type ramp → --fs-7 (22px). A ramp se declara "the
  // referência histórica de font sizes" (prototipo-ui/cowork/Wagner/legado/ds-v6/tokens.css), é gerada em :root por
  // resources/css/tokens/_generated-foundations-*.css e chega via foundations.css (AppShellV2).
  // `leading-none` anda JUNTO por obrigação, não por gosto: text-2xl trazia line-height 2rem
  // embutido no utilitário e o arbitrary value NÃO traz — sem ele o line-height viraria herdado.
  // Casa com a regra de acabamento da ramp ("lh 1 números") e com --fs-8/--fs-9 no Financeiro.
  // size=large 36px (text-4xl) vem da tabela da ADR 0110; size=compact 20px (text-xl) não tem
  // dono declarado — os dois estão FORA da ramp (…18 · 22 · 28 · 38…) e não foram tocados aqui.
  const valueClass =
    size === 'compact'
      ? 'text-xl font-semibold'
      : size === 'large'
        ? 'text-4xl font-semibold'
        : 'text-[length:var(--fs-7)] leading-none font-semibold';

  const defaultContent = (
    <>
      <div className="flex items-center justify-between gap-2">
        {/* ADR 0110 §Tipografia canon: KPI label = text-[11px] font-semibold uppercase tracking-widest.
            `min-w-0 break-words` (e NÃO `truncate`): o rótulo é copy de contrato — tem de aparecer
            inteiro, quebrando em 2 linhas quando o card é estreito. Com `truncate` ele sumia
            (medido em prod 2026-08-24: 4 de 6 rótulos cortados a 1280 em /ponto, 6 de 6 em
            /governance/dashboard). `min-w-0` é obrigatório: sem ele o flex não encolhe abaixo do
            min-content e a palavra longa vaza pra fora do card (medi 8px de vazamento a 1280). */}
        <span className="text-[11px] font-semibold text-muted-foreground uppercase tracking-widest min-w-0 break-words">
          {label}
        </span>
        {icon && (
          <div className={cn(iconContainerVariants({ tone, size }))}>
            <Icon name={icon} size={iconSize} />
          </div>
        )}
      </div>
      <div className="flex items-baseline gap-2 min-w-0">
        {/* O VALOR carrega o tom quando `danger` — corrigido 2026-08-27, e a causa era uma
            INVERSÃO de hierarquia, não um tom fraco. Medido contra a âncora
            (`chat-jana.css:151-155`, o `KPICard` que o `jana-merge.jsx:891` consome):
              âncora   → valor em `var(--neg)` (vermelho) · ícone 15px em `var(--text-3)` (CINZA)
              produção → valor em `text-foreground` (neutro) · ícone 36px em `text-destructive`
            Ou seja: aqui o ENFEITE gritava e o NÚMERO ficava mudo — o oposto do que a âncora faz,
            e o oposto do que a leitura pede (quem dói é o número, não o ícone ao lado dele).
            [W] reportou como quatro defeitos ("fundo errado", "fonte", "ícone errado", "tamanho").

            ⚠️ ERRATA 2026-08-27 (auditoria adversarial), DUAS correções ao que está escrito acima:

            (1) "os quatro são sintoma desta inversão" era AFIRMAÇÃO SEM CONSERTO. Este diff muda
                UMA classe — a cor do valor. O eixo do ícone que o próprio texto acima mede como
                divergente (36px `text-destructive` × 15px cinza na âncora) segue INTOCADO em
                `iconContainerVariants` (:61 `bg-destructive/10 text-destructive`, :66 `h-9 w-9`).
                O commit admitia; este comentário não — e é o comentário que a próxima sessão lê.

            (2) O EIXO pode estar errado, e isto NÃO está resolvido. A âncora separa dois campos
                que este ajuste fundiu:
                  `emphasize`            → `.jc-kpi.emph`  = borda + fundo, NÃO toca o valor
                  `deltaCls === "red big"` → `.jc-kpi-v.red` = a cor do VALOR
                E o `JanaCockpit.tsx` declara que `tone` mapeia a `emphasize` ("1 de **3** com
                `emphasize:true`"). Ou seja: pendurou-se no eixo do FUNDO um efeito que a âncora
                pendura no eixo do DELTA. Passou despercebido porque o dataset tem N=1 — o único
                KPI marcado ("A receber vencido") carrega os DOIS campos, e com um ponto só os
                eixos são indistinguíveis. A conclusão foi INTERPOLADA, não lida do contrato.
                Some-se que o bloco de dados que a sustenta é o retrato do Martinho.

                ⚠️ DOIS NÚMEROS DESTE PARÁGRAFO FORAM CORRIGIDOS EM 2026-08-31, medidos:
                  · era "1 de 4" — o `chat-jana.jsx` publica **3** KPIs, não 4
                    (`grep -c emphasize` → 2: o dado em `:94` e o render em `:256`).
                    O `JanaCockpit.tsx` foi corrigido no mesmo diff; esta linha o CITA, então
                    tinha que cair junto, senão vira canon citando canon que já mudou.
                  · era "…o mesmo que renderiza 'Frota utilização'". **O dataset NÃO renderiza
                    frota.** As análises publicadas são 5 — `inad fat conc churn cheq` — e a
                    classe `jc-an-frota` (`:371`) é o ramo de `a.kind === "donut"`, kind que
                    **nenhuma** delas usa: ramo morto. E na âncora oficial (`jana-merge.jsx`)
                    frota não existe de forma alguma (`grep -in 'frota\|truck'` → rc=1, zero,
                    com controle positivo `grep -c JM_KPI_DRILL` → 2, rc=0).
                O **veredito** [W] de 2026-08-10 sobre não construir frota segue de pé — o que
                caiu foi a afirmação de que este dataset a exibe. N=1 e o argumento do eixo
                continuam INTACTOS: a dúvida deste bloco não depende desses dois números.
                NÃO revertido porque a decisão é de produto ([W]), e porque o efeito (número que
                dói em vermelho) é defensável por si. Mas NÃO se apoie nisto como "a âncora manda":
                ela manda no `deltaCls`, e este componente não tem esse campo.

            ESCOPO: só `danger`. `success`/`warning` seguem com valor neutro de propósito — verde
            afirmando "bom" sobre um número é o vício que o §Anti-hooks do charter da Jana já
            proíbe, e `warning` colorido brigaria com o Delta ao lado. */}
        <span className={cn(valueClass, tone === 'danger' ? 'text-destructive' : 'text-foreground', 'tabular-nums truncate')}>{value}</span>
        {delta && <Delta {...delta} isGood={deltaIsGood} />}
      </div>
      {description && (
        <p className="text-xs text-muted-foreground leading-snug break-words">{description}</p>
      )}
      {action && <div className="mt-1">{action}</div>}
    </>
  );

  // Sob `filter` a ORDEM muda (ícone ANTES do texto) e o texto vira uma coluna própria — por
  // isso é outro corpo, não outras classes no mesmo. O `min-w-0` no wrapper é o mesmo da âncora
  // (`style={{ minWidth: 0 }}`) e obrigatório pelo mesmo motivo do bloco default: sem ele o flex
  // não encolhe abaixo do min-content e o rótulo longo vaza pra fora do card.
  const filterContent = (
    <>
      {icon && (
        <span className={cn(filterIconVariants({ filterTone }))}>
          <Icon name={icon} size={18} />
        </span>
      )}
      <span className="min-w-0 flex-1">
        {/* Mesmas classes do label default — ADR 0110 §Tipografia canon. `break-words` (e NÃO
            `truncate`) porque o rótulo é copy de contrato; vale igual aqui. */}
        <span className="block text-[11px] font-semibold text-muted-foreground uppercase tracking-widest leading-none min-w-0 break-words">
          {label}
        </span>
        <span className="mt-1 flex items-baseline gap-2 min-w-0">
          {/* `--fs-6` (18px) = o degrau da ramp que a âncora usa no tile de filtro, um abaixo do
              `--fs-7` (22px) do card default. `leading-tight` anda junto pelo mesmo motivo do
              default: o arbitrary value não traz line-height embutido. A regra do `danger`
              carregar o tom no VALOR é preservada — é contrato do componente, não do default. */}
          <span
            className={cn(
              'text-[length:var(--fs-6)] leading-tight font-semibold tabular-nums truncate',
              tone === 'danger' ? 'text-destructive' : 'text-foreground',
            )}
          >
            {value}
          </span>
          {delta && <Delta {...delta} isGood={deltaIsGood} />}
        </span>
        {description && (
          <span className="block mt-0.5 text-[length:var(--fs-1)] leading-none text-muted-foreground break-words">
            {description}
          </span>
        )}
        {action && <span className="mt-1 block">{action}</span>}
      </span>
    </>
  );

  const content = isFilter ? filterContent : defaultContent;

  const classes = cn(
    kpiCardVariants({ tone, size, variant }),
    onClick && 'text-left hover:border-primary/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer',
    selected && 'border-primary ring-1 ring-primary/40',
    className,
  );

  // `data-variant` só existe sob `filter`. Carimbá-lo sempre (`variant ?? 'default'`, como o
  // `data-tone` ao lado faz) somaria um atributo ao markup dos 40 consumidores atuais — e a lei
  // desta mudança é que quem não pedir a variante não muda de byte.
  const variantAttr = isFilter ? { 'data-variant': 'filter' } : {};

  if (onClick) {
    return (
      <button
        type="button"
        onClick={onClick}
        aria-pressed={selected}
        data-slot="kpi-card"
        data-tone={tone ?? 'default'}
        {...variantAttr}
        className={classes}
      >
        {content}
      </button>
    );
  }

  return (
    <div data-slot="kpi-card" data-tone={tone ?? 'default'} {...variantAttr} className={classes}>
      {content}
    </div>
  );
}

function Delta({
  value,
  label,
  direction,
  isGood,
}: {
  value: number;
  label?: string;
  direction?: 'up' | 'down' | 'neutral';
  isGood: boolean;
}) {
  const dir = direction ?? (value > 0 ? 'up' : value < 0 ? 'down' : 'neutral');
  const Icon_ = dir === 'up' ? ArrowUpRight : dir === 'down' ? ArrowDownRight : Minus;
  const good = (dir === 'up' && isGood) || (dir === 'down' && !isGood);
  const neutral = dir === 'neutral';
  const color = neutral
    ? 'text-muted-foreground'
    : good
      ? 'text-success'
      : 'text-destructive';

  const sign = value > 0 ? '+' : '';
  return (
    <span className={cn('inline-flex items-center gap-0.5 text-xs font-medium tabular-nums', color)}>
      <Icon_ size={12} />
      {sign}
      {value}
      {label && <span className="text-muted-foreground font-normal ml-1">{label}</span>}
    </span>
  );
}
