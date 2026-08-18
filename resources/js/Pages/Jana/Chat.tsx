// @memcofre
//   tela: /copiloto
//   stories: US-COPI-001, US-COPI-002, US-COPI-003, US-COPI-MEM-007
//   rules: R-COPI-001, R-COPI-MEM-005
//   adrs: 0026, 0031, 0032, 0034, 0035, 0036, 0039 (Cockpit), UI-0008
//   tests: tests/Feature/Modules/Copiloto/AdapterResolverTest, tests/Feature/Modules/Copiloto/BridgeMemoriaChatTest
//   status: implementada (Sprint 1: migrada pra AppShellV2)
//   module: Copiloto

import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
  Bell, ChevronLeft, Cog, Inbox, List, Pin, Plus, Search, SlidersHorizontal,
} from 'lucide-react';
import { toast } from 'sonner';

import AppShellV2 from '@/Layouts/AppShellV2';
import { ThreadHeader } from '@/Components/cockpit/Thread';
import { JanaAreaHeader } from './components/JanaAreaHeader';
import {
  AvatarRef,
  BusinessOpt,
  ConversaFoco,
  ConversaResumo,
  Mensagem as CockpitMensagem,
  Rotina,
} from '@/Components/cockpit/shared';
import { JanaAssistantUiChat } from './_components/AssistantUiChat';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
  Card, CardContent, CardFooter, CardHeader, CardTitle,
} from '@/Components/ui/card';

// ── tipos do backend Copiloto ──────────────────────────────────────────

interface MensagemBackend {
  id: number;
  role: 'user' | 'assistant' | 'system';
  content: string;
  created_at: string;
  propostas?: Proposta[];
}

interface Proposta {
  nome: string;
  metrica: string;
  valor_alvo: number;
  periodo: string;
  dificuldade: 'facil' | 'realista' | 'ambicioso';
  racional: string;
  dependencias: string[];
}

interface Sugestao {
  id: number;
  payload_json: Proposta;
}

interface ConversaBackend {
  id: number;
  titulo: string;
  status: string;
  iniciada_em: string;
}

interface Props {
  // Shell props (vindos do shellPropsFor() do controller)
  businessNome: string;
  businesses: BusinessOpt[];
  usuarioNome: string;
  usuarioNomeCurto: string;
  usuarioEmail: string;
  usuarioCargo: string;
  usuarioIniciais: string;
  conversas: {
    fixadas: ConversaResumo[];
    rotinas: Rotina[];
    recentes: ConversaResumo[];
  };
  // Props específicos do Copiloto Chat
  conversa: ConversaBackend;
  mensagens: MensagemBackend[];
  sugestoesPendentes?: Sugestao[];
}

// ── helpers ────────────────────────────────────────────────────────────

const DIFICULDADE_CONFIG: Record<string, { label: string; className: string }> = {
  facil:     { label: 'Fácil',     className: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' },
  realista:  { label: 'Realista',  className: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300' },
  ambicioso: { label: 'Ambicioso', className: 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-300' },
};

function formatCurrency(value: number) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
}

// Gradiente do avatar do Copiloto (usado em todas msgs do assistant)
const COPILOTO_AVATAR: AvatarRef = { iniciais: 'JA', gradId: 17 };

// ── histórico recolhível: persistência + breakpoint ────────────────────
// Charter §Goals: localStorage com prefix `oimpresso.jana.*` (nunca sessionStorage).
const HIST_KEY = 'oimpresso.jana.hist';
const HIST_BREAKPOINT = '(max-width: 1100px)';

function lerHistAberto(): boolean {
  try {
    return localStorage.getItem(HIST_KEY) !== '0';
  } catch {
    return true; // storage bloqueado (modo privado/iframe) → default aberto
  }
}

function gravarHistAberto(aberto: boolean): void {
  try {
    localStorage.setItem(HIST_KEY, aberto ? '1' : '0');
  } catch {
    /* storage bloqueado — o estado segue só em memória nesta sessão */
  }
}

function mediaEstreita(): MediaQueryList | null {
  return typeof window !== 'undefined' && typeof window.matchMedia === 'function'
    ? window.matchMedia(HIST_BREAKPOINT)
    : null;
}

// Adaptador: converte Mensagem do backend (role/content) → Mensagem do Cockpit (autor/texto)
function adaptarMensagem(m: MensagemBackend): CockpitMensagem {
  const dt = new Date(m.created_at);
  const hora = dt.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  // Day separator: usa "Hoje" se a msg é do mesmo dia que agora; senão data dd/mm
  const hoje = new Date();
  const sameDay =
    dt.getFullYear() === hoje.getFullYear() &&
    dt.getMonth() === hoje.getMonth() &&
    dt.getDate() === hoje.getDate();
  const dia = sameDay
    ? 'Hoje'
    : dt.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });

  if (m.role === 'user') {
    return {
      id: m.id,
      autor: 'me',
      texto: m.content,
      hora,
      dia,
      lida: true,
    };
  }
  // assistant ou system → them, com avatar do Copiloto
  return {
    id: m.id,
    autor: 'them',
    texto: m.content,
    hora,
    dia,
    whoAvatar: COPILOTO_AVATAR,
    whoNome: 'Jana',
  };
}

// ── PropostaCard (componente especifico do Copiloto, mantido inline) ───

function PropostaCard({ sugestao }: { sugestao: Sugestao }) {
  const p = sugestao.payload_json;
  const dif = DIFICULDADE_CONFIG[p.dificuldade] ?? DIFICULDADE_CONFIG['realista']!;

  function escolher() {
    router.post(`/ia/sugestoes/${sugestao.id}/escolher`, {}, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => toast.success('Meta criada com sucesso!'),
      onError: () => toast.error('Erro ao escolher meta.'),
    });
  }

  function rejeitar() {
    router.post(`/ia/sugestoes/${sugestao.id}/rejeitar`, {}, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => toast.info('Proposta rejeitada.'),
    });
  }

  return (
    <Card className="flex flex-col gap-2 bg-card">
      <CardHeader className="pb-2">
        <div className="flex items-start justify-between gap-2">
          <CardTitle className="text-base leading-tight">{p.nome}</CardTitle>
          <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${dif.className}`}>
            {dif.label}
          </span>
        </div>
        <div className="flex flex-wrap gap-1 pt-1">
          <Badge variant="outline">{p.metrica}</Badge>
          <Badge variant="outline">{p.periodo}</Badge>
          <Badge variant="secondary">{formatCurrency(p.valor_alvo)}</Badge>
        </div>
      </CardHeader>
      <CardContent className="pb-2">
        <p className="line-clamp-3 text-sm text-muted-foreground">{p.racional}</p>
        {p.dependencias.length > 0 && (
          <div className="mt-2 flex flex-wrap gap-1">
            {p.dependencias.map((dep, i) => (
              <span key={i} className="rounded bg-muted px-1.5 py-0.5 text-xs text-muted-foreground">
                {dep}
              </span>
            ))}
          </div>
        )}
      </CardContent>
      <CardFooter className="flex gap-2 pt-0">
        <Button
          size="sm"
          className="flex-1"
          onClick={escolher}
          aria-label={`Escolher meta ${p.nome}, dificuldade ${p.dificuldade}`}
        >
          Escolher esta meta
        </Button>
        <Button size="sm" variant="outline" onClick={rejeitar} aria-label="Rejeitar proposta">
          Rejeitar
        </Button>
      </CardFooter>
    </Card>
  );
}

// ── pagina ──────────────────────────────────────────────────────────────

export default function Chat({
  businessNome,
  businesses,
  usuarioNome,
  usuarioNomeCurto,
  usuarioEmail,
  usuarioCargo,
  usuarioIniciais,
  conversas,
  conversa,
  mensagens,
  sugestoesPendentes = [],
}: Props) {
  // Adapta mensagens só pra metadata visual da sidebar de conversas (avatar,
  // último excerto). O Thread real é renderizado pela lib assistant-ui.
  const mensagensCockpit = useMemo(
    () => mensagens.map(adaptarMensagem),
    [mensagens],
  );

  // ConversaFoco — informacional pra ThreadHeader/sidebar (não usado no Thread real)
  const conversaFoco: ConversaFoco = useMemo(() => ({
    id: String(conversa.id),
    titulo: conversa.titulo,
    tipo: 'copiloto',
    online: true,
    avatar: COPILOTO_AVATAR,
    mensagens: mensagensCockpit,
  }), [conversa, mensagensCockpit]);

  // Histórico recolhível (protótipo jana-merge.jsx §JmConversa). Persistido em
  // localStorage prefix `oimpresso.jana.*` — Charter §Goals (nunca sessionStorage).
  const [histAberto, setHistAberto] = useState(lerHistAberto);

  // Tela estreita (≤1100px): o histórico vira sobreposição em vez de sumir.
  // Antes o CSS fazia `display:none` em ≤1023px e a lista ficava INALCANÇÁVEL —
  // não dava pra trocar de conversa. O rail de 40px sempre carrega o atalho.
  const [estreito, setEstreito] = useState(() => mediaEstreita()?.matches ?? false);

  useEffect(() => {
    const mq = mediaEstreita();
    if (!mq) return;
    const onChange = (e: MediaQueryListEvent) => {
      setEstreito(e.matches);
      if (e.matches) setHistAberto(false);
    };
    if (mq.matches) setHistAberto(false);
    mq.addEventListener('change', onChange);
    return () => mq.removeEventListener('change', onChange);
  }, []);

  function toggleHist() {
    setHistAberto((v) => {
      const nv = !v;
      gravarHistAberto(nv);
      return nv;
    });
  }

  function selectConv(id: string) {
    // D-14: partial reload — troca de conversa só re-busca conversa/mensagens/
    // sugestões (ref PR #3889). Shell (conversas, businesses, usuário) não
    // trafega de novo — highlight da lista é client-side via activeConvId.
    router.get(`/ia/conversas/${id}`, {}, {
      preserveScroll: true,
      preserveState: true,
      only: ['conversa', 'mensagens', 'sugestoesPendentes'],
    });
  }

  return (
    <AppShellV2
      title="Jana — Chat"
      business={{ nome: businessNome, opcoes: businesses }}
      user={{
        nome: usuarioNome,
        nomeCurto: usuarioNomeCurto,
        email: usuarioEmail,
        cargo: usuarioCargo,
        iniciais: usuarioIniciais,
      }}
      conversas={conversas}
      conversaFoco={conversaFoco}
      activeConvId={String(conversa.id)}
      onSelectConv={selectConv}
    >
      <Head title="Jana — Chat" />

      {/* JanaAreaHeader — header sticky com tabs Dashboard | Chat (Wagner
          2026-05-18). Espelha app.jsx Header function do protótipo Cockpit.
          Componente compartilhado com Dashboard.tsx. Gate F1.5:
          memory/requisitos/Jana/Chat-header-tabs-visual-comparison.md */}
      <JanaAreaHeader active="chat" />

      {/* Master/detail interno — UI-0011 (sidebar single-pane) migrou conv
          switcher pra dentro da própria Page. 320px lista + 1fr thread. */}
      <div
        className={
          'copiloto-chat-layout'
          + (histAberto ? '' : ' hist-collapsed')
          + (estreito && histAberto ? ' hist-overlay' : '')
        }
      >
        <ConvSidePanel
          fixadas={conversas.fixadas}
          recentes={conversas.recentes}
          activeConvId={String(conversa.id)}
          onSelectConv={selectConv}
          aberto={histAberto}
          estreito={estreito}
          onToggle={toggleHist}
        />
        <div className="copiloto-chat-thread">
          <ThreadHeader conv={conversaFoco} />
          <JanaAssistantUiChat
            conversaId={conversa.id}
            mensagensIniciais={mensagens}
            belowThread={
              sugestoesPendentes.length > 0 ? (
                <div className="px-5 pb-3 space-y-2">
                  <p className="text-xs font-medium uppercase tracking-wide" style={{ color: 'var(--text-mute)' }}>
                    Propostas de metas
                  </p>
                  <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {sugestoesPendentes.map((s) => (
                      <PropostaCard key={s.id} sugestao={s} />
                    ))}
                  </div>
                </div>
              ) : null
            }
          />
        </div>

        {/* Scrim da sobreposição (≤1100px) — clicar fora fecha o histórico. */}
        {estreito && histAberto && (
          <button
            type="button"
            className="copiloto-chat-scrim"
            aria-label="Fechar histórico"
            onClick={toggleHist}
          />
        )}
      </div>
    </AppShellV2>
  );
}

// ── ConvSidePanel — lista de conversas migrada da SidebarChat removida ─
//
// Layout enriquecido conforme Cowork chat.jsx (snapshot 2026-05-09) + Charter
// §Goals: header + search client-side + 4 tabs filtro (labels Charter Todas/
// Minhas/Compartilhadas/Arquivadas — não OS/Equipe/Clientes do Cowork que
// conflitam com semântica Jana IA). Gate F1.5 em Chat-visual-comparison.md.
//
// FILTROS (2026-08-07) — eram 4 abas decorativas mostrando "Em breve"; hoje são
// 2 que filtram de verdade. As outras duas não eram "backend ainda não expõe":
//   · "Minhas" era TAUTOLÓGICA — buildConversasListPayload já faz
//     `Conversa::where('user_id', $userId)`, então tudo na lista já é do usuário.
//   · "Compartilhadas" seria SEMPRE vazia — não existe compartilhamento de
//     conversa: o ChatController tem `abort_unless($conversa->user_id ===
//     auth()->id(), 403)` em 4 pontos. Ninguém lê a conversa de outro, nem admin.
// "Arquivadas" ficou porque a coluna `status` já existia, já vinha no get() e o
// PATCH jana.conversas.update já a aceita — só não trafegava pro frontend.
// Reabrir "Compartilhadas" exige modelar participantes + afrouxar o 403: é PR
// próprio e decisão [W], não flag esquecida.

type ConvTab = 'todas' | 'arquivadas';

const CONV_TABS: Array<{ id: ConvTab; label: string }> = [
  { id: 'todas',      label: 'Todas'      },
  { id: 'arquivadas', label: 'Arquivadas' },
];

const STATUS_ARQUIVADA = 'arquivada';

function isArquivada(c: ConversaResumo): boolean {
  return c.status === STATUS_ARQUIVADA;
}

// Normaliza pra busca acento-insensitive
function normalizeSearch(s: string): string {
  return s.toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '');
}

// Exportado (além do default `Chat`) só pra ser exercitável em jsdom —
// tests/jana-chat-conversas.test.tsx cobre filtro + J/K + ⌘⇧H + aria-live.
// Inertia resolve a Page pelo default export; named export extra é inerte.
export function ConvSidePanel({
  fixadas,
  recentes,
  activeConvId,
  onSelectConv,
  aberto,
  estreito,
  onToggle,
}: {
  fixadas: ConversaResumo[];
  recentes: ConversaResumo[];
  activeConvId: string;
  onSelectConv: (id: string) => void;
  aberto: boolean;
  estreito: boolean;
  onToggle: () => void;
}) {
  const [query, setQuery] = useState('');
  const [tab, setTab] = useState<ConvTab>('todas');
  const liveRef = useRef<HTMLSpanElement>(null);
  const ultimoAnunciado = useRef(activeConvId);

  const { fixadasShow, recentesShow, visiveis } = useMemo(() => {
    const q = normalizeSearch(query.trim());
    // 'todas' = tudo que NÃO está arquivado (espelha o protótipo:
    // `filtro === 'todas' ? t.escopo !== 'arquivadas' : t.escopo === filtro`).
    const keep = (c: ConversaResumo) =>
      (tab === 'arquivadas' ? isArquivada(c) : !isArquivada(c))
      && (!q || normalizeSearch(c.titulo).includes(q));

    const f = fixadas.filter(keep);
    const r = recentes.filter(keep);
    // Ordem visual da lista — é o que J/K percorre.
    return { fixadasShow: f, recentesShow: r, visiveis: [...f, ...r] };
  }, [fixadas, recentes, query, tab]);

  // Região viva: anuncia a troca de conversa pra leitor de tela (clique OU J/K).
  // Guarda por id pra não re-anunciar a mesma conversa em re-render.
  useEffect(() => {
    if (ultimoAnunciado.current === activeConvId) return;
    ultimoAnunciado.current = activeConvId;
    const atual = [...fixadas, ...recentes].find((c) => c.id === activeConvId);
    if (liveRef.current) {
      liveRef.current.textContent = atual ? `Conversa: ${atual.titulo}` : '';
    }
  }, [activeConvId, fixadas, recentes]);

  // Teclado (Larissa/Wagner trabalham no teclado): J/K anda entre CONVERSAS,
  // ⌘⇧H recolhe o histórico. Trocar de conversa é o que se faz o dia todo;
  // rolar mensagem é ↑/↓ nativo do scroll — ref. pacote JANA-FUSAO-2026-08-06.
  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      const el = document.activeElement as HTMLElement | null;
      const digitando = !!el
        && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.isContentEditable);

      if ((e.metaKey || e.ctrlKey) && e.shiftKey && (e.key === 'h' || e.key === 'H')) {
        e.preventDefault();
        onToggle();
        return;
      }
      // J/K são letras cruas: não roubar do composer nem de combos do browser.
      if (digitando || e.metaKey || e.ctrlKey || e.altKey) return;

      const k = e.key.toLowerCase();
      if (k !== 'j' && k !== 'k') return;
      if (visiveis.length === 0) return;

      const i = visiveis.findIndex((c) => c.id === activeConvId);
      // Conversa ativa fora do filtro corrente (ex.: arquivada com aba "Todas")
      // → entra pela ponta em vez de ficar inerte.
      const prox = i === -1
        ? (k === 'j' ? 0 : visiveis.length - 1)
        : (k === 'j' ? Math.min(i + 1, visiveis.length - 1) : Math.max(i - 1, 0));

      const alvo = visiveis[prox];
      if (!alvo || alvo.id === activeConvId) return;
      e.preventDefault();
      onSelectConv(alvo.id);
    }

    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [visiveis, activeConvId, onSelectConv, onToggle]);

  // Na sobreposição, escolher conversa fecha o histórico (libera a thread).
  function escolher(id: string) {
    onSelectConv(id);
    if (estreito) onToggle();
  }

  // Recolhido: o rail de 40px carrega o atalho — nada fica inalcançável.
  if (!aberto) {
    return (
      <aside className="copiloto-chat-convs collapsed">
        <span ref={liveRef} className="cs-sr" aria-live="polite" />
        <button
          type="button"
          className="cs-peek"
          onClick={onToggle}
          title="Expandir histórico · ⌘⇧H"
          aria-label="Expandir histórico de conversas"
          aria-expanded={false}
        >
          <List size={14} />
          <span className="cs-peek-l">Histórico</span>
          <span className="cs-peek-n">{visiveis.length}</span>
        </button>
      </aside>
    );
  }

  return (
    <aside className="copiloto-chat-convs">
      <span ref={liveRef} className="cs-sr" aria-live="polite" />
      <header className="cs-head">
        <button
          type="button"
          className="cs-iconbtn"
          onClick={onToggle}
          title="Recolher histórico · ⌘⇧H"
          aria-label="Recolher histórico de conversas"
          aria-expanded={true}
        >
          <ChevronLeft size={14} />
        </button>
        <h2>Chat</h2>
        <span className="cs-count">{visiveis.length}</span>
        <button type="button" className="cs-iconbtn" title="Filtros" aria-label="Filtros">
          <SlidersHorizontal size={14} />
        </button>
        <a href="/ia/conversas/nova" className="cs-iconbtn primary" title="Nova conversa · ⌘N" aria-label="Nova conversa">
          <Plus size={14} />
        </a>
      </header>

      <div className="cs-search">
        <Search size={12} className="cs-search-ic" />
        <input
          type="search"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="Buscar conversas..."
          aria-label="Buscar conversas"
        />
        <span className="kbd">⌘K</span>
      </div>

      <div className="cs-tabs" role="tablist" aria-label="Filtros de conversa">
        {CONV_TABS.map((t) => (
          <button
            key={t.id}
            type="button"
            role="tab"
            aria-selected={tab === t.id}
            className={`cs-tab ${tab === t.id ? 'active' : ''}`}
            onClick={() => setTab(t.id)}
          >
            {t.label}
          </button>
        ))}
      </div>

      <div className="sb-actions">
        <a href="/tarefas" className="sb-action">
          <Inbox size={14} /> <span>Tarefas</span>
        </a>
        <div className="sb-action">
          <Bell size={14} /> <span>Despachos</span>
          <span className="beta">Beta</span>
        </div>
        <div className="sb-action">
          <Cog size={14} /> <span>Personalizar</span>
        </div>
      </div>

      <div className="sb-section-h">Fixadas</div>
      {fixadasShow.length === 0 ? (
        <div className="sb-action" style={{ opacity: 0.6 }}>
          <Pin size={14} /> <span>{query ? 'Nenhuma fixada nesta busca' : 'Arraste para fixar'}</span>
        </div>
      ) : (
        fixadasShow.map((c) => (
          <SbConvItem key={c.id} c={c} active={c.id === activeConvId} onSelect={escolher} />
        ))
      )}

      <div className="sb-section-h">{tab === 'arquivadas' ? 'Arquivadas' : 'Recentes'}</div>
      {recentesShow.length === 0 ? (
        <div className="sb-action" style={{ opacity: 0.6 }}>
          <span>
            {query
              ? 'Nenhuma conversa nesta busca'
              : tab === 'arquivadas'
                ? 'Nenhuma conversa arquivada'
                : 'Nenhuma conversa ainda'}
          </span>
        </div>
      ) : (
        recentesShow.map((c) => (
          <SbConvItem key={c.id} c={c} active={c.id === activeConvId} onSelect={escolher} />
        ))
      )}

      {/* Dica visível dos atalhos — protótipo §jm-hist-keys. O atalho que não
          se anuncia não existe pra quem não leu o charter. */}
      <div className="cs-keys">
        <span className="kbd">J</span>
        <span className="kbd">K</span>
        <span>anda</span>
        <span className="cs-keys-sep">·</span>
        <span className="kbd">⌘⇧H</span>
        <span>recolhe</span>
      </div>
    </aside>
  );
}

function SbConvItem({
  c,
  active,
  onSelect,
}: {
  c: ConversaResumo;
  active: boolean;
  onSelect: (id: string) => void;
}) {
  return (
    <div
      className={`sb-conv ${active ? 'active' : ''}`}
      onClick={() => onSelect(c.id)}
      role="button"
      tabIndex={0}
      onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); onSelect(c.id); } }}
    >
      <span className={`sb-bullet ${c.unread ? 'filled' : ''}`} />
      <span className="sb-conv-t">{c.titulo}</span>
      {c.unread ? <span className="sb-conv-badge">{c.unread > 99 ? '99+' : c.unread}</span> : null}
    </div>
  );
}
