// Wave D — Tab "OSs" wrapper (drawer 760px Cliente)
// Refs: ADR 0179 (paradigma drawer substitui Show.tsx full-page) + Charter Index.charter.md v3.
// Missao: empacotar as 8 sub-tabs operacionais Wave Final 2026-05-21 (PRs #1298-1307) dentro
// do drawer 760px. Layout: vertical nav 120px + content 640px scrollable (decisao Wave D
// final — alternativa A do RUNBOOK §4 Wave D, pattern Linear/Notion + protótipo Cowork
// `prototipo-ui/prototipos/clientes/clientes-tabs.jsx`).
//
// Restrições Tier 0 (ADR 0093): este wrapper é puramente client-side; multi-tenant
// continua garantido nos controllers backend que cada sub-tab consome
// (ContactController, SellController, DocumentAndNoteController, AuditEntryService...).
// Cada sub-componente em `_show/*` já foi mergeado preservando paradigma — NAO modificamos.
//
// Pegadinhas:
// - Sub-tabs com payload defer (Activities/Pessoas/Subscriptions/Rewards) recebem `undefined`
//   por ora; renderizam empty-state ou skeleton graceful. Wave futura pode injetar via
//   ContactController::index expandido.
// - 120 + 640 = 760: cabe no drawer. `overflow-y: auto` garante scroll vertical em tabelas
//   largas (Sales/Payments/Documents) — sem scroll horizontal global no drawer.
// - Acessibilidade: nav vertical com role implícito via lista de buttons; aria-selected
//   marca tab ativa; aria-label="Sub-abas operacionais" pra screen readers.

import { useState } from 'react';
import {
  Activity,
  Banknote,
  FileText,
  Gift,
  ListChecks,
  ReceiptText,
  Recycle,
  Users,
  type LucideIcon,
} from 'lucide-react';

import LedgerTab from '../_show/LedgerTab';
import SalesTab from '../_show/SalesTab';
import PaymentsTab from '../_show/PaymentsTab';
import DocumentsTab from '../_show/DocumentsTab';
import ActivitiesTab from '../_show/ActivitiesTab';
import PessoasContatoTab from '../_show/PessoasContatoTab';
import SubscriptionsTab from '../_show/SubscriptionsTab';
import RewardPointsTab from '../_show/RewardPointsTab';
import type { ContactInfo } from './IdentificacaoTab';

type OssSubTabKey =
  | 'ledger'
  | 'sales'
  | 'payments'
  | 'documents'
  | 'activities'
  | 'persons'
  | 'subscriptions'
  | 'rewards';

export interface OssTabProps {
  contact: ContactInfo;
  /**
   * Permissoes injetadas pelo Index.tsx (Wave futura pode estender ContactController::index).
   * Por ora todas default false — sub-tabs operam em modo read-only.
   */
  permissions?: {
    view_sell?: boolean;
    upload?: boolean;
    delete_document?: boolean;
    edit_note?: boolean;
  };
  /** Lista de filiais do business pra filtro do LedgerTab. Default []. */
  locations?: Array<{ id: number; name: string }>;
}

const SUB_TABS: Array<{ key: OssSubTabKey; label: string; icon: LucideIcon }> = [
  { key: 'ledger', label: 'Extrato', icon: ListChecks },
  { key: 'sales', label: 'Vendas', icon: ReceiptText },
  { key: 'payments', label: 'Pagamentos', icon: Banknote },
  { key: 'documents', label: 'Documentos', icon: FileText },
  { key: 'activities', label: 'Atividades', icon: Activity },
  { key: 'persons', label: 'Pessoas', icon: Users },
  { key: 'subscriptions', label: 'Assinaturas', icon: Recycle },
  { key: 'rewards', label: 'Pontos', icon: Gift },
];

export default function OssTab({ contact, permissions = {}, locations = [] }: OssTabProps) {
  const [active, setActive] = useState<OssSubTabKey>('ledger');

  return (
    <div
      className="flex h-full min-h-[480px]"
      data-testid="oss-tab-root"
      role="tabpanel"
      aria-label="Operacoes do cliente"
    >
      {/* Vertical nav 120px — sub-abas operacionais */}
      <nav
        className="w-[120px] flex-shrink-0 border-r border-border pr-2 space-y-0.5"
        aria-label="Sub-abas operacionais"
        role="tablist"
        aria-orientation="vertical"
      >
        {SUB_TABS.map((t) => {
          const Icon = t.icon;
          const isActive = active === t.key;
          return (
            <button
              key={t.key}
              type="button"
              role="tab"
              onClick={() => setActive(t.key)}
              aria-selected={isActive}
              aria-controls={`oss-subpanel-${t.key}`}
              data-testid={`oss-subtab-${t.key}`}
              className={
                'w-full flex items-center gap-2 rounded-md px-2 py-1.5 text-xs font-medium transition-colors text-left ' +
                (isActive
                  ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300'
                  : 'text-muted-foreground hover:bg-muted hover:text-foreground')
              }
            >
              <Icon size={14} aria-hidden="true" />
              {t.label}
            </button>
          );
        })}
      </nav>

      {/* Content 640px scrollable — sub-tab ativa */}
      <div
        className="flex-1 min-w-0 pl-4 overflow-y-auto"
        id={`oss-subpanel-${active}`}
        role="tabpanel"
        aria-labelledby={`oss-subtab-${active}`}
      >
        {active === 'ledger' && (
          <LedgerTab
            contactId={contact.id}
            contactName={contact.name}
            locations={locations}
          />
        )}
        {active === 'sales' && (
          <SalesTab
            contactId={contact.id}
            sales={undefined}
            endpoint={`/cliente/${contact.id}`}
          />
        )}
        {active === 'payments' && (
          <PaymentsTab
            contactId={contact.id}
            canViewSell={permissions.view_sell ?? false}
          />
        )}
        {active === 'documents' && (
          <DocumentsTab
            contactId={contact.id}
            permissions={{
              upload: permissions.upload ?? false,
              delete_document: permissions.delete_document ?? false,
              edit_note: permissions.edit_note ?? false,
            }}
          />
        )}
        {active === 'activities' && <ActivitiesTab activities={undefined} />}
        {active === 'persons' && (
          <PessoasContatoTab contactId={contact.id} contact_persons={undefined} />
        )}
        {active === 'subscriptions' && <SubscriptionsTab subscriptions={undefined} />}
        {active === 'rewards' && <RewardPointsTab reward_points={undefined} />}
      </div>
    </div>
  );
}
