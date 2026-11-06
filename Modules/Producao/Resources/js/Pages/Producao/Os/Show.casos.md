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

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Abas Custo (só leitura), Materiais e Arte — lotes 3.3 e 3.4.
- **[BACKLOG]** Cartão do Quadro e bloco da Linha abrem a Ficha — lote 3.5.
- **[BACKLOG]** Refugo e Histórico da OS — fora do lote 3.

## Trilha do tempo
- 2026-11-06 · [W+C] Ficha nasce na auditoria lote 3.2. Trio carimbado com `criar-tela.mjs` (PT-03) e movido para o caminho do módulo, que o gerador não cobre.
