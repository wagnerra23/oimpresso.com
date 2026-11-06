// UC-PCOSF-06 · de onde se abre a Ficha da OS (/producao/ordens/{id}, lote 3.2). Só a OS por item (`pcp_ordem`,
// ADR 0436) tem Ficha; a OS da Comunicação Visual (`comvis_os`) segue com a tela do módulo dela e não ganha link.
// Usado pelo cartão do Quadro e pelo bloco da Linha do tempo.
export const OS_POR_ITEM = 'pcp_ordem';

export function urlDaFicha(osTipo: string, osId: number): string | null {
  return osTipo === OS_POR_ITEM && Number.isInteger(osId) && osId > 0 ? `/producao/ordens/${osId}` : null;
}
