// @docvault
//   tela: /hrm/leave-type
//   module: Essentials
//   status: implementada
//   rules: R-ESSE-001
//   tests: Modules/Essentials/Tests/Feature/HrmTiposIndexTest
//
// Essentials/Tipos — carimbada do PT-01 Lista por criar-tela.mjs (UI-0013).
// A estrutura imita a irmã Essentials/Holidays/Index.tsx (mesmo módulo, mesma
// família de rota /hrm/*): <table> + Card + Deferred. Isso satisfaz a assinatura
// PT-01 por construção (pt-signatures.mjs: `s.list` aceita <table>) sem inventar
// paginação server-side que esta lista — dezenas de tipos, não milhares — não tem.
//
// A coluna "Limite" EXISTE de fato: `essentials_leave_types.max_leave_count`
// (int nullable) + `leave_count_interval` (enum month/year nullable), migration
// 2019_05_17_153306. Quando nula, a tela mostra "sem limite" / "—" — não inventa campo.
//
// RUNBOOK: memory/requisitos/Essentials/RUNBOOK-tipos.md (ADR 0104 F1 PLAN)

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import { Skeleton } from '@/Components/ui/skeleton';
import { CalendarClock, Pencil, Plus, Trash2 } from 'lucide-react';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/Components/ui/alert-dialog';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Inline } from '@/Components/layout';

interface LeaveType {
  id: number;
  leave_type: string;
  max_leave_count: number | null;
  leave_count_interval: 'month' | 'year' | null;
  leaves_count: number;
}

interface Props {
  // `tipos` vem via Inertia::defer — undefined no first render.
  tipos?: LeaveType[];
  can_manage: boolean;
}

/** Copy literal do protótipo (hrm-page.jsx, subview "tipos"): `N dias` | "sem limite". */
const limiteLabel = (t: LeaveType) =>
  t.max_leave_count ? `${t.max_leave_count} dias` : 'sem limite';

/** Copy literal do protótipo: "por ano" | "por mês". Sem intervalo declarado, "—". */
const intervaloLabel = (t: LeaveType) => {
  if (t.leave_count_interval === 'year') return 'por ano';
  if (t.leave_count_interval === 'month') return 'por mês';
  return '—';
};

export default function TiposIndex({ tipos, can_manage }: Props) {
  const rows = tipos ?? [];
  const [deleteTarget, setDeleteTarget] = useState<LeaveType | null>(null);
  // Motivo do 422 vindo do servidor. Mantém o diálogo ABERTO com a explicação —
  // um toast sozinho some, e o usuário fica sem saber por que a exclusão não passou.
  const [blocked, setBlocked] = useState<{ msg: string; leaves: number } | null>(null);
  const [deleting, setDeleting] = useState(false);

  const closeDelete = () => {
    setDeleteTarget(null);
    setBlocked(null);
  };

  /**
   * DELETE por `fetch`, não por `router.delete`: o contrato do servidor
   * (EssentialsLeaveTypeController::destroy, PR #6789) responde 422 com JSON
   * `{success:false, msg, blocked_by:{leaves:N}}`. O Inertia interpreta 422 como
   * erro de VALIDAÇÃO e só expõe a chave `errors` — `msg` e `blocked_by` se perdem
   * no caminho, e o usuário veria um "erro" genérico. `fetch` lê o corpo real.
   * Mesmo padrão de Cliente/Index.tsx (contato com vendas → success:false + msg).
   */
  const confirmDelete = async () => {
    if (!deleteTarget) return;
    setDeleting(true);
    setBlocked(null);
    try {
      const csrf =
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';
      const r = await fetch(`/hrm/leave-type/${deleteTarget.id}`, {
        method: 'DELETE',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
      });
      const json = await r.json().catch(() => ({ success: false }));

      if (r.ok && json?.success) {
        toast.success(json.msg || 'Tipo de licença excluído.');
        closeDelete();
        // `reload` recebe ReloadOptions — não aceita `preserveScroll` (isso é de VisitOptions,
        // o que a irmã Holidays usa no `router.delete`). Partial reload só de `tipos`.
        router.reload({ only: ['tipos'] });
        return;
      }

      if (r.status === 422 && json?.blocked_by) {
        // O motivo É o conteúdo do erro: quantas licenças travam a exclusão.
        setBlocked({
          msg: json.msg || 'Este tipo de licença está em uso.',
          leaves: Number(json.blocked_by.leaves ?? 0),
        });
        return;
      }

      toast.error(json?.msg || 'Não foi possível excluir este tipo de licença.');
    } catch {
      toast.error('Falha de rede ao excluir o tipo de licença.');
    } finally {
      setDeleting(false);
    }
  };

  return (
    <>
      <div className="mx-auto max-w-6xl p-6 space-y-4">
        {/* `data-contract` = âncora do contrato de tela (prototipo-ui/contrato/). NÃO remova o
            atributo sem tirar a seção do .contract.json — o gate contrato-de-tela cobra os dois. */}
        <Inline asChild align="start" justify="between" gap={3}>
        <header data-contract="cabecalho">
          <div>
            <Inline asChild align="center" gap={2}>
            <h1 className="text-2xl font-semibold tracking-tight">
              <CalendarClock size={22} /> Tipos de licença
            </h1>
            </Inline>
            <p className="text-sm text-muted-foreground mt-1">
              Sem tipo não dá para pedir licença. O limite é informativo hoje.
            </p>
          </div>
        </header>
        </Inline>

        <Inline align="center" gap={2} data-contract="toolbar">
          <Deferred
            data="tipos"
            fallback={<span className="text-sm text-muted-foreground">Carregando…</span>}
          >
            <span className="text-sm text-muted-foreground">
              {rows.length} {rows.length === 1 ? 'tipo' : 'tipos'}
            </span>
          </Deferred>
          <span className="flex-1" />
          {can_manage && (
            <Button asChild>
              <a href="/hrm/leave-type/create">
                <Plus size={14} className="mr-1.5" /> Novo tipo
              </a>
            </Button>
          )}
        </Inline>

        <Deferred data="tipos" fallback={<Skeleton className="h-64 w-full" />}>
          <Card data-contract="lista">
            <CardContent className="p-0">
              {rows.length === 0 ? (
                <div className="p-12 text-center text-sm text-muted-foreground">
                  <CalendarClock size={32} className="mx-auto mb-2 opacity-50" />
                  <p className="font-medium text-foreground">Nenhum tipo de licença cadastrado</p>
                  <p className="mt-1">
                    Sem tipo não dá para pedir licença — comece por Férias (30 dias/ano) e Licença
                    médica.
                  </p>
                </div>
              ) : (
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead className="border-b border-border bg-muted/30 text-xs text-muted-foreground">
                      <tr>
                        <th className="text-left p-3 font-medium">Tipo</th>
                        <th className="text-right p-3 font-medium">Limite</th>
                        <th className="text-left p-3 font-medium">Intervalo</th>
                        <th className="text-right p-3 font-medium">Pedidos no ano</th>
                        {can_manage && <th className="text-right p-3 font-medium">Ações</th>}
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-border">
                      {rows.map((t) => (
                        <tr key={t.id} className="hover:bg-accent/30">
                          <td className="p-3 font-medium">{t.leave_type}</td>
                          <td className="p-3 text-right tabular-nums">{limiteLabel(t)}</td>
                          <td className="p-3 text-xs">{intervaloLabel(t)}</td>
                          <td className="p-3 text-right tabular-nums">{t.leaves_count}</td>
                          {can_manage && (
                            <td className="p-3 text-right">
                              <Inline justify="end" gap={1}>
                                <Button size="sm" variant="ghost" className="h-7 w-7 p-0" asChild>
                                  <a
                                    href={`/hrm/leave-type/${t.id}/edit`}
                                    aria-label={`Editar ${t.leave_type}`}
                                  >
                                    <Pencil size={12} />
                                  </a>
                                </Button>
                                <Button
                                  size="sm"
                                  variant="ghost"
                                  className="h-7 w-7 p-0 text-destructive"
                                  aria-label={`Excluir ${t.leave_type}`}
                                  onClick={() => {
                                    setBlocked(null);
                                    setDeleteTarget(t);
                                  }}
                                >
                                  <Trash2 size={12} />
                                </Button>
                              </Inline>
                            </td>
                          )}
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </CardContent>
          </Card>
        </Deferred>
      </div>

      <AlertDialog open={deleteTarget !== null} onOpenChange={(open) => !open && closeDelete()}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>
              {blocked ? 'Não dá para excluir este tipo' : 'Excluir tipo de licença?'}
            </AlertDialogTitle>
            <AlertDialogDescription>
              {blocked ? (
                <span data-testid="tipo-bloqueado">{blocked.msg}</span>
              ) : (
                <>&quot;{deleteTarget?.leave_type}&quot; será apagado permanentemente.</>
              )}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{blocked ? 'Fechar' : 'Cancelar'}</AlertDialogCancel>
            {!blocked && (
              <AlertDialogAction
                onClick={(e) => {
                  e.preventDefault();
                  void confirmDelete();
                }}
                disabled={deleting}
                className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              >
                Excluir
              </AlertDialogAction>
            )}
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

TiposIndex.layout = (page: ReactNode) => (
  <AppShellV2
    title="Tipos de licença"
    breadcrumbItems={[{ label: 'HRM' }, { label: 'Tipos de licença' }]}
  >
    {page}
  </AppShellV2>
);
