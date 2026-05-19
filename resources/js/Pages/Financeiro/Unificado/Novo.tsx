// @memcofre tela=/financeiro/unificado/novo module=Financeiro status=stub
//
// Stub picker entre receber/pagar até US-FIN-XXX entregar formulário unificado
// inline. Resolve o 404 do botão "+ Novo" / "+ Adicionar primeiro lançamento"
// na Visão Unificada (Index.tsx) sem reescrever feature inteira.

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import PageHeader from '@/Components/shared/PageHeader';
import { Icon } from '@/Components/Icon';

export default function NovoLancamento() {
  return (
    <>
      <PageHeader
        icon="plus-circle"
        title="Novo lançamento"
        description="Escolha o tipo do lançamento"
      />

      <div className="mt-6 grid gap-4 grid-cols-1 md:grid-cols-2 max-w-3xl">
        <Card
          role="button"
          tabIndex={0}
          onClick={() => router.visit('/financeiro/contas-receber')}
          onKeyDown={(e) => { if (e.key === 'Enter') router.visit('/financeiro/contas-receber'); }}
          className="cursor-pointer hover:border-emerald-500/40 transition-colors"
        >
          <CardContent className="p-6 flex flex-col gap-3">
            <div className="flex items-center gap-3">
              <div className="flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-500/10 text-emerald-600">
                <Icon name="arrow-down-circle" size={20} />
              </div>
              <h3 className="text-base font-semibold">Novo recebimento</h3>
            </div>
            <p className="text-sm text-muted-foreground">
              Cadastre um título a receber (cliente vai pagar você). Ex: venda fatura, prestação de
              serviço, mensalidade.
            </p>
            <Button variant="outline" size="sm" className="self-start">
              Continuar →
            </Button>
          </CardContent>
        </Card>

        <Card
          role="button"
          tabIndex={0}
          onClick={() => router.visit('/financeiro/contas-pagar')}
          onKeyDown={(e) => { if (e.key === 'Enter') router.visit('/financeiro/contas-pagar'); }}
          className="cursor-pointer hover:border-amber-500/40 transition-colors"
        >
          <CardContent className="p-6 flex flex-col gap-3">
            <div className="flex items-center gap-3">
              <div className="flex items-center justify-center w-10 h-10 rounded-lg bg-amber-500/10 text-amber-600">
                <Icon name="arrow-up-circle" size={20} />
              </div>
              <h3 className="text-base font-semibold">Novo pagamento</h3>
            </div>
            <p className="text-sm text-muted-foreground">
              Cadastre um título a pagar (você vai pagar a alguém). Ex: fornecedor, aluguel, conta
              de luz, imposto.
            </p>
            <Button variant="outline" size="sm" className="self-start">
              Continuar →
            </Button>
          </CardContent>
        </Card>
      </div>

      <p className="mt-6 text-xs text-muted-foreground max-w-3xl">
        Form unificado inline (sem precisar escolher antes) está no roadmap — esta é uma ponte
        provisória pras telas existentes de contas a receber e pagar.
      </p>
    </>
  );
}

NovoLancamento.layout = (page: React.ReactNode) => (
  <AppShellV2>
    <div className="fin-cowork">{page}</div>
  </AppShellV2>
);
