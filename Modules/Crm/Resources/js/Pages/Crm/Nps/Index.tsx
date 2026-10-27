// @memcofre
//   tela: /crm/nps
//   module: Crm
//   stories: US-CRM-097 (NpsPainelController)
//   permissao: crm.nps.ver
//
// Painel NPS (PT-02 · KPIs em cima, lista embaixo). Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Âncora de design: prototipo-ui/cowork/Wagner/crm-nps-captura-page.jsx (quadro B).

import { Deferred, router } from '@inertiajs/react';
import { PageHeader } from '@/Components/PageHeader';
import KpiCard from '@/Components/shared/KpiCard';
import { Segmented } from '@/Components/ui/segmented';
import { Skeleton } from '@/Components/ui/skeleton';

interface Resumo { respostas: number; promotores: number; neutros: number; detratores: number; nps: number | null; enviadas: number }
interface Resposta { id: number; nota: number; cliente: string | null; origem: string; comentario: string | null; respondida_em: string }
interface Props { dias: number; periodos: number[]; resumo?: Resumo; respostas?: Resposta[] }

const pct = (n: number, de: number) => (de > 0 ? Math.round((n / de) * 1000) / 10 : 0);

function tomNota(n: number): string {
  if (n >= 9) return 'bg-success/15 text-success';
  if (n >= 7) return 'bg-warning/15 text-warning';
  return 'bg-destructive/15 text-destructive';
}

export default function NpsIndex({ dias, periodos, resumo, respostas }: Props) {
  const trocar = (v: string) => router.get('/crm/nps', { dias: v }, { preserveScroll: true, preserveState: true });
  const r = resumo;

  return (
    <div className="flex flex-col gap-4">
      <PageHeader title="NPS" subtitle="Clientes que receberam a pesquisa depois da venda ou da OS entregue." />

      <div className="flex justify-end">
        <Segmented
          aria-label="Período"
          value={String(dias)}
          onValueChange={trocar}
          options={periodos.map((p) => ({ value: String(p), label: `${p} dias` }))}
        />
      </div>

      <Deferred data="resumo" fallback={<Skeleton className="h-24 w-full" />}>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4" data-contract="nps-indicadores">
          <KpiCard label="NPS" value={r?.nps === null || r?.nps === undefined ? '—' : (r.nps > 0 ? `+${r.nps}` : String(r.nps))} description="de −100 a +100" />
          <KpiCard
            label="Respostas"
            value={String(r?.respostas ?? 0)}
            description={`de ${r?.enviadas ?? 0} enviadas · ${pct(r?.respostas ?? 0, r?.enviadas ?? 0)}%`}
          />
          <div className="rounded-lg border bg-card p-4 sm:col-span-2" data-testid="nps-distribuicao">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Distribuição</p>
            <div className="mt-3 flex h-2.5 overflow-hidden rounded-full bg-muted" aria-hidden="true">
              <i className="block bg-success" style={{ width: `${pct(r?.promotores ?? 0, r?.respostas ?? 0)}%` }} />
              <i className="block bg-warning" style={{ width: `${pct(r?.neutros ?? 0, r?.respostas ?? 0)}%` }} />
              <i className="block bg-destructive" style={{ width: `${pct(r?.detratores ?? 0, r?.respostas ?? 0)}%` }} />
            </div>
            <p className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
              <span>{r?.promotores ?? 0} promotores (9-10)</span>
              <span>{r?.neutros ?? 0} neutros (7-8)</span>
              <span>{r?.detratores ?? 0} detratores (0-6)</span>
            </p>
          </div>
        </div>
      </Deferred>

      <Deferred data="respostas" fallback={<Skeleton className="h-64 w-full" />}>
        <div className="overflow-hidden rounded-lg border bg-card" data-contract="nps-respostas">
          <div className="overflow-x-auto">
            <table className="w-full min-w-[640px] text-sm">
              <thead>
                <tr className="border-b text-left text-xs uppercase tracking-wide text-muted-foreground">
                  <th className="px-4 py-2 font-medium">Nota</th>
                  <th className="px-4 py-2 font-medium">Cliente</th>
                  <th className="px-4 py-2 font-medium">Origem</th>
                  <th className="px-4 py-2 font-medium">Comentário</th>
                  <th className="px-4 py-2 font-medium">Respondida</th>
                </tr>
              </thead>
              <tbody>
                {(respostas ?? []).length === 0 && (
                  <tr><td colSpan={5} className="px-4 py-10 text-center text-muted-foreground">Nenhuma resposta neste período</td></tr>
                )}
                {(respostas ?? []).map((x) => (
                  <tr key={x.id} className="border-b last:border-0 align-top">
                    <td className="px-4 py-2.5">
                      <span className={'inline-flex h-6 min-w-7 items-center justify-center rounded px-1 text-xs font-semibold tabular-nums ' + tomNota(x.nota)}>{x.nota}</span>
                    </td>
                    <td className="px-4 py-2.5">{x.cliente ?? 'Sem cliente'}</td>
                    <td className="px-4 py-2.5 font-mono text-xs text-muted-foreground">{x.origem}</td>
                    <td className={'max-w-md px-4 py-2.5 ' + (x.comentario ? '' : 'text-muted-foreground')}>{x.comentario ?? 'sem comentário'}</td>
                    <td className="px-4 py-2.5 text-xs tabular-nums text-muted-foreground">{x.respondida_em}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <p className="border-t bg-muted/40 px-4 py-2.5 text-xs text-muted-foreground">
            Detratores aparecem primeiro. Cada cliente recebe no máximo uma pesquisa a cada 90 dias.
          </p>
        </div>
      </Deferred>
    </div>
  );
}
