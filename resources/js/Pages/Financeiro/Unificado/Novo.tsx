// @memcofre tela=/financeiro/unificado/novo module=Financeiro status=stub
//
// Stub picker entre receber/pagar até US-FIN-XXX entregar formulário unificado
// inline. Resolve o 404 do botão "+ Novo" / "+ Adicionar primeiro lançamento"
// na Visão Unificada (Index.tsx) sem reescrever feature inteira.

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { ArrowDownCircle, ArrowUpCircle, Plus } from 'lucide-react';

export default function NovoLancamento() {
  return (
    // Onda 13 (2026-05-19) — canon paridade Unificado: header os-page-h + wrapper fin-curadoria
    <div className="fin-curadoria vendas-aplus p-6">
      <header className="os-page-h fin-page-h">
        <div className="os-page-h-l fin-page-h-l">
          <h1>Novo lançamento <span className="fin-hero-title-sub">· Escolha o tipo</span></h1>
          <p>Receber (entrada) ou pagar (saída) — picker provisório até form unificado inline entregar</p>
        </div>
      </header>

      <div className="mt-6 grid gap-4 grid-cols-1 md:grid-cols-2 max-w-3xl">
        <div
          role="button"
          tabIndex={0}
          onClick={() => router.visit('/financeiro/contas-receber')}
          onKeyDown={(e) => { if (e.key === 'Enter') router.visit('/financeiro/contas-receber'); }}
          className="fin-stat cursor-pointer hover:border-emerald-400 transition-colors"
        >
          <div className="flex items-center gap-3">
            <div className="flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-50 text-emerald-700">
              <ArrowDownCircle size={20} />
            </div>
            <h3 className="text-base font-semibold m-0">Novo recebimento</h3>
          </div>
          <p className="text-[13px] text-stone-500 mt-2">
            Cadastre um título a receber (cliente vai pagar você). Ex: venda fatura, prestação de
            serviço, mensalidade.
          </p>
          <button type="button" className="os-btn ghost self-start mt-2">
            <Plus size={13} /> Continuar
          </button>
        </div>

        <div
          role="button"
          tabIndex={0}
          onClick={() => router.visit('/financeiro/contas-pagar')}
          onKeyDown={(e) => { if (e.key === 'Enter') router.visit('/financeiro/contas-pagar'); }}
          className="fin-stat cursor-pointer hover:border-amber-400 transition-colors"
        >
          <div className="flex items-center gap-3">
            <div className="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-50 text-amber-700">
              <ArrowUpCircle size={20} />
            </div>
            <h3 className="text-base font-semibold m-0">Novo pagamento</h3>
          </div>
          <p className="text-[13px] text-stone-500 mt-2">
            Cadastre um título a pagar (você vai pagar a alguém). Ex: fornecedor, aluguel, conta
            de luz, imposto.
          </p>
          <button type="button" className="os-btn ghost self-start mt-2">
            <Plus size={13} /> Continuar
          </button>
        </div>
      </div>

      <p className="mt-6 text-[11px] text-stone-500 max-w-3xl">
        Form unificado inline (sem precisar escolher antes) está no roadmap — esta é uma ponte
        provisória pras telas existentes de contas a receber e pagar.
      </p>
    </div>
  );
}

NovoLancamento.layout = (page: React.ReactNode) => (
  <AppShellV2>
    <div className="fin-cowork">{page}</div>
  </AppShellV2>
);
