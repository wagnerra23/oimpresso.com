// Trabalho — a lista ÚNICA do hub Forja.
//
// @memcofre
//   tela: /forja/trabalho
//   module: Forja
//   adrs: 0070 (Jira-style) · 0093 (Tier 0) · 0253 (primitivos) · 0388 (réplica primeiro) · UI-0013
//   permissao: jana.mcp.usage.all
//   paridade: prototipo-ui/cowork/forja-page.jsx — view `trabalho`, sub-visão `lista`
//
// ── PARIDADE §11 ONDA 4 (2026-09-02) — a tela é a do protótipo ──────────────
// Decisão [W]: *"pode fazer igual ao protótipo e revogar todo o resto"*. A lei é a
// ADR 0388 ("réplica primeiro"): o protótipo é o contrato de LAYOUT, e a
// conformidade do DS vira item em `INCONSISTENCIAS-replica.md`, não veto.
//
// O que a sonda mediu em 2026-09-02 (`forja-cockpit-visual-comparison.md`) e o
// que esta onda fecha:
//
//   D2 filtros em 1 linha × 3   → agora são as MESMAS três barras do protótipo
//                                 (`fj-frentebar` · `fj-toolbar` · `fj-filterbar2`)
//   D2 linha com 3 colunas × 13 → a `fj-row` densa, com os slots que têm dado real
//   D4 KPI valor 22px × 17px    → `tf-kpi-v` do bundle (17px), no lugar do KpiCard canon
//   D8 KPI é DIV × BUTTON       → o KPI VOLTA A FILTRAR: clicar recorta a lista
//   D6 primary 0,55 × 0,70      → herdado do `[data-theme="dark"] .fj-page` (Onda 2.1)
//
// ── O QUE MUDOU DE VOCABULÁRIO, e por que não é regressão de DS ─────────────
// Saíram `PageHeader`, `KpiGrid`/`KpiCard` e os primitivos `Grid`/`Inline`/`Stack`
// desta tela; entraram as classes `fj-`/`tf-` do `cowork-forja-bundle.css`. Isso
// é a ADR 0388 aplicada: onde existe âncora de design, a aparência é a dela. O
// header canon continua em `<ForjaHub>` (Onda 2), que é o dono do topo em TODAS
// as telas do hub — não foi tocado aqui.
//
// ── O QUE NÃO VEIO DO PROTÓTIPO, e por que é decisão, não esquecimento ──────
// Cada item abaixo está no charter §"Diferenças declaradas" e na lista de
// inconsistências. Nenhum é "não deu tempo": todos os quatro exigem
// COMPORTAMENTO novo, e a ADR 0388 é licença de aparência (D-5, textual:
// "usar 'é réplica' para tocar comportamento (rota, permissão, dado, cálculo)").
//
//   · checkbox + `.fj-bulkbar`  → mutação em massa; sem endpoint, e o charter
//                                 proíbe escrita fora do `TaskCrudService` (FSM)
//   · `Papéis` e `Perguntar ✦`  → abrem painéis (runbook e IA) que não existem
//   · `carry ×N` e `frescor`    → campos que `mcp_tasks` não tem
//   · atalhos `j`/`k`/`↵`/`?`   → a hint do rodapé sairia anunciando teclado que
//                                 esta tela não escuta (afordância falsa, LC-15)
//
// ── ESTADO QUE MORA NO NAVEGADOR, igual ao protótipo ────────────────────────
// Favorito, fixado, visões salvas, densidade e grupos colapsados são
// `localStorage` — no protótipo TAMBÉM são (linhas 787-830 do `forja-page.jsx`).
// Não viram coluna nem user-pref no banco: o Non-Goal do charter ("rank híbrido
// com pin PERSISTIDO... depende de user-pref gravada") segue valendo inteiro.
// Filtro, ordem, agrupamento, KPI-filtro e papel viajam na URL — assim o link
// carrega a vista, e o D1 (partial reload) continua sendo o que o §11 pede.

import AppShellV2 from '@/Layouts/AppShellV2';
import { router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Segmented } from '@/Components/ui/segmented';
import ForjaHub from '../../team-mcp/Forja/_components/ForjaHub';
import TrabalhoQuadro, { type EixoQuadro } from './_components/TrabalhoQuadro';
import TrabalhoLista, { type TarefaLista } from './_components/TrabalhoLista';
import { PAPEIS as PAPEL_INFO } from './_components/trabalhoTokens';
import { LayoutGrid, List as ListIcon, GanttChartSquare, Search, Star as StarIcon } from 'lucide-react';

interface Kpis {
  total: number; ativas: number; p0: number; fazendo: number;
  bloqueadas: number; sem_dono: number; atrasadas: number;
}
const KPIS_VAZIO: Kpis = { total: 0, ativas: 0, p0: 0, fazendo: 0, bloqueadas: 0, sem_dono: 0, atrasadas: 0 };

interface Props {
  titulo: string;
  subtitle: string;
  filtros: Record<string, unknown>;
  sorts: string[];
  statuses: string[];
  /** Allowlists do backend — espelhá-las aqui criaria 2ª declaração pra divergir. */
  grupos: string[];
  papeis: string[];
  /** Fases do pipeline (`ForjaQuadroService`) — só o rótulo, pro selo da linha. */
  fases?: { key: string; label: string }[];
  filtrosGantt?: string[];
  agents?: string[];
  tasks?: TarefaLista[];
  kpis?: Kpis;
  frentes?: Record<number, string>;
}

const ORDEM_LABEL: Record<string, string> = {
  rank: 'rank', execucao: 'execução', recent: 'recentes', due: 'vencimento', title: 'título', id: 'id',
};
const GRUPO_LABEL: Record<string, string> = {
  onda: 'Onda', frente: 'Frente', fase: 'Fase', papel: 'Papel', prioridade: 'Prioridade', modulo: 'Módulo',
};
/** Rótulo do chip que aparece quando o KPI-filtro está ligado (`hfLabel` do protótipo). */
const SAUDE_LABEL: Record<string, string> = { p0: 'P0', fazendo: 'fazendo', bloqueadas: 'bloqueados' };

/** Leitura tolerante de `localStorage` — o protótipo faz o mesmo try/catch. */
function lerLocal<T>(chave: string, fallback: T): T {
  try {
    const cru = localStorage.getItem(chave);
    return cru ? (JSON.parse(cru) as T) : fallback;
  } catch { return fallback; }
}
function gravarLocal(chave: string, valor: unknown): void {
  try { localStorage.setItem(chave, JSON.stringify(valor)); } catch { /* modo privado, cota: seguir sem persistir */ }
}

// `titulo`, `subtitle` e `statuses` seguem no contrato do controller mas NÃO são
// lidos aqui (o `subtitle` porque a nota da barra usa a copy LITERAL do protótipo):
// o título da tela é o `h1 Forja` do `<ForjaHub>` (o protótipo não repete título
// dentro da view), e `statuses` alimenta o filtro por status, que esta onda não
// desenha — o recorte por estado é o KPI-filtro. Tirá-los do controller seria
// mudar contrato de dados por causa de layout; ficam declarados em `Props`.
export default function Trabalho({
  filtros, sorts, grupos, papeis, fases = [], tasks = [], kpis = KPIS_VAZIO,
  frentes = {}, filtrosGantt = [], agents = [],
}: Props) {
  const [busca, setBusca] = useState(String(filtros.q ?? ''));
  const ordem = String(filtros.sort ?? 'rank');
  const visao = String(filtros.visao ?? 'lista');
  const eixo = String(filtros.eixo ?? 'execucao') as EixoQuadro;
  const grupo = String(filtros.grupo ?? 'frente');
  const saude = filtros.saude ? String(filtros.saude) : null;
  const papelAtivo = filtros.papel ? String(filtros.papel) : null;

  // ─── Estado do viewer (localStorage), igual ao protótipo ───
  const [denso, setDenso] = useState(() => lerLocal('oimpresso.forja.denso', false));
  const [favOnly, setFavOnly] = useState(false);
  const [favoritos, setFavoritos] = useState<Set<string>>(() => new Set(lerLocal<string[]>('oimpresso.forja.fav', [])));
  const [fixados, setFixados] = useState<Set<string>>(() => new Set(lerLocal<string[]>('oimpresso.forja.pin', [])));
  const [visoes, setVisoes] = useState<{ name: string; qs: string }[]>(() => lerLocal('oimpresso.forja.views', []));
  const [colapsados, setColapsados] = useState<Set<string>>(() => new Set());

  useEffect(() => { gravarLocal('oimpresso.forja.denso', denso); }, [denso]);
  useEffect(() => { gravarLocal('oimpresso.forja.fav', [...favoritos]); }, [favoritos]);
  useEffect(() => { gravarLocal('oimpresso.forja.pin', [...fixados]); }, [fixados]);
  useEffect(() => { gravarLocal('oimpresso.forja.views', visoes); }, [visoes]);

  const alternar = useCallback((set: Set<string>, id: string): Set<string> => {
    const novo = new Set(set);
    if (novo.has(id)) novo.delete(id); else novo.add(id);
    return novo;
  }, []);

  /**
   * Recarrega só o que muda — cabeçalho e controles ficam.
   *
   * Vale pra TODO controle que recorta ou reordena (busca, ordem, agrupamento,
   * KPI-filtro, papel, visão): é o D1 do §11 — um GET Inertia parcial, sem full
   * reload, com a vista inteira na URL.
   */
  const aplicar = useCallback((patch: Record<string, string | null>) => {
    const query: Record<string, string> = {};
    for (const [k, v] of Object.entries({ ...filtros, ...patch })) {
      if (v !== null && v !== undefined && v !== '') query[k] = String(v);
    }
    router.get('/forja/trabalho', query, {
      preserveState: true, preserveScroll: true,
      only: ['tasks', 'kpis', 'filtros', 'agents'],
    });
  }, [filtros]);

  /** Liga/desliga o KPI-filtro — clicar o cartão aceso o apaga (toggle do protótipo). */
  const alternarSaude = useCallback((chave: string) => {
    aplicar({ saude: saude === chave ? null : chave });
  }, [aplicar, saude]);

  /**
   * O Gantt é a 3ª vista do mesmo trabalho, mas mora em `/forja/roadmap-gantt` —
   * e continua morando lá (4 colisões medidas, no charter). Leva só os filtros
   * que o DESTINO lê (`TrabalhoService::FILTROS_ATALHO_GANTT`, do backend):
   * mandar os outros seria parâmetro ignorado em silêncio.
   */
  const irParaGantt = useCallback(() => {
    const carrega: Record<string, string> = {};
    for (const chave of filtrosGantt) {
      const valor = filtros[chave];
      if (valor !== null && valor !== undefined && valor !== '') carrega[chave] = String(valor);
    }
    router.get('/forja/roadmap-gantt', carrega);
  }, [filtros, filtrosGantt]);

  const submeterBusca = useCallback((e: React.FormEvent) => {
    e.preventDefault();
    aplicar({ q: busca });
  }, [aplicar, busca]);

  /** Salva a vista atual (a query string) — `localStorage`, como no protótipo. */
  const salvarVisao = useCallback(() => {
    const qs = window.location.search.replace(/^\?/, '');
    const name = `visão ${visoes.length + 1}`;
    setVisoes((v) => [...v, { name, qs }]);
  }, [visoes.length]);

  const faseLabel = useCallback((fase: string) => fases.find((f) => f.key === fase)?.label.replace(/^F[\d.]+\s+/, ''), [fases]);

  // ─── A lista que a tela desenha ───
  // O backend já recortou por busca/status/saúde/papel e ordenou. Aqui sobra o
  // que é DO VIEWER: o filtro de favoritos e o pin furando a fila.
  const visiveis = useMemo(
    () => (favOnly ? tasks.filter((t) => favoritos.has(t.task_id)) : tasks),
    [tasks, favOnly, favoritos],
  );

  const ordenadas = useMemo(() => {
    if (fixados.size === 0) return visiveis;
    // Estável: `sort` do V8 preserva a ordem do backend dentro de cada bloco.
    return [...visiveis].sort((a, b) => Number(fixados.has(b.task_id)) - Number(fixados.has(a.task_id)));
  }, [visiveis, fixados]);

  /**
   * Agrupamento — os SEIS do protótipo. Ordem de inserção, como lá
   * (`Object.entries` do `groups`): o primeiro grupo é o do trabalho mais
   * bem-ranqueado, não o maior.
   */
  const gruposLista = useMemo(() => {
    const chaveDe = (t: TarefaLista): string => {
      switch (grupo) {
        case 'onda':       return t.forja_onda ?? 'Sem onda';
        case 'fase':       return t.forja_fase ? (fases.find((f) => f.key === t.forja_fase)?.label ?? t.forja_fase) : 'Execução (sem fase)';
        case 'papel':      return t.forja_papel ? `[${t.forja_papel}] ${PAPEL_INFO[t.forja_papel]?.nome ?? ''}`.trim() : 'Sem papel';
        case 'prioridade': return t.priority.toUpperCase();
        case 'modulo':     return t.module ?? 'Sem módulo';
        default:           return t.frente_id ? (frentes[t.frente_id] ?? `#${t.frente_id}`) : '— sem frente —';
      }
    };
    const mapa = new Map<string, TarefaLista[]>();
    for (const t of ordenadas) {
      const k = chaveDe(t);
      const atual = mapa.get(k);
      if (atual) atual.push(t); else mapa.set(k, [t]);
    }
    return [...mapa.entries()];
  }, [ordenadas, grupo, frentes, fases]);

  const totais = useMemo(() => ({
    n: ordenadas.length,
    p0: ordenadas.filter((t) => t.priority === 'p0').length,
    bloqueados: ordenadas.filter((t) => t.is_blocked || t.blocked_by.length > 0).length,
  }), [ordenadas]);

  const isLista = visao === 'lista';

  return (
    <AppShellV2 title="Forja — Trabalho" breadcrumbItems={[{ label: 'Forja' }, { label: 'Trabalho' }]}>
      <ForjaHub active="trabalho" />

      {/* `.fj-page` é o root do protótipo — e é o escopo onde o `--accent` dark
          vale 0,70 (bloco da Onda 2.1 no bundle). Sem ele, esta tela voltaria ao
          0,55 da fundação, que é o D6 de todas as rodadas de comparação. */}
      <div className="fj-page" data-testid="trabalho-page">
        <div className="fj-frentebar">
          <Segmented
            aria-label="Visão do trabalho"
            value={visao}
            onValueChange={(v) => (v === 'gantt' ? irParaGantt() : aplicar({ visao: v }))}
            options={[
              { value: 'lista',  label: <><ListIcon size={11} aria-hidden /> Lista</> },
              { value: 'quadro', label: <><LayoutGrid size={11} aria-hidden /> Quadro</> },
              // Abre OUTRA tela (o Gantt tem trio e payload próprios). A seta e o
              // `title` avisam; esconder a troca de URL seria mentir sobre onde a
              // pessoa está.
              { value: 'gantt',  label: <span title="Abre a linha do tempo levando os filtros compatíveis (outra tela)"><GanttChartSquare size={11} aria-hidden /> Gantt ↗</span> },
            ]}
            data-testid="trabalho-visao"
          />
          {/* Copy LITERAL do protótipo (linha 1138 do forja-page.jsx). Não uso a
              prop `subtitle` aqui: ela diz quase a mesma coisa com outras
              palavras, e as duas juntas sairiam com dois travessões. `.mono`
              resolve nos dois lados (`.cockpit .mono` em cockpit.css:1270). */}
          <span className="fj-frente-note">
            <b className="mono">{kpis.total}</b> mcp_tasks numa lista só — FORJA junto das demais frentes (agrupe por Frente ou busque)
          </span>
        </div>

        {/* KPI que FILTRA (D8): `<button>`, não `<div>`. No protótipo o número diz
            o tamanho do problema e o clique mostra quais são — por isso os valores
            vêm do POOL, nunca do recorte que eles mesmos aplicam. */}
        <div className="fj-kpirow" data-testid="trabalho-kpis">
          <button type="button" className="tf-kpi" disabled>
            <span className="tf-kpi-v">{kpis.total}</span><span className="tf-kpi-l">Total</span>
          </button>
          <button type="button" onClick={() => alternarSaude('p0')} aria-pressed={saude === 'p0'}
            className={'tf-kpi click ' + (kpis.p0 ? 'bad' : 'ok') + (saude === 'p0' ? ' on' : '')} data-testid="trabalho-kpi-p0">
            <span className="tf-kpi-v">{kpis.p0}</span><span className="tf-kpi-l">P0 abertas</span>
          </button>
          <button type="button" onClick={() => alternarSaude('fazendo')} aria-pressed={saude === 'fazendo'}
            className={'tf-kpi click' + (saude === 'fazendo' ? ' on' : '')} data-testid="trabalho-kpi-fazendo">
            <span className="tf-kpi-v">{kpis.fazendo}</span><span className="tf-kpi-l">Fazendo</span>
          </button>
          <button type="button" onClick={() => alternarSaude('bloqueadas')} aria-pressed={saude === 'bloqueadas'}
            className={'tf-kpi click ' + (kpis.bloqueadas ? 'warn' : 'ok') + (saude === 'bloqueadas' ? ' on' : '')} data-testid="trabalho-kpi-bloqueadas">
            <span className="tf-kpi-v">{kpis.bloqueadas}</span><span className="tf-kpi-l">Bloqueadas</span>
          </button>
          <span className="fj-kpirow-note">clique filtra a lista e o quadro</span>
        </div>

        <div className="fj-toolbar">
          <div className="fj-groupby">
            <span className="fj-groupby-lbl">{isLista ? 'Agrupar' : 'Eixo'}</span>
            {isLista
              ? grupos.map((g) => (
                <button key={g} type="button" onClick={() => aplicar({ grupo: g })} aria-pressed={grupo === g}
                  className={'fj-gb-btn' + (grupo === g ? ' active' : '')} data-testid={`trabalho-grupo-${g}`}>
                  {GRUPO_LABEL[g] ?? g}
                </button>
              ))
              : (['pipeline', 'execucao'] as EixoQuadro[]).map((e) => (
                <button key={e} type="button" onClick={() => aplicar({ eixo: e })} aria-pressed={eixo === e}
                  className={'fj-gb-btn' + (eixo === e ? ' active' : '')} data-testid={`trabalho-eixo-${e}`}
                  title={e === 'execucao' ? 'O que está andando — vale pra toda task' : 'Em que ponto do protocolo de tela — só trabalho de tela tem fase'}>
                  {e === 'execucao' ? 'Execução (status)' : 'Pipeline de telas'}
                </button>
              ))}

            {isLista && <span className="fj-groupby-lbl" style={{ marginLeft: 8 }}>Ordem</span>}
            {isLista && sorts.map((s) => (
              <button key={s} type="button" onClick={() => aplicar({ sort: s })} aria-pressed={ordem === s}
                className={'fj-gb-btn' + (ordem === s ? ' active' : '')} data-testid={`trabalho-ordem-${s}`}
                title={s === 'rank' ? 'estado do trabalho, depois prioridade' : undefined}>
                {ORDEM_LABEL[s] ?? s}
              </button>
            ))}
            {isLista && (
              <button type="button" className="fj-gb-btn" onClick={() => setDenso((x) => !x)} data-testid="trabalho-densidade">
                {denso ? 'densidade: compacta' : 'densidade: normal'}
              </button>
            )}
            <button type="button" onClick={() => setFavOnly((f) => !f)} aria-pressed={favOnly}
              className={'fj-gb-btn fj-fav-toggle' + (favOnly ? ' active' : '')} title="Só favoritos" data-testid="trabalho-favoritos">
              <StarIcon className={'fj-fav-glyph' + (favOnly ? ' on' : '')} size={13} fill={favOnly ? 'currentColor' : 'none'} aria-hidden />
              favoritos
            </button>
            {saude && (
              <button type="button" className="fj-fchip" onClick={() => aplicar({ saude: null })} data-testid="trabalho-chip-saude">
                {SAUDE_LABEL[saude] ?? saude} ✕
              </button>
            )}
          </div>

          {/* Sem a DSL do protótipo (`is:p0 @CL ~FA-1 tipo:bug`): o backend busca
              por título, id, dono e módulo. Anunciar sintaxe que ele ignora seria
              afordância falsa — o placeholder diz o que a busca de fato faz. */}
          <form className="fj-search" onSubmit={submeterBusca}>
            <Search size={12} aria-hidden />
            <input value={busca} onChange={(e) => setBusca(e.target.value)}
              placeholder="Buscar por título, id, dono ou módulo…" data-testid="trabalho-busca" />
          </form>
        </div>

        <div className="fj-filterbar2">
          <span className="fj-groupby-lbl">Papel</span>
          <button type="button" onClick={() => aplicar({ papel: null })} aria-pressed={!papelAtivo}
            className={'fj-gb-btn' + (!papelAtivo ? ' active' : '')} data-testid="trabalho-papel-todos">todos</button>
          {papeis.map((p) => (
            <button key={p} type="button" onClick={() => aplicar({ papel: papelAtivo === p ? null : p })}
              aria-pressed={papelAtivo === p} className={'fj-gb-btn' + (papelAtivo === p ? ' active' : '')}
              title={PAPEL_INFO[p] ? `${PAPEL_INFO[p].nome} — ${PAPEL_INFO[p].desc}` : undefined}
              data-testid={`trabalho-papel-${p}`}>[{p}]</button>
          ))}

          {isLista && (
            <>
              <span className="fj-fb-sep" />
              <span className="fj-groupby-lbl">Visões</span>
              {visoes.map((v, i) => (
                <button key={`${v.name}-${i}`} type="button" className="fj-view-chip" title="aplicar visão"
                  onClick={() => router.get(`/forja/trabalho?${v.qs}`)}>
                  {v.name}
                  <span className="fj-view-x" role="button" tabIndex={0} aria-label={`Remover ${v.name}`}
                    onClick={(e) => { e.stopPropagation(); setVisoes((s) => s.filter((_, j) => j !== i)); }}
                    onKeyDown={(e) => { if (e.key === 'Enter') { e.stopPropagation(); setVisoes((s) => s.filter((_, j) => j !== i)); } }}>✕</span>
                </button>
              ))}
              <button type="button" className="fj-view-save" onClick={salvarVisao} data-testid="trabalho-salvar-visao">+ salvar visão</button>
            </>
          )}
        </div>

        {isLista ? (
          <TrabalhoLista
            tarefas={ordenadas} grupos={gruposLista} denso={denso}
            colapsados={colapsados} onColapsar={(g) => setColapsados((c) => alternar(c, g))}
            favoritos={favoritos} onFavoritar={(id) => setFavoritos((c) => alternar(c, id))}
            fixados={fixados} onFixar={(id) => setFixados((c) => alternar(c, id))}
            agents={agents} faseLabel={faseLabel}
          />
        ) : (
          <TrabalhoQuadro
            tasks={ordenadas} eixo={eixo} agents={agents}
            favoritos={favoritos} onFavoritar={(id) => setFavoritos((c) => alternar(c, id))}
          />
        )}

        <div className="fj-totalbar" data-testid="trabalho-total">
          <span><b>{totais.n}</b> issues</span>
          <span><b>{totais.p0}</b> P0</span>
          <span><b>{totais.bloqueados}</b> bloqueados</span>
          <span className="fj-total-rank" title="Ordem vem do backend (rank = estado do trabalho, depois prioridade). Fixado fura a fila.">
            ordem: {ORDEM_LABEL[ordem] ?? ordem}
            {fixados.size > 0 && <b> + {fixados.size} fixado{fixados.size > 1 ? 's' : ''}</b>}
          </span>
        </div>
      </div>
    </AppShellV2>
  );
}
