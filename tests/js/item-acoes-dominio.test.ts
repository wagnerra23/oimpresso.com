/**
 * UC-V320 — cada ação da linha do item abre o drawer numa aba PRÓPRIA.
 *
 * A âncora de design (`prototipo-ui/cowork/venda-v3/sells-create.jsx:63-65`) tem
 * DOIS botões que abrem o MESMO drawer e passa a aba na chamada:
 * `abrirItem = (i, aba) => { setAbaItem(aba || 'geral'); … }`. O que os distingue
 * é exatamente isso — sem a aba, um "ver detalhes" ao lado de um "Impostos" que
 * caem no mesmo lugar é um botão a mais sem função.
 *
 * O invariante é escrito como PROPRIEDADE (ações distintas ⇒ abas distintas), não
 * como "detalhe devolve 'geral'". Um teste que repete o literal do mapa passaria
 * verde mesmo se o mapa inteiro apontasse pra 'tributacao' — é a lápide §5
 * 2026-06-05 (teste derivado do código é tautológico e trava o desvio).
 */
import { describe, expect, it } from 'vitest';
import {
  ABAS,
  ABA_POR_ACAO,
  abaDaAcao,
  type AcaoDaLinha,
} from '@/Pages/Sells/_components/v3/item-fiscal-dominio';

describe('UC-V320 · a ação escolhida decide a aba do drawer', () => {
  const acoes = Object.keys(ABA_POR_ACAO) as AcaoDaLinha[];

  it('UC-V320 · duas ações NUNCA colapsam na mesma aba', () => {
    // O defeito real que isto guarda: `abaInicial` fixo no render fazia os dois
    // botões abrirem em Tributação. Contar o conjunto pega isso sem citar valor.
    const abas = acoes.map(abaDaAcao);
    expect(new Set(abas).size).toBe(acoes.length);
    expect(acoes.length).toBeGreaterThanOrEqual(2);
  });

  it('UC-V320 · toda ação devolve uma aba que o drawer conhece', () => {
    // Caminho independente: valida contra ABAS (o contrato do drawer), não
    // contra o próprio mapa — se alguém escrever 'tributação' com acento, cai aqui.
    for (const acao of acoes) {
      expect(ABAS).toContain(abaDaAcao(acao));
    }
  });

  it('UC-V320 · o caminho do imposto não passa pela visão geral', () => {
    // A única afirmação de domínio, e ela vem da âncora, não do código: quem
    // clica em "Impostos" chega na tributação — não num passo intermediário.
    expect(abaDaAcao('impostos')).not.toBe(abaDaAcao('detalhe'));
    expect(abaDaAcao('impostos')).toBe('tributacao');
  });
});
