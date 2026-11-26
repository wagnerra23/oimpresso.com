/**
 * Rótulo e largura do cabeçalho de cada coluna da lista de vendas. Usados pela tabela
 * (`SellsTabelaUnificada`) e pela janela do botão Colunas (`ColunasListaModal`).
 * Ficam fora do arquivo do componente para o react-refresh (só componente em arquivo .tsx).
 */
import type { CSSProperties } from 'react';
import type { ColumnId } from './SellsTabelaUnificada';

export const COL_HEADERS: Record<ColumnId, { label: string; width?: number; style?: CSSProperties; className?: string }> = {
  check:      { label: '', width: 24, style: { padding: '0 0 0 12px' } },
  invoice:    { label: 'Venda', width: 82 },
  date:       { label: 'Data', width: 80 },
  client:     { label: 'Cliente' },
  seller:     { label: 'Atendido por', width: 168 },
  source:     { label: 'Origem', width: 138, className: 'vd-col-source' },
  pipeline:   { label: 'Pipeline', width: 128 },
  fiscal:     { label: 'Fiscal', width: 148 },
  payment:    { label: 'Pagamento', width: 128 },
  location:   { label: 'Localização', width: 120 },
  paid:       { label: 'Pago', width: 100 },
  due:        { label: 'A receber', width: 110 },
  total:      { label: 'Total', width: 110 },
  status:     { label: 'Status', width: 88 },
  commission: { label: 'Comissão', width: 120 },
};
