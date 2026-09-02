// Mesa de Aprovações — a superfície do funil de admissão (ADR 0368).
//
// @memcofre
//   tela: /forja/aprovacoes
//   module: Forja
//   adrs: 0368 (funil de admissão) · 0070 (Jira-style) · UI-0013 (Constituição UI v2) · 0388 (réplica primeiro)
//   permissao: jana.mcp.usage.all
//   paridade: fila = McpTask::AWAITING_HUMAN; decisão = TaskCrudService (mesmo
//             chokepoint da tool MCP `tasks-update`)
//
// ── 2026-09-02 · PARIDADE §11 Onda 3 — esta tela É a view `hoje` do protótipo ──
// Decisão [W] (ADR 0388, "réplica primeiro"): onde existe âncora, a aparência a
// entregar é a do protótipo, e regra de conformidade do DS vira item de lista, não
// veto. A fonte é `prototipo-ui/cowork/forja-aprova.jsx` — NÃO o `forja-page.jsx`
// que o charter apontava (ele só monta a view; o markup mora no `forja-aprova`).
// Provado SYNC contra o Cowork vivo em 2026-09-02T11:17Z, sha cc4cde3692da.
//
// Markup copiado 1:1: `.ap-page` › `.ap-head` (número-herói + sub + alerta de
// handoff) › `.ap-vivo` (faixa "Ao vivo no MCP") › `.ap-mesa` (fila à esquerda,
// painel do artefato à direita) › `.fj-hj-team` (placar por papel) › `.ap-toast`.
// As classes vêm do bundle `cowork-forja-bundle.css` (Onda 1), importado pelo
// <ForjaHub>. Saíram daqui o `PageHeader` canon e o `KpiGrid`: o protótipo põe o
// número no herói, e um segundo cabeçalho não existe na view.
//
// ⚠️ AS TRÊS DIVERGÊNCIAS DELIBERADAS (categoria, não bug de paridade — ADR 0385):
//
//   1. VERBOS DOS BOTÕES. O protótipo escreve "Aprovar aplicação"; aqui eles vêm
//      de `decisoes`, derivados de `McpTask::TRANSITIONS` — Admitir · Parquear ·
//      Recusar. Duas leis mandam nisso e nenhuma é de aparência: a ADR 0368 §6
//      proíbe "aprovado" (a palavra já significa outra coisa no
//      CAPTERRA-INVENTARIO) e o anti-hook do charter proíbe hardcodar a lista.
//      A ADR 0388 é de LAYOUT e diz, em D-5, que réplica não toca comportamento.
//
//   2. O CAMPO DE NOTA. No protótipo ele pertence ao "Devolver c/ comentário";
//      aqui ele abre na decisão que declara `exige_motivo` (Recusar, ADR 0368 §5).
//      Mesma caixa (`.ap-devolver`), mesmo lugar, dono diferente — o dono é o FSM.
//
//   3. OS 4 TIPOS DE SUBMISSÃO. Plano/Modificação/Design/Proposta são mock: só
//      `Proposta` tem estado canônico (`pending_approval`); os outros vivem em
//      `cowork_handoffs` e já têm dono. Fundir as duas fontes numa fila é decisão
//      [W] (ForjaAprovacoesService, docblock). Então não há `ArtefatoPlano`,
//      `ArtefatoMod` nem lightbox de screenshot: eles não teriam o que mostrar.
//
// ⚠️ `<ForjaHub>` é OBRIGATÓRIO em Page sob /forja/*: o hub esconde a topbar do
// AppShellV2 e desenha a própria faixa. Sem montar aqui, a tela abre sem
// navegação nenhuma — foi o que aconteceu com o Roadmap (Gantt) até 2026-08-06
// (lápide §5 "Navegação tem CINCO superfícies na Forja").
//
// ⚠️ POR QUE O DESFAZER ADIA O ENVIO EM VEZ DE REVERTER ─────────────────────
// O FSM não tem volta pra `pending_approval`: `TRANSITIONS` não a lista como
// destino de todo/backlog/cancelled. Um botão "Desfazer" que tentasse reverter
// bateria em 422 sempre — seria mecanismo ANUNCIANDO saída que não implementa
// (lápide §5 2026-07-30). Então a janela de 6s acontece ANTES do POST: durante
// ela nada foi persistido, e desfazer é só cancelar o timer. Modelo "Undo Send"
// do Gmail. O custo honesto: a decisão leva 6s pra valer — e é justamente esse
// atraso que torna o desfazer real em vez de decorativo.

import AppShellV2 from '@/Layouts/AppShellV2';
import { Deferred, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState, type CSSProperties } from 'react';
import ForjaHub from '../../team-mcp/Forja/_components/ForjaHub';

/** Faixa de espera calculada no backend (ForjaAprovacoesService::sla). */
type Sla = 'ok' | 'atencao' | 'urgente';

interface ItemFila {
  task_id: string;
  identifier: string | null;
  title: string;
  module: string | null;
  type: string | null;
  priority: string | null;
  owner: string | null;
  created_at: string | null;
  created_at_human: string | null;
  espera_min: number;
  sla: Sla;
}

/** Decisão possível — vem DERIVADA do FSM pelo backend, nunca hardcoded aqui. */
interface Decisao {
  status: string;
  verbo: string;
  descricao: string;
  exige_motivo: boolean;
  atalho: string;
}

/** Uma pessoa/agente da equipe na faixa "Ao vivo no MCP". */
interface AtorVivo {
  slug: string;
  pessoa: string;
  /** `mcp_actors.type` — human · ai_agent · service. O eixo do schema, não o do mock. */
  tipo: string;
  /** `mcp_actors.trust_level` — L0..L4. */
  confianca: string;
  status: 'executando' | 'aguardando' | 'offline';
  fazendo: string;
  custo_hoje: number;
  ha: string | null;
}

/** Uma linha do placar, por papel do loop (`cowork_handoffs.created_by`). */
interface LinhaPlacar {
  papel: string;
  sinal: string | null;
  sinal_ok: boolean;
  critique: number | null;
  critique_serie: number[];
  critique_baixo: boolean;
  entregas: number;
  retrabalho: number;
  retrabalho_pct: number;
  /** Sem fonte por papel — o backend manda `null` de propósito. Ver o docblock do service. */
  sessoes_hoje: number | null;
  custo_hoje: number | null;
  quota_dia: number | null;
}

interface Props {
  titulo: string;
  subtitle: string;
  decisoes: Decisao[];
  // Props caras chegam por Inertia::defer → `undefined` no 1º paint. Default no
  // destructuring pra não crashar antes do defer (skill inertia-defer-default;
  // o sintoma sem isso é tela branca — PR #1940).
  fila?: ItemFila[];
  contagem?: number;
  aoVivo?: AtorVivo[];
  placar?: LinhaPlacar[];
  handoffsProblema?: number;
}

/** Segundos de arrependimento antes da decisão sair pro servidor. */
const JANELA_DESFAZER_S = 6;

/** Rótulo do estado na faixa ao vivo — o mesmo mapa do protótipo (`st`). */
const ESTADO_VIVO: Record<AtorVivo['status'], { label: string; cls: string }> = {
  executando: { label: 'executando', cls: 'run' },
  aguardando: { label: 'espera você', cls: 'wait' },
  offline: { label: 'offline', cls: 'off' },
};

/** Matiz do avatar por tipo de ator — o que `mcp_actors` declara, não o nível do mock. */
const MATIZ_TIPO: Record<string, number> = {
  human: 250,
  ai_agent: 295,
  service: 195,
};

/**
 * Papéis do loop e suas cores — copiado do `FORJA_ACTORS` do protótipo
 * (`forja-data.jsx:6`). Isto é DESIGN, não dado: a cor e o nome de cada papel
 * são decisão do Cowork, e o `--rc` do `.fj-role-tag` os consome. O que é DADO
 * (quantas entregas, que critique, quando foi o último sinal) vem do backend.
 * Papel fora do mapa cai no cinza neutro em vez de sumir.
 */
const PAPEIS: Record<string, { nome: string; cor: string; agente: boolean }> = {
  W: { nome: 'Wagner', cor: 'oklch(0.57 0.16 25)', agente: false },
  CC: { nome: 'Claude Cowork', cor: 'oklch(0.55 0.15 295)', agente: true },
  CD: { nome: 'Claude Design', cor: 'oklch(0.60 0.13 60)', agente: true },
  CL: { nome: 'Claude Code', cor: 'oklch(0.52 0.10 195)', agente: true },
  CA: { nome: 'Claude A11y', cor: 'oklch(0.55 0.13 150)', agente: true },
  AN: { nome: 'Claude Analista', cor: 'oklch(0.50 0.10 195)', agente: true },
};

const PAPEL_NEUTRO = { nome: '', cor: 'oklch(0.55 0.02 250)', agente: true };

/** Selo do papel (`fj-role`) — o `RoleBadge` do protótipo (`forja-page.jsx:60`). */
function SeloPapel({ papel }: { papel: string }) {
  const a = PAPEIS[papel] ?? PAPEL_NEUTRO;
  return (
    <span
      className="fj-role"
      style={{ '--rc': a.cor } as CSSProperties}
      title={a.nome ? `${a.nome} · ${a.agente ? 'agente' : 'humano'}` : papel}
    >
      <span className="fj-role-av" style={{ background: a.cor }}>
        {a.agente ? (
          <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.4" aria-hidden>
            <rect x="4" y="8" width="16" height="11" rx="2.5" />
            <path d="M12 4v4M9 13h.01M15 13h.01" />
          </svg>
        ) : (
          <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="2.4" aria-hidden>
            <circle cx="12" cy="8" r="3.4" />
            <path d="M5 20c0-3.3 3-5.5 7-5.5s7 2.2 7 5.5" />
          </svg>
        )}
      </span>
      <span className="fj-role-tag">[{papel}]</span>
      {a.nome && <span className="fj-role-name">{a.nome}</span>}
    </span>
  );
}

const brl = (v: number) => `R$ ${v.toFixed(2).replace('.', ',')}`;

/** Iniciais do avatar (`ap-av`) — mesma regra do protótipo. */
function iniciais(nome: string): string {
  return nome
    .split(/\s+/)
    .map((p) => p[0] ?? '')
    .slice(0, 2)
    .join('')
    .toUpperCase();
}

/** Sparkline do critique (`fj-spark`) — polilinha, igual ao `ApSpark` do protótipo. */
function Spark({ data }: { data: number[] }) {
  if (data.length < 2) return null;
  const pts = data
    .map((d, i) => {
      const n = Math.max(0, Math.min(1, (d - 70) / 30));
      return `${i * (60 / (data.length - 1))},${17 - n * 15}`;
    })
    .join(' ');
  return (
    <svg className="fj-spark" viewBox="0 0 60 18" preserveAspectRatio="none" aria-hidden>
      <polyline points={pts} fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinejoin="round" />
    </svg>
  );
}

interface Pendente {
  item: ItemFila;
  decisao: Decisao;
  motivo: string;
  restam: number;
}

export default function Aprovacoes({
  titulo,
  subtitle,
  decisoes,
  fila = [],
  contagem = 0,
  aoVivo = [],
  placar = [],
  handoffsProblema = 0,
}: Props) {
  const [motivo, setMotivo] = useState('');
  const [erro, setErro] = useState<string | null>(null);
  const [enviando, setEnviando] = useState(false);
  /** Decisão tomada, ainda NÃO enviada — a janela de arrependimento. */
  const [pendente, setPendente] = useState<Pendente | null>(null);
  /** Caixa de nota aberta (`ap-devolver`). Abre pela decisão que exige motivo. */
  const [anotando, setAnotando] = useState(false);
  const notaRef = useRef<HTMLTextAreaElement | null>(null);

  // Item em foco na mesa. `null` = ninguém escolheu ainda, e aí vale o mais antigo
  // (`fila[0]`) — a ordem é do backend, não da UI. Escolher na fila só MOVE o foco;
  // não reordena e não decide nada. Se o escolhido sai da fila (decidido aqui ou por
  // outra sessão), o `find` falha e o foco volta sozinho pro mais antigo.
  const [selId, setSelId] = useState<string | null>(null);
  const atual = pendente ? null : (fila.find((i) => i.task_id === selId) ?? fila[0] ?? null);

  // Item novo na mesa = motivo/erro/nota do anterior não valem mais.
  useEffect(() => {
    setMotivo('');
    setErro(null);
    setAnotando(false);
  }, [atual?.task_id]);

  /** Manda a decisão pro servidor. Só chamado quando a janela de desfazer expira. */
  const enviar = useCallback((p: Pendente) => {
    setEnviando(true);

    router.post(
      `/forja/aprovacoes/${p.item.task_id}/decidir`,
      { destino: p.decisao.status, motivo: p.motivo },
      {
        preserveScroll: true,
        onError: (errs) =>
          setErro(
            Object.values(errs)[0] ??
              `Não foi possível ${p.decisao.verbo.toLowerCase()} ${p.item.identifier ?? p.item.task_id}.`,
          ),
        onFinish: () => {
          setEnviando(false);
          router.reload({ only: ['fila', 'contagem', 'aoVivo'] });
        },
      },
    );
  }, []);

  // `enviar` numa ref pro efeito do contador não depender dela (e não reiniciar
  // o timer a cada render).
  const enviarRef = useRef(enviar);
  useEffect(() => {
    enviarRef.current = enviar;
  }, [enviar]);

  // Contagem regressiva. Ao zerar, a decisão SAI — antes disso ela não existe
  // pra ninguém além desta tela.
  useEffect(() => {
    if (!pendente) return;

    if (pendente.restam <= 0) {
      enviarRef.current(pendente);
      setPendente(null);
      return;
    }

    const t = setTimeout(() => setPendente((p) => (p ? { ...p, restam: p.restam - 1 } : null)), 1000);
    return () => clearTimeout(t);
  }, [pendente]);

  const decidir = useCallback(
    (decisao: Decisao) => {
      if (!atual || enviando || pendente) return;

      if (decisao.exige_motivo && motivo.trim() === '') {
        // Espelha a trava do backend (ADR 0368 §5) pra dar o retorno na hora —
        // mas quem de fato barra é o TaskCrudService, não este if. Abre a caixa
        // em vez de só reclamar: o [W] precisa do lugar pra escrever.
        setAnotando(true);
        setTimeout(() => notaRef.current?.focus(), 30);
        setErro('Recusar exige motivo — ele vai pro inventário e evita que a mesma proposta volte em três meses.');
        return;
      }

      setErro(null);
      setAnotando(false);
      setPendente({ item: atual, decisao, motivo: motivo.trim(), restam: JANELA_DESFAZER_S });
    },
    [atual, enviando, pendente, motivo],
  );

  /** Desfazer = cancelar antes do envio. Nada foi persistido, então é de verdade. */
  const desfazer = useCallback(() => setPendente(null), []);

  // Atalhos: cada decisão traz o seu (a/d/x), vindos do backend junto do FSM.
  // `j`/`k` andam na fila — navegação, não decisão: não declaram fluxo nenhum.
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      const alvo = e.target as HTMLElement | null;
      // Não sequestrar tecla enquanto o [W] digita o motivo.
      if (alvo && ['INPUT', 'TEXTAREA', 'SELECT'].includes(alvo.tagName)) return;
      if (alvo?.isContentEditable) return;
      if (e.metaKey || e.ctrlKey || e.altKey) return;

      if ((e.key === 'j' || e.key === 'k') && fila.length > 0) {
        e.preventDefault();
        const i = atual ? fila.findIndex((x) => x.task_id === atual.task_id) : 0;
        const alvoIdx = e.key === 'j' ? Math.min(fila.length - 1, i + 1) : Math.max(0, i - 1);
        // `noUncheckedIndexedAccess`: o indice esta clampado ao tamanho da fila,
        // mas o compilador nao prova isso — entao o item ausente e tratado, nao suposto.
        const proximo = fila[alvoIdx];
        if (proximo) setSelId(proximo.task_id);
        return;
      }

      const d = decisoes.find((x) => x.atalho === e.key.toLowerCase());
      if (d) {
        e.preventDefault();
        decidir(d);
      }
    };

    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [decisoes, decidir, fila, atual]);

  /** O herói conta o que está na mesa. `contagem` é a fonte; a fila é o teto de 200. */
  const naMesa = contagem || fila.length;

  const dicas = useMemo(
    () => decisoes.map((d) => ({ atalho: d.atalho, verbo: d.verbo.toLowerCase() })),
    [decisoes],
  );

  return (
    <AppShellV2 title={`Forja — ${titulo}`} breadcrumbItems={[{ label: 'Forja' }, { label: titulo }]}>
      <ForjaHub active="aprovacoes" pendencias={naMesa} />

      <div className="ap-page">
        {/* ── cabeçalho: o número é o herói ─────────────────────────────── */}
        <div className="ap-head">
          <div className="fj-hj-n" data-testid="mesa-heroi">
            <b>{naMesa}</b>
            <span>esperando o seu aval</span>
          </div>
          <p className="fj-hj-sub">
            {subtitle} Sua equipe trabalha no Claude Code conectada ao MCP; nada aplica sem você.
            {dicas.map((d) => (
              <span key={d.atalho}>
                {' '}
                <kbd>{d.atalho}</kbd> {d.verbo}
              </span>
            ))}
            .
          </p>
          {handoffsProblema > 0 && (
            <button
              type="button"
              className="ap-handoff-alert"
              data-testid="mesa-handoff-alerta"
              onClick={() => router.visit('/forja/handoffs')}
            >
              {handoffsProblema} handoff{handoffsProblema > 1 ? 's' : ''} com problema →
            </button>
          )}
        </div>

        {/* ── faixa "Ao vivo no MCP" ────────────────────────────────────── */}
        <Deferred data="aoVivo" fallback={null}>
          {aoVivo.length > 0 && (
            <div className="ap-vivo" data-testid="mesa-ao-vivo">
              <span className="ap-vivo-lbl">Ao vivo no MCP</span>
              {aoVivo.map((p) => {
                const st = ESTADO_VIVO[p.status];
                return (
                  <div
                    key={p.slug}
                    className={`ap-vivo-card ${st.cls}`}
                    title={p.fazendo}
                    data-testid={`mesa-vivo-${p.slug}`}
                  >
                    <span
                      className="ap-av"
                      style={{ '--ah': MATIZ_TIPO[p.tipo] ?? 250 } as CSSProperties}
                      title={`${p.pessoa} · ${p.tipo} · ${p.confianca}`}
                    >
                      {iniciais(p.pessoa)}
                    </span>
                    <div className="ap-vivo-tx">
                      <span className="ap-vivo-nome">
                        <span className="ap-vivo-n-tx">{p.pessoa}</span>
                        <span
                          className="ap-nivel"
                          style={{ '--ah': MATIZ_TIPO[p.tipo] ?? 250 } as CSSProperties}
                        >
                          {p.confianca}
                        </span>
                      </span>
                      <span className="ap-vivo-fazendo">{p.fazendo}</span>
                    </div>
                    <div className="ap-vivo-meta">
                      <span className={`ap-vivo-st ${st.cls}`}>
                        <i />
                        {st.label}
                      </span>
                      <span className="ap-vivo-custo">
                        {brl(p.custo_hoje)} hoje{p.ha ? ` · ${p.ha}` : ''}
                      </span>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </Deferred>

        {erro && (
          <p role="alert" className="ap-regra" data-testid="mesa-erro">
            {erro}
          </p>
        )}

        {/* ── a mesa: fila à esquerda, artefato à direita ───────────────── */}
        {!atual && !pendente ? (
          <div className="fj-mcp-card" data-testid="mesa-vazia">
            <div className="fj-hj-zero">
              <p>
                <b>Fila zerada.</b> Ninguém espera você — quando uma proposta entrar no funil, ela
                aparece aqui, mais antiga primeiro.
              </p>
            </div>
          </div>
        ) : (
          atual && (
            <div className="ap-mesa">
              {/* `role=listbox/option` + `tabIndex` + `onKeyDown`: o protótipo usa
                  `<li onClick>` cru, que não abre por teclado. Aqui a ESTRUTURA e a
                  classe são as dele (o `.ap-item` é `display:flex` e o `:last-child`
                  tira a última borda — só funciona com a classe no próprio `<li>`),
                  e a operabilidade é acrescentada por cima. A ADR 0388 tira o veto
                  da CONFORMIDADE do DS, não da acessibilidade: a versão anterior
                  desta tela já era navegável por teclado, e réplica não regride isso. */}
              <ul className="ap-fila" data-testid="mesa-fila" role="listbox" aria-label="Fila de aprovações">
                {fila.map((i) => (
                  <li
                    key={i.task_id}
                    className={`ap-item${i.task_id === atual.task_id ? ' sel' : ''}`}
                    role="option"
                    tabIndex={0}
                    aria-selected={i.task_id === atual.task_id}
                    onClick={() => setSelId(i.task_id)}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        setSelId(i.task_id);
                      }
                    }}
                    data-testid={`mesa-fila-item-${i.task_id}`}
                  >
                    <span className="ap-av sm" title={i.owner ?? 'sem dono'}>
                      {iniciais(i.owner ?? '—')}
                    </span>
                    <div className="ap-item-tx">
                      <span className="ap-item-top">
                        <b>{i.owner ?? 'sem dono'}</b>
                        {i.priority && <span className="ap-nivel">{i.priority}</span>}
                        {i.module && <span className="ap-tipo">{i.module}</span>}
                      </span>
                      <span className="ap-item-t">{i.title}</span>
                    </div>
                    <span
                      className={`ap-espera${i.sla === 'urgente' ? ' bad' : i.sla === 'atencao' ? ' warn' : ''}`}
                      title={
                        i.sla === 'urgente'
                          ? 'SLA estourado: espera acima de 2h'
                          : i.sla === 'atencao'
                            ? 'esperando há mais de 30 min'
                            : 'esperando há pouco'
                      }
                    >
                      {i.created_at_human ?? `${i.espera_min} min`}
                    </span>
                  </li>
                ))}
              </ul>

              <section className="ap-painel" data-testid="mesa-artefato">
                <header className="ap-p-head">
                  <div className="ap-p-head-l">
                    {atual.type && <span className="ap-tipo lg">{atual.type}</span>}
                    <h2>{atual.title}</h2>
                    <p className="ap-p-sub">
                      <b>{atual.owner ?? 'sem dono'}</b>
                      {atual.module ? ` · ${atual.module}` : ''} · esperando{' '}
                      {atual.created_at_human ?? `${atual.espera_min} min`}
                    </p>
                  </div>
                  <span className="ap-item-id mono">{atual.identifier ?? atual.task_id}</span>
                </header>

                {/* O artefato: o que `mcp_tasks` guarda de uma proposta. Sem diff, sem
                    passos e sem screenshot — esses vivem em `cowork_handoffs`, e
                    fundir as duas fontes é decisão [W] (divergência 3 do cabeçalho). */}
                <div className="ap-art">
                  <p className="ap-art-obj">{atual.title}</p>
                  <div className="ap-art-meta">
                    {atual.module && (
                      <span>
                        módulo <b>{atual.module}</b>
                      </span>
                    )}
                    {atual.priority && (
                      <span>
                        prioridade <b>{atual.priority}</b>
                      </span>
                    )}
                    <span>
                      id <b className="mono">{atual.identifier ?? atual.task_id}</b>
                    </span>
                  </div>
                </div>

                <p className="ap-regra">
                  Sua decisão move a proposta no funil de admissão — recusar exige motivo, que vai
                  pro inventário e evita que a mesma capacidade volte em três meses.
                </p>

                {anotando && (
                  <div className="ap-devolver">
                    <textarea
                      ref={notaRef}
                      id="mesa-motivo"
                      data-testid="mesa-motivo"
                      rows={2}
                      value={motivo}
                      onChange={(e) => setMotivo(e.target.value)}
                      placeholder="Por que esta proposta é recusada? O texto vai pro inventário."
                      aria-label="Motivo da recusa"
                    />
                    <button type="button" className="os-btn ghost" onClick={() => setAnotando(false)}>
                      Cancelar
                    </button>
                  </div>
                )}

                {/* Os verbos vêm do FSM, não do protótipo — divergência 1 do cabeçalho. */}
                <footer className="ap-acoes">
                  {decisoes.map((d, idx) => (
                    <button
                      key={d.status}
                      type="button"
                      data-testid={`mesa-decisao-${d.status}`}
                      className={
                        idx === 0
                          ? 'os-btn primary ap-ok'
                          : d.exige_motivo
                            ? 'os-btn ghost ap-no'
                            : 'os-btn ghost'
                      }
                      disabled={enviando}
                      title={d.descricao}
                      onClick={() => decidir(d)}
                    >
                      {d.verbo}
                      <kbd>{d.atalho}</kbd>
                    </button>
                  ))}
                </footer>
              </section>
            </div>
          )
        )}

        {/* ── placar por papel do loop ──────────────────────────────────── */}
        <Deferred data="placar" fallback={null}>
          {placar.length > 0 && (
            <section className="fj-mcp-card fj-hj-team" data-testid="mesa-placar">
              <div className="fj-hj-team-head">
                <h3>Equipe de agentes · placar</h3>
                {placar.some((a) => !a.sinal_ok) && (
                  <span className="fj-hj-team-alert">
                    {placar.filter((a) => !a.sinal_ok).length} sem sinal
                  </span>
                )}
                <span className="fj-hj-team-note">
                  cowork_handoffs + gates — medido, nada auto-relatado
                </span>
              </div>
              <table className="fj-team-tbl">
                <thead>
                  <tr>
                    <th>Agente</th>
                    <th>Sinal</th>
                    <th className="num">Sessões hoje</th>
                    <th>Custo hoje / quota</th>
                    <th>Critique F1.5</th>
                    <th className="num">Retrabalho</th>
                    <th className="num">Entregas 7d</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {placar.map((a) => (
                    <tr key={a.papel} className={a.sinal_ok ? '' : 'warn'}>
                      <td>
                        <SeloPapel papel={a.papel} />
                      </td>
                      <td>
                        <span className={`fj-hb${a.sinal_ok ? '' : ' bad'}`}>
                          <span className="fj-hb-dot" />
                          {a.sinal ?? 'sem sinal'}
                        </span>
                      </td>
                      {/* Sem fonte por papel: não existe vínculo papel→usuário no schema,
                          e `mcp_cc_sessions`/`mcp_audit_log`/`mcp_quotas` são por usuário.
                          Inventar o vínculo seria dado fantasma. */}
                      <td className="num mono" title="Sem fonte: mcp_cc_sessions é por usuário e não há vínculo papel→usuário no schema.">
                        —
                      </td>
                      <td title="Sem fonte: mcp_audit_log e mcp_quotas são por usuário e não há vínculo papel→usuário no schema.">
                        —
                      </td>
                      <td>
                        <span className="fj-crit">
                          <b className={a.critique_baixo ? 'low' : ''}>{a.critique ?? '—'}</b>
                          <Spark data={a.critique_serie} />
                        </span>
                      </td>
                      <td className="num mono">
                        {a.retrabalho}
                        {a.retrabalho > 0 && <small className="fj-ret-pct"> · {a.retrabalho_pct}%</small>}
                      </td>
                      <td className="num mono">{a.entregas}</td>
                      {/* 8ª coluna do protótipo: a saída pra quem está mudo. */}
                      <td className="act">
                        {!a.sinal_ok && (
                          <button
                            type="button"
                            className="os-btn ghost"
                            onClick={() => router.visit('/forja/handoffs')}
                          >
                            verificar
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
              <p className="fj-hj-team-foot">
                Critique = <code>gate_status.critique_score</code> do ack (o mesmo piso 80 que o
                <code> handoff-ack</code> exige) · retrabalho = handoff <code>rejected</code>{' '}
                devolvido ao autor · janela de 7 dias.
              </p>
            </section>
          )}
        </Deferred>

        {/* ── toast com a janela de arrependimento ──────────────────────── */}
        {pendente && (
          <div className="ap-toast" data-testid="mesa-desfazer" aria-live="polite">
            {pendente.item.identifier ?? pendente.item.task_id} · {pendente.decisao.verbo} — vale em{' '}
            {pendente.restam}s
            <button type="button" className="ap-undo" onClick={desfazer} data-testid="mesa-desfazer-btn">
              Desfazer
            </button>
          </div>
        )}
      </div>
    </AppShellV2>
  );
}
