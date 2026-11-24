// lote-saida.ts — envio das ações em lote de NF-e/NFC-e (UC-FCKP-22..24; usado também na NF-e).
//
// Uma porta só para o Painel e para a tela de NF-e: monta o POST para
// `/nfe-brasil/emissoes/lote/{xml|danfe|reenviar-email}`, baixa o arquivo quando há um e
// devolve a frase que a tela mostra. O backend (NfeLoteController) só aceita nota autorizada
// da empresa da sessão e conta o resto como "fora".

import type { TipoSaidaLote } from '../_components/LoteSaidaModal';

const ROTA: Record<TipoSaidaLote, string> = { zip: 'xml', pdf: 'danfe', email: 'reenviar-email' };

/** Faz o pedido e devolve a mensagem para a tela (`null` quando o arquivo baixou sem nada fora). */
export async function enviarLote(tipo: TipoSaidaLote, ids: number[]): Promise<string | null> {
  const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
  const res = await fetch(`/nfe-brasil/emissoes/lote/${ROTA[tipo]}`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
    body: JSON.stringify({ ids }),
  });
  const ctype = res.headers.get('Content-Type') ?? '';
  if (!res.ok || (tipo !== 'email' && ctype.includes('json'))) {
    const corpo = ctype.includes('json') ? await res.json().catch(() => ({})) : {};
    return corpo.message ?? 'Não foi possível concluir a ação.';
  }
  if (tipo === 'email') {
    const r = await res.json();
    return `E-mail reenviado de ${r.enviadas} nota${r.enviadas === 1 ? '' : 's'}`
      + (r.falhas ? ` · ${r.falhas} falharam` : '')
      + (r.fora ? ` · ${r.fora} fora (não autorizadas)` : '');
  }
  const fora = Number(res.headers.get('X-Lote-Fora') ?? 0);
  const blob = await res.blob();
  const nome = /filename="?([^";]+)"?/.exec(res.headers.get('Content-Disposition') ?? '')?.[1]
    ?? (tipo === 'zip' ? 'xmls.zip' : 'danfes.pdf');
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = nome;
  a.click();
  URL.revokeObjectURL(url);
  return fora > 0 ? `${fora} nota${fora > 1 ? 's' : ''} fora do arquivo (não autorizada ou sem arquivo)` : null;
}
