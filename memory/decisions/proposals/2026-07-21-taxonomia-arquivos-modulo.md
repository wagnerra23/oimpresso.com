---
proposal_id: taxonomia-arquivos-modulo
status: accepted
promoted_to: 0345-topicos-vivos-aprendizado-por-critica-revisada
created: 2026-07-21
proposed_by: claude-code
decided_by: wagner
parent_adr: 0256 (knowledge survival) + estrutura-canon-memoria (proposal irmã)
related_adrs: [0256, 0085, 0079, 0080, 0291, 0273, 0314, 0264]
type: arquitetura-de-conhecimento
---

# Taxonomia de arquivos-tema por módulo — 1 arquivo = 1 propósito, BRIEFING = índice

- **Status:** aceito por [W] em 2026-07-21 e promovido à [ADR 0345](../0345-topicos-vivos-aprendizado-por-critica-revisada.md).
- **Data:** 2026-07-21 · **Autor:** [CC].
- **Origem:** pedido [W] 2026-07-21 — *"cada tópico do briefing vem de um arquivo único, tema único; o briefing seria o resumo; a IA sabe onde entrar por módulo, sempre atualizado."* Pesquisa que ancora: [session 2026-07-21 arte-catálogo (PR #4611)](https://github.com/wagnerra23/oimpresso.com/pull/4611) (a proposta [W] = modelo IDP; o repo já tem ~80% em fragmentos).
- **NÃO é paralelo** (§5 proibicoes 2026-06-05): estende [`estrutura-canon-memoria`](estrutura-canon-memoria.md) (o programa-mãe da estrutura de `memory/`) + a lei [ADR 0256](../0256-knowledge-survival-meia-vida-catraca-sentinela.md). Esta proposta é a **fatia "descritor + superfície por módulo"** dele, não um segundo programa.

## Contexto — o problema real (medido)

Hoje um módulo tem **4-6 docs por módulo com fronteiras difusas** + **um conflito de casa**:

- `Modules/<X>/SCOPE.md` (36/36) — **fronteira/ownership**, ENFORÇADO por `scope-guard.yml` (blocking em PR) + `bin/check-scope.php` ([ADR 0085](../0085-fase-3-4-scope-md-completo-actor-resolver-pii-redactor.md)).
- `memory/requisitos/<X>/BRIEFING.md` (85) — **estado-vivo/resumo**, grace (warn-only) no memory-schema-gate.
- `memory/requisitos/<X>/SPEC.md` (59) — **requisitos** (US), anchor-lint required.
- `memory/requisitos/<X>/SUPERFICIE.md` (8, gerado) — **superfície de arquivos derivada** ([módulo-surface](../../../scripts/governance/module-surface.mjs), `--check` advisory, promote_by 2026-08-04).
- por tela: `charter` / `casos` / `scorecard` — lei / contrato UC / nota.

**Overlap medido:** só o `purpose` de SCOPE ≈ a abertura "Estado atual" do BRIEFING (1 linha duplicada). Não é catástrofe, mas é fricção.

**O conflito (o achado que dói):** existem **11 `Modules/<X>/BRIEFING.md` concorrentes** (Admin, Jana, Crm, Essentials, Governance, Connector, ...) além dos 85 canônicos em `memory/requisitos/`. O de Jana está **stale e afirma 96/100** enquanto o canônico diz **73**. "BRIEFING" mora em **dois lugares** — e a IA não sabe qual é o verdadeiro.

## Decisão proposta

### 1. Cada arquivo = UM propósito (Diátaxis: não borrar fronteira)

| Arquivo | Propósito ÚNICO | Casa canônica | Enforcement |
|---|---|---|---|
| `SCOPE.md` | **fronteira/ownership** (`contains[]`/`not_contains[]`/`db_tables_owned`) | `Modules/<X>/` | scope-guard (blocking) |
| `SUPERFICIE.md` | **superfície de arquivos DERIVADA** (por papel) | `memory/requisitos/<X>/` | module-surface `--check` |
| `BRIEFING.md` | **resumo/índice** estado-vivo que **APONTA** (não recopia) | `memory/requisitos/<X>/` | memory-schema grace→required pós-backfill |
| `SPEC.md` | **requisitos** (US) | `memory/requisitos/<X>/` | anchor-lint (required) |
| `charter`/`casos`/`scorecard` | **lei / contrato UC / nota** por tela | ao lado do `.tsx` | já required |

### 2. Regra dura — ponteiro, não cópia
O BRIEFING **referencia** os irmãos (SCOPE/SUPERFICIE/SPEC) por link; **fato duplicado entre dois docs = bug de taxonomia**. O BRIEFING é o índice; a verdade derivável mora no gerador/descritor apropriado (ADR 0256: "derivado sobrevive; escrito+lembrado apodrece"). A linha-ponteiro pro SUPERFICIE (já em 8 BRIEFINGs) é o 1º exemplo.

### 3. Unidade = MÓDULO, não tabela-de-domínio (decisão [W] registrada)
A superfície deriva por **módulo** (como charter/casos/SPEC/requisitos já são). Módulos **Classe B** (código no core `app/`: Venda, Produto) declaram uma **semente curada de prefixos** no gerador (`CORE_APP_MODULES`, revisável no diff). As **tabelas do domínio** entram como **metadado-âncora** na SUPERFICIE, **nunca como derivador** — medição 2026-07-21: derivar por tabela over-inclui (`Transaction::` em 168 arquivos varre Financeiro/Jana inteiros).

### 4. Disciplina do hook (da pesquisa IDP — o que NÃO fazer)
Quando um hook mantiver os docs frescos: ele **DERIVA os fatos-máquina** (superfície, contagens, status computado) + **RASCUNHA** a prosa como proposta + **humano/gate LIBERA** o canon. **NUNCA** um hook LLM-escreve prosa canônica sozinho (Dosu/Mintlify/Swimm todos travam; o distiller `jana:distill-module-truth` já tem o schedule **desligado** — [ADR 0291](../0291-distiller-modulo-verdade-contrato-emenda-0270-f3.md) — instinto correto). Corolário: `briefing.schema` "NUNCA presence-gate de campo auto-declarado" (L-24) segue valendo.

### 5. Resolver o conflito das 11 BRIEFING concorrentes
`memory/requisitos/<X>/BRIEFING.md` é a **única casa** do BRIEFING. Os 11 `Modules/<X>/BRIEFING.md` viram **lápide-ponteiro** (frontmatter `status: deprecated` + corpo curto apontando pro canônico). **PR separado PÓS-ratificação** desta ADR (não big-bang junto; §5 2026-07-12 tocar legado em massa acorda gates).

## Consequências

- **Positivo:** a IA (e o time) sabe, por módulo, exatamente qual arquivo responde qual pergunta — "quais arquivos?" (SUPERFICIE), "o que é/não é meu?" (SCOPE), "estado?" (BRIEFING), "requisito?" (SPEC). Reclamação no chat → alvo cirúrgico. Sem "dois BRIEFINGs mentindo".
- **Custo:** 1 PR de lápides (11 arquivos) pós-ratificação. Nenhum gate novo nasce desta ADR (usa os que existem).
- **Não muda** SCOPE (mantém enforcement) nem cria dependência/tecnologia nova.

## Rollback
Proposta em `proposals/` — se [W] recusar, `status: rejected` (append-only, não deleta). Se ratificada e o PR de lápides der ruim, reverte-se o PR de lápides sem tocar a taxonomia.

## O que esta ADR NÃO decide (fora de escopo)
- O piloto do **scorecard-de-função** (parecer concordo/não por função) — ADR própria (`proposals/2026-07-21-funcao-scorecard-opiniao-ancorada-rubrica.md`, Fase C — ainda não criada).
- Automação por hook do BRIEFING — só a **disciplina** (§4) é decidida aqui; o mecanismo é follow-up.
