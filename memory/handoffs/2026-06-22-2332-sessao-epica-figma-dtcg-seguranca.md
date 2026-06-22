---
date: "2026-06-22"
time: "23:32 BRT"
slug: sessao-epica-figma-dtcg-seguranca
tldr: "Sessão épica (~13 PRs). '[W] por que não achou a fonte de design?' → Figma MCP always-on venceu o canon-em-docs; red-team matou minha proposta-teatro → rede block-figma L0-L5 (#3213, ADR 0299). Figma não é o protocolo (o MCP é; fonte portável = token DTCG); DTCG ativado (#3230). Pivot receita → auditoria: 3/10 client-ready, INSEGURO (APP_KEY de prod no git, IDOR cross-tenant em dinheiro, webhook Asaas sem auth). Onda 0: #3235/#3236/#3244. Segredos: rotação pendente [W]."
decided_by: [W]
cycle: CYCLE-08
prs: [3213, 3214, 3215, 3216, 3217, 3218, 3219, 3220, 3230, 3235, 3236]
related_adrs: ["0299-figma-nao-e-fonte-de-design", "0300-errata-0239-nome-real-fonte-design-system", "0293-governanca-decisao-design-responsavel-registro-veredito", "0249-ds-v6-naming-amends-0235"]
next_steps:
  - "[W] P0 SÓ-SEU: rotacionar APP_KEY de prod + ~9 segredos de infra + DB_PASSWORD/PAGSEGURO_KEY (vivos no git history, assumir comprometidos) + APP_DEBUG=false — relógio mais crítico"
  - "[W] decisão Produto: '2 telas ou unifica?' (/products card-grid vs /produto/unificado denso) — destrava a reconciliação por setor"
  - "US-FIN-061 (P0): workflow refazendo o IDOR do AccountController estava rodando no fim — checar veredito do verificador adversarial e abrir PR se aprovado"
  - "Commit+push memory/requisitos/*/SPEC.md pra sincronizar as 8 tasks novas pro MCP (escritas server-side, mas o SPEC.md local ficou na working-copy da sessão paralela feat/vendas-link-caixa-do-dia)"
  - "ADR registrando a mudança de fonte-de-token (DTCG .tokens.json, não CSS) + varrer os ~18 source-greppers latentes que grepam cockpit.css antes que algum quebre"
---

## Estado MCP no momento

CYCLE-08 "Receita — Onda A" · 82% decorrido · **5 dias restantes** · goals revenue **todos 🔲 não-atingidos** (pricing público, 5 migrações-demo, MRR 2000, ComVis V1, Agrosys). `my-work`: 30 tasks ([W]), 8 novas desta sessão **ainda não no DB** (SPEC.md pendente de push). Sessão **paralela** ativa: repo principal `D:/oimpresso.com` na branch `feat/vendas-link-caixa-do-dia` (não mexi pra não atropelar).

## O que aconteceu

Arco em 4 atos disparados por perguntas curtas do [W], cada uma virando o leme:
1. **"por que não achou? não pode ter falhas"** → diagnóstico honesto (conflito de AUTORIDADE: ordem always-on do Figma MCP venceu canon-em-docs) → **defesa adversarial**: 9 céticos + refutação mataram minha proposta inicial (3 nudges = teatro probabilístico); sobrou rede mecânica **L0-L5 block-figma** (hook PreToolUse fail-closed denylist-por-servidor + catraca + baseline armado), ADR 0299.
2. **"é a melhor forma? tem outro que substituiu?"** → 2 deep-researches: **Figma não é o protocolo** (o MCP é, genérico/Anthropic; a fonte portável é o **token DTCG W3C**; Figma é editor). Transporte Cowork→code auditado em **71%**.
3. **"faça as ondas em paralelo"** → 3 ondas aditivas (medidor de %, registro de componentes, gates de design no CI) + 2 ratificações + ratify 0293 + **ativação do DTCG** (#3230, que quebrou 2 source-greppers — provou que ativar é MIGRAÇÃO de fonte, não swap). Re-grade **+7.9**.
4. **"eu quero clientes" → "o sistema é fraco e inseguro, nota 6"** → o [W] freou certo. Auditoria adversarial de segurança: **3/10 client-ready, INSEGURO de verdade** (APP_KEY de prod no git, IDOR de escrita cross-tenant em dinheiro, webhook Asaas sem auth, ~9 segredos vivos). Onda 0 começou; verificação adversarial pegou 2 fixes incompletos.

## Artefatos gerados

~13 PRs na main (lista em `prs`). ADRs 0299/0300 + 0293 ratificado. **8 tasks** (US-FIN-061 P0, US-INFRA-047, US-SELL-054, US-COM-011, US-CRM-079, US-CRM-080, US-REPA-002, US-COPI-129). **/schedule** `promover-gates-design-2026-07-06`. Workflows (transcripts): adversarial-design-sot-defense, figma-protocol-research, maturidade ×2, ondas ×2, ratificação, auditoria-seguranca, us-fin-061.

## Persistência

git (PRs na main) ✅ · MCP (tasks server-side, SPEC.md a sincronizar) ⏳ · este handoff via worktree `handoff-0622`.

## Lições catalogadas

- **Verificação adversarial é o ativo** — matou minha própria proposta (teatro) E pegou 2 fixes de segurança incompletos (FIX 2 reprovado deixava money-write aberto + teste-teatro; FIX 4 tinha bypass do InstallController). Sem ela, falsa segurança teria mergeado.
- **Ativar DTCG = migração de fonte-da-verdade, não swap** — "provado byte-idêntico" era do CSS computado; os source-greppers (CockpitAccentCanonTest, palette-generate) grepam o CSS-fonte e quebram. ~18 outros latentes.
- **Sessões paralelas** (de novo): o repo principal trocou de branch sozinho (sessão paralela) — `tasks-create` gravou SPEC.md numa working-copy que não é minha. Não commitar lá.
- **Figma ≠ MCP ≠ tokens** — "ter Figma MCP instalado" não torna Figma o protocolo; prova que o MCP é o trilho.
- **Renumerar ADR por colisão de sessão paralela** (0298→0299) + regenerar `_INDEX-GENERATED` a cada ADR novo (peguei 2×).

## Pointers detalhados (on-demand)

- Defesa block-figma: ADR 0299 + `.claude/hooks/block-figma-without-optin.mjs` + `memory/requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md §0`
- Maturidade/DTCG: workflows transcripts + `resources/css/tokens/*.tokens.json` + ADR 0300
- Segurança: workflow `w0fm0ec3w` (auditoria) + `w5w9t6j9b` (verificação Onda 0) + `wyrkokvnj` (US-FIN-061 redo)
- Método por setor (reconciliação proto↔charter↔produção): workflow `w88vb3hab`
