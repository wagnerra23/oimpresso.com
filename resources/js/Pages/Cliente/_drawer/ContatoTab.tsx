// Wave C-FE — ContatoTab.tsx
//
// Tab 2 do drawer 760px Cliente. tel/tel2/email/site/canal preferido.
// Refs: ADR 0179 · Charter Index.charter.md v3 · HANDOFF_CLIENTES.md §2.2
// Cowork blueprint: prototipo-ui/prototipos/clientes/clientes-drawer.jsx::SectionContato
//
// Contrato:
//   PATCH /cliente/{id}/contato  body: { tel, tel2, email, site, canal }
//
// Pegadinhas:
//  - Autosave on blur (debounce 800ms) + optimistic UI + rollback 4xx/5xx
//  - Telefone máscara (00) 0 0000-0000 (canon `maskTel` em br-mask.ts)
//  - Email regex inline + erro UX-only — backend revalida
//  - Canal preferido: radio com 4 valores (whatsapp/email/telefone/presencial)

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Loader2, AlertCircle, CheckCircle2 } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { maskTel } from '@/Lib/br-mask';
import { validateEmail } from '@/Lib/br-validate';

export interface ContactInfo {
  id: number;
  tel?: string | null;
  tel2?: string | null;
  mobile?: string | null;
  landline?: string | null;
  email?: string | null;
  site?: string | null;
  canal?: 'whatsapp' | 'email' | 'telefone' | 'presencial' | null;
}

export interface ContatoTabProps {
  contact: ContactInfo;
  onSaved?: (field: string, value: unknown) => void;
  disabled?: boolean;
}

type CanalValue = 'whatsapp' | 'email' | 'telefone' | 'presencial';

const DEBOUNCE_MS = 800;

const CANAIS: Array<{ value: CanalValue; label: string }> = [
  { value: 'whatsapp', label: 'WhatsApp' },
  { value: 'email', label: 'E-mail' },
  { value: 'telefone', label: 'Telefone' },
  { value: 'presencial', label: 'Presencial' },
];

function getCsrfToken(): string {
  return (
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? ''
  );
}

export default function ContatoTab({ contact, onSaved, disabled = false }: ContatoTabProps) {
  // Resolve tel inicial: prioriza `tel` (nova coluna Wave B), fallback pra mobile/landline UPOS legacy
  const initialTel = contact.tel ?? contact.mobile ?? contact.landline ?? '';
  const [tel, setTel] = useState<string>(maskTel(initialTel));
  const [tel2, setTel2] = useState<string>(maskTel(contact.tel2 ?? ''));
  const [email, setEmail] = useState<string>(contact.email ?? '');
  const [site, setSite] = useState<string>(contact.site ?? '');
  const [canal, setCanal] = useState<CanalValue | ''>((contact.canal as CanalValue) ?? '');

  const [savingField, setSavingField] = useState<string | null>(null);
  const [savedField, setSavedField] = useState<string | null>(null);
  const [errorField, setErrorField] = useState<{ field: string; message: string } | null>(null);

  const debounceTimersRef = useRef<Record<string, ReturnType<typeof setTimeout>>>({});
  const previousValuesRef = useRef<Record<string, unknown>>({});

  useEffect(() => {
    const t = contact.tel ?? contact.mobile ?? contact.landline ?? '';
    setTel(maskTel(t));
    setTel2(maskTel(contact.tel2 ?? ''));
    setEmail(contact.email ?? '');
    setSite(contact.site ?? '');
    setCanal((contact.canal as CanalValue) ?? '');
    setErrorField(null);
    setSavedField(null);
  }, [contact.id]);

  // ── Validação inline ─────────────────────────────────────────────────
  const emailError = useMemo<string | null>(() => {
    const v = validateEmail(email);
    if (v === false) return 'Formato de e-mail inválido.';
    return null;
  }, [email]);

  // ── Autosave debounced ───────────────────────────────────────────────
  const rollbackField = useCallback((field: string, prev: unknown) => {
    if (field === 'tel') setTel((prev as string) ?? '');
    else if (field === 'tel2') setTel2((prev as string) ?? '');
    else if (field === 'email') setEmail((prev as string) ?? '');
    else if (field === 'site') setSite((prev as string) ?? '');
    else if (field === 'canal') setCanal((prev as CanalValue | '') ?? '');
  }, []);

  const performSave = useCallback(
    async (field: string, value: unknown, prev: unknown) => {
      if (disabled) return;
      setSavingField(field);
      setErrorField(null);

      try {
        const r = await fetch(`/cliente/${contact.id}/contato`, {
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
          console.error(`[ContatoTab] autosave ${field} falhou`, { status: r.status });
          return;
        }
        setSavedField(field);
        setTimeout(() => setSavedField((c) => (c === field ? null : c)), 1800);
        onSaved?.(field, value);
      } catch (err) {
        rollbackField(field, prev);
        setErrorField({ field, message: 'Falha de rede. Tente de novo.' });
        // eslint-disable-next-line no-console
        console.error(`[ContatoTab] autosave ${field} network`, err);
      } finally {
        setSavingField((c) => (c === field ? null : c));
      }
    },
    [contact.id, disabled, onSaved, rollbackField]
  );

  const scheduleAutosave = useCallback(
    (field: string, value: unknown, prev: unknown) => {
      if (debounceTimersRef.current[field]) clearTimeout(debounceTimersRef.current[field]);
      previousValuesRef.current[field] = prev;
      debounceTimersRef.current[field] = setTimeout(() => {
        performSave(field, value, previousValuesRef.current[field]);
      }, DEBOUNCE_MS);
    },
    [performSave]
  );

  useEffect(() => {
    return () => {
      Object.values(debounceTimersRef.current).forEach((t) => clearTimeout(t));
    };
  }, []);

  const handleBlur = useCallback(
    (field: string, value: unknown) => {
      if (field === 'email' && emailError) return;
      const prev = previousValuesRef.current[field];
      if (debounceTimersRef.current[field]) {
        clearTimeout(debounceTimersRef.current[field]);
        delete debounceTimersRef.current[field];
      }
      performSave(field, value, prev);
    },
    [emailError, performSave]
  );

  const handleCanalChange = useCallback(
    (v: CanalValue) => {
      const prev = canal;
      if (prev === v) return;
      setCanal(v);
      performSave('canal', v, prev);
    },
    [canal, performSave]
  );

  return (
    <div className="space-y-5">
      <div className="grid gap-4 md:grid-cols-2">
        <div>
          <Label htmlFor="ct-tel" className="text-xs font-medium">
            Telefone principal
          </Label>
          <Input
            id="ct-tel"
            value={tel}
            placeholder="(00) 0 0000-0000"
            disabled={disabled}
            inputMode="tel"
            onChange={(e) => {
              const prev = tel;
              const v = maskTel(e.target.value);
              setTel(v);
              scheduleAutosave('tel', v, prev);
            }}
            onBlur={(e) => handleBlur('tel', e.target.value)}
          />
          <FieldStatus
            saving={savingField === 'tel'}
            saved={savedField === 'tel'}
            backendError={errorField?.field === 'tel' ? errorField.message : null}
          />
        </div>

        <div>
          <Label htmlFor="ct-tel2" className="text-xs font-medium">
            Telefone alternativo <span className="text-muted-foreground font-normal">(opcional)</span>
          </Label>
          <Input
            id="ct-tel2"
            value={tel2}
            placeholder="(00) 0 0000-0000"
            disabled={disabled}
            inputMode="tel"
            onChange={(e) => {
              const prev = tel2;
              const v = maskTel(e.target.value);
              setTel2(v);
              scheduleAutosave('tel2', v, prev);
            }}
            onBlur={(e) => handleBlur('tel2', e.target.value)}
          />
          <FieldStatus
            saving={savingField === 'tel2'}
            saved={savedField === 'tel2'}
            backendError={errorField?.field === 'tel2' ? errorField.message : null}
          />
        </div>

        <div className="md:col-span-2">
          <Label htmlFor="ct-email" className="text-xs font-medium">
            E-mail
          </Label>
          <Input
            id="ct-email"
            type="email"
            value={email}
            placeholder="contato@exemplo.com.br"
            disabled={disabled}
            aria-invalid={!!emailError}
            aria-describedby={emailError ? 'ct-email-error' : undefined}
            onChange={(e) => {
              const prev = email;
              const v = e.target.value;
              setEmail(v);
              scheduleAutosave('email', v, prev);
            }}
            onBlur={(e) => handleBlur('email', e.target.value)}
            className={emailError ? 'border-rose-500 focus-visible:ring-rose-400' : ''}
          />
          <FieldStatus
            error={emailError}
            errorId="ct-email-error"
            saving={savingField === 'email'}
            saved={savedField === 'email'}
            backendError={errorField?.field === 'email' ? errorField.message : null}
          />
        </div>

        <div className="md:col-span-2">
          <Label htmlFor="ct-site" className="text-xs font-medium">
            Site <span className="text-muted-foreground font-normal">(opcional)</span>
          </Label>
          <Input
            id="ct-site"
            type="url"
            value={site}
            placeholder="exemplo.com.br"
            disabled={disabled}
            onChange={(e) => {
              const prev = site;
              const v = e.target.value;
              setSite(v);
              scheduleAutosave('site', v, prev);
            }}
            onBlur={(e) => handleBlur('site', e.target.value)}
          />
          <FieldStatus
            saving={savingField === 'site'}
            saved={savedField === 'site'}
            backendError={errorField?.field === 'site' ? errorField.message : null}
          />
        </div>

        <div className="md:col-span-2">
          <Label className="text-xs font-medium">
            Canal preferido <span className="text-muted-foreground font-normal">(opcional)</span>
          </Label>
          <div
            role="radiogroup"
            aria-label="Canal preferido"
            className="mt-1 flex flex-wrap gap-2"
          >
            {CANAIS.map((opt) => {
              const checked = canal === opt.value;
              return (
                <label
                  key={opt.value}
                  className={`inline-flex cursor-pointer items-center gap-2 rounded-md border px-3 py-1.5 text-xs transition-colors ${
                    checked
                      ? 'border-primary bg-primary/10 text-foreground'
                      : 'border-input bg-background text-muted-foreground hover:border-muted-foreground/40'
                  } ${disabled ? 'pointer-events-none opacity-50' : ''}`}
                >
                  <input
                    type="radio"
                    name="ct-canal"
                    value={opt.value}
                    checked={checked}
                    onChange={() => handleCanalChange(opt.value)}
                    className="sr-only"
                    disabled={disabled}
                  />
                  <span
                    aria-hidden
                    className={`h-2 w-2 rounded-full ${
                      checked ? 'bg-primary' : 'bg-muted-foreground/30'
                    }`}
                  />
                  {opt.label}
                </label>
              );
            })}
          </div>
          <FieldStatus
            saving={savingField === 'canal'}
            saved={savedField === 'canal'}
            backendError={errorField?.field === 'canal' ? errorField.message : null}
          />
        </div>
      </div>
    </div>
  );
}

interface FieldStatusProps {
  error?: string | null;
  errorId?: string;
  saving?: boolean;
  saved?: boolean;
  backendError?: string | null;
}

function FieldStatus({ error, errorId, saving, saved, backendError }: FieldStatusProps) {
  if (error) {
    return (
      <p id={errorId} className="mt-1 inline-flex items-center gap-1 text-xs text-rose-600" role="alert">
        <AlertCircle size={11} aria-hidden /> {error}
      </p>
    );
  }
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
