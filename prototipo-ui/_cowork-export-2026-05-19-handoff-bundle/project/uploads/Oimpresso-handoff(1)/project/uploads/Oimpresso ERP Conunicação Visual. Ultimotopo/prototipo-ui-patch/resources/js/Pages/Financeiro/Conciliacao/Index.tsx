// @memcofre
//   tela: /financeiro/conciliacao
//   module: Financeiro
//   status: em-implementacao
//   stories: US-FIN-015 (conciliacao-ofx)
//   rules: R-FIN-001 (multi-tenant), R-FIN-009 (fuzzy-match-confidence)
//   adrs: ui/0114 (cockpit-v2), arq/0006 (importador-ofx)
//   tests: Modules/Financeiro/Tests/Feature/ConciliacaoControllerTest

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Card } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import PageHeader from '@/Components/shared/PageHeader';
import KpiGrid from '@/Components/shared/KpiGrid';
import KpiCard from '@/Components/shared/KpiCard';

interface Linha {
  id: number;
  ofx_id: string;
  data: string;
  data_label: string;
  descricao: string;
  valor: number;
  status: 'matched' | 'suggest' | 'none';
  match_entry_id: number | null;
  match_descricao: string | null;
  match_ref: string | null;
  match_confidence: number | null;
}

interface Props {
  periodo_label: string;
  conta: string;
  importado_em: string;
  total_linhas: number;
  conciliados: number;
  pendentes: number;
  total_in: number;
  total_out: number;
  linhas: Linha[];
}

const brl = (v: number) => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0);
const brlNoSign = (v: number) => brl(Math.abs(v)).replace('R$', '').trim();

export default function ConciliacaoIndex({
  periodo_label,
  conta,
  importado_em,
  total_linhas,
  conciliados,
  pendentes,
  total_in,
  total_out,
  linhas,
}: Props) {
  const [selected, setSelected] = useState<number | null>(null);

  const aceitarSugestao = (id: number) => {
    router.post(route('financeiro.conciliacao.aceitar', id), {}, { preserveScroll: true });
  };
  const desfazerMatch = (id: number) => {
    router.post(route('financeiro.conciliacao.desfazer', id), {}, { preserveScroll: true });
  };

  return (
    <AppShellV2>
      <PageHeader title="Conciliação bancária" subtitle={`Extrato ${conta} × sistema · importado ${importado_em}`} />

      <KpiGrid columns={4}>
        <KpiCard label="Período" value={periodo_label} caption={`${conta} · ${importado_em}`} />
        <KpiCard
          label="Conciliados"
          value={`${conciliados}/${total_linhas}`}
          tone="emerald"
          progress={total_linhas > 0 ? conciliados / total_linhas : 0}
        />
        <KpiCard
          label="Pendente revisão"
          value={String(pendentes)}
          tone="amber"
          caption={`com sugestão automática: ${linhas.filter((l) => l.status === 'suggest').length}`}
        />
        <KpiCard label="Total no extrato" valueDual={[`+ ${brl(total_in)}`, brl(total_out)]} tone="dual" />
      </KpiGrid>

      <Card className="mx-6 mt-4 mb-4 overflow-hidden">
        <div className="grid grid-cols-2 border-b border-stone-200 text-[10px] uppercase tracking-widest text-stone-500 font-medium">
          <div className="px-5 py-2.5 border-r border-stone-200 flex items-center gap-2">
            Extrato {conta}
            <span className="text-stone-400 normal-case tracking-normal text-[11px]">· {total_linhas} lançamentos</span>
          </div>
          <div className="px-5 py-2.5 flex items-center gap-2">
            Sistema oimpresso
            <span className="text-stone-400 normal-case tracking-normal text-[11px]">· match sugerido</span>
          </div>
        </div>

        {linhas.map((l) => (
          <div
            key={l.id}
            className={`grid grid-cols-2 border-b border-stone-100 text-[12.5px] tabular-nums ${
              selected === l.id ? 'bg-stone-50' : 'hover:bg-stone-50/60'
            }`}
            onClick={() => setSelected(l.id)}
          >
            <div className="px-5 py-3 border-r border-stone-200 flex items-center gap-3 cursor-pointer">
              <div className="text-stone-700 w-[60px]">{l.data_label}</div>
              <div className="flex-1 min-w-0">
                <div className="font-medium text-stone-900 truncate">{l.descricao}</div>
                <div className="text-[10.5px] text-stone-400 font-mono">{l.ofx_id}</div>
              </div>
              <div className={`font-semibold ${l.valor > 0 ? 'text-emerald-700' : 'text-stone-900'}`}>
                {l.valor > 0 ? '+' : '−'} {brlNoSign(l.valor)}
              </div>
            </div>

            <div className="px-5 py-3 flex items-center gap-3">
              {l.status === 'matched' && (
                <>
                  <span className="inline-flex items-center gap-1 text-emerald-700 text-[11px] font-medium px-2 py-0.5 rounded-full bg-emerald-50 whitespace-nowrap">
                    <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                    Conciliado
                  </span>
                  <div className="flex-1 min-w-0">
                    <div className="font-medium text-stone-900 truncate">{l.match_descricao}</div>
                    <div className="text-[10.5px] text-stone-400 font-mono">{l.match_ref}</div>
                  </div>
                  <Button variant="ghost" size="sm" onClick={(e) => { e.stopPropagation(); desfazerMatch(l.id); }}>×</Button>
                </>
              )}
              {l.status === 'suggest' && (
                <>
                  <span className="inline-flex items-center gap-1 text-amber-700 text-[11px] font-medium px-2 py-0.5 rounded-full bg-amber-50 whitespace-nowrap">
                    <span className="w-1.5 h-1.5 rounded-full bg-amber-500" />
                    Sugerido
                  </span>
                  <div className="flex-1 min-w-0">
                    <div className="font-medium text-stone-900 truncate">{l.match_descricao}</div>
                    <div className="text-[10.5px] text-stone-400 font-mono">
                      {l.match_ref} · {Math.round((l.match_confidence ?? 0) * 100)}% match
                    </div>
                  </div>
                  <Button
                    size="sm"
                    className="bg-emerald-50 text-emerald-700 hover:bg-emerald-100"
                    onClick={(e) => { e.stopPropagation(); aceitarSugestao(l.id); }}
                  >
                    ✓ Aceitar
                  </Button>
                </>
              )}
              {l.status === 'none' && (
                <>
                  <span className="inline-flex items-center gap-1 text-stone-500 text-[11px] font-medium px-2 py-0.5 rounded-full bg-stone-100 whitespace-nowrap">
                    <span className="w-1.5 h-1.5 rounded-full bg-stone-400" />
                    Sem match
                  </span>
                  <div className="flex-1 text-stone-500 italic">Provável tarifa bancária — criar lançamento?</div>
                  <Button variant="outline" size="sm">+ Criar</Button>
                  <Button variant="ghost" size="sm">Buscar</Button>
                </>
              )}
            </div>
          </div>
        ))}
      </Card>
    </AppShellV2>
  );
}
