// @memcofre tela=/financeiro/orcamento module=Financeiro
// US-FIN-076 — orçado × realizado do mês por conta do plano de contas. Fonte de design:
// prototipo-ui/cowork/Wagner/financeiro-centro-custo-orcado.html (aba "Orçado × realizado").

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import FinanceiroSubNav from '@/Pages/Financeiro/_shared/FinanceiroSubNav';
import { PageHeader } from '@/Components/PageHeader';

interface Linha {
  plano_conta_id: number;
  codigo: string | null;
  nome: string | null;
  tipo: string | null;
  orcado: number;
  realizado: number;
  desvio: number;
  desvio_pct: number | null;
  favoravel: boolean;
}

interface Totais {
  receitas: { orcado: number; realizado: number };
  despesas: { orcado: number; realizado: number };
  resultado: { orcado: number; realizado: number; desvio: number };
}

interface Centro {
  id: number;
  codigo: string | null;
  nome: string;
  ativo: boolean;
}

interface Props {
  mes: string;
  regime: 'competencia' | 'caixa';
  centro_custo: number | null;
  meses: string[];
  centros_custo: Centro[];
  linhas: Linha[];
  totais: Totais;
}

const brl = (v: number) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const mesLabel = (m: string) => `${m.slice(5, 7)}/${m.slice(0, 4)}`;
const sinal = (v: number) => (v > 0 ? '+' : '');

function visitar(params: Record<string, string | number | null>) {
  const q: Record<string, string> = {};
  Object.entries(params).forEach(([k, v]) => {
    if (v !== null && v !== '') q[k] = String(v);
  });
  router.get('/financeiro/orcamento', q, { preserveState: true, preserveScroll: true, only: ['mes', 'regime', 'centro_custo', 'linhas', 'totais'] });
}

/** Célula do orçado: edita no blur/Enter; só grava se o texto mudou. */
function CelulaOrcado({ linha, editavel, onSalvar }: { linha: Linha; editavel: boolean; onSalvar: (valor: string) => void }) {
  const inicial = linha.orcado ? linha.orcado.toFixed(2).replace('.', ',') : '';
  const [texto, setTexto] = useState(inicial);
  useEffect(() => setTexto(inicial), [inicial]);

  if (!editavel) {
    return <span>{linha.orcado ? brl(linha.orcado) : <span className="text-muted-foreground">—</span>}</span>;
  }
  const gravar = () => {
    if (texto.trim() !== inicial) onSalvar(texto.trim());
  };
  return (
    <input
      id={`orcado-${linha.plano_conta_id}`}
      aria-label={`Orçado de ${linha.nome ?? 'conta'}`}
      inputMode="decimal"
      value={texto}
      placeholder="0,00"
      onChange={(e) => setTexto(e.target.value)}
      onBlur={gravar}
      onKeyDown={(e) => {
        if (e.key === 'Enter') (e.target as HTMLInputElement).blur();
      }}
      className="h-7 w-[120px] rounded-md border bg-background px-2 text-right tabular-nums"
    />
  );
}

function Index({ mes, regime, centro_custo, meses, centros_custo, linhas, totais }: Props) {
  // Em "todos" o orçado é a soma de geral + centros: só leitura. Sem centro cadastrado, todos = geral.
  const editavel = centro_custo !== null || centros_custo.length === 0;
  const centroParaGravar = centro_custo && centro_custo > 0 ? centro_custo : null;
  const filtros = { mes, regime, centro_custo: centro_custo === 0 ? 'sem' : centro_custo };

  const salvar = (l: Linha, valor: string) => {
    router.post(
      '/financeiro/orcamento',
      { mes, plano_conta_id: l.plano_conta_id, centro_custo_id: centroParaGravar, valor },
      {
        preserveScroll: true,
        preserveState: true,
        only: ['linhas', 'totais', 'flash'],
        onSuccess: () => toast.success(`Orçado de ${l.nome ?? 'conta'} salvo`),
        onError: (e) => toast.error(Object.values(e)[0] ?? 'Não foi possível salvar o orçado'),
      },
    );
  };

  const grupos = [
    { titulo: 'Receitas', itens: linhas.filter((l) => l.tipo === 'receita') },
    { titulo: 'Despesas', itens: linhas.filter((l) => l.tipo !== 'receita') },
  ];

  return (
    <div className="fin-curadoria vendas-aplus p-6 max-w-6xl mx-auto space-y-6">
      <div data-contract="cabecalho">
        <PageHeader title="Orçado × realizado" subtitle={<>Quanto foi orçado por conta no mês, quanto aconteceu e o desvio</>}>
          <div className="flex-shrink-0 flex items-center gap-1.5 ml-auto">
            <FinanceiroSubNav active="orcamento" hidePrimary />
          </div>
        </PageHeader>
      </div>

      <div className="rounded-md border bg-card">
        <div data-contract="filtros" className="flex flex-wrap items-center gap-2 px-4 py-3 border-b">
          <select
            id="orc-mes"
            aria-label="Mês"
            value={mes}
            onChange={(e) => visitar({ ...filtros, mes: e.target.value })}
            className="h-8 rounded-md border bg-background px-2 text-sm"
          >
            {meses.map((m) => (
              <option key={m} value={m}>{mesLabel(m)}</option>
            ))}
          </select>
          <select
            id="orc-centro"
            aria-label="Centro de custo"
            value={centro_custo === null ? '' : centro_custo === 0 ? 'sem' : String(centro_custo)}
            onChange={(e) => visitar({ ...filtros, centro_custo: e.target.value })}
            className="h-8 rounded-md border bg-background px-2 text-sm"
          >
            <option value="">Todos os centros</option>
            {centros_custo.length > 0 && <option value="sem">Geral (sem centro)</option>}
            {centros_custo.map((c) => (
              <option key={c.id} value={c.id}>{`${c.codigo ? `${c.codigo} · ` : ''}${c.nome}${c.ativo ? '' : ' (inativo)'}`}</option>
            ))}
          </select>
          <div className="ml-auto inline-flex rounded-md border overflow-hidden" role="group" aria-label="Regime">
            {(['competencia', 'caixa'] as const).map((r) => (
              <button
                key={r}
                type="button"
                aria-pressed={regime === r}
                onClick={() => visitar({ ...filtros, regime: r })}
                className={`h-8 px-3 text-xs ${regime === r ? 'bg-accent/15 text-foreground font-medium' : 'text-muted-foreground hover:bg-muted'}`}
              >
                {r === 'competencia' ? 'Competência' : 'Caixa'}
              </button>
            ))}
          </div>
        </div>

        <div data-contract="lista" className="overflow-x-auto">
          <table className="w-full text-sm tabular-nums">
            <thead className="bg-muted/50">
              <tr className="text-left">
                <th className="px-4 py-2 font-medium">Conta</th>
                <th className="px-4 py-2 font-medium text-right">Orçado</th>
                <th className="px-4 py-2 font-medium text-right">Realizado</th>
                <th className="px-4 py-2 font-medium text-right">Desvio</th>
                <th className="px-4 py-2 font-medium text-right">%</th>
                <th className="px-4 py-2 font-medium w-[140px]">Consumo</th>
              </tr>
            </thead>
            <tbody>
              {linhas.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-4 py-8 text-center text-muted-foreground">
                    Nenhuma conta de receita ou despesa que aceite lançamento. Cadastre no plano de contas.
                  </td>
                </tr>
              )}
              {grupos.map((g) =>
                g.itens.length === 0 ? null : (
                  <GrupoLinhas key={g.titulo} titulo={g.titulo} itens={g.itens} editavel={editavel} onSalvar={salvar} />
                ),
              )}
              {linhas.length > 0 && (
                <tr className="border-t-2 font-semibold">
                  <td className="px-4 py-2.5">Resultado do mês</td>
                  <td className="px-4 py-2.5 text-right">{brl(totais.resultado.orcado)}</td>
                  <td className="px-4 py-2.5 text-right">{brl(totais.resultado.realizado)}</td>
                  <td className={`px-4 py-2.5 text-right ${totais.resultado.desvio < 0 ? 'text-destructive' : totais.resultado.desvio > 0 ? 'text-success' : 'text-muted-foreground'}`}>
                    {totais.resultado.desvio === 0 ? '—' : `${sinal(totais.resultado.desvio)}${brl(totais.resultado.desvio)}`}
                  </td>
                  <td />
                  <td />
                </tr>
              )}
            </tbody>
          </table>
        </div>
        <p className="px-4 py-3 border-t text-xs text-muted-foreground">
          Orçado é por mês e por conta do plano de contas; com um centro escolhido, é o orçamento daquele centro.
          Realizado em competência soma os títulos não cancelados do mês (rateados pelo centro quando há filtro); em caixa, as baixas do mês.
          {editavel ? ' Clique no orçado para editar.' : ' Para editar o orçado, escolha Geral ou um centro: em Todos os centros ele é a soma.'}
        </p>
      </div>
    </div>
  );
}

function GrupoLinhas({ titulo, itens, editavel, onSalvar }: { titulo: string; itens: Linha[]; editavel: boolean; onSalvar: (l: Linha, v: string) => void }) {
  return (
    <>
      <tr className="bg-muted/40">
        <td colSpan={6} className="px-4 py-1.5 font-semibold text-xs uppercase tracking-wider text-muted-foreground">{titulo}</td>
      </tr>
      {itens.map((l) => {
        const consumo = l.orcado > 0 ? Math.min(l.realizado / l.orcado, 1.5) : 0;
        const cor = l.desvio === 0 ? 'text-muted-foreground' : l.favoravel ? 'text-success' : 'text-destructive';
        return (
          <tr key={l.plano_conta_id} className="border-t hover:bg-muted/30">
            <td className="px-4 py-2">
              <span className="font-mono text-xs text-muted-foreground mr-2">{l.codigo}</span>
              {l.nome}
            </td>
            <td className="px-4 py-2 text-right">
              <CelulaOrcado linha={l} editavel={editavel} onSalvar={(v) => onSalvar(l, v)} />
            </td>
            <td className="px-4 py-2 text-right">{l.realizado ? brl(l.realizado) : <span className="text-muted-foreground">—</span>}</td>
            <td className={`px-4 py-2 text-right ${cor}`}>{l.desvio === 0 ? '—' : `${sinal(l.desvio)}${brl(l.desvio)}`}</td>
            <td className={`px-4 py-2 text-right ${l.desvio_pct === null || l.desvio === 0 ? 'text-muted-foreground' : cor}`}>
              {l.desvio_pct === null || l.desvio === 0 ? '—' : `${sinal(l.desvio_pct)}${l.desvio_pct.toLocaleString('pt-BR')}%`}
            </td>
            <td className="px-4 py-2">
              <div
                className="relative h-1.5 rounded-full bg-muted"
                title={l.orcado > 0 ? `${Math.round((l.realizado / l.orcado) * 100)}% do orçado` : 'Sem orçado'}
                aria-hidden
              >
                <i
                  className={`absolute inset-y-0 left-0 rounded-full ${l.favoravel ? 'bg-success' : 'bg-destructive'}`}
                  style={{ width: `${(consumo / 1.5) * 100}%` }}
                />
                <b className="absolute -top-0.5 -bottom-0.5 w-0.5 bg-foreground" style={{ left: `${100 / 1.5}%` }} />
              </div>
            </td>
          </tr>
        );
      })}
    </>
  );
}

Index.layout = (page: React.ReactNode) => (
  <AppShellV2 title="Orçado × realizado · Financeiro" breadcrumbItems={[{ label: 'Financeiro' }, { label: 'Orçamento' }]}>
    <div className="fin-cowork">{page}</div>
  </AppShellV2>
);
export default Index;
