// @memcofre
//   tela: /fiscal
//   module: Fiscal (cockpit unificado)
//   status: em-implementacao (Wave Cowork "Notas Fiscais" visual)
//   stories: US-FISCAL-002 (Cockpit sub-página 1 do design KB-9.75)
//   rules: R-FIN-001 (multi-tenant), R-FISCAL-001 (HasBusinessScope ADR 0093)
//   adrs: 0093, 0094, 0101, 0104, 0114
//   tests: Modules/Fiscal/Tests/Feature/CockpitMultiTenantTest
//
// Origem: design Cowork fiscal-page.jsx §3 FiscalCockpit + KpiRibbon + NotasUnifiedTab.
// Substitui o visual 6-KPI grid + quick links pelo padrão "Notas Fiscais"
// (header chips + ribbon estreito + tabela unificada NF-e/NFC-e/NFS-e).

import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import AppShellV2 from '@/Layouts/AppShellV2';
import { Head, router } from '@inertiajs/react';
import {
  Archive, ChevronDown, FileText, Plus, Receipt, RefreshCw,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

import AlertasFiscais, { type AlertaFiscal } from './_components/AlertasFiscais';
import EventosDrawer, { type EventoFiscal } from './_components/EventosDrawer';
import FxShell from './_components/FxShell';
import NFSeDrawer, { type NFSeDrawerData } from './_components/NFSeDrawer';
import NotaDrawerV2, { type NotaDrawerData } from './_components/NotaDrawerV2';
import SavedViewsChips from './_components/SavedViewsChips';
import SendToContabilDrawer, { type SendToContabilData } from './_components/SendToContabilDrawer';
import WriteOffAuditoriaCard, { type WriteOffSummary } from './_components/WriteOffAuditoriaCard';
import { brl, truncKey } from './_lib/fiscal-helpers';
import { Checkbox } from '@/Components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';

import '../../../css/fiscal-cockpit.css';

interface Kpis {
  emitidas: number;
  autorizadas: number;
  autorizadasPct: number;
  rejeitadas: number;
  faturamentoFiscal: number;
  dfeAguardando: number;
  certificadoValidadeDias: number | null;
}

interface Sparklines {
  emitidas: number[];
  autorizadas: number[];
  rejeitadas: number[];
  faturamento: number[];
}

type Tipo = 'NF-e' | 'NFC-e' | 'NFS-e';
type StatusKind = 'sefaz' | 'nfse';

interface NotaRow {
  id: string;
  tipo: Tipo;
  kind: 'nfe' | 'nfse';
  num: string;
  serie: string | null;
  when: string;
  emittedAtIso?: string | null;
  cliente: string;
  doc: string;
  uf: string;
  venda: string | null;
  ref: string | null;
  keyOrCode: string;
  iss?: number;
  status: number | string;
  statusKind: StatusKind;
  rejMsg: string | null;
  modelo: number | null;
  value: number;
  prazoCancel: { label: string; urgency: 'ok' | 'warn' | 'crit' } | null;
  prazoCce: { label: string; urgency: 'ok' | 'warn' | 'crit' } | null;
  // Detalhes opcionais (carregados sob demanda no PR seguinte; hoje vêm
  // do mockNotasUnificadas do CockpitController quando presentes)
  itens?: Array<{ nome: string; codigo: string; qtd: number; vl: number }>;
  boleto?: { id: string; venc: string; valor: number; status: 'pago' | 'pendente' | 'vencido' } | null;
  arquivos?: Array<{ tipo: string; nome: string; tamanho: string; status: string }>;
  emails?: Array<{ tipo: string; para: string; quando: string; status: string }>;
  auditoria?: Array<{ quando: string; autor: string; acao: string }>;
  eventos?: Array<{
    id: string | number;
    tipo: string;
    sequencia?: number;
    descricao: string;
    emit: string;
    autor: string;
    sefaz: number;
  }>;
  // NFS-e específicos
  codServ?: string;
  competencia?: string;
  cnpj?: string | null;
  cpf?: string | null;
}

interface SavedViewCounts {
  todas: number;
  resolver: number;
  janela24: number;
  processando: number;
  nfse: number;
  nfce: number;
}

interface SefazStatus {
  uf: string;
  operacional: boolean;
  label: string;
}

interface CockpitProps {
  kpis: Kpis;
  sparklines: Sparklines;
  alerts: AlertaFiscal[];
  notas: NotaRow[];
  savedViewCounts: SavedViewCounts;
  sefazStatus: SefazStatus;
  // Onda 2 — drawers do header (Eventos + Enviar p/ contabilidade)
  eventosMock?: EventoFiscal[];
  contabilData?: SendToContabilData | null;
  // Onda 3 — auditoria mensal (write-off candidatos)
  writeOffSummary?: WriteOffSummary | null;
}

type ViewId = 'todas' | 'resolver' | 'janela24' | 'processando' | 'nfse' | 'nfce' | 'custom';
type TipoFilter = 'todos' | Tipo;
type StatusFilter = 'todos' | 'autorizadas' | 'rejeitadas' | 'processando' | 'cancelaveis';
type Density = 'compact' | 'comfort' | 'relax';

const REJECTED_NFE_CODES = [110, 204, 220, 539, 691, 778];

function isRejected(n: NotaRow): boolean {
  if (n.statusKind === 'sefaz') return REJECTED_NFE_CODES.includes(n.status as number);
  return n.status === 'rejeitada';
}
function isProcessing(n: NotaRow): boolean {
  return n.statusKind === 'sefaz' ? n.status === 999 : n.status === 'processando';
}
function isAuthorized(n: NotaRow): boolean {
  if (n.statusKind === 'sefaz') return n.status === 100;
  return n.status === 'autorizada';
}

const SAVED_VIEWS: Array<{ id: Exclude<ViewId, 'custom'>; label: string; tipo: TipoFilter; status: StatusFilter; tone?: 'ok' | 'warn' | 'bad' }> = [
  { id: 'todas',       label: 'Todas',                tipo: 'todos', status: 'todos' },
  { id: 'resolver',    label: 'Pra resolver hoje',    tipo: 'todos', status: 'rejeitadas',  tone: 'bad' },
  { id: 'janela24',    label: 'Janela 24h aberta',    tipo: 'todos', status: 'cancelaveis', tone: 'warn' },
  { id: 'processando', label: 'Aguardando SEFAZ',     tipo: 'todos', status: 'processando' },
  { id: 'nfse',        label: 'Só serviço (NFS-e)',   tipo: 'NFS-e', status: 'todos' },
  { id: 'nfce',        label: 'Só balcão (NFC-e)',    tipo: 'NFC-e', status: 'todos' },
];

const STATUS_LABEL: Record<number, string> = {
  100: 'Autorizada',
  110: 'Rejeição',
  204: 'Duplicidade',
  220: 'NF-e numérica',
  539: 'Dest. inválido',
  691: 'Item rejeitado',
  778: 'XML inválido',
  999: 'Processando',
};

// Adaptadores NotaRow → drawer data (extraem campos do tipo unificado).
function mapToNotaDrawerData(n: NotaRow): NotaDrawerData {
  return {
    id: n.id,
    num: n.num,
    serie: n.serie ?? '1',
    modelo: (n.modelo ?? 55) as 55 | 65,
    key: n.keyOrCode,
    status: typeof n.status === 'number' ? n.status : 0,
    rejMsg: n.rejMsg,
    dest: n.cliente,
    cnpj: n.cnpj ?? null,
    cpf: n.cpf ?? null,
    uf: n.uf,
    venda: n.venda,
    when: n.when,
    emittedAtIso: n.emittedAtIso ?? null,
    value: n.value,
    itens: n.itens,
    boleto: n.boleto,
    arquivos: n.arquivos,
    emails: n.emails,
    auditoria: n.auditoria,
    eventos: n.eventos,
  };
}

function mapToNFSeDrawerData(n: NotaRow): NFSeDrawerData {
  const statusRaw = typeof n.status === 'string' ? n.status : 'autorizada';
  const status = (['autorizada', 'processando', 'rejeitada', 'cancelada'].includes(statusRaw)
    ? statusRaw
    : 'autorizada') as 'autorizada' | 'processando' | 'rejeitada' | 'cancelada';
  return {
    id: n.id,
    num: n.num,
    competencia: n.competencia ?? n.when,
    tomador: n.cliente,
    cnpj: n.cnpj ?? null,
    cpf: n.cpf ?? null,
    municipio: `${n.uf}`, // simplificado; backend real virá com cidade/UF
    iss: n.iss ?? 0,
    codServ: n.codServ ?? n.keyOrCode,
    ref: n.ref,
    when: n.when,
    status,
    rejMsg: n.rejMsg,
    value: n.value,
  };
}

export default function Cockpit({
  kpis, alerts, notas, savedViewCounts, sefazStatus,
  eventosMock = [], contabilData = null, writeOffSummary = null,
}: CockpitProps) {
  const goto = (path: string) => router.visit(path);

  // Filtros locais (PR seguinte: sync via URL + server-side filter).
  const [search, setSearch] = useState('');
  const [tipo, setTipo] = useState<TipoFilter>('todos');
  const [status, setStatus] = useState<StatusFilter>('todos');
  const [view, setView] = useState<ViewId>('todas');
  const [density, setDensity] = useState<Density>('comfort');
  // Onda 3 · o default 8 e as opções 8/25/50 vêm do protótipo (fiscal-page.jsx), não de palpite.
  const [pagina, setPagina] = useState(1);
  const [porPagina, setPorPagina] = useState(8);
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [clienteFilter, setClienteFilter] = useState<string | null>(null);

  // Drawer focus (id da nota aberta). Resolve qual drawer abrir pelo tipo.
  const [openedId, setOpenedId] = useState<string | null>(null);
  const openedNota = useMemo(() => notas.find((n) => n.id === openedId) ?? null, [notas, openedId]);
  const openedNfe = openedNota && openedNota.kind === 'nfe'
    ? mapToNotaDrawerData(openedNota)
    : null;
  const openedNfse = openedNota && openedNota.kind === 'nfse'
    ? mapToNFSeDrawerData(openedNota)
    : null;

  // Onda 2 — drawers do header chip (Eventos + Enviar p/ contabilidade)
  const [eventosOpen, setEventosOpen] = useState(false);
  const [contabilOpen, setContabilOpen] = useState(false);

  const applyView = (vid: Exclude<ViewId, 'custom'>) => {
    const v = SAVED_VIEWS.find((x) => x.id === vid);
    if (!v) return;
    setView(v.id);
    setTipo(v.tipo);
    setStatus(v.status);
    setClienteFilter(null);
    setSearch('');
  };

  const handleManualFilter = (kind: 'tipo' | 'status', val: string) => {
    if (kind === 'tipo') setTipo(val as TipoFilter);
    if (kind === 'status') setStatus(val as StatusFilter);
    if (view !== 'custom') setView('custom');
  };

  const filtrados = useMemo<NotaRow[]>(() => {
    let r = notas;
    if (tipo !== 'todos') r = r.filter((n) => n.tipo === tipo);
    if (status === 'autorizadas') r = r.filter(isAuthorized);
    if (status === 'rejeitadas')  r = r.filter(isRejected);
    if (status === 'processando') r = r.filter(isProcessing);
    if (status === 'cancelaveis') r = r.filter((n) => n.kind === 'nfe' && n.prazoCancel != null);
    if (clienteFilter) r = r.filter((n) => n.cliente === clienteFilter);
    if (search) {
      const s = search.toLowerCase();
      const sNum = s.replace(/\D/g, '');
      r = r.filter((n) =>
        n.num.includes(s) ||
        n.cliente.toLowerCase().includes(s) ||
        (sNum.length >= 3 && n.keyOrCode.includes(sNum))
      );
    }
    return r;
  }, [notas, tipo, status, clienteFilter, search]);

  // Onda 3 · paginação CLIENT-SIDE, espelhando `FxNotasPage` de fiscal-page.jsx.
  //
  // O universo paginado é `filtrados` — a lista JÁ carregada e filtrada —, NUNCA um total
  // de banco. `NotasUnifiedService::LIMITE` corta a fonte em 50 antes de ela chegar aqui
  // (o docblock de lá declara: "é resumo; a lista completa vive em /fiscal/nfe"), e não
  // existe contagem total escopada por business para servir de denominador honesto:
  // `contadores()['todas']` conta a MESMA lista truncada. Por isso o rodapé fala de
  // "resultados carregados" e não promete um total do negócio — ver UC-FCKP-09.
  const paginas = Math.max(1, Math.ceil(filtrados.length / porPagina));
  const rows = useMemo<NotaRow[]>(
    () => filtrados.slice((pagina - 1) * porPagina, pagina * porPagina),
    [filtrados, pagina, porPagina],
  );

  // Limpa seleção e volta pra página 1 quando filtra ou troca o tamanho da página: sem
  // isso o operador fica numa página que não existe mais, com seleção de linhas que ele
  // não vê. `porPagina` entra nas deps de propósito (vem do protótipo).
  useEffect(() => {
    setSelected(new Set());
    setPagina(1);
  }, [tipo, status, search, clienteFilter, porPagina]);

  const toggleSel = (id: string) => {
    const next = new Set(selected);
    if (next.has(id)) next.delete(id); else next.add(id);
    setSelected(next);
  };
  const selectAll = () => {
    if (selected.size === rows.length) setSelected(new Set());
    else setSelected(new Set(rows.map((r) => r.id)));
  };

  // Popmenu "+ Emitir" (preservado do visual anterior — Wagner 2026-05-25).
  const [emitirOpen, setEmitirOpen] = useState(false);
  const emitirRef = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (!emitirOpen) return;
    const handler = (e: MouseEvent) => {
      if (emitirRef.current && !emitirRef.current.contains(e.target as Node)) setEmitirOpen(false);
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [emitirOpen]);

  const totalRej = kpis.rejeitadas + (alerts.filter((a) => a.level === 'crit').length);
  const crumb = `Maio 2026 · ${kpis.emitidas} notas · ${totalRej} requerem ação`;

  return (
    <AppShellV2>
      <Head title="Fiscal · Notas Fiscais" />

      <FxShell
        route="fiscal"
        title="Notas Fiscais"
        crumb={crumb}
        env={sefazStatus.label}
        envTone={sefazStatus.operacional ? 'ok' : 'bad'}
        cheats={[
          { keys: ['⌘', 'K'], label: 'buscar' },
          { keys: ['N'], label: 'emitir' },
          { keys: ['J', 'K'], label: 'navegar' },
          { keys: ['?'], label: 'mais atalhos' },
        ]}
        actions={
          <>
            <Button type="button" variant="cowork-ghost" onClick={() => setEventosOpen(true)}>
              <RefreshCw size={12} /> Eventos
              {eventosMock.length > 0 && (
                <span className="ml-1 text-[10px] font-bold text-muted-foreground">{eventosMock.length}</span>
              )}
            </Button>
            <Button
              type="button"
              variant="cowork-ghost"
              onClick={() => setContabilOpen(true)}
              disabled={!contabilData}
              title={contabilData ? 'Abrir fluxo de envio mensal' : 'Backend stub — TODO[CL]'}
            >
              <Archive size={12} /> Enviar p/ contabilidade
            </Button>
            <div ref={emitirRef} className="fx-popmenu-wrap">
              <Button
                type="button"
                variant="cowork-primary"
                onClick={() => setEmitirOpen((v) => !v)}
                aria-haspopup="menu"
                aria-expanded={emitirOpen}
              >
                <Plus size={12} /> Emitir <ChevronDown size={11} />
              </Button>
              {emitirOpen && (
                <div role="menu" className="fx-popmenu">
                  <button role="menuitem" className="fx-popmenu-item" onClick={() => { setEmitirOpen(false); goto('/fiscal/nfe'); }}>
                    <Receipt size={13} /> NF-e
                  </button>
                  <button role="menuitem" className="fx-popmenu-item" onClick={() => { setEmitirOpen(false); goto('/fiscal/nfe?modelo=65'); }}>
                    <Receipt size={13} /> NFC-e
                  </button>
                  <button role="menuitem" className="fx-popmenu-item" onClick={() => { setEmitirOpen(false); goto('/nfse'); }}>
                    <FileText size={13} /> NFS-e
                  </button>
                </div>
              )}
            </div>
          </>
        }
      >
        {/* KPI ribbon estreito (substitui fx-kpis-cockpit 6-card grid) */}
        <div className="fx-ribbon" data-contract="fiscal-cockpit-kpis" role="region" aria-label="KPIs fiscais">
          <span className="fx-ribbon-item">
            <small>Emitidas</small>
            <b>{kpis.emitidas}</b>
            <em className="up">↑ 12 vs abr</em>
          </span>
          <span className="fx-ribbon-item">
            <small>Autorizadas</small>
            <b className="ok-text">{kpis.autorizadas}</b>
            <em>{kpis.autorizadasPct}%</em>
          </span>
          <span className="fx-ribbon-item">
            <small>Rejeitadas</small>
            <b className={kpis.rejeitadas > 0 ? 'emph' : ''}>{kpis.rejeitadas}</b>
            {kpis.rejeitadas > 0 && <em className="down">requer ação</em>}
          </span>
          <span className="fx-ribbon-item">
            <small>DF-e p/ manifestar</small>
            <b>{kpis.dfeAguardando}</b>
            <em>prazo 90d</em>
          </span>
          <span className="fx-ribbon-item">
            <small>Certif. A1</small>
            <b>{kpis.certificadoValidadeDias != null ? `${kpis.certificadoValidadeDias}d` : '—'}</b>
            <em>{kpis.certificadoValidadeDias != null && kpis.certificadoValidadeDias <= 30 ? 'renovar' : 'vigente'}</em>
          </span>
          <span className="fx-ribbon-item">
            <small>Faturado fiscal</small>
            <b>{brl(kpis.faturamentoFiscal).replace('R$ ', 'R$ ')}</b>
          </span>
          <Button type="button" variant="outline" size="xs" className="ml-auto shrink-0 self-center" onClick={() => goto('/fiscal/sped')}>
            Fechar mês →
          </Button>
        </div>

        {/* Fila de alertas — o que o ribbon conta em "requerem ação", item a item.
            Só renderiza quando há alerta; zero alertas = nó ausente. */}
        <AlertasFiscais alerts={alerts} />

        {/* Onda 3 L — Write-off auditoria mensal (só renderiza se houver candidatos) */}
        <WriteOffAuditoriaCard summary={writeOffSummary} />

        {/* Toolbar minimalista — search + 3 selects + density */}
        <div className="fx-notas-toolbar">
          {/* Espaço da lupa pela utilitária canon `.cw-input-icon-left`, NÃO `pl-*`:
              a Tailwind é layered e perde pro `.cw-input` unlayered (cowork-fields.css). */}
          <div className="relative min-w-[240px] flex-1">
            <svg
              width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"
              className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"
              aria-hidden="true"
            >
              <circle cx="11" cy="11" r="8" /><path d="m21 21-4.3-4.3" />
            </svg>
            <Input
              type="search"
              className="cw-input-icon-left"
              placeholder="Buscar nº, cliente, CNPJ, chave…"
              aria-label="Buscar notas por número, cliente, CNPJ ou chave"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>

          {/* Onda 3 C — SavedViewsChips (substitui o <select> por chips horizontais Linear-style) */}
          <SavedViewsChips
            views={SAVED_VIEWS.map((v) => ({
              id: v.id,
              label: v.label,
              count: savedViewCounts[v.id] ?? 0,
              tone: v.tone ?? null,
            }))}
            value={view}
            onChange={(id) => applyView(id as Exclude<ViewId, 'custom'>)}
            customCount={rows.length}
            isCustom={view === 'custom'}
          />

          <Select value={tipo} onValueChange={(v) => handleManualFilter('tipo', v)}>
            <SelectTrigger size="sm" className="w-auto" aria-label="Filtrar por tipo">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="todos">Todos os tipos · {notas.length}</SelectItem>
              <SelectItem value="NF-e">NF-e · {notas.filter((n) => n.tipo === 'NF-e').length}</SelectItem>
              <SelectItem value="NFC-e">NFC-e · {notas.filter((n) => n.tipo === 'NFC-e').length}</SelectItem>
              <SelectItem value="NFS-e">NFS-e · {notas.filter((n) => n.tipo === 'NFS-e').length}</SelectItem>
            </SelectContent>
          </Select>

          <Select value={status} onValueChange={(v) => handleManualFilter('status', v)}>
            <SelectTrigger size="sm" className="w-auto" aria-label="Filtrar por status">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="todos">Todos status · {notas.length}</SelectItem>
              <SelectItem value="autorizadas">Autorizadas · {notas.filter(isAuthorized).length}</SelectItem>
              <SelectItem value="rejeitadas">Rejeitadas · {notas.filter(isRejected).length}</SelectItem>
              <SelectItem value="cancelaveis">Janela 24h · {notas.filter((n) => n.kind === 'nfe' && n.prazoCancel != null).length}</SelectItem>
              <SelectItem value="processando">Processando · {notas.filter(isProcessing).length}</SelectItem>
            </SelectContent>
          </Select>

          <div className="fx-density" role="radiogroup" aria-label="Densidade da tabela">
            <button type="button" className={density === 'compact' ? 'active' : ''} onClick={() => setDensity('compact')} title="Compacto" aria-pressed={density === 'compact'}>
              <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="6" width="18" height="2" /><rect x="3" y="11" width="18" height="2" /><rect x="3" y="16" width="18" height="2" /></svg>
            </button>
            <button type="button" className={density === 'comfort' ? 'active' : ''} onClick={() => setDensity('comfort')} title="Confortável" aria-pressed={density === 'comfort'}>
              <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="4" width="18" height="3" /><rect x="3" y="10" width="18" height="3" /><rect x="3" y="16" width="18" height="3" /></svg>
            </button>
            <button type="button" className={density === 'relax' ? 'active' : ''} onClick={() => setDensity('relax')} title="Relaxado" aria-pressed={density === 'relax'}>
              <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="18" height="5" /><rect x="3" y="11" width="18" height="5" /></svg>
            </button>
          </div>
        </div>

        {/* Chip de cliente filtrado */}
        {clienteFilter && (
          <div className="fx-active-filter">
            <span>Filtrando por cliente:</span>
            <b>{clienteFilter}</b>
            <button onClick={() => setClienteFilter(null)} title="Limpar filtro" aria-label="Limpar filtro">×</button>
          </div>
        )}

        {/* Bulk action bar */}
        {selected.size > 0 && (
          <div className="fx-bulk-bar" role="region" aria-label="Ações em lote">
            <span><b>{selected.size}</b> nota{selected.size > 1 ? 's' : ''} selecionada{selected.size > 1 ? 's' : ''}</span>
            <Button type="button" variant="cowork-ghost">Baixar XMLs (ZIP)</Button>
            <Button type="button" variant="cowork-ghost">Baixar DANFEs (PDF)</Button>
            <Button type="button" variant="cowork-ghost">Reenviar por e-mail</Button>
            <Button type="button" variant="cowork-ghost" onClick={() => setSelected(new Set())}>Limpar seleção</Button>
          </div>
        )}

        {/* Tabela unificada */}
        {rows.length === 0 ? (
          <div className="fx-empty">
            <b>Nenhuma nota pra esses filtros</b>
            <small>Tente outro preset ou limpe a busca.</small>
          </div>
        ) : (
          <div className={`fx-density-${density}`}>
            <div className="fx-table">
              <table>
                <thead>
                  <tr>
                    <th style={{ width: 36 }}>
                      <Checkbox
                        checked={selected.size === rows.length && rows.length > 0}
                        onCheckedChange={selectAll}
                        aria-label="Selecionar todas"
                      />
                    </th>
                    <th style={{ width: 90 }}>Tipo</th>
                    <th style={{ width: 80 }}>Número</th>
                    <th>Cliente / chave</th>
                    <th style={{ width: 160 }}>Status</th>
                    <th style={{ width: 130 }}>Prazo</th>
                    <th style={{ width: 130, textAlign: 'right' }}>Valor</th>
                  </tr>
                </thead>
                <tbody>
                  {rows.map((n) => {
                    const tipoCls = n.tipo === 'NF-e' ? 't-nfe' : n.tipo === 'NFC-e' ? 't-nfce' : 't-nfse';
                    const rejected = isRejected(n);
                    return (
                      <tr
                        key={n.id}
                        onClick={() => setOpenedId(n.id)}
                        className={openedId === n.id ? 'fx-row-focus' : ''}
                        style={{ cursor: 'pointer' }}
                        title="Click pra abrir detalhes (drawer)"
                      >
                        <td onClick={(e) => e.stopPropagation()}>
                          <Checkbox
                            checked={selected.has(n.id)}
                            onCheckedChange={() => toggleSel(n.id)}
                            aria-label={`Selecionar nota ${n.num}`}
                          />
                        </td>
                        <td><span className={`fx-tipo-dot ${tipoCls}`}>{n.tipo}</span></td>
                        <td className="fx-mono">
                          <b>{n.num}</b>
                          {n.serie && <small>série {n.serie}</small>}
                          {!n.serie && <small>{n.when.split(' ')[0]}</small>}
                        </td>
                        <td>
                          <a
                            className="fx-link"
                            onClick={(e) => {
                              e.preventDefault();
                              setClienteFilter(n.cliente);
                            }}
                            title="Filtrar por este cliente"
                          >
                            <b>{n.cliente}</b>
                          </a>
                          <div style={{ fontSize: 11, color: 'var(--fx-text-mute)' }}>
                            {n.doc} · {n.uf}
                            {n.venda && <> · <span className="fx-link">{n.venda}</span></>}
                            {n.ref && !n.venda && <> · <span className="fx-link">{n.ref}</span></>}
                          </div>
                          {n.keyOrCode && (
                            <code className="fx-cell-key" title={n.keyOrCode}>
                              {n.kind === 'nfe' ? truncKey(n.keyOrCode) : `cód. ${n.keyOrCode}`}
                              {n.kind === 'nfse' && n.iss && ` · ${n.iss}% ISS`}
                            </code>
                          )}
                        </td>
                        <td>
                          {n.statusKind === 'sefaz' ? (
                            <>
                              <span className={`fx-sefaz ${isAuthorized(n) ? 'ok' : rejected ? 'bad' : 'warn'}`}>
                                <span className="lbl">
                                  {STATUS_LABEL[n.status as number] || `Status ${n.status}`}
                                </span>
                              </span>
                              {n.rejMsg && <div style={{ fontSize: 11, color: 'var(--bad)', marginTop: 3 }}>↳ {n.rejMsg}</div>}
                            </>
                          ) : (
                            <>
                              <span className={`fx-sefaz ${isAuthorized(n) ? 'ok' : rejected ? 'bad' : 'warn'}`}>
                                <span className="lbl">
                                  {String(n.status).charAt(0).toUpperCase() + String(n.status).slice(1)}
                                </span>
                              </span>
                              {n.rejMsg && <div style={{ fontSize: 11, color: 'var(--bad)', marginTop: 3 }}>↳ {n.rejMsg}</div>}
                            </>
                          )}
                        </td>
                        <td>
                          {n.prazoCancel ? (
                            <span className={`fx-timepill u-${n.prazoCancel.urgency}`}>
                              <RefreshCw size={9} /> cancelar em <b>{n.prazoCancel.label}</b>
                            </span>
                          ) : n.prazoCce ? (
                            <span className={`fx-timepill u-${n.prazoCce.urgency}`}>
                              CC-e <b>{n.prazoCce.label}</b>
                            </span>
                          ) : rejected ? (
                            <span className="fx-timepill u-crit"><b>ação</b></span>
                          ) : (
                            <span style={{ color: 'var(--fx-text-mute)' }}>—</span>
                          )}
                        </td>
                        <td style={{ textAlign: 'right' }}>
                          <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8, justifyContent: 'flex-end' }}>
                            <span className="fx-row-actions" onClick={(e) => e.stopPropagation()}>
                              <button type="button" className="fx-row-act" title="Baixar XML">XML</button>
                              <button type="button" className="fx-row-act" title="Baixar DANFE">PDF</button>
                              {rejected && <button type="button" className="fx-row-act danger" title="Retransmitir">↻</button>}
                            </span>
                            <span className="fx-strong">{brl(n.value)}</span>
                          </span>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* Onda 3 · rodapé de paginação. Ordem e copy vêm de fiscal-page.jsx §FxNotasPage.
            O hint "J/K navega · ↵ abre · N emite" do protótipo NÃO entra: no protótipo o
            cockpit e a lista de NF-e são o MESMO componente, então o atalho valia para os
            dois; em produção são telas separadas e a Onda 2 (PR 6707) entregou o teclado só
            (o número vai sem cerquilha de propósito: seus dígitos são todos hex válidos e
            esta linha é CONTINUAÇÃO de comentário JSX, que o skip do `ui:lint` declaradamente
            não cobre — o falso-positivo R1 catalogado no docblock de `UiLintCommand`)
            no Nfe.tsx. Anunciar aqui um atalho que esta tela não tem seria copy mentindo. */}
        {filtrados.length > 0 && (
          <div className="fx-pager" data-contract="paginacao-notas">
            <span>
              {(pagina - 1) * porPagina + 1}–{Math.min(pagina * porPagina, filtrados.length)}
              {' de '}{filtrados.length} carregadas
            </span>

            <Select value={String(porPagina)} onValueChange={(v) => setPorPagina(Number(v))}>
              <SelectTrigger size="sm" className="w-auto" aria-label="Notas por página">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="8">8 por página</SelectItem>
                <SelectItem value="25">25 por página</SelectItem>
                <SelectItem value="50">50 por página</SelectItem>
              </SelectContent>
            </Select>

            <Button
              type="button"
              variant="cowork-ghost"
              disabled={pagina <= 1}
              onClick={() => setPagina((p) => Math.max(1, p - 1))}
            >
              Anterior
            </Button>
            <span className="fx-pager-n">{pagina} / {paginas}</span>
            <Button
              type="button"
              variant="cowork-ghost"
              disabled={pagina >= paginas}
              onClick={() => setPagina((p) => Math.min(paginas, p + 1))}
            >
              Próxima
            </Button>
          </div>
        )}
      </FxShell>

      {/* Drawer NFe/NFCe (slide-in 480px com 7 seções + receita SEFAZ) */}
      <NotaDrawerV2
        nota={openedNfe}
        onClose={() => setOpenedId(null)}
        onQuickFilterCliente={setClienteFilter}
      />

      {/* Drawer NFSe (versão leve — sem chave 44d, com cód serviço/ISS) */}
      <NFSeDrawer
        nota={openedNfse}
        onClose={() => setOpenedId(null)}
      />

      {/* Drawer Eventos (chip 'Eventos' do header — Onda 2 D) */}
      <EventosDrawer
        open={eventosOpen}
        eventos={eventosMock}
        onClose={() => setEventosOpen(false)}
      />

      {/* Drawer Enviar p/ contabilidade (chip do header — Onda 2 D) */}
      <SendToContabilDrawer
        open={contabilOpen}
        data={contabilData}
        onClose={() => setContabilOpen(false)}
      />
    </AppShellV2>
  );
}
