---
id: resources-js-pages-jana-index-casos
casos: Jana Painel · metas ativas · farol server-side · cockpit deferido · /ia
irmaos: Index.charter.md (lei) · memory/requisitos/Jana/RUNBOOK-index.md (runbook) · prototipo-ui/contrato/jana-painel.contract.json (contrato visual)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-08-18"
---

# Casos de uso — /ia (Painel da Jana)

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> ⚠️ **Por que os 🧪 estavam presos (achado de 2026-08-17, corrigido no PR do UC-10).** O
> `PainelContratoTest.php` nasceu no PR #5862 **fora** da allowlist da lane `PHP / Pest (Jana ·
> MySQL)`, que roda **arquivo por arquivo** (`jana-pest.yml`, *"cada novo teste MySQL-only do Jana é
> adicionado AQUI"*). Resultado: nenhum dos nove UCs jamais rodou no CI, e o `🧪 aguarda run verde`
> não era uma espera — era um estado **inalcançável**, com a lane concluindo verde sem eles
> (`Tests: 6 skipped, 245 passed`). Registrar o teste no repo **não é** a lane executá-lo
> (§5 2026-08-02 + emenda 08-12). Faltava ainda o trigger: o `paths:` não incluía
> `resources/js/Pages/Jana/**`, então mexer no `.tsx` não acordava o teste que o defende. As duas
> pernas foram corrigidas; **a prova é o contador da lane subir de 245**, não o check ficar verde.

> Derivados do `Index.charter.md` (§Goals/§Anti-hooks) e do `jana-painel.contract.json` — **não**
> do `Index.tsx`. Derivar do código seria tautológico (§5 2026-06-05): passaria verde mesmo com o
> comportamento errado.
>
> ⚠️ **O que a âncora diz sobre FONTE de dado continua não valendo.** O `related_prototype` é
> `prototipo-ui/cowork/jana-merge.jsx` (resolva sempre por `node prototipo-ui/ancora.mjs Jana/Index`,
> nunca no olho). Ele cita 6 `Analise*Service` que **não existem** no repo — a fonte real é
> `app/Services/Sells/SellsCockpitAggregator.php`. Isso é o §Anti-hooks do charter *"não citar no
> drawer fonte/serviço que não existe"*, e segue valendo: nome fictício num drawer chamado "de onde
> vem esse número" é mentira com selo de autoridade.
>
> _**Frota deixou de ser proibida** — [W] removeu o Non-Goal no charter v7 (#5867), textual:
> "frota e caçambas locações remova do charter". O UC que travava isso **saiu deste arquivo** na
> mesma leva. As regras visuais da âncora sempre valeram; agora o domínio dela também não é mais
> exceção. Sem obstáculo de máquina: o `dominio-gate` nunca varreu `Pages/Jana` — seus
> `forbidden_ui_paths` são três, todos de `OficinaAuto`._

## Revalidação de 2026-08-18 — por que o `last_run` subiu

O G-6 acusou `stale:` porque o `Index.tsx` mudou depois do `last_run` de 08-17. O que mudou, e o
que isso faz com cada UC:

| mudança | UCs afetados |
|---|---|
| METAS foi pro slot `aposKpis` do `JanaCockpit` (posição da âncora) | **nenhum** — os UCs falam de payload e copy, não de ordem vertical |
| os 2 blocos mock (`JanaKpiStrip`, `ProximaAcaoCard`) saíram | **nenhum** — eram mock declarado, sem UC |
| a âncora `painel-cta-conversar` mudou de hospedeiro (do mock pro botão real) | **UC-09** — re-rodado: `contrato:check` **limpo**, 5 seções + ordem OK |
| `ApuracaoService::projecao()` extraída + campo no payload | **UC-02** — a prop `metas` ganhou um campo, mas a CONTAGEM não mudou: seguem 4 eager + 1 deferida |

Nenhum `Status:` mudou. Os `🧪` continuam `🧪` e o `✅` do UC-04 continua ✅ — o
`FarolServerSideTest` não foi tocado, e a extração do `projecao()` preservou o veredito do farol
(cada `return 'cinza'` virou um `null`, as fronteiras −5%/−15% intactas).

⚠️ O que **não** foi revalidado por execução: os `🧪` seguem sem run verde na lane MySQL. O bump do
`last_run` diz *"o diff foi conferido contra os UCs"*, **não** *"os UCs foram provados"* — quem prova
é o CI. Subir o número calado é o que o G-6 existe pra impedir.

## UC-COPI-PAINEL-01 — A rota `/ia` abre o Painel (200 + componente)
Status: 🧪 (`Modules/Jana/Tests/Feature/PainelContratoTest.php` — cita o UC; aguarda run verde na lane MySQL)

Usuário autenticado do business abre `GET /ia`. O grupo `/ia` garante auth; o Controller renderiza
o componente Inertia `Jana/Index`. Âncora: SPEC US-COPI-148 (fusão numa tela única; `/ia/dashboard`
responde 301 pra cá).

**Pronto quando:** GET `/ia` autenticado → 200 e `assertInertia(component 'Jana/Index')`.

## UC-COPI-PAINEL-02 — Contrato de props: 4 eager + 1 deferida
Status: 🧪 (`PainelContratoTest` — `missing(coworkAggregates)` + as 4 eager; aguarda run verde)

A tela recebe `metas`, `sellKpis`, `insightsAggregates` e `janaContext` de forma **eager**, e
`coworkAggregates` de forma **deferida**. A separação não é acidente: o HOTFIX [W] de 2026-05-25
(pós-PR #1547) fixou que `metas` **não** pode ser deferida, porque a Page lê `metas.length` direto.

Âncora: charter §Goals + o comentário canon no `IndexController::index()`.

**Pronto quando:** as 4 props eager chegam no first render e `coworkAggregates` **não** está entre elas.

## UC-COPI-PAINEL-03 — Escopo `business_id`, incluindo o `orWhereNull` intencional (Tier 0)
Status: 🧪 (`PainelContratoTest` — `?business_id=999` ignorado; aguarda run verde)

As metas listadas são do business da sessão **ou** repo-wide (`business_id IS NULL`). Um business
vizinho **nunca** vê meta alheia. O `orWhereNull` do `buildMetasPayload` é **intencional** — um teste
ingênuo o leria como vazamento, e é por isso que ele é citado aqui explicitamente.

Âncora: [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md). Tenant de
teste é o fictício **98** ([ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) — nunca biz=4.

**Pronto quando:** com meta em `biz=A` e sessão em `biz=B`, o payload de B não contém a meta de A.

## UC-COPI-PAINEL-04 — O farol é do SERVIDOR, e "sem base pra julgar" é `cinza`, nunca vermelho
Status: ✅ (`Modules/Jana/Tests/Feature/FarolServerSideTest.php` — 2 casos citam este UC no título)

O Painel pinta cada meta com um farol. Quem decide a cor é `ApuracaoService::farol()`; a Page só
**consome** o campo que chega no payload. Quando não há base pra julgar — sem período, sem apuração,
período que não começou, ou período de duração zero — a resposta é `cinza`, que é o rótulo de *"não
dá pra dizer"*, e **não** vermelho.

Âncora: charter §Goals *"Farol calculado server-side … frontend só consome"* + §Anti-hooks
*"⛔ Cálculo de farol no frontend"*.

O quarto caso é a divergência consciente do port e está travada de propósito: no JS a duração zero
dividia por zero → `NaN`, o `NaN` falhava os dois `>=` e a meta caía em **vermelho**. Dado incoerente
não é "meta indo mal".

**Pronto quando:** os quatro casos devolvem `cinza`; as fronteiras `-5%` e `-15%` seguem verde/amarelo/
vermelho; e `Index.tsx` **não** contém `function calcularFarol` (só o leitor `farolDaMeta`).

## UC-COPI-PAINEL-05 — Empty state declara ausência (o estado real de 100% dos tenants)
Status: 🧪 (`PainelContratoTest` — copy lida do contrato, não do `.tsx`; aguarda run verde)

Sem metas cadastradas, o Painel diz **"Nenhuma meta cadastrada ainda"** e oferece **"Conversar com
a Jana"** — nunca lista vazia muda nem zeros. Medido em 2026-08-09: é o estado real de **100% dos
tenants**, então não é borda, é o caminho principal.

Âncora: contrato `painel-metas-vazio` (copy literal + estados `vazio|com-metas|aguardando-apuracao`)
+ charter §UX targets (`EmptyState` shared component).

**Pronto quando:** 0 metas → as duas strings aparecem sob `data-contract="painel-metas-vazio"`.

## UC-COPI-PAINEL-06 — Meta sem apuração não vira zero
Status: 🧪 (`PainelContratoTest` — idem; aguarda run verde)

Meta cadastrada e ainda **não apurada** mostra **"Aguardando apuração…"**. Âncora: contrato
`painel-meta-apurando`, cujo `_papel` é literal — *"não pode mostrar zero como se fosse resultado"*.

**Pronto quando:** meta sem `MetaApuracao` → a copy aparece e nenhum valor numérico a substitui.

## UC-COPI-PAINEL-07 — Série curta declara ausência em vez de desenhar zero
Status: 🧪 (`PainelContratoTest` — idem; aguarda run verde)

Meta sem série temporal mostra **"Sem histórico"** em vez de desenhar uma linha no zero. Âncora:
contrato `painel-meta-sem-historico` — *"ausência de dado se declara, não se desenha como zero"*.

**Pronto quando:** meta com 0 apurações → a copy aparece e nenhum gráfico é renderizado.

## UC-COPI-PAINEL-08 — Enquanto o cockpit não chega, a tela NÃO mostra zero
Status: 🧪 (`PainelContratoTest` — 4 asserções + 2 controles negativos; bite provado: remover o skeleton do Faturamento reprova o caso. Aguarda run verde **e** o screenshot F1.5)

`coworkAggregates` é deferida (`IndexController:47`). Até resolver, o Painel deve declarar
**carregando** — não pintar `R$ 0`, delta nulo e sparkline vazia como se fossem resultado.

Âncora: é a MESMA regra dos UC-06/07, escrita no contrato para metas e válida para o cockpit
(*"não pode mostrar zero como se fosse resultado"* · *"ausência se declara, não se desenha como
zero"*), somada ao protótipo, que resolve isso com `JmPainelSkeleton` (`jana-merge.jsx`, âncora de
símbolo) e 6 classes `.jm-sk-*`.

**Pronto quando:** com `coworkAggregates` ausente no first render, o Painel mostra estado de
carregamento e **nenhum** `R$ 0`/`0%` derivado de `?? 0`; e ao chegar a prop, os valores reais aparecem.

## UC-COPI-PAINEL-09 — As 5 âncoras do contrato existem, e a ordem é respeitada
Status: 🧪 (`PainelContratoTest` — 5 âncoras + ordem como subsequência; aguarda run verde)

As 5 âncoras `data-contract` existem no `Index.tsx` e a ordem declarada
`[painel-metas-header, painel-cta-conversar, painel-metas-vazio]` é subsequência da ordem de arquivo.

_A ordem **mudou em 2026-08-17** e este UC acompanha. Era `[cta, metas-header, metas-vazio]`, escrita
quando a âncora `painel-cta-conversar` vivia dentro do `ProximaAcaoCard` — bloco declarado "Mock pra
demo" no próprio código, **acima** do cabeçalho de METAS. [W] mandou a tela ficar igual ao protótipo;
o mock saiu e o bloco METAS subiu pra posição da âncora (logo após os KPIs), então o cabeçalho passa a
vir antes do CTA. A **copy** de cada seção não mudou — só o hospedeiro do CTA e a ordem, que é
derivada do layout. O UC não é o dono do número: quem manda é o `jana-painel.contract.json`, e este
texto o cita._

Âncora: o próprio contrato de tela ([ADR 0286](../../../../memory/decisions/0286-contrato-de-tela.md)).

**Pronto quando:** `npm run contrato:check -- prototipo-ui/contrato/jana-painel.contract.json` sai 0.


## UC-COPI-PAINEL-10 — "Configurar" abre drawer, e o drawer não promete o que o servidor não cumpre
Status: 🧪 (`PainelContratoTest` — 6 asserções + 2 controles negativos; bite provado em 4 vetores. Aguarda run verde **e** o screenshot F1.5)

O botão **Configurar** do `JanaAreaHeader` era clicável, sem rota e sem `disabled` — uma das duas
promessas que o contrato manteve deliberadamente **fora** dele (*"pinar uma promessa é congelá-la"*).
Agora abre `_components/JanaConfigDrawer.tsx`.

Âncora: `prototipo-ui/cowork/jana-merge.jsx` §`JmConfigDrawer` — âncora de SÍMBOLO
(`grep -n "JmConfigDrawer" prototipo-ui/cowork/jana-merge.jsx`).

**A divergência vs a âncora é o ponto do caso.** Medido em 2026-08-17, o protótipo oferece quatro
coisas que o servidor não honra — e portá-las reintroduziria a classe que este contrato já barrou:

| a âncora oferece | o que existe |
|---|---|
| 6 toggles de análise | a tela renderiza **4** cards (`inad`/`fat`/`conc`/`metodos`); churn, frota e cheques são a ordem 7 do mapa (*"fonte de dado que não existe"*) |
| "Enviar brief todo dia" + hora | o brief é gerado server-side (`BriefingAgent`); nenhum cron lê o `localStorage` deste navegador |
| "Versão em áudio" (TTS) | não existe — o próprio protótipo diz que *"entra na M2"* |
| retenção *"ela esquece sozinha"* | `jana:retention-purge` foi **descartado por [W]** (*"num ERP não se apaga PII"*) |

Fica só o que é verdade **e** é de fato local: **quais análises aparecem no painel**. Esse toggle não
mente porque não promete cálculo — o aggregator apura as quatro numa consulta só, e o drawer diz isso
em letra. Preferência que vale pra empresa toda aponta pro dono server-side que já existe
(`PATCH /ia/alertas/config` → `business.essentials_settings.alertas`, per-business, Tier 0) em vez de
ganhar um segundo dono aqui.

Persistência em `localStorage['oimpresso.jana.cfg']` — prefixo `oimpresso.jana.*`, canon do charter
irmão (`Chat.charter.md` §Goals + §Anti-hooks *"❌ sessionStorage"*). A escrita preserva as chaves que
não são nossas (o protótipo grava `brief`/`pro`/`retencao` na mesma chave).

**Pronto quando:** o botão abre o drawer; os 4 toggles escondem/mostram o card correspondente e
sobrevivem ao reload; esconder as quatro mostra o estado que diz como voltar; e **nenhum** toggle de
brief/áudio/retenção existe.

_Por que a asserção é de ARQUIVO: mesmo motivo do UC-08 — o defeito é de render/promessa e o Pest não
monta React. E por que ela é ESTRUTURAL (contagem de `<Switch`, não busca por "Frota"): a prosa do
próprio componente **registra** por que aqueles toggles não entraram, então `not->toContain('Frota')`
passaria só por acidente de capitalização e quebraria quando o comentário fosse reescrito — o
falso-positivo que o §5 2026-07-26 cataloga. Medido antes de fechar: `<Switch` 2→3 com um toggle novo;
entradas 4→5 com uma análise sem fonte._

## UC-COPI-PAINEL-11 — A meta abre na própria tela, e o drawer não projeta o futuro
Status: 🧪 (`PainelContratoTest` — 5 blocos de asserção + 1 controle negativo; aguarda run verde **e** o screenshot F1.5)

Clicar num card de meta abria `/ia/metas/{id}` — um `<Link>` que **tirava o usuário do Painel** rumo
a uma tela Blade. O `Index-visual-comparison.md` marcava isso como o maior buraco da tela (R5, ordem
1). Agora o card é um botão que abre `_components/JanaMetaDrawer.tsx`: Situação (realizado · alvo ·
% do alvo · delta vs a janela anterior), Série de até 12 janelas em barras, e "De onde vem esse
número". O caminho pra tela própria **não se perdeu** — virou "Abrir a meta" no rodapé do drawer.

Âncora: `prototipo-ui/cowork/jana-merge.jsx` §`JmMetaDrawer` — âncora de SÍMBOLO
(`grep -n "JmMetaDrawer" prototipo-ui/cowork/jana-merge.jsx`).

**A divergência vs a âncora é o ponto do caso, de novo.** O protótipo mostra uma **projeção de
fechamento** na Situação, calculada **no cliente**: `jmMeta()` faz `atual × 1.3` quando a meta
acumula e extrapola a tendência das últimas 4 janelas quando é média/taxa. Portar isso repetiria
letra por letra o defeito que este mesmo contrato já travou no UC-04 — o farol é do servidor porque
veredito é do servidor, e projeção é veredito sobre o futuro. No lugar dela vai **"% do alvo"**, que
é aritmética sobre dois números já exibidos. Pelo mesmo motivo a `nota` por meta ficou de fora: o
payload não tem o campo.

**Pronto quando:** o card não contém mais o link que sai da página; o clique abre o drawer; o rodapé
preserva `/ia/metas/{id}`; a Situação tem **três** números e nenhum é projeção; e a fonte citada é
`ApuracaoService::farol`, nunca o nome que a âncora usa.

_Por que a asserção é de ARQUIVO e ESTRUTURAL: mesmo motivo dos UC-08 e UC-10 — o Pest não monta
React, e buscar a palavra "Projeção" proibiria o próprio comentário que registra a decisão. Duas
asserções minhas caíram exatamente nessa armadilha **na escrita** (`Ver detalhe` e o nome errado da
classe, ambos vivos em comentário) e foram trocadas por estruturais antes de rodar: contagem de
`<Numero rotulo=` (3) e ausência do literal do link._

## UC-COPI-PAINEL-12 — a ação sugerida vira decisão registrada, e a prévia é do SERVIDOR
Status: 🧪 (`PainelContratoTest` — 4 `it()`: 1 de arquivo + 3 de runtime, com 2 controles negativos; aguarda run verde **e** o screenshot F1.5)

Todo CTA da seção "Ações que … sugere" era **decorativo** — `title="(HITL — em breve V2)"`, zero
`onClick`. Era a **ordem 1** do `Index-visual-comparison.md` (§Resumo) e a única linha cuja trava
dizia, em letra, *"backend — sem ele, todo CTA da seção é decorativo"*. Agora o CTA abre
`_components/JanaAcaoModal.tsx`: prévia do que a ação faria + **Aprovar**, que grava em
`jana_acao_aprovacoes`.

Âncora: `prototipo-ui/cowork/jana-merge.jsx` §`JmAcaoModal` — âncora de SÍMBOLO
(`grep -n "JmAcaoModal" prototipo-ui/cowork/jana-merge.jsx`).

**A divergência vs a âncora é o ponto do caso, pela terceira vez — e agora no eixo da PRÉVIA.** O
`JmAcaoModal` traz as 4 prévias em **texto fixo**, com números do Martinho (`biz=164`), citando
`Analise*Service` que não existem no repo (re-medido 2026-08-17 no espelho **e** no Cowork vivo).
Portar isso repetiria letra por letra o que o UC-04 travou no farol e o charter travou na fonte do
drill: **veredito nasce no servidor**. A prévia vem de `GET /ia/acoes/{key}/previa`, lida do mesmo
agregado que pinta a linha (`SellsCockpitAggregator::buildInsightsAggregates`) — prévia e linha não
podem divergir.

**O escopo parou antes do envio, e o botão fala a verdade sobre isso.** Este passo registra a
aprovação; nada sai. Por isso os 5 rótulos mudaram:

| era (botão morto) | é (abre o modal) | chave no backend |
|---|---|---|
| Disparar | **Revisar régua** | `regua-whatsapp` |
| Preparar | **Revisar proposta** | `negociar-top` |
| Investigar | **Revisar recorte** | `investigar-ticket` |
| Detalhe | **Revisar leitura** | `pix-adocao` |
| Lembrar | **Revisar lembrete** | `preventivo-pendentes` |

Manter "Disparar" abrindo um modal que não dispara trocaria botão morto por botão que **mente** — e
o §Anti-hooks *"prometer no botão o que a rota não entrega"* vale igual pros dois.

**Pronto quando:** o CTA abre o modal; cada rótulo do `.tsx` tem chave em `AcaoHitlService::ACOES`
(regra só no front morreria em 404 — botão morto com um passo a mais); chave desconhecida dá **404**
em prévia e em aprovar; o que fica gravado em `previa` é o texto do **servidor**, mesmo com o cliente
mandando outro; e o registro nasce com o `business_id` da **sessão**, invisível fora do tenant.

⚠️ **O gate de pixel não defende este caso — medido em 2026-08-18.** A tela `Jana` está no manifesto
do visreg e o job roda de verdade (12 min), mas o `VisregTenantSeeder` semeia **zero** `transactions`;
sem venda, `acoes` sai vazio e a seção não entra no DOM. O #5895 mudou 5 rótulos e acrescentou um modal
com o pixel-diff **verde e cego**. Por isso as asserções deste UC são de arquivo + runtime, e não
"o screenshot bateu": aqui o screenshot não tem o que bater. Detalhe em
[`Index-visual-comparison.md` §R9](../../../memory/requisitos/Jana/Index-visual-comparison.md).

_Por que a 1ª asserção é de ARQUIVO e ESTRUTURAL: mesmo motivo dos UC-08/10/11 — o Pest não monta
React, e `not->toContain('HITL — em breve V2')` **falharia**, porque a frase está viva no comentário
que registra o que saiu (o falso-positivo do §5 2026-07-26, que já mordeu esta suíte duas vezes na
escrita). O que morde é a FORMA do botão morto (`title={\`${a.cta.label}…\`}`) e a **contagem** de
`cta: { label: '` batendo com `count(ACOES)`. As outras três são de RUNTIME porque o defeito que
importa é de comportamento — prévia forjada e vazamento de tenant não se veem no `.tsx`._

## UC-COPI-PAINEL-13 — Churn ouro: o recorte é RELATIVO, porque o piso do protótipo é premissa de outro tenant
Status: 🧪 (`PainelContratoTest` — 2 `it()`: 1 de runtime com controle negativo + 1 estrutural sobre TODAS as fontes do drill; aguarda run verde **e** o screenshot F1.5)

Quinta análise do Painel, e a primeira depois das quatro que nasceram juntas. Mostra os **5
clientes de maior valor acumulado entre os que não compram há mais de 90 dias** — a lista pra
quem alguém deveria ligar hoje.

Âncora: `prototipo-ui/cowork/jana-merge.jsx` §`JmDrillDrawer` (`grep -n "churn:" prototipo-ui/cowork/jana-merge.jsx`
→ :559 o toggle, :648 a fonte).

**A divergência vs a âncora é o ponto do caso, pela quarta vez — e agora no eixo do RECORTE.** O
protótipo define churn ouro por um **piso absoluto em reais** (o valor literal está em
`jana-merge.jsx` :648 — aqui não se repete: BRL não entra no git, Tier 0). Esse número não
é errado — ele é **de outro tenant**. O protótipo foi desenhado sobre o movimento do Martinho
(`biz=164`, mecânica pesada de caminhão: ticket alto, poucos clientes). Aplicado à ROTA LIVRE
(`biz=4`, vestuário) o mesmo piso devolveria lista vazia todos os dias, e **card que nunca tem
linha é indistinguível de card quebrado** — o usuário não sabe se ninguém sumiu ou se a tela
parou. Importar o número seria a lápide §5 2026-07-16 em letra: *"que premissa do modelo DELES
sustenta essa solução, e ela vale AQUI?"*.

O recorte aqui é **relativo**: os 5 maiores LTV **entre** os inativos, sem piso. Funciona em
qualquer vertical sem número mágico por tenant, e é exatamente o que o `JanaDrillDrawer` diz ao
usuário em "De onde vem esse número" — sem prometer uma `AnaliseChurnService` que não existe.

**Venda sem cliente identificado fica de fora, e isso também é decisão.** No card de concentração
(UC anterior) o balde `Cliente padrão` informa — ele mostra quanto da receita não tem nome. Aqui
não: a lista existe pra **alguém ligar**, e balde anônimo não vira telefonema. Por isso o `join` é
INNER e o nome vazio é filtrado no SQL.

**Pronto quando:** um cliente parado há 200 dias aparece; um cliente que comprou ontem **não**
aparece nem valendo 5× mais (é o controle negativo — sem ele, um bug que ignorasse a data passaria
verde e o card viraria "top clientes" com outro título); `ltv`, `diasInativo` e `ultimaCompra` são
medidos, não estimados; o recorte é do tenant pedido e de mais nenhum (ADR 0093); e **todo**
`metodo:` declarado pelo drill drawer existe de fato no aggregator.

⚠️ **O gate de pixel não defende este caso — pela mesma razão medida no UC-12.** O
`VisregTenantSeeder` semeia zero `transactions`; sem venda não há inativo, o card renderiza o
empty state e o pixel-diff fica verde e cego. As asserções são de runtime + estrutura, não "o
screenshot bateu".

_Por que a 2ª asserção varre TODAS as fontes e não só a do churn: o defeito que o
`JanaDrillDrawer` existe pra evitar é de CLASSE — "o drawer promete um método que não existe" —, e
travar só a instância de hoje deixaria a próxima entrar igual. `method_exists` sobre a classe real
é comportamento, não presença de string: um `metodo:` bem escrito apontando pra nome inventado
reprova. Tem controle positivo (`expect($m[1])->not->toBeEmpty()`) porque regex que para de casar
devolveria lista vazia e o caso viraria carimbo — lápide §5 2026-08-01._

## UC-COPI-PAINEL-14 — O rótulo do KPI declara a janela que o dado TEM
Status: 🧪 (`PainelContratoTest` — 2 `it()`: 1 estrutural com controle negativo + 1 de runtime que prova a contenção; aguarda run verde)

O card dizia **"Receita mês"** e mostrava `sparkSum` — a soma da **sparkline**, que é
`whereBetween(transaction_date, [hoje-29, hoje 23:59])`: **30 dias deslizantes**, não o mês
corrente. No dia 21, isso cobre 23/jul a 21/ago. Os dois só coincidem no dia 30 ou 31.

**De onde veio a palavra errada — medido em 2026-08-21.** A âncora oficial desta tela
(`prototipo-ui/cowork/jana-merge.jsx`, o `related_prototype` do charter) **não tem este KPI**. O
rótulo veio de `chat-jana.jsx` :87 — o protótipo que o §5 de 2026-08-10 declarou **NÃO-âncora**
("desenha o cockpit de cobrança, não este Painel"). E lá o rótulo é **coerente**, porque o delta ao
lado é `"-68% vs mai/25"`: mês contra mês. Aqui herdou-se a palavra sem a semântica — o dado é de
30 dias e o delta é diário. É a lápide §5 2026-07-16 em letra: *"que premissa do modelo DELES
sustenta essa solução, e ela vale AQUI?"*.

**Por que nenhum gate pegou.** A tela tem **6 âncoras `data-contract`, todas sobre Metas** — nenhuma
cobre os KPIs. O `contrato-de-tela` valida copy e ordem de 1 dos 6 blocos e conclui "✅ limpo". O
defeito morava fora do alcance dele. Fechar essa lacuna exige âncora no `KpiCard` compartilhado
(interface fechada, 30+ telas consumindo) — **escopo separado**, não este PR.

**Três correções, e só uma toca número:**

| o quê | era | é | mexe em valor? |
|---|---|---|---|
| rótulo do card + do skeleton | `Receita mês` | `Receita 30 dias` | não |
| rótulo do delta | `vs ontem` | `hoje vs ontem` | não |
| fallback | `sparkSum \|\| faturadoHoje` | `sparkSum` | **não — era inalcançável** |

**O fallback era código morto, não robustez.** As duas consultas têm filtros idênticos
(`business_id · type=sell · status=final · sub_type NULL`) e a janela da série vai até o **fim de
hoje**, então `faturadoHoje ⊆ sparkSum`. Se houve venda hoje, `sparkSum > 0` e o `||` nunca dispara;
se não houve, ambos são 0 e o fallback devolvia o mesmo 0. O 2º `it()` prova essa contenção
semeando venda de hoje — e quebra se alguém encurtar a janela (`endOfDay` → `startOfDay`).

**Pronto quando:** card e skeleton dizem a mesma coisa (senão o rótulo antigo pisca enquanto a prop
deferida não chega); o valor vem da série sem fallback; o delta declara a própria janela; e a FORMA
do rótulo antigo (`label="Receita mês"`) não volta.

_A asserção usa `label="…"` com o atributo, nunca a prosa: `not->toContain('Receita mês')` FALHARIA,
porque a frase está viva no comentário que registra o que saiu — o falso-positivo do §5 2026-07-26,
que já mordeu esta suíte duas vezes._

### Decisão pendente de [W] — 30 dias ou mês-calendário?

Este PR fez o **rótulo dizer a verdade sobre o dado**. O caminho inverso — passar o cálculo a
mês-calendário para casar a palavra antiga — **mexe em valor exibido** e cai na regra mestre de
VALOR (provar por dois caminhos + apresentar antes→depois). Também quebraria a sparkline, que é
deslizante por natureza e alimenta o mesmo card.

O protótipo resolve isso com **seletor de período** (`JM_PERIODOS`, 3 janelas) — registrado no
inventário como `❌ precisa de backend`. Com ele, "mês" volta a ter referente. Sem ele, qualquer
rótulo temporal fixo é escolha arbitrária, e a honesta é a que descreve o recorte real.

## Nota do conserto do UC-COPI-PAINEL-08 (2026-08-17)

`_components/JanaCockpitSkeleton.tsx` (novo, ancorado em `jana-merge.jsx` §`JmPainelSkeleton`) +
`carregandoCockpit = coworkAggregates === undefined` no `JanaCockpit`. Os `?? 0` **ficaram** — são
eles que impedem o `TypeError` e mantêm válida a entrada `Jana/Index` na
`DEFER_GUARD_ONLY_ALLOWLIST`; o que mudou é o **render**.

Escopo medido: só os **2** KPIs que dependem da prop deferida (Receita 30 dias · PIX hoje) trocam de
card. `A receber vencido` e `Ticket médio` vêm de `insightsAggregates` (eager) e **não** podem
sumir — há controle negativo no teste.

_Rótulos atualizados em 2026-08-17 (`Faturamento mês` → `Receita mês`, `Inadimplência total` →
`A receber vencido`) no alinhamento de copy com a âncora. Só a PALAVRA mudou: a prop de origem, a
deferição e o escopo do controle negativo são os mesmos. O controle negativo do teste foi
reapontado no MESMO diff — `not->toMatch` com label extinto passa vazio (LC-11)._

De quebra, a série ganhou o terceiro estado que faltava: antes, `sparkline.length === 0` dizia
*"Carregando sparkline…"* — então um business **sem vendas** ficava "carregando" pra sempre, e
carregando-de-verdade era indistinguível de vazio. Agora: carregando → skeleton; vazio real →
**"Sem histórico"**.

**Por que nenhum gate tinha pego.** `InertiaDeferredFrontendGuardTest` tem `Jana/Index` na
`DEFER_GUARD_ONLY_ALLOWLIST` com razão **verdadeira**: o `JanaCockpit` guarda com `?.`/`?? 0`/`?? []`,
então não há `TypeError` nem tela branca. O guard mede **"quebra?"** — e a resposta é não. Ninguém
media **"declara carregando?"**. A allowlist **não deve ser mexida** por causa disto: ela é verdadeira
para o que aquele gate afirma.

---

## Decisões pendentes de [W] que travam o ciclo desta tela

Medido em 2026-08-17 com `node scripts/governance/ciclo-completo.mjs` — que é o **dono do número**;
o placar abaixo é retrato daquele dia, re-rode em vez de confiar nele. O PR do UC-08 levou a tela de
**1/6** a **3/6** (`casos` e `teste` fecharam). O PR do UC-10 **não move o placar**: ele acrescenta
caso e teste a peças que já estavam fechadas. As 3 que faltam seguem sendo decisão [W]:

- ⚖️ **`related_prototype`** — o check `pt_declarado` só casa `PT-0X`, e o campo vale
  `jana-merge.jsx`. Mantê-lo reprova `pt_declarado` e `golden_live` **para sempre**; trocar por
  `n/a (herda PT-04 Dashboard)` ganha o check e **perde** a proveniência declarada do drill-down
  (`JmDrillDrawer` · `JM_KPI_DRILL`). É proveniência de tela, não wiring — decisão [W].
  _Medido 2026-08-17: a 3ª saída (`jana-merge.jsx (PT-04 Dashboard)`, que satisfaria os dois) **não
  funciona** — o parêntese entra no path e o `ancora.mjs` deixa de ler o arquivo. Era um falso dilema
  aparente; a medição fechou a porta._
- ⚖️ **Golden PT-04 `draft` → `live`** — aprovação de **screenshot** (gate F1.5, [ADR 0107](../../../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md)).
  Nenhum código resolve, e trava **3 telas**, não só esta.
- ⚖️ **"Dashboard" × "Painel"** — a aba se chama Painel e a rota é `/ia`, mas título, breadcrumb e o
  nome do componente exportado ainda dizem Dashboard.
- ⚖️ **O botão "Exportar (em breve)"** — clicável, sem `disabled` e sem rota. Some, vira `disabled`
  com o motivo, ou entrega? Enquanto não decidido **não entra no contrato** — pinar uma promessa é
  congelá-la. _(Eram **dois**; **Configurar** saiu desta lista em 2026-08-17 — entregou, ver UC-10.
  A pergunta segue idêntica para o que sobrou.)_
- ⚖️ **Projeção de fechamento das metas** — a âncora mostra "<valor> no fechamento" em cada card. Não
  foi portada porque projetar no frontend é o UC-04 ao contrário (ver UC-11). Se vira produto, o dono
  é `ApuracaoService` — onde `farol` já mora — e a tela só consome. É backend, não wiring.
- ⚖️ **Seletor de período nas Metas** — a âncora tem 3 janelas clicáveis. `buildMetasPayload` carrega
  só `periodoAtual`, então não há o que filtrar no cliente. Backend.
- ⚖️ **Chips do brief** — existem **três**, e nenhum tem `onClick` (medido 2026-08-17). Ligá-los pra
  navegar sem semear a pergunta trocaria botão morto por botão que mente: o rótulo promete um assunto
  ("Disparar régua WhatsApp pros N atrasados") e o destino seria uma conversa em branco —
  `ChatController@novaConversa` não aceita pergunta inicial. Backend + Page.
- ⚖️ **Brief diário, áudio e retenção como configuração de verdade** — o drawer do UC-10
  deliberadamente **não** os oferece, porque hoje o servidor não honra nenhum dos três (medição na
  tabela do UC-10). Se devem virar config real, o caminho é backend — estender o dono que já existe
  (`PATCH /ia/alertas/config`, per-business) ou dar chave própria ao brief. É produto, não wiring.
