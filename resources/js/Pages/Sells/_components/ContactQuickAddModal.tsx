// US-SELL-PARIDADE P0-1 — Botão (+) cadastrar cliente inline em Sells/Create.
//
// Gap P0 catalogado em RUNBOOK-paridade-create.md §5.1: Blade tem
// add_new_customer (+) abrindo contact_modal; Inertia só tinha postMessage
// listener (Create.tsx:555-566) sem botão visível → Lara forçada a abrir
// /contacts/create-page em nova aba (interrompe fluxo, viola pain #1
// "velocidade pra abrir uma venda" Martinho reunião 13/maio).
//
// Estratégia:
//   - Modal Dialog (Radix shadcn) com 5-6 campos mínimos paridade Blade.
//   - POST /contacts via fetch (mesma rota legacy ContactController@store).
//     NÃO usar router.post — full Inertia visit reseta form da venda.
//   - onContactCreated(contact) callback → parent auto-seleciona no autocomplete.
//   - Atalho Esc fecha (default Dialog) · Ctrl/Cmd+S submete.
//   - Validação client-side mínima (nome obrigatório, telefone obrigatório,
//     CPF/CNPJ valida dígito se preenchido). Backend re-valida.
//
// Refs:
//   - RUNBOOK-paridade-create.md §5.1 (P0-1)
//   - resources/views/contact/create.blade.php (modal Blade legacy — fonte campos)
//   - resources/js/Pages/Crm/Contacts/Create.tsx (mascaras + validadores reusados)
//   - app/Http/Controllers/ContactController.php@store (endpoint reusado)
//   - app/Utils/ContactUtil.php@createNewContact (retorna {success, data, msg})
//   - ADR 0093 multi-tenant Tier 0 (business_id vem da session backend)

import { useCallback, useEffect, useMemo, useState, type FormEvent } from 'react';
import { Loader2, Save, UserPlus, X } from 'lucide-react';

import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

// ── Types ────────────────────────────────────────────────────────────────────

export type ContactType = 'customer' | 'supplier' | 'both';
export type PersonType = 'individual' | 'business';

export interface CreatedContact {
  id: number;
  name: string;
  mobile?: string | null;
  contact_id?: string | null;
}

interface Props {
  /** Controla visibilidade — Sells/Create gerencia state. */
  open: boolean;
  onClose: () => void;
  /**
   * Chamado após sucesso. Parent (Sells/Create) usa pra setar contact_id
   * + forçar valor no CustomerSearchAutocomplete (mesmo mecanismo do
   * postMessage listener existente).
   */
  onContactCreated: (contact: CreatedContact) => void;
  /**
   * Pré-preenche nome — se vier da query do autocomplete ("cadastrar 'X'").
   * Sells/Create por enquanto abre vazio; campo é opcional pra futuro.
   */
  prefillName?: string;
  /**
   * Tipo default. Em Sells/Create sempre customer. Mantido configurável
   * caso outro lugar reuse (purchase flow → supplier).
   */
  defaultType?: ContactType;
}

// ── CPF/CNPJ validators (espelha Crm/Contacts/Create — UI hint apenas) ──────

function digitsOnly(s: string): string {
  return (s || '').replace(/\D/g, '');
}

function isValidCpf(raw: string): boolean {
  const d = digitsOnly(raw);
  if (d.length !== 11 || /^(\d)\1+$/.test(d)) return false;
  let sum = 0;
  for (let i = 0; i < 9; i++) sum += parseInt(d[i], 10) * (10 - i);
  let rev = 11 - (sum % 11);
  if (rev >= 10) rev = 0;
  if (rev !== parseInt(d[9], 10)) return false;
  sum = 0;
  for (let i = 0; i < 10; i++) sum += parseInt(d[i], 10) * (11 - i);
  rev = 11 - (sum % 11);
  if (rev >= 10) rev = 0;
  return rev === parseInt(d[10], 10);
}

function isValidCnpj(raw: string): boolean {
  const d = digitsOnly(raw);
  if (d.length !== 14 || /^(\d)\1+$/.test(d)) return false;
  const calc = (base: string, factors: number[]): number => {
    const sum = factors.reduce((acc, f, i) => acc + parseInt(base[i], 10) * f, 0);
    const rev = sum % 11;
    return rev < 2 ? 0 : 11 - rev;
  };
  const f1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
  const f2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
  const v1 = calc(d, f1);
  if (v1 !== parseInt(d[12], 10)) return false;
  const v2 = calc(d, f2);
  return v2 === parseInt(d[13], 10);
}

function maskTaxNumber(raw: string): string {
  const d = digitsOnly(raw);
  if (d.length <= 11) {
    return d
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
  }
  return d
    .slice(0, 14)
    .replace(/(\d{2})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d)/, '$1.$2')
    .replace(/(\d{3})(\d)/, '$1/$2')
    .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
}

function maskPhone(raw: string): string {
  const d = digitsOnly(raw).slice(0, 11);
  if (d.length <= 10) {
    return d
      .replace(/(\d{2})(\d)/, '($1) $2')
      .replace(/(\d{4})(\d{1,4})$/, '$1-$2');
  }
  return d
    .replace(/(\d{2})(\d)/, '($1) $2')
    .replace(/(\d{5})(\d{1,4})$/, '$1-$2');
}

// ── Component ────────────────────────────────────────────────────────────────

interface FormState {
  type: ContactType;
  contact_type_radio: PersonType;
  supplier_business_name: string;
  first_name: string;
  tax_number: string;
  mobile: string;
  email: string;
  city: string;
  state: string;
}

const EMPTY_FORM: FormState = {
  type: 'customer',
  contact_type_radio: 'individual',
  supplier_business_name: '',
  first_name: '',
  tax_number: '',
  mobile: '',
  email: '',
  city: '',
  state: '',
};

export default function ContactQuickAddModal({
  open,
  onClose,
  onContactCreated,
  prefillName,
  defaultType = 'customer',
}: Props) {
  const [form, setForm] = useState<FormState>(() => ({
    ...EMPTY_FORM,
    type: defaultType,
    first_name: prefillName ?? '',
  }));
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [globalError, setGlobalError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  // Reset ao abrir/fechar pra não vazar state entre cadastros consecutivos.
  useEffect(() => {
    if (open) {
      setForm({
        ...EMPTY_FORM,
        type: defaultType,
        first_name: prefillName ?? '',
      });
      setErrors({});
      setGlobalError(null);
      setSubmitting(false);
    }
  }, [open, prefillName, defaultType]);

  const update = useCallback(<K extends keyof FormState>(field: K, value: FormState[K]) => {
    setForm((prev) => ({ ...prev, [field]: value }));
    setErrors((prev) => {
      if (!prev[field as string]) return prev;
      const next = { ...prev };
      delete next[field as string];
      return next;
    });
  }, []);

  // Auto-derive contact_type_radio do tax_number digits (UI hint).
  useEffect(() => {
    const d = digitsOnly(form.tax_number);
    if (d.length === 11 && form.contact_type_radio !== 'individual') {
      setForm((p) => ({ ...p, contact_type_radio: 'individual' }));
    } else if (d.length === 14 && form.contact_type_radio !== 'business') {
      setForm((p) => ({ ...p, contact_type_radio: 'business' }));
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [form.tax_number]);

  const taxValid = useMemo(() => {
    if (!form.tax_number) return true;
    const d = digitsOnly(form.tax_number);
    if (d.length === 11) return isValidCpf(d);
    if (d.length === 14) return isValidCnpj(d);
    return false;
  }, [form.tax_number]);

  const validate = (): boolean => {
    const e: Record<string, string> = {};
    const nameOk = form.first_name.trim() || form.supplier_business_name.trim();
    if (!nameOk) {
      e.first_name = 'Informe o nome ou razão social.';
    }
    if (!form.mobile.trim()) {
      e.mobile = 'Telefone celular é obrigatório.';
    }
    if (form.email && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(form.email)) {
      e.email = 'Email inválido.';
    }
    if (form.tax_number && !taxValid) {
      const len = digitsOnly(form.tax_number).length;
      e.tax_number = len === 11 ? 'CPF inválido.' : len === 14 ? 'CNPJ inválido.' : 'CPF/CNPJ deve ter 11 ou 14 dígitos.';
    }
    setErrors(e);
    return Object.keys(e).length === 0;
  };

  const handleSubmit = async (ev?: FormEvent) => {
    ev?.preventDefault();
    if (submitting) return;
    setGlobalError(null);
    if (!validate()) return;
    setSubmitting(true);
    try {
      const meta = document.querySelector('meta[name="csrf-token"]');
      const csrf = meta?.getAttribute('content') ?? '';
      // Payload espelhando o que ContactController@store espera (Form Request keys
      // em sell/create.blade.php contact_modal + create.blade.php). Backend faz o
      // resto: trim, business_id da session, contact_id gerado se vazio.
      const payload: Record<string, unknown> = {
        type: form.type,
        contact_type_radio: form.contact_type_radio,
        supplier_business_name: form.supplier_business_name,
        first_name: form.first_name,
        middle_name: '',
        last_name: '',
        prefix: '',
        tax_number: digitsOnly(form.tax_number),
        mobile: form.mobile,
        landline: '',
        alternate_number: '',
        email: form.email,
        city: form.city,
        state: form.state,
        country: 'Brasil',
        contact_id: '',
        opening_balance: 0,
      };
      const res = await fetch('/contacts', {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrf,
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
      });
      const json = await res.json().catch(() => ({}));
      if (!res.ok || json?.success === false) {
        setGlobalError(json?.msg ?? 'Falha ao salvar contato.');
        return;
      }
      // ContactUtil@createNewContact retorna {success, data: Contact, msg}.
      // Contact model serializa com todos campos — pegamos só id/name/mobile.
      const created = (json?.data ?? {}) as Partial<CreatedContact> & { id?: number; name?: string };
      if (!created?.id || !created?.name) {
        setGlobalError('Contato criado mas resposta inesperada. Recarregue a página.');
        return;
      }
      onContactCreated({
        id: created.id,
        name: created.name,
        mobile: created.mobile ?? null,
        contact_id: created.contact_id ?? null,
      });
      onClose();
    } catch (err) {
      setGlobalError('Erro de rede: ' + String((err as Error)?.message || err));
    } finally {
      setSubmitting(false);
    }
  };

  // Cmd/Ctrl+S submete enquanto modal aberto. Esc é nativo do Radix Dialog.
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 's') {
        e.preventDefault();
        handleSubmit();
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, form, submitting]);

  return (
    <Dialog open={open} onOpenChange={(o) => { if (!o) onClose(); }}>
      <DialogContent className="sm:max-w-xl" data-testid="contact-quick-add-modal">
        <form onSubmit={handleSubmit}>
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <UserPlus className="h-5 w-5 text-primary" />
              Cadastrar novo cliente
            </DialogTitle>
            <DialogDescription>
              Cadastro rápido — só os essenciais. Demais campos depois em Contatos.
            </DialogDescription>
          </DialogHeader>

          <div className="mt-4 space-y-4">
            {globalError && (
              <div
                className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-300"
                role="alert"
              >
                {globalError}
              </div>
            )}

            {/* Tipo (customer/supplier/both) */}
            <div>
              <Label className="text-xs font-medium text-foreground">Tipo</Label>
              <div className="flex items-center gap-2 mt-2 flex-wrap">
                {([
                  { value: 'customer', label: 'Cliente' },
                  { value: 'supplier', label: 'Fornecedor' },
                  { value: 'both', label: 'Ambos' },
                ] as Array<{ value: ContactType; label: string }>).map((opt) => {
                  const isActive = form.type === opt.value;
                  return (
                    <button
                      key={opt.value}
                      type="button"
                      onClick={() => update('type', opt.value)}
                      className={
                        'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium transition-colors ' +
                        (isActive
                          ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'
                          : 'bg-muted/40 text-muted-foreground hover:bg-muted hover:text-foreground')
                      }
                      aria-pressed={isActive}
                    >
                      {opt.label}
                    </button>
                  );
                })}
              </div>
            </div>

            {/* Pessoa física / jurídica */}
            <div>
              <Label className="text-xs font-medium text-foreground">Pessoa</Label>
              <div className="flex items-center gap-2 mt-2 flex-wrap">
                {(['individual', 'business'] as PersonType[]).map((pt) => {
                  const isActive = form.contact_type_radio === pt;
                  return (
                    <button
                      key={pt}
                      type="button"
                      onClick={() => update('contact_type_radio', pt)}
                      className={
                        'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium transition-colors ' +
                        (isActive
                          ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'
                          : 'bg-muted/40 text-muted-foreground hover:bg-muted hover:text-foreground')
                      }
                      aria-pressed={isActive}
                    >
                      {pt === 'individual' ? 'Física (CPF)' : 'Jurídica (CNPJ)'}
                    </button>
                  );
                })}
              </div>
            </div>

            {/* Razão social (só se jurídica) */}
            {form.contact_type_radio === 'business' && (
              <div>
                <Label htmlFor="cqa_supplier_business_name" className="text-xs font-medium text-foreground">
                  Razão social
                </Label>
                <Input
                  id="cqa_supplier_business_name"
                  name="supplier_business_name"
                  type="text"
                  value={form.supplier_business_name}
                  onChange={(e) => update('supplier_business_name', e.target.value)}
                  placeholder="Empresa Exemplo LTDA"
                  className="mt-1"
                />
              </div>
            )}

            {/* Nome (obrigatório) */}
            <div>
              <Label htmlFor="cqa_first_name" className="text-xs font-medium text-foreground">
                {form.contact_type_radio === 'business' ? 'Nome do contato' : 'Nome completo'}{' '}
                <span className="text-rose-600">*</span>
              </Label>
              <Input
                id="cqa_first_name"
                name="first_name"
                type="text"
                value={form.first_name}
                onChange={(e) => update('first_name', e.target.value)}
                placeholder={form.contact_type_radio === 'business' ? 'João da Silva' : 'Maria Santos'}
                className="mt-1"
                autoFocus
                required
              />
              {errors.first_name && (
                <p className="text-xs text-rose-600 mt-1" role="alert">
                  {errors.first_name}
                </p>
              )}
            </div>

            {/* CPF/CNPJ */}
            <div>
              <Label htmlFor="cqa_tax_number" className="text-xs font-medium text-foreground">
                {form.contact_type_radio === 'business' ? 'CNPJ' : 'CPF'}
              </Label>
              <Input
                id="cqa_tax_number"
                name="tax_number"
                type="text"
                value={form.tax_number}
                onChange={(e) => update('tax_number', maskTaxNumber(e.target.value))}
                placeholder={form.contact_type_radio === 'business' ? '12.345.678/0001-99' : '123.456.789-01'}
                className="mt-1"
              />
              {errors.tax_number && (
                <p className="text-xs text-rose-600 mt-1" role="alert">
                  {errors.tax_number}
                </p>
              )}
            </div>

            {/* Telefone (obrigatório) + Email */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <Label htmlFor="cqa_mobile" className="text-xs font-medium text-foreground">
                  Telefone <span className="text-rose-600">*</span>
                </Label>
                <Input
                  id="cqa_mobile"
                  name="mobile"
                  type="tel"
                  value={form.mobile}
                  onChange={(e) => update('mobile', maskPhone(e.target.value))}
                  placeholder="(48) 99999-9999"
                  className="mt-1"
                  required
                />
                {errors.mobile && (
                  <p className="text-xs text-rose-600 mt-1" role="alert">
                    {errors.mobile}
                  </p>
                )}
              </div>
              <div>
                <Label htmlFor="cqa_email" className="text-xs font-medium text-foreground">Email</Label>
                <Input
                  id="cqa_email"
                  name="email"
                  type="email"
                  value={form.email}
                  onChange={(e) => update('email', e.target.value)}
                  placeholder="contato@exemplo.com.br"
                  className="mt-1"
                />
                {errors.email && (
                  <p className="text-xs text-rose-600 mt-1" role="alert">
                    {errors.email}
                  </p>
                )}
              </div>
            </div>

            {/* Cidade + UF */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="md:col-span-2">
                <Label htmlFor="cqa_city" className="text-xs font-medium text-foreground">Cidade</Label>
                <Input
                  id="cqa_city"
                  name="city"
                  type="text"
                  value={form.city}
                  onChange={(e) => update('city', e.target.value)}
                  placeholder="Termas do Gravatal"
                  className="mt-1"
                />
              </div>
              <div>
                <Label htmlFor="cqa_state" className="text-xs font-medium text-foreground">UF</Label>
                <Input
                  id="cqa_state"
                  name="state"
                  type="text"
                  value={form.state}
                  onChange={(e) => update('state', e.target.value.toUpperCase().slice(0, 2))}
                  placeholder="SC"
                  maxLength={2}
                  className="mt-1"
                />
              </div>
            </div>
          </div>

          <DialogFooter className="mt-6">
            <Button type="button" variant="outline" onClick={onClose} disabled={submitting}>
              <X className="mr-1.5 h-4 w-4" />
              Cancelar
            </Button>
            <Button type="submit" disabled={submitting} data-testid="contact-quick-add-submit">
              {submitting ? (
                <Loader2 className="mr-1.5 h-4 w-4 animate-spin" />
              ) : (
                <Save className="mr-1.5 h-4 w-4" />
              )}
              {submitting ? 'Salvando…' : 'Salvar cliente'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
