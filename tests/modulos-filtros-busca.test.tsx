// /modulos — filtros combinados e busca de 4 campos (UC-MOD-08, UC-MOD-09).
//
// Por que jsdom: filtrar e buscar são 100% client-side (useMemo sobre a prop `modules`).
// O Pest prova o payload; não prova a interseção dos filtros nem o debounce. E o contrato
// de tela (prototipo-ui/contrato/modulos.contract.json) trava a COPY dos dois blocos —
// não o comportamento. Este arquivo é a perna que faltava.
//
// Estes dois UC estavam como [BACKLOG] no casos.md justamente por não terem teste que os
// citasse (G-2 exige a citação do id). Com este arquivo eles voltam a ser UC.
//
// A busca tem debounce de 300ms — os casos avançam o timer com fake timers em vez de
// esperar, senão o teste fica lento e flaky.

import { describe, it, expect, afterEach, beforeEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent, act } from '@testing-library/react';

import ModulesIndex from '@/Pages/Modules/Index';

type ModuleRow = React.ComponentProps<typeof ModulesIndex>['modules'][number];

function modulo(over: Partial<ModuleRow> = {}): ModuleRow {
  return {
    name: 'Alvo',
    alias: 'alvo',
    version: '1.0',
    description: 'Módulo de fixture.',
    area: 'Outros',
    active: true,
    registered: true,
    has_migrations: false,
    migration_count: 0,
    has_datacontroller: true,
    error: null,
    ...over,
  };
}

// Espelha o shape real: nome, alias, descrição e área são os 4 campos que a busca varre.
const FIXTURE: ModuleRow[] = [
  modulo({ name: 'OficinaAuto', alias: 'oficina-auto', area: 'Operações', description: 'Vertical de oficinas.' }),
  modulo({ name: 'Financeiro', alias: 'financeiro', area: 'Outros', description: 'Contas a pagar e receber.' }),
  modulo({ name: 'Repair', alias: 'repair', area: 'Operações', description: 'Ordens de serviço.', active: false }),
];

function digitar(termo: string) {
  const campo = screen.getByLabelText('Buscar módulo');
  fireEvent.change(campo, { target: { value: termo } });
  // vence o debounce de 300ms sem esperar de verdade
  act(() => {
    vi.advanceTimersByTime(350);
  });
}

/** Nomes de módulo visíveis na tabela agora. */
function linhasVisiveis(): string[] {
  return FIXTURE.map((m) => m.name).filter((n) => screen.queryByText(n) !== null);
}

beforeEach(() => vi.useFakeTimers({ shouldAdvanceTime: true }));
afterEach(() => {
  vi.useRealTimers();
  cleanup();
});

describe('/modulos · UC-MOD-09 · busca casa nome, alias, descrição e área', () => {
  it('casa pelo NOME', () => {
    render(<ModulesIndex modules={FIXTURE} />);
    digitar('financeiro');
    expect(linhasVisiveis()).toEqual(['Financeiro']);
  });

  it('casa pelo ALIAS, que difere do nome (oficina-auto ≠ OficinaAuto)', () => {
    render(<ModulesIndex modules={FIXTURE} />);
    digitar('oficina-auto');
    expect(linhasVisiveis()).toEqual(['OficinaAuto']);
  });

  it('casa pela DESCRIÇÃO', () => {
    render(<ModulesIndex modules={FIXTURE} />);
    digitar('ordens de serviço');
    expect(linhasVisiveis()).toEqual(['Repair']);
  });

  it('casa pela ÁREA, trazendo os dois módulos daquela área', () => {
    render(<ModulesIndex modules={FIXTURE} />);
    digitar('operações');
    expect(linhasVisiveis().sort()).toEqual(['OficinaAuto', 'Repair']);
  });

  // controle negativo — sem ele, um filtro que não filtra nada passaria em tudo acima
  it('termo que não casa nada não deixa nenhuma linha', () => {
    render(<ModulesIndex modules={FIXTURE} />);
    digitar('zzzz-nao-existe');
    expect(linhasVisiveis()).toEqual([]);
  });
});

describe('/modulos · UC-MOD-08 · filtro e busca se combinam', () => {
  it('busca por área + termo devolve a INTERSEÇÃO, não a união', () => {
    render(<ModulesIndex modules={FIXTURE} />);

    // "Operações" traria OficinaAuto + Repair; somado a "repair" sobra um só.
    digitar('operações repair');
    expect(linhasVisiveis()).toEqual([]); // termo único, não dois termos: não casa nada

    digitar('repair');
    expect(linhasVisiveis()).toEqual(['Repair']);
  });

  it('o contador reflete o recorte, não o total', () => {
    render(<ModulesIndex modules={FIXTURE} />);
    expect(screen.getByText('3 módulos')).toBeTruthy();

    digitar('financeiro');
    expect(screen.getByText('1 de 3 módulos')).toBeTruthy();
  });
});
