# ADR UI-0016 (_DesignSystem) · Design contextualizado por persona como processo canon

- **Status**: accepted
- **Data**: 2026-05-27
- **Decisores**: Wagner, Claude
- **Categoria**: ui
- **Related**: UI-0013 (Constituição UI v2), UI-0015 (Cowork default), ADR 0094 (Constituição v2), ADR 0105 (cliente-como-sinal), ADR 0109 (Claude Design plugin)

## Contexto

Até 2026-05-27 o design de tela no oimpresso operava em **improviso contextual**: Wagner descrevia tela + objetivo + restrições; Claude/agentes inferiam persona implícita do treinamento + Constituição UI v2. Resultado: 70% certeiro, 30% precisa refinar várias iterações.

Reflexão Wagner 2026-05-27:

> "ramo de atividade e a função dele dentro da empresa e o tamanho da empresa e provavelgosto do usuário mudam como a interface é formada. (...) o futuro é conhecer o cliente"

Diagnóstico: design genérico vira commodity (Bling/Tiny/Omie). Diferenciação competitiva do oimpresso depende de **adaptar produto ao papel + ramo + tamanho** sem cliente perceber explicitamente. Mas isso só é possível se temos **persona estruturada como ativo do projeto** — não só sensibilidade do designer.

## Decisão

Adotar **design contextualizado por persona** como processo canônico do oimpresso, com 3 ativos formais:

### 1. Persona library (`memory/clientes/<cliente-real>/personas/*.yml`)

Persona NÃO é abstração — é encarnação de funcionário em cliente real pagante. Estrutura:

- 1 cliente real → 1 perfil empresa (`perfil.yml`)
- 1 cliente real → N personas (1 por papel relevante: dono, gerente, operador, etc)
- Cada persona = 10 atributos canon (papel, demografia, ambiente, modelo mental, frequência uso, JTBD principal, fricção temida, métrica de sucesso, etc)
- Linka pro cliente real (`business_id`, ARR, plano contratado, último discovery)

Princípio ADR 0105 (cliente-como-sinal): só persona com **cliente paga + reportou** entra canon. Persona hipotética vira `_proposta_*.yml` (não canon).

### 2. Framework 15 dimensões com ponderação por persona (`framework-15-dimensoes.md`)

Score 0-100 em 15 dimensões UX (Density · Discoverability · Speed-to-task · Error recovery · Cognitive load · Aesthetic-usability · Affordance · Brand confidence · Mobile fit · WCAG A11y · i18n PT-BR · Performance perceived · Information hierarchy · Microcopy · Internal consistency).

Cada persona tem peso 1-3× por dimensão (Larissa pesa Speed-to-task ×3, Daniela pesa Information hierarchy ×3). Score ponderado total = decisão por persona.

### 3. Skills orquestradoras

- **`design-deep-analysis`** (Tier B) — entry point pra refinar tela. Carrega persona + roda design:* skills (critique/system/ux-copy/a11y) em paralelo + devolve 3 alternativas A/B/C com diff de código + métrica antes/depois.
- **`cliente-discovery`** (Tier C) — quando Wagner visita cliente em campo OU faz call. Exibe script canon Mom Test (Rob Fitzpatrick) + JTBD (Clayton Christensen) + day-in-the-life shadowing (IDEO). Captura raw em `memory/clientes/<cliente>/discovery-YYYY-MM-DD.md` e gera draft de persona YAML.

## Consequências

**Positivas:**

- Cada nova tela analisada vira processo de 5-8 min com profundidade real (vs ~30 min de improviso atual).
- Time MCP (Felipe/Maiara/Eliana/Luiz) usa o mesmo padrão → consistência entre devs.
- Persona = ativo do projeto, propagado via git → MCP webhook. Sobrevive trocas de pessoa, sessões.
- Adaptação subliminal do produto possível (defaults inteligentes por papel/ramo/tamanho — não "feature persona" exposta).
- Discovery raw em `memory/clientes/<cliente>/discovery-*.md` cria histórico de aprendizado (append-only).

**Negativas:**

- Setup inicial ~1.5h (este PR).
- Curadoria — persona desatualizada vira ruído. Disciplina: rebuild com cliente real a cada 6 meses ou quando feedback diverge muito.
- Risco overengineering — não construir "fluxo por persona" no código. Persona é input pra decisão, não branch de produto.

## Limites éticos + LGPD

- Persona contém info de funcionário identificável → tratar como PII canon ([ADR 0093 multi-tenant Tier 0](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md))
- Consentimento explícito do cliente pra documentar persona pública no canon
- Retention: persona viva enquanto cliente é ativo + 12 meses pós-churn (auditoria)
- NUNCA exibir "Seu perfil de uso" pro cliente — persona é interna ao design system

## Alternativas consideradas

### A) Manter improviso atual

Rejeitado — Wagner explicitamente pediu sistematização ("estais fazendo o padrão do site inteiro"). Improviso não escala pra time MCP.

### B) Personas abstratas globais (Larissa, Daniela como tipos)

Rejeitado — ADR 0105 (cliente-como-sinal). Persona sem cliente real vira ficção, deriva, perde valor.

### C) Comprar persona library de fora (NN/g, Forrester)

Rejeitado — caro, generalista, não cobre PME brasileira oficina/vestuário/etc. Vantagem competitiva está em personas próprias.

## Implementação

PR `feat/design-deep-framework-canon` 2026-05-27:

1. ADR canon (este arquivo)
2. `framework-15-dimensoes.md` + tabela ponderação
3. `RUNBOOK-design-deep.md` + `RUNBOOK-cliente-discovery.md`
4. 2 perfis empresa + 4 personas iniciais:
   - Rota Livre/Larissa (dona-balconista vestuário)
   - Martinho Caçambas/Jair (dono — decisor estratégico)
   - Martinho Caçambas/Daniela (gerente operacional)
   - Martinho Caçambas/Kamila (admin/financeiro — esposa do Jair)
5. 2 skills (`design-deep-analysis`, `cliente-discovery`)

## Errata 2026-05-27 pós-commit inicial

- "Martinho" é nome da EMPRESA (Martinho Caçambas / Martinho Transportes), não pessoa.
- **Jair** é o nome real do dono.
- **Kamila** é esposa do Jair + administrativo/financeiro/NF-e/cobrança PJ frota.
- Persona `martinho.yml` original (criada como "dono") foi corrigida e renomeada → `jair.yml`.
- Persona `kamila.yml` adicionada como canon.
