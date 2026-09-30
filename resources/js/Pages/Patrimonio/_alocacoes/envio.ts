// @patrimonio · thread 18 — o que os drawers de Alocações MANDAM pro servidor.
//
// Separado dos componentes de propósito: é aqui que mora a REGRA MESTRE do lado do cliente
// (quantidade → string que `Util::num_uf` lê sem ambiguidade; data → formato da empresa que
// `Util::uf_date($x, true)` lê). O vitest `patrimonio-alocacoes-envio.test.ts` prova estas
// strings, e o Pest `AlocacoesFormContratoTest` posta AS MESMAS strings e lê o banco — são os
// dois caminhos da dupla prova.
//
// O drawer NÃO decide saldo. Ele só confere o que é forma (campo vazio, número ≤ 0, datas
// fora de ordem); quem recusa por quantidade é o servidor (trava da thread 02 na alocação,
// trava do `RevokeAllocatedAssetController::store` na devolução), e o erro volta pro campo.

import { paraFormatoDoNegocio, paraNumUf } from '../_shared/cadastroBem';

/** Casas enviadas = casas exibidas no `NumericInputPtBR` (mesma regra do cadastro de bem). */
export const CASAS_QUANTIDADE = 2;

export interface FormAlocacao {
  assetId: string;
  receiver: string;
  quantidade: number;
  /** `YYYY-MM-DDTHH:mm` — o valor de um `<input type="datetime-local">`. */
  alocadoEm: string;
  /** `YYYY-MM-DD` ou vazio (indeterminado). */
  prazo: string;
  motivo: string;
  /** Vazio = o servidor gera pelo prefixo da empresa. */
  refNo: string;
}

export interface FormDevolucao {
  quantidade: number;
  devolvidoEm: string;
  motivo: string;
  refNo: string;
}

/**
 * `2026-09-30T14:05` → data+hora no formato que `uf_date($x, true)` lê: `business.date_format`
 * seguido de ` H:i` (24h) ou ` h:i A` (12h) — exatamente o que o `Util::uf_date` concatena.
 */
export function paraDataHoraDoNegocio(isoLocal: string, formatoData: string, hora12: boolean): string {
  const [data = '', hora = ''] = isoLocal.split('T');
  const [hh = '00', mm = '00'] = hora.split(':');
  const dataNegocio = paraFormatoDoNegocio(data, formatoData);
  if (!hora12) return `${dataNegocio} ${hh.padStart(2, '0')}:${mm.padStart(2, '0')}`;
  const h = Number(hh);
  const sufixo = h >= 12 ? 'PM' : 'AM';
  const h12 = h % 12 === 0 ? 12 : h % 12;
  return `${dataNegocio} ${String(h12).padStart(2, '0')}:${mm.padStart(2, '0')} ${sufixo}`;
}

export function validarAlocacao(f: FormAlocacao): Record<string, string> {
  const e: Record<string, string> = {};
  if (!f.assetId) e.assetId = 'Escolha o bem.';
  if (!f.receiver) e.receiver = 'Escolha quem recebe o bem.';
  if (!(f.quantidade > 0)) e.quantidade = 'A quantidade precisa ser maior que zero.';
  if (!f.alocadoEm) e.alocadoEm = 'Informe a data da alocação.';
  if (f.prazo && f.alocadoEm && f.prazo < f.alocadoEm.slice(0, 10)) {
    e.prazo = 'A data final não pode ser antes do início.';
  }
  return e;
}

export function validarDevolucao(f: FormDevolucao): Record<string, string> {
  const e: Record<string, string> = {};
  if (!(f.quantidade > 0)) e.quantidade = 'A quantidade precisa ser maior que zero.';
  if (!f.devolvidoEm) e.devolvidoEm = 'Informe a data da devolução.';
  return e;
}

/** Corpo do POST `asset/allocation` e do PUT `asset/allocation/{id}` — chaves do `AssetAllocationService`. */
export function montarEnvioAlocacao(f: FormAlocacao, formatoData: string, hora12: boolean): Record<string, string> {
  const corpo: Record<string, string> = {
    asset_id: f.assetId,
    receiver: f.receiver,
    quantity: paraNumUf(f.quantidade, CASAS_QUANTIDADE),
    transaction_datetime: paraDataHoraDoNegocio(f.alocadoEm, formatoData, hora12),
    // `allocated_upto` é data SEM hora: `uf_date($x)` sem o segundo argumento.
    allocated_upto: f.prazo ? paraFormatoDoNegocio(f.prazo, formatoData) : '',
    reason: f.motivo.trim(),
  };
  if (f.refNo.trim()) corpo.ref_no = f.refNo.trim();
  return corpo;
}

/** Corpo do POST `asset/revocation`. O `asset_id` NÃO vai: o servidor o tira da alocação. */
export function montarEnvioDevolucao(
  alocacaoId: number,
  f: FormDevolucao,
  formatoData: string,
  hora12: boolean,
): Record<string, string> {
  const corpo: Record<string, string> = {
    parent_id: String(alocacaoId),
    quantity: paraNumUf(f.quantidade, CASAS_QUANTIDADE),
    transaction_datetime: paraDataHoraDoNegocio(f.devolvidoEm, formatoData, hora12),
    reason: f.motivo.trim(),
  };
  if (f.refNo.trim()) corpo.ref_no = f.refNo.trim();
  return corpo;
}

/** `Date` local → `YYYY-MM-DDTHH:mm` (o default "agora" dos campos de data+hora). */
export function agoraLocal(d: Date = new Date()): string {
  const p = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;
}
