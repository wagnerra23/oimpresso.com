// ADR 0192 Onda 2 follow-up — Editor UI do split de comissão (mecânico/balcão).
// Plug-point: Sells/Edit.tsx. Persiste via PATCH dedicado /sells/{id}/commission-split
// (SellCommissionSplitController), fora do submit principal (que é monstro UPOS legacy).
// Shape canon JSON: { mecanico_id, mecanico_pct, balcao_id|null, balcao_pct } total=100.
// Validation real-time client + defesa-em-profundidade server (multi-tenant via Rule::exists).

import { useMemo, useState } from 'react';
import { router } from '@inertiajs/react';
import { Loader2, Save, Trash2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

export interface CommissionSplitValue {
  mecanico_id: number;
  mecanico_pct: number;
  balcao_id: number | null;
  balcao_pct: number;
}

export interface CommissionSplitEditorProps {
  value: CommissionSplitValue | null;
  users: Record<number, string>;
  saveUrl: string;
  disabled?: boolean;
}

const PRECISION = 0.01;
const approx = (a: number, b: number) => Math.abs(a - b) < PRECISION;
const clampPct = (raw: string) => Math.max(0, Math.min(100, parseFloat(raw) || 0));

export default function CommissionSplitEditor({ value, users, saveUrl, disabled = false }: CommissionSplitEditorProps) {
  const [mecanicoId, setMecanicoId] = useState<number | ''>(value?.mecanico_id ?? '');
  const [mecanicoPct, setMecanicoPct] = useState<number>(value?.mecanico_pct ?? 100);
  const [balcaoId, setBalcaoId] = useState<number | ''>(value?.balcao_id ?? '');
  const [balcaoPct, setBalcaoPct] = useState<number>(value?.balcao_pct ?? 0);
  const [saving, setSaving] = useState(false);
  const [serverError, setServerError] = useState<string | null>(null);
  const [savedFlash, setSavedFlash] = useState(false);

  const userOptions = useMemo(
    () =>
      Object.entries(users)
        .filter(([id]) => id !== '' && Number(id) > 0)
        .map(([id, name]) => ({ id: Number(id), name: String(name).trim() }))
        .sort((a, b) => a.name.localeCompare(b.name, 'pt-BR')),
    [users],
  );

  const isSoloMecanico = balcaoId === '';
  const total = mecanicoPct + (isSoloMecanico ? 0 : balcaoPct);
  const totalOk = isSoloMecanico ? approx(mecanicoPct, 100) : approx(total, 100);
  const sameUser = !isSoloMecanico && Number(mecanicoId) === Number(balcaoId);
  const missingMecanico = mecanicoId === '' || Number(mecanicoId) <= 0;

  const validationError = useMemo(() => {
    if (missingMecanico) return 'Selecione o mecânico.';
    if (sameUser) return 'Mecânico e balconista não podem ser a mesma pessoa.';
    if (!totalOk)
      return isSoloMecanico
        ? 'Modo 100% mecânico: mecânico precisa ter 100%.'
        : `Soma das porcentagens precisa ser 100% (atual: ${total.toFixed(2)}%).`;
    return null;
  }, [missingMecanico, sameUser, totalOk, total, isSoloMecanico]);

  const canSave = !disabled && !saving && !validationError;

  function persist(payload: CommissionSplitValue | null) {
    setServerError(null);
    setSaving(true);
    setSavedFlash(false);
    router.patch(
      saveUrl,
      { commission_split: payload } as Record<string, unknown>,
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
          setSavedFlash(true);
          setTimeout(() => setSavedFlash(false), 2500);
          if (payload === null) {
            setMecanicoId('');
            setMecanicoPct(100);
            setBalcaoId('');
            setBalcaoPct(0);
          }
        },
        onError: (errors) => {
          const firstKey = Object.keys(errors)[0];
          setServerError(firstKey ? String(errors[firstKey]) : 'Erro ao salvar comissão.');
        },
        onFinish: () => setSaving(false),
      },
    );
  }

  function handleSave() {
    if (!canSave) return;
    persist({
      mecanico_id: Number(mecanicoId),
      mecanico_pct: Math.round(mecanicoPct * 100) / 100,
      balcao_id: isSoloMecanico ? null : Number(balcaoId),
      balcao_pct: isSoloMecanico ? 0 : Math.round(balcaoPct * 100) / 100,
    });
  }

  function handleClear() {
    if (disabled || saving) return;
    if (!window.confirm('Remover split de comissão desta venda?')) return;
    persist(null);
  }

  function handleMecanicoPctChange(raw: string) {
    const pct = clampPct(raw);
    setMecanicoPct(pct);
    if (!isSoloMecanico) setBalcaoPct(Math.round((100 - pct) * 100) / 100);
  }

  function handleBalcaoIdChange(raw: string) {
    if (raw === '') {
      setBalcaoId('');
      setBalcaoPct(0);
      setMecanicoPct(100);
    } else {
      setBalcaoId(Number(raw));
      if (isSoloMecanico) {
        setMecanicoPct(50);
        setBalcaoPct(50);
      }
    }
  }

  const selectClass = 'mt-1 w-full border border-input rounded-md px-3 py-2 bg-background text-sm disabled:opacity-50';

  return (
    <section className="rounded-lg border border-border bg-card p-5 space-y-4" data-testid="commission-split-editor">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h2 className="font-semibold text-sm">Comissão (mecânico / balcão)</h2>
          <p className="text-xs text-muted-foreground mt-1">
            Split de comissão. Total deve somar 100%. Deixe balcão vazio para 100% do mecânico.
          </p>
        </div>
        {savedFlash && (
          <span className="text-xs text-emerald-600 font-medium" role="status">Salvo ✓</span>
        )}
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <Label htmlFor="commission-mecanico-id">Mecânico *</Label>
          <select
            id="commission-mecanico-id"
            value={mecanicoId}
            onChange={(e) => setMecanicoId(e.target.value === '' ? '' : Number(e.target.value))}
            disabled={disabled || saving}
            className={selectClass}
            aria-required="true"
          >
            <option value="">— Selecione —</option>
            {userOptions.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
          </select>
        </div>
        <div>
          <Label htmlFor="commission-mecanico-pct">Mecânico %</Label>
          <Input
            id="commission-mecanico-pct"
            type="number"
            min={0}
            max={100}
            step={0.01}
            value={mecanicoPct}
            onChange={(e) => handleMecanicoPctChange(e.target.value)}
            disabled={disabled || saving}
            className="mt-1"
          />
        </div>

        <div>
          <Label htmlFor="commission-balcao-id">Balconista (opcional)</Label>
          <select
            id="commission-balcao-id"
            value={balcaoId}
            onChange={(e) => handleBalcaoIdChange(e.target.value)}
            disabled={disabled || saving}
            className={selectClass}
          >
            <option value="">— Sem balcão (100% mecânico) —</option>
            {userOptions.map((u) => <option key={u.id} value={u.id}>{u.name}</option>)}
          </select>
        </div>
        {!isSoloMecanico && (
          <div>
            <Label htmlFor="commission-balcao-pct">Balcão %</Label>
            <Input
              id="commission-balcao-pct"
              type="number"
              min={0}
              max={100}
              step={0.01}
              value={balcaoPct}
              onChange={(e) => setBalcaoPct(clampPct(e.target.value))}
              disabled={disabled || saving}
              className="mt-1"
            />
          </div>
        )}
      </div>

      <div className="min-h-[20px] text-xs" aria-live="polite">
        {validationError ? (
          <p className="text-destructive" role="alert">{validationError}</p>
        ) : (
          <p className="text-muted-foreground">
            Total: <span className="font-mono font-medium">{(isSoloMecanico ? 100 : total).toFixed(2)}%</span>
          </p>
        )}
        {serverError && <p className="text-destructive mt-1" role="alert">{serverError}</p>}
      </div>

      <div className="flex items-center gap-2 pt-2 border-t border-border">
        <Button type="button" onClick={handleSave} disabled={!canSave} size="sm">
          {saving ? (
            <><Loader2 className="h-4 w-4 mr-2 animate-spin" />Salvando…</>
          ) : (
            <><Save className="h-4 w-4 mr-2" />Salvar comissão</>
          )}
        </Button>
        {value && (
          <Button type="button" variant="ghost" size="sm" onClick={handleClear} disabled={disabled || saving}>
            <Trash2 className="h-4 w-4 mr-2" />
            Limpar
          </Button>
        )}
      </div>
    </section>
  );
}
