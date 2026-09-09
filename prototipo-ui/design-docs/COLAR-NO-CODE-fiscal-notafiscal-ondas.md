# Fiscal / Nota fiscal (`Modules/Fiscal` + `NfeBrasil` + `NFSe`) — ponte do módulo · reescrita 2026-09-08

> **Este arquivo virou ponteiro.** O pacote de 10 ondas que morava aqui (03/09, 36,8 KB) foi medido contra a árvore `11eff17f13db` em 08/09 e **5 ondas já estavam em produção**. Manter o texto é cache que envelhece (L-42).
> **Dono do módulo agora:** `cowork-inbox/fiscal/playbook/00-INDICE.md` (`SINCRONIZAR Fiscal`, rev.2) — **2 threads executáveis, 0 travadas**. A aferição (`_saida-03.md`) fechou as 5 decisões [W] com caminho e linha. Destino no `main`: `prototipo-ui/design-docs/cowork-inbox/fiscal/playbook/`.
> **Constituição:** `CONSTITUICAO-COWORK.md` (C1–C13) + `memory/proibicoes.md`.

## 0 · Leis DESTE módulo (preservadas — as únicas linhas do pacote antigo que continuam valendo)
1. **A produção não se repinta "pro protótipo"** *(C4)*. As 7 telas do Fiscal são réplica viva e estão **à frente** do alvo em vários pontos medidos.
2. **Nenhuma onda escreve motor fiscal.** Cálculo é do `MotorTributarioService`; CC-e/inutilização/cancelamento/retransmissão são Services do `NfeBrasil` (o `AcoesController` delega); emissão de serviço é do `NfseEmissaoService`; SPED é do `SpedIcmsIpiGeneratorService`.
3. **Ledger de `Eventos` é append-only** — sem `UPDATE`/`DELETE`; correção é evento novo.
4. **Sem número fiscal sem lei citada literal** (`Ajuste SINIEF 07/2005`, janela 24h NFC-e / 168h NF-e, `cstat 102`, `tpEvento 110110/110111`, LC 116, CONFAZ Guia Prático v3.1.1 perfil A).
5. **`501`/`NAO_IMPLEMENTADO` nunca se apresenta como sucesso** (XML, DANFE, TXT do SPED).
6. **Agravante de C6:** 🔴 (fiscal/legal/multi-tenant/schema) vai **sozinho no PR**, com o teste no mesmo PR.

## 1 · O que morreu na releitura de 08/09
| onda | estado real | prova |
|---|---|---|
| 1 Alertas fiscais | **feita** | `AlertasFiscais.tsx` + `Cockpit.tsx:23` |
| 2 Linha por teclado | **feita nas 2 telas** | `Cockpit.tsx:605` · `Nfe.tsx:231`/`:316` · `UC-FCKP-11` · PR #6707 |
| 4 Sparklines ⛔[W]2 | **feita** | `RibbonSpark.tsx` + `Cockpit.tsx:29` — **decisão respondida pelo código** |
| 5 Tipo + densidade ⛔[W]1 | **feita nas 3 telas** | `DensidadeToggle.tsx` em `Cockpit:24` · `Nfe:25` · `Nfse:20` — **decisão respondida pelo código** |
| 10 Procedência ⛔[W]5 | **feita, 9 chaves** | `Cockpit.tsx:31` é **named import** (a 1ª busca falhou por sintaxe); renderizado em `:368 :380 :393 :425`(×2)` :465 :473 :500 :501` |
| 6 DF-e lote ⛔[W]3 | **feita** | `Dfe.tsx:381` `data-contract="lote-dfe"` · modal `:607` · *"definitiva por nota"* `:659` · UC 07..10 verdes |
| 7 CSV de eventos ⛔[W]3 | **feita e contratada** | `Eventos.charter.md` §Contrato do export CSV · `GET /fiscal/eventos/export` · `UC-FEVT-05/06/07` |
| 8 Config abas + gate ⛔[W]4 | **feita** | `Config.tsx:26` (4 abas) · `UC-FCFG-06`/`07` · 2 testes de gate/cerimônia |
| 9 SPED competência | **feita** | `Sped.tsx:318-335` régua com `motivoBloqueio` + `ItensDaRegua` |
| "0 contrato `fiscal-*`" | **7 existem** | `contrato/fiscal-{cockpit,config,dfe,eventos,nfe,nfse,sped}.contract.json` |
| 3 Paginação | **de pé** | `Pagination` = 0 hit em `Pages/Fiscal/` → thread 02 |
| rede | **E2E = 0** | 17 specs em `e2e/`, nenhum fiscal → thread 01 |

## 2 · Threads
| # | thread | dono | estado |
|---|---|---|---|
| 01 | Rede: `e2e/fiscal-cockpit.spec.ts` + `fiscal-nfe.spec.ts` | [CL] | **próximo** |
| 02 | Paginação `.fx-pager` no Cockpit | [CL] | **próximo** |
| 03 | Aferição read-only | [CC] | **FEITA** → `_saida-03.md` |

## 3 · RESÍDUO — fila [W] (as 11 perguntas preservadas)
**Respondidas pelo código — confirmar ou contestar, não decidir de novo:** ~~#1 tipo+densidade~~ · ~~#2 sparklines~~ · ~~#3 DF-e lote (uma requisição **por nota**, de propósito) + CSV (**servidor**)~~ · ~~#4 Config abas/gate/`envioDocumentos`~~ (resta só a **ADR** dos 2 pontos — papel, não tela) · ~~#5 procedência~~ (**9 chaves** no cockpit).
**O que sobra para [W] é confirmação de escopo:** as 9 superfícies de procedência são as que você queria? Se a intenção era **não** ter sparkline, o que está em produção é a divergência.
**🔴 achado na aferição, sem dono:** `ManifestacaoService::buildConfig()` e `DistribuicaoDfeService::buildConfig()` fazem `select([...,'state'])` em `business` e **a coluna `state` não existe** (133 colunas no schema canônico, no CT 100 e na Hostinger; nenhuma das 43 migrations a cria) — `Dfe.casos.md:216`. Se ainda vale, **nenhuma manifestação chega à SEFAZ**.
**Stale (1 linha):** `Dfe.charter.md:39` ainda diz `❌ Bulk manifestar — backlog`, refutado pelos UC 07..10.
**Fila 🔴, fora deste pacote:** #6 emissão pelo cockpit × UI legada `NfeBrasil/Transactions` · #7 IBS/CBS `US-FISCAL-021` · #8 lane com migrations do NfeBrasil (destrava o único 🔴 Tier 0, `UC-FNFE-01`) · #9 NFS-e emissão · #10 contingência (modos do piloto) · #11 telemetria/Jana.
**Capacidade que não existe (não é onda de UI):** emissão NF-e/NFC-e (motor vivo, falta superfície + gate) · emissão NFS-e (`Modules/NFSe` testado, sem tela) · **contingência SEFAZ** (`tpEmis` hardcoded em 1, `NfeService.php:1198` — SEFAZ fora do ar = balcão parado) · cálculo IBS/CBS · SPED PIS/COFINS · importação de XML de entrada (contador de entrada é literal `0`).
**Rastreabilidade:** 8 CU sem UC (`CU-FISC-02·03·08·09·10·11·15·16`) · `US-FISCAL-022` `todo` com `CertHealthCheckCommand` + teste vivos (divergência SPEC×código).
