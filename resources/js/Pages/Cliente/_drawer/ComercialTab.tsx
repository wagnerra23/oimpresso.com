// Wave C-FE — ComercialTab.tsx
//
// Tab 4 do drawer 760px Cliente. Limite, prazo, tabela preço, pgto, obs comercial.
// Refs: ADR 0179 · Charter Index.charter.md v3 · HANDOFF_CLIENTES.md §2.4
// Cowork blueprint: prototipo-ui/prototipos/clientes/clientes-drawer.jsx::SectionComercial
//
// Contrato:
//   PATCH /cliente/{id}/comercial  body: { limite_credito, prazo_padrao_dias, customer_group_id, pgto_padrao, mensagem_venda, obs_comercial }
//
// Pegadinhas:
//  - limite_credito é INTEGER (centavos no banco — opcional, vazio = sem limite)
//  - prazo é INTEGER em dias
//  - Tabela de preço: customer_group_id REAL (FK customer_groups) — lista vem
//    via prop `priceGroups` (id+name do business, multi-tenant scope). Substitui
//    o antigo dropdown fake hardcoded `tabela_preco_padrao`.
//  - Pgto: pix/boleto/cartao/dinheiro/transferencia (5 valores)
//  - Mensagem para a venda: textarea livre, exibida como alerta ao vendedor no POS
//  - Obs comercial: textarea livre
//  - Autosave on blur (debounce 800ms) + optimistic UI + rollback 4xx/5xx

import { useCallback, useEffect, useRef, useState } from 'react';
import { Loader2, AlertCircle, CheckCircle2 } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { Label } from '@/Components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';

export interface PriceGroupOption {
  id: number;
  name: string;
}

export interface ContactInfo {
  id: number;
  limite_credito?: number | null;
  prazo_padrao_dias?: number | null;
  // Tabela de preço REAL (FK customer_groups). Fonte de verdade do drawer.
  customer_group_id?: number | null;
  pgto_padrao?: string | null;
  // Mensagem exibida como alerta ao vendedor no POS (migrado do Delphi).
  mensagem_venda?: string | null;
  obs_comercial?: string | null;
}

export interface ComercialTabProps {
  contact: ContactInfo;
  /**
   * Tabelas de preço REAIS do business (customer_groups id+name). Lista
   * server-side (ContactController::index, scope business_id). Substitui o
   * dropdown fake hardcoded. `undefined`/`[]` → dropdown vazio com aviso.
   */
  priceGroups?: PriceGroupOption[];
  onSaved?: (field: string, value: unknown) => void;
  disabled?: boolean;
}

const DEBOUNCE_MS = 800;

const PGTO_OPTIONS: Array<{ value: string; label: string }> = [
  { value: 'pix', label: 'PIX' },
  { value: 'boleto', label: 'Boleto' },
  { value: 'cartao', label: 'Cartão' },
  { value: 'dinheiro', label: 'Dinheiro' },
  { value: 'transferencia', label: 'Transferência' },
];

function getCsrfToken(): string {
  return (
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? ''
  );
}

function toNumberOrNull(v: string): number | null {
  if (v === '' || v == null) return null;
  const n = parseInt(v.replace(/\D/g, ''), 10);
  return Number.isFinite(n) ? n : null;
}

// Sentinel pro item "sem tabela" — shadcn Select proíbe SelectItem value="".
const NO_GROUP = '__none__';

export default function ComercialTab({
  contact,
  priceGroups = [],
  onSaved,
  disabled = false,
}: ComercialTabProps) {
  const [limite, setLimite] = useState<string>(
    contact.limite_credito != null ? String(contact.limite_credito) : ''
  );
  const [prazo, setPrazo] = useState<string>(
    contact.prazo_padrao_dias != null ? String(contact.prazo_padrao_dias) : ''
  );
  // customer_group_id real (FK customer_groups). Armazenado como string no
  // Select (NO_GROUP = sem tabela); convertido pra number|null no autosave.
  const [grupoPreco, setGrupoPreco] = useState<string>(
    contact.customer_group_id != null ? String(contact.customer_group_id) : NO_GROUP
  );
  const [pgto, setPgto] = useState<string>(contact.pgto_padrao ?? '');
  const [mensagemVenda, setMensagemVenda] = useState<string>(contact.mensagem_venda ?? '');
  const [obs, setObs] = useState<string>(contact.obs_comercial ?? '');

  const [savingField, setSavingField] = useState<string | null>(null);
  const [savedField, setSavedField] = useState<string | null>(null);
  const [errorField, setErrorField] = useState<{ field: string; message: string } | null>(null);

  const debounceTimersRef = useRef<Record<string, ReturnType<typeof setTimeout>>>({});
  const previousValuesRef = useRef<Record<string, unknown>>({});

  useEffect(() => {
    setLimite(contact.limite_credito != null ? String(contact.limite_credito) : '');
    setPrazo(contact.prazo_padrao_dias != null ? String(contact.prazo_padrao_dias) : '');
    setGrupoPreco(contact.customer_group_id != null ? String(contact.customer_group_id) : NO_GROUP);
    setPgto(contact.pgto_padrao ?? '');
    setMensagemVenda(contact.mensagem_venda ?? '');
    setObs(contact.obs_comercial ?? '');
    setErrorField(null);
    setSavedField(null);
  }, [contact.id]);

  const rollbackField = useCallback((field: string, prev: unknown) => {
    if (field === 'limite_credito') setLimite(prev != null ? String(prev) : '');
    else if (field === 'prazo_padrao_dias') setPrazo(prev != null ? String(prev) : '');
    else if (field === 'customer_group_id') setGrupoPreco(prev != null ? String(prev) : NO_GROUP);
    else if (field === 'pgto_padrao') setPgto((prev as string) ?? '');
    else if (field === 'mensagem_venda') setMensagemVenda((prev as string) ?? '');
    else if (field === 'obs_comercial') setObs((prev as string) ?? '');
  }, []);

  const performSave = useCallback(
    async (field: string, value: unknown, prev: unknown) => {
      if (disabled) return;
      setSavingField(field);
      setErrorField(null);
      try {
        const r = await fetch(`/cliente/${contact.id}/comercial`, {
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
           
          console.error(`[ComercialTab] autosave ${field} falhou`, { status: r.status });
          return;
        }
        setSavedField(field);
        setTimeout(() => setSavedField((c) => (c === field ? null : c)), 1800);
        onSaved?.(field, value);
      } catch (err) {
        rollbackField(field, prev);
        setErrorField({ field, message: 'Falha de rede. Tente de novo.' });
         
        console.error(`[ComercialTab] autosave ${field} network`, err);
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

  const handleBlurNumber = useCallback(
    (field: 'limite_credito' | 'prazo_padrao_dias', stringValue: string) => {
      const prev = previousValuesRef.current[field];
      const numValue = toNumberOrNull(stringValue);
      if (debounceTimersRef.current[field]) {
        clearTimeout(debounceTimersRef.current[field]);
        delete debounceTimersRef.current[field];
      }
      performSave(field, numValue, prev);
    },
    [performSave]
  );

  const handleBlurText = useCallback(
    (field: string, value: string) => {
      const prev = previousValuesRef.current[field];
      if (debounceTimersRef.current[field]) {
        clearTimeout(debounceTimersRef.current[field]);
        delete debounceTimersRef.current[field];
      }
      performSave(field, value, prev);
    },
    [performSave]
  );

  const handlePgtoChange = useCallback(
    (v: string) => {
      const prev = pgto;
      if (prev === v) return;
      setPgto(v);
      performSave('pgto_padrao', v, prev);
    },
    [pgto, performSave]
  );

  // Tabela de preço REAL: converte string do Select (NO_GROUP | id) pra
  // number|null antes de salvar `customer_group_id`. prev preserva o valor
  // STRING anterior pra rollback fiel.
  const handleGrupoPrecoChange = useCallback(
    (v: string) => {
      const prev = grupoPreco;
      if (prev === v) return;
      setGrupoPreco(v);
      const id = v === NO_GROUP ? null : Number.parseInt(v, 10);
      const prevId = prev === NO_GROUP ? null : Number.parseInt(prev, 10);
      performSave('customer_group_id', id, prevId);
    },
    [grupoPreco, performSave]
  );

  return (
    <div className="space-y-5">
      <div className="grid gap-4 md:grid-cols-2">
        <div>
          <Label htmlFor="cm-limite" className="cw-label">
            Limite de crédito <span className="text-muted-foreground font-normal">(R$, vazio = sem limite)</span>
          </Label>
          <Input
            variant="cowork"
            id="cm-limite"
            value={limite}
            placeholder="0"
            disabled={disabled}
            inputMode="numeric"
            onChange={(e) => {
              const prev = limite;
              const v = e.target.value.replace(/\D/g, '');
              setLimite(v);
              scheduleAutosave('limite_credito', toNumberOrNull(v), toNumberOrNull(prev));
            }}
            onBlur={(e) => handleBlurNumber('limite_credito', e.target.value)}
          />
          <FieldStatus
            saving={savingField === 'limite_credito'}
            saved={savedField === 'limite_credito'}
            backendError={errorField?.field === 'limite_credito' ? errorField.message : null}
          />
        </div>

        <div>
          <Label htmlFor="cm-prazo" className="cw-label">
            Prazo padrão <span className="text-muted-foreground font-normal">(dias)</span>
          </Label>
          <Input
            variant="cowork"
            id="cm-prazo"
            value={prazo}
            placeholder="30"
            disabled={disabled}
            inputMode="numeric"
            onChange={(e) => {
              const prev = prazo;
              const v = e.target.value.replace(/\D/g, '');
              setPrazo(v);
              scheduleAutosave('prazo_padrao_dias', toNumberOrNull(v), toNumberOrNull(prev));
            }}
            onBlur={(e) => handleBlurNumber('prazo_padrao_dias', e.target.value)}
          />
          <FieldStatus
            saving={savingField === 'prazo_padrao_dias'}
            saved={savedField === 'prazo_padrao_dias'}
            backendError={errorField?.field === 'prazo_padrao_dias' ? errorField.message : null}
          />
        </div>

        <div className="md:col-span-2">
          <Label htmlFor="cm-tabela" className="cw-label">
            Tabela de preço
          </Label>
          <Select
            value={grupoPreco}
            onValueChange={handleGrupoPrecoChange}
            disabled={disabled || priceGroups.length === 0}
          >
            <SelectTrigger id="cm-tabela" variant="cowork" className="w-full">
              <SelectValue placeholder="Selecionar tabela" />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value={NO_GROUP}>Sem tabela (preço padrão)</SelectItem>
              {priceGroups.map((g) => (
                <SelectItem key={g.id} value={String(g.id)}>
                  {g.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {priceGroups.length === 0 && (
            <p className="mt-1 text-xs text-muted-foreground">
              Nenhuma tabela de preço cadastrada neste negócio.
            </p>
          )}
          <FieldStatus
            saving={savingField === 'customer_group_id'}
            saved={savedField === 'customer_group_id'}
            backendError={errorField?.field === 'customer_group_id' ? errorField.message : null}
          />
        </div>

        <div className="md:col-span-2">
          <Label htmlFor="cm-pgto" className="cw-label">
            Forma de pagamento preferida
          </Label>
          <Select
            value={pgto}
            onValueChange={handlePgtoChange}
            disabled={disabled}
          >
            <SelectTrigger id="cm-pgto" variant="cowork" className="w-full">
              <SelectValue placeholder="Selecionar forma de pagamento" />
            </SelectTrigger>
            <SelectContent>
              {PGTO_OPTIONS.map((o) => (
                <SelectItem key={o.value} value={o.value}>
                  {o.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <FieldStatus
            saving={savingField === 'pgto_padrao'}
            saved={savedField === 'pgto_padrao'}
            backendError={errorField?.field === 'pgto_padrao' ? errorField.message : null}
          />
        </div>

        <div className="md:col-span-2">
          <Label htmlFor="cm-mensagem-venda" className="cw-label">
            Mensagem para a venda{' '}
            <span className="text-muted-foreground font-normal">(alerta ao vendedor no PDV)</span>
          </Label>
          <Textarea
            id="cm-mensagem-venda"
            value={mensagemVenda}
            placeholder="Ex.: cliente paga só com boleto · conferir limite antes de faturar…"
            disabled={disabled}
            rows={3}
            onChange={(e) => {
              const prev = mensagemVenda;
              const v = e.target.value;
              setMensagemVenda(v);
              scheduleAutosave('mensagem_venda', v, prev);
            }}
            onBlur={(e) => handleBlurText('mensagem_venda', e.target.value)}
          />
          <FieldStatus
            saving={savingField === 'mensagem_venda'}
            saved={savedField === 'mensagem_venda'}
            backendError={errorField?.field === 'mensagem_venda' ? errorField.message : null}
          />
        </div>

        <div className="md:col-span-2">
          <Label htmlFor="cm-obs" className="cw-label">
            Observações comerciais <span className="text-muted-foreground font-normal">(opcional)</span>
          </Label>
          <Textarea
            id="cm-obs"
            value={obs}
            placeholder="Particularidades de negociação, condições especiais…"
            disabled={disabled}
            rows={3}
            onChange={(e) => {
              const prev = obs;
              const v = e.target.value;
              setObs(v);
              scheduleAutosave('obs_comercial', v, prev);
            }}
            onBlur={(e) => handleBlurText('obs_comercial', e.target.value)}
          />
          <FieldStatus
            saving={savingField === 'obs_comercial'}
            saved={savedField === 'obs_comercial'}
            backendError={errorField?.field === 'obs_comercial' ? errorField.message : null}
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
