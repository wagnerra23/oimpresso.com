# Destino — Handoff Cowork "Oimpresso ERP Comunicação Visual" · PROCESSO_MEMÓRIA_CC

**Data:** 2026-06-02
**Fonte:** claude.ai/design — handoff bundle (`api.anthropic.com/v1/design/h/P2YXuYMvf2WuqsCe6qqRLg`)
**Arquivo aberto pelo [W]:** `project/PROCESSO_MEMORIA_CC.md` → **tarefa: implementar o método de memória anti-regressão.**
**Executor:** [CC] (Claude Code). **Escopo escolhido por [W]:** _Process install_ (doc + espinha + guards rodáveis) · wiring _working-tier + proposta CLAUDE.md_.

> Nota de origem: o 1º link (`5frcSKzpw2xN2yIcOM7DqQ`) **expirou (404)** no meio da sessão; [W] reexportou. Este destino usa o bundle novo (`P2YX…`), com `PROCESSO_MEMORIA_CC.md` já **24.500 B** (vs 22.223 do 1º) — ganhou a seção **★ NÚCLEO (13 invariantes)** e os artefatos `LICOES_CC.md` / `METODO_TELA` / `*.casos.md` / `_PROPOSTA-0244`.

---

## O que é o PROCESSO_MEMÓRIA_CC

**Não é tela** — é a **raiz do método** (plano ⚙️ PROCESSO da arquitetura de 3 planos). Define como a memória de design evolui **sem regredir**: anéis 🔍Avaliar→🧪Testar→✅Adotar→⛔Descartar (Technology Radar), Charter/Register/ADR, e as defesas mecânicas (DS-GUARD §8 · Bateria §9 · Benchmark §11 · Gatilho §12 · Integridade §15). Lei suprema: _REGRESSÃO É INACEITÁVEL._

## O que foi landeado (escopo: process install)

| Artefato | Alvo no git | Tier (§14) | Origem | Estado |
|---|---|---|---|---|
| Método (raiz) | `memory/reference/prototipo-ui/PROCESSO_MEMORIA_CC.md` | 2 (canon) | bundle, **verbatim** (24.500 B) | ✅ landeado |
| Lições | `memory/LICOES_CC.md` | 2 (canon) | bundle, **verbatim** (L-01..L-25, 36.661 B) | ✅ landeado |
| DS-GUARD (§8) | `scripts/design/ds-guard.mjs` | — (ferramenta) | **implementado** do §8 (lógica verbatim) | ✅ rodável |
| Integridade (§15) | `scripts/design/integrity-check.mjs` | — (ferramenta) | **implementado** do §15 (IT1–IT7) | ✅ rodável |
| Espinha | `memory/reference/prototipo-ui/STATUS.md` | **1 (só Cowork)** | bundle, **snapshot read-only** | ⚠️ ver abaixo |
| Espinha | `memory/reference/prototipo-ui/MEMORY_INDEX.md` | **1 (só Cowork)** | bundle, **snapshot read-only** | ⚠️ ver abaixo |

### ⚠️ STATUS.md / MEMORY_INDEX.md = snapshot Tier-1 (Cowork-autoritativo)
A §14 marca a espinha como **"— (só Cowork)"**: a fonte viva é o Cowork. Estas cópias no git são **snapshot read-only de 2026-06-02** (TESTE-03: "repo é snapshot read-only — ninguém edita os dois lados"). **Não editar aqui** — editar no Cowork e reexportar. Foram landeadas porque [W] pediu explicitamente no escopo; servem de referência + cravam o ponteiro always-read no lado git (STATUS já aponta pra `PROCESSO_MEMORIA_CC`, satisfaz IT3).

## Wiring (always-read · §7 — "doc morto sem o ponteiro")
- ✅ **STATUS → PROCESSO**: já presente no próprio `STATUS.md` (linha 4 "🌱 LER TAMBÉM"). IT3 verde.
- ✅ **`COWORK_NOTES.md`**: banner 🌱 RAIZ DO MÉTODO adicionado no header (working-tier).
- ✅ **`CLAUDE.md` (raiz)**: passo **4b always-read aplicado** (Opção A) — [W] autorizou explicitamente nesta sessão; entrou no mesmo commit. Tier-0/[W]-only respeitado (autorização registrada). Bloco em `_PROPOSTA-always-read-PROCESSO-MEMORIA.md` mantido como registro (trilha L-22).

## Verificação (rodada nesta sessão)
- `node scripts/design/integrity-check.mjs` → **IT1–IT7 PASS** (estrutura sã). IT4 = L-01..L-25 contíguo.
- `node scripts/design/ds-guard.mjs --all` → relatório de dívida flaga 4 (compras `--cmp-*(18)` + 3 telas-na-raiz) e passa `oficina-page.css` → **reproduz o TESTE-06** ("separou meu trabalho bom do ruim").

## NÃO landeado (adjacente — disponível pra "Wave 2" se [W] quiser)
Estavam no bundle mas ficam **fora do escopo "process install"** (seriam "full sync"). Caminhos no bundle (`project/`):
- `METODO_TELA_ANTI-REGRESSAO.md` — "Lei formal" citada no NÚCLEO (linha 27). **Recomendado** como próximo (o doc referencia).
- `*.casos.md` — contrato de não-regressão por tela (NÚCLEO inv. 4): `Vendas.casos.md`, `Financeiro.casos.md`, `Compras.casos.md`, `OficinaProducao.casos.md`.
- `memory/decisions/_PROPOSTA-0244-estrategia-teste-estado-arte.md` — proposta (locators resilientes + Playwright + Storybook).
- `memory/sessions/2026-06-01-loop-graduacao-licao.md` — log de sessão.
- `ds-v5/` (components.css 77 KB · tokens.css · doc.css · interactive.js) — design system v5.
- ADR **0238/0239/0243** — citados pelo doc; **só [W]** cria ADR (append-only Tier 0). Não criados aqui.

> Para trazer qualquer um: reabrir o link do bundle e `tar -x` o caminho — ou só pedir "landa o METODO_TELA + casos.md também".

## Estado git
Commitado em branch própria **`feat/cowork-processo-memoria-cc`** (base `origin/main`) → **PR pra `main`** pra review/merge do [W] (publication-policy: [CC] abre PR mas não dá merge). Worktree isolado — `feat/staging-ct100` e seu working tree ficaram intactos.

## Referências
- [`PROCESSO_MEMORIA_CC.md`](PROCESSO_MEMORIA_CC.md) — o método landeado (§8 DS-GUARD · §15 Integridade)
- [`memory/LICOES_CC.md`](../../../memory/LICOES_CC.md) — L-01..L-25
- [ADR 0114](../../../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md) · [ADR 0104](../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md) · [`PROTOCOL.md`](PROTOCOL.md)
- Precedente de landing: `cowork-2026-05-26-comunicacao-visual/README-DESTINO.md`
