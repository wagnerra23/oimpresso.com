// Wave C-FE — EnderecoTab.tsx
//
// Tab 3 do drawer 760px Cliente. CEP + ViaCEP autopreenche + endereço completo.
// Refs: ADR 0179 · Charter Index.charter.md v3 · HANDOFF_CLIENTES.md §2.3
// Cowork blueprint: prototipo-ui/prototipos/clientes/clientes-drawer.jsx::SectionEndereco
//
// Contrato:
//   PATCH /cliente/{id}/endereco
//     body canon: { zip_code, address_line_1, address_line_2, numero, neighborhood, city, state }
//   GET   /cliente/lookup/cep/{cep} → { logradouro, bairro, cidade, uf } (PT-BR ViaCEP)
//
// Bug fix 2026-05-22 — naming canon:
//   - Antes: body usava PT-BR (cep/endereco/numero/complemento/bairro/cidade/uf).
//     Backend valida só canon EN (whitelist), descartava tudo silenciosamente.
//     Autosave mostrava "Salvo" mas nada persistia no DB.
//   - Agora: state + PATCH body usam canon do schema. Labels visuais PT-BR
//     preservados (UX BR não muda).
//   - `numero` BR canon restaurado pela migration 2026_05_22_120000_add_numero_to_contacts
//     (regressão UPOS 6.7 — mesma situação de ADR 0178 pros campos fiscais).
//
// Pegadinhas:
//  - Autosave on blur (debounce 800ms) + optimistic UI + rollback 4xx/5xx
//  - "Buscar CEP" é loader inline (anti-padrão T-AP-15 modal aninhado)
//  - ViaCEP server-side proxy obrigatório (ADR 0179 — biz=4 Larissa rate limit)
//  - UF: select 27 valores oficiais BR

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Loader2, Search, CheckCircle2, AlertCircle } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import EnderecosEntregaList from './EnderecosEntregaList';
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
  // Canon UPOS (preferido — vem do backend autosave response).
  zip_code?: string | null;
  address_line_1?: string | null;
  address_line_2?: string | null;
  numero?: string | null;
  neighborhood?: string | null;
  city?: string | null;
  state?: string | null;
  // Endereço de entrega (shipping_address) — opcional, texto livre.
  shipping_address?: string | null;
  // Aliases PT-BR (legado — listagem em /cliente emite assim hoje).
  cep?: string | null;
  endereco?: string | null;
  complemento?: string | null;
  bairro?: string | null;
  cidade?: string | null;
  uf?: string | null;
}

export interface EnderecoTabProps {
  contact: ContactInfo;
  onSaved?: (field: string, value: unknown) => void;
  /**
   * Wagner 2026-05-27 — chamado apos lookup CEP persistir campos novos no
   * banco. Pai (ClienteSheet) atualiza `draftContact` ou row no parent state
   * DIRETAMENTE pra evitar cache stale Inertia (rows.find retornaria snapshot
   * pre-lookup). Padrao alinhado ao `IdentificacaoTab.onContactUpdated`.
   *
   * Sintoma sem este callback: user clica Buscar CEP → 5 PATCHes 200 OK →
   * fecha drawer → reabre → campos voltam aos VELHOS (rows nao atualizou).
   */
  onContactUpdated?: (patched: Record<string, unknown>) => void;
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

export default function EnderecoTab({ contact, onSaved, onContactUpdated, disabled = false }: EnderecoTabProps) {
  // Inicialização — preferir canon UPOS, fallback PT-BR legado pra graceful com
  // a listagem (Index.tsx ainda emite cidade/uf PT-BR no payload da tabela).
  const [zipCode, setZipCode] = useState<string>(maskCEP(contact.zip_code ?? contact.cep ?? ''));
  const [addressLine1, setAddressLine1] = useState<string>(contact.address_line_1 ?? contact.endereco ?? '');
  const [numero, setNumero] = useState<string>(contact.numero ?? '');
  const [addressLine2, setAddressLine2] = useState<string>(contact.address_line_2 ?? contact.complemento ?? '');
  const [neighborhood, setNeighborhood] = useState<string>(contact.neighborhood ?? contact.bairro ?? '');
  const [city, setCity] = useState<string>(contact.city ?? contact.cidade ?? '');
  const [stateUf, setStateUf] = useState<string>(contact.state ?? contact.uf ?? '');

  const [savingField, setSavingField] = useState<string | null>(null);
  const [savedField, setSavedField] = useState<string | null>(null);
  const [errorField, setErrorField] = useState<{ field: string; message: string } | null>(null);
  const [cepLookup, setCepLookup] = useState<CepLookupState>('idle');
  const [cepLookupMsg, setCepLookupMsg] = useState<string | null>(null);

  const debounceTimersRef = useRef<Record<string, ReturnType<typeof setTimeout>>>({});
  const previousValuesRef = useRef<Record<string, unknown>>({});

  useEffect(() => {
    // Fix Wagner 2026-05-27 — deps reduzidas pra `[contact.id]` APENAS
    // (alinha com ContatoTab/IdentificacaoTab/ComercialTab). Antes deps expandidas
    // `[contact.zip_code, contact.address_line_1, ...]` disparavam re-sync toda vez
    // que o parent recebia update do `contact` (router.reload, onContactUpdated)
    // → sobrescrevia state local que o user tinha digitado em outra aba e voltado
    // ("trocou as outras abas apaga tudo"). Agora useEffect só roda na mount
    // inicial (drawer abre) ou se drawer abrir cliente diferente.
    //
    // Trade-off: pós CNPJ lookup, o EnderecoTab NÃO auto-atualiza state local
    // (user precisa ver via key remount controlado pelo pai via `key={enderecoVersion}`
    // em ClienteSheet — disparado por onCnpjEnderecoPersisted).
    setZipCode(maskCEP((contact.zip_code ?? contact.cep ?? '') as string));
    setAddressLine1((contact.address_line_1 ?? contact.endereco ?? '') as string);
    setNumero((contact.numero ?? '') as string);
    setAddressLine2((contact.address_line_2 ?? contact.complemento ?? '') as string);
    setNeighborhood((contact.neighborhood ?? contact.bairro ?? '') as string);
    setCity((contact.city ?? contact.cidade ?? '') as string);
    setStateUf((contact.state ?? contact.uf ?? '') as string);
    setErrorField(null);
    setSavedField(null);
    setCepLookup('idle');
    setCepLookupMsg(null);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [contact.id]);

  const cepError = useMemo<string | null>(() => {
    const v = validateCEP(zipCode);
    if (v === false) return 'CEP precisa ter 8 dígitos.';
    return null;
  }, [zipCode]);

  // Field key = nome canon do schema (zip_code, address_line_1, ...).
  // Setter por field pra rollback em caso de 4xx/5xx.
  const rollbackField = useCallback((field: string, prev: unknown) => {
    const s = (prev as string) ?? '';
    if (field === 'zip_code') setZipCode(s);
    else if (field === 'address_line_1') setAddressLine1(s);
    else if (field === 'address_line_2') setAddressLine2(s);
    else if (field === 'numero') setNumero(s);
    else if (field === 'neighborhood') setNeighborhood(s);
    else if (field === 'city') setCity(s);
    else if (field === 'state') setStateUf(s);
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
           
          console.error(`[EnderecoTab] autosave ${field} falhou`, { status: r.status });
          return;
        }
        setSavedField(field);
        setTimeout(() => setSavedField((c) => (c === field ? null : c)), 1800);
        onSaved?.(field, value);
      } catch (err) {
        rollbackField(field, prev);
        setErrorField({ field, message: 'Falha de rede. Tente de novo.' });
         
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
      if (field === 'zip_code' && cepError) return;
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
    const digits = onlyDigits(zipCode);
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
      // ViaCEP retorna PT-BR (logradouro/complemento/bairro/cidade/uf).
      // Mapeamos pra canon do schema (PR #1422). `complemento` adicionado
      // 2026-05-23 (PR #1419) — fecha gap 80% → 100% aderência ViaCEP.
      const novoLogr = (json?.logradouro as string) ?? '';
      const novoComplemento = (json?.complemento as string) ?? '';
      const novoBairro = (json?.bairro as string) ?? '';
      const novaCidade = (json?.cidade as string) ?? '';
      const novaUf = (json?.uf as string) ?? '';

      // Wagner 2026-05-27 — acumula campos preenchidos pra emitir 1 callback batch
      // ao final ao parent (ClienteSheet) atualizar draftContact/rows direto
      // sem depender de router.reload (que nao pega Inertia::defer cache).
      const contactPatch: Record<string, unknown> = {};

      if (novoLogr) {
        setAddressLine1(novoLogr);
        performSave('address_line_1', novoLogr, addressLine1);
        contactPatch.address_line_1 = novoLogr;
      }
      // Complemento ViaCEP (ex "lado impar 612 a 1510" SP) — SOBRESCREVE
      // (política feedback-lookup-cnpj-sobrescreve-dados: dado oficial público).
      if (novoComplemento) {
        setAddressLine2(novoComplemento);
        performSave('address_line_2', novoComplemento, addressLine2);
        contactPatch.address_line_2 = novoComplemento;
      }
      if (novoBairro) {
        setNeighborhood(novoBairro);
        performSave('neighborhood', novoBairro, neighborhood);
        contactPatch.neighborhood = novoBairro;
      }
      if (novaCidade) {
        setCity(novaCidade);
        performSave('city', novaCidade, city);
        contactPatch.city = novaCidade;
      }
      if (novaUf) {
        setStateUf(novaUf);
        performSave('state', novaUf, stateUf);
        contactPatch.state = novaUf;
      }
      // Notifica parent pra atualizar draftContact/row -- evita cache stale
      // Inertia ao fechar+reabrir drawer pos lookup (bug Wagner 2026-05-27).
      if (Object.keys(contactPatch).length > 0) {
        onContactUpdated?.(contactPatch);
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
       
      console.error('[EnderecoTab] cep lookup failed', err);
    }
  }, [zipCode, addressLine1, addressLine2, neighborhood, city, stateUf, performSave, onContactUpdated]);

  const handleUfChange = useCallback(
    (v: string) => {
      const prev = stateUf;
      if (prev === v) return;
      setStateUf(v);
      performSave('state', v, prev);
    },
    [stateUf, performSave]
  );

  return (
    <div className="space-y-5">
      <div className="grid gap-4 md:grid-cols-2">
        <div>
          <Label htmlFor="ed-cep" className="cw-label">
            CEP
          </Label>
          <div className="flex gap-2">
            <Input
              variant="cowork"
              id="ed-cep"
              value={zipCode}
              placeholder="00000-000"
              disabled={disabled}
              inputMode="numeric"
              aria-invalid={!!cepError}
              aria-describedby={cepError ? 'ed-cep-error' : undefined}
              onChange={(e) => {
                const prev = zipCode;
                const v = maskCEP(e.target.value);
                setZipCode(v);
                scheduleAutosave('zip_code', v, prev);
              }}
              onBlur={(e) => {
                handleBlur('zip_code', e.target.value);
                if (onlyDigits(e.target.value).length === 8) {
                  handleCepLookup();
                }
              }}
              /* aria-invalid já dispara `.cw-input[aria-invalid="true"]` no CSS (border error) — não precisa className condicional duplicado */
            />
            <Button
              type="button"
              variant="outline"
              size="sm"
              disabled={disabled || cepLookup === 'loading' || onlyDigits(zipCode).length !== 8}
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
                  <CheckCircle2 size={14} className="text-success-fg" /> Encontrado
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
              className={`mt-1 text-xs ${cepLookup === 'error' ? 'text-destructive' : 'text-success-fg'}`}
              role="status"
              aria-live="polite"
            >
              {cepLookupMsg}
            </p>
          )}
          <FieldStatus
            error={cepError}
            errorId="ed-cep-error"
            saving={savingField === 'zip_code'}
            saved={savedField === 'zip_code'}
            backendError={errorField?.field === 'zip_code' ? errorField.message : null}
          />
        </div>

        <div>
          <Label htmlFor="ed-numero" className="cw-label">
            Número
          </Label>
          <Input
            variant="cowork"
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
          <Label htmlFor="ed-endereco" className="cw-label">
            Endereço
          </Label>
          <Input
            variant="cowork"
            id="ed-endereco"
            value={addressLine1}
            placeholder="Rua, avenida, alameda…"
            disabled={disabled}
            onChange={(e) => {
              const prev = addressLine1;
              const v = e.target.value;
              setAddressLine1(v);
              scheduleAutosave('address_line_1', v, prev);
            }}
            onBlur={(e) => handleBlur('address_line_1', e.target.value)}
          />
          <FieldStatus
            saving={savingField === 'address_line_1'}
            saved={savedField === 'address_line_1'}
            backendError={errorField?.field === 'address_line_1' ? errorField.message : null}
          />
        </div>

        <div className="md:col-span-2">
          <Label htmlFor="ed-complemento" className="cw-label">
            Complemento <span className="text-muted-foreground font-normal">(opcional)</span>
          </Label>
          <Input
            variant="cowork"
            id="ed-complemento"
            value={addressLine2}
            placeholder="Apto, conjunto, sala…"
            disabled={disabled}
            onChange={(e) => {
              const prev = addressLine2;
              const v = e.target.value;
              setAddressLine2(v);
              scheduleAutosave('address_line_2', v, prev);
            }}
            onBlur={(e) => handleBlur('address_line_2', e.target.value)}
          />
          <FieldStatus
            saving={savingField === 'address_line_2'}
            saved={savedField === 'address_line_2'}
            backendError={errorField?.field === 'address_line_2' ? errorField.message : null}
          />
        </div>

        <div>
          <Label htmlFor="ed-bairro" className="cw-label">
            Bairro
          </Label>
          <Input
            variant="cowork"
            id="ed-bairro"
            value={neighborhood}
            placeholder=""
            disabled={disabled}
            onChange={(e) => {
              const prev = neighborhood;
              const v = e.target.value;
              setNeighborhood(v);
              scheduleAutosave('neighborhood', v, prev);
            }}
            onBlur={(e) => handleBlur('neighborhood', e.target.value)}
          />
          <FieldStatus
            saving={savingField === 'neighborhood'}
            saved={savedField === 'neighborhood'}
            backendError={errorField?.field === 'neighborhood' ? errorField.message : null}
          />
        </div>

        <div>
          <Label htmlFor="ed-cidade" className="cw-label">
            Cidade
          </Label>
          <Input
            variant="cowork"
            id="ed-cidade"
            value={city}
            placeholder=""
            disabled={disabled}
            onChange={(e) => {
              const prev = city;
              const v = e.target.value;
              setCity(v);
              scheduleAutosave('city', v, prev);
            }}
            onBlur={(e) => handleBlur('city', e.target.value)}
          />
          <FieldStatus
            saving={savingField === 'city'}
            saved={savedField === 'city'}
            backendError={errorField?.field === 'city' ? errorField.message : null}
          />
        </div>

        <div>
          <Label htmlFor="ed-uf" className="cw-label">
            UF
          </Label>
          <Select value={stateUf} onValueChange={handleUfChange} disabled={disabled}>
            <SelectTrigger id="ed-uf" variant="cowork" className="w-full">
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
            saving={savingField === 'state'}
            saved={savedField === 'state'}
            backendError={errorField?.field === 'state' ? errorField.message : null}
          />
        </div>

      </div>

      <div className="border-t border-border pt-4">
        <EnderecosEntregaList contactId={contact.id} disabled={disabled} />
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
      <p id={errorId} className="mt-1 inline-flex items-center gap-1 text-xs text-destructive" role="alert">
        <AlertCircle size={11} aria-hidden /> {error}
      </p>
    );
  }
  if (backendError) {
    return (
      <p className="mt-1 inline-flex items-center gap-1 text-xs text-destructive" role="alert">
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
      <p className="mt-1 inline-flex items-center gap-1 text-xs text-success-fg" aria-live="polite">
        <CheckCircle2 size={11} aria-hidden /> Salvo
      </p>
    );
  }
  return null;
}
