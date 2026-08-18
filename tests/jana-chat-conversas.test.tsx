// Histórico de conversas da Jana — filtro real + teclado + a11y (fatia C da
// fusão, pacote JANA-FUSAO-2026-08-06; fonte: prototipo-ui/cowork/jana-merge.jsx
// §JmConversa).
//
// Por que jsdom e não Pest: o que mudou aqui é COMPORTAMENTO de teclado e de
// filtro client-side. O Pest estrutural (tests/Feature/) consegue provar que o
// Controller manda `status` no payload, mas não consegue apertar J/K nem medir
// o que o leitor de tela ouve. Estes casos apertam as teclas de fato — é o que
// a DoD da fatia pediu ("testando os atalhos de fato, não só olhando a tela").
//
// Cada caso é bite-test: falha se a feature for removida. Os 3 últimos são
// controle negativo — provam que o atalho NÃO dispara onde não deve (composer
// em foco, combo do browser, ponta da lista).

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent } from '@testing-library/react';

import { ConvSidePanel } from '@/Pages/Jana/Chat';
import type { ConversaResumo } from '@/Components/cockpit/shared';

// Espelha o que buildConversasListPayload manda: 3 ativas + 1 arquivada.
const RECENTES: ConversaResumo[] = [
  { id: '1', titulo: 'Por que a receita caiu 68%?', status: 'ativa' },
  { id: '2', titulo: 'Top devedores ativos', status: 'ativa' },
  { id: '3', titulo: 'Caçambas paradas há mais de 7 dias', status: 'ativa' },
  { id: '9', titulo: 'Fechamento de abril', status: 'arquivada' },
];

function montar(over: Partial<React.ComponentProps<typeof ConvSidePanel>> = {}) {
  const onSelectConv = vi.fn();
  const onToggle = vi.fn();
  const props = {
    fixadas: [] as ConversaResumo[],
    recentes: RECENTES,
    activeConvId: '2',
    onSelectConv,
    aberto: true,
    estreito: false,
    onToggle,
    ...over,
  };
  const utils = render(<ConvSidePanel {...props} />);
  return { onSelectConv, onToggle, container: utils.container };
}

/** Aperta a tecla no window — é onde o handler vive. */
function tecla(key: string, init: KeyboardEventInit = {}) {
  fireEvent.keyDown(window, { key, ...init });
}

beforeEach(() => cleanup());
afterEach(() => cleanup());

describe('UC-COPI-CHAT-01 — filtro Todas | Arquivadas (o filtro filtra de verdade)', () => {
  it('"Todas" mostra as ativas e ESCONDE a arquivada', () => {
    montar();
    expect(screen.getByText('Top devedores ativos')).toBeTruthy();
    expect(screen.queryByText('Fechamento de abril')).toBeNull();
  });

  it('"Arquivadas" mostra SÓ a arquivada', () => {
    montar();
    fireEvent.click(screen.getByRole('tab', { name: 'Arquivadas' }));

    expect(screen.getByText('Fechamento de abril')).toBeTruthy();
    expect(screen.queryByText('Top devedores ativos')).toBeNull();
  });

  it('só existem 2 abas — Minhas/Compartilhadas foram removidas', () => {
    montar();
    const abas = screen.getAllByRole('tab').map((b) => b.textContent);
    expect(abas).toEqual(['Todas', 'Arquivadas']);
  });

  it('nenhuma aba mostra o empty state "Em breve" (fachada removida)', () => {
    montar();
    fireEvent.click(screen.getByRole('tab', { name: 'Arquivadas' }));
    expect(screen.queryByText('Em breve')).toBeNull();
  });
});

describe('UC-COPI-CHAT-02 — J/K navega entre CONVERSAS (não entre mensagens)', () => {
  it('J desce pra próxima conversa da lista visível', () => {
    const { onSelectConv } = montar({ activeConvId: '2' });
    tecla('j');
    // lista visível (sem a arquivada): 1, 2, 3 → depois de 2 vem 3
    expect(onSelectConv).toHaveBeenCalledWith('3');
  });

  it('K sobe pra conversa anterior', () => {
    const { onSelectConv } = montar({ activeConvId: '2' });
    tecla('k');
    expect(onSelectConv).toHaveBeenCalledWith('1');
  });

  it('J/K respeitam o FILTRO — na aba Arquivadas não pulam pra uma ativa', () => {
    const { onSelectConv } = montar({ activeConvId: '2' });
    fireEvent.click(screen.getByRole('tab', { name: 'Arquivadas' }));
    onSelectConv.mockClear();

    // Visível agora só tem a id 9; a ativa (2) está fora do filtro → entra pela ponta.
    tecla('j');
    expect(onSelectConv).toHaveBeenCalledWith('9');
  });

  // ── controle negativo ───────────────────────────────────────────────
  it('NÃO dispara enquanto o usuário digita (foco em input)', () => {
    const { onSelectConv } = montar();
    const busca = screen.getByLabelText('Buscar conversas');
    busca.focus();

    fireEvent.keyDown(busca, { key: 'j', bubbles: true });
    expect(onSelectConv).not.toHaveBeenCalled();
  });

  it('NÃO dispara com modificador (⌘J / Ctrl+J são do browser)', () => {
    const { onSelectConv } = montar();
    tecla('j', { metaKey: true });
    tecla('j', { ctrlKey: true });
    expect(onSelectConv).not.toHaveBeenCalled();
  });

  it('NÃO passa da ponta da lista (K no primeiro item é inerte)', () => {
    const { onSelectConv } = montar({ activeConvId: '1' });
    tecla('k');
    expect(onSelectConv).not.toHaveBeenCalled();
  });
});

describe('UC-COPI-CHAT-03 — ⌘⇧H recolhe o histórico + a dica é visível', () => {
  it('⌘⇧H chama o toggle', () => {
    const { onToggle } = montar();
    tecla('H', { metaKey: true, shiftKey: true });
    expect(onToggle).toHaveBeenCalledTimes(1);
  });

  it('Ctrl+⇧H também (Windows — a Larissa não usa Mac)', () => {
    const { onToggle } = montar();
    tecla('h', { ctrlKey: true, shiftKey: true });
    expect(onToggle).toHaveBeenCalledTimes(1);
  });

  it('a dica dos atalhos aparece na tela', () => {
    montar();
    expect(screen.getByText('J')).toBeTruthy();
    expect(screen.getByText('K')).toBeTruthy();
    expect(screen.getByText('⌘⇧H')).toBeTruthy();
    expect(screen.getByText('recolhe')).toBeTruthy();
  });

  it('recolhido, o rail mantém o atalho — a lista NÃO fica inalcançável', () => {
    const { onToggle } = montar({ aberto: false });
    const peek = screen.getByRole('button', { name: 'Expandir histórico de conversas' });

    fireEvent.click(peek);
    expect(onToggle).toHaveBeenCalledTimes(1);
  });
});

describe('UC-COPI-CHAT-04 — aria-live anuncia a troca de conversa', () => {
  it('anuncia o título quando a conversa ativa muda', () => {
    const onSelectConv = vi.fn();
    const onToggle = vi.fn();
    const base = {
      fixadas: [] as ConversaResumo[],
      recentes: RECENTES,
      onSelectConv,
      aberto: true,
      estreito: false,
      onToggle,
    };
    const { rerender, container } = render(<ConvSidePanel {...base} activeConvId="2" />);

    const live = container.querySelector('[aria-live="polite"]');
    expect(live).toBeTruthy();
    // Primeiro render não anuncia (senão o leitor fala sozinho ao abrir a tela).
    expect(live!.textContent).toBe('');

    rerender(<ConvSidePanel {...base} activeConvId="3" />);
    expect(live!.textContent).toBe('Conversa: Caçambas paradas há mais de 7 dias');
  });
});

describe('UC-COPI-CHAT-11 — o histórico diz QUANTAS conversas, expandido e recolhido', () => {
  // Por que estes casos nasceram DEPOIS da feature: o contador e o peek existem
  // desde 61c770ec0 (2026-08-07, PR #5405) e ficaram 10 dias sem contrato. O
  // `Chat.casos.md` chegou a listá-los como AUSENTES — a comparação com o
  // protótipo procurou pelas classes DELE (`jm-hist-n`, `jm-hist-peek-n`), que
  // não existem aqui porque a tela viva as traduziu pro DS (`cs-count`,
  // `cs-peek-n`). Classe de protótipo não é âncora; comportamento é.

  it('expandido, o cabeçalho mostra o número de conversas visíveis', () => {
    const { container } = montar();
    // 3 ativas — a arquivada não conta na aba Todas.
    expect(container.querySelector('.cs-count')!.textContent).toBe('3');
  });

  it('o contador RESPEITA o filtro — na aba Arquivadas cai pra 1', () => {
    const { container } = montar();

    fireEvent.click(screen.getByRole('tab', { name: 'Arquivadas' }));

    expect(container.querySelector('.cs-count')!.textContent).toBe('1');
  });

  it('recolhido, o rail mostra o rótulo "Histórico" E o número — não só o ícone', () => {
    const { container } = montar({ aberto: false });
    const peek = screen.getByRole('button', { name: 'Expandir histórico de conversas' });

    expect(peek.querySelector('.cs-peek-l')!.textContent).toBe('Histórico');
    expect(peek.querySelector('.cs-peek-n')!.textContent).toBe('3');
    // Controle negativo: recolhido não sobra cabeçalho expandido na árvore.
    expect(container.querySelector('.cs-head')).toBeNull();
  });
});
