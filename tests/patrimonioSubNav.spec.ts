// Contrato da barra de abas do Patrimônio — o artefato que as telas 08–12 herdam.
//
// Por que existe: a thread 07 do playbook SINCRONIZAR Patrimônio cria este `_shared` como
// primeira tela da frente, e errar nele custa seis telas, não uma. As três regressões que
// este teste defende são as que o protótipo torna FÁCEIS de cometer:
//
//   1. inventar rota pra Garantias/Auditoria — as duas abas existem no protótipo
//      (`patrimonio-page.jsx:835`) e NÃO existem no backend (nenhuma rota em
//      `Modules/AssetManagement/Routes/web.php`). Um `href` plausível daria 404.
//   2. perder o gate multi-tenant — sem entry do módulo no `shell.menu` a barra inteira
//      não pode renderizar (ADR 0093; `DataController:109` é quem declara a entry, atrás
//      do pacote `assetmanagement_module` + permissões `asset.*`).
//   3. casar a entry errada — o `key` do ghost é contrato com a rota; rótulo de menu muda.
//
// UCs defendidos: UC-PAT-02 · UC-PAT-03 · UC-PAT-04 (resources/js/Pages/Patrimonio/Index.casos.md)

import { describe, it, expect } from 'vitest';
import {
  PATRIMONIO_SUBNAV_GHOSTS,
  PATRIMONIO_SEM_ROTA,
  pickPatrimonioEntry,
  type PatMenuEntry,
} from '@/Pages/Patrimonio/_shared/patrimonioMenu';

// Fixture = a entry como o DataController do AssetManagement a declara hoje
// (`Modules/AssetManagement/Http/Controllers/DataController.php:109-138`), lida do main.
const MENU_COM_PATRIMONIO: PatMenuEntry[] = [
  {
    label: 'Financeiro',
    group: 'financas',
    ghosts: [{ key: 'unificado', label: 'Financeiro', href: '/financeiro/unificado' }],
  },
  {
    label: 'Gestão de ativos',
    primary: { label: 'Novo ativo', href: '/asset/assets/create', shortcut: 'N' },
    ghosts: [
      { key: 'dashboard', label: 'Painel', href: '/asset/dashboard' },
      { key: 'assets', label: 'Ativos', href: '/asset/assets' },
      { key: 'allocation', label: 'Alocações', href: '/asset/allocation' },
      { key: 'revocation', label: 'Devoluções', href: '/asset/revocation' },
      { key: 'asset-maintenance', label: 'Manutenção', href: '/asset/asset-maintenance' },
      { key: 'settings', label: 'Configurações', href: '/asset/settings' },
    ],
  },
];

describe('UC-PAT-03 · gate multi-tenant da barra de abas', () => {
  it('acha a entry do Patrimônio pelo ghost `dashboard` sob /asset/', () => {
    const entry = pickPatrimonioEntry(MENU_COM_PATRIMONIO);
    expect(entry?.label).toBe('Gestão de ativos');
    expect(entry?.primary?.href).toBe('/asset/assets/create');
  });

  it('sem entry do módulo no shell.menu, devolve undefined (a barra não renderiza)', () => {
    // É o caso do business sem o pacote `assetmanagement_module`, ou do usuário sem
    // nenhuma permission `asset.*`: o DataController não declara a entry.
    const semPatrimonio = MENU_COM_PATRIMONIO.filter((m) => m.label !== 'Gestão de ativos');
    expect(pickPatrimonioEntry(semPatrimonio)).toBeUndefined();
    expect(pickPatrimonioEntry(undefined)).toBeUndefined();
    expect(pickPatrimonioEntry([])).toBeUndefined();
  });

  it('não casa um ghost `dashboard` de OUTRO módulo', () => {
    const outroModulo: PatMenuEntry[] = [
      { label: 'Governança', ghosts: [{ key: 'dashboard', label: 'Painel', href: '/governance/dashboard' }] },
    ];
    expect(pickPatrimonioEntry(outroModulo)).toBeUndefined();
  });
});

describe('UC-PAT-04 · abas sem rota não viram link', () => {
  it('Garantias e Auditoria NÃO estão entre os ghosts navegáveis', () => {
    const keys = PATRIMONIO_SUBNAV_GHOSTS.map((g) => g.key);
    expect(keys).not.toContain('garantias');
    expect(keys).not.toContain('auditoria');
  });

  it('Garantias e Auditoria existem como itens inertes, com `title` explicando', () => {
    const keys = PATRIMONIO_SEM_ROTA.map((i) => i.key);
    expect(keys).toEqual(['garantias', 'auditoria']);
    for (const item of PATRIMONIO_SEM_ROTA) {
      // `title` não-vazio: o usuário precisa saber POR QUE o item não leva a lugar nenhum.
      expect(item.title && item.title.length).toBeGreaterThan(20);
      // `PageHeaderOverflowItem` não tem `href` — é o que o torna inerte por construção.
      expect(item).not.toHaveProperty('href');
    }
  });
});

describe('UC-PAT-02 · toda aba navegável aponta pra rota que existe', () => {
  // Espelha `Modules/AssetManagement/Routes/web.php` (prefixo `asset`), lido do main:
  // 6 Route::resource + o GET dashboard. `install` fica de fora — não é aba.
  const ROTAS_REAIS = new Set([
    '/asset/dashboard',
    '/asset/assets',
    '/asset/allocation',
    '/asset/revocation',
    '/asset/asset-maintenance',
    '/asset/settings',
  ]);

  it('nenhum href foi inventado', () => {
    for (const ghost of PATRIMONIO_SUBNAV_GHOSTS) {
      expect(ROTAS_REAIS.has(ghost.href), `href inexistente: ${ghost.href} (${ghost.key})`).toBe(true);
    }
  });

  it('as 5 abas do protótipo vêm primeiro, na ordem dele', () => {
    // `maxVisible={5}` no componente: estas 5 aparecem inline, o resto vai pro `⋯`.
    expect(PATRIMONIO_SUBNAV_GHOSTS.slice(0, 5).map((g) => g.label)).toEqual([
      'Painel', 'Bens', 'Alocações', 'Manutenções', 'Configurações',
    ]);
  });

  it('a entidade se chama "Bens" — o canon de tradução (lang.php `assets`), não "Ativos"', () => {
    expect(PATRIMONIO_SUBNAV_GHOSTS.find((g) => g.key === 'assets')?.label).toBe('Bens');
  });

  it('keys são únicas — o PageHeaderTabs resolve a aba ativa por key', () => {
    const keys = PATRIMONIO_SUBNAV_GHOSTS.map((g) => g.key);
    expect(new Set(keys).size).toBe(keys.length);
  });
});
