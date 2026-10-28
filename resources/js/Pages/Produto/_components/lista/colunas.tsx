// Colunas da lista de produtos — as do protótipo (TODAS em produto-blade.jsx), com as larguras dele.
//
// Mora fora da Page para poder ser testada com a tabela de verdade
// (tests/js/produto-lista-ordenacao.test.tsx).
//
// ⚠️ Coluna ordenável PRECISA de `accessorFn`. A ordenação é do servidor (`sort` + `dir` em
// ProductController::buildProdutoListaPaginada), mas a tabela só desenha o botão do cabeçalho
// quando a coluna diz de onde vem o valor: `getCanSort()` do TanStack exige `accessorFn`, e só
// `enableSorting: true` não basta. Medido em produção em 2026-10-12: as 8 colunas tinham
// `enableSorting: true` sem `accessorFn`, o cabeçalho saía como texto puro e o clique não fazia
// nada — com typecheck, build e CI verdes. O `id` da coluna é a chave que o servidor aceita.
//
// Redimensionar: como no protótipo, toda coluna pode ser arrastada pela borda, menos Ação e
// Imagem (`resizable: false`) e a última da grade (regra da tabela, não daqui).
//
// Tela estreita: as larguras daqui são as do protótipo; `aplicarEncaixe` encolhe as que não cabem
// (regra em `encaixe.ts`, UC-PIDX-26). Por isso as células de texto levam `title`: cortadas com
// "…", mostram o texto inteiro ao passar o mouse.

import type { ColumnDef } from '@tanstack/react-table';
import type { ReactNode } from 'react';
import { Globe } from 'lucide-react';
import { Inline } from '@/Components/layout';
import { brl } from '../../Unificado/_components/catalogo';
import AcoesProduto, { type AcaoProduto } from './AcoesProduto';
import {
  TIPO_ROTULO,
  dataBr,
  estoqueTexto,
  faixaDePreco,
  subLinhaProduto,
  type ContextoLista,
  type FiltrosLista,
  type LinhaProduto,
  type PermissoesLista,
} from './tipos';

export interface OpcoesDasColunas {
  /** Colunas opcionais ligadas no menu "Colunas". */
  visiveis: Record<string, boolean>;
  densa: boolean;
  permissions: PermissoesLista;
  contexto: ContextoLista;
  aoEscolher: (acao: AcaoProduto, l: LinhaProduto) => void;
}

export function colunasDaLista({ visiveis, densa, permissions, contexto, aoEscolher }: OpcoesDasColunas): ColumnDef<LinhaProduto, unknown>[] {
  const v = (id: string) => visiveis[id] === true;
  const out: ColumnDef<LinhaProduto, unknown>[] = [
    {
      id: 'acao',
      header: 'Ação',
      enableSorting: false,
      meta: { width: 96, resizable: false },
      cell: ({ row }) => (
        <AcoesProduto linha={row.original} permissoes={permissions} gruposDePreco={contexto.grupos_de_preco} emiteNota={contexto.emite_nota} aoEscolher={aoEscolher} />
      ),
    },
  ];
  if (v('imagem')) {
    out.push({
      id: 'imagem',
      header: 'Imagem',
      enableSorting: false,
      meta: { width: 74, resizable: false },
      cell: ({ row }) =>
        row.original.imagem ? (
          <img src={row.original.imagem} alt="" className="pb-thumb object-cover" loading="lazy" />
        ) : (
          <div className="pb-thumb" aria-hidden>
            IMG
          </div>
        ),
    });
  }
  out.push({
    id: 'produto',
    header: 'Produto',
    enableSorting: true,
    accessorFn: (l) => l.nome,
    meta: { width: 300 },
    cell: ({ row }) => (
      <div className="min-w-0">
        <Inline gap={1} wrap={false} className="min-w-0">
          <span className="truncate font-semibold text-[var(--text)]" title={row.original.nome}>
            {row.original.nome}
          </span>
          {/* Marca de quem sincroniza com o WooCommerce, como a lista Blade (`fa-wordpress` sob o
              nome). O servidor só manda o campo quando o módulo está instalado. UC-PIDX-23. */}
          {/* UC-PIDX-31: natureza fiscal decidida pelo contador. Mercadoria não ganha selo (é o padrão). */}
          {row.original.natureza_fiscal === 'servico' && (
            <span className="shrink-0 text-[11px] text-[var(--text-mute)]" data-contract="produto-lista-natureza">
              Serviço
            </span>
          )}
          {row.original.natureza_fiscal === 'pendente' && (
            <span
              className="shrink-0 text-[11px] font-medium text-[var(--warn)]"
              title="O contador ainda não decidiu se é mercadoria ou serviço. A NF-e comum sai como mercadoria; a emissão separada (NF-e + NFS-e) fica bloqueada."
              data-contract="produto-lista-natureza"
            >
              Fiscal a decidir
            </span>
          )}
          {row.original.woocommerce_sync === true && (
            <Globe
              size={12}
              className="shrink-0 text-[var(--text-mute)]"
              role="img"
              aria-label="Sincroniza com o WooCommerce"
              data-contract="produto-lista-woocommerce"
            >
              <title>Sincroniza com o WooCommerce</title>
            </Globe>
          )}
        </Inline>
        {!densa && <div className="truncate text-[11px] text-[var(--text-mute)]">{subLinhaProduto(row.original)}</div>}
      </div>
    ),
  });
  if (v('locais')) {
    out.push({
      id: 'locais',
      header: 'Locais',
      enableSorting: false,
      meta: { width: 150 },
      cell: ({ row }) => comDica(row.original.locais.join(', ')),
    });
  }
  if (v('compra') && permissions.view_purchase_price === true) {
    out.push({
      id: 'compra',
      header: 'Preço de compra un.',
      enableSorting: true,
      accessorFn: (l) => l.compra_max ?? null,
      // Sem mono: no protótipo o preço é texto normal de 13px (medido com design-diff em 2026-10-11).
      meta: { width: 150, align: 'right' },
      cell: ({ row }) => preco(faixaDePreco(row.original.tipo, row.original.compra_min, row.original.compra_max)),
    });
  }
  if (v('venda') && permissions.access_default_selling_price === true) {
    out.push({
      id: 'venda',
      header: 'Preço de venda',
      enableSorting: true,
      accessorFn: (l) => l.venda_max ?? null,
      meta: { width: 150, align: 'right' },
      cell: ({ row }) => {
        const proposto = row.original.preco_proposto;
        if (!proposto) return preco(faixaDePreco(row.original.tipo, row.original.venda_min, row.original.venda_max));
        // UC-PIDX-28 (UC-FLX-17): a compra recebida propôs preço novo, que espera aprovação no
        // detalhe da compra. Só aviso: a lista não muda preço.
        const texto =
          proposto.quantidade === 1 && proposto.preco !== null
            ? `proposto ${brl(proposto.preco)} · aguardando`
            : `${proposto.quantidade} preços propostos · aguardando`;
        return (
          <div className="min-w-0">
            {preco(faixaDePreco(row.original.tipo, row.original.venda_min, row.original.venda_max))}
            <div className="truncate text-[11px] font-medium text-[var(--warn)]" title={texto} data-contract="produto-lista-preco-proposto">
              {texto}
            </div>
          </div>
        );
      },
    });
  }
  if (v('estoque')) {
    out.push({
      id: 'estoque',
      header: 'Estoque atual',
      enableSorting: true,
      accessorFn: (l) => l.estoque,
      meta: { width: 130, align: 'right' },
      cell: ({ row }) => {
        const saldo = (
          <span className={'pb-stock' + (!row.original.controla_estoque ? ' na' : row.original.abaixo_do_alerta ? ' low' : '')}>
            {estoqueTexto(row.original)}
          </span>
        );
        const req = row.original.requisicao;
        if (!req) return saldo;
        // UC-PIDX-30 (UC-FLX-19): requisição de compra aberta, do estoque mínimo ou feita à mão.
        const texto = `Requisição ${req.numero} aberta em ${dataBr(req.data).slice(0, 5)}${req.fornecedor ? ` · ${req.fornecedor}` : ''}`;
        return (
          <div className="min-w-0">
            {saldo}
            <div className="truncate text-[11px] text-[var(--accent)]" title={texto} data-contract="produto-lista-requisicao">
              {texto}
            </div>
          </div>
        );
      },
    });
  }
  if (v('tipo')) {
    out.push({
      id: 'tipo',
      header: 'Tipo',
      enableSorting: true,
      accessorFn: (l) => l.tipo,
      meta: { width: 110 },
      cell: ({ row }) => <span className={`pb-pill ${row.original.tipo}`}>{TIPO_ROTULO[row.original.tipo] ?? row.original.tipo}</span>,
    });
  }
  if (v('categoria')) {
    out.push({
      id: 'categoria',
      header: 'Categoria',
      enableSorting: true,
      accessorFn: (l) => l.categoria,
      meta: { width: 160 },
      cell: ({ row }) => (
        <div className="min-w-0">
          <div className="truncate font-semibold" title={row.original.categoria ?? undefined}>
            {row.original.categoria ?? '—'}
          </div>
          {!densa && row.original.subcategoria && (
            <div className="truncate text-[11px] text-[var(--text-mute)]">{row.original.subcategoria}</div>
          )}
        </div>
      ),
    });
  }
  if (v('marca')) {
    out.push({ id: 'marca', header: 'Marca', enableSorting: true, accessorFn: (l) => l.marca, meta: { width: 120 }, cell: ({ row }) => comDica(row.original.marca) });
  }
  if (v('imposto')) {
    out.push({ id: 'imposto', header: 'Imposto', enableSorting: false, meta: { width: 110 }, cell: ({ row }) => comDica(row.original.imposto) });
  }
  if (v('sku')) {
    out.push({ id: 'sku', header: 'SKU', enableSorting: true, accessorFn: (l) => l.sku, meta: { width: 120, mono: true }, cell: ({ row }) => comDica(row.original.sku) });
  }
  if (v('serie')) {
    out.push({
      id: 'serie',
      header: 'IMEI / série',
      enableSorting: false,
      meta: { width: 110 },
      cell: ({ row }) => (row.original.serie ? 'Sim' : '—'),
    });
  }
  for (let i = 1; i <= 7; i++) {
    const rotulo = contexto.rotulos_campos[String(i)];
    if (rotulo && v(`cf${i}`)) {
      out.push({
        id: `cf${i}`,
        header: rotulo,
        enableSorting: false,
        meta: { width: 170 },
        cell: ({ row }) => comDica(row.original.campos[String(i)]),
      });
    }
  }
  return out;
}

/** Texto da célula com o inteiro no `title` (a célula corta com "…" quando a coluna encolhe). */
function comDica(texto: string | null | undefined): ReactNode {
  if (!texto || texto === '—') return '—';
  return <span title={texto}>{texto}</span>;
}

/**
 * Preço da célula. A faixa de produto variável ("‹mínimo› – ‹máximo›") quebra em duas linhas logo
 * depois do traço quando a coluna encolhe (UC-PIDX-26): cortada com "…", o preço máximo sumia. Os
 * outros espaços viram inseparáveis, então a quebra só cai no traço ("R$" nunca fica sozinho). No
 * modo confortável a linha já tem duas linhas de texto (nome + detalhe): medido em 1366px, ela passa
 * de 45 para 47px.
 */
function preco(texto: string): ReactNode {
  if (!texto || texto === '—') return '—';
  const comQuebraNoTraco = texto.replace(/ /g, '\u00a0').replace('\u00a0–\u00a0', '\u00a0– ');
  return (
    <span title={texto} className="whitespace-normal">
      {comQuebraNoTraco}
    </span>
  );
}

/**
 * Aplica as larguras do encaixe (`encaixarColunas`, UC-PIDX-26). A coluna encolhida deixa o rótulo
 * do cabeçalho quebrar em duas linhas — "Preço de compra un." não cabe numa linha em 92px, e sem
 * quebrar ele invadiria a coluna vizinha. As colunas que não mudaram ficam como estão.
 */
export function aplicarEncaixe(
  colunas: ColumnDef<LinhaProduto, unknown>[],
  larguras: Record<string, number>,
): ColumnDef<LinhaProduto, unknown>[] {
  return colunas.map((c) => {
    const largura = c.id ? larguras[c.id] : undefined;
    if (largura === undefined) return c;
    const header = typeof c.header === 'string' ? <span className="whitespace-normal">{c.header}</span> : c.header;
    return { ...c, header, meta: { ...c.meta, width: largura } } as ColumnDef<LinhaProduto, unknown>;
  });
}

/**
 * Filtros que a tabela devolve ao servidor ao ordenar e paginar: os da URL, mais a ordem e a
 * direção SEMPRE explícitas. A URL omite o padrão (`sort=produto`, `dir=asc`) para ficar curta, mas
 * a tabela só inverte a direção quando recebe `dir: 'asc'` da coluna ativa — sem isso o segundo
 * clique repetia "asc" e nenhuma coluna chegava a ordenar ao contrário.
 */
export function filtrosParaATabela(
  daUrl: Record<string, string | number>,
  f: Pick<FiltrosLista, 'sort' | 'dir'>,
): Record<string, string | number> {
  return { ...daUrl, sort: f.sort, dir: f.dir };
}
