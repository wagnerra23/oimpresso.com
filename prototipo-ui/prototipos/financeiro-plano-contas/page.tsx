// @memcofre
//   tela: /financeiro/plano-de-contas
//   module: Financeiro
//   status: em-implementacao
//   stories: US-FIN-018 (plano-contas-hierarquico)
//   rules: R-FIN-001 (multi-tenant), R-FIN-011 (codigo-imutavel-com-lancamentos)
//   adrs: ui/0114 (cockpit-v2)
//   tests: Modules/Financeiro/Tests/Feature/PlanoContasControllerTest

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Card } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import PageHeader from '@/Components/shared/PageHeader';

interface Conta {
  id: number;
  codigo: string;          // "1.1.01"
  nome: string;
  level: 0 | 1 | 2;
  tipo: 'rec' | 'exp';
  saldo_mes: number;
  qtd_lancamentos_mes: number;
}

interface Props {
  modelo: string;          // "Comunicação Visual · 2 níveis"
  contas: Conta[];
  filters: { busca: string };
}

const brl = (v: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);
const brlNoSign = (v: number) => brl(Math.abs(v)).replace('R$', '').trim();

export default function PlanoContasIndex({ modelo, contas, filters }: Props) {
  const [busca, setBusca] = useState(filters.busca ?? '');

  const aplicarBusca = (v: string) => {
    setBusca(v);
    router.get(route('financeiro.plano-de-contas.index'), { busca: v }, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    });
  };

  const editar = (id: number) => {
    router.get(route('financeiro.plano-de-contas.edit', id));
  };

  const novaConta = () => {
    router.get(route('financeiro.plano-de-contas.create'));
  };

  const importarModelo = () => {
    router.get(route('financeiro.plano-de-contas.importar'));
  };

  return (
    <AppShellV2>
      <PageHeader title="Plano de contas" subtitle={modelo} />

      <Card className="mx-6 mt-4 mb-4 overflow-hidden">
        <div className="px-5 py-3 border-b border-stone-200 flex items-center gap-3">
          <div className="min-w-0">
            <div className="text-[10px] uppercase tracking-widest text-stone-500 font-medium whitespace-nowrap">Plano de contas</div>
            <div className="text-[16px] font-semibold mt-0.5 whitespace-nowrap">{modelo}</div>
          </div>
          <div className="ml-auto flex items-center gap-2 shrink-0">
            <Input
              placeholder="Buscar conta…"
              value={busca}
              onChange={(e) => aplicarBusca(e.target.value)}
              className="h-8 w-[160px] text-[12.5px]"
            />
            <Button variant="outline" size="sm" onClick={importarModelo}>Importar</Button>
            <Button size="sm" onClick={novaConta}>+ Nova</Button>
          </div>
        </div>

        <table className="w-full text-[12.5px] tabular-nums">
          <thead>
            <tr className="text-[10px] uppercase tracking-widest text-stone-500 border-b border-stone-200 bg-stone-50/40">
              <th className="pl-6 pr-2 py-2 text-left font-medium w-[100px]">Código</th>
              <th className="px-2 py-2 text-left font-medium">Conta</th>
              <th className="px-2 py-2 text-left font-medium w-[120px]">Tipo</th>
              <th className="px-2 py-2 text-right font-medium w-[80px]">Lanç. mês</th>
              <th className="px-2 py-2 text-right font-medium w-[140px]">Saldo mês</th>
              <th className="pl-2 pr-6 py-2 w-[80px]"></th>
            </tr>
          </thead>
          <tbody>
            {contas.map((c) => (
              <tr
                key={c.id}
                className={`border-b border-stone-100 hover:bg-stone-50/60 ${c.level === 0 ? 'bg-stone-50/40' : ''}`}
              >
                <td className="pl-6 pr-2 py-2 text-stone-500 font-mono text-[11.5px]">{c.codigo}</td>
                <td className="px-2 py-2" style={{ paddingLeft: 12 + c.level * 18 }}>
                  <span
                    className={
                      c.level === 0
                        ? 'font-semibold text-stone-900'
                        : c.level === 1
                        ? 'font-medium text-stone-800'
                        : 'text-stone-600'
                    }
                  >
                    {c.level > 0 && <span className="text-stone-300 mr-1.5">└</span>}
                    {c.nome}
                  </span>
                </td>
                <td className="px-2 py-2">
                  <span
                    className={`inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full ${
                      c.tipo === 'rec' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'
                    }`}
                  >
                    <span
                      className={`w-1.5 h-1.5 rounded-full ${c.tipo === 'rec' ? 'bg-emerald-500' : 'bg-rose-500'}`}
                    />
                    {c.tipo === 'rec' ? 'Receita' : 'Despesa'}
                  </span>
                </td>
                <td className="px-2 py-2 text-right text-stone-600">
                  {c.qtd_lancamentos_mes > 0 ? c.qtd_lancamentos_mes : <span className="text-stone-300">—</span>}
                </td>
                <td
                  className={`px-2 py-2 text-right font-medium ${
                    c.saldo_mes === 0
                      ? 'text-stone-300'
                      : c.saldo_mes > 0
                      ? 'text-emerald-700'
                      : 'text-stone-900'
                  }`}
                >
                  {c.saldo_mes === 0 ? '—' : (
                    <>
                      <span className="text-stone-400 mr-0.5">{c.saldo_mes > 0 ? '+' : '−'}</span>
                      {brlNoSign(c.saldo_mes)}
                    </>
                  )}
                </td>
                <td className="pl-2 pr-6 py-2 text-right">
                  <button
                    onClick={() => editar(c.id)}
                    className="text-[11px] text-stone-500 hover:text-stone-900 underline-offset-2 hover:underline"
                  >
                    editar
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </AppShellV2>
  );
}
