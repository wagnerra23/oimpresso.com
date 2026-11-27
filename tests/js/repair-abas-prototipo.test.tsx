/**
 * Repair — a lista do módulo (sidebar + abas do topo) segue o protótipo.
 *
 * [W] 2026-11-06: sidebar e abas mostram a MESMA lista, os ghosts do DataController. A fonte da
 * ordem e dos rótulos é o protótipo (`prototipo-ui/cowork/Wagner/repair-page.jsx`, barra `MP.Tabs`
 * "Áreas da assistência técnica"). O teste LÊ as duas pontas, como o `ponto-subnav-abas.test.tsx`,
 * para não virar um terceiro dono da lista.
 *
 * "Portal do cliente" não passa pelo `$aba(...)`: é montada por `abaDoPortal()`, com o link público do
 * ConsultaOs da empresa (D-PORTAL, [W] 2026-10-02). O último teste confere que ela está na posição do
 * protótipo, entre Modelos e Configurações.
 */
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const raiz = resolve(__dirname, '../..');
const ler = (p: string) => readFileSync(resolve(raiz, p), 'utf8');
const FORA_DO_SERVIDOR = ['Portal do cliente'];

function blocoDasAbas(): string {
  const src = ler('Modules/Repair/Http/Controllers/DataController.php');
  const ini = src.indexOf('return array_values(array_filter([', src.indexOf('function abasDoRepair'));
  return src.slice(ini, src.indexOf(']));', ini));
}

function abasDoPrototipo(): string[] {
  const src = ler('prototipo-ui/cowork/Wagner/repair-page.jsx');
  const ini = src.indexOf('aria="Áreas da assistência técnica"');
  const bloco = src.slice(ini, src.indexOf(']}', ini));
  return [...bloco.matchAll(/key:\s*"[^"]+",\s*label:\s*"([^"]+)"/g)].map((m) => m[1]);
}

/** Lê as abas de `DataController::abasDoRepair()` — as linhas `$aba(key, label, href[, tela])`. */
function ghostsDoServidor(): Array<{ label: string; href: string }> {
  const bloco = blocoDasAbas();
  return [...bloco.matchAll(/\$aba\('[^']+', '([^']+)', '([^']+)'/g)].map((m) => ({ label: m[1], href: m[2] }));
}

describe('Repair · lista do módulo igual ao protótipo', () => {
  it('controle: as duas leituras acharam o que deviam', () => {
    expect(abasDoPrototipo()).toHaveLength(8);
    const g = ghostsDoServidor();
    expect(g[0]?.label).toBe('Painel');
    expect(g.at(-1)?.label).toBe('Configurações');
  });

  it('mesma ordem e mesmo rótulo do protótipo, menos o que ainda não tem tela', () => {
    const esperado = abasDoPrototipo().filter((l) => !FORA_DO_SERVIDOR.includes(l));
    expect(ghostsDoServidor().map((g) => g.label)).toEqual(esperado);
  });

  it('cada aba aponta para uma rota do módulo', () => {
    for (const g of ghostsDoServidor()) expect(g.href).toMatch(/^\/repair\//);
  });

  it('Portal do cliente fica onde o protótipo põe: entre Modelos e Configurações', () => {
    const bloco = blocoDasAbas();
    const modelos = bloco.indexOf("'Modelos'");
    const portal = bloco.indexOf('$this->abaDoPortal(');
    const config = bloco.indexOf("'Configurações'");
    expect(modelos).toBeGreaterThan(-1);
    expect(portal).toBeGreaterThan(modelos);
    expect(config).toBeGreaterThan(portal);
    expect(abasDoPrototipo().slice(-2)).toEqual(['Portal do cliente', 'Configurações']);
  });
});
