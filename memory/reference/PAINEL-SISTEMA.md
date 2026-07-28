---
name: PAINEL-SISTEMA — índice gerado do estado do sistema oimpresso
description: MATRIZ gerada por scripts/governance/system-map.mjs. NÃO editar à mão (regenera). Índice que aponta pros donos canônicos + fatos deriváveis + frescor real.
type: reference
authority: generated
lifecycle: ativo
---

# 🗺️ PAINEL-SISTEMA — estado do oimpresso

> ⚙️ **Gerado por máquina** (`system-map.mjs`) em **2026-07-28**. NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/system-map.mjs`. Este é um **índice que aponta pros donos canônicos**, não uma cópia deles.
> Views humanas (mapa 🗺️ / guia 🧭 em claude.ai) derivam DESTES dados.

## Módulos & verticais

> Status/narrativa vivem no BRIEFING de cada módulo (curado). Aqui: existência + **último toque real** (git). Data absoluta (determinística — sem churn diário); a leitura de "está velho?" é do olho: um BRIEFING de meses atrás é candidato a re-destilar.

| Módulo | BRIEFING | Último toque |
|---|---|---|
| Admin | [BRIEFING](../requisitos/Admin/BRIEFING.md) | 2026-07-23 |
| ADS | [BRIEFING](../requisitos/ADS/BRIEFING.md) | 2026-07-23 |
| Arquivos | [BRIEFING](../requisitos/Arquivos/BRIEFING.md) | 2026-07-23 |
| AssetManagement | [BRIEFING](../requisitos/AssetManagement/BRIEFING.md) | 2026-07-23 |
| Auditoria | [BRIEFING](../requisitos/Auditoria/BRIEFING.md) | 2026-07-23 |
| Brief | [BRIEFING](../requisitos/Brief/BRIEFING.md) | 2026-07-23 |
| Cms | [BRIEFING](../requisitos/Cms/BRIEFING.md) | 2026-07-23 |
| Compras | [BRIEFING](../requisitos/Compras/BRIEFING.md) | 2026-07-27 |
| ComunicacaoVisual | [BRIEFING](../requisitos/ComunicacaoVisual/BRIEFING.md) | 2026-07-28 |
| Connector | [BRIEFING](../requisitos/Connector/BRIEFING.md) | 2026-07-23 |
| ConsultaOs | [BRIEFING](../requisitos/ConsultaOs/BRIEFING.md) | 2026-07-23 |
| Crm | [BRIEFING](../requisitos/Crm/BRIEFING.md) | 2026-07-23 |
| Essentials | [BRIEFING](../requisitos/Essentials/BRIEFING.md) | 2026-07-23 |
| Financeiro | [BRIEFING](../requisitos/Financeiro/BRIEFING.md) | 2026-07-27 |
| Fiscal | [BRIEFING](../requisitos/Fiscal/BRIEFING.md) | 2026-07-27 |
| Governance | [BRIEFING](../requisitos/Governance/BRIEFING.md) | 2026-07-27 |
| Jana | [BRIEFING](../requisitos/Jana/BRIEFING.md) | 2026-07-27 |
| KB | [BRIEFING](../requisitos/KB/BRIEFING.md) | 2026-07-28 |
| Manufacturing | [BRIEFING](../requisitos/Manufacturing/BRIEFING.md) | 2026-07-23 |
| NfeBrasil | [BRIEFING](../requisitos/NfeBrasil/BRIEFING.md) | 2026-07-28 |
| NFSe | [BRIEFING](../requisitos/NFSe/BRIEFING.md) | 2026-07-23 |
| Officeimpresso | [BRIEFING](../requisitos/Officeimpresso/BRIEFING.md) | 2026-07-23 |
| OficinaAuto | [BRIEFING](../requisitos/OficinaAuto/BRIEFING.md) | 2026-07-27 |
| PaymentGateway | [BRIEFING](../requisitos/PaymentGateway/BRIEFING.md) | 2026-07-23 |
| Ponto | [BRIEFING](../requisitos/Ponto/BRIEFING.md) | 2026-07-27 |
| ProductCatalogue | [BRIEFING](../requisitos/ProductCatalogue/BRIEFING.md) | 2026-07-23 |
| ProjectMgmt | [BRIEFING](../requisitos/ProjectMgmt/BRIEFING.md) | 2026-07-28 |
| RecurringBilling | [BRIEFING](../requisitos/RecurringBilling/BRIEFING.md) | 2026-07-28 |
| Repair | [BRIEFING](../requisitos/Repair/BRIEFING.md) | 2026-07-23 |
| Spreadsheet | [BRIEFING](../requisitos/Spreadsheet/BRIEFING.md) | 2026-07-23 |
| SRS | [BRIEFING](../requisitos/SRS/BRIEFING.md) | 2026-07-23 |
| Superadmin | [BRIEFING](../requisitos/Superadmin/BRIEFING.md) | 2026-07-23 |
| TeamMcp | [BRIEFING](../requisitos/TeamMcp/BRIEFING.md) | 2026-07-28 |
| Vestuario | [BRIEFING](../requisitos/Vestuario/BRIEFING.md) | 2026-07-28 |
| VozDoCliente | [BRIEFING](../requisitos/VozDoCliente/BRIEFING.md) | 2026-07-28 |
| Whatsapp | [BRIEFING](../requisitos/Whatsapp/BRIEFING.md) | 2026-07-23 |
| Woocommerce | [BRIEFING](../requisitos/Woocommerce/BRIEFING.md) | 2026-07-23 |

## Camada de IA

> Contagem DERIVADA da árvore (contrato `implements`, não pasta). Isto conta **arquivo que implementa contrato** — não é nota, não é status e não prova que a peça roda. O que cada agente faz e se está ligado vive no BRIEFING do módulo e na config; aqui só existe o censo. Antes disto, estes números viviam à mão num diagrama e já tinham errado (`16 provedores` era 15).

- **Agentes** (`implements Agent`, fora de `Tests/`): **22** — todos em `Ai/Agents/`, convenção íntegra.
  - por módulo: Jana 14 · ADS 4 · Crm 3 · Whatsapp 1
- **Tools MCP registradas** no `OimpressoMcpServer`: **44** — Jana 39 · TeamMcp 4 · Brief 1. Bate com os arquivos `*Tool.php` em `Modules/*/Mcp/Tools/`. _Registrada ≠ exposta_: a exposição é gated por `MCP_TOOLS_EXPOSED` (`config/mcp.php`), estado de runtime que a árvore não sabe.
- **Provedores** declarados em `config/ai.php`: **15** · default = `openai` — anthropic, azure, bedrock, cohere, deepseek, eleven, gemini, groq, jina, mistral, ollama, openai, openrouter, voyageai, xai. _Declarado ≠ com chave_: a credencial mora no ambiente.
- **Implementações de `MemoriaContrato`**: McpMemoriaDriver · MeilisearchDriver · NullMemoriaDriver · RetrievalTelemetryDecorator
- **Rerankers** (`implements Reranker`): BgeReranker · LlmRerankerAdapter · NullReranker · RrfReranker
- **Tools SQL do Brief Diário**: **5** · **agentes de engenharia**: **24** — catálogo separado do runtime PHP.
- Arquitetura completa, topologia, compose e probes: [`Jana/ARCHITECTURE.md`](../requisitos/Jana/ARCHITECTURE.md) — gerada por esta mesma máquina.

> Não derivável e por isso NÃO listado aqui: quais pipelines de retrieval existem e qual está ligado — isso mora na config e no BRIEFING da Jana, e um número inventado aqui seria pior que a ausência.

## Programa SDD (governança)

- Scorecard: **12/13** métricas medidas · floor full-suite = **339**.
- Fonte viva: `governance/sdd-scorecard.json` (gerado por `sdd-scorecard.mjs`). Avaliação adversarial: `/sdd-avaliar`.
- Roadmap dono: [`memory/requisitos/_Governanca/roadmap/_ROADMAP.md`](../requisitos/_Governanca/roadmap/_ROADMAP.md).

## Auditorias & Gates

> Fontes versionadas (offline, sem `gh api`): censo [`gates-registry.json`](../../scripts/governance/gates-registry.json) (o que **existe**) + [`required-checks-baseline.json`](../../governance/required-checks-baseline.json) (o que **bloqueia**, congelado). Anti-demoção invisível: `protection-drift.mjs` (GT-G4). As catracas mordem: `gate-selftest` (GT-G6). Censo cobrado por `memory-health` Check G/M.

### Bloqueiam merge — 35 required (enforcement: everyone)
> Congelados no baseline (captura 2026-06-20). Divergência do vivo é sinalizada pelo `protection-drift`, não reconciliada aqui.

- ADR (memory/decisions/*.md)
- ADR 0216 PR scan (governance:audit --diff-only)
- ADR frontmatter
- Ancora de design nao-shell (F2/F6 required)
- Append-only canon (ADRs, handoffs, Constituição)
- Casos-coverage · ratchet (trio + rastreabilidade)
- Charter (resources/js/Pages/**/*.charter.md)
- DS gate
- Dominio-dict · ratchet (enum ⇔ dicionário)
- ESLint · ratchet vs baseline
- Frontend / Vite build
- Layout primitives · ratchet
- Modulo backend com BRIEFING (cobertura)
- No hardcode business_id (Tier 0)
- No-mock-in-prod · ratchet
- PHP / Pest (Financeiro · MySQL)
- PHP / Pest (NfeBrasil · MySQL)
- PHP / Pest (Unit)
- PHPStan / Larastan · ratchet vs baseline
- PII scan (CPF/CNPJ literal)
- SDD scorecard ratchet (métrica armada não regride · GT-G3)
- SPEC (memory/requisitos/*/SPEC.md)
- Secret scan (gitleaks · só linhas novas do PR)
- Stylelint · ratchet vs baseline
- Tier-0 guards (WithoutGlobalScopes + BusinessId)
- Tópico (memory/requisitos/*/topicos/*.md)
- anchor entry/covers gate
- anchor-lint ADR 0273
- charter status:live precisa de sinal de prod
- doneness-lint ADR 0302
- gate selftest (as catracas mordem · GT-G6)
- screen-coverage-gate
- visual-regression
- deadlink-gate (ratchet · integridade referencial)
- Governance Gate (índice + memory-health + meta-teste)

### Censo — 116 workflows por classe

> Lista completa + propósito de cada um: [`gates-registry.json`](../../scripts/governance/gates-registry.json) (o dono). Aqui: contagem + exemplos.

| Classe | Qtd | Exemplos |
|---|---|---|
| gate (bloqueia/valida PR) | 85 | a11y-axe-gate, a11y-gate, adr-index-gate, adr-lint, … |
| meta (testa os gates) | 7 | block-brl-values-selftest, devcontainer-firewall, gate-selftest, guards-meta-gate, … |
| automacao (cron/dispatch) | 21 | agent-cost-per-pr, agent-pr-outcomes, briefing-code-staleness, casos-results-publish, … |
| deploy (entrega) | 2 | deploy, quick-sync |
| qualidade | 1 | brl-scan |

## Decisões (ADRs)

- **362** ADRs no total. Índice gerado: [`_INDEX-GENERATED.md`](../decisions/_INDEX-GENERATED.md) · lifecycle: [`_INDEX-LIFECYCLE.md`](../decisions/_INDEX-LIFECYCLE.md).
- Por status: aceito: 328 · superseded: 14 · deprecated: 12 · proposto: 6 · rascunho: 1 · recusado: 1.
- **4** reversões de rota (ADR com `supersedes:`).

## Ideias avaliadas e ABANDONADAS (§5 — não re-propor)

> Dono canônico: [`memory/proibicoes.md §5`](../proibicoes.md). 57 entradas.

- ~~2026-06-05 — Roadmap/plano de evolução PARALELO a canon existente~~
- ~~2026-06-05 — Teste que deriva do CÓDIGO (tautológico) em vez do contrato~~
- ~~2026-06-09 — Domínio de "locação" na Oficina (alucinação herdada do legado)~~
- ~~2026-06-29 — Migrar `<select>`→Radix `<Select>` mapeando opções data-driven SEM filtrar valor vazio~~
- ~~2026-06-30 — Guarda de âncora de design por NOME/PASTA (denylist OU allowlist) em vez de proveniência por charter~~
- ~~2026-07-01 — Gate de CI "charter foi tocado no diff" (charter-sync-gate) pra forçar sync código↔charter~~
- ~~2026-07-01 — Re-promover `foundation-ratchet` a required (roadmap Pfr) DEPOIS da 0314 tê-lo demovido~~
- ~~2026-07-09 — Workflow render-diff prod×proto em CI (RE-PROPOSTO e re-morto — a lápide canônica é a ADR 0290)~~
- ~~2026-07-09 — Sentinela anti-"hard-fail fantasma" em job advisory (premissa falsa)~~
- ~~2026-07-09 — Guard de bundle Cowork exigindo `@scope`/`@layer` (100% falso-positivo)~~
- ~~2026-07-09 — Dobrar casos+contrato numa catraca de cobertura nova (duplica o `casos-gate` required)~~
- ~~2026-07-09 — Frescor por `verificado_em` vs git-mtime (duplica o `briefing-code-staleness`)~~
- ~~2026-07-09 — Chokepoint de guard em comando fantasma (`flag:set`) que o fluxo real não atravessa~~
- ~~2026-07-09 — Consolidar os dois §5 (código e design) num ponteiro único~~
- ~~2026-07-09 — Claims de superioridade "acima do mercado" REFUTADAS (lápides de humildade — não re-alegar sem re-verificar)~~
- ~~2026-07-10 — Grade de réguas por decomposição em slices, sem teste de integração (fabrica "0 acima" falso)~~
- ~~2026-07-10 — Remover a redeclaração de tokens de domínio nos bundles `.fin-cowork`/`.sells-cowork` (parece "não-redeclarar", reintroduz bug de PORTAL)~~
- ~~2026-07-12 — Normalização MECÂNICA em massa de arquivos LEGADOS de `memory/` (backfill de frontmatter)~~
- ~~2026-07-15 — Apresentar ACHADO / causa-raiz / correção a partir de LEITURA de código, sem prova e sem varredura~~
- ~~2026-07-16 — Importar solução de OUTRO sistema sem checar se o problema existe no NOSSO (3× na mesma sessão)~~
- ~~2026-07-16 — Medir a PROPRIEDADE ERRADA e chamar de "verificado"~~
- ~~2026-07-15 — Force-migrar os 2 comboboxes async de `Sells` pro canon `Command` (onda combobox)~~
- ~~2026-07-17 — Doc canônico RESTATEAR número que outro sistema sabe melhor (oráculo errado)~~
- ~~2026-07-17 — Deduzir QUEM RODA (schedule/fila/cron) parseando código, quando o runtime sabe responder~~
- ~~2026-07-17 — Verificar cron/daemon em host gerenciado por `crontab -l` (o binário pode não existir → falso "não tem cron")~~
- ~~2026-07-17 — Regravar o baseline do `jana:drift-sentinel` pra "real" (o chip C3) — polir um alarme TAUTOLÓGICO~~
- ~~2026-07-16 — Gate/label/comentário AFIRMAR o próprio enforcement em tempo presente ("segue advisory", "não bloqueia")~~
- ~~2026-07-16 — Eleger US (SPEC) ou UC (casos.md) como CANAL DE PEDIDO do dono (ponto de entrada pré-código)~~
- ~~2026-07-17 — "Razão de fidelidade" (nota única) agregando os vereditos do `style-fingerprint` + baseline por tela~~
- ~~2026-07-17 — Alegar que "recusar agregar fidelidade é só nosso" é superioridade (REFUTADO — Chromatic já faz)~~
- ~~2026-07-17 — Casar custo→PR por SHA (ou Anthropic Analytics API) pra fechar o "órfão" do `agent-cost-per-pr`~~
- ~~2026-07-17 — Promover `component-registry-check` a required (ou re-emendar a 0314 per-gate) sem mordida provada~~
- ~~2026-07-19 — EMENDA da lápide 2026-07-10 (falácia de composição): a PERGUNTA de integração virou CARIMBO — braço discriminativo obrigatório~~
- ~~2026-07-20 — Aposentar mcp-first-warning / charter-validate / modulo-preflight-warning alegando que a skill homônima (Tier B) "já cobre" o hook~~
- ~~2026-07-20 — Auto-feed do ledger de aprendizado: as 6 formas rejeitadas de "ler os últimos erros" (S1-estrita · cadeia/família · watermark · Check-em-memory-health · fontes CI-red/reverts/degradação · big-bang)~~
- ~~2026-07-21 — Re-importar o modelo oficial Anthropic (Claude Design canvas no CENTRO do fluxo, alimentado por "briefing compilado") como o fluxo de design do oimpresso~~
- ~~2026-07-22 — Responder "quais arquivos/artefatos tem a tela" por Glob/leitura em vez da porta viva derivada~~
- ~~2026-07-22 — Mover os 6 dicionários de `memory/dominio/` (SINGULAR) para `memory/dominios/` (plural) — DOIS DONOS, não pasta duplicada~~
- ~~2026-07-22 — Blindar candidatos de realocação "por julgamento" sem rodar a máquina + afirmar comportamento de extrator por leitura~~
- ~~2026-07-23 — Criar um "mapa/porta única da arquitetura de arquivos" (doc + gerador) — os donos JÁ existem; e o 03-architecture.md é fóssil-armadilha~~
- ~~2026-07-24 — Citar data de `git log` como recibo sem conferir se o clone é RASO (e o irmão: teste que passa por NÃO-EXECUÇÃO)~~
- ~~2026-07-25 — Índice de "porta viva por PERGUNTA" como acelerador do loop de aprendizado (a porta preexistia e não preveniu)~~
- ~~2026-07-25 — Detectar "importou solução externa sem checar a premissa" (LC-09) por vocabulário de sistema externo em doc canon~~
- ~~2026-07-26 — Medir REINCIDÊNCIA PÓS-GATE comparando data de ocorrência (prosa do ledger) com nascimento do gate (git)~~
- ~~2026-07-26 — Comparar `node --check` de dois arquivos em CONTEXTOS DE RESOLUÇÃO DE MÓDULO diferentes (ESM × CJS) e concluir "o agente quebrou"~~
- ~~2026-07-26 — Presence-gate que ENVELHECEU EM PRODUÇÃO: critério de "evidência" por string solta (reincidência LC-11, e a primeira que não morreu na origem)~~
- ~~2026-07-26 — Lint que acusa `toHaveKey` em teste de contrato como "assert acoplado à chave" (100% FP medido)~~
- ~~2026-07-27 — Decidir "o gap P0 está fechado?" por `existsSync` do artefato — o banner do PRÓPRIO loop IA-OS dizia "LOOP FECHADO" com item aberto~~
- ~~2026-07-27 — Grade de máquina com denominador INVENTADO + métrica de outro universo lida como veredito (e a hipótese adversarial que a própria auditoria refutou)~~
- ~~2026-07-27 — `git stash pop` (ou qualquer consumo de estado GLOBAL do repo) presumindo que a entry do topo é sua~~
- ~~2026-07-27 — Ligar o `jana:retention-purge` (varredura automática que anonimiza PII por TTL) — DESCARTADO por [W]: num ERP não se apaga PII~~
- ~~2026-07-27 — EMENDA da lápide 2026-07-12: a classe "tocar legado acorda gate diff-aware" tem 3 EIXOS, não só o SPEC~~
- ~~2026-07-28 — Declarar "a máquina NÃO existe" a partir de grep estreito — e a resposta NÃO é índice novo~~
- ~~2026-07-28 — Validar um gate rodando UM dos modos que o CI roda, e chamar de verde~~
- ~~2026-07-28 — Lint que detecte "mensagem passada como NEEDLE" em `toContain` (o defeito é real; o lint cai na lápide do `toHaveKey`)~~
- ~~2026-07-28 — Teste que afirma "registrado" medindo `app(Class::class)` — 2 comandos mortos escondidos por 2,4 meses (3ª instância LC-11 em produção)~~
- ~~2026-07-28 — Medir cobertura de um glob de CÓDIGO com o pathspec do git (`*` atravessa `/`, `glob()` do PHP não)~~

## Tier 0 gaps (esperam decisão/desbloqueio)

- ⛔ 2026-05-28 — Token Hostinger API inacessível ao agente autônomo

## Rastro

- **325** handoffs · **551** session logs. Índice: [`memory/08-handoff.md`](../08-handoff.md).
- Sessions recentes:
  - `2026-07-28-sdd-vestuario-etiquetas`
  - `2026-07-28-sdd-teammcp-hub`
  - `2026-07-28-sdd-recurringbilling-cobranca-recorrente`
  - `2026-07-28-sdd-nfebrasil-emissao-fiscal`
  - `2026-07-28-sdd-kb-index`
  - `2026-07-28-sdd-faltantes-fila-derivada-e-merges`

---
_Gerado por `scripts/governance/system-map.mjs` · 2026-07-28 · deriva das fontes canônicas, não as substitui._
