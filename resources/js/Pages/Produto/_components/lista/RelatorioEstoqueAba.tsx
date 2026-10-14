// Aba "Relatório de estoque" da lista de produtos — `RelatorioEstoque` do protótipo
// (produto-blade.jsx, vista `lista`) e a aba `#product_stock_report` da lista Blade.
// Nada é calculado aqui: linhas e totais chegam prontos de
// ProductController::buildRelatorioEstoqueDaLista (as MESMAS contas de Relatórios → Estoque).

import type { CSSProperties } from 'react';
import { Button } from '@/Components/ui/button';
import { Inline } from '@/Components/layout';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { brl } from '../../Unificado/_components/catalogo';
import { POR_PAGINA, type RelatorioEstoqueLista } from './tipos';

const qtd = (n: number, unidade = '') =>
  `${n.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 2 })} ${unidade}`.trim();
const dinheiro = (n: number | null) => (n === null ? '' : brl(n));
const horaDe = (iso: string | null) =>
  iso ? new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';
/**
 * Cor do lucro potencial da LINHA. Protótipo: verde (`--pos`) — lá o lucro é sempre positivo. Aqui o
 * número vem do estoque de verdade: positivo fica verde, negativo (custo acima do preço de venda)
 * fica vermelho, e zero ou sem valor não ganha cor. O total do rodapé não tem cor, como no protótipo.
 *
 * ⚠️ A cor vai em `style`, NUNCA em classe utilitária. O bundle da tela tem
 * `.pb-tbl tbody td { color: var(--text) }` fora de qualquer camada do Tailwind, e regra sem camada
 * vence `@layer utilities`: `text-[var(--pos)]` chegava ao DOM e a célula continuava na cor comum.
 * Medido em produção em 2026-10-14 (cor computada igual nas 40 positivas, nas 3 negativas e no total).
 */
const corDoLucro = (n: number | null): CSSProperties | undefined =>
  n === null || n === 0 ? undefined : { color: n > 0 ? 'var(--pos)' : 'var(--neg)' };
/**
 * Linha vermelha da Blade (estoque no alerta ou abaixo). Aqui a causa é OUTRA: herança. A cor do
 * `<tr>` não chega às células porque cada `td` declara `color: var(--text)` próprio — com camada ou
 * sem. Trocar o `--text` da linha faz
 * TODAS as células dela usarem o vermelho, sem repetir a cor em cada uma. Até 2026-10-14 a classe
 * no `<tr>` era inerte: a linha de alerta nunca apareceu vermelha.
 */
const corDaLinha = (alerta: boolean): CSSProperties | undefined =>
  alerta ? ({ '--text': 'var(--neg)' } as CSSProperties) : undefined;

export default function RelatorioEstoqueAba({
  dados,
  irParaPagina,
  aoMudarPorPagina,
}: {
  dados: RelatorioEstoqueLista;
  irParaPagina: (pagina: number) => void;
  /** Troca o tamanho da página (-1 = todas as linhas). Quem recebe volta para a página 1. */
  aoMudarPorPagina: (porPagina: number) => void;
}) {
  const { linhas, totais, mostra_preco: preco, mostra_valor: valor, paginacao } = dados;
  // SKU · Produto · Variação · Categoria · Local · [Preço] · Estoque · [3 valores] · 3 movimentos
  const antesDoEstoque = 5 + (preco ? 1 : 0);

  return (
    <div data-contract="produto-relatorio-estoque">
      <div className="pb-tblwrap">
        <table className="pb-tbl">
          <caption className="sr-only">Relatório de estoque dos produtos</caption>
          <thead>
            <tr>
              <th>SKU</th>
              <th>Produto</th>
              <th>Variação</th>
              <th>Categoria</th>
              <th>Local</th>
              {preco && <th className="r">Preço unitário</th>}
              <th className="r">Estoque atual</th>
              {valor && (
                <>
                  <th className="r">Valor pelo custo</th>
                  <th className="r">Valor pela venda</th>
                  <th className="r">Lucro potencial</th>
                </>
              )}
              <th className="r">Total vendido</th>
              <th className="r">Total transferido</th>
              <th className="r">Total ajustado</th>
            </tr>
          </thead>
          <tbody>
            {linhas.length === 0 && (
              <tr>
                {/* Cor apagada em `style` pela mesma razão do lucro: a classe perdia para o bundle. */}
                <td
                  colSpan={antesDoEstoque + 4 + (valor ? 3 : 0)}
                  className="py-6 text-center"
                  style={{ color: 'var(--text-mute)' }}
                >
                  Nenhum produto com estoque para esses filtros.
                </td>
              </tr>
            )}
            {linhas.map((l, i) => (
              // Como a linha vermelha da Blade: estoque no alerta ou abaixo.
              <tr key={`${l.sku}-${l.local}-${i}`} style={corDaLinha(l.alerta)}>
                <td className="m">{l.sku}</td>
                <td>
                  <b>{l.produto}</b>
                </td>
                <td>{l.variacao}</td>
                <td>{l.categoria}</td>
                <td>{l.local}</td>
                {preco && <td className="r">{dinheiro(l.preco)}</td>}
                {/* Produto sem controle de estoque: a Blade mostra "--". */}
                <td className="r">{l.estoque === null ? '--' : qtd(l.estoque, l.unidade)}</td>
                {valor && (
                  <>
                    <td className="r">{dinheiro(l.valor_compra)}</td>
                    <td className="r">{dinheiro(l.valor_venda)}</td>
                    <td className="r" style={corDoLucro(l.lucro_potencial)}>
                      {dinheiro(l.lucro_potencial)}
                    </td>
                  </>
                )}
                <td className="r">{qtd(l.vendido, l.unidade)}</td>
                <td className="r">{qtd(l.transferido, l.unidade)}</td>
                <td className="r">{qtd(l.ajustado, l.unidade)}</td>
              </tr>
            ))}
          </tbody>
          <tfoot>
            <tr>
              <td colSpan={antesDoEstoque}>
                <b>
                  Totais (todas as {totais.linhas} {totais.linhas === 1 ? 'linha' : 'linhas'})
                </b>
                {totais.em && <small>calculado às {horaDe(totais.em)}</small>}
              </td>
              <td className="r">
                <b>{qtd(totais.estoque)}</b>
              </td>
              {valor && (
                <>
                  <td className="r">
                    <b>{dinheiro(totais.valor_compra)}</b>
                  </td>
                  <td className="r">
                    <b>{dinheiro(totais.valor_venda)}</b>
                  </td>
                  <td className="r">
                    <b>{dinheiro(totais.lucro_potencial)}</b>
                  </td>
                </>
              )}
              <td className="r">
                <b>{qtd(totais.vendido)}</b>
              </td>
              <td className="r">
                <b>{qtd(totais.transferido)}</b>
              </td>
              <td className="r">
                <b>{qtd(totais.ajustado)}</b>
              </td>
            </tr>
          </tfoot>
        </table>
      </div>
      <div className="pb-pag" data-contract="produto-relatorio-estoque-paginas">
        <span aria-live="polite">
          Página {paginacao.atual} de {paginacao.ultima} · {paginacao.total} {paginacao.total === 1 ? 'linha' : 'linhas'}
        </span>
        <div className="sp" />
        <Button variant="outline" size="sm" disabled={paginacao.atual <= 1} onClick={() => irParaPagina(paginacao.atual - 1)}>
          Anterior
        </Button>
        <Button
          variant="outline"
          size="sm"
          disabled={paginacao.atual >= paginacao.ultima}
          onClick={() => irParaPagina(paginacao.atual + 1)}
        >
          Próxima
        </Button>
        {/* O mesmo seletor do rodapé da lista, com as mesmas opções (as da lista Blade). */}
        <Inline gap={1}>
          Mostrar
          <Select value={String(paginacao.por_pagina)} onValueChange={(v) => aoMudarPorPagina(Number(v))}>
            <SelectTrigger className="h-6 w-[88px]" aria-label="Linhas do relatório por página">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {POR_PAGINA.map((n) => (
                <SelectItem key={n} value={String(n)}>
                  {n === -1 ? 'Todos' : n}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          por página
        </Inline>
      </div>
    </div>
  );
}
