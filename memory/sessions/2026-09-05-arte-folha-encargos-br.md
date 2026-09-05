---
date: "2026-09-05"
topic: "Estado da arte — folha de pagamento BR com encargos (INSS/IRRF/FGTS/13o/ferias) e o que custa levar o PayrollController do Essentials ate la"
authors: ["C"]
prs: []
outcomes:
  - "Fase 1: 5 referencias + os 5 mecanismos que decidem a arquitetura (rubrica x incidencia x vigencia)"
  - "Fase 2: PayrollController lido (1.188 linhas) — folha e planilha, zero encargo, total calculado no navegador"
  - "Fase 3: 12 gaps rankeados; motor minimo mensalista e onda, folha completa e trimestre, eSocial e obrigacao perpetua"
  - "Recomendacao: ADR mae que fixa modelo + fronteira gerencial x oficial ANTES de qualquer linha de motor"
related_adrs: ["0093-multi-tenant-isolation-tier-0", "0105-cliente-como-sinal-guiar-sem-mandar", "0106-recalibracao-velocidade-fator-10x-ia-pair", "0121-oimpresso-modular-especializado-por-vertical", "0382-remove-trava-de-sinal-para-trabalho-dirigido-por-w"]
---

# Estado da arte — folha de pagamento BR com encargos

> **Pedido:** [W] decidiu em 2026-09-05 (D2 do `PEDIDO-CL-hrm.md`) que a folha do `Modules/Essentials`
> deixa de ser gerencial e passa a ser completa, com encargos. **A decisao nao esta em discussao aqui** —
> este documento diz o que ela custa e como se faz certo.
>
> **Metodo:** Fase 1 (pesquisa web limpa, sem ler o repo) → Fase 2 (leitura do codigo) → Fase 3 (gaps).
> A pesquisa foi feita ANTES de abrir qualquer arquivo do projeto, de proposito.

---

## 1. Pesquisa — como os melhores fazem folha BR em 2026

| Referencia | Quem e | Mecanismo concreto | Por que e referencia |
|---|---|---|---|
| **eSocial (governo)** | O leiaute oficial — S-1010 (rubricas), S-1200 (remuneracao), S-1210 (pagamento) | Nao e "sistema": e o **modelo de dados imposto**. Cada rubrica declara `natRubr` (Tabela 3), `codIncCP`, `codIncIRRF`, `codIncFGTS`, `codIncPisPasep` e vigencia `iniValid`/`fimValid` em AAAA-MM | Define o formato que **todo** o mercado teve que adotar. Quem modela folha diferente disso, refaz |
| **eSocial Modulo Web / Simplificado** | O proprio governo, de graca, pra ME/EPP | Calcula folha, ferias e rescisao com tabelas padrao e emite as guias, sem software nenhum | E o piso competitivo do segmento do oimpresso. O produto pago precisa entregar valor **acima** disso |
| **TOTVS RH (RM/Protheus/Datasul) e Senior HCM** | Os dois maiores ERPs de folha BR | Publicam **feed de exigencia legal** por norma e amarram a release. O feed da Senior tem entrada propria pra Lei 15.270/2025 com a formula do redutor | Provam que folha nao e feature: e uma **linha de producao legal** com equipe dedicada e cadencia de release |
| **TecnoSpeed PlugDFe (eSocial)** | Transporte fiscal como servico, API REST multi-tenant | Voce manda os dados; eles geram o XML, assinam com o A1 e transmitem os 48 eventos | Separa "calcular certo" (fica com voce) de "acompanhar XSD/leiaute" (sai). Mesmo padrao que este projeto ja usa em NF-e |
| **BPO de folha (ADP, Deloitte, casas de DP)** | Terceirizacao do processo inteiro | O cliente manda eventos; o BPO calcula, declara e responde tecnicamente | E o benchmark do "quanto custa nao construir". Citado como economico na faixa de ~80-300 colaboradores |

### Os 5 mecanismos que decidem a arquitetura

**(1) A unidade nao e "calculo de INSS" — e a rubrica com incidencia.**
Ninguem maduro escreve `calcularInss($salario)`. Escreve-se: verba (provento/desconto/base/informativa) x natureza (Tabela 3) x **tres flags de incidencia independentes** (previdenciaria, IRRF, FGTS) x vigencia. As bases de INSS, IRRF e FGTS sao **somatorios derivados** dessas flags, nunca constantes no codigo. Isso e o que permite adicionar "vale-transporte", "premio", "PLR" sem tocar no motor — e e o que o eSocial valida no S-1200 contra o S-1010 declarado.

**(2) Vigencia por competencia esta no schema, nao e um extra.**
`iniValid`/`fimValid` em AAAA-MM ficam **na propria rubrica**. O mesmo vale pra tabela legal: INSS e IRRF sao resolvidos *pela competencia do calculo*, nunca pelo "vigente hoje". Folha que le a tabela atual nao consegue reprocessar marco em setembro — e reprocessar e rotina, nao excecao.

**(3) Retroativo tem forma canonica, e ela obriga o motor a ser reentrante.**
No S-1200 existem dois blocos: `infoPerApur` (competencia corrente) e `infoPerAnt` (periodos anteriores). Dissidio homologado em outubro retroativo a maio se **paga** em outubro, mas a contribuicao previdenciaria e calculada **mes a mes, com a aliquota e o teto de cada competencia original**. Motor que so sabe calcular "o mes atual" nao atende — e isso nao e refactor, e desenho.

**(4) A lei muda a FORMA do algoritmo, nao so a constante.**
Exemplo vivo e recente: a Lei 15.270/2025, vigente 01/2026, **nao reescreveu a tabela do IRRF** — acrescentou um **redutor** aplicado sobre o imposto ja calculado, com faixa de isencao ampliada, faixa de transicao com formula linear propria (`978,62 - 0,133145 x rendimento tributavel`, conforme o comunicado de exigencia legal da Senior) e nada acima do teto da transicao. Quem tinha "constante numa tabela" teve que **inserir um passo novo no pipeline**. No mesmo periodo, a recepcao do S-1200 de janeiro/2026 chegou a ser **suspensa** esperando a portaria que reajusta as faixas do INSS — houve uma janela em que o mercado inteiro nao conseguia declarar.

**(5) FGTS nao se calcula "por fora" desde o FGTS Digital.**
SEFIP / GRF / Conectividade Social foram substituidos. A guia do FGTS e apurada **do que foi declarado no eSocial**. Desde 05/2026 ate o FGTS de processo trabalhista migrou pro FGTS Digital. Consequencia dura pro nosso caso: **calcular FGTS sem declarar no eSocial produz um numero que nao vira guia**.

**Nao existe biblioteca.** Busca em 2026-09-05 por pacote PHP/Laravel de calculo trabalhista BR devolveu projetos didaticos em Java/Python e nada mantido. O caminho "usar biblioteca" esta **vazio por ausencia de oferta**, nao por preferencia — e isso muda a pergunta de build-vs-buy (secao 4).

---

## 2. Comparacao — o que o oimpresso tem hoje

### 2.1 O `PayrollController` (lido: 1.188 linhas, 20 metodos)

O primeiro passo do pedido foi ler o arquivo. **Ele nao e uma folha — e uma planilha de proventos e descontos com persistencia.**

- `create()` monta a tela: puxa `essentials_salary`, dias trabalhados, `total_work_duration` (de `essentials_attendances`), **comissao de venda** (dois modos: sobre faturado ou sobre recebido), **comissao por meta de venda** (`essentials_user_sales_targets`) e as verbas cadastradas em `getEmployeeAllowancesAndDeductions`.
- `store()` grava um `Transaction` com `type = 'payroll'`, `payment_status = 'due'`, e **as verbas como dois blobs JSON de arrays paralelos** (`allowance_names[]` / `allowance_amounts[]` / `allowance_types[]` / `allowance_percents[]`, idem deducoes) nas colunas `essentials_allowances` e `essentials_deductions` (migration de 2019).
- O total vem do formulario: `$payroll['total_before_tax'] = $payroll['final_total'];`

**Zero calculo de encargo, e isso e medido, nao impressao.** `git grep` por `inss|irrf|fgts|esocial` em `Modules/Essentials/**` retorna **0 ocorrencias**. As unicas ocorrencias em codigo do repo estao no `Modules/Financeiro/Http/Controllers/ImpostosController.php`, e sao **guias ja lancadas como titulo a pagar** — cujo proprio docblock diz, textualmente: *"o sistema nao tem folha — honesto"*.

### 2.2 O achado que colide com a regra Tier 0 de valor

**Fato (provado por leitura + varredura contada, 3 sitios):**

- `Modules/Essentials/Resources/views/payroll/form_script.blade.php:116-117` calcula o bruto **no navegador** (`var gross_amount = total + total_allowance - total_deduction;`) e escreve o hidden com `.val()` **cru**, sem passar pelo formatador (`__write_number`).
- `PayrollController::store()` atribui `total_before_tax = final_total` **sem `num_uf`** e persiste.
- `git grep 'gross_amount' -- '*.js'` (fora de `node_modules`) retorna **zero** — nao ha outra implementacao. O servidor **nao recalcula o total da folha**.

**Hipotese (mecanismo tracado, NAO executado — nao apresento como achado):** a linha 124 do mesmo script le o valor de volta com `__read_number` → `__number_uf` → `accounting.unformat(input, separador_decimal)`. Com separador decimal `,` (pt-BR), um float cru como `3456.79` teria o ponto tratado como separador de milhar — **a mesma forma do incidente ROTA LIVRE de 2026-06-05** que inflou 16 vendas. O alvo seria `payroll_groups.gross_total`. Reproducao: rodar `accounting.unformat("3456.79", ",")` no console da tela com locale pt-BR ativo. Enquanto isso nao for rodado, e hipotese.

Isto e **pre-existente**, nao e causado pela decisao D2. Mas define a barra: a folha nova nasce dentro da regra mestre de VALOR, e a superficie atual nao esta.

### 2.3 O que ja existe e e bom (credito, pra nao medir um sistema que nao existe mais)

- **`ponto_apuracao_dia` e melhor do que a maioria das folhas SMB recebe.** Ja segrega, por dia e em minutos: `he_diurna_minutos`, `he_noturna_minutos`, `adicional_noturno_minutos`, `dsr_repercussao_minutos`, `falta_minutos`, `atraso_minutos`, `saida_antecipada_minutos`, `banco_horas_credito/debito`, mais violacoes de intra/interjornada e um `estado` com `CONSOLIDADO`/`FECHADO`. O ledger de banco de horas tem `multiplicador` e tipo `PAGAMENTO`. **Essa e exatamente a forma que uma folha precisa consumir.**
- **O Financeiro ja tem receptor de guia.** `ImpostosController` lanca guia como titulo payable, idempotente por `metadata.guia`, e ja reconhece o vocabulario `FGTS` / `DCTF` / `INSS` / `DAS`. Encargo apurado tem pra onde ir sem tabela nova.
- **A divisao de responsabilidade ja estava decidida.** A [ADR 0014](../decisions/0014-essentials-pontowr2-integracao.md) (2026-04-21, hoje `lifecycle: arquivado`) ja dizia: *"Gerar folha de pagamento | Essentials (Payroll) — alimentado por PontoWR2"*. D1/D2 **executam** uma direcao registrada ha quase 5 meses; nao inauguram.

### 2.4 A tabela

| Dimensao | Estado da arte (Fase 1) | oimpresso hoje | Distancia |
|---|---|---|---|
| **Modelo de verba** | Rubrica com `natRubr` + 3 flags de incidencia + vigencia AAAA-MM | 2 blobs JSON de arrays paralelos, sem tipo, sem incidencia, sem vigencia | **longa** — e fundacao; errar aqui obriga reescrever |
| **Tabela legal** | Versionada por competencia, resolvida pela competencia do calculo | Nao existe | **longa** |
| **Motor de calculo** | Pipeline proventos → bases derivadas → INSS → IRRF → FGTS → liquido, reentrante | Soma no navegador; servidor persiste o que recebe | **longa** |
| **Encargos patronais** | CPP 20% + RAT/FAP + terceiros, com branch por regime (no Simples anexos I-III/V a CPP esta no DAS; no **Anexo IV** recolhe-se separado) | Nao existe | **longa** |
| **13o / ferias** | Bases proprias, INSS separado, IRRF exclusivo na fonte, media de variaveis, 1/3, abono | Nao existe | **longa** |
| **Retroativo** | `infoPerAnt`: paga no mes, calcula na competencia de origem | Nao existe (nem o conceito de competencia na folha) | **longa** |
| **Insumo de jornada** | Horas por faixa de adicional, faltas, DSR, banco liquidado | `essentials_attendances` (presenca web simples) — e [W] ja decidiu (D1) que cede pro Ponto | **media** — o dado bom existe no Ponto, falta o consolidado mensal |
| **Consolidacao mensal do Ponto** | Fechamento de competencia como pre-condicao da folha | `ponto_apuracao_dia` tem os minutos por dia e `estado=FECHADO`; **os geradores do `ReportService` (`esocial`, `he`, `bancoHoras`, `atrasos`, `afd`, `afdt`, `aej`) sao stubs que lancam `RuntimeException`** | **media** |
| **Holerite / recibo** | Documento com rubricas, bases e totais, arquivavel | `show.blade.php` imprime o agregado | **media** |
| **Contabilizacao da guia** | Encargo vira obrigacao financeira rastreavel | Receptor pronto no Financeiro (idempotente por `metadata.guia`) | **curta** — aqui o oimpresso esta a frente do esperado |
| **Multi-tenant** | Requisito de mercado | `business_id` Tier 0 ja e lei ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)); o `BRIEFING` registra que o write cross-tenant do Essentials foi fechado com gate explicito | **curta** — e onde o oimpresso supera SaaS single-tenant |
| **eSocial** | Obrigatorio pra folha oficial | Nao existe; `Ponto/ReportService::esocial()` e stub | **longa** (secao 5) |

**Contexto de sinal, dito uma vez** ([ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md); e por [ADR 0382](../decisions/0382-remove-trava-de-sinal-para-trabalho-dirigido-por-w.md) isto **nao e objecao**, e insumo de dimensionamento): a auditoria do Ponto propos como piloto **biz=1 (WR2, time CLT do proprio [W])** e explicitamente **nao** biz=4 — Larissa opera com 1-2 pessoas e o Art. 74 da CLT desobriga controle de ponto abaixo de 20 empregados. E o `SPEC.md` do Ponto registra medicao de 2026-08-03: **0 marcacoes, 0 intercorrencias, 0 escalas em producao**. Ou seja: a folha nova sera consumida primeiro pela propria casa. Isso muda o **plano de validacao** (piloto interno, o que e bom pro risco), nao a decisao.

---

## 3. O que a folha precisa RECEBER do Ponto

Nao planejo a integracao (outro agente esta nisso). Digo so o contrato de entrada, por colaborador e por competencia:

1. **Carga prevista e trabalhada** em minutos — base do desconto proporcional.
2. **Faltas**, em minutos **e em dias** — dia inteiro repercute em DSR e em periodo aquisitivo de ferias; minuto nao.
3. **Atrasos e saidas antecipadas** em minutos, separados de falta.
4. **Horas extras por faixa de adicional, ja com o multiplicador** — `he_diurna_minutos` sozinho nao basta: 50% e 100% sao rubricas diferentes, e o percentual vem de convencao coletiva. A folha precisa do par (minutos, multiplicador), nao do minuto.
5. **Adicional noturno** com a hora reduzida (52 min 30 s) **ja resolvida na apuracao** — se chegar em minutos crus, a folha vai ter que reimplementar regra de jornada, e ai sao dois donos da mesma regra.
6. **DSR sobre variaveis** — o Ponto ja calcula `dsr_repercussao_minutos`; a folha precisa dele, nao recalcular.
7. **Banco de horas: so o que foi LIQUIDADO** — movimento tipo `PAGAMENTO` com o `multiplicador`. Saldo compensado nao vira dinheiro; se a folha ler saldo em vez de liquidacao, paga o que ja foi compensado.
8. **Estado da competencia** — so `CONSOLIDADO`/`FECHADO` entra em folha, e o fechamento precisa ser um evento datado (a folha vai precisar saber *contra que fechamento* calculou, pra reprocessar).
9. **Afastamentos com codigo e data** — afastamento acima de 15 dias muda **quem paga** (empresa → INSS). Isso nao e detalhe de UI; e mudanca de quem e o devedor.

Hoje 1-8 existem como **linhas diarias** em `ponto_apuracao_dia`; o consolidado mensal e o item 9 (que vem do Essentials via D3) nao tem porta.

---

## 4. A pergunta que decide o tamanho: construir x biblioteca x terceiro

| Caminho | O que custa hoje | O que custa **perpetuamente** | Veredito |
|---|---|---|---|
| **Biblioteca** | — | — | **Descartado por ausencia de oferta.** Nao existe pacote PHP/Laravel maduro de calculo trabalhista BR (medido 2026-09-05). O que ha sao projetos didaticos em Java/Python |
| **Terceiro (folha inteira via API/BPO)** | Integracao (cadastro, eventos, conciliacao) | Baixo — a obrigacao legal e do fornecedor | Viavel, mas **muda o produto**: o oimpresso deixa de ter folha e passa a ter integracao. Coerente com "modular por vertical" se o objetivo e o cliente ter folha; incoerente se o objetivo e **vender folha como capacidade do modulo** |
| **Construir o calculo + terceirizar o transporte fiscal** | Motor proprio; eSocial via API tipo PlugDFe | **Alto e continuo** (abaixo) | **E o que o mercado faz de fato**, e o unico que preserva o produto. Separa "obrigacao de calcular certo" (fica) de "obrigacao de acompanhar XSD/leiaute" (sai) |

**A conta da manutencao perpetua, que e a parte que ninguem orca:**

- **Tabela INSS**: portaria interministerial, tipicamente em janeiro — e **pode atrasar** (em 01/2026 a recepcao do S-1200 ficou suspensa esperando).
- **Tabela IRRF**: mudou **por lei** em 2026, e mudou a **forma**, nao o numero (redutor novo sobre o imposto calculado).
- **Notas Tecnicas do eSocial**: cadencia observada de ~2-3 por ano mexendo em leiaute, regra de validacao e XSD (NT 01/2024, 02/2024, 03/2025, 04/2025, 05/2025, 06/2026 — esta ultima ainda revisada em abril e entrando em producao em duas datas distintas).
- **Salario minimo, FAP anual, e convencao coletiva por categoria** (data-base, piso, percentual de HE, adicionais) — este ultimo e por cliente, nao por lei geral.
- **Horizonte de risco: 5 anos.** E o prazo prescricional pra o trabalhador questionar diferenca com contrato vigente. Um erro de calculo hoje e cobravel por 5 anos.

**E quem responde:** o passivo trabalhista e do **empregador**, nao do fornecedor de software. Mas a responsabilidade tecnica e reputacional e de **quem processa**. Traduzido pro oimpresso: um erro nosso nao gera multa pra nos — gera multa pro cliente **e** encerra a relacao. Nao existe versao de "calcular e nao manter".

---

## 5. eSocial — da pra ter folha com encargos sem?

**Resposta honesta: calcular, sim. Operar, nao.**

O que da pra fazer sem tocar eSocial: calcular INSS, IRRF, FGTS, 13o e ferias corretamente, emitir holerite, lancar a despesa e a provisao no Financeiro. Isso e um produto legitimo — chame de **folha gerencial-com-encargos**: o numero esta certo, e a declaracao continua saindo por outro caminho (contador, ou o proprio Modulo Web do governo).

O que **nao** da:

1. **O FGTS so vira guia pelo FGTS Digital**, que se alimenta do eSocial. Calcular 8% e mostrar na tela nao produz recolhimento.
2. **INSS/IRRF idem**, via DCTFWeb, que tambem parte do eSocial.
3. **E o problema real: dois motores divergem.** Se a folha do oimpresso calcula um numero e a folha declarada ao eSocial (pelo contador) calcula outro, a divergencia e o passivo — e ela vai aparecer, porque ninguem confere centavo a centavo todo mes. Uma folha que **nao declara** precisa dizer isso no charter em voz alta, senao o cliente assume que declarou.

**O que muda se a resposta for "entao vamos ao eSocial":** entra um segundo projeto, do tamanho do primeiro. Minimo real: os eventos de tabela (S-1000, S-1005, S-1010, S-1020, S-1030), os periodicos (S-1200, S-1210, S-1298/S-1299) e os nao-periodicos de vinculo (S-2200, S-2205, S-2206, S-2230, S-2299), mais certificado A1, mais homologacao em **producao restrita** (relogio do mundo real, nao acelera com IA-pair), mais o ciclo de **retificacao**, que e onde mora a maior parte do esforco operacional. Terceirizar o transporte (PlugDFe e equivalentes) tira o XML, a assinatura e o XSD — **nao tira** o mapeamento de dados nem a retificacao.

**E o competidor de graca, dito sem drama:** o Modulo Web do eSocial ja calcula folha, ferias e rescisao e emite guia pra ME/EPP, sem custo. Isso nao mata o produto — define o que ele precisa entregar acima: **integracao com o resto do ERP**, que e exatamente o que [W] disse na D1 (*"vincular com outros modulos seria muito melhor, hoje esta tudo separado"*). Folha isolada perde do governo. Folha que sabe a jornada do Ponto, a comissao da venda e joga a guia no Financeiro, nao.

---

## 6. Gaps rankeados

Esforco em **h IA-pair** ([ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md): fator 10x + margem 2x ja aplicada). Onde o limite e humano (validacao contabil, homologacao, canary), esta marcado **relogio real** e **nao acelera**.

| # | Gap | Impacto | Esforco (IA-pair) | Pre-req |
|---|---|---|---|---|
| 1 | **Modelo de rubrica** (verba x natureza x 3 incidencias x vigencia) substituindo os blobs JSON | **alto** — e a fundacao; errar obriga reescrever tudo | 8-12h | ADR mae |
| 2 | **Tabela legal versionada por competencia** (INSS; IRRF com dependente / simplificado / redutor) + resolucao pela competencia do calculo | **alto** | 6-10h | #1 |
| 3 | **Suite de casos-verdade com resultado de fonte EXTERNA** (tabela publicada, calculadora oficial, caso de manual) — nunca derivada do nosso codigo | **alto** — e o que satisfaz a regra Tier 0 de valor | 12-20h | pode nascer ANTES do motor |
| 4 | **Motor de calculo mensal reentrante por competencia** (proventos → bases → INSS → IRRF → FGTS → liquido) | **alto** | 12-20h | #1, #2, #3 |
| 5 | **Recalculo server-side do total** — fechar o buraco de hoje (bruto calculado no navegador, servidor persiste o recebido) | **alto** — superficie viva, mesma forma do incidente de 2026-06-05 | 4-8h | nenhum |
| 6 | **Encargos patronais com branch de regime** (CPP 20% + RAT/FAP + terceiros; no Simples I-III/V esta no DAS, no **Anexo IV** recolhe separado) | **alto** | 8-12h | #4 |
| 7 | **Contrato de entrada do Ponto** — consolidado mensal por colaborador (secao 3), com o par (minutos, multiplicador) | **alto** | nao dimensiono (fora do meu escopo); o receptor `ReportService` e stub hoje | integracao Ponto (outro agente) |
| 8 | **13o salario** (2 parcelas, INSS separado, IRRF exclusivo na fonte na 2a, avos, media de variaveis) | **alto** | 15-25h | #4 |
| 9 | **Ferias** (1/3, abono pecuniario, media de variaveis, IRRF em base propria, faltas que reduzem o direito) | **alto** | 15-25h | #4 |
| 10 | **Retroativo / reprocessamento** (pagar na competencia corrente, calcular na de origem) | **alto** — se nao nascer no desenho, e reescrita, nao ajuste | 20-30h | #4 (mas o **desenho** entra em #1) |
| 11 | **Holerite e recibo** com rubricas, bases e totais | **medio** | 6-10h | #4 |
| 12 | **Guia → titulo no Financeiro** (receptor ja existe, idempotente por `metadata.guia`) | **medio** | 4-8h | #6 |
| — | **Rescisao** (nao pedida na D2) | — | 30-50h | fica **fora**, e nomeada de proposito: e onde mora a maior parte do passivo trabalhista, e uma folha que calcula 13o e ferias mas nao rescisao vai ser cobrada por isso |
| — | **eSocial** (secao 5) | — | 80-150h + homologacao em **relogio real** (semanas) | ADR propria; o Ponto ja tem dono parcial disso em `GAP-PONTO-005` — **nao abrir plano paralelo** |
| — | **Manutencao legal perpetua** | — | ~40-80h/ano, **para sempre** | — |

**O tamanho real, sem inflar e sem amaciar:**

- **Motor minimo mensalista** (#1-#6, #11-#12; mensalista CLT, sem 13o/ferias/rescisao/eSocial): **~60-100h IA-pair**. Isso e **onda** — 2 a 3 semanas de calendario com validacao.
- **Folha completa da D2** (+ 13o, ferias, retroativo): **~110-180h IA-pair**, mais a validacao contabil que **nao acelera**. Isso e **trimestre**, nao onda. Faixa honesta de calendario: **2 a 4 meses**.
- **Com eSocial**: some **2 a 3 meses** e uma **obrigacao perpetua** de acompanhar leiaute.
- **A partir do dia 1 do primeiro holerite emitido**, entra um custo fixo anual de manutencao legal que nunca zera, com horizonte de risco de 5 anos.

---

## 7. Como o plano satisfaz a regra Tier 0 de VALOR

A regra mestre de [`memory/proibicoes.md`](../proibicoes.md) exige, antes de mergear qualquer coisa que mexa em valor: **(1)** dupla confirmacao por **dois caminhos independentes** com numeros concretos, **(2)** apresentacao do impacto **antes→depois**, **(3)** aprovacao explicita de [W]. Folha e valor que vira **obrigacao trabalhista** — o nivel de exigencia sobe, nao desce.

**Caminho A — casos-verdade de fonte externa.** Fixtures cujo resultado esperado vem de **fora do nosso codigo**: tabela oficial publicada, calculadora do governo, caso resolvido de manual. Isto e o gap #3, e ele nasce **antes** do motor de proposito — teste escrito depois do codigo tende a copiar o codigo, que e a lapide de 2026-06-05 (*teste que deriva do codigo, tautologico*). Cada caso cita a fonte e a competencia.

**Caminho B — recomputacao independente.** O mesmo caso conferido por um segundo caminho que **nao compartilha implementacao** com o motor: recomputo do somatorio de bases a partir das rubricas, batendo centavo a centavo. Nao vale "o teste passou" — dois caminhos, dois numeros, igualdade provada.

**Antes→depois como pre-condicao de escrita, nao como relatorio.** Todo reprocessamento de competencia **produz o diff por colaborador e por rubrica antes de gravar**. Reprocessar folha e rotina (retroativo, correcao de tabela, dissidio), entao isso nao pode ser um relatorio opcional — e a porta. Vale igual pro primeiro calculo: a tela mostra o que vai virar obrigacao antes de virar.

**E os tres que a regra ja tem e valem aqui:** tenant de teste e o **98** ([ADR 0358](../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)), nunca biz=4; nada de PII real (CPF, salario) em PR, log ou fixture; e nenhum valor monetario em `memory/` — motivo pelo qual **este documento nao repete uma unica faixa de tabela legal**: numero de lei apodrece e o oraculo dele e a portaria, nao um `.md` nosso (lapide de 2026-07-17).

---

## 8. Recomendacao

**Comece pelo ADR mae de folha — alto impacto, ~2-4h IA-pair, zero pre-requisito bloqueante.**

Nao e burocracia: e que **tres decisoes travam todo o resto**, e cada linha de motor escrita antes delas e aposta.

1. **O modelo e rubrica x incidencia x vigencia** (nao "funcao que calcula INSS"). Isto e o gap #1 e determina se o trabalho e reaproveitavel ou descartavel.
2. **A fronteira: folha gerencial-com-encargos x folha oficial.** Qual das duas o charter promete ao cliente. Sem isso escrito, o cliente vai assumir a segunda — e a diferenca entre elas e um trimestre e uma obrigacao perpetua.
3. **Calcular in-house + transportar por terceiro** (ou nao) — a decisao que define se a manutencao legal recorrente inclui XSD de eSocial ou so tabela de calculo.

**Proxima acao hoje:** escrever `memory/decisions/proposals/2026-09-05-folha-com-encargos-modelo-e-fronteira.md` com essas tres decisoes, cada uma com a alternativa rejeitada e o custo de reverter. Merge de [W] = ratificacao.

**Em paralelo, sem esperar a ADR** (nao depende de nenhuma das tres): o gap **#5** — fazer o servidor recalcular o total da folha em vez de persistir o que o navegador mandou. E pequeno, e superficie viva, e e a unica coisa desta lista que ja esta errada **hoje**.

**O que NAO fazer agora:** planejar a integracao com o Ponto (tem outro agente), e abrir plano de eSocial (o Ponto ja tem dono parcial em `GAP-PONTO-005`, com pre-requisito de ADR propria — abrir paralelo aqui seria duplicar o dono).

---

## Fontes (Fase 1, consultadas em 2026-09-05)

- [S-1010 Tabela de Rubricas — documentacao Senior](https://documentacao.senior.com.br/gestao-de-pessoas-hcm/esocial/leiautes/tabelas/s-1010.htm) · [S-1200](https://documentacao.senior.com.br/gestao-de-pessoas-hcm/esocial/leiautes/periodicos/s-1200.htm) · [S-1210](https://documentacao.senior.com.br/gestao-de-pessoas-hcm/esocial/leiautes/periodicos/s-1210.htm)
- [Lei 15.270/2025 — Reforma do IR 2026 (comunicado de exigencia legal, Senior)](https://documentacao.senior.com.br/exigenciaslegais/noticias/trabalhista-previdenciaria/2025/2025-11-27-trabalhista-lei-15-270-2025-reforma-do-imposto-de-renda-2026/)
- [Manual de Orientacao do eSocial S-1.3 (gov.br)](https://www.gov.br/esocial/pt-br/documentacao-tecnica/manuais/mos-s-1-3-consolidada-ate-a-no-s-1-3-07-2026.pdf) · [Documentacao tecnica / Notas Tecnicas](https://www.gov.br/esocial/pt-br/documentacao-tecnica)
- [NT S-1.3 no 06/2026 — ajustes de leiaute (TOTVS)](https://www.totvs.com/blog/fiscal-clientes/esocial-nota-tecnica-s-1-3-n06-2026-com-ajustes-e-novas-validacoes/)
- [eSocial Simplificado — modulos WEB (gov.br)](https://www.gov.br/esocial/pt-br/noticias/esocial-simplificado-veja-como-sera-a-implantacao-dos-modulos-web) · [Modulo simplificado pra ME/EPP (Fecomercio)](https://www.fecomercio.com.br/noticia/micros-e-pequenas-empresas-terao-acesso-a-modulo-simplificado-do-esocial)
- [Manual do FGTS Digital v1.70 (gov.br)](https://www.gov.br/trabalho-e-emprego/pt-br/servicos/empregador/fgtsdigital/manual-e-documentacao-tecnica/manual-de-orientacao-do-fgts-digital-versao-1-70-12-06-2026.pdf) · [FGTS em processos trabalhistas via FGTS Digital desde 05/2026](https://www.gov.br/esocial/pt-br/noticias/ministerio-do-trabalho-e-emprego/recolhimentos-de-fgts-em-processos-trabalhistas-serao-efetuados-via-fgts-digital-a-partir-de-maio-2026)
- [Dissidios e folhas retroativas — compliance eSocial (Techware)](https://www.techware.com.br/blog/gestao-de-dissidios-e-folhas-retroativas-em-larga-escala-guia-de-compliance-no-esocial-para-grandes-empresas/) · [Calculo retroativo (LG lugar de gente)](https://prd-ng1.lg.com.br/Gente/Recursos/Help/FOPA/FUN.FOPA.062.html)
- [API/Componente eSocial pra ERP — TecnoSpeed PlugDFe](https://tecnospeed.com.br/plugdfe/esocial/)
- [TOTVS RH Folha de Pagamento](https://www.totvs.com/rh/folha-de-pagamento/) · [Espaco Legislacao TOTVS — eSocial](https://espacolegislacao.totvs.com/esocial/)
- [Contribuicao Previdenciaria — Anexo IV do Simples Nacional (Receita Federal)](https://www.gov.br/receitafederal/pt-br/assuntos/orientacao-tributaria/cobrancas-e-intimacoes/contribuicao-previdenciaria-anexo-iv-do-simples-nacional)
- [BPO de folha de pagamento (ADP Brasil)](https://br.adp.com/conteudo/artigos-e-estudos/articles/b/bpo-folha-de-pagamento.aspx) · [ROI do BPO (Techware)](https://www.techware.com.br/blog/roi-do-bpo-de-folha-de-pagamento-guia-para-lideres-de-rh/)
- [Erros de folha e passivo trabalhista — de quem e a responsabilidade (Jettax)](https://www.jettax.com.br/blog/esocial-e-folha-de-pagamento-erros-que-mais-geram-passivos-trabalhistas/)
