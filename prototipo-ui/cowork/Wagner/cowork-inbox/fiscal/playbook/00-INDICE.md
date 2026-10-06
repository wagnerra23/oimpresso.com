---
sessao: "00"
titulo: SINCRONIZAR Fiscal — índice do playbook (fonte da máquina em §5)
autor: "[CC]"
criado: 2026-09-08
revisado: 2026-10-06 rev.11 — +23..26 (retenções, Fator R, benefício, filial); bateria do protótipo com 27 notas — +21/+22 configurar pelo certificado (reuso ADR 0186 + templates L1). rev.8 — revisão de lacunas: +16 casos (FCP, operação, CFOP ?, tenant nas tabelas novas, Jana fora do ar, pergunta à Jana, conserto cadastral, CST PIS/COFINS, idempotência, pronto pra emitir) + thread 20 (devolução). rev.7 — cada thread traz os UC/R-NFE que implementa, em formato do main (Dado/Quando/Então + teste). rev.6 — leitura integral: SPECs NfeBrasil/Fiscal/NFSe, casos das 4 telas, NfeService inteiro, proibicoes (filtrado); +18 +19; US citada em cada thread. rev.4 — [W] aceitou D-MOTOR · D-OPERACAO · D-SIM · D-UF · D-IA · D-ENTRADA · D-SUPORTE (_DECISOES-W-2026-10-06.md); threads 05–16 abertas. rev.3 — Tributação (UC-TRB-01..29): thread 04 executável + 7 bloqueadas por decisão [W] (§2 · §4). rev.2 2026-09-08 após a thread 03
base: wagnerra23/oimpresso.com@main (árvores 11eff17f13db · 99530522729d, lidas 2026-09-08 11:09 e 11:19 UTC)
destino_no_main: prototipo-ui/cowork/Wagner/cowork-inbox/fiscal/playbook/
constituicao: CONSTITUICAO-COWORK.md (C1–C12) + memory/proibicoes.md — citadas, não copiadas
regra: PEDIDO, não inventário. Estado é derivado (§2-bis). Nunca em prototipo-ui/cowork/Wagner/ (guard R1).
---

# SINCRONIZAR Fiscal — playbook

> **Absorve e CORRIGE** `COLAR-NO-CODE-fiscal-notafiscal-ondas.md` (03/09, 10 ondas · 36,8 KB). Medido em 08/09: **9 das 10 ondas estão feitas em produção** e **as 5 decisões ⛔ [W] foram respondidas pelo código**. Sobra **1 onda de UI** (paginação) e **1 lacuna de rede** (E2E = 0). *(Errata 2026-09-23: a paginação já estava feita pelo #6711 em 04/09 — eram **10 das 10**; ver §0 e `_saida-02.md`. A lacuna de rede fechou pela thread 01.)*
> **Leis do módulo:** as 6 do `§0` do arquivo-ponte continuam valendo (motor fiscal não se toca · ledger append-only · lei citada literal · `501` nunca é sucesso · 🔴 sozinho no PR). Não recopiadas.

## 0 · O que a medição derrubou

| onda de 03/09 | estado | prova |
|---|---|---|
| 1 Alertas fiscais | **feita** | `AlertasFiscais.tsx` + `Cockpit.tsx:23` |
| 2 Linha por teclado | **feita nas 2 telas** | `Cockpit.tsx:605` · `Nfe.tsx:231`/`:316` · `UC-FCKP-11` · PR #6707 |
| 3 Paginação `.fx-pager` | **feita** (PR #6711, 04/09) | `Cockpit.tsx:721` `.fx-pager` `data-contract="paginacao-notas"` · `UC-FCKP-09` · lane `fiscal-cockpit-paginacao-gate` → recibo `_saida-02.md` |
| 4 Sparklines ⛔[W]2 | **feita** | `RibbonSpark.tsx` + `Cockpit.tsx:29` |
| 5 Tipo + densidade ⛔[W]1 | **feita nas 3 telas** | `DensidadeToggle` em `Cockpit:24` · `Nfe:25` · `Nfse:20` |
| 6 DF-e lote ⛔[W]3 | **feita** | `Dfe.tsx:381` `data-contract="lote-dfe"` · modal `:607` · *"definitiva por nota"* `:659` · UC 07..10 verdes |
| 7 CSV de eventos ⛔[W]3 | **feita e contratada** | `Eventos.charter.md` §Contrato do export CSV · `GET /fiscal/eventos/export` · `UC-FEVT-05/06/07` |
| 8 Config abas + gate ⛔[W]4 | **feita** | `Config.tsx:26` (4 abas) · `UC-FCFG-06`/`07` · `GatesPermissaoFiscalTest` · `TrocaAmbienteCerimoniaTest` |
| 9 SPED competência | **feita** | `Sped.tsx:318-335` régua com `motivoBloqueio` + `ItensDaRegua` |
| 10 Procedência ⛔[W]5 | **feita, 9 chaves** | `Cockpit.tsx:31` **named import** · renderizado em `:368 :380 :393 :425(×2) :465 :473 :500 :501` |
| §7c "0 contrato `fiscal-*`" | **7 existem** | `contrato/fiscal-{cockpit,config,dfe,eventos,nfe,nfse,sped}.contract.json` |
| §8.2 rede | **E2E = 0** | 17 specs em `e2e/`, nenhum fiscal → thread 01 |

**Emitir as 10 ondas de novo seria pedir 9 PRs por trabalho já mergeado.** Detalhe e provas: `_saida-03.md`.

> **Errata 2026-09-23 — a onda 3 também estava feita.** Em 08/09 esta linha dizia *"DE PÉ · `Pagination` = 0 hit"*. A busca procurou a palavra errada: a paginação entrou pelo #6711 em **04/09**, com nomes PT-BR (`pagina`/`porPagina`), e nunca usou `Pagination` — que, aliás, não existe como componente do DS. Logo foram **10 das 10** ondas, não 9. Medição e gate rodado: `_saida-02.md`.

## 1 · LEVANTAR — 4 denominadores
**D1 rota:** `Modules/Fiscal/Routes/web.php` — 7 telas (cockpit · NF-e/NFC-e · NFS-e · DF-e · Eventos · Config · SPED).
**D2 nav legado:** emissão segue em `NfeBrasil/Transactions` — fila 🔴, fora deste pacote.
**D3 protótipo:** `.fx-page` com **10 filhos** nesta ordem — `.fx-h · .ds-tabbar · .fx-ribbon · .fx-alerts · .fx-writeoff · .fx-toolbar · .fx-chips · .fx-table.fx-d-comfort · .fx-pager · .fx-toasts`; T1 **1099/1099/1099**, dark.
**D4 runtime:** as 7 rotas já têm Page React com trio completo (7 `.tsx` de 8,5–42 KB + 7 charter + 7 casos), **16 componentes** e **11 `_lib`**. Nenhuma rota espera tela nova.

## 2 · Threads

| # | thread | dono | prefixo | vaga | arquivo |
|---|---|---|---|---|---|
| 01 | Rede: 2 specs E2E (cockpit + NF-e), derivados dos contratos existentes | [CL] | `e2e/fiscal-cockpit.spec.ts` · `e2e/fiscal-nfe.spec.ts` | 1 | `01-rede-e2e.md` |
| 02 | Paginação `.fx-pager` — **já feita pelo #6711** (04/09), recibo retroativo `_saida-02.md` | [CL] | `Pages/Fiscal/Cockpit.tsx` (+ `CockpitController` **se** o corte for server-side) | 1 | `02-paginacao.md` |
| ~~03~~ | ~~Aferição~~ | [CC] | — | — | **FEITA** → `_saida-03.md` |
| ~~04 05 06~~ (rev.1) | ~~DF-e lote · Config · Procedência~~ | — | — | — | **mortas**: respondidas pelo código (§0) — os números foram reusados na rev.3 |
| 04 | Validação IBS/CBS no `UpsertRegraTributariaRequest` (o motor lê, o form não grava) | [CL] | `Modules/NfeBrasil/Http/Requests/UpsertRegraTributariaRequest.php` + teste novo + `RegraForm.casos.md` | 1 | `04-validacao-ibs-cbs.md` |
| 05 | Campos IBS/CBS no `RegraForm.tsx` | [CL] | `RegraForm.tsx` + casos | 2 | `05-regraform-ibs-cbs.md` (depende 04 · 12) |
| 06 | Motor: MVA → ICMS-ST, FCP, DIFAL 🔴 | [CL] | `MotorTributarioService.php` + teste | 1 | `06-motor-st-fcp-difal.md` |
| 07 | Operação + vigência 🔴 | [CL] | migration + models + motor | 2 | `07-operacao-vigencia.md` (depende 06) |
| 08 | Simulador read-only | [CL] | controller + `Index.tsx` | 3 | `08-simulador.md` (depende 07 · 12) |
| 09 | Tabela ICMS/FCP por UF curada | [CL] | migration + seeder | 3 | `09-icms-uf.md` (depende 07) |
| 10 | Sugestões da IA | [CL] | service + tabela + controller | 3 | `10-ia-sugestoes.md` (depende 07) |
| 11 | ADR: entrada por XML | [CL] | `memory/decisions/proposals/` | 1 | `11-adr-entrada-xml.md` |
| 12 | ALVO `nfe-brasil--tributacao` + promover âncora nos charters | [CL] | `governance/design/targets/` + 2 charters | 1 | `12-alvo-tributacao.md` |
| 13 | Aferição: de onde vêm os chamados | [CL] | só `_saida-13.md` | 1 | `13-afericao-chamados.md` |
| 14 | Saúde fiscal | [CL] | service + `Index.tsx` | 4 | `14-saude-fiscal.md` (depende 07 · 12 · 15) |
| 15 | Aceite do contador | [CL] | migration + permissão | 3 | `15-aceite-contador.md` (depende 07) |
| 18 | Apagar regra exige permissão (UC-NFRF-04 · falha esperada no main) 🔴 | [CL] | `TributacaoController@destroy` | 1 | `18-destroy-regra-sem-permissao.md` |
| 19 | Import CSV grava no tenant do preview (UC-NFIM-04 · falha esperada no main) 🔴 | [CL] | `ImportRegrasController` + service | 1 | `19-import-csv-tenant.md` |
| 21 | Configurar pelo certificado — backend (leitura + sugestão + aplicar exige NCM) | [CL] | lookup + `TributacaoTemplateService` | 1 | `21-certificado-template-backend.md` |
| 22 | Configurar pelo certificado — tela | [CL] | `Index.tsx` + e2e | 4 | `22-certificado-template-tela.md` (depende 21 · 12) |
| 23 | Retenções federais na NFS-e | [CL] | `Modules/NFSe` | — | `23-retencoes-nfse.md` (espera a consolidação NFSe × NfeBrasil) |
| 24 | Fator R automático | [CL] | service novo | 1 | `24-fator-r.md` |
| 25 | Benefício fiscal (redução + cBenef) 🔴 | [CL] | migration + motor | 3 | `25-beneficio-cbenef.md` (depende 07 · 17) |
| 26 | Filial em outro estado 🔴 | [CL] | `NfeService` + motor | 3 | `26-filial-origem.md` (depende 17 · 07) |
| 27 | CNPJ alfanumérico | [CL] | validadores + XML | 1 | `27-cnpj-alfanumerico.md` |
| 28 | Fechamento do mês pro contador | [CL] | service novo | 3 | `28-fechamento-contador.md` |
| 29 | ICMS × ISS por produto · DIFAL Simples por UF | [CL] | migration + motor | 3 | `29-icms-iss-e-difal-por-uf.md` |
| 20 | Devolução copia a nota de origem 🔴 | [CL] | serviço de devolução + teste | 3 | `20-devolucao.md` (depende 17 · 07) |
| 17 | **Nota com os itens reais (fase 2B)** 🔴 — pré-requisito de 06 · 07 · 14 · 16 | [CL] | `NfeService.php` + teste | 1 | `17-emissao-por-item.md` |
| 16 | Bloqueio com saída + conserto cadastral | [CL] | ponto do bloqueio + `sefaz-actions.ts` | 5 | `16-bloqueio-com-saida.md` (depende 13 · 14) |

**Vaga 1:** 01 ∥ 02 (prefixos disjuntos), ambas entregues. **rev.11 — vaga 1 (prefixos disjuntos):** 18 ∥ 19 ∥ 17 ∥ 11 ∥ 13 ∥ 21 ∥ 12 ∥ 24 ∥ 27, e **depois** 04 (04 e 18 tocam o mesmo controller/request de tributação, então vão em sequência; 18 e 19 primeiro, porque são T0 já catalogados). A 06 saiu da vaga 1: sem a 17 o motor só calcula o item genérico. A 12 espera D-ANCORA. Depois: vaga 2 (05 · 07) → vaga 3 (08 · 09 · 10 · 15) → vaga 4 (14) → vaga 5 (16). Alvo de layout de tudo isso: `fiscal-tributacao.jsx` (rota `fiscal-tributacao` do protótipo) · casos `fiscal-tributacao.casos.md` em `cowork/Wagner/`.

## 2-bis · ESTADO — derivado, nunca escrito
`node scripts/qa/placar.mjs --indice prototipo-ui/cowork/Wagner/cowork-inbox/fiscal/playbook/00-INDICE.md --proximo`
Render medido em 2026-09-23: `Fiscal: entregue 3 de 3 · próximo 0 · em curso 0 · pendente 0 · bloqueada 0` — **nenhum executável**. *(Em 08/09 esta linha esperava `entregue 1 de 3 · próximo 2`, e o comando apontava `placar-indice.mjs` direto — ele é a lógica, não a entrada, e rodado sozinho não imprime nada.)*

## 3 · Abertura de thread (colar como 1ª mensagem — sessão limpa)
```
Sessão fresca. ANTES de abrir: gh pr list --state open e cruze com os arquivos do seu prefixo.
Leia nesta ordem, do main:
1. prototipo-ui/cowork/Wagner/cowork-inbox/ponte/03-REGRAS-DE-PARALELISMO.md   ← Leis 1–4
2. memory/reference/prototipo-ui/CONSTITUICAO-COWORK.md                                       ← C1–C12
3. .../fiscal/playbook/00-INDICE.md  §0 · §1 · §2                            ← o que JÁ está feito
4. .../fiscal/playbook/NN-<sua-thread>.md                                    ← escopo · recorte · prova
5. .../fiscal/playbook/_saida-03.md                                          ← a medição que matou 9 ondas
6. o §0 (leis do módulo) de prototipo-ui/cowork/Wagner/COLAR-NO-CODE-fiscal-notafiscal-ondas.md
7. os RECORTES nomeados na sua thread (arquivo :: símbolo :: faixa) — e SÓ eles
AVISO: os blocos 1 e 1-bis do arquivo-ponte estão VENCIDOS. Não abra PR por onda de lá.
Você escreve SOMENTE no seu prefixo e no seu _saida-NN.md.
Terminou: escreva _saida-NN.md e pare.
```

## 4 · RESÍDUO Fiscal — fila [W]

**Respondidas pelo código — confirmar ou contestar, não decidir de novo:**
- ~~#1 tipo+densidade~~ · ~~#2 sparklines~~ · ~~#3 DF-e lote + CSV~~ (lote é **uma requisição por nota**, de propósito; CSV é **servidor**) · ~~#4 Config abas/gate~~ (falta só a **ADR** dos 2 pontos já decididos — papel, não tela) · ~~#5 procedência~~ (**9 chaves** no cockpit).
- A pergunta que sobra de #5 é de escopo: **as 9 superfícies em produção são as que você queria, e falta alguma fora do cockpit?**
- A pergunta que sobra de #2: se a intenção era **não** ter sparkline, o que está em produção é a divergência — e isso é [W], não Code.

**🔴 grave, achado na aferição (não é onda de UI):** `Dfe.casos.md:216` registra que `ManifestacaoService::buildConfig()` e `DistribuicaoDfeService::buildConfig()` fazem `select(['name','tax_number_1','state'])` em `business` e **a coluna `state` não existe** (133 colunas no schema canônico, no staging CT 100 e na Hostinger; nenhuma das 43 migrations a cria). Se ainda vale, **nenhuma manifestação chega à SEFAZ por caminho nenhum**. Precisa de dono.

**Stale a reconciliar (1 linha):** `Dfe.charter.md:39` ainda diz `❌ Bulk manifestar — backlog`, refutado pelos UC 07..10 verdes — precedência *teste verde > casos > charter > SPEC*.

**Fila 🔴 preservada:** emissão pelo cockpit × UI legada `NfeBrasil/Transactions` · IBS/CBS `US-FISCAL-021` · lane com migrations do NfeBrasil (destrava o único 🔴 Tier 0, `UC-FNFE-01`) · NFS-e emissão · contingência SEFAZ (*stale em 2026-10-06:* `NfeService::resolverTpEmis` existe e a contingência é opt-in por tenant — US-NFE-006 / ADR TECH-0002; reconfirmar antes de tratar como lacuna) · telemetria/Jana.
**Capacidade que não existe:** emissão NF-e/NFC-e (motor vivo, falta superfície + gate) · emissão NFS-e (`Modules/NFSe` testado, sem tela) · contingência · cálculo IBS/CBS · SPED PIS/COFINS · importação de XML de entrada (contador de entrada é literal `0`).
**Rastreabilidade:** 8 CU sem UC (`CU-FISC-02·03·08·09·10·11·15·16` — o 16 pode fechar com a evidência do `_saida-03`) · `US-FISCAL-022` `todo` com `CertHealthCheckCommand` + teste vivos.

**rev.3 · Tributação — decisões [W]: RESPONDIDAS em 2026-10-06, todas "sim" com condição → `_DECISOES-W-2026-10-06.md`. Texto original das perguntas preservado abaixo:**
- **D-MOTOR** — a lei 2 do módulo diz *"Nenhuma onda escreve motor fiscal"*. Calcular ST/FCP/DIFAL (06) e operação/vigência (07) **é** escrever no `MotorTributarioService`. Autoriza uma lane 🔴 própria (sozinha no PR, `MotorTributarioServiceTest` estendido no mesmo PR)? Ou a lei vale, e essas capacidades viram ADR antes?
- **D-OPERACAO** — a configuração passa a começar pela **natureza de operação** (padrão Bling · Tiny/Olist · Conta Azul), com uma regra geral por operação no lugar do `tributacao_default` único e exceções produto · NCM · NCM+UF dentro dela, cada uma com `válida de/até`. Os templates L1 (US-NFE-TPL-001) passam a gerar as operações. Sim/não?
- **D-SIM** — `Index.charter.md` tem o Non-Goal *"Calculadora interativa de tributo"*. O protótipo propõe um simulador **read-only que chama o próprio `MotorTributarioService::calcular`** (não recalcula no front) e mostra o nível N1–N4 usado. Revoga o Non-Goal só pra isso?
- **D-UF** — o Non-Goal *"Sincronização automática com tabela TBT"* continua. A proposta é uma tabela ICMS/FCP por UF **curada** (sem sync), com vigência e revisão do contador. Sim/não?
- **D-IA** — `RegraForm.charter.md` diz *"Não calcula automaticamente alíquotas a partir do NCM"*. A proposta é a IA **sugerir** (NCM pela descrição, serviço × mercadoria, IBS/CBS faltando), nunca aplicar; aceitar exige `nfe.tributacao.manage` e grava em `activity('nfe.tributacao')`. Qual motor (Jana?), e o Non-Goal cai só pra sugestão?
- **D-ENTRADA** — importar XML de entrada não existe (o contador de entrada é literal `0`). Vira projeto com ADR, ou thread no `NfeBrasil`?
- **rev.6 · NFS-e fica fora deste playbook, com motivo:** `nfse_emissoes` tem 2 vocabulários (`memory/dominio/fiscal-faturamento.md`), a consolidação NFSe × NfeBrasil é decisão [W] pendente, e a configuração já tem dono (US-NFSE-007, *"[ ] Mapeamento item venda → código serviço LC 116 (config no produto)"*, e `nfse_provider_configs` com `cnae`/`lc116_codigo_default`/`aliquota_iss` por empresa). A aba Serviços do protótipo é alvo dessa US.
**Achado lateral (não é thread):** `Index.charter.md` registra que `aplicarTemplate` é a mutação mais destrutiva e a única **sem** `activity()` (US-NFE-062). Continua em aberto.

## 5 · Fonte da máquina (schema em `_schema/playbook.schema.json`)
```json
{
  "modulo": "Fiscal",
  "sha": "99530522729d",
  "gerado": "2026-09-08",
  "absorve": [
    "prototipo-ui/cowork/Wagner/COLAR-NO-CODE-fiscal-notafiscal-ondas.md"
  ],
  "decisoes": [
    {
      "id": "D-TIPO",
      "pergunta": "Select de tipo + densidade em NF-e/NFS-e",
      "respondida": true,
      "resposta": "codigo: DensidadeToggle em Cockpit.tsx:24, Nfe.tsx:25, Nfse.tsx:20"
    },
    {
      "id": "D-SPARK",
      "pergunta": "As 3 sparklines do ribbon entram?",
      "respondida": true,
      "resposta": "codigo: RibbonSpark em Cockpit.tsx:29"
    },
    {
      "id": "D-DFE",
      "pergunta": "DF-e lote e CSV de eventos",
      "respondida": true,
      "resposta": "codigo: Dfe.tsx:381 data-contract=lote-dfe (uma requisicao POR NOTA) + GET /fiscal/eventos/export no servidor (Eventos.charter.md)"
    },
    {
      "id": "D-CONFIG",
      "pergunta": "Config: abas, gate de ambiente e Envio de documentos",
      "respondida": true,
      "resposta": "codigo: ConfigTab 4 abas (Config.tsx:26), gate UC-FCFG-06, cerimonia UC-FCFG-07, prop envioDocumentos em :140. Resta a ADR dos 2 pontos — papel"
    },
    {
      "id": "D-PROC",
      "pergunta": "CU-FISC-16 procedencia nas superficies",
      "respondida": true,
      "resposta": "codigo: named import em Cockpit.tsx:31, 9 chaves renderizadas (:368 :380 :393 :425x2 :465 :473 :500 :501)"
    },
    {
      "id": "D-MOTOR",
      "pergunta": "Lei 2 do modulo proibe onda que escreve motor fiscal. Autoriza lane 21 propria (sozinha, com MotorTributarioServiceTest) para ST/FCP/DIFAL e operacao/vigencia?",
      "respondida": true,
      "resposta": "[W] 2026-10-06 (_DECISOES-W-2026-10-06.md): sim — lane 21 propria, sozinha, com MotorTributarioServiceTest (emenda a lei 2)"
    },
    {
      "id": "D-OPERACAO",
      "pergunta": "Natureza de operacao vira porta de entrada: regra geral por operacao substitui tributacao_default unico; excecoes com valida de/ate; templates L1 geram as operacoes?",
      "respondida": true,
      "resposta": "[W] 2026-10-06 (_DECISOES-W-2026-10-06.md): sim — regra geral por operacao + excecoes com vigencia; default vira regra geral da Venda (append-only)"
    },
    {
      "id": "D-SIM",
      "pergunta": "Revoga o Non-Goal 'Calculadora interativa de tributo' (Index.charter.md) para um simulador read-only que chama MotorTributarioService::calcular?",
      "respondida": true,
      "resposta": "[W] 2026-10-06 (_DECISOES-W-2026-10-06.md): sim — so read-only chamando calcular, com aviso de previa"
    },
    {
      "id": "D-UF",
      "pergunta": "Tabela ICMS/FCP por UF curada (sem sync TBT), com vigencia e revisao do contador?",
      "respondida": true,
      "resposta": "[W] 2026-10-06 (_DECISOES-W-2026-10-06.md): sim — curada, sem sync TBT, numero so com lei citada"
    },
    {
      "id": "D-IA",
      "pergunta": "IA sugere (nunca aplica) NCM, natureza e IBS/CBS; aceitar exige nfe.tributacao.manage + activity log. Qual motor, e o Non-Goal do RegraForm cai so para sugestao?",
      "respondida": true,
      "resposta": "[W] 2026-10-06: sim; sugere nunca aplica; motor Jana CONFIRMADO (SugestaoFiscalAgent HasStructuredOutput + Tool DeclaraPermissao)"
    },
    {
      "id": "D-ENTRADA",
      "pergunta": "Importacao de XML de entrada (contador literal 0): projeto com ADR ou thread no NfeBrasil?",
      "respondida": true,
      "resposta": "[W] 2026-10-06 (_DECISOES-W-2026-10-06.md): ADR primeiro (thread 11)"
    },
    {
      "id": "D-SUPORTE",
      "pergunta": "Reduzir suporte: medir -> saude fiscal -> bloqueio com saida -> conserto cadastral -> aceite do contador; IA por ultimo?",
      "respondida": true,
      "resposta": "[W] 2026-10-06: sim; medicao (13) antes de qualquer automacao; NF-e sem NCM nao e saida"
    },
    {
      "id": "D-ANCORA",
      "pergunta": "Alvo de forma da Tributacao: fiscal-tributacao.jsx (Cowork) ou prototipos/nfe-tributacao (designer-agente, PR #7145)? Promover protótipo sobre 4 telas vivas e soberania [W] (UI-0029).",
      "respondida": true,
      "resposta": "[W] 2026-10-06: alvo = prototipo do Cowork (fiscal-tributacao.jsx); PR #7145 vira comparacao"
    },
    {
      "id": "D-CONTADOR",
      "pergunta": "Acesso do contador: link de revisao por e-mail (padrao, assinado 14d + codigo) + usuario Contador opcional + planilha via Import CSV; aceite nao bloqueia emissao?",
      "respondida": true,
      "resposta": "[W] 2026-10-06: sim — link assinado 14d + codigo (padrao), usuario Contador opcional, planilha via Import CSV; aceite nao bloqueia emissao; admin nao aceita pelo contador"
    },
    {
      "id": "D-NATUREZA",
      "respondida": true,
      "resposta": "[W] 2026-10-06: ok, como proposto"
    },
    {
      "id": "D-DIFAL-UF",
      "respondida": true,
      "resposta": "[W] 2026-10-06: ok, como proposto"
    },
    {
      "id": "D-FECHAMENTO",
      "respondida": true,
      "resposta": "[W] 2026-10-06: ok, como proposto"
    },
    {
      "id": "D-REJEITADA",
      "respondida": true,
      "resposta": "[W] 2026-10-06: ok, como proposto"
    }
  ],
  "threads": [
    {
      "id": "01",
      "titulo": "Rede: 2 specs E2E (cockpit + NF-e)",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "01-rede-e2e.md",
      "prefixo": [
        "e2e/fiscal-cockpit.spec.ts",
        "e2e/fiscal-nfe.spec.ts"
      ],
      "nao_toca": [
        "resources/js/Pages/Fiscal/",
        "Modules/Fiscal/",
        "governance/design/contracts/fiscal-cockpit.contract.json",
        "governance/design/contracts/fiscal-nfe.contract.json"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "e2e/fiscal-cockpit.spec.ts"
        },
        {
          "tipo": "arquivo",
          "path": "e2e/fiscal-nfe.spec.ts"
        },
        {
          "tipo": "arquivo",
          "path": "governance/design/contracts/fiscal-cockpit.contract.json",
          "guarda": true
        },
        {
          "tipo": "contem",
          "path": "resources/js/Pages/Fiscal/Cockpit.tsx",
          "padrao": "onKeyDown",
          "guarda": true,
          "nota": "UC-FCKP-11 nao pode sumir num PR de rede"
        }
      ]
    },
    {
      "id": "02",
      "titulo": "Paginacao .fx-pager no Cockpit",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "02-paginacao.md",
      "prefixo": [
        "resources/js/Pages/Fiscal/Cockpit.tsx",
        "Modules/Fiscal/Http/Controllers/CockpitController.php"
      ],
      "nao_toca": [
        "resources/js/Pages/Fiscal/_components/",
        "resources/js/Pages/Fiscal/_lib/",
        "resources/js/Pages/Fiscal/Nfe.tsx"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "resources/js/Pages/Fiscal/Cockpit.tsx",
          "padrao": "fx-pager",
          "nota": "era \"Pagination\" — falso-negativo: a tela pagina com nomes PT-BR (pagina/porPagina); trocado 2026-09-23"
        },
        {
          "tipo": "contem",
          "path": "resources/js/Pages/Fiscal/Cockpit.tsx",
          "padrao": "onKeyDown",
          "guarda": true
        },
        {
          "tipo": "arquivo",
          "path": "resources/js/Pages/Fiscal/Cockpit.casos.md",
          "guarda": true,
          "nota": "43.511 B — ESTENDER, nunca recriar"
        }
      ]
    },
    {
      "id": "03",
      "titulo": "Afericao read-only (FEITA)",
      "dono": "CC",
      "vaga": 1,
      "arquivo": "03-afericao.md",
      "prefixo": [],
      "nao_toca": [
        "resources/js/Pages/Fiscal/",
        "Modules/Fiscal/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "prototipo-ui/cowork/Wagner/cowork-inbox/fiscal/playbook/_saida-03.md"
        }
      ],
      "nota_provas": "matou as threads 04/05/06 da rev.1 e fechou as 5 decisoes [W] com caminho e linha"
    },
    {
      "id": "04",
      "titulo": "Validacao IBS/CBS no UpsertRegraTributariaRequest",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "04-validacao-ibs-cbs.md",
      "prefixo": [
        "Modules/NfeBrasil/Http/Requests/UpsertRegraTributariaRequest.php",
        "Modules/NfeBrasil/Tests/Feature/RegraTributariaIbsCbsValidacaoTest.php",
        "resources/js/Pages/NfeBrasil/Tributacao/RegraForm.casos.md"
      ],
      "nao_toca": [
        "Modules/NfeBrasil/Services/",
        "resources/js/Pages/NfeBrasil/Tributacao/RegraForm.tsx",
        "Modules/NfeBrasil/Database/Migrations/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "Modules/NfeBrasil/Http/Requests/UpsertRegraTributariaRequest.php",
          "padrao": "c_class_trib"
        },
        {
          "tipo": "contem",
          "path": "Modules/NfeBrasil/Http/Requests/UpsertRegraTributariaRequest.php",
          "padrao": "aliquota_cbs"
        },
        {
          "tipo": "arquivo",
          "path": "Modules/NfeBrasil/Tests/Feature/RegraTributariaIbsCbsValidacaoTest.php"
        },
        {
          "tipo": "contem",
          "path": "resources/js/Pages/NfeBrasil/Tributacao/RegraForm.casos.md",
          "padrao": "RegraTributariaIbsCbsValidacaoTest"
        },
        {
          "tipo": "arquivo",
          "path": "Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php",
          "guarda": true
        }
      ],
      "depende_threads": [
        "18"
      ]
    },
    {
      "id": "05",
      "titulo": "Campos IBS/CBS no RegraForm",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "05-regraform-ibs-cbs.md",
      "prefixo": [
        "resources/js/Pages/NfeBrasil/Tributacao/RegraForm.tsx",
        "resources/js/Pages/NfeBrasil/Tributacao/RegraForm.casos.md"
      ],
      "nao_toca": [],
      "depende_threads": [
        "04",
        "12"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "resources/js/Pages/NfeBrasil/Tributacao/RegraForm.tsx",
          "padrao": "c_class_trib"
        }
      ]
    },
    {
      "id": "06",
      "titulo": "Motor: MVA -> ICMS-ST, FCP, DIFAL",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "06-motor-st-fcp-difal.md",
      "prefixo": [
        "Modules/NfeBrasil/Services/MotorTributarioService.php",
        "Modules/NfeBrasil/Services/Tributacao/TributoCalculado.php",
        "Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php"
      ],
      "nao_toca": [],
      "provas": [
        {
          "tipo": "contem",
          "path": "Modules/NfeBrasil/Services/Tributacao/TributoCalculado.php",
          "padrao": "valor_st"
        },
        {
          "tipo": "contem",
          "path": "Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php",
          "padrao": "mva"
        }
      ],
      "depende_threads": [
        "17"
      ]
    },
    {
      "id": "07",
      "titulo": "Operacao + vigencia nas regras",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "07-operacao-vigencia.md",
      "prefixo": [
        "Modules/NfeBrasil/Database/Migrations/",
        "Modules/NfeBrasil/Models/",
        "Modules/NfeBrasil/Services/MotorTributarioService.php",
        "Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php"
      ],
      "nao_toca": [],
      "depende_threads": [
        "06"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "Modules/NfeBrasil/Services/MotorTributarioService.php",
          "padrao": "valida_de"
        }
      ]
    },
    {
      "id": "08",
      "titulo": "Simulador read-only",
      "dono": "CL",
      "vaga": 3,
      "arquivo": "08-simulador.md",
      "prefixo": [
        "Modules/NfeBrasil/Http/Controllers/TributacaoController.php",
        "resources/js/Pages/NfeBrasil/Tributacao/Index.tsx",
        "resources/js/Pages/NfeBrasil/Tributacao/Index.charter.md",
        "resources/js/Pages/NfeBrasil/Tributacao/Index.casos.md"
      ],
      "nao_toca": [],
      "depende_threads": [
        "07",
        "12"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "Modules/NfeBrasil/Http/Controllers/TributacaoController.php",
          "padrao": "simular"
        }
      ]
    },
    {
      "id": "09",
      "titulo": "Tabela ICMS/FCP por UF curada",
      "dono": "CL",
      "vaga": 3,
      "arquivo": "09-icms-uf.md",
      "prefixo": [
        "Modules/NfeBrasil/Database/Migrations/",
        "Modules/NfeBrasil/Database/Seeders/",
        "Modules/NfeBrasil/Models/"
      ],
      "nao_toca": [],
      "depende_threads": [
        "07"
      ],
      "provas": []
    },
    {
      "id": "10",
      "titulo": "Sugestoes da IA",
      "dono": "CL",
      "vaga": 3,
      "arquivo": "10-ia-sugestoes.md",
      "prefixo": [
        "Modules/Jana/Ai/Agents/SugestaoFiscalAgent.php",
        "Modules/Jana/Ai/Tools/Fiscal/ProdutoFiscalTool.php",
        "Modules/NfeBrasil/Services/Tributacao/SugestaoFiscalService.php",
        "Modules/NfeBrasil/Database/Migrations/",
        "Modules/NfeBrasil/Http/Controllers/SugestaoFiscalController.php"
      ],
      "nao_toca": [],
      "depende_threads": [
        "07"
      ],
      "provas": []
    },
    {
      "id": "11",
      "titulo": "ADR entrada por XML",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "11-adr-entrada-xml.md",
      "prefixo": [
        "memory/decisions/proposals/2026-10-06-entrada-xml-compra.md"
      ],
      "nao_toca": [],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "memory/decisions/proposals/2026-10-06-entrada-xml-compra.md"
        }
      ]
    },
    {
      "id": "12",
      "titulo": "ALVO nfe-brasil--tributacao",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "12-alvo-tributacao.md",
      "prefixo": [
        "governance/design/targets/nfe-brasil--tributacao.alvo.json",
        "resources/js/Pages/NfeBrasil/Tributacao/Index.charter.md",
        "resources/js/Pages/NfeBrasil/Tributacao/RegraForm.charter.md",
        "memory/reference/prototipo-ui/sources/Wagner/nfe-tributacao.md"
      ],
      "nao_toca": [],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "governance/design/targets/nfe-brasil--tributacao.alvo.json"
        }
      ]
    },
    {
      "id": "13",
      "titulo": "Afericao: origem dos chamados fiscais",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "13-afericao-chamados.md",
      "prefixo": [],
      "nao_toca": [],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "prototipo-ui/cowork/Wagner/cowork-inbox/fiscal/playbook/_saida-13.md"
        }
      ]
    },
    {
      "id": "14",
      "titulo": "Saude fiscal",
      "dono": "CL",
      "vaga": 4,
      "arquivo": "14-saude-fiscal.md",
      "prefixo": [
        "Modules/NfeBrasil/Services/",
        "resources/js/Pages/NfeBrasil/Tributacao/Index.tsx",
        "resources/js/Pages/NfeBrasil/Tributacao/Index.casos.md"
      ],
      "nao_toca": [],
      "depende_threads": [
        "17",
        "07",
        "12",
        "15"
      ],
      "provas": []
    },
    {
      "id": "15",
      "titulo": "Aceite do contador",
      "dono": "CL",
      "vaga": 3,
      "arquivo": "15-aceite-contador.md",
      "prefixo": [
        "Modules/NfeBrasil/Database/Migrations/",
        "Modules/NfeBrasil/Models/",
        "Modules/NfeBrasil/Http/Controllers/"
      ],
      "nao_toca": [],
      "depende_threads": [
        "07"
      ],
      "provas": []
    },
    {
      "id": "16",
      "titulo": "Bloqueio com saida + conserto cadastral",
      "dono": "CL",
      "vaga": 5,
      "arquivo": "16-bloqueio-com-saida.md",
      "prefixo": [
        "resources/js/Pages/Fiscal/_lib/sefaz-actions.ts"
      ],
      "nao_toca": [],
      "depende_threads": [
        "17",
        "13",
        "14"
      ],
      "provas": []
    },
    {
      "id": "17",
      "titulo": "Nota com os itens reais (fase 2B)",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "17-emissao-por-item.md",
      "prefixo": [
        "Modules/NfeBrasil/Services/NfeService.php",
        "Modules/NfeBrasil/Tests/Feature/NfeEmissaoPorItemTest.php"
      ],
      "nao_toca": [
        "Modules/NfeBrasil/Services/MotorTributarioService.php",
        "resources/js/"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "Modules/NfeBrasil/Tests/Feature/NfeEmissaoPorItemTest.php"
        },
        {
          "tipo": "contem",
          "path": "Modules/NfeBrasil/Services/NfeService.php",
          "padrao": "itens_ncm_padrao"
        },
        {
          "tipo": "arquivo",
          "path": "Modules/NfeBrasil/Tests/Feature/MotorTributarioServiceTest.php",
          "guarda": true
        }
      ]
    },
    {
      "id": "18",
      "titulo": "Apagar regra exige permissao fiscal (UC-NFRF-04)",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "18-destroy-regra-sem-permissao.md",
      "prefixo": [
        "Modules/NfeBrasil/Http/Controllers/TributacaoController.php"
      ],
      "nao_toca": [
        "Modules/NfeBrasil/Services/MotorTributarioService.php"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "resources/js/Pages/NfeBrasil/Tributacao/RegraForm.casos.md",
          "padrao": "UC-NFRF-04"
        },
        {
          "tipo": "arquivo",
          "path": "Modules/NfeBrasil/Tests/Feature/TributacaoGatesContratoTest.php",
          "guarda": true
        }
      ]
    },
    {
      "id": "19",
      "titulo": "Import CSV grava no tenant do preview (UC-NFIM-04)",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "19-import-csv-tenant.md",
      "prefixo": [
        "Modules/NfeBrasil/Http/Controllers/ImportRegrasController.php",
        "Modules/NfeBrasil/Services/Tributacao/ImportRegrasCsvService.php"
      ],
      "nao_toca": [
        "Modules/NfeBrasil/Services/MotorTributarioService.php"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "resources/js/Pages/NfeBrasil/Tributacao/ImportCsv.casos.md",
          "padrao": "UC-NFIM-04"
        },
        {
          "tipo": "arquivo",
          "path": "Modules/NfeBrasil/Tests/Feature/TributacaoGatesContratoTest.php",
          "guarda": true
        }
      ]
    },
    {
      "id": "20",
      "titulo": "Devolucao copia base e aliquotas da nota de origem",
      "dono": "CL",
      "vaga": 3,
      "arquivo": "20-devolucao.md",
      "prefixo": [
        "Modules/NfeBrasil/Services/",
        "Modules/NfeBrasil/Tests/Feature/NfeDevolucaoTest.php"
      ],
      "nao_toca": [
        "Modules/NfeBrasil/Services/MotorTributarioService.php"
      ],
      "depende_threads": [
        "17",
        "07"
      ],
      "provas": [
        {
          "tipo": "arquivo",
          "path": "Modules/NfeBrasil/Tests/Feature/NfeDevolucaoTest.php"
        }
      ]
    },
    {
      "id": "21",
      "titulo": "Configurar pelo certificado - backend",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "21-certificado-template-backend.md",
      "prefixo": [
        "Modules/NfeBrasil/Services/",
        "Modules/NfeBrasil/Services/Tributacao/TributacaoTemplateService.php",
        "Modules/NfeBrasil/Http/Controllers/"
      ],
      "nao_toca": [
        "Modules/NfeBrasil/Services/SefazConsultaCadastroService.php",
        "Modules/NfeBrasil/Services/CertificadoService.php",
        "resources/js/"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "Modules/NfeBrasil/Services/Tributacao/TributacaoTemplateService.php",
          "padrao": "sugerir"
        },
        {
          "tipo": "contem",
          "path": "Modules/NfeBrasil/Services/Tributacao/TributacaoTemplateService.php",
          "padrao": "template.aplicado"
        }
      ]
    },
    {
      "id": "22",
      "titulo": "Configurar pelo certificado - tela",
      "dono": "CL",
      "vaga": 4,
      "arquivo": "22-certificado-template-tela.md",
      "prefixo": [
        "resources/js/Pages/NfeBrasil/Tributacao/Index.tsx",
        "resources/js/Pages/NfeBrasil/Tributacao/Index.casos.md"
      ],
      "nao_toca": [
        "Modules/"
      ],
      "depende_threads": [
        "21",
        "12"
      ],
      "provas": [
        {
          "tipo": "contem",
          "path": "resources/js/Pages/NfeBrasil/Tributacao/Index.casos.md",
          "padrao": "UC-NFTR-18"
        }
      ]
    },
    {
      "id": "23",
      "titulo": "Retencoes federais na NFS-e",
      "dono": "CL",
      "vaga": 2,
      "arquivo": "23-retencoes-nfse.md",
      "prefixo": [
        "Modules/NFSe/Services/"
      ],
      "nao_toca": [
        "resources/js/"
      ],
      "provas": [],
      "bloqueio": "consolidacao NfeBrasil x NFSe ([W] pendente em fiscal-faturamento.md)"
    },
    {
      "id": "24",
      "titulo": "Fator R automatico",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "24-fator-r.md",
      "prefixo": [
        "Modules/NfeBrasil/Services/FatorRService.php"
      ],
      "nao_toca": [
        "resources/js/"
      ],
      "provas": []
    },
    {
      "id": "25",
      "titulo": "Beneficio fiscal (reducao + cBenef)",
      "dono": "CL",
      "vaga": 3,
      "arquivo": "25-beneficio-cbenef.md",
      "prefixo": [
        "Modules/NfeBrasil/Database/Migrations/",
        "Modules/NfeBrasil/Services/MotorTributarioService.php"
      ],
      "nao_toca": [
        "resources/js/"
      ],
      "depende_threads": [
        "07",
        "17"
      ],
      "provas": []
    },
    {
      "id": "26",
      "titulo": "Filial em outro estado",
      "dono": "CL",
      "vaga": 3,
      "arquivo": "26-filial-origem.md",
      "prefixo": [
        "Modules/NfeBrasil/Services/NfeService.php"
      ],
      "nao_toca": [
        "resources/js/"
      ],
      "depende_threads": [
        "17",
        "07"
      ],
      "provas": []
    },
    {
      "id": "27",
      "titulo": "CNPJ alfanumerico",
      "dono": "CL",
      "vaga": 1,
      "arquivo": "27-cnpj-alfanumerico.md",
      "prefixo": [
        "Modules/NfeBrasil/"
      ],
      "provas": []
    },
    {
      "id": "28",
      "titulo": "Fechamento do mes pro contador",
      "dono": "CL",
      "vaga": 3,
      "arquivo": "28-fechamento-contador.md",
      "prefixo": [
        "Modules/Fiscal/Services/FechamentoContadorService.php"
      ],
      "depende_threads": [
        "15",
        "24"
      ],
      "provas": []
    },
    {
      "id": "29",
      "titulo": "ICMS x ISS por produto e DIFAL Simples por UF",
      "dono": "CL",
      "vaga": 3,
      "arquivo": "29-icms-iss-e-difal-por-uf.md",
      "prefixo": [
        "Modules/NfeBrasil/Database/Migrations/"
      ],
      "depende_threads": [
        "07",
        "15"
      ],
      "provas": []
    }
  ]
}
```
