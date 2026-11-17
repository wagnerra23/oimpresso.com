---
id: modules-producao-pages-producao-os-show-casos
casos: Produção · Ficha da OS · /producao/ordens/{id}
irmaos: FichaDaOs.charter.md (lei) · FichaDaOs.tsx (conteúdo, no painel do Quadro e da Linha)
tecnica: Caso de uso = narrativa do PCP + critério de aceite verificável (Dado/Quando/Então)
por_que: a OS por item só tinha rota JSON para Liberar, Refazer e Cancelar; sem tela, a OS de empresa sem sinal mínimo ficava parada em Aguardando liberação.
owner: wagner
last_run: "2026-11-17"
last_run_ci: "_pendente_ — o veredito entra quando a lane MySQL rodar o ProducaoFichaOsContratoTest."
---

# Casos de Uso & Aceite — Produção · Ficha da OS (`/producao/ordens/{id}`)

> **Âncora:** `prototipo-ui/cowork/Wagner/cowork-inbox/producao/Producao.casos.md` (UC-PCV-10) e as ações de tela da
> OS por item de `ProcessoDaOrdem::ACOES` (ADR 0436). Os UCs não derivam do `.tsx`.
> Teste: `Modules/Producao/Tests/Feature/ProducaoFichaOsContratoTest.php`.

## UC-PCV-10 · Ficha da OS · `must`

**Dado** uma OS por item com duas etapas programadas (2,0 h e 0,5 h previstas) **Quando** a primeira recebe 1,5 h de
apontamento **Então** a Ficha mostra a situação da OS e as etapas na ordem do roteiro, cada uma com setor, posto,
responsável, apontado × previsto (1,5/2,0 e 0,0/0,5) e a situação da etapa · o apontado acima do previsto aparece em
vermelho · o progresso conta só as etapas não canceladas.

Status: 🧪

## UC-PCOSF-01 · Os botões seguem o papel · `must`

**Dado** a OS em Aguardando liberação **Então** só quem tem `producao.liberar` vê o Liberar, com a lista de quem pode
ser responsável de cada etapa aberta · só quem tem `vendas.gerente` vê o Cancelar item (em Aguardando liberação ou
Aguardando material) · o Refazer aparece só em Retrabalho, para `producao.liberar` · quem não tem papel não vê
nenhum dos três. Esconder é só conforto: as rotas conferem o papel de novo (UC-PCOSR-01..03, no Apontamento).

Status: 🧪

## UC-PCOSF-02 · Tier 0 e portões · `must`

**Dado** a OS de outra empresa **Quando** abro o endereço dela **Então** 404, e a da minha empresa abre · sem
`producao.access`, ou sem o módulo no pacote, 403.

Status: 🧪

## UC-PCOSF-03 · Aba Custo, só leitura · `must` valor

**Dado** a OS em andamento com 1,5 h apontadas num posto de máquina a 80,00 por hora e nada de material **Quando** quem
tem `producao.desempenho.ver` abre a aba Custo **Então** o realizado é 120,00 (máquina 120,00 · pessoa 0,00 ·
material 0,00 · terceiros 0,00) · o mesmo número sai do `GET /producao/ordens/{id}/previsto-realizado` (dupla prova:
a Ficha e o endpoint, mais a conta à mão) · nada é gravado (`custo_real` e apontamentos iguais antes e depois) · sem a
permissão a aba não aparece e o custo vem nulo. A Ficha não faz conta nova: formata o que o
PrevistoRealizadoDaOrdem e o CustoRealService devolvem. Custo por etapa (os 85 por hora únicos do protótipo) fica fora:
é a decisão B8, em aberto com [W].

Status: 🧪

## UC-PCOSF-04 · Aba Materiais · `must`

**Dado** a receita do produto pedindo 124 m² de lona para a quantidade da OS e 133,4 m² já apontados na etapa
**Então** a aba Materiais mostra previsto 124, apontado 133,4 e o saldo "estourou 9,4" em vermelho (o mesmo exemplo do
UC-PCV-16) · com a OS em Aguardando material aparece também o que falta, quanto tem e o que está a caminho ·
produto sem receita e sem apontamento mostra a lista vazia com a explicação. Só leitura: quem baixa é o
apontamento (US-PCP-021). O saldo sai do backend em décimos de milésimo inteiros, sem float.

Status: 🧪

## UC-PCOSF-05 · Aba Arte e arquivos · `must`

**Dado** a OS com a v1 aprovada pelo cliente e uma v2 enviada e ainda sem resposta **Então** a aba lista v2 e v1, da
mais nova à mais antiga, e marca a v1 como a que vale para a produção (UC-PCV-11) · com a última aguardando ou com
ajuste pedido aparecem "Reenviar link" e "Enviar nova prova" (UC-PCART-07); com a última aprovada aparece "Trocar e
pedir nova aprovação" · pedido de arte de outra empresa não aparece · a versão é a gravada no envio (UC-PCART-09):
"Reenviar link" com o mesmo arquivo aparece com o mesmo número, como no celular do operador.

Status: 🧪

## UC-PCOSF-06 · Quadro e Linha abrem a Ficha · `must`

**Dado** uma OS por item no Quadro e na Linha do tempo **Quando** clico no número do cartão do Quadro, ou clico (sem
arrastar) ou dou Enter no bloco da Linha **Então** a Ficha abre no painel lateral (UC-PCOSF-08 e UC-PCOSF-09) · o endereço
`/producao/ordens/{id}` leva ao Quadro com a Ficha aberta (UC-PCOSF-10) · arrastar o bloco para reprogramar não abre a Ficha · a OS da Comunicação Visual
(`comvis_os`) não tem Ficha e não ganha link. Teste: `tests/js/producao-ficha-link.test.ts` (endereço e controle); o clique no
navegador se confere no smoke.

Status: 🧪

## UC-PCOSF-07 · O Quadro entrega a Ficha para o painel lateral · `must` `[T0]`

**Dado** o Quadro aberto com `?os={id}` de uma OS por item da empresa **Então** ele entrega a Ficha (`ficha`: OS, etapas e
botões), a mesma da página, para o painel lateral do protótipo (decisão [W] no #10850) · as abas (`ficha_custo`,
`ficha_materiais`, `ficha_artes`, `ficha_pessoas`) só são montadas quando a tela pede a aba · **Dado** `?os=` de outra
empresa, inexistente ou ausente **Então** `ficha` vem nula e o Quadro abre normalmente.

Status: 🧪

## UC-PCOSF-08 · O Quadro abre a Ficha no painel lateral · `must`

**Dado** o Quadro **Quando** clico no número de uma OS por item **Então** a Ficha abre num painel de 760px à direita, sem
sair do quadro (decisão [W] no #10850, protótipo `FichaOs` de `producao-cv-os.jsx`) · a URL ganha `?os={id}` e mantém o
filtro, para o F5 e o link copiado reabrirem o painel · fechar o painel tira o `?os=` · cada aba (Arte, Materiais,
Custo, e quem pode ser responsável no Liberar) é pedida ao Quadro só quando é aberta, e a aba de uma OS não aparece na
seguinte · depois de liberar, refazer, cancelar ou trocar a arte, o quadro se atualiza junto · **Dado** `?os=` de outra
empresa ou inexistente **Então** o painel diz "OS não encontrada". Teste: `tests/js/producao-quadro-painel-ficha.test.tsx`
(abas por OS, troca e controle) e `tests/js/producao-ficha-link.test.ts` (URL); o servidor é o UC-PCOSF-07; o clique
no navegador se confere no smoke.

Status: 🧪

## UC-PCOSF-09 · A Linha do tempo abre a Ficha no painel lateral · `must` `[T0]`

**Dado** a Linha do tempo **Quando** clico (sem arrastar) ou dou Enter no bloco de uma OS por item **Então** a Ficha abre
no mesmo painel de 760px do Quadro (UC-PCOSF-08), sem sair da linha · a URL ganha `?os={id}` e mantém o filtro e o dia ·
depois de liberar, refazer, cancelar ou trocar a arte, os blocos e os eventos se atualizam junto · a Linha entrega
`ficha` e as abas `ficha_*` como o Quadro, montadas no mesmo lugar (`PropsDaFicha::doPainel`) · **Dado** `?os=` de outra
empresa ou inexistente **Então** `ficha` vem nula e a Linha abre normal. Teste: `ProducaoFichaOsContratoTest`
(UC-PCOSF-09: servidor e Tier 0) e `tests/js/producao-ficha-link.test.ts` (URL na Linha); o clique no navegador se
confere no smoke.

Status: 🧪

## UC-PCOSF-10 · O endereço antigo da Ficha leva ao Quadro com ela aberta · `must` `[T0]`

**Dado** um link antigo `/producao/ordens/{id}` (favorito, mensagem, histórico) de uma OS por item da empresa **Quando**
abro **Então** vou para `/producao/quadro?os={id}`, com a Ficha aberta no painel lateral (decisão [W] no #10850) · os
portões continuam antes de redirecionar: **Dado** OS de outra empresa ou inexistente **Então** 404 · sem
`producao.access` ou sem o módulo no pacote **Então** 403. Teste: `ProducaoFichaOsContratoTest` (UC-PCOSF-02 · UC-PCOSF-10
e UC-PCV-10) e o teste de navegador `ProducaoTelasBrowserTest` (abre o endereço antigo e espera o painel).

Status: 🧪

## UC-PCESC-01 · A empresa que vem do registro não depende da sessão · `must`

**Dado** uma OS da empresa A e a sessão logada na empresa B **Quando** a rota pública, o gancho do núcleo ou o job consulta a
OS de A **Então** acha a OS de A. O escopo da sessão, sozinho, a esconderia: por isso a consulta dispensa o escopo e leva
`business_id` explícito, com `// SUPERADMIN:` dizendo de onde vem a empresa.

Status: 🧪

## UC-PCESC-02 · A leitura da tela nunca vê outra empresa · `must`

**Dado** a sessão logada na empresa A **Então** a leitura acha a OS de A · **Dado** a sessão em B **Quando** pede a OS de A,
com a empresa A ou B **Então** a OS não é encontrada.

Status: 🧪

## UC-PCESC-03 · Gravar segue na empresa recebida · `must`

**Dado** a sessão logada em B **Quando** o roteiro de A é programado **Então** a etapa nasce em A. O escopo global não atua
em INSERT.

Status: 🧪

## UC-PCESC-04 · Toda dispensa de escopo diz o porquê · `must`

**Dado** o código do Modules/Producao **Então** todo `withoutGlobalScope` ou `withoutGlobalScopes` traz `// SUPERADMIN:` na
mesma linha ou nas 3 anteriores.

Status: 🧪

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Refugo e Histórico da OS — fora do lote 3.

## Trilha do tempo
- 2026-11-17 · [M+C] `/producao/ordens/{id}` passa a levar ao Quadro com a Ficha aberta no painel (UC-PCOSF-10), com os portões de antes. Os testes da Ficha leem os dados pelo painel do Quadro.
- 2026-11-17 · [M+C] A página `OsShow` (`Show.tsx`) foi apagada: ninguém mais a renderiza. O conteúdo da Ficha é o `FichaDaOs.tsx`, no painel; este arquivo e o charter vieram de `Os/` para o lado dele.
- 2026-11-17 · [M+C] A Linha do tempo abre a Ficha no mesmo painel lateral do Quadro (UC-PCOSF-09). As props do painel ficam num lugar só, `PropsDaFicha::doPainel`.
- 2026-11-17 · [M+C] O número da OS no Quadro abre a Ficha no painel lateral de 760px, com `?os=` na URL (UC-PCOSF-08, decisão [W] no #10850). A Linha segue abrindo a página.
- 2026-11-16 · [M+C] O Quadro entrega a Ficha com `?os=` para o painel lateral (UC-PCOSF-07, decisão [W] no #10850).
- 2026-11-16 · [M+C] O conteúdo da Ficha vira o componente `_components/FichaDaOs.tsx`, para o painel lateral do Quadro usar o mesmo (decisão [W] no #10850). A página não muda.
- 2026-11-09 · [W+C] Dispensas de escopo revistas (lote 8.1): 21 removidas, 12 comentadas (UC-PCESC-01..04).
- 2026-11-06 · [M+C] Aba Arte lê a versão gravada (UC-PCART-09) em vez de contar os envios.
- 2026-11-06 · [W+C] Quadro e Linha abrem a Ficha (lote 3.5).
- 2026-11-06 · [W+C] Abas Materiais e Arte (lote 3.4).
- 2026-11-06 · [W+C] Aba Custo só leitura (lote 3.3).
- 2026-11-06 · [W+C] Ficha nasce na auditoria lote 3.2. Trio carimbado com `criar-tela.mjs` (PT-03) e movido para o caminho do módulo, que o gerador não cobre.
