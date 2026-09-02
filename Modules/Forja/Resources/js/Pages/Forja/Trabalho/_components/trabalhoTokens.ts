// Arquivo SEM componente de propósito: constante exportada ao lado de componente
// quebra o Fast Refresh do Vite (`react-refresh/only-export-components`), e o
// conserto certo é separar — não absorver o aviso na baseline.
//
// Vocabulário visual do Trabalho — os mapas da FONTE DE DESIGN (PARIDADE §11 Onda 4).
//
// Cada componente daqui é a cópia do átomo homônimo do protótipo, com o MESMO
// nome de classe do bundle `cowork-forja-bundle.css` (Onda 1). Nada foi
// redesenhado: onde este arquivo diverge do protótipo, é bug meu, não escolha.
// A lei é a ADR 0388 ("réplica primeiro": o protótipo é o contrato de layout).
//
// ── POR QUE `oklch()` INLINE, e por que isso não é descuido ──────────────────
// O protótipo pinta prioridade, tipo, fase e status por HUE calculado
// (`oklch(0.6 0.18 <hue>)`). Não há token do DS para "a cor da fase F1.5" — a
// escala é contínua e mora na fonte de design. Pela ADR 0388 D-2 isso vira item
// na lista de inconsistências (`INCONSISTENCIAS-replica.md`), não motivo pra
// mudar o layout. Trocar por token seria inventar um desenho que ninguém pediu.
//
// ── O QUE NÃO ESTÁ AQUI, e por quê ──────────────────────────────────────────
// `FrescorPill` e o chip `carry` do protótipo NÃO existem neste arquivo: são
// campos que `mcp_tasks` não tem (`frescor`, `frescorDias`, `carry`). No
// protótipo os dois são condicionais (`issue.carry > 0 &&`), então a ausência
// do dado já os apaga lá — renderizar um valor fixo seria dado fantasma. Ver
// o charter §"Diferenças declaradas".

/* ─── Vocabulário da FONTE DE DESIGN (`prototipo-ui/cowork/forja-data.jsx`) ───
 *
 * Estes mapas são espelho consciente do protótipo, não declaração nova. Cada um
 * tem um caso que cruza os dois lados (`UC-TRAB-11`/`12`/`13`) pelo mesmo motivo
 * do `UC-PIPE-*`: espelho sem trava vira segunda declaração que diverge na
 * primeira mudança e ninguém percebe.
 */

/** `FJ_PRIO` — hue por prioridade. `mcp_tasks` guarda em minúsculas. */
export const PRIO_HUE: Record<string, number> = { p0: 25, p1: 60, p2: 295, p3: 250 };

/** `FORJA_TYPES` — rótulo e hue por tipo de trabalho. */
export const TIPOS: Record<string, { label: string; hue: number }> = {
  tela:   { label: 'Tela',   hue: 295 },
  gate:   { label: 'Gate',   hue: 195 },
  adr:    { label: 'ADR',    hue: 270 },
  bug:    { label: 'Bug',    hue: 25 },
  refino: { label: 'Refino', hue: 60 },
  infra:  { label: 'Infra',  hue: 230 },
  doc:    { label: 'Doc',    hue: 150 },
  epico:  { label: 'Épico',  hue: 320 },
};

/** `FORJA_STATUS` — rótulo PT e hue. `neutral` pinta o ponto de cinza. */
export const STATUS_FJ: Record<string, { label: string; hue: number; neutral?: boolean }> = {
  doing:   { label: 'Fazendo',   hue: 250 },
  review:  { label: 'Revisão',   hue: 60 },
  todo:    { label: 'A fazer',   hue: 250, neutral: true },
  blocked: { label: 'Bloqueada', hue: 25 },
  backlog: { label: 'Backlog',   hue: 250, neutral: true },
  done:    { label: 'Concluído', hue: 150 },
};

/**
 * `FORJA_PHASES` — só o HUE.
 *
 * A lista de fases (quais existem e em que ordem) tem UM dono no front:
 * `TrabalhoQuadro.tsx`, travado contra o backend pelo `UC-TRAB-07` e contra a
 * fonte de design pelo `PipelineParidadeTest`. Não a redeclaro aqui — o que
 * falta lá é só a cor, que o backend não serve (perda declarada no `UC-PIPE-04`).
 */
export const FASE_HUE: Record<string, number> = {
  'F0': 250, 'F1': 295, 'F1.5': 270, 'F2': 60, 'F3': 195, 'F3.5': 150, 'F4': 145,
};

/**
 * `FORJA_ACTORS` — os papéis do loop, com a cor e o tipo (humano × agente).
 *
 * A distinção agente/humano é o conceito central da Forja (o subtítulo do hub
 * fala em "atores humano vs agente"); perdê-la esvazia a tela. `desc` entra no
 * `title` porque é o que o protótipo mostra ao passar o mouse.
 */
export const PAPEIS: Record<string, { nome: string; agente: boolean; cor: string; desc: string }> = {
  W:  { nome: 'Wagner',           agente: false, cor: 'oklch(0.57 0.16 25)',  desc: 'Decide · aprova screenshot e merge' },
  CC: { nome: 'Claude Cowork',    agente: true,  cor: 'oklch(0.55 0.15 295)', desc: 'F1 — protótipo visual' },
  CD: { nome: 'Claude Design',    agente: true,  cor: 'oklch(0.60 0.13 60)',  desc: 'F1.5 — critique' },
  CL: { nome: 'Claude Code',      agente: true,  cor: 'oklch(0.52 0.10 195)', desc: 'F3 — Inertia/React real' },
  CA: { nome: 'Claude A11y',      agente: true,  cor: 'oklch(0.55 0.13 150)', desc: 'F3.5 — WCAG 2.1 AA' },
  AN: { nome: 'Claude Analista',  agente: true,  cor: 'oklch(0.50 0.10 195)', desc: 'F0 — triagem & enriquecimento de ticket' },
  W2: { nome: 'Wagner aprovador', agente: false, cor: 'oklch(0.52 0.08 250)', desc: 'F2 + F4 síncronos' },
};
