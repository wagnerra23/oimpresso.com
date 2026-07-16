// Contrato CLIENT-SIDE da tela /kb/v2 (SOPs · KB Unificado tri-pane).
//
// =====================================================================================
// POR QUE EXISTE
// =====================================================================================
// O KbIndexV2ContractTest.php (Pest) cobre o contrato da ROTA (auth · render · read-only ·
// sem side-effects · Tier 0) — UC-KBV2-01..06. Os UCs 07/08 são de COMPORTAMENTO CLIENT
// (persistência localStorage · atalhos de teclado) e o Pest não os alcança: vivem em hooks
// React, não no request. Ficaram órfãos no G-2 (ADR 0264) — este arquivo os fecha.
//
// As asserções derivam do CONTRATO (resources/js/Pages/kb/Index.v2.casos.md + charter),
// NÃO da implementação. Isso é regra dura: teste extraído do que o código "faz hoje" é
// tautológico — passa mesmo se o comportamento estiver errado e ainda TRAVA o desvio
// (memory/proibicoes.md §5, entrada 2026-06-05).
//
// LIMITE HONESTO (UC-KBV2-08): jsdom NÃO faz layout — "tri-pane a 1280px sem scroll
// horizontal" e "console limpo" são irredutivelmente browser e seguem manuais (declarado
// no casos.md). Aqui trava-se a metade COMPORTAMENTAL do UC: ⌘K abre a paleta, Esc fecha.
//
// Cobre: resources/js/Pages/kb/_lib/useKbFavorites.ts · useKbRecent.ts · useKbKeyboardNav.ts
// Refs: ADR 0264 (G-2 rastreabilidade UC↔teste) · ADR 0258 (todo ✅ tem que ter sido visto falhar)

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { renderHook, act, cleanup } from '@testing-library/react';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

import { useKbFavorites } from '@/Pages/kb/_lib/useKbFavorites';
import { useKbRecent } from '@/Pages/kb/_lib/useKbRecent';
import { useKbKeyboardNav, type KbKeyboardActions } from '@/Pages/kb/_lib/useKbKeyboardNav';

beforeEach(() => {
  localStorage.clear();
  sessionStorage.clear();
});
afterEach(cleanup);

// =====================================================================================
// UC-KBV2-07 — Persistência client-side é localStorage prefixado
// =====================================================================================
// Contrato (casos.md): "Favoritos, recentes e categorias expandidas persistem via
// localStorage prefix `oimpresso.kb.*` (nunca sessionStorage)". Anti-pattern do charter:
// sessionStorage. A metade "DevTools mostra as chaves" é manual; a metade verificável —
// grava sob o prefixo · sobrevive ao remount (= o reload) · zero sessionStorage — é esta.
describe('UC-KBV2-07 — persistência client-side é localStorage prefixado', () => {
  const PREFIX = 'oimpresso.kb.';

  it('favoritar grava sob o prefixo canônico e NÃO toca sessionStorage', () => {
    const { result } = renderHook(() => useKbFavorites());
    act(() => result.current.toggleFav(42));

    const keys = Object.keys(localStorage);
    expect(keys.length).toBeGreaterThan(0);
    // Toda chave escrita pela tela é prefixada — o contrato é o PREFIXO, não a chave exata
    // (o sufixo é detalhe de implementação e versionamento; travá-lo engessaria sem valor).
    for (const k of keys) expect(k).toMatch(new RegExp(`^${PREFIX.replace('.', '\\.')}`));
    // Anti-pattern declarado no charter: sessionStorage não é usado pra nada.
    expect(Object.keys(sessionStorage)).toHaveLength(0);
    // E o dado persistido é de fato o favorito (não um write vazio que "cumpre" o prefixo).
    expect(JSON.stringify(Object.values({ ...localStorage }))).toContain('42');
  });

  it('favorito SOBREVIVE ao remount (= o reload do critério de aceite)', () => {
    const first = renderHook(() => useKbFavorites());
    act(() => first.result.current.toggleFav(7));
    expect(first.result.current.isFav(7)).toBe(true);
    first.unmount();

    // Monta de novo lendo o storage do zero — é o que um F5 faz.
    const second = renderHook(() => useKbFavorites());
    expect(second.result.current.isFav(7)).toBe(true);
    expect(second.result.current.favs).toContain(7);
  });

  it('desfavoritar remove na volta (o estado persistido acompanha, não só cresce)', () => {
    const a = renderHook(() => useKbFavorites());
    act(() => a.result.current.toggleFav(9));
    act(() => a.result.current.toggleFav(9));
    a.unmount();

    const b = renderHook(() => useKbFavorites());
    expect(b.result.current.isFav(9)).toBe(false);
  });

  it('recentes persistem sob o prefixo e sobrevivem ao remount', () => {
    const a = renderHook(() => useKbRecent());
    act(() => a.result.current.pushRecent(3));
    a.unmount();

    for (const k of Object.keys(localStorage)) expect(k).toMatch(/^oimpresso\.kb\./);
    expect(Object.keys(sessionStorage)).toHaveLength(0);

    const b = renderHook(() => useKbRecent());
    expect(b.result.current.recent).toContain(3);
  });

  it('storage indisponível (private mode / quota) NÃO quebra a tela', () => {
    // Charter: a persistência é conveniência, não requisito de render. Se o setItem
    // lança (Safari private mode), a tela tem que seguir viva.
    const spy = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
      throw new DOMException('QuotaExceededError');
    });
    expect(() => {
      const { result } = renderHook(() => useKbFavorites());
      act(() => result.current.toggleFav(1));
    }).not.toThrow();
    spy.mockRestore();
  });
});

// =====================================================================================
// UC-KBV2-08 — ⌘K abre a paleta · Esc fecha (metade comportamental)
// =====================================================================================
// Contrato (casos.md): "⌘K (ou /) abre o CommandPalette; Esc fecha o leitor."
// (charter Goal 5 · UX Targets). O "1280px sem scroll horizontal" + "console limpo"
// NÃO estão aqui: jsdom não tem layout engine — seguem manuais/browser, por honestidade.
describe('UC-KBV2-08 — atalhos: ⌘K abre a paleta, Esc fecha (1280px/scroll seguem manuais)', () => {
  const actions = (over: Partial<KbKeyboardActions> = {}): KbKeyboardActions => ({
    paletteOpen: false,
    troubleOpen: false,
    aiOpen: false,
    pathsOpen: false,
    healthOpen: false,
    composerOpen: false,
    onOpenPalette: vi.fn(),
    onFocusSearch: vi.fn(),
    onCloseAll: vi.fn(),
    onNext: vi.fn(),
    onPrev: vi.fn(),
    onEnter: vi.fn(),
    ...over,
  });

  const press = (key: string, init: KeyboardEventInit = {}) =>
    act(() => {
      window.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, ...init }));
    });

  it('⌘K (Mac) abre a paleta', () => {
    const a = actions();
    renderHook(() => useKbKeyboardNav(a));
    press('k', { metaKey: true });
    expect(a.onOpenPalette).toHaveBeenCalledTimes(1);
  });

  it('Ctrl+K (Windows/Linux — Wagner é win32) abre a paleta', () => {
    const a = actions();
    renderHook(() => useKbKeyboardNav(a));
    press('k', { ctrlKey: true });
    expect(a.onOpenPalette).toHaveBeenCalledTimes(1);
  });

  it('"/" foca a busca quando não há overlay aberto', () => {
    const a = actions();
    renderHook(() => useKbKeyboardNav(a));
    press('/');
    expect(a.onFocusSearch).toHaveBeenCalledTimes(1);
  });

  it('Esc com overlay aberto fecha', () => {
    const a = actions({ paletteOpen: true });
    renderHook(() => useKbKeyboardNav(a));
    press('Escape');
    expect(a.onCloseAll).toHaveBeenCalledTimes(1);
  });

  it('ESPECIFICIDADE: "k" SEM modificador NÃO abre a paleta (é navegar pra cima)', () => {
    // Controle-negativo: sem isso o teste do ⌘K passaria mesmo se o handler ignorasse
    // o modificador — e aí digitar "k" na lista abriria a paleta na cara do usuário.
    const a = actions();
    renderHook(() => useKbKeyboardNav(a));
    press('k');
    expect(a.onOpenPalette).not.toHaveBeenCalled();
    expect(a.onPrev).toHaveBeenCalledTimes(1);
  });

  it('ESPECIFICIDADE: atalho de letra NÃO dispara enquanto digita num input', () => {
    // Contrato do hook: "atalhos só disparam fora de campos digitáveis". Sem isso,
    // escrever "novo" na busca abriria o composer no "n".
    const a = actions({ canWrite: true, onNewArticle: vi.fn() });
    renderHook(() => useKbKeyboardNav(a));
    const input = document.createElement('input');
    document.body.appendChild(input);
    act(() => {
      input.dispatchEvent(new KeyboardEvent('keydown', { key: 'n', bubbles: true }));
    });
    expect(a.onNewArticle).not.toHaveBeenCalled();
    input.remove();
  });

  it('⌘K funciona MESMO digitando (é a saída universal da busca)', () => {
    const a = actions();
    renderHook(() => useKbKeyboardNav(a));
    const input = document.createElement('input');
    document.body.appendChild(input);
    act(() => {
      input.dispatchEvent(new KeyboardEvent('keydown', { key: 'k', metaKey: true, bubbles: true }));
    });
    expect(a.onOpenPalette).toHaveBeenCalledTimes(1);
    input.remove();
  });
});

// =====================================================================================
// UC-KBV2-10 — ação sem backend NÃO afirma sucesso
// =====================================================================================
// Contrato (casos.md UC-KBV2-10): enquanto a tela não faz chamada de rede, nenhuma ação de
// ESCRITA pode devolver `toast.success` — afirmar "Artigo re-verificado e marcado como fresco"
// sem persistir nada mente pro usuário, e numa KB cujo produto é frescor isso corrompe o
// próprio sinal que a tela vende. O rótulo `· MOCK` do PageHeader rotula a FONTE DE DADOS,
// não a AÇÃO — por isso ele não cobre este caso.
//
// A invariante LIGA duas propriedades independentes (não é tautologia): "não há rede no
// arquivo" ⟹ "não há afirmação de sucesso nas ações de escrita". Quando a ONDA 1 ligar o
// endpoint, o detector de rede passa a achar o POST e o teste LIBERA o `success` — o teste
// não engessa a promoção, só proíbe a mentira. Achado da revisão adversarial 2026-07-16.
describe('UC-KBV2-10 — ação sem backend não afirma sucesso (anti-mentira)', () => {
  const PAGE = resolve(process.cwd(), 'resources/js/Pages/kb/Index.v2.tsx');
  // As 4 ações de ESCRITA declaradas no charter (Automation Hooks) que ainda não têm endpoint.
  // `toggleFav` NÃO entra: ele persiste de verdade (localStorage, UC-KBV2-07) — o success dele
  // é honesto. A lista é do contrato, não "o que o código faz hoje".
  const ACOES_DE_ESCRITA = ['voteHelpful', 'voteOutdated', 'reverify', 'attachToOS'];

  const semComentarios = (src: string) =>
    src.replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:])\/\/.*$/gm, '$1');

  /** corpo do handler `const <nome> = (...) => { ... };` (até o fecho no mesmo nível). */
  const corpoDoHandler = (src: string, nome: string): string => {
    const i = src.indexOf(`const ${nome} = (`);
    if (i === -1) return '';
    const fim = src.indexOf('\n  };', i);
    return src.slice(i, fim === -1 ? src.length : fim);
  };

  const temRede = (src: string) =>
    /\brouter\.(post|put|patch|delete|visit)\b|\baxios\b|\bfetch\s*\(|\buseForm\b/.test(semComentarios(src));

  it('nenhuma das 4 ações de escrita devolve toast.success enquanto a tela é write-free', () => {
    const src = readFileSync(PAGE, 'utf8');
    const redeAtiva = temRede(src);
    for (const acao of ACOES_DE_ESCRITA) {
      const corpo = corpoDoHandler(src, acao);
      expect(corpo, `handler ${acao} sumiu/renomeou — atualize o contrato UC-KBV2-10`).not.toBe('');
      if (!redeAtiva) {
        expect(corpo, `${acao} afirma sucesso sem persistir nada (tela write-free)`).not.toMatch(/toast\.success/);
      }
    }
  });

  it('as 4 ações avisam que é demonstração (não ficam mudas)', () => {
    // Não basta remover o success: sumir com o feedback deixaria o clique sem resposta —
    // o usuário reclicaria achando que falhou. O contrato pede aviso honesto, não silêncio.
    const src = readFileSync(PAGE, 'utf8');
    for (const acao of ACOES_DE_ESCRITA) {
      expect(corpoDoHandler(src, acao), `${acao} sem feedback nenhum`).toMatch(/toast\.info\(/);
    }
  });

  it('CONTROLE-NEGATIVO: o detector morde uma fixture mentirosa e libera uma com rede', () => {
    // Prova que o teste acima não passa por acidente (ADR 0258 — visto falhar).
    const mentirosa = `const reverify = (id: number) => {\n    toast.success('Artigo re-verificado');\n  };`;
    expect(temRede(mentirosa)).toBe(false);
    expect(corpoDoHandler(mentirosa, 'reverify')).toMatch(/toast\.success/); // → o assert acima falharia

    // Com rede real, o success é legítimo e o teste NÃO deve bloquear a promoção.
    const comRede = `const reverify = (id: number) => {\n    router.post('/kb/nodes/x/reverify');\n    toast.success('ok');\n  };`;
    expect(temRede(comRede)).toBe(true);

    // E comentário citando router/fetch NÃO conta como rede (senão um TODO liberaria a mentira).
    expect(temRede(`// TODO: trocar pra router.post(...) depois\nconst x = 1;`)).toBe(false);
  });
});
