// fiscalStatus.ts — modelo de domínio + helpers do status fiscal (sem JSX).
//
// Separado do FiscalStatusBadge.tsx pra não misturar export de função com export
// de componente (react-refresh/only-export-components). Fonte ÚNICA do vocabulário
// de status fiscal: cobre os 3 documentos (NFC-e 65 · NF-e 55 · NFS-e).

/** Documento fiscal. '65' = NFC-e (consumidor) · '55' = NF-e (B2B) · 'nfse' = serviço municipal. */
export type FiscalDocModel = '55' | '65' | 'nfse';

/** Estado semântico unificado do documento fiscal (independe do doc). */
export type FiscalStatusKind =
  | 'emitting'    // job disparado, ainda sem retorno SEFAZ/prefeitura
  | 'waiting'     // pendente/processando — aguardando webservice
  | 'authorized'  // autorizada / emitida com sucesso
  | 'rejected'    // rejeitada (erro de validação)
  | 'denied'      // denegada (irregularidade cadastral)
  | 'cancelled'   // cancelada após autorização
  | 'inutilized'; // faixa de numeração inutilizada

/** Status string vindos dos hooks (useEmissoesPorTransaction / useNfceStatus). */
export type EmissaoLikeStatus =
  | 'pendente' | 'autorizada' | 'rejeitada' | 'denegada' | 'cancelada' | 'inutilizada';

const DOC_LABEL: Record<FiscalDocModel, string> = {
  '55': 'NF-e',
  '65': 'NFC-e',
  nfse: 'NFS-e',
};

/** Rótulo amigável do documento. `modelo_label` dos hooks ('NFe'/'NFC-e') também aceito. */
export function docLabel(model?: FiscalDocModel | string | null): string {
  if (!model) return 'Documento fiscal';
  if (model === '55' || model === '65' || model === 'nfse') return DOC_LABEL[model];
  // Normaliza rótulos legados ('NFe' → 'NF-e').
  if (model === 'NFe') return 'NF-e';
  return model;
}

/** Mapeia o status string dos hooks para o estado semântico unificado. */
export function emissaoStatusToKind(
  status: EmissaoLikeStatus | string | null | undefined,
): FiscalStatusKind {
  switch (status) {
    case 'autorizada': return 'authorized';
    case 'rejeitada': return 'rejected';
    case 'denegada': return 'denied';
    case 'cancelada': return 'cancelled';
    case 'inutilizada': return 'inutilized';
    case 'pendente': return 'waiting';
    default: return 'emitting'; // null / desconhecido = ainda emitindo
  }
}
