// Rail, lentes e sumário de /documentacao (US-DOC-001, onda 2). Mede o DOM que os componentes
// RENDERIZAM a partir do payload do controller — não o texto dos arquivos (LC-11).
//
// Inertia é mockado: `Link` vira <a> com os mesmos atributos e `router.get` é espião.
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, cleanup, fireEvent } from '@testing-library/react';

const get = vi.fn();
vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children, as: _as, ...rest }: Record<string, unknown>) => (
    <a href={href as string} {...(rest as object)}>{children as never}</a>
  ),
  router: { get: (...a: unknown[]) => get(...a) },
}));

import DocRail from '@/Pages/Documentacao/_components/DocRail';
import LenteBar from '@/Pages/Documentacao/_components/LenteBar';
import DocSumario from '@/Pages/Documentacao/_components/DocSumario';
import type { Navegacao } from '@/Pages/Documentacao/_components/tipos';

const item = (id: string, grupo: string, ordinal: number) => ({
  id, grupo, titulo: `T ${id}`, rotulo: `R ${id}`, descricao: null, ordinal,
});

// Dois grupos: o ordinal CONTINUA no segundo grupo (3, não 1) — é o servidor que numera.
const nav: Navegacao = {
  grupos: [
    { id: 'start', titulo: 'Comece aqui', itens: [item('reference-a', 'start', 1), item('reference-b', 'start', 2)] },
    { id: 'dominio', titulo: 'Domínio', itens: [item('reference-c', 'dominio', 3)] },
  ],
  linear: [],
  lente: null,
  lentes: { operar: 'Operar', construir: 'Construir' },
};

afterEach(() => { cleanup(); get.mockReset(); });

describe('DocRail', () => {
  it('mostra o ordinal que vem do servidor, contínuo entre grupos', () => {
    const { container } = render(<DocRail nav={nav} atual={null} escopoProsa="x e y" />);
    const ordinais = [...container.querySelectorAll('.doc-nav i')].map((i) => i.textContent);
    expect(ordinais).toEqual(['01', '02', '03']);
  });

  it('na capa (atual null) só "Comece aqui" vem ativo', () => {
    const { container } = render(<DocRail nav={nav} atual={null} escopoProsa="x e y" />);
    const ativos = [...container.querySelectorAll('[aria-current="page"]')].map((a) => a.getAttribute('href'));
    expect(ativos).toEqual(['/documentacao']);
  });

  it('num documento, só o item dele vem ativo', () => {
    const { container } = render(<DocRail nav={nav} atual="reference-c" escopoProsa="x e y" />);
    const ativos = [...container.querySelectorAll('[aria-current="page"]')].map((a) => a.getAttribute('href'));
    expect(ativos).toEqual(['/documentacao/reference-c']);
  });

  it('a busca vai pra rota de busca com o termo', () => {
    const { container } = render(<DocRail nav={nav} atual={null} escopoProsa="x e y" />);
    const input = container.querySelector('input[type="search"]') as HTMLInputElement;
    expect(input.getAttribute('aria-label')).toBe('Buscar em x e y');
    fireEvent.change(input, { target: { value: 'MCP' } });
    fireEvent.submit(input.closest('form') as HTMLFormElement);
    expect(get).toHaveBeenCalledWith('/documentacao/buscar', { q: 'MCP' });
  });
});

describe('LenteBar', () => {
  it('"Tudo" manda lente vazia (apaga a preferência) e vem ativo sem lente', () => {
    const { container } = render(<LenteBar nav={nav} path="/documentacao" />);
    const abas = [...container.querySelectorAll('a')];
    expect(abas.map((a) => a.getAttribute('href'))).toEqual([
      '/documentacao?lente=', '/documentacao?lente=operar', '/documentacao?lente=construir',
    ]);
    expect(abas.map((a) => a.getAttribute('aria-current'))).toEqual(['true', null, null]);
  });

  it('marca a lente que o servidor devolveu', () => {
    const { container } = render(<LenteBar nav={{ ...nav, lente: 'operar' }} path="/documentacao" />);
    const ativa = container.querySelector('[aria-current="true"]');
    expect(ativa?.textContent).toBe('Operar');
  });
});

describe('DocSumario', () => {
  it('lista os itens na ordem do servidor, com âncora no id', () => {
    const sumario = [
      { id: 'a1', nivel: 2, codigo: 'A1', rotulo: 'Primeiro' },
      { id: 'b8-1', nivel: 4, codigo: 'B8.1', rotulo: 'Sub' },
    ];
    const { container } = render(<DocSumario sumario={sumario} />);
    expect([...container.querySelectorAll('.doc-toc a')].map((a) => a.getAttribute('href'))).toEqual(['#a1', '#b8-1']);
  });

  it('sem títulos não mostra o bloco "Nesta página"', () => {
    const { container } = render(<DocSumario sumario={[]} />);
    expect(container.querySelector('.doc-toc')).toBeNull();
  });
});
