// Atalhos de teclado do sidebar (`G X`) — ADR 0180 Fase 8.
//
// O que este teste protege: o backend declara `shortcut` desde a Fase 4 e
// NADA valida unicidade global (o validador de `App\Sidebar\SidebarMenuItem`
// é por item). Medido em 2026-08-28 no `origin/main`: 14 atalhos declarados,
// com DUAS colisões reais — `G C` (Financeiro × Crm) e `G F` (Forja ×
// Financeiro). A regra de segurança é que sequência em conflito não navega e
// não vira dica; se alguém trocar isso por "primeiro ganha", estes casos caem.
//
// Cobre: resources/js/Components/cockpit/useSidebarShortcut.ts

import { describe, it, expect } from 'vitest';

import {
  construirIndiceAtalhos,
  normalizarSequencia,
} from '@/Components/cockpit/useSidebarShortcut';
import type { ShellMenuItem } from '@/Components/cockpit/shared';

const item = (label: string, href: string, shortcut?: string): ShellMenuItem =>
  ({ label, href, ...(shortcut ? { shortcut } : {}) });

describe('normalizarSequencia', () => {
  it('aceita as duas formas que o backend valida', () => {
    expect(normalizarSequencia('G S')).toBe('S');
    expect(normalizarSequencia('G C D')).toBe('C D');
  });

  it('normaliza caixa e espaço extra', () => {
    expect(normalizarSequencia('  g   s  ')).toBe('S');
  });

  it('recusa o que não é sequência G+letras', () => {
    // `N` é o formato do shortcut de PRIMARY action (SidebarPrimaryAction),
    // que valida /^[A-Z]( [A-Z])*$/ — não é atalho de navegação do menu.
    expect(normalizarSequencia('N')).toBeNull();
    expect(normalizarSequencia('G')).toBeNull();
    expect(normalizarSequencia('G 1')).toBeNull();
    expect(normalizarSequencia('X S')).toBeNull();
    expect(normalizarSequencia(undefined)).toBeNull();
  });
});

describe('construirIndiceAtalhos', () => {
  it('liga o atalho declarado ao href do item', () => {
    const i = construirIndiceAtalhos([item('Compras', '/compras', 'G S')]);
    expect(i.porSequencia.get('S')).toBe('/compras');
    expect(i.usaveis.has('G S')).toBe(true);
    expect(i.ambiguas).toEqual([]);
  });

  it('DESCARTA os dois lados quando dois items declaram a mesma sequência', () => {
    // Reproduz a colisão real `G C` (Financeiro × Crm) medida no main.
    const i = construirIndiceAtalhos([
      item('Cobrança', '/financeiro/cobranca', 'G C'),
      item('Clientes', '/cliente', 'G C'),
    ]);
    expect(i.porSequencia.has('C')).toBe(false);
    expect(i.usaveis.has('G C')).toBe(false);
    expect(i.ambiguas).toContain('C');
  });

  it('não descarta quando os dois items são o MESMO destino', () => {
    const i = construirIndiceAtalhos([
      item('Vendas', '/sells', 'G V'),
      item('Vendas', '/sells', 'G V'),
    ]);
    expect(i.porSequencia.get('V')).toBe('/sells');
    expect(i.ambiguas).toEqual([]);
  });

  it('DESCARTA quando uma sequência é prefixo de outra', () => {
    // `C` dispararia antes de dar tempo de digitar o `D` de `C D`.
    const i = construirIndiceAtalhos([
      item('Cobrança', '/cobranca', 'G C'),
      item('Cadastro', '/cadastro', 'G C D'),
    ]);
    expect(i.porSequencia.size).toBe(0);
    expect(i.ambiguas).toEqual(['C', 'C D']);
  });

  it('ignora item sem href utilizável', () => {
    const i = construirIndiceAtalhos([
      item('Grupo sem destino', '#', 'G G'),
      { label: 'Sem href', shortcut: 'G H' } as ShellMenuItem,
    ]);
    expect(i.porSequencia.size).toBe(0);
    expect(i.ambiguas).toEqual([]);
  });

  it('percorre children', () => {
    const i = construirIndiceAtalhos([
      { label: 'Pai', href: '/pai', children: [item('Filho', '/filho', 'G Y')] },
    ]);
    expect(i.porSequencia.get('Y')).toBe('/filho');
  });

  it('tolera menu vazio ou ausente', () => {
    expect(construirIndiceAtalhos([]).porSequencia.size).toBe(0);
    expect(construirIndiceAtalhos(undefined).porSequencia.size).toBe(0);
  });
});
