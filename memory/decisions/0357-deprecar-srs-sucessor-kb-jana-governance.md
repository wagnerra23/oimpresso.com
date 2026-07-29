---
slug: 0357-deprecar-srs-sucessor-kb-jana-governance
number: 357
title: "Deprecar Modules/SRS — sucessores KB (acervo) + Jana (chat) + Governance (validação); o DEPRECATION-PLAN de maio revalidado em julho"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-29"
module: memcofre
supersedes: []
related: ["0080-trust-tiers-operacional-audit-findings", "0053-mcp-server-governanca-como-produto", "0061-conhecimento-canonico-git-mcp-zero-automem", "0088-module-rename-php-only", "0092-tabela-rename-copiloto-para-jana", "0093-multi-tenant-isolation-tier-0", "0094-constituicao-v2-7-camadas-8-principios", "0121-oimpresso-modular-especializado-por-vertical", "0150-kb-unificado-grafo-conhecimento-modulo-ia-central"]
quarter: 2026-Q3
---

# ADR 0357 — Deprecar Modules/SRS

## Contexto

`Modules/SRS` (ex-MemCofre) está em estado **zumbi declarado** desde maio de 2026, e o
[DEPRECATION-PLAN](../requisitos/SRS/DEPRECATION-PLAN.md) de 2026-05-17 já mapeou as 6 etapas da saída.
O plano nunca saiu do papel: a etapa E1 — esta ADR — ficou **2,5 meses sem execução**.

O `BRIEFING.md` do módulo diz textualmente: *"legado uso interno raro (backoffice Wagner); deprecação
PLANEJADA (DEPRECATION-PLAN 2026-05-17, Caminho 1 aprovado) mas NÃO executada — módulo 100% presente e
servindo em prod. Sucessor prático: MCP server canon."*

### O que foi reverificado em 2026-07-29 (o plano é de maio; o mundo andou)

**Segue valendo, medido hoje:**

- As **7 tabelas `docs_*`** e as **7 entidades `Doc*`** continuam intactas — nada migrou.
- O destino `kb_sources` **não existe** — a migração de dados não começou.
- As **6 telas** (`/srs`, `/memoria`, `/memoria/file`, `/inbox`, `/ingest`, `/chat`, `/modulos/{x}`)
  continuam roteadas e servindo em produção, cada uma com charter.
- O sucessor primário **se fortaleceu**: o `KbBridgeFromMcpJob` roda a cada 15 min em prod (biz=1), e
  `/copiloto/admin/memoria` **já foi absorvido pelo KB** — redirects 301 em
  [`Modules/Jana/Http/routes.php`](../../Modules/Jana/Http/routes.php) e `MemoriaController` movido pra
  `Modules\KB`. Existe precedente de absorção que funcionou, e é o molde a repetir.

**Mudou desde maio — e reforça a decisão:**

1. **O risco Tier 0 nº 3 do plano evaporou, e virou argumento a favor.** O cron
   `memcofre:sync-memories` (daily 23:00) foi **desativado em 2026-06-07** na auditoria de conflitos de
   memória. O comentário em [`app/Console/Kernel.php`](../../app/Console/Kernel.php) registra o motivo:
   *"foi o MECANISMO que vazou credenciais em claro pro git e ressuscitava o legado a cada noite. Viola
   ADR 0061"*. O principal job agendado do módulo já foi morto **por ser nocivo** — o plano o tratava
   como bloqueador de E5, e ele já não existe.
2. **A nota do módulo subiu de 58 para 76** no `module-grades-baseline.json`. Isso **não é sinal de
   vida**: o próprio plano já observava que *"atividade alta é de governance/Pest, não feature"*. Um
   módulo zumbi cuja nota sobe por saturação de teste é o caso em que a régua mede higiene, não
   utilidade — registrado aqui pra que a nota não seja usada como contra-argumento.

### Duas correções ao plano de maio

- **A ADR 0168 que o plano reservava foi ocupada** por outro assunto (Protocolo Wagner). Esta decisão
  assume o número **0357**; qualquer menção a "ADR 0168 de deprecação do SRS" no plano aponta pra cá.
- **O plano previa `supersedes: [0080]` e isto está errado.** O `SCOPE.md` do SRS declara
  `charter_adr: 0080`, mas a 0080 é *"Trust Tiers operacional + Architecture & Scope + audit findings"* —
  uma decisão ampla de governança, não o charter deste módulo. Deprecar um módulo **não** supersede
  trust tiers. Esta ADR não supersede nada; a 0080 entra como `related`. O ponteiro errado no
  `SCOPE.md` fica registrado como achado, a corrigir em E4.

## Decisão

**Deprecar `Modules/SRS`**, seguindo o roadmap de 6 etapas do DEPRECATION-PLAN, com os sucessores
canônicos já mapeados:

| Capacidade do SRS | Sucessor |
|---|---|
| Acervo de documentos + busca | `Modules/KB` — dono de `kb_nodes`, alimentado por `mcp_memory_documents` |
| Chat assistido sobre o corpus | `Modules/Jana` — chat IA canônico |
| Validação / drift / auditoria | `Modules/Governance` + `mcp_audit_log` (append-only) |

**Esta ADR executa apenas a etapa E1** — registrar a decisão e o ciclo de vida. Nenhuma linha de código
de comportamento muda neste PR. As etapas E2 a E6 (deprecação em PHPDoc, migração de dados, refactor de
namespace, remoção e registro final) seguem gated individualmente por [W], na ordem do plano.

### O que esta ADR NÃO decide

- **O destino final de `docs_requirements` e `docs_links`.** O plano os marca `ORPHAN → Wagner decide`,
  com a hipótese de descontinuar (a fonte canônica de US é `memory/requisitos/<X>/SPEC.md` via MCP).
  Fica aberto até E3.
- **A fusão dos acervos.** O KB tem persona medida "Wagner / governança (biz=1)", com 99,8% do acervo em
  documento de governança. Absorver conteúdo de outra persona — o `essentials_kb`, que é operação do
  cliente — é **decisão de produto separada** e não entra nesta deprecação.
- **Remoção de código.** `git rm` só em E5, após 30 dias estáveis pós-E4.

## Consequências

**Positivas.** Uma duplicação de tela morre (o `/memoria` do SRS versus o `/kb`); o acervo passa a ter um
dono só; três tabelas repo-wide com exceção de multi-tenant saem do mapa de risco; e a superfície de
manutenção cai em 8 controllers, 6 services, 7 entities e 9 comandos.

**Negativas e riscos assumidos.** O módulo **serve em produção hoje** — deprecar é mexer em coisa viva,
não em código morto. Os riscos Tier 0 que **continuam de pé**:

- **PII em `docs_chat_messages.content`** (LGPD Art. 16): linhas legadas podem ter CPF/e-mail sem
  redação. A migração exige re-rodar o `PiiRedactor` antes de arquivar ou mover.
- **Multi-tenant em 7 entidades**, com 3 tabelas repo-wide por exceção. Exige Pest cross-tenant biz=1
  versus biz=99 **antes e depois** da migração ([ADR 0093](0093-multi-tenant-isolation-tier-0.md)).
- **Índice FULLTEXT** em `mcp_memory_documents`: recriação custosa se a migração de `docs_evidences`
  for feita sem cuidado.

**Reversibilidade.** Até E4 inclusive, reversível por revert de PR. A partir de E5 (`git rm` + drop de
tabela), a reversão depende dos dumps preservados em `governance/archive/` — por isso o plano exige 30
dias de observação entre E4 e E5.

## Alternativas consideradas

**Manter e investir** — descartada em maio pelo próprio BRIEFING (*"❌ Não investir em features novas"*),
e enfraquecida desde então: o sucessor amadureceu e o cron do módulo foi desligado por ser nocivo.

**Deixar apodrecer sem decisão registrada** — é o estado atual, e é o pior dos três: o módulo continua
servindo, continua sendo mantido por saturação de teste, e a nota sobe dando impressão de saúde.

**Remover de uma vez** — descartada: sete tabelas, PII e seis telas com charter em produção não saem sem
migração faseada e janela de observação.
