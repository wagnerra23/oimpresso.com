// Onda 3b da paridade Jana × `prototipo-ui/cowork/jana-merge.jsx` §JmConversa:
// cabeçalho da thread + composer (placeholder que anuncia `/`) + os 3 chips.
//
// Por que jsdom e não Pest: o que mudou é COMPORTAMENTO de teclado e o que
// acontece ao CLICAR num chip. O Pest estrutural prova que o Controller manda
// `status` no payload; não aperta `/` nem mede se o chip envia a pergunta.
//
// Os casos do chip são bite-test de verdade: eles não conferem que o botão
// existe — conferem que clicar nele chega ao endpoint de stream com a copy
// literal no corpo. Trocar o `ThreadPrimitive.Suggestion` por um `<button>`
// decorativo derruba o teste (UC-JPAIN-16 por analogia: nenhum chip nasce mudo).

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { render, screen, cleanup, fireEvent, waitFor } from '@testing-library/react';

import { JanaConversaHeader } from '@/Pages/Jana/_components/JanaConversaHeader';
import { JanaAssistantUiChat } from '@/Pages/Jana/_components/AssistantUiChat';

vi.mock('@inertiajs/react', () => ({ router: { reload: vi.fn(), get: vi.fn() } }));
vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));
// `react-markdown`/`remark-gfm` são ESM pesados e irrelevantes aqui — o que se
// mede é composer e chip, não render de markdown.
vi.mock('react-markdown', () => ({ default: ({ children }: any) => <div>{children}</div> }));
vi.mock('remark-gfm', () => ({ default: () => {} }));

// Copy LITERAL da âncora (`chat-jana.jsx`: `placeholder` e `data.suggestions`).
// Escrita aqui à mão de propósito: importar a constante do componente tornaria
// o caso tautológico (§5 2026-06-05).
const PLACEHOLDER = 'Pergunte algo à Jana sobre vendas, OS, financeiro…  ( / para focar )';
const CHIPS = ['Quem deve mais?', 'Onde estou perdendo?', 'Quais ações hoje?'];

const MSG_USER = {
  id: 1, role: 'user' as const, content: 'oi', created_at: '2026-09-03T10:00:00Z',
};

/** Resposta SSE mínima: um chunk e fim. */
function respostaStream() {
  const chunks = [new TextEncoder().encode('data: {"type":"chunk","content":"ok"}\n\n')];
  let i = 0;
  return {
    ok: true,
    body: { getReader: () => ({ read: async () => (i < chunks.length ? { done: false, value: chunks[i++] } : { done: true, value: undefined }) }) },
  };
}

function montarChat(mensagensIniciais: Array<typeof MSG_USER> = []) {
  const utils = render(<JanaAssistantUiChat conversaId={7} mensagensIniciais={mensagensIniciais} />);
  // `getByPlaceholderText` NORMALIZA whitespace e não distingue um espaço de
  // dois — e são justamente os dois espaços da âncora que se quer provar. Lê-se
  // o atributo cru.
  const composer = utils.container.querySelector('textarea') as HTMLTextAreaElement;
  return { ...utils, composer };
}

beforeEach(() => {
  // jsdom não implementa `scrollTo`; o viewport do assistant-ui chama no mount.
  if (!Element.prototype.scrollTo) Element.prototype.scrollTo = () => {};
  vi.stubGlobal('fetch', vi.fn(async () => respostaStream() as any));
});
afterEach(() => { cleanup(); vi.unstubAllGlobals(); vi.clearAllMocks(); });

describe('UC-JCHAT-15 — o cabeçalho da thread é o da âncora, não o de mensageiro', () => {
  it('mostra título + "só sua", e nenhuma ação de mensageiro (ligar/detalhes/mais)', () => {
    const { container } = render(<JanaConversaHeader titulo="Por que a receita caiu 68%?" status="ativa" />);

    expect(screen.getByText('Por que a receita caiu 68%?')).toBeTruthy();
    expect(screen.getByText('só sua')).toBeTruthy();
    // Nada de telefone/info/⋯ — e nenhum `.th-head` de volta.
    expect(container.querySelectorAll('button').length).toBe(0);
    expect(container.querySelector('.th-head')).toBeNull();
    expect(screen.queryByText('Assistente IA · Jana')).toBeNull();
  });

  it('mostra a pílula "arquivada" só quando o status é arquivada', () => {
    const { unmount } = render(<JanaConversaHeader titulo="Fechamento de abril" status="arquivada" />);
    expect(screen.getByText('arquivada')).toBeTruthy();
    unmount();

    render(<JanaConversaHeader titulo="Fechamento de abril" status="ativa" />);
    expect(screen.queryByText('arquivada')).toBeNull();
  });
});

describe('UC-JCHAT-16 — o composer anuncia `/` e o `/` funciona', () => {
  it('usa a copy literal da âncora no placeholder (dois espaços inclusive)', () => {
    const { composer } = montarChat();
    expect(composer.getAttribute('placeholder')).toBe(PLACEHOLDER);
  });

  it('`/` foca o composer e `Esc` desfoca', () => {
    const { composer: ta } = montarChat();
    ta.blur();

    fireEvent.keyDown(window, { key: '/' });
    expect(document.activeElement).toBe(ta);

    fireEvent.keyDown(window, { key: 'Escape' });
    expect(document.activeElement).not.toBe(ta);
  });

  it('controle negativo: `/` NÃO é sequestrado de quem já está digitando', () => {
    const { composer: ta } = montarChat();
    const outro = document.createElement('input');
    document.body.appendChild(outro);
    outro.focus();

    fireEvent.keyDown(window, { key: '/' });
    expect(document.activeElement).toBe(outro);
    expect(document.activeElement).not.toBe(ta);
    outro.remove();
  });
});

describe('UC-JCHAT-17 — os 3 chips existem, com a copy da âncora, e nenhum nasce mudo', () => {
  it('aparecem com a conversa em andamento, e não no vazio', () => {
    const { unmount } = montarChat();
    CHIPS.forEach((c) => expect(screen.queryByText(c)).toBeNull());
    unmount();

    montarChat([MSG_USER]);
    CHIPS.forEach((c) => expect(screen.getByText(c)).toBeTruthy());
  });

  it('clicar num chip ENVIA a pergunta pelo stream da conversa', async () => {
    montarChat([MSG_USER]);

    fireEvent.click(screen.getByText('Quem deve mais?'));

    await waitFor(() => expect(fetch).toHaveBeenCalled());
    const [url, init] = (fetch as any).mock.calls[0];
    expect(url).toBe('/ia/conversas/7/mensagens/stream');
    expect(JSON.parse(init.body).content).toBe('Quem deve mais?');
  });
});
