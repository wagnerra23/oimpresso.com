// UC-PCOSF-06 · o bloco da Linha abre a Ficha da OS (/producao/ordens/{id}); o cartão do Quadro abre o painel (UC-PCOSF-08).
// Fonte: Modules/Producao/Resources/js/Pages/Producao/Os/Show.casos.md. Só a OS por item (`pcp_ordem`) tem Ficha.
// O que mede: o endereço que os dois pontos de entrada usam. CONTROLE: a OS da Comunicação Visual não ganha link.
// O que NÃO prova: o clique no navegador (o arrastar da Linha não pode abrir a Ficha) — isso se confere no smoke.
import { describe, expect, it } from 'vitest';
import { osDoPainel, urlDaFicha, urlDoPainel } from '../../Modules/Producao/Resources/js/Pages/Producao/_lib/ficha';

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

// UC-PCOSF-08 · no Quadro a Ficha abre no painel e a URL guarda a OS (`?os={id}`) sem perder o filtro.

describe('UC-PCOSF-08 · URL do painel da Ficha no Quadro', () => {
  it('abrir põe ?os= e mantém o filtro; fechar tira só o ?os=', () => {
    expect(urlDoPainel('', 7)).toBe('/producao/quadro?os=7');
    expect(urlDoPainel('?so=atrasadas&q=banner', 7)).toBe('/producao/quadro?so=atrasadas&q=banner&os=7');
    expect(urlDoPainel('?so=atrasadas&os=7', 9)).toBe('/producao/quadro?so=atrasadas&os=9');
    expect(urlDoPainel('?so=atrasadas&os=7', null)).toBe('/producao/quadro?so=atrasadas');
    expect(urlDoPainel('?os=7', null)).toBe('/producao/quadro');
  });
  it('lê a OS da URL; controle: vazio, texto, zero e negativo não abrem o painel', () => {
    expect(osDoPainel('?so=arte&os=12')).toBe(12);
    for (const s of ['', '?os=', '?os=abc', '?os=0', '?os=-3', '?os=1.5']) expect(osDoPainel(s)).toBeNull();
  });
});
