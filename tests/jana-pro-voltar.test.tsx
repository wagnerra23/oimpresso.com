// Jana Pro — o destino do "voltar" (fonte: prototipo-ui `Jana Pro - Paywall CC.html`,
// lido no Cowork; mapeado em memory/requisitos/Jana/Pro-visual-comparison.md §R2/§R8).
//
// Por que jsdom e não Pest: o `ProContractTest` prova o que o CONTROLLER entrega
// (props, Tier 0, idempotência) — todos os 6 UCs desta tela moram lá. Nenhum deles
// toca a tela. O que quebrou aqui é client-side: `router.visit(...)` com o endereço
// errado. Pest de Controller não morde `router.visit`; estes casos mordem.
//
// O defeito: a onda de fusão (US-COPI-148) fez `/ia` virar o Painel e moveu o chat
// pra `/ia/conversa`. Os botões que prometem a conversa continuaram apontando pra
// `/ia` — o rótulo "Voltar ao chat" levava a um dashboard. Cada caso abaixo falha se
// alguém reverter o endereço.
//
// O 4º é controle negativo: prova que o atalho NÃO dispara com uma tecla qualquer —
// sem ele, um `useEffect` que navegasse a cada keydown passaria nos três primeiros.

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent } from '@testing-library/react';

const visit = vi.fn();
vi.mock('@inertiajs/react', () => ({ router: { visit: (...a: unknown[]) => visit(...a) } }));

import Pro from '@/Pages/Jana/Pro';

const PROPS = {
  plan: 'free' as const,
  pricing: { monthly: 49, trialDays: 14 },
  proof: { bruto: 84320, liquido: 79110, caixa: 71480 },
  business: { id: 1, name: 'Empresa Teste' },
};

const CONVERSA = '/ia/conversa';

beforeEach(() => {
  visit.mockClear();
  render(<Pro {...PROPS} />);
});
afterEach(cleanup);

describe('UC-PRO-07 · o "voltar" leva à Conversa, não ao Painel', () => {
  it('o botão "Voltar ao chat" do header vai pra /ia/conversa', () => {
    fireEvent.click(screen.getByRole('button', { name: /voltar ao chat/i }));
    expect(visit).toHaveBeenCalledWith(CONVERSA);
  });

  it('o botão "Falar com a Jana sobre o Pro" do footer vai pro mesmo lugar', () => {
    fireEvent.click(screen.getByRole('button', { name: /falar com a jana/i }));
    expect(visit).toHaveBeenCalledWith(CONVERSA);
  });

  it('o atalho Esc leva ao mesmo destino que os dois botões', () => {
    fireEvent.keyDown(document, { key: 'Escape' });
    expect(visit).toHaveBeenCalledWith(CONVERSA);
  });

  // Controle negativo — sem ele os 3 acima passariam com um handler que navega sempre.
  it('tecla qualquer NÃO navega (só Esc sai da tela)', () => {
    fireEvent.keyDown(document, { key: 'a' });
    fireEvent.keyDown(document, { key: 'Enter' }); // Enter SEM modificador não é o atalho
    expect(visit).not.toHaveBeenCalled();
  });
});
