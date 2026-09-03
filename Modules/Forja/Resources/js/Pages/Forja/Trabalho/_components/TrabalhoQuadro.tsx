// Quadro do Trabalho — a RÉPLICA do `KanbanView` do protótipo (PARIDADE §11 Onda 5).
//
// Árvore do protótipo (`prototipo-ui/cowork/forja-page.jsx` :467-503), 1:1:
//
//   .fj-quadro-wrap
//     ├ .fj-quadro-ancora        →  o parágrafo que explica o eixo E o recorte
//     └ .fj-kanban
//         └ .fj-kcol[--ph]  × N
//             ├ .fj-kcol-head
//             │   ├ .fj-kcol-top   (dot · id · rótulo · contagem)
//             │   ├ .fj-kcol-quem  (RoleBadge do dono · o que ele faz)   ← só Pipeline
//             │   └ .fj-kcol-sai   ("sai quando: …")                     ← só Pipeline
//             └ .fj-kcol-body
//                 ├ .fj-kc × N     (o card)
//                 └ .fj-kcol-empty (coluna vazia)
//
// A versão anterior era DS puro (`Grid`/`Card`/`Inline` + utilitárias Tailwind).
// Não estava errada — estava ANTES da ADR 0388, que fez do protótipo o contrato
// de layout. O que muda aqui é a aparência; as decisões de PRODUTO abaixo (dois
// eixos, o filtro do Pipeline, o recorte honesto, sem drag) vêm intactas.
//
// ── DOIS EIXOS sobre a MESMA lista, e por que ────────────────────────────────
//
//   PIPELINE (F0→F4) — "em que ponto do protocolo de TELA isto está?"
//     Só faz sentido pra trabalho de tela. Task de infra/gate/ADR não tem fase, e
//     isso é correto — não é dado faltando. Por isso o eixo FILTRA em vez de
//     inventar uma coluna "sem fase": card sem fase não pertence a este board.
//
//   EXECUÇÃO (status canon) — "o que está andando?"
//     Vocabulário de `mcp_tasks` (backlog/todo/doing/review/done/blocked). Vale
//     pra TODA task, inclusive as que não são de tela. É onde infra/gate/ADR vive.
//
// ⚠️ CORREÇÃO (decisão [W] 2026-08-11). A versão anterior deste comentário
// afirmava "F4/`done` NÃO é coluna do pipeline: quando a tela conclui, ela sai do
// board e vira changelog". Eu inventei isso derivando do CÓDIGO em vez de abrir a
// fonte de design — `prototipo-ui/cowork/forja-data.jsx` sempre teve `F4 Merge`
// com `owner: "W2"`, e os charters de Trabalho/Aprovações já diziam "F0→F4 é
// constituição". Ver proibicoes §5 2026-08-10.
//
// F4 Merge É coluna: merge é ESTADO DE TRABALHO com dono humano, não arquivo.
// O eixo Execução é outra pergunta — lá `done`/`cancelled` seguem fora, porque
// board de trabalho mostra o que está em curso.
//
// ⚠️ E É AQUI QUE A RÉPLICA DIVERGE DO PROTÓTIPO, DE PROPÓSITO. O `KanbanView`
// filtra F4 fora (`forja-page.jsx` :473 — `PHASES.filter(p => p.id !== "F4")`) e
// desenha **6** colunas; este board desenha **7**. Medido no protótipo rodando
// em 2026-09-03: `ids = [F0, F1, F1.5, F2, F3, F3.5]`.
//
// Copiar aquele filtro REVERTERIA a decisão [W] de 2026-08-11 em silêncio — foi
// ela que fez o backend ganhar a fase e esvaziou a `DIVERGENCIA_DECLARADA` do
// `PipelineParidadeTest`. Por isso a divergência está declarada no charter
// §"Diferenças declaradas do Quadro" em vez de "corrigida": aqui o protótipo é
// que está atrás do produto, não o contrário.
//
// Consequência de copy, e ela é obrigatória: o parágrafo-âncora daqui diz
// `(F0 → F4)` onde o protótipo diz `(F0 → F3.5)` + "no merge (F4) ele sai do
// quadro e vira entrada no changelog". Manter a copy literal contradiria as
// colunas logo abaixo dela.
//
// ── SEM DRAG, e isto NÃO é dívida desta onda ─────────────────────────────────
// O protótipo arrasta card (`draggable` + `onDrop` → `onMove`/`onMoveExec`) e a
// coluna vazia dele diz "arraste aqui". Aqui não: mover card é MUTAÇÃO, e mutação
// fora do caminho governado (`TaskCrudService`, que valida o FSM) seria um segundo
// caminho de escrita — o erro que a Mesa de Aprovações evitou de propósito. É
// Non-Goal escrito no charter, não esquecimento.
//
// Por isso a coluna vazia diz **"vazia"**, não "arraste aqui": anunciar um gesto
// que a tela não escuta é afordância falsa (LC-15). A diferença está declarada no
// charter §"Diferenças declaradas".
//
// ── Os selos são os ÁTOMOS-RÉPLICA, não os do `shared/` ──────────────────────
// `PrioDot`/`OwnerSeal`/`TypeChip`/`Star` vêm de `./trabalhoAtomos` — os mesmos
// que a lista usa desde a Onda 4. O charter tinha um anti-hook mandando usar
// `shared/TaskBadges`; ele foi escrito ANTES da ADR 0388 e está reconciliado no
// charter desta onda. O que aquele anti-hook protege — não perder a distinção
// AGENTE × HUMANO — continua honrado: é exatamente o que o `OwnerSeal` faz, com
// a allowlist `agents` do backend (nunca heurística de nome).

import { FASE_HUE, STATUS_FJ } from './trabalhoTokens';
import { LockIco, OwnerSeal, PrioDot, RoleBadge, Star, TypeChip } from './trabalhoAtomos';
import type { TarefaLista } from './TrabalhoLista';

export type EixoQuadro = 'pipeline' | 'execucao';

/**
 * As fases do protocolo — MESMA lista e MESMOS rótulos do `ForjaQuadroService`
 * (`Modules/Forja/Services/ForjaQuadroService.php`). Duplicar aqui seria criar
 * uma segunda declaração do pipeline; isto é espelho consciente do backend, e o
 * `UC-TRAB-07` trava a igualdade dos dois lados (ele lê as `key:` deste bloco).
 *
 * `owner`/`faz`/`sai` NÃO vêm do backend — ele serve só `key` e `label`. Eles são
 * a definição do protocolo, e a fonte é `forja-data.jsx` (`FORJA_PHASES`), a mesma
 * de onde o `FASE_HUE` foi espelhado na Onda 4 e pelo mesmo motivo: é vocabulário
 * de design, constante, que o payload não tem por que carregar por task. São o que
 * torna a coluna auto-explicativa no protótipo (`.fj-kcol-quem` / `.fj-kcol-sai`);
 * sem eles a réplica perde duas das três linhas do cabeçalho.
 */
const FASES: { key: string; label: string; owner: string; faz: string; sai: string }[] = [
  { key: 'F0',   label: 'Brief',      owner: 'W',  faz: 'você escreve o pedido',            sai: 'brief aceito → agente assume' },
  { key: 'F1',   label: 'Design',     owner: 'CC', faz: 'protótipo visual no Cowork',       sai: 'handoff + ✓ lido @main' },
  { key: 'F1.5', label: 'Critique',   owner: 'CD', faz: 'avaliação heurística do design',   sai: 'score ≥ 80' },
  { key: 'F2',   label: 'Screenshot', owner: 'W2', faz: 'VOCÊ aprova o visual',             sai: 'seu aprovo (gate F2)' },
  { key: 'F3',   label: 'Code',       owner: 'CL', faz: 'implementação Inertia/React real', sai: 'PR aberto + gates verdes' },
  { key: 'F3.5', label: 'A11y',       owner: 'CA', faz: 'acessibilidade WCAG 2.1 AA',       sai: 'a11y verde' },
  { key: 'F4',   label: 'Merge',      owner: 'W2', faz: 'VOCÊ funde o PR',                  sai: 'merge → entra no changelog' },
];

/** Colunas do eixo Execução — as ATIVAS. `done`/`cancelled` saem do board. */
const STATUS_ATIVOS: string[] = ['todo', 'doing', 'review', 'blocked'];

/**
 * Uma coluna do board, nos DOIS eixos.
 *
 * `rotulo`/`owner`/`faz`/`sai` são `null` no eixo Execução — lá o protótipo mostra
 * só o rótulo PT do status no lugar do ID, sem as duas linhas de cabeçalho que
 * explicam o dono da fase (`{fases && …}` no protótipo, :488-490).
 */
interface ColunaQuadro {
  chave: string;
  id: string;
  rotulo: string | null;
  hue: number;
  owner: string | null;
  faz: string | null;
  sai: string | null;
  itens: TarefaLista[];
}

/** O card do quadro — `.fj-kc` do protótipo (`KanbanCard`, :445-465). */
function QuadroCard({
  t, favorito, onFavoritar, agents,
}: {
  t: TarefaLista;
  favorito: boolean;
  onFavoritar: (id: string) => void;
  agents: string[];
}) {
  return (
    <div className="fj-kc" data-testid="quadro-card">
      <div className="fj-kc-top">
        <PrioDot prio={t.priority} title={`Prioridade ${t.priority.toUpperCase()}`} />
        <span className="fj-id">{t.display_id}</span>
        {t.forja_tipo && <TypeChip tipo={t.forja_tipo} />}
        <span className="fj-kc-spacer" />
        <Star on={favorito} onClick={() => onFavoritar(t.task_id)} />
      </div>

      <div className="fj-kc-title">{t.title}</div>

      <div className="fj-kc-foot">
        <OwnerSeal papel={t.forja_papel} owner={t.owner} agents={agents} />
        {t.forja_onda && <span className="fj-onda-chip">~{t.forja_onda}</span>}
        <span className="fj-kc-spacer" />
        {t.is_blocked && <LockIco />}
      </div>
    </div>
  );
}

export default function TrabalhoQuadro({
  tasks, eixo, agents = [], favoritos, onFavoritar,
}: {
  tasks: TarefaLista[];
  eixo: EixoQuadro;
  agents?: string[];
  favoritos: Set<string>;
  onFavoritar: (id: string) => void;
}) {
  const pipeline = eixo === 'pipeline';

  // Tipo explícito de propósito: os dois ramos do ternário têm shapes diferentes
  // (`rotulo`/`owner`/`faz`/`sai` só existem no Pipeline), e um `.map()` sobre a
  // UNIÃO de dois arrays não compila. Uma forma só, com os campos do Execução em
  // `null`, é o que mantém o JSX abaixo com um caminho só.
  const colunas: ColunaQuadro[] = pipeline
    ? FASES.map((f) => ({
        chave: f.key,
        // No Pipeline o protótipo mostra o ID em mono (`F1.5`) e o rótulo ao lado
        // ("Critique") — dois slots distintos, não um rótulo concatenado.
        id: f.key,
        rotulo: f.label,
        // `?? 250` porque o índice é `number | undefined` sob `noUncheckedIndexedAccess`.
        // 250 é o hue neutro do protótipo (o mesmo default do eixo Execução).
        hue: FASE_HUE[f.key] ?? 250,
        owner: f.owner,
        faz: f.faz,
        sai: f.sai,
        // Só quem TEM a fase. Card sem fase não é trabalho de tela — não
        // pertence a este board, e forçá-lo numa coluna mentiria sobre ele.
        itens: tasks.filter((t) => t.forja_fase === f.key),
      }))
    : STATUS_ATIVOS.map((s) => ({
        chave: s,
        // No Execução só há um slot: o rótulo PT do status ocupa o lugar do ID.
        id: STATUS_FJ[s]?.label ?? s,
        rotulo: null,
        hue: STATUS_FJ[s]?.hue ?? 250,
        owner: null,
        faz: null,
        sai: null,
        itens: tasks.filter((t) => t.status === s),
      }));

  const totalNoBoard = colunas.reduce((n, c) => n + c.itens.length, 0);
  const foraDoBoard = tasks.length - totalNoBoard;

  return (
    <div className="fj-quadro-wrap">
      {/* O parágrafo-âncora do protótipo já carrega a honestidade sobre o recorte:
          o board NUNCA mostra tudo, e quem conta card e compara com o KPI precisa
          saber quantas ficaram de fora e por quê — senão conclui que sumiu task. */}
      <p className="fj-quadro-ancora" data-testid="quadro-recorte">
        {pipeline ? (
          <>
            <b>O ciclo de vida de cada tela, do brief à acessibilidade.</b> Cada card avança
            da esquerda pra direita conforme o protocolo formaliza a fase (F0 → F4).
            {foraDoBoard > 0 && (
              <>
                {' '}<b data-testid="quadro-fora">{foraDoBoard}</b> issue(s) sem fase
                (infra · gate · ADR) vivem no eixo Execução.
              </>
            )}
          </>
        ) : (
          <>
            <b>Execução de todo o trabalho — visual ou não.</b> As 4 colunas ativas do canon
            (A fazer → Fazendo → Revisão → Bloqueada); Backlog e Concluído não são colunas —
            ficam na Lista, via KPI-filtro.
            {foraDoBoard > 0 && (
              <>
                {' '}<b data-testid="quadro-fora">{foraDoBoard}</b> fora (backlog, concluídas
                ou canceladas) — board mostra o que está em curso.
              </>
            )}
          </>
        )}
      </p>

      <div className="fj-kanban" data-testid="trabalho-quadro">
        {colunas.map((col) => (
          <section key={col.chave} className="fj-kcol" style={{ '--ph': col.hue } as React.CSSProperties}>
            <header className="fj-kcol-head">
              <div className="fj-kcol-top">
                <span className="fj-kcol-dot" />
                <b>{col.id}</b>
                {col.rotulo && <span className="fj-kcol-lbl">{col.rotulo}</span>}
                <span className="fj-kcol-count">{col.itens.length}</span>
              </div>

              {col.owner && (
                <div className="fj-kcol-quem">
                  <RoleBadge papel={col.owner} />
                  <span className="fj-kcol-faz">{col.faz}</span>
                </div>
              )}
              {col.sai && <div className="fj-kcol-sai">sai quando: <b>{col.sai}</b></div>}
            </header>

            <div className="fj-kcol-body">
              {col.itens.map((t) => (
                <QuadroCard
                  key={t.task_id}
                  t={t}
                  favorito={favoritos.has(t.task_id)}
                  onFavoritar={onFavoritar}
                  agents={agents}
                />
              ))}
              {/* "vazia", não "arraste aqui" — ver o bloco SEM DRAG no topo. */}
              {col.itens.length === 0 && <div className="fj-kcol-empty">vazia</div>}
            </div>
          </section>
        ))}
      </div>
    </div>
  );
}

export { FASES as FASES_PIPELINE, STATUS_ATIVOS };
