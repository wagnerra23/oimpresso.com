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
// O drawer de detalhe (PT-02) NÃO está aqui: entra na SA-O2b. Clicar na linha ainda não faz
// nada — e é melhor não fazer nada do que abrir um drawer vazio.

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

interface Props {
  filtros: Filtros;
  pacotes?: PacoteOpcao[];
  negocios?: Pagina;
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
function tomDaAssinatura(rotulo: string): 'default' | 'secondary' | 'destructive' | 'outline' {
  if (rotulo === 'Ativa') return 'default';
  if (rotulo === 'Vencida' || rotulo === 'Bloqueada') return 'destructive';
  if (rotulo === 'Pendente') return 'secondary';
  return 'outline';
}

const plural = (n: number, sing: string, plur: string) => `${n} ${n === 1 ? sing : plur}`;

function Select({
  valor,
  opcoes,
  onChange,
  rotulo,
}: {
  valor: string;
  opcoes: { v: string; label: string }[];
  onChange: (v: string) => void;
  rotulo: string;
}) {
  return (
    <select
      aria-label={rotulo}
      value={valor}
      onChange={(e) => onChange(e.target.value)}
      className="h-9 rounded-md border bg-background px-3 text-xs text-foreground"
    >
      {opcoes.map((o) => (
        <option key={o.v} value={o.v}>
          {o.label}
        </option>
      ))}
    </select>
  );
}

function NegociosIndex({ filtros, pacotes, negocios }: Props) {
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

      <div className="flex flex-wrap items-center gap-2 px-6 pt-4">
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
          <Tabela negocios={negocios} busca={filtros.q ?? ''} temFiltro={temFiltro} irPara={irPara} />
        </Deferred>
      </div>
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
}: {
  negocios?: Pagina;
  busca: string;
  temFiltro: boolean;
  irPara: (m: Record<string, string | number | undefined>) => void;
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
          <table className="w-full text-sm">
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
                <tr key={n.id} className="border-b last:border-0">
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

        <div className="flex flex-wrap items-center justify-between gap-3 border-t px-4 py-3">
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

NegociosIndex.layout = (page: ReactNode) => <AppShellV2>{page}</AppShellV2>;

export default NegociosIndex;
