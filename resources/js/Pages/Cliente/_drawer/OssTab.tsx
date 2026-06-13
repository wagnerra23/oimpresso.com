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
import PessoasContatoTab from '../_show/PessoasContatoTab';
import SubscriptionsTab from '../_show/SubscriptionsTab';
import RewardPointsTab from '../_show/RewardPointsTab';
import AuditoriaTab from './AuditoriaTab';
import type { ContactInfo } from './IdentificacaoTab';

// Wagner 2026-05-27 -- iteracao 2: removido sub-tab `placas` (promovido pra
// tab principal acessado via botao header) + removido sub-tab `activities`
// (duplicava `Auditoria` -- mesma fonte `activity_log` Spatie).
// Wagner 2026-06-13: `auditoria` ENTRA aqui como sub-aba de Operações (antes era
// chip flutuante no header — "chips e abas são a mesma coisa, integrar").
export type OssSubTabKey =
  | 'ledger'
  | 'sales'
  | 'payments'
  | 'documents'
  | 'persons'
  | 'subscriptions'
  | 'rewards'
  | 'auditoria';

export interface OssTabProps {
  contact: ContactInfo;
  /**
   * Sub-aba controlada de fora (Wagner 2026-06-01): o chip "📎 N anexos" do
   * header do drawer (Index.tsx) passa `activeSubTab='documents'` pra cair
   * direto nos anexos. Se ausente, OssTab usa estado interno (default 'ledger').
   */
  activeSubTab?: OssSubTabKey;
  /** Reporta troca de sub-aba (clique interno) pro pai manter sync — highlight do chip. */
  onSubTabChange?: (key: OssSubTabKey) => void;
  /** Wagner 2026-06-01 — repassa a contagem viva de anexos pro header (chip "📎 N anexos"). */
  onDocumentsCountChange?: (count: number) => void;
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
  { key: 'persons', label: 'Pessoas', icon: Users },
  { key: 'subscriptions', label: 'Assinaturas', icon: Recycle },
  { key: 'rewards', label: 'Pontos', icon: Gift },
  { key: 'auditoria', label: 'Auditoria', icon: Activity },
];

export default function OssTab({
  contact,
  activeSubTab,
  onSubTabChange,
  onDocumentsCountChange,
  permissions = {},
  locations = [],
}: OssTabProps) {
  // Estado interno = fallback quando OssTab roda sem controle externo. Quando o
  // pai passa `activeSubTab` (Index.tsx via chip header), ELE manda na renderização.
  const [internalActive, setInternalActive] = useState<OssSubTabKey>('ledger');
  const active = activeSubTab ?? internalActive;

  const selectSubTab = (key: OssSubTabKey) => {
    setInternalActive(key);
    onSubTabChange?.(key);
  };

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
              onClick={() => selectSubTab(t.key)}
              aria-selected={isActive}
              aria-controls={`oss-subpanel-${t.key}`}
              data-testid={`oss-subtab-${t.key}`}
              className={
                'w-full flex items-center gap-2 rounded-md px-2 py-1.5 text-xs font-medium transition-colors text-left ' +
                (isActive
                  ? 'bg-primary/10 text-primary'
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
          // Fix 2026-06-08: no drawer, SalesTab busca os dados sozinho via
          // `jsonEndpoint` (self-fetch). Sem isso recebia sales=undefined e
          // ficava preso no skeleton — "as vendas não aparecem no cadastro".
          <SalesTab
            contactId={contact.id}
            jsonEndpoint={`/cliente/${contact.id}/sales-json`}
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
            onCountChange={onDocumentsCountChange}
          />
        )}
        {/* Wagner 2026-05-27 iteracao 2: `placas` virou tab principal acessada
            via botao header; `activities` consolidada com tab principal `Auditoria`. */}
        {active === 'persons' && (
          <PessoasContatoTab contactId={contact.id} contact_persons={undefined} />
        )}
        {/* Fix 2026-06-08: passa contactId pro self-fetch (sem prop = busca o endpoint JSON,
            em vez de ficar preso no skeleton "Carregando…"). */}
        {active === 'subscriptions' && <SubscriptionsTab contactId={contact.id} />}
        {active === 'rewards' && <RewardPointsTab contactId={contact.id} />}
        {/* Wagner 2026-06-13: Auditoria integrada ao rail de Operações (saiu do chip). */}
        {active === 'auditoria' && <AuditoriaTab contact={{ id: contact.id }} />}
      </div>
    </div>
  );
}
