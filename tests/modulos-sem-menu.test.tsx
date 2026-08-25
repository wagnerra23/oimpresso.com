// /modulos — o marcador "sem menu" (UC-MOD-18, patch P3).
//
// Por que jsdom e não Pest: o Pest de serviço já prova que `has_datacontroller` é
// COMPUTADO certo (ModuleManagerServiceTest, UC-MOD-18). O que ele não consegue provar
// é que a TELA usa o campo — e o defeito era exatamente esse: a prop chegava e o
// componente a ignorava. Aqui o assert é sobre o que renderiza.
//
// Contexto que torna este teste a ÚNICA prova possível hoje: medido em 2026-08-19,
// **32 de 32** módulos têm DataController, então o marcador nasce escuro em produção.
// Não há linha real para fotografar num smoke visual; o caso só existe em fixture.
//
// Os dois últimos são controle negativo — provam que o marcador NÃO aparece onde não deve.

import { describe, it, expect, afterEach } from 'vitest';
import { render, screen, cleanup, within } from '@testing-library/react';

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

afterEach(cleanup);

describe('/modulos · marcador "sem menu"', () => {
  it('módulo ativo SEM DataController é sinalizado', () => {
    render(<ModulesIndex modules={[modulo({ name: 'SemMenu', has_datacontroller: false })]} />);

    expect(screen.getByText('sem menu')).toBeTruthy();
  });

  it('o marcador explica a consequência ao passar o mouse', () => {
    render(<ModulesIndex modules={[modulo({ name: 'SemMenu', has_datacontroller: false })]} />);

    expect(screen.getByText('sem menu').getAttribute('title')).toContain('sidebar');
  });

  it('marca a linha CERTA quando há módulos com e sem DataController', () => {
    render(
      <ModulesIndex
        modules={[
          modulo({ name: 'ComMenu', has_datacontroller: true }),
          modulo({ name: 'SemMenu', has_datacontroller: false }),
        ]}
      />,
    );

    const marcadores = screen.getAllByText('sem menu');
    expect(marcadores).toHaveLength(1);

    // sobe até a <tr> e confere que é a linha do SemMenu
    const linha = marcadores[0].closest('tr') as HTMLElement;
    expect(within(linha).getByText('SemMenu')).toBeTruthy();
  });

  // ── controle negativo ──────────────────────────────────────────────────────
  it('NÃO marca módulo que tem DataController', () => {
    render(<ModulesIndex modules={[modulo({ has_datacontroller: true })]} />);

    expect(screen.queryByText('sem menu')).toBeNull();
  });

  it('NÃO marca módulo INATIVO sem DataController — inativo já não monta menu, o aviso seria ruído', () => {
    render(<ModulesIndex modules={[modulo({ active: false, has_datacontroller: false })]} />);

    expect(screen.queryByText('sem menu')).toBeNull();
  });
});
