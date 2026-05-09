import { Boom } from '@hapi/boom';
import { DisconnectReason } from '@whiskeysockets/baileys';

/**
 * Heurísticas para detectar ban Meta vs queda transitória.
 *
 * Sinais de ban (não recuperáveis sem novo número):
 *  - DisconnectReason.loggedOut (401 — sessão revogada permanentemente)
 *  - DisconnectReason.forbidden
 *  - DisconnectReason.badSession (recorrente)
 *
 * Sinais transitórios (reconectar):
 *  - connectionClosed / connectionLost / connectionReplaced / restartRequired
 *  - timedOut / multideviceMismatch
 */
export interface BanAnalysis {
  banned: boolean;
  reason: string;
  shouldReconnect: boolean;
}

export function analyzeDisconnect(error: unknown): BanAnalysis {
  const statusCode =
    error instanceof Boom
      ? error.output.statusCode
      : (error as { output?: { statusCode?: number } } | undefined)?.output?.statusCode;

  switch (statusCode) {
    case DisconnectReason.loggedOut:
      return { banned: true, reason: 'logged_out', shouldReconnect: false };
    case DisconnectReason.forbidden:
      return { banned: true, reason: 'forbidden', shouldReconnect: false };
    case DisconnectReason.badSession:
      return { banned: false, reason: 'bad_session', shouldReconnect: true };
    case DisconnectReason.connectionClosed:
      return { banned: false, reason: 'connection_closed', shouldReconnect: true };
    case DisconnectReason.connectionLost:
      return { banned: false, reason: 'connection_lost', shouldReconnect: true };
    case DisconnectReason.connectionReplaced:
      return { banned: false, reason: 'connection_replaced', shouldReconnect: false };
    case DisconnectReason.restartRequired:
      return { banned: false, reason: 'restart_required', shouldReconnect: true };
    case DisconnectReason.timedOut:
      return { banned: false, reason: 'timed_out', shouldReconnect: true };
    case DisconnectReason.multideviceMismatch:
      return { banned: true, reason: 'multidevice_mismatch', shouldReconnect: false };
    default:
      return { banned: false, reason: `unknown_${statusCode ?? 'no_status'}`, shouldReconnect: true };
  }
}
