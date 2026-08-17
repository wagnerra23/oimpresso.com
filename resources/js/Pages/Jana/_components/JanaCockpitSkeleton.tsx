import { Inline } from '@/Components/layout/inline';
import { Stack } from '@/Components/layout/stack';
import { Skeleton } from '@/Components/ui/skeleton';

/**
 * Estado de CARREGAMENTO do que depende de `coworkAggregates` (prop deferida).
 *
 * ── POR QUE EXISTE (UC-PAINEL-08) ────────────────────────────────────────────
 * `IndexController` entrega `coworkAggregates` via `Inertia::defer()`. Até
 * resolver, o cockpit lia `?? 0` e pintava **R$ 0** / sparkline vazia — zero
 * como se fosse resultado. Isso contradiz a regra que a própria tela declara no
 * `prototipo-ui/contrato/jana-painel.contract.json`:
 *   `painel-meta-apurando`      "não pode mostrar zero como se fosse resultado"
 *   `painel-meta-sem-historico` "ausência de dado se declara, não se desenha como zero"
 * O contrato escreveu a regra para METAS; ela vale igual para o cockpit.
 *
 * O `InertiaDeferredFrontendGuardTest` NÃO pegava isso, e está certo: ele mede
 * "quebra?" (TypeError/tela branca), e o `?? 0` justamente impede a quebra. São
 * dois contratos diferentes — este arquivo é o dono do segundo.
 *
 * ── ÂNCORA ───────────────────────────────────────────────────────────────────
 * `prototipo-ui/cowork/jana-merge.jsx` §`JmPainelSkeleton` — âncora de SÍMBOLO
 * (`grep -n "JmPainelSkeleton" prototipo-ui/cowork/jana-merge.jsx`), nunca linha.
 * Lá o skeleton é `.jm-sk-card` (caption + title) para KPI e `.jm-sk-grid` para
 * os cards de análise. Aqui a mesma ESTRUTURA sai de tokens do DS, não das
 * classes `jm-*` do protótipo — o protótipo é a fonte do desenho, não do CSS.
 */

/** Substitui um `<KpiCard>` inteiro enquanto o número não chegou.
 *  Troca-se o CARD, não o `value`: o `KpiCard` compartilhado tipa
 *  `value: string | number` e alargá-lo pra `ReactNode` mexeria num componente
 *  usado por vários módulos — blast radius que este defeito não justifica. */
export function KpiCardSkeleton({ label }: { label: string }) {
  return (
    <Stack
      gap={2}
      className="rounded-lg border border-border bg-card p-4"
      aria-busy="true"
      aria-label={`${label} — carregando`}
    >
      <span className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{label}</span>
      <Skeleton className="h-7 w-24" />
      <Skeleton className="h-3 w-16" />
    </Stack>
  );
}

/** Linha do brief que depende do faturamento de hoje. */
export function BriefValorSkeleton() {
  return <Skeleton className="inline-block h-4 w-20 align-middle" aria-busy="true" />;
}

/** Área do gráfico enquanto a série não chegou.
 *  Distinto de "série vazia de verdade" — ver `JanaCockpit` §sparkline. */
export function SparklineSkeleton() {
  return (
    <div aria-busy="true" aria-label="Faturamento — carregando série">
      <Skeleton className="h-10 w-full" />
      <Inline gap={2} justify="between" className="mt-1">
        <Skeleton className="h-2 w-8" />
        <Skeleton className="h-2 w-8" />
      </Inline>
    </div>
  );
}
