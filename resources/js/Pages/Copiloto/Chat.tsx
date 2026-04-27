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
import { toast } from 'sonner';

import AppShellV2 from '@/Layouts/AppShellV2';
import { Composer, Thread, ThreadHeader } from '@/Components/cockpit/Thread';
import {
  AvatarRef,
  BusinessOpt,
  ConversaFoco,
  ConversaResumo,
  Mensagem as CockpitMensagem,
  Rotina,
} from '@/Components/cockpit/shared';
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
    whoNome: 'Copiloto',
  };
}

// ── PropostaCard (componente especifico do Copiloto, mantido inline) ───

function PropostaCard({ sugestao }: { sugestao: Sugestao }) {
  const p = sugestao.payload_json;
  const dif = DIFICULDADE_CONFIG[p.dificuldade] ?? DIFICULDADE_CONFIG['realista']!;

  function escolher() {
    router.post(`/copiloto/sugestoes/${sugestao.id}/escolher`, {}, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => toast.success('Meta criada com sucesso!'),
      onError: () => toast.error('Erro ao escolher meta.'),
    });
  }

  function rejeitar() {
    router.post(`/copiloto/sugestoes/${sugestao.id}/rejeitar`, {}, {
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
  const [enviando, setEnviando] = useState(false);

  // Adapta mensagens backend → formato Cockpit
  const mensagensCockpit = useMemo(
    () => mensagens.map(adaptarMensagem),
    [mensagens],
  );

  // ConversaFoco da conversa atual (fora do mock — vem do backend real agora)
  const conversaFoco: ConversaFoco = useMemo(() => ({
    id: String(conversa.id),
    titulo: conversa.titulo,
    tipo: 'copiloto',
    online: true,
    avatar: COPILOTO_AVATAR,
    mensagens: mensagensCockpit,
  }), [conversa, mensagensCockpit]);

  function handleSend(texto: string) {
    if (!texto.trim() || enviando) return;
    setEnviando(true);
    router.post(
      `/copiloto/conversas/${conversa.id}/mensagens`,
      { content: texto },
      {
        onSuccess: () => setEnviando(false),
        onError: () => {
          toast.error('Erro ao enviar mensagem.');
          setEnviando(false);
        },
        preserveScroll: true,
      },
    );
  }

  function selectConv(id: string) {
    router.get(`/copiloto/conversas/${id}`, {}, {
      preserveScroll: true,
      preserveState: true,
    });
  }

  return (
    <AppShellV2
      title="Copiloto · Chat"
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
      <Head title="Copiloto · Chat" />

      <ThreadHeader conv={conversaFoco} />

      <Thread mensagens={mensagensCockpit} typing={false} />

      {/* Cards de propostas pendentes — específico Copiloto, não estão no Components/cockpit */}
      {sugestoesPendentes.length > 0 && (
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
      )}

      <Composer onSend={handleSend} conv={conversaFoco} />
    </AppShellV2>
  );
}
