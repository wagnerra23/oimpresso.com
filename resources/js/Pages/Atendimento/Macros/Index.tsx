// @memcofre
//   tela: /atendimento/macros
//   stories: US-WA-048 (Macros — quick replies + automation actions, Chatwoot pattern)
//   gap: P1 #6 + #12 em memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12.md
//   spec: memory/requisitos/Whatsapp/SPEC.md
//   permissao: whatsapp.settings.manage
//
// Macro = template estendido com ações múltiplas (send + tag + status + assign).
// UI: lista de macros (table) + modal nova/editar com accordion "Ações avançadas".
// Composer dropdown (em ConversationThread.tsx) consome /atendimento/macros/list JSON.

import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Plus, Pencil, Trash2, Zap, X, Beaker } from 'lucide-react';

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';

type ActionKind = 'add_tag' | 'set_status' | 'assign_user';

interface MacroAction {
  type: ActionKind;
  tag_id?: number | null;
  status?: string | null;
  user_id?: number | string | null;
}

interface Macro {
  id: number;
  label: string;
  shortcut: string | null;
  body: string;
  actions_json: MacroAction[];
  used_count: number;
  // US-WA-049: opcional — contador de variantes A/B cadastradas
  // (back-compat com Controller que ainda não popula). Default 0.
  variants_count?: number;
  created_at: string | null;
  updated_at: string | null;
}

interface TagOption {
  id: number;
  slug: string;
  label: string;
  color: string;
}

interface StatusOption {
  value: string;
  label: string;
}

interface Props {
  macros: Macro[];
  availableTags: TagOption[];
  availableStatuses: StatusOption[];
}

interface FormState {
  id: number | null;
  label: string;
  shortcut: string;
  body: string;
  actions: MacroAction[];
  showAdvanced: boolean;
}

const EMPTY_FORM: FormState = {
  id: null,
  label: '',
  shortcut: '',
  body: '',
  actions: [],
  showAdvanced: false,
};

export default function MacrosIndex({ macros, availableTags, availableStatuses }: Props) {
  const [open, setOpen] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);

  function openCreate() {
    setForm(EMPTY_FORM);
    setOpen(true);
  }

  function openEdit(m: Macro) {
    setForm({
      id: m.id,
      label: m.label,
      shortcut: m.shortcut ?? '',
      body: m.body,
      actions: m.actions_json ?? [],
      showAdvanced: (m.actions_json ?? []).length > 0,
    });
    setOpen(true);
  }

  function addAction(kind: ActionKind) {
    const blank: MacroAction = { type: kind };
    setForm({ ...form, actions: [...form.actions, blank], showAdvanced: true });
  }

  function updateAction(idx: number, patch: Partial<MacroAction>) {
    const next = form.actions.map((a, i) => (i === idx ? { ...a, ...patch } : a));
    setForm({ ...form, actions: next });
  }

  function removeAction(idx: number) {
    setForm({ ...form, actions: form.actions.filter((_, i) => i !== idx) });
  }

  function submit() {
    if (submitting || !form.label.trim() || !form.body.trim()) return;
    setSubmitting(true);

    const payload = {
      label: form.label,
      shortcut: form.shortcut || null,
      body: form.body,
      actions_json: form.actions,
    };

    const opts = {
      preserveScroll: true,
      onSuccess: () => {
        setOpen(false);
        setForm(EMPTY_FORM);
      },
      onFinish: () => setSubmitting(false),
    };

    if (form.id) {
      router.put(route('atendimento.macros.update', { id: form.id }), payload, opts);
    } else {
      router.post(route('atendimento.macros.store'), payload, opts);
    }
  }

  function destroy(m: Macro) {
    if (!confirm(`Remover macro "${m.label}"?`)) return;
    router.delete(route('atendimento.macros.destroy', { id: m.id }), { preserveScroll: true });
  }

  return (
    <div className="space-y-4">
      <PageHeader
        icon="zap"
        title="Macros"
        description="Respostas rápidas com ações automáticas (envia msg + aplica tag/status/atribuição em 1 clique)"
        action={
          <Button onClick={openCreate} className="gap-1.5">
            <Plus size={14} aria-hidden />
            Nova macro
          </Button>
        }
      />

      {macros.length === 0 ? (
        <Card>
          <CardContent className="py-10 text-center text-muted-foreground">
            <Zap size={28} className="mx-auto mb-3 opacity-50" aria-hidden />
            <p className="text-sm">Nenhuma macro cadastrada ainda.</p>
            <p className="text-xs mt-1">
              Macros aparecem como dropdown <code className="font-mono">/</code> no composer da inbox.
            </p>
            <Button onClick={openCreate} variant="outline" className="mt-4 gap-1.5">
              <Plus size={14} aria-hidden />
              Criar a primeira
            </Button>
          </CardContent>
        </Card>
      ) : (
        <Card>
          <CardContent className="p-0">
            <table className="w-full text-sm">
              <thead className="bg-muted/40 text-xs uppercase text-muted-foreground">
                <tr>
                  <th className="text-left px-3 py-2">Rótulo</th>
                  <th className="text-left px-3 py-2">Atalho</th>
                  <th className="text-left px-3 py-2">Ações</th>
                  <th className="text-center px-3 py-2">Variantes</th>
                  <th className="text-right px-3 py-2">Usos</th>
                  <th className="px-3 py-2 w-32">&nbsp;</th>
                </tr>
              </thead>
              <tbody>
                {macros.map((m) => (
                  <tr key={m.id} className="border-t hover:bg-muted/30">
                    <td className="px-3 py-2">
                      <div className="font-medium">{m.label}</div>
                      <div className="text-xs text-muted-foreground line-clamp-1 max-w-md">
                        {m.body}
                      </div>
                    </td>
                    <td className="px-3 py-2">
                      {m.shortcut ? (
                        <code className="font-mono text-xs bg-muted px-1.5 py-0.5 rounded">
                          /{m.shortcut}
                        </code>
                      ) : (
                        <span className="text-xs text-muted-foreground">—</span>
                      )}
                    </td>
                    <td className="px-3 py-2">
                      {m.actions_json.length === 0 ? (
                        <span className="text-xs text-muted-foreground">só envia msg</span>
                      ) : (
                        <div className="flex gap-1 flex-wrap">
                          {m.actions_json.map((a, i) => (
                            <Badge key={i} variant="outline" className="text-[10px]">
                              {a.type}
                            </Badge>
                          ))}
                        </div>
                      )}
                    </td>
                    <td className="px-3 py-2 text-center">
                      <Link
                        href={route('atendimento.macros.variants.index', { macro: m.id })}
                        className="inline-flex items-center gap-1 text-xs hover:underline"
                        aria-label={`Variantes A/B de ${m.label}`}
                      >
                        <Beaker size={11} aria-hidden />
                        <span className="tabular-nums">{m.variants_count ?? 0}</span>
                      </Link>
                    </td>
                    <td className="px-3 py-2 text-right tabular-nums text-xs">{m.used_count}</td>
                    <td className="px-3 py-2 text-right">
                      <div className="flex gap-1 justify-end">
                        <Button
                          size="sm"
                          variant="ghost"
                          asChild
                          aria-label={`Variantes A/B de ${m.label}`}
                          className="h-7 w-7 p-0"
                          title="Variantes A/B"
                        >
                          <Link href={route('atendimento.macros.variants.index', { macro: m.id })}>
                            <Beaker size={13} aria-hidden />
                          </Link>
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => openEdit(m)}
                          aria-label={`Editar ${m.label}`}
                          className="h-7 w-7 p-0"
                        >
                          <Pencil size={13} aria-hidden />
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => destroy(m)}
                          aria-label={`Remover ${m.label}`}
                          className="h-7 w-7 p-0 text-red-600 hover:text-red-700"
                        >
                          <Trash2 size={13} aria-hidden />
                        </Button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </CardContent>
        </Card>
      )}

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>{form.id ? 'Editar macro' : 'Nova macro'}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <Label>Rótulo</Label>
                <Input
                  value={form.label}
                  onChange={(e) => setForm({ ...form, label: e.target.value })}
                  placeholder="Pedir CNPJ"
                  maxLength={80}
                />
              </div>
              <div>
                <Label>Atalho (opcional)</Label>
                <Input
                  value={form.shortcut}
                  onChange={(e) =>
                    setForm({ ...form, shortcut: e.target.value.toLowerCase().replace(/^\//, '') })
                  }
                  placeholder="cnpj (sem barra)"
                  maxLength={30}
                />
              </div>
            </div>
            <div>
              <Label>Corpo da mensagem</Label>
              <Textarea
                value={form.body}
                onChange={(e) => setForm({ ...form, body: e.target.value })}
                rows={4}
                placeholder="Por favor envie seu CNPJ pra emitir NF..."
                maxLength={4096}
              />
              <div className="text-[11px] text-muted-foreground mt-1">
                {form.body.length} / 4096 caracteres
              </div>
            </div>

            {/* Accordion "Ações avançadas" */}
            <div className="border rounded-md">
              <button
                type="button"
                className="w-full text-left px-3 py-2 text-sm font-medium hover:bg-muted/30 flex items-center justify-between"
                onClick={() => setForm({ ...form, showAdvanced: !form.showAdvanced })}
              >
                <span>Ações avançadas ({form.actions.length})</span>
                <span className="text-xs text-muted-foreground">
                  {form.showAdvanced ? 'recolher' : 'expandir'}
                </span>
              </button>
              {form.showAdvanced && (
                <div className="px-3 py-3 border-t space-y-2">
                  {form.actions.length === 0 && (
                    <div className="text-xs text-muted-foreground">
                      Nenhuma ação. Clique abaixo pra adicionar — executam após envio.
                    </div>
                  )}
                  {form.actions.map((a, idx) => (
                    <div key={idx} className="flex items-center gap-2 bg-muted/30 rounded p-2">
                      <Badge variant="outline" className="text-[10px] shrink-0">
                        {a.type}
                      </Badge>
                      {a.type === 'add_tag' && (
                        <Select
                          value={a.tag_id ? String(a.tag_id) : ''}
                          onValueChange={(v) => updateAction(idx, { tag_id: Number(v) })}
                        >
                          <SelectTrigger className="h-8 text-xs flex-1">
                            <SelectValue placeholder="Escolha a tag" />
                          </SelectTrigger>
                          <SelectContent>
                            {availableTags.map((t) => (
                              <SelectItem key={t.id} value={String(t.id)}>
                                {t.label}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      )}
                      {a.type === 'set_status' && (
                        <Select
                          value={a.status ?? ''}
                          onValueChange={(v) => updateAction(idx, { status: v })}
                        >
                          <SelectTrigger className="h-8 text-xs flex-1">
                            <SelectValue placeholder="Novo status" />
                          </SelectTrigger>
                          <SelectContent>
                            {availableStatuses.map((s) => (
                              <SelectItem key={s.value} value={s.value}>
                                {s.label}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      )}
                      {a.type === 'assign_user' && (
                        <Input
                          value={typeof a.user_id === 'string' || a.user_id ? String(a.user_id) : ''}
                          onChange={(e) =>
                            updateAction(idx, {
                              user_id: e.target.value === 'self' ? 'self' : Number(e.target.value) || null,
                            })
                          }
                          placeholder="ID do user ou 'self'"
                          className="h-8 text-xs flex-1"
                        />
                      )}
                      <Button
                        size="sm"
                        variant="ghost"
                        onClick={() => removeAction(idx)}
                        className="h-7 w-7 p-0 text-red-600"
                        aria-label="Remover ação"
                      >
                        <X size={13} aria-hidden />
                      </Button>
                    </div>
                  ))}
                  <div className="flex gap-2 flex-wrap pt-1">
                    <Button size="sm" variant="outline" onClick={() => addAction('add_tag')}>
                      + Adicionar tag
                    </Button>
                    <Button size="sm" variant="outline" onClick={() => addAction('set_status')}>
                      + Mudar status
                    </Button>
                    <Button size="sm" variant="outline" onClick={() => addAction('assign_user')}>
                      + Atribuir user
                    </Button>
                  </div>
                </div>
              )}
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setOpen(false)} disabled={submitting}>
              Cancelar
            </Button>
            <Button
              onClick={submit}
              disabled={submitting || !form.label.trim() || !form.body.trim()}
            >
              {submitting ? 'Salvando...' : form.id ? 'Salvar' : 'Criar macro'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

MacrosIndex.layout = (page: any) => <AppShellV2>{page}</AppShellV2>;
