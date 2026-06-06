---
date: 2026-06-06
topic: "Plano — inventário derivado + gates anti-duplicação (CSS/JS/componentes) pra IA não recriar nem hand-rolar errado"
authors: [W, C]
related_adrs:
  - 0209-eslint-ratchet-baseline
  - 0239-governanca-design-system-git-ssot
  - 0240-task-ledger-evidencia-antifragilidade
  - 0013-constituicao-ui-v2-camadas
---

# Plano — segurança contra componente/função duplicada

> **Origem (Wagner, 2026-06-06):** *"vou ter a lista de css, JS, componentes primitivos? se eu perguntar
> vai saber onde a função existe e se reusa ou cria nova, em qual arquivo? preciso organizar isso pra IA
> não enlouquecer… não nascer componente errado ou duplicado, isso quebra o projeto. quero o projeto mais
> seguro."* + *"pode fazer teste pra não piorar? como vai sobreviver ao tempo?"*

## Princípio de fundo (decide tudo abaixo)

**Documento-índice escrito à mão APODRECE** (vira mentira em 2 semanas, a IA confia, recria errado — pior
que não ter). A única coisa que sobrevive ao tempo é **inventário DERIVADO do código + gate que falha o
merge se a regra for violada**. É exatamente o que já provou funcionar no `ds:report` (ADR 0240:
*"derivado+enforçado sobrevive / escrito+lembrado apodrece"*). Todo este plano segue essa lei.

## Diagnóstico (estado real medido em 2026-06-06)

| Camada | Já temos? | Buraco |
|---|---|---|
| Componentes UI primitivos | ✅ `REGISTRY_DS_COMPONENTES.md` (27 `ui/` + 17 `shared/`) + `REUSE_MAPPING.md` + regras `ds/*` + ratchet | catálogo **manual** (risco de apodrecer) e cobre só ~44 dos **84 `.tsx`** de `Components/` |
| Funções / hooks / utils JS-TS | ❌ **nada** | se pergunto "onde formata moeda / calcula margem?" → hoje é grep on-demand → **recria** |
| Duplicação estrutural (cópia de bloco) | ❌ nada | bloco copiado entre telas passa batido |
| CSS | 🟡 stylelint + ratchet de `oklch` | **35 bundles** (`sells-cowork-*`=11, `fin-*`=6) sem mapa de quem-é-canon → CSS duplicado nasce |

---

## As 3 frentes (cada uma: o que faz · TESTE pra não piorar · como SOBREVIVE)

### Frente 1 — `reuse:check`: inventário JS/TS **derivado** (ataca o buraco principal)

**O que faz.** Script `scripts/reuse-index.mjs` varre `resources/js/**` e **gera automaticamente** (nunca
escrito à mão) um índice de todo `export` — componentes, hooks (`useX`), utils, services — com
`{ nome, tipo, arquivo, assinatura }`. Dois modos:
- `npm run reuse:check "formatar moeda"` → responde **"já existe em `resources/js/utils/currency.ts:formatBRL` — REUSA"** ou **"não há equivalente — pode criar"**.
- modo agente: antes de eu criar `function/const/component`, consulto o índice e decido reusar vs criar **com arquivo na mão**.

**Teste pra não piorar (gate `reuse:duplicates`).** Pest/CI que falha se nascer **export com mesmo nome
semântico em 2 arquivos** (ex: dois `formatCurrency`). Ratchet igual ao eslint-baseline: a contagem de
duplicados conhecidos **só pode descer**, nunca subir. PR que introduz duplicata → CI vermelho.

**Como sobrevive ao tempo.** Índice é **regenerado do código a cada chamada** (zero edição manual → não
apodrece). Ancorado por `measured_against_sha` (padrão ADR 0240). Hook de pré-flight (skill
`constituicao-ui-aware` / `preflight-modulo`) passa a rodar `reuse:check` antes de Write em `.tsx`/`.ts`.

### Frente 2 — `knip` + `jscpd` no CI (dead-code + cópia de bloco)

**O que faz.** `knip` acha export/arquivo **morto** (ninguém importa → candidato a apagar, não duplicar).
`jscpd` acha **bloco de código copiado** entre arquivos (a duplicação que `reuse:check` por-nome não pega).

**Teste pra não piorar.** Ambos entram com **baseline ratchet** (mesma mecânica do PHPStan/eslint ADR 0209):
fotografa o débito atual, e o gate só falha se **aumentar**. Não obriga limpar o legado de uma vez — só
impede piorar. É literalmente a resposta ao "pode fazer teste pra não piorar?": **a catraca É o teste.**

**Como sobrevive ao tempo.** Roda em CI a cada PR (não depende de eu lembrar). Baseline versionado no git.

### Frente 3 — Mapa dos 35 bundles CSS (decisão de arquitetura, não código ainda)

**O que faz.** Doc derivado `CSS_BUNDLE_MAP.md` (gerado por script que lê `inertia.css`/`app.css` + quem
importa o quê) respondendo: **qual bundle é canon, qual é legado, qual classe mora onde**. Sem isso, a IA
não sabe onde botar CSS novo → duplica.

**Teste pra não piorar.** Gate `css:no-new-bundle` — PR que cria **novo arquivo `.css`** fora da lista
canônica → falha (força reusar bundle existente ou justificar via ADR). + ratchet de `oklch` cru já existe.

**Como sobrevive ao tempo.** Mapa **derivado** dos imports reais (não escrito à mão). Decisão de
arquitetura (camadas / consolidação) vira **ADR** → vira lei, não memória solta.

---

## Cross-cutting: as duas perguntas do Wagner, respondidas de propósito

**"Pode fazer teste pra não piorar?"** → Sim, e é o eixo. Cada frente nasce com **gate de ratchet**: o
débito atual vira baseline, o teste só falha se a métrica **piorar**. Nunca exige limpar tudo de uma vez
(isso travaria o trabalho de Receita). O teste **é** a garantia de não-regressão.

**"Como vai sobreviver ao tempo?"** → Três defesas, todas já validadas no projeto:
1. **Derivado, nunca escrito** — todo índice/mapa é gerado do código → impossível apodrecer.
2. **Freshness + `measured_against_sha`** — todo artefato sabe contra qual commit foi medido (ADR 0240).
3. **Gate no CI + hook de pré-flight** — não depende de eu (ou de qualquer dev) lembrar; a máquina cobra.

> Anti-padrão explicitamente rejeitado: criar um `INVENTARIO.md` gigante manual. Já temos a lição
> (ADR 0240) de que isso vira lápide. Tudo aqui é máquina-derivado.

---

## Ordem de execução proposta (PRs pequenos, espírito commit-discipline)

| Passo | Entrega | Por quê primeiro |
|---|---|---|
| **PR-1** | `reuse-index.mjs` + `npm run reuse:check` (só leitura, sem gate ainda) | ataca o buraco #1 (função JS duplicada) — maior dor do "enlouquecer" |
| **PR-2** | gate `reuse:duplicates` (ratchet) + wire no pré-flight skill | trava a porta (teste pra não piorar) |
| **PR-3** | `knip` + `jscpd` com baseline ratchet no CI | dead-code + cópia de bloco |
| **PR-4** | `CSS_BUNDLE_MAP.md` derivado + gate `css:no-new-bundle` | doma o caos dos 35 bundles |
| **PR-5** | ADR "inventário derivado anti-duplicação" (canoniza o método) | vira lei, sobrevive |

## ⚠️ Decisões que precisam de [W] antes de codar

1. **Escopo do ciclo.** Isto é **off-cycle** (ciclo vigente = Receita — Onda A). É melhoria de plataforma/
   segurança, não Receita direta. Wagner decide: encaixa agora (justificativa: "projeto mais seguro" reduz
   retrabalho que atrasa Receita) ou agenda pós-Onda A.
2. **Começar por PR-1** (índice JS, valor imediato) — confirmado como preferência na conversa.
3. **`knip`/`jscpd` são deps novas** — leves, dev-only, mas são adição. OK adicionar?

---

## Adendo 2026-06-06 — gut-check vs erros REAIS + ajustes (Wagner: "quais erros acontecem em design? plano preparado?")

Cruzei o plano com os **21 anti-padrões catalogados** em `LICOES_F3_FINANCEIRO_REJEITADO.md` (6 meta + 15 técnicos, sessão 2026-05-09). Coverage honesto:

| Família de erro real | Coberto? | Onde |
|---|---|---|
| Recriar função/componente que já existe | ✅ plano F1+F2 | `reuse:check` + `jscpd` |
| **Inventar Model/Service/Controller que não existe** (T-AP-1/7) | 🟡→✅ **ajuste**: F1 vira JS **e PHP** | índice de símbolos do repo inteiro |
| CSS duplicado / cor crua / bundle errado | ✅ plano F3 + `ds/*` | mapa + `css:no-new-bundle` |
| Hand-roll de primitivo (`<input>` cru) | ✅ já existe | regras `ds/*` + ratchet |
| Tenant scope ausente (Tier 0) | ✅ já existe | `multi-tenant-gate` |
| **Mock data / `rand()` / NO-OP em prod** (T-AP-12/13) | ❌→✅ **nova F6** | lint `no-mock-in-prod` |
| Marketing otimista ("F3 completo"=stub, M-AP-2) | ❌→🟡 F6 parcial | detecta stub-markers |
| Pular pré-flight sob pressa (M-AP-1, causa-raiz) | 🟡 parcial | `reuse:check` no pré-flight reforça |
| **Julgamento de maturidade** ("está pronto?") | ❌ por design | pré-flight + screenshot [W] — gate impede regredir, humano decide avançar |

### Ajustes aplicados ao plano
- **F1 expandida**: `reuse-index` indexa **JS/TS + PHP** (Models/Services/Controllers). `reuse:check "BaixaService"` → "não existe; reais são `Titulo`/`TituloBaixa` em `Modules/Financeiro/`". Mata T-AP-1/T-AP-7.
- **Nova Frente 6 — `no-mock-in-prod`**: gate que falha CI se controller de prod tiver `rand(`, `// TODO`, `@memcofre status: stub`, ou método só-`return back()`. Ataca T-AP-12/13/M-AP-2.

### Preparado pra evoluir (resposta [W] 2026-06-06)
Sim, por 3 camadas plugáveis: (1) **símbolos pluggable** — novo kind = entrada na tabela de extratores; (2) **gates = registry-of-rules + ratchet** — novo check = nova linha de baseline; (3) **tudo derivado + `measured_against_sha`** — código muda → índice se regenera sozinho. Adicionar check futuro = diff de ~10 linhas, não projeto novo.

## Execução (Wagner: "pode fazer em paralelo · pode fazer sim" — 2026-06-06)
- **F1** (`reuse-index.mjs` JS+PHP) — fundação, construída pelo Claude (keystone, F2 depende dela).
- **F3** (`css-bundle-map.mjs` + doc) — independente → **agente paralelo**.
- **F6** (`no-mock-in-prod` scanner + ratchet) — independente → **agente paralelo**.
- **F2** (gate `reuse:duplicates`) — após F1 landar.
- Nenhum commit/PR sem [W] — construo + verifico rodando, mostro, [W] aprova PRs.

## Resultados da execução paralela (2026-06-06) — 3 frentes prontas, verificadas, NÃO commitadas

| Frente | Artefato (novo, leitura pura) | Medido |
|---|---|---|
| **F1** | `scripts/reuse-index.mjs` | **3434 símbolos** (711 component · 463 controller · 352 service · 282 model · 202 util · 20 hook). `reuse:check "BaixaService"` → "❌ não existe; reais `TituloBaixa`/`BaixaRepository`" (mata T-AP-7). `--duplicates` → **26 reais** |
| **F6** | `scripts/no-mock-in-prod.mjs` + `no-mock-baseline.json` | **23 no baseline** (19 stub-marker · 2 NO-OP · 1 mock-array · 1 rand). Ratchet provado (exit 1 regressão / exit 0 limpo). Achados graves: `rand(` `Connector/UserController.php:406`; `update(){return back()}` `Essentials/DocumentController.php:109` |
| **F3** | `scripts/css-bundle-map.mjs` + `CSS_BUNDLE_MAP.md` | **34 bundles · 28 wired · 6 órfãos** (app/base/components/layout/themes/utilities = stubs Blade legacy). Pior oklch cru: `cowork-canon-financeiro-bundle` 867 · `sells-cowork` 619 · `fin-output` 222 |

### 🔴 Achado-headline (a doença, capturada)
`resources/js/Pages/Financeiro/Cobranca/_components/atoms.tsx` **re-implementou à mão 4 componentes canônicos** que já existem em `@/Components/shared`: `PageHeader`, `KpiCard`, `StatusBadge`, `Field`. + `cn` re-rolado em `Cobranca/_lib` (canon é `Lib/utils.ts`) + `formatBytes` dup em `Whatsapp/_components/helpers.ts`. Exatamente o "componente duplicado que quebra o projeto" — achado de primeira pela F1.

### Próximo (decisão [W])
- **F2** — gate `reuse:duplicates` (ratchet sobre as 26) + `css:no-new-bundle` + wire `reuse:check`/`no-mock` no CI e pré-flight. (leitura pura)
- **Limpeza `atoms.tsx`** (apagar re-rolls, importar do `shared`) — TOCA produção → PR separado com aval [W].
- **Empacotamento em PRs** + decisão de ciclo (off-cycle vs Receita) pendente [W].

## ⚠️ Re-alinhamento de preflight (2026-06-06) — achei o `MANUAL-CSS-JS.md` ANTES de commitar

Ao escrever o workflow CI, descobri **`memory/requisitos/_DesignSystem/MANUAL-CSS-JS.md`** (canônico, v1.1, atualizado HOJE) com roadmap **F0–F7**. Cruzamento honesto:
- **F3 `css-bundle-map` → DESCARTADO.** Sprawl CSS já é F0/F1/F5 do manual, com `css-size-gate` + `pageheader-gate` já ativos. A duplicação do PageHeader (meu "headline") **já é conhecida** (F4 do manual, 104 telas, ratchet `pageheader-gate`). Criar doc CSS paralelo = duplicar governança. **A ferramenta anti-dup evitou que eu duplicasse — dogfood.**
- **F1 `reuse-index`+F2 → MANTIDO (gap real).** Manual problema #5 ("colisões de símbolo / copy-paste") era diagnosticado **sem gate**; REGISTRY é manual/parcial. Índice derivado JS+PHP é net-new. → **PR #2343**.
- **F6 `no-mock` → MANTIDO (gap real, ortogonal).** Manual é CSS/JS; mock/rand/NO-OP em controller é backend (F3-rejeitado). → **PR #2344**.

### Achado guardado pro F5 do manual (não vira doc, vira nota)
`css-bundle-map` detectou **6 bundles órfãos** (carregados por ninguém, stubs Blade legacy): `app`, `base`, `components`, `layout`, `themes`, `utilities`. Candidatos a deleção (toca build → PR à parte com aval [W]) — avança a métrica-mãe do manual (linhas CSS ↓). Confirmar que nenhum é referenciado via `asset('css/…')` antes de remover.

### Backlog gap observado (não meu pra resolver agora)
O manual diz que o roadmap F0–F7 "vive como tasks MCP módulo `_DesignSystem`" — **mas não há nenhuma task lá** (`tasks-list module:_DesignSystem` = vazio). O roadmap está só no doc. Vale criar as tasks F0–F7 quando [W] quiser.

## Estado final da sessão
- **PR #2343** reuse-index + gate `reuse:duplicates` (3434 símbolos, baseline 26, ratchet provado) — referencia o manual #5.
- **PR #2344** no-mock-in-prod + ratchet (baseline 23, achados rand/NO-OP reais).
- css-map descartado (sobrepunha o manual); 6 órfãos viram nota pro F5.
- Ambos: Node puro, leitura pura, ratchet "não piorar", derivado (não apodrece). Worktree `.claude/worktrees/anti-dup`.
- **Pendente [W]:** revisar/mergear os 2 PRs · decidir limpeza `atoms.tsx` · criar tasks F0–F7 do manual.

## FECHO (2026-06-06) — 3 PRs mergeados no main

- **#2343** `reuse-index` + gate `reuse:duplicates` (`b3e88c14e`).
- **#2344** `no-mock-in-prod` + gate (`4b43d553c`).
- **#2345** rule `.claude/rules/reuse-check.md` (loop-closer — força `reuse:check` antes de criar) (`0e8f95411`).
- Confirmado via `ls-tree origin/main`: 2 scripts + 2 baselines + 2 workflows + 1 rule. Worktree `anti-dup` removido.

### Recalibração honesta (análise atoms.tsx, read-only)
O reuse-index **superestima** (flagra candidatos por nome, não veredito — é o esperado: lista → verificação humana refina). Dos 4 "re-rolls" do `atoms.tsx`: `PageHeader` = **morto** (0 usos), `formatBytes` (Whatsapp) = **drop-in**, `Field` = sem canon transversal, e só `KpiCard`(5×)+`StatusBadge`(2×) são reais — **não drop-in** (mudam visual DS v6, precisam estender canon + gate screenshot [W]).

### Pendente [W] (não auto-executado — toca produção/visual)
1. **Limpeza mecânica** (deletar PageHeader morto + formatBytes drop-in) — zero-risco mas toca `Pages/**` (mwart-gate) → aval [W].
2. **KpiCard/StatusBadge** → ciclo de design (screenshot [W]).
3. **Tasks F0–F7** do MANUAL-CSS-JS (não existem no MCP, só no doc).
4. **6 bundles CSS órfãos** (nota pro F5) — confirmar + deletar = PR build à parte.

## ENCERRAMENTO (2026-06-06) — 7 PRs mergeados + 2 rodadas paralelas

| PR | Entrega | Estado |
|---|---|---|
| #2343 | reuse-index + gate `reuse:duplicates` (3434 símbolos JS+PHP, baseline 26) | ✅ merged |
| #2344 | no-mock-in-prod + gate (baseline 23) | ✅ merged |
| #2345 | rule `.claude/rules/reuse-check.md` (loop-closer pré-flight) | ✅ merged |
| #2346 | dedup formatBytes → re-export (baseline reuse 26→25) | ✅ merged |
| #2347 | seed F0–F7 do MANUAL no SPEC.md (US-027..034) | ✅ merged |
| #2348 | deletar 6 stubs CSS mortos (−81 linhas, **4 baselines** limpos cirurgicamente) | ✅ merged |
| #2349 | gate `jscpd` (copy-paste, 5.3% medido, threshold 5.5) | ✅ merged |

### jscpd — clones grandes achados (que reuse-index por-nome NÃO pega)
- `Purchase/Create.tsx` ↔ `StockAdjustment/Create.tsx` — **272 linhas copiadas**
- `RecurringBilling/Planos` Create↔Edit — 200 linhas
- `OficinaAuto/Vehicles` Create↔Edit — 193 linhas
→ candidatos a extrair componente compartilhado (F3 layout primitives ajuda).

### A disciplina pegou 4 erros (o maior valor)
1. preflight → manual overlap (descartei frente CSS duplicada).
2. grep árvore inteira → PageHeader NÃO morto (PaymentGateways cross-módulo) → não quebrei.
3. comparar IDs → F0–F7 stale-numerados → landei correto via git.
4. agente órfãos → stubs em **4** guards (não 2); `.conformance-baseline` crasharia CI com ENOENT → tratado.

### Estado de segurança (honesto)
4 redes novas (reuse-gate, no-mock-gate, reuse-check rule, jscpd) + as existentes. Todos RATCHET (impedem PIORAR, não consertam legado: 25 dups + 23 mocks + 5.3% copy-paste + 20k CSS sprawl continuam, só congelados). Gates não julgam corretude/maturidade — humano + testes + gate visual seguem necessários. Cada gate teve o modo-FALHA testado (não só sucesso).

### Pendências reais [W]
- KpiCard/StatusBadge re-rolls → ciclo de design (screenshot).
- Reconciliar contador MCP `_DesignSystem` (027 vs 018, gap 019–026) no servidor.
- Atacar os clones grandes do jscpd quando houver sinal.
