// US-SELL-016 — Barra flutuante de ações em lote sobre seleção múltipla na
// Grade Avançada. Refs: ADR 0136 (Sells: Lista vs Grade Avançada toggle).
//
// Aparece quando `selectedIds.size > 0`. Endpoints backend:
//   POST /sells/bulk-print  -> HTML printable consolidado
//   POST /sells/bulk-export -> CSV download
//
// "Agrupar por…" fica desabilitado com tooltip "P1 — em breve" (US-SELL-019).

import { useState } from 'react';
import { Layers3, Loader2, Printer, Sheet as SheetIcon, X } from 'lucide-react';
import { Button } from '@/Components/ui/button';

interface SellsBulkActionsBarProps {
  selectedIds: number[];
  totalFiltered: number;
  onClearSelection: () => void;
}

export default function SellsBulkActionsBar({
  selectedIds,
  totalFiltered,
  onClearSelection,
}: SellsBulkActionsBarProps) {
  const [busy, setBusy] = useState<'print' | 'export' | null>(null);

  if (selectedIds.length === 0) return null;

  const csrf = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';

  async function handlePrint() {
    setBusy('print');
    try {
      // Submit via form auto-target=_blank pra browser abrir nova aba e disparar window.print().
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = '/sells/bulk-print';
      form.target = '_blank';
      form.style.display = 'none';

      const csrfInput = document.createElement('input');
      csrfInput.type = 'hidden';
      csrfInput.name = '_token';
      csrfInput.value = csrf;
      form.appendChild(csrfInput);

      selectedIds.forEach((id) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'ids[]';
        input.value = String(id);
        form.appendChild(input);
      });

      document.body.appendChild(form);
      form.submit();
      document.body.removeChild(form);
    } finally {
      // Pequeno delay pra UX (busy spinner aparece e some).
      setTimeout(() => setBusy(null), 600);
    }
  }

  async function handleExport() {
    setBusy('export');
    try {
      const res = await fetch('/sells/bulk-export', {
        method: 'POST',
        headers: {
          Accept: 'text/csv',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf,
        },
        credentials: 'same-origin',
        body: JSON.stringify({ ids: selectedIds }),
      });
      if (!res.ok) {
        alert('Falha ao exportar CSV: ' + res.status);
        return;
      }
      const blob = await res.blob();
      // Disparar download client-side preservando filename do header se possível.
      const cd = res.headers.get('Content-Disposition') || '';
      const m = cd.match(/filename="?([^";]+)"?/i);
      const filename = m?.[1] ?? `vendas-${new Date().toISOString().slice(0, 10)}.csv`;
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    } catch (e) {
      alert('Erro ao exportar: ' + String((e as Error)?.message || e));
    } finally {
      setBusy(null);
    }
  }

  return (
    <div
      className="sticky top-0 z-20 mb-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-blue-200 bg-blue-50/95 px-4 py-2.5 shadow-sm backdrop-blur-sm dark:border-blue-900/60 dark:bg-blue-950/60"
      role="toolbar"
      aria-label="Ações em lote sobre vendas selecionadas"
    >
      <div className="flex items-center gap-2 text-sm">
        <span className="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-blue-600 px-1.5 text-xs font-semibold text-white tabular-nums">
          {selectedIds.length}
        </span>
        <span className="font-medium text-blue-900 dark:text-blue-100">
          {selectedIds.length === 1 ? 'venda selecionada' : 'vendas selecionadas'}
        </span>
        {totalFiltered > selectedIds.length && (
          <span className="text-xs text-blue-700/70 dark:text-blue-300/70">
            de {totalFiltered.toLocaleString('pt-BR')} no filtro
          </span>
        )}
        <button
          type="button"
          onClick={onClearSelection}
          className="ml-2 inline-flex items-center gap-1 rounded px-2 py-0.5 text-xs text-blue-700 hover:bg-blue-100 hover:text-blue-900 dark:text-blue-300 dark:hover:bg-blue-900/40"
          aria-label="Limpar seleção"
        >
          <X size={12} />
          Limpar
        </button>
      </div>

      <div className="flex items-center gap-2">
        <Button
          size="sm"
          variant="outline"
          onClick={handlePrint}
          disabled={busy !== null}
          className="bg-background"
        >
          {busy === 'print' ? (
            <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />
          ) : (
            <Printer className="mr-1.5 h-3.5 w-3.5" />
          )}
          Imprimir seleção
        </Button>
        <Button
          size="sm"
          variant="outline"
          onClick={handleExport}
          disabled={busy !== null}
          className="bg-background"
        >
          {busy === 'export' ? (
            <Loader2 className="mr-1.5 h-3.5 w-3.5 animate-spin" />
          ) : (
            <SheetIcon className="mr-1.5 h-3.5 w-3.5" />
          )}
          Exportar CSV
        </Button>
        <Button
          size="sm"
          variant="outline"
          disabled
          title="P1 — em breve (US-SELL-019)"
          className="bg-background opacity-60"
        >
          <Layers3 className="mr-1.5 h-3.5 w-3.5" />
          Agrupar por…
        </Button>
      </div>
    </div>
  );
}
