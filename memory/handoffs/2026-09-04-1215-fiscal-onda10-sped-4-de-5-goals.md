---
id: handoffs-2026-09-04-1215-fiscal-onda10-sped-4-de-5-goals
date: "2026-09-04"
time: "12:15"
slug: fiscal-onda10-sped-4-de-5-goals
tldr: "Onda 10 do Fiscal em 2 PRs (#6723 Goals 1/4/5 · #6728 Goal 2). 4 dos 5 Goals do charter do Cowork fechados; o Goal 3 (prévia) segue em decisão [W], agora com a informação que faltava MEDIDA. O cartão de validação contradiz a copy do protótipo de propósito — ela dizia 'golden não existe' e o golden nasceu no dia seguinte ao charter. DUAS AÇÕES PENDENTES DE [W]: aprovar a regravação da baseline visual (Fiscal/Sped 1,2261%, mudança intencional confirmada no diff) e decidir o Goal 3."
decided_by: [W]
prs: [6723, 6728]
us: [US-FISCAL-010, US-FISCAL-016, US-FISCAL-017]
related_adrs:
  - 0062-separacao-runtime-hostinger-ct100
  - 0093-multi-tenant-isolation-tier-0
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0286-contrato-de-tela
---

# Handoff — Fiscal Onda 10 (SPED): 4 dos 5 Goals do charter do Cowork

Narrativa completa em
[`memory/sessions/2026-09-04-fiscal-onda10-sped-goals-cowork.md`](../sessions/2026-09-04-fiscal-onda10-sped-goals-cowork.md).

## Estado

| Goal | Onda 9 | Onda 10 | Onde |
|---|---|---|---|
| 1. Barra com os 4 pré-requisitos | 🟡 no drawer | ✅ barra na página + data de encerramento | [#6723](https://github.com/wagnerra23/oimpresso.com/pull/6723) |
| 2. Bypass de superadmin explícito | ❌ | ✅ ação nomeada com efeito no servidor | [#6728](https://github.com/wagnerra23/oimpresso.com/pull/6728) |
| 3. Prévia do TXT | ❌ | ❌ **decisão [W]** | — |
| 4. Cartão de validação externa | ❌ | ✅ medido do disco | #6723 |
| 5. Blocos com os registros | ❌ | ✅ medido do golden | #6723 |

Âncoras `data-contract` do protótipo presentes na tela: **1 → 4**.

**#6728 está empilhado sobre #6723** — mergear o #6723 primeiro.

## Estado MCP no momento do fechamento

⚠️ **As tools MCP do oimpresso não estavam disponíveis nesta sessão** — o brief chegou pelo hook
`brief-fetch-curl` do SessionStart (Brief #605), e o **`whats-active` (LC-19) não pôde ser
chamado**. Registro como limitação da sessão, não como consulta feita.

Substituto: `list_sessions` do host — **6 sessões Fiscal ativas**. Uma toca SPED (*"Investigar
emitente vazio no SPED (CNPJ/IE/UF fixa SP)"*), mas no `SpedIcmsIpiGeneratorService` e no registro
`0000`, que a lei desta onda proibia tocar. **Interseção de arquivos: vazia.**

## ⚠️ Duas coisas que precisam de [W]

### 1. Regravar a baseline visual da tela `Fiscal/Sped`

O `visual-regression` está **vermelho no #6723**, e **é meu** — não herdado: `Fiscal/Sped →
1.2261%`, dentro do raio do PR, na zona cinza (0,1%–2%). (O `Jana → 1.6746%` que aparece no mesmo
log é herdado do main e não bloqueia.)

**Não deduzi que era intencional — decodifiquei.** Baixei o artifact `pixel-diff-views`, conferi
que a "baseline" do HTML é **byte-idêntica** ao `.snap` do repo, e **olhei o diff**: o vermelho é
(a) a barra de validação nova no topo, com as 5 linhas e o texto *"Competência 05/2026 encerrou em
31/05/2026"* — o Goal 1 visível; (b) a tabela deslocando para baixo por causa dela; (c) o card
"Blocos do arquivo" no rodapé. **Sidebar, header e subnav intactos.** É exatamente a mudança
pretendida, e nada além.

O caminho canônico é `workflow_dispatch` do `visual-regression` no **modo update**, escopado
(`screens: ["Fiscal/Sped"]` — o update completo não cabe no timeout), e ele exige **aprovação [W]
pelo gate F1.5**. Não disparei.

### 2. Decidir o Goal 3 (prévia do TXT)

[W] inclinou para *"blocos + linhas do golden como referência de layout"* mas disse que faltava
informação. A informação foi medida e está no corpo do #6723 — resumo: o golden é **1.794 bytes ·
47 linhas · sha `e4eeccd4…`**, saída **real** do gerador, e a **primeira linha dele se identifica
como fictícia** (`|0000|…|CI TENANT 98 (FICTICIO)|…`). Uma prévia com essas linhas é
auto-evidentemente não-sua — o risco de confusão é bem menor do que eu supunha ao formular a
pergunta. Em troca, ela expõe na tela os dois defeitos que o golden revelou (CNPJ/IE vazios, UF
fixa `SP`).

## Testes (CT 100, MySQL staging)

| Comando | Resultado |
|---|---|
| `--filter=SpedOnda10` | **11 passed (50 assertions)** — o caso HTTP das props **executou** (1.87s) |
| `--filter=SpedBypassSuperadmin` | **5 passed, 1 skipped (13 assertions)** — o **403 rodou** |
| `--filter='Sped\|SimplesOnly'` | **46 → 57 → 62 passed** (Onda 9 → PR1 → PR2). Delta bate com os casos adicionados |

**As 2 falhas da suíte são pré-existentes, e eu medi em vez de deduzir:** restaurei o
`SpedController.php` do `origin/main` no container e rodei `SimplesOnlyGateTest` — `UC-FSPED-09 ·
superadmin bypassa` falhou **igual**, com `PermissionDoesNotExist: superadmin`.
⚠️ **O handoff da Onda 9 atribuía essa falha a `users_username_unique` — é outra causa**, e o
registro fica corrigido aqui.

## Higiene do CT 100 (para quem for rodar teste lá)

O checkout do `oimpresso-staging` está em `c1abe9548`, com arquivos copiados à mão por sessões
anteriores. **O `Modules/Fiscal/Routes/web.php` de lá é mais velho que o `origin/main`** —
sobrescrevê-lo quebra rotas para as outras sessões. Apliquei só a inserção da rota nova, **com
backup antes**, e **restaurei ao fim** conferindo o sha (`59a5c5b4efe2fa54`).

Numa cópia anterior sobrescrevi o `SpedController.php` **sem** guardar o original — deslize sem
consequência (era reconstruível do `origin/main`), mas o backup passou a vir antes de qualquer
escrita. **Fica como regra para a próxima sessão: baixe e guarde antes de escrever no container.**

## Pendências herdadas que continuam abertas

- **Seed da permission `superadmin` no CT 100** — enquanto faltar, o `UC-FSPED-09` e o 503
  ponta-a-ponta do `UC-FSF1-02` não executam em lane nenhuma. Lacuna de ambiente, não de teste.
- **Emitente do registro `0000`** (CNPJ/IE vazios, UF fixa `SP`) — sessão paralela ativa.
- **Watchdog G6 vermelho** — conta OpenAI sem crédito (US-COPI-145), sessão paralela triando.
- **Smoke visual autenticado pós-merge** — pendente do merge.
