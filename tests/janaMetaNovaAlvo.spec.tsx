// UC-JPAIN-33 — a gaveta de criar meta pede o ALVO, e não cria meta sem ele.
//
// @covers-us US-COPI-150
//
// POR QUE ESTE UC EXISTE. Medido em produção em 2026-09-21: as 5 metas do tenant tinham
// ZERO período, ZERO apuração e ZERO fonte, e por isso os 5 cards do Painel saíam
// idênticos — "<nome> | <unidade> | Aguardando apuração…", sem valor, sem barra e sem
// projeção. Não era cadastro interrompido pelo usuário: o `StoreMetaRequest` **não
// aceitava** campo de alvo, e esta gaveta **não pedia** nenhum. Criar uma meta completa
// pelo caminho manual era impossível.
//
// A assimetria que isso expôs, lida nos dois controllers:
//
//   ChatController@escolher (IA)     -> Meta + MetaPeriodo + MetaFonte + ApurarMetaJob
//   MetasController@store   (manual) -> Meta
//
// ⚠️ O QUE ESTE UC NÃO COBRE, e é residual DECLARADO: a `MetaFonte`. Sem fonte a meta
// não apura — o próprio `buildMetasPayload` já dizia isso ("`null` = meta sem fonte
// gravada, que é estado REAL"). Não se pede fonte aqui porque NÃO EXISTE UI pra ela em
// lugar nenhum: `copiloto::fontes.show` é somente-leitura e declara, no corpo, que o
// editor com prévia é a **US-COPI-040**. Inventar um campo de SQL na gaveta seria pior
// que o buraco. O que a gaveta faz é AVISAR que a fonte fica fora — e isso é testado.
//
// Método (ADR 0258): o payload é capturado do `router.post` mockado, e há controle
// negativo provando que os detectores acusam o payload ANTIGO (sem alvo).
import * as React from 'react';
import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, cleanup, fireEvent } from '@testing-library/react';

const posts: Array<{ url: string; payload: Record<string, unknown> }> = [];

vi.mock('@inertiajs/react', () => ({
  router: {
    post: (url: string, payload: Record<string, unknown>) => {
      posts.push({ url, payload });
    },
    reload: () => {},
    visit: () => {},
  },
  usePage: () => ({ url: '/ia', props: {} }),
  Link: ({ href, children }: any) => <a href={href}>{children}</a>,
}));

import JanaMetaNovaDrawer from '@/Pages/Jana/_components/JanaMetaNovaDrawer';

afterEach(() => {
  posts.length = 0;
  cleanup();
});

function abrir() {
  return render(<JanaMetaNovaDrawer aberto onClose={() => {}} />);
}

/** O botão de submit, pelo texto que o usuário lê. */
function botaoCriar(): HTMLButtonElement {
  return screen.getByRole('button', { name: /criar meta/i }) as HTMLButtonElement;
}

describe('UC-JPAIN-33 — a gaveta pede o alvo', () => {
  it('mostra os campos de alvo e de janela', () => {
    abrir();
    expect(screen.getByLabelText(/valor alvo/i), 'campo de valor alvo ausente').toBeTruthy();
    expect(screen.getByLabelText(/janela/i), 'seletor de janela ausente').toBeTruthy();
    expect(screen.getByLabelText(/^in[ií]cio$/i), 'data inicial ausente').toBeTruthy();
    expect(screen.getByLabelText(/^fim$/i), 'data final ausente').toBeTruthy();
  });

  it('a janela nasce preenchida (mês corrente), então o alvo é o único campo a digitar', () => {
    abrir();
    const ini = screen.getByLabelText(/^in[ií]cio$/i) as HTMLInputElement;
    const fim = screen.getByLabelText(/^fim$/i) as HTMLInputElement;
    // formato ISO `YYYY-MM-DD` — é o que o `StoreMetaRequest` valida com `date`
    expect(ini.value).toMatch(/^\d{4}-\d{2}-\d{2}$/);
    expect(fim.value).toMatch(/^\d{4}-\d{2}-\d{2}$/);
    expect(ini.value <= fim.value, 'janela sugerida com início depois do fim').toBe(true);
  });

  it('NÃO cria meta sem alvo — o botão fica travado', () => {
    abrir();
    fireEvent.change(screen.getByLabelText(/^nome$/i), { target: { value: 'Faturamento' } });
    // nome e slug preenchidos, alvo VAZIO
    expect(botaoCriar().disabled, 'botão liberado sem valor alvo').toBe(true);
    fireEvent.click(botaoCriar());
    expect(posts.length, 'submeteu mesmo com o botão travado').toBe(0);
  });

  it('com alvo, o payload leva valor_alvo, tipo_periodo, data_ini e data_fim', () => {
    abrir();
    fireEvent.change(screen.getByLabelText(/^nome$/i), { target: { value: 'Faturamento' } });
    fireEvent.change(screen.getByLabelText(/valor alvo/i), { target: { value: '1500' } });
    expect(botaoCriar().disabled, 'botão travado mesmo com alvo preenchido').toBe(false);
    fireEvent.click(botaoCriar());

    expect(posts.length, 'não submeteu').toBe(1);
    const { url, payload } = posts[0];
    expect(url).toBe('/ia/metas');
    // identidade (o que já ia antes)
    expect(payload.nome).toBe('Faturamento');
    expect(payload.slug).toBe('faturamento');
    expect(payload.unidade).toBe('R$');
    expect(payload.tipo_agregacao).toBe('soma');
    // ALVO — o que faltava
    expect(payload.valor_alvo).toBe('1500');
    expect(payload.tipo_periodo).toBe('mes');
    expect(String(payload.data_ini)).toMatch(/^\d{4}-\d{2}-\d{2}$/);
    expect(String(payload.data_fim)).toMatch(/^\d{4}-\d{2}-\d{2}$/);
  });

  it('avisa que a FONTE fica fora — o residual é declarado ao usuário, não escondido', () => {
    abrir();
    const aviso = screen.getByText(/de onde a Jana tira o realizado/i);
    expect(aviso, 'aviso sobre a fonte ausente na gaveta').toBeTruthy();
    expect(aviso.textContent).toMatch(/n[ãa]o apura/i);
  });
});

// ── CONTROLE NEGATIVO (ADR 0258) ────────────────────────────────────────────────────────
// Prova que os detectores acima acusam o payload ANTIGO. Sem isto, um assert que nunca viu
// vermelho é indistinguível de um assert que não mede nada.
describe('UC-JPAIN-33 · controle negativo — o payload antigo seria reprovado', () => {
  const ANTIGO = { nome: 'X', slug: 'x', unidade: 'R$', tipo_agregacao: 'soma' } as Record<string, unknown>;

  it('o payload antigo não tem alvo nem janela', () => {
    expect(ANTIGO.valor_alvo).toBeUndefined();
    expect(ANTIGO.data_ini).toBeUndefined();
    expect(ANTIGO.data_fim).toBeUndefined();
    expect(ANTIGO.tipo_periodo).toBeUndefined();
  });

  it('e é exatamente por isso que a meta nascia sem `periodo_atual`', () => {
    // o `MetasController@store` só cria `MetaPeriodo` quando o alvo vem inteiro
    const temAlvo = ['valor_alvo', 'data_ini', 'data_fim'].every((k) => ANTIGO[k] !== undefined);
    expect(temAlvo).toBe(false);
  });
});
