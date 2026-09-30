/**
 * `shared/DataTable` — a mensagem de lista vazia fica na área VISÍVEL da rolagem.
 *
 * O defeito, medido em produção (Patrimonio/Bens, 2026-09-30, janela 1280): a célula vazia tem a
 * largura da TABELA (1626px) e centralizava o texto nela, então o texto ficava em x=842..1306 com
 * o wrapper visível em 260..1024 — cortado à direita. Com `sticky left-0` + `w-[100cqw]` e o
 * wrapper como container (`containerType: inline-size`), o mesmo texto ficou em 410..874, centro
 * 642 = centro da área visível, e não saiu do lugar com a tabela rolada 400px.
 *
 * O vitest roda em jsdom com `css: false`: aqui se prova a CLASSE e o `style` que carregam esse
 * comportamento; o layout computado está no parágrafo acima e no corpo do PR.
 */
import { describe, expect, it, vi } from 'vitest';
import { render } from '@testing-library/react';
import DataTable, { type PaginatorShape } from '@/Components/shared/DataTable';

vi.mock('@inertiajs/react', () => ({ router: { get: vi.fn(), visit: vi.fn() } }));

type Linha = { id: number; nome: string };
const VAZIO: PaginatorShape<Linha> = { data: [], total: 0, current_page: 1, last_page: 1, from: null, to: null, links: [] };
const COLUNAS: any = [
  { accessorKey: 'nome', header: 'Nome', meta: { width: 900 } },
  { id: 'extra', header: 'Extra', meta: { width: 900 } },
];

function renderizar(extra: Record<string, unknown> = {}) {
  const { container } = render(
    <DataTable<Linha> columns={COLUNAS} data={[]} pagination={VAZIO} endpoint="/x" caption="t" emptyMessage="Nada aqui" {...extra} />,
  );
  const vazio = container.querySelector('[data-slot="datatable-vazio"]') as HTMLElement | null;
  const wrapper = container.querySelector('table')?.parentElement as HTMLElement | null;
  return { vazio, wrapper };
}

describe('DataTable · mensagem de lista vazia na área visível', () => {
  it('a mensagem mora num bloco sticky à esquerda, com a largura visível do container', () => {
    const { vazio } = renderizar();
    expect(vazio, 'bloco da mensagem vazia').not.toBeNull();
    expect(vazio!.textContent).toBe('Nada aqui');
    const cls = vazio!.className.split(/\s+/);
    expect(cls).toEqual(expect.arrayContaining(['sticky', 'left-0', 'w-[100cqw]', 'text-center']));
  });

  it('o wrapper da rolagem é o container de `cqw` — também quando as classes dele são substituídas', () => {
    expect(renderizar().wrapper?.style.containerType).toBe('inline-size');
    expect(renderizar({ tableWrapperClassName: 'arq-lista' }).wrapper?.style.containerType).toBe('inline-size');
  });
});
