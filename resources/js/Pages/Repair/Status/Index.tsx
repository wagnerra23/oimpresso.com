// @memcofre tela=/repair/status module=Repair
// Sprint 2.5 / MWART-0002 — port da tela de Status (Repair) Blade → Inertia/React.
// FORMA portada de prototipo-ui/cowork/Wagner/repair-page.jsx, região `Status` (L304-337) +
// repair-page.css (.rep-status-row / .rep-st) — ADR UI-0029, protótipo soberano na forma.
// Plano: memory/requisitos/Repair/RUNBOOK-repair-status.md
// Diff medido: memory/requisitos/Repair/6telas-index-visual-comparison.md §3.4

import AppShellV2 from '@/Layouts/AppShellV2';
import { Link } from '@inertiajs/react';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Icon } from '@/Components/Icon';
import { Grid, Stack } from '@/Components/layout';
import type { ReactNode } from 'react';

interface StatusRow {
  id: number;
  name: string;
  color: string | null;
  sort_order: number;
  is_completed_status: number;
  sms_template: string | null;
  /** Quantas folhas de OS usam este status — status_id é FK em job_sheets. */
  job_sheets_count: number;
}

interface PageProps {
  statuses: StatusRow[];
}

/**
 * Pill do status — a cor vem do DADO (repair_statuses.color), não de um tom do DS:
 * é escolha do usuário por business. Espelha `.rep-st` do protótipo, que deriva fundo
 * e borda da própria cor via color-mix. `color` é nullable no schema — sem cor, cai
 * nos tokens neutros do DS em vez de gerar um color-mix inválido.
 */
function SeloStatus({ name, color }: { name: string; color: string | null }) {
  if (!color) {
    return (
      <span className="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border border-border bg-muted px-2.5 py-0.5 text-xs font-semibold text-muted-foreground">
        <i className="h-1.5 w-1.5 rounded-full bg-muted-foreground" aria-hidden="true" />
        {name}
      </span>
    );
  }

  return (
    <span
      className="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-2.5 py-0.5 text-xs font-semibold"
      style={{
        color,
        backgroundColor: `color-mix(in oklch, ${color} 12%, transparent)`,
        borderColor: `color-mix(in oklch, ${color} 30%, transparent)`,
      }}
    >
      <i className="h-1.5 w-1.5 rounded-full" style={{ backgroundColor: color }} aria-hidden="true" />
      {name}
    </span>
  );
}

export default function StatusIndex({ statuses }: PageProps) {
  const emUso = statuses.filter((s) => s.job_sheets_count > 0).length;

  return (
    <div className="container mx-auto space-y-4 p-4">
      <PageHeader
        icon="flag"
        title="Status do reparo"
        description="Cada status carrega cor, ordem, marcação de conclusão e o modelo de SMS disparado ao cliente. A ordem alimenta o kanban da produção."
        action={
          <Button asChild>
            <Link href="/repair/status/create">
              <Icon name="plus" className="mr-2 h-4 w-4" />
              Adicionar status
            </Link>
          </Button>
        }
      />

      {statuses.length === 0 ? (
        <EmptyState
          icon="flag"
          title="Nenhum status configurado"
          description="Crie pelo menos 1 status pra usar no fluxo de OS."
        />
      ) : (
        <>
          {/* .rep-status-list do prototipo: gap 6px -> gap={2} (8px), o token vizinho. */}
          <Stack gap={2}>
            {statuses.map((s) => (
              <Grid
                key={s.id}
                gap={3}
                className="grid-cols-[1fr_auto] items-center rounded-lg border border-border bg-card px-3 py-2.5 transition-colors hover:bg-accent/40 focus-within:bg-accent/40 xl:grid-cols-[190px_230px_160px_1fr_auto]"
              >
                <SeloStatus name={s.name} color={s.color} />

                <span className="col-span-2 font-mono text-[11px] text-muted-foreground xl:col-span-1 xl:justify-self-start">
                  ordem {s.sort_order} · {s.job_sheets_count} folha(s)
                </span>

                <span className="col-span-2 text-xs text-foreground/80 xl:col-span-1">
                  {s.is_completed_status === 1 ? 'marcado como concluído' : 'pendente'}
                </span>

                {/* Abaixo de xl o protótipo esconde o template (media query 1100px). */}
                <span
                  className="hidden min-w-0 truncate text-xs text-muted-foreground xl:block"
                  title={s.sms_template ?? undefined}
                >
                  {s.sms_template ? `SMS: “${s.sms_template}”` : 'sem modelo de SMS'}
                </span>

                <Button variant="ghost" size="sm" asChild className="justify-self-end">
                  <Link href={`/repair/status/${s.id}/edit`}>Editar</Link>
                </Button>
              </Grid>
            ))}
          </Stack>

          {emUso > 0 && (
            <Alert className="border-warning bg-warning/10">
              <Icon name="triangle-alert" className="h-4 w-4 text-warning-fg" />
              <AlertTitle>Excluir status não é reversível para as folhas</AlertTitle>
              <AlertDescription>
                O status é FK das folhas — apagar um status usado deixa folha órfã. Antes de
                excluir, migre as folhas. Hoje {emUso} de {statuses.length}{' '}
                {emUso === 1 ? 'status está em uso' : 'status estão em uso'}.
              </AlertDescription>
            </Alert>
          )}

          <p className="text-xs text-muted-foreground">
            Permissão: <code className="font-mono">access_job_sheet_status</code>
          </p>
        </>
      )}
    </div>
  );
}

StatusIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;
