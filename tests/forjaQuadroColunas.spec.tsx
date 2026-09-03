// Quadro da Forja — TODA fase declarada vira COLUNA RENDERIZADA (e na mesma ordem).
//
// ── O buraco que este arquivo fecha (medido em 2026-09-03, fecho do PR #6695) ─
//
// A decisão [W] de 2026-08-11 — "F4 Merge É coluna" — mantém 7 colunas no eixo
// Pipeline, divergindo DE PROPÓSITO do protótipo, que filtra F4 e desenha 6
// (`prototipo-ui/cowork/forja-page.jsx`: `PHASES.filter(p => p.id !== "F4")`).
// A divergência está declarada no charter e no docblock do `TrabalhoQuadro.tsx`.
//
// Só que ela não tinha defesa mecânica. A cadeia de paridade que existe é toda
// DECLARATIVA — ela lê constantes, e nunca o que a tela desenha:
//
//   protótipo ──PipelineParidadeTest──▶ backend ──UC-TRAB-07──▶ `const FASES`
//                                                                     │
//                                                          NINGUÉM    │
//                                                                     ▼
//                                                          colunas RENDERIZADAS
//
// Vetor concreto: um `.filter(f => f.key !== 'F4')` no `FASES.map(` do
// `TrabalhoQuadro.tsx` NÃO altera o bloco `const FASES`. O `UC-TRAB-07` extrai
// aquele bloco por regex e segue verde; o `PipelineParidadeTest` compara fonte de
// design × backend × cabeçalho e também não vê o render. Resultado: a decisão [W]
// seria revertida em silêncio por quem "corrigisse" 7→6 obedecendo o §3.4 do
// pacote de export — que diz 6 porque mediu o protótipo, e está certo sobre ele.
//
// ── Por que o assert NÃO crava "7" ───────────────────────────────────────────
//
// Cravar o número fabricaria falso-positivo: no dia em que o protocolo ganhar uma
// fase (protótipo → backend → espelho, a cadeia acima), a mudança é legítima e o
// teste reprovaria por estar desatualizado. O predicado honesto é relacional, e
// por isso deriva do DONO em vez de repetir o número dele (§5 2026-07-17 — não
// restatear número que outro sistema sabe melhor):
//
//     colunas renderizadas  ===  fases declaradas   (mesma contagem, MESMA ordem)
//
// Fase nova entra nos dois lados → passa. Filtro no `.map()` → o render encolhe
// enquanto a declaração fica → REPROVA, que é exatamente o vetor.
//
// ── O que este arquivo NÃO é ─────────────────────────────────────────────────
//
// NÃO é render-diff protótipo×produção (morto 2×: ADR 0290 + §5 2026-07-09).
// Não há protótipo aqui, não há screenshot, não há par, não há rede nem auth: é
// jsdom hermético sobre o NOSSO componente, e o que ele afirma é a decisão de
// produto — não fidelidade ao protótipo, de quem o Pipeline diverge de propósito.
//
// NÃO é guard sintático (a família com 7 lápides no §5: allowlist-de-pasta 06-30
// · `@scope` 07-09 · vocabulário 130 FP 07-16 · `toHaveKey` 100% FP 07-26 ·
// `toContain` 07-28 · par usuário/senha 08-02 · `jq` 08-11). Ele não procura
// `.filter` perto do `.map`: ele RENDERIZA e conta o DOM. Trocar o `.filter` por
// `slice`, por um `if`, por early-return ou por índice fixo cai igual.
//
// Refs: decisão [W] 2026-08-11 (`PipelineParidadeTest` §DIVERGENCIA_DECLARADA,
// hoje vazia) · `Index.charter.md` §"Diferenças declaradas do Quadro" ·
// `Modules/Forja/Tests/Feature/TrabalhoListaTest.php` (UC-TRAB-07) ·
// `memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md` §2026-09-03.

import { describe, it, expect, afterEach } from 'vitest';
import { render, cleanup } from '@testing-library/react';

import TrabalhoQuadro, {
  FASES_PIPELINE,
  STATUS_ATIVOS,
} from '../Modules/Forja/Resources/js/Pages/Forja/Trabalho/_components/TrabalhoQuadro';
import { STATUS_FJ } from '../Modules/Forja/Resources/js/Pages/Forja/Trabalho/_components/trabalhoTokens';
import type { TarefaLista } from '../Modules/Forja/Resources/js/Pages/Forja/Trabalho/_components/TrabalhoLista';

afterEach(cleanup);

/** Task mínima — só os campos que o Quadro lê pra decidir coluna e desenhar card. */
function tarefa(over: Partial<TarefaLista> = {}): TarefaLista {
  return {
    task_id: 'T-1',
    display_id: 'FORJA-1',
    title: 'tarefa de fixture',
    module: null,
    owner: null,
    priority: 'p2',
    status: 'todo',
    type: null,
    estimate_h: null,
    story_points: null,
    blocked_by: [],
    is_blocked: false,
    is_overdue: false,
    forja_tipo: null,
    forja_fase: null,
    forja_papel: null,
    forja_onda: null,
    frente_id: null,
    ...over,
  } as TarefaLista;
}

function desenhar(eixo: 'pipeline' | 'execucao', tasks: TarefaLista[] = []) {
  const { container } = render(
    <TrabalhoQuadro
      tasks={tasks}
      eixo={eixo}
      agents={[]}
      favoritos={new Set<string>()}
      onFavoritar={() => {}}
    />,
  );

  const colunas = Array.from(container.querySelectorAll('.fj-kcol'));

  return {
    container,
    colunas,
    /** O `<b>` do `.fj-kcol-top` — no Pipeline é o ID da fase; no Execução, o rótulo PT. */
    ids: colunas.map((c) => c.querySelector('.fj-kcol-top b')?.textContent ?? ''),
  };
}

describe('UC-TRAB-19 — o Quadro RENDERIZA uma coluna por fase declarada', () => {
  it('anti-falso-verde: as duas declarações têm conteúdo (senão 0 === 0 passaria)', () => {
    // Mesma guarda do UC-TRAB-07 e do UC-PIPE-01: sem isto, num dia em que as
    // constantes esvaziassem, "render vazio === declaração vazia" ficaria verde e
    // o arquivo inteiro viraria carimbo.
    expect(FASES_PIPELINE.length).toBeGreaterThan(3);
    expect(STATUS_ATIVOS.length).toBeGreaterThan(3);
  });

  it('Pipeline — uma coluna por fase, na MESMA ordem da declaração', () => {
    const { colunas, ids } = desenhar('pipeline');
    const esperado = FASES_PIPELINE.map((f) => f.key);

    // A contagem primeiro: ela é a que nomeia o defeito ("cortaram fase"). A
    // igualdade de ordem logo abaixo cobre o resto (troca, renome, reordenação).
    expect(
      `${colunas.length} coluna(s) para ${FASES_PIPELINE.length} fase(s) — desenhadas: ${ids.join(' · ')} | declaradas: ${esperado.join(' · ')}`,
    ).toBe(
      `${FASES_PIPELINE.length} coluna(s) para ${FASES_PIPELINE.length} fase(s) — desenhadas: ${esperado.join(' · ')} | declaradas: ${esperado.join(' · ')}`,
    );

    // Ordem importa: o funil é uma sequência (mesma razão do UC-PIPE-02).
    expect(ids).toEqual(esperado);
  });

  it('Pipeline — F4 Merge é coluna, e recebe card (decisão [W] 2026-08-11)', () => {
    // O caso acima cai se QUALQUER fase sumir. Este nomeia a que já sumiu uma vez:
    // de 2026-08-09 a 2026-08-11 o F4 não existia no backend porque o agente
    // derivou o quadro do CÓDIGO em vez da fonte de design (§5 2026-08-10).
    //
    // E ele vai além de "a coluna existe": prova que ela é ALIMENTADA. Coluna
    // desenhada que nunca recebe card seria decorativa — e o card cairia no
    // "fora do board", que é a redação de quem NÃO pertence ao pipeline.
    const { ids, colunas, container } = desenhar('pipeline', [
      tarefa({ task_id: 'T-F4', display_id: 'FORJA-F4', forja_fase: 'F4' }),
    ]);

    expect(ids).toContain('F4');

    const f4 = colunas[ids.indexOf('F4')];
    expect(f4?.querySelector('.fj-kcol-count')?.textContent).toBe('1');
    expect(f4?.querySelector('.fj-kcol-empty')).toBeNull();

    // Nada ficou de fora: a task do F4 pertence ao board.
    expect(container.querySelector('[data-testid="quadro-fora"]')).toBeNull();
  });

  it('Execução — uma coluna por status ativo, com o rótulo PT do canon', () => {
    const { colunas, ids } = desenhar('execucao');

    expect(colunas.length).toBe(STATUS_ATIVOS.length);

    // No Execução o slot do ID carrega o rótulo PT (`todo` → "A fazer"), não a chave.
    expect(ids).toEqual(STATUS_ATIVOS.map((s) => STATUS_FJ[s]?.label ?? s));
  });

  it('Execução — `done` segue FORA do board, e a tela diz quantos ficaram', () => {
    // A contrapartida honesta do caso anterior: o eixo Execução mostra o que está
    // EM CURSO. Se um dia `done` virasse coluna, a contagem de "fora" cairia a 0 e
    // quem compara card com KPI passaria a ver dois números diferentes.
    const { ids, container } = desenhar('execucao', [
      tarefa({ task_id: 'T-D', display_id: 'FORJA-D', status: 'done' }),
    ]);

    expect(ids).not.toContain(STATUS_FJ.done?.label);
    expect(container.querySelector('[data-testid="quadro-fora"]')?.textContent).toBe('1');
  });
});
