# HANDOFF — Oimpresso Designer (prancheta visual interativa via MCP)

> **Pra quem:** Claude Code do Wagner (`[CL]`), que já conhece a estrutura do repo `oimpresso.com`.
> **De:** sessão pareada com **Luiz (otavi)** em **2026-06-05** (Claude Code desktop, fora do repo).
> **O que é:** handoff autocontido pra você **continuar a construção** de uma ferramenta de design estilo Figma que seja **interativa com o Claude Code** e **distribuível pra equipe**. Lê isto inteiro antes de tocar em qualquer coisa.
> **Status:** protótipo funcional standalone existe (ver §3). Falta empacotar como MCP + re-baseiar no canon real. **Nada foi commitado no repo do Wagner.**

---

## 1. Objetivo (o que o Luiz quer)

Uma ferramenta de design **estilo Figma** pra **criar telas do sistema do zero**, com 3 exigências, nas palavras dele:

1. **Interativa com o Claude Code** — a IA fica *no loop* ajudando a montar a tela (mão dupla: o humano arrasta/edita no canvas, a IA gera/ajusta/explica), não um passo separado de "cole esse prompt".
2. **Distribuível pro Wagner plugar na IA dele** (que já conhece a estrutura do sistema) e que **a IA possa melhorar a própria ferramenta**.
3. **Depois, disponível pra equipe inteira.**

Restrição-mãe do Luiz: **ajudar a fazer design sem ferir a estrutura e o core do sistema.** "Padronização do sistema inteiro" → um *design especialista* (a expertise do canon embutida pra quem não é especialista não errar).

---

## 2. O canon real (verificado no repo — ATENÇÃO: divergência importante)

O Luiz começou me passando um doc `PADRAO-OIMPRESSO Teste.md` que descreve **azul 220 + roxo só pra Jana**. **Isso é v3, ANTIGO.** Puxei o `origin/main` (`b3d4ae153`) e o canon real é outro:

### 2.1 `prototipo-ui/tokens.css` — v4, "SINGLE SOURCE OF TRUTH" (2026-05-29)
- 🟣 **Accent = ROXO** `oklch(0.55 0.15 295)` (full-roxo: accent **e** primário). **Supersede ADR 0190.** Não é mais "azul 220 + Jana roxo" — o roxo virou o accent oficial.
- Sidebar **dark petróleo hue 240** (continua dark, NÃO roxo).
- Nomes reais: `--bg/--bg-2/--surface/--border(-2)/--text(-dim/-mute)`, `--accent(-2/-soft/-fg)`, `--sb-*`, `--origin-{OS,CRM,FIN,PNT,MFG}-{bg,fg}`, status **`--ok-bg/--ok-fg` · `--warn-bg/--warn-fg` · `--danger-bg/--danger-fg` · `--info-bg/--info-fg`**, espaço **`--s-1..--s-10`**, raio `--radius-sm(4)/--radius(6)/--radius-md(8)/--radius-lg(12)`, `--shadow-0..4`, camada **semântica** (`--color-action-primary`, `--surface-*`, `--border-focus`, `--focus-ring`), `[data-density=compact|default|comfy]`, `[data-theme=dark]`. `--row-h: 30px` (não 40).
- Componentes consomem a **camada semântica**, não os primitivos.

### 2.2 `prototipo-ui/design-system.css` — componentes v3 (2026-05-28)
- Classes são **`.btn.primary` / `.btn.ghost` / `.btn.danger` / `.btn.icon/.sm/.lg`** (compostas — **NÃO** `.btn-primary`).
- `.badge` tem **fundo** (`--bg-2`), pill. `.field/.input/.select/.textarea/.search`.

### 2.3 `prototipo-ui/ds-v6/` — DS v6, **aprovado [W] em 2026-06-03** (ainda protótipo estático)
- Accent roxo 295 consistente, mas **nomenclatura nova**: `--sunken/--raised/--hairline`, `--text-2..-4`, `--accent-hi/-line`, **`--pos/--neg/--warn` (+ `-soft`)**, `--stage-{slate,indigo,rose,emerald,green}` (dots de FSM/kanban), `--r-2..-4/--r-pill`, `--sh-1/-3`. Dark hue 282. Tema em `localStorage` `dsv6.theme`.
- 11 componentes canônicos + `receita.html` (método "como nasce uma tela") + `gabarito-vendas.html`. README: porta pra produção é **Tier 0 / MWART**, gate visual por tela quando [W] liberar.

> **DECISÃO PENDENTE PRO WAGNER:** o designer deve embutir **v4 (vivo)**, **DS v6 (aprovado, futuro)**, ou — recomendado — **ler o `tokens.css`/`design-system.css` direto do repo** (1:1, à prova de ficar stale; serve v4 hoje, v6 quando portar)?

---

## 3. Estado atual — protótipo funcional (standalone)

Arquivo: **`canvas-prototype.html`** (nesta pasta; original em `C:\Users\otavi\Desktop\oimpresso-designer.html`). HTML único, autocontido, **verificado em runtime** (preview MCP: console limpo, features testadas).

**⚠️ Foi construído no canon v3 STALE (azul 220 + "roxo só Jana").** A lib de tokens/componentes embutida é uma RÉPLICA do PADRÃO antigo — **precisa re-baseiar no §2** antes de valer como canon. O **auditor** dele hoje acusa roxo 295 como "violação da Jana" — isso **tem que inverter** (roxo 295 agora é o accent oficial).

O que o protótipo já faz (a lógica é reaproveitável):
- **Canvas** com zoom; **árvore de nós** (screen → containers → componentes); seleção; **painel Camadas** com drag-reorder (before/after/into).
- **Inspector** rico por tipo; **undo/redo**; atalhos (Delete/Ctrl+D/Esc); toggles tema/marca/densidade/raio/shell.
- **Modo ▶ Visualizar** com **modelo Trigger→Ação** (clique/hover/mudança → toast, abrir drawer, abrir modal, navegar, bulk, selectRow, stub) que **executam** — fecha o buraco "botão morto" (PADRÃO Parte D).
- **6 estados** (cheia/loading/vazia/sem-resultado/erro).
- **~20 componentes** + drawer/modal/toast/bulkbar como overlays.
- **Export** em 4 formatos: pedido determinístico (estilo Parte F) · **YAML** (anatomia+tokens+ações, estilo plugin Figma "Specs") · JSON · HTML.
- **Auditoria** (botão ⌕): cola HTML/CSS legado → aponta cor hardcoded, soberania, storage sem prefixo, fonte/raio/gradiente/emoji, com token sugerido + severidade (P0/P1/P2) + checklist Portão C3.

Bugs já corrigidos na sessão: helper `el()` com arg trocado; seletor `.bulkbar`; `</script>` literal dentro de string (escapar `<\/script>`).

---

## 4. O ecossistema do repo onde isto encaixa (já existe — NÃO reinventar)

| Peça | Arquivo | Pra quê |
|---|---|---|
| Distribuição de tooling pra equipe | **`.mcp.json`** (commitado) | time recebe MCP servers via `git pull`. **É aqui que a ferramenta entra.** |
| Loop design↔código formalizado | **`prototipo-ui/PROTOCOL.md`** (ADR 0114) | 7 fases F0..F4; modelo **autônomo 0-humano** desde 2026-05-31 (ADR 0241, gates viraram CI: PR UI Judge Sonnet 4.5 + visual-regression). F1 (design) hoje nasce no **Cowork web app**. |
| Migração Blade→Inertia | MWART (ADR 0104) | único caminho; 5 fases. |
| Guard de DS | **`prototipo-ui/ds-guard.mjs`** | detecta "paleta inventada" (≥4 tokens de cor mesmo prefixo bespoke), "tela na raiz", nome ilegível. Roda no fim de toda build. |
| Invariantes anti-regressão | **`prototipo-ui/PROCESSO_MEMORIA_CC.md`** | NÚCLEO 13 invariantes + §5 regressões proibidas + §8 (ds-guard) + §15 (integrity-check). |
| Constituição UI | ADR **UI-0013** (`memory/requisitos/_DesignSystem/adr/ui/`) | 4 camadas. + `padroes-tela/` + `PRE-MERGE-UI.md`. |

**Governança (CLAUDE.md):** **Tier 0 = humano (Wagner)** para: ADR novo, multi-tenant, segredos, **lógica de lint/TOOLING**, decisão de produto. ⇒ **a ferramenta É tooling → a adoção dela exige ADR + aprovação do Wagner.** Por isso este handoff é standalone e **nada foi commitado no repo.**

---

## 5. Arquitetura recomendada

A ferramenta = a **prancheta visual interativa que falta no F1** do loop, empacotada como **servidor MCP** (mesma convenção do `.mcp.json` → qualquer Claude Code da equipe opera) + canvas web, compartilhando um **`screen.json`** em disco.

```
Humano + IA  ⇄  [MCP: oimpresso-design]  ⇄  screen.json  ⇄  canvas web (live-reload)
 conversam       ferramentas abaixo         (fonte única)     o humano vê e arrasta
```

**Ferramentas MCP sugeridas:**
| Tool | Faz |
|---|---|
| `read_canvas()` | retorna o `screen.json` atual (a IA "vê" o que tem) |
| `set_canvas(spec)` / `add_component(parentId,type,props)` / `update_node(id,props)` / `remove_node(id)` / `move_node(id,target,mode)` | mutam a tela → canvas atualiza ao vivo |
| `list_components()` | registry canônico (lido de `design-system.css`) |
| `get_tokens()` | **lê `prototipo-ui/tokens.css` do repo** → nunca fica stale |
| `audit(spec\|code)` | lint alinhado ao `ds-guard.mjs` (paleta inventada, soberania, etc.) |
| `screenshot()` | render do canvas pra IA conferir visualmente |
| `export(format)` | gera artefatos do `PROTOCOL.md` F1 (`prototipos/<tela>/`) — page.tsx/spec |

**Por que MCP e não HTML solto / Electron com chat:** HTML solto "não se pluga na IA do Wagner"; Electron+chat é pesado, precisa de chave e não é padrão. MCP é o mecanismo oficial pra dar "mãos" a qualquer IA e o repo **já distribui MCP via `.mcp.json`**. Stack do repo: Node 18.16 + npm 9.5 disponíveis.

---

## 6. Plano faseado

| Fase | Entrega | Toca repo do Wagner? |
|---|---|---|
| **1** | Pacote standalone: servidor MCP + canvas compartilhando `screen.json` (em `D:\Mobile\oimpresso-designer\`). Reaproveita a lógica do `canvas-prototype.html`. | ❌ não |
| **2** | **Canon vivo + re-baseline (§2):** `get_tokens`/`list_components` lendo `tokens.css`/`design-system.css` reais (roxo 295, `.btn.primary`, `--s-*`, status `-bg/-fg`); auditor alinhado ao `ds-guard.mjs` (e **roxo 295 deixa de ser violação**). | só leitura |
| **3** | `export` gerando os artefatos do `PROTOCOL.md` F1 (`prototipos/<tela>/page.tsx` + spec) + alinhar com `padroes-tela/`. | gera, humano commita |
| **4** | **ADR de adoção** + entrada no `.mcp.json` + onboarding equipe. | decisão do Wagner (Tier 0) |

---

## 7. Decisões pendentes pro Wagner (subjetivo → escala pra ele)

1. **Canon alvo:** v4 vivo / DS v6 aprovado / **ler do repo (recomendado)**.
2. **A ferramenta substitui ou complementa o F1 [CC] Cowork?** (hoje o design nasce no Cowork web app). Sugestão: complementa — é a prancheta *local* + co-design com a IA, que **emite os mesmos artefatos** que o loop já consome.
3. **Onde o pacote mora** quando adotado (ex.: `prototipo-ui/designer/` ou `tools/design-mcp/`) — exige ADR (append-only, Tier 0).
4. **Saída:** pedido+spec pro Claude Code portar (recomendado, casa com MWART/F3) vs gerar `.tsx` direto.

---

## 8. Próximos passos concretos (Fase 1 — pra você, `[CL]`)

1. Scaffold `D:\Mobile\oimpresso-designer\`: `package.json`, `mcp-server.mjs` (MCP via stdio — pode usar `@modelcontextprotocol/sdk` ou JSON-RPC à mão pra zero-dep), `screen.json` (spec exemplo), `canvas.html` (a partir do `canvas-prototype.html`, mas lendo/salvando `screen.json` via endpoint local em vez de `localStorage`).
2. Implementar `read_canvas`/`set_canvas`/`add_component`/`update_node` operando sobre `screen.json`; canvas faz **live-reload** (polling/SSE) pra co-criação aparecer ao vivo.
3. Validar: subir o server, `tools/list` responde, uma chamada `add_component` reflete no canvas. (Rode `ds-guard.mjs` no CSS gerado — §8 do PROCESSO_MEMORIA_CC.)
4. Só **depois** a Fase 2 (re-baseline no canon real — §2). Não re-baseiar antes da decisão #1 do Wagner.

**Regras duras ao continuar:** PT-BR na UI · sem emoji (SVG) · cor só por token · não quebrar os 13 invariantes do `PROCESSO_MEMORIA_CC.md` · não commitar no repo do Wagner sem ADR · `git` é SSOT (ADR 0239) · append-only em ADR/SYNC_LOG.

---

## 9. Arquivos de referência (no repo `D:\oimpresso.com`)

- `CLAUDE.md` · `.mcp.json` · `prototipo-ui/PROTOCOL.md` · `prototipo-ui/PROCESSO_MEMORIA_CC.md` · `prototipo-ui/ds-guard.mjs`
- `prototipo-ui/tokens.css` (v4) · `prototipo-ui/design-system.css` (v3) · `prototipo-ui/ds-v6/{README,showcase,receita,gabarito-vendas}.{md,html}`
- ADRs: 0114 (loop) · 0241 (autônomo) · 0104 (MWART) · 0109 (Claude Design plugin) · 0107 (gate visual) · 0190 (superseded pelo full-roxo v4) · UI-0013 (Constituição UI v2) · 0239 (git=SSOT)
- Protótipo: `D:\Mobile\oimpresso-designer\canvas-prototype.html` (cópia) / `C:\Users\otavi\Desktop\oimpresso-designer.html` (original)

---

## 10. Histórico da sessão (o que Luiz + Claude fizeram)

1. Construí o designer estilo Figma do zero (canvas/camadas/inspector/undo) — v1.
2. v2: modo Visualizar + ações (Trigger→Ação) executáveis + 6 estados + mais componentes + export YAML — depois de pesquisar Subframe/Plasmic/Builder/v0/Framer/Figma/shadcn (conclusão: shadcn "you own the code" == soberania → **não** colar framework externo).
3. v3: módulo de Auditoria (lint contra o PADRÃO).
4. Luiz esclareceu a missão: padronizar o sistema inteiro **sem ferir o core**, ferramenta = bancada de padronização.
5. Pull do repo do Wagner → descoberta de que o canon mudou (roxo 295 / DS v6) e de que **já existe** o loop PROTOCOL.md + ds-guard + distribuição via `.mcp.json`.
6. Luiz pediu: ferramenta **interativa com Claude Code, distribuível pro Wagner→equipe**. → este handoff.

**Estado vivo:** protótipo pronto (canon stale), arquitetura MCP definida, Fase 1 não-iniciada, aguardando decisões §7 do Wagner. Nada commitado no repo.
