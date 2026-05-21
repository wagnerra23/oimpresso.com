// AcoesDropdown.tsx — paridade Blade /purchases dropdown "Ações" (9 opções).
// US-COM-001 — Wave 5b.
//
// Bridge mode: ações que JÁ EXISTEM no Inertia (Ver / Ver pagamentos / Excluir)
// rodam nativo; ações que ainda dependem do Blade legacy (Editar, Imprimir,
// Rótulos, Reembolso, Atualizar status, Notif. pendente) abrem rota Blade
// preservando UX legacy enquanto migração progride.

import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

export interface AcaoVisibility {
  canEdit: boolean;
  canDelete: boolean;
  canEmitirNotaFiscal?: boolean;
  canRefund?: boolean;
}

interface AcoesDropdownProps {
  compraId: number;
  status: string;
  /** Status pagamento (paid/partial/due) — reservado pra mostrar "Reembolso" só em paid */
  paymentStatus?: string;
  hasReturn?: boolean;
  visibility?: AcaoVisibility;
  onOpenDrawer: (compraId: number, tab?: 'resumo' | 'pagamentos') => void;
}

interface ActionItem {
  id: string;
  label: string;
  icon: string;
  onClick: () => void;
  divider?: 'before' | 'after';
  variant?: 'default' | 'danger';
  hidden?: boolean;
}

export default function AcoesDropdown({
  compraId,
  status,
  paymentStatus: _paymentStatus,
  hasReturn = false,
  visibility = { canEdit: true, canDelete: true, canRefund: true },
  onOpenDrawer,
}: AcoesDropdownProps) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  // Click-outside fecha o menu
  useEffect(() => {
    if (!open) return;
    const onClick = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };
    const onEsc = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false);
    };
    document.addEventListener('mousedown', onClick);
    document.addEventListener('keydown', onEsc);
    return () => {
      document.removeEventListener('mousedown', onClick);
      document.removeEventListener('keydown', onEsc);
    };
  }, [open]);

  const handleDelete = () => {
    if (
      window.confirm(
        `Excluir compra #${compraId}? Esta ação não pode ser desfeita. O estoque será revertido.`
      )
    ) {
      router.delete(`/purchases/${compraId}`, {
        onSuccess: () => {
          router.reload({ only: ['rows', 'kpis'] });
        },
      });
    }
    setOpen(false);
  };

  const openBladeNewTab = (url: string) => {
    window.open(url, '_blank', 'noopener,noreferrer');
    setOpen(false);
  };

  const navigateBlade = (url: string) => {
    window.location.href = url;
    setOpen(false);
  };

  const actions: ActionItem[] = [
    {
      id: 'ver',
      label: 'Ver',
      icon: '👁',
      onClick: () => {
        onOpenDrawer(compraId, 'resumo');
        setOpen(false);
      },
    },
    {
      id: 'impressao',
      label: 'Impressão',
      icon: '🖨',
      onClick: () => openBladeNewTab(`/purchases/print/${compraId}`),
    },
    {
      id: 'editar',
      label: 'Editar',
      icon: '✎',
      hidden: !visibility.canEdit,
      onClick: () => navigateBlade(`/purchases/${compraId}/edit`),
    },
    {
      id: 'excluir',
      label: 'Excluir',
      icon: '🗑',
      variant: 'danger',
      hidden: !visibility.canDelete,
      onClick: handleDelete,
    },
    {
      id: 'rotulos',
      label: 'Rótulos',
      icon: '🏷',
      divider: 'after',
      onClick: () => openBladeNewTab(`/labels/show?purchase_id=${compraId}`),
    },
    {
      id: 'pagamentos',
      label: 'Ver pagamentos',
      icon: '💰',
      onClick: () => {
        onOpenDrawer(compraId, 'pagamentos');
        setOpen(false);
      },
    },
    {
      id: 'reembolso',
      label: 'Reembolso de compra',
      icon: '↩',
      hidden: !visibility.canRefund || hasReturn || status === 'rascunho',
      onClick: () => navigateBlade(`/purchase-return/add/${compraId}`),
    },
    {
      id: 'status',
      label: 'Atualizar status',
      icon: '↻',
      onClick: () => {
        // Wave 8 substitui por modal Inertia. Bridge: abre tela de edição
        // que já tem o select de status no form Blade.
        navigateBlade(`/purchases/${compraId}/edit#status`);
      },
    },
    {
      id: 'notify',
      label: 'Elementos pendentes de notificação',
      icon: '✉',
      onClick: () => navigateBlade(`/purchases/${compraId}/edit#notify-pending`),
    },
  ];

  const visibleActions = actions.filter((a) => !a.hidden);

  return (
    <div className="relative inline-block" ref={ref}>
      <button
        type="button"
        onClick={(e) => {
          e.stopPropagation();
          setOpen((v) => !v);
        }}
        aria-haspopup="menu"
        aria-expanded={open}
        className="inline-flex items-center gap-1 rounded-md bg-primary-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500"
      >
        Ações
        <svg className="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
          <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
        </svg>
      </button>

      {open && (
        <div
          role="menu"
          className="absolute left-0 z-50 mt-1 w-56 rounded-md border border-stone-200 bg-white py-1 text-sm shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
        >
          {visibleActions.map((action, i) => (
            <div key={action.id}>
              {action.divider === 'before' && i > 0 && (
                <div className="my-1 h-px bg-stone-200" />
              )}
              <button
                type="button"
                role="menuitem"
                onClick={(e) => {
                  e.stopPropagation();
                  action.onClick();
                }}
                className={`flex w-full items-center gap-2.5 px-3 py-1.5 text-left hover:bg-stone-50 focus:bg-stone-50 focus:outline-none ${
                  action.variant === 'danger' ? 'text-rose-700 hover:bg-rose-50' : 'text-stone-700'
                }`}
              >
                <span className="w-4 text-center text-base">{action.icon}</span>
                <span className="flex-1 truncate">{action.label}</span>
              </button>
              {action.divider === 'after' && (
                <div className="my-1 h-px bg-stone-200" />
              )}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
