---
date: "2026-09-15"
time: "1220 BRT"
slug: "handoff-19-ciclo-ponto"
tldr: "Handoff 19 importado e as 2 decisões com alvo no produto aplicadas (redação CPF/PIS + trava de vínculo na remoção de escala). PASSO 0 ligado na rota ZIP. Fechamento NÃO construído — 3 restrições registradas na US-PONTO-015, blocked_by [W]. O vermelho do watchdog G6 é do repo, não dos PRs, e outra sessão assumiu."
decided_by: [W]
cycle: null
prs: [7272, 7275, 7277, 7278, 7279]
us: ["US-PONTO-015"]
next_steps:
  - "[W] responder o blocked_by da US-PONTO-015 (o que 'fechar' faz com as marcações, granularidade, permissão, intercorrência pós-fechamento)"
  - "Medir paridade visual das ~20 Pages do Ponto contra o protótipo (design-diff --probe, exige app no ar)"
  - "Shell do espelho referencia 3 arquivos em _ds/ aposentado — conserto nasce no Cowork vivo"
related_adrs: ["0390-espelho-cowork-build-only", "0374-emenda-0315-espelho-cowork-e-rota-prevista", "0325-projetos-cowork-por-id", "0344-two-strikes-cobre-processo"]
---

# Handoff 2026-09-15 12:20 — handoff 19 do Cowork: ciclo Ponto + PASSO 0 na rota ZIP

## TL;DR

[W] entregou o zip e disse "pode importar e aplicar". Importado pela rota canônica; as decisões do ciclo que tinham alvo no produto foram aplicadas; o Fechamento **não** foi construído de propósito. 5 PRs mergeados. Quem retomar: **leia primeiro o §Bloqueios** — há uma decisão do [W] pendente e um vermelho de CI que é do repo.

## Cronologia desta sessão

1. Base stale detectada (47 atrás) — e o canon do protocolo tinha **mudado de lugar** nesses 47. Trabalhei de `origin/main` fresco.
2. Painel + selftests antes de tocar em nada. PASSO 0 → `indeterminado`, resolvido pela via que ele mesmo prescreve, com corroboração medida.
3. Import (#7272) → PASSO 0 na rota ZIP (#7275) → 2 telas (#7277, #7278) → US + BRIEFING (#7279).
4. No fim: registro no ledger (LC-08 nº 155) + 2 lápides §5 + este handoff.

## Estado atual dos artefatos

| Artefato | Estado |
|---|---|
| espelho `prototipo-ui/cowork/Wagner` | 7 build-only do handoff 19 promovidos · bundle `b19625fb` · 17 docs de `cowork-inbox` **recusados** (são pedido, e o espelho estava à frente do zip em vários) |
| `receber-handoff.mjs` | PASSO 0 embutido · `--conta w` exigido quando `indeterminado` · `nao-vinculada` sem escape |
| `Ponto/Colaboradores/Index` | CPF/PIS redigidos (3 últimos) · `UC-COLIDX-03` com teste que morde por mutação |
| `Ponto/Escalas/Index` + `Escala` | "Remover" na UI, **indisponível com vínculo** · `podeSerRemovida()` no entity · `UC-ESCIDX-03/04` |
| `Ponto/SPEC.md` | US-PONTO-015 (`todo`, `blocked_by`) com as 3 restrições |
| `Ponto/BRIEFING.md` | redestilado (parcial declarada) · `distilled_at` 2026-09-15 |

## Decisões tomadas

- **[W] 2026-09-14:** *"é liberado ser igual ao protótipo"* — resolve a §Pendências do charter de Colaboradores (mascarar) **e** o Non-Goal de Escalas que perguntava se a UI devia expor a remoção. Supersede, **para a coluna de CPF**, a posição de 2026-08-21; o fato daquela data fica preservado nos dois arquivos.
- **[W] 2026-09-15:** aprovar os 5 PRs **com o vermelho declarado** do watchdog G6, e designar outra sessão para aposentá-lo.
- **Minha (técnica):** exibição redigida ≠ busca inteira; predicado de remoção extraído pro entity para ser testável sem sessão; Fechamento **não** construído a partir de 3 frases de protótipo.

## Bloqueios / pendências

1. **US-PONTO-015 `blocked_by` [W]** — sem resposta a US não sai de `todo`: o que "fechar" faz com as marcações · por colaborador ou por empregador · qual permissão · intercorrência que chega depois.
2. **`crons de governança vivos? (watchdog G6)` reprova TODO PR do repo** — não é dos PRs. `governance/module-grades-baseline.json` com data interna congelada **por doutrina do próprio arquivo** (60d declarados × **15d** de toque real), **zero escritores**, e o silêncio do watchdog é chaveado por `workflow`, não por artefato. Nenhuma edição honesta do artefato deixa o gate verde. **Outra sessão assumiu** (`claude/re-cure-baseline-ct-100-48d292`) — ⚠️ a lápide §5 de hoje registra o risco do nome dela: o artefato documenta em 4 rebaselines que o **CT 100 mede ~+1 sistemático** e que travar piso acima do que o CI alcança é o modo de falha conhecido. A §FONTE dele exige número do **CI**.
3. **Paridade visual do Ponto nunca medida** — apliquei as 7 decisões, não a fidelidade das ~20 telas.

## Próximos passos (ordem)

1. [W] responde o item 1 acima.
2. Sessão irmã fecha o watchdog G6 (ler a lápide §5 de 2026-09-15 antes — ela existe pra evitar re-medição e o buraco do CT 100).
3. Medir paridade visual do Ponto (`design-diff --probe`, app no ar).
4. Fechamento pelo MWART, depois de (1).

## Estado MCP no momento do fechamento

⚠️ **Declaração honesta de fonte:** as tools MCP (`cycles-active`, `my-work`, `sessions-recent`, `decisions-search`, `whats-active`) **não estavam conectadas** nesta sessão — o `.mcp.json` tinha `laravel-boost` em `CONNECTION_CLOSED` e nenhuma tool `mcp__Oimpresso*` foi exposta. O snapshot abaixo é o **Daily Brief servido pelo hook `brief-fetch-curl` do SessionStart**, que é o mesmo dado por outra porta, mais o substituto que usei para `whats-active`. Registro a porta em vez de afirmar que rodei a tool.

**Brief #642 (gerado ~1h antes do fechamento):**
- Cycle: — · HITL pending Wagner: **5**
- Em voo (11 itens), com Ponto presente: *"Lane required de Ponto vira arvore-menos-quarentena, 10d"*
- Flags: 🟠 **682 US não atribuídas** (523 sem dono; mais antiga US-ACCO-001, 137d) · migration aging, PRs aguardando review e visual regression sem nada crítico
- Decisões 24h: ADRs — · commits 8 · incidentes 0

**Substituto de `whats-active` (`list_sessions` do host, 11:59Z):** sessão irmã **`Re-cure baseline no CT 100`** (`claude/re-cure-baseline-ct-100-48d292`, worktree `agent-2-credits-7f5edb`) ativa e dona do item 2 dos bloqueios. Nenhuma outra sessão nos meus paths. Foi o que me fez **não** tocar no watchdog nem no baseline (LC-19).

**Regime de evolução (hook `loop-fechar-check`, cache 2026-09-14):** E1 66/0 · E2 186/222 · E3 63/222 · E4 20/222 · E5 85.8/100 · E6 11/0 · **E7 12 classes LC sem gate** · E8 1/0 · E9 não medido · E10 1085/0. Este handoff move o E7 em uma ocorrência (LC-08 → 155), não em classes.

## Referências

- PRs: [#7272](https://github.com/wagnerra23/oimpresso.com/pull/7272) · [#7275](https://github.com/wagnerra23/oimpresso.com/pull/7275) · [#7277](https://github.com/wagnerra23/oimpresso.com/pull/7277) · [#7278](https://github.com/wagnerra23/oimpresso.com/pull/7278) · [#7279](https://github.com/wagnerra23/oimpresso.com/pull/7279)
- Session log: `memory/sessions/2026-09-15-handoff-19-ciclo-ponto-e-passo-0-zip.md`
- §5: duas lápides de 2026-09-15 (fonte `memory/licoes-rejeitadas.md`) · ledger LC-08 nº 155
