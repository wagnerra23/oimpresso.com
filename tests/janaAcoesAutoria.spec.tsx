// UC-JPAIN-24 — a seção de ações é assinada pela JANA, não por quem está olhando a tela.
//
// Âncora: `prototipo-ui/cowork/Wagner/jana-merge.jsx` §`JmPainel` —
// `AÇÕES QUE {data.person.name.toUpperCase()} SUGERE`, onde `data.person` é
// `{ name: "Jana", role: "Analista IA" }` (âncora de SÍMBOLO; re-localize com
// `grep -n "person:" prototipo-ui/cowork/Wagner/chat-jana.jsx`).
// Precedência de FORMA: protótipo > teste > casos > charter > SPEC (ADR UI-0029).
//
// O BUG que este teste trava (vivo em prod até 2026-09-18): o título interpolava
// `firstNameUpper`, derivado de `userName` — o USUÁRIO LOGADO —, atribuindo ao LEITOR
// sugestões que o servidor derivou de 5 regras sobre o dado dele. Não é divergência de
// copy: é troca de SUJEITO.
//
// ⚠️ O antes→depois REAL em prod é `VOCÊ` → `Jana`, não `<nome>` → `Jana`. Medido em
// 2026-09-18 (biz=1, DOM estabilizado): o h2 renderizava `Ações que VOCÊ sugere`, porque
// `userName` chega falsy e o fallback `|| 'você'` de `:318` está ativo. Este teste injeta
// `userName` de propósito — ele trava o contrato do COMPONENTE, que é o caminho em que o
// bug é mais grave (com o dado presente, a tela assinaria com o nome de quem olha).
// A CAUSA do falsy é NÃO-MEDIDA e é pendência separada; nada aqui conclui sobre ela.
//
// Por que o `Index-visual-comparison.md` não pegou: a linha §R7 registrava
// `"AÇÕES QUE <NOME> SUGERE"` × `"Ações que <Nome> sugere"` com veredito ✅ — a
// notação abstraiu justamente o que divergia (QUEM é o nome), e o ✅ virou
// falso-verde. Corrigido no mesmo PR (regra de precedência: o perdedor se corrige junto).
//
// Método (ADR 0258 — "todo ✅ tem que ter sido visto falhar"): o detector de autoria
// tem controle POSITIVO (acha o nome do usuário quando ele está lá) e NEGATIVO (não
// confunde a saudação, que legitimamente usa o primeiro nome).

import * as React from 'react';
import { describe, it, expect, afterEach } from 'vitest';
import { render, cleanup } from '@testing-library/react';
import JanaCockpit from '@/Pages/Jana/_components/JanaCockpit';

afterEach(cleanup);

/** Nome de teste que NÃO pode assinar as sugestões. Não é o nome de ninguém real. */
const USUARIO = 'Wagner Rocha';
const PRIMEIRO_NOME = 'Wagner';

/**
 * Props mínimas que fazem a seção "Ações" existir.
 *
 * A seção só renderiza com `acoes.length > 0`, e a 1ª regra do `useMemo` exige
 * `overdueCount > 0`. Os demais números são contagem/estrutura — nunca valor
 * monetário, que não vai pro git (Tier 0, `memory/proibicoes.md` §REGRA BRL).
 */
function renderCockpit(userName?: string) {
  const { container } = render(
    <JanaCockpit
      userName={userName}
      sellKpis={{ total: 1, paid: 0, due: 1, partial: 0, overdue: 1 }}
      insightsAggregates={{
        overdueCount: 1,
        overdueValue: 0,
        ageingBuckets: { '0-30d': 0, '30-90d': 0, '90-365d': 0, '>365d': 0 },
        methodsAgg: [],
        topClientes: [],
        topDevedor: null,
        ticketMedio: 0,
        totalAReceber: 0,
        churnOuro: [],
      }}
    />,
  );
  return container;
}

/** O h2 da seção de ações — o único que fala em "sugere". */
function tituloDasAcoes(container: HTMLElement): HTMLElement {
  const h2 = Array.from(container.querySelectorAll<HTMLElement>('h2')).find((el) =>
    /sugere/i.test(el.textContent ?? ''),
  );
  if (!h2) throw new Error('seção de ações não renderizou (nenhum <h2> contendo "sugere")');
  return h2;
}

/** Detector: este texto atribui a autoria ao usuário logado? */
function assinaPeloUsuario(texto: string): boolean {
  return new RegExp(PRIMEIRO_NOME, 'i').test(texto);
}

describe('detector de autoria — controle positivo e negativo (ADR 0258)', () => {
  it('SENSIBILIDADE: o detector acha o nome do usuário quando ele assina', () => {
    // Exatamente o texto que prod renderizava antes do fix. Se este assert cair,
    // o detector cegou e os testes abaixo viram carimbo.
    expect(assinaPeloUsuario('Ações que WAGNER sugere')).toBe(true);
    expect(assinaPeloUsuario('Ações que Wagner sugere')).toBe(true);
  });

  it('ESPECIFICIDADE: o detector não acusa o título correto', () => {
    expect(assinaPeloUsuario('Ações que Jana sugere')).toBe(false);
  });
});

describe('UC-JPAIN-24 — quem assina as sugestões é a Jana', () => {
  it('o título nomeia a Jana, com usuário logado presente', () => {
    const titulo = tituloDasAcoes(renderCockpit(USUARIO));
    expect(titulo.textContent).toContain('Jana');
  });

  it('o título NÃO é assinado pelo usuário logado', () => {
    const titulo = tituloDasAcoes(renderCockpit(USUARIO));
    expect(assinaPeloUsuario(titulo.textContent ?? '')).toBe(false);
  });

  it('o título é ESTÁVEL entre usuários — não varia com quem olha', () => {
    const comUsuario = tituloDasAcoes(renderCockpit(USUARIO)).textContent;
    const outroUsuario = tituloDasAcoes(renderCockpit('Larissa Souza')).textContent;
    const semUsuario = tituloDasAcoes(renderCockpit(undefined)).textContent;

    expect(comUsuario).toBe(outroUsuario);
    expect(comUsuario).toBe(semUsuario);
  });

  it('COM userName presente, a saudação segue personalizada — o fix não virou régua cega', () => {
    // Contra-prova de que o conserto foi cirúrgico: a âncora personaliza a saudação
    // (`firstName` em `:489`) e só a AUTORIA das ações é da Jana. Sem este caso,
    // trocar tudo por "você" passaria nos asserts acima.
    //
    // ⚠️ Este é contrato do COMPONENTE, não estado de produção: medido em 2026-09-18,
    // prod renderiza `Boa tarde.` sem nome, porque `userName` chega falsy. O caso vale
    // pelo que impede (o fix se espalhar para a saudação), não como afirmação sobre o
    // que a tela mostra hoje.
    const container = renderCockpit(USUARIO);
    expect(container.textContent).toContain(PRIMEIRO_NOME);
  });

  it('SEM userName, o título é da Jana e a saudação degrada sem nome — o caminho de prod', () => {
    // O estado que produção de fato renderiza hoje. Antes do fix este caminho dizia
    // "Ações que VOCÊ sugere" — o leitor, explícito.
    const container = renderCockpit(undefined);
    expect(tituloDasAcoes(container).textContent).toContain('Jana');
    expect(tituloDasAcoes(container).textContent).not.toMatch(/você/i);
  });

  it('a caixa alta vem do CSS, como a `.jc-h2` da âncora', () => {
    // A âncora é `text-transform: uppercase` no CSS (`chat-jana.css` §H2), não
    // `.toUpperCase()` no dado — jsdom não computa classe Tailwind, então o que
    // se trava aqui é a CLASSE, que é o que o bundle traduz.
    const titulo = tituloDasAcoes(renderCockpit(USUARIO));
    expect(titulo.className).toContain('uppercase');
  });
});
