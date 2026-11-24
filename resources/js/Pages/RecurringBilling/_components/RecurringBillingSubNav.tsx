// RecurringBillingSubNav — a navegação entre as 4 vistas da Cobrança Recorrente
// (Assinaturas · Planos · Faturas · Configurações), na forma do protótipo: faixa de abas abaixo
// do título (`PageHeaderNav` / `ph-nav` em cobranca-recorrente-page.jsx), sem contador.
//
// DERIVA, não declara: as abas são os `ghosts` da entrada "Cobrança Recorrente" do sidebar
// (Modules/RecurringBilling/Http/Controllers/DataController.php), entregues pelo shell em
// `shell.menu`. Escrever a lista aqui criaria um segundo dono — mesmo motivo do ConfiguracoesSubNav.
// Sem a entrada no menu (sem permissão `recurringbilling.access`), a tela segue sem abas.

import { usePage } from '@inertiajs/react';
import PageHeaderTabs, { type PageHeaderGhost } from '@/Components/shared/PageHeaderTabs';

interface ItemDeMenu { label?: string; ghosts?: PageHeaderGhost[]; children?: ItemDeMenu[] }

/** A entrada do menu cujos ghosts incluem o `key` pedido (keys são únicas no menu). */
function acharEntrada(itens: ItemDeMenu[] | undefined, key: string): ItemDeMenu | null {
  for (const item of itens ?? []) {
    if (item.ghosts?.some((g) => g.key === key)) return item;
    const dentro = acharEntrada(item.children, key);
    if (dentro) return dentro;
  }
  return null;
}

export type RecurringBillingAba = 'recurring-billing' | 'planos' | 'faturas' | 'configuracoes';

export default function RecurringBillingSubNav({ ativa }: { ativa: RecurringBillingAba }) {
  const { shell } = usePage<{ shell?: { menu?: ItemDeMenu[] } }>().props;
  const entrada = acharEntrada(shell?.menu, 'recurring-billing');
  const ghosts = entrada?.ghosts ?? [];
  if (ghosts.length < 2) return null;

  return (
    <div className="mb-4 border-b border-border" data-contract="subnav">
      <PageHeaderTabs ghosts={ghosts} activeGhostKey={ativa} group="financas" maxVisible={ghosts.length} />
    </div>
  );
}
