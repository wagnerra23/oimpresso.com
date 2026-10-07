# Decisões do [W] que travam threads de playbook — 2026-10-07

> Lista única, só leitura. Nada aqui foi decidido nem aplicado: cada item traz a pergunta, as
> saídas com a consequência e uma recomendação. O estado de cada thread **é o placar**, não esta
> folha — onde divergirem, o placar vence.

## 0 · Como foi medido

- Base: `origin/main` @ `451a35dc9d` (2026-10-06 21:11 -03).
- Comando, um por playbook: `node scripts/qa/placar.mjs --indice prototipo-ui/cowork/Wagner/cowork-inbox/<mod>/playbook/00-INDICE.md`.
- `[bloqueada]` no placar = a thread tem o campo `bloqueio` no JSON do índice
  (`scripts/qa/placar-indice.mjs:248`). Isso **não** significa que espera o [W]: o campo também é
  usado para thread descartada, cancelada, vetada ou absorvida. Por isso li o `bloqueio`, a thread
  e o `_saida-NN.md` de cada uma.
- As 15 bloqueadas batem com a lista de 2026-10-06: compras 03/04/05 · crm 01 · fiscal 23 ·
  governance 05 · home 01 · hrm 05/10 · officeimpresso 03/A3/07 · patrimonio 05 · produto 07.

**Resultado:** das 15, **4 esperam decisão do [W]** (compras 05, fiscal 23, officeimpresso A3/07)
e **2 deixam resíduo de decisão** (governance 05, hrm 10). As outras 9 já foram respondidas,
fechadas ou absorvidas; seguem `bloqueada` porque o índice não foi reescrito no Cowork (§3).
Somam-se as 2 threads `[proximo]` de cutover/valor do `venda-menu` (C1, Q2).

---

## 1 · Cutover e valor — `venda-menu`

### 1.1 · C1 — ligar para biz=1: Descontos, Importação, Pedido de venda
- **O que trava:** o C0 está entregue (#8630, `VendasMwartCutoverTest` 33/33 na lane Sells,
  `_saida-C0.md`). Ligar é mexer no `.env` de **produção** (Hostinger):
  `MWART_VENDAS_DISCOUNT_INDEX=true` + `_BIZ=1`, idem `IMPORT_SALES` e `SALES_ORDER_INDEX`
  (`config/mwart.php:176-216`). Mudança de `.env` de produção escala ao [W] (skill `publication-policy`).
- **Pergunta:** *"Posso pôr as 3 chaves `MWART_VENDAS_*` com `_BIZ=1` no `.env` de produção e
  fazer o smoke de cada tela no biz=1?"*
- **Saídas:**
  - (a) **Liga as 3 juntas** → o GET comum do biz=1 passa a ver React nas 3; começa a janela de 48 h do C2.
  - (b) **Liga uma por vez** → raio menor, mas o C2 e o C3 (7 dias) andam 3× mais devagar.
  - (c) **Espera** → as Pages seguem acessíveis só com `X-Inertia`; menu e URL direta continuam no Blade.
- **Recomendação:** (a). São as telas de menor uso, a rota de fuga é desligar a chave, e o D4/D5
  do próprio playbook já fixaram lote + 7 dias por lote.

### 1.2 · Q2 — Cotações no cutover (`mwart.vendas_cotacoes`)
- **O que trava:** a chave não existe em `config/mwart.php` (o placar mede isso). A Q3 que a
  precedia foi mergeada (#8843, `QuotationConvertTest` 4/4). Duas decisões juntas:
  ligar a lista de cotações no GET comum, e — achado da Q3 — o botão "Converter em venda" nasce
  **escondido**, porque segue `config('constants.enable_convert_draft_to_invoice') = false`
  (`config/constants.php:85`, "Experimental beta feature"), igual ao Blade.
- **Pergunta:** *"Cotações entra no lote C1 (chave nova `vendas_cotacoes`, biz=1)? E o botão de
  converter cotação em venda fica escondido como no Blade, ou liga?"*
- **Saídas:**
  - (a) **Q2 no C1, botão escondido** → paridade exata com o Blade; nenhum fluxo de valor/estoque novo em produção.
  - (b) **Q2 no C1, botão ligado no biz=1** → função nova (gera venda `final`, baixa estoque, 409 na 2ª) entra junto do cutover; regra mestre de valor/estoque se aplica.
  - (c) **Q2 depois do C1** → um lote a mais, sem ganho de segurança.
- **Recomendação:** (a). Ligar o converter é decisão de produto separada, com antes→depois próprio;
  misturar com o cutover junta dois riscos num smoke só.

---

## 2 · Bloqueadas que esperam o [W]

### 2.1 · compras 05 — smoke/canary da grade tam×cor (US-COM-005)
- **O que trava:** a grade está no código (`Purchase/Create.tsx` importa `GradeMatrixInput`). A
  D-GRADE foi respondida por delegação ("sim: [W] aprova por screenshot"), mas o screenshot em si
  não aconteceu. É gate humano [W2], nenhum arquivo destrava.
- ⚠️ **Conflito no texto da thread:** ela declara o canário em **biz=4 (Larissa)**. A R6
  (`proibicoes.md`) proíbe biz=4 em teste/smoke; smoke manual é biz=1. Canário no biz=4 só faz
  sentido como F5 com aviso prévio ao cliente.
- **Pergunta:** *"Você roda o smoke da grade no biz=1 e aprova pelo screenshot, ou a grade espera
  o canário com a ROTA LIVRE avisada?"*
- **Saídas:**
  - (a) **Smoke biz=1 agora + screenshot [W]** → US-COM-005 fecha; o canário biz=4 vira etapa F5 separada.
  - (b) **Canário biz=4 com aviso** → valida no cliente real, mas exige o aviso e a janela de 7 dias.
  - (c) **Adiar** → `Create.charter.md` segue `aguarda smoke/canary Wagner`.
- **Recomendação:** (a), cobrindo os 6 invariantes da thread (1 POST, grade de 1 eixo, estoque só
  após `received`, `permitted_locations`, 403 sem `purchase.create`, 1280px dark).

### 2.2 · fiscal 23 — retenções federais na NFS-e
- **O que trava:** o `bloqueio` cita a consolidação NfeBrasil × NFSe, decisão [W] pendente em
  `memory/dominio/fiscal-faturamento.md:39-46`. Os dois módulos criaram a mesma tabela
  `nfse_emissoes` com vocabulários diferentes; o vivo é o do NfeBrasil. A thread escreve em
  `Modules/NFSe/Services/`, e o prefixo muda se a NFS-e for do NfeBrasil.
- Medido por cima: `Modules/NfeBrasil` tem `EmitirNFSeJob`, `CancelarNfseJob`, `NfseCancelService`,
  model `NfseEmissao`; `Modules/NFSe` tem `NfseEmissaoService` e o DTO `NfseEmissaoPayload`. Os
  dois estão ativos em `modules_statuses.json`.
- **Pergunta:** *"Qual módulo é o dono da NFS-e: NfeBrasil (deprecar o NFSe) ou NFSe?"*
- **Saídas:**
  - (a) **NfeBrasil dono** → o vocabulário vivo já é dele; abre plano de deprecação do NFSe; thread 23 vai pra `Modules/NfeBrasil/Services/`.
  - (b) **NFSe dono** → reverte o vocabulário vivo de `nfse_emissoes.status` (migration) e tira a NFS-e do NfeBrasil.
  - (c) **Não consolidar agora** → thread 23 entra em `Modules/NFSe` como está; risco de código num módulo que pode ser deprecado.
- **Recomendação:** (a), mas como decisão de direção só: o plano vem do agente `deprecar-modulo`
  antes de qualquer PR. Até lá, a 23 continua bloqueada.

### 2.3 · officeimpresso A3 e 07 — Logs: remedir e fechar o design-diff
- **O que trava (`_saida-A3`):** o lado prod não é medível. (1) a rota serve a Blade porque a flag
  `useV2OfficeimpressoLogs` está desligada para o usuário de medida no staging; (2) a Blade chega
  com CSS vazio; (3) staging sem máquinas; (4) **a tela não é a mesma**: a `Logs/Index.tsx` lista
  **máquinas**, a vista `oi-log` do protótipo lista **eventos** do `licenca_log`. A A4 deu vista
  própria à Timeline. A 07 depende da A3.
- **Pergunta:** *"Logs/Index é lista de máquinas (como hoje) ou log de eventos (como o protótipo)?
  E posso ligar `useV2OfficeimpressoLogs` no staging para medir?"*
- **Saídas:**
  - (a) **Eventos (protótipo vence, UI-0029) + flag no staging** → a tela muda de conteúdo; vira thread de build antes da medida.
  - (b) **Máquinas (produção vence) + flag no staging** → o Cowork redesenha a vista; a medida passa a comparar a mesma coisa.
  - (c) **Encerrar A3/07 como Non-Goal** → sem medida dos Logs; fica declarado no charter.
- **Recomendação:** ligar a flag no staging em qualquer caso (sem ela nada se mede). Sobre o
  conteúdo, (b): a decisão sobre o que é a tela é de produto, e hoje quem usa vê máquinas.
  Pela UI-0029 o protótipo manda na FORMA, não no dado que a tela lista.

---

## 3 · Resíduos de decisão em threads já entregues

### 3.1 · governance 05 — `Gate::before`
- A thread **foi entregue**: #7928 (ADR 0415 `aceito`, `GateBeforePlataformaTest`). O `bloqueio`
  ("decisão [W] em aberto") está velho. Restam dois itens que o `_saida-05` deixou ao [W]:
- **Pergunta:** *"(1) `jana.superadmin` deixa de ser concedível no editor de papéis da empresa?
  (2) `governance.*` vira permissão de plataforma (passo 3 da ADR 0392)?"*
- **Saídas:** (a) **(1) sim, (2) não** → fecha um Tier 0 (`MetasController::store` aceita `business_id` alheio) sem tirar o módulo vendável · (b) **ambos sim** → governance deixa de ser concedível pelo dono da empresa · (c) **nenhum** → `jana.superadmin` segue concedível.
- **Recomendação:** (a). Conferir antes se o item (1) já não saiu num PR posterior.

### 3.2 · hrm 10 — Folha com encargos
- A ADR-mãe foi ratificada (#7920). O bloqueio agora é de sequência, não de decisão: nenhuma Page
  antes dos passos 2-5 do §9 (recálculo server-side → casos-verdade → contrato do modelo de verba),
  e o insumo do Ponto ainda não tem dado em produção.
- **Pergunta:** *"Abro agora o passo 2 da folha (recálculo server-side), ou ela espera o Ponto ter dado em produção?"*
- **Saídas:** (a) **abrir já** → o recálculo nasce testado contra casos-verdade, sem hora real · (b) **esperar o Ponto** → nada anda na folha até lá · (c) **despriorizar** → a thread fica bloqueada sem prazo.
- **Recomendação:** (a). O passo 2 é motor + casos-verdade externos, não precisa da hora do Ponto
  para ser provado; a regra mestre de valor vale do primeiro PR.

---

## 4 · Bloqueadas que NÃO esperam o [W]

Estado `bloqueada` só porque o índice do Cowork não foi reescrito. Ação: o Cowork atualiza o
índice (o espelho não se edita). Nenhuma pergunta ao [W].

| thread | por quê não é do [W] | recibo |
|---|---|---|
| compras 03 · Fornecedores | D-FORN respondida 2026-09-24 (atalho para `/cliente?type=supplier`) e aplicada | `_saida-03.md` |
| compras 04 · Ghost `/compras/create` | D-GHOST respondida; ghost já não existia desde #1525; resíduo removido | `_saida-04.md` |
| crm 01 · contratos lote 1 | absorvida: contrato entra no PR da Page (02·03·04) | `_saida-01.md` |
| produto 07 · contratos 4 telas | absorvida: idem (02·04·05·06) | `_saida-07.md` |
| home 01 · PT-04→PT-05 | descartada por [W] 2026-09-23 | `_saida-01.md` |
| hrm 05 · Turnos | cancelada por D4 (escala é do Ponto) | — |
| officeimpresso 03 · dropar senha | vetada por [W] (D4 = "não") | `_saida-03.md` |
| patrimonio 05 · retenção automática | descartada (§5 `proibicoes.md` 2026-07-27) | `_saida-04.md` |
| governance 05 · `Gate::before` | entregue (#7928); resíduo em §3.1 | `_saida-05.md` |

---

## 5 · Fora do pedido, listadas para ver numa tela só

Decisões `respondida: false` em outros índices. Não li as threads delas; sem recomendação.

| módulo | id | trava | texto |
|---|---|---|---|
| comissoes | D-COM-1 | — | ADR 0151 segue proposta: playbook só no legado, sem `Modules/Comissao`? |
| comissoes | D-COM-2 | — | comissão sobre venda paga ou faturada |
| modulos | D1 | — | versão exibida: `system.<alias>_version` |
| modulos | D4 | 02 | install em fila só se houver worker em produção |
| modulos | D5 | — | remover chaves órfãs do `modules_statuses.json` |
| modulos-faltantes | VEST-D1 | — | ligar hard-block de `vestuario.etiqueta.*` |
| modulos-faltantes | VEST-D2 | — | prévia antes de imprimir: podar charter ou construir |
| modulos-faltantes | PERM | 03, 04 | permissão que abre Voz do Cliente e Catálogo QR |
