// @memcofre tela=/repair/job-sheet/add-parts/{id} module=Repair
// Wave 3 B6 MWART — JobSheet AddParts port Blade → Inertia.
// SEM FSM (ação não-transitiva). Form lista peças editável.
//
// Score-up 2026-05-31 (board: "pede Variation ID numérico cru; sem totais"):
//   - Variation ID cru → autocomplete de produto (nome/SKU). Reusa o MESMO endpoint
//     legado `/products/list` do Sells ProductSearchAutocomplete (ProductController@
//     getProducts → ProductUtil@filterProduct) + o MESMO padrão TanStack Query
//     (debounce 250ms, signal cancela request stale — ADR 0211). Inline (não componente
//     próprio) porque a task restringe a edição a este arquivo; e local porque o payload
//     Inertia de addParts NÃO traz `location_id` (Controller fora de escopo) — o gate de
//     location do componente Sells inviabilizaria a busca. Backend isola por business_id,
//     então busca sem location_id funciona (paridade Blade `jobsheetPartRow`).
//   - Subtotal por linha (qty × unit_price vindo de selling_price) + total da OS.
//   - Erros por campo (linha sem produto / qty inválida) inline + Save guard.

import AppShellV2 from '@/Layouts/AppShellV2';
import { useForm, Link, router } from '@inertiajs/react';
import { useState, useEffect, useRef, useMemo, type FormEvent } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Save, X, Plus, Trash2, Wrench, Search, Loader2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import PageHeader from '@/Components/shared/PageHeader';

interface JobSheetMin {
  id: number;
  job_sheet_no: string | null;
  contact_name: string | null;
}

interface PartRow {
  variation_id: number | null;
  variation_name: string | null;
  quantity: number;
  unit: string | null;
  unit_price?: number | null;
}

interface DropdownOption {
  [key: string]: string;
}

interface Props {
  job_sheet: JobSheetMin;
  parts: PartRow[];
  status_update_data?: {
    status_id?: number;
  } | null;
  status_dropdown: DropdownOption;
  status_template_tags: Record<string, string>;
}

// Shape devolvido por `/products/list` (ProductUtil@filterProduct). Subconjunto
// dos campos que usamos aqui — espelha ProductSearchResult do Sells.
interface PartSearchResult {
  product_id: number;
  variation_id: number;
  name: string;
  variation?: string;
  sub_sku?: string;
  sku: string;
  selling_price?: number;
  unit?: string;
}

const BRL = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
const DEBOUNCE_MS = 250;
const MIN_QUERY_LENGTH = 2;

const emptyRow = (): PartRow => ({
  variation_id: null,
  variation_name: '',
  quantity: 1,
  unit: null,
  unit_price: null,
});

/**
 * Autocomplete de peça (variation) inline. Mesmo contrato do Sells
 * ProductSearchAutocomplete (endpoint `/products/list` + TanStack Query), porém
 * sem o gate de `locationId` — addParts não recebe location no payload e o backend
 * já filtra por business_id. Chama `onSelect` com a variação escolhida.
 */
function PartSearchField({
  onSelect,
  ariaLabel,
  invalid,
}: {
  onSelect: (part: PartSearchResult) => void;
  ariaLabel: string;
  invalid?: boolean;
}) {
  const [query, setQuery] = useState('');
  const [debouncedQuery, setDebouncedQuery] = useState('');
  const [open, setOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (query.length < MIN_QUERY_LENGTH) {
      setDebouncedQuery('');
      return;
    }
    const handle = setTimeout(() => setDebouncedQuery(query), DEBOUNCE_MS);
    return () => clearTimeout(handle);
  }, [query]);

  const partQuery = useQuery({
    queryKey: ['repair-parts', debouncedQuery],
    queryFn: async ({ signal }): Promise<PartSearchResult[]> => {
      const params = new URLSearchParams({ term: debouncedQuery });
      // Paridade Blade (pos.js default) — busca por nome + sku + lote.
      ['name', 'sku', 'lot'].forEach((f) => params.append('search_fields[]', f));
      const res = await fetch(`/products/list?${params.toString()}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        signal,
      });
      if (!res.ok) return [];
      const data = await res.json();
      return Array.isArray(data) ? (data as PartSearchResult[]).slice(0, 10) : [];
    },
    enabled: debouncedQuery.length >= MIN_QUERY_LENGTH,
  });

  const results = partQuery.data ?? [];
  const loading = partQuery.isFetching;

  useEffect(() => {
    if (partQuery.data !== undefined) setOpen(true);
  }, [partQuery.data]);

  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, []);

  const pick = (part: PartSearchResult) => {
    onSelect(part);
    setQuery('');
    setOpen(false);
  };

  return (
    <div ref={containerRef} className="relative w-full">
      <div className="relative">
        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          type="search"
          variant="shadcn"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          onFocus={() => results.length > 0 && setOpen(true)}
          onKeyDown={(e) => {
            if (e.key === 'Enter') e.preventDefault();
            if (e.key === 'Escape') setOpen(false);
          }}
          placeholder="Buscar produto por nome ou SKU…"
          aria-label={ariaLabel}
          aria-invalid={invalid}
          aria-expanded={open}
          aria-haspopup="listbox"
          autoComplete="off"
          className="h-8 pl-9 pr-8"
        />
        {loading && (
          <Loader2 className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-muted-foreground" />
        )}
      </div>

      {open && results.length > 0 && (
        <div
          role="listbox"
          className="absolute z-50 mt-1 max-h-72 w-full min-w-[18rem] overflow-auto rounded-md border border-border bg-popover shadow-md"
        >
          {results.map((r) => {
            const label = r.variation && r.variation !== 'DUMMY' ? `${r.name} - ${r.variation}` : r.name;
            const skuLabel = r.sub_sku ?? r.sku;
            return (
              <button
                key={`${r.variation_id}-${skuLabel}`}
                type="button"
                role="option"
                aria-selected={false}
                onClick={() => pick(r)}
                className="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground focus:outline-none"
              >
                <span className="flex min-w-0 flex-col">
                  <span className="truncate font-medium">{label}</span>
                  <span className="text-xs text-muted-foreground">SKU {skuLabel}</span>
                </span>
                {r.selling_price !== undefined && r.selling_price !== null && (
                  <span className="shrink-0 text-sm tabular-nums">
                    {BRL.format(Number(r.selling_price))}
                  </span>
                )}
              </button>
            );
          })}
        </div>
      )}

      {open && debouncedQuery.length >= MIN_QUERY_LENGTH && results.length === 0 && !loading && (
        <div className="absolute z-50 mt-1 w-full rounded-md border border-border bg-popover px-3 py-2 text-sm text-muted-foreground shadow-md">
          Nenhum produto encontrado para "{debouncedQuery}".
        </div>
      )}
    </div>
  );
}

export default function JobSheetAddParts({
  job_sheet,
  parts: initialParts,
  status_update_data,
  status_dropdown,
}: Props) {
  const [parts, setParts] = useState<PartRow[]>(
    initialParts && initialParts.length > 0 ? initialParts : [emptyRow()]
  );
  // Erros por campo: chave `${idx}.variation_id` | `${idx}.quantity`.
  const [rowErrors, setRowErrors] = useState<Record<string, string>>({});

  const { data, setData, processing } = useForm({
    status_id: status_update_data?.status_id ?? '',
    update_note: '',
    send_sms: false,
    send_email: false,
    sms_body: '',
    email_body: '',
    email_subject: '',
  });

  const addRow = () => setParts([...parts, emptyRow()]);

  const removeRow = (idx: number) => {
    setParts(parts.filter((_, i) => i !== idx));
    setRowErrors((prev) => {
      const next: Record<string, string> = {};
      for (const [key, msg] of Object.entries(prev)) {
        const [rowIdx, field] = key.split('.');
        const n = Number(rowIdx);
        if (n === idx) continue;
        next[`${n > idx ? n - 1 : n}.${field}`] = msg;
      }
      return next;
    });
  };

  const clearError = (key: string) =>
    setRowErrors((prev) => {
      if (!prev[key]) return prev;
      const clone = { ...prev };
      delete clone[key];
      return clone;
    });

  const updateRow = (idx: number, field: keyof PartRow, value: string | number | null) => {
    setParts(parts.map((r, i) => (i === idx ? { ...r, [field]: value } : r)));
    clearError(`${idx}.${field}`);
  };

  // Preenche a linha a partir do produto selecionado no autocomplete.
  const onPickPart = (idx: number, part: PartSearchResult) => {
    const sku = part.sub_sku ?? part.sku;
    setParts(
      parts.map((r, i) =>
        i === idx
          ? {
              ...r,
              variation_id: part.variation_id,
              variation_name: sku ? `${part.name} (${sku})` : part.name,
              unit: part.unit ?? r.unit ?? null,
              unit_price:
                part.selling_price !== undefined && part.selling_price !== null
                  ? Number(part.selling_price)
                  : r.unit_price ?? null,
            }
          : r,
      ),
    );
    clearError(`${idx}.variation_id`);
  };

  const lineSubtotal = (row: PartRow): number => {
    const price = Number(row.unit_price ?? 0);
    const qty = Number(row.quantity ?? 0);
    return price > 0 && qty > 0 ? price * qty : 0;
  };

  const total = useMemo(
    () => parts.reduce((acc, row) => acc + lineSubtotal(row), 0),
    [parts]
  );

  const validate = (): boolean => {
    const errs: Record<string, string> = {};
    parts.forEach((row, idx) => {
      if (!row.variation_id) errs[`${idx}.variation_id`] = 'Selecione um produto.';
      if (!row.quantity || row.quantity <= 0) errs[`${idx}.quantity`] = 'Qtd. deve ser maior que zero.';
    });
    setRowErrors(errs);
    return Object.keys(errs).length === 0;
  };

  const onSubmit = (e: FormEvent) => {
    e.preventDefault();
    if (!validate()) return;
    router.post(`/repair/job-sheet/save-parts/${job_sheet.id}`, {
      parts: parts
        .filter((p) => p.variation_id)
        .map((p) => ({ variation_id: p.variation_id, quantity: p.quantity })),
      status_id: data.status_id,
      update_note: data.update_note,
      send_sms: data.send_sms,
      send_email: data.send_email,
      sms_body: data.sms_body,
      email_body: data.email_body,
      email_subject: data.email_subject,
    });
  };

  const hasStatusUpdate = !!status_update_data?.status_id;

  return (
    <AppShellV2>
      <form onSubmit={onSubmit} className="container mx-auto p-4 space-y-4">
        <PageHeader
          icon="wrench"
          title={`Adicionar peças — OS #${job_sheet.job_sheet_no ?? job_sheet.id}`}
          description={job_sheet.contact_name ?? 'Sem cliente'}
          action={
            <div className="flex gap-2">
              <Button type="button" variant="outline" size="sm" asChild>
                <Link href={`/repair/job-sheet/${job_sheet.id}`}>
                  <X className="mr-1 h-4 w-4" /> Cancelar
                </Link>
              </Button>
              <Button type="submit" size="sm" disabled={processing}>
                <Save className="mr-1 h-4 w-4" /> Salvar peças
              </Button>
            </div>
          }
        />

        <section className="rounded-lg border bg-card p-4 space-y-3">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-semibold flex items-center gap-2">
              <Wrench className="h-4 w-4" /> Peças usadas
            </h2>
            <Button type="button" size="sm" variant="outline" onClick={addRow}>
              <Plus className="mr-1 h-4 w-4" /> Adicionar peça
            </Button>
          </div>

          {parts.length === 0 ? (
            <p className="text-xs text-muted-foreground italic">Nenhuma peça adicionada.</p>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b text-left text-xs text-muted-foreground">
                  <th className="py-2 px-2">Produto</th>
                  <th className="py-2 px-2 w-24">Qtd</th>
                  <th className="py-2 px-2 w-20">Unid.</th>
                  <th className="py-2 px-2 w-28 text-right">Preço un.</th>
                  <th className="py-2 px-2 w-28 text-right">Subtotal</th>
                  <th className="py-2 px-2 w-12"></th>
                </tr>
              </thead>
              <tbody>
                {parts.map((row, idx) => {
                  const variationErr = rowErrors[`${idx}.variation_id`];
                  const qtyErr = rowErrors[`${idx}.quantity`];
                  return (
                    <tr key={idx} className="border-b last:border-b-0 align-top">
                      <td className="py-2 px-2">
                        {row.variation_id ? (
                          <div className="flex items-center justify-between gap-2 rounded-md border border-border bg-muted/40 px-2 py-1.5">
                            <span className="truncate text-sm font-medium">
                              {row.variation_name || `#${row.variation_id}`}
                            </span>
                            <button
                              type="button"
                              onClick={() => updateRow(idx, 'variation_id', null)}
                              aria-label="Trocar produto"
                              className="shrink-0 text-muted-foreground hover:text-foreground"
                            >
                              <X className="h-3.5 w-3.5" />
                            </button>
                          </div>
                        ) : (
                          <PartSearchField
                            onSelect={(part) => onPickPart(idx, part)}
                            ariaLabel={`Buscar produto da linha ${idx + 1}`}
                            invalid={!!variationErr}
                          />
                        )}
                        {variationErr && (
                          <p className="mt-1 text-xs text-destructive" role="alert">
                            {variationErr}
                          </p>
                        )}
                      </td>
                      <td className="py-2 px-2">
                        <Input
                          type="number"
                          variant="shadcn"
                          min="1"
                          step="1"
                          value={row.quantity}
                          onChange={(e) => updateRow(idx, 'quantity', Number(e.target.value))}
                          aria-label={`Quantidade da linha ${idx + 1}`}
                          aria-invalid={!!qtyErr}
                          className="h-8 tabular-nums"
                        />
                        {qtyErr && (
                          <p className="mt-1 text-xs text-destructive" role="alert">
                            {qtyErr}
                          </p>
                        )}
                      </td>
                      <td className="py-2 px-2 text-xs text-muted-foreground">{row.unit ?? '—'}</td>
                      <td className="py-2 px-2 text-right text-sm tabular-nums text-muted-foreground">
                        {row.unit_price ? BRL.format(Number(row.unit_price)) : '—'}
                      </td>
                      <td className="py-2 px-2 text-right text-sm font-medium tabular-nums">
                        {lineSubtotal(row) > 0 ? BRL.format(lineSubtotal(row)) : '—'}
                      </td>
                      <td className="py-2 px-2">
                        <Button
                          type="button"
                          size="sm"
                          variant="ghost"
                          onClick={() => removeRow(idx)}
                          aria-label={`Remover linha ${idx + 1}`}
                          className="h-7 w-7 p-0"
                        >
                          <Trash2 className="h-3 w-3" />
                        </Button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
              {total > 0 && (
                <tfoot>
                  <tr className="border-t">
                    <td className="py-2 px-2 text-right text-xs font-medium text-muted-foreground" colSpan={4}>
                      Total estimado das peças
                    </td>
                    <td className="py-2 px-2 text-right text-sm font-semibold tabular-nums">
                      {BRL.format(total)}
                    </td>
                    <td />
                  </tr>
                </tfoot>
              )}
            </table>
          )}
        </section>

        {hasStatusUpdate && (
          <section className="rounded-lg border bg-card p-4 space-y-3">
            <h2 className="text-sm font-semibold">Atualizar status (pendente do fluxo)</h2>
            <div>
              <Label htmlFor="status_id">Status</Label>
              {/* eslint-disable-next-line no-restricted-syntax -- select nativo simples (status update); estilizado com tokens DS */}
              <select
                id="status_id"
                value={data.status_id as string | number}
                onChange={(e) => setData('status_id', e.target.value)}
                className="w-full rounded-md border px-3 py-2 text-sm"
              >
                <option value="">— Status —</option>
                {Object.entries(status_dropdown).map(([id, name]) => (
                  <option key={id} value={id}>
                    {name}
                  </option>
                ))}
              </select>
            </div>
          </section>
        )}
      </form>
    </AppShellV2>
  );
}
