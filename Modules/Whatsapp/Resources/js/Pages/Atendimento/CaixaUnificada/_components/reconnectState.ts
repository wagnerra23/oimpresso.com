// reconnectState — contrato de "sessão já ativa" do fluxo Reconectar (Camada 1).
//
// Bug 2026-06-18 (verificado ao vivo no daemon CT 100: canal logado + recebendo
// mensagens, webhook 200): o endpoint `connect` sinaliza canal já ativo com
// `state:'paired'` (+ `paired:true`), enquanto o `status` usa `state:'connected'`.
// O ReconnectModal só reconhecia 'connected' → a verdade "Canal já pareado —
// sessão ativa" caía no ramo de ERRO (texto vermelho `destructive-fg`) com o botão
// "Já escaneei" fantasma. Aqui os dois vocabulários ficam unificados num único
// predicado puro, testável e travado por vitest (a catraca que faltou no #2974).

export interface ReconnectResponse {
  ok?: boolean;
  state?: string | null;
  paired?: boolean;
}

/**
 * true quando a sessão já está ativa/logada — é SUCESSO, não erro.
 *
 * Aceita os dois vocabulários do backend de propósito:
 *   - `connect` (ChannelsController::whatsmeowPairedResponse) → `state:'paired'` + `paired:true`
 *   - `status`  (ChannelsController::statusWhatsmeow)          → `state:'connected'`
 */
export function isSessionActive(data: ReconnectResponse | null | undefined): boolean {
  if (!data) return false;
  return data.paired === true || data.state === 'paired' || data.state === 'connected';
}
