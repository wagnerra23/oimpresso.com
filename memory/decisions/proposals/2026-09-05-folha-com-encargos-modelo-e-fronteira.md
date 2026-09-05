---
proposal_id: folha-com-encargos-modelo-e-fronteira
slug: 2026-09-05-folha-com-encargos-modelo-e-fronteira
title: "Folha com encargos — o modelo de verba, a fronteira do que não declaramos, e como construir"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
kind: decision
module: Essentials
created: '2026-09-05'
proposed_by: claude-code
decided_by:
  - W
decided_at: '2026-09-05'
quarter: 2026-Q3
supersedes: []
related:
  - 0014-essentials-pontowr2-integracao
  - 0093-multi-tenant-isolation-tier-0
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
  - 0383-ponto-interno-nao-coleta-biometria
---

# Folha com encargos — modelo de verba, fronteira e caminho de construção

> **Origem:** emenda [W] de 2026-09-05 ao `PEDIDO-CL-hrm.md` — **D2: folha COMPLETA, com
> encargos (INSS/IRRF/FGTS/13º/férias)**. Essa emenda **inverteu** a proposta que estava na
> tabela original do mesmo pedido (*"rotular na UI como folha gerencial (…) sem prometer cálculo
> de encargo"*). Quem ler só a tabela lê a decisão revogada; a emenda é que vale.
>
> Esta ADR é a **mãe** — fecha as três decisões cujo erro obriga a reescrever o resto. Ela não
> constrói motor, não cria tabela e não escreve migration: fixa o **contrato** que o motor terá
> de honrar.

---

## 1. Contexto — o que existe hoje, medido

### 1.1 A folha atual não é uma folha — é planilha com persistência

| Fato | Recibo |
|---|---|
| `PayrollController` tem **1.188 linhas** | `wc -l Modules/Essentials/Http/Controllers/PayrollController.php` |
| **Zero** menção a INSS, IRRF, FGTS ou eSocial em todo o módulo | `git grep -i -E` pelos quatro termos em `Modules/Essentials` → **rc=1** (zero ocorrências). Controle positivo com `payroll` no mesmo escopo → rc=0 com hits, logo o grep enxerga a árvore |
| As verbas vivem em **dois blobs `text` nullable** na tabela `transactions` | `essentials_allowances` e `essentials_deductions`, criados em `2019_06_28_134217_add_payroll_columns_to_transactions_table.php` |

O formato dentro dos blobs é o ponto que decide esta ADR. Não é um objeto por verba — são
**quatro arrays paralelos indexados por posição**, um para cada atributo:

- `allowances.allowance_names[]` — o rótulo, texto livre
- `allowances.allowance_amounts[]` — o valor
- `allowances.allowance_types[]` — `fixed` ou `percent`
- `allowances.allowance_percents[]` — o percentual, quando for o caso

<sub>Ver `PayrollController.php:246-249` (comissão de venda), `:278-281` (allowance genérica) e
`:283-286` (o espelho para deductions).</sub>

Uma verba é, portanto, uma **tupla posicional sem identidade**: não tem código, não tem
natureza, não sabe se incide em INSS, IRRF ou FGTS, e não sabe em que competência aquela regra
valia. Somar `allowance_amounts` dá o bruto — e é só isso que o código faz hoje. Não há como
perguntar "qual a base de INSS?", porque a informação necessária para responder nunca foi
gravada.

O `ImpostosController` do Financeiro já registrava o diagnóstico no próprio docblock, com todas
as letras: *"o sistema não tem folha — honesto"*.

### 1.2 O que JÁ está pronto e no formato certo

O lado do Ponto não tem esse problema. `ponto_apuracao_dia`
(`2026_04_18_000006_create_ponto_apuracao_dia_table.php`) já segrega, em colunas tipadas:

`he_diurna_minutos` · `he_noturna_minutos` · `adicional_noturno_minutos` ·
`dsr_repercussao_minutos` · `falta_minutos` · `atraso_minutos` ·
`saida_antecipada_minutos` · `banco_horas_credito_minutos` · `banco_horas_debito_minutos`

e `ponto_banco_horas.multiplicador` é `decimal(4,2) default 1.00`
(`2026_04_18_000007`). **O Ponto já entrega o insumo no formato certo; é a folha que não sabe
recebê-lo.** A emenda [W] do mesmo dia reforça: pela **D1**, a presença web do Essentials cede
lugar e o `Modules/Ponto` vira **dono único da jornada**.

O Financeiro também já tem o receptor: `ImpostosController` lança título a pagar **idempotente
por `metadata->guia`**, com contrato e guard cobrindo (`ImpostosContractTest:197`,
`ImpostosGuardTest:142` — *"re-POST não duplica"*), e já reconhece o vocabulário
`FGTS`/`DCTF`/`INSS`/`DAS`. Encargo apurado tem para onde ir sem tabela nova.

### 1.3 Esta ADR EXECUTA uma direção já registrada — não a inaugura

A [ADR 0014](../0014-essentials-pontowr2-integracao.md) (2026-04-21, hoje `reference` ·
`arquivado`) já fixou a divisão de responsabilidade: *"Gerar folha de pagamento — Essentials
(Payroll), alimentado por PontoWR2"*. Ou seja, **quem** gera a folha e **de onde** vem o insumo
estão decididos há quase cinco meses.

O que a 0014 **não** decidiu — e é o que esta ADR decide — é o **modelo de verba**, a
**fronteira** do que declaramos, e o **caminho de construção**. Registro isso com precisão
porque a formulação preguiçosa ("a 0014 só menciona folha de passagem") seria verdadeira ao pé
da letra e enganosa no efeito: subestimaria uma direção canônica que esta ADR está seguindo, e
convidaria a próxima sessão a re-desenhar o que já estava desenhado.

Fora a 0014, o tema não tem dona. Varredura no corpo de **todas** as ADRs canon (`git grep -l -i
-E` por "folha de pagamento", "encargos trabalhistas", "eSocial", "holerite" e "contracheque" em
`memory/decisions/*.md`, com controle positivo passando antes) devolveu mais dois arquivos,
ambos `reference` · `arquivado` e ambos sem decidir nada:
[0015](../0015-connector-api-gateway.md) (*"integração com eSocial **pode** usar o mesmo
gateway"* — hipótese) e [0016](../0016-plano-otimizacao-e-roadmap.md) (checkbox
**não-marcado**: `[ ] eSocial (eventos S-1300, S-2200)`).

No `Essentials/SPEC.md` as US vão de `US-ESS-001` a `US-ESS-011` (Todo, Leave, DocumentShare,
Reminder, install, multi-tenant, Metas) — **zero** de payroll.

### 1.4 A fronteira que o Ponto já declarou — e que esta ADR respeita

`memory/requisitos/Ponto/SPEC.md:33` traz, como Non-Goal explícito:

> `❌ Folha de pagamento (handoff via eSocial S-1200/S-2299 — out of scope)`

Isso **não conflita** com esta ADR: o Ponto declara que folha não é escopo *dele*, e nomeia o
eSocial como o handoff previsto. A decisão §3 diz que a folha também **não** declara ao eSocial —
a cadeia inteira Ponto → Folha para antes da transmissão, deliberadamente e nos dois documentos.
Se um dia entrar, entra por ADR sucessora que emenda os dois.

### 1.5 Proveniência desta ADR, e a convergência que ela ganhou

O estado da arte que fundamenta esta decisão
(`memory/sessions/2026-09-05-arte-folha-encargos-br.md`) ficou **untracked** no worktree de
outra sessão e está sendo aterrissado no
[#6877](https://github.com/wagnerra23/oimpresso.com/pull/6877). Quando esta ADR começou a ser
escrita ele não estava em `origin/main` nem em ref algum, então **cada fato de §1 foi medido do
zero, de forma independente** — e os números convergiram: 1.188 linhas, dois blobs de arrays
paralelos, zero ocorrências de INSS/IRRF/FGTS/eSocial, *"calcular sim, operar não"*.

Duas medições separadas chegando ao mesmo resultado é evidência mais forte que uma; por isso o
registro fica. As seções 2 a 5 abaixo **incorporam** o que aquele documento mediu e esta sessão
não tinha — em especial a Lei 15.270/2025 (§2.3), o contrato de entrada do Ponto (§3.4) e o
buraco vivo de valor (§5.5).

**Herdado, não re-medido aqui:** as estimativas de esforço (motor mínimo mensalista 60-100h;
folha completa 110-180h; manutenção 40-80h/ano) e a ausência de biblioteca (§4.1) vêm daquele
documento e estão rotuladas como tal onde aparecem.

---

## 2. Decisão 1 — o modelo de verba

> **Uma verba é `rubrica × natureza × três flags de incidência independentes × vigência AAAA-MM`.
> As bases são somatórios derivados, nunca constantes. E o motor nasce reentrante.**

Esta é a única peça cujo erro obriga a reescrever todo o resto — por isso vem antes do motor.

### 2.1 Por que a unidade não é `calcularInss()`

O reflexo natural é começar por uma função de cálculo. Está errado por uma razão estrutural: a
alíquota é a parte **fácil e pública** do problema; a parte difícil é saber **sobre o quê** ela
incide. Duas verbas de mesmo valor podem ter bases completamente diferentes — o adicional
noturno integra as três bases, a diária de viagem dentro do limite legal não integra nenhuma, e
o vale-transporte descontado reduz o líquido sem tocar em base alguma.

Se a verba não carrega essa informação, nenhuma função de cálculo consegue inferi-la. É por isso
que o layout **S-1010 (Tabela de Rubricas)** do eSocial existe: ele obriga o empregador a
declarar, por rubrica, `natRubr` mais as incidências previdenciária, de IRRF e de FGTS, com
vigência `iniValid`/`fimValid` em `AAAA-MM`. Adotar essa forma agora não é antecipar o eSocial —
é usar a modelagem que o próprio regulador já provou ser a mínima suficiente, e que o mercado
inteiro teve de adotar.

### 2.2 As três flags são independentes — e é isso que mata a alternativa simples

A tentação é um enum único (`natureza: provento_tributavel`). Não funciona: as combinações
reais são esparsas e não se reduzem a uma escala ordenada. Alguns exemplos que quebram qualquer
enum:

| Verba | INSS | IRRF | FGTS |
|---|:---:|:---:|:---:|
| Salário base | ✅ | ✅ | ✅ |
| Horas extras | ✅ | ✅ | ✅ |
| Férias indenizadas | ❌ | ❌ | ❌ |
| Aviso prévio indenizado | ❌ | ❌ | ✅ |
| Salário-família | ❌ | ❌ | ❌ |
| 13º salário | ✅ (apuração própria) | ✅ (exclusiva na fonte) | ✅ |

`aviso prévio indenizado` sozinho já refuta o enum: é a combinação `❌ ❌ ✅`, que não existe em
nenhuma escala ordenada. **Três booleanos independentes por vigência**, e o problema some.

### 2.3 A tabela legal é versionada por competência — e a lei muda a FORMA, não só o número

Vigência não é um extra do modelo; é parte dele. A incidência de uma rubrica muda por lei sem que
a rubrica mude de identidade, então a vigência é linha própria (`vigencia_inicio`/`vigencia_fim`
em `AAAA-MM`). Consultar incidência é sempre *"desta rubrica, na competência X"* — nunca *"desta
rubrica"*. O mesmo vale para as tabelas de INSS e IRRF: resolvidas **pela competência do
cálculo**, jamais pela "vigente hoje". Folha que lê a tabela atual não consegue reprocessar março
em setembro — e reprocessar é rotina, não exceção.

E há um agravante que descarta de vez o desenho "constante numa tabela". **A lei muda a forma do
algoritmo.** O caso vivo é a **Lei 15.270/2025**, vigente desde 01/2026: ela não reescreveu a
tabela do IRRF — acrescentou um **redutor aplicado sobre o imposto já calculado**, com faixa de
isenção ampliada e uma faixa de transição de fórmula linear própria. Quem tinha o IRRF modelado
como "buscar alíquota e deduzir parcela" teve de **inserir um passo novo no pipeline**, não
trocar um número.

Consequência de desenho: o motor é um **pipeline de passos ordenados e extensíveis por
competência**, não uma função com constantes parametrizadas.

### 2.4 Bases são derivadas — e nunca gravadas como verdade

A base de cada encargo é o **somatório dos itens cuja vigência de rubrica marca aquela flag**,
resolvida na competência de apuração do item. Em uma linha:

> `base_inss` = soma dos `item.valor` em que `vigencia(item.rubrica, item.competencia_apuracao).incide_inss` é verdadeiro.

A base **pode** ser materializada por performance ou auditoria, mas apenas como *cache com
proveniência* — recalculável e conferido contra o somatório. Gravar base como constante é
recriar o problema do blob num formato mais bonito: no dia em que uma rubrica for corrigida, a
base fica mentindo e nada acusa.

### 2.5 Reentrância não é refactor depois — é o eixo do desenho

Retroativo é o caso normal, não a exceção: dissídio homologado em outubro com efeito desde maio,
correção de marcação após o fechamento, reprocessamento por erro de lançamento. Em todos, a
regra é a mesma e é a que quebra implementações ingênuas:

> **Paga-se na competência corrente, calcula-se mês a mês na alíquota e no teto da competência
> original.**

O eSocial nomeia essa distinção (`infoPerApur` para o período de apuração corrente,
`infoPerAnt` para períodos anteriores) precisamente porque ela não é derivável depois.

Consequência dura para o modelo: **todo item de folha carrega duas competências** —
`competencia_pagamento` e `competencia_apuracao`. Um motor que assuma que as duas são iguais
passa em todo teste de folha normal e produz valor errado no primeiro retroativo — errado para
menos ou para mais conforme o teto tenha subido, o que é indistinguível de arredondamento até
alguém conferir centavo a centavo.

Se essa coluna não nascer no modelo, o conserto posterior é: migrar a tabela de itens,
reescrever o motor, reescrever os casos-verdade e reprocessar todo o histórico. É esse o custo
que esta ADR existe para evitar.

### 2.6 Encargo patronal tem branch de regime — e o regime é do business

O modelo precisa suportar, desde o desenho, que **o mesmo cálculo produza recolhimentos
diferentes conforme o regime tributário do business**. A contribuição patronal (CPP 20% + RAT/FAP
+ terceiros) não é uniforme: nos anexos I a III e V do Simples ela já está embutida no DAS,
enquanto no **Anexo IV** é recolhida separadamente.

Isso não é detalhe de relatório — muda **o que a folha deve calcular** por tenant. Um motor que
assuma um regime único produz encargo patronal errado (a mais ou a menos) para todo business no
outro caso. O regime entra como atributo do business, resolvido por competência como tudo o mais.

### 2.7 O contrato mínimo (forma, não migration)

Quatro conceitos. Nomes e colunas exatas são do PR de implementação; o que esta ADR fixa é que
eles existam e se relacionem assim:

| Conceito | Papel | Chaves que o contrato exige |
|---|---|---|
| **Rubrica** | catálogo por business | `business_id`, código, descrição, natureza, sentido (provento/desconto/base/informativa) |
| **Vigência de rubrica** | a regra no tempo | rubrica, `vigencia_inicio`/`vigencia_fim` (`AAAA-MM`), `incide_inss`, `incide_irrf`, `incide_fgts` |
| **Competência** | o fechamento | `business_id`, `AAAA-MM`, estado |
| **Item** | a verba lançada | colaborador, rubrica, valor, **`competencia_pagamento`**, **`competencia_apuracao`**, proveniência |

`business_id` com global scope em todas, Tier 0 irrevogável
([ADR 0093](../0093-multi-tenant-isolation-tier-0.md)). Estado de competência atravessa
`ExecuteStageActionService` se e quando ganhar máquina de estados
([ADR 0143](../0143-fsm-pipeline-live-prod-marco-2026-05-12.md)) — não se muda estado por
`save()` direto.

**Proveniência é campo de primeira classe no item:** de onde veio aquele valor — apuração do
ponto (com o `ponto_apuracao_dia.id`), lançamento manual (com o autor), ou cálculo do motor
(com a versão da regra). Sem isso, conferir divergência com o contador vira arqueologia.

---

## 3. Decisão 2 — a fronteira gerencial × oficial

> **A folha CALCULA e IMPRIME. A folha NÃO DECLARA.**

### 3.1 O que fica dentro e o que fica fora

| ✅ Dentro do escopo | ❌ Fora — e não "por ora": por decisão |
|---|---|
| Cálculo de proventos e descontos por rubrica | Transmitir eventos ao eSocial (S-1010, S-1200, S-2299) |
| INSS, IRRF e FGTS **calculados** e demonstrados | Emitir guia (DARF, GPS, FGTS Digital) |
| 13º e férias (escopo da D2) | DCTFWeb |
| Holerite / recibo de pagamento | RAIS, CAGED, DIRF |
| Título a pagar no Financeiro (receptor de §1.2) | Ser a fonte oficial perante o fisco |
| Consumo do consolidado do Ponto (§3.4) | |

### 3.2 Por que a linha cai exatamente aí — é fato de plataforma, não preferência

Dá para calcular certo e emitir holerite sem tocar no eSocial: o recibo de pagamento é uma
obrigação entre empregador e empregado (a CLT, no Art. 464, condiciona a quitação do salário ao
recibo assinado), não um documento transmitido ao fisco.

Já a guia **não** tem esse caminho. SEFIP, GRF e Conectividade Social foram substituídos: o FGTS
Digital apura a guia **do que foi declarado no eSocial**, e desde 05/2026 até o FGTS de processo
trabalhista migrou para lá. INSS e IRRF seguem o mesmo desenho, via DCTFWeb. Portanto: **calcular
8% e mostrar na tela não produz recolhimento.** Isso não é uma escolha nossa a revisitar com mais
esforço de engenharia; é como a plataforma foi construída.

Daí a fronteira ser honesta e não covarde: ela não diz "ainda não chegamos lá", diz "calcular e
declarar são dois produtos, e estamos entregando o primeiro".

Um dado que dimensiona o outro lado: entrar no eSocial é **um segundo projeto do tamanho do
primeiro** — eventos de tabela (S-1000, S-1005, S-1010, S-1020, S-1030), periódicos (S-1200,
S-1210, S-1298/S-1299) e não-periódicos de vínculo (S-2200, S-2205, S-2206, S-2230, S-2299),
mais certificado A1, mais homologação em produção restrita (relógio real, não acelera com
IA-pair), mais o ciclo de **retificação**, onde mora a maior parte do esforço operacional.
Terceirizar o transporte tira o XML, a assinatura e o XSD; **não tira** o mapeamento de dados nem
a retificação.

### 3.3 O risco real não é errar a conta — é dois motores divergirem em silêncio

Este é o ponto que o charter tem de dizer em voz alta.

A partir do dia em que a folha calcula encargos, existem **dois** motores produzindo os mesmos
números: o nosso e o do contador (que é quem declara). Enquanto ninguém confere, os dois
convivem em paz aparente — e a divergência não aparece na tela, aparece na fiscalização, meses
depois, quando o custo de corrigir já multiplicou.

Pior: a divergência silenciosa é **assimétrica no engano**. Se a nossa folha mostra um líquido e
o contador declara outro, o empregado recebeu um dos dois — e a nossa tela é a que ele viu. Sem
dizer isso explicitamente na UI, **o cliente assume que declarou.**

**Mitigação obrigatória, e faz parte desta decisão:** a folha oferece **conciliação por
competência**. O valor do contador entra como dado (importação ou digitação), e a folha reporta
a diferença **por rubrica**, não só no total — porque um total que bate por compensação de dois
erros opostos é o pior resultado possível.

Regra de autoridade enquanto a fronteira for esta: **onde divergir, o contador é a fonte
oficial.** A divergência não bloqueia o pagamento; ela fica visível e datada. Uma folha que se
declarasse vencedora contra quem declara ao fisco seria exatamente o "segundo motor sem
auditoria" que este parágrafo existe para impedir.

### 3.4 O que a folha RECEBE do Ponto — contrato de entrada tem outro dono

A folha consome um **consolidado mensal por colaborador**, não as linhas diárias cruas. O
contrato de entrada (9 itens: carga prevista e trabalhada, faltas em minutos **e em dias**,
atrasos separados de falta, HE **com o multiplicador junto**, adicional noturno com a hora
reduzida já resolvida, DSR sobre variáveis, banco de horas **apenas o liquidado**, estado da
competência, e afastamentos com código e data) está detalhado no estado da arte de §1.5 e é
executado pela **ADR sucessora da 0014**, que trata a integração Ponto × Essentials.

Esta ADR **aponta e não redefine** — abrir um segundo contrato de entrada aqui criaria dois donos
da mesma regra. Três itens daquele contrato, porém, condicionam o modelo desta ADR e ficam
registrados:

- **HE é o par (minutos, multiplicador)**, nunca o minuto sozinho: 50% e 100% são rubricas
  diferentes e o percentual vem de convenção coletiva.
- **Banco de horas só entra se LIQUIDADO** (movimento de pagamento). Ler saldo em vez de
  liquidação paga o que já foi compensado.
- **Afastamento acima de 15 dias muda quem paga** (empresa → INSS). Isso não é detalhe de UI: é
  mudança de quem é o devedor, e o modelo de verba tem de expressá-la.

---

## 4. Decisão 3 — o caminho de construção

> **Calcular in-house. Terceirizar o transporte fiscal — se e quando declarar entrar em escopo.**

### 4.1 A opção "usar biblioteca" está vazia por ausência de oferta

O estado da arte mediu (2026-09-05) que **não existe pacote PHP/Laravel maduro de cálculo
trabalhista brasileiro** — o que há são projetos didáticos em Java e Python. Isso elimina a
alternativa mais barata não por análise de trade-off, mas por indisponibilidade: sobram in-house
e terceiro.

⚠️ Fato **herdado** de §1.5, não re-medido nesta sessão. Antes de o primeiro PR de motor abrir,
deve ser re-conferido — o custo de conferir é uma busca; o de errar é reimplementar do zero algo
que já existia.

### 4.2 As duas alternativas reais, e por que o híbrido vence

| Caminho | Custo perpétuo | Veredito |
|---|---|---|
| **Terceiro (folha inteira via API/BPO)** | baixo — a obrigação é do fornecedor | Viável, mas **muda o produto**: o oimpresso deixa de ter folha e passa a ter integração |
| **Calcular in-house + terceirizar o transporte** | alto e contínuo | **É o que o mercado faz**, e o único que preserva o produto |

A razão está na natureza das duas metades:

- **O cálculo é onde estão o valor e o acoplamento.** Ele precisa conhecer o consolidado do
  Ponto, o multiplicador de banco de horas, a comissão da venda, o Financeiro e o FSM.
  Terceirizar isso é exportar o dado, receber um número e não poder explicá-lo — o oposto do que
  uma folha auditável precisa ser.
- **O transporte é commodity regulada.** Leiaute muda por ato normativo, certificado digital,
  retorno assíncrono, ambiente de homologação. Nada disso diferencia ninguém, e tudo custa
  manutenção perpétua.

### 4.3 O competidor de graça define a barra — e ele responde "por que construir"

O **Módulo Web do eSocial** já calcula folha, férias e rescisão e emite guia para ME/EPP, de
graça. Isso não mata o produto; define o que ele precisa entregar acima: **integração com o
resto do ERP** — que é exatamente o que [W] apontou na D1 (*"vincular com outros módulos seria
muito melhor, hoje está tudo separado"*).

Dito sem rodeio: **folha isolada perde do governo.** Folha que sabe a jornada do Ponto, a
comissão da venda e joga a guia no Financeiro, não. É esse — e não a conta de INSS — o
diferencial que justifica construir.

### 4.4 O precedente é nosso e já está em produção

Este é exatamente o desenho que o `Modules/NfeBrasil` já usa: as regras fiscais e a montagem do
documento são nossas; a conversa com a SEFAZ é intermediada. A folha herda o mesmo formato — logo
a decisão não introduz padrão arquitetural novo no projeto, e sim adota o que já está validado.

---

## 5. Como o plano satisfaz a regra mestre de valor (Tier 0)

A regra mestre de [`memory/proibicoes.md`](../../proibicoes.md) — *CÁLCULO DE VALOR ou ESTOQUE* —
exige três coisas de toda alteração que mexa em valor. Folha é o caso mais puro dessa regra que
o projeto já teve: **todo** número que ela produz é valor, o destinatário é uma pessoa física com
direito trabalhista, e o prazo para questionar diferença com contrato vigente é de **cinco anos**.

### 5.1 Casos-verdade de fonte EXTERNA, nascendo antes do motor

**Ordem obrigatória: os casos-verdade entram no repo antes da primeira linha do motor.**

Isso não é preferência de TDD — é a única forma de não cair na lápide de 2026-06-05 (*"teste que
deriva do CÓDIGO em vez do contrato"*). Um caso-verdade escrito depois do motor tende a
codificar o que o motor faz, e aí a suíte inteira vira uma tautologia verde que não protege nada.

Fonte admissível — **sempre externa ao nosso código**: holerite real conferido por contador,
calculadora oficial, tabela publicada, ou exemplo de leiaute do próprio regulador. Fonte
**inadmissível**: o resultado do nosso motor, de qualquer versão.

Cada fixture declara, no próprio arquivo, **de onde veio o número** e **de que competência ele
é**. Sem isso, ela não é caso-verdade — é registro do que já fazíamos.

### 5.2 Dupla confirmação — dois caminhos independentes, centavo a centavo

1. **O caso-verdade externo** (§5.1) — motor contra fonte de fora.
2. **Recomputação independente** — o mesmo caso refeito por outro caminho (conferência manual
   documentada, ou planilha), batendo **centavo a centavo**, não "aproximadamente".

O "centavo a centavo" é literal e tem motivo técnico: o INSS é progressivo por faixas desde 2020,
e **cada faixa arredonda**. Somar as faixas arredondando só no fim dá resultado diferente de
arredondar faixa a faixa — divergência de centavos que um teste com tolerância engole e a
fiscalização não. A política de arredondamento é, portanto, **parte do contrato**, e tem de estar
declarada, não implícita na ordem das operações do código.

### 5.3 antes→depois como PRÉ-CONDIÇÃO de escrita, não relatório

Aqui esta ADR vai além do texto literal da regra mestre, e de propósito.

A regra pede que o impacto seja apresentado antes de aplicar. Em quase todo o sistema isso é um
evento raro (uma migration, um backfill). **Na folha, reprocessar competência é rotina** —
retroativo, correção de marcação, dissídio. Se o antes→depois for um relatório emitido depois da
gravação, ele vira registro de um estrago já feito, e o volume garante que alguém vai parar de
ler.

Portanto: **o diff por colaborador × rubrica é gate de gravação.** O reprocessamento calcula,
apresenta o delta, e **só grava após confirmação**. Sem confirmação, não grava. A aprovação
pareia com a R10 e com o item 3 da regra mestre.

Reprocessar competência **fechada** é ato ainda mais forte: exige reabertura explícita e
registrada, porque naquele ponto já houve pagamento e, eventualmente, declaração de terceiro.

### 5.4 Multi-tenant, tenant de teste e PII

`business_id` com global scope em toda tabela e toda query, Tier 0 irrevogável
([ADR 0093](../0093-multi-tenant-isolation-tier-0.md)). Teste automatizado roda no tenant
fictício **98** ([ADR 0358](../0358-doutrina-de-teste-tenant-98-supersede-0101.md)); **biz=4
(ROTA LIVRE) é proibido sem exceção** em teste, fixture, smoke ou exemplo.

Folha carrega PII e valor por pessoa física: nenhum CPF, nome de empregado ou remuneração real
entra em fixture, log, PR ou `memory/**`. A proibição de valores BRL em canon se estende, aqui, a
salário — por óbvio.

### 5.5 A superfície ATUAL já está fora da regra — e isso não espera o motor

O estado arte de §1.5 traçou um mecanismo na folha de hoje que precisa ser dito, com o rótulo que
o próprio autor lhe deu:

- **Fato, por leitura e varredura contada em 3 sítios:** `payroll/form_script.blade.php:116-117`
  calcula o bruto **no navegador** e escreve o campo oculto com valor cru, sem passar pelo
  formatador; `PayrollController::store()` atribui `total_before_tax = final_total` **sem
  `num_uf`** e persiste; e `git grep 'gross_amount' -- '*.js'` fora de `node_modules` retorna
  **zero**, logo não há outra implementação. **O servidor não recalcula o total da folha.**
- **Hipótese, explicitamente NÃO executada:** o autor traçou que a leitura de volta, com
  separador decimal pt-BR, teria a mesma forma do incidente ROTA LIVRE de 2026-06-05 (float cru
  com ponto interpretado como separador de milhar). Ele registrou como hipótese porque **não
  rodou a reprodução**, e esta ADR preserva o rótulo — promover isso a achado seria a lápide de
  2026-07-15.

Duas consequências, e as duas são decisão desta ADR:

1. **Recálculo server-side do total é item independente** — não tem pré-requisito, não espera o
   modelo de verba, e trata uma superfície **viva**. Deve ser tratado como tal, não como fase do
   motor.
2. **A hipótese exige reprodução antes de virar correção.** Rodar a reprodução é barato; propor
   remédio antes do diagnóstico é escolher o tratamento sem o exame.

Isto é **pré-existente** e não foi causado pela D2. Mas define a barra: a folha nova nasce dentro
da regra mestre; a superfície atual não está.

---

## 6. Piloto — biz=1, e a ressalva que o piloto tem

O piloto natural é o **biz=1**, o time CLT do próprio [W]. Três razões, e uma delas é jurídica:

1. **É folha real com risco contido.** Empregados reais, encargos reais, holerite que alguém
   confere de verdade — mas o dono do negócio é quem sofre a consequência de um erro, o que é a
   definição correta de piloto.
2. **Não cria obrigação de ponto nova.** O Art. 74 da CLT torna o controle de jornada
   obrigatório para estabelecimentos com mais de 20 empregados; abaixo disso é desobrigado. Como
   o biz=1 está nessa faixa, o piloto valida a **folha** sem herdar, de quebra, uma obrigação
   regulatória de ponto que hoje não existe ali.
3. **biz=4 está fora por regra.** ROTA LIVRE concentra 99% do volume de vendas, é cliente real, e
   a doutrina de teste ([ADR 0358](../0358-doutrina-de-teste-tenant-98-supersede-0101.md))
   proíbe biz=4 como tenant de teste ou smoke. Larissa também opera com 1-2 pessoas.

⚠️ **A ressalva, dita agora para não virar surpresa:** a folha depende do consolidado do Ponto, e
o Ponto **não tem dado em produção** — o `SPEC.md` registra medição de 2026-08-03 com **0
marcações, 0 intercorrências, 0 escalas** — enquanto os geradores do `ReportService` (`esocial`,
`he`, `bancoHoras`, `atrasos`, `afd`, `afdt`, `aej`) **são stubs que lançam `RuntimeException`**.
Ou seja: o insumo tem o formato certo (§1.2) e ainda não tem **porta nem conteúdo**. O piloto de
folha depende da integração Ponto × Essentials avançar, e essa dependência é de sequência, não de
desenho.

O contador do biz=1 é, no piloto, a **contraprova viva** da conciliação de §3.3: os dois motores
existem, e a divergência por rubrica é medida desde o primeiro fechamento — não a partir do dia
em que alguém desconfiar.

---

## 7. Consequências

**Aceitas:**

- O modelo de verba fica mais pesado que o blob atual — quatro conceitos onde hoje há dois
  arrays. É o preço de conseguir responder "qual a base de INSS?" sem adivinhar.
- A folha não substitui o contador enquanto a fronteira de §3 valer, e isso é dito **na tela**,
  não escondido.
- **Não existe versão de "calcular e não manter".** A manutenção é uma linha de produção legal
  com cadência própria: tabela do INSS por portaria (que **pode atrasar** — em 01/2026 a recepção
  do S-1200 chegou a ser suspensa esperando), IRRF que mudou por lei e mudou de forma, Notas
  Técnicas do eSocial a cerca de 2-3 por ano, salário mínimo, FAP anual, e convenção coletiva por
  categoria — esta última **por cliente**, não por lei geral.
- **O passivo trabalhista é do empregador, não do fornecedor de software** — mas a
  responsabilidade técnica e reputacional é de quem processa. Um erro nosso não gera multa para
  nós: gera multa para o cliente **e** encerra a relação.

**Rejeitadas, com o motivo — para não serem re-propostas:**

| Ideia | Por que cai |
|---|---|
| Enum único de natureza de verba, em vez de 3 flags | Refutado por `aviso prévio indenizado` (❌ INSS, ❌ IRRF, ✅ FGTS): a combinação não é ordenável (§2.2) |
| Tabela legal como constante parametrizada | A Lei 15.270/2025 acrescentou um **passo** ao pipeline do IRRF, não trocou um número (§2.3) |
| Gravar base de INSS/IRRF/FGTS como constante no fechamento | Recria o blob com outro nome: rubrica corrigida deixa a base mentindo sem nada acusar (§2.4) |
| Motor mensalista simples agora, retroativo "depois" | Não é refactor: sem `competencia_apuracao` no item, o conserto migra tabela + motor + casos-verdade + reprocessa histórico (§2.5) |
| Encargo patronal com regime único | Anexo IV do Simples recolhe CPP separado; os demais embutem no DAS (§2.6) |
| Terceirizar o cálculo junto com o transporte | Exporta o acoplamento com Ponto/Financeiro/FSM e devolve um número que não sabemos explicar (§4.2) |
| Emitir guia sem declarar ao eSocial | Impossível por construção da plataforma — a guia é consequência da declaração (§3.2) |
| Ler saldo de banco de horas em vez da liquidação | Paga o que já foi compensado (§3.4) |
| Casos-verdade gerados a partir do nosso motor | Lápide de 2026-06-05: teste tautológico, verde e inútil (§5.1) |
| antes→depois como relatório pós-gravação | Reprocessar é rotina na folha; relatório pós-fato documenta estrago (§5.3) |
| Tratar a hipótese de §5.5 como achado e já corrigir | Mecanismo traçado ≠ reproduzido; remédio antes do diagnóstico é a lápide de 2026-07-15 |

---

## 8. O que esta ADR NÃO decide

Deliberadamente fora, para não inventar o que ainda não tem sinal — e **nada aqui reduz o escopo
da D2**:

- **Nomes de tabela, colunas e migrations** — §2.7 fixa a forma; o PR de implementação escolhe os
  nomes.
- **O algoritmo de 13º e férias.** Eles **estão** no escopo da D2 e o modelo de §2 tem de
  suportá-los (bases próprias, INSS separado, IRRF exclusivo na fonte, média de variáveis, 1/3,
  abono). O que fica para ADR filha é **como** cada um calcula — não *se* entram.
- **Rescisão.** Não foi nomeada na emenda [W]; entra por decisão, não por dedução a partir da
  folha mensal.
- **Escopo de regime de contratação.** O piloto é mensalista CLT. Horista, comissionado puro,
  autônomo, estágio e aprendiz não entram por herança.
- **O contrato de entrada do Ponto** — tem dono próprio (§3.4).
- **UI.** Nenhuma tela é decidida aqui; quando houver, segue o processo de tela vigente (charter +
  casos + Padrão de Tela).
- **Cronograma.** As estimativas herdadas (§1.5) não viram compromisso nesta ADR.

---

## 9. Próximo passo

Se ratificada, a ordem é fixa e o motivo de cada posição está em §5.1:

1. **Recálculo server-side do total** (§5.5) — independente, sem pré-requisito, superfície viva.
   Antes dele, rodar a reprodução que converte a hipótese em achado ou a descarta.
2. **Casos-verdade externos** no repo — com fonte e competência declaradas, e nenhum motor ainda.
3. **Contrato do modelo de verba** (§2.7) — as quatro entidades, com `competencia_apuracao` desde
   o primeiro dia.
4. **Tabela legal versionada por competência** (§2.3), como pipeline extensível.
5. **Motor mensalista** contra os casos-verdade de (2).
6. **Gate de escrita** com antes→depois (§5.3).
7. **Conciliação com o contador** por rubrica (§3.3), no fechamento do biz=1.

---

**Ratificação:** esta proposta nasce `status: proposto`. **O merge por [W] é o ato de
ratificação** — não há aprovação implícita, e nenhum motor começa antes dela.
