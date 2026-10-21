// Custeio e ponto de equilíbrio — aba "custeio" da tela /financeiro/dre (US-FIN-070 · UC-FLX-20).
//
// Só leitura: os números vêm prontos do CusteioRkwService (o mesmo do GET /financeiro/custeio-rkw),
// em reais como string com 2 casas. Esta tela só formata — não soma, não divide, não arredonda.
// Aplicar o custo-hora sugerido num posto é ação explícita em Produção › Postos (UC-PCCH-05).

import { router } from '@inertiajs/react';

export interface CusteioPosto {
  posto_id: number;
  codigo: string;
  nome: string;
  horas: string;
  rateio_fixo: string;
  custo_hora_atual: string;
  parcela_fixa_por_hora: string;
  custo_hora_rkw: string;
}

export interface CusteioData {
  mes: string;
  erro?: string;
  despesas_fixas?: string;
  custos_variaveis?: string;
  receita?: string;
  horas?: string;
  parcela_fixa_por_hora?: string | null;
  margem_contribuicao_pct?: string | null;
  ponto_equilibrio?: string | null;
  avisos?: string[];
  postos?: CusteioPosto[];
  pode_ver_postos?: boolean;
}

const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', minimumFractionDigits: 2 });
const brl = (v: string | null | undefined): string => (v === null || v === undefined ? '—' : BRL.format(Number(v)));
const num = (v: string | null | undefined): string =>
  v === null || v === undefined ? '—' : Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function mesLabel(mes: string): string {
  const [a, m] = mes.split('-').map(Number);
  if (!a || !m) return mes;
  const nome = new Date(a, m - 1, 1).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
  return nome.charAt(0).toUpperCase() + nome.slice(1);
}

function Kpi({ rotulo, valor, dica, destaque }: { rotulo: string; valor: string; dica: string; destaque?: boolean }) {
  return (
    <div className={'fin-stat' + (destaque ? ' fin-stat-hero' : '')}>
      <small>{rotulo}</small>
      <b className="tabular-nums">{valor}</b>
      <span className="fin-stat-hint">{dica}</span>
    </div>
  );
}

export function CusteioView({ custeio }: { custeio: CusteioData | null | undefined }) {
  if (!custeio) {
    return (
      <div className="mx-6 mt-4 mb-4 p-8 text-center text-[length:var(--fs-3)] text-[var(--text-dim)] bg-[var(--surface)] border border-[var(--border)] rounded-[11px]">
        Carregando custeio do mês…
      </div>
    );
  }

  const trocaMes = (mes: string) => {
    if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(mes) || mes === custeio.mes) return;
    router.visit(`/financeiro/dre?aba=custeio&mes=${mes}`, { preserveScroll: true, replace: true, only: ['aba', 'custeio'] });
  };

  const postos = custeio.postos ?? [];
  const avisos = custeio.avisos ?? [];

  return (
    <div data-contract="dre-custeio">
      <div className="px-6 pt-4 flex flex-wrap items-center gap-3">
        <label className="inline-flex items-center gap-2 text-[length:var(--fs-3)] text-[var(--text-dim)]">
          Mês do fechamento
          <input
            type="month"
            aria-label="Mês do fechamento"
            className="h-8 px-2 rounded-md border border-[var(--border)] bg-[var(--surface)] text-[var(--text)] tabular-nums"
            defaultValue={custeio.mes}
            onChange={(e) => trocaMes(e.target.value)}
          />
        </label>
        <span className="text-[length:var(--fs-2)] text-[var(--text-mute)]">
          Fixa e variável vêm da classe de cada conta no{' '}
          <a href="/financeiro/plano-contas" className="underline">plano de contas</a>; conta sem classe fica fora.
        </span>
      </div>

      {custeio.erro && (
        <div role="alert" className="mx-6 mt-3 px-4 py-3 rounded-lg text-[length:var(--fs-3)] bg-[var(--neg-soft)] text-[var(--neg)]">
          {custeio.erro}
        </div>
      )}

      {avisos.length > 0 && (
        <ul
          className="mx-6 mt-3 px-4 py-3 rounded-lg text-[length:var(--fs-3)] flex flex-col gap-1"
          style={{ background: 'var(--color-warning-soft)', border: '1px solid color-mix(in oklch, var(--color-warning) 40%, transparent)' }}
        >
          {avisos.map((a) => <li key={a}>ⓘ {a}</li>)}
        </ul>
      )}

      {!custeio.erro && (
        <>
          <div className="fin-stats mt-3">
            <Kpi destaque rotulo="PONTO DE EQUILÍBRIO" valor={brl(custeio.ponto_equilibrio)} dica={`receita mínima em ${mesLabel(custeio.mes)}`} />
            <Kpi rotulo="MARGEM DE CONTRIBUIÇÃO" valor={custeio.margem_contribuicao_pct ? `${num(custeio.margem_contribuicao_pct)}%` : '—'} dica="(receita − variáveis) ÷ receita" />
            <Kpi rotulo="DESPESAS FIXAS" valor={brl(custeio.despesas_fixas)} dica="contas fixas, a pagar no mês" />
            <Kpi rotulo="CUSTOS VARIÁVEIS" valor={brl(custeio.custos_variaveis)} dica="contas variáveis, a pagar no mês" />
            <Kpi rotulo="RECEITA" valor={brl(custeio.receita)} dica="contas de receita, a receber no mês" />
          </div>

          <div className="px-6 pt-4 pb-4">
            <div className="bg-[var(--surface)] border border-[var(--border)] rounded-[11px] shadow-[var(--sh-1)] overflow-hidden">
              <div className="px-5 py-3 border-b border-[var(--border)] flex flex-wrap items-center gap-3">
                <div className="min-w-0">
                  <div className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-dim)] font-medium">
                    Rateio das despesas fixas pelas horas dos postos
                  </div>
                  <div className="text-[length:var(--fs-5)] font-semibold mt-0.5">
                    {mesLabel(custeio.mes)} · {num(custeio.horas)} h apontadas · {brl(custeio.parcela_fixa_por_hora)}/h de parcela fixa
                  </div>
                </div>
                {custeio.pode_ver_postos && (
                  <a href="/producao/postos" className="ml-auto text-[length:var(--fs-3)] underline text-[var(--text-dim)] hover:text-[var(--text)]">
                    Aplicar custo-hora nos postos →
                  </a>
                )}
              </div>

              {!custeio.pode_ver_postos ? (
                <div className="px-6 py-8 text-center text-[length:var(--fs-3)] text-[var(--text-dim)]">
                  O custo-hora de cada posto é dado de desempenho da produção: pede a permissão “ver desempenho” do PCP.
                </div>
              ) : postos.length === 0 ? (
                <div className="px-6 py-8 text-center text-[length:var(--fs-3)] text-[var(--text-dim)]">
                  Nenhum posto com horas fechadas em {mesLabel(custeio.mes)}.
                </div>
              ) : (
                <table className="w-full text-[length:var(--fs-3)] tabular-nums">
                  <thead>
                    <tr className="text-[length:var(--fs-1)] uppercase tracking-widest text-[var(--text-dim)] border-b border-[var(--border)] bg-[var(--bg-2)]">
                      <th className="pl-6 pr-2 py-2 text-left font-medium">Posto</th>
                      <th className="px-2 py-2 text-right font-medium w-[90px]">Horas</th>
                      <th className="px-2 py-2 text-right font-medium w-[140px]">Rateio fixo</th>
                      <th className="px-2 py-2 text-right font-medium w-[140px]">Custo-hora atual</th>
                      <th className="px-2 py-2 text-right font-medium w-[130px]">+ Parcela fixa</th>
                      <th className="pl-2 pr-6 py-2 text-right font-medium w-[150px]">Custo-hora RKW</th>
                    </tr>
                  </thead>
                  <tbody>
                    {postos.map((p) => (
                      <tr key={p.posto_id} className="border-b border-[var(--border-2)]" data-contract="dre-custeio-posto">
                        <td className="pl-6 pr-2 py-2">
                          <span className="font-mono text-[length:var(--fs-2)] text-[var(--text-mute)] mr-2">{p.codigo}</span>
                          {p.nome}
                        </td>
                        <td className="px-2 py-2 text-right">{num(p.horas)}</td>
                        <td className="px-2 py-2 text-right">{brl(p.rateio_fixo)}</td>
                        <td className="px-2 py-2 text-right text-[var(--text-dim)]">{brl(p.custo_hora_atual)}</td>
                        <td className="px-2 py-2 text-right text-[var(--text-dim)]">{brl(p.parcela_fixa_por_hora)}</td>
                        <td className="pl-2 pr-6 py-2 text-right font-semibold">{brl(p.custo_hora_rkw)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          </div>
        </>
      )}
    </div>
  );
}
