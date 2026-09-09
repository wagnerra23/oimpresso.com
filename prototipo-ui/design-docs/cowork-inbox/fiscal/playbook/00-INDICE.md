---
sessao: "00"
titulo: SINCRONIZAR Fiscal — índice do playbook (fonte da máquina em §5)
autor: "[CC]"
criado: 2026-09-08
revisado: 2026-09-08 rev.2 — após a thread 03 (aferição). 4 das 6 threads da rev.1 morreram; ver `_saida-03.md`
base: wagnerra23/oimpresso.com@main (árvores 11eff17f13db · 99530522729d, lidas 2026-09-08 11:09 e 11:19 UTC)
destino_no_main: prototipo-ui/design-docs/cowork-inbox/fiscal/playbook/
constituicao: CONSTITUICAO-COWORK.md (C1–C12) + memory/proibicoes.md — citadas, não copiadas
regra: PEDIDO, não inventário. Estado é derivado (§2-bis). Nunca em prototipo-ui/cowork/ (guard R1).
---

# SINCRONIZAR Fiscal — playbook

> **Absorve e CORRIGE** `COLAR-NO-CODE-fiscal-notafiscal-ondas.md` (03/09, 10 ondas · 36,8 KB). Medido em 08/09: **9 das 10 ondas estão feitas em produção** e **as 5 decisões ⛔ [W] foram respondidas pelo código**. Sobra **1 onda de UI** (paginação) e **1 lacuna de rede** (E2E = 0).
> **Leis do módulo:** as 6 do `§0` do arquivo-ponte continuam valendo (motor fiscal não se toca · ledger append-only · lei citada literal · `501` nunca é sucesso · 🔴 sozinho no PR). Não recopiadas.

## 0 · O que a medição derrubou

| onda de 03/09 | estado | prova |
|---|---|---|
| 1 Alertas fiscais | **feita** | `AlertasFiscais.tsx` + `Cockpit.tsx:23` |
| 2 Linha por teclado | **feita nas 2 telas** | `Cockpit.tsx:605` · `Nfe.tsx:231`/`:316` · `UC-FCKP-11` · PR #6707 |
| **3 Paginação `.fx-pager`** | **DE PÉ** | `Pagination` = **0 hit** em `Pages/Fiscal/` → thread 02 |
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

## 1 · LEVANTAR — 4 denominadores
**D1 rota:** `Modules/Fiscal/Routes/web.php` — 7 telas (cockpit · NF-e/NFC-e · NFS-e · DF-e · Eventos · Config · SPED).
**D2 nav legado:** emissão segue em `NfeBrasil/Transactions` — fila 🔴, fora deste pacote.
**D3 protótipo:** `.fx-page` com **10 filhos** nesta ordem — `.fx-h · .ds-tabbar · .fx-ribbon · .fx-alerts · .fx-writeoff · .fx-toolbar · .fx-chips · .fx-table.fx-d-comfort · .fx-pager · .fx-toasts`; T1 **1099/1099/1099**, dark.
**D4 runtime:** as 7 rotas já têm Page React com trio completo (7 `.tsx` de 8,5–42 KB + 7 charter + 7 casos), **16 componentes** e **11 `_lib`**. Nenhuma rota espera tela nova.

## 2 · Threads

| # | thread | dono | prefixo | vaga | arquivo |
|---|---|---|---|---|---|
| 01 | Rede: 2 specs E2E (cockpit + NF-e), derivados dos contratos existentes | [CL] | `e2e/fiscal-cockpit.spec.ts` · `e2e/fiscal-nfe.spec.ts` | 1 | `01-rede-e2e.md` |
| 02 | Paginação `.fx-pager` — a única onda de UI viva | [CL] | `Pages/Fiscal/Cockpit.tsx` (+ `CockpitController` **se** o corte for server-side) | 1 | `02-paginacao.md` |
| ~~03~~ | ~~Aferição~~ | [CC] | — | — | **FEITA** → `_saida-03.md` |
| ~~04 05 06~~ | ~~DF-e lote · Config · Procedência~~ | — | — | — | **mortas**: respondidas pelo código (§0) |

**Vaga 1:** 01 ∥ 02 (prefixos disjuntos). Nenhuma outra thread — e não se inventa terceira para o playbook parecer cheio.

## 2-bis · ESTADO — derivado, nunca escrito
`node scripts/qa/placar-indice.mjs --indice prototipo-ui/design-docs/cowork-inbox/fiscal/playbook/00-INDICE.md --root . --proximo`
Render esperado: `Fiscal: entregue 1 de 3 · próximo 2 · bloqueada 0` — **PRÓXIMO: 01 · 02.**

## 3 · Abertura de thread (colar como 1ª mensagem — sessão limpa)
```
Sessão fresca. ANTES de abrir: gh pr list --state open e cruze com os arquivos do seu prefixo.
Leia nesta ordem, do main:
1. prototipo-ui/design-docs/cowork-inbox/ponte/03-REGRAS-DE-PARALELISMO.md   ← Leis 1–4
2. prototipo-ui/CONSTITUICAO-COWORK.md                                       ← C1–C12
3. .../fiscal/playbook/00-INDICE.md  §0 · §1 · §2                            ← o que JÁ está feito
4. .../fiscal/playbook/NN-<sua-thread>.md                                    ← escopo · recorte · prova
5. .../fiscal/playbook/_saida-03.md                                          ← a medição que matou 9 ondas
6. o §0 (leis do módulo) de prototipo-ui/COLAR-NO-CODE-fiscal-notafiscal-ondas.md
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

**Fila 🔴 preservada:** emissão pelo cockpit × UI legada `NfeBrasil/Transactions` · IBS/CBS `US-FISCAL-021` · lane com migrations do NfeBrasil (destrava o único 🔴 Tier 0, `UC-FNFE-01`) · NFS-e emissão · contingência SEFAZ (`tpEmis` **hardcoded em 1**, `NfeService.php:1198`) · telemetria/Jana.
**Capacidade que não existe:** emissão NF-e/NFC-e (motor vivo, falta superfície + gate) · emissão NFS-e (`Modules/NFSe` testado, sem tela) · contingência · cálculo IBS/CBS · SPED PIS/COFINS · importação de XML de entrada (contador de entrada é literal `0`).
**Rastreabilidade:** 8 CU sem UC (`CU-FISC-02·03·08·09·10·11·15·16` — o 16 pode fechar com a evidência do `_saida-03`) · `US-FISCAL-022` `todo` com `CertHealthCheckCommand` + teste vivos.

## 5 · Fonte da máquina (schema em `_schema/playbook.schema.json`)
```json
{
  "modulo": "Fiscal",
  "sha": "99530522729d",
  "gerado": "2026-09-08",
  "absorve": ["prototipo-ui/COLAR-NO-CODE-fiscal-notafiscal-ondas.md"],
  "decisoes": [
    { "id": "D-TIPO", "pergunta": "Select de tipo + densidade em NF-e/NFS-e", "respondida": true, "resposta": "codigo: DensidadeToggle em Cockpit.tsx:24, Nfe.tsx:25, Nfse.tsx:20" },
    { "id": "D-SPARK", "pergunta": "As 3 sparklines do ribbon entram?", "respondida": true, "resposta": "codigo: RibbonSpark em Cockpit.tsx:29" },
    { "id": "D-DFE", "pergunta": "DF-e lote e CSV de eventos", "respondida": true, "resposta": "codigo: Dfe.tsx:381 data-contract=lote-dfe (uma requisicao POR NOTA) + GET /fiscal/eventos/export no servidor (Eventos.charter.md)" },
    { "id": "D-CONFIG", "pergunta": "Config: abas, gate de ambiente e Envio de documentos", "respondida": true, "resposta": "codigo: ConfigTab 4 abas (Config.tsx:26), gate UC-FCFG-06, cerimonia UC-FCFG-07, prop envioDocumentos em :140. Resta a ADR dos 2 pontos — papel" },
    { "id": "D-PROC", "pergunta": "CU-FISC-16 procedencia nas superficies", "respondida": true, "resposta": "codigo: named import em Cockpit.tsx:31, 9 chaves renderizadas (:368 :380 :393 :425x2 :465 :473 :500 :501)" }
  ],
  "threads": [
    { "id": "01", "titulo": "Rede: 2 specs E2E (cockpit + NF-e)", "dono": "CL", "vaga": 1, "arquivo": "01-rede-e2e.md",
      "prefixo": ["e2e/fiscal-cockpit.spec.ts", "e2e/fiscal-nfe.spec.ts"],
      "nao_toca": ["resources/js/Pages/Fiscal/", "Modules/Fiscal/", "prototipo-ui/contrato/fiscal-cockpit.contract.json", "prototipo-ui/contrato/fiscal-nfe.contract.json"],
      "provas": [
        { "tipo": "arquivo", "path": "e2e/fiscal-cockpit.spec.ts" },
        { "tipo": "arquivo", "path": "e2e/fiscal-nfe.spec.ts" },
        { "tipo": "arquivo", "path": "prototipo-ui/contrato/fiscal-cockpit.contract.json", "guarda": true },
        { "tipo": "contem", "path": "resources/js/Pages/Fiscal/Cockpit.tsx", "padrao": "onKeyDown", "guarda": true, "nota": "UC-FCKP-11 nao pode sumir num PR de rede" }
      ] },
    { "id": "02", "titulo": "Paginacao .fx-pager no Cockpit", "dono": "CL", "vaga": 1, "arquivo": "02-paginacao.md",
      "prefixo": ["resources/js/Pages/Fiscal/Cockpit.tsx", "Modules/Fiscal/Http/Controllers/CockpitController.php"],
      "nao_toca": ["resources/js/Pages/Fiscal/_components/", "resources/js/Pages/Fiscal/_lib/", "resources/js/Pages/Fiscal/Nfe.tsx"],
      "provas": [
        { "tipo": "contem", "path": "resources/js/Pages/Fiscal/Cockpit.tsx", "padrao": "Pagination" },
        { "tipo": "contem", "path": "resources/js/Pages/Fiscal/Cockpit.tsx", "padrao": "onKeyDown", "guarda": true },
        { "tipo": "arquivo", "path": "resources/js/Pages/Fiscal/Cockpit.casos.md", "guarda": true, "nota": "43.511 B — ESTENDER, nunca recriar" }
      ] },
    { "id": "03", "titulo": "Afericao read-only (FEITA)", "dono": "CC", "vaga": 1, "arquivo": "03-afericao.md",
      "prefixo": [], "nao_toca": ["resources/js/Pages/Fiscal/", "Modules/Fiscal/"],
      "provas": [ { "tipo": "arquivo", "path": "prototipo-ui/design-docs/cowork-inbox/fiscal/playbook/_saida-03.md" } ],
      "nota_provas": "matou as threads 04/05/06 da rev.1 e fechou as 5 decisoes [W] com caminho e linha" }
  ]
}
```
