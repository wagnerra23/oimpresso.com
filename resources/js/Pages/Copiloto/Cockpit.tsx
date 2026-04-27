// @memcofre
//   tela: /copiloto/cockpit
//   stories: US-COPI-COCKPIT-001 (MVP)
//   adrs: 0039 (padrao "Chat Cockpit"), UI-0008 (cockpit como layout-mae)
//   status: mvp-piloto-em-validacao
//   module: Copiloto
//   nota: rota PARALELA ao /copiloto atual; nao substitui Chat.tsx.
//         Refatorada pra usar AppShellV2 (layout-mae) — extracted Sprint 0
//         (commit que essa versao referencia).

import { useState } from 'react';

import AppShellV2 from '@/Layouts/AppShellV2';
import {
  ChatTabs,
  Composer,
  Thread,
  ThreadContext,
  ThreadHeader,
} from '@/Components/cockpit/Thread';
import {
  BusinessOpt,
  ConversaFoco,
  ConversaResumo,
  LS,
  Mensagem,
  Rotina,
} from '@/Components/cockpit/shared';

interface Props {
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
  conversaFoco: ConversaFoco;
  conversaAtivaRealId: number | null;
}

export default function Cockpit({
  businessNome,
  businesses,
  usuarioNome,
  usuarioNomeCurto,
  usuarioEmail,
  usuarioCargo,
  usuarioIniciais,
  conversas,
  conversaFoco,
}: Props) {
  // State da pagina (chat-specifico):
  // - chatTab: aba interna Todos/OS/Equipe/Clientes
  // - activeConvId: conversa selecionada na sidebar
  // - mensagensLocal: lista de mensagens otimisticamente atualizada
  // - typing: indicador de "estao digitando" (mock — Fase 3 plug real)
  const [chatTab, setChatTab] = useState<string>(() => {
    if (typeof window === 'undefined') return 'todos';
    return localStorage.getItem(LS.CHAT_TAB) || 'todos';
  });
  const [activeConvId, setActiveConvId] = useState<string>(() => {
    if (typeof window === 'undefined') return conversaFoco.id;
    return localStorage.getItem(LS.CONV) || conversaFoco.id;
  });
  const [mensagensLocal, setMensagensLocal] = useState<Mensagem[]>(conversaFoco.mensagens);
  const [typing, setTyping] = useState<boolean>(false);

  // Persiste chatTab + activeConvId
  if (typeof window !== 'undefined') {
    // useEffect não necessario — escreve direto, idempotente
    localStorage.setItem(LS.CHAT_TAB, chatTab);
    localStorage.setItem(LS.CONV, activeConvId);
  }

  function handleSend(texto: string) {
    const novaMsg: Mensagem = {
      id: Date.now(),
      autor: 'me',
      texto,
      hora: new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
      lida: false,
      dia: 'Hoje',
    };
    setMensagensLocal((arr) => [...arr, novaMsg]);
    // Simula resposta em 2-3s pra demonstrar typing indicator
    // (Fase 3: substituir por POST /copiloto/conversas/{id}/mensagens real)
    setTimeout(() => setTyping(true), 600);
    setTimeout(() => {
      setTyping(false);
      const replyAvatar = conversaFoco.mensagens.find((m) => m.autor === 'them')?.whoAvatar;
      const replyNome = conversaFoco.mensagens.find((m) => m.autor === 'them')?.whoNome;
      const reply: Mensagem = {
        id: Date.now() + 1,
        autor: 'them',
        whoAvatar: replyAvatar,
        whoNome: replyNome,
        texto: 'Recebido, vou verificar e te respondo já já 👍',
        hora: new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
        dia: 'Hoje',
      };
      setMensagensLocal((arr) => [...arr, reply]);
    }, 2400);
  }

  return (
    <AppShellV2
      title="Copiloto · Cockpit"
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
      activeConvId={activeConvId}
      onSelectConv={setActiveConvId}
    >
      <ThreadHeader conv={conversaFoco} />
      <ChatTabs active={chatTab} onChange={setChatTab} />
      <ThreadContext conv={conversaFoco} />
      <Thread
        mensagens={mensagensLocal}
        typing={typing}
        typingAvatar={conversaFoco.mensagens.find((m) => m.autor === 'them')?.whoAvatar}
      />
      <Composer onSend={handleSend} conv={conversaFoco} />
    </AppShellV2>
  );
}
