// US-CRM-078 — EnderecoTab vira LISTA de endereços (matriz/filial/casa/obra).
//
// Tab 3 do drawer 760px Cliente. Lista os endereços do contato + adicionar /
// editar / remover / marcar padrão. Reusa o lookup ViaCEP server-side.
// O endereço `is_default` é espelhado pelo backend nos campos inline de
// `contacts` (compat UPOS/NFe/Sells) — ver ContactAddressController.
//
// Refs: ADR 0179 · ADR 0093 (Tier 0) · memory/requisitos/Cliente/SPEC.md §US-CRM-078
//
// Contrato (Modules/Crm/Http/Controllers/ContactAddressController):
//   GET    /cliente/{id}/enderecos                 → { addresses: ContactAddressRow[] }
//   POST   /cliente/{id}/enderecos                 → { addresses }   (cria)
//   PATCH  /cliente/{id}/enderecos/{addressId}     → { addresses }   (edita)
//   DELETE /cliente/{id}/enderecos/{addressId}     → { addresses }   (remove)
//   PATCH  /cliente/{id}/enderecos/{addressId}/padrao → { addresses } (marca padrão)
//   GET    /cliente/lookup/cep/{cep} → { logradouro, complemento, bairro, cidade, uf }

import { useCallback, useEffect, useState } from 'react';
import { Loader2, Search, CheckCircle2, AlertCircle, Plus, Star, Truck, Pencil, Trash2 } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { maskCEP, onlyDigits } from '@/Lib/br-mask';
import { validateCEP } from '@/Lib/br-validate';

export interface ContactAddressRow {
  id: number;
  label?: string | null;
  zip_code?: string | null;
  address_line_1?: string | null;
  numero?: string | null;
  address_line_2?: string | null;
  neighborhood?: string | null;
  city?: string | null;
  state?: string | null;
  city_code?: string | null;
  is_default: boolean;
  is_shipping: boolean;
  one_line?: string | null;
}

export interface EnderecoTabProps {
  contact: { id: number };
  disabled?: boolean;
  /**
   * Drawer host (Index.tsx) — sincroniza o endereço principal no parent
   * (draftContact/row) após mudança na lista, evitando inline stale.
   */
  onContactUpdated?: (patched: Record<string, unknown>) => void;
}

type FormState = {
  label: string;
  zip_code: string;
  address_line_1: string;
  numero: string;
  address_line_2: string;
  neighborhood: string;
  city: string;
  state: string;
};

type CepLookupState = 'idle' | 'loading' | 'ok' | 'error';

const UF_OPTIONS = [
  'AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO',
  'MA', 'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR',
  'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO',
];

const EMPTY_FORM: FormState = {
  label: '', zip_code: '', address_line_1: '', numero: '',
  address_line_2: '', neighborhood: '', city: '', state: '',
};

function getCsrfToken(): string {
  return (
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? ''
  );
}

function rowToForm(a: ContactAddressRow): FormState {
  return {
    label: a.label ?? '',
    zip_code: maskCEP(a.zip_code ?? ''),
    address_line_1: a.address_line_1 ?? '',
    numero: a.numero ?? '',
    address_line_2: a.address_line_2 ?? '',
    neighborhood: a.neighborhood ?? '',
    city: a.city ?? '',
    state: a.state ?? '',
  };
}

export default function EnderecoTab({ contact, disabled = false, onContactUpdated }: EnderecoTabProps) {
  const [addresses, setAddresses] = useState<ContactAddressRow[]>([]);
  const [loading, setLoading] = useState(true);
  // null = só lista; 'new' = formulário de novo; number = editando aquele id.
  const [editing, setEditing] = useState<'new' | number | null>(null);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [cepLookup, setCepLookup] = useState<CepLookupState>('idle');

  const headers = useCallback(
    () => ({
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': getCsrfToken(),
      'X-Requested-With': 'XMLHttpRequest',
    }),
    []
  );

  const loadList = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const r = await fetch(`/cliente/${contact.id}/enderecos`, {
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
      });
      if (!r.ok) throw new Error(`Erro ${r.status}`);
      const j = await r.json();
      setAddresses((j.addresses ?? []) as ContactAddressRow[]);
    } catch (err) {
      setError('Não foi possível carregar os endereços.');
      // eslint-disable-next-line no-console
      console.error('[EnderecoTab] load', err);
    } finally {
      setLoading(false);
    }
  }, [contact.id]);

  useEffect(() => {
    void loadList();
    setEditing(null);
  }, [loadList]);

  // Sincroniza o endereço principal (is_default) no parent (draftContact/row)
  // sempre que a lista muda — espelha o que o backend gravou nos campos inline
  // de `contacts`, evitando inline stale na listagem sem reabrir o drawer.
  useEffect(() => {
    const def = addresses.find((a) => a.is_default);
    if (def) {
      onContactUpdated?.({
        zip_code: def.zip_code,
        address_line_1: def.address_line_1,
        numero: def.numero,
        address_line_2: def.address_line_2,
        neighborhood: def.neighborhood,
        city: def.city,
        state: def.state,
        city_code: def.city_code,
      });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [addresses]);

  const openNew = () => {
    setForm(EMPTY_FORM);
    setEditing('new');
    setError(null);
    setCepLookup('idle');
  };
  const openEdit = (a: ContactAddressRow) => {
    setForm(rowToForm(a));
    setEditing(a.id);
    setError(null);
    setCepLookup('idle');
  };
  const cancel = () => {
    setEditing(null);
    setForm(EMPTY_FORM);
    setError(null);
  };

  const cepError = validateCEP(form.zip_code) === false ? 'CEP precisa ter 8 dígitos.' : null;

  const handleCepLookup = useCallback(async () => {
    const digits = onlyDigits(form.zip_code);
    if (digits.length !== 8) return;
    setCepLookup('loading');
    try {
      const r = await fetch(`/cliente/lookup/cep/${digits}`, {
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
      });
      if (!r.ok) {
        setCepLookup('error');
        return;
      }
      const j = await r.json();
      setForm((f) => ({
        ...f,
        address_line_1: (j?.logradouro as string) || f.address_line_1,
        address_line_2: (j?.complemento as string) || f.address_line_2,
        neighborhood: (j?.bairro as string) || f.neighborhood,
        city: (j?.cidade as string) || f.city,
        state: (j?.uf as string) || f.state,
      }));
      setCepLookup('ok');
      setTimeout(() => setCepLookup('idle'), 2500);
    } catch {
      setCepLookup('error');
    }
  }, [form.zip_code]);

  const save = useCallback(async () => {
    if (disabled || cepError) return;
    setSaving(true);
    setError(null);
    const isNew = editing === 'new';
    const url = isNew
      ? `/cliente/${contact.id}/enderecos`
      : `/cliente/${contact.id}/enderecos/${editing as number}`;
    try {
      const r = await fetch(url, {
        method: isNew ? 'POST' : 'PATCH',
        headers: headers(),
        body: JSON.stringify({ ...form, zip_code: onlyDigits(form.zip_code) }),
      });
      if (!r.ok) {
        if (r.status === 422) {
          const j = await r.json().catch(() => ({}));
          const first = j?.errors ? (Object.values(j.errors)[0] as string[])?.[0] : null;
          setError(first ?? 'Dados inválidos.');
        } else {
          setError(`Erro ${r.status} ao salvar.`);
        }
        return;
      }
      const j = await r.json();
      setAddresses((j.addresses ?? []) as ContactAddressRow[]);
      cancel();
    } catch (err) {
      setError('Falha de rede. Tente de novo.');
      // eslint-disable-next-line no-console
      console.error('[EnderecoTab] save', err);
    } finally {
      setSaving(false);
    }
  }, [disabled, cepError, editing, contact.id, form, headers]);

  const remove = useCallback(
    async (id: number) => {
      if (disabled) return;
      if (!window.confirm('Remover este endereço?')) return;
      try {
        const r = await fetch(`/cliente/${contact.id}/enderecos/${id}`, {
          method: 'DELETE',
          headers: headers(),
        });
        if (!r.ok) {
          setError(`Erro ${r.status} ao remover.`);
          return;
        }
        const j = await r.json();
        setAddresses((j.addresses ?? []) as ContactAddressRow[]);
      } catch {
        setError('Falha ao remover.');
      }
    },
    [disabled, contact.id, headers]
  );

  const setDefault = useCallback(
    async (id: number) => {
      if (disabled) return;
      try {
        const r = await fetch(`/cliente/${contact.id}/enderecos/${id}/padrao`, {
          method: 'PATCH',
          headers: headers(),
          body: JSON.stringify({ is_shipping: true }),
        });
        if (!r.ok) {
          setError(`Erro ${r.status} ao marcar padrão.`);
          return;
        }
        const j = await r.json();
        setAddresses((j.addresses ?? []) as ContactAddressRow[]);
      } catch {
        setError('Falha ao marcar padrão.');
      }
    },
    [disabled, contact.id, headers]
  );

  if (loading) {
    return (
      <div className="flex items-center gap-2 py-8 text-sm text-muted-foreground">
        <Loader2 size={16} className="animate-spin" /> Carregando endereços…
      </div>
    );
  }

  // ── Formulário (novo ou edição) ───────────────────────────────────────
  if (editing !== null) {
    const set = (k: keyof FormState, v: string) => setForm((f) => ({ ...f, [k]: v }));
    return (
      <div className="space-y-5">
        <div className="flex items-center justify-between">
          <h3 className="text-sm font-semibold text-foreground">
            {editing === 'new' ? 'Novo endereço' : 'Editar endereço'}
          </h3>
        </div>

        {error && (
          <p className="inline-flex items-center gap-1 text-xs text-rose-600" role="alert">
            <AlertCircle size={12} aria-hidden /> {error}
          </p>
        )}

        <div className="grid gap-4 md:grid-cols-2">
          <div className="md:col-span-2">
            <Label htmlFor="ed-label" className="cw-label">Rótulo</Label>
            <Input
              variant="cowork" id="ed-label" value={form.label} placeholder="Matriz, Filial, Casa, Obra…"
              disabled={disabled} onChange={(e) => set('label', e.target.value)}
            />
          </div>

          <div>
            <Label htmlFor="ed-cep" className="cw-label">CEP</Label>
            <div className="flex gap-2">
              <Input
                variant="cowork" id="ed-cep" value={form.zip_code} placeholder="00000-000"
                disabled={disabled} inputMode="numeric" aria-invalid={!!cepError}
                onChange={(e) => set('zip_code', maskCEP(e.target.value))}
                onBlur={() => { if (onlyDigits(form.zip_code).length === 8) void handleCepLookup(); }}
              />
              <Button
                type="button" variant="outline" size="sm"
                disabled={disabled || cepLookup === 'loading' || onlyDigits(form.zip_code).length !== 8}
                onClick={() => void handleCepLookup()} className="shrink-0" aria-label="Buscar CEP no ViaCEP"
              >
                {cepLookup === 'loading' ? (<><Loader2 size={14} className="animate-spin" /> Buscando…</>)
                  : cepLookup === 'ok' ? (<><CheckCircle2 size={14} className="text-emerald-600" /> Ok</>)
                  : (<><Search size={14} /> Buscar</>)}
              </Button>
            </div>
            {cepError && <p className="mt-1 text-xs text-rose-600">{cepError}</p>}
          </div>

          <div>
            <Label htmlFor="ed-numero" className="cw-label">Número</Label>
            <Input variant="cowork" id="ed-numero" value={form.numero} placeholder="123"
              disabled={disabled} onChange={(e) => set('numero', e.target.value)} />
          </div>

          <div className="md:col-span-2">
            <Label htmlFor="ed-endereco" className="cw-label">Endereço</Label>
            <Input variant="cowork" id="ed-endereco" value={form.address_line_1}
              placeholder="Rua, avenida, alameda…" disabled={disabled}
              onChange={(e) => set('address_line_1', e.target.value)} />
          </div>

          <div className="md:col-span-2">
            <Label htmlFor="ed-complemento" className="cw-label">
              Complemento <span className="text-muted-foreground font-normal">(opcional)</span>
            </Label>
            <Input variant="cowork" id="ed-complemento" value={form.address_line_2}
              placeholder="Apto, conjunto, sala…" disabled={disabled}
              onChange={(e) => set('address_line_2', e.target.value)} />
          </div>

          <div>
            <Label htmlFor="ed-bairro" className="cw-label">Bairro</Label>
            <Input variant="cowork" id="ed-bairro" value={form.neighborhood} disabled={disabled}
              onChange={(e) => set('neighborhood', e.target.value)} />
          </div>

          <div>
            <Label htmlFor="ed-cidade" className="cw-label">Cidade</Label>
            <Input variant="cowork" id="ed-cidade" value={form.city} disabled={disabled}
              onChange={(e) => set('city', e.target.value)} />
          </div>

          <div>
            <Label htmlFor="ed-uf" className="cw-label">UF</Label>
            <Select value={form.state} onValueChange={(v) => set('state', v)} disabled={disabled}>
              <SelectTrigger id="ed-uf" variant="cowork" className="w-full">
                <SelectValue placeholder="UF" />
              </SelectTrigger>
              <SelectContent>
                {UF_OPTIONS.map((u) => (<SelectItem key={u} value={u}>{u}</SelectItem>))}
              </SelectContent>
            </Select>
          </div>
        </div>

        <div className="flex gap-2">
          <Button type="button" onClick={() => void save()} disabled={disabled || saving || !!cepError}>
            {saving ? (<><Loader2 size={14} className="animate-spin" /> Salvando…</>) : 'Salvar endereço'}
          </Button>
          <Button type="button" variant="outline" onClick={cancel} disabled={saving}>Cancelar</Button>
        </div>
      </div>
    );
  }

  // ── Lista ─────────────────────────────────────────────────────────────
  return (
    <div className="space-y-4">
      {error && (
        <p className="inline-flex items-center gap-1 text-xs text-rose-600" role="alert">
          <AlertCircle size={12} aria-hidden /> {error}
        </p>
      )}

      {addresses.length === 0 ? (
        <p className="py-6 text-sm text-muted-foreground">Nenhum endereço cadastrado.</p>
      ) : (
        <ul className="space-y-2">
          {addresses.map((a) => (
            <li key={a.id} className="rounded-md border border-border p-3">
              <div className="flex items-start justify-between gap-2">
                <div className="min-w-0">
                  <div className="flex flex-wrap items-center gap-1.5">
                    <span className="text-sm font-medium text-foreground">{a.label || 'Endereço'}</span>
                    {a.is_default && (
                      <span className="inline-flex items-center gap-0.5 rounded bg-amber-50 px-1.5 py-0.5 text-[11px] font-medium text-amber-700">
                        <Star size={11} /> Padrão
                      </span>
                    )}
                    {a.is_shipping && (
                      <span className="inline-flex items-center gap-0.5 rounded bg-sky-50 px-1.5 py-0.5 text-[11px] font-medium text-sky-700">
                        <Truck size={11} /> Entrega
                      </span>
                    )}
                  </div>
                  <p className="mt-0.5 truncate text-xs text-muted-foreground">{a.one_line || '—'}</p>
                </div>
                <div className="flex shrink-0 items-center gap-1">
                  {!a.is_default && (
                    <Button type="button" variant="ghost" size="sm" disabled={disabled}
                      onClick={() => void setDefault(a.id)} aria-label="Marcar como padrão">
                      <Star size={14} />
                    </Button>
                  )}
                  <Button type="button" variant="ghost" size="sm" disabled={disabled}
                    onClick={() => openEdit(a)} aria-label="Editar endereço">
                    <Pencil size={14} />
                  </Button>
                  <Button type="button" variant="ghost" size="sm" disabled={disabled}
                    onClick={() => void remove(a.id)} aria-label="Remover endereço"
                    className="text-rose-600 hover:text-rose-700">
                    <Trash2 size={14} />
                  </Button>
                </div>
              </div>
            </li>
          ))}
        </ul>
      )}

      <Button type="button" variant="outline" onClick={openNew} disabled={disabled}>
        <Plus size={14} /> Adicionar endereço
      </Button>
    </div>
  );
}
