---
id: resources-js-pages-jana-index-casos
casos: Jana Painel · metas ativas · farol server-side · cockpit deferido · /ia
irmaos: Index.charter.md (lei) · memory/requisitos/Jana/RUNBOOK-index.md (runbook) · governance/design/contracts/jana-painel.contract.json (contrato visual)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-09-21"
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
> `prototipo-ui/cowork/Wagner/jana-merge.jsx` (resolva sempre por `node scripts/design/ancora.mjs Jana/Index`,
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

> **Revalidação 2026-08-26 — PR #6298 (DS onda 1), head `b011221e50`.** O `.tsx` migrou 26
> widgets pro Design System; os UCs continuam descrevendo o mesmo comportamento. Recibo: o
> `PainelContratoTest` rodou na lane `PHP / Pest (Jana · MySQL)` **neste sha** (run 32975514598)
> com **20 de 20 casos, 135 asserções, 0 skip** — primeira vez que os 20 rodam contra o `.tsx`
> migrado. O ponto de risco era a âncora `painel-metas-vazio`, que trocou de hospedeiro (saiu do
> `<p>` do título e foi pro `<Card>` que envolve o `EmptyState`); UC-JPAIN-05 (copy + âncora) e
> UC-JPAIN-09 (5 âncoras + ordem) são exatamente quem mede isso, e passaram.
>
> Os `🧪` **não** viraram `✅` de propósito: o G-7 lê o manifesto commitado, publicado pelo
> `casos-results-publish`. Lane verde citada em prosa não é veredito capturado.

## Revalidação de 2026-08-31 — o `last_run` sobe porque o conjunto de KPIs mudou

O G-6 mede data-git: o `JanaCockpit.tsx` mudou depois do `last_run` de 08-28, então o `stale:`
é correto e se paga aqui. O que mudou, e o efeito por UC:

| mudança | UCs afetados |
|---|---|
| o KPI `PIX hoje` saiu; `KpiGrid` foi de `cols={4}` pra `cols={3}` | **UC-JPAIN-18** (novo — é ele que trava o conjunto e a ordem) |
| o skeleton do `PIX hoje` saiu junto | **UC-JPAIN-08** — de 2 KPIs deferidos pra **1**; a asserção do PIX saiu no MESMO diff, e o texto do caso foi corrigido abaixo |
| `pixHoje`/`pixHojeTotal` **ficaram** — a ação "PIX adoção" e a linha do brief seguem consumindo | **UC-JPAIN-02** — **nenhuma** mudança: `coworkAggregates` continua deferida e continua com 5 consumidores no componente (`faturadoHoje`, `pixHoje`, `deltaRev`, `deltaTicket`, `sparkline`). A contagem 4 eager + 1 deferida está intacta |
| comentários que afirmavam `frota` na âncora e `1 de 4 emphasize` | **nenhum** — prosa, não comportamento; corrigidos com medição datada porque comentário falso é instrução ativa pra próxima sessão |

Nenhum `Status:` mudou por esta revalidação. O UC-JPAIN-18 nasce `🧪`: o teste existe e o
extrator dele foi provado contra fixtures **e** contra o arquivo real, mas quem dá veredito é a
lane `PHP / Pest (Jana · MySQL)` — e o `🧪→✅` só vem pelo manifesto do `casos-results-publish`,
nunca por prosa.

⚠️ **O que ainda não foi feito, e é gate de [W]:** a `visual-regression` vai acusar, porque o
grid mudou de 4 pra 3 colunas. **A baseline não foi regravada** — regravar exige a aprovação
visual F1.5, que é decisão [W], não do agente (§5 2026-08-24: regravar baseline pra fechar
divergência determinística sem entender a causa é a lápide, e aqui a causa é conhecida e
intencional, mas a aprovação continua sendo dele).

## UC-JPAIN-01 — A rota `/ia` abre o Painel (200 + componente)
Status: 🧪 (`Modules/Jana/Tests/Feature/PainelContratoTest.php` — cita o UC; aguarda run verde na lane MySQL)

Usuário autenticado do business abre `GET /ia`. O grupo `/ia` garante auth; o Controller renderiza
o componente Inertia `Jana/Index`. Âncora: SPEC US-COPI-148 (fusão numa tela única; `/ia/dashboard`
responde 301 pra cá).

**Pronto quando:** GET `/ia` autenticado → 200 e `assertInertia(component 'Jana/Index')`.

## UC-JPAIN-02 — Contrato de props: 4 eager + 1 deferida
Status: 🧪 (`PainelContratoTest` — `missing(coworkAggregates)` + as 4 eager; aguarda run verde)

A tela recebe `metas`, `sellKpis`, `insightsAggregates` e `janaContext` de forma **eager**, e
`coworkAggregates` de forma **deferida**. A separação não é acidente: o HOTFIX [W] de 2026-05-25
(pós-PR #1547) fixou que `metas` **não** pode ser deferida, porque a Page lê `metas.length` direto.

Âncora: charter §Goals + o comentário canon no `IndexController::index()`.

**Pronto quando:** as 4 props eager chegam no first render e `coworkAggregates` **não** está entre elas.

## UC-JPAIN-03 — Escopo `business_id`, incluindo o `orWhereNull` intencional (Tier 0)
Status: 🧪 (`PainelContratoTest` — `?business_id=999` ignorado; aguarda run verde)

As metas listadas são do business da sessão **ou** repo-wide (`business_id IS NULL`). Um business
vizinho **nunca** vê meta alheia. O `orWhereNull` do `buildMetasPayload` é **intencional** — um teste
ingênuo o leria como vazamento, e é por isso que ele é citado aqui explicitamente.

Âncora: [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md). Tenant de
teste é o fictício **98** ([ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) — nunca biz=4.

**Pronto quando:** com meta em `biz=A` e sessão em `biz=B`, o payload de B não contém a meta de A.

## UC-JPAIN-04 — O farol é do SERVIDOR, e "sem base pra julgar" é `cinza`, nunca vermelho
Status: 🧪 (`Modules/Jana/Tests/Feature/FarolServerSideTest.php` — 2 casos citam este UC no título) — o verde volta quando o manifesto G-7 capturar o veredito (a lane passou a emitir JUnit em 2026-08-24); ate la, "teste cita o UC, sem veredito" e o status honesto

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

## UC-JPAIN-05 — Empty state declara ausência (o estado real de 100% dos tenants)
Status: 🧪 (`PainelContratoTest` — copy lida do contrato, não do `.tsx`; aguarda run verde)

Sem metas cadastradas, o Painel diz **"Nenhuma meta cadastrada ainda"** e oferece **"Conversar com
a Jana"** — nunca lista vazia muda nem zeros. Medido em 2026-08-09: é o estado real de **100% dos
tenants**, então não é borda, é o caminho principal.

Âncora: contrato `painel-metas-vazio` (copy literal + estados `vazio|com-metas|aguardando-apuracao`)
+ charter §UX targets (`EmptyState` shared component).

**Pronto quando:** 0 metas → as duas strings aparecem sob `data-contract="painel-metas-vazio"`.

## UC-JPAIN-06 — Meta sem apuração não vira zero
Status: 🧪 (`PainelContratoTest` — idem; aguarda run verde)

Meta cadastrada e ainda **não apurada** mostra **"Aguardando apuração…"**. Âncora: contrato
`painel-meta-apurando`, cujo `_papel` é literal — *"não pode mostrar zero como se fosse resultado"*.

**Pronto quando:** meta sem `MetaApuracao` → a copy aparece e nenhum valor numérico a substitui.

## UC-JPAIN-07 — Série curta declara ausência em vez de desenhar zero
Status: 🧪 (`PainelContratoTest` — idem; aguarda run verde)

Meta sem série temporal mostra **"Sem histórico"** em vez de desenhar uma linha no zero. Âncora:
contrato `painel-meta-sem-historico` — *"ausência de dado se declara, não se desenha como zero"*.

**Pronto quando:** meta com 0 apurações → a copy aparece e nenhum gráfico é renderizado.

## UC-JPAIN-08 — Enquanto o cockpit não chega, a tela NÃO mostra zero
Status: 🧪 (`PainelContratoTest` — 4 asserções + 2 controles negativos; bite provado: remover o skeleton do Faturamento reprova o caso. Aguarda run verde **e** o screenshot F1.5)

`coworkAggregates` é deferida (`IndexController:47`). Até resolver, o Painel deve declarar
**carregando** — não pintar `R$ 0`, delta nulo e sparkline vazia como se fossem resultado.

Âncora: é a MESMA regra dos UC-06/07, escrita no contrato para metas e válida para o cockpit
(*"não pode mostrar zero como se fosse resultado"* · *"ausência se declara, não se desenha como
zero"*), somada ao protótipo, que resolve isso com `JmPainelSkeleton` (`jana-merge.jsx`, âncora de
símbolo) e 6 classes `.jm-sk-*`.

**Pronto quando:** com `coworkAggregates` ausente no first render, o Painel mostra estado de
carregamento e **nenhum** `R$ 0`/`0%` derivado de `?? 0`; e ao chegar a prop, os valores reais aparecem.

## UC-JPAIN-09 — As 5 âncoras do contrato existem, e a ordem é respeitada
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

**Pronto quando:** `npm run contrato:check -- governance/design/contracts/jana-painel.contract.json` sai 0.


## UC-JPAIN-10 — "Configurar" abre drawer, e o drawer não promete o que o servidor não cumpre
Status: 🧪 (`PainelContratoTest` — 6 asserções + 2 controles negativos; bite provado em 4 vetores. Aguarda run verde **e** o screenshot F1.5)

O botão **Configurar** do `JanaAreaHeader` era clicável, sem rota e sem `disabled` — uma das duas
promessas que o contrato manteve deliberadamente **fora** dele (*"pinar uma promessa é congelá-la"*).
Agora abre `_components/JanaConfigDrawer.tsx`.

Âncora: `prototipo-ui/cowork/Wagner/jana-merge.jsx` §`JmConfigDrawer` — âncora de SÍMBOLO
(`grep -n "JmConfigDrawer" prototipo-ui/cowork/Wagner/jana-merge.jsx`).

**A divergência vs a âncora é o ponto do caso.** Medido em 2026-08-17, o protótipo oferece quatro
coisas que o servidor não honra — e portá-las reintroduziria a classe que este contrato já barrou:

| a âncora oferece | o que existe |
|---|---|
| 6 toggles de análise | a tela renderiza **5** cards (`inad`/`fat`/`conc`/`metodos`/`churn`); frota e cheques são a ordem 7 do mapa (*"fonte de dado que não existe"*) — e a de **cheques** foi medida em 2026-08-31 e **confirmada ausente**, com as três fontes na §Pendência do UC-JPAIN-18 |
| "Enviar brief todo dia" + hora | o brief é gerado server-side (`BriefingAgent`); nenhum cron lê o `localStorage` deste navegador |
| "Versão em áudio" (TTS) | não existe — o próprio protótipo diz que *"entra na M2"* |
| retenção *"ela esquece sozinha"* | `jana:retention-purge` foi **descartado por [W]** (*"num ERP não se apaga PII"*) |

Fica só o que é verdade **e** é de fato local: **quais análises aparecem no painel**. Esse toggle não
mente porque não promete cálculo — o aggregator apura as cinco numa consulta só, e o drawer diz isso
em letra. Preferência que vale pra empresa toda aponta pro dono server-side que já existe
(`PATCH /ia/alertas/config` → `business.essentials_settings.alertas`, per-business, Tier 0) em vez de
ganhar um segundo dono aqui.

Persistência em `localStorage['oimpresso.jana.cfg']` — prefixo `oimpresso.jana.*`, canon do charter
irmão (`Chat.charter.md` §Goals + §Anti-hooks *"❌ sessionStorage"*). A escrita preserva as chaves que
não são nossas (o protótipo grava `brief`/`pro`/`retencao` na mesma chave).

**Pronto quando:** o botão abre o drawer; os 5 toggles escondem/mostram o card correspondente e
sobrevivem ao reload; esconder as cinco mostra o estado que diz como voltar; e **nenhum** toggle de
brief/áudio/retenção existe.

_Por que a asserção é de ARQUIVO: mesmo motivo do UC-08 — o defeito é de render/promessa e o Pest não
monta React. E por que ela é ESTRUTURAL (contagem de `<Switch`, não busca por "Frota"): a prosa do
próprio componente **registra** por que aqueles toggles não entraram, então `not->toContain('Frota')`
passaria só por acidente de capitalização e quebraria quando o comentário fosse reescrito — o
falso-positivo que o §5 2026-07-26 cataloga. Medido antes de fechar: `<Switch` 2→3 com um toggle novo;
entradas 4→5 com uma análise sem fonte._

## UC-JPAIN-11 — A meta abre na própria tela, e o drawer não projeta o futuro
Status: 🧪 (`PainelContratoTest` — 5 blocos de asserção + 1 controle negativo; aguarda run verde **e** o screenshot F1.5)

Clicar num card de meta abria `/ia/metas/{id}` — um `<Link>` que **tirava o usuário do Painel** rumo
a uma tela Blade. O `Index-visual-comparison.md` marcava isso como o maior buraco da tela (R5, ordem
1). Agora o card é um botão que abre `_components/JanaMetaDrawer.tsx`: Situação (realizado · alvo ·
% do alvo · delta vs a janela anterior), Série de até 12 janelas em barras, e "De onde vem esse
número". O caminho pra tela própria **não se perdeu** — virou "Abrir a meta" no rodapé do drawer.

Âncora: `prototipo-ui/cowork/Wagner/jana-merge.jsx` §`JmMetaDrawer` — âncora de SÍMBOLO
(`grep -n "JmMetaDrawer" prototipo-ui/cowork/Wagner/jana-merge.jsx`).

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

> ⚠️ **Atualização de 2026-08-25 — a parte "nenhum é projeção" deste caso CADUCOU.** O texto acima
> fica como registro do que era verdade em 2026-08-17. A razão dele — *"projeção é veredito sobre o
> futuro; o protótipo calcula no CLIENTE (`atual × 1.3`)"* — continua **inteira**. O que mudou é o
> mundo: a onda 5 (#5923, 2026-08-18) passou a consumir `meta.projecao`, que o SERVIDOR calcula
> (`ApuracaoService::projecao`) e já mandava no payload de `/ia`. Não se calcula nada no cliente;
> consome-se, pela mesma porta do `farol`.
>
> Portanto, desde 08-18 o **drawer** mostra projeção (`JanaMetaDrawer.tsx` §Situação, *"No ritmo
> atual fecha em …"*), e a partir deste PR o **card** também (rodapé `jm-meta-f` da âncora). Quem ler
> a frase *"nenhum é projeção"* como contrato vigente erra — ela vale só para a decisão de 08-17.
>
> **A asserção do teste NÃO muda e continua correta:** ela conta `<Numero rotulo=` (3) na Situação, e
> a projeção não é um `<Numero>` nem no drawer nem no card — é linha de rodapé. O controle negativo
> (ausência do literal do link) idem. O teste segue provando o que promete; era a PROSA que tinha
> envelhecido, e é ela que este bloco conserta — precedência *teste > casos*, corrigindo o perdedor
> no mesmo PR.

- [BACKLOG] a projeção no RODAPÉ do card (não só no drawer) ainda não tem teste que a cite —
  vira UC quando ganhar um. Hoje é prosa honesta, sem gate: o `PainelContratoTest` cobre a
  Situação do drawer (contagem de `<Numero rotulo=`), e o rodapé do card está fora do alcance dele.

## UC-JPAIN-12 — a ação sugerida vira decisão registrada, e a prévia é do SERVIDOR
Status: 🧪 (`PainelContratoTest` — 4 `it()`: 1 de arquivo + 3 de runtime, com 2 controles negativos; aguarda run verde **e** o screenshot F1.5)

Todo CTA da seção "Ações que … sugere" era **decorativo** — `title="(HITL — em breve V2)"`, zero
`onClick`. Era a **ordem 1** do `Index-visual-comparison.md` (§Resumo) e a única linha cuja trava
dizia, em letra, *"backend — sem ele, todo CTA da seção é decorativo"*. Agora o CTA abre
`_components/JanaAcaoModal.tsx`: prévia do que a ação faria + **Aprovar**, que grava em
`jana_acao_aprovacoes`.

Âncora: `prototipo-ui/cowork/Wagner/jana-merge.jsx` §`JmAcaoModal` — âncora de SÍMBOLO
(`grep -n "JmAcaoModal" prototipo-ui/cowork/Wagner/jana-merge.jsx`).

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

## UC-JPAIN-13 — Churn ouro: o recorte é RELATIVO, porque o piso do protótipo é premissa de outro tenant
Status: 🧪 (`PainelContratoTest` — 2 `it()`: 1 de runtime com controle negativo + 1 estrutural sobre TODAS as fontes do drill; aguarda run verde **e** o screenshot F1.5)

Quinta análise do Painel, e a primeira depois das quatro que nasceram juntas. Mostra os **5
clientes de maior valor acumulado entre os que não compram há mais de 90 dias** — a lista pra
quem alguém deveria ligar hoje.

Âncora: `prototipo-ui/cowork/Wagner/jana-merge.jsx` §`JmDrillDrawer` (`grep -n "churn:" prototipo-ui/cowork/Wagner/jana-merge.jsx`
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

## UC-JPAIN-14 — O rótulo do KPI declara a janela que o dado TEM
Status: 🧪 (`PainelContratoTest` — 2 `it()`: 1 estrutural com controle negativo + 1 de runtime que prova a contenção; aguarda run verde)

O card dizia **"Receita mês"** e mostrava `sparkSum` — a soma da **sparkline**, que é
`whereBetween(transaction_date, [hoje-29, hoje 23:59])`: **30 dias deslizantes**, não o mês
corrente. No dia 21, isso cobre 23/jul a 21/ago. Os dois só coincidem no dia 30 ou 31.

**De onde veio a palavra errada — medido em 2026-08-21.** A âncora oficial desta tela
(`prototipo-ui/cowork/Wagner/jana-merge.jsx`, o `related_prototype` do charter) **não tem este KPI**. O
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

## UC-JPAIN-16 — nenhum botão novo do Painel nasce clicável sem fazer nada
Status: 🧪 (`PainelContratoTest` — 1 `it()` com bite-test de 4 asserções + corpus real; **provado localmente** nos três estados, aguarda run verde na lane e o screenshot F1.5)

Derivado do `jana-painel.contract.json` (§`pendencias`) e do `Index-visual-comparison.md`
(§Resumo, linhas `R3 · chips do brief` e `ação Exportar`) — **não** do `.tsx`. Derivar do código
seria tautológico (§5 2026-06-05).

O Painel tem hoje **5 botões clicáveis que não fazem nada**. Dois já eram conhecidos; três não
estavam em nenhum inventário com o ponteiro certo:

| rótulo | onde | estado no inventário |
|---|---|---|
| `Exportar` | `Index.tsx` | 🟡 mudo — o `(em breve)` saiu do `title` em 2026-08-31; o botão **não** mudou, decisão [W] aberta |
| `Ouvir áudio` | `JanaCockpit.tsx` | 🟡 `title="(em breve — TTS V2)"` |
| `Disparar régua WhatsApp pros {n} atrasados` | `JanaCockpit.tsx` | 🟡 "botão morto" — **ref de linha podre** |
| `Ver top devedores` | `JanaCockpit.tsx` | idem |
| `Investigar queda ticket médio` | `JanaCockpit.tsx` | idem |

Os três últimos eram **piores** que os dois primeiros: não prometem nada. O usuário clica em
*"Disparar régua WhatsApp pros 7 atrasados"* e nada acontece, sem explicação — enquanto o
`(em breve)` ao menos avisa. Eles cresceram exatamente onde o **UC-JPAIN-12** consertou os CTAs
vizinhos, no mesmo arquivo: o conserto foi por instância, e a classe reincidiu ao lado.

⚠️ **Atualização 2026-08-31 — o `Exportar` mudou de categoria, não de estado.** Ao remover o
`(em breve)` do `title` (pedido [W]), ele deixou de avisar e **continua sem handler**: passou do
balde "promete e não cumpre" para o balde "clica e nada acontece, sem explicação" — o que este
próprio UC classifica como pior. O único que ainda promete data é o `Ouvir áudio`. A lista
`$conhecidos` do teste **não muda** (o predicado é FORMA — `onClick`/`disabled`/`href` —, e
nenhum deles apareceu); muda o motivo escrito ao lado dele.

### O que este caso decide — e o que ele não decide

Ele **não** escolhe o destino de nenhum dos 5. Essa decisão é de [W], e o contrato já diz por quê:
*"Some, vira `disabled` com o motivo, ou entrega? Enquanto não decidido, NÃO entra no contrato:
pinar uma promessa é congelá-la."* Consertar um sem decisão seria escolher no lugar dele.

O que ele faz é **forward-only** ([ADR 0275](../../../../memory/decisions/0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md)):
trava o conjunto conhecido e derruba o **sexto**. Transforma dívida invisível em dívida declarada
— e a invisibilidade é o que deixou os três chips atravessarem o conserto do vizinho.

### Por que ESTRUTURAL, e não a prosa "em breve"

`not->toContain('em breve')` **falharia hoje**: a frase vive nos comentários que registram as
decisões — 4 ocorrências medidas em `Index.tsx`/`JanaCockpit.tsx`, incluindo a que celebra o
conserto do botão "Configurar". Proibir a prosa proibiria registrar a decisão: é o falso-positivo
do §5 2026-07-26, o mesmo que mordeu os UC-10, UC-11 e UC-12 **na escrita**. O parse remove
comentário antes de contar; o que morde é a **forma** do botão.

### FP medido ANTES de ligar (regra "LIGUE A MÁQUINA" item 4)

| predicado | Jana | repo inteiro | veredito |
|---|---|---|---|
| `<Button>` sem `onClick` (cru) | 14 de 35 | 45 de 687 | ❌ ~85% FP — quase tudo `<Link href><Button>` |
| + filtro de wrapper-pai | **5** de 35 | 15 de 687 | ✅ os 5 da Jana são reais |

Por isso o caso **não** é ampliado pra fora do Painel: os 15 do repo inteiro não foram
classificados, e ligar sem medir aquele corpus seria a família de guard sintático que o §5 já
enterrou 5×.

### Identificação por RÓTULO, não por `arquivo:linha`

Ref de linha apodrece no primeiro refactor (§5 2026-07-26) — e o inventário desta própria tela é a
prova: ele aponta os chips em `JanaCockpit.tsx:479-500`, onde hoje está o parágrafo do brief; os
chips estão em 553/557/565. O parse ainda remove comentários, então a linha dele nem casa com a do
arquivo (medido: "Ouvir áudio" é 455 no arquivo e 373 depois do strip). O rótulo é o que o usuário
vê e o que se mantém estável.

E por lista, não por contagem: `toBe(5)` trancaria nos dois sentidos — consertar um derrubaria o
caso (predicado absoluto onde cabia delta, §5 2026-08-24) e trocar um pelo outro passaria em
silêncio. Consertar = **apagar a linha** no mesmo PR.

## Nota do conserto do UC-JPAIN-08 (2026-08-17)

`_components/JanaCockpitSkeleton.tsx` (novo, ancorado em `jana-merge.jsx` §`JmPainelSkeleton`) +
`carregandoCockpit = coworkAggregates === undefined` no `JanaCockpit`. Os `?? 0` **ficaram** — são
eles que impedem o `TypeError` e mantêm válida a entrada `Jana/Index` na
`DEFER_GUARD_ONLY_ALLOWLIST`; o que mudou é o **render**.

Escopo medido: só o KPI que depende da prop deferida (`Receita 30 dias`) troca de card.
`A receber vencido` e `Ticket médio` vêm de `insightsAggregates` (eager) e **não** podem
sumir — há controle negativo no teste.

_**Eram 2 até 2026-08-31** (`Receita 30 dias` · `PIX hoje`). O `PIX hoje` saiu do painel na
paridade com a âncora (UC-JPAIN-18) e a asserção dele saiu no MESMO diff. Aqui o `toMatch` com
alvo extinto **falha** — ele acusa, não passa vazio —, mas a prosa que dizia "os dois" viraria
mentira silenciosa, e é o que esta nota conserta._

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
- ✅ **"Dashboard" × "Painel"** — RESOLVIDO em 2026-08-31. O `AppShellV2` recebe `title="Jana — Painel"`
  (a âncora carimba `data-screen-label="Jana — Painel"` e rotula a aba `label: "Painel"`; o prefixo de
  área fica, como em `Jana — Memória`). O **breadcrumb** já não existia — saiu como dado morto na
  revalidação de 2026-08-18, abaixo. Sobra o **identificador do componente** (`export default function
  Dashboard`), que é símbolo de JS e não copy que o usuário lê; renomear é refactor, não alinhamento.
- ⚖️ **O botão "Exportar"** — clicável, sem `disabled` e sem rota. Some, vira `disabled` com o
  motivo, ou entrega? _Atualizado 2026-08-31:_ o `(em breve)` **saiu** do `title` (a âncora não
  promete — lá o gatilho é `<button class="jm-btn ghost">Exportar</button>`, sem tooltip), mas isso
  **não respondeu a pergunta**: o botão segue mudo. Segue **fora do contrato** — pinar `Exportar`
  enquanto ele não faz nada congela uma affordance morta. _(Eram **dois**; **Configurar** saiu desta
  lista em 2026-08-17 — entregou, ver UC-10.)_
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

## Revalidação de 2026-08-18 — por que o `last_run` subiu

O G-6 acusou `stale:` porque o `Index.tsx` mudou depois do `last_run` de 08-17. **O que mudou:** o
`breadcrumbItems` foi removido do `AppShellV2` — era **dado morto**. O shell só renderiza breadcrumb
dentro de `{!hideTopbar && …}` (`AppShellV2.tsx:559`) e `hideTopbar` é `true` por default (`:243`);
nenhuma tela da Jana passa `hideTopbar={false}`, então o array nunca chegou à tela.

**Interseção com os UCs desta tela: nenhuma.** O que saiu nunca renderizou — não havia comportamento
observável para um UC cobrir. Nenhum `Status:` mudou.

Registrado porque o §5 de 2026-07-27 cataloga esta classe: mudança semanticamente inerte **não é inerte
pro gate** — o G-6 mede data de git, não semântica.

## Revalidação de 2026-08-25 — a data acima não era a que o git viu

O `casos-gate` voltou a acusar `stale:` nesta tela, e a causa **não é uma mudança nova**: é a MESMA
remoção do `breadcrumbItems` que a seção acima descreve. A seção foi escrita em 08-18, e o
[#5907](https://github.com/wagnerra23/oimpresso.com/pull/5907) só entrou no `main` em **2026-08-25**,
depois de um rebase de 299 commits. O G-6 compara o `last_run` com a **data de git do `.tsx`**, e o
squash-merge carimba a data da entrada, não a do trabalho — então o frontmatter nasceu 7 dias atrás
do próprio arquivo que descreve. Não é engano de quem revalidou: é o efeito de PR que demora a
mergear, e ele reaparece em toda tela que revalide antes de entrar.

**Re-verifiquei a premissa por conta própria** em vez de herdar a afirmação do commit — o relato de
outra sessão é hipótese a testar, não fato:

| O que a seção de 08-18 afirma | Como conferi | Resultado |
|---|---|---|
| o shell só renderiza breadcrumb sob `!hideTopbar` | `grep -n hideTopbar` em `AppShellV2.tsx` | `:559` → `{!hideTopbar && (` ✓ |
| `hideTopbar` é `true` por default | idem | `:243` → `hideTopbar = true,` ✓ |
| nenhuma tela da Jana passa `hideTopbar={false}` | `grep -rn hideTopbar resources/js/Pages/Jana/` | só comentários e este `.casos.md`; zero uso da prop ✓ |
| o diff foi só isso | `git show 4de9d46bcb -- …/Jana/Index.tsx` | 1 prop removida + 4 linhas de comentário ✓ |

**Interseção com os 14 UCs: nenhuma** (contados com `grep -c '^## UC-'`). Os UC-JPAIN-01..14 são
rota, contrato de props, escopo `business_id`, farol de servidor, empty state, meta, série, skeleton,
âncoras do contrato, drawer, decisão registrada, churn e rótulo de KPI — nada toca topbar, breadcrumb
ou `<title>`. Nenhum `Status:` muda; os `🧪` seguem `🧪`.

**Não rodei a suíte nesta leva.** O bump é por revalidação de contrato, e digo isso porque o G-6
aceita a data e só o leitor percebe a diferença.

## UC-JPAIN-17 — o selo de plano lê o PACOTE, não o cliente
Status: 🧪 (`JanaPlanoTierTest` — 3 `it()`, um comportamental e dois de fonte; o teste diz
por que cada um é o que é. Aguarda run verde na lane e o screenshot F1.5.)

Derivado da âncora (`prototipo-ui/cowork/Wagner/jana-merge.jsx:970` + `chat-jana.jsx:217`) e da decisão
[W] de 2026-08-27 — **não** do `.tsx`. Derivar do código seria tautológico (§5 2026-06-05).

Até 2026-08-27 este selo era o item **BLOQUEADO** da onda 4 (`PARIDADE` §8.1), e o motivo não era
trabalho: **não havia de onde ler o plano**. O `ProController` mandava `'plan' => 'free'` literal;
não existia coluna, tabela nem chave de tier; e no protótipo o `pro` é um toggle de simulação, cuja
legenda diz *"aqui o Pro é simulação pra ver o gating"*. O `useJanaConfig` já recusava gravá-lo
*"porque o servidor não as honra"*.

O que mudou é a FONTE, não o desenho: `jana_pro_module` virou chave de pacote marcável no
Superadmin (sem billing — Asaas real segue Sprint JANA-B, ADR 0140), e o selo lê
`shell`/`jana.pro`, derivado da assinatura.

O caso defende três coisas, e a terceira é a que dói se quebrar:

| o que | por quê |
|---|---|
| o selo mostra `plano Pro` só com `jana_pro_module` no pacote | senão volta a afirmar estado que o sistema não sabe |
| `jana_module` e `jana_pro_module` seguem eixos SEPARADOS | fundi-los repete, dentro do código, o engano que um humano cometeu lendo o painel |
| sem pacote legível o degrade é `Grátis` | afirmar Pro a quem não é promete recurso pago; o inverso só omite |

---

## UC-JPAIN-18 — o Painel mostra os 3 KPIs da âncora, e o 4º saiu sem levar o dado
Status: 🧪 (`PainelContratoTest` — 1 `it()` com bite-test do extrator em 4 fixtures + 4
asserções sobre o arquivo real; aguarda run verde na lane MySQL)

Derivado da **âncora** (`node scripts/design/ancora.mjs Jana/Index` →
`prototipo-ui/cowork/Wagner/jana-merge.jsx`, frescor verificado contra o Cowork vivo em 2026-08-27) e da
decisão [W] de 2026-08-31 — **não** do `.tsx`.

**A medição.** A âncora renderiza `data.kpis.map(…)` dentro da `jc-kpis`, e esse array publica
**3** entradas: `Receita mês` · `A receber vencido` · `Ticket médio`. A tela viva tinha **4** — o
extra era `PIX hoje`. Com o 4º fora, a ORDEM dos três restantes casa 1:1 com a do protótipo, e o
`KpiGrid` passa a `cols={3}` (sem isso sobraria um vão de uma coluna no desktop `lg`).

**O que este caso NÃO trava, de propósito.** O rótulo do 1º card. Ele é `Receita 30 dias` aqui e
`Receita mês` na âncora, e a divergência é **deliberada**: o dado são 30 dias deslizantes, e o
UC-JPAIN-14 corrigiu a palavra com esse fundamento. Copiar a copy do protótipo reintroduziria bug
conhecido — **neste ponto é o protótipo que está atrás.**

**O card saiu; o DADO não.** `pixHojeTotal` segue chegando na prop deferida e `pixHoje` segue
sendo lido por dois consumidores vivos: a ação sugerida *"PIX adoção em N% — manter"* e a linha do
brief (`· PIX <valor> (N% imediato)`). Isso não é detalhe: é o que separa *"tirar o card"* de
*"tirar a capacidade"* — a segunda seria outra decisão. O teste asserta os dois consumidores
justamente pra que o negativo (`not->toContain('label="PIX hoje"')`) não vire decoração no dia em
que alguém apagar o `pixHoje` inteiro (LC-11 na forma silenciosa — a mesma que o controle
negativo do UC-JPAIN-08 já ensina neste arquivo).

**Duas afirmações de canon caducaram no caminho, e ficam corrigidas com recibo:**

| afirmava | medido em 2026-08-31 |
|---|---|
| `JanaCockpit.tsx`: *"a âncora traz `Frota utilização`"* (presente) | **falso**. `grep -in 'frota\|truck' jana-merge.jsx` → **rc=1, zero**, com controle positivo no mesmo arquivo (`grep -c JM_KPI_DRILL` → 2, rc=0). A frota só existe no `chat-jana.jsx` (não-âncora) e **nem lá é KPI**: é o ícone `truck:` e a classe `jc-an-frota` |
| `JanaCockpit.tsx`: *"1 de 4 com `emphasize:true`"* | denominador errado. São **1 de 3** — `grep -c emphasize` → 2 (1 dado em `:94`, 1 render em `:256`) sobre um array de 3. A **regra** visual (enfatiza um só, e só no alerta) não mudou |

> ⛔ **Regra, não ressalva — [W] revogou a objeção em 2026-08-31.** Levantei uma vez que o array
> `kpis` é autorado no `chat-jana.jsx` e que o mock é do Martinho, logo a ausência do PIX poderia
> ser premissa deles (§5 2026-07-16). [W], textual: *"essa ressalva deve ser por isso que não fica
> igual. deve ser revogado. que igual."* **Fica assim:** o conjunto de KPIs desta tela é **o que a
> âncora renderiza**, e não se re-litiga por sessão — ressalva pendurada em canon é recusa
> disfarçada, e é ela que mantém a tela diferente do protótipo (ADR 0382 · §5 2026-08-24).
>
> Segue valendo o que é regra de FONTE: derivar do `chat-jana.jsx` o que ele **não** renderiza (a
> frota) continua proibido. Espelhar o que a âncora renderiza é paridade — o oposto.

**Pronto quando:** o grid tem exatamente os 3 rótulos na ordem da âncora; `KpiGrid` declara
`cols={3}`; `label="PIX hoje"` não existe; e `pixHoje` segue com consumidor vivo.

### Pendência registrada — o card "Cheques" NÃO foi construído, por falta de fonte

O item 5 do mesmo pedido de 2026-08-28 era trocar a análise **Métodos de pagamento** por
**Cheques**, e a instrução de [W] era medir antes: *"se a fonte não existir, NÃO invente"*. Medido
— **não existe**, e o card fica pendente. As três fontes:

| fonte consultada | o que respondeu |
|---|---|
| **o card no protótipo** (`chat-jana.jsx` §`id:"cheq"`) | pede *"Cheques previsão · Na mão / a depositar"* com **ciclo de vida**: total em circulação, quitados (%), ativos hoje, e um HITL *"lembra qual dia depositar cada cheque"* |
| **o schema real** (`database/schema/mysql-schema.sql`) | `cheque` existe só como **valor de enum de forma de pagamento** (`fin_titulos.forma_pagamento`, `fin_titulo_baixas.meio_pagamento`), mais `transaction_payments.cheque_number` (identificador) e `cash_registers.total_cheques` (agregado por sessão de caixa). **Zero** colunas de ciclo: sem data de depósito/bom-para, sem status de custódia, sem devolvido |
| **o dono do inventário legado** (`memory/dominios/wr-comercial/…/MAPPING.md`) | o ciclo existe no Delphi — `FINANCEIRO_CHEQUE`, 21 colunas com `STATUS`, `DT_REPASSADO`, `DEVOLVIDO`, `MOTIVO`, `BANCO/AGENCIA/CONTA`. E o mapeamento diz, em letra, que ele **não desceu**: *"(escopo separado — cheque ≠ conta bancária)"* e, na lista de questões abertas, *"Fora desta Fase 4. Wagner decide se entra na próxima ou em fase separada"* |

Ou seja: os números do protótipo (4.421 cheques, R$ [redacted Tier 0] em circulação, 99,9% quitados) vêm da base
Delphi, de uma tabela cuja migração é **decisão [W] adiada** — não de algo que a `/ia` possa apurar.

**Por que uma versão "degradada" também foi recusada.** Daria pra contar `transaction_payments`
com `method` de cheque e exibir *"N cheques · R$ X"*. Mas isso (a) **duplicaria** o card que já
existe — cheque é uma das fatias de **Métodos de pagamento** — e (b) manteria o título
*"previsão · na mão / a depositar"* prometendo uma projeção que nenhuma coluna sustenta. Card que
mostra número inventado é pior que card ausente.

**Destravar exige, nesta ordem:** decisão [W] sobre migrar `FINANCEIRO_CHEQUE` → tabela + importer
→ agregador → só então o card. Enquanto isso, a análise `metodos` **fica**, e o toggle dela no
`JanaConfigDrawer` segue com os mesmos 5 ids (`inad`/`fat`/`conc`/`metodos`/`churn`).

---

## UC-JPAIN-20 — os 3 KPIs do topo são RÉPLICA do `.jc-kpi`, não o card PT-04
Status: 🧪 (render-test `tests/janaKpiReplica.spec.tsx` — 13 asserções, **13 passed** rodadas
localmente em 2026-09-03, com bite-test provado por mutação; e `PainelContratoTest` reescrito. O
`✅` só entra quando o verde vier do MANIFESTO — G-7 —, e ele ainda não veio: o CT 100 estava fora
do ar nesta sessão (`tailscale ssh` devolveu 502), então o veredito é o das lanes de CI. Run local
não é run do manifesto, e escrever `✅` aqui seria exatamente o "o Status pode mentir" que o
casos-gate existe pra impedir.)

Derivado da **âncora** (`node scripts/design/ancora.mjs Jana/Index` →
`prototipo-ui/cowork/Wagner/jana-merge.jsx` §`data.kpis.map` → `KPICard`; markup em `chat-jana.jsx`,
estilo em `chat-jana.css` §`── KPIs ──`) e da medição registrada em
[`Index-visual-comparison.md` §Rodada MEDIDA de 2026-09-03](../../../../memory/requisitos/Jana/Index-visual-comparison.md),
tabela **KPIs** — **não** do `.tsx`. Pedido [W] de 2026-09-03, textual: *"KPIs feios"*.

**Por que este caso existe.** A tabela da medição tem 4 linhas `❌` e 3 `🟡`, e as duas que [W]
nomeia como "o feio" estão escritas lá com todas as letras: *"é o `feio`: a caixa de ícone e o
padding"*. O card em produção era o `KpiCard` shared (anatomia PT-04): caixa de ícone 36×36
`bg-muted`, `p-4`, r12, rótulo sans. A âncora desenha outra coisa — rótulo **mono** de 10px com o
ícone de 15px **inline** ao lado, r8, `12px 14px 14px`.

| eixo | âncora `.jc-kpi` | produção (antes) | agora |
|---|---|---|---|
| grid | `repeat(4, 1fr)` · gap 10 · 3 de 4 | `cols={3}` · gap 12 · ocupa tudo | `cols={4}` + `gap-2.5` |
| moldura | `--r-2` (8px) · `12px 14px 14px` · gap 3 | r12 · `p-4` · gap 8 | `rounded-[var(--radius,8px)]` · `pt-3 px-3.5 pb-3.5` · `gap-[3px]` |
| rótulo | mono 10px/700 `.06em` · ícone 15px inline | sans 11px/600 · ícone em caixa 36×36 | mono 10px/700 `.06em` · ícone 15px inline |
| alarme | `--neg-soft` (tinta sólida) + valor `--fs-8` | `bg-destructive/5` + valor `--fs-7` | `bg-destructive-soft` + valor `--fs-8` |
| delta | `-68% vs mai/25` | `+3 hoje vs ontem` (sem unidade) | `-22% em 4m` / `+3% 7d` |

### Componente NOVO, e o shared fica intocado

`_components/JanaKpiCard.tsx`, sob [ADR 0388](../../../../memory/decisions/0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias.md)
§D-1 ("réplica primeiro") e a precedência de FORMA da
[ADR UI-0029](../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)
(*protótipo > teste > casos > charter > SPEC*). Mudar o `KpiCard` shared imporia a forma da Jana
às outras **36** telas que o consomem — a [ADR 0385](../../../../memory/decisions/0385-sidebar-alinhado-ao-prototipo-diferenca-em-tres-categorias.md)
já dizia que *"diferente não é erro"*. Réplica local é o caminho que a 0388 abriu justamente pra
isso não virar impasse.

**Zero cor crua.** Cada declaração da âncora sai por token semântico do DS, e o mapa completo está
no docblock do componente. O `--neg-soft` da âncora vira `bg-destructive-soft`, utility do DS já
usada em 6 sites.

⚠️ **O RAIO precisou ser MEDIDO, e a tradução óbvia estava errada.** A primeira versão deste
caso afirmava que `--r-2` (8px) *"casa exatamente com `--radius-lg` (`0.5rem`), então é
`rounded-lg`"*. Isso é verdade no `:root` e **falso onde a tela vive**: dentro do `.cockpit` o
`--radius-lg` é **REDEFINIDO pra 12px**. Medido na bancada, com o CSS construído do próprio PR:

```
:root      --radius-lg .5rem                .cockpit   --radius-lg 12px   --radius 8px
no .cockpit:  rounded-sm 6  rounded-md 6  rounded-lg 12  rounded-xl 12
```

Nenhuma utility da escala entrega 8px ali — e `rounded-lg` reproduziria **exatamente o r12 que
esta onda existe pra consertar**. O token que vale 8px no escopo é `--radius`, que é o mesmo que
o `--r-2` do espelho resolve; daí `rounded-[var(--radius,8px)]`, com fallback pra quem renderizar
fora do `.cockpit`. Fica registrado porque a afirmação errada já estava escrita: ler o token no
`:root` e concluir sobre o escopo é medir no lugar errado (§5 2026-07-16). **Inconsistência declarada, não escondida:** os dois tokens de alarme não têm o mesmo
valor — âncora `oklch(0.36 0.12 25)` × prod `oklch(0.26 0.07 18)` no escuro. Ficou o token de
produção, porque o vocabulário `destructive-*` é o que o resto do ERP lê; a diferença é de
calibragem do token, e reconciliá-la é decisão de Fundação ([W]), não desta tela.

### Os DOIS EIXOS voltam a ser dois

A âncora separa o que produção fundira:

```
emphasize: true          → .jc-kpi.emph      = fundo + borda + valor no degrau --fs-8
deltaCls === "red big"   → .jc-kpi-v.red     = a cor do VALOR, e só ela
```

O `KpiCard` shared pendura os dois num `tone` só, e o próprio `KpiCard.tsx` registrou isso como
errata em 2026-08-27: *"pendurou-se no eixo do FUNDO um efeito que a âncora pendura no eixo do
DELTA. Passou despercebido porque o dataset tem N=1"*. Aqui viram `emphasis` e `valueTone`,
declaráveis em separado — e o teste **prova** a independência, que é o que N=1 não conseguia
mostrar. O shared **não** foi desfusionado: ele tem outros 36 consumidores e a decisão é de quem
os tem.

### O que este caso NÃO muda, de propósito

- **Os 3 rótulos e a ordem** — são do UC-JPAIN-18, e seguem intactos.
- **`Receita 30 dias`** contra `Receita mês` da âncora — UC-JPAIN-14; aqui é o protótipo que está
  atrás, e copiar a copy dele reintroduziria bug conhecido.
- **O drill** dos 2 KPIs que têm análise do mesmo dado. Mudou só ONDE ele mora: o clicável é o
  **wrapper**, e o card volta a ser `DIV`, como na âncora (lá é `.jm-an-hit` por fora do
  `.jc-kpi`). Isso fecha o `DIVERGE(bug)` que a sonda acusou como `kpi.tag BUTTON×DIV`.
  Divergência **consciente** da âncora: o wrapper é `<button>` de verdade, não `div role="button"`
  — mesma affordance, sem o teclado à mão.
- **UC-JPAIN-16** — o card só nasce clicável quando recebe `onClick`; sem ele não há `button`
  nenhum no DOM, e o teste asserta isso.

### Medição de runtime — mesma sonda nos dois lados (2026-09-03)

Bancada: o HTML **real** que o `JanaKpiCard` renderiza (extraído do render, não escrito à mão) sobre
o CSS **construído** deste PR (`app-*.css` + `AppShellV2-*.css`, os dois que a `/ia` carrega),
dentro de `.cockpit[data-theme="dark"]` × o `.jc-kpi` do espelho sobre `styles.css` + `chat-jana.css`,
mesmo tema, mesma viewport (1440), mesma função de sonda.

| campo | âncora `.jc-kpi` | produção `JanaKpiCard` | |
|---|---|---|---|
| colunas do grid | 4 | 4 | ✅ |
| gap do grid | 10px | 10px | ✅ |
| raio | 8px | 8px | ✅ |
| padding | `12px 14px 14px 14px` | `12px 14px 14px 14px` | ✅ |
| gap interno | 3px | 3px | ✅ |
| rótulo | 10px/700 · ls 0.6px · mono · uppercase | 10px/700 · ls 0.6px · mono · uppercase | ✅ |
| valor (normal) | 22px/700 · lh 22px | 22px/700 · lh 22px | ✅ |
| valor (`emph`) | 28px/700 · lh 28px | 28px/700 · lh 28px | ✅ |
| `small` | 11px | 11px | ✅ |
| ícone | 15×15 | 15×15 | ✅ |
| tag do card | `DIV` | `DIV` | ✅ |
| largura | 301px | 301px | ✅ |
| altura | 94px | **98px** | 4px — line-height do `small` herdado do body de cada bancada; a **98** é a altura que a medição de 2026-09-03 registrou pra âncora |

**Dois defeitos que só a medição pegou** — os dois passavam em typecheck, lint, vitest e CI, e os
dois deixariam a correção **inerte** (LC-30):

1. **O raio.** A tradução intuitiva `--r-2` → `rounded-lg` está certa no `:root` (`.5rem`) e
   **errada onde a tela vive**: no `.cockpit` o `--radius-lg` é redefinido pra **12px**, e nenhuma
   utility da escala entrega 8px ali (`sm` 6 · `md` 6 · `lg` 12 · `xl` 12). `rounded-lg`
   reproduziria **exatamente o r12 que esta onda existe pra consertar**. O token que vale 8px no
   escopo é `--radius` — o mesmo que o `--r-2` do espelho resolve.
2. **O `leading-none` sumia.** Escrito ANTES do `text-[length:var(--fs-7)]`, o `twMerge` o
   descarta (os dois caem no grupo do par `text-[size/leading]`), e o line-height voltava pro 1.5
   herdado: **33px sobre fonte de 22px**, contra os 22px da âncora — o card ficava **112px** em vez
   de 98. Provado isolado: `twMerge('leading-none … text-[length:var(--fs-7)]')` devolve a string
   **sem** ele. Os dois consertos têm assert próprio no `janaKpiReplica.spec.tsx`, então reordenar
   ou voltar pra utility da escala é teste vermelho, não card 18px mais alto que ninguém mede.

### O teste morde — provado por mutação

Reintroduzi a forma antiga no componente (`rounded-xl` + `bg-destructive/5`) e rodei:
**3 falharam, 10 passaram**. Restaurado, **13 passaram**. Os dois detectores do spec (cor crua de
palette · caixa de ícone) têm controle de sensibilidade E de especificidade, porque detector que
nunca acusa é decoração (ADR 0258).

### O perdedor foi corrigido no MESMO PR

`PainelContratoTest` fixava a forma antiga em quatro pontos: o extrator `painelKpisDoGrid` casava
`<KpiCard\s+label=`, as 3 fixtures do bite-test, e o `toContain('<KpiGrid cols={3}>')`. Fossem
deixados, o extrator devolveria `[]` e o **UC-JPAIN-18 ficaria verde por não achar nada** — LC-11
na forma silenciosa. Reescritos, nunca desabilitados (UI-0029), mais um par novo que trava a
proveniência do card (`import JanaKpiCard from './JanaKpiCard'` presente, `shared/KpiCard`
ausente) justamente pra que o extrator não possa voltar a medir o vazio.

**Pronto quando:** o grid declara `cols={4}` com `gap-2.5`; o card é `JanaKpiCard` com moldura r8,
rótulo mono 10px e ícone inline de 15px; o alarme usa tinta sólida e sobe o valor pro `--fs-8`;
`emphasis` e `valueTone` são independentes; o drill segue com o card em `DIV`; e a lane
`jana-pest.yml` fecha verde com o `PainelContratoTest` reescrito.

---

## Revalidação de 2026-08-28 — o `.tsx` mudou de PATH de import, e só isso

O `casos-gate` acusou `stale:` nesta tela. A causa é mecânica: a pasta
`Pages/Jana/components/` (sem underscore) foi para o canon `_components/`, e o G-6 compara a
**data-git do `.tsx`** com o `last_run` — mudança semanticamente inerte **não é inerte pro
gate** (é a lápide §5 2026-07-27: comentário, whitespace e rename contam como "a tela mudou").

**Revalidação de contrato, medida:**

| O que conferi | Como | Resultado |
|---|---|---|
| o tamanho do diff nesta tela | `git diff origin/main...HEAD --numstat -- …/Index.tsx` | **2 linhas**, todas de `import` |
| o que mudou nelas | `git diff` das mesmas linhas | só o PATH de `FabJana` e `JanaAreaHeader` — nome, símbolo e uso idênticos |
| o componente mudou de conteúdo? | `git log --stat` do rename | **não** — o git detectou 100%% de similaridade nos dois arquivos |

**Interseção com os UCs: nenhuma.** Um caso de uso descreve comportamento de tela; path de
import não é comportamento. Nenhum `Status:` muda.

**Não rodei a suíte** — CT 100 respondeu 502 durante toda a sessão e Pest local é proibido
(ADR 0062). O bump é por revalidação de CONTRATO, e digo porque o G-6 aceita a data e só o
leitor percebe a diferença.

---

## Revalidação de 2026-08-31 — dois itens de COPY vieram da âncora, e o terceiro não existia

O `casos-gate` acusa `stale:` porque o `.tsx` mudou depois do `last_run` — o G-6 compara
**data-git do `.tsx`** com a data daqui e não lê o conteúdo do diff (§5 2026-07-27).

**O que mudou na tela — `git diff --numstat`: 2 linhas.**

| # | Alvo | Antes | Depois | Fonte da decisão |
|---|---|---|---|---|
| 1 | `AppShellV2 title` | `Jana — Dashboard` | `Jana — Painel` | âncora: `data-screen-label="Jana — Painel"` + aba `label: "Painel"` |
| 2 | `title` do botão Exportar | `Exportar relatório (em breve)` | `Exportar relatório` | âncora não promete: `<button class="jm-btn ghost">Exportar</button>`, sem tooltip |

**O terceiro item pedido não era uma divergência.** O pedido descrevia "o subtítulo das análises"
como se o contrato o pinasse em `painel-metas-header`. Medido:

| O que conferi | Como | Resultado |
|---|---|---|
| subtítulo das ANÁLISES, prod × âncora | comparação byte a byte das duas strings | **idêntico** — `clique num card pra ver de onde vem o número` já está em `_components/JanaCockpit.tsx`, igual ao `jana-merge.jsx` |
| a quem pertence `painel-metas-header` | `_papel` do contrato + a âncora `data-contract` no `.tsx` | ao bloco **METAS**, não às análises |
| a âncora tem subtítulo em METAS? | leitura do símbolo `JmMetasSecao` | **não** — o cabeçalho é `METAS ATIVAS` + controles; `jm-h2-sub` ocorre **1 vez** no protótipo e é do bloco `ANÁLISES PRINCIPAIS` |

Trocar a copy de `painel-metas-header` teria posto uma instrução de análises sobre o cabeçalho de
metas — divergindo da âncora em vez de alinhar a ela. **Não foi feito.** A divergência real que a
medição achou (prod tem `Acompanhamento contínuo`; a âncora não tem subtítulo nenhum ali) ficou
registrada em `_nota_metas_header` no contrato: mexer em copy pinada é decisão [W].

**Interseção com os UCs:** o **UC-JPAIN-16** — que cataloga os botões mudos — teve a linha do
`Exportar` e a prosa do "piores" atualizadas acima. **Nenhum `Status:` muda**, e a lista
`$conhecidos` do teste continua idêntica: o predicado dele é FORMA (`onClick`/`disabled`/
`href`/`asChild`/spread/wrapper-pai), e nenhum desses apareceu — só o texto do `title`, que o
parse do teste sequer lê. Os outros 15 UCs não tocam título de shell nem tooltip.

**O que NÃO entrou no contrato, e por quê (medido, não suposto):** a seção `painel-titulo` que o
`_pendente_w` previa. Rodando o gate com ela: `X seção "painel-titulo" sem âncora data-contract no
alvo`. O título é **prop** do `AppShellV2` compartilhado, não elemento desta tela, e criar um
`<span data-contract>` só pra satisfazer o gate é o que o `_nota_alvo` do contrato recusa.

**Não rodei a suíte Pest** — é CT 100 apenas (ADR 0062) e não estava no escopo desta leva. O bump é
por **revalidação de CONTRATO**, e digo porque o G-6 aceita a data e só o leitor percebe a diferença.
Rodados aqui: `casos-coverage-guard` nos 4 modos, `module-surface --all --check`,
`contrato-de-tela --contract` e `--preflight`.

## UC-JPAIN-19 — a barra de abas é FAIXA PRÓPRIA abaixo do header, e o header não tem primary "Conversar"

**Status:** 🧪 — `npx vitest run tests/janaAreaHeaderParidade.spec.tsx` → 4 passed (jsdom local, 2026-09-03); vira ✅ quando o manifesto `casos-results` aterrissar (G-7 lê o manifesto commitado, não esta linha)

**Fonte:** âncora `prototipo-ui/cowork/Wagner/jana-merge.jsx` §`JanaPage` — todo `tab` renderiza
`<JanaHeader/>` e SÓ DEPOIS `{tabs}` (`JmTabs`, via `CliTabs`/TabBar do DS); `JanaHeader` tem na
zona direita `Atualizado HH:MM` (botão, dot verde) → `{plano}` → `Nova conversa` (só `isChat`) →
`Configurar` → `Exportar`, e **nenhum** primary. Charter v13 §Goals. UI-0029 (protótipo soberano
sobre ADR UI, ratificada 2026-08-31).

**Narrativa:** o operador abre qualquer tela da área Jana e vê a barra de abas na LARGURA TODA,
logo abaixo da linha do título, com um ícone por aba — como no protótipo e como o Clientes já faz.
O título fica sozinho na linha dele, com a identidade do tenant em mono embaixo. À direita,
"Atualizado HH:MM" (que reapura ao clicar), o selo de plano e as ações da tela. Não há botão
"Conversar" competindo com a aba Conversa.

**Medido em 2026-09-03 (mesma sonda nos dois lados, dark × dark, viewport 2560):**

| item | âncora (render do espelho) | produção (antes) | veredito |
|---|---|---|---|
| posição da tablist | `nav` filho de `.jc-page`, `left=284 w=2237 h=36`, 14px abaixo do header | inline na Zona C do header, `left=1654 w=451`, mesmo `top` do h1 | ❌ DIVERGE (bug) → **corrigido** |
| aba | 13px/500 · ativa 600 + underline accent + pill accent-soft · ícone 14px | 14px/400 · ativa 600 + underline accent + pill · **sem ícone** | ❌ **DÍVIDA A FECHAR** — ícone corrigido; os 13×14px **NÃO ficam** (⛔ REVOGADO por [W] 2026-09-18). O `tests/pageHeaderTabsFidelity.spec.tsx` trava o `PageHeaderTabs` contra `clientes-page.css` `.cli-moduletopnav-tab.active` — o protótipo do **Clientes** —, e a Jana o consome via `JanaSubNav.tsx:6`. Componente compartilhado não impõe a forma de uma tela às outras: o caminho é **réplica local** (ADR 0388 §D-1), como a Jana já fez no `JanaKpiCard` |
| "Atualizado" | Zona R, 1º item, botão com dot | dentro do subtítulo | ❌ → **corrigido** |
| primary no header | não existe | "Conversar" (do `DataController.primary`) | ❌ → **removido** |
| subtítulo | mono 11.5px `TENANT · biz=N · versão` | sans 12px `TENANT·biz=N·Atualizado` | 🟡 → mono; `versão` não existe na prod (dado) |
| título | ~~19px~~ **22px** | 22px | ⚠️ **ERRATA 2026-09-18** — tamanho IGUAL; diverge o **peso** (prod 700 × âncora 600) → **DÍVIDA A FECHAR**, prod converge. Ver abaixo |

**Critério de aceite (o que o teste mede, no DOM renderizado):**

1. a `[role=tablist]` é descendente do `<header>` mas **não** da linha título/ações, e vem depois do `h1`;
2. as 6 abas da âncora, na ordem `Painel · Conversa · Alertas · Ações · Memória · Plataforma`, cada uma com `svg`;
3. fora da tablist, nenhum `a`/`button` do header se chama "Conversar";
4. o botão `Atualizado HH:MM` está no mesmo container das ações e antes delas; o subtítulo não o contém.

**Teste:** `tests/janaAreaHeaderParidade.spec.tsx` (vitest, `npm test`) — 4 `it(...)` que citam este UC.

**O que NÃO entrou aqui, de propósito:** contador `n` nas abas (backend, R2 do
`Index-visual-comparison.md`), Exportar em menu de 3 itens (o botão segue mudo — UC-JPAIN-16 /
decisão [W]), e o **peso** do título (abaixo).

> ⚠️ **ERRATA 2026-09-18 — este bullet dizia "o título 22×19px".** O 19px era verdade quando a
> tabela foi escrita (2026-09-03) e sai como fato datado. Deixou de ser em **2026-09-11** (#7224),
> que fez `JanaHeader` delegar ao `CliPageHead`: a regra que produzia 19px
> (`chat-jana.css:40` `.jc-id h1`) ficou **órfã** — `.jc-id` tem **0 nós** no DOM —, e o `h1`
> passou a herdar o token do DS (`colors_and_type.css:373` `h1 { var(--fs-7) }`, `--fs-7: 22px`).
> Medido hoje nos dois lados, viewport 2560 (a mesma de 09-03) e 1440, dark × dark:
> **tamanho 22px = 22px (IGUAL)**; **peso prod 700 × âncora 600 (DIVERGE)**.
> O 700 vem de `Components/PageHeader/PageHeader.tsx:111` (`font-bold`, docblock *"peso Vendas"* —
> o protótipo de **Vendas**), que é o `PageHeader` importado pelo `JanaAreaHeader` (`:56`).
>
> ⛔ **A 1ª redação desta errata dizia que a decisão [W] de manter *"segue valendo"*. REVOGADO
> por [W] em 2026-09-18:** _"eu revogo tudo, de todos. a regra mudou agora é o Protótipo quem
> manda, e a paridade deve ser o objetivo"_. O peso 600 da âncora é o alvo; **prod converge**.
> Componente compartilhado não impõe a forma de uma tela às outras — o caminho é **réplica
> local** no `JanaAreaHeader`, como a Jana já fez no `JanaKpiCard` (ADR 0388 §D-1), sem tocar
> nas outras 37 telas. Trilha completa no charter v17.

## UC-JPAIN-21 — o card de meta lê "<valor> de <alvo>" e "<pct>% do alvo"
Status: 🧪 (**duas** defesas, as duas com mordida provada por mutação; aguardam o verde vir do
MANIFESTO — G-7 — e o screenshot pós-deploy. Sem `✅` por leitura.)

Derivado da **âncora** (`node scripts/design/ancora.mjs Jana/Index` →
`prototipo-ui/cowork/Wagner/jana-merge.jsx` §`JmMetaCard`, âncora de SÍMBOLO —
`grep -n "function JmMetaCard" prototipo-ui/cowork/Wagner/jana-merge.jsx`) e do pacote de paridade do
Cowork de 2026-09-07 (`prototipo-ui/design-docs/COLAR-NO-CODE-jana-tabs-cor-e-icone.md` §1-ter,
ONDA 2.1) — **não** do `.tsx`. Precedência de FORMA: protótipo > teste > casos > charter
([ADR UI-0029](../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)).

**Por que este caso existe.** Medido lado a lado pelo Cowork (leitura do `main` @ `43b76c1ec327`):
o alvo desenha o valor grande com `de <alvo>` na mesma linha (`jm-meta-v` = `<b>{atual}</b>
<small>de {alvo}</small>`) e o rodapé `32% do alvo` à esquerda com a projeção à direita
(`jm-meta-f`). A produção escrevia `Alvo: <valor>` no rodapé com a porcentagem solta em negrito
depois — o alvo aparecia como rótulo embaixo e **não** ao lado do número, e o "% do alvo" perdia
o substantivo.

| eixo | âncora `JmMetaCard` | produção (antes) | agora |
|---|---|---|---|
| linha do valor | `<b>{atual}</b><small>de {alvo}</small>` | só o valor | valor + `<small>de {alvo}</small>` inline (baseline nativa, sem flex novo) |
| rodapé | `{pct}% do alvo` · projeção à direita | `Alvo: X` + `32%` solto · projeção à direita | `{pct}% do alvo` · projeção intacta |
| sem apuração | `Aguardando apuração…` + `<small>alvo X</small>` | `Aguardando apuração…` + rodapé `Alvo: X` | `Aguardando apuração…` (copy pinada intacta) + rodapé `alvo X` |
| sem alvo | — | sem rodapé | sem rodapé (inalterado) |

**Critério de aceite (o que o teste mede, no arquivo, com espaços normalizados):**

1. o `<small>de {formatValue(alvo, …)}</small>` é filho do bloco do valor e condicionado a `alvo !== null`;
2. o rodapé é a ternária `progresso !== null ? "<pct>% do alvo" : "alvo <X>"` — "% do alvo" nunca nasce com `progresso` nulo (nada de "0% do alvo");
3. o literal antigo `Alvo: {formatValue(alvo, meta.unidade)}` saiu do JSX;
4. a projeção segue `ml-auto shrink-0 font-mono text-[10.5px] tabular-nums` e segue lendo `meta.projecao.projetado` (servidor — o cálculo do protótipo, `atualN*1.3`, **não** foi portado; lei 4 do pacote).

**O que NÃO mudou, de propósito:** `farolDaMeta` e a faixa lateral do farol (bolinha × faixa é
decisão [W], ADR 0385 "diferente não é erro"), o `Badge` de unidade, o `Sparkline`, as copies
pinadas `painel-meta-apurando`/`painel-meta-sem-historico`, o cabeçalho da seção
(`painel-metas-header`), `JanaCockpit.tsx` e o drawer. Contagem de flex/grid do arquivo:
**11 antes, 11 depois** (`layout:check`, ratchet por arquivo) — o `<small>` é inline justamente
pra não somar container.

**Testes — dois lados, de propósito:**

| lado | arquivo | o que mede | lane |
|---|---|---|---|
| arquivo | `Modules/Jana/Tests/Feature/PainelContratoTest.php` | o JSX escreve o sufixo, a ternária e não escreve o rótulo antigo | `PHP / Pest (Jana · MySQL)` |
| **DOM renderizado** | `tests/janaMetaCardRodape.spec.tsx` | o card **mostra** "64 de 200" e "32% do alvo"; sem apuração mostra "alvo 200" e nenhum "%"; a projeção só aparece quando o payload manda | `Jana Conversas Gate` (jsdom) |

O par existe porque Pest não monta React: a asserção de arquivo passa mesmo que a mudança seja
INERTE no runtime (classe LC-30). O spec jsdom fecha esse flanco — e ele já pagou por si na
escrita: a primeira versão stubava o `JanaCockpit` sem repassar `aposKpis` e renderizava **0
cards**, revelando que a seção METAS não é irmã do cockpit no JSX, é prop dele
(`JanaCockpit.tsx:724`). Ler o `Index.tsx` não teria mostrado isso.

**Mordida provada** (mutação no `Index.tsx`, restore = verde e arquivo byte-idêntico):

| mutação | Pest | jsdom |
|---|---|---|
| `% do alvo` vira `%` no ternário | 1 failed (3 assertions) | vermelho |
| o `<small>de {alvo}</small>` some | — | vermelho |

**Contador da lane** (a prova de que o spec de fato EXECUTA, não só existe — §5 2026-08-02):
`51 passed` antes, **`57 passed`** com este arquivo no comando; delta `+6` = os 6 casos daqui.


## UC-JPAIN-22 — o payload de `/ia` carrega origem, escopo e fonte da meta
Status: 🧪 (o teste existe e cita este UC; o `✅` vem do MANIFESTO — G-7 —, nunca de leitura.)

**Onda:** PR-3 do [`RUNBOOK-metas`](../../../../memory/requisitos/Jana/RUNBOOK-metas.md) §9.4 —
*"Fonte e apurações como seções"*.

Derivado das **duas Blades que esta onda absorve**, não do payload:

| Blade | O que ela mostrava | Onde estava no payload |
|---|---|---|
| `metas/show.blade.php` | slug · tipo · **origem** · **escopo** | slug e tipo já vinham; origem e escopo **não existiam** |
| `fontes/show.blade.php` | a `config_json` gravada, só-leitura | **não existia** |

O §9.4 é explícito: o PR-4 (cutover) *"não antes do drawer entregar o que a Blade fazia"*.
Enquanto os três campos não chegam, o cutover fica travado por construção.

**⚠️ ERRATA DO PRÓPRIO AUTOR — a primeira redação deste caso afirmava uma armadilha que NÃO
EXISTE, e o teste a derrubou.** Fica registrada, não apagada.

O que eu escrevi: que meta de **plataforma** (`business_id` nulo) entrava no Painel e que a
fonte dela cairia fora do escopo do parent, exigindo um `withoutGlobalScope` no eager-load.
**Falso.** O teste reprovou em `expect($plataforma)->not->toBeNull()` — o que sumia era a
**META**, nunca a fonte dela.

Medido depois, em [`app/Scopes/ScopeByBusiness.php`](../../../../app/Scopes/ScopeByBusiness.php):

| papel | escopo da META (`ScopeByBusiness`) | escopo da FONTE (`…ViaParent`) | concordam? |
|---|---|---|---|
| usuário comum | `business_id = <sessão>` **estrito** | `parent.business_id = <sessão>` | sim |
| superadmin | `= X` **ou** `IS NULL` | `= X` **ou** `IS NULL` | sim |

Nos **dois** papéis os dois escopos concordam, então a dispensa não resolvia nada — só removia
uma defesa Tier 0 sem necessidade, que é o oposto do que a ADR 0093 pede. **Ela saiu.**

**Corolário que fica:** o `orWhereNull('business_id')` da consulta do Painel é **inerte** para
usuário comum. Mexer nele é outro escopo, não deste PR — mas quem for mexer deve saber que ele
promete uma visibilidade que o escopo global já negou.

**Isolamento cross-tenant não se duplica aqui:** já tem dono em `MultiTenantIsolationTest` e
`EntitiesFilhasMultiTenantViaParentTest`.

**Critério de aceite (o que o teste mede, no payload real de `/ia`):**

1. a meta traz `origem`, `business_id` e `fonte` com `driver`, `cadencia` e `config_json` — some qualquer um dos três e o caso reprova;
2. `business_id` sai **cru** do servidor: a frase "Plataforma" × "Este negócio" é decisão da tela, não do back.

**Teste:** `Modules/Jana/Tests/Feature/PainelContratoTest.php` · lane `PHP / Pest (Jana · MySQL)`
(o arquivo está no run-set do `jana-pest.yml`, e o gatilho casa `Modules/Jana/**` +
`resources/js/Pages/Jana/**`). Fixtures criadas e removidas em `finally` — no CT 100 a base
persiste entre execuções, e assert que falha no meio deixaria linha para trás.

---

## UC-JPAIN-23 — o drawer desenha Identificação, Apurações gravadas e Fonte do número
Status: 🧪 (mesma regra do UC acima: veredito vem do manifesto.)

**Onda:** PR-3, a metade de FORMA.

Derivado da **âncora** `prototipo-ui/cowork/Wagner/jana-metas.jsx` — §`JmApuracoesSecao` e
§`JmFonteDrawer`, âncoras de SÍMBOLO
(`grep -n "function JmApuracoesSecao" prototipo-ui/cowork/Wagner/jana-metas.jsx`) — e do cabeçalho da
própria fonte, que declara absorver `metas/{index,create,edit,show}` **+** `fontes/show`
*"para dentro da tela única da Jana — sem rota nova"*. Precedência de FORMA: protótipo > teste >
casos > charter ([ADR UI-0029](../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)).

| seção | vem de | conteúdo |
|---|---|---|
| **Identificação** | `metas/show.blade.php` | identificador · agregação · origem · escopo |
| **Apurações gravadas** | §`JmApuracoesSecao` | tabela `Data ref.` × `Realizado`, título com a contagem |
| **Fonte do número** | §`JmFonteDrawer` + `fontes/show.blade.php` | driver · cadência · `config_json` + aviso de só-leitura |

**Decisões declaradas, para não parecerem esquecimento:**

- **A Série continua.** Ela mostra a FORMA da curva; a tabela mostra os NÚMEROS com data. A
  Blade entregava a segunda, e a âncora tem as duas. Remover uma seria perder informação.
- **O aviso não usa tom de alerta.** A âncora pede `tone="warn"`; o `Alert` do Design System
  só tem `default` e `destructive`, e **variante nova de componente do DS é decisão do dono**
  — então a copy carrega o sentido e nenhum token nasce aqui.
- **A data é recortada, não convertida.** `dataCurta()` fatia a string em vez de usar
  `new Date()`: `data_ref` é data **sem hora**, e construir um `Date` a partir de
  `"2026-05-14"` interpreta como UTC meia-noite — em fuso negativo volta um dia, e a apuração
  do dia 14 apareceria como 13.
- **A tabela é semântica com `<caption class="sr-only">`.** O `DataTable` compartilhado exige
  `pagination`/`endpoint`, que não existem aqui; forjar um paginador para 12 linhas seria pior.

**⚠️ O que este caso NÃO prova.** É asserção de **arquivo** — passa mesmo que a mudança seja
inerte no runtime (classe LC-30). Quem prova runtime é o **UC-JPAIN-22**, que lê o payload de
verdade. O par existe justamente porque Pest não monta React.

**Teste:** `Modules/Jana/Tests/Feature/PainelContratoTest.php` · mesma lane.

## UC-JPAIN-24 — quem assina as sugestões é a JANA, não quem está olhando a tela
Status: 🧪 (`npx vitest run tests/janaAcoesAutoria.spec.tsx` → **7 passed** jsdom local, 2026-09-18, com mordida provada por mutação; vira ✅ quando o manifesto `casos-results` aterrissar — o G-7 lê o manifesto commitado, não esta linha)

**Fonte:** âncora `prototipo-ui/cowork/Wagner/jana-merge.jsx` §`JmPainel` —
`AÇÕES QUE {data.person.name.toUpperCase()} SUGERE`, e `data.person` é
`{ name: "Jana", role: "Analista IA" }` (âncora de SÍMBOLO; re-localize com
`grep -n "person:" prototipo-ui/cowork/Wagner/chat-jana.jsx`). Precedência de FORMA:
protótipo > teste > casos > charter > SPEC ([ADR UI-0029](../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)).

**O defeito, e por que não é copy.** Até 2026-09-18 o título interpolava `firstNameUpper`,
derivado de `userName` (`IndexController` → `auth()->user()->name`), atribuindo ao **leitor**
sugestões que o **servidor** derivou de 5 regras sobre o dado dele. Troca de **sujeito**, não
divergência de rótulo — a frase afirmava autoria errada com selo de autoridade, que é o mesmo
vetor que o `JanaDrillDrawer` existe para evitar (não escrever o que o payload não sustenta).

> **O antes→depois REAL em produção é `VOCÊ` → `Jana`.** Medido em 2026-09-18 (`/ia`
> autenticado, biz=1, dark, DOM estabilizado — 1129 nós em 3 leituras iguais, 0 skeletons): o
> h2 renderizava **`Ações que VOCÊ sugere`**, porque `userName` chega **falsy** e o fallback
> `|| 'você'` de `:318` está ativo. O discriminante é a saudação, que sai `Boa tarde.` **sem
> nome** — e `:489` só omite o nome quando `userName` é falsy. Com o dado presente, o mesmo
> código assinaria com o nome de quem olha; os dois estados erram o mesmo sujeito, e o segundo
> é o mais grave.
>
> **A CAUSA foi medida (2026-09-18, sessão irmã; reproduzida aqui antes de citar): a tabela
> `users` não tem coluna `name`.** A migration `2014_10_12_000000_create_users_table.php:17-27`
> declara `surname`, `first_name`, `last_name`, `username`, `email`, `password`, `language` — e
> `app/User.php` não define `getNameAttribute` (0 hits; controle positivo no mesmo arquivo:
> `getUserFullNameAttribute` na `:310`, rc=0). Logo `optional(auth()->user())->name` devolve
> **null sempre**, em **6 linhas / 6 arquivos** — cinco em `Modules/Jana/Http/Controllers/`
> (`IndexController:54`, `ChatController:118`, `AlertasController:46`, `AcaoHitlController:51`,
> `SuperadminController:152`) **e um em `Modules/KB/Http/Controllers/MemoriaController.php:56`**,
> que serve a aba **Memória** — mesma área, outro módulo. Varredura pelo destino (`'userName'`)
> **no repo inteiro**, contada: escopar a `Modules/Jana/` devolve 5 e perde o sexto — é a mesma
> doença de grep estreito que este UC registra no eixo da copy, um nível acima. A personalização
> da área Jana **nunca funcionou**; são **dois defeitos empilhados**, não um.
>
> ⛔ **O conserto óbvio está errado: NÃO usar `user_full_name`.** No UltimatePOS `surname` é
> **PREFIXO**, não sobrenome — `resources/views/user/profile.blade.php:78` o rotula
> `__('business.prefix')` com `prefix_placeholder`. Como `getUserFullNameAttribute` retorna
> `"{surname} {first_name} {last_name}"`, o `split(' ')[0]` de `:318` devolveria **`"Sr."`**: a
> tela diria `Boa tarde, Sr.` e, sem este fix, `Ações que SR. sugere`. O campo certo é
> **`first_name`** (NOT NULL, migration `:19`). **Backend, PR próprio, fora deste** — endereçado
> por sessão irmã.

| eixo | âncora | tela viva (antes) | agora |
|---|---|---|---|
| sujeito | `data.person.name` = **Jana** | `firstNameUpper` — **o leitor** (`VOCÊ` hoje; o nome dele se o dado chegasse) | **Jana** |
| varia por quem olha? | não | **sim**, por construção | não |
| caixa alta | CSS `.jc-h2` `text-transform: uppercase` | CSS `uppercase` do `SectionTitle` | idem — **nunca foi divergência** |

**O que o teste trava (8 casos, `tests/janaAcoesAutoria.spec.tsx`):** o título nomeia a Jana ·
não é assinado pelo usuário · é **estável entre usuários** (mesma string para dois nomes
diferentes e para `undefined`) · **o caminho de prod** (`userName` falsy) também nomeia a Jana e
não diz "você" · a caixa alta continua vindo da CLASSE, não de `.toUpperCase()` no dado · e —
controle que impede o conserto de virar régua cega — **com `userName` presente a saudação segue
personalizada**: `firstName` é uso legítimo em `:489` e a âncora também personaliza ali. Sem esse
caso, trocar tudo por "você" passaria nos demais asserts. ⚠️ Esse último é contrato do
**componente**, não afirmação sobre o que a tela mostra hoje — ver a nota de produção acima.

**Mordida provada (ADR 0258 — todo ✅ tem que ter sido visto falhar).** Restaurado o
`firstNameUpper` no título, **4 dos 8 caem por `AssertionError`** — veredito de natureza, não
erro de execução: `expected 'Ações que WAGNER sugere' to contain 'Jana'`,
`expected 'Ações que WAGNER sugere' to be 'Ações que LARISSA sugere'` (exibe o defeito
literalmente) e `expected 'Ações que VOCÊ sugere' to contain 'Jana'` — este último **reproduz o
estado medido em produção**. Os 4 que seguem verdes são os controles do detector, a caixa alta e
a saudação — corretos em não depender do bug.

**Por que nenhum gate pegou, e a lição de método.** O dono do inventário
(`memory/requisitos/Jana/Index-visual-comparison.md` §R7) **tinha** a linha, com veredito **✅**:
escrita `"AÇÕES QUE <NOME> SUGERE"` × `"Ações que <Nome> sugere"`, ela abstraiu num placeholder
comum exatamente a variável em disputa. Mediu a FORMA da frase e calou sobre o conteúdo —
**falso-verde por 32 dias**, e pior que uma ausência, porque um ✅ desliga a cobrança. Corrigido
no mesmo PR (precedência: o perdedor se corrige junto). **Regra que fica:** par de copy que
interpola se registra com o VALOR resolvido de cada lado, nunca com o molde.

**Teste:** `tests/janaAcoesAutoria.spec.tsx` (vitest/jsdom — roda local, não é lane Pest).

## UC-JPAIN-25 — o nome do usuário vem de um atributo que EXISTE, e sem o prefixo
Status: 🧪 (`--filter=NomeExibicaoContrato` no CT 100 → **5 passed (14 assertions)**, 2026-09-18, com mordida provada nos DOIS sentidos; e a lane `PHP / Pest (Jana · MySQL)` do CI confirma `PASS` com os 5 casos ✓ no head. Vira ✅ quando o manifesto `casos-results` aterrissar — o G-7 lê o manifesto commitado, não esta linha)

**Fonte (três externas ao código consertado, nenhuma lida dele):**
1. `database/migrations/2014_10_12_000000_create_users_table.php:17-27` — as colunas que a tabela
   `users` de fato tem: `surname`, `first_name`, `last_name`, `username`, `email`, `password`,
   `language`. **Não há `name`.**
2. `resources/views/user/profile.blade.php:78` — rotula `surname` como `__('business.prefix')`,
   placeholder `prefix_placeholder`: no UltimatePOS `surname` é **PREFIXO** (Sr./Sra./Dr.), não
   sobrenome.
3. `app/Http/Middleware/HandleInertiaRequests.php:68` — o idioma canônico do projeto para "nome do
   usuário" já existia: `auth.user.name` é montado como `first_name . ' ' . last_name`.

**O defeito.** Os 6 call-sites que montavam `janaContext.userName` liam
`optional(auth()->user())->name` — atributo que o model não tem e a tabela não declara. Devolvia
**null em silêncio**, em todos os tenants, desde sempre. Cinco em `Modules/Jana/Http/Controllers/`
(`IndexController:54`, `ChatController:118`, `AlertasController:46`, `AcaoHitlController:51`,
`SuperadminController:152`) e — o que uma varredura escopada a `Modules/Jana/` **perde** — um em
`Modules/KB/Http/Controllers/MemoriaController.php:56`, que serve a aba **Memória** da mesma área.
Varredura contada com string fixa, imune a escaping: `auth()->user()->name` e `Auth::user()->name`
crus não existem no repo (rc=1).

> **Medido em produção (2026-09-18, `/ia` autenticado, biz=1, dark, DOM estabilizado — 1129 nós em
> 3 leituras iguais, 0 skeletons):** a saudação do brief saía **`Boa tarde.`**, sem vírgula e sem
> nome. Esse é o **discriminante** do UC: `:489` só omite o nome quando `userName` é falsy, então a
> frase seca prova o null sem precisar do payload. É a mesma causa que o [UC-JPAIN-24](#uc-jpain-24--quem-assina-as-sugestões-é-a-jana-não-quem-está-olhando-a-tela)
> descreve no título das Ações — **dois defeitos empilhados, um de dado e um de sujeito**, e cada um
> precisou do seu conserto: sem este, o título apenas trocaria `VOCÊ` por `WAGNER`.
>
> **Verificado depois do deploy (2026-09-18, mesma tela, 1116 nós):** `Boa noite, Wagner.` —
> o nome chega. Controle positivo na mesma leitura (`Receita 30 dias` presente pelo nó folha),
> controle negativo limpo.

**⛔ O conserto óbvio está ERRADO, e o caso 2 existe para travar isso.** `user_full_name`
(`app/User.php:312`) retorna `"{surname} {first_name} {last_name}"` — com o prefixo. Como o
consumidor faz `split(' ')[0]` (`JanaCockpit.tsx:318`), trocar por ele faria a tela exibir
**`"Sr."`**: um bug no lugar do outro, com o agravante de *parecer* consertado. O accessor
`nome_exibicao` (`app/User.php`) usa o idioma da fonte 3 e carrega os dois ⛔ no docblock.

**Por que o caso 2 é discriminante por construção** (§5 2026-09-05): com `surname='Sr.'`, os três
candidatos divergem — `nome_exibicao` → `"Wagner Rocha"`, `user_full_name` → `"Sr. Wagner Rocha"`,
`name` → `null`. Um assert que passasse nos três não provaria nada; este só passa no primeiro.

**Mordida provada nos dois sentidos** (não "passou" — *vi falhar*): com o `User.php` novo e os 6
controllers ainda antigos no container, `1 failed, 4 passed`, e o caso-guarda reprovou **listando
os 6 arquivos um a um**; com o fix completo, `5 passed (14 assertions)`. Os casos 1-4 são de
unidade e não tocam o banco — rodam sempre, nunca skipam (skip sai exit 0 — LC-13).

**Teste:** `Modules/Jana/Tests/Feature/NomeExibicaoContratoTest.php` (lane Pest `Jana · MySQL`).

## UC-JPAIN-27 — o h2 de seção é RÉPLICA da `.jc-h2`, não o h2 do golden

> ⚠️ **Este UC nasceu 25 e virou 27 — colisão de id entre sessões paralelas, pega antes do merge.**
> Duas sessões trabalhando na mesma tela no mesmo dia conferiram unicidade de `UC-JPAIN-25`
> **cada uma no seu instante**, e as duas viram 0 hits — porque o PR da outra ainda não existia.
> A irmã mergeou primeiro (`NomeExibicaoContratoTest.php`, 7 citações), então o 25 é dela e este
> cedeu. Medido antes de decidir: `git grep -ohE "UC-JPAIN-[0-9]+" origin/main` → ocupados até
> **25**; 26 é o UC-JPAIN-26 desta mesma leva; 27 livre.
>
> **A lição não é "conferir unicidade" — nós dois conferimos.** É que a checagem responde pelo
> **instante**, e num repo com sessões paralelas o id só está de fato livre quando o PR entra. Isso
> é a lápide §5 2026-09-04 ("prova por ID casado num corpus global") no eixo do **relógio**, não do
> escopo: lá o id colidia entre módulos, aqui colide entre sessões. E o dano seria o mesmo —
> **falso-crédito**: o gate acha o id no corpus e credita cobertura ao dono errado, sem ficar
> vermelho. Sintoma pelo qual isto apareceu: a sessão irmã avisou que "o UC-JPAIN-25 está citado
> por um teste mas não está no `casos.md`" — ela via o dela; eu tinha escrito o meu.
Status: 🧪 (`npx vitest run tests/janaSectionTitleReplica.spec.tsx` → **5 passed** jsdom local, 2026-09-18, com mordida provada por mutação; vira ✅ quando o manifesto `casos-results` aterrissar)

**Fonte:** âncora `.jc-h2` em `prototipo-ui/cowork/Wagner/chat-jana.css` §"── H2 ──" — âncora de
SÍMBOLO (`grep -n "jc-h2" prototipo-ui/cowork/Wagner/chat-jana.css`); o sub-rótulo é
`.jc-h2 .jm-h2-sub` em `jana-merge.css`. Precedência de FORMA: protótipo > teste > casos > charter
> SPEC ([ADR UI-0029](../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)),
sob [ADR 0388](../../../../memory/decisions/0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias.md) §D-1.

| eixo | âncora | tela viva (antes) | agora |
|---|---|---|---|
| font-size | **11px** | 14px (`text-sm`) | **11px** |
| font-weight | **700** | 600 (`font-semibold`) | **700** |
| letter-spacing | **0.88px** (`.08em`) | 1.4px (`tracking-widest`) | **0.88px** |
| família | **mono** (`var(--mono)`) | sans (herdada) | **mono** |
| gap do ícone | **7px** | 8px (`gap-2`) | **7px** |
| caixa alta | `uppercase` no CSS | `uppercase` no CSS | **idem — nunca divergiu** |
| sub-rótulo | `margin-left:auto` · mono 10.5px/400 · `.02em` | `ml-1` · sans 11px · `tracking-normal` | **alinhado** |

**Réplica LOCAL, e é o ponto do caso.** O `SectionTitle` é função interna do `JanaCockpit.tsx` —
não sai dele. Alinhar aqui **não** impõe a forma da Jana às outras telas, que é o erro invertido
que o `PageHeader`/`PageHeaderTabs` (37-38 telas) tornaria inevitável. Mesmo caminho que o
`JanaKpiCard` tomou em vez de mexer no `KpiCard` compartilhado.

**`font-mono` é tradução PROVADA, não suposta:** o espelho declara `--mono: var(--font-mono)`
(`styles.css:6446`), o token do projeto — mesmo mapeamento que o `JanaKpiCard` já usa em
`.jc-kpi-h`.

**O que o teste trava (5 casos):** todo h2 carrega a métrica da âncora · **nenhum** h2 mantém a
métrica antiga do golden (o par que impede o meio-termo) · o ícone é 14px como `.jc-h2 .ic` · o
sub vai pra **direita** (`ml-auto`, não `ml-1`) em mono 10.5px · e um detector com controle de
sensibilidade, porque `gap-2` casaria por substring dentro de `gap-[7px]` se o matcher fosse
ingênuo — ele compara **token inteiro**.

**Mordida provada.** Restaurada a métrica do golden, **2 de 5 caem**: `h2 … sem font-mono`
(ausência da nova) e `h2 ainda tem text-sm (métrica do golden)` (presença da antiga). Os dois
sentidos, de propósito.

⚠️ **A COR do sub-rótulo NÃO foi tocada — decisão declarada, não esquecimento.** A âncora usa
`var(--text-dim)`, que **não é definido no escopo desta tela**: `chat-jana.css` e `jana-merge.css`
não o declaram, e ele só aparece em `estoque-page.css` e `mockup-pages.css`, de outras telas. Sem
token resolvível, trocar a cor seria adivinhar — fica medido e aberto.

⚠️ **Frescor da fonte, declarado:** `cowork-mirror-freshness --sla` dá **⬜ INCONCLUSIVO**, não
SYNC — o `--compare` está completo e no SLA (705/705 sync, 2026-09-17), mas **5 arquivos do vivo
faltam no espelho** (`.gitignore`, `.thumbnail`, 3 do `_ds/`). O `chat-jana.css`, dono destes
números, **está entre os sync**, e o eixo novo "vê AUSÊNCIA, nunca MODIFICAÇÃO" — então os valores
aplicados vêm de arquivo provado fresco. O que segue por provar é o que o `_ds/` ausente poderia
redefinir, e é exatamente por isso que a cor ficou de fora.

**Teste:** `tests/janaSectionTitleReplica.spec.tsx` (vitest/jsdom — roda local, não é lane Pest).

### Emenda 2026-09-18 — o mesmo `SectionTitle` fechou o cabeçalho de METAS (4 linhas → 1)

O `Index.tsx` passou a usar este componente no cabeçalho do bloco METAS. Prod tinha **4 linhas**
(badge `METAS` + `Acompanhamento contínuo` + h2 de 20px `Metas ativas` + a contagem
`N metas ativas — visão consolidada do business`); a âncora `JmMetasSecao` tem **uma**
(`<h2 class="jc-h2">` + controles em `margin-left:auto`).

**A trava era de CONTRATO, não de forma.** `Metas ativas` e `Acompanhamento contínuo` eram copy
**pinada** em `governance/design/contracts/jana-painel.contract.json` §`painel-metas-header`, e a
`_nota_metas_header` (2026-08-31) registrava a divergência dizendo *"não corrigida porque copy
pinada é lei [W]"*. **[W] escolheu remover** quando perguntado diretamente — copy de contrato está
na lista curta de soberania real (`memory/proibicoes.md` §Comportamento), ao lado de merge e
valor/estoque, então não era decisão do agente. O contrato foi atualizado no MESMO PR, e a nota
de 2026-08-31 **ficou**, com a revogação ao lado.

**Duas pegadinhas medidas, que custam tempo a quem repetir:**

1. **`data-contract` tem que ser a string LITERAL no arquivo do `alvo`.** Passar `dataContract`
   camelCase entrega o atributo no DOM e **mesmo assim** reprova — `X seção "painel-metas-header"
   sem âncora data-contract no alvo` —, porque o gate faz busca textual. Por isso a prop do
   `SectionTitle` se chama `'data-contract'`, com hífen.
2. **O mock do `JanaCockpit` em `janaMetaCardRodape.spec.tsx` quebrou** ao surgir o export
   `SectionTitle`: os 5 casos do **UC-JPAIN-21** abortaram com `No "SectionTitle" export is
   defined on the … mock`, e a mensagem **não diz que é do mock** — parece defeito no card de
   meta. O stub tem de renderizar os children, porque os botões do cabeçalho passaram a morar lá
   dentro. É a mesma armadilha que aquele arquivo já documentava para o stub do cockpit.

⚠️ **O seletor de período e o `Farol | Cadastro` NÃO vieram** — seguem ❌ **backend**
(`IndexController::buildMetasPayload` carrega só `periodoAtual`; sem a série de janelas no payload
não há o que filtrar). O cabeçalho fechou na FORMA e na COPY; a **capacidade** continua pendente.

## UC-JPAIN-26 — a aba da Jana usa a métrica da âncora DELA, sem mover as outras 5 áreas
Status: 🧪 (`npx vitest run tests/pageHeaderTabsDensity.spec.tsx` → **6 passed** jsdom local, 2026-09-18, com bite-test comparativo; vira ✅ quando o manifesto `casos-results` aterrissar)

**Duas âncoras, porque o componente serve mais de um dono:**

| densidade | âncora | métrica |
|---|---|---|
| `default` | protótipo do **Clientes** (`clientes-page.css` `.cli-moduletopnav-tab`), fixado por [W] 2026-07-14 | 14px/400 · `px-3` |
| `compact` | âncora da **Jana** (`jana-merge.jsx` §`JmTabs`) | 13px/500 · `padding 0 14px` |

**Parâmetro em vez de réplica local — decisão [W] 2026-09-18**, escolhida sobre outras três
(deixar como está · componente de abas próprio da Jana · rever o protótipo do Clientes). Razão
medida: o `JanaSubNav` **delega inteiramente** ao `PageHeaderTabs` e não tem markup de aba
próprio, então replicar custaria duplicar a barra inteira — diferente do `JanaKpiCard`
(UC-JPAIN-20), que replicava um card. As outras **5 áreas** não passam a prop e seguem no
`default`, byte-idêntico (há um caso que prova a identidade de `omitir` vs `default`).

**⚠️ O achado que motivou este UC não é a métrica — é que a justificativa registrada era FALSA.**
O `Index-visual-comparison.md` dizia *"13×14px fica (fidelidade travada em
`pageHeaderTabsFidelity.spec`)"*. Medido por mutação: trocado o default para `compact`, aquele
spec segue **13/13 VERDE**. Ele trava radius, underline `--accent`, pill do contador e o peso da
aba **ATIVA**; font-size, padding e o peso da **inativa** passavam livres. O item não estava
travado — estava **não-feito**, com aparência de decisão técnica. Mesma família do falso-verde do
`<NOME>` (UC-JPAIN-24): **garantia afirmada e não existente desliga a cobrança melhor que um
buraco declarado.**

**O bite-test é COMPARATIVO, e é a prova do buraco.** Na mesma mutação (`default` → `compact`):

| spec | veredito |
|---|---|
| `pageHeaderTabsFidelity` | **13/13 verde** — cego |
| `pageHeaderTabsDensity` (este) | **2 de 6 caem** — `default perdeu text-sm` + comparação de className inteira |

**O que o teste trava (6 casos):** o `default` não se mexe (a rede que faltava) · omitir a prop é
idêntico a `default` · `compact` entrega 13px/`px-[14px]`/500 · a aba **ATIVA** segue
`font-semibold` nas **duas** densidades · `density` não carrega radius nem cor junto (guarda de
vizinhança) · e um detector com controle de sensibilidade, porque `px-3` casaria por substring
dentro de `px-[14px]`.

⚠️ **Ícone e badge NÃO eram gaps.** O ícone já estava corrigido, e o `badge` opt-in **existe no
componente** (pill do contador, cores de ativo/inativo travadas por 5 casos do spec de
fidelidade). O que falta para as abas mostrarem `Conversa 3` é o **contador chegar do
`DataController`** — backend, com raio nas 4 telas da área, não UI ausente.

**Teste:** `tests/pageHeaderTabsDensity.spec.tsx` (vitest/jsdom — roda local, não é lane Pest).

## UC-JPAIN-28 — o tier Pro governa brief, análises e ações; METAS nunca

Status: 🧪 (`npx vitest run tests/janaPainelGatingPro.spec.tsx` → **9 passed** jsdom local,
2026-09-21, com mordida provada por mutação; vira ✅ quando o manifesto `casos-results` aterrissar)

**Fonte:** âncora `prototipo-ui/cowork/Wagner/jana-merge.jsx` §`JanaPage` — âncora de SÍMBOLO
(`grep -n "upsell({ t:" prototipo-ui/cowork/Wagner/jana-merge.jsx`). Pedido descido pelo playbook
do Cowork em `cowork-inbox/jana/playbook/01-painel.gating-pro.md` (ONDA 01), recebido no handoff 28.

A âncora desenha um produto de **dois planos**. A produção renderizava tudo pra todo mundo — e
exibia o selo "Grátis" do `JanaPlanoBadge` **ao lado** do conteúdo que o `/ia/pro` vende
([ADR 0140](../../../../memory/decisions/0140-jana-pro-produto-comercial-saas.md)). O `useJanaPro()` já era lido
no `Index.tsx`, mas só alimentava o badge: o tier não governava seção nenhuma.

| seção | Grátis | Pro |
|---|---|---|
| header · abas · nota-mob | renderiza | renderiza |
| **brief diário** | **upsell** `O brief diário é do plano Pro` | `BriefDiario` |
| KPIs (3) | renderizam, **sem drill** | renderizam, clicáveis |
| **METAS ATIVAS** | renderiza | renderiza |
| h2 `ANÁLISES PRINCIPAIS` | renderiza (sem a sub-linha) | renderiza + sub-linha |
| **as 5 análises** | **upsell** `As 5 análises são do plano Pro` | grade |
| **AÇÕES QUE JANA SUGERE** | **ausente** — h2 e faixa não montam | renderiza |

**Zero fundação.** `jana.pro` já chegava como shared prop **lazy** (`HandleInertiaRequests.php:170`
→ `janaPlanoPro` lendo `jana_pro_module` na assinatura ativa), default `false` — fail-safe: na
dúvida, Grátis. Nenhum campo, nenhuma query, nenhum endpoint. O `SellsCockpitAggregator` segue
apurando igual: esconder card não economiza cálculo, e o drawer já diz isso ao usuário.

**O marcador da grade é `Top 5 clientes`, e isso é parte do caso.** "Inadimplência" **não** serve:
a descrição do upsell de análises contém a palavra ("Inadimplência, faturamento, concentração,
churn ouro e métodos de pagamento"), então usá-la daria positivo no Grátis — mediria o upsell e
chamaria de grade. É a armadilha de §5 2026-09-16 (prefixo que também é prefixo de outra coisa),
e o primeiro `describe` do spec **prova a unicidade** antes de qualquer caso usá-la.

**O `PARAR SE` do pedido foi medido, e não disparou.** A ficha declarava no §8 não ter lido os dois
donos do contrato. Lidos:

- `governance/design/contracts/jana-painel.contract.json` declara **6** seções —
  `painel-cta-conversar`, `painel-metas-header`, `painel-metas-vazio`, `painel-meta-apurando`,
  `painel-meta-sem-historico`, `painel-plano` — e **nenhuma** é brief/análises/ações. A `ordem` são
  as 3 do eixo METAS, que esta onda não toca.
- `Modules/Jana/Tests/Feature/PainelContratoTest.php` não tem **nenhuma** referência ao tier:
  `grep -noE "jana\.pro|janaPro|'pro'|useJanaPro"` → **0**, com `grep -c "UC-JPAIN"` → **62** de
  controle positivo. Os **110** hits de `pro` que um grep ingênuo devolve são substring de
  *processo · produto · próprio · prova*.

**Os dois casos que pareciam colidir medem o texto-FONTE, não o render** — e por isso sobrevivem:

- **UC-JPAIN-16** extrai rótulos de `<Button>` do arquivo. Rodadas as regex **literais** dele
  contra o arquivo editado, a lista sai idêntica às 5 dívidas declaradas (`Disparar régua WhatsApp
  pros atrasados`, `Exportar`, `Investigar queda ticket médio`, `Ouvir áudio`, `Ver top devedores`):
  os 2 botões novos ficam de fora porque estão dentro de `<Link href="/ia/pro">`, que o `$wrapper`
  do extrator reconhece como quem dá comportamento ao filho.
- **UC-JPAIN-18** recorta o bloco `<KpiGrid>` e segue devolvendo
  `['Receita 30 dias','A receber vencido','Ticket médio']` **na ordem**, com
  `<KpiGrid cols={4} className="gap-2.5">` intacto. O gating tirou o `onClick`, não os cards.

**O que o teste trava (9 casos):** dois controles de sensibilidade (o marcador da grade não é
substring da copy do upsell; o render Pro contém os 3 marcadores, senão a ausência no Grátis não
provaria nada) · brief vira upsell com copy literal · grade vira upsell com copy literal · a faixa
de ações some **inteira**, sem virar um terceiro upsell · os 2 upsells levam a `/ia/pro` e o botão
não nasce mudo · o Grátis tem **menos** botões que o Pro (direção, não número — se o gating for
revertido, empatam) · `aposKpis` renderiza nos **dois** planos (METAS nunca gated) · e o default da
prop protege o `Chat.tsx`, outro consumidor do cockpit.

**Mordida provada.** Revertido o gating nos 3 pontos (`{pro ? (` → `{true ? (` nos dois blocos e
`{pro && acoes.length > 0 &&` → `{acoes.length > 0 &&`), **4 de 9** caem — brief, grade, ações e os
links `/ia/pro`. Os 5 restantes passam porque são controles que a mutação não alcança, e isso é o
desenho, não sobra.

⚠️ **`overdueCount: 1` no fixture não é decorativo:** é ele que faz `acoes` ter ≥1 item
(`JanaCockpit.tsx` §`const acoes` → `if (overdueCount > 0)`). Sem isso o caso das ações ficaria
verde nos dois planos por vacuidade — um teste que não pode reprovar (§5 2026-09-05).

> ⚠️ **ERRATA (2026-09-21, ~1h depois) — o parágrafo seguinte CADUCOU, e em canon isso não é
> detalhe: afirmação de impossibilidade vira instrução de desistência pra próxima sessão
> (§5 2026-09-01). O smoke autenticado EXISTE, e quem o produziu foi o próprio CI.**
>
> O `visual-regression` renderiza a tela **logada** (`tests/Browser/CoreScreens/PixelBaselineTest.php`,
> escopo `VISREG_SCREENS=["Jana"]`) e publica `pixel-diff-views/jana.html` com **baseline × atual ×
> diff**. Medido no run `35592531986` do [#7587](https://github.com/wagnerra23/oimpresso.com/pull/7587):
> `VisregThreshold [Jana]: diff 2.0169% > τ_alto 2.0000%`.
>
> **O que o render ATUAL mostra** (conferido imagem a imagem, depois de descobrir que a ordem no
> HTML é `Diff · Baseline · Atual` — rotulei errado na primeira leitura e quase reportei o inverso):
> selo `plano Grátis` no header · o brief **substituído** pelo upsell, com a copy literal e o botão
> `Ver Jana Pro` · o h2 `ANÁLISES PRINCIPAIS` **sem** a sub-linha `clique num card pra ver de onde
> vem o número`, que a baseline tinha · `METAS ATIVAS` de pé com o `painel-metas-vazio` intacto.
> O ícone `calendar` do upsell renderizou como calendário, o que confirma em runtime a medição do
> resolvedor do `Icon`.
>
> Isto fecha o risco de **LC-30** nesta onda: a declaração `pro={pro}` **não** é inerte — o efeito
> está no DOM renderizado, não só no fonte.
>
> **O que segue aberto**, e é bem menor que o texto abaixo sugere: o recorte do DoD §9 que o visreg
> não cobre — **light mode** e o par `jana.pro` **true/false lado a lado** (o CI renderiza dark, e o
> tenant de teste não tem `jana_pro_module`, então só o ramo Grátis foi visto). E a **aprovação [W]
> (F1.5)** da baseline nova, que é dele.
>
> O texto original fica, não apagado — era honesto quando foi escrito, e a lição é sobre o tempo
> verbal, não sobre a medição.

⚠️ ~~**Smoke autenticado NÃO foi feito, e nada aqui afirma render medido.**~~ `oimpresso.com/ia` e
`staging.oimpresso.com/ia` devolvem **302** sem sessão, e o `launch.json` deste repo só serve
protótipo estático. O DoD §9 do pedido (4 screenshots: dark/light × Pro/Grátis) segue **aberto**.
O que foi medido é: tsc (0 erros nos 2 arquivos tocados, contra 307 pré-existentes no repo),
eslint (2 arquivos analisados, 0/0), `layout:check` (total 1695, **igual** ao baseline), as regex
reais dos UC-16/18, e a copy **byte a byte** contra a âncora (5 strings, com controle negativo).

**Teste:** `tests/janaPainelGatingPro.spec.tsx` (vitest/jsdom — roda local, não é lane Pest).

## UC-JPAIN-29 — business sem histórico vê UM estado de página, não 6 caixas vazias

Status: 🧪 (`npx vitest run tests/janaPainelEstadoVazio.spec.tsx` → **11 passed** jsdom local,
2026-09-21, com mordida provada por **duas** mutações; vira ✅ quando o manifesto
`casos-results` aterrissar)

**Fonte:** âncora `prototipo-ui/cowork/Wagner/jana-merge.jsx` §`JanaPage` — âncora de SÍMBOLO
(`grep -n "ainda não tem histórico" prototipo-ui/cowork/Wagner/jana-merge.jsx`). Pedido descido
pelo playbook do Cowork em `cowork-inbox/jana/playbook/02-painel.estado-vazio.md` (ONDA 02),
recebido no handoff 28.

A produção tinha empty-state **por bloco**: "Sem histórico" no sparkline, "Sem dados de clientes",
"Sem pagamentos registrados", "Ninguém de peso parou de comprar", "Nenhuma meta cadastrada ainda".
Num business recém-onboardado o resultado é uma tela de caixas vazias e `R$ 0,00` repetido — cada
bloco dizendo baixinho que não tem dado, **nenhum** dizendo por quê nem o que fazer. É exatamente
quem mais precisa da frase.

**Copy literal da âncora:** título `A Jana ainda não tem histórico pra analisar` · descrição
`Ela precisa de pelo menos um mês de movimento pra montar o brief, os KPIs e as análises. Enquanto
isso, pergunte o que quiser na aba Conversa.` · ação `Ir para a Conversa` → `/ia/conversa`.

### O predicado, e por que `coworkAggregates` fica de fora

```
semHistorico = sellKpis.total === 0
            && insightsAggregates.totalAReceber === 0
            && insightsAggregates.topClientes.length === 0
            && insightsAggregates.methodsAgg.length === 0
```

Tudo já chega **sem defer** (o controller declara `Inertia::defer` só em `coworkAggregates`).
Nenhum campo novo, nenhuma query, nenhuma flag de servidor.

`coworkAggregates` fica **fora de propósito**: é deferida, e `undefined` ali significa "ainda não
chegou", não "não tem dado". Misturar os dois faria a tela **piscar** o empty-state durante o
carregamento normal. **Precedência:** `carregandoCockpit` (skeleton) → `semHistorico` → conteúdo.

⚠️ **A dúvida que a ficha declarou aberta está RESPONDIDA, e não por suposição.** Ela pedia
confirmar que `topClientes`/`methodsAgg` vêm `[]` e não `null`. As linhas imediatamente acima do
predicado já fazem `methodsAggList.reduce(...)` e `topClientesList.reduce(...)` **direto, sem
guard, desde sempre** — se o servidor mandasse `null`, a tela estaria quebrada hoje em qualquer
business. É o comportamento vivo que prova.

### O que NÃO desceu, e por quê

O protótipo bifurca em `vazio || erro` e mostra duas copies. **Só o ramo `vazio` virou pedido.**
O ramo de **ERRO** ("Não consegui ler os dados da empresa agora" + "Tentar de novo") **não tem
fonte no `main`**: o `IndexController` não emite sinal de falha — `buildSellKpis` /
`buildInsightsAggregates` resolvem ou estouram, e um estouro vira página de erro do Inertia, não
este card. Exportá-lo seria pedir UI pra um estado que o servidor não sabe produzir. Fechar exige
decidir (a) o que é falha recuperável do aggregator, (b) como ela chega à Page, (c) o que "tentar
de novo" recarrega — **PR de fundação + decisão [W]**, não esta onda.

### ⚠️ `variant="first"` não existe — e a escolha está declarada

A ficha pedia `EmptyState` com `variant="first"`. Medido: o componente declara
`type Variant = 'default' | 'search' | 'error' | 'success'`
(`resources/js/Components/shared/EmptyState.tsx`). Ficou no **`default`**, que é o mais próximo da
intenção (primeiro uso, não erro nem filtro). Inventar uma variante nova pra um caso seria criar
token de UI por atalho.

**Ícone conferido contra o resolvedor real**, e isso não é zelo excessivo: o `Icon` faz
`map[name] ?? map[toPascalCase(name)] ?? Icons.Circle` — o fallback é **silencioso**, então nome
errado vira um círculo em produção com o CI verde. Replicado o `toPascalCase` e rodado contra o
`lucide-react` do projeto: `sparkles` → `Sparkles` ✅ (e o controle negativo `xxx-nao-existe-yyy`
cai no `Circle`, provando que a sonda discrimina).

**O que o teste trava (11 casos):** um controle de sensibilidade (com movimento, o corpo renderiza
e o estado vazio **não** aparece — senão "apareceu" não distinguiria predicado de componente que
sempre mostra) · a copy literal dos três textos · o corpo some **inteiro**, incluindo os
empty-states por bloco · a saída leva a `/ia/conversa` e o botão não nasce mudo · **METAS
continuam** · o anti-flicker durante o defer · e as **quatro pernas** do predicado, uma por caso,
mais o sentido inverso.

**Mordida provada, em dois eixos:**

| mutação | caem |
|---|---|
| `if (false && !carregandoCockpit && semHistorico)` — desliga o ramo | **5 de 11** |
| `if (semHistorico)` — tira o guard do defer, reintroduz o **flicker** | **exatamente 1** — o anti-flicker |

A segunda é a que vale mais: **só** o caso do flicker cai, provando que ele mede exatamente o que
o nome diz, e não se apoia nos vizinhos.

⚠️ **METAS e vendas são eixos SEPARADOS.** Um business pode ter meta cadastrada e zero venda, e
vice-versa. Este estado cobre o eixo VENDAS; a seção METAS segue com o `painel-metas-vazio` dela,
cuja copy é **pinada em contrato**. Fundir os dois apagaria copy que é lei [W] — e por isso há caso
dedicado provando que `aposKpis` renderiza dentro do estado vazio.

**Nenhuma âncora do contrato é afetada**, e isto foi medido, não deduzido: as 6 seções do
`jana-painel.contract.json` renderizam em `Index.tsx` (5) e `JanaPlanoBadge.tsx` (1) — **nenhuma no
`JanaCockpit.tsx`**, que é o único arquivo que esta onda toca. O UC-JPAIN-09 (âncoras + ordem) fica
intacto.

⚠️ **A errata do UC-JPAIN-28 NÃO cobre este caso, e a diferença é o predicado.** Lá o
`visual-regression` do CI serviu de smoke autenticado porque o gating Pro aparece no render com
dados. Aqui não serve: o `$seedJanaVisregFlow` semeia **uma venda vencida**, então
`sellKpis.total > 0`, `semHistorico` é `false`, e o empty-state **não monta** naquele render — o
visreg fotografa exatamente o ramo que este UC **não** trata. Quem quiser o smoke deste caso
precisa de um business **sem vendas**, que o seed não produz.

⚠️ **Smoke autenticado NÃO foi feito.** `/ia` devolve **302** sem sessão em prod e staging, e o
`launch.json` só serve protótipo estático. O DoD §9 do pedido (4 screenshots: dark/light × com e
sem vendas, mais a prova do flicker com throttle) segue **aberto** — nada aqui afirma render
medido. **Business zerado não foi observado em prod**: o cenário é inferido do payload, como a
ficha já declarava.

**Teste:** `tests/janaPainelEstadoVazio.spec.tsx` (vitest/jsdom — roda local, não é lane Pest).
⚠️ O `eslint` **não cobre `tests/`** (`File ignored because no matching configuration was
supplied`), então "N arquivos analisados" naquele diretório não é "N verificados".

---

## UC-JPAIN-31 — a grade das análises é RÉPLICA da `.jc-grid`: 3 colunas, gap 12px, breakpoints da âncora

> ⚠️ **Este UC nasceu 30 e virou 31 — 2ª colisão de id nesta tela, mesmo mecanismo do UC-JPAIN-27.**
> Medi unicidade em `origin/main` e vi ocupados até 29; a sessão irmã do `h1` mediu no mesmo dia,
> viu o mesmo, e abriu o [#7637](https://github.com/wagnerra23/oimpresso.com/pull/7637) com o 30
> antes de mim. Ela ofereceu ceder; **cedi eu**, porque o PR dela já estava aberto e reverter id em
> PR publicado custa mais que renumerar em árvore local. A nota do UC-JPAIN-27 já dizia a lição
> com todas as letras — *"a checagem responde pelo INSTANTE, e o id só está de fato livre quando o
> PR entra"* — e ela se confirmou **13 dias depois**, com 5 sessões na mesma tela. Não é "conferir
> melhor": é que a conferência não pode ser conclusiva num repo com sessões paralelas.

Status: 🧪 (`npx vitest run tests/janaGradeAnalisesReplica.spec.tsx` → **6 passed** jsdom local,
2026-09-21, com mordida provada por mutação; vira ✅ quando o manifesto `casos-results` aterrissar)

**Fonte:** âncora `.jc-grid` e `.jc-acoes` em `prototipo-ui/cowork/Wagner/chat-jana.css`
§"── Análises ──" e §"── Ações sugeridas ──" — âncora de SÍMBOLO
(`grep -n "jc-grid" prototipo-ui/cowork/Wagner/chat-jana.css`). Precedência de FORMA:
protótipo > teste > casos > charter > SPEC
([ADR UI-0029](../../../../memory/requisitos/_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)),
sob [ADR 0388](../../../../memory/decisions/0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias.md) §D-1.

| eixo | âncora | tela viva (antes) | agora |
|---|---|---|---|
| colunas (>1100px) | **3** | 2 (`lg:grid-cols-2`) | **3** (`min-[1101px]:grid-cols-3`) |
| colunas (761–1100px) | **2** | 2 | **2** (`min-[761px]:grid-cols-2`) |
| colunas (≤760px) | **1** | 1 | **1** (`grid-cols-1`) |
| gap | **12px** | 16px (`gap-4`) | **12px** (`gap-3`) |
| `.jc-acoes` padding | **0** | 24px 0 (`py-6` do `Card`) | **0** (`py-0`) |

**Medido em runtime, não deduzido** (2026-09-21, Chrome, mesma janela, viewport 2560, dark nos dois
lados, container **2237px idêntico** ⇒ a diferença não vinha de largura disponível): âncora
`737.656px × 3` · prod `1110.5px × 2`. Aplicadas a className nova **e** as regras do CSS compilado
ao DOM da prod, ela devolveu `737.656px 737.672px 737.672px` — o mesmo valor da âncora — e reverteu
limpo. Canário rodado **nos dois lados** antes de concluir.

**Por que `min-[761px]`/`min-[1101px]` e não `lg:`/`xl:`.** A âncora quebra em `max-width: 1100px`
e `max-width: 760px`; `lg:` é 1024px e `xl:` é 1280px. Com `lg:` a prod parava em 2 colunas no
monitor de **1280px** da ROTA LIVRE, onde a âncora já mostra 3 — aproximar num breakpoint É a
divergência, não uma tradução dela. As duas regras foram **provadas no CSS compilado** (com
controle positivo e negativo), porque classe que o Tailwind não gera é correção inerte:
`@media (min-width:1101px){.min-\[1101px\]\:grid-cols-3{grid-template-columns:repeat(3,minmax(0,1fr))}}`.

⚠️ **O `gap: 24px` das Ações era o sintoma, não a causa.** Medido: o `Card` tem **UM** filho, e gap
sem segundo filho não separa nada. Quem produzia o respiro de 24px é o `py-6` do `Card` canon
(`ui/card.tsx:29`). A rodada de 2026-09-07 mediu certo e nomeou a propriedade inerte; zerei os dois
porque a âncora tem os dois zerados e porque `gap: normal` em flex **é** `0px`.

⚠️ **O `margin-bottom` (18px na âncora × 16px na prod) NÃO entra neste UC.** Medido: o 16px vem do
`space-y-4` do container da página — a className da grade não declara margem —, logo rege **todas**
as seções (KPIs, Metas, Análises, Ações). É ritmo vertical da tela, não da grade; convergir ali é
decisão [W], não conserto de passagem.

⚠️ **Consequência declarada:** com 3 colunas o card de análise cai de **1110,5px → ~737,7px**
(−33,6% naquela viewport). O sparkline de Faturamento é `preserveAspectRatio="none"`, então a curva
**comprime horizontalmente** (altura travada em 40px). A sessão irmã que mede os gráficos foi
avisada **antes** de medir, para carimbar os números dela como "medidos em grade de 2 colunas".

**Teste:** `tests/janaGradeAnalisesReplica.spec.tsx` (vitest/jsdom — roda local, não é lane Pest),
6 casos: 1 de controle positivo/negativo do detector + 5 de contrato, incluindo o ramo **sem Pro**
(onde a âncora mostra upsell e não pode haver grade). Mordida provada: restaurar `gap-4
lg:grid-cols-2` derruba **2** asserts — um por ausência da nova, outro por presença da antiga —, e
remover `py-0 gap-0` derruba **1**. Arquivo restaurado com **hash conferido** após a mutação
(`96dff22f8a951e5f` antes e depois), para nenhum mutante sobreviver no diff.
