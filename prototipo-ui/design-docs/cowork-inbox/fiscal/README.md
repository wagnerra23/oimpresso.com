---
id: cowork-inbox-fiscal-readme
intake: Fiscal — cockpit unificado (F1 Cowork · ondas 1–5)
owner: wagner
autor: "[CC] Claude Cowork"
data: 2026-08-24
lido_no_main: "2026-08-24 — resources/js/Pages/Fiscal/** (7 charters + 7 casos.md) · Modules/Fiscal/{Routes/web.php, Http/Controllers/CockpitController.php} · prototipo-ui/contrato/contract.schema.json"
---

# Fronteiras do F1 Fiscal — o que este pacote declara

O módulo Fiscal **já existe vivo** no `main` (7 telas Inertia com charter e casos). Este pacote **não** propõe módulo novo: declara as fronteiras do que o F1 do Cowork acrescentou por cima do vivo, nas 5 ondas de refino, para o [CL] traduzir sem adivinhar.

## Arquivos

| Arquivo | O que é |
|---|---|
| `Cockpit.charter.md` · `Cockpit.casos.md` | lei + aceite das ondas 1/2/3 na tela de notas (mutações, ⌘K, teclado, densidade, paginação, procedência) |
| `Dfe.charter.md` · `Dfe.casos.md` | lei + aceite da manifestação em lote e da aba Histórico |
| `Sped.charter.md` · `Sped.casos.md` | lei + aceite da prévia do TXT e do cartão de validação externa |
| `Config.charter.md` · `Config.casos.md` | lei + aceite da tela de configuração — **proposta [CC], ratificação [W] pendente**: a tela é editável, gate próprio `fiscal.config.ambiente`, séries lidas de verdade |
| `Config.divergencia.md` | o registro do impasse que originou o charter acima (mantido como histórico) |
| `FiscalOndasF1Test.php` | Pest que **nasce vermelho** nos 5 pontos que o vivo ainda não sustenta |
| `../../prototipo-ui/contrato/fiscal-{cockpit,dfe,sped,config}.contract.json` | contrato de tela (ADR 0286) com âncoras `data-contract` já instrumentadas no F1 |
| `../../prototipo-ui/cowork/fiscal/` | export do build (4 jsx + css, nada de `.md` — regra do `cowork-ssot-guard`); é o caminho que os contratos declaram em `fonte` |

## Precedência aplicada

`teste verde > casos > charter > SPEC` (proibicoes.md).

**Exceção registrada:** o `Config.casos.md` do vivo se recusa a escrever UC enquanto a disputa charter × código estiver aberta, porque isso seria escolher o vencedor de uma disputa de intenção. [W] autorizou a decisão nesta sessão — então o trio do Config existe, **marcado como proposta [CC] até virar ADR**. Se [W] reverter, `UC-FCFG-02..06` caem inteiros.

## Decisões [W] que travam a tradução

1. **Procedência das 6 superfícies de demonstração** (`CU-FISC-16`): lista unificada de notas, eventos do cabeçalho, contadores das visões salvas, situação da SEFAZ, pacote da contabilidade, write-off. Opções: marcar procedência na UI (é o que o F1 faz), esconder atrás de flag, ou declarar Non-Goal. Sem isso, filtro/visão/densidade/lote não viram contrato — o próprio `Cockpit.casos.md` do vivo diz isso.
2. ~~**Config: charter read-only × formulários existentes**~~ → **decidido nesta sessão**: a tela é editável, o charter cede, e as duas ações de risco ficam atrás de `fiscal.config.ambiente`. Falta virar ADR.
3. ~~**Séries por filial**~~ → **decidido**: leitura real, filial inventada removida. Falta virar ADR.
4. **Manifestação em lote** é backlog declarado no charter do vivo. Aprova virar rota (`POST /fiscal/acoes/dfe/lote`) ou fica só no F1?
5. **Export CSV de eventos** — mesmo caso (backlog declarado). Aprova rota de export server-side ou fica no cliente?

## O que NÃO está aqui

- Nada foi commitado no `main`: as tools de GitHub deste projeto são read-only. Ponte = cola zero-toque ou Issue `cowork-intake`.
- O build do F1 vive neste projeto (`fiscal-data.jsx` · `fiscal-actions.jsx` · `fiscal-page.jsx` · `fiscal-subpages.jsx` · `fiscal-page.css`); o export pro `prototipo-ui/cowork/fiscal/` depende da mesma ponte.
