// US-CRM-078 (fase 2) — EnderecosEntregaList.tsx
//
// Lista de endereços ESTRUTURADOS do contato (entrega/comercial), dentro do
// EnderecoTab do drawer 760 Cliente. Substitui o campo único de texto livre
// `shipping_address` por uma lista add/editar/remover + marcar "entrega"
// (is_shipping) e "principal" (is_default).
//
// Por que estruturado: a NF-e (grupo <entrega>/enderEntrega) exige campos
// separados (logradouro/numero/bairro/município IBGE/UF/CEP). Texto livre não
// serve. A tabela contact_addresses (US-078) já tem esses campos.
//
// Contrato (ContactAddressController — US-078):
//   GET    /cliente/{id}/enderecos                  → { success, addresses[] }
//   POST   /cliente/{id}/enderecos                  → { success, addresses[] }
//   PATCH  /cliente/{id}/enderecos/{addressId}      → { success, addresses[] }
//   DELETE /cliente/{id}/enderecos/{addressId}      → { success, addresses[] }
// CEP: GET /cliente/lookup/cep/{cep} → { logradouro, bairro, cidade, uf }
//
// Multi-tenant: backend escopa por sessão (business_id) — nunca enviado pelo front.

import { useCallback, useEffect, useState } from 'react';
import { Loader2, Search, Plus, Pencil, Trash2, MapPin, Truck, Star } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { maskCEP } from '@/Lib/br-mask';

export interface ContactAddress {
  id: number;
  label: string | null;
  zip_code: string | null;
  address_line_1: string | null;
  numero: string | null;
  address_line_2: string | null;
  neighborhood: string | null;
  city: string | null;
  state: string | null;
  city_code: string | null;
  is_default: boolean;
  is_shipping: boolean;
  one_line: string;
}

interface EnderecosEntregaListProps {
  contactId: number;
  disabled?: boolean;
}

const UF_OPTIONS = [
  'AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO',
  'MA', 'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR',
  'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO',
];

type FormState = {
  label: string;
  zip_code: string;
  address_line_1: string;
  numero: string;
  address_line_2: string;
  neighborhood: string;
  city: string;
  state: string;
  city_code: string;
  is_shipping: boolean;
};

const EMPTY_FORM: FormState = {
  label: '',
  zip_code: '',
  address_line_1: '',
  numero: '',
  address_line_2: '',
  neighborhood: '',
  city: '',
  state: '',
  city_code: '',
  is_shipping: false,
};

function getCsrfToken(): string {
  return (
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? ''
  );
}

const JSON_HEADERS = () => ({
  'Content-Type': 'application/json',
  Accept: 'application/json',
  'X-Requested-With': 'XMLHttpRequest',
  'X-CSRF-TOKEN': getCsrfToken(),
});

export default function EnderecosEntregaList({ contactId, disabled = false }: EnderecosEntregaListProps) {
  const [addresses, setAddresses] = useState<ContactAddress[]>([]);
  const [loading, setLoading] = useState<boolean>(true);
  const [editingId, setEditingId] = useState<number | 'new' | null>(null);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [saving, setSaving] = useState<boolean>(false);
  const [cepLoading, setCepLoading] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const r = await fetch(`/cliente/${contactId}/enderecos`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!r.ok) {
        setAddresses([]);
        return;
      }
      const json = (await r.json()) as { addresses?: ContactAddress[] };
      setAddresses(Array.isArray(json.addresses) ? json.addresses : []);
    } catch {
      setAddresses([]);
    } finally {
      setLoading(false);
    }
  }, [contactId]);

  useEffect(() => {
    void load();
  }, [load]);

  const openNew = () => {
    setForm(EMPTY_FORM);
    setError(null);
    setEditingId('new');
  };

  const openEdit = (a: ContactAddress) => {
    setForm({
      label: a.label ?? '',
      zip_code: maskCEP(a.zip_code ?? ''),
      address_line_1: a.address_line_1 ?? '',
      numero: a.numero ?? '',
      address_line_2: a.address_line_2 ?? '',
      neighborhood: a.neighborhood ?? '',
      city: a.city ?? '',
      state: a.state ?? '',
      city_code: a.city_code ?? '',
      is_shipping: a.is_shipping,
    });
    setError(null);
    setEditingId(a.id);
  };

  const cancel = () => {
    setEditingId(null);
    setForm(EMPTY_FORM);
    setError(null);
  };

  const lookupCep = async () => {
    const digits = (form.zip_code ?? '').replace(/\D/g, '');
    if (digits.length !== 8) return;
    setCepLoading(true);
    try {
      const r = await fetch(`/cliente/lookup/cep/${digits}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!r.ok) return;
      const d = (await r.json()) as {
        logradouro?: string; bairro?: string; cidade?: string; uf?: string;
      };
      setForm((f) => ({
        ...f,
        address_line_1: d.logradouro || f.address_line_1,
        neighborhood: d.bairro || f.neighborhood,
        city: d.cidade || f.city,
        state: (d.uf || f.state).toUpperCase(),
      }));
    } catch {
      /* lookup é best-effort */
    } finally {
      setCepLoading(false);
    }
  };

  const save = async () => {
    setSaving(true);
    setError(null);
    const payload = {
      label: form.label || null,
      zip_code: form.zip_code.replace(/\D/g, '') || null,
      address_line_1: form.address_line_1 || null,
      numero: form.numero || null,
      address_line_2: form.address_line_2 || null,
      neighborhood: form.neighborhood || null,
      city: form.city || null,
      state: form.state || null,
      city_code: form.city_code.replace(/\D/g, '') || null,
      is_shipping: form.is_shipping,
    };
    const isNew = editingId === 'new';
    const url = isNew
      ? `/cliente/${contactId}/enderecos`
      : `/cliente/${contactId}/enderecos/${editingId}`;
    try {
      const r = await fetch(url, {
        method: isNew ? 'POST' : 'PATCH',
        headers: JSON_HEADERS(),
        credentials: 'same-origin',
        body: JSON.stringify(payload),
      });
      const json = (await r.json().catch(() => ({}))) as {
        addresses?: ContactAddress[]; errors?: Record<string, string[]>;
      };
      if (!r.ok) {
        const first = json.errors ? Object.values(json.errors)[0]?.[0] : null;
        setError(first ?? 'Não foi possível salvar o endereço.');
        return;
      }
      setAddresses(Array.isArray(json.addresses) ? json.addresses : addresses);
      cancel();
    } catch {
      setError('Erro de rede ao salvar.');
    } finally {
      setSaving(false);
    }
  };

  const remove = async (id: number) => {
    if (!window.confirm('Remover este endereço?')) return;
    try {
      const r = await fetch(`/cliente/${contactId}/enderecos/${id}`, {
        method: 'DELETE',
        headers: JSON_HEADERS(),
        credentials: 'same-origin',
      });
      const json = (await r.json().catch(() => ({}))) as { addresses?: ContactAddress[] };
      if (r.ok) setAddresses(Array.isArray(json.addresses) ? json.addresses : []);
    } catch {
      /* noop */
    }
  };

  const markShipping = async (id: number) => {
    try {
      const r = await fetch(`/cliente/${contactId}/enderecos/${id}`, {
        method: 'PATCH',
        headers: JSON_HEADERS(),
        credentials: 'same-origin',
        body: JSON.stringify({ is_shipping: true }),
      });
      const json = (await r.json().catch(() => ({}))) as { addresses?: ContactAddress[] };
      if (r.ok) setAddresses(Array.isArray(json.addresses) ? json.addresses : addresses);
    } catch {
      /* noop */
    }
  };

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <div>
          <h4 className="text-sm font-semibold text-foreground">Endereços de entrega / comerciais</h4>
          <p className="text-xs text-muted-foreground">
            Endereços estruturados além do principal. Marque qual é o de entrega (usado na nota fiscal).
          </p>
        </div>
        {!disabled && editingId === null && (
          <Button type="button" variant="outline" size="sm" onClick={openNew}>
            <Plus className="h-4 w-4 mr-1" /> Adicionar
          </Button>
        )}
      </div>

      {loading ? (
        <div className="flex items-center gap-2 text-sm text-muted-foreground py-3">
          <Loader2 className="h-4 w-4 animate-spin" /> Carregando endereços…
        </div>
      ) : (
        <ul className="space-y-2">
          {addresses.length === 0 && editingId === null && (
            <li className="text-sm text-muted-foreground py-2">Nenhum endereço cadastrado ainda.</li>
          )}
          {addresses.map((a) => (
            <li
              key={a.id}
              className="flex items-start justify-between gap-3 rounded-md border border-border p-3"
            >
              <div className="flex items-start gap-2 min-w-0">
                <MapPin className="h-4 w-4 mt-0.5 text-muted-foreground shrink-0" />
                <div className="min-w-0">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="text-sm font-medium text-foreground">
                      {a.label || 'Endereço'}
                    </span>
                    {a.is_default && (
                      <span className="inline-flex items-center gap-1 rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
                        <Star className="h-3 w-3" /> Principal
                      </span>
                    )}
                    {a.is_shipping && (
                      <span className="inline-flex items-center gap-1 rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-medium text-primary">
                        <Truck className="h-3 w-3" /> Entrega
                      </span>
                    )}
                  </div>
                  <p className="text-xs text-muted-foreground truncate">{a.one_line || '—'}</p>
                </div>
              </div>
              {!disabled && (
                <div className="flex items-center gap-1 shrink-0">
                  {!a.is_shipping && (
                    <Button type="button" variant="ghost" size="sm" onClick={() => markShipping(a.id)} title="Marcar como endereço de entrega">
                      <Truck className="h-4 w-4" />
                    </Button>
                  )}
                  <Button type="button" variant="ghost" size="sm" onClick={() => openEdit(a)} title="Editar">
                    <Pencil className="h-4 w-4" />
                  </Button>
                  {!a.is_default && (
                    <Button type="button" variant="ghost" size="sm" onClick={() => remove(a.id)} title="Remover">
                      <Trash2 className="h-4 w-4 text-destructive" />
                    </Button>
                  )}
                </div>
              )}
            </li>
          ))}
        </ul>
      )}

      {editingId !== null && (
        <div className="rounded-md border border-border p-4 space-y-3 bg-muted/30">
          <div className="grid gap-3 md:grid-cols-2">
            <div className="md:col-span-2">
              <Label htmlFor="ea-label" className="cw-label">Rótulo</Label>
              <Input
                id="ea-label"
                placeholder="Ex: Matriz, Filial Centro, Obra"
                value={form.label}
                onChange={(e) => setForm((f) => ({ ...f, label: e.target.value }))}
              />
            </div>
            <div>
              <Label htmlFor="ea-cep" className="cw-label">CEP</Label>
              <div className="flex gap-2">
                <Input
                  id="ea-cep"
                  value={form.zip_code}
                  onChange={(e) => setForm((f) => ({ ...f, zip_code: maskCEP(e.target.value) }))}
                  onBlur={lookupCep}
                  inputMode="numeric"
                />
                <Button type="button" variant="outline" size="sm" onClick={lookupCep} disabled={cepLoading}>
                  {cepLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Search className="h-4 w-4" />}
                </Button>
              </div>
            </div>
            <div>
              <Label htmlFor="ea-numero" className="cw-label">Número</Label>
              <Input
                id="ea-numero"
                value={form.numero}
                onChange={(e) => setForm((f) => ({ ...f, numero: e.target.value }))}
              />
            </div>
            <div className="md:col-span-2">
              <Label htmlFor="ea-logradouro" className="cw-label">Logradouro</Label>
              <Input
                id="ea-logradouro"
                value={form.address_line_1}
                onChange={(e) => setForm((f) => ({ ...f, address_line_1: e.target.value }))}
              />
            </div>
            <div className="md:col-span-2">
              <Label htmlFor="ea-complemento" className="cw-label">Complemento</Label>
              <Input
                id="ea-complemento"
                value={form.address_line_2}
                onChange={(e) => setForm((f) => ({ ...f, address_line_2: e.target.value }))}
              />
            </div>
            <div>
              <Label htmlFor="ea-bairro" className="cw-label">Bairro</Label>
              <Input
                id="ea-bairro"
                value={form.neighborhood}
                onChange={(e) => setForm((f) => ({ ...f, neighborhood: e.target.value }))}
              />
            </div>
            <div>
              <Label htmlFor="ea-cidade" className="cw-label">Cidade</Label>
              <Input
                id="ea-cidade"
                value={form.city}
                onChange={(e) => setForm((f) => ({ ...f, city: e.target.value }))}
              />
            </div>
            <div>
              <Label htmlFor="ea-uf" className="cw-label">UF</Label>
              <Select value={form.state} onValueChange={(v) => setForm((f) => ({ ...f, state: v }))}>
                <SelectTrigger id="ea-uf">
                  <SelectValue placeholder="UF" />
                </SelectTrigger>
                <SelectContent>
                  {UF_OPTIONS.map((u) => (
                    <SelectItem key={u} value={u}>{u}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="flex items-center gap-2 md:col-span-2">
              <Checkbox
                id="ea-shipping"
                checked={form.is_shipping}
                onCheckedChange={(c) => setForm((f) => ({ ...f, is_shipping: c === true }))}
              />
              <Label htmlFor="ea-shipping" className="cursor-pointer text-sm font-normal">
                Usar como endereço de entrega (nota fiscal)
              </Label>
            </div>
          </div>

          {error && <p className="text-sm text-destructive">{error}</p>}

          <div className="flex justify-end gap-2">
            <Button type="button" variant="ghost" size="sm" onClick={cancel} disabled={saving}>
              Cancelar
            </Button>
            <Button type="button" size="sm" onClick={save} disabled={saving}>
              {saving && <Loader2 className="h-4 w-4 mr-1 animate-spin" />}
              Salvar endereço
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
