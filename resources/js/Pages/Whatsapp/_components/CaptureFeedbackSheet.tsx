/**
 * CaptureFeedbackSheet — captura de feedback canon a partir de mensagem WhatsApp.
 *
 * Wagner 2026-05-27. Refs ADR UI-0016 (design contextualizado por persona),
 * ADR 0093 (multi-tenant Tier 0), feedback-management RUNBOOK.
 *
 * Fluxo:
 *   1. Wagner clica "📋 Feedback" em bubble de mensagem (ConversationThread)
 *   2. Sheet 760px lateral abre pré-preenchido (literal já lá, persona inferida)
 *   3. Wagner edita 1-2 campos (severity NN/g, JTBD, módulo afetado) e salva
 *   4. POST /atendimento/feedback/capture
 *   5. Toast confirmação + Sheet fecha
 *   6. Severity ≥ 3 → MCP task pendente (criada async)
 */

import { useState, useCallback, useEffect } from 'react';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/Components/ui/sheet';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Button } from '@/Components/ui/button';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/Components/ui/select';
import { Checkbox } from '@/Components/ui/checkbox';
import { ClipboardCheck, AlertCircle, CheckCircle2, Loader2, Ban, AlertTriangle, ZapIcon, FileText } from 'lucide-react';
import { cn } from '@/Lib/utils';

export interface CaptureFeedbackInput {
  literal: string;
  source_message_id?: number | null;
  conversation_id?: number | null;
  contact_id?: number | null;
  persona_slug?: string | null;
  cliente_slug?: string | null;
  contact_phone?: string | null;
  contact_name?: string | null;
  /** true=customer|both, false=lead|supplier, null=desconhecido (backend valida) */
  contact_is_customer?: boolean | null;
}

export interface CaptureFeedbackSheetProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  input: CaptureFeedbackInput;
  onSaved?: (feedbackId: number, mcpTaskPending: boolean, devTaskRequested?: boolean) => void;
}

const SEVERITY_OPTIONS = [
  { value: '0', label: '0 — Não é problema (wish-list)' },
  { value: '1', label: '1 — Cosmético (chato mas convive)' },
  { value: '2', label: '2 — Minor (problema real, tem workaround)' },
  { value: '3', label: '3 — Major (impede tarefa frequente)' },
  { value: '4', label: '4 — Catastrófico (bloqueia uso)' },
];

const MODULO_OPTIONS = [
  { value: 'sells', label: 'Sells (vendas / POS)' },
  { value: 'cliente', label: 'Cliente / Contacts' },
  { value: 'oficinaauto', label: 'OficinaAuto (OS + frota)' },
  { value: 'financeiro', label: 'Financeiro' },
  { value: 'nfe-brasil', label: 'NF-e Brasil' },
  { value: 'nfse', label: 'NFS-e' },
  { value: 'compras', label: 'Compras' },
  { value: 'produto', label: 'Produto' },
  { value: 'whatsapp', label: 'WhatsApp / Atendimento' },
  { value: 'ponto', label: 'Ponto / RH' },
  { value: 'jana', label: 'Jana / Copiloto' },
  { value: 'outros', label: 'Outros / não tenho certeza' },
];

const MOTIVACAO_OPTIONS = [
  { value: 'funcional', label: 'Funcional — resolver tarefa' },
  { value: 'emocional', label: 'Emocional — sentir no controle / profissional' },
  { value: 'social', label: 'Social — preservar imagem / relação' },
];

function getCsrfToken(): string {
  return (
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? ''
  );
}

export default function CaptureFeedbackSheet({ open, onOpenChange, input, onSaved }: CaptureFeedbackSheetProps) {
  const [literal, setLiteral] = useState<string>(input.literal ?? '');
  const [contexto, setContexto] = useState<string>('');
  const [personaSlug, setPersonaSlug] = useState<string>(input.persona_slug ?? '');
  const [clienteSlug, setClienteSlug] = useState<string>(input.cliente_slug ?? '');
  const [severity, setSeverity] = useState<string>('2');
  const [modulo, setModulo] = useState<string>('');
  const [job, setJob] = useState<string>('');
  const [motivacao, setMotivacao] = useState<string>('funcional');
  const [workaround, setWorkaround] = useState<string>('');
  const [workaroundCusto, setWorkaroundCusto] = useState<string>('');

  const [createDevTask, setCreateDevTask] = useState<boolean>(false);

  const [saving, setSaving] = useState<boolean>(false);
  const [savedOk, setSavedOk] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);
  const [devTaskMsg, setDevTaskMsg] = useState<string | null>(null);

  // Reset state quando sheet abre com novo input
  useEffect(() => {
    if (open) {
      setLiteral(input.literal ?? '');
      setContexto('');
      setPersonaSlug(input.persona_slug ?? '');
      setClienteSlug(input.cliente_slug ?? '');
      setSeverity('2');
      setModulo('');
      setJob('');
      setMotivacao('funcional');
      setWorkaround('');
      setWorkaroundCusto('');
      setSaving(false);
      setSavedOk(false);
      setError(null);
      setDevTaskMsg(null);
      // Pré-marca dev_task se severity ≥ 3 + cliente é pagante (sinal forte)
      setCreateDevTask(false);
    }
  }, [open, input]);

  const severityValueNum = parseInt(severity, 10);
  const isPayingCustomer = input.contact_is_customer === true;
  const isExplicitlyNotCustomer = input.contact_is_customer === false;
  const contactLinked = !!input.contact_id;

  // ADR 0105 guard rails (visuais — backend é a verdade)
  // - cliente confirmado pagante + sev ≥ 3 → pré-marca + recomenda
  // - sev ≥ 2 + (pagante OU desconhecido) → toggle disponível neutro
  // - sev 0-1 → toggle disponível mas aviso "talvez só feedback"
  // - cliente explicitamente NÃO pagante → toggle bloqueado
  const devTaskBlocked = isExplicitlyNotCustomer;
  const devTaskRecommended = isPayingCustomer && severityValueNum >= 3;
  const devTaskWarnLowSev = severityValueNum < 2;
  const devTaskWarnNoContact = !contactLinked && !devTaskBlocked;

  useEffect(() => {
    if (!open) return;
    // Auto-marca quando entra severity ≥ 3 + cliente pagante confirmado
    if (devTaskRecommended && !createDevTask) {
      setCreateDevTask(true);
    }
    // Auto-desmarca se bloqueado
    if (devTaskBlocked && createDevTask) {
      setCreateDevTask(false);
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [severity, input.contact_is_customer, open]);

  const handleSave = useCallback(async () => {
    if (!literal.trim()) {
      setError('Texto literal da mensagem é obrigatório.');
      return;
    }
    setSaving(true);
    setError(null);

    try {
      const r = await fetch('/atendimento/feedback/capture', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          literal: literal.trim(),
          contexto: contexto.trim() || null,
          source_message_id: input.source_message_id ?? null,
          conversation_id: input.conversation_id ?? null,
          contact_id: input.contact_id ?? null,
          persona_slug: personaSlug.trim() || null,
          cliente_slug: clienteSlug.trim() || null,
          canal: 'whatsapp',
          modulo_afetado: modulo || null,
          job: job.trim() || null,
          motivacao_tipo: motivacao || null,
          workaround_o_que_faz: workaround.trim() || null,
          workaround_custo: workaroundCusto.trim() || null,
          severity_nng: parseInt(severity, 10),
          primeira_vez: true,
          create_dev_task: createDevTask && !devTaskBlocked,
        }),
      });

      if (!r.ok) {
        const j = await r.json().catch(() => ({}));
        const errMsg =
          j?.errors?.literal?.[0] ??
          j?.errors?.severity_nng?.[0] ??
          j?.message ??
          `Erro ${r.status} ao salvar.`;
        setError(errMsg);
        setSaving(false);
        return;
      }

      const j = await r.json();
      setSavedOk(true);
      setSaving(false);
      const devTask = j?.dev_task ?? null;
      if (devTask?.message) {
        setDevTaskMsg(devTask.message);
      }

      onSaved?.(j.feedback.id, j.feedback.mcp_task_pending, !!j.feedback.dev_task_requested);

      // Sheet fica mais tempo aberto quando há mensagem dev_task pra ler
      setTimeout(() => {
        onOpenChange(false);
      }, devTask?.message ? 2500 : 1500);
    } catch (err) {
       
      console.error('[CaptureFeedbackSheet] save error', err);
      setError('Falha de rede. Tente novamente.');
      setSaving(false);
    }
  }, [
    literal, contexto, personaSlug, clienteSlug, severity, modulo, job, motivacao,
    workaround, workaroundCusto, createDevTask, devTaskBlocked,
    input, onSaved, onOpenChange,
  ]);

  const isHighSeverity = severityValueNum >= 3;

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="cw-sheet w-[760px] sm:max-w-[760px] p-0 flex flex-col">
        <SheetHeader className="cw-sheet-header px-6 py-4 space-y-2">
          <SheetTitle className="text-lg font-semibold inline-flex items-center gap-2">
            <ClipboardCheck size={20} className="text-[var(--cw-accent)]" />
            Capturar feedback de cliente
          </SheetTitle>
          <p className="text-xs text-muted-foreground">
            Estrutura canônica Voice of Customer · NN/g severity 0-4 · linka persona +
            charter + MCP task (sev≥3)
          </p>
        </SheetHeader>

        <div className="flex-1 overflow-y-auto px-6 py-4 space-y-3">
          {/* Contato origem */}
          {(input.contact_name || input.contact_phone) && (
            <div className="rounded-md bg-muted/40 border border-border px-3 py-2 text-xs">
              <div className="font-medium">{input.contact_name ?? 'Contato'}</div>
              {input.contact_phone && (
                <div className="text-muted-foreground font-mono">{input.contact_phone}</div>
              )}
              {personaSlug && (
                <div className="text-[var(--cw-accent)] font-semibold mt-1">
                  🎯 Persona inferida: {personaSlug}
                </div>
              )}
            </div>
          )}

          {/* Literal */}
          <div>
            <Label className="mb-1 block">
              Texto literal da reclamação <span className="text-rose-600">*</span>
            </Label>
            <Textarea
              value={literal}
              onChange={(e) => setLiteral(e.target.value)}
              placeholder='"tô tentando emitir nota mas dá erro..."'
              rows={3}
              disabled={saving}
            />
          </div>

          {/* Persona + Cliente */}
          <div className="grid grid-cols-2 gap-2.5">
            <div>
              <Label className="mb-1 block">Persona slug</Label>
              <Input
                value={personaSlug}
                onChange={(e) => setPersonaSlug(e.target.value)}
                placeholder="kamila-martinho"
                disabled={saving}
              />
            </div>
            <div>
              <Label className="mb-1 block">Cliente slug</Label>
              <Input
                value={clienteSlug}
                onChange={(e) => setClienteSlug(e.target.value)}
                placeholder="martinho-cacambas"
                disabled={saving}
              />
            </div>
          </div>

          {/* Severity (destaque) */}
          <div>
            <Label className="mb-1 block">
              Severity NN/g <span className="text-rose-600">*</span>
            </Label>
            <Select value={severity} onValueChange={setSeverity} disabled={saving}>
              <SelectTrigger className="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {SEVERITY_OPTIONS.map((o) => (
                  <SelectItem key={o.value} value={o.value}>
                    {o.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {isHighSeverity && (
              <p className="mt-1 text-[11px] text-warning-fg inline-flex items-center gap-1">
                ⚡ Severity ≥ 3 — vai criar MCP task automaticamente.
              </p>
            )}
          </div>

          {/* Módulo afetado */}
          <div>
            <Label className="mb-1 block">Módulo afetado</Label>
            <Select value={modulo} onValueChange={setModulo} disabled={saving}>
              <SelectTrigger className="w-full">
                <SelectValue placeholder="Selecionar módulo" />
              </SelectTrigger>
              <SelectContent>
                {MODULO_OPTIONS.map((o) => (
                  <SelectItem key={o.value} value={o.value}>
                    {o.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {/* JTBD */}
          <div>
            <Label className="mb-1 block">Job-to-be-done (o que cliente queria atingir)</Label>
            <Input
              value={job}
              onChange={(e) => setJob(e.target.value)}
              placeholder="emitir nota sem erro pro contador"
              disabled={saving}
            />
          </div>

          <div>
            <Label className="mb-1 block">Motivação tipo</Label>
            <Select value={motivacao} onValueChange={setMotivacao} disabled={saving}>
              <SelectTrigger className="w-full">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {MOTIVACAO_OPTIONS.map((o) => (
                  <SelectItem key={o.value} value={o.value}>
                    {o.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {/* Workaround */}
          <div className="grid grid-cols-2 gap-2.5">
            <div>
              <Label className="mb-1 block">Workaround atual (o que faz hoje)</Label>
              <Input
                value={workaround}
                onChange={(e) => setWorkaround(e.target.value)}
                placeholder="liga pro Wagner toda vez"
                disabled={saving}
              />
            </div>
            <div>
              <Label className="mb-1 block">Custo do workaround</Label>
              <Input
                value={workaroundCusto}
                onChange={(e) => setWorkaroundCusto(e.target.value)}
                placeholder="10min/dia + frustração"
                disabled={saving}
              />
            </div>
          </div>

          {/* Contexto */}
          <div>
            <Label className="mb-1 block">Contexto adicional (opcional)</Label>
            <Textarea
              value={contexto}
              onChange={(e) => setContexto(e.target.value)}
              placeholder="Quando aconteceu, em qual fluxo, outras notas..."
              rows={2}
              disabled={saving}
            />
          </div>

          {/* Errors */}
          {error && (
            <div className="rounded-md border border-destructive/20 bg-destructive-soft px-3 py-2 text-xs text-destructive-fg inline-flex items-center gap-2">
              <AlertCircle size={14} />
              {error}
            </div>
          )}

          {/* Success */}
          {savedOk && (
            <div className="rounded-md border border-success/20 bg-success-soft px-3 py-2 text-xs text-success-fg inline-flex items-center gap-2">
              <CheckCircle2 size={14} />
              Feedback salvo no canon. Persona + charter atualizados.
            </div>
          )}

          {/* Dev task result message */}
          {savedOk && devTaskMsg && (
            <div className="rounded-md border border-sky-300 bg-sky-50 px-3 py-2 text-xs text-sky-800 inline-flex items-center gap-2">
              <FileText size={14} />
              {devTaskMsg}
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="border-t border-border px-6 py-3 space-y-2">
          {/* Toggle: Virar chamado de desenvolvimento */}
          <div className="flex items-start gap-2">
            <Checkbox
              id="create-dev-task-toggle"
              checked={createDevTask}
              onCheckedChange={(v) => setCreateDevTask(v === true)}
              disabled={saving || savedOk || devTaskBlocked}
              data-testid="create-dev-task-toggle"
              className="mt-0.5 h-3.5 w-3.5 cursor-pointer disabled:cursor-not-allowed"
            />
            <label
              htmlFor="create-dev-task-toggle"
              className={cn(
                'text-xs leading-tight cursor-pointer select-none',
                devTaskBlocked && 'text-muted-foreground cursor-not-allowed',
              )}
            >
              <div className="font-medium inline-flex items-center gap-1.5">
                Virar chamado de desenvolvimento
                {devTaskRecommended && (
                  <span className="text-[10px] font-semibold text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded">
                    RECOMENDADO
                  </span>
                )}
              </div>
              <div className="text-[11px] text-muted-foreground mt-0.5 inline-flex items-start gap-1">
                {devTaskBlocked && (
                  <>
                    <Ban size={11} className="mt-0.5 flex-shrink-0" />
                    <span>Contato não é cliente pagante (ADR 0105 — backlog só recebe quem paga ou reporta).</span>
                  </>
                )}
                {!devTaskBlocked && devTaskWarnNoContact && (
                  <>
                    <AlertTriangle size={11} className="mt-0.5 flex-shrink-0" />
                    <span>Número sem cliente vinculado — backend valida ao salvar.</span>
                  </>
                )}
                {!devTaskBlocked && !devTaskWarnNoContact && devTaskWarnLowSev && (
                  <>
                    <AlertTriangle size={11} className="mt-0.5 flex-shrink-0" />
                    <span>Severity baixa — considere apenas registrar como feedback.</span>
                  </>
                )}
                {!devTaskBlocked && !devTaskWarnNoContact && !devTaskWarnLowSev && !devTaskRecommended && (
                  <span>Marca pra Wagner criar task MCP em até 24h via triagem.</span>
                )}
                {!devTaskBlocked && !devTaskWarnNoContact && devTaskRecommended && (
                  <>
                    <ZapIcon size={11} className="mt-0.5 flex-shrink-0" />
                    <span>Sev ≥ 3 + cliente pagante. Pré-marcado em verde — Wagner triagem cria task em até 24h.</span>
                  </>
                )}
              </div>
            </label>
          </div>

          <div className="flex items-center justify-end gap-2">
          <Button variant="cowork-ghost" onClick={() => onOpenChange(false)} disabled={saving}>
            Cancelar
          </Button>
          <Button variant="cowork-primary" onClick={handleSave} disabled={saving || savedOk}>
            {saving ? (
              <>
                <Loader2 size={14} className="mr-1.5 animate-spin" />
                Salvando…
              </>
            ) : savedOk ? (
              <>
                <CheckCircle2 size={14} className="mr-1.5" />
                Salvo
              </>
            ) : (
              <>
                <ClipboardCheck size={14} className="mr-1.5" />
                {createDevTask && !devTaskBlocked ? 'Salvar + abrir chamado' : 'Salvar feedback'}
              </>
            )}
          </Button>
          </div>
        </div>
      </SheetContent>
    </Sheet>
  );
}
