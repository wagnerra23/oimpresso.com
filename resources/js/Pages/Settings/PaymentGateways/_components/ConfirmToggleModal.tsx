// ConfirmToggleModal.tsx — Trust L3 fail-fast confirm modal
import { useEffect } from 'react';
import { Check, AlertCircle } from 'lucide-react';
import { Btn } from '../../../Financeiro/Cobranca/_components/atoms';
import { DRIVERS, cn, type GatewayKey } from '../_lib/gateway-shared';
import type { SettingsGateway } from '../_lib/gateway-shared';

interface Props {
  gateway: SettingsGateway;
  newValue: boolean;
  affectedCount?: number;
  onConfirm: () => void;
  onClose: () => void;
}

export default function ConfirmToggleModal({ gateway, newValue, affectedCount = 0, onConfirm, onClose }: Props) {
  const isDisabling = !newValue;
  const d = DRIVERS[gateway.driver as GatewayKey];

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [onClose]);

  return (
    <div className="fixed inset-0 z-40 grid place-items-center p-6" onClick={onClose} role="dialog" aria-modal="true" aria-label={isDisabling ? 'Confirmar desativação' : 'Confirmar ativação'}>
      <div className="absolute inset-0 bg-stone-900/40" />
      <div className="relative w-[460px] bg-white rounded-lg shadow-2xl border border-stone-200" onClick={e => e.stopPropagation()}>
        <div className="p-5">
          <div className="flex items-start gap-3">
            <span className={cn(
              'w-9 h-9 rounded-md grid place-items-center shrink-0',
              isDisabling ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700',
            )}>
              {isDisabling ? <AlertCircle className="h-4 w-4" /> : <Check className="h-4 w-4" />}
            </span>
            <div className="flex-1">
              <h3 className="text-[14px] font-semibold text-stone-900">
                {isDisabling ? 'Desativar gateway?' : 'Ativar gateway?'}
              </h3>
              <p className="text-[12px] text-stone-600 mt-1">
                <strong>{gateway.nome}</strong>{d ? ` · driver ${d.nome}` : ''}
              </p>
            </div>
          </div>

          {isDisabling && affectedCount > 0 && (
            <div className="mt-3 bg-rose-50 border border-rose-200 rounded p-3 text-[11.5px] text-rose-900">
              <strong>{affectedCount} cobrança(s) em aberto</strong> dependem deste gateway.
              Desativar bloqueia novas emissões — as existentes continuam pagas via webhook se o banco confirmar.
            </div>
          )}

          {!isDisabling && (
            <div className="mt-3 bg-emerald-50 border border-emerald-200 rounded p-3 text-[11.5px] text-emerald-900">
              Gateway disponível imediatamente pra emissão de cobranças. Health check rodará em 1min.
            </div>
          )}

          <div className="mt-4 text-[10.5px] text-stone-500">
            Esta ação é auditada (Trust L3) e fica registrada no log da credencial via Spatie ActivityLog.
          </div>
        </div>
        <div className="px-5 py-3 border-t border-stone-200 bg-stone-50 flex gap-2 justify-end">
          <Btn variant="outline" onClick={onClose}>Cancelar</Btn>
          <Btn variant="primary" onClick={() => { onConfirm(); onClose(); }} className={isDisabling ? '!bg-rose-600 hover:!bg-rose-700 !border-rose-600' : ''}>
            <Check className="h-3 w-3" />{isDisabling ? 'Desativar' : 'Ativar'}
          </Btn>
        </div>
      </div>
    </div>
  );
}
