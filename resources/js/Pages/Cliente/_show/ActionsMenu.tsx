// Wave E — US-CRM-067 Menu Ações dropdown + atalhos (MWART F3 paridade /contacts/{id} header actions)
// Restrições Tier 0 (ADR 0093): backend endpoints filtram business_id global scope.
// Backend endpoints existentes:
//   GET /contacts/update-status/{id} (toggle is_active, ContactController::updateStatus linha 1960)
//   DELETE /contacts/{id} (ContactController::destroy linha 1190)
//   POST /payments/pay-contact-due/{contact_id} (TransactionPaymentController::getPayContactDue linha 447 routes)
//   POST /ledger-discount (LedgerDiscountController::store)
//
// Pattern reuse: Components/ui/dropdown-menu.tsx (shadcn primitive).

import { useState } from 'react';
import { router } from '@inertiajs/react';
import {
  ChevronDown,
  CreditCard,
  FileText,
  MoreVertical,
  PiggyBank,
  Power,
  Receipt,
  ShoppingCart,
  Trash2,
} from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import AddDiscountModal from './AddDiscountModal';

export interface ActionsMenuProps {
  contactId: number;
  contactName: string;
  contactType: 'customer' | 'supplier' | 'both';
  isActive: boolean;
  permissions: {
    pay_due: boolean;
    delete: boolean;
    toggle_status: boolean;
    add_discount: boolean;
  };
  /** Callback opcional pra parent recarregar dados após ação destrutiva */
  onAfterDestroy?: () => void;
}

function getCsrf(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export default function ActionsMenu({
  contactId,
  contactName,
  contactType,
  isActive,
  permissions,
  onAfterDestroy,
}: ActionsMenuProps) {
  const [discountModalOpen, setDiscountModalOpen] = useState(false);
  const [busy, setBusy] = useState(false);

  const handleToggleStatus = async () => {
    if (!confirm(isActive ? `Desativar ${contactName}?` : `Reativar ${contactName}?`)) return;
    setBusy(true);
    try {
      const res = await fetch(`/contacts/update-status/${contactId}`, {
        credentials: 'same-origin',
        headers: {
          'X-CSRF-TOKEN': getCsrf(),
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json',
        },
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      router.reload({ only: ['contact', 'stats'] });
    } catch {
      alert('Erro ao alterar status. Tente novamente.');
    } finally {
      setBusy(false);
    }
  };

  const handleDelete = async () => {
    if (!confirm(`Excluir ${contactName}? Esta ação não pode ser desfeita.`)) return;
    setBusy(true);
    try {
      const fd = new FormData();
      fd.append('_method', 'DELETE');
      const res = await fetch(`/contacts/${contactId}`, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: {
          'X-CSRF-TOKEN': getCsrf(),
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json',
        },
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      if (onAfterDestroy) onAfterDestroy();
      else window.location.href = '/contacts/customer';
    } catch {
      alert('Erro ao excluir cliente. Verifique se há transações vinculadas.');
    } finally {
      setBusy(false);
    }
  };

  const goToPay = () => {
    // Modal de pagamento existe em legacy via /payments/pay-contact-due/{id}
    window.location.href = `/payments/pay-contact-due/${contactId}`;
  };

  return (
    <div className="inline-flex items-center gap-2" data-testid="actions-menu-root">
      {permissions.add_discount && (
        <Button
          variant="outline"
          size="sm"
          onClick={() => setDiscountModalOpen(true)}
          disabled={busy}
          data-testid="actions-add-discount-btn"
        >
          <PiggyBank className="mr-1.5 h-4 w-4" aria-hidden />
          Aplicar desconto
        </Button>
      )}

      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button variant="outline" size="sm" disabled={busy} data-testid="actions-dropdown-trigger" aria-label="Mais ações">
            <MoreVertical className="h-4 w-4" aria-hidden />
            <ChevronDown className="ml-1 h-3 w-3" aria-hidden />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" className="w-56" data-testid="actions-dropdown-content">
          <DropdownMenuLabel className="text-[10px] uppercase tracking-wider text-muted-foreground">
            Ações financeiras
          </DropdownMenuLabel>
          {permissions.pay_due && (
            <DropdownMenuItem onClick={goToPay} data-testid="actions-pay-due">
              <CreditCard className="mr-2 h-4 w-4 text-muted-foreground" aria-hidden />
              Receber pagamento
            </DropdownMenuItem>
          )}

          <DropdownMenuSeparator />
          <DropdownMenuLabel className="text-[10px] uppercase tracking-wider text-muted-foreground">
            Atalhos
          </DropdownMenuLabel>
          <DropdownMenuItem asChild data-testid="actions-shortcut-ledger">
            <a href={`/contacts/ledger?contact_id=${contactId}`}>
              <FileText className="mr-2 h-4 w-4 text-muted-foreground" aria-hidden />
              Ver extrato completo
            </a>
          </DropdownMenuItem>
          {(contactType === 'customer' || contactType === 'both') && (
            <DropdownMenuItem asChild data-testid="actions-shortcut-sales">
              <a href={`/sells?customer_id=${contactId}`}>
                <ShoppingCart className="mr-2 h-4 w-4 text-muted-foreground" aria-hidden />
                Ver vendas
              </a>
            </DropdownMenuItem>
          )}
          {(contactType === 'supplier' || contactType === 'both') && (
            <DropdownMenuItem asChild data-testid="actions-shortcut-purchases">
              <a href={`/purchases?supplier_id=${contactId}`}>
                <Receipt className="mr-2 h-4 w-4 text-muted-foreground" aria-hidden />
                Ver compras
              </a>
            </DropdownMenuItem>
          )}

          <DropdownMenuSeparator />
          <DropdownMenuLabel className="text-[10px] uppercase tracking-wider text-muted-foreground">
            Gerenciar
          </DropdownMenuLabel>
          {permissions.toggle_status && (
            <DropdownMenuItem onClick={handleToggleStatus} data-testid="actions-toggle-status">
              <Power className="mr-2 h-4 w-4 text-muted-foreground" aria-hidden />
              {isActive ? 'Desativar cliente' : 'Reativar cliente'}
            </DropdownMenuItem>
          )}
          {permissions.delete && (
            <DropdownMenuItem
              onClick={handleDelete}
              className="text-rose-700 dark:text-rose-400 focus:text-rose-700 focus:bg-rose-50 dark:focus:bg-rose-950/40"
              data-testid="actions-delete"
            >
              <Trash2 className="mr-2 h-4 w-4" aria-hidden />
              Excluir cliente
            </DropdownMenuItem>
          )}
        </DropdownMenuContent>
      </DropdownMenu>

      {discountModalOpen && (
        <AddDiscountModal
          contactId={contactId}
          contactName={contactName}
          contactType={contactType}
          onClose={() => setDiscountModalOpen(false)}
          onSuccess={() => {
            setDiscountModalOpen(false);
            router.reload({ only: ['ledger', 'stats', 'transactions'] });
          }}
        />
      )}
    </div>
  );
}
