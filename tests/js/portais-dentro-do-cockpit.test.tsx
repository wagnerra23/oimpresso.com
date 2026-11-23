// Portais dentro do `.cockpit` — diálogo e drawer que consomem tokens do Design System.
//
// ── POR QUE ESTE ARQUIVO EXISTE ──────────────────────────────────────────────
// Diálogo, alerta e drawer são portais. O Radix monta o conteúdo no <body>, fora do `.cockpit`,
// e lá `--bg`, `--text`, `--text-mute`, `--warn` chegam vazios. Medido em produção em
// 2026-10-19 (lápide §5 2026-10-19, emenda da 2026-07-10):
//   - editor de posto (Produção): a ajuda `.pcv-help` saía branca, sem o cinza esmaecido;
//   - lista de produtos: o aviso da exclusão (`text-[var(--warn)]`) saía branco, sem o laranja.
// O conserto é a prop `container` apontando para o `.cockpit` — a mesma do `ui/sheet`, agora
// também no `ui/dialog` e no `ui/alert-dialog`.
// A janela de lançar item da Venda V3 tinha o mesmo defeito e ficou de fora daquele conserto. Medido
// em produção em 2026-11-03 (empresa 1, escuro): `background-color: rgba(0, 0, 0, 0)` — o
// `.sells-cowork { background: var(--bg) }` fora de @layer vence o `bg-background`, e `--bg` é vazio
// no <body>. Os 9 portais da V3 passaram a montar no `.cockpit` pelo hook `useCockpit`
// (`Sells/_components/v3/cockpit-portal.ts`); os casos abaixo cobrem dois deles.
// A lista de produtos tinha o mesmo defeito no que NÃO era diálogo. Medido em produção em
// 2026-11-23 (empresa 1, escuro, 1440px, contra `produto-blade.jsx`): o saldo por variação, o menu
// Ações e a janela de cadastro rápido saíam com a fonte do sistema (`ui-sans-serif`) e com `--text`,
// `--text-mute`, `--border` e `--surface` vazios. Por isso o `ui/popover`, o `ui/dropdown-menu` e o
// `ui/select` também ganharam `container`, e todas as peças flutuantes da lista passaram a recebê-lo.
//
// ── O QUE ELE MEDE (componentes REAIS) ───────────────────────────────────────
// Onde o conteúdo fica na árvore, que é o que decide se os tokens chegam. Cada consumidor tem
// um CONTROLE: sem `.cockpit` na página o conteúdo cai no <body>. Se o harness não distinguisse
// os dois lugares, os dois casos sairiam iguais e o 2º não provaria nada.
//
// ── O QUE **NÃO** PROVA (resíduo declarado) ──────────────────────────────────
//   - Nada sobre cor, fundo ou fonte: o jsdom não resolve `var(--…)` de folha de estilo. Os
//     valores foram medidos no navegador em produção e se medem de novo no smoke pós-deploy.
//   - Os diálogos que vivem no próprio `Produto/Index.tsx` (excluir, confirmar, WooCommerce) e o
//     seletor de linhas por página não são renderizados aqui: a página inteira não monta em jsdom
//     sem o shell. Eles usam o mesmo `container` que os casos abaixo provam; o efeito deles se
//     confere no smoke. Os filtros, o menu de colunas, o relatório e o estoque inicial recebem o
//     `container` pelo mesmo caminho do cadastro rápido, que está coberto.
//
// Comando local (vitest, fora do CT 100 — ADR 0062 cobre Pest/PHPStan):
//   npx vitest run tests/js/portais-dentro-do-cockpit.test.tsx

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, cleanup, fireEvent, screen } from '@testing-library/react';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/Components/ui/dialog';
import { AlertDialog, AlertDialogContent, AlertDialogDescription, AlertDialogTitle } from '@/Components/ui/alert-dialog';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/Components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import AcoesProduto from '@/Pages/Produto/_components/lista/AcoesProduto';
import PopoverAncorado from '@/Pages/Produto/_components/lista/PopoverAncorado';
import CadastroRapidoProduto from '@/Pages/Produto/_components/lista/CadastroRapido';
import type { LinhaProduto, PermissoesLista } from '@/Pages/Produto/_components/lista/tipos';

vi.mock('@inertiajs/react', () => ({
  router: { put: vi.fn() },
  useForm: (inicial: Record<string, unknown>) => ({
    data: inicial,
    setData: vi.fn(),
    errors: {},
    processing: false,
    transform: vi.fn(),
    put: vi.fn(),
    post: vi.fn(),
  }),
}));

// Import depois do mock: o EditorPosto usa `useForm` do Inertia.
const { default: EditorPosto } = await import('../../Modules/Producao/Resources/js/Pages/Producao/Postos/_components/EditorPosto');

const { LancarItem } = await import('@/Pages/Sells/_components/v3/LancarItem');
const { default: ColunasModal } = await import('@/Pages/Sells/_components/v3/ColunasModal');

const conteudo = (sel: string) => document.querySelector<HTMLElement>(sel);

let avulso: HTMLElement | null = null;
const cockpitAvulso = () => {
  avulso = document.createElement('div');
  avulso.className = 'cockpit';
  document.body.appendChild(avulso);
  return avulso;
};

afterEach(() => {
  cleanup();
  avulso?.remove();
  avulso = null;
});

describe('ui/dialog e ui/alert-dialog aceitam `container`', () => {
  it('controle: sem container, o diálogo cai no body', () => {
    render(
      <Dialog open>
        <DialogContent>
          <DialogTitle>t</DialogTitle>
          <DialogDescription>d</DialogDescription>
        </DialogContent>
      </Dialog>,
    );
    expect(conteudo('[data-slot="dialog-content"]')).not.toBeNull();
    expect(conteudo('[data-slot="dialog-content"]')!.closest('.cockpit')).toBeNull();
  });

  it('com container, o diálogo monta dentro dele', () => {
    const ck = cockpitAvulso();
    render(
      <Dialog open>
        <DialogContent container={ck}>
          <DialogTitle>t</DialogTitle>
          <DialogDescription>d</DialogDescription>
        </DialogContent>
      </Dialog>,
    );
    expect(conteudo('[data-slot="dialog-content"]')!.closest('.cockpit')).toBe(ck);
  });

  it('controle: sem container, o alerta cai no body', () => {
    render(
      <AlertDialog open>
        <AlertDialogContent>
          <AlertDialogTitle>t</AlertDialogTitle>
          <AlertDialogDescription>d</AlertDialogDescription>
        </AlertDialogContent>
      </AlertDialog>,
    );
    expect(conteudo('[data-slot="alert-dialog-content"]')).not.toBeNull();
    expect(conteudo('[data-slot="alert-dialog-content"]')!.closest('.cockpit')).toBeNull();
  });

  it('com container, o alerta monta dentro dele', () => {
    const ck = cockpitAvulso();
    render(
      <AlertDialog open>
        <AlertDialogContent container={ck}>
          <AlertDialogTitle>t</AlertDialogTitle>
          <AlertDialogDescription>d</AlertDialogDescription>
        </AlertDialogContent>
      </AlertDialog>,
    );
    expect(conteudo('[data-slot="alert-dialog-content"]')!.closest('.cockpit')).toBe(ck);
  });
});

describe('Produção · o editor de posto monta dentro do .cockpit', () => {
  const editor = () => <EditorPosto posto={null} veiculo={false} onClose={() => {}} />;
  const drawer = () => conteudo('[data-contract="producao-posto-editor"]');

  it('controle: sem .cockpit na página, o drawer cai no body', () => {
    render(editor());
    expect(drawer()).not.toBeNull();
    expect(drawer()!.closest('.cockpit')).toBeNull();
  });

  it('com .cockpit na página, o drawer monta dentro dele', () => {
    render(<div className="cockpit">{editor()}</div>);
    expect(drawer()!.closest('.cockpit')).not.toBeNull();
  });
});

describe('Venda V3 · os diálogos montam dentro do .cockpit', () => {
  const produto = {
    sku: 'P-1', nome: 'Martelo', un: 'UNID', preco: 157, estoque: 0,
    tipo: 'produto' as const, ean: null, fabrica: null, categoria: null,
  };
  const lancar = () => (
    <LancarItem produto={produto} executantes={[]} podeEditarPreco onFechar={() => {}} onConfirmar={() => {}} />
  );
  const colunas = () => <ColunasModal aberto onFechar={() => {}} ativas={[]} onAtivasChange={() => {}} />;
  const dialogo = () => conteudo('[data-slot="dialog-content"]');

  it('controle: sem .cockpit na página, o lançar item cai no body', () => {
    render(lancar());
    expect(dialogo()).not.toBeNull();
    expect(dialogo()!.closest('.cockpit')).toBeNull();
  });

  it('com .cockpit na página, o lançar item monta dentro dele', () => {
    render(<div className="cockpit">{lancar()}</div>);
    expect(dialogo()!.closest('.cockpit')).not.toBeNull();
  });

  it('com .cockpit na página, o diálogo de colunas monta dentro dele', () => {
    render(<div className="cockpit">{colunas()}</div>);
    expect(dialogo()!.closest('.cockpit')).not.toBeNull();
  });
});

describe('ui/popover, ui/dropdown-menu e ui/select aceitam `container`', () => {
  const pop = (container?: HTMLElement) => (
    <Popover open>
      <PopoverTrigger>abrir</PopoverTrigger>
      <PopoverContent container={container}>painel</PopoverContent>
    </Popover>
  );
  const menu = (container?: HTMLElement) => (
    <DropdownMenu open>
      <DropdownMenuTrigger>abrir</DropdownMenuTrigger>
      <DropdownMenuContent container={container}>
        <DropdownMenuItem>item</DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
  const lista = (container?: HTMLElement) => (
    <Select open value="a">
      <SelectTrigger aria-label="escolha">
        <SelectValue />
      </SelectTrigger>
      <SelectContent container={container}>
        <SelectItem value="a">A</SelectItem>
      </SelectContent>
    </Select>
  );

  it.each([
    ['popover', pop, 'popover-content'],
    ['menu', menu, 'dropdown-menu-content'],
    ['seletor', lista, 'select-content'],
  ] as const)('controle: sem container, o %s cai no body', (_nome, peca, slot) => {
    render(peca());
    expect(conteudo(`[data-slot="${slot}"]`)).not.toBeNull();
    expect(conteudo(`[data-slot="${slot}"]`)!.closest('.cockpit')).toBeNull();
  });

  it.each([
    ['popover', pop, 'popover-content'],
    ['menu', menu, 'dropdown-menu-content'],
    ['seletor', lista, 'select-content'],
  ] as const)('com container, o %s monta dentro dele', (_nome, peca, slot) => {
    const ck = cockpitAvulso();
    render(peca(ck));
    expect(conteudo(`[data-slot="${slot}"]`)!.closest('.cockpit')).toBe(ck);
  });
});

describe('Lista de produtos · as peças flutuantes montam dentro do .cockpit', () => {
  const LINHA: LinhaProduto = {
    id: 7, nome: 'Camiseta', sku: 'CAM-7', tipo: 'single', categoria: null, subcategoria: null, unidade: 'Unidade', unidade_curta: 'Un',
    marca: null, imposto: null, imagem: null, controla_estoque: true, estoque: 5, alerta: null, abaixo_do_alerta: false,
    inativo: false, nao_para_venda: false, serie: false, locais: ['Matriz'], variacoes: 1, campos: {}, anexo: null,
  };
  const TUDO: PermissoesLista = {
    create: true, update: true, delete: true, opening_stock: true, view: true, stock_report: true,
    etiquetas: true, view_purchase_price: true, access_default_selling_price: true,
  };
  const abrirAcoes = (container?: HTMLElement) => {
    render(<AcoesProduto linha={LINHA} permissoes={TUDO} gruposDePreco={1} emiteNota aoEscolher={() => {}} container={container} />);
    fireEvent.keyDown(screen.getByRole('button', { name: 'Ações do produto Camiseta' }), { key: 'Enter' });
    return conteudo('[data-slot="dropdown-menu-content"]');
  };
  const abrirSaldo = (container?: HTMLElement) => {
    render(
      <PopoverAncorado rotulo="Saldo por variação de Camiseta" conteudo={<p>saldo</p>} container={container}>
        5 Un
      </PopoverAncorado>,
    );
    fireEvent.click(screen.getByRole('button', { name: 'Saldo por variação de Camiseta' }));
    return conteudo('[data-slot="popover-content"]');
  };
  const abrirCadastro = (container?: HTMLElement) => {
    render(
      <CadastroRapidoProduto
        aberto
        aoFechar={() => {}}
        unidades={[{ id: '3', nome: 'Unidade (Un)' }]}
        categorias={[]}
        locais={[{ id: '1', nome: 'Matriz' }]}
        aoCriar={() => {}}
        container={container}
      />,
    );
    return conteudo('[data-slot="dialog-content"]');
  };

  it.each([
    ['menu Ações', abrirAcoes],
    ['saldo por variação (UC-PIDX-37)', abrirSaldo],
    ['cadastro rápido (UC-PIDX-14)', abrirCadastro],
  ] as const)('controle: sem container, o %s cai no body', (_nome, abrir) => {
    const peca = abrir();
    expect(peca).not.toBeNull();
    expect(peca!.closest('.cockpit')).toBeNull();
  });

  it.each([
    ['menu Ações', abrirAcoes],
    ['saldo por variação (UC-PIDX-37)', abrirSaldo],
    ['cadastro rápido (UC-PIDX-14)', abrirCadastro],
  ] as const)('com o .cockpit da página, o %s monta dentro dele', (_nome, abrir) => {
    const ck = cockpitAvulso();
    expect(abrir(ck)!.closest('.cockpit')).toBe(ck);
  });
});
