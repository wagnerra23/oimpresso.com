// UC-PCOSF-06 · de onde se abre a Ficha da OS (/producao/ordens/{id}, lote 3.2). Só a OS por item (`pcp_ordem`,
// ADR 0436) tem Ficha; a OS da Comunicação Visual (`comvis_os`) segue com a tela do módulo dela e não ganha link.
// O cartão do Quadro e o bloco da Linha abrem a Ficha no painel (UC-PCOSF-08/09, abaixo); a página segue existindo.
export const OS_POR_ITEM = 'pcp_ordem';

export function urlDaFicha(osTipo: string, osId: number): string | null {
  return osTipo === OS_POR_ITEM && Number.isInteger(osId) && osId > 0 ? `/producao/ordens/${osId}` : null;
}

// UC-PCOSF-08/09 · no Quadro e na Linha do tempo a Ficha abre no painel lateral (decisão [W] no #10850) e a URL guarda
// a OS aberta (`?os={id}`, com o filtro que já estiver na URL), para o F5 e o link copiado reabrirem o painel.
export function urlDoPainel(search: string, osId: number | null, caminho = '/producao/quadro'): string {
  const u = new URLSearchParams(search);
  if (osId !== null && Number.isInteger(osId) && osId > 0) u.set('os', String(osId));
  else u.delete('os');
  const s = u.toString();
  return caminho + (s ? '?' + s : '');
}

export function osDoPainel(search: string): number | null {
  const v = new URLSearchParams(search).get('os');
  return v && /^\d+$/.test(v) && Number(v) > 0 ? Number(v) : null;
}
