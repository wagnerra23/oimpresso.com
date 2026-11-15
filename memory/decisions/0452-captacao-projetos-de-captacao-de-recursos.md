---
slug: 0452-captacao-projetos-de-captacao-de-recursos
number: 452
title: "Captação: módulo próprio para projetos de captação de recursos (edital, lei de incentivo, fundo, doação, parceria, voluntariado)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-11-14"
module: null
quarter: 2026-Q4
tags: [captacao, uniafibro, ong, associacoes, edital, lei-de-incentivo, doacao, prestacao-de-contas, multi-tenant]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0093-multi-tenant-isolation-tier-0
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
  - 0451-associacoes-rede-uniafibro-area-de-membros
pii: false
review_triggers:
  - "a entidade precisar emitir recibo de doação dedutível do imposto de renda"
  - "o aporte recebido precisar virar título no Financeiro (conciliação bancária do projeto)"
  - "o voluntário precisar de cadastro próprio, com termo de adesão (Lei 9.608/1998)"
  - "um órgão exigir envio da prestação de contas pelo sistema dele (Salic, SICONV/Transferegov)"
---

# ADR 0452 · Captação: projetos de captação de recursos

> **Status `proposto`.** Decisões confirmadas por [W] em 2026-11-14: proponente gravado como nome e
> CNPJ no próprio projeto; voluntariado só como aporte de horas, sem cadastro da pessoa; ADR e
> esqueleto no mesmo PR. O merge desta ADR é o ato de ratificação.

## Contexto

A Uniafibro-SC e as associações-membro dela (AGFESC em Criciúma, AFIBROMAR em Maravilha) vivem de
projeto: Lei Rouanet, FIA (Fundo da Infância e Adolescência), FUMPROAD (fundo estadual de SC para a
pessoa com deficiência), editais municipais e estaduais de saúde, doação de empresa e de pessoa,
parceria privada e trabalho voluntário. Cada projeto tem proponente, fontes, valor pedido e aprovado,
período de execução e, no fim, prestação de contas com comprovante de cada despesa.

A [ADR 0451](0451-associacoes-rede-uniafibro-area-de-membros.md) (cadastro da rede) deixou mensalidade
e repasse de dinheiro fora da decisão. Captação também é dinheiro, mas com outro ciclo (projeto →
fonte → aporte → despesa → prestação de contas) e serve qualquer ONG, associação ou instituto, não
só redes de associações.

Em 2026-11-14 (`origin/main` `20040dde34`) não existe nenhuma ADR, task, commit ou tabela de captação
no repo (`decisions-search`, `tasks-list`, `git log --all --grep=captac` e `git grep -i captac`
em `memory/` e `Modules/`: zero).

**Restrição medida:** Lei Rouanet, FIA e FUMPROAD exigem proponente com CNPJ. A Uniafibro ainda não
tem CNPJ (registro em cartório em andamento, ADR 0451); hoje quem pode inscrever é a associação-membro.

## Decisão

1. **D1 — Módulo novo `Modules/Captacao`, liberado pelo pacote `captacao_module`** no superadmin e
   pela permissão `captacao.access`; `captacao.editar` cadastra e altera. Empresa sem o pacote não vê
   nada. O nome é genérico de propósito: a Uniafibro é a primeira empresa a usar.
2. **D2 — Tier 0.** Toda tabela tem `business_id` NOT NULL, indexado, com FK para `business` e escopo
   global no Model (ADR 0093). Para a Uniafibro é a mesma empresa das Associações (0451 D1).
3. **D3 — Proponente é dado do projeto:** nome e CNPJ em texto no próprio projeto. Sem FK para
   `assoc_associacoes`: Captação não depende do módulo Associações e serve entidade que não é rede.
4. **D4 — Dados.**
   - `captacao_fontes`: catálogo de fontes da empresa — tipo (`edital`, `lei_incentivo`, `fundo`,
     `doacao_pj`, `doacao_pf`, `parceria`, `voluntariado`), nome, órgão, número do edital e prazo de
     inscrição.
   - `captacao_projetos`: título, objetivo, proponente (D3), situação, valor solicitado e aprovado,
     período de execução e prazo da prestação de contas.
   - `captacao_aportes`: cada recurso que entra num projeto, ligado à fonte. Natureza `financeiro`,
     `bens`, `servicos` ou `horas_voluntarias` (com as horas e o valor estimado). O doador, quando
     houver, é um `contact` do núcleo. Aporte não se apaga: sai da lista com motivo.
   - `captacao_comprovantes`: despesa executada no projeto — descrição, rubrica, valor, data e arquivo
     guardado pelo `Modules/Arquivos`. Comprovante não se apaga: sai da lista com motivo, porque é
     prestação de contas.
5. **D5 — Situação do projeto** por lista fechada com a data da última mudança: `ideia` → `elaboracao`
   → `inscrito` → `aprovado` ou `reprovado` → `captando` → `execucao` → `prestacao_contas` →
   `encerrado`. Não usa o FSM canônico (ADR 0143), que serve Vendas e OS.
6. **D6 — Prazo da prestação de contas.** O projeto em `execucao` ou `prestacao_contas` com prazo
   vencendo aparece em alerta; vencido, aparece como atrasado. O sistema avisa; não bloqueia nada.
7. **D7 — Financeiro fica de fora nesta decisão.** Aporte não vira título e comprovante não vira
   despesa no `Modules/Financeiro`. A integração é onda própria, opcional e atrás de flag.
8. **D8 — Dado pessoal.** Doador pessoa física entra só como contato do núcleo (nome e contato), sem
   CPF próprio do módulo e sem dado de saúde. Voluntário não tem cadastro: o aporte guarda horas e
   descrição. Nenhum dado pessoal ou valor de doação em log, commit ou PR.

## Fora desta decisão

- Recibo de doação dedutível e qualquer emissão fiscal.
- Cadastro de voluntário como pessoa, com termo de adesão.
- Envio de prestação de contas para sistemas do governo (Salic, Transferegov).
- Mensalidade e repasse entre associações (já fora pela ADR 0451).
- Ligar projeto à associação-membro por FK (D3).

## Ondas

1. Esta ADR **e** o esqueleto: módulo, migrations das quatro tabelas, entidades com escopo, regra de
   acesso, teste de isolamento entre empresas (98 × 99), SCOPE, SPEC e BRIEFING. [W] pediu num PR só.
2. Fontes e projetos: cadastro, lista e mudança de situação.
3. Aportes por projeto, com o total captado contra o aprovado.
4. Comprovantes e relatório de prestação de contas por projeto, com o alerta de prazo (D6).
5. Integração com o Financeiro, opcional, atrás de flag (D7).

## Alternativas consideradas

- **Pôr captação dentro do `Modules/Associacoes`.** Acopla dinheiro de projeto ao cadastro da rede,
  que a 0451 deixou de fora de propósito, e não serve ONG que não é rede.
- **Usar o `Modules/Financeiro` direto (título por doação).** Não guarda projeto, fonte, rubrica nem
  prazo de prestação de contas; o ciclo do projeto se perderia em lançamentos soltos.
- **Proponente por FK para a associação-membro.** Recusado por [W] em 2026-11-14 (D3).
- **Cadastro de voluntário já no esqueleto.** Recusado por [W] em 2026-11-14 (D8): a carga de LGPD não
  se paga antes de alguém precisar do termo de adesão.

## Consequências

- Mais um módulo com pacote próprio; empresas sem o pacote não veem nada.
- O módulo guarda valores de projeto e de doação. Qualquer cálculo de total (onda 3 em diante) segue a
  regra mestre de valor do projeto: dupla prova e antes→depois antes de ir para produção.
- A Uniafibro precisa de empresa no superadmin e do pacote ligado antes da onda 2 ir para produção.
