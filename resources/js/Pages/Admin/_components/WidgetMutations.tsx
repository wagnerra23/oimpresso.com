// Widget — Ações mutacionais Admin Center (Sprint 2).
// 3 botões com double-confirmation pattern (reason >=5 chars + confirm).
//
// All actions audit-logged em mcp_admin_audit_log via AdminAuditLogger.

import { useState, FormEvent } from 'react';
import { router } from '@inertiajs/react';
import { Icon } from '@/Components/Icon';

interface ActionResult {
  ok: boolean;
  message?: string;
  note?: string;
  errors?: string[];
  applied_count?: number;
  token_plaintext?: string;
  exit_code?: number;
  overall_status?: string;
  check_count?: number;
}

type ActionKey = 'curador_apply' | 'regen_token' | 'run_health';

interface ActionConfig {
  key: ActionKey;
  label: string;
  icon: string;
  description: string;
  endpoint: string;
  extraFields?: Array<{
    name: string;
    label: string;
    type: 'text' | 'number';
    placeholder?: string;
  }>;
  buttonClass: string;
}

const ACTIONS: ActionConfig[] = [
  {
    key: 'curador_apply',
    label: 'Aplicar batch Curador',
    icon: 'play-circle',
    description: 'Marca arquivos do batch aprovado como aplicados (move/copia via pipeline US-ARQ-008..014).',
    endpoint: '/admin/mutations/curador/apply',
    extraFields: [{
      name: 'batch_id',
      label: 'Batch ID',
      type: 'text',
      placeholder: '2026-05-10-sensitive-001',
    }],
    buttonClass: 'bg-primary hover:bg-primary/90',
  },
  {
    key: 'regen_token',
    label: 'Regenerar token MCP',
    icon: 'key',
    description: 'Rotaciona token mcp_tokens (default: token mais recente Wagner). Plaintext exibido 1× — salve antes de fechar.',
    endpoint: '/admin/mutations/mcp-token/regenerate',
    extraFields: [{
      name: 'token_id',
      label: 'Token ID (opcional)',
      type: 'number',
      placeholder: 'auto = mais recente Wagner',
    }],
    buttonClass: 'bg-warning hover:bg-warning/90',
  },
  {
    key: 'run_health',
    label: 'Rodar health-check agora',
    icon: 'refresh-cw',
    description: 'Executa jana:health-check síncrono (cap 30s) + atualiza snapshot. W2/W4/W10 atualizam.',
    endpoint: '/admin/mutations/health-check/run-now',
    extraFields: [],
    buttonClass: 'bg-success hover:bg-success/90',
  },
];

export default function WidgetMutations() {
  const [open, setOpen] = useState<ActionKey | null>(null);
  const [reason, setReason] = useState('');
  const [extras, setExtras] = useState<Record<string, string>>({});
  const [busy, setBusy] = useState(false);
  const [result, setResult] = useState<ActionResult | null>(null);

  const action = ACTIONS.find((a) => a.key === open);

  function reset() {
    setOpen(null);
    setReason('');
    setExtras({});
    setResult(null);
    setBusy(false);
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    if (!action) return;
    if (reason.length < 5) {
      setResult({ ok: false, errors: ['Reason precisa >=5 chars'] });
      return;
    }
    setBusy(true);
    setResult(null);

    const body = new FormData();
    body.append('reason', reason);
    body.append('confirm', 'true');
    for (const [k, v] of Object.entries(extras)) {
      if (v) body.append(k, v);
    }

    try {
      const csrfMeta = document.querySelector('meta[name="csrf-token"]');
      const csrf = csrfMeta?.getAttribute('content') ?? '';
      const res = await fetch(action.endpoint, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
        },
        body,
      });
      const data: ActionResult = await res.json();
      setResult(data);
    } catch (err) {
      setResult({ ok: false, message: String(err) });
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="text-sm space-y-2">
      <div className="grid grid-cols-1 md:grid-cols-3 gap-2">
        {ACTIONS.map((a) => (
          <button
            key={a.key}
            type="button"
            onClick={() => {
              setOpen(a.key);
              setResult(null);
            }}
            className={`text-white text-sm px-3 py-2 rounded flex items-center gap-2 ${a.buttonClass}`}
          >
            <Icon name={a.icon} />
            <span>{a.label}</span>
          </button>
        ))}
      </div>

      {open && action && (
        <div className="border rounded p-3 bg-slate-50 mt-3">
          <div className="flex items-start justify-between mb-2">
            <div>
              <div className="font-semibold flex items-center gap-2">
                <Icon name={action.icon} /> {action.label}
              </div>
              <p className="text-xs text-gray-600 mt-1">{action.description}</p>
            </div>
            <button
              type="button"
              className="text-gray-400 hover:text-gray-700"
              onClick={reset}
              disabled={busy}
            >
              ✕
            </button>
          </div>

          <form onSubmit={handleSubmit} className="space-y-2">
            {(action.extraFields ?? []).map((f) => (
              <label key={f.name} className="block">
                <span className="text-xs text-gray-600">{f.label}</span>
                <input
                  type={f.type}
                  placeholder={f.placeholder}
                  value={extras[f.name] ?? ''}
                  onChange={(e) => setExtras({ ...extras, [f.name]: e.target.value })}
                  className="w-full border rounded px-2 py-1 text-sm mt-0.5"
                  disabled={busy}
                />
              </label>
            ))}

            <label className="block">
              <span className="text-xs text-gray-600">
                Razão (audit log) <span className="text-destructive">*obrigatório, ≥5 chars</span>
              </span>
              <textarea
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder="Por que esta ação agora?"
                rows={2}
                className="w-full border rounded px-2 py-1 text-sm mt-0.5"
                disabled={busy}
                required
                minLength={5}
              />
            </label>

            <div className="flex gap-2">
              <button
                type="submit"
                disabled={busy || reason.length < 5}
                className={`text-white text-sm px-3 py-1.5 rounded disabled:opacity-50 ${action.buttonClass}`}
              >
                {busy ? '⏳ Executando…' : '✓ Confirmar'}
              </button>
              <button
                type="button"
                onClick={reset}
                disabled={busy}
                className="text-sm px-3 py-1.5 rounded border border-gray-300 hover:bg-gray-100"
              >
                Cancelar
              </button>
            </div>
          </form>

          {result && (
            <div
              className={`mt-3 p-2 rounded text-xs ${
                result.ok ? 'bg-success-soft text-success-fg' : 'bg-destructive-soft text-destructive-fg'
              }`}
            >
              <div className="font-semibold mb-1">{result.ok ? '✓ Sucesso' : '✗ Falha'}</div>
              {result.message && <div>{result.message}</div>}
              {result.note && <div className="italic">{result.note}</div>}
              {result.errors && (
                <ul className="list-disc list-inside">
                  {result.errors.map((e, i) => <li key={i}>{e}</li>)}
                </ul>
              )}
              {result.applied_count != null && <div>Arquivos aplicados: {result.applied_count}</div>}
              {result.token_plaintext && (
                <div className="mt-1 p-2 bg-amber-100 text-amber-900 rounded font-mono break-all">
                  ⚠️ Salve agora: <code>{result.token_plaintext}</code>
                </div>
              )}
              {result.exit_code != null && (
                <div>
                  exit: {result.exit_code} · status: {result.overall_status} · checks: {result.check_count}
                </div>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
