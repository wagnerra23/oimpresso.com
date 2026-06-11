// @memcofre
//   tela: /copiloto
//   stories: US-COPI-001, US-COPI-002, US-COPI-003, US-COPI-MEM-007
//   rules: R-COPI-001, R-COPI-MEM-005
//   adrs: 0026, 0031, 0032, 0034, 0035, 0036, 0039 (Cockpit), UI-0008
//   tests: tests/Feature/Modules/Copiloto/AdapterResolverTest, tests/Feature/Modules/Copiloto/BridgeMemoriaChatTest
//   status: implementada (Sprint 1: migrada pra AppShellV2)
//   module: Copiloto

import { Head, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Bell, Cog, Inbox, Pin, Plus, Search, SlidersHorizontal } from 'lucide-react';
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
const COPILOTO_AVATAR: AvatarRef = { iniciais: 'CP', gradId: 17 };

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

  function selectConv(id: string) {
    router.get(`/ia/conversas/${id}`, {}, {
      preserveScroll: true,
      preserveState: true,
    });
  }

  return (
    <AppShellV2
      title="Jana · Chat"
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
      <Head title="Jana · Chat" />

      {/* JanaAreaHeader — header sticky com tabs Dashboard | Chat (Wagner
          2026-05-18). Espelha app.jsx Header function do protótipo Cockpit.
          Componente compartilhado com Dashboard.tsx. Gate F1.5:
          memory/requisitos/Jana/Chat-header-tabs-visual-comparison.md */}
      <JanaAreaHeader active="chat" />

      {/* Master/detail interno — UI-0011 (sidebar single-pane) migrou conv
          switcher pra dentro da própria Page. 320px lista + 1fr thread. */}
      <div className="copiloto-chat-layout">
        <ConvSidePanel
          fixadas={conversas.fixadas}
          recentes={conversas.recentes}
          activeConvId={String(conversa.id)}
          onSelectConv={selectConv}
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
// Hoje só 'todas' filtra de fato (backend não expõe flag compartilhada/
// arquivada em ConversaResumo). 'minhas/compartilhadas/arquivadas' mostram
// empty state "Em breve" pra dar preview do roadmap.

type ConvTab = 'todas' | 'minhas' | 'compartilhadas' | 'arquivadas';

const CONV_TABS: Array<{ id: ConvTab; label: string }> = [
  { id: 'todas',           label: 'Todas'         },
  { id: 'minhas',          label: 'Minhas'        },
  { id: 'compartilhadas',  label: 'Compartilhadas'},
  { id: 'arquivadas',      label: 'Arquivadas'    },
];

// Normaliza pra busca acento-insensitive
function normalizeSearch(s: string): string {
  return s.toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '');
}

function ConvSidePanel({
  fixadas,
  recentes,
  activeConvId,
  onSelectConv,
}: {
  fixadas: ConversaResumo[];
  recentes: ConversaResumo[];
  activeConvId: string;
  onSelectConv: (id: string) => void;
}) {
  const [query, setQuery] = useState('');
  const [tab, setTab] = useState<ConvTab>('todas');

  const { fixadasShow, recentesShow, showEmptyTabPlaceholder } = useMemo(() => {
    if (tab !== 'todas') {
      // Backend ainda não expõe flag compartilhada/arquivada — mostra empty
      // state e mantém lista vazia até backend evoluir.
      return { fixadasShow: [], recentesShow: [], showEmptyTabPlaceholder: true };
    }
    const q = normalizeSearch(query.trim());
    if (!q) {
      return { fixadasShow: fixadas, recentesShow: recentes, showEmptyTabPlaceholder: false };
    }
    const matchQ = (c: ConversaResumo) => normalizeSearch(c.titulo).includes(q);
    return {
      fixadasShow: fixadas.filter(matchQ),
      recentesShow: recentes.filter(matchQ),
      showEmptyTabPlaceholder: false,
    };
  }, [fixadas, recentes, query, tab]);

  return (
    <aside className="copiloto-chat-convs">
      <header className="cs-head">
        <h2>Chat</h2>
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

      {showEmptyTabPlaceholder ? (
        <div className="cs-empty">
          <span>Em breve</span>
          <small>Filtro disponível quando o backend expor o campo.</small>
        </div>
      ) : (
        <>
          <div className="sb-section-h">Fixadas</div>
          {fixadasShow.length === 0 ? (
            <div className="sb-action" style={{ opacity: 0.6 }}>
              <Pin size={14} /> <span>{query ? 'Nenhuma fixada nesta busca' : 'Arraste para fixar'}</span>
            </div>
          ) : (
            fixadasShow.map((c) => (
              <SbConvItem key={c.id} c={c} active={c.id === activeConvId} onSelect={onSelectConv} />
            ))
          )}

          <div className="sb-section-h">Recentes</div>
          {recentesShow.length === 0 ? (
            <div className="sb-action" style={{ opacity: 0.6 }}>
              <span>{query ? 'Nenhuma recente nesta busca' : 'Nenhuma conversa ainda'}</span>
            </div>
          ) : (
            recentesShow.map((c) => (
              <SbConvItem key={c.id} c={c} active={c.id === activeConvId} onSelect={onSelectConv} />
            ))
          )}
        </>
      )}
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
