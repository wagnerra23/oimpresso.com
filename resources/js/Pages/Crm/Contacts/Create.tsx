// US-CRM-CONT-002 — Cadastrar contato (cliente/fornecedor) Inertia/React.
// Pain-point #1 canary Martinho/Dani: velocidade pra abrir cadastro.
// Target: < 5 segundos do clique "Novo" até cliente salvo.
//
// 3 sections: Identificação (sempre aberta) + CPF/CNPJ + endereço (collapsível) + Avançado (collapsível).
// Validação inline + atalhos Cmd/Ctrl+S salvar / Esc cancelar.
//
// Refs: ADR 0104, ADR 0110, ADR 0093, RUNBOOK-contacts.md, Create.charter.md.

import AppShellV2 from '@/Layouts/AppShellV2';
import { useCallback, useEffect, useMemo, useState, type FormEvent, type ReactNode } from 'react';
import { ChevronDown, ChevronRight, Loader2, Save, X } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';

// ── Types ────────────────────────────────────────────────────────────────────

interface ContactTypes {
  customer?: string;
  supplier?: string;
  both?: string;
}

interface CustomerGroupOption {
  id: number;
  name: string;
}

export interface ContactsCreatePageProps {
  types: ContactTypes;
  customerGroups: CustomerGroupOption[];
  selectedType: 'customer' | 'supplier' | 'both' | null;
  prefillName: string;
  permissions: {
    create: boolean;
  };
  // Existing contact when in edit mode (Edit.tsx reusa este componente)
  contact?: ContactFormValues & { id: number };
  mode?: 'create' | 'edit';
}

interface ContactFormValues {
  type: 'customer' | 'supplier' | 'both';
  contact_type_radio: 'individual' | 'business';
  supplier_business_name: string;
  prefix: string;
  first_name: string;
  middle_name: string;
  last_name: string;
  tax_number: string;
  mobile: string;
  landline: string;
  alternate_number: string;
  email: string;
  contact_id: string;
  address_line_1: string;
  address_line_2: string;
  city: string;
  state: string;
  country: string;
  zip_code: string;
  customer_group_id: string;
  pay_term_number: string;
  pay_term_type: 'days' | 'months' | '';
  credit_limit: string;
  opening_balance: string;
  dob: string;
}

const EMPTY_FORM: ContactFormValues = {
  type: 'customer',
  contact_type_radio: 'individual',
  supplier_business_name: '',
  prefix: '',
  first_name: '',
  middle_name: '',
  last_name: '',
  tax_number: '',
  mobile: '',
  landline: '',
  alternate_number: '',
  email: '',
  contact_id: '',
  address_line_1: '',
  address_line_2: '',
  city: '',
  state: '',
  country: 'Brasil',
  zip_code: '',
  customer_group_id: '',
  pay_term_number: '',
  pay_term_type: '',
  credit_limit: '',
  opening_balance: '',
  dob: '',
};

// ── CPF/CNPJ validators (UI hint apenas — backend valida) ────────────────────

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
    // CPF mask 999.999.999-99
    return d
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d)/, '$1.$2')
      .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
  }
  // CNPJ mask 99.999.999/9999-99
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

// ── Page ─────────────────────────────────────────────────────────────────────

export default function ContactsCreate(props: ContactsCreatePageProps) {
  const isEdit = props.mode === 'edit' && !!props.contact;
  const initialType: ContactFormValues['type'] =
    (isEdit && props.contact ? (props.contact.type as ContactFormValues['type']) : null) ||
    props.selectedType ||
    (props.types.customer ? 'customer' : 'supplier');

  const [form, setForm] = useState<ContactFormValues>(() => {
    if (isEdit && props.contact) {
      return { ...EMPTY_FORM, ...props.contact, type: initialType };
    }
    return {
      ...EMPTY_FORM,
      type: initialType as ContactFormValues['type'],
      first_name: props.prefillName ?? '',
    };
  });

  const [openSection2, setOpenSection2] = useState(false);
  const [openSection3, setOpenSection3] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [globalError, setGlobalError] = useState<string | null>(null);

  const update = useCallback(<K extends keyof ContactFormValues>(field: K, value: ContactFormValues[K]) => {
    setForm((prev) => ({ ...prev, [field]: value }));
    setErrors((prev) => {
      if (!prev[field as string]) return prev;
      const next = { ...prev };
      delete next[field as string];
      return next;
    });
  }, []);

  // Auto-derive contact_type_radio from tax_number length (UI hint).
  useEffect(() => {
    const d = digitsOnly(form.tax_number);
    if (d.length === 11 && form.contact_type_radio !== 'individual') {
      setForm((p) => ({ ...p, contact_type_radio: 'individual' }));
    } else if (d.length === 14 && form.contact_type_radio !== 'business') {
      setForm((p) => ({ ...p, contact_type_radio: 'business' }));
    }
  }, [form.tax_number]);

  const taxValid = useMemo(() => {
    if (!form.tax_number) return true; // optional
    const d = digitsOnly(form.tax_number);
    if (d.length === 11) return isValidCpf(d);
    if (d.length === 14) return isValidCnpj(d);
    return false;
  }, [form.tax_number]);

  const validate = (): boolean => {
    const e: Record<string, string> = {};
    if (!form.first_name.trim() && !form.supplier_business_name.trim()) {
      e.first_name = 'Informe o nome ou razão social.';
    }
    if (!form.mobile.trim()) {
      e.mobile = 'Telefone celular é obrigatório.';
    }
    if (form.email && !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(form.email)) {
      e.email = 'Email inválido.';
    }
    if (form.tax_number && !taxValid) {
      e.tax_number = digitsOnly(form.tax_number).length === 11
        ? 'CPF inválido.'
        : digitsOnly(form.tax_number).length === 14
          ? 'CNPJ inválido.'
          : 'CPF/CNPJ deve ter 11 ou 14 dígitos.';
    }
    setErrors(e);
    return Object.keys(e).length === 0;
  };

  const handleSubmit = async (ev: FormEvent) => {
    ev.preventDefault();
    if (submitting) return;
    setGlobalError(null);
    if (!validate()) {
      // Foca primeiro erro pra UX rápida.
      try {
        const first = Object.keys(errors)[0] || (Object.keys(validate()) as any);
        const el = document.querySelector(`[name="${first}"]`) as HTMLElement | null;
        el?.focus();
      } catch (_) { /* */ }
      return;
    }
    setSubmitting(true);
    try {
      const meta = document.querySelector('meta[name="csrf-token"]');
      const csrf = meta?.getAttribute('content') ?? '';
      const url = isEdit && props.contact ? `/contacts/${props.contact.id}` : '/contacts';
      const method = isEdit ? 'PUT' : 'POST';
      // Backend espera tax_number raw (digits-only).
      const payload: Record<string, unknown> = {
        ...form,
        tax_number: digitsOnly(form.tax_number),
      };
      const res = await fetch(url, {
        method,
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
      // Redirect para Show do recém-criado/atualizado.
      const id = isEdit && props.contact ? props.contact.id : (json?.data?.id ?? null);
      if (id) {
        window.location.href = `/contacts/${id}`;
      } else {
        window.location.href = `/contacts?type=${form.type === 'both' ? 'customer' : form.type}`;
      }
    } catch (err) {
      setGlobalError('Erro de rede: ' + String((err as Error)?.message || err));
    } finally {
      setSubmitting(false);
    }
  };

  // Atalhos teclado: Cmd/Ctrl+S salva, Esc cancela.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 's') {
        e.preventDefault();
        (document.getElementById('contacts-create-form') as HTMLFormElement | null)?.requestSubmit();
      } else if (e.key === 'Escape') {
        const cancelUrl = `/contacts?type=${form.type === 'both' ? 'customer' : form.type}`;
        window.location.href = cancelUrl;
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [form.type]);

  const typeOptions = useMemo(() => {
    const opts: Array<{ value: 'customer' | 'supplier' | 'both'; label: string }> = [];
    if (props.types.customer) opts.push({ value: 'customer', label: 'Cliente' });
    if (props.types.supplier) opts.push({ value: 'supplier', label: 'Fornecedor' });
    if (props.types.both) opts.push({ value: 'both', label: 'Ambos' });
    return opts;
  }, [props.types]);

  return (
    <div className="-m-6 bg-muted/30 min-h-[calc(100vh-3rem)]">
      <form id="contacts-create-form" onSubmit={handleSubmit}>
        {/* Header */}
        <div className="border-b border-border bg-background">
          <div className="container mx-auto px-8 pt-6 pb-4 max-w-4xl">
            <div className="flex items-start gap-4">
              <div className="flex-1 min-w-0">
                <div className="text-xs text-muted-foreground mb-1">
                  <a href={`/contacts?type=${form.type === 'both' ? 'customer' : form.type}`} className="hover:text-foreground">
                    Contatos
                  </a>
                  <span className="mx-1">/</span>
                  <span>{isEdit ? 'Editar' : 'Novo contato'}</span>
                </div>
                <h1 className="text-2xl font-semibold tracking-tight text-foreground">
                  {isEdit ? 'Editar contato' : 'Novo contato'}
                </h1>
                <p className="text-sm text-muted-foreground mt-1 leading-relaxed">
                  {isEdit
                    ? 'Atualize os dados do contato. Mudanças são salvas no banco multi-tenant.'
                    : 'Cadastre rápido: 3 campos obrigatórios (tipo, nome, telefone). Demais campos colapsáveis abaixo.'}
                </p>
              </div>
              <div className="flex-shrink-0 flex items-center gap-2">
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => {
                    window.location.href = `/contacts?type=${form.type === 'both' ? 'customer' : form.type}`;
                  }}
                >
                  <X className="mr-1.5 h-4 w-4" />
                  Cancelar
                </Button>
                <Button type="submit" disabled={submitting}>
                  {submitting ? <Loader2 className="mr-1.5 h-4 w-4 animate-spin" /> : <Save className="mr-1.5 h-4 w-4" />}
                  {submitting ? 'Salvando…' : 'Salvar'}
                </Button>
              </div>
            </div>
          </div>
        </div>

        <div className="container mx-auto px-8 py-6 max-w-4xl space-y-4">
          {globalError && (
            <div className="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-300">
              {globalError}
            </div>
          )}

          {/* Section 1 — Identificação (sempre aberta) */}
          <section className="rounded-lg border border-border bg-background p-5 space-y-4">
            <div>
              <h2 className="text-sm font-semibold text-foreground">Identificação</h2>
              <p className="text-xs text-muted-foreground mt-0.5">
                Campos obrigatórios para criar o contato.
              </p>
            </div>

            {/* Tipo */}
            <div>
              <Label className="text-xs font-medium text-foreground">Tipo</Label>
              <div className="flex items-center gap-2 mt-2 flex-wrap">
                {typeOptions.map((opt) => {
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
                <button
                  type="button"
                  onClick={() => update('contact_type_radio', 'individual')}
                  className={
                    'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium transition-colors ' +
                    (form.contact_type_radio === 'individual'
                      ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'
                      : 'bg-muted/40 text-muted-foreground hover:bg-muted hover:text-foreground')
                  }
                  aria-pressed={form.contact_type_radio === 'individual'}
                >
                  Física (CPF)
                </button>
                <button
                  type="button"
                  onClick={() => update('contact_type_radio', 'business')}
                  className={
                    'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium transition-colors ' +
                    (form.contact_type_radio === 'business'
                      ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300'
                      : 'bg-muted/40 text-muted-foreground hover:bg-muted hover:text-foreground')
                  }
                  aria-pressed={form.contact_type_radio === 'business'}
                >
                  Jurídica (CNPJ)
                </button>
              </div>
            </div>

            {/* Razão social (se jurídica) */}
            {form.contact_type_radio === 'business' && (
              <div>
                <Label htmlFor="supplier_business_name" className="text-xs font-medium text-foreground">
                  Razão social
                </Label>
                <Input
                  id="supplier_business_name"
                  name="supplier_business_name"
                  type="text"
                  value={form.supplier_business_name}
                  onChange={(e) => update('supplier_business_name', e.target.value)}
                  placeholder="Empresa Exemplo LTDA"
                  className="mt-1"
                />
              </div>
            )}

            {/* Nome (sempre obrigatório) */}
            <div>
              <Label htmlFor="first_name" className="text-xs font-medium text-foreground">
                {form.contact_type_radio === 'business' ? 'Nome do contato' : 'Nome completo'}{' '}
                <span className="text-rose-600">*</span>
              </Label>
              <Input
                id="first_name"
                name="first_name"
                type="text"
                value={form.first_name}
                onChange={(e) => update('first_name', e.target.value)}
                placeholder={form.contact_type_radio === 'business' ? 'João da Silva' : 'Maria Santos'}
                className="mt-1"
                autoFocus={!props.prefillName}
                required
              />
              {errors.first_name && <p className="text-xs text-rose-600 mt-1">{errors.first_name}</p>}
            </div>

            {/* Telefone (obrigatório) + Email (opcional) lado a lado */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <Label htmlFor="mobile" className="text-xs font-medium text-foreground">
                  Telefone <span className="text-rose-600">*</span>
                </Label>
                <Input
                  id="mobile"
                  name="mobile"
                  type="tel"
                  value={form.mobile}
                  onChange={(e) => update('mobile', maskPhone(e.target.value))}
                  placeholder="(48) 99999-9999"
                  className="mt-1"
                  required
                />
                {errors.mobile && <p className="text-xs text-rose-600 mt-1">{errors.mobile}</p>}
              </div>
              <div>
                <Label htmlFor="email" className="text-xs font-medium text-foreground">Email</Label>
                <Input
                  id="email"
                  name="email"
                  type="email"
                  value={form.email}
                  onChange={(e) => update('email', e.target.value)}
                  placeholder="contato@exemplo.com.br"
                  className="mt-1"
                />
                {errors.email && <p className="text-xs text-rose-600 mt-1">{errors.email}</p>}
              </div>
            </div>
          </section>

          {/* Section 2 — Documento + endereço (collapsible) */}
          <CollapsibleSection
            title="Documento e endereço"
            description="CPF/CNPJ, endereço completo. Opcional, mas recomendado para emitir NFe."
            open={openSection2}
            onToggle={() => setOpenSection2((v) => !v)}
          >
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <Label htmlFor="tax_number" className="text-xs font-medium text-foreground">
                  {form.contact_type_radio === 'business' ? 'CNPJ' : 'CPF'}
                </Label>
                <Input
                  id="tax_number"
                  name="tax_number"
                  type="text"
                  value={form.tax_number}
                  onChange={(e) => update('tax_number', maskTaxNumber(e.target.value))}
                  placeholder={form.contact_type_radio === 'business' ? '12.345.678/0001-99' : '123.456.789-01'}
                  className="mt-1"
                />
                {errors.tax_number && <p className="text-xs text-rose-600 mt-1">{errors.tax_number}</p>}
              </div>
              <div>
                <Label htmlFor="contact_id" className="text-xs font-medium text-foreground">
                  Código interno
                </Label>
                <Input
                  id="contact_id"
                  name="contact_id"
                  type="text"
                  value={form.contact_id}
                  onChange={(e) => update('contact_id', e.target.value)}
                  placeholder="Auto-gerado se vazio"
                  className="mt-1"
                />
              </div>
            </div>

            <div>
              <Label htmlFor="address_line_1" className="text-xs font-medium text-foreground">Endereço</Label>
              <Input
                id="address_line_1"
                name="address_line_1"
                type="text"
                value={form.address_line_1}
                onChange={(e) => update('address_line_1', e.target.value)}
                placeholder="Rua / Avenida, número"
                className="mt-1"
              />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="md:col-span-2">
                <Label htmlFor="city" className="text-xs font-medium text-foreground">Cidade</Label>
                <Input
                  id="city"
                  name="city"
                  type="text"
                  value={form.city}
                  onChange={(e) => update('city', e.target.value)}
                  placeholder="Termas do Gravatal"
                  className="mt-1"
                />
              </div>
              <div>
                <Label htmlFor="state" className="text-xs font-medium text-foreground">UF</Label>
                <Input
                  id="state"
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

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <Label htmlFor="zip_code" className="text-xs font-medium text-foreground">CEP</Label>
                <Input
                  id="zip_code"
                  name="zip_code"
                  type="text"
                  value={form.zip_code}
                  onChange={(e) => update('zip_code', e.target.value)}
                  placeholder="00000-000"
                  className="mt-1"
                />
              </div>
              <div>
                <Label htmlFor="country" className="text-xs font-medium text-foreground">País</Label>
                <Input
                  id="country"
                  name="country"
                  type="text"
                  value={form.country}
                  onChange={(e) => update('country', e.target.value)}
                  className="mt-1"
                />
              </div>
            </div>
          </CollapsibleSection>

          {/* Section 3 — Avançado (collapsible) */}
          <CollapsibleSection
            title="Avançado"
            description="Telefone fixo, grupo de clientes, condições de pagamento, limite de crédito."
            open={openSection3}
            onToggle={() => setOpenSection3((v) => !v)}
          >
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <Label htmlFor="landline" className="text-xs font-medium text-foreground">Telefone fixo</Label>
                <Input
                  id="landline"
                  name="landline"
                  type="tel"
                  value={form.landline}
                  onChange={(e) => update('landline', maskPhone(e.target.value))}
                  placeholder="(48) 3333-3333"
                  className="mt-1"
                />
              </div>
              <div>
                <Label htmlFor="alternate_number" className="text-xs font-medium text-foreground">Telefone alternativo</Label>
                <Input
                  id="alternate_number"
                  name="alternate_number"
                  type="tel"
                  value={form.alternate_number}
                  onChange={(e) => update('alternate_number', maskPhone(e.target.value))}
                  className="mt-1"
                />
              </div>
            </div>

            {form.type !== 'supplier' && props.customerGroups.length > 0 && (
              <div>
                <Label htmlFor="customer_group_id" className="text-xs font-medium text-foreground">Grupo de cliente</Label>
                <select
                  id="customer_group_id"
                  name="customer_group_id"
                  value={form.customer_group_id}
                  onChange={(e) => update('customer_group_id', e.target.value)}
                  className="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                >
                  <option value="">Sem grupo</option>
                  {props.customerGroups.map((g) => (
                    <option key={g.id} value={g.id}>{g.name}</option>
                  ))}
                </select>
              </div>
            )}

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <Label htmlFor="pay_term_number" className="text-xs font-medium text-foreground">Prazo de pagamento</Label>
                <Input
                  id="pay_term_number"
                  name="pay_term_number"
                  type="number"
                  min="0"
                  value={form.pay_term_number}
                  onChange={(e) => update('pay_term_number', e.target.value)}
                  className="mt-1"
                />
              </div>
              <div>
                <Label htmlFor="pay_term_type" className="text-xs font-medium text-foreground">Unidade</Label>
                <select
                  id="pay_term_type"
                  name="pay_term_type"
                  value={form.pay_term_type}
                  onChange={(e) => update('pay_term_type', e.target.value as 'days' | 'months' | '')}
                  className="mt-1 h-9 w-full rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                >
                  <option value="">—</option>
                  <option value="days">Dias</option>
                  <option value="months">Meses</option>
                </select>
              </div>
              <div>
                <Label htmlFor="credit_limit" className="text-xs font-medium text-foreground">Limite de crédito</Label>
                <Input
                  id="credit_limit"
                  name="credit_limit"
                  type="text"
                  value={form.credit_limit}
                  onChange={(e) => update('credit_limit', e.target.value)}
                  placeholder="Sem limite"
                  className="mt-1"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <Label htmlFor="opening_balance" className="text-xs font-medium text-foreground">Saldo inicial</Label>
                <Input
                  id="opening_balance"
                  name="opening_balance"
                  type="text"
                  value={form.opening_balance}
                  onChange={(e) => update('opening_balance', e.target.value)}
                  placeholder="0,00"
                  className="mt-1"
                />
              </div>
              <div>
                <Label htmlFor="dob" className="text-xs font-medium text-foreground">Data de nascimento</Label>
                <Input
                  id="dob"
                  name="dob"
                  type="date"
                  value={form.dob}
                  onChange={(e) => update('dob', e.target.value)}
                  className="mt-1"
                />
              </div>
            </div>
          </CollapsibleSection>
        </div>
      </form>
    </div>
  );
}

ContactsCreate.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

// ── Subcomponents ────────────────────────────────────────────────────────────

function CollapsibleSection({
  title,
  description,
  open,
  onToggle,
  children,
}: {
  title: string;
  description?: string;
  open: boolean;
  onToggle: () => void;
  children: ReactNode;
}) {
  return (
    <section className="rounded-lg border border-border bg-background">
      <button
        type="button"
        onClick={onToggle}
        className="w-full flex items-center justify-between px-5 py-4 text-left hover:bg-muted/30 transition-colors"
        aria-expanded={open}
      >
        <div className="flex-1 min-w-0">
          <h2 className="text-sm font-semibold text-foreground">{title}</h2>
          {description && <p className="text-xs text-muted-foreground mt-0.5">{description}</p>}
        </div>
        {open ? <ChevronDown size={16} className="text-muted-foreground" /> : <ChevronRight size={16} className="text-muted-foreground" />}
      </button>
      {open && (
        <div className="px-5 pb-5 pt-1 space-y-4 border-t border-border">
          {children}
        </div>
      )}
    </section>
  );
}
