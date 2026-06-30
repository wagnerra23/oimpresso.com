# Cowork (design) — nova estrutura, como buscar info, e telas a desenvolver

> **LER NO INÍCIO DE CADA SESSÃO DE DESIGN.** Contrato de adaptação à estrutura SSOT (Wagner 2026-06-23, pós-adversário). Substitui o hábito de "exportar tudo + manter memória própria". Detalhe do método na [ADR-proposta SSOT](../memory/decisions/proposals/2026-06-23-prototipo-ssot-unico-com-historico.md) (§Método operacional endurecido) + livro-razão [`RECONCILIACAO-COWORK-MEMORIA.md`](RECONCILIACAO-COWORK-MEMORIA.md).

## 🚀 O QUE FAZER AGORA (migração — uma vez)
Você (Cowork) tem que **adotar a estrutura nova + reconstruir** como exporta:
1. **Limpe seu workspace** (esteira ≠ armazém):
   - Apague sua cópia de `memory/` (você lê a canônica via MCP, não guarda).
   - Apague cópias de process docs (CLAUDE/STATUS/PROTOCOL/CODE_NOTES/CONSTITUICAO…) — são canon no repo.
   - Apague resíduo de processo: `_arquivo/`, `benchmark/`, `uploads/`, screenshots-as-source, prompts `GAPS_*`/`FORCE_*`, e os docs `Adversário`/`Tribunal`/`Avaliação`/`Estado-da-Arte` (a conclusão deles vira `memory/`; o cru sai).
   - Apague charters/casos do seu export — são canon vivo (`resources/js/Pages/`).
2. **Reconstrua o export = só BUILD** (jsx/tsx/css/html). Seu zip daqui pra frente leva só isso.
3. **Re-exporte as 2 telas órfãs** que faltam no `cowork/`: **`compras-grade-matrix`** e **`inventario-migracao`** (a máquina trava nelas até você exportar — allowlist zera).
4. **Adote o read-order**: no início de cada sessão leia (no `main`/MCP) → **este doc** + **`FRESCOR-PRODUCAO-vs-PROTOTIPO.md`** + o **charter** da tela que vai mexer.

## 🔁 ROTINA (cada handoff)
1. Pegue a tela no **FRESCOR** (🟠 = desenvolver · 🔵 = puxe o vivo, não refaça · ⚪ = espera [W]).
2. Leia o **charter** dela no `main` → o que a tela é + seus **dados/props/estado**.
3. Desenvolva → exporte o **build** pro `cowork/`.
4. Pendência sua → `COWORK_NOTES.md` "📥 Pendentes"; leia o retorno do Code em `CODE_NOTES.md` + `FRESCOR`.
5. **Nunca**: memória própria · despejo de sessão · transporte (PNG/dupes) · duplicar charter/process-doc.
> A máquina `cowork-ssot-guard` **dá erro** se quebrar isso (`.md` no `cowork/` · bundle datado · protótipo fora do lugar).

## Por que mudou
Antes: cada handoff era um zip flat despejado em pastas espalhadas (`prototipos/<tela>/`) + cópia da memória → **bagunça, sem diff, fonte stale, duplicação**. Agora: **1 fonte da verdade com histórico**, memória única canônica, e um canal que te diz onde a produção já te passou.

## A estrutura (o que é o quê)
| Camada | Onde | Quem manda | Você (design) |
|---|---|---|---|
| **Seu export visual** | `prototipo-ui/cowork/` (sobrescrito a cada handoff) | **Cowork** | exporta aqui (só fonte: jsx/tsx/css/html) |
| **Memória** (decisões, sessões, regras) | `memory/**` → **MCP** | **repo/canon** | **só LÊ** (nunca mantenha memória paralela) |
| **Charters & casos** (contrato por tela) | `resources/js/Pages/**/*.charter.md` · `*.casos.md` | **repo/canon** | **só LÊ** (NÃO re-crie/duplique no seu export) |
| **Tela viva** (produção) | `resources/js/Pages/**/*.tsx` no `main` | **repo/canon** | **só LÊ** (é o estado real, não sua fotocópia) |

## Como buscar a informação (fonte única = a memória canônica / MCP)
1. **Estado real de uma tela** → leia o `.tsx` + `.charter.md` dela **no `main`** (MCP/git), **nunca** sua cópia local (ela envelhece — é o seu próprio PORTÃO 1).
2. **Decisões/regras/identidade** → `memory/decisions/` (ADRs) via MCP `decisions-search`; a memória da tela → o **charter** dela.
3. **O que a produção já passou de você** → [`FRESCOR-PRODUCAO-vs-PROTOTIPO.md`](FRESCOR-PRODUCAO-vs-PROTOTIPO.md) (🔵 produção à frente = **puxe o vivo**, não re-exporte como fonte · 🟠 produção atrás = **desenvolva** · ⚪ fundação = espere [W]).
4. **O que já foi conciliado** (idempotência) → [`RECONCILIACAO-COWORK-MEMORIA.md`](RECONCILIACAO-COWORK-MEMORIA.md).

## O que você NÃO faz mais
- ❌ **Não mantenha memória própria** nem exporte `memory/` — a única é a canônica. Conhecimento novo seu é **destilado** (resumo ancorado por-tela no charter/SPEC), nunca despejado como sessão crua.
- ❌ **Não exporte transporte**: screenshots/PNG, duplicatas `?v=`, `.bak`, prompts já processados, DS antigos. Só fonte.
- ❌ **Não re-crie charters/casos** no seu export — são canon; duplicar gera conflito (foram removidos do `cowork/`).
- ❌ **Não trate seu export como fonte** de uma tela 🔵 — a produção já passou; puxe o vivo.

## O loop (cada handoff)
`Você exporta → cowork/ (overwrite+commit = diff)` → `Code: git diff = o que mudou → mapeia só o que mudou → destila memória nova no charter/SPEC` → `frescor volta pra você`. Telas/DS compartilhado (sidebar, PageHeader) = PR de fundação sequencial, nunca em paralelo.

## Telas a desenvolver (estado 2026-06-23 — do FRESCOR)
**🟠 DESENVOLVER (produção atrás — valor real):**
- **Compras · Grade Matrix** (tam×cor) — gap de produto pro vestuário (Larissa/ROTA LIVRE). Componente `GradeMatrixInput.tsx` existe mas órfão + falta endpoint backend. Caller canônico = `Purchase/Create.tsx` (não a Index). Gated por ADR de convergência compras↔purchase.

**🔵 NÃO re-exportar como fonte — produção já passou (puxe o vivo):**
- **Atendimento/CaixaUnificada** (V4, charter v19) — é o OURO; "não repintar" (LEI). Use-a como referência de DS pras outras telas, não a refaça.
- **Cliente** (Crm) — drawer 760 + US-078 já no vivo.
- **PageHeader** — já é canon (roxo 295 universal).

**⚪ FUNDAÇÃO — espera decisão [W] (não paraleliza com telas):**
- **Sidebar** — trava no desempate dark (`cockpit.css`) × light (UI-0014). Cmd+K visível é o único ganho barato livre.

**📋 RESTO DO WORKSPACE (vendas, financeiro, oficina, kb, norte, forja…):** não foi feito frescor por-tela ainda — **não assuma**; rode o frescor (compara seu export × `Pages/<Mod>/<Tela>.tsx` no `main`) antes de tratar como "a desenvolver".

## Roteamento — onde cada arquivo do export vai
| Tipo de arquivo no export | Destino | Por quê |
|---|---|---|
| `*-page.jsx`/`.tsx`/`.css`/`.html` (build da tela) | `prototipo-ui/cowork/` | design SSOT (build) |
| `*.charter.md` | canon `resources/js/Pages/<Mod>/<Tela>.charter.md` | contrato vivo — você lê/atualiza, não duplica |
| `*.casos.md` | canon (ao lado do charter) | casos de uso |
| sessão/análise `.md` (raciocínio) | **destilar** resumo no charter/SPEC; raw fica no zip | conhecimento = canon, não dump |
| process docs (STATUS/CODE_NOTES/PROTOCOL…) | já são canon em `prototipo-ui/` root | não re-exportar |
| `memory/**` do export | **ignorar** (canon é o repo/MCP) | fonte única de memória |
| ADRs | canon `memory/decisions/` | não duplicar |
| PNG/screenshot/dupes/`.bak` | descartar (transporte) | derivado/lixo |

## Como o Cowork se limpa (auto-limpeza)
No seu workspace (Claude.ai), antes de exportar:
1. **Apague sua cópia de `memory/`** — você lê a canônica via MCP, não guarda.
2. **Apague cópias de process docs** (CLAUDE/STATUS/PROTOCOL/CODE_NOTES…) — são canon no repo.
3. **Não versione screenshots/PNG** como fonte — são derivados.
4. **Charters/casos**: edite o canon (via cowork-inbox/PR), nunca cópia divergente.
5. **Exporte só o build** (jsx/tsx/css/html).
> Regra checável: se um arquivo tem dono canônico fora do `cowork/`, **não vai no export**.

## Por tela: você sabe O QUE e os DADOS
Cada tela tem um **charter** (`<Tela>.charter.md`) = o contrato: missão, goals/non-goals, **dados/props/estado**, decisões já tomadas. Antes de desenvolver:
1. Pegue a tela do **FRESCOR** (🟠 = desenvolver).
2. Leia o **charter** dela no `main` → o que a tela é + seus dados.
3. Exporte o build pro `cowork/<arquivo>`.
> Tela sem charter ainda → pede `charter-write` antes (não inventa dados).

## Canais de pendência (bidirecional)
- **Cowork → Code** (o que você pede): [`COWORK_NOTES.md`](COWORK_NOTES.md) → seção "📥 Pendentes".
- **Code → Cowork** (o que falta a você): [`CODE_NOTES.md`](CODE_NOTES.md) + [`FRESCOR-PRODUCAO-vs-PROTOTIPO.md`](FRESCOR-PRODUCAO-vs-PROTOTIPO.md) (telas 🟠 + onde a produção te passou).
> Já existem — **use, não crie doc novo** (anti-scatter).

## A máquina que protege isso (nunca mais acontece)
[`scripts/governance/cowork-ssot-guard.mjs`](../scripts/governance/cowork-ssot-guard.mjs) (roda no `design-memory-gate.yml`) **dá erro** se: `.md` no `cowork/` · bundle datado `cowork-*` · protótipo fora do `cowork/`. Allowlist transitório (`compras-grade-matrix`, `inventario-migracao`) = telas que VOCÊ deve exportar pro `cowork/` pra zerar.

---
_Origem: handoff 2026-06-23 + red-team adversarial da integração de memória. Pareado com a [ADR-proposta SSOT](../memory/decisions/proposals/2026-06-23-prototipo-ssot-unico-com-historico.md) (método) e [`FRESCOR-PRODUCAO-vs-PROTOTIPO.md`](FRESCOR-PRODUCAO-vs-PROTOTIPO.md) (frescor por-tela)._
