// QueuesSheet.tsx — painel "Filas" da Caixa Unificada V4 (US-WA-301 · ADR 0267).
//
// Sheet in-place (sem context switch) com CRUD das filas persistidas em
// `whatsapp_queues`. Visual: chips hue como `QUEUES` do protótipo Cowork
// (inbox-page.jsx) — dot OKLCH + label + SLA + tags-gatilho.
//
// `dist` é persistido mas o roteamento automático (round-robin/sticky) é US
// futura — o select existe pra já capturar a intenção (TODO honesto ADR 0267).
// `members` fica FORA da UI nesta fase (sem fingir feature — anti M-AP-2).
//
// Mutações via QueuesController (atendimento.filas.*) — permission
// whatsapp.settings.manage (botão fica read-only sem ela).

import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Pencil, Plus, Trash2, X } from 'lucide-react';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/Components/ui/sheet';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Inline, Stack } from '@/Components/layout';
import { cn } from '@/Lib/utils';
import type { ConvTag, QueueAdminItem } from './helpers';

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  queues: QueueAdminItem[];
  availableTags: ConvTag[];
  canManage: boolean;
}

const DIST_LABELS: Record<string, string> = {
  manual: 'Manual',
  round_robin: 'Round-robin (em breve)',
  sticky: 'Sticky (em breve)',
};

interface FormState {
  id: number | null;
  label: string;
  hue: number;
  sla_minutes: string;
  dist: string;
  trigger_tags: string[];
}

const EMPTY_FORM: FormState = { id: null, label: '', hue: 220, sla_minutes: '', dist: 'manual', trigger_tags: [] };

export default function QueuesSheet({ open, onOpenChange, queues, availableTags, canManage }: Props) {
  const [form, setForm] = useState<FormState | null>(null);
  const [saving, setSaving] = useState(false);

  const reloadOnly = ['queuesAdmin', 'queues', 'stats', 'conversations'];

  function submit() {
    if (!form || saving || !form.label.trim()) return;
    setSaving(true);
    const payload = {
      label: form.label.trim(),
      hue: form.hue,
      sla_minutes: form.sla_minutes !== '' ? Number(form.sla_minutes) : null,
      dist: form.dist,
      trigger_tags: form.trigger_tags,
    };
    const opts = {
      preserveScroll: true,
      preserveState: true,
      only: reloadOnly,
      onSuccess: () => setForm(null),
      onFinish: () => setSaving(false),
    };
    if (form.id !== null) {
      router.put(route('atendimento.filas.update', form.id), payload, opts);
    } else {
      router.post(route('atendimento.filas.store'), payload, opts);
    }
  }

  function remove(q: QueueAdminItem) {
    if (q.is_default || saving) return;
    if (!confirm(`Remover a fila "${q.label}"? Conversas voltam pra heurística tag→fila.`)) return;
    setSaving(true);
    router.delete(route('atendimento.filas.destroy', q.id), {
      preserveScroll: true,
      preserveState: true,
      only: reloadOnly,
      onFinish: () => setSaving(false),
    });
  }

  function toggleTriggerTag(slug: string) {
    if (!form) return;
    setForm({
      ...form,
      trigger_tags: form.trigger_tags.includes(slug)
        ? form.trigger_tags.filter(s => s !== slug)
        : [...form.trigger_tags, slug],
    });
  }

  return (
    <Sheet open={open} onOpenChange={(o) => { if (!o) setForm(null); onOpenChange(o); }}>
      <SheetContent side="right" className="w-full sm:max-w-md overflow-y-auto">
        <SheetHeader>
          <SheetTitle>Filas de atendimento</SheetTitle>
          <SheetDescription>
            Conversas entram na fila pela heurística de tags-gatilho. SLA alimenta
            os indicadores da lista. Distribuição automática é etapa futura.
          </SheetDescription>
        </SheetHeader>

        <Stack gap={2} className="mt-4">
          {queues.length === 0 && (
            <p className="text-[12px] text-muted-foreground italic">Carregando filas…</p>
          )}
          {queues.map(q => (
            <div
              key={q.id}
              className="border rounded-md px-3 py-2"
              data-testid={`caixa-unif-queue-row-${q.slug}`}
            >
              <Inline gap={2} align="center" justify="between">
                <Inline gap={2} align="center" className="min-w-0">
                  <span
                    className="inline-block w-2.5 h-2.5 rounded-full flex-shrink-0"
                    style={{ background: `oklch(0.62 0.13 ${q.hue})` }}
                    aria-hidden
                  />
                  <span className="min-w-0">
                    <b className="block text-[12.5px] font-semibold truncate">
                      {q.label}
                      {q.is_default && (
                        <span className="ml-1.5 text-[9.5px] font-medium text-muted-foreground bg-muted border rounded-full px-1.5">
                          default
                        </span>
                      )}
                    </b>
                    <small className="block text-[10.5px] text-muted-foreground font-mono truncate">
                      {q.slug}
                      {q.sla ? ` · SLA ${q.sla}` : ' · sem SLA'}
                      {` · ${DIST_LABELS[q.dist] ?? q.dist}`}
                    </small>
                  </span>
                </Inline>
                {canManage && (
                  <Inline gap={1} align="center" className="flex-shrink-0">
                    <button
                      type="button"
                      onClick={() => setForm({
                        id: q.id,
                        label: q.label,
                        hue: q.hue,
                        sla_minutes: q.sla_minutes !== null ? String(q.sla_minutes) : '',
                        dist: q.dist,
                        trigger_tags: q.trigger_tags,
                      })}
                      className="p-1.5 rounded text-muted-foreground hover:text-foreground hover:bg-muted"
                      title={`Editar fila ${q.label}`}
                      data-testid={`caixa-unif-queue-edit-${q.slug}`}
                    >
                      <Pencil size={13} aria-hidden />
                    </button>
                    <button
                      type="button"
                      onClick={() => remove(q)}
                      disabled={q.is_default}
                      className="p-1.5 rounded text-muted-foreground hover:text-destructive hover:bg-destructive/5 disabled:opacity-35 disabled:cursor-not-allowed"
                      title={q.is_default ? 'Fila default não pode ser removida (fallback da heurística)' : `Remover fila ${q.label}`}
                      data-testid={`caixa-unif-queue-delete-${q.slug}`}
                    >
                      <Trash2 size={13} aria-hidden />
                    </button>
                  </Inline>
                )}
              </Inline>
              {q.trigger_tags.length > 0 && (
                <Inline gap={1} wrap className="mt-1.5">
                  {q.trigger_tags.map(slug => (
                    <span
                      key={slug}
                      className="inline-block px-2 py-px text-[10px] font-mono rounded-full text-foreground"
                      style={{ background: 'oklch(0.94 0.03 80)', border: '1px solid oklch(0.86 0.06 80)' }}
                    >
                      {slug}
                    </span>
                  ))}
                </Inline>
              )}
            </div>
          ))}

          {canManage && form === null && (
            <Button
              type="button"
              variant="outline"
              className="gap-1.5"
              onClick={() => setForm({ ...EMPTY_FORM })}
              data-testid="caixa-unif-queue-new"
            >
              <Plus size={14} aria-hidden /> Nova fila
            </Button>
          )}

          {canManage && form !== null && (
            <Stack gap={3} className="border rounded-md p-3 bg-muted/20" data-testid="caixa-unif-queue-form">
              <Inline gap={0} align="center" justify="between">
                <b className="text-[12.5px] font-semibold">
                  {form.id !== null ? 'Editar fila' : 'Nova fila'}
                </b>
                <button
                  type="button"
                  onClick={() => setForm(null)}
                  className="p-1 rounded text-muted-foreground hover:text-foreground hover:bg-muted"
                  title="Fechar formulário"
                >
                  <X size={13} aria-hidden />
                </button>
              </Inline>

              <Stack gap={1}>
                <Label htmlFor="queue-label" className="text-[11px]">Nome</Label>
                <Input
                  id="queue-label"
                  value={form.label}
                  onChange={e => setForm({ ...form, label: e.target.value })}
                  placeholder="Ex.: Suporte técnico"
                  data-testid="caixa-unif-queue-form-label"
                />
              </Stack>

              <Inline gap={3} align="end">
                <Stack gap={1} className="flex-1">
                  <Label htmlFor="queue-hue" className="text-[11px]">Cor (hue 0-360)</Label>
                  <Inline gap={2} align="center">
                    <Input
                      id="queue-hue"
                      type="number"
                      min={0}
                      max={360}
                      value={form.hue}
                      onChange={e => setForm({ ...form, hue: Math.max(0, Math.min(360, Number(e.target.value) || 0)) })}
                      data-testid="caixa-unif-queue-form-hue"
                    />
                    <span
                      className="inline-block w-5 h-5 rounded-full border flex-shrink-0"
                      style={{ background: `oklch(0.62 0.13 ${form.hue})` }}
                      aria-hidden
                    />
                  </Inline>
                </Stack>
                <Stack gap={1} className="flex-1">
                  <Label htmlFor="queue-sla" className="text-[11px]">SLA (minutos)</Label>
                  <Input
                    id="queue-sla"
                    type="number"
                    min={1}
                    placeholder="sem SLA"
                    value={form.sla_minutes}
                    onChange={e => setForm({ ...form, sla_minutes: e.target.value })}
                    data-testid="caixa-unif-queue-form-sla"
                  />
                </Stack>
              </Inline>

              <Stack gap={1}>
                <Label className="text-[11px]">Distribuição</Label>
                <Select value={form.dist} onValueChange={v => setForm({ ...form, dist: v })}>
                  <SelectTrigger data-testid="caixa-unif-queue-form-dist">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {Object.entries(DIST_LABELS).map(([value, label]) => (
                      <SelectItem key={value} value={value}>{label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <small className="text-[10px] text-muted-foreground">
                  Round-robin/sticky são capturados agora e ativam quando o roteamento automático existir (ADR 0267).
                </small>
              </Stack>

              <Stack gap={1}>
                <Label className="text-[11px]">Tags-gatilho</Label>
                <Inline gap={1} wrap>
                  {availableTags.length === 0 && (
                    <small className="text-[10.5px] text-muted-foreground italic">Nenhuma tag cadastrada</small>
                  )}
                  {availableTags.map(t => {
                    const active = form.trigger_tags.includes(t.slug);
                    return (
                      <button
                        key={t.id}
                        type="button"
                        onClick={() => toggleTriggerTag(t.slug)}
                        aria-pressed={active}
                        data-testid={`caixa-unif-queue-form-tag-${t.slug}`}
                        className={cn(
                          'inline-flex items-center h-6 px-2 rounded-full border text-[10.5px] font-medium transition-colors',
                          active
                            ? 'bg-primary/10 border-primary text-primary'
                            : 'bg-card border-border text-muted-foreground hover:text-foreground hover:border-muted-foreground',
                        )}
                      >
                        {t.label}
                      </button>
                    );
                  })}
                </Inline>
              </Stack>

              <Inline gap={2} justify="end">
                <Button type="button" variant="ghost" onClick={() => setForm(null)}>
                  Cancelar
                </Button>
                <Button
                  type="button"
                  onClick={submit}
                  disabled={saving || !form.label.trim()}
                  data-testid="caixa-unif-queue-form-save"
                >
                  {saving ? 'Salvando…' : form.id !== null ? 'Salvar fila' : 'Criar fila'}
                </Button>
              </Inline>
            </Stack>
          )}

          {!canManage && (
            <p className="text-[11px] text-muted-foreground">
              Você não tem a permissão de configuração (whatsapp.settings.manage) — visualização apenas.
            </p>
          )}
        </Stack>
      </SheetContent>
    </Sheet>
  );
}
