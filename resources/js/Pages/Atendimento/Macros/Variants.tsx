// @memcofre
//   tela: /atendimento/macros/{macro_id}/variants
//   stories: US-WA-049 (A/B testing variants pra macros HSM)
//   gap: P2 #18 em memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12-v2.md
//   pattern: Take Blip (multivariate template testing)
//   permissao: whatsapp.settings.manage
//
// MacroVariant = override do body de uma Macro com weight (distribuição
// ponderada) + active flag. Sorteador `MacroVariantPicker` pega variante
// no apply baseado em weight; métricas response_rate = response/sent
// mostram qual variante performa melhor.

import { router, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Plus, Pencil, Trash2, ArrowLeft, Trophy, Beaker } from 'lucide-react';

import AppShellV2 from '@/Layouts/AppShellV2';
import PageHeader from '@/Components/shared/PageHeader';
import { Card, CardContent } from '@/Components/ui/card';
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

interface MacroLite {
  id: number;
  label: string;
  shortcut: string | null;
  body: string;
}

interface Variant {
  id: number;
  macro_id: number;
  label: string;
  body: string;
  weight: number;
  active: boolean;
  sent_count: number;
  response_count: number;
  response_rate: number | null;
  created_at: string | null;
  updated_at: string | null;
}

interface Props {
  macro: MacroLite;
  variants: Variant[];
}

interface FormState {
  id: number | null;
  label: string;
  body: string;
  weight: number;
  active: boolean;
}

const EMPTY_FORM: FormState = {
  id: null,
  label: '',
  body: '',
  weight: 50,
  active: true,
};

function formatRate(rate: number | null): string {
  if (rate === null) return '—';
  return `${(rate * 100).toFixed(1)}%`;
}

export default function MacroVariants({ macro, variants }: Props) {
  const [open, setOpen] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);

  function openCreate() {
    setForm(EMPTY_FORM);
    setOpen(true);
  }

  function openEdit(v: Variant) {
    setForm({
      id: v.id,
      label: v.label,
      body: v.body,
      weight: v.weight,
      active: v.active,
    });
    setOpen(true);
  }

  function submit() {
    if (submitting || !form.label.trim() || !form.body.trim()) return;
    setSubmitting(true);

    const payload = {
      label: form.label,
      body: form.body,
      weight: Math.max(0, Math.min(100, form.weight)),
      active: form.active,
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
      router.put(
        route('atendimento.macros.variants.update', { macro: macro.id, variant: form.id }),
        payload,
        opts,
      );
    } else {
      router.post(
        route('atendimento.macros.variants.store', { macro: macro.id }),
        payload,
        opts,
      );
    }
  }

  function destroy(v: Variant) {
    if (!confirm(`Remover variante "${v.label}"? Histórico de uso será preservado.`)) return;
    router.delete(
      route('atendimento.macros.variants.destroy', { macro: macro.id, variant: v.id }),
      { preserveScroll: true },
    );
  }

  function markWinner(v: Variant) {
    if (
      !confirm(
        `Marcar "${v.label}" como vencedora? Outras variantes serão desativadas (mantém histórico).`,
      )
    )
      return;
    router.post(
      route('atendimento.macros.variants.mark_winner', { macro: macro.id, variant: v.id }),
      {},
      { preserveScroll: true },
    );
  }

  const activeCount = variants.filter((v) => v.active).length;

  return (
    <div className="space-y-4">
      <PageHeader
        icon="beaker"
        title={`Variantes A/B — ${macro.label}`}
        description="Sorteio ponderado por weight no envio. Compare taxa de resposta entre variantes pra decidir vencedora."
        action={
          <div className="flex gap-2">
            <Button asChild variant="outline" size="sm" className="gap-1.5">
              <Link href={route('atendimento.macros.index')}>
                <ArrowLeft size={14} aria-hidden />
                Voltar
              </Link>
            </Button>
            <Button onClick={openCreate} className="gap-1.5">
              <Plus size={14} aria-hidden />
              Nova variante
            </Button>
          </div>
        }
      />

      <Card>
        <CardContent className="py-3 text-xs text-muted-foreground space-y-1">
          <div>
            <strong className="font-mono">Macro:</strong>{' '}
            {macro.shortcut ? <code className="font-mono">/{macro.shortcut}</code> : '—'} ·{' '}
            <span className="line-clamp-1">{macro.body}</span>
          </div>
          <div>
            <strong>Variantes ativas:</strong> {activeCount}/{variants.length}
            {activeCount === 0 && variants.length > 0 && (
              <span className="ml-2 text-amber-600">
                (sem variantes ativas — apply usa body padrão da macro)
              </span>
            )}
          </div>
        </CardContent>
      </Card>

      {variants.length === 0 ? (
        <Card>
          <CardContent className="py-10 text-center text-muted-foreground">
            <Beaker size={28} className="mx-auto mb-3 opacity-50" aria-hidden />
            <p className="text-sm">Nenhuma variante cadastrada.</p>
            <p className="text-xs mt-1">
              Sem variantes, apply sempre usa o body padrão da macro.
            </p>
            <Button onClick={openCreate} variant="outline" className="mt-4 gap-1.5">
              <Plus size={14} aria-hidden />
              Criar primeira variante
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
                  <th className="text-left px-3 py-2">Body</th>
                  <th className="text-right px-3 py-2">Peso</th>
                  <th className="text-right px-3 py-2">Envios</th>
                  <th className="text-right px-3 py-2">Respostas</th>
                  <th className="text-right px-3 py-2">Taxa</th>
                  <th className="text-center px-3 py-2">Status</th>
                  <th className="px-3 py-2 w-32">&nbsp;</th>
                </tr>
              </thead>
              <tbody>
                {variants.map((v) => (
                  <tr key={v.id} className="border-t hover:bg-muted/30">
                    <td className="px-3 py-2 font-medium">{v.label}</td>
                    <td className="px-3 py-2">
                      <div className="text-xs text-muted-foreground line-clamp-2 max-w-md">
                        {v.body}
                      </div>
                    </td>
                    <td className="px-3 py-2 text-right tabular-nums">{v.weight}</td>
                    <td className="px-3 py-2 text-right tabular-nums">{v.sent_count}</td>
                    <td className="px-3 py-2 text-right tabular-nums">{v.response_count}</td>
                    <td className="px-3 py-2 text-right tabular-nums font-medium">
                      {formatRate(v.response_rate)}
                    </td>
                    <td className="px-3 py-2 text-center">
                      {v.active ? (
                        <Badge variant="default" className="text-[10px]">
                          ativa
                        </Badge>
                      ) : (
                        <Badge variant="outline" className="text-[10px] text-muted-foreground">
                          inativa
                        </Badge>
                      )}
                    </td>
                    <td className="px-3 py-2 text-right">
                      <div className="flex gap-1 justify-end">
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => markWinner(v)}
                          aria-label={`Marcar ${v.label} como vencedora`}
                          title="Marcar vencedora (desativa outras + peso=100)"
                          className="h-7 w-7 p-0 text-amber-600 hover:text-amber-700"
                          disabled={!v.active}
                        >
                          <Trophy size={13} aria-hidden />
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => openEdit(v)}
                          aria-label={`Editar ${v.label}`}
                          className="h-7 w-7 p-0"
                        >
                          <Pencil size={13} aria-hidden />
                        </Button>
                        <Button
                          size="sm"
                          variant="ghost"
                          onClick={() => destroy(v)}
                          aria-label={`Remover ${v.label}`}
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
            <DialogTitle>{form.id ? 'Editar variante' : 'Nova variante'}</DialogTitle>
          </DialogHeader>
          <div className="space-y-3">
            <div>
              <Label>Rótulo da variante</Label>
              <Input
                value={form.label}
                onChange={(e) => setForm({ ...form, label: e.target.value })}
                placeholder="Versão A — formal"
                maxLength={80}
              />
            </div>
            <div>
              <Label>Body (corpo da mensagem)</Label>
              <Textarea
                value={form.body}
                onChange={(e) => setForm({ ...form, body: e.target.value })}
                rows={4}
                placeholder="Texto override do body da macro..."
                maxLength={4096}
              />
              <div className="text-[11px] text-muted-foreground mt-1">
                {form.body.length} / 4096 caracteres
              </div>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <Label>Peso ({form.weight})</Label>
                <Input
                  type="range"
                  min={0}
                  max={100}
                  step={1}
                  value={form.weight}
                  onChange={(e) => setForm({ ...form, weight: Number(e.target.value) || 0 })}
                />
                <div className="text-[11px] text-muted-foreground mt-1">
                  0 = pausada · 50 = 50/50 com outras · 100 = sempre essa
                </div>
              </div>
              <div className="flex items-end">
                <label className="flex items-center gap-2 text-sm">
                  <input
                    type="checkbox"
                    checked={form.active}
                    onChange={(e) => setForm({ ...form, active: e.target.checked })}
                  />
                  Ativa (entra na loteria)
                </label>
              </div>
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
              {submitting ? 'Salvando...' : form.id ? 'Salvar' : 'Criar variante'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

MacroVariants.layout = (page: any) => <AppShellV2>{page}</AppShellV2>;
