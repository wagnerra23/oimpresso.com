---
slug: 0409-zero-baseline-de-tolerancia-conformidade-absoluta
number: 409
title: "Zero baseline de tolerância — conformidade absoluta e dívida acordada por toque"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-09-21"
module: governance
tags: [governanca, baseline, tier-0, multi-tenant, qualidade, visual, ci]
supersedes: []
supersedes_partially:
  - 0208-larastan-baseline-ratchet
related:
  - 0093-multi-tenant-isolation-tier-0
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0314-required-so-tier-0
  - 0404-ultimo-importado-e-autoridade-do-espelho
  - 0406-o-que-ultimo-importado-decide-emenda-0404
pii: false
---

# ADR 0409 — Zero baseline de tolerância: conformidade absoluta e dívida acordada por toque

## Contexto

A auditoria de 2026-09-21 mediu que várias lanes de qualidade respondiam à pergunta
"piorou em relação ao arquivo congelado?", mas não à pergunta "está correto?". O estado
observado incluiu **6.677 ocorrências** toleradas pelo baseline do PHPStan, **7.594** pelo
UI lint, **2.339** pelo ESLint, **440** pelo Stylelint, **333** pelo TypeScript, **246**
pela acessibilidade e **2.450** pelo layout. O gate multi-tenant mantinha **66 Models** em
`grandfathered` e uma exceção arquitetural explícita.

Essa diferença é material. Um check verde podia significar apenas que o erro já estava
catalogado. No caso multi-tenant, o comentário e o JSON afirmavam desde 2026-07-31 que a
dívida sairia quando o arquivo fosse tocado, mas o teste unia `grandfathered` e `allowlist`
numa única isenção. Portanto, alterar um Model grandfathered sem aplicar escopo continuava
verde. A promessa documental não tinha dente.

Na importação do protótipo do Bubble, snapshots congelados também não provam paridade com o
protótipo vivo. Eles provam paridade com a captura escolhida. Se a captura estiver velha,
incompleta ou absorver uma regressão, a comparação pode ficar verde enquanto protótipo e
aplicação divergem.

Há três objetos diferentes que vinham recebendo o mesmo nome:

1. **tolerância de erro conhecido** — lista que permite a falha continuar;
2. **contrato** — inventário deliberado, com semântica própria, como rotas ou componentes
   que obrigatoriamente devem existir;
3. **evidência** — captura, relatório ou medição vinculada a uma execução e a um SHA.

Só o primeiro objeto mascara conformidade.

## Decisão

**Baseline de tolerância não é estado aceitável de chegada e não concede conformidade.**
Toda lane deve migrar para um critério absoluto: a unidade examinada passa ou falha pela
regra atual, sem crédito por a mesma falha já existir num arquivo congelado.

Os arquivos de tolerância existentes são dívida transitória, com remoção programada. Eles
não podem crescer. Enquanto ainda existirem, alterar a unidade acorda a dívida e exige a
cura no mesmo PR. Para código, a unidade mínima é o arquivo; para uma regra com fronteira
menor demonstrável, o gate pode usar símbolo ou trecho desde que o detector prove essa
fronteira e tenha bite-test.

### Tier 0 multi-tenant

Aplicação imediata desta decisão:

- Model novo sem escopo automático reprova;
- Model existente que perde o escopo reprova;
- Model `grandfathered` alterado e ainda sem escopo reprova;
- `grandfathered` só diminui e chega a zero;
- exceção legítima não usa tolerância. Ela fica em contrato nominal separado, com razão,
  ADR de módulo quando exigida pela ADR 0093 e teste que prove a natureza global.

A lane required entrega ao teste a lista de Models modificados no diff. O teste cruza essa
lista com os infratores reais e com a dívida transitória. Assim, editar texto no JSON ou
manter o caminho em `grandfathered` não silencia a violação acordada.

### Análise estática e lints

PHPStan, TypeScript, ESLint, Stylelint, UI lint, acessibilidade e layout seguem a mesma
transição:

1. arquivo novo ou tocado precisa ficar sem violação da regra aplicável;
2. dívida não tocada continua visível em relatório de redução, sem receber selo de
   conformidade;
3. cada saneamento remove entradas; nenhuma absorção adiciona dívida;
4. ao chegar a zero, o arquivo de tolerância é apagado e o gate passa a operar somente
   pelo critério absoluto.

Mudar regra ou corrigir falso-positivo exige corrigir o detector e seu bite-test. Regenerar
uma lista para acomodar a saída não é correção.

### Protótipo Bubble até produção

A prova de paridade visual passa a comparar, na mesma execução:

1. fonte canônica do protótipo importado;
2. renderização determinística dessa fonte nas rotas e estados declarados;
3. aplicação candidata no mesmo viewport, tema, dados e estado;
4. diferença calculada e critérios funcionais da tela;
5. smoke de produção após deploy para disponibilidade, assets, rotas e erros de console.

Snapshot fica como evidência vinculada ao SHA e à execução. Ele não decide sozinho se a
aplicação está fiel ao protótipo e não pode ser atualizado no mesmo PR apenas para fazer a
comparação passar. Quando a intenção visual muda, o protótipo canônico muda primeiro e a
execução registra a nova relação entre fonte, aplicação e evidência.

### Nomes e garantias

Artefato que representa intenção estável recebe nome de **contrato**, **manifesto** ou
**inventário**, conforme a função. Artefato produzido por uma execução recebe nome de
**evidência** ou **resultado** e carrega SHA, fonte, data e versão do instrumento. O termo
`baseline` fica reservado durante a migração aos arquivos de tolerância que ainda serão
eliminados; criar um novo exige nova decisão de [W].

Um gate só pode declarar conformidade quando:

- executou o detector sobre o escopo declarado;
- teve controle positivo contra verde por não-execução;
- teve bite-test que prova que a falha real reprova;
- não descontou violações por constarem de lista histórica;
- publicou a evidência necessária para reproduzir o resultado.

## Plano de migração

1. **Tier 0:** acordar dívida por toque no multi-tenant e reduzir os 66 itens até zero.
2. **Código tocado:** aplicar critério absoluto por arquivo nos analisadores e lints.
3. **Visual:** substituir autoridade de snapshot pela comparação protótipo vivo versus
   aplicação na mesma execução; manter capturas apenas como evidência.
4. **Dívida remanescente:** abrir lotes pequenos de saneamento, sempre reduzindo contagem.
5. **Remoção:** apagar arquivos e caminhos de absorção quando cada contador chegar a zero.
6. **Contratos reais:** renomear inventários que não sejam tolerância para evitar que sua
   função seja confundida com perdão de erro.

## Consequências

- Check verde volta a significar conformidade no escopo que ele declarou.
- Arquivo legado tocado pode exigir correção adicional no mesmo PR; esse custo é a dívida
  que antes ficava invisível.
- PRs de saneamento permanecem pequenos e verificáveis, sem backfill cego em massa.
- A exceção arquitetural fica mais cara e explícita, especialmente no Tier 0.
- Capturas visuais continuam úteis para auditoria, mas deixam de ser autoridade autônoma.
- A ADR 0208 permanece válida quanto à adoção do Larastan e ao aumento de rigor; sua escolha
  de baseline como mecanismo permanente de aprovação fica parcialmente substituída.

## Evidência inicial

Esta ADR nasceu junto da primeira correção mecânica: a lane
`No hardcode business_id (Tier 0)` passou a fornecer `MTS_CHANGED_MODELS`, e
`MultiTenantScopeArchitectureTest.php` passou a reprovar Model grandfathered tocado que
continue infrator. O bite-test da lista de tocados cobre normalização, deduplicação e rejeição
de paths fora da fronteira de Models de módulo.
