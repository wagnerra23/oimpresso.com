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
    },
    defaultVariants: {
      tone: 'default',
      size: 'default',
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

interface Props extends VariantProps<typeof kpiCardVariants> {
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
  className,
  onClick,
  selected,
}: Props) {
  const iconSize = size === 'compact' ? 14 : size === 'large' ? 22 : 18;
  // ADR 0110 §Tipografia canon: KPI value = font-semibold (NÃO font-bold).
  // size=default = o degrau "KPI médio" da type ramp → --fs-7 (22px). A ramp se declara "the
  // single source of font sizes" (prototipo-ui/cowork/ds-v6/tokens.css), é gerada em :root por
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

  const content = (
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

  const classes = cn(
    kpiCardVariants({ tone, size }),
    onClick && 'text-left hover:border-primary/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring cursor-pointer',
    selected && 'border-primary ring-1 ring-primary/40',
    className,
  );

  if (onClick) {
    return (
      <button
        type="button"
        onClick={onClick}
        aria-pressed={selected}
        data-slot="kpi-card"
        data-tone={tone ?? 'default'}
        className={classes}
      >
        {content}
      </button>
    );
  }

  return (
    <div data-slot="kpi-card" data-tone={tone ?? 'default'} className={classes}>
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
