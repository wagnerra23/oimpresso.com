// Dialog de confirmação pra transição FSM via drag-and-drop.
//
// shadcn AlertDialog (Components/ui/alert-dialog.tsx) — fail-secure:
//   - Cancelar = botão outline (cinza), default focus quando is_critical
//   - Confirmar = variant "default" (cinza-escuro) ou "destructive" (vermelho) se isCritical
//   - "Esta ação é irreversível" exibido só quando isCritical=true
//
// Uso (Index.tsx):
//   const [pending, setPending] = useState<PendingTransition|null>(null)
//   <DragConfirmDialog
//     pending={pending}
//     onConfirm={async () => { await fetch(...) ; setPending(null) }}
//     onCancel={() => setPending(null)}
//   />

import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { AlertTriangle, ArrowRight } from 'lucide-react';

export interface PendingTransition {
  cacambaId: number;
  rentalId: number | null;
  fromColumn: string;
  toColumn: string;
  actionKey: string;
  actionLabel: string;
  isCritical: boolean;
  title: string;
  description: string;
  // Campos opcionais pra exibir no body
  plate?: string;
  cliente_nome?: string | null;
  valor_receber?: number | null;
  dias_locacao?: number | null;
}

interface Props {
  pending: PendingTransition | null;
  loading: boolean;
  onConfirm: () => void;
  onCancel: () => void;
}

const formatBRL = (value: number | null | undefined) =>
  new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(
    Number(value ?? 0),
  );

export default function DragConfirmDialog({
  pending,
  loading,
  onConfirm,
  onCancel,
}: Props) {
  const open = pending !== null;

  return (
    <AlertDialog
      open={open}
      onOpenChange={(next) => {
        if (!next && !loading) onCancel();
      }}
    >
      <AlertDialogContent>
        <AlertDialogHeader>
          <AlertDialogTitle className="flex items-center gap-2">
            {pending?.isCritical ? (
              <AlertTriangle size={18} className="text-amber-600" />
            ) : (
              <ArrowRight size={18} className="text-slate-600" />
            )}
            {pending?.title ?? 'Confirmar transição'}
          </AlertDialogTitle>
          <AlertDialogDescription>
            {pending?.description ?? ''}
          </AlertDialogDescription>
        </AlertDialogHeader>

        {pending && (
          <div className="rounded-md border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs text-slate-700 space-y-1">
            {pending.plate ? (
              <div className="flex justify-between gap-3">
                <span className="text-slate-500">Caçamba</span>
                <span className="font-mono font-medium text-slate-900">
                  {pending.plate}
                </span>
              </div>
            ) : null}
            {pending.cliente_nome ? (
              <div className="flex justify-between gap-3">
                <span className="text-slate-500">Cliente</span>
                <span className="font-medium text-slate-900 truncate max-w-[60%]">
                  {pending.cliente_nome}
                </span>
              </div>
            ) : null}
            {pending.dias_locacao != null ? (
              <div className="flex justify-between gap-3">
                <span className="text-slate-500">Diárias</span>
                <span className="font-medium text-slate-900 tabular-nums">
                  {pending.dias_locacao}
                </span>
              </div>
            ) : null}
            {pending.valor_receber != null && pending.valor_receber > 0 ? (
              <div className="flex justify-between gap-3">
                <span className="text-slate-500">Valor</span>
                <span className="font-semibold tabular-nums text-emerald-700">
                  {formatBRL(pending.valor_receber)}
                </span>
              </div>
            ) : null}
            <div className="flex justify-between gap-3 pt-1 border-t border-slate-200">
              <span className="text-slate-500">Ação FSM</span>
              <span className="font-mono text-[11px] text-slate-700">
                {pending.actionLabel}
              </span>
            </div>
          </div>
        )}

        {pending?.isCritical && (
          <div className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 flex items-start gap-2">
            <AlertTriangle size={14} className="mt-0.5 flex-shrink-0" />
            <span>
              <b>Esta ação é irreversível</b> — o histórico FSM registra
              auditoria permanente.
            </span>
          </div>
        )}

        <AlertDialogFooter>
          <AlertDialogCancel disabled={loading}>Cancelar</AlertDialogCancel>
          <AlertDialogAction
            variant={pending?.isCritical ? 'destructive' : 'default'}
            onClick={(e) => {
              e.preventDefault();
              onConfirm();
            }}
            disabled={loading}
          >
            {loading ? 'Aplicando…' : 'Confirmar'}
          </AlertDialogAction>
        </AlertDialogFooter>
      </AlertDialogContent>
    </AlertDialog>
  );
}
