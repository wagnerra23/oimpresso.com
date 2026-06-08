// ServiceOrderItemFormSheet — drawer lateral 480px pra criar/editar item de OS.
// Wave 5 US-OFICINA-005-bis (depende de PR #1624 backend Wave 1.3).
//
// Consome endpoints:
//   POST   /oficina-auto/ordens-servico/{order}/items          (modo create)
//   PUT    /oficina-auto/ordens-servico/{order}/items/{item}   (modo edit)
//
// Multi-tenant Tier 0 [ADR 0093]: NUNCA injeta business_id no payload.
// Controller deriva de auth()->user(). Payload aqui só carrega
// { tipo, descricao, quantidade, valor_unitario, notes }.
//
// CRÍTICO React 19 — useCallback nos handlers, useMemo no total calculado
// (lição PR #717 SaleSheet/FsmActionPanel: re-render loop em descendentes).
//
// Visual canon ADR 0190 — botão Salvar = PageHeaderPrimary roxo 295 universal
// (não <Button> shadcn cinza). Drawer lateral 480px = padrão form simples
// (760px reservado pra cadastros complexos; ADR 0179 §Drawer-lateral).

import { useCallback, useEffect, useMemo, useState } from 'react';
import { Loader2, Package, Save, UserCog, Wrench, X } from 'lucide-react';
import { toast } from 'sonner';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/Components/ui/sheet';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { PageHeaderPrimary } from '@/Components/PageHeader/PageHeaderPrimary';
import type { ItemTipo, ServiceOrderItemDto } from './ServiceOrderItemRow';

interface Props {
  serviceOrderId: number;
  item?: ServiceOrderItemDto | null;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSaved: (item: ServiceOrderItemDto) => void;
}

const TIPO_OPTIONS: Array<{ value: ItemTipo; label: string; icon: typeof Wrench; hint: string }> = [
  { value: 'peca', label: 'Peça', icon: Package, hint: 'Material físico (cilindro, filtro)' },
  { value: 'mao_obra', label: 'Mão de obra', icon: Wrench, hint: 'Hora trabalhada pelo mecânico' },
  { value: 'servico_terceiro', label: 'Serviço terceiro', icon: UserCog, hint: 'Subcontratado externo' },
];

function getCsrfToken(): string {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta?.getAttribute('content') ?? '';
}

function toFloat(value: string): number {
  if (!value) return 0;
  // Aceita "1,5" (BR) ou "1.5" — normaliza pra ponto
  const normalized = value.replace(/\./g, '').replace(',', '.');
  const n = parseFloat(normalized);
  return Number.isNaN(n) ? 0 : n;
}

function formatBRL(num: number): string {
  return num.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

export default function ServiceOrderItemFormSheet({
  serviceOrderId,
  item,
  open,
  onOpenChange,
  onSaved,
}: Props) {
  const isEdit = !!item;

  const [tipo, setTipo] = useState<ItemTipo>('peca');
  const [descricao, setDescricao] = useState('');
  const [quantidade, setQuantidade] = useState('1');
  const [valorUnitario, setValorUnitario] = useState('');
  const [notes, setNotes] = useState('');
  const [saving, setSaving] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});

  // Reset state ao abrir/trocar item (modo create vs edit).
  useEffect(() => {
    if (!open) return;
    if (item) {
      setTipo(item.tipo);
      setDescricao(item.descricao);
      setQuantidade(String(item.quantidade));
      setValorUnitario(String(item.valor_unitario));
      setNotes(item.notes ?? '');
    } else {
      setTipo('peca');
      setDescricao('');
      setQuantidade('1');
      setValorUnitario('');
      setNotes('');
    }
    setFieldErrors({});
  }, [open, item]);

  const totalCalculado = useMemo(() => {
    return toFloat(quantidade) * toFloat(valorUnitario);
  }, [quantidade, valorUnitario]);

  const handleSubmit = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault();
      setSaving(true);
      setFieldErrors({});

      // Multi-tenant Tier 0 [ADR 0093]: NUNCA enviar business_id (Controller deriva).
      const payload = {
        tipo,
        descricao: descricao.trim(),
        quantidade: toFloat(quantidade),
        valor_unitario: toFloat(valorUnitario),
        notes: notes.trim() || null,
      };

      const url = isEdit
        ? `/oficina-auto/ordens-servico/${serviceOrderId}/items/${item!.id}`
        : `/oficina-auto/ordens-servico/${serviceOrderId}/items`;
      const method = isEdit ? 'PUT' : 'POST';

      try {
        const res = await fetch(url, {
          method,
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify(payload),
        });

        if (res.status === 422) {
          const json = await res.json();
          if (json?.errors) {
            setFieldErrors(json.errors);
            toast.error('Verifique os campos destacados.');
          } else {
            toast.error(json?.message ?? 'Validação falhou.');
          }
          return;
        }
        if (!res.ok) {
          const json = await res.json().catch(() => ({}));
          toast.error(json?.message ?? `Erro HTTP ${res.status}`);
          return;
        }

        const json = (await res.json()) as ServiceOrderItemDto;
        toast.success(isEdit ? 'Item atualizado.' : 'Item adicionado.');
        onSaved(json);
        onOpenChange(false);
      } catch (err) {
        toast.error(err instanceof Error ? err.message : 'Erro de rede.');
      } finally {
        setSaving(false);
      }
    },
    [tipo, descricao, quantidade, valorUnitario, notes, isEdit, item, serviceOrderId, onSaved, onOpenChange],
  );

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent
        side="right"
        className="!w-full sm:!max-w-[480px] flex flex-col p-0 overflow-hidden"
      >
        <SheetHeader className="px-6 pt-6 pb-4 border-b border-border">
          <SheetTitle className="text-lg font-semibold">
            {isEdit ? 'Editar item' : 'Adicionar item'}
          </SheetTitle>
          <SheetDescription className="text-sm text-muted-foreground">
            {isEdit
              ? 'Atualiza descrição, quantidade ou valor do item lançado.'
              : 'Lance peça, mão-de-obra ou serviço terceiro nesta OS.'}
          </SheetDescription>
        </SheetHeader>

        <form
          onSubmit={handleSubmit}
          className="flex-1 flex flex-col overflow-hidden"
          id="service-order-item-form"
        >
          <div className="flex-1 overflow-y-auto px-6 py-5 space-y-5">
            {/* Tipo — radio 3 opções */}
            <div>
              <Label className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground mb-2 block">
                Tipo *
              </Label>
              <div className="grid grid-cols-1 gap-1.5">
                {TIPO_OPTIONS.map((opt) => {
                  const Icon = opt.icon;
                  const selected = tipo === opt.value;
                  return (
                    <button
                      key={opt.value}
                      type="button"
                      onClick={() => setTipo(opt.value)}
                      className={
                        'flex items-center gap-3 rounded-md border px-3 py-2 text-left transition-all ' +
                        (selected
                          ? 'border-foreground bg-foreground/5'
                          : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50')
                      }
                      aria-pressed={selected}
                    >
                      <Icon size={16} className={selected ? 'text-foreground' : 'text-muted-foreground'} />
                      <div className="min-w-0 flex-1">
                        <div className="text-sm font-medium text-foreground">{opt.label}</div>
                        <div className="text-[11px] text-muted-foreground">{opt.hint}</div>
                      </div>
                      <span
                        className={
                          'h-3.5 w-3.5 rounded-full border-2 shrink-0 ' +
                          (selected ? 'border-foreground bg-foreground' : 'border-slate-300')
                        }
                        aria-hidden
                      />
                    </button>
                  );
                })}
              </div>
              {fieldErrors.tipo && (
                <p className="text-xs text-destructive mt-1">{fieldErrors.tipo[0]}</p>
              )}
            </div>

            {/* Descrição */}
            <div>
              <Label htmlFor="item-descricao" className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                Descrição *
              </Label>
              <Input
                id="item-descricao"
                type="text"
                maxLength={150}
                value={descricao}
                onChange={(e) => setDescricao(e.target.value)}
                placeholder="Ex: Cilindro hidráulico 6.5T"
                required
                autoFocus={!isEdit}
                className="mt-1"
              />
              {fieldErrors.descricao && (
                <p className="text-xs text-destructive mt-1">{fieldErrors.descricao[0]}</p>
              )}
            </div>

            {/* Quantidade + Valor unitário */}
            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label htmlFor="item-quantidade" className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                  Quantidade *
                </Label>
                <Input
                  id="item-quantidade"
                  type="number"
                  step="0.001"
                  min="0.001"
                  value={quantidade}
                  onChange={(e) => setQuantidade(e.target.value)}
                  required
                  className="mt-1 tabular-nums"
                />
                {fieldErrors.quantidade && (
                  <p className="text-xs text-destructive mt-1">{fieldErrors.quantidade[0]}</p>
                )}
              </div>
              <div>
                <Label htmlFor="item-valor-unitario" className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                  Valor unit. (R$) *
                </Label>
                <Input
                  id="item-valor-unitario"
                  type="number"
                  step="0.01"
                  min="0"
                  value={valorUnitario}
                  onChange={(e) => setValorUnitario(e.target.value)}
                  placeholder="0,00"
                  required
                  className="mt-1 tabular-nums"
                />
                {fieldErrors.valor_unitario && (
                  <p className="text-xs text-destructive mt-1">{fieldErrors.valor_unitario[0]}</p>
                )}
              </div>
            </div>

            {/* Total calc client-side (readonly visual) */}
            <div className="rounded-md border border-emerald-200 bg-emerald-50/60 px-3 py-2 flex items-center justify-between">
              <span className="text-[10.5px] font-semibold uppercase tracking-wider text-emerald-800">
                Total
              </span>
              <span className="text-base tabular-nums font-semibold text-emerald-800">
                {formatBRL(totalCalculado)}
              </span>
            </div>

            {/* Notes opcional */}
            <div>
              <Label htmlFor="item-notes" className="text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">
                Observação (opcional)
              </Label>
              <textarea
                id="item-notes"
                rows={3}
                maxLength={1000}
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                placeholder="Detalhes adicionais sobre o item…"
                className="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
              />
              {fieldErrors.notes && (
                <p className="text-xs text-destructive mt-1">{fieldErrors.notes[0]}</p>
              )}
            </div>
          </div>

          {/* Footer sticky com ações */}
          <div className="border-t border-border px-6 py-3 bg-background flex items-center justify-end gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => onOpenChange(false)}
              disabled={saving}
            >
              <X className="size-4 mr-1" />
              Cancelar
            </Button>
            {saving ? (
              <Button type="button" size="sm" disabled>
                <Loader2 className="size-4 mr-1 animate-spin" />
                Salvando…
              </Button>
            ) : (
              <PageHeaderPrimary
                label={isEdit ? 'Salvar' : 'Adicionar'}
                icon={Save}
                onClick={() => {
                  // PageHeaderPrimary renderiza <button type="button"> — disparamos submit
                  // do form ancestral via id estável. Garante validação HTML5 dos required.
                  const formEl = document.getElementById(
                    'service-order-item-form',
                  ) as HTMLFormElement | null;
                  if (formEl) formEl.requestSubmit();
                }}
              />
            )}
          </div>
        </form>
      </SheetContent>
    </Sheet>
  );
}
