/**
 * CRM — a lista do módulo (sidebar + abas do topo) segue o protótipo.
 *
 * [W] 2026-11-06: sidebar e abas mostram a MESMA lista, os ghosts do DataController. Fonte da ordem e
 * dos rótulos: `prototipo-ui/cowork/Wagner/crm-blade.jsx` (`ABAS` + `TITULOS`, barra "Telas do módulo
 * CRM"). O teste LÊ as duas pontas, para não virar um terceiro dono da lista.
 *
 * Diferenças declaradas, não escondidas:
 *  - "Funil" existe só em produção (US-CRM-092, ADR 0441) e fica depois do Painel.
 *  - Cinco abas do protótipo ainda não estão no servidor (Comissões, Modelo de proposta, Marketplace
 *    B2B, Pedido de ordem, Fontes e estágios): entram num PR seguinte, cada uma com a permissão da rota.
 */
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const raiz = resolve(__dirname, '../..');
const ler = (p: string) => readFileSync(resolve(raiz, p), 'utf8');
const SO_NO_SERVIDOR = ['Funil'];
const AINDA_FORA = ['Comissões', 'Modelo de proposta', 'Marketplace B2B', 'Pedido de ordem', 'Fontes e estágios'];

function abasDoPrototipo(): string[] {
  const src = ler('prototipo-ui/cowork/Wagner/crm-blade.jsx');
  const titulos = src.slice(src.indexOf('const TITULOS = {'), src.indexOf('};', src.indexOf('const TITULOS = {')));
  const mapa = Object.fromEntries([...titulos.matchAll(/(\w+):\s*"([^"]+)"/g)].map((m) => [m[1], m[2]]));
  const abas = src.slice(src.indexOf('const ABAS = ['), src.indexOf('];', src.indexOf('const ABAS = [')));
  return [...abas.matchAll(/"(\w+)"/g)].map((m) => mapa[m[1]]);
}

function ghostsDoServidor(): string[] {
  const src = ler('Modules/Crm/Http/Controllers/DataController.php');
  const ini = src.indexOf("'ghosts'   => array_values(array_filter([");
  const bloco = src.slice(ini, src.indexOf('])),', ini));
  return [...bloco.matchAll(/'label' => '([^']+)'/g)].map((m) => m[1]);
}

describe('CRM · lista do módulo igual ao protótipo', () => {
  it('controle: as duas leituras acharam o que deviam', () => {
    expect(abasDoPrototipo()).toHaveLength(14);
    expect(abasDoPrototipo()).not.toContain(undefined);
    const g = ghostsDoServidor();
    expect(g[0]).toBe('Painel');
    expect(g.at(-1)).toBe('Configurações');
  });

  it('mesmos rótulos e mesma ordem do protótipo, tirando o que está declarado acima', () => {
    const esperado = abasDoPrototipo().filter((l) => !AINDA_FORA.includes(l));
    expect(ghostsDoServidor().filter((l) => !SO_NO_SERVIDOR.includes(l))).toEqual(esperado);
  });

  it('o Funil, que só existe em produção, vem logo depois do Painel', () => {
    const g = ghostsDoServidor();
    expect(g.indexOf('Funil')).toBe(1);
  });
});
