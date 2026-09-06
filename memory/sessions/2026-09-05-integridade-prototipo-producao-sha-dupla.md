---
date: "2026-09-05"
topic: "Integridade protótipo × produção por dupla âncora SHA (fonte × alvo): 8/8 recibos do design-sync invalidados, 3 mapas STALE, 3 âncoras do espelho atrás do Cowork vivo — tudo re-medido e sincronizado"
authors: ["C"]
related_adrs: ["0384-design-sync-recibos-executaveis-por-tela", "0374-emenda-0315-espelho-cowork-e-rota-prevista", "0379-bundle-design-transacao-manifesto-delta-staging"]
outcomes:
  - "projeção design-sync recomputada: 1 applied + 7 compared (Fiscal) re-registrados contra os .tsx de 04/09"
  - "recibo de Arquivos refeito de origin/main fresco (o de 01/09 nasceu stale — LC-20 3ª ocorrência)"
  - "3 map.json STALE regenerados com --atualizar (preenchimento preservado) · 2 âncoras data-contract declaradas em Compras"
  - "espelho Cowork: inbox/financeiro/vendas-page.jsx + github.md descidos do vivo por --export-from (fiel por construção) · ledger de frescor registrado"
---

# Integridade protótipo × produção — dupla ancoragem SHA

> Pedido [W]: *"confira a integridade entre o protótipo e a produção com dupla ancoragem sha do git"*
> → *"faça lista módulos telas funções"* → *"pode fazer todos… sincronize tudo"*.
> Medido sobre `origin/main` = `e86130722d`. Portas vivas usadas, nesta ordem:
> `scripts/design-sync/status.mjs --refresh` (ADR 0384) · `scripts/governance/design-code-map-check.mjs --check` ·
> `scripts/governance/cowork-mirror-freshness.mjs --sla | --snapshot-from | --compare --ledger | --export-from` (ADR 0374).

## 1 · O que a dupla âncora mostrou (antes de mexer)

| achado | medida |
|---|---|
| Projeção commitada (01/09) afirmava | 1 applied + 7 compared |
| Recomputada com hashes de 05/09 | 0 applied + 0 compared — 67 `anchored` |
| Ledger `applications.json` | 8/8 recibos invalidados pela cascata D-7 |
| Fiscal (7) | fonte `fiscal-page.jsx` igual; **alvo** `.tsx` moveu em 04/09 (#6723 … #6741) — a cascata funcionou como desenhada |
| Arquivos (1) | **fonte** "moveu" sem commit desde 27/08: o recibo de 01/09 gravou o sha da versão de **24/08** (#6198) quando o main já estava em **27/08** (#6379). Recibo nasceu stale. Classe [LC-20](../LICOES_CODE.md) |
| `*.map.json` | 12 mapas · 3 STALE por re-export do protótipo (`caixa-unificada` 10/07, `Financeiro/unificado` 24/08, `Sells/vendas` 10/07) · 11/52 partes com âncora `data-contract` |
| Espelho Cowork (`--sla`) | rc=1 — última rodada 03/09 mediu 1/258; 157 arquivos vivos nunca desceram |

Lista completa módulos → telas → funções (26 módulos · 93 telas · 52 partes mapeadas) entregue ao [W] como arquivo na sessão; a fonte viva é `status.mjs --json` + os 12 `*.map.json`, não este log.

## 2 · O que foi feito (cada linha com o comando que a reproduz)

1. **Recibos** — `status.mjs --mark-compared fiscal-page.jsx --target Fiscal/<7>.tsx --map …` (7×) · `--mark-compared` + `--mark-applied arquivos-page.jsx` (evidência `arquivos-index-gap.md`). Resultado: `{applied:1, compared:7, anchored:59, to-create:8, blocked:18}`. `--check-lifecycle --module Fiscal --minimum compared` → rc 0.
2. **Mapas** — `gerar-map.mjs <gap.md> --atualizar` nos 3 STALE (stdout → arquivo; preenchimento humano preservado). `design-code-map-check --check --strict` → rc 0, 0 DRIFT.
3. **Âncora criada** — `compras-grade-matrix.map.json`: partes `header-selecao-de-modelo` e `integracao` ganharam `vivo.ancora: "purchase-itens"` (regiões 420-466 de `Purchase/Create.tsx` renderizam dentro do `Card data-contract="purchase-itens"`, :414→:573). Estável: 11 → **13/52**. As 2 "acionáveis" restantes são de Arquivos com `vivo: TODO` (achados de diff sem região) — não dá para ancorar com honestidade.
4. **Espelho** — rota do bundle esgotada: `sync/bundle.manifest.json` remoto = bundle ativo local **byte a byte** (255/255, id `5023b274…`, 24/08); o diário `github.md` diz 3× "ciclo fechado SEM pacote regenerado" (lápide §5 2026-08-27: regeneração é do lado Cowork). Então `get_file` das 3 âncoras dos mapas STALE (todas > piso de persistência, chegaram como **arquivo**): `--snapshot-from` → 3 DIVERGE → direção medida por `diff` (espelho = import de 27/08 sem edição local; vivo carrega as migrações do diário: abas → `TabBar`, segmented → `CliSeg`) → `--export-from` (3 jsx + `github.md` → `design-docs/`) → `--compare --check --ledger` = **3 sync · 0 stale · 255 unchecked**. Guards: `cowork-ssot-guard` ✓ · `--unverified --check` ✓ · `--check-refs` ✓ · `--check-orfaos` ✓ · `anchor-content-check` ✓.
5. **Mapas de novo** — o export mudou o sha de conteúdo dos 3 protótipos → `--atualizar` outra vez → `--strict` rc 0.

## 3 · O que ficou aberto (declarado, não escondido)

- **255/258 arquivos do espelho seguem sem veredito** — `fiscal-page.jsx` (32 KB) e `arquivos-page.jsx` (43 KB) estão abaixo do piso em que o `get_file` persiste em arquivo; compará-los exigiria transcrição (ADR 0374) ou o bundle regenerado no Cowork. Canário barato: as 7 tags `?v=` do shell vivo existem no shell do espelho, então o **shell** está em dia.
- **11 telas com `-gap.md` sem `.map.json`** (Cliente, Compras, Crm, KB, OficinaAuto, Produto, RecurringBilling, Sells×2, _DesignSystem×2): o gerador recusa — gap.md em formato antigo, sem tabela `Parte`+`Ação`. Criar mapa exige reescrever o gap.md primeiro.
- **18 protótipos órfãos + 8 a criar** (`--check-mapping` rc 1, estado pré-existente no main): destino inequívoco é decisão [W] por tela.
- **Testado/validado = 0/0** em todas as 93 telas — nenhum recibo executável de teste ou smoke existe ainda (D-5/D-6 da 0384).

## 4 · Lições (recibo)

- [LC-20](../LICOES_CODE.md) → 3ª ocorrência (base envelhecida em escrita): recibo de design-sync gravado de checkout atrasado. A defesa que pegou foi a **dupla âncora** (hash da fonte × hash do alvo no ledger, ADR 0379/0384), não memória.
- A rota do bundle **confirma** o que já está no espelho, nunca o que mudou no vivo depois da emissão (lápide §5 2026-08-25) — o `get_file` por âncora foi a única rota que trouxe estado real.
