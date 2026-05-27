// Wave C-FE — ClassificacaoTab.tsx
//
// Tab 5 do drawer 760px Cliente. Segmento + tags + status + VIP toggle.
// Refs: ADR 0179 · Charter Index.charter.md v3 · HANDOFF_CLIENTES.md §2.5
// Cowork blueprint: prototipo-ui/prototipos/clientes/clientes-drawer.jsx::SectionClassificacao
//
// Contrato:
//   PATCH /cliente/{id}/classificacao  body: { segmento, tags[], status, vip }
//
// Pegadinhas:
//  - Segmento: radio com 6 valores (varejo/atacado/agência/corporativo/evento/governo)
//  - Tags: multi-select de 9 valores (varejo/atacado/corporativo/evento/parceiro/agência/governo/vip/reincidente)
//  - Status: select com 3 valores (ativo/inativo/bloqueado)
//  - VIP: boolean flag global (DIFERENTE da tag `vip`)
//  - Autosave on blur/change + optimistic UI + rollback 4xx/5xx

import { useCallback, useEffect, useRef, useState } from 'react';
import { Loader2, AlertCircle, CheckCircle2 } from 'lucide-react';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';

export interface ContactInfo {
  id: number;
  segmento?: string | null;
  tags?: string[] | null;
  status?: 'ativo' | 'inativo' | 'bloqueado' | string | null;
  vip?: boolean | null;
  // ADR 0188 Onda 4 — flags multi-papel (Wagner 2026-05-25 UX refactor:
  // movido de IdentificacaoTab pra cá · papel é classificação semântica).
  is_customer?: boolean | null;
  is_supplier?: boolean | null;
  is_employee?: boolean | null;
  is_representative?: boolean | null;
}

/** 4 papéis canônicos ADR 0188. Ordem visual estável (Cliente primeiro · papel
 *  mais comum em PME ROTA LIVRE biz=4). */
const PAPEL_OPTIONS: Array<{ flag: 'is_customer' | 'is_supplier' | 'is_employee' | 'is_representative'; label: string }> = [
  { flag: 'is_customer',       label: 'Cliente' },
  { flag: 'is_supplier',       label: 'Fornecedor' },
  { flag: 'is_employee',       label: 'Funcionário' },
  { flag: 'is_representative', label: 'Representante' },
];

export interface ClassificacaoTabProps {
  contact: ContactInfo;
  onSaved?: (field: string, value: unknown) => void;
  disabled?: boolean;
}

const SEGMENTO_OPTIONS: Array<{ value: string; label: string }> = [
  { value: 'varejo', label: 'Varejo (lojinha, loja própria)' },
  { value: 'atacado', label: 'Atacado / distribuição' },
  { value: 'agencia', label: 'Agência / parceiro de mídia' },
  { value: 'corporativo', label: 'Corporativo / B2B' },
  { value: 'evento', label: 'Evento pontual' },
  { value: 'governo', label: 'Governo / órgão público' },
];

const TAG_OPTIONS = [
  'varejo',
  'atacado',
  'corporativo',
  'evento',
  'parceiro',
  'agência',
  'governo',
  'vip',
  'reincidente',
];

const STATUS_OPTIONS: Array<{ value: 'ativo' | 'inativo' | 'bloqueado'; label: string; color: string }> = [
  { value: 'ativo', label: 'Ativo', color: 'text-emerald-700' },
  { value: 'inativo', label: 'Inativo', color: 'text-stone-700' },
  { value: 'bloqueado', label: 'Bloqueado', color: 'text-rose-700' },
];

function getCsrfToken(): string {
  return (
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? ''
  );
}

export default function ClassificacaoTab({ contact, onSaved, disabled = false }: ClassificacaoTabProps) {
  const [segmento, setSegmento] = useState<string>(contact.segmento ?? '');
  const [tags, setTags] = useState<string[]>(Array.isArray(contact.tags) ? contact.tags : []);
  const [status, setStatus] = useState<string>(contact.status ?? 'ativo');
  const [vip, setVip] = useState<boolean>(!!contact.vip);
  // ADR 0188 Onda 4 — 4 flags multi-papel (Wagner 2026-05-25 UX refactor).
  const [isCustomer, setIsCustomer] = useState<boolean>(Boolean(contact.is_customer));
  const [isSupplier, setIsSupplier] = useState<boolean>(Boolean(contact.is_supplier));
  const [isEmployee, setIsEmployee] = useState<boolean>(Boolean(contact.is_employee));
  const [isRepresentative, setIsRepresentative] = useState<boolean>(Boolean(contact.is_representative));

  const [savingField, setSavingField] = useState<string | null>(null);
  const [savedField, setSavedField] = useState<string | null>(null);
  const [errorField, setErrorField] = useState<{ field: string; message: string } | null>(null);

  const previousValuesRef = useRef<Record<string, unknown>>({});

  useEffect(() => {
    setSegmento(contact.segmento ?? '');
    setTags(Array.isArray(contact.tags) ? contact.tags : []);
    setStatus(contact.status ?? 'ativo');
    setVip(!!contact.vip);
    setIsCustomer(Boolean(contact.is_customer));
    setIsSupplier(Boolean(contact.is_supplier));
    setIsEmployee(Boolean(contact.is_employee));
    setIsRepresentative(Boolean(contact.is_representative));
    setErrorField(null);
    setSavedField(null);
  }, [contact.id]);

  const rollbackField = useCallback((field: string, prev: unknown) => {
    if (field === 'segmento') setSegmento((prev as string) ?? '');
    else if (field === 'tags') setTags(Array.isArray(prev) ? (prev as string[]) : []);
    else if (field === 'status') setStatus((prev as string) ?? 'ativo');
    else if (field === 'vip') setVip(!!prev);
  }, []);

  const performSave = useCallback(
    async (field: string, value: unknown, prev: unknown) => {
      if (disabled) return;
      setSavingField(field);
      setErrorField(null);
      try {
        const r = await fetch(`/cliente/${contact.id}/classificacao`, {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({ [field]: value }),
        });
        if (!r.ok) {
          rollbackField(field, prev);
          let msg = `Erro ${r.status} ao salvar.`;
          if (r.status === 422) {
            const j = await r.json().catch(() => ({}));
            msg = j?.errors?.[field]?.[0] ?? j?.message ?? msg;
          } else if (r.status === 403) msg = 'Sem permissão.';
          else if (r.status === 404) msg = 'Cliente não encontrado.';
          setErrorField({ field, message: msg });
          // eslint-disable-next-line no-console
          console.error(`[ClassificacaoTab] autosave ${field} falhou`, { status: r.status });
          return;
        }
        setSavedField(field);
        setTimeout(() => setSavedField((c) => (c === field ? null : c)), 1800);
        onSaved?.(field, value);
      } catch (err) {
        rollbackField(field, prev);
        setErrorField({ field, message: 'Falha de rede. Tente de novo.' });
        // eslint-disable-next-line no-console
        console.error(`[ClassificacaoTab] autosave ${field} network`, err);
      } finally {
        setSavingField((c) => (c === field ? null : c));
      }
    },
    [contact.id, disabled, onSaved, rollbackField]
  );

  const handleSegmentoChange = useCallback(
    (v: string) => {
      const prev = segmento;
      if (prev === v) return;
      setSegmento(v);
      previousValuesRef.current['segmento'] = prev;
      performSave('segmento', v, prev);
    },
    [segmento, performSave]
  );

  const handleToggleTag = useCallback(
    (tag: string) => {
      const prev = tags;
      const novoTags = tags.includes(tag) ? tags.filter((t) => t !== tag) : [...tags, tag];
      setTags(novoTags);
      previousValuesRef.current['tags'] = prev;
      performSave('tags', novoTags, prev);
    },
    [tags, performSave]
  );

  const handleStatusChange = useCallback(
    (v: string) => {
      const prev = status;
      if (prev === v) return;
      setStatus(v);
      previousValuesRef.current['status'] = prev;
      performSave('status', v, prev);
    },
    [status, performSave]
  );

  const handleVipToggle = useCallback(
    (checked: boolean) => {
      const prev = vip;
      if (prev === checked) return;
      setVip(checked);
      previousValuesRef.current['vip'] = prev;
      performSave('vip', checked, prev);
    },
    [vip, performSave]
  );

  // ADR 0188 Onda 4 — toggle papel via endpoint /papeis separado (não
  // /classificacao) pra isolar invariante ">=1 papel ativo" no backend.
  // Optimistic UI: troca state imediato + PATCH paralelo. Rollback no 4xx/5xx.
  const handlePapelToggle = useCallback(
    async (
      flag: 'is_customer' | 'is_supplier' | 'is_employee' | 'is_representative',
    ) => {
      if (disabled) return;

      const setters = {
        is_customer: setIsCustomer,
        is_supplier: setIsSupplier,
        is_employee: setIsEmployee,
        is_representative: setIsRepresentative,
      };
      const currentValues = {
        is_customer: isCustomer,
        is_supplier: isSupplier,
        is_employee: isEmployee,
        is_representative: isRepresentative,
      };
      const newValue = !currentValues[flag];

      setters[flag](newValue);
      setSavingField(flag);
      setErrorField(null);

      try {
        const r = await fetch(`/cliente/${contact.id}/papeis`, {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({ [flag]: newValue }),
        });

        if (!r.ok) {
          setters[flag](currentValues[flag]);
          let msg = `Erro ${r.status} ao salvar papel.`;
          if (r.status === 422) {
            const j = await r.json().catch(() => ({}));
            msg = j?.errors?.[flag]?.[0] ?? j?.errors?.is_customer?.[0] ?? msg;
          } else if (r.status === 403) {
            msg = 'Sem permissão pra editar papéis.';
          }
          setErrorField({ field: flag, message: msg });
          // eslint-disable-next-line no-console
          console.error(`[ClassificacaoTab] papeis ${flag} falhou`, { status: r.status });
          return;
        }

        setSavedField(flag);
        setTimeout(() => setSavedField((c) => (c === flag ? null : c)), 1800);
        onSaved?.(flag, newValue);
      } catch (err) {
        setters[flag](currentValues[flag]);
        setErrorField({ field: flag, message: 'Falha de rede. Tente de novo.' });
        // eslint-disable-next-line no-console
        console.error(`[ClassificacaoTab] papeis ${flag} network`, err);
      } finally {
        setSavingField((c) => (c === flag ? null : c));
      }
    },
    [contact.id, disabled, isCustomer, isSupplier, isEmployee, isRepresentative, onSaved],
  );

  // Helper pra map flag → state local (usado no JSX dos chips).
  const papelValue = (flag: typeof PAPEL_OPTIONS[number]['flag']): boolean => {
    if (flag === 'is_customer') return isCustomer;
    if (flag === 'is_supplier') return isSupplier;
    if (flag === 'is_employee') return isEmployee;
    return isRepresentative;
  };

  return (
    <div className="space-y-5">
      <div className="grid gap-4 md:grid-cols-2">
        {/* ADR 0188 Onda 4 — Papéis (4 chips toggleáveis · Wagner 2026-05-25
            UX feedback: chips estilo TagChip canon · padrão Stripe/Linear).
            Permite N papéis simultâneos no mesmo cadastro (insight Delphi).
            Backend invariante: ≥1 papel ativo (anti soft-delete acidental). */}
        <div className="md:col-span-2">
          <Label className="text-xs font-medium">
            Papéis <span className="text-muted-foreground font-normal">(clique pra alternar)</span>
          </Label>
          <div
            className="mt-1 flex flex-wrap gap-1.5"
            role="group"
            aria-label="Papéis do contato"
          >
            {PAPEL_OPTIONS.map(({ flag, label }) => {
              const checked = papelValue(flag);
              return (
                <button
                  key={flag}
                  type="button"
                  onClick={() => handlePapelToggle(flag)}
                  disabled={disabled || savingField === flag}
                  aria-pressed={checked}
                  className={`rounded-full border px-2.5 py-1 text-xs transition-colors ${
                    checked
                      ? 'border-primary bg-primary/10 text-foreground'
                      : 'border-input bg-background text-muted-foreground hover:border-muted-foreground/40 hover:text-foreground'
                  } ${disabled ? 'pointer-events-none opacity-50' : ''}`}
                >
                  {label}
                </button>
              );
            })}
          </div>
          <FieldStatus
            saving={['is_customer', 'is_supplier', 'is_employee', 'is_representative'].includes(savingField ?? '')}
            saved={['is_customer', 'is_supplier', 'is_employee', 'is_representative'].includes(savedField ?? '')}
            backendError={
              errorField?.field?.startsWith('is_') ? errorField.message : null
            }
          />
        </div>

        {/* Segmento — radio 6 valores */}
        <div className="md:col-span-2">
          <Label className="text-xs font-medium">Segmento</Label>
          <div
            role="radiogroup"
            aria-label="Segmento do cliente"
            className="mt-1 grid gap-2 md:grid-cols-2"
          >
            {SEGMENTO_OPTIONS.map((opt) => {
              const checked = segmento === opt.value;
              return (
                <label
                  key={opt.value}
                  className={`inline-flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-xs transition-colors ${
                    checked
                      ? 'border-primary bg-primary/10 text-foreground'
                      : 'border-input bg-background text-muted-foreground hover:border-muted-foreground/40'
                  } ${disabled ? 'pointer-events-none opacity-50' : ''}`}
                >
                  <input
                    type="radio"
                    name="cl-segmento"
                    value={opt.value}
                    checked={checked}
                    onChange={() => handleSegmentoChange(opt.value)}
                    className="sr-only"
                    disabled={disabled}
                  />
                  <span
                    aria-hidden
                    className={`h-2 w-2 shrink-0 rounded-full ${
                      checked ? 'bg-primary' : 'bg-muted-foreground/30'
                    }`}
                  />
                  <span className="leading-tight">{opt.label}</span>
                </label>
              );
            })}
          </div>
          <FieldStatus
            saving={savingField === 'segmento'}
            saved={savedField === 'segmento'}
            backendError={errorField?.field === 'segmento' ? errorField.message : null}
          />
        </div>

        {/* Tags — multi-select 9 valores */}
        <div className="md:col-span-2">
          <Label className="text-xs font-medium">
            Tags <span className="text-muted-foreground font-normal">(clique pra alternar)</span>
          </Label>
          <div
            className="mt-1 flex flex-wrap gap-1.5"
            role="group"
            aria-label="Tags do cliente"
          >
            {TAG_OPTIONS.map((tag) => {
              const checked = tags.includes(tag);
              return (
                <button
                  key={tag}
                  type="button"
                  onClick={() => handleToggleTag(tag)}
                  disabled={disabled}
                  aria-pressed={checked}
                  className={`rounded-full border px-2.5 py-1 text-xs transition-colors ${
                    checked
                      ? 'border-primary bg-primary/10 text-foreground'
                      : 'border-input bg-background text-muted-foreground hover:border-muted-foreground/40 hover:text-foreground'
                  } ${disabled ? 'pointer-events-none opacity-50' : ''}`}
                >
                  {tag}
                </button>
              );
            })}
          </div>
          <FieldStatus
            saving={savingField === 'tags'}
            saved={savedField === 'tags'}
            backendError={errorField?.field === 'tags' ? errorField.message : null}
          />
        </div>

        {/* Status — select */}
        <div>
          <Label htmlFor="cl-status" className="text-xs font-medium">
            Status
          </Label>
          <Select value={status} onValueChange={handleStatusChange} disabled={disabled}>
            <SelectTrigger id="cl-status" variant="cowork" className="w-full">
              <SelectValue placeholder="Selecionar status" />
            </SelectTrigger>
            <SelectContent>
              {STATUS_OPTIONS.map((o) => (
                <SelectItem key={o.value} value={o.value}>
                  <span className={`inline-flex items-center gap-2 ${o.color}`}>
                    <span
                      aria-hidden
                      className={`h-1.5 w-1.5 rounded-full ${
                        o.value === 'ativo'
                          ? 'bg-emerald-500'
                          : o.value === 'inativo'
                          ? 'bg-stone-400'
                          : 'bg-rose-500'
                      }`}
                    />
                    {o.label}
                  </span>
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <FieldStatus
            saving={savingField === 'status'}
            saved={savedField === 'status'}
            backendError={errorField?.field === 'status' ? errorField.message : null}
          />
        </div>

        {/* VIP toggle — boolean flag global (diferente da tag `vip`) */}
        <div>
          <Label htmlFor="cl-vip" className="text-xs font-medium">
            VIP <span className="text-muted-foreground font-normal">(opcional)</span>
          </Label>
          <div className="mt-1 flex items-center gap-3 rounded-md border border-input bg-background px-3 py-2">
            <Switch
              id="cl-vip"
              checked={vip}
              disabled={disabled}
              onCheckedChange={handleVipToggle}
              aria-label="Marcar cliente como VIP"
            />
            <div className="flex flex-col">
              <span className="text-xs font-medium">Marcar como VIP</span>
              <span className="text-[10px] text-muted-foreground">Prioridade na agenda de produção</span>
            </div>
          </div>
          <FieldStatus
            saving={savingField === 'vip'}
            saved={savedField === 'vip'}
            backendError={errorField?.field === 'vip' ? errorField.message : null}
          />
        </div>
      </div>
    </div>
  );
}

interface FieldStatusProps {
  saving?: boolean;
  saved?: boolean;
  backendError?: string | null;
}

function FieldStatus({ saving, saved, backendError }: FieldStatusProps) {
  if (backendError) {
    return (
      <p className="mt-1 inline-flex items-center gap-1 text-xs text-rose-600" role="alert">
        <AlertCircle size={11} aria-hidden /> {backendError}
      </p>
    );
  }
  if (saving) {
    return (
      <p className="mt-1 inline-flex items-center gap-1 text-xs text-muted-foreground" aria-live="polite">
        <Loader2 size={11} className="animate-spin" aria-hidden /> Salvando…
      </p>
    );
  }
  if (saved) {
    return (
      <p className="mt-1 inline-flex items-center gap-1 text-xs text-emerald-600" aria-live="polite">
        <CheckCircle2 size={11} aria-hidden /> Salvo
      </p>
    );
  }
  return null;
}
