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
// A janela de lançar item da Venda V3 tem o mesmo defeito (fundo transparente) e ficou de fora
// deste conserto: a Venda é do Wagner por enquanto.
//
// ── O QUE ELE MEDE (componentes REAIS) ───────────────────────────────────────
// Onde o conteúdo fica na árvore, que é o que decide se os tokens chegam. Cada consumidor tem
// um CONTROLE: sem `.cockpit` na página o conteúdo cai no <body>. Se o harness não distinguisse
// os dois lugares, os dois casos sairiam iguais e o 2º não provaria nada.
//
// ── O QUE **NÃO** PROVA (resíduo declarado) ──────────────────────────────────
//   - Nada sobre cor, fundo ou fonte: o jsdom não resolve `var(--…)` de folha de estilo. Os
//     valores foram medidos no navegador em produção e se medem de novo no smoke pós-deploy.
//   - Os dois diálogos da lista de produtos (`Produto/Index.tsx`) não são renderizados aqui: a
//     página inteira não monta em jsdom sem o shell. Eles usam o mesmo `container` que os casos
//     abaixo provam; o efeito deles se confere no smoke.
//
// Comando local (vitest, fora do CT 100 — ADR 0062 cobre Pest/PHPStan):
//   npx vitest run tests/js/portais-dentro-do-cockpit.test.tsx

import { describe, it, expect, afterEach, vi } from 'vitest';
import { render, cleanup } from '@testing-library/react';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/Components/ui/dialog';
import { AlertDialog, AlertDialogContent, AlertDialogDescription, AlertDialogTitle } from '@/Components/ui/alert-dialog';

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
