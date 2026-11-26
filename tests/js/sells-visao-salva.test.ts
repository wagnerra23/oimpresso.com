/**
 * Lista de vendas — com que visão salva ela abre (decisão [W] 2026-11-25, item 08 da conferência
 * lista × protótipo): "Hoje" para quem nunca escolheu; a escolha gravada vale depois.
 *
 * O caso da ROTA LIVRE é o motivo de a escolha antiga ser respeitada: em 2026-05-21 ela pediu que
 * a lista abrisse em "Todas" (#1311). E na chave antiga o id 'hoje' era "Pendentes pgto.".
 */
import { describe, expect, it } from 'vitest';
import { CHAVE_VISAO, hojeLocal, visaoInicial } from '@/Pages/Sells/_components/visaoSalva';

const IDS = ['hoje', 'pendentes-pgto', 'pendentes', 'atrasadas', 'todas'];
const gravado = (dados: Record<string, string>) => (k: string) => dados[k] ?? '';

describe('Lista de vendas · visão com que abre', () => {
  it('quem nunca escolheu abre em Hoje', () => {
    expect(visaoInicial(gravado({}), IDS)).toBe('hoje');
  });

  it('quem tinha Todas gravado continua em Todas (pedido da ROTA LIVRE, #1311)', () => {
    expect(visaoInicial(gravado({ savedView: 'todas' }), IDS)).toBe('todas');
  });

  it("o 'hoje' da chave antiga era Pendentes pgto. e continua sendo", () => {
    expect(visaoInicial(gravado({ savedView: 'hoje' }), IDS)).toBe('pendentes-pgto');
  });

  it('outra visão antiga é mantida', () => {
    expect(visaoInicial(gravado({ savedView: 'atrasadas' }), IDS)).toBe('atrasadas');
  });

  it("na chave nova, 'hoje' é o dia de hoje e vence a antiga", () => {
    expect(visaoInicial(gravado({ [CHAVE_VISAO]: 'hoje', savedView: 'todas' }), IDS)).toBe('hoje');
  });

  it('id desconhecido cai em Hoje', () => {
    expect(visaoInicial(gravado({ [CHAVE_VISAO]: 'favoritas' }), IDS)).toBe('hoje');
    expect(visaoInicial(gravado({ savedView: 'sumiu' }), IDS)).toBe('hoje');
  });

  it('hojeLocal usa o dia local, não o de UTC', () => {
    const noite = new Date(2026, 10, 26, 22, 30); // 26/11 às 22h30 no fuso local
    expect(hojeLocal(noite)).toBe('2026-11-26');
  });
});
