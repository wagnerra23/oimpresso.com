// GradeMatrixInput.tsx — entrada matricial tam × cor pra Compras de vestuário.
// US-COM-005 — Wave 4.5 scaffold (Larissa biz=4 ROTA LIVRE piloto).
//
// Headless puro (sem TanStack Table v8 ainda — V1 cabe em inputs nativos).
// Cada célula = 1 SKU filho (variation_id no backend).
//
// Referência arte 2026-05-21:
// memory/sessions/2026-05-21-arte-grade-matrix-input-vestuario.md
//
// Teclado canônico (Cin7 / Lightspeed pattern):
// - Tab        → próxima COLUNA (mesma linha)
// - Shift+Tab  → coluna anterior
// - Enter      → próxima LINHA (mesma coluna)
// - Shift+Enter→ linha anterior
// - Esc        → cancela (chama onCancel)
// - ↑↓←→       → navegação 4 direções

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

export interface GradeRow {
  /** ID da variation (ex: tamanho "P", "M", "G", "GG") */
  id: string | number;
  /** Label exibido na linha */
  label: string;
}

export interface GradeCol {
  /** ID da product_variation (ex: cor "Preto", "Branco") */
  id: string | number;
  /** Label exibido no header da coluna */
  label: string;
}

export interface GradeCell {
  rowId: GradeRow['id'];
  colId: GradeCol['id'];
  /** ID do SKU filho (variation_id no backend) */
  variationId: number;
  qty: number;
}

export interface GradeMatrixInputProps {
  /** Linhas da grade — geralmente tamanhos (P/M/G/GG) */
  rows: GradeRow[];
  /** Colunas da grade — geralmente cores */
  cols: GradeCol[];
  /** Mapping linha+coluna → variation_id real do backend */
  cellVariationMap: Record<string, number>;
  /** Quantidades iniciais (edição posterior — opcional) */
  initialQty?: Record<string, number>;
  /** Custo unitário 1 por modelo (override por célula em V2) */
  unitCost?: number;
  /** Disable input — read-only mode */
  disabled?: boolean;
  /** Callback save atomic — chamado por caller no submit do form */
  onChange?: (cells: GradeCell[]) => void;
  /** Callback Esc — caller decide se fecha drawer / cancela */
  onCancel?: () => void;
}

/**
 * Key composta linha+coluna pra Map/Record.
 */
function cellKey(rowId: GradeRow['id'], colId: GradeCol['id']): string {
  return `${rowId}__${colId}`;
}

export default function GradeMatrixInput({
  rows,
  cols,
  cellVariationMap,
  initialQty = {},
  unitCost = 0,
  disabled = false,
  onChange,
  onCancel,
}: GradeMatrixInputProps) {
  // matrix-1d (single-axis): GradeLayoutBuilder emite cols=[{id:'qtd', label:'Qtd'}] quando o
  // produto tem só 1 eixo de variação (ex: Tamanho, sem Cor — caso biz=4 ROTA LIVRE, confirmado
  // em prod 2026-06-23). Sem eixo cor, a cópia "Tam / Cor" / "próxima cor" confunde a Larissa
  // (não-técnica) — troca pra linguagem só-tamanho. 2d (nome composto "P/Preto") mantém cor.
  const isSingleAxis = cols.length === 1 && String(cols[0]?.id) === 'qtd';

  // Matriz qty[rowIdx][colIdx] → number. State 2D pra render rápido.
  const [qtys, setQtys] = useState<number[][]>(() =>
    rows.map((r) =>
      cols.map((c) => {
        const key = cellKey(r.id, c.id);
        return Number(initialQty[key] ?? 0);
      })
    )
  );

  // Refs pra navegação teclado entre <input>s. inputRefs[rowIdx][colIdx]
  const inputRefs = useRef<(HTMLInputElement | null)[][]>([]);
  if (inputRefs.current.length !== rows.length) {
    inputRefs.current = rows.map(() => cols.map(() => null));
  }

  // Atomic emit callback — caller acumula em state e submete tudo de uma vez.
  const emitChange = useCallback(
    (newQtys: number[][]) => {
      if (!onChange) return;
      const cells: GradeCell[] = [];
      rows.forEach((r, ri) => {
        cols.forEach((c, ci) => {
          const qty = newQtys[ri]?.[ci] ?? 0;
          if (qty <= 0) return;
          const variationId = cellVariationMap[cellKey(r.id, c.id)];
          if (variationId == null) return;
          cells.push({ rowId: r.id, colId: c.id, variationId, qty });
        });
      });
      onChange(cells);
    },
    [rows, cols, cellVariationMap, onChange]
  );

  const updateQty = useCallback(
    (ri: number, ci: number, value: number) => {
      // Validação inline — qty negativa proibida.
      const safe = Math.max(0, Math.min(9999, Number.isFinite(value) ? value : 0));
      setQtys((prev) => {
        const next = prev.map((row) => row.slice());
        const targetRow = next[ri];
        if (targetRow) {
          targetRow[ci] = safe;
        }
        emitChange(next);
        return next;
      });
    },
    [emitChange]
  );

  const focusCell = useCallback((ri: number, ci: number) => {
    const el = inputRefs.current[ri]?.[ci];
    if (el) {
      el.focus();
      el.select();
    }
  }, []);

  const handleKeyDown = useCallback(
    (e: React.KeyboardEvent<HTMLInputElement>, ri: number, ci: number) => {
      if (disabled) return;

      const shift = e.shiftKey;
      const isLastCol = ci === cols.length - 1;
      const isLastRow = ri === rows.length - 1;
      const isFirstCol = ci === 0;
      const isFirstRow = ri === 0;

      switch (e.key) {
        case 'Tab': {
          if (shift) {
            if (!isFirstCol) {
              e.preventDefault();
              focusCell(ri, ci - 1);
            } else if (!isFirstRow) {
              e.preventDefault();
              focusCell(ri - 1, cols.length - 1);
            }
            // shift+tab no primeiro célula deixa o browser tabbing pra fora
          } else {
            if (!isLastCol) {
              e.preventDefault();
              focusCell(ri, ci + 1);
            } else if (!isLastRow) {
              e.preventDefault();
              focusCell(ri + 1, 0);
            }
          }
          break;
        }
        case 'Enter': {
          e.preventDefault();
          if (shift) {
            if (!isFirstRow) focusCell(ri - 1, ci);
          } else {
            if (!isLastRow) focusCell(ri + 1, ci);
          }
          break;
        }
        case 'Escape': {
          e.preventDefault();
          onCancel?.();
          break;
        }
        case 'ArrowUp': {
          if (!isFirstRow) {
            e.preventDefault();
            focusCell(ri - 1, ci);
          }
          break;
        }
        case 'ArrowDown': {
          if (!isLastRow) {
            e.preventDefault();
            focusCell(ri + 1, ci);
          }
          break;
        }
        case 'ArrowLeft': {
          // Só navega se cursor está no início do input
          const target = e.target as HTMLInputElement;
          if (target.selectionStart === 0 && !isFirstCol) {
            e.preventDefault();
            focusCell(ri, ci - 1);
          }
          break;
        }
        case 'ArrowRight': {
          const target = e.target as HTMLInputElement;
          if (target.selectionStart === target.value.length && !isLastCol) {
            e.preventDefault();
            focusCell(ri, ci + 1);
          }
          break;
        }
      }
    },
    [cols.length, rows.length, focusCell, onCancel, disabled]
  );

  // Totais por linha / coluna / grand — useMemo evita recalcular a cada keystroke
  const totals = useMemo(() => {
    const rowTotals = qtys.map((row) => row.reduce((s, n) => s + n, 0));
    const colTotals = cols.map((_c, ci) => qtys.reduce((s, row) => s + (row[ci] ?? 0), 0));
    const grand = rowTotals.reduce((s, n) => s + n, 0);
    return { rowTotals, colTotals, grand };
  }, [qtys, cols]);

  // Autofocus primeira célula vazia ao montar
  useEffect(() => {
    if (disabled) return;
    for (let ri = 0; ri < rows.length; ri++) {
      for (let ci = 0; ci < cols.length; ci++) {
        if ((qtys[ri]?.[ci] ?? 0) === 0) {
          focusCell(ri, ci);
          return;
        }
      }
    }
    // se grid toda preenchida, foca primeira
    focusCell(0, 0);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Empty state — produto single sem variações
  if (rows.length === 0 || cols.length === 0) {
    return (
      <div className="rounded-md border border-stone-200 bg-white p-4 text-sm text-stone-600">
        Este produto não tem variações configuradas. Use a quantidade simples no formulário acima.
      </div>
    );
  }

  return (
    <div className="rounded-lg border border-stone-200 bg-white shadow-sm overflow-hidden">
      <div className="overflow-x-auto">
        <table className="min-w-full border-collapse text-sm">
          <thead className="sticky top-0 bg-stone-50">
            <tr>
              <th className="border-b border-stone-200 px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-stone-500">
                {isSingleAxis ? 'Tamanho' : 'Tam / Cor'}
              </th>
              {cols.map((c, ci) => (
                <th
                  key={c.id}
                  className="border-b border-stone-200 px-3 py-2 text-center text-xs font-semibold uppercase tracking-wide text-stone-700"
                  data-col-id={c.id}
                >
                  {c.label}
                  <div className="mt-0.5 font-mono text-[10px] text-stone-400">
                    Σ {totals.colTotals[ci]}
                  </div>
                </th>
              ))}
              <th className="border-b border-stone-200 px-3 py-2 text-right text-xs font-semibold uppercase tracking-wide text-stone-700">
                Total linha
              </th>
            </tr>
          </thead>
          <tbody>
            {rows.map((r, ri) => (
              <tr key={r.id} className="hover:bg-stone-50/60">
                <th
                  scope="row"
                  className="border-b border-stone-100 bg-stone-50/50 px-3 py-1.5 text-left text-sm font-medium text-stone-700"
                  data-row-id={r.id}
                >
                  {r.label}
                </th>
                {cols.map((c, ci) => {
                  const hasVariation = cellVariationMap[cellKey(r.id, c.id)] != null;
                  return (
                    <td
                      key={c.id}
                      className="border-b border-stone-100 px-1.5 py-1 text-center"
                    >
                      <input
                        ref={(el) => {
                          if (!inputRefs.current[ri]) inputRefs.current[ri] = [];
                          inputRefs.current[ri][ci] = el;
                        }}
                        type="number"
                        min={0}
                        max={9999}
                        step={1}
                        inputMode="numeric"
                        disabled={disabled || !hasVariation}
                        value={qtys[ri]?.[ci] || ''}
                        onChange={(e) => updateQty(ri, ci, Number(e.target.value))}
                        onKeyDown={(e) => handleKeyDown(e, ri, ci)}
                        onFocus={(e) => e.target.select()}
                        className={`w-16 rounded-md border px-2 py-1 text-center font-mono text-sm tabular-nums focus:outline-none focus:ring-2 focus:ring-primary-500 ${
                          hasVariation
                            ? 'border-stone-200 bg-white text-stone-900'
                            : 'border-stone-100 bg-stone-50 text-stone-300 cursor-not-allowed'
                        }`}
                        aria-label={
                          isSingleAxis
                            ? `Quantidade tamanho ${r.label}`
                            : `Quantidade ${r.label} ${c.label}`
                        }
                        title={
                          hasVariation
                            ? isSingleAxis
                              ? `Tamanho ${r.label}`
                              : `${r.label} × ${c.label}`
                            : 'SKU não cadastrado pra essa combinação'
                        }
                      />
                    </td>
                  );
                })}
                <td className="border-b border-stone-100 px-3 py-1.5 text-right font-mono text-sm font-semibold tabular-nums text-stone-700">
                  {totals.rowTotals[ri]}
                </td>
              </tr>
            ))}
          </tbody>
          <tfoot className="bg-stone-50">
            <tr>
              <th className="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide text-stone-500">
                Total
              </th>
              {cols.map((c, ci) => (
                <td
                  key={c.id}
                  className="px-3 py-2 text-center font-mono text-sm font-semibold tabular-nums text-stone-700"
                >
                  {totals.colTotals[ci]}
                </td>
              ))}
              <td className="px-3 py-2 text-right font-mono text-base font-bold tabular-nums text-stone-900">
                {totals.grand}
                {unitCost > 0 && (
                  <div className="mt-0.5 text-xs font-normal text-stone-500">
                    {(totals.grand * unitCost).toLocaleString('pt-BR', {
                      style: 'currency',
                      currency: 'BRL',
                    })}
                  </div>
                )}
              </td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div className="border-t border-stone-100 bg-stone-50/50 px-3 py-1.5 text-[11px] text-stone-500">
        Atalhos:{' '}
        {isSingleAxis ? (
          <>
            <kbd className="rounded border border-stone-200 bg-white px-1 font-mono text-[10px]">Enter</kbd>{' '}
            próximo tamanho ·{' '}
            <kbd className="rounded border border-stone-200 bg-white px-1 font-mono text-[10px]">↑↓</kbd>{' '}
            navega ·{' '}
            <kbd className="rounded border border-stone-200 bg-white px-1 font-mono text-[10px]">Esc</kbd>{' '}
            cancela
          </>
        ) : (
          <>
            <kbd className="rounded border border-stone-200 bg-white px-1 font-mono text-[10px]">Tab</kbd>{' '}
            próxima cor ·{' '}
            <kbd className="rounded border border-stone-200 bg-white px-1 font-mono text-[10px]">Enter</kbd>{' '}
            próxima linha ·{' '}
            <kbd className="rounded border border-stone-200 bg-white px-1 font-mono text-[10px]">Esc</kbd>{' '}
            cancela ·{' '}
            <kbd className="rounded border border-stone-200 bg-white px-1 font-mono text-[10px]">↑↓←→</kbd>{' '}
            navega
          </>
        )}
      </div>
    </div>
  );
}
