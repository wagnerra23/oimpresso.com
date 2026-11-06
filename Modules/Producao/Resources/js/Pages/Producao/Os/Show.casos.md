---
id: modules-producao-pages-producao-os-show-casos
casos: Produção · Ficha da OS · /producao/ordens/{id}
irmaos: Show.charter.md (lei) · Show.tsx (tela)
tecnica: Caso de uso = narrativa do PCP + critério de aceite verificável (Dado/Quando/Então)
por_que: a OS por item só tinha rota JSON para Liberar, Refazer e Cancelar; sem tela, a OS de empresa sem sinal mínimo ficava parada em Aguardando liberação.
owner: wagner
last_run: "2026-11-06"
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
pedir nova aprovação" · pedido de arte de outra empresa não aparece.

Status: 🧪

## UC-PCOSF-06 · Quadro e Linha abrem a Ficha · `must`

**Dado** uma OS por item no Quadro e na Linha do tempo **Quando** clico no número do cartão do Quadro, ou clico (sem
arrastar) ou dou Enter no bloco da Linha **Então** abre `/producao/ordens/{id}` · arrastar o bloco para reprogramar não
abre a Ficha · a OS da Comunicação Visual (`comvis_os`) não tem Ficha e não ganha link. Teste:
`tests/js/producao-ficha-link.test.ts` (endereço e controle); o clique no navegador se confere no smoke.

Status: 🧪

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Refugo e Histórico da OS — fora do lote 3.

## Trilha do tempo
- 2026-11-06 · [W+C] Quadro e Linha abrem a Ficha (lote 3.5).
- 2026-11-06 · [W+C] Abas Materiais e Arte (lote 3.4).
- 2026-11-06 · [W+C] Aba Custo só leitura (lote 3.3).
- 2026-11-06 · [W+C] Ficha nasce na auditoria lote 3.2. Trio carimbado com `criar-tela.mjs` (PT-03) e movido para o caminho do módulo, que o gerador não cobre.
