// @memcofre
//   tela: /superadmin/business
//   module: Superadmin
//   stories: SA-O2 (Blade/AdminLTE + DataTables → Inertia)
//   permissao: superadmin
//
// Lista de negócios da plataforma. Responde: "quem é este cliente e o que ele tem contratado?".
// Charter: ./Index.charter.md · Casos: ./Index.casos.md
// Âncora de design: prototipo-ui/cowork/superadmin-page.jsx → ViewNegocios() (L860+)
// RUNBOOK: memory/requisitos/Superadmin/RUNBOOK-negocios.md
//
// Paginação é SERVER-SIDE (o backend devolve uma página por vez). O protótipo pagina no
// cliente porque trabalha com mock de 12 linhas; em produção são centenas de negócios, e
// trazer tudo pro browser seria a mesma dívida que o DataTables tinha.
//
// O drawer de detalhe (PT-02) entrou na SA-O2b: é um ESTADO da lista (`?negocio=<id>` via
// partial reload), não outra tela. Duas seções do F1 ficaram DE FORA por falta de vínculo no
// dado, não por esquecimento — o `detalheDoNegocio()` do controller explica cada uma.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Skeleton } from '@/Components/ui/skeleton';
import PageHeader from '@/Components/shared/PageHeader';
import EmptyState from '@/Components/shared/EmptyState';
import { Select, plural, tomDaAssinatura } from '../_components/assinatura';

interface Filtros {
  q: string;
  pacote: string | null;
  assinatura: string | null;
  status: string | null;
  venda: string | null;
}

interface PacoteOpcao {
  id: number;
  nome: string;
}

interface NegocioLinha {
  id: number;
  nome: string;
  dono: string | null;
  email: string | null;
  cidade: string | null;
  pacote: string | null;
  ativo: boolean;
  assinatura: string;
  criado: string | null;
}

interface Pagina {
  linhas: NegocioLinha[];
  total: number;
  pagina: number;
  paginas: number;
  por_pagina: number;
}

interface HistoricoItem {
  id: number;
  pacote: string | null;
  inicio: string | null;
  fim: string | null;
  situacao: string;
}

interface UsoItem {
  rotulo: string;
  usado: number;
  /** null = sem pacote vigente · 0 = ILIMITADO (convenção do UltimatePOS, confirmada por [W]) */
  teto: number | null;
}

interface Detalhe {
  id: number;
  nome: string;
  cidade: string | null;
  ativo: boolean;
  criado: string | null;
  dono: string | null;
  email: string | null;
  fone_dono: string | null;
  fone_negocio: string | null;
  ultima_venda: string | null;
  uso: UsoItem[];
  historico: HistoricoItem[];
}

interface Props {
  filtros: Filtros;
  aberto?: number | null;
  pacotes?: PacoteOpcao[];
  negocios?: Pagina;
  detalhe?: Detalhe | null;
}

const ROTA = '/superadmin/business';

const ASSINATURAS = [
  { v: '', label: 'Assinatura: todas' },
  { v: 'vigente', label: 'Vigente' },
  { v: 'vencida', label: 'Vencida' },
  { v: 'sem', label: 'Sem assinatura' },
];

const STATUS = [
  { v: '', label: 'Status: todos' },
  { v: 'ativo', label: 'Ativo' },
  { v: 'inativo', label: 'Inativo' },
];

const VENDAS = [
  { v: '', label: 'Última venda: qualquer' },
  { v: 'today', label: 'Vendeu hoje' },
  { v: 'yesterday', label: 'Desde ontem' },
  { v: 'this_week', label: 'Últimos 7 dias' },
  { v: 'this_month', label: 'Este mês' },
  { v: 'this_year', label: 'Este ano' },
];

/** Tom do badge por rótulo já traduzido — a tela nunca vê o enum cru. */

function NegociosIndex({ filtros, aberto, pacotes, negocios, detalhe }: Props) {
  const [q, setQ] = useState(filtros.q ?? '');
  const buscaRef = useRef<HTMLInputElement>(null);
  const primeiraRodada = useRef(true);

  // `/` foca a busca — atalho que o F1 pede (UC-SA-004).
  useEffect(() => {
    const h = (e: KeyboardEvent) => {
      const alvo = (e.target as HTMLElement)?.tagName;
      if (alvo === 'INPUT' || alvo === 'TEXTAREA' || e.metaKey || e.ctrlKey) return;
      if (e.key === '/') {
        e.preventDefault();
        buscaRef.current?.focus();
      }
    };
    document.addEventListener('keydown', h);
    return () => document.removeEventListener('keydown', h);
  }, []);

  // Busca com debounce 300ms — mesmo padrão do Usuario360 do módulo.
  useEffect(() => {
    if (primeiraRodada.current) {
      primeiraRodada.current = false;
      return;
    }
    const t = setTimeout(() => {
      if (q.trim() === (filtros.q ?? '').trim()) return;
      irPara({ q: q.trim(), page: undefined });
    }, 300);
    return () => clearTimeout(t);
  }, [q, filtros.q]);

  // O drawer é um ESTADO da lista: `?negocio=<id>` no partial reload, sem rota nova.
  const abrir = (id: number) => {
    router.get(
      ROTA,
      { ...filtrosAtuais(), negocio: id },
      { only: ['detalhe', 'aberto'], preserveState: true, preserveScroll: true, replace: true },
    );
  };

  const fechar = () => {
    router.get(
      ROTA,
      filtrosAtuais(),
      { only: ['detalhe', 'aberto'], preserveState: true, preserveScroll: true, replace: true },
    );
  };

  // `esc` fecha — o F1 pede (UC-SA-005). Só escuta quando há drawer aberto.
  useEffect(() => {
    if (!aberto) return;
    const h = (e: KeyboardEvent) => {
      if (e.key === 'Escape') fechar();
    };
    document.addEventListener('keydown', h);
    return () => document.removeEventListener('keydown', h);
  }, [aberto]);

  const filtrosAtuais = () => ({
    q: filtros.q || undefined,
    pacote: filtros.pacote || undefined,
    assinatura: filtros.assinatura || undefined,
    status: filtros.status || undefined,
    venda: filtros.venda || undefined,
  });

  /** Toda navegação preserva os demais filtros — trocar um não zera os outros. */
  const irPara = (mudanca: Record<string, string | number | undefined>) => {
    const base: Record<string, string | number | undefined> = {
      q: filtros.q || undefined,
      pacote: filtros.pacote || undefined,
      assinatura: filtros.assinatura || undefined,
      status: filtros.status || undefined,
      venda: filtros.venda || undefined,
    };
    router.get(
      ROTA,
      { ...base, ...mudanca },
      { only: ['negocios', 'filtros'], preserveState: true, preserveScroll: true, replace: true },
    );
  };

  const temFiltro =
    (filtros.q ?? '') !== '' || !!filtros.pacote || !!filtros.assinatura || !!filtros.status || !!filtros.venda;

  return (
    <div className="pb-8">
      <PageHeader title="Negócios" subtitle="Todos os clientes da plataforma" />

      <div className="flex flex-wrap items-center gap-2 px-6 pt-4" data-contract="superadmin.negocios.busca-filtros">
        <Input
          ref={buscaRef}
          value={q}
          onChange={(e) => setQ(e.target.value)}
          placeholder="Buscar por nome, dono, e-mail ou número…"
          className="h-9 w-full max-w-sm text-xs"
        />

        <Deferred data="pacotes" fallback={<Skeleton className="h-9 w-40" />}>
          <FiltroPacote pacotes={pacotes} valor={filtros.pacote ?? ''} onChange={(v) => irPara({ pacote: v || undefined, page: undefined })} />
        </Deferred>

        <Select rotulo="Assinatura" valor={filtros.assinatura ?? ''} opcoes={ASSINATURAS} onChange={(v) => irPara({ assinatura: v || undefined, page: undefined })} />
        <Select rotulo="Status do negócio" valor={filtros.status ?? ''} opcoes={STATUS} onChange={(v) => irPara({ status: v || undefined, page: undefined })} />
        <Select rotulo="Última venda" valor={filtros.venda ?? ''} opcoes={VENDAS} onChange={(v) => irPara({ venda: v || undefined, page: undefined })} />

        {temFiltro && (
          <Button
            variant="ghost"
            size="sm"
            className="h-9 text-xs"
            onClick={() => {
              setQ('');
              router.get(ROTA, {}, { only: ['negocios', 'filtros'], preserveState: true, preserveScroll: true, replace: true });
            }}
          >
            Limpar
          </Button>
        )}
      </div>

      <div className="px-6 pt-4">
        <Deferred data="negocios" fallback={<Card><CardContent className="p-4"><Skeleton className="h-64 w-full" /></CardContent></Card>}>
          <Tabela negocios={negocios} busca={filtros.q ?? ''} temFiltro={temFiltro} irPara={irPara} onAbrir={abrir} />
        </Deferred>
      </div>

      {aberto ? (
        <Deferred data="detalhe" fallback={<DrawerEsqueleto onFechar={fechar} />}>
          <Drawer detalhe={detalhe} onFechar={fechar} />
        </Deferred>
      ) : null}
    </div>
  );
}

/* O <Deferred> segura o filho até a prop chegar; ele NÃO injeta — cada bloco recebe o valor. */

function FiltroPacote({
  pacotes,
  valor,
  onChange,
}: {
  pacotes?: PacoteOpcao[];
  valor: string;
  onChange: (v: string) => void;
}) {
  const opcoes = [{ v: '', label: 'Pacote: todos' }, ...(pacotes ?? []).map((p) => ({ v: String(p.id), label: p.nome }))];

  return <Select rotulo="Pacote" valor={valor} opcoes={opcoes} onChange={onChange} />;
}

function Tabela({
  negocios,
  busca,
  temFiltro,
  irPara,
  onAbrir,
}: {
  negocios?: Pagina;
  busca: string;
  temFiltro: boolean;
  irPara: (m: Record<string, string | number | undefined>) => void;
  onAbrir: (id: number) => void;
}) {
  const p = negocios;
  const linhas = p?.linhas ?? [];

  if (linhas.length === 0) {
    return (
      <Card>
        <CardContent className="p-0">
          <EmptyState
            title={temFiltro ? 'Nenhum negócio com esses filtros' : 'Nenhum negócio cadastrado'}
            description={
              busca
                ? `A busca por "${busca}" não retornou resultado. Tente outro termo ou limpe os filtros.`
                : temFiltro
                  ? 'Ajuste ou limpe os filtros para ver a lista completa.'
                  : 'Quando um negócio for cadastrado, ele aparece aqui.'
            }
          />
        </CardContent>
      </Card>
    );
  }

  const inicio = (p!.pagina - 1) * p!.por_pagina + 1;
  const fim = Math.min(p!.pagina * p!.por_pagina, p!.total);

  return (
    <Card>
      <CardContent className="p-0">
        <div className="overflow-x-auto">
          <table className="w-full text-sm" data-contract="superadmin.negocios.tabela">
            <thead>
              <tr className="border-b text-left text-xs text-muted-foreground">
                <th className="px-4 py-2 font-medium">Negócio</th>
                <th className="px-4 py-2 font-medium">Dono</th>
                <th className="px-4 py-2 font-medium">Pacote</th>
                <th className="px-4 py-2 font-medium">Assinatura</th>
                <th className="px-4 py-2 font-medium">Status</th>
                <th className="px-4 py-2 font-medium">Cadastro</th>
              </tr>
            </thead>
            <tbody>
              {linhas.map((n) => (
                <tr
                  key={n.id}
                  onClick={() => onAbrir(n.id)}
                  className="cursor-pointer border-b last:border-0 hover:bg-muted/50"
                >
                  <td className="px-4 py-2.5">
                    <div className="flex flex-col">
                      <span className="font-medium">{n.nome}</span>
                      <span className="text-[11px] text-muted-foreground">
                        negócio #{n.id}
                        {n.cidade ? ` · ${n.cidade}` : ''}
                      </span>
                    </div>
                  </td>
                  <td className="px-4 py-2.5">
                    <div className="flex flex-col">
                      <span className="text-muted-foreground">{n.dono ?? '—'}</span>
                      {n.email && <span className="text-[11px] text-muted-foreground/80">{n.email}</span>}
                    </div>
                  </td>
                  <td className="px-4 py-2.5 text-muted-foreground">{n.pacote ?? '—'}</td>
                  <td className="px-4 py-2.5">
                    <Badge variant={tomDaAssinatura(n.assinatura)}>{n.assinatura}</Badge>
                  </td>
                  <td className="px-4 py-2.5 text-muted-foreground">{n.ativo ? 'Ativo' : 'Inativo'}</td>
                  <td className="px-4 py-2.5 tabular-nums text-muted-foreground">{n.criado ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        <div className="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3" data-contract="superadmin.negocios.paginacao">
          <span className="text-[11px] text-muted-foreground">
            {inicio}–{fim} de {plural(p!.total, 'negócio', 'negócios')}
          </span>
          <div className="flex gap-1.5">
            <Button variant="outline" size="sm" className="h-8 text-xs" disabled={p!.pagina <= 1} onClick={() => irPara({ page: p!.pagina - 1 })}>
              Anterior
            </Button>
            <Button variant="outline" size="sm" className="h-8 text-xs" disabled={p!.pagina >= p!.paginas} onClick={() => irPara({ page: p!.pagina + 1 })}>
              Próxima
            </Button>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

function Scrim({ onFechar }: { onFechar: () => void }) {
  return <div className="fixed inset-0 z-40 bg-black/40" onClick={onFechar} aria-hidden="true" />;
}

function Casca({ children, onFechar, titulo }: { children: ReactNode; onFechar: () => void; titulo: string }) {
  return (
    <>
      <Scrim onFechar={onFechar} />
      <aside
        role="dialog"
        aria-modal="true"
        aria-label={titulo}
        className="fixed inset-y-0 right-0 z-50 flex w-[min(460px,92vw)] flex-col border-l bg-background shadow-2xl"
      >
        {children}
      </aside>
    </>
  );
}

function DrawerEsqueleto({ onFechar }: { onFechar: () => void }) {
  return (
    <Casca onFechar={onFechar} titulo="Carregando negócio">
      <div className="flex flex-col gap-3 p-5">
        <Skeleton className="h-5 w-48" />
        <Skeleton className="h-3 w-32" />
        <Skeleton className="mt-4 h-40 w-full" />
        <Skeleton className="h-32 w-full" />
      </div>
    </Casca>
  );
}

function Linha({ rotulo, valor }: { rotulo: string; valor: ReactNode }) {
  return (
    <div className="flex items-center justify-between gap-4">
      <span className="text-xs text-muted-foreground">{rotulo}</span>
      <span className="text-right text-xs font-medium">{valor ?? '—'}</span>
    </div>
  );
}

function Secao({ titulo, children }: { titulo: string; children: ReactNode }) {
  return (
    <section className="border-b px-5 py-4">
      <h3 className="mb-2.5 text-[10.5px] font-semibold uppercase tracking-wider text-muted-foreground">{titulo}</h3>
      {children}
    </section>
  );
}

function Uso({ item }: { item: UsoItem }) {
  const semPacote = item.teto === null;
  const ilimitado = item.teto === 0;
  const pct = !semPacote && !ilimitado ? Math.min((item.usado / (item.teto as number)) * 100, 100) : 0;
  // ≥90% grita, ≥70% avisa — os cortes que o F1 pede (UC-SA-006).
  const tom = pct >= 90 ? 'bg-destructive' : pct >= 70 ? 'bg-amber-500' : 'bg-primary';

  return (
    <div className="flex flex-col gap-1">
      <div className="flex items-baseline justify-between gap-3">
        <span className="text-xs text-muted-foreground">{item.rotulo}</span>
        <span className="text-xs tabular-nums">
          {item.usado}
          {semPacote ? '' : ilimitado ? ' · ilimitado' : ` / ${item.teto}`}
        </span>
      </div>
      {/* Sem teto não há barra: progresso contra ilimitado não informa nada. */}
      {!semPacote && !ilimitado && (
        <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
          <div className={`h-full rounded-full ${tom}`} style={{ width: `${Math.max(pct, 2)}%` }} />
        </div>
      )}
    </div>
  );
}

function Drawer({ detalhe, onFechar }: { detalhe?: Detalhe | null; onFechar: () => void }) {
  if (!detalhe) {
    return (
      <Casca onFechar={onFechar} titulo="Negócio não encontrado">
        <div className="p-5">
          <EmptyState title="Negócio não encontrado" description="Ele pode ter sido removido enquanto a lista estava aberta." />
        </div>
      </Casca>
    );
  }

  const d = detalhe;
  const vigente = d.historico.length > 0 ? d.historico[0] : null;

  return (
    <Casca onFechar={onFechar} titulo={`Negócio ${d.nome}`}>
      <header className="flex items-start justify-between gap-3 border-b px-5 py-4">
        <div className="min-w-0">
          <span className="text-[11px] tabular-nums text-muted-foreground">negócio #{d.id}</span>
          <h2 className="truncate text-base font-semibold">{d.nome}</h2>
          <p className="mt-0.5 text-[11px] text-muted-foreground">
            {d.cidade ? `${d.cidade} · ` : ''}
            {d.criado ? `cadastro ${d.criado}` : 'sem data de cadastro'}
          </p>
        </div>
        <Button variant="outline" size="sm" className="h-8 shrink-0 text-xs" onClick={onFechar} title="Fechar (esc)">
          Fechar
        </Button>
      </header>

      <div className="flex-1 overflow-y-auto" data-contract="superadmin.negocios.drawer">
        <Secao titulo="Assinatura">
          <div className="flex flex-col gap-2">
            <Linha rotulo="Situação" valor={vigente ? <Badge variant={tomDaAssinatura(vigente.situacao)}>{vigente.situacao}</Badge> : 'Sem assinatura'} />
            <Linha rotulo="Pacote" valor={vigente?.pacote} />
            <Linha rotulo="Vigência" valor={vigente?.inicio ? `${vigente.inicio} → ${vigente.fim ?? 'sem fim'}` : null} />
          </div>
          <p className="mt-3 rounded border bg-muted/40 px-3 py-2 text-[11px] leading-relaxed text-muted-foreground">
            O <strong>valor</strong> desta assinatura não aparece aqui: a cobrança recorrente vive
            em <code>rb_subscriptions</code>, ligada ao contato do CRM, e não há vínculo com o
            negócio — casar por nome acerta 4 de 109. Mostrar valor errado é pior que não mostrar.
          </p>
        </Secao>

        <Secao titulo="Uso contra o limite do pacote">
          <div className="flex flex-col gap-3">
            {d.uso.map((u) => (
              <Uso key={u.rotulo} item={u} />
            ))}
          </div>
        </Secao>

        <Secao titulo="Dono e contato">
          <div className="flex flex-col gap-2">
            <Linha rotulo="Dono" valor={d.dono} />
            <Linha rotulo="E-mail" valor={d.email} />
            <Linha rotulo="Celular" valor={d.fone_dono} />
            <Linha rotulo="Telefone do negócio" valor={d.fone_negocio} />
            <Linha rotulo="Acesso" valor={d.ativo ? 'Ativo' : 'Inativo'} />
            <Linha rotulo="Última venda" valor={d.ultima_venda ?? 'nunca vendeu'} />
          </div>
        </Secao>

        <Secao titulo="Histórico de assinaturas">
          {d.historico.length === 0 ? (
            <p className="text-xs text-muted-foreground">Nunca assinou — só cadastro.</p>
          ) : (
            <ul className="flex flex-col gap-2.5">
              {d.historico.map((h) => (
                <li key={h.id} className="flex items-start justify-between gap-3">
                  <div className="flex min-w-0 flex-col">
                    <span className="text-xs font-medium">{h.pacote ?? 'sem pacote'}</span>
                    <span className="text-[11px] text-muted-foreground">
                      {h.inicio ?? '—'} a {h.fim ?? '—'}
                    </span>
                  </div>
                  <Badge variant={tomDaAssinatura(h.situacao)}>{h.situacao}</Badge>
                </li>
              ))}
            </ul>
          )}
        </Secao>
      </div>
    </Casca>
  );
}

NegociosIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default NegociosIndex;
