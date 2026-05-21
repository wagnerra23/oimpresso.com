// Wave C-FE — EnderecoTab.tsx
//
// Tab 3 do drawer 760px Cliente. CEP + ViaCEP autopreenche + endereço completo.
// Refs: ADR 0179 · Charter Index.charter.md v3 · HANDOFF_CLIENTES.md §2.3
// Cowork blueprint: prototipo-ui/prototipos/clientes/clientes-drawer.jsx::SectionEndereco
//
// Contrato:
//   PATCH /cliente/{id}/endereco  body: { cep, endereco, numero, complemento, bairro, cidade, uf }
//   GET   /cliente/lookup/cep/{cep} → { logradouro, bairro, cidade, uf }
//
// Pegadinhas:
//  - Autosave on blur (debounce 800ms) + optimistic UI + rollback 4xx/5xx
//  - "Buscar CEP" é loader inline (anti-padrão T-AP-15 modal aninhado)
//  - ViaCEP server-side proxy obrigatório (ADR 0179 — biz=4 Larissa rate limit)
//  - UF: select 27 valores oficiais BR
//  - Endereço/Número/Bairro/Cidade ficam editáveis após lookup (user pode corrigir)

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Loader2, Search, CheckCircle2, AlertCircle } from 'lucide-react';
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

export interface ContactInfo {
  id: number;
  cep?: string | null;
  endereco?: string | null;
  address_line_1?: string | null;
  numero?: string | null;
  complemento?: string | null;
  bairro?: string | null;
  cidade?: string | null;
  city?: string | null;
  uf?: string | null;
  state?: string | null;
}

export interface EnderecoTabProps {
  contact: ContactInfo;
  onSaved?: (field: string, value: unknown) => void;
  disabled?: boolean;
}

type CepLookupState = 'idle' | 'loading' | 'ok' | 'error';

const DEBOUNCE_MS = 800;

// 27 UFs BR oficiais (IBGE)
const UF_OPTIONS = [
  'AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO',
  'MA', 'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR',
  'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO',
];

function getCsrfToken(): string {
  return (
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? ''
  );
}

export default function EnderecoTab({ contact, onSaved, disabled = false }: EnderecoTabProps) {
  const [cep, setCep] = useState<string>(maskCEP(contact.cep ?? ''));
  const [endereco, setEndereco] = useState<string>(contact.endereco ?? contact.address_line_1 ?? '');
  const [numero, setNumero] = useState<string>(contact.numero ?? '');
  const [complemento, setComplemento] = useState<string>(contact.complemento ?? '');
  const [bairro, setBairro] = useState<string>(contact.bairro ?? '');
  const [cidade, setCidade] = useState<string>(contact.cidade ?? contact.city ?? '');
  const [uf, setUf] = useState<string>(contact.uf ?? contact.state ?? '');

  const [savingField, setSavingField] = useState<string | null>(null);
  const [savedField, setSavedField] = useState<string | null>(null);
  const [errorField, setErrorField] = useState<{ field: string; message: string } | null>(null);
  const [cepLookup, setCepLookup] = useState<CepLookupState>('idle');
  const [cepLookupMsg, setCepLookupMsg] = useState<string | null>(null);

  const debounceTimersRef = useRef<Record<string, ReturnType<typeof setTimeout>>>({});
  const previousValuesRef = useRef<Record<string, unknown>>({});

  useEffect(() => {
    setCep(maskCEP(contact.cep ?? ''));
    setEndereco(contact.endereco ?? contact.address_line_1 ?? '');
    setNumero(contact.numero ?? '');
    setComplemento(contact.complemento ?? '');
    setBairro(contact.bairro ?? '');
    setCidade(contact.cidade ?? contact.city ?? '');
    setUf(contact.uf ?? contact.state ?? '');
    setErrorField(null);
    setSavedField(null);
    setCepLookup('idle');
    setCepLookupMsg(null);
  }, [contact.id]);

  const cepError = useMemo<string | null>(() => {
    const v = validateCEP(cep);
    if (v === false) return 'CEP precisa ter 8 dígitos.';
    return null;
  }, [cep]);

  const rollbackField = useCallback((field: string, prev: unknown) => {
    if (field === 'cep') setCep((prev as string) ?? '');
    else if (field === 'endereco') setEndereco((prev as string) ?? '');
    else if (field === 'numero') setNumero((prev as string) ?? '');
    else if (field === 'complemento') setComplemento((prev as string) ?? '');
    else if (field === 'bairro') setBairro((prev as string) ?? '');
    else if (field === 'cidade') setCidade((prev as string) ?? '');
    else if (field === 'uf') setUf((prev as string) ?? '');
  }, []);

  const performSave = useCallback(
    async (field: string, value: unknown, prev: unknown) => {
      if (disabled) return;
      setSavingField(field);
      setErrorField(null);
      try {
        const r = await fetch(`/cliente/${contact.id}/endereco`, {
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
          console.error(`[EnderecoTab] autosave ${field} falhou`, { status: r.status });
          return;
        }
        setSavedField(field);
        setTimeout(() => setSavedField((c) => (c === field ? null : c)), 1800);
        onSaved?.(field, value);
      } catch (err) {
        rollbackField(field, prev);
        setErrorField({ field, message: 'Falha de rede. Tente de novo.' });
        // eslint-disable-next-line no-console
        console.error(`[EnderecoTab] autosave ${field} network`, err);
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
      if (field === 'cep' && cepError) return;
      const prev = previousValuesRef.current[field];
      if (debounceTimersRef.current[field]) {
        clearTimeout(debounceTimersRef.current[field]);
        delete debounceTimersRef.current[field];
      }
      performSave(field, value, prev);
    },
    [cepError, performSave]
  );

  // ── Lookup CEP — loader inline ───────────────────────────────────────
  const handleCepLookup = useCallback(async () => {
    const digits = onlyDigits(cep);
    if (digits.length !== 8) return;
    setCepLookup('loading');
    setCepLookupMsg(null);
    try {
      const r = await fetch(`/cliente/lookup/cep/${digits}`, {
        method: 'GET',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
      });
      if (!r.ok) {
        setCepLookup('error');
        setCepLookupMsg(r.status === 404 ? 'CEP não encontrado.' : `Erro ${r.status}.`);
        return;
      }
      const json = await r.json();
      const logradouro = (json?.logradouro as string) ?? '';
      const novoBairro = (json?.bairro as string) ?? '';
      const novaCidade = (json?.cidade as string) ?? '';
      const novaUf = (json?.uf as string) ?? '';

      if (logradouro) {
        setEndereco(logradouro);
        performSave('endereco', logradouro, endereco);
      }
      if (novoBairro) {
        setBairro(novoBairro);
        performSave('bairro', novoBairro, bairro);
      }
      if (novaCidade) {
        setCidade(novaCidade);
        performSave('cidade', novaCidade, cidade);
      }
      if (novaUf) {
        setUf(novaUf);
        performSave('uf', novaUf, uf);
      }
      setCepLookup('ok');
      setCepLookupMsg('Endereço preenchido pelo ViaCEP.');
      setTimeout(() => {
        setCepLookup('idle');
        setCepLookupMsg(null);
      }, 2800);
    } catch (err) {
      setCepLookup('error');
      setCepLookupMsg('Falha ao consultar ViaCEP.');
      // eslint-disable-next-line no-console
      console.error('[EnderecoTab] cep lookup failed', err);
    }
  }, [cep, endereco, bairro, cidade, uf, performSave]);

  const handleUfChange = useCallback(
    (v: string) => {
      const prev = uf;
      if (prev === v) return;
      setUf(v);
      performSave('uf', v, prev);
    },
    [uf, performSave]
  );

  return (
    <div className="space-y-5">
      <div className="grid gap-4 md:grid-cols-2">
        <div>
          <Label htmlFor="ed-cep" className="text-xs font-medium">
            CEP
          </Label>
          <div className="flex gap-2">
            <Input
              id="ed-cep"
              value={cep}
              placeholder="00000-000"
              disabled={disabled}
              inputMode="numeric"
              aria-invalid={!!cepError}
              aria-describedby={cepError ? 'ed-cep-error' : undefined}
              onChange={(e) => {
                const prev = cep;
                const v = maskCEP(e.target.value);
                setCep(v);
                scheduleAutosave('cep', v, prev);
              }}
              onBlur={(e) => {
                handleBlur('cep', e.target.value);
                if (onlyDigits(e.target.value).length === 8) {
                  handleCepLookup();
                }
              }}
              className={cepError ? 'border-rose-500 focus-visible:ring-rose-400' : ''}
            />
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={disabled || cepLookup === 'loading' || onlyDigits(cep).length !== 8}
              onClick={handleCepLookup}
              className="shrink-0"
              aria-label="Buscar CEP no ViaCEP"
            >
              {cepLookup === 'loading' ? (
                <>
                  <Loader2 size={14} className="animate-spin" /> Buscando…
                </>
              ) : cepLookup === 'ok' ? (
                <>
                  <CheckCircle2 size={14} className="text-emerald-600" /> Encontrado
                </>
              ) : (
                <>
                  <Search size={14} /> Buscar CEP
                </>
              )}
            </Button>
          </div>
          {cepLookupMsg && (
            <p
              className={`mt-1 text-xs ${cepLookup === 'error' ? 'text-rose-600' : 'text-emerald-600'}`}
              role="status"
              aria-live="polite"
            >
              {cepLookupMsg}
            </p>
          )}
          <FieldStatus
            error={cepError}
            errorId="ed-cep-error"
            saving={savingField === 'cep'}
            saved={savedField === 'cep'}
            backendError={errorField?.field === 'cep' ? errorField.message : null}
          />
        </div>

        <div>
          <Label htmlFor="ed-numero" className="text-xs font-medium">
            Número
          </Label>
          <Input
            id="ed-numero"
            value={numero}
            placeholder="123"
            disabled={disabled}
            onChange={(e) => {
              const prev = numero;
              const v = e.target.value;
              setNumero(v);
              scheduleAutosave('numero', v, prev);
            }}
            onBlur={(e) => handleBlur('numero', e.target.value)}
          />
          <FieldStatus
            saving={savingField === 'numero'}
            saved={savedField === 'numero'}
            backendError={errorField?.field === 'numero' ? errorField.message : null}
          />
        </div>

        <div className="md:col-span-2">
          <Label htmlFor="ed-endereco" className="text-xs font-medium">
            Endereço
          </Label>
          <Input
            id="ed-endereco"
            value={endereco}
            placeholder="Rua, avenida, alameda…"
            disabled={disabled}
            onChange={(e) => {
              const prev = endereco;
              const v = e.target.value;
              setEndereco(v);
              scheduleAutosave('endereco', v, prev);
            }}
            onBlur={(e) => handleBlur('endereco', e.target.value)}
          />
          <FieldStatus
            saving={savingField === 'endereco'}
            saved={savedField === 'endereco'}
            backendError={errorField?.field === 'endereco' ? errorField.message : null}
          />
        </div>

        <div className="md:col-span-2">
          <Label htmlFor="ed-complemento" className="text-xs font-medium">
            Complemento <span className="text-muted-foreground font-normal">(opcional)</span>
          </Label>
          <Input
            id="ed-complemento"
            value={complemento}
            placeholder="Apto, conjunto, sala…"
            disabled={disabled}
            onChange={(e) => {
              const prev = complemento;
              const v = e.target.value;
              setComplemento(v);
              scheduleAutosave('complemento', v, prev);
            }}
            onBlur={(e) => handleBlur('complemento', e.target.value)}
          />
          <FieldStatus
            saving={savingField === 'complemento'}
            saved={savedField === 'complemento'}
            backendError={errorField?.field === 'complemento' ? errorField.message : null}
          />
        </div>

        <div>
          <Label htmlFor="ed-bairro" className="text-xs font-medium">
            Bairro
          </Label>
          <Input
            id="ed-bairro"
            value={bairro}
            placeholder=""
            disabled={disabled}
            onChange={(e) => {
              const prev = bairro;
              const v = e.target.value;
              setBairro(v);
              scheduleAutosave('bairro', v, prev);
            }}
            onBlur={(e) => handleBlur('bairro', e.target.value)}
          />
          <FieldStatus
            saving={savingField === 'bairro'}
            saved={savedField === 'bairro'}
            backendError={errorField?.field === 'bairro' ? errorField.message : null}
          />
        </div>

        <div>
          <Label htmlFor="ed-cidade" className="text-xs font-medium">
            Cidade
          </Label>
          <Input
            id="ed-cidade"
            value={cidade}
            placeholder=""
            disabled={disabled}
            onChange={(e) => {
              const prev = cidade;
              const v = e.target.value;
              setCidade(v);
              scheduleAutosave('cidade', v, prev);
            }}
            onBlur={(e) => handleBlur('cidade', e.target.value)}
          />
          <FieldStatus
            saving={savingField === 'cidade'}
            saved={savedField === 'cidade'}
            backendError={errorField?.field === 'cidade' ? errorField.message : null}
          />
        </div>

        <div>
          <Label htmlFor="ed-uf" className="text-xs font-medium">
            UF
          </Label>
          <Select value={uf} onValueChange={handleUfChange} disabled={disabled}>
            <SelectTrigger id="ed-uf" className="w-full">
              <SelectValue placeholder="UF" />
            </SelectTrigger>
            <SelectContent>
              {UF_OPTIONS.map((u) => (
                <SelectItem key={u} value={u}>
                  {u}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <FieldStatus
            saving={savingField === 'uf'}
            saved={savedField === 'uf'}
            backendError={errorField?.field === 'uf' ? errorField.message : null}
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
