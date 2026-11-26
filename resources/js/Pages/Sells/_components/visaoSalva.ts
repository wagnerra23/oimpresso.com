/**
 * Visão salva com que a lista de vendas abre.
 *
 * Quem nunca escolheu abre em "Hoje" (decisão [W] 2026-11-25, item 08 da conferência lista ×
 * protótipo); depois vale a última escolha da pessoa, como no protótipo.
 *
 * A escolha antiga, gravada em 'savedView', é respeitada: a ROTA LIVRE (biz=4) pediu em
 * 2026-05-21 que a lista abrisse em "Todas" (#1311), e quem já tem "Todas" gravado continua nela.
 * Na chave antiga o id 'hoje' significava "Pendentes pgto." (rótulo herdado da reescrita de
 * 2026-05-17) e é traduzido. A escolha nova é gravada em 'savedView.v2', onde 'hoje' é o dia.
 */
export const CHAVE_VISAO = 'savedView.v2';
const CHAVE_ANTIGA = 'savedView';

export function visaoInicial(get: (chave: string) => string, ids: readonly string[]): string {
  const atual = get(CHAVE_VISAO);
  if (atual) return ids.includes(atual) ? atual : 'hoje';
  const antiga = get(CHAVE_ANTIGA);
  if (antiga === 'hoje') return 'pendentes-pgto';
  return ids.includes(antiga) ? antiga : 'hoje';
}

/** Data de hoje no fuso do navegador (AAAA-MM-DD). `toISOString()` daria o dia em UTC. */
export function hojeLocal(agora: Date = new Date()): string {
  const m = String(agora.getMonth() + 1).padStart(2, '0');
  const d = String(agora.getDate()).padStart(2, '0');
  return `${agora.getFullYear()}-${m}-${d}`;
}
