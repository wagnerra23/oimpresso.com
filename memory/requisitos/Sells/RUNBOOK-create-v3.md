---
slug: sells-runbook-create-v3
title: "Sells — Runbook da tela Venda V3 (preview de design, paralelo)"
type: runbook
module: Sells
owner: L
status: rascunho
last_validated: "2026-08-06"
date: 2026-08-06
---

<!--
  `status: rascunho` e não `ativo` de propósito: o §F4 (smoke em staging) NUNCA RODOU
  — o CT 100 está inalcançável desta máquina (sem Tailscale). `last_validated` é a data
  em que o §F1 foi conferido contra o código (flag `useV2SellsCreate`, SellPosController,
  deploy.yml), não a data de um smoke. Quando o §F4 rodar de verdade, sobe a data E o status.
-->



# RUNBOOK — Venda V3 (`/sells/create-v3`)

## Preparação do fechamento — 2026-10-30, onda 2

Pedido [W]: preparar a V3 para substituir Create/Edit; PDV separado (contrato atualizado em
`CreateV3.casos.md`). As seções históricas abaixo descreveram o preview em agosto; não são
autorização de cutover. Nesta frente não houve integração em `CreateV3.tsx`, merge, deploy ou flip.

Base conferida: `origin/main @ f23fe1a021`, depois de fetch; #10216/#10217/#10223/#10224/#10225
incorporados e #10226 aberto. O módulo independente `v3/fechamento-dominio.ts` preparou:
despesas (até quatro, após imposto), imposto do pedido por alíquota real ou desligado,
cotação de ST vinculada ao corpo da consulta, sinal nulo/zero/0–100, limite do adiantamento
e separação de parcelas recebidas/a receber. `fechamento-dominio.test.ts` entrou na lane existente.

### Entradas exigidas na integração sequencial

- **Cliente/preço (onda 1):** cliente e local reais, prazo, sinal do cliente, saldo de adiantamento
  (`advance_balance` na busca), impostos permitidos com ID/alíquota e padrão da empresa.
  `ConfigVenda.impostoDoPedido` já existe: passar sua negação como `impostoDesligado`.
- **Itens (onda 1):** valor líquido após descontos, sem imposto/frete/despesas/ST/arredondamento;
  quantidade faturada e preço líquido unitário por variação, iguais aos gravados. Não alimentar
  o fechamento com `totalParaGravar`, que já incluiu frete e arredondamento.
- **ST:** montar `ConsultaSt` no formato de `SellIcmsStController`, chamar `POST /sells/icms-st`
  e guardar a resposta com `receberCotacaoSt`. Mudança de cliente/local/itens/total/frete invalida
  a cotação. Bloquear Gravar enquanto pendente/erro. Não inferir ST por UF ou copiar 13,2% da cena:
  UC-VST-06 também cobriu contribuinte da mesma UF. Depois de `concluirComSt`, enviar ST e total
  juntos; a conferência canônica continuou em `IcmsStDaVenda::conferirEGravar`.
- **Arredondamento:** passar `config.arredondamento` a `concluirComSt`, só depois do ST.
  Preservar `round_off_amount`; validar o total/note/título pelo backend antes de integrar.
- **Pagamentos:** exigir `sell.payments` (frente cliente #10226). Adiantamento usa `method=advance`,
  conta nula, sem reduzir preço; conferir o total agregado de todos os adiantamentos contra o saldo
  e o que falta receber, não cada linha isoladamente. O servidor continua responsável pela recusa.
- **Parcelas:** `separarParcelas` preserva todos os campos e só soma RECEBIDA em `pago`.
  Não converter A RECEBER em `payment[]`: `TransactionUtil::createOrUpdatePaymentLines` registra
  pagamento efetivo. `TituloAutoService::sincronizarDeTransacao` sincronizou título agregado
  (`parcela_numero=null`); esta frente não criou endpoint de plano nem títulos paralelos.
  É dependência concreta definir a gravação atômica do plano no Financeiro, com idempotência,
  datas/contas reais e dupla prova. Não liberar gravação de plano sem esse caminho.
- **Edição (onda 1):** conservar IDs de pagamentos/parcelas já gravados e respeitar as travas de
  nota/pagamento. Este módulo não monta payload de update nem recria baixa existente.

TEF não foi implementado nesta preparação: `TefService/TefController` e flag própria já existiam;
driver de adquirente e integração visual tiveram pendências na US-SELL-075. Pagamento de cartão
manual não significa cobrança TEF. PDV/offline/caixa mantiveram a fronteira de `CreateV3.casos.md`.

### Impacto de referência e prova

| Cenário sintético | Antes | Depois |
|---|---:|---:|
| Despesa de 25 sobre líquido 930 + imposto 167,40 + frete 18 | 1.115,40 | 1.140,40 |
| ST retornado 132,00 sobre base 1.140,40 | 1.140,40 | 1.272,40 |
| ST 132,03 + arredondamento de 0,05 | 1.272,43 | 1.272,45 (+0,02) |
| Adiantamento 40 sobre venda 100 | Preço 100 / saldo a receber 100 | Preço 100 / saldo a receber 60 |
| Parcelamento 100 em 3; primeira recebida | 100 a distribuir | 33,34 recebido + 66,66 previsto |

Provas independentes: funções reais + contas fixas em centavos nos testes (sem derivar esperado
do código). Foram 55 testes verdes em quatro arquivos (8 novos); ESLint do domínio passou,
o spec foi ignorado pela configuração atual do lint. JUnit local em
`test-results/vitest-fechamento-junit.xml`. Sem Pest/PHPStan local e sem execução no CT100 nesta
frente de domínio TS. Nenhum registro ou estoque foi alterado; os números são sintéticos.
Faltou prova HTTP/NF-e/título e smoke real após a fiação. Nenhum UC da Page foi promovido a ✅.

> **Tipo:** runbook de tela NOVA e PARALELA — não é migração, não substitui nada.
> **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) (MWART) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) (multi-tenant) · [ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md) (staging CT 100)
> **Dono:** [L] Luiz — assumiu a tela de cadastro de venda em 2026-08-06.

## §F1 PLAN

### Por que esta tela existe

Restrição de negócio declarada por [L] em 2026-08-06, textual:

> *"Tela do Guilherme e da Larissa não pode ser alterada de forma alguma, se não eles quebram contrato e perdemos dinheiro."*

A tela viva de cadastro de venda é `Pages/Sells/Create.tsx`, servida por
[`SellPosController@create`](../../../app/Http/Controllers/SellPosController.php) na rota `/pos/create`.
Quem opera nela é a **ROTA LIVRE** (`business_id=4` — Larissa dona, Guilherme retaguarda),
que responde por 99% do volume de vendas do oimpresso novo.

**A feature flag não protege.** Medido em `origin/main` (2026-08-06):
`FeatureFlagService::$fallbackDefaults['useV2SellsCreate'] = true`, com a nota
*"Wagner: 'ative para todos pronto' → flip fallback default p/ TRUE"* (2026-05-27).
Ou seja, a V2 React está ligada por padrão para todos — mexer em `Create.tsx` e
deployar atinge o cliente **direto**, sem intermediário.

Daí o desenho: **arquivo novo + rota nova**, sem tocar em nada que a tela viva consome.

### Fronteira — o que este trabalho NÃO pode fazer

| ⛔ Proibido | Por quê |
|---|---|
| Editar `Pages/Sells/Create.tsx` | é a tela deles |
| Editar `SellPosController@create` | serve a tela deles; duplicar as ~200 linhas de props geraria drift |
| Editar componente compartilhado que `Create.tsx` importa | a alteração vaza pra tela deles pelo import |
| Calcular valor / total / desconto / estoque na V3 | território **[V0]** (REGRA MESTRE, `memory/proibicoes.md`) — foi assim que nasceu o incidente `num_uf` de 2026-06-05 (`final_total` inflado ~×100.000 em 16 vendas do biz=4) |
| Gravar qualquer coisa | não há `store()`, não há POST, não há rota de escrita |

Precisando de variação de um componente existente, **nasce cópia local** em
`Pages/Sells/_components/` — nunca edição do original.

### Escopo da v1

Tela **apresentacional**: os números chegam prontos do controller como dados de cena
(`SellsV3Controller::dadosDeCena()`), espelhando
`prototipo-ui/design-oimpresso/04-modulos/vendas/sells-data.js`.

Ligar props reais (as ~24 de `SellPosController@create`) é **decisão separada**, depois que o
desenho assentar — e aí volta a valer a REGRA MESTRE de valor/estoque.

## §F2 BACKEND BASELINE

Não se aplica: não há backend a preservar. O controller novo
([`SellsV3Controller`](../../../app/Http/Controllers/SellsV3Controller.php)) é `GET`-only,
gate de permissão idêntico ao da tela real (`superadmin` ou `sell.create`), sem escrita.

`business_id` vem de `session('user.business_id')` e é passado à view — a tela não consulta
tabela de negócio nenhuma, então não há query a escopar (ADR 0093 satisfeito por ausência).

## §F3 FRONTEND

- `Pages/Sells/CreateV3.tsx` — layout `AppShellV2`, componentes de `@/Components/ui/*`, ícones `lucide-react`.
- 3 passos numerados: **Cliente · Itens · Fechamento** (âncora de design abaixo).
- Faixa de aviso permanente no topo: quem abrir por engano sabe em 1 segundo que não é produção.
- Botão "Finalizar venda" **desabilitado** por construção.

**Âncora de design:** `prototipo-ui/cowork/Wagner/venda-v3.jsx` (seção `sells-create.jsx`) _(repontado 2026-10-07 por decisão [W] para a cópia do [W]: ela recebeu o conteúdo da do Felipe — busca de cliente sem acento e por dígito, CST, parcelas — e mantém a placa do total escura no tema escuro; a referência anterior era `prototipo-ui/cowork/Felipe/venda-v3.jsx`)_ _(repontado 2026-09-21 — o espelho do Felipe passou a ser o pacote "Produto Unificado V2", onde a Venda V3 veio num arquivo só: `venda-v3.jsx`, seção `sells-create.jsx`, exporta `window.VendaV3Create`. A âncora anterior `venda-v3/sells-create.jsx` segue no histórico em [bdf6d07e3](https://github.com/wagnerra23/oimpresso.com/blob/bdf6d07e3/prototipo-ui/cowork/Felipe/venda-v3/sells-create.jsx); dono: `resources/js/Pages/Sells/CreateV3.charter.md`)_ _(histórico: repontado 2026-09-15 — a âncora anterior `prototipo-ui/design-oimpresso/04-modulos/vendas/sells-create.jsx` nunca versionado no repo; dono: `resources/js/Pages/Sells/CreateV3.charter.md`)_
(cockpit "Venda — Guia de Produção", importado do projeto de design Oimpresso `019e2365` em 2026-08-06).
Roda local em `http://localhost:5570` via a config `cockpit-vendas` do `.claude/launch.json`.

## §F4 QA

Ambiente: **staging** (`https://staging.oimpresso.com`, CT 100), publicado por branch:

```bash
tailscale ssh root@ct100-mcp 'bash /opt/oimpresso-staging/code/docker/oimpresso-staging/deploy.sh claude/sells-create-v3'
```

Staging é máquina separada da produção (Hostinger), com banco próprio
(`oimpresso-staging-db`), APP_KEY própria e credenciais/certificados truncados —
não cobra, não emite NFe, não conecta WhatsApp.

⚠️ **Staging é compartilhado.** Deployar substitui a branch publicada; conferir com o time antes.

Smoke: abrir `/sells/create-v3` autenticado e conferir (a) faixa de preview visível,
(b) 3 passos renderizados, (c) botão de finalizar desabilitado, (d) zero erro no console.
Nunca usar `biz=4` em teste — [ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md).

## §F5 CUTOVER

**Não há cutover previsto.** Esta tela não substitui `/pos/create`.

Se um dia a V3 for candidata a virar a tela real, isso é decisão [W] e exige processo próprio:
paridade contra a Blade + todo CU `must` verde na lane + canary — e aí a fronteira acima deixa de valer,
porque o alvo passa a ser justamente o arquivo que hoje é intocável.

## Pendências declaradas

- **Trio:** charter e casos existem ([`CreateV3.charter.md`](../../../resources/js/Pages/Sells/CreateV3.charter.md) · [`CreateV3.casos.md`](../../../resources/js/Pages/Sells/CreateV3.casos.md)).
  Falta a 3ª perna: **nenhum teste**. Por isso o `casos.md` traz `[BACKLOG]` sem id e não `UC-*` —
  UC sem teste quebra o G-2 do `casos-gate` (required) e nasceria órfão.
- **§F4 nunca rodou.** O CT 100 está inalcançável desta máquina (Tailscale não instalado, verificado
  em PATH, serviço e 3 raízes de disco). Por isso o frontmatter é `status: rascunho`.
- **Charter incompleto por disciplina, não por esquecimento:** Non-Goals além dos declarados por [L],
  Automation Anti-hooks e Contrato visual ficam `_pendente [W]_` — a skill `charter-write` é proibida
  de inferir esses campos, e anti-padrão inventado no charter é pior que ausente porque parece canon.
- `php -l` local não valida este módulo: a máquina tem **PHP 7.4** e o projeto exige **^8.3**.
  Sintaxe PHP 8 (`?->`) reprova localmente mesmo em arquivo íntegro do `main`. Validação real é CI/CT 100.
