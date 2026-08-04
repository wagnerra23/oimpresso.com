---
date: "2026-08-04"
time: "17:30 BRT"
slug: "ciclo-maquinas-templates-verificacao"
tldr: "Ciclo do trabalho mapeado em 11 etapas; verificação independente das 11 máquinas que o cobram deu 3 mordem · 4 só relatam · 4 mistas — com 8 achados de gate que não pode reprovar. Nada corrigido: é levantamento."
decided_by: [W]
cycle: null
us: []
next_steps:
  - "Corrigir os 2 gates de design que convertem exit 1 em exit 0 (pt-conformance, design-coverage)"
  - "Quebrar o laço do shipped-log-gate: --check pula `parcial` e o gerador sempre carimba `parcial`"
  - "Corrigir gates-registry.json (chama gate-selftest de advisory; é required desde 2026-07-02)"
  - "Corrigir _HOOKS-INDEX.md: heurística exit-2 é por arquivo, deveria ser por ramo de evento"
  - "Investigar por que mcp:tasks:unassigned falhou em silêncio hoje e em 29/07"
  - "Decisão [W]: invocar jana:plan-drift · ligar --check no feature-lint · parent_plan como coluna"
hour: "17:30 BRT"
topic: "Ciclo completo, responsabilidade por máquina, templates dos 8 artefatos e verificação de quem realmente morde"
authors: [C]
prs: [5264, 5270, 5272]
related_adrs:
  - 0261-enforcement-faseado-gates-ci
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0273-anchor-spec-codigo-formato-canonico
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0329-doutrina-documentacao-de-processo-executavel
  - 0336-gates-design-promocao-por-mordida-provada-emenda-0314
  - 0345-topicos-vivos-aprendizado-por-critica-revisada
---

# Handoff — ciclo, máquinas e templates

## O que a sessão entregou (mergeado)

| PR | Conteúdo |
|---|---|
| [#5264](https://github.com/wagnerra23/oimpresso.com/pull/5264) | Forja: reconcilia SPEC (formato atual + drift PMG-008), consolida `INVENTARIO`→`CAPTERRA-INVENTARIO`, trio de 3 telas |
| [#5270](https://github.com/wagnerra23/oimpresso.com/pull/5270) | mata o stub `/ia/regras` — domínio ADS/MCP fora da fronteira da Jana |
| [#5272](https://github.com/wagnerra23/oimpresso.com/pull/5272) | 2 propostas (ciclo · templates) + ANEXO + RUNBOOK + lápide §5 + ledger |

## O ciclo tem 11 etapas, não 8

`pedido → requisito → design → contrato-da-feature → aprovação → execução → contrato-da-tela → gates → deploy → âncora → aprendizado`.

O desenho de 8 caiu por refutação adversarial: **faltavam design, deploy e aprendizado** (a palavra "deploy" aparecia 0× na 1ª proposta).

## Verificação das 11 máquinas — 3 mordem · 4 só relatam · 4 mistas

12 agentes independentes, cada um respondendo **presente? · invocada? · morde?** com comando + saída. Os 8 achados:

1. **`pt-conformance` e `design-coverage` não conseguem ficar vermelhos** — o `--check` está dentro de `if/then/else` que converte `exit 1` em `exit 0`; emite `::warning` e conclui verde. Bite-test com fixture ruim confirma.
2. **`shipped-log-gate` é hard-fail que nunca teve como reprovar** — o `--check` pula arquivo com `status: parcial` (`shipped-log-generate.mjs:206`) e o gerador **sempre** carimba `parcial` quando o cron roda (`L314`/`L129`). Laço fechado, conjunto vazio.
3. **`casos-gate` (required) verde com 122 de 206 telas sem `casos.md`** — cobertura 41%, e só 28% dos UC têm veredito de teste executado. É ratchet sobre dívida grandfatherada.
4. **`anchor entry/covers` (required) só passa por causa do baseline** — o mesmo comando sem `--baseline` sai `exit 1` e reprova a árvore inteira.
5. **`mcp:tasks:unassigned` falhou em silêncio** hoje e em 29/07; 25% de buracos na série da 1ª semana, medido no log de prod. O Kernel declara essa série como *"o valor deste cron"*.
6. **`gates-registry.json` chama `gate-selftest` de "advisory"** — é **required desde 2026-07-02**. Doc afirmando o próprio enforcement, errado (§5 2026-07-16).
7. **`_HOOKS-INDEX.md` marca 2 dos 6 hooks de prompt com `exit-2`** — bite-test diz **0 de 6** bloqueiam nesse evento. A heurística do gerador (`hooks-manifest-generate.mjs:86`) é regex sobre o arquivo inteiro; o mesmo `.mjs` está wirado em 2 eventos e o sinal do ramo `PreToolUse` vaza pro `UserPromptSubmit`.
8. **`gate-selftest` (required) não cobre o `two-strikes`** — 0 ocorrências. A única prova mecânica do loop de aprendizado vive em job advisory.

**Padrão comum:** o mecanismo existe, roda, e não pode reprovar — por flag desligada, `if/else` que engole o exit, baseline que perdoa, ou laço que garante conjunto vazio.

## As 3 quebras de INVOCAÇÃO (do mapeamento, ainda abertas)

- `jana:plan-drift` — existe, tem teste, **0 invocadores**. Confirmado por runtime: `schedule:list` no CT 100 → 0 em 76 comandos.
- `feature-lint` — 14 códigos de recusa, e o CI o roda **sem `--check`**, em job não-required.
- `ancora-codigo-sync --stamp/--sync` — **0 invocadores**; e 67,7% das âncoras são não-medíveis (270 de 437 em `sha_fora_da_ancestralidade`, causa: squash-merge).

## Erros meus registrados (LC-08 46 → 50)

- medi contra árvore **272 commits atrás** (o `gh pr merge --delete-branch` devolveu o worktree pro `main` local) → publiquei "37 SCOPE" (são 32) e "`Forja/SCOPE.md` ausente" (existe);
- varri `scripts/` e `.github/` e conclui *"ninguém cobra o SCOPE"* — estava em `bin/`;
- confundi `governance-audit.mjs` (Node, 0 invocadores) com `governance:audit` (artisan, agendado) — **[W] pegou**;
- **pushei 4 commits para a branch de um PR já mergeado** (#5270 fechou 13:02, segui empurrando) — reincidência literal de sub-forma já escrita no ledger; recuperado por cherry-pick no #5272;
- **inventei o slug de uma ADR** (`0261-fatos-derivados-nao-viram-gate-de-merge`) e usei como fundamento — o `deadlink-gate` (required) pegou; errata no corpo.

Lápide §5 nova: **placeholder `{{X}}` sem aspas em frontmatter YAML** — não é string, é *flow mapping*; `permission_prefix: {{modulo}}.*` **lança**, e o `DriftAlertService` faz `return []` no catch → detecção de drift desliga em silêncio.

## Estado MCP no momento do fechamento

- `cycles-active` → **nenhum cycle ATIVO em COPI**
- `my-work` → **30 tasks** (13 review · 11 blocked · 6 todo)
- Último handoff anterior: `2026-08-03-1708-recebimento-parcial-parcela-sdd.md`
- `main` no merge `69039c8cd`: **11 success · 1 failure · 1 skipped** — a falha é `Deploy to Hostinger`, `ssh: Connection timed out` (exit 255), o flaky de SSH já documentado; o deploy das 13:02 passou. **Não é regressão deste PR** (docs-only).

## Como retomar

Ler as duas propostas em `memory/decisions/proposals/2026-08-04-*` (a do ciclo tem a errata das 14 afirmações refutadas; a dos templates tem as 8 regras transversais e a tabela artefato→path→template→schema→gerador→gate) + o `RUNBOOK-nascimento-de-artefato.md`.

**Nada dos 8 achados foi corrigido.** São levantamento com comando de reprodução em cada um.
