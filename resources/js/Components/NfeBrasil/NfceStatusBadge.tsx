// @memcofre
//   modulo: NfeBrasil (NfceStatusBadge)
//   stories: US-NFE-002 fase 2C (badge UI status NFC-e pós-venda)
//   adrs: UI-0008 (cockpit), 0058 (Centrifugo CT 100), 0062 (Hostinger sem daemons)
//   nota: WRAPPER REATIVO — polla status via useNfceStatus e delega a apresentação
//         pro FiscalStatusBadge (componente único de status fiscal). A cor/ícone/
//         vocabulário moram lá; aqui fica só o transport (polling) + a nuance de
//         "aguardando SEFAZ" quando o poll desiste (hasGivenUp).

import { useNfceStatus } from '@/Hooks/useNfceStatus';

import { FiscalStatusBadge } from './FiscalStatusBadge';

interface NfceStatusBadgeProps {
  transactionId: number;
  /** Texto custom em vez de "NFC-e". Útil quando integrar tela com NFe55 também. */
  label?: string;
  /** Compact mode = só ícone + chave. Default false (mostra detalhes). */
  compact?: boolean;
}

export function NfceStatusBadge({
  transactionId,
  label = 'NFC-e',
  compact = false,
}: NfceStatusBadgeProps) {
  const { data, isPolling, hasGivenUp } = useNfceStatus(transactionId);

  // Estado: ainda emitindo (sem dados ou status pendente/null).
  if (!data || data.status === null || data.status === 'pendente') {
    if (hasGivenUp) {
      // Poll desistiu — SEFAZ lenta. Banner warn explícito (≠ "emitindo").
      return (
        <FiscalStatusBadge
          status="waiting"
          label={label}
          title={`${label}: aguardando SEFAZ`}
          detail="A SEFAZ pode estar lenta. Atualize em alguns minutos."
        />
      );
    }
    return (
      <FiscalStatusBadge
        status="emitting"
        label={label}
        spin={isPolling}
        title={`Emitindo ${label}…`}
        detail={
          data?.status === 'pendente'
            ? 'Job processando — aguarde retorno SEFAZ.'
            : 'Aguardando job NFC-e iniciar.'
        }
      />
    );
  }

  // Estado terminal — delega o mapeamento status→cor/ícone pro componente único.
  return (
    <FiscalStatusBadge
      status={data.status}
      label={label}
      numero={data.numero}
      chave={data.chave_44}
      cstat={data.cstat}
      motivo={data.motivo}
      compact={compact}
    />
  );
}
