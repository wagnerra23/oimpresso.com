# Verificação [Code] do pedido `COLAR-NO-CODE-AUTOMACAO-DO-PROTOCOLO.md`

> Companheiro do pedido que desceu nesta PR. **Não altera o pedido** (ele é fóssil datado do lado design).
> Registra o que foi **medido** aqui antes de qualquer linha de A1…A7 ser escrita, com o comando ao lado
> pra o leitor reproduzir (§5 2026-07-28: número em canon vem com o comando).
> **Data da medição: 2026-09-03**, contra `origin/main` fresco (branch criada em 0/0 vs `origin/main`).

## Recibo da descida

- Origem única resolvida por `DesignSync.list_files` no projeto **`019dcfd3-6ef2-7ee6-8512-b1b0e5544e58`**
  (projeto "telas", não-listado, por ID — painel `protocolo.config.mjs`). Uma só ocorrência do nome, na raiz.
- `get_file` devolveu `truncated: false`, 7167 bytes. Escrita inline pelo agente sob ADR 0389.
- Consumidores rodados pós-escrita, os dois verdes:
  - `node scripts/governance/cowork-ssot-guard.mjs` → `✓ fonte única OK` (R1 respeitada: o `.md` pousou em
    `design-docs/`, não em `cowork/`).
  - `node prototipo-ui/design-docs/cowork-inbox/ponte/ponte-handoff-lint.mjs` → rc=0.

## A claim central do pedido — VERIFICADA

O pedido afirma *"nenhum dono novo. Cada PR estende máquina que já existe"* e nomeia 8 donos.
**Os 8 existem em `origin/main`** (`git ls-tree origin/main -- <path>`):

`design-memory-gate.yml` · `cowork-ssot-guard.mjs` · `cowork-mirror-freshness.mjs` ·
`casos-coverage-guard.mjs` (+ `casos-gate.yml`) · `contrato-de-tela.mjs` · `design-spec-gen.mjs` ·
`prototipo-readiness.mjs` · `gerar-payload-partes.mjs`.

Ressalva honesta que o pedido não diz: **os 8 arquivos-alvo são todos novos**. "Estende o tema" e
"cria arquivo" não se excluem — mas em dois casos abaixo o arquivo novo cairia **em cima de dono vivo**.

## Correção 1 — A1: a pergunta do pedido tem resposta, e é SIM

O A1 pergunta *"Playwright já usado no repo? se não, `puppeteer-core` + Chrome do runner"*.

Medido em `origin/main:package.json`: **`@playwright/test ^1.49.0`**, com `e2e:install`
(`playwright install --with-deps chromium`) e `e2e:check` (`playwright test`).
O `pestphp/pest-plugin-browser` v4 também já bundleia e injeta `axe.min.js`
(declarado em `tests/Browser/CoreScreens/A11yAxeBrowserTest.php`).

**Consequência:** o A1 usa Playwright. Não se adiciona `puppeteer-core` — dependência nova exige ADR
(`memory/proibicoes.md` §Código), e aqui ela seria um 2º driver de browser sem necessidade.

## Correção 2 — A4: a11y já tem TRÊS fases com dono; o A4 como escrito seria a QUARTA

Medido (`git ls-tree -r --name-only origin/main | grep -iE "a11y|axe"`):

| Fase | Dono vivo | O que já cobre |
|---|---|---|
| 1 | `scripts/a11y-ratchet.mjs` + `config/a11y-baseline.json` | `jsx-a11y/*` estático como **categoria protegida — a contagem só DESCE**, nem via `lint:baseline:write` |
| 2 | `.github/workflows/a11y-axe-gate.yml` — *"A11y axe runtime (jsdom · componentes canon)"* | axe no DOM simulado |
| 3 | `tests/Browser/CoreScreens/A11yAxeBrowserTest.php` | axe no **Chromium real** (Playwright): **contraste de cor, ordem de foco, ARIA-em-contexto**, com **ratchet por level** (level 0 = critical, piso conservador) |

O próprio docblock da Fase 1 **já nomeava** a Fase 2 como sua continuação
(*"axe-core RUNTIME (contraste/ARIA/foco que o estático não vê) = Fase 2"*).

**O que sobra do A4, e é legítimo:** as sondas que o axe de fato não faz — `DIV` clicável sem
`role`/`tabindex` · `svg` em clicável sem `aria-hidden` nem nome · `aria-live` ausente ·
overlay sem `role`/`aria-modal`/foco · `aria-selected|pressed` estático · alvo <24×24.
**O lugar delas é dentro da Fase 3 / do ratchet existente**, não num `scripts/qa/a11y-alvo.mjs`
paralelo — que seria LC-19 medido (autorar máquina paralela a tema que já tem dono).

**Corolário sobre o A2:** no Chromium real o `getComputedStyle` **resolve `oklch` sozinho**, e o axe
já calcula `color-contrast` dali. O erro do 2,62 nasceu de parsear `oklch` com regex de `rgb()`
**fora** do browser. Logo o A2 continua valendo para cálculo derivado fora do browser — mas
**não é pré-requisito** para medir contraste dentro dele.

## Correção 3 — A7: já existe um template de intake para esta mesma rota

`.github/ISSUE_TEMPLATE/cowork-intake.yml` existe em `origin/main`. O A7 propõe
`.github/ISSUE_TEMPLATE/onda.yml` para a mesma rota (abrir sessão de onda a partir do `cowork-inbox`).
Antes de criar o segundo: ou o `onda.yml` **estende** o `cowork-intake.yml`, ou o par precisa de razão
escrita. `.claude/commands/onda.md` não tem esse conflito — o diretório tem 6 comandos, nenhum de onda.

## Não medido (declarado, não escondido)

- **O bloqueio herdado do A3** (`--compare` abortando com *"exige um snapshot.json existente"*) foi
  **relatado pelo pedido e não re-verificado aqui** — não rodei o `--compare`. Quem for fazer o A3
  mede antes de assumir (§5 2026-09-01: afirmação de bloqueio em doc canon não se herda, re-executa-se).
- **A baseline de a11y da Forja** (23 `DIV` · 66/66 svg · 81/118 alvos) veio do lado design; não a reproduzi.
- **Os 17 `forja-*.jsx` abaixo do piso de ~48 KB** — não medi os tamanhos.

## O que esta PR NÃO faz

Não escreve A1…A7. É só a **ponte**: o pedido desce e fica versionado, com a verificação ao lado.
Cada PR-A é 1 assunto próprio, na ordem que o pedido fixa (`A1+A2 → A5 → A3 → A4 → A6 → A7`).
