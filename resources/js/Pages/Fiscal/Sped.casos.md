---
id: resources-js-pages-fiscal-sped-casos
casos: SPED & Livros · /fiscal/sped
irmaos: Sped.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso.
owner: wagner
last_run: "2026-09-04"
---

# Casos de Uso & Aceite — SPED & Livros

> **Revalidação `last_run` 2026-09-04 — Onda 10 Fiscal (os Goals do charter do Cowork que a
> Onda 9 não entregou):** entram **UC-FSF1-01** (o bloqueio diz QUANDO deixa de bloquear),
> **UC-FSF1-06** (blocos do arquivo) e **UC-FSF1-07** (validação externa). Os três são
> **comportamento invocado**, não source-grep: chamam `SpedController::checagens` e
> `SpedReferenciaArquivoService` e olham o resultado. Cada um vem com **bite-test** — apontar o
> serviço para um arquivo ausente derruba a estrutura e a afirmação de validação junto.
>
> ⚠️ **O que Pest NÃO prova aqui, e não é fingido:** se a barra de validação está na PÁGINA ou
> dentro do drawer. Isso é posição de DOM; o veredito vem do smoke visual e do gate
> `contrato-de-tela` (que checa âncora + copy + ordem — rodado nos 4 modos do job, todos rc=0, com
> bite-test provando que reprova copy ausente e ordem trocada). Os UCs acima assertam o **payload**
> do servidor, que é o que determina o que a tela pode renderizar.

> **Revalidação `last_run` 2026-09-03 — Onda 9 Fiscal (F1 Cowork · régua de geração + golden):**
> entram **UC-FSF1-03** e **UC-FSF1-05**, os dois casos que desceram do Cowork vermelhos de
> propósito. Rodado no CT 100, MySQL de staging: **14 passed (182 assertions)** no arquivo novo, e a
> suíte `Sped|SimplesOnly` foi de **32 passed** (árvore original) para **46 passed** — +14, batendo
> exatamente com os casos adicionados. As **2 falhas** que aparecem nessa suíte são **pré-existentes
> e não deste PR** (medidas na árvore original antes de qualquer mudança): `SimplesOnlyGateTest`
> colide em `users_username_unique` porque o banco do CT 100 **persiste** entre runs, e
> `CuradorEngineTest` é de outro módulo, pego pelo filtro.
>
> ⚠️ **O §recibo de 2026-07-27 abaixo CADUCOU e fica preservado como fato datado.** Medido em
> 2026-09-03: `nfe_emissoes` **existe** no staging do CT 100 (`Schema::hasTable` = SIM) — o
> provisionamento de 2026-07-28 fechou aquela lacuna. O que **não** caducou: a lane de CI segue
> SQLite in-memory, e nenhum caso desta tela deve depender de banco pra rodar lá.


> **Revalidação `last_run` 2026-09-01 — Onda 1 Fiscal (saneamento `fx-*` → DS):** mudança de
> **apresentação apenas** — `fx-callout` → `<Alert>`, 4 `fx-chip` → `<Button>`, `fx-search` +
> `<input type="search">` → `<Input>`, `fx-filters` → `<Inline>`, 3 `fx-btn` → `<Button>` (o de
> download virou `<Button asChild>` sobre o `<a download>`, preservando o atributo). Conferi os
> 10 UC um a um: **todos assertam backend** — Tier 0 cross-tenant, validação de competência,
> superfície do gerador/rota, CFOP 5102×6102, MotorTributário, totalizador C190, constantes de
> fallback, gate de permissão e a flag de config. **Nenhum toca o `.tsx`.**
> Dois pontos checados no código, não presumidos: **(a)** a âncora `data-contract="fiscal-sped-
> status"` sobreviveu à troca do `<div>` pelo `<Alert>` (`contrato-de-tela` rc=0, com a copy
> literal "Próximas Waves:"); **(b)** o `role="region"` + `aria-label` do banner foram MANTIDOS
> — o `<Alert>` traz `role="alert"` embutido (live-region assertiva, errado para banner
> informativo estático), e o `{...props}` dele vem depois do padrão, então o override pega.
> **Nenhum teste foi re-executado** (Pest = CT 100).

> **Revalidação `last_run` 2026-08-28 — o que foi conferido:** este PR muda a tela em **um único ponto**: o atributo `data-contract="fiscal-sped-status"` no wrapper, âncora do mapa [`fiscal-sped.map.json`](../../../../memory/requisitos/Fiscal/fiscal-sped.map.json). Conferi o diff do `.tsx` contra a lista de UC deste arquivo — **nenhum UC depende de atributo de DOM**, logo nenhum aceite mudou. **Nenhum teste foi re-executado** nesta revalidação (Pest = CT 100); os vereditos seguem como estavam.

> Persona: **Eliana (contadora)** — gera/confere EFD-ICMS/IPI mensal. Cockpit Fiscal.
> Âncora de contrato: SPEC `US-FISCAL-010` (panorama) + `US-FISCAL-016`/`US-FISCAL-017` (gerador) +
> `US-FISCAL-020` (MotorTributario) + `Sped.charter.md`. Layout: Guia Prático EFD-ICMS/IPI v3.1.1
> perfil A (EFD instituída pelo **Ajuste SINIEF 02/2009**); CFOP pela tabela do **Convênio S/Nº de
> 15/12/1970** (5xxx = operação interna · 6xxx = interestadual); CSOSN 102 = "tributada pelo Simples
> Nacional sem permissão de crédito" (**Ajuste SINIEF 07/2005**).
>
> ⚠️ **Tela toca VALOR FISCAL** (regra-mestre cálculo · proibicoes.md): o gerador produz totais ICMS
> no TXT (Bloco C190/E110). Ver `d1_calculo` no scorecard.
>
> **Status:** ✅ passa (UC-id com veredito `pass` no manifesto) · 🧪 UC citado por teste, veredito
> ainda não capturado no manifesto · ⬜ não verificado · ❌ quebrou.

## Onde estes testes realmente rodam (recibo — não afirmação atemporal)

Medido em **2026-07-27**, `tailscale ssh root@ct100-mcp "docker exec -e DB_CONNECTION=mysql
oimpresso-staging php artisan test --filter=..."`:

| Suíte | Resultado medido |
|---|---|
| `SpedIcmsIpiGeneratorServiceTest` + `SpedMotorTributarioIntegrationTest` | **21 passed** (97 asserções) |
| `SpedControllerTest` (caso que toca `nfe_emissoes`) | **skip** — tabela ausente no staging |
| `SimplesOnlyGateTest` (HTTP 403/503) | **skip** — mesma causa |

**Limite honesto:** a lane de CI (`modules-pest.yml`) roda **SQLite in-memory**, e o staging do CT 100
**não tem as migrations do NfeBrasil**. Logo os casos que precisam de `nfe_emissoes` **não executam em
nenhuma lane disponível hoje** — é lacuna de ambiente, não defeito de teste. Re-rode o comando acima em
vez de editar estes números.

## UC-FSPED-01 — A geração nunca cruza business (Tier 0)
Status: 🧪 (`SpedIcmsIpiGeneratorServiceTest::UC-FSPED-01 · gerar lança RuntimeException cross-tenant` — **passa**; `SpedControllerTest::UC-FSPED-01 · agregação de períodos` skipa sem `nfe_emissoes`)
Dado a sessão do business X · Quando alguém pede a geração do SPED do business Y (X≠Y) · Então
`RuntimeException: Cross-tenant attempt` **antes de qualquer query**, e a agregação do painel só conta
notas do business da sessão. Âncora: ADR 0093 (`business_id` global scope, Tier 0 IRREVOGÁVEL).
**Pronto quando:** biz=1 pedindo biz=99 lança; biz=1 pedindo biz=1 segue o fluxo normal.

## UC-FSPED-02 — Competência inválida é rejeitada antes de gerar
Status: 🧪 (`SpedIcmsIpiGeneratorServiceTest::UC-FSPED-02 · …ano < 2020` / `…ano > ano atual` / `…mes fora 1-12` — 3 casos, **passam**)
Dado ano anterior a 2020, ano futuro, ou mês fora de 1–12 · Quando a contadora pede o arquivo · Então
`InvalidArgumentException` ("Ano inválido" / "Mês inválido") — nunca um TXT de competência impossível.
**Pronto quando:** 2019, ano+1, e os meses 0/13/-1/99 falham; competência corrente passa.

## UC-FSPED-03 — O gerador é público e a rota de download existe
Status: 🧪 (`SpedIcmsIpiGeneratorServiceTest::UC-FSPED-03 · gerar method público existe + signature canônica` + `SpedControllerTest::UC-FSPED-03 · SpedController expõe gerar()…` — **passam**)
Dado o contrato do módulo · Quando outra camada chama o gerador · Então existe
`SpedIcmsIpiGeneratorService::gerar(int $businessId, int $ano, int $mes): string` e a rota nomeada
`fiscal.sped.icms-ipi` (`GET /fiscal/sped/icms-ipi/{ano}/{mes}`) está registrada.
**Pronto quando:** reflection confirma a assinatura e `Route::has('fiscal.sped.icms-ipi')` é verdadeiro.
_(Este UC substitui o guard revogado "Controller é placeholder — sem gerador SPED real ainda", que
ficava verde só porque assertava a ausência dos nomes `exportSped`/`gerarEFD` enquanto o gerador real
se chama `gerar`. Ver cabeçalho do `SpedControllerTest`.)_

## UC-FSPED-04 — CFOP segue a UF: 5102 interno, 6102 interestadual (Tier 0 · risco de multa)
Status: 🧪 (`SpedMotorTributarioIntegrationTest::UC-FSPED-04 · …CFOP 5102 (interno)…` + `…CFOP 6102 (interestadual)… (audit R1)` — **passam**)
Dado venda SC→SC (interna) e SC→RS (interestadual) · Quando o fallback resolve o tributo do item ·
Então CFOP **5102** no primeiro e **6102** no segundo, e o NCM real é preservado quando informado.
Âncora: US-FISCAL-020 + audit sênior 2026-05-25 R1 — o hardcode 5102 gerava SPED inválido em venda
interestadual (exposição a multa). Tabela CFOP: Convênio S/Nº de 1970.
**Pronto quando:** `fallbackSimplesNacional('SP','SP',null)` → 5102 e `('SC','RS','61091000')` → 6102.

## UC-FSPED-05 — Com MotorTributario configurado, o ICMS é o calculado (não o fallback)
Status: 🧪 (`SpedMotorTributarioIntegrationTest::UC-FSPED-05 · resolverTributoItem com motor configurado…` — **passa**)
Dado motor devolvendo Lucro Presumido (CST 00, CFOP 6102, alíquota 18%) e item de base 1.000,00 ·
Quando resolve o tributo · Então `vl_icms = 180,00`, CST/CFOP/NCM vêm do motor e **não** do fallback.
Âncora: US-FISCAL-020. ⚠️ Toca valor fiscal — mudança aqui exige a regra-mestre de cálculo.
**Pronto quando:** base × alíquota bate por dois caminhos (asserção do teste + recomputação à mão).

## UC-FSPED-06 — Sem regra no motor, cai no Simples Nacional sem quebrar
Status: 🧪 (`SpedMotorTributarioIntegrationTest::UC-FSPED-06 · …fallback quando motor lança NcmObrigatorioException` + `UC-FSPED-06 · instanciação sem motor (legado)…` — **passam**)
Dado motor que lança `NcmObrigatorioException`/`TributacaoNaoConfiguradaException` (caso biz=4 hoje), ou
serviço instanciado sem motor (legado) · Quando resolve o tributo · Então CSOSN **102**, alíquota 0 e
ICMS 0 — degradação graciosa, sem exceção vazando pro download. Âncora: US-FISCAL-020 + SINIEF 07/2005.
**Pronto quando:** o fallback devolve 102/5102 e a instanciação sem DI continua funcionando.

## UC-FSPED-07 — O totalizador C190 agrupa por CST|CFOP|alíquota, não por hardcode
Status: 🧪 (`SpedMotorTributarioIntegrationTest::UC-FSPED-07 · keyTotalizadorC190 não retorna mais hardcode "102"…` — **passa**)
Dado itens com CST/CFOP/alíquota distintos · Quando monta a chave do totalizador C190 · Então a chave é
**composta** (contém CST, CFOP e separador `|`) e nunca a constante "102" — senão operações diferentes
colapsariam numa linha só do Bloco C190. Âncora: US-FISCAL-020 GAP-3.
**Pronto quando:** `keyTotalizadorC190` de um item CST 00/CFOP 6102/18% difere da chave do Simples.

## UC-FSPED-08 — Os fallbacks vivem em constantes nomeadas, não espalhados
Status: 🧪 (`SpedMotorTributarioIntegrationTest::UC-FSPED-08 · refactor define constantes FALLBACK_* centralizadas` — **passa**)
Dado o serviço · Quando se procura o valor de fallback · Então ele está em constante única
(`FALLBACK_NCM_SEM_CADASTRO='00000000'`, `FALLBACK_CST_CSOSN_SIMPLES_SEM_CREDITO='102'`,
`FALLBACK_CFOP_VENDA_INTERNA_SIMPLES='5102'`, `FALLBACK_CFOP_VENDA_INTERESTADUAL_SIMPLES='6102'`,
`FALLBACK_ALIQ_ICMS_SIMPLES=0.0`). Âncora: US-FISCAL-020 GAP-3 (audit sênior).
**Pronto quando:** `getConstants()` traz as 5 chaves com exatamente esses valores.

## UC-FSPED-09 — O download é gated por permissão e pela trava do Simples
Status: 🧪 (`SimplesOnlyGateTest::UC-FSPED-09 · …403` / `…503 quando flag true` / `…superadmin bypassa` / `…flag false libera` — 4 casos; **skipam** hoje por falta de `nfe_emissoes`, ver §recibo)
Dado a rota de download · Quando o usuário **não** tem `fiscal.sped.export` · Então **403** (a permissão
é checada antes de tudo). Quando tem a permissão mas `fiscal.sped_simples_only_lock` está ligada · Então
**503** explicativo citando GAP-FISCAL-003, preservando a visualização do painel. Superadmin bypassa;
flag desligada libera. Âncora: charter (permissão `fiscal.sped.export`) + Onda ESTABILIZAR 2026-05-25.
**Pronto quando:** os 4 status batem numa lane com as migrations do NfeBrasil aplicadas.

## UC-FSPED-10 — A trava nasce ligada em produção e mora no config canônico
Status: 🧪 (`SimplesOnlyGateConfigTest::UC-FSPED-10 · flag default = true…` / `…vive em config/fiscal.php` / `…SpedController referencia a flag` — 3 casos, rodam sempre)
Dado nenhuma configuração explícita · Quando o app lê `fiscal.sped_simples_only_lock` · Então o default é
**true** (fail-secure: sem trava, um TXT com hardcode sairia pro Fisco), a chave vive em
`config/fiscal.php` e o `SpedController::gerar` de fato a consulta. Âncora: audit sênior R1.
**Pronto quando:** default true, chave no arquivo canônico, e o Controller referencia a flag.

## UC-FSF1-03 — Competência em aberto é recusada antes de qualquer query
Status: 🧪 (`SpedOndaF1Test` — 10 casos: 4 sobre a guarda do Service + 6 sobre a régua da tela. **14 passed (182 assertions)** medidos no CT 100 em 2026-09-03; o manifesto do G-7 é alimentado pelas lanes de CI, não por rodada local — daí 🧪 e não ✅)
Dado uma competência cujo mês ainda não terminou · Quando alguém pede a geração — pela tela ou
chamando o Service direto · Então a recusa acontece **na validação, antes de qualquer query**:
`InvalidArgumentException` com "Competencia em aberto", e na tela o botão fica desabilitado com o
motivo no `title`. Até esta onda **só a tela bloqueava**; o Service aceitava.
Âncora: `FiscalOndasF1Test` (Cowork 2026-09-03) + Guia Prático EFD-ICMS/IPI v3.1.1 perfil A — o
registro **0000** declara `DT_INI`/`DT_FIN` do período de apuração, então um mês não encerrado
produziria movimento parcial se apresentando como a apuração fechada daquele período.
**A régua da tela tem 4 checagens, cada uma com ✓/✕ e o motivo em texto**, avaliadas no servidor
(`SpedController::checagens` — a tela renderiza, não decide):

| # | Checagem | Reprova quando | Prova |
|---|---|---|---|
| a | `ano-minimo` — ano ≥ 2020 | competência de 2019 | motivo cita "2019" e "2020" |
| b | `nao-futura` — competência não-futura | competência de daqui a 2 meses | motivo cita a competência `mm/aaaa` |
| c | `fechada` — mês encerrado | competência corrente | motivo cita a competência e o registro `0000` |
| d | `trava` — `fiscal.sped_simples_only_lock` | trava ligada e usuário não-superadmin | motivo cita a chave da flag; superadmin dispensa |

**Pronto quando:** cada critério reprova isoladamente com o motivo certo, uma competência
inteiramente válida aprova nas 4, e o mês corrente é recusado pelo Service com o tipo de exceção
que prova que nenhuma query rodou (business inexistente daria `RuntimeException`, não
`InvalidArgumentException`).

## UC-FSF1-05 — Existe golden file do TXT EFD-ICMS/IPI, e ele é bem-formado
Status: 🧪 (`SpedOndaF1Test` — 4 casos, **passam** no mesmo run acima; 🧪 pelo mesmo motivo — o veredito ainda não está no manifesto do G-7)
Dado o gerador · Quando se quer comparar a saída com uma referência · Então existe
`Modules/Fiscal/Tests/Fixtures/sped-icms-ipi-golden.txt`, produzido a partir da **saída real** do
`SpedIcmsIpiGeneratorService` (tenant fictício 98 · competência 2026-01 · 1794 bytes · 47 linhas ·
SHA-256 `e4eeccd4…`), com o insumo e a receita de regeração declarados no `.meta.md` ao lado.
O teste não se contenta com `file_exists`: confere que toda linha é pipe-delimited, que o `0000`
abre com `COD_VER 018`/`COD_FIN 0`/`IND_PERFIL A`, que o `9999` fecha declarando `QTD_LIN` igual à
contagem real, que os 5 blocos abrem e fecham (`0001/0990`, `C001/C990`, `E001/E990`, `H001/H990`,
`9001/9990`), e que **cada contador `9900` bate com as linhas reais** do arquivo.
**Pronto quando:** apagar o golden deixa os 4 casos vermelhos (bite-test feito).

> ⚠️ **O que este golden EXPÔS, e não foi consertado nesta onda.** Ao gerar o arquivo pela primeira
> vez ponta-a-ponta, dois achados apareceram:
> **(1)** o gerador **quebrava com `TypeError` antes de terminar** — o `foreach` dos contadores do
> Bloco 9 passa a chave do array para `registro9900(string $reg)`, e o PHP coage `'9001'`/`'9900'`/
> `'9990'`/`'9999'` para `int` (só `'0000'` e `'C100'` escapam, por causa do zero à esquerda e da
> letra). Corrigido com um `(string)` — zero efeito no conteúdo emitido. Ninguém tinha visto porque
> **nenhum teste chegava a gerar o arquivo**, e a trava fail-secure impede o download em produção.
> **(2)** o registro `0000` sai com **CNPJ vazio, IE vazia e `UF` fixa em `SP`**. Medido: a tabela
> `business` **não tem** `state`, `city`, `zip_code`, `landmark`, `tax_number`,
> `inscricao_estadual`, `mobile` nem `email` — no UltimatePOS elas moram em `business_locations`, e
> o Service cai no fallback `'SP'`. Como o CFOP interno×interestadual é decidido pela UF do
> emitente, **toda** operação é comparada contra SP. Isso é motor fiscal e **decisão do
> responsável**, não conserto silencioso — ver `sped-icms-ipi-golden.meta.md` §"O que este golden
> EXPÕE".

## UC-FSF1-01 — O bloqueio diz o motivo E a data em que deixa de bloquear
Status: 🧪 (`SpedOnda10Test` — 3 casos, rodam em toda lane: não tocam banco)
Dado a competência corrente, ainda em aberto · Quando [E] lê por que não pode gerar · Então o
motivo cita **a data em que a competência encerra** (último dia do próprio mês), além do critério
e da norma. Até a Onda 9 o motivo dizia só "o mês ainda não terminou": informava o *que* falta,
nunca *quando* deixa de faltar.

⚠️ **A data é o ENCERRAMENTO, não o prazo de entrega — e essa diferença tem um teste próprio.**
O protótipo do Cowork cita ali o campo `entrega` (dia 15 do mês seguinte). São coisas distintas:
09/2026 encerra em 30/09 e a entrega vence 15/10. Quem lesse "fecha em 15/10" esperaria duas
semanas a mais do que precisa para gerar. O prazo de entrega segue na coluna própria da tabela.
**Pronto quando:** o motivo do mês corrente contém a data de encerramento e **não** contém a de
entrega; o mês encerrado também informa em que data encerrou.

## UC-FSF1-06 — Os blocos do arquivo são medidos, não escritos
Status: 🧪 (`SpedOnda10Test` — 4 casos, incluindo o bite-test)
Dado o arquivo de referência EFD-ICMS/IPI · Quando [E] quer saber o que vai dentro de cada bloco ·
Então a tela lista, por bloco, **os registros que o arquivo realmente contém** — medidos linha a
linha pelo `SpedReferenciaArquivoService`, nunca uma lista canônica escrita à mão que apodrece no
primeiro ajuste do gerador. A soma das linhas por bloco bate com a contagem real do arquivo, e
registro repetido (as 22 linhas `9900`) conta linha sem repetir o nome na lista.

- **Regressão que defende:** uma lista escrita à mão continuaria "certa" na tela depois de o
  gerador parar de emitir um registro — e a contadora leria estrutura que o arquivo não tem.
- **Pronto quando:** os 5 blocos saem na ordem `0 · C · E · H · 9` com os registros reais, a soma
  bate, e apontar o serviço a um arquivo AUSENTE devolve `disponivel: false` com `blocos: []` —
  ausência declarada, nunca estrutura presumida (bite-test).

## UC-FSF1-07 — A tela não finge validação que não houve, nem nega a que houve
Status: 🧪 (`SpedOnda10Test` — 3 casos, incluindo 2 bite-tests)
Dado o cartão de validação externa · Quando [E] pergunta se o arquivo foi validado · Então lê o
estado **verdadeiro na data**: o smoke no PVA-EFD segue **nunca executado** (derivado da ausência
do recibo em `Modules/Fiscal/Tests/Fixtures/sped-pva-smoke.recibo.md`), e o arquivo de referência
**existe**, com bytes, linhas e SHA-256 lidos do disco.

⚠️ **Por que a copy do protótipo não pôde ser copiada.** O cartão do F1 diz literalmente
*"Golden file do TXT: não existe"*. O charter do Cowork é de 2026-08-24; o golden nasceu em
2026-09-03 ([PR #6708](https://github.com/wagnerra23/oimpresso.com/pull/6708)). Traduzir a copy
literal teria posto uma **afirmação falsa** na tela. Nada aqui é afirmado: tudo é lido do disco a
cada request, e o dia em que alguém rodar o PVA e deixar o recibo, a tela para de dizer "nunca
executado" **sozinha**, sem editar código.
- **Pronto quando:** `golden.presente` é verdadeiro contra o arquivo real; `pvaSmoke.executado`
  vira verdadeiro ao apontar para um arquivo que existe (bite-test — um texto fixo passaria no
  primeiro assert e falharia neste); e sem arquivo de referência, "apuração do ICMS no arquivo"
  cai junto, porque ela é medida pela presença do Bloco E.

## Backlog de casos (sem id — entram quando um teste de COMPORTAMENTO os cobrir)

> ⚠️ Os itens abaixo **têm teste**, mas o teste é **source-grep**: asserta que uma string existe no
> código-fonte (`expect($src)->toContain(...)`), não que o comportamento acontece. Um grep de
> `"if (\$vlTotalDebitos > 0)"` quebra se alguém renomear a variável e passa se a lógica estiver
> errada — por isso **não viram UC** (lápide §5 2026-06-05: teste que não prova comportamento é pior
> que ausente, porque parece cobertura). Viram UC quando um teste gerar o TXT e inspecionar as linhas.

- **[BACKLOG · source-grep] TXT traz os 23 registros canônicos dos Blocos 0+C+E+H+9** — `SpedIcmsIpiGeneratorServiceTest::contract: 23 registros canon` procura os NOMES DE MÉTODO (`registro0000`…) no fonte e conta um array literal escrito no próprio teste. Não gera arquivo nem valida linha `|REG|…|`.
- **[BACKLOG · source-grep] Bloco E: E110 consolida os débitos do C190 e E116 só sai com ICMS a recolher** — os dois casos assertam `toContain("array_sum(array_column(...))")` e `toContain("if ($vlTotalDebitos > 0)")` no fonte.
- **[BACKLOG · source-grep] Bloco H é esqueleto (IND_MOV=1)** — asserta `toContain('registroH001(1)')` no fonte; o inventário real exige integração Stock/ProductCatalogue (declaração de 31/12).
- **[BACKLOG · source-grep] Span OTel `fiscal.sped.gerar`** — grep da string no fonte.
- **[BACKLOG · ⬜ sem teste] Panorama das 5 competências** — mês, notas autorizadas, valor, status (aberto/pronto/entregue) e prazo. `SpedController::index` agrega; nenhum teste asserta o payload das 5 competências. _(O gate do export deixou de ser só "tem notas?" em 2026-09-03: agora são as 4 checagens do `UC-FSF1-03` mais a contagem de notas, e as checagens **têm** teste. O que segue sem teste é a agregação em si.)_ _(O prazo exibido usa dia 15 como heurística; o prazo legal da EFD é fixado por cada UF.)_
- **[BACKLOG · ⬜ sem teste] Smoke PVA-EFD homologação CONFAZ** — importar o TXT no validador oficial sem erro estrutural. _(O golden file passou a existir em 2026-09-03 — `UC-FSF1-05` —, e ele confere estrutura, blocos e contadores. O que continua sem prova é a importação no **PVA-EFD real**, que é ferramenta externa; e o golden já expõe dois motivos pelos quais o PVA recusaria hoje: CNPJ/IE vazios e UF fixa. Ver o aviso no UC-FSF1-05.)_
- **[BACKLOG · ⬜ sem teste] Entradas (DF-e manifestada), EFD-Contribuições (PIS/COFINS) e saldo credor anterior real no E110** — Non-Goals declarados; nenhum código nem teste.

## Como rodar a suíte
1. **Pest:** lane Fiscal no CT 100 (ADR 0062) — `tailscale ssh root@ct100-mcp "docker exec -e DB_CONNECTION=mysql oimpresso-staging php artisan test --filter='Sped|SimplesOnly'"`. Ver §recibo pro que executa e o que skipa hoje.
2. **Cadência:** rodar ao fim de toda mexida. UC ❌ = regressão fiscal (multa).

## Trilha do tempo
- 2026-09-04 · [CC] Onda 10 (F1 Cowork): entram `UC-FSF1-01`, `UC-FSF1-06` e `UC-FSF1-07`, traduzindo os Goals 1 (completar), 5 e 4 do charter do Cowork. A régua ganhou barra na página (antes só existia dentro do drawer) e as duas superfícies novas são **medidas no arquivo de referência** — o cartão de validação teve de contradizer a copy do protótipo, que diz "golden file: não existe" e ficou desatualizada no dia seguinte ao charter. Goal 3 (prévia do TXT) segue em aberto por decisão [W].
- 2026-07-03 · [CC] criado no Passo 3 do programa de ondas (régua por tela). 22 testes mapeados, 0 citavam UC-id.
- 2026-09-03 · [CC] Onda 9 (F1 Cowork): entram `UC-FSF1-03` (régua de 4 checagens + guarda de entrada no Service) e `UC-FSF1-05` (golden file). Dois achados registrados sem conserto silencioso — o `TypeError` do Bloco 9, corrigido com um cast, e o emitente sem CNPJ/IE/UF, que é decisão do responsável. O §recibo de 07-27 caducou: `nfe_emissoes` existe no CT 100 desde o provisionamento de 07-28.
- 2026-07-27 · [CC] fecha a G-2: 10 UC declarados (`UC-FSPED-01..10`) e citados pelos testes existentes. Separado o que é **comportamento provado** (invocação real) do que é **source-grep** — os 5 source-grep ficam backlog explícito em vez de virar UC de fachada. Revogado o guard `Controller é placeholder` (verde por nome, ver `UC-FSPED-03`). Prefixo `UC-FSPED-` em vez do `UC-FISCAL-` planejado: as 6 telas Fiscal compartilhariam o mesmo id e o G-2 casa por substring — colisão viraria cobertura falsa cruzada.
