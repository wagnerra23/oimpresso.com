// Drawer "Novo plano" / "Editar plano" — forma do protótipo `cobranca-recorrente-page.jsx`
// (`PlanoForm` sobre `FormDrawer`, vista rb-planos, ROTAS["rb-planos"].drawers).
// Charter: ../Planos/Create.charter.md · ../Planos/Edit.charter.md
// Grava pelos endpoints de sempre: POST /recurring-billing/planos · PUT /recurring-billing/planos/{id}.
// Multi-tenant Tier 0: o servidor resolve o business pela sessão; nada de business_id aqui.

import { useForm } from '@inertiajs/react';
import { useEffect, useRef, type FormEvent, type ReactNode } from 'react';
import { Sheet, SheetContent, SheetDescription, SheetTitle } from '@/Components/ui/sheet';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';

export type Ciclo = 'monthly' | 'quarterly' | 'semiannual' | 'yearly' | 'custom';
export type FiscalType = 'nfe' | 'nfse' | 'none';

export interface PlanoFormData {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  descricao_curta: string | null;
  valor: number;
  ciclo: Ciclo;
  ciclo_dias: number | null;
  trial_days: number;
  ativo: boolean;
  fiscal_type: FiscalType;
  fiscal_cfop: string | null;
  fiscal_servico: string | null;
}

export interface PlanoDefaults {
  ciclo: Ciclo;
  trial_days: number;
  ativo: boolean;
  fiscal_type: FiscalType;
}

export type PlanoDrawerModo = { modo: 'novo'; defaults?: PlanoDefaults } | { modo: 'editar'; plan: PlanoFormData };

const CICLO_OPTIONS: Array<{ value: Ciclo; label: string }> = [
  { value: 'monthly', label: 'Mensal' },
  { value: 'quarterly', label: 'Trimestral' },
  { value: 'semiannual', label: 'Semestral' },
  { value: 'yearly', label: 'Anual' },
  { value: 'custom', label: 'Customizado (dias)' },
];

const FISCAL_OPTIONS: Array<{ value: FiscalType; label: string }> = [
  { value: 'none', label: 'Não emite nota fiscal' },
  { value: 'nfe', label: 'NFe (produto)' },
  { value: 'nfse', label: 'NFS-e (serviço)' },
];

const DEFAULTS: PlanoDefaults = { ciclo: 'monthly', trial_days: 0, ativo: true, fiscal_type: 'none' };

// Campo do protótipo (FIELD): 32px, raio 6, fundo da superfície, 13px.
const CAMPO =
  'h-8 w-full rounded-[6px] border border-border bg-card px-2.5 text-[13px] text-foreground outline-none focus:border-primary focus:ring-2 focus:ring-primary/20';

export default function PlanoFormDrawer({ alvo, onClose }: { alvo: PlanoDrawerModo; onClose: () => void }) {
  const plan = alvo.modo === 'editar' ? alvo.plan : null;
  const d = alvo.modo === 'novo' ? (alvo.defaults ?? DEFAULTS) : DEFAULTS;

  const form = useForm({
    name: plan?.name ?? '',
    slug: plan?.slug ?? '',
    descricao_curta: plan?.descricao_curta ?? '',
    description: plan?.description ?? '',
    valor: plan?.valor ?? 0,
    ciclo: plan?.ciclo ?? d.ciclo,
    ciclo_dias: plan?.ciclo_dias ?? 30,
    trial_days: plan?.trial_days ?? d.trial_days,
    ativo: plan?.ativo ?? d.ativo,
    fiscal_type: plan?.fiscal_type ?? d.fiscal_type,
    fiscal_cfop: plan?.fiscal_cfop ?? '',
    fiscal_servico: plan?.fiscal_servico ?? '',
  });

  const formRef = useRef<HTMLFormElement>(null);

  function submit(e: FormEvent) {
    e.preventDefault();
    // preserveState só com erro: no sucesso o servidor redireciona para a lista e o drawer fecha.
    const opts = { preserveScroll: true, preserveState: 'errors' as const };
    if (plan) form.put(`/recurring-billing/planos/${plan.id}`, opts);
    else form.post('/recurring-billing/planos', opts);
  }

  // ⌘↵ / Ctrl+Enter salva (o Esc já é do Sheet).
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
        e.preventDefault();
        formRef.current?.requestSubmit();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  const slugMudou = !!plan && form.data.slug !== plan.slug;
  const titulo = plan ? 'Editar plano' : 'Novo plano';

  return (
    <Sheet open onOpenChange={(o) => !o && onClose()}>
      <SheetContent
        side="right"
        showCloseButton={false}
        className="w-full max-w-[92vw] gap-0 border-l border-border bg-card p-0 sm:max-w-[560px]"
        data-contract="drawer-plano"
      >
        <form ref={formRef} onSubmit={submit} className="flex h-full flex-col">
          <div className="flex h-[58px] shrink-0 items-center gap-3 border-b border-border px-[18px]">
            <div className="min-w-0 flex-1">
              <div className="text-[10.5px] font-medium uppercase tracking-[0.08em] text-muted-foreground">Plano</div>
              <SheetTitle className="mt-px truncate text-sm font-semibold text-foreground">{titulo}</SheetTitle>
              <SheetDescription className="mt-0.5 text-xs text-muted-foreground">
                {plan ? `${plan.name} · #${plan.id}` : 'Cadastrar plano recorrente'}
              </SheetDescription>
            </div>
          </div>

          <div className="flex flex-1 flex-col gap-[18px] overflow-y-auto p-[18px]">
            <Secao titulo="Identificação">
              <Campo label="Nome do plano" req erro={form.errors.name}>
                <input className={CAMPO} value={form.data.name} onChange={(e) => form.setData('name', e.target.value)}
                  placeholder="Ex: Sinalização Mensal" maxLength={150} required autoFocus />
              </Campo>
              <Campo label="Slug" erro={form.errors.slug}
                dica={slugMudou ? 'Atenção: alterar o slug pode quebrar links e integrações que usam o plano antigo.' : 'Gerado do nome se ficar vazio.'}>
                <input className={`${CAMPO} font-mono ${slugMudou ? 'border-warning' : ''}`} value={form.data.slug}
                  onChange={(e) => form.setData('slug', e.target.value.toLowerCase())} maxLength={80} />
              </Campo>
              <Campo label="Descrição curta" erro={form.errors.descricao_curta}>
                <input className={CAMPO} value={form.data.descricao_curta}
                  onChange={(e) => form.setData('descricao_curta', e.target.value)} maxLength={200} />
              </Campo>
              <Campo label="Descrição completa" erro={form.errors.description}>
                <textarea className={`${CAMPO} h-16 resize-y py-1.5`} value={form.data.description}
                  onChange={(e) => form.setData('description', e.target.value)} maxLength={2000} />
              </Campo>
            </Secao>

            <Secao titulo="Cobrança">
              <div className="grid grid-cols-2 gap-3">
                <Campo label="Valor (R$)" req erro={form.errors.valor}>
                  <input className={`${CAMPO} font-mono tabular-nums`} type="number" step="0.01" min="0" required
                    value={form.data.valor} onChange={(e) => form.setData('valor', parseFloat(e.target.value) || 0)} />
                </Campo>
                <Campo label="Ciclo" req erro={form.errors.ciclo}>
                  <Select value={form.data.ciclo} onValueChange={(v) => form.setData('ciclo', v as Ciclo)}>
                    <SelectTrigger variant="shadcn" className={CAMPO} aria-label="Ciclo"><SelectValue /></SelectTrigger>
                    <SelectContent>
                      {CICLO_OPTIONS.map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}
                    </SelectContent>
                  </Select>
                </Campo>
                {form.data.ciclo === 'custom' && (
                  <Campo label="Ciclo (dias)" req erro={form.errors.ciclo_dias} dica="Entre 1 e 365.">
                    <input className={`${CAMPO} font-mono tabular-nums`} type="number" min={1} max={365}
                      value={form.data.ciclo_dias} onChange={(e) => form.setData('ciclo_dias', parseInt(e.target.value, 10) || 0)} />
                  </Campo>
                )}
                <Campo label="Trial (dias)" erro={form.errors.trial_days} dica="Entre 0 e 90.">
                  <input className={`${CAMPO} font-mono tabular-nums`} type="number" min={0} max={90}
                    value={form.data.trial_days} onChange={(e) => form.setData('trial_days', parseInt(e.target.value, 10) || 0)} />
                </Campo>
              </div>
              <label className="flex items-center gap-2 text-[13px] text-muted-foreground">
                <input type="checkbox" checked={form.data.ativo} onChange={(e) => form.setData('ativo', e.target.checked)} />
                Plano ativo (disponível pra novas assinaturas)
              </label>
            </Secao>

            <Secao titulo="Emissão fiscal">
              <Campo label="Tipo" erro={form.errors.fiscal_type}>
                <Select value={form.data.fiscal_type} onValueChange={(v) => form.setData('fiscal_type', v as FiscalType)}>
                  <SelectTrigger variant="shadcn" className={CAMPO} aria-label="Tipo de emissão fiscal"><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {FISCAL_OPTIONS.map((o) => <SelectItem key={o.value} value={o.value}>{o.label}</SelectItem>)}
                  </SelectContent>
                </Select>
              </Campo>
              {form.data.fiscal_type === 'nfe' && (
                <Campo label="CFOP" req erro={form.errors.fiscal_cfop} dica="Ex: 5101 (venda de produção própria).">
                  <input className={`${CAMPO} font-mono`} value={form.data.fiscal_cfop} maxLength={8}
                    onChange={(e) => form.setData('fiscal_cfop', e.target.value)} />
                </Campo>
              )}
              {form.data.fiscal_type === 'nfse' && (
                <Campo label="Código de serviço" req erro={form.errors.fiscal_servico} dica="Item da lista de serviços (LC 116/2003).">
                  <input className={`${CAMPO} font-mono`} value={form.data.fiscal_servico} maxLength={8}
                    onChange={(e) => form.setData('fiscal_servico', e.target.value)} />
                </Campo>
              )}
            </Secao>
          </div>

          <div className="flex h-[60px] shrink-0 items-center gap-2 border-t border-border px-[18px]">
            <span className="flex-1" />
            <Button type="button" variant="outline" onClick={onClose}
              className="h-8 rounded-[7px] px-3 text-[12.5px] font-medium shadow-none">
              Cancelar <kbd className="font-mono text-[10px] text-muted-foreground">Esc</kbd>
            </Button>
            <Button type="submit" disabled={form.processing || !form.data.name}
              className="h-8 rounded-[7px] px-3 text-[12.5px] font-medium">
              {form.processing ? 'Salvando…' : plan ? 'Salvar alterações' : 'Salvar plano'}
              <kbd className="font-mono text-[10px] opacity-80">⌘↵</kbd>
            </Button>
          </div>
        </form>
      </SheetContent>
    </Sheet>
  );
}

// Seção do protótipo (`cr-card`): borda de 1px, raio 9, padding 12×14, grid com gap 12;
// rótulo `cr-blk-label` 10.5px/500 em caixa alta, tracking 0.08em.
function Secao({ titulo, children }: { titulo: string; children: ReactNode }) {
  return (
    <section className="grid gap-3 rounded-[9px] border border-border px-3.5 py-3">
      <div className="mb-0 text-[10.5px] font-medium uppercase tracking-[0.08em] text-muted-foreground">{titulo}</div>
      {children}
    </section>
  );
}

function Campo({ label, req, erro, dica, children }: { label: string; req?: boolean; erro?: string; dica?: string; children: ReactNode }) {
  return (
    <label className="block">
      <span className="mb-1 block text-[11.5px] font-medium text-muted-foreground">
        {label}
        {req && <span className="text-primary"> *</span>}
      </span>
      {children}
      {dica && !erro && <div className="mt-[3px] text-[11px] text-muted-foreground">{dica}</div>}
      {erro && <div className="mt-[3px] text-[11px] font-medium text-destructive-fg">{erro}</div>}
    </label>
  );
}
