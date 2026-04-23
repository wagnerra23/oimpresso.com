// @docvault
//   tela: /ponto/intercorrencias/create
//   module: PontoWr2
//   status: implementada
//   stories: US-PONT-001
//   rules: R-PONT-001, R-PONT-004
//   adrs: arq/0001
//   tests: Modules/PontoWr2/Tests/Feature/IntercorrenciasCreateTest

import AppShell from '@/Layouts/AppShell';
import { router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { toast } from 'sonner';
import {
  AlertTriangle,
  ArrowLeft,
  Loader2,
  Save,
  Sparkles,
  Zap,
} from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';
import { cn } from '@/Lib/utils';

interface Colaborador {
  id: number;
  matricula: string | null;
  nome: string;
}

interface Tipo {
  value: string;
  label: string;
}

interface Props {
  colaboradores: Colaborador[];
  tipos: Tipo[];
  ai_enabled: boolean;
}

interface AIResult {
  success: boolean;
  data?: {
    tipo: string;
    dia_todo: boolean;
    prioridade: 'NORMAL' | 'URGENTE';
    impacta_apuracao: boolean;
    descontar_banco_horas: boolean;
    justificativa_formal: string;
    confianca: number;
    motivo: string;
  };
  error?: string;
  cached?: boolean;
}

export default function IntercorrenciasCreate({ colaboradores, tipos, ai_enabled }: Props) {
  const form = useForm({
    colaborador_config_id: '' as string | number,
    tipo: '',
    data: new Date().toISOString().slice(0, 10),
    dia_todo: false,
    intervalo_inicio: '',
    intervalo_fim: '',
    justificativa: '',
    prioridade: 'NORMAL' as 'NORMAL' | 'URGENTE',
    impacta_apuracao: false,
    descontar_banco_horas: false,
  });

  const [descricaoLivre, setDescricaoLivre] = useState('');
  const [aiLoading, setAiLoading] = useState(false);
  const [aiLastResult, setAiLastResult] = useState<AIResult | null>(null);

  const handleAI = async () => {
    if (descricaoLivre.trim().length < 10) {
      toast.error('Digite pelo menos 10 caracteres.');
      return;
    }

    setAiLoading(true);
    setAiLastResult(null);
    try {
      const token =
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
      const res = await fetch('/ponto/intercorrencias-ai/classify', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': token,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ descricao: descricaoLivre }),
      });
      const json: AIResult = await res.json();
      setAiLastResult(json);

      if (json.success && json.data) {
        // Aplica os campos sugeridos no form
        form.setData((prev) => ({
          ...prev,
          tipo: json.data!.tipo,
          dia_todo: json.data!.dia_todo,
          prioridade: json.data!.prioridade,
          impacta_apuracao: json.data!.impacta_apuracao,
          descontar_banco_horas: json.data!.descontar_banco_horas,
          justificativa: json.data!.justificativa_formal,
        }));

        const confiancaPct = Math.round(json.data.confianca * 100);
        toast.success(
          `IA preencheu o formulário (${confiancaPct}% de confiança)${json.cached ? ' · cache' : ''}`,
          { description: json.data.motivo },
        );
      } else {
        toast.error(json.error ?? 'Falha ao classificar.');
      }
    } catch (e: unknown) {
      const msg = e instanceof Error ? e.message : 'Erro desconhecido';
      toast.error(`Falha de rede: ${msg}`);
    } finally {
      setAiLoading(false);
    }
  };

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.post('/ponto/intercorrencias', {
      forceFormData: true,
      onSuccess: () => {
        toast.success('Intercorrência criada como rascunho.');
      },
      onError: () => {
        toast.error('Verifique os campos do formulário.');
      },
    });
  };

  return (
    <AppShell
      title="Nova Intercorrência"
      breadcrumb={[
        { label: 'Ponto WR2' },
        { label: 'Intercorrências', href: '/ponto/intercorrencias' },
        { label: 'Nova' },
      ]}
    >
      <div className="mx-auto max-w-5xl p-6 space-y-6">
        <header className="flex items-start justify-between gap-3">
          <div>
            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
              <AlertTriangle size={22} /> Nova Intercorrência
            </h1>
            <p className="text-sm text-muted-foreground mt-1">
              Registre ausências, consultas médicas, esquecimentos de marcação, etc.
            </p>
          </div>
          <Button variant="outline" size="sm" onClick={() => router.visit('/ponto/intercorrencias')}>
            <ArrowLeft size={14} className="mr-1.5" />
            Voltar
          </Button>
        </header>

        {/* ================== Campo IA ================== */}
        <Card className="border-primary/40 bg-primary/5">
          <CardHeader className="pb-3">
            <CardTitle className="text-base flex items-center gap-2">
              <Sparkles size={16} className="text-primary" /> Descrever em texto livre
              {!ai_enabled && (
                <span className="ml-2 text-[10px] font-normal bg-muted text-muted-foreground px-2 py-0.5 rounded">
                  IA desligada no servidor
                </span>
              )}
            </CardTitle>
            <CardDescription className="text-xs">
              Digite como aconteceu em linguagem natural. A IA vai sugerir tipo, prioridade e
              reescrever a justificativa formalmente.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-3">
            <Textarea
              value={descricaoLivre}
              onChange={(e) => setDescricaoLivre(e.target.value)}
              placeholder="Ex.: Saí mais cedo hoje porque tive consulta no dentista às 16h. Retornei às 17h30."
              rows={3}
              className="bg-background"
              disabled={!ai_enabled}
            />
            <div className="flex items-center justify-between gap-2">
              <p className="text-xs text-muted-foreground">
                {descricaoLivre.length}/2000 caracteres
                {!ai_enabled && ' · Configure AI_ENABLED + OPENAI_API_KEY no servidor'}
              </p>
              <Button
                type="button"
                onClick={handleAI}
                disabled={!ai_enabled || aiLoading || descricaoLivre.trim().length < 10}
                className="gap-2"
              >
                {aiLoading ? (
                  <>
                    <Loader2 size={14} className="animate-spin" /> Classificando…
                  </>
                ) : (
                  <>
                    <Zap size={14} /> Preencher com IA
                  </>
                )}
              </Button>
            </div>

            {aiLastResult && !aiLastResult.success && (
              <Alert variant="destructive">
                <AlertTriangle size={14} />
                <AlertTitle>IA retornou erro</AlertTitle>
                <AlertDescription>{aiLastResult.error}</AlertDescription>
              </Alert>
            )}

            {aiLastResult?.success && aiLastResult.data && (
              <Alert className="border-emerald-500/40 bg-emerald-500/5">
                <Sparkles size={14} className="text-emerald-600" />
                <AlertTitle className="text-emerald-700 dark:text-emerald-400">
                  Classificado com {Math.round(aiLastResult.data.confianca * 100)}% de confiança
                  {aiLastResult.cached && ' (cache)'}
                </AlertTitle>
                <AlertDescription className="text-xs">{aiLastResult.data.motivo}</AlertDescription>
              </Alert>
            )}
          </CardContent>
        </Card>

        {/* ================== Form estruturado ================== */}
        <form onSubmit={submit} className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Dados da ocorrência</CardTitle>
              <CardDescription className="text-xs">
                Confirme/ajuste os campos. Eles serão submetidos ao RH para aprovação.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <Field label="Colaborador" error={form.errors.colaborador_config_id} required>
                  <Select
                    value={String(form.data.colaborador_config_id || '')}
                    onValueChange={(v) => form.setData('colaborador_config_id', v)}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Selecione o colaborador" />
                    </SelectTrigger>
                    <SelectContent>
                      {colaboradores.length === 0 ? (
                        <div className="px-2 py-3 text-xs text-muted-foreground text-center">
                          Nenhum colaborador com controle de ponto ativo.
                        </div>
                      ) : (
                        colaboradores.map((c) => (
                          <SelectItem key={c.id} value={String(c.id)}>
                            {c.matricula ? `${c.matricula} — ` : ''}
                            {c.nome}
                          </SelectItem>
                        ))
                      )}
                    </SelectContent>
                  </Select>
                </Field>

                <Field label="Tipo" error={form.errors.tipo} required>
                  <Select value={form.data.tipo} onValueChange={(v) => form.setData('tipo', v)}>
                    <SelectTrigger>
                      <SelectValue placeholder="Selecione o tipo" />
                    </SelectTrigger>
                    <SelectContent>
                      {tipos.map((t) => (
                        <SelectItem key={t.value} value={t.value}>
                          {t.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </Field>

                <Field label="Data" error={form.errors.data} required>
                  <Input
                    type="date"
                    value={form.data.data}
                    max={new Date().toISOString().slice(0, 10)}
                    onChange={(e) => form.setData('data', e.target.value)}
                  />
                </Field>

                <Field label="Prioridade" error={form.errors.prioridade}>
                  <Select
                    value={form.data.prioridade}
                    onValueChange={(v) => form.setData('prioridade', v as 'NORMAL' | 'URGENTE')}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="NORMAL">Normal</SelectItem>
                      <SelectItem value="URGENTE">Urgente</SelectItem>
                    </SelectContent>
                  </Select>
                </Field>
              </div>

              <div className="flex items-center gap-2">
                <input
                  id="dia_todo"
                  type="checkbox"
                  className="size-4"
                  checked={form.data.dia_todo}
                  onChange={(e) => form.setData('dia_todo', e.target.checked)}
                />
                <Label htmlFor="dia_todo" className="text-sm cursor-pointer">
                  Dia inteiro (sem horário específico)
                </Label>
              </div>

              {!form.data.dia_todo && (
                <div className="grid grid-cols-2 gap-4">
                  <Field label="Início" error={form.errors.intervalo_inicio}>
                    <Input
                      type="time"
                      value={form.data.intervalo_inicio}
                      onChange={(e) => form.setData('intervalo_inicio', e.target.value)}
                    />
                  </Field>
                  <Field label="Fim" error={form.errors.intervalo_fim}>
                    <Input
                      type="time"
                      value={form.data.intervalo_fim}
                      onChange={(e) => form.setData('intervalo_fim', e.target.value)}
                    />
                  </Field>
                </div>
              )}

              <Field label="Justificativa" error={form.errors.justificativa} required>
                <Textarea
                  value={form.data.justificativa}
                  onChange={(e) => form.setData('justificativa', e.target.value)}
                  placeholder="Justificativa formal para o RH (mínimo 10 caracteres)"
                  rows={4}
                />
                <p className="text-[10px] text-muted-foreground mt-1">
                  {form.data.justificativa.length}/2000 · mínimo 10
                </p>
              </Field>

              <div className="flex flex-col gap-2 pt-2">
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    className="size-4"
                    checked={form.data.impacta_apuracao}
                    onChange={(e) => form.setData('impacta_apuracao', e.target.checked)}
                  />
                  <span className="text-sm">Impacta apuração (abona ausência)</span>
                </label>
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    className="size-4"
                    checked={form.data.descontar_banco_horas}
                    onChange={(e) => form.setData('descontar_banco_horas', e.target.checked)}
                  />
                  <span className="text-sm">Descontar do banco de horas</span>
                </label>
              </div>
            </CardContent>
          </Card>

          <div className="flex justify-end gap-2">
            <Button
              type="button"
              variant="outline"
              onClick={() => router.visit('/ponto/intercorrencias')}
              disabled={form.processing}
            >
              Cancelar
            </Button>
            <Button type="submit" disabled={form.processing} className="gap-1.5">
              {form.processing ? (
                <Loader2 size={14} className="animate-spin" />
              ) : (
                <Save size={14} />
              )}
              Salvar como rascunho
            </Button>
          </div>
        </form>
      </div>
    </AppShell>
  );
}

// ============================================================================
// Helper: Field com label + erro
// ============================================================================

function Field({
  label,
  error,
  required,
  children,
}: {
  label: string;
  error?: string;
  required?: boolean;
  children: React.ReactNode;
}) {
  return (
    <div className="space-y-1.5">
      <Label className={cn(required && "after:content-['*'] after:text-destructive after:ml-0.5")}>
        {label}
      </Label>
      {children}
      {error && <p className="text-xs text-destructive">{error}</p>}
    </div>
  );
}
