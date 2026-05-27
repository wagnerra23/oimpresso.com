// TituloCreateSheet — Onda 25 (2026-05-25) US-FIN-021 Insert manual de título.
//
// Sheet drawer inline pra criar novo título a receber OU a pagar. Tipo
// pré-fixado pela origem do click no DropdownMenu "+ Novo título" do header
// (Novo recebimento → tipo='receber' · Novo pagamento → tipo='pagar').
//
// Plano de Contas combobox filtra DCASP por tipo do título — Eliana não vê
// 50+ contas misturadas, só receita/ativo (receber) ou despesa/custo/passivo
// (pagar). Backend defesa em profundidade via assertPlanoCoerente.
//
// Substitui o stub /unificado/novo (Non-Goal #1 do charter v6).

import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { TrendingUp, TrendingDown } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Sheet, SheetContent, SheetTitle, SheetDescription } from '@/Components/ui/sheet';
import { PlanoContaCombobox, type PlanoConta } from './PlanoContaCombobox';

// PR I (2026-05-25) G5 — sugestão de valor último/médio por contraparte.
interface ValorSugerido {
  count: number;
  ultimo_valor: number | null;
  media_valor: number | null;
}

interface Categoria {
  id: number;
  nome: string;
}

interface TituloCreateSheetProps {
  open: boolean;
  onClose: () => void;
  tipo: 'receber' | 'pagar';
  categorias: Categoria[];
  planos: PlanoConta[];
}

const hojeIso = () => new Date().toISOString().slice(0, 10);

export function TituloCreateSheet({ open, onClose, tipo, categorias, planos }: TituloCreateSheetProps) {
  const kind = tipo === 'receber' ? 'receivable' : 'payable';
  const accentHue = tipo === 'receber' ? 145 : 25; // verde / rose

  const [sugestao, setSugestao] = useState<ValorSugerido | null>(null);

  const form = useForm({
    tipo,
    cliente_descricao: '',
    observacoes: '',
    categoria_id: '' as number | '',
    plano_conta_id: null as number | null,
    vencimento: hojeIso(),
    valor_total: 0,
  });

  // Reset quando trocar tipo (usuário fechou drawer e abriu outro).
  useEffect(() => {
    form.setData({
      tipo,
      cliente_descricao: '',
      observacoes: '',
      categoria_id: '' as number | '',
      plano_conta_id: null as number | null,
      vencimento: hojeIso(),
      valor_total: 0,
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tipo, open]);

  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    const data: Record<string, unknown> = {
      tipo: form.data.tipo,
      cliente_descricao: form.data.cliente_descricao || null,
      observacoes: form.data.observacoes || null,
      categoria_id: form.data.categoria_id === '' ? null : form.data.categoria_id,
      plano_conta_id: form.data.plano_conta_id ?? null,
      vencimento: form.data.vencimento,
      valor_total: form.data.valor_total,
    };
    form.post('/financeiro/unificado', {
      ...({ data } as any),
      preserveScroll: true,
      onSuccess: () => onClose(),
    });
  };

  const titulo = tipo === 'receber' ? 'Nova conta a receber' : 'Nova conta a pagar';
  const Icon = tipo === 'receber' ? TrendingUp : TrendingDown;

  return (
    <Sheet open={open} onOpenChange={(o) => !o && onClose()}>
      <SheetContent side="right" className="w-[460px] sm:max-w-[460px] flex flex-col p-0">
        <header className="os-drawer-head">
          <div className="os-drawer-head-l">
            <SheetTitle asChild>
              <h2 className="m-0 flex items-center gap-2">
                <Icon size={16} style={{ color: `oklch(0.55 0.15 ${accentHue})` }} />
                {titulo}
              </h2>
            </SheetTitle>
            <SheetDescription asChild>
              <p>Lançamento manual · numero gerado automaticamente</p>
            </SheetDescription>
          </div>
        </header>

        <form onSubmit={submit} className="os-drawer-body p-5 space-y-4 text-[13px] flex-1 overflow-y-auto">
          <div className="space-y-1.5">
            <label htmlFor="cr-desc" className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">
              Descrição / contraparte
            </label>
            <Input
              id="cr-desc"
              value={form.data.cliente_descricao}
              onChange={(e) => form.setData('cliente_descricao', e.target.value)}
              onBlur={async (e) => {
                const val = e.target.value.trim();
                if (val.length < 3) { setSugestao(null); return; }
                try {
                  const r = await fetch(`/financeiro/unificado/sugerir-valor?contraparte=${encodeURIComponent(val)}&tipo=${tipo}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                  });
                  if (! r.ok) return;
                  const data: ValorSugerido = await r.json();
                  if (data.count > 0) setSugestao(data);
                } catch { /* silent */ }
              }}
              placeholder={tipo === 'receber' ? 'Ex: Cliente João Silva · sublocação' : 'Ex: Fornecedor papelaria XYZ'}
              maxLength={255}
              autoFocus
            />
            {form.errors.cliente_descricao && (
              <p className="text-[11px] text-destructive">{form.errors.cliente_descricao}</p>
            )}
            {/* PR I G5 — sugestão de valor baseada em histórico contraparte */}
            {sugestao && sugestao.ultimo_valor !== null && (
              <p className="text-[11px] text-muted-foreground">
                Histórico: {sugestao.count} lançamento{sugestao.count > 1 ? 's' : ''} ·
                último <b>R$ {sugestao.ultimo_valor.toFixed(2)}</b> ·
                média <b>R$ {sugestao.media_valor?.toFixed(2)}</b>
                <button
                  type="button"
                  className="ml-2 underline hover:text-foreground"
                  onClick={() => form.setData('valor_total', sugestao.ultimo_valor ?? 0)}
                >
                  usar último
                </button>
              </p>
            )}
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1.5">
              <label htmlFor="cr-valor" className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">
                Valor (R$)
              </label>
              <Input
                id="cr-valor"
                type="number"
                step="0.01"
                min="0.01"
                value={form.data.valor_total || ''}
                onChange={(e) => form.setData('valor_total', parseFloat(e.target.value) || 0)}
                placeholder="0,00"
              />
              {form.errors.valor_total && (
                <p className="text-[11px] text-destructive">{form.errors.valor_total}</p>
              )}
            </div>

            <div className="space-y-1.5">
              <label htmlFor="cr-venc" className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">
                Vencimento
              </label>
              <Input
                id="cr-venc"
                type="date"
                value={form.data.vencimento}
                onChange={(e) => form.setData('vencimento', e.target.value)}
              />
              {form.errors.vencimento && (
                <p className="text-[11px] text-destructive">{form.errors.vencimento}</p>
              )}
            </div>
          </div>

          <div className="space-y-1.5">
            <label htmlFor="cr-cat" className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">
              Categoria
            </label>
            <select
              id="cr-cat"
              value={form.data.categoria_id as string | number}
              onChange={(e) => form.setData('categoria_id', e.target.value as unknown as number | '')}
              className="w-full h-9 rounded-md border border-input bg-background px-3 text-[13px]"
            >
              <option value="">(Sem categoria)</option>
              {categorias.map((c) => (
                <option key={c.id} value={c.id}>{c.nome}</option>
              ))}
            </select>
            {form.errors.categoria_id && (
              <p className="text-[11px] text-destructive">{form.errors.categoria_id}</p>
            )}
          </div>

          <div className="space-y-1.5">
            <label htmlFor="cr-plano" className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">
              Plano de contas
            </label>
            <PlanoContaCombobox
              id="cr-plano"
              planos={planos}
              kind={kind}
              value={form.data.plano_conta_id}
              onChange={(id) => form.setData('plano_conta_id', id)}
              placeholder={tipo === 'receber' ? 'Receita ou ativo' : 'Despesa, custo ou passivo'}
            />
            <p className="text-[11px] text-muted-foreground">
              Opcional. Se vazio, BackfillPlanoContaCommand atribui &quot;(a classificar)&quot; depois.
            </p>
            {form.errors.plano_conta_id && (
              <p className="text-[11px] text-destructive">{form.errors.plano_conta_id}</p>
            )}
          </div>

          <div className="space-y-1.5">
            <label htmlFor="cr-obs" className="text-[11px] uppercase tracking-widest text-muted-foreground font-medium">
              Observações
            </label>
            <textarea
              id="cr-obs"
              value={form.data.observacoes}
              onChange={(e) => form.setData('observacoes', e.target.value)}
              maxLength={2000}
              rows={3}
              className="w-full rounded-md border border-input bg-background px-3 py-2 text-[13px] resize-none"
              placeholder="Notas internas (opcional)…"
            />
            {form.errors.observacoes && (
              <p className="text-[11px] text-destructive">{form.errors.observacoes}</p>
            )}
          </div>

          <div className="flex gap-2 pt-3 border-t border-border mt-auto">
            <Button
              type="submit"
              disabled={form.processing}
              style={{ backgroundColor: `oklch(0.55 0.15 ${accentHue})`, color: 'oklch(0.99 0 0)' }}
            >
              {form.processing ? 'Salvando…' : `Criar ${tipo === 'receber' ? 'recebimento' : 'pagamento'}`}
            </Button>
            <Button type="button" variant="outline" onClick={onClose} disabled={form.processing}>
              Cancelar
            </Button>
          </div>
        </form>
      </SheetContent>
    </Sheet>
  );
}

export default TituloCreateSheet;
