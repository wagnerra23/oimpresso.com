// Atalhos do Board da Forja — PMG-008 (overlay `?` + Enter) e anti-regressão dos legados.
//
// POR QUE ESTE SPEC EXISTE: o Pest do módulo (Modules/Forja/Tests/Feature/
// BoardControllerTest) verifica o Controller — não consegue afirmar nada sobre
// tecla. A lógica de teclado vive em Modules/Forja/Resources/js/Pages/Forja/Board/_components/
// useBoardShortcuts.ts justamente pra poder ser montada aqui sem a Page inteira.
//
// O que ele defende, além do que a PMG-008 adicionou:
//  - J/K/E/A e `/` continuam fazendo o que o charter (lei) diz que fazem. A US
//    pedia `e` = "editar"; reatribuir teria quebrado E = avançar status. O spec
//    trava a decisão — se alguém reatribuir, fica vermelho aqui.
//  - o guard de digitação: sem ele, digitar "java" na busca move cards e muda
//    status de task.
//
// Roda em CI pela lane .github/workflows/forja-shortcuts-gate.yml (medido em
// 2026-08-03: nenhuma lane roda `vitest run` sem argumento — spec sem lane
// própria nunca executa, e verde-por-não-execução é pior que teste ausente).
// Local: npx vitest run tests/forjaBoardShortcuts.spec.tsx

import { describe, it, expect, afterEach, vi } from 'vitest';
import { useState } from 'react';
import { render, screen, cleanup, fireEvent, waitFor } from '@testing-library/react';
import useBoardShortcuts, {
  type ShortcutTask,
} from '@/Pages/Forja/Board/_components/useBoardShortcuts';
import ShortcutsOverlay from '@/Pages/Forja/Board/_components/ShortcutsOverlay';

const TASKS: ShortcutTask[] = [
  { task_id: 'COPI-1', status: 'todo' },
  { task_id: 'COPI-2', status: 'doing' },
  { task_id: 'COPI-3', status: 'review' },
];

interface Spies {
  onAdvance: ReturnType<typeof vi.fn>;
  onRetreat: ReturnType<typeof vi.fn>;
  onOpenDetail: ReturnType<typeof vi.fn>;
  onFocusSearch: ReturnType<typeof vi.fn>;
}

/**
 * Harness com o MESMO wiring do Board/Index.tsx (hook + overlay + estado de
 * seleção), sem AppShellV2/Inertia. O `data-testid=sel` publica a seleção
 * corrente pra o teste ler o efeito de J/K sem espiar estado interno.
 */
function Harness({ spies }: { spies: Spies }) {
  const [selectedId, setSelectedId] = useState<string | null>('COPI-1');
  const [helpOpen, setHelpOpen] = useState(false);

  useBoardShortcuts<ShortcutTask>({
    tasks: TASKS,
    selectedId,
    onSelect: setSelectedId,
    onAdvance: spies.onAdvance,
    onRetreat: spies.onRetreat,
    onOpenDetail: spies.onOpenDetail,
    onFocusSearch: spies.onFocusSearch,
    helpOpen,
    onHelpToggle: setHelpOpen,
  });

  return (
    <div>
      <span data-testid="sel">{selectedId ?? '—'}</span>
      <input aria-label="Buscar" />
      <ShortcutsOverlay open={helpOpen} onClose={() => setHelpOpen(false)} />
    </div>
  );
}

function setup() {
  const spies: Spies = {
    onAdvance: vi.fn(),
    onRetreat: vi.fn(),
    onOpenDetail: vi.fn(),
    onFocusSearch: vi.fn(),
  };
  render(<Harness spies={spies} />);
  return spies;
}

const sel = () => screen.getByTestId('sel').textContent;
const overlay = () => screen.queryByRole('dialog');

afterEach(() => cleanup());

describe('useBoardShortcuts — PMG-008 (overlay + Enter)', () => {
  it('`?` abre o overlay com a lista de atalhos', async () => {
    setup();
    expect(overlay()).toBeNull();

    fireEvent.keyDown(document.body, { key: '?' });

    await waitFor(() => expect(overlay()).not.toBeNull());
    expect(screen.getByText('Atalhos do Board')).toBeTruthy();
    // A lista descreve os atalhos que existem de verdade.
    expect(screen.getByText('Avançar status (todo → doing → review → done)')).toBeTruthy();
    expect(screen.getByText('Abrir o painel de detalhe')).toBeTruthy();
  });

  it('`?` de novo e `Esc` fecham o overlay', async () => {
    setup();

    fireEvent.keyDown(document.body, { key: '?' });
    await waitFor(() => expect(overlay()).not.toBeNull());
    fireEvent.keyDown(document.body, { key: '?' });
    await waitFor(() => expect(overlay()).toBeNull());

    fireEvent.keyDown(document.body, { key: '?' });
    await waitFor(() => expect(overlay()).not.toBeNull());
    fireEvent.keyDown(document.body, { key: 'Escape' });
    await waitFor(() => expect(overlay()).toBeNull());
  });

  it('com o overlay aberto, J não navega os cards por trás do modal', async () => {
    setup();
    fireEvent.keyDown(document.body, { key: '?' });
    await waitFor(() => expect(overlay()).not.toBeNull());

    fireEvent.keyDown(document.body, { key: 'j' });

    expect(sel()).toBe('COPI-1');
  });

  it('Enter abre o detalhe da task selecionada', () => {
    const spies = setup();

    fireEvent.keyDown(document.body, { key: 'j' }); // COPI-1 -> COPI-2
    fireEvent.keyDown(document.body, { key: 'Enter' });

    expect(spies.onOpenDetail).toHaveBeenCalledTimes(1);
    expect(spies.onOpenDetail).toHaveBeenCalledWith('COPI-2');
  });
});

describe('useBoardShortcuts — anti-regressão dos atalhos que o charter fixa', () => {
  it('J/K navegam e param nos extremos', () => {
    setup();
    expect(sel()).toBe('COPI-1');

    fireEvent.keyDown(document.body, { key: 'j' });
    expect(sel()).toBe('COPI-2');
    fireEvent.keyDown(document.body, { key: 'j' });
    expect(sel()).toBe('COPI-3');
    fireEvent.keyDown(document.body, { key: 'j' }); // fim da lista: não passa
    expect(sel()).toBe('COPI-3');

    fireEvent.keyDown(document.body, { key: 'k' });
    expect(sel()).toBe('COPI-2');
    fireEvent.keyDown(document.body, { key: 'k' });
    expect(sel()).toBe('COPI-1');
    fireEvent.keyDown(document.body, { key: 'k' }); // topo: não passa
    expect(sel()).toBe('COPI-1');
  });

  it('E avança e A volta o status do card selecionado — NÃO abrem edição', () => {
    const spies = setup();

    fireEvent.keyDown(document.body, { key: 'e' });
    expect(spies.onAdvance).toHaveBeenCalledWith(
      expect.objectContaining({ task_id: 'COPI-1' }),
    );

    fireEvent.keyDown(document.body, { key: 'a' });
    expect(spies.onRetreat).toHaveBeenCalledWith(
      expect.objectContaining({ task_id: 'COPI-1' }),
    );

    // A US pedia `e` = editar; o charter fixa E = avançar. Se alguém reatribuir,
    // este assert cai junto com o de cima.
    expect(spies.onOpenDetail).not.toHaveBeenCalled();
  });

  it('`/` foca a busca', () => {
    const spies = setup();
    fireEvent.keyDown(document.body, { key: '/' });
    expect(spies.onFocusSearch).toHaveBeenCalledTimes(1);
  });
});

describe('useBoardShortcuts — guard de digitação', () => {
  it('digitar num input não navega, não muda status e não abre o overlay', () => {
    const spies = setup();
    const input = screen.getByLabelText('Buscar');

    // "ja?e" — cada tecla seria um atalho se o guard falhasse.
    fireEvent.keyDown(input, { key: 'j' });
    fireEvent.keyDown(input, { key: 'a' });
    fireEvent.keyDown(input, { key: '?' });
    fireEvent.keyDown(input, { key: 'e' });

    expect(sel()).toBe('COPI-1');
    expect(spies.onAdvance).not.toHaveBeenCalled();
    expect(spies.onRetreat).not.toHaveBeenCalled();
    expect(overlay()).toBeNull();
  });

  it('`/` digitado num input não rouba o foco pra busca', () => {
    const spies = setup();
    fireEvent.keyDown(screen.getByLabelText('Buscar'), { key: '/' });
    expect(spies.onFocusSearch).not.toHaveBeenCalled();
  });
});
