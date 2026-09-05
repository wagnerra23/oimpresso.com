// @docvault
//   tela: /hrm/leave
//   module: Essentials
//   status: implementada
//   rules: R1-R12 (Index.charter.md)
//   tests: Modules/Essentials/Tests/Feature/HrmLicencaTest
//
// HRM · Licenças — PR-9 da onda HRM-O7 (PEDIDO-CL-hrm.md).
// Carimbada do PT-01 Lista por criar-tela.mjs (UI-0013) e preenchida contra o alvo
// medido em prototipo-ui/design-docs/cowork-inbox/hrm/EXPORT-HRM-2026-09-04.md §3.
//
// As âncoras `data-contract` casam 1:1 com prototipo-ui/contrato/essentials-licencas
// .contract.json — não remova o atributo sem tirar a seção do contrato.
import AppShellV2 from '@/Layouts/AppShellV2';
import { PageHeader } from '@/Components/PageHeader';
import { Deferred, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import { toast } from 'sonner';
import {
  AlertCircle,
  CalendarDays,
  Check,
  Loader2,
  Plus,
  Search,
  Tags,
  X,
} from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Checkbox } from '@/Components/ui/checkbox';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Select,
  SelectContent,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { SafeSelectItem } from '@/Components/ui/SafeSelectItem';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/Components/ui/sheet';
import { Skeleton } from '@/Components/ui/skeleton';
import { Textarea } from '@/Components/ui/textarea';

// ── formas vindas do EssentialsLeaveController ──────────────────────────────
interface Licenca {
  id: number;
  ref_no: string | null;
  tipo: string | null;
  tipo_id: number;
  colaborador: string;
  user_id: number;
  start_date: string;      // ISO — comparável com `hoje`
  end_date: string;
  periodo_label: string;   // já formatado no servidor (respeita o negócio)
  dias: number;
  motivo: string | null;
  status: 'pending' | 'approved' | 'cancelled';
  status_label: string;
  status_note: string | null;
}

interface Paginador {
  data: Licenca[];
  total: number;
  current_page: number;
  last_page: number;
  links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface TipoOption { id: number; label: string; limite: number; intervalo: string | null }
interface PessoaOption { id: number; label: string }
interface Saldo {
  id: number;
  tipo: string;
  limite: number | null;
  intervalo: string | null;
  aprovado: number;
  em_analise: number;
  consumo: number | null;
  risco: boolean;
}
interface Filtros {
  busca: string | null;
  status: string | null;
  leave_type: number | null;
  user_id: number | null;
  start_date: string | null;
  end_date: string | null;
}
interface Props {
  // Tudo abaixo com `?` chega via Inertia::defer — undefined no primeiro render.
  licencas?: Paginador;
  tipos?: TipoOption[];
  colaboradores?: PessoaOption[];
  kpis?: { pendentes: number; aprovadas: number; tipos: number };
  saldos?: Saldo[];
  filtros: Filtros;
  permissoes: {
    ver_todos: boolean;
    aprovar: boolean;
    excluir: boolean;
    criar_para_terceiros: boolean;
  };
  situacoes: Array<{ valor: string; label: string }>;
  hoje: string;
  date_format: string;
}

const TODAS = 'ALL';
const ROTA = '/hrm/leave';
const PROPS_DA_LISTA = ['licencas', 'kpis', 'saldos', 'filtros'];

const csrf = () =>
  (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';

/**
 * ISO (`2026-09-21`, o que <input type="date"> devolve) → formato do negócio.
 * O servidor grava com `ModuleUtil::uf_date`, que parseia com `business.date_format`;
 * postar ISO cru faz o `createFromFormat` lançar e o erro chega como "algo deu errado".
 */
function paraFormatoDoNegocio(iso: string, formato: string): string {
  const [ano, mes, dia] = iso.split('-');
  if (!ano || !mes || !dia) return iso;
  return formato
    .replace('d', dia)
    .replace('m', mes)
    .replace('Y', ano);
}

const variantePorStatus: Record<Licenca['status'], 'secondary' | 'default' | 'outline'> = {
  pending: 'secondary',
  approved: 'default',
  cancelled: 'outline',
};

export default function LicencasIndex({
  licencas,
  tipos,
  colaboradores,
  kpis,
  saldos,
  filtros,
  permissoes,
  situacoes,
  hoje,
  date_format,
}: Props) {
  const linhas = licencas?.data ?? [];
  const total = licencas?.total ?? 0;
  const listaTipos = tipos ?? [];
  const listaPessoas = colaboradores ?? [];

  const [busca, setBusca] = useState(filtros.busca ?? '');
  const [selecionadas, setSelecionadas] = useState<number[]>([]);
  const [aberta, setAberta] = useState<Licenca | null>(null);
  const [confirmando, setConfirmando] = useState<{ alvo: Licenca[]; status: 'approved' | 'cancelled' } | null>(null);
  const [nota, setNota] = useState('');
  const [enviando, setEnviando] = useState(false);
  const [pedindo, setPedindo] = useState(false);
  const [aba, setAba] = useState<'licencas' | 'saldo'>('licencas');

  const campoBusca = useRef<HTMLInputElement>(null);

  const temFiltro = Boolean(
    filtros.busca || filtros.status || filtros.leave_type || filtros.user_id
      || filtros.start_date || filtros.end_date,
  );

  const aplicar = useCallback((chave: keyof Filtros, valor: string | number | null) => {
    router.get(
      ROTA,
      { ...filtros, [chave]: valor === '' || valor === TODAS ? undefined : valor },
      { preserveState: true, preserveScroll: true, replace: true, only: PROPS_DA_LISTA },
    );
  }, [filtros]);

  const limpar = useCallback(() => {
    setBusca('');
    router.get(ROTA, {}, { preserveScroll: true, only: PROPS_DA_LISTA });
  }, []);

  // Busca com debounce — o filtro é do SERVIDOR (a lista é paginada; filtrar só o
  // que veio nesta página mentiria sobre o contador "N de M").
  useEffect(() => {
    if (busca === (filtros.busca ?? '')) return;
    const t = setTimeout(() => aplicar('busca', busca || null), 350);
    return () => clearTimeout(t);
  }, [busca, filtros.busca, aplicar]);

  // Atalhos escopados: `/` foca a busca, `n` abre o pedido. Não disparam enquanto
  // o foco está num campo — senão digitar "n" num textarea abriria o formulário.
  useEffect(() => {
    const emCampo = (alvo: EventTarget | null) => {
      const el = alvo as HTMLElement | null;
      return !!el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.isContentEditable);
    };
    const onKey = (e: KeyboardEvent) => {
      if (emCampo(e.target) || e.metaKey || e.ctrlKey || e.altKey) return;
      if (e.key === '/') { e.preventDefault(); campoBusca.current?.focus(); }
      if (e.key === 'n') { e.preventDefault(); setPedindo(true); }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  const alternar = (id: number) =>
    setSelecionadas((atual) => atual.includes(id) ? atual.filter((x) => x !== id) : [...atual, id]);

  const pendentesSelecionadas = useMemo(
    () => linhas.filter((l) => selecionadas.includes(l.id)),
    [linhas, selecionadas],
  );

  /**
   * POST em `/hrm/change-status` — endpoint LEGADO que devolve JSON puro (a blade
   * ainda o consome). `router.post` do Inertia esperaria resposta Inertia, então o
   * caminho é fetch + `router.reload`, o mesmo padrão de Pages/Cliente/Index.tsx.
   * O 422 do limite por tipo (LeaveBalanceService) traz `msg` com o saldo — mostrar
   * essa mensagem é o ponto: "não coube" precisa dizer quanto falta.
   */
  const trocarSituacao = async () => {
    if (!confirmando || enviando) return;
    setEnviando(true);
    let ok = 0;
    let ultimaFalha = '';
    for (const licenca of confirmando.alvo) {
      try {
        const r = await fetch('/hrm/change-status', {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({
            leave_id: licenca.id,
            status: confirmando.status,
            status_note: nota,
          }),
        });
        const json = await r.json().catch(() => ({ success: false }));
        if (r.ok && json?.success) ok += 1;
        else ultimaFalha = json?.msg || 'Não foi possível trocar a situação.';
      } catch {
        ultimaFalha = 'Falha de rede. Verifique a conexão e tente de novo.';
      }
    }
    setEnviando(false);
    if (ok > 0) {
      toast.success(
        ok === 1
          ? 'Situação atualizada. O colaborador foi notificado.'
          : `${ok} licenças atualizadas. Os colaboradores foram notificados.`,
      );
      setSelecionadas([]);
      setAberta(null);
      router.reload({ only: PROPS_DA_LISTA });
    }
    if (ultimaFalha) toast.error(ultimaFalha);
    setConfirmando(null);
    setNota('');
  };

  const badge = (l: Licenca) => (
    <Badge variant={variantePorStatus[l.status]}>{l.status_label}</Badge>
  );

  // Estado da linha, do alvo (§3 do export): urgente = pendente com início já
  // vencido (é o que pede decisão hoje); arquivada = cancelada.
  const classeDaLinha = (l: Licenca) => {
    if (l.status === 'pending' && l.start_date <= hoje) {
      return 'border-l-2 border-l-amber-500 bg-amber-500/5 hover:bg-amber-500/10';
    }
    if (l.status === 'cancelled') return 'opacity-60 hover:bg-accent/30';
    return 'hover:bg-accent/30';
  };

  return (
    <>
      <div data-contract="cabecalho">
        <PageHeader
          title="Licenças"
          subtitle={<>Pedidos de licença do negócio · <strong>{total}</strong> no filtro atual</>}
          actions={
            <Button onClick={() => setPedindo(true)}>
              <Plus size={14} className="mr-1.5" /> Pedir licença
            </Button>
          }
          below={
            <nav data-contract="abas" className="flex items-center gap-1" aria-label="Seções de licenças">
              <button
                type="button"
                role="tab"
                aria-selected={aba === 'licencas'}
                onClick={() => setAba('licencas')}
                className={`rounded-md px-3 py-1.5 text-sm ${aba === 'licencas' ? 'bg-accent font-medium' : 'text-muted-foreground hover:bg-accent/50'}`}
              >
                Licenças
              </button>
              <button
                type="button"
                role="tab"
                aria-selected={aba === 'saldo'}
                onClick={() => setAba('saldo')}
                className={`rounded-md px-3 py-1.5 text-sm ${aba === 'saldo' ? 'bg-accent font-medium' : 'text-muted-foreground hover:bg-accent/50'}`}
              >
                Saldo por tipo
              </button>
              {/* A 3ª aba é LINK, não painel: o cadastro de tipos tem controller e tela
                  próprios (/hrm/leave-type). Fingir uma aba que navega para fora seria
                  mentir sobre a estrutura. */}
              <a
                href="/hrm/leave-type"
                className="rounded-md px-3 py-1.5 text-sm text-muted-foreground hover:bg-accent/50"
              >
                Tipos de licença
              </a>
            </nav>
          }
        />
      </div>

      <div className="mx-auto max-w-7xl space-y-4 p-6">
        {/* ── KPIs ── */}
        <Deferred data="kpis" fallback={<Skeleton className="h-20 w-full" />}>
          <div data-contract="kpis" className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <Card><CardContent className="pt-4">
              <p className="text-xs text-muted-foreground">Pendentes</p>
              <p className="text-2xl font-semibold tabular-nums">{kpis?.pendentes ?? 0}</p>
            </CardContent></Card>
            <Card><CardContent className="pt-4">
              <p className="text-xs text-muted-foreground">Aprovadas</p>
              <p className="text-2xl font-semibold tabular-nums">{kpis?.aprovadas ?? 0}</p>
            </CardContent></Card>
            <Card><CardContent className="pt-4">
              <p className="text-xs text-muted-foreground">Tipos cadastrados</p>
              <p className="text-2xl font-semibold tabular-nums">{kpis?.tipos ?? 0}</p>
            </CardContent></Card>
          </div>
        </Deferred>

        {aba === 'saldo' ? (
          <SaldoPorTipo saldos={saldos} />
        ) : (
          <>
            {/* ── toolbar ── */}
            <Card>
              <CardContent className="flex flex-wrap items-center gap-2 py-3" data-contract="toolbar">
                <div className="relative min-w-56 flex-1">
                  <Search size={14} aria-hidden="true" className="absolute left-2.5 top-1/2 -translate-y-1/2 text-muted-foreground" />
                  <Input
                    ref={campoBusca}
                    value={busca}
                    onChange={(e) => setBusca(e.target.value)}
                    placeholder="Buscar por referência, colaborador, tipo ou motivo"
                    aria-label="Buscar licenças"
                    className="pl-8"
                  />
                </div>

                <Select
                  value={filtros.status ?? TODAS}
                  onValueChange={(v) => aplicar('status', v === TODAS ? null : v)}
                >
                  <SelectTrigger className="w-44" aria-label="Filtrar por situação">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SafeSelectItem value={TODAS}>Todas as situações</SafeSelectItem>
                    {situacoes.map((s) => (
                      <SafeSelectItem key={s.valor} value={s.valor}>{s.label}</SafeSelectItem>
                    ))}
                  </SelectContent>
                </Select>

                <Select
                  value={filtros.leave_type ? String(filtros.leave_type) : TODAS}
                  onValueChange={(v) => aplicar('leave_type', v === TODAS ? null : Number(v))}
                >
                  <SelectTrigger className="w-44" aria-label="Filtrar por tipo">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SafeSelectItem value={TODAS}>Todos os tipos</SafeSelectItem>
                    {listaTipos.map((t) => (
                      <SafeSelectItem key={t.id} value={String(t.id)}>{t.label}</SafeSelectItem>
                    ))}
                  </SelectContent>
                </Select>

                {/* R3: o seletor de colaborador só existe pra quem vê todos. Esconder é
                    conveniência — quem recorta de verdade é o controller. */}
                {permissoes.ver_todos && listaPessoas.length > 0 && (
                  <Select
                    value={filtros.user_id ? String(filtros.user_id) : TODAS}
                    onValueChange={(v) => aplicar('user_id', v === TODAS ? null : Number(v))}
                  >
                    <SelectTrigger className="w-48" aria-label="Filtrar por colaborador">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SafeSelectItem value={TODAS}>Todos os colaboradores</SafeSelectItem>
                      {listaPessoas.map((p) => (
                        <SafeSelectItem key={p.id} value={String(p.id)}>{p.label}</SafeSelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                )}

                <span className="text-sm tabular-nums text-muted-foreground">
                  {linhas.length} de {total}
                </span>

                <span className="ml-auto hidden items-center gap-2 text-xs text-muted-foreground lg:flex">
                  <kbd className="rounded border border-border px-1.5 py-0.5">/</kbd> buscar
                  <kbd className="rounded border border-border px-1.5 py-0.5">n</kbd> novo
                </span>
              </CardContent>
            </Card>

            {/* ── seleção em lote ── */}
            {selecionadas.length > 0 && permissoes.aprovar && (
              <div
                data-contract="lote"
                role="status"
                className="flex flex-wrap items-center gap-2 rounded-md border border-border bg-accent/40 px-3 py-2 text-sm"
              >
                <span className="tabular-nums">{selecionadas.length} selecionadas</span>
                <Button
                  size="sm"
                  variant="outline"
                  className="ml-auto"
                  onClick={() => setConfirmando({ alvo: pendentesSelecionadas, status: 'approved' })}
                >
                  Aprovar
                </Button>
                <Button
                  size="sm"
                  variant="outline"
                  onClick={() => setConfirmando({ alvo: pendentesSelecionadas, status: 'cancelled' })}
                >
                  Cancelar licenças
                </Button>
                <Button size="sm" variant="ghost" onClick={() => setSelecionadas([])}>Limpar seleção</Button>
              </div>
            )}

            {/* ── lista ── */}
            <Deferred data="licencas" fallback={<Skeleton className="h-96 w-full" />}>
              <Card>
                <CardContent className="p-0">
                  {linhas.length === 0 ? (
                    <div data-contract="vazio" className="p-12 text-center">
                      <CalendarDays size={32} aria-hidden="true" className="mx-auto mb-2 opacity-50" />
                      <p className="text-sm font-medium">
                        {temFiltro ? 'Nenhuma licença para este filtro.' : 'Nenhuma licença registrada ainda.'}
                      </p>
                      <p className="mt-1 text-sm text-muted-foreground">
                        {temFiltro
                          ? 'A busca e os filtros acima estão restringindo a lista.'
                          : 'Quando alguém pedir licença, o pedido aparece aqui para aprovação.'}
                      </p>
                      {temFiltro ? (
                        <Button variant="outline" size="sm" className="mt-4" onClick={limpar}>
                          Limpar busca e filtros
                        </Button>
                      ) : (
                        <Button size="sm" className="mt-4" onClick={() => setPedindo(true)}>
                          <Plus size={14} className="mr-1.5" /> Pedir licença
                        </Button>
                      )}
                    </div>
                  ) : (
                    <div className="overflow-x-auto" data-contract="tabela">
                      <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/30 text-xs text-muted-foreground">
                          <tr>
                            <th scope="col" className="w-10 p-3">
                              <span className="sr-only">Selecionar</span>
                            </th>
                            <th scope="col" className="p-3 text-left font-medium">Ref.</th>
                            <th scope="col" className="p-3 text-left font-medium">Tipo</th>
                            <th scope="col" className="p-3 text-left font-medium">Colaborador</th>
                            <th scope="col" className="p-3 text-left font-medium">Período</th>
                            <th scope="col" className="p-3 text-left font-medium">Motivo</th>
                            <th scope="col" className="p-3 text-left font-medium">Situação</th>
                            <th scope="col" className="p-3 text-right font-medium">Ação</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-border" data-contract="situacoes">
                          {linhas.map((l) => (
                            <tr
                              key={l.id}
                              role="button"
                              tabIndex={0}
                              onClick={() => setAberta(l)}
                              onKeyDown={(e) => {
                                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setAberta(l); }
                              }}
                              className={`cursor-pointer ${classeDaLinha(l)}`}
                            >
                              {/* stopPropagation: clique no filho NÃO abre o drawer. */}
                              <td className="p-3" onClick={(e) => e.stopPropagation()}>
                                <Checkbox
                                  checked={selecionadas.includes(l.id)}
                                  onCheckedChange={() => alternar(l.id)}
                                  aria-label={`Selecionar licença ${l.ref_no ?? l.id}`}
                                />
                              </td>
                              <td className="p-3 font-mono text-xs">{l.ref_no ?? '—'}</td>
                              <td className="p-3">{l.tipo ?? '—'}</td>
                              <td className="p-3">
                                <span className="font-medium">{l.colaborador || '—'}</span>
                              </td>
                              <td className="p-3">
                                <span className="block">{l.periodo_label}</span>
                                <span className="block text-xs text-muted-foreground tabular-nums">
                                  {l.dias} {l.dias === 1 ? 'dia' : 'dias'}
                                </span>
                              </td>
                              <td className="max-w-64 p-3 text-muted-foreground">
                                <span className="block overflow-hidden text-ellipsis whitespace-nowrap">
                                  {l.motivo ?? '—'}
                                </span>
                              </td>
                              <td className="p-3">{badge(l)}</td>
                              <td className="p-3 text-right" data-contract="acoes-linha" onClick={(e) => e.stopPropagation()}>
                                {permissoes.aprovar && l.status === 'pending' ? (
                                  <span className="inline-flex gap-1">
                                    <Button
                                      size="sm"
                                      variant="outline"
                                      onClick={() => setConfirmando({ alvo: [l], status: 'approved' })}
                                    >
                                      <Check size={13} className="mr-1" /> Aprovar
                                    </Button>
                                    <Button
                                      size="sm"
                                      variant="ghost"
                                      onClick={() => setConfirmando({ alvo: [l], status: 'cancelled' })}
                                    >
                                      <X size={13} className="mr-1" /> Cancelar
                                    </Button>
                                  </span>
                                ) : (
                                  <span className="text-xs text-muted-foreground">—</span>
                                )}
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  )}
                </CardContent>
              </Card>
            </Deferred>

            {licencas && licencas.last_page > 1 && (
              <nav className="flex flex-wrap gap-1" aria-label="Paginação">
                {licencas.links.map((link, i) => (
                  <Button
                    key={i}
                    size="sm"
                    variant={link.active ? 'default' : 'outline'}
                    disabled={!link.url}
                    onClick={() => link.url && router.visit(link.url, { preserveScroll: true, only: PROPS_DA_LISTA })}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                  />
                ))}
              </nav>
            )}
          </>
        )}
      </div>

      <DrawerLicenca
        licenca={aberta}
        saldos={saldos}
        linhas={linhas}
        onClose={() => setAberta(null)}
      />

      <PedirLicenca
        aberto={pedindo}
        onClose={() => setPedindo(false)}
        tipos={listaTipos}
        pessoas={permissoes.criar_para_terceiros ? listaPessoas : []}
        dateFormat={date_format}
      />

      <Dialog open={!!confirmando} onOpenChange={(o) => { if (!o) { setConfirmando(null); setNota(''); } }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>
              {confirmando?.status === 'approved' ? 'Aprovar licenças' : 'Cancelar licenças'}
            </DialogTitle>
            <DialogDescription>
              {confirmando
                ? `${confirmando.alvo.length} ${confirmando.alvo.length === 1 ? 'licença' : 'licenças'} — cada colaborador é notificado por e-mail.`
                : ''}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-2">
            <Label htmlFor="nota-situacao">Observação (opcional)</Label>
            <Textarea
              id="nota-situacao"
              value={nota}
              onChange={(e) => setNota(e.target.value)}
              placeholder="Vai junto com a notificação ao colaborador."
            />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => { setConfirmando(null); setNota(''); }}>Voltar</Button>
            <Button onClick={trocarSituacao} disabled={enviando}>
              {enviando && <Loader2 size={14} className="mr-1.5 animate-spin" />}
              {confirmando?.status === 'approved' ? 'Aprovar' : 'Cancelar licenças'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

// ── aba "Saldo por tipo" ────────────────────────────────────────────────────
function SaldoPorTipo({ saldos }: { saldos?: Saldo[] }) {
  const linhas = saldos ?? [];
  return (
    <Deferred data="saldos" fallback={<Skeleton className="h-64 w-full" />}>
      <Card>
        <CardContent className="p-0">
          {linhas.length === 0 ? (
            <div className="p-12 text-center text-sm text-muted-foreground">
              <Tags size={32} aria-hidden="true" className="mx-auto mb-2 opacity-50" />
              Nenhum tipo de licença cadastrado.
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="border-b border-border bg-muted/30 text-xs text-muted-foreground">
                  <tr>
                    <th scope="col" className="p-3 text-left font-medium">Tipo</th>
                    <th scope="col" className="p-3 text-left font-medium">Limite</th>
                    <th scope="col" className="p-3 text-left font-medium">Aprovado</th>
                    <th scope="col" className="p-3 text-left font-medium">Em análise</th>
                    <th scope="col" className="p-3 text-left font-medium">Consumo</th>
                    <th scope="col" className="p-3 text-left font-medium">Risco</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-border">
                  {linhas.map((s) => (
                    <tr key={s.id} className="hover:bg-accent/30">
                      <td className="p-3 font-medium">{s.tipo}</td>
                      {/* R8/UC-HRM-19: limite 0 é "sem limite", não "zero dias". */}
                      <td className="p-3 tabular-nums">
                        {s.limite === null
                          ? <span className="text-muted-foreground">sem limite</span>
                          : `${s.limite} por ${s.intervalo === 'month' ? 'mês' : 'ano'}`}
                      </td>
                      <td className="p-3 tabular-nums">{s.aprovado}</td>
                      <td className="p-3 tabular-nums">{s.em_analise}</td>
                      <td className="p-3 tabular-nums">{s.consumo === null ? '—' : `${s.consumo}%`}</td>
                      <td className="p-3">
                        {s.risco
                          ? <Badge variant="destructive">Estoura o limite</Badge>
                          : <span className="text-xs text-muted-foreground">—</span>}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </CardContent>
      </Card>
    </Deferred>
  );
}

// ── drawer da licença (PT-02 dentro do PT-01) ───────────────────────────────
function DrawerLicenca({
  licenca, saldos, linhas, onClose,
}: { licenca: Licenca | null; saldos?: Saldo[]; linhas: Licenca[]; onClose: () => void }) {
  const saldo = saldos?.find((s) => s.tipo === licenca?.tipo);

  // Conflitos = outras licenças do MESMO colaborador cujo período se sobrepõe.
  // Só olha a página carregada, e o texto diz isso — afirmar "nenhum conflito"
  // varrendo 25 de N seria mentir sobre a cobertura.
  const conflitos = licenca
    ? linhas.filter((l) =>
        l.id !== licenca.id
        && l.user_id === licenca.user_id
        && l.status !== 'cancelled'
        && l.start_date <= licenca.end_date
        && l.end_date >= licenca.start_date)
    : [];

  return (
    <Sheet open={!!licenca} onOpenChange={(o) => { if (!o) onClose(); }}>
      <SheetContent className="w-full overflow-y-auto sm:max-w-lg" data-contract="drawer">
        {licenca && (
          <>
            <SheetHeader>
              <SheetTitle className="flex items-center gap-2">
                <span className="font-mono text-sm">{licenca.ref_no ?? `#${licenca.id}`}</span>
                <Badge variant={variantePorStatus[licenca.status]}>{licenca.status_label}</Badge>
              </SheetTitle>
              <SheetDescription>
                {licenca.colaborador} · {licenca.tipo ?? 'sem tipo'}
              </SheetDescription>
            </SheetHeader>

            <dl className="mt-6 space-y-4 text-sm">
              <div>
                <dt className="text-xs text-muted-foreground">Período</dt>
                <dd className="tabular-nums">
                  {licenca.periodo_label} · {licenca.dias} {licenca.dias === 1 ? 'dia' : 'dias'}
                </dd>
              </div>
              <div>
                <dt className="text-xs text-muted-foreground">Motivo</dt>
                <dd className="whitespace-pre-wrap">{licenca.motivo ?? '—'}</dd>
              </div>

              <div>
                <dt className="mb-1 text-xs text-muted-foreground">Saldo do tipo</dt>
                <dd>
                  {saldo
                    ? (saldo.limite === null
                        ? 'Tipo sem limite configurado.'
                        : `${saldo.aprovado} aprovados + ${saldo.em_analise} em análise de ${saldo.limite} por ${saldo.intervalo === 'month' ? 'mês' : 'ano'}.`)
                    : <span className="text-muted-foreground">—</span>}
                </dd>
              </div>

              <div>
                <dt className="mb-1 text-xs text-muted-foreground">Conflitos no período</dt>
                <dd>
                  {conflitos.length === 0 ? (
                    <span className="text-muted-foreground">
                      Nenhum conflito entre as licenças desta página.
                    </span>
                  ) : (
                    <ul className="space-y-1">
                      {conflitos.map((c) => (
                        <li key={c.id} className="flex items-center gap-2">
                          <AlertCircle size={13} aria-hidden="true" className="text-amber-500" />
                          <span>{c.ref_no ?? `#${c.id}`} · {c.periodo_label} · {c.status_label}</span>
                        </li>
                      ))}
                    </ul>
                  )}
                </dd>
              </div>

              <div>
                <dt className="mb-1 text-xs text-muted-foreground">Histórico</dt>
                <dd>
                  {licenca.status_note
                    ? <p className="whitespace-pre-wrap">{licenca.status_note}</p>
                    : <span className="text-muted-foreground">Sem observação registrada.</span>}
                  <a
                    className="mt-2 inline-block text-xs underline"
                    href={`/hrm/leave/activity/${licenca.id}`}
                    target="_blank"
                    rel="noreferrer"
                  >
                    Ver registro de alterações
                  </a>
                </dd>
              </div>
            </dl>

            {/* R9: licença criada NÃO se edita — o `update()` do servidor é vazio.
                Por isso o drawer não oferece edição, só leitura + troca de situação
                pela linha da lista. */}
          </>
        )}
      </SheetContent>
    </Sheet>
  );
}

// ── formulário "Pedir licença" ──────────────────────────────────────────────
function PedirLicenca({
  aberto, onClose, tipos, pessoas, dateFormat,
}: {
  aberto: boolean;
  onClose: () => void;
  tipos: TipoOption[];
  pessoas: PessoaOption[];
  dateFormat: string;
}) {
  const hojeIso = new Date().toISOString().slice(0, 10);
  const [tipo, setTipo] = useState('');
  const [inicio, setInicio] = useState(hojeIso);
  const [fim, setFim] = useState(hojeIso);
  const [motivo, setMotivo] = useState('');
  const [para, setPara] = useState('');
  const [erro, setErro] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);

  const enviar = async () => {
    if (enviando) return;
    setEnviando(true);
    setErro(null);
    try {
      const corpo: Record<string, unknown> = {
        essentials_leave_type_id: tipo ? Number(tipo) : null,
        start_date: paraFormatoDoNegocio(inicio, dateFormat),
        end_date: paraFormatoDoNegocio(fim, dateFormat),
        reason: motivo,
      };
      if (para) corpo.employees = [Number(para)];

      const r = await fetch(ROTA, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf(),
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(corpo),
      });
      const json = await r.json().catch(() => ({ success: false }));

      // 422 vem do StoreLeaveRequest (formato/tenant) ou do LeaveBalanceService
      // (limite do tipo). A mensagem do saldo diz quanto falta — mostrá-la é o ponto.
      if (!r.ok || !json?.success) {
        const deCampo = json?.errors
          ? (Object.values(json.errors)[0] as string[] | undefined)?.[0]
          : undefined;
        setErro(deCampo || json?.msg || 'Não foi possível registrar o pedido.');
        return;
      }
      toast.success('Pedido registrado. Os administradores foram notificados.');
      setTipo(''); setMotivo(''); setPara('');
      onClose();
      router.reload({ only: PROPS_DA_LISTA });
    } catch {
      setErro('Falha de rede. Verifique a conexão e tente de novo.');
    } finally {
      setEnviando(false);
    }
  };

  return (
    <Dialog open={aberto} onOpenChange={(o) => { if (!o) { setErro(null); onClose(); } }}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Pedir licença</DialogTitle>
          <DialogDescription>
            A referência é gerada pelo servidor com o prefixo das configurações do negócio.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-3">
          {erro && (
            <p role="alert" className="rounded-md border border-destructive/40 bg-destructive/10 px-3 py-2 text-sm">
              {erro}
            </p>
          )}

          <div className="space-y-1">
            <Label htmlFor="pl-tipo">Tipo de licença</Label>
            <Select value={tipo} onValueChange={setTipo}>
              <SelectTrigger id="pl-tipo"><SelectValue placeholder="Escolha o tipo" /></SelectTrigger>
              <SelectContent>
                {tipos.map((t) => (
                  <SafeSelectItem key={t.id} value={String(t.id)}>{t.label}</SafeSelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {pessoas.length > 0 && (
            <div className="space-y-1">
              <Label htmlFor="pl-para">Colaborador</Label>
              <Select value={para} onValueChange={setPara}>
                <SelectTrigger id="pl-para"><SelectValue placeholder="Para mim" /></SelectTrigger>
                <SelectContent>
                  {pessoas.map((p) => (
                    <SafeSelectItem key={p.id} value={String(p.id)}>{p.label}</SafeSelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
          )}

          <div className="grid grid-cols-2 gap-3">
            <div className="space-y-1">
              <Label htmlFor="pl-inicio">Início</Label>
              <Input id="pl-inicio" type="date" value={inicio} onChange={(e) => setInicio(e.target.value)} />
            </div>
            <div className="space-y-1">
              <Label htmlFor="pl-fim">Fim</Label>
              <Input id="pl-fim" type="date" value={fim} onChange={(e) => setFim(e.target.value)} />
            </div>
          </div>

          <div className="space-y-1">
            <Label htmlFor="pl-motivo">Motivo</Label>
            <Textarea id="pl-motivo" value={motivo} onChange={(e) => setMotivo(e.target.value)} />
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={onClose}>Voltar</Button>
          <Button onClick={enviar} disabled={enviando}>
            {enviando && <Loader2 size={14} className="mr-1.5 animate-spin" />}
            Pedir licença
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

LicencasIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Licenças" breadcrumbItems={[{ label: 'HRM' }, { label: 'Licenças' }]}>
    {page}
  </AppShellV2>
);
