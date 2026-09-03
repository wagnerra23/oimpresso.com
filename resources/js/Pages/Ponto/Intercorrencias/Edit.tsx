// Edição de rascunho de intercorrência — /ponto/intercorrencias/{id}/edit
//
// Esta tela era a ÚLTIMA Blade viva do módulo (SDD §5.4 item 1: dos 21 renders dos
// controllers do Ponto, 20 Inertia + 1 Blade — este). Quem clicava "editar" num
// rascunho saía do shell React e caía no AdminLTE. Migrada em 2026-08-28 por
// decisão [W] ("a tela fica").
//
// Paridade: os campos vêm do `_form.blade.php` legado + do `IntercorrenciaRequest`
// (a validação que o submit vai encontrar), NÃO do Create.tsx. O Create é a
// referência VISUAL (mesmos componentes do DS, mesmo helper Field); o contrato de
// campos é o FormRequest.
//
// Diferença deliberada vs Create: aqui NÃO há bloco de classificação por IA. O
// `aiClassify` existe para transformar texto livre em campos na CRIAÇÃO; num
// rascunho que já tem campos preenchidos ele reescreveria escolha do operador.

import AppShellV2 from '@/Layouts/AppShellV2';
import { router, useForm } from '@inertiajs/react';
import { type FormEvent, type ReactNode } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import { Grid, Inline } from '@/Components/layout';
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
import { FileLock2, Loader2, Save } from 'lucide-react';

interface Colaborador {
  id: number;
  matricula: string | null;
  nome: string;
}

interface Tipo {
  value: string;
  label: string;
}

interface Intercorrencia {
  id: string | number;
  codigo: string;
  estado: string;
  colaborador_config_id: number | string | null;
  tipo: string;
  data: string | null;
  dia_todo: boolean;
  intervalo_inicio: string;
  intervalo_fim: string;
  justificativa: string;
  prioridade: 'NORMAL' | 'URGENTE';
  impacta_apuracao: boolean;
  descontar_banco_horas: boolean;
}

interface Props {
  intercorrencia: Intercorrencia;
  colaboradores: Colaborador[];
  tipos: Tipo[];
}

export default function IntercorrenciasEdit({ intercorrencia, colaboradores, tipos }: Props) {
  const form = useForm({
    colaborador_config_id: (intercorrencia.colaborador_config_id ?? '') as string | number,
    tipo: intercorrencia.tipo ?? '',
    data: intercorrencia.data ?? '',
    dia_todo: intercorrencia.dia_todo,
    intervalo_inicio: intercorrencia.intervalo_inicio ?? '',
    intervalo_fim: intercorrencia.intervalo_fim ?? '',
    justificativa: intercorrencia.justificativa ?? '',
    prioridade: (intercorrencia.prioridade ?? 'NORMAL') as 'NORMAL' | 'URGENTE',
    impacta_apuracao: intercorrencia.impacta_apuracao,
    descontar_banco_horas: intercorrencia.descontar_banco_horas,
  });

  const submit = (e: FormEvent) => {
    e.preventDefault();
    form.put(`/ponto/intercorrencias/${intercorrencia.id}`);
  };

  return (
    <>
      <div className="mx-auto max-w-4xl p-6 space-y-4">
        <header className="os-page-h">
          <div className="os-page-h-l">
            <h1>
              Editar intercorrência{' '}
              {/* As 19 telas irmãs usam a paleta CRUA do Tailwind neste subtítulo (família
                  stone, tom 400) — dívida sistemática do `os-page-h`, documentada no RUNBOOK
                  do módulo. Código novo não engrossa dívida conhecida: aqui vai o token. Se a
                  diferença de tom incomodar, o conserto é nas 19, não o retorno da cor crua.
                  O nome exato da classe NÃO é reproduzido de propósito: o `ui:lint` R1 varre o
                  TEXTO do arquivo e só pula linha que comece com barra-barra, asterisco ou
                  barra-asterisco — a forma de comentário do JSX abre com chave antes disso e
                  não casa no skip, então citar a classe proibida aqui CONTA como violação. */}
              <span className="text-muted-foreground font-normal">· {intercorrencia.codigo}</span>
            </h1>
            <p>
              Ajuste os dados e salve. Depois de <strong>submeter</strong>, a ocorrência vai para
              aprovação e deixa de ser editável.
            </p>
          </div>
        </header>

        {/* O backend recusa (403) qualquer estado != RASCUNHO — CU-PONTO-05. Este
            aviso explica a regra ANTES do operador perder o trabalho digitado. */}
        <Alert>
          <FileLock2 className="size-4" />
          <AlertTitle>Só rascunho é editável</AlertTitle>
          <AlertDescription className="text-xs">
            Esta intercorrência está em <strong>{intercorrencia.estado}</strong>. Uma vez submetida,
            o histórico dela vira trilha de aprovação e não pode mais ser reescrito.
          </AlertDescription>
        </Alert>

        <form onSubmit={submit} className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Dados da ocorrência</CardTitle>
              <CardDescription className="text-xs">
                Confirme/ajuste os campos. Eles serão submetidos ao RH para aprovação.
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <Grid cols={1} gap={4} className="md:grid-cols-2">
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
              </Grid>

              <Inline gap={2}>
                <Checkbox
                  id="dia_todo"
                  checked={form.data.dia_todo}
                  onCheckedChange={(v) => form.setData('dia_todo', v === true)}
                />
                <Label htmlFor="dia_todo" variant="shadcn" className="font-normal cursor-pointer">
                  Dia todo
                </Label>
              </Inline>

              {/* Espelha o `required_unless:dia_todo,true` do IntercorrenciaRequest:
                  esconder os horários quando é dia todo evita o operador preencher
                  campo que a validação vai ignorar. */}
              {!form.data.dia_todo && (
                <Grid cols={1} gap={4} className="md:grid-cols-2">
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
                </Grid>
              )}

              <Field label="Justificativa" error={form.errors.justificativa} required>
                <Textarea
                  rows={5}
                  value={form.data.justificativa}
                  onChange={(e) => form.setData('justificativa', e.target.value)}
                />
                <p className="text-xs text-muted-foreground">
                  {form.data.justificativa.length}/2000 · mínimo 10
                </p>
              </Field>

              <Grid cols={1} gap={4} className="md:grid-cols-2">
                <Inline gap={2}>
                  <Checkbox
                    id="impacta_apuracao"
                    checked={form.data.impacta_apuracao}
                    onCheckedChange={(v) => form.setData('impacta_apuracao', v === true)}
                  />
                  <Label
                    htmlFor="impacta_apuracao"
                    variant="shadcn"
                    className="font-normal cursor-pointer"
                  >
                    Impacta a apuração
                  </Label>
                </Inline>
                <Inline gap={2}>
                  <Checkbox
                    id="descontar_banco_horas"
                    checked={form.data.descontar_banco_horas}
                    onCheckedChange={(v) => form.setData('descontar_banco_horas', v === true)}
                  />
                  <Label
                    htmlFor="descontar_banco_horas"
                    variant="shadcn"
                    className="font-normal cursor-pointer"
                  >
                    Descontar do banco de horas
                  </Label>
                </Inline>
              </Grid>
            </CardContent>
          </Card>

          <Inline gap={2} justify="end">
            <Button
              type="button"
              variant="outline"
              onClick={() => router.visit(`/ponto/intercorrencias/${intercorrencia.id}`)}
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
              Salvar rascunho
            </Button>
          </Inline>
        </form>
      </div>
    </>
  );
}

IntercorrenciasEdit.layout = (page: ReactNode) => (
  <AppShellV2
    title="Editar intercorrência"
    breadcrumbItems={[
      { label: 'Ponto WR2' },
      { label: 'Intercorrências', href: '/ponto/intercorrencias' },
      { label: 'Editar' },
    ]}
  >
    {page}
  </AppShellV2>
);

// ============================================================================
// Helper: Field com label + erro (mesmo do Create.tsx — paridade visual)
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
