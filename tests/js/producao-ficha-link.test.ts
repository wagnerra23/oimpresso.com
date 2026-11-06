// UC-PCOSF-06 · o cartão do Quadro e o bloco da Linha abrem a Ficha da OS (/producao/ordens/{id}).
// Fonte: Modules/Producao/Resources/js/Pages/Producao/Os/Show.casos.md. Só a OS por item (`pcp_ordem`) tem Ficha.
// O que mede: o endereço que os dois pontos de entrada usam. CONTROLE: a OS da Comunicação Visual não ganha link.
// O que NÃO prova: o clique no navegador (o arrastar da Linha não pode abrir a Ficha) — isso se confere no smoke.
import { describe, expect, it } from 'vitest';
import { urlDaFicha } from '../../Modules/Producao/Resources/js/Pages/Producao/_lib/ficha';

describe('UC-PCOSF-06 · endereço da Ficha da OS', () => {
  it('OS por item abre /producao/ordens/{id}', () => {
    expect(urlDaFicha('pcp_ordem', 7)).toBe('/producao/ordens/7');
  });
  it('controle: OS da Comunicação Visual e id inválido não ganham link', () => {
    expect(urlDaFicha('comvis_os', 7)).toBeNull();
    expect(urlDaFicha('pcp_ordem', 0)).toBeNull();
    expect(urlDaFicha('pcp_ordem', Number.NaN)).toBeNull();
  });
});
