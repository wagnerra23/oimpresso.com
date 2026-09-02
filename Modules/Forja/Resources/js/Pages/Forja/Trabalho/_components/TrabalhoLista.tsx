// A LISTA do Trabalho — réplica do bloco `isLista` do `forja-page.jsx`
// (linhas 1152-1219 do protótipo). PARIDADE §11 Onda 4.
//
// Estrutura copiada, não redesenhada:
//   .fj-list[.compact]
//     └ .fj-group[.collapsed]
//         ├ .fj-group-head  →  .fj-group-toggle (chevron · título · contagem)
//         └ .fj-row × N     →  a linha densa de 13 slots
//     └ .fj-empty  (quando o filtro não casa nada)
//
// ── O QUE A LINHA MOSTRA, e de onde vem cada slot ───────────────────────────
// O protótipo põe treze coisas na linha; a medição de 2026-09-02 registrou que
// a produção mostrava três. Cada slot abaixo é dado REAL de `mcp_tasks` — nenhum
// é preenchido com valor de exemplo:
//
//   recuo           (slot fixo — ver abaixo)           prio-dot   priority
//   id              display_id                          tipo       forja_tipo ?? type
//   título          title                               tam        estimate_h / story_points
//   vínculos        blocked_by                          módulo     module
//   cadeado         is_blocked / blocked_by             fase/stat  forja_fase ?? status
//   dono            forja_papel ?? owner                pin/★      localStorage do viewer
//
// ── O ÉPICO QUE NÃO EXISTE (erro meu, corrigido antes do merge) ─────────────
// A 1ª versão desta lista tinha o chevron de sub-issues e o `EpicRoll` do
// protótipo, indexando as tarefas por `epic_id`. Estava ERRADO e teria ficado
// mudo pra sempre: em `mcp_tasks`, `epic_id` é FK pra **`McpEpic`**
// (`belongsTo(McpEpic::class)`, McpTask.php:230) — uma entidade OUTRA, não outra
// task. No protótipo o pai é um issue da MESMA lista (`kidsOf[issue.id]`), então
// a hierarquia que ele desenha simplesmente não tem equivalente aqui.
// Derivar do NOME do campo em vez de abrir a relação é a classe LC-08; o slot
// vira o recuo fixo, que é o que o protótipo mostra em linha sem filhas.
//
// ── OS DOIS SLOTS QUE NÃO VIERAM, e por que não é esquecimento ──────────────
//   `carry ×N`  — quantas vezes a issue foi carregada de onda encerrada. Exige
//                 histórico de ondas, que `mcp_tasks` não guarda.
//   `frescor`   — "lido @main / não verificado / sync Nd". Exige o carimbo de
//                 verificação contra o main, que não existe fora do mock.
// Os dois são CONDICIONAIS no protótipo (`issue.carry > 0 &&`), então a falta do
// dado já os apaga lá também. Inventar valor seria dado fantasma — o pedido
// desta onda proíbe com todas as letras.
//
// ── E A CAIXA DE SELEÇÃO, que é a diferença que se VÊ ───────────────────────
// O protótipo abre cada linha com um checkbox que alimenta a `.fj-bulkbar`
// (mudar fase, papel, prioridade, onda e status de N issues de uma vez). Isso é
// MUTAÇÃO EM MASSA, e não existe endpoint pra ela: o charter proíbe escrita fora
// do `TaskCrudService` (que valida o FSM), e a ADR 0388 diz que "réplica" é
// licença de APARÊNCIA, nunca de comportamento. Selecionar sem poder agir seria
// afordância falsa — a classe LC-15 do ledger. Fica declarado no charter e na
// lista de inconsistências, não escondido.


// Tipos canônicos do vocabulário de task — os mesmos que o Quadro e o Board
// usam. Sem eles a lista teria `string` solto e o cast pro `<TrabalhoQuadro>`
// viraria `as never`, que é esconder divergência de contrato em vez de tê-la.
import type { Status } from '@/Components/board/badges';
import type { Priority } from '@/Lib/taskTokens';
import {
  GroupChevron, LockIco, OwnerSeal, PhaseBadge, Pin, PrioDot, Star, StatusPill, TypeChip, VincChip,
} from './trabalhoAtomos';

export interface TarefaLista {
  task_id: string;
  display_id: string;
  title: string;
  module: string | null;
  owner: string | null;
  priority: Priority;
  status: Status;
  type: string | null;
  estimate_h: number | null;
  story_points: number | null;
  blocked_by: string[];
  is_blocked: boolean;
  is_overdue: boolean;
  forja_tipo: string | null;
  forja_fase: string | null;
  forja_papel: string | null;
  forja_onda: string | null;
  frente_id: number | null;
}

interface Props {
  tarefas: TarefaLista[];
  /** Rótulo por chave de grupo, já resolvido pelo pai (frentes vêm do backend). */
  grupos: [string, TarefaLista[]][];
  denso: boolean;
  colapsados: Set<string>;
  onColapsar: (g: string) => void;
  favoritos: Set<string>;
  onFavoritar: (id: string) => void;
  fixados: Set<string>;
  onFixar: (id: string) => void;
  /** Slugs de atores-agente (allowlist do backend) — alimenta o `OwnerSeal`. */
  agents: string[];
  /** Rótulo curto da fase (`F3 Code` → `Code`), do dono das fases. */
  faseLabel: (fase: string) => string | undefined;
}

export default function TrabalhoLista({
  tarefas, grupos, denso, colapsados, onColapsar,
  favoritos, onFavoritar, fixados, onFixar, agents, faseLabel,
}: Props) {
  return (
    <div className={'fj-list' + (denso ? ' compact' : '')} data-testid="trabalho-lista">
      {grupos.map(([g, itens]) => {
        const colapsado = colapsados.has(g);
        return (
          <div key={g} className={'fj-group' + (colapsado ? ' collapsed' : '')}>
            <div className="fj-group-head">
              <button type="button" className="fj-group-toggle" onClick={() => onColapsar(g)}
                aria-expanded={!colapsado} data-testid="trabalho-grupo">
                <GroupChevron colapsado={colapsado} />
                <span className="fj-group-title">{g}</span>
                <span className="fj-group-count">{itens.length}</span>
              </button>
            </div>

            {!colapsado && itens.map((t) => {
              const tam = t.estimate_h != null ? `${t.estimate_h}h` : t.story_points != null ? `${t.story_points}sp` : null;
              return (
                <div key={t.task_id} className={'fj-row' + (fixados.has(t.task_id) ? ' pinned' : '')} data-testid="trabalho-linha">
                  {/* Slot de recuo — no protótipo ele é o chevron de sub-issues
                      quando a issue tem filhas. Aqui é SEMPRE o recuo: ver o
                      docblock §"o épico que não existe". */}
                  <span className="fj-row-indent" />

                  <PrioDot prio={t.priority} title={`Prioridade ${t.priority.toUpperCase()}`} />
                  <span className="fj-id">{t.display_id}</span>
                  <TypeChip tipo={t.forja_tipo ?? t.type ?? 'doc'} />
                  <span className="fj-title">{t.title}</span>

                  {tam && <span className="fj-tam" title="Esforço — horas estimadas ou pontos">{tam}</span>}

                  <span className="fj-row-mid">
                    {/* Único vínculo REAL de `mcp_tasks` hoje. O protótipo mostra
                        até dois — mesmo teto, pra linha não estourar. */}
                    {t.blocked_by.slice(0, 2).map((b) => (
                      <VincChip key={b} k="issue" v={b} title={`Bloqueada por ${b}`} />
                    ))}
                    {t.module && <span className="fj-mod">{t.module}</span>}
                  </span>

                  {(t.is_blocked || t.blocked_by.length > 0) && <LockIco />}

                  {/* Fase quando é trabalho de TELA; senão o status de execução.
                      Ausência de fase é informação (infra/gate/ADR não têm), não
                      buraco — o charter proíbe tratá-la como dado faltando. */}
                  {t.forja_fase
                    ? <PhaseBadge fase={t.forja_fase} label={faseLabel(t.forja_fase)} />
                    : <StatusPill s={t.status} />}

                  <OwnerSeal papel={t.forja_papel} owner={t.owner} agents={agents} />
                  <Pin on={fixados.has(t.task_id)} onClick={() => onFixar(t.task_id)} />
                  <Star on={favoritos.has(t.task_id)} onClick={() => onFavoritar(t.task_id)} />
                </div>
              );
            })}
          </div>
        );
      })}

      {tarefas.length === 0 && (
        <div className="fj-empty" data-testid="trabalho-vazio">
          <p>Nenhum issue casa com o filtro.</p>
        </div>
      )}
    </div>
  );
}
