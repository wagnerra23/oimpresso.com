---
date: "2026-09-02"
hour: "21:30 BRT"
duration: "4h"
topic: "Jana — as 3 abas que faltavam no Painel (Alertas · Ações · Plataforma), uma por PR, mais o espelho STALE re-baixado"
authors: [W, C]
outcomes:
  - "Espelho jana-merge.jsx estava STALE (1136 x 1141 linhas) e foi re-baixado pela máquina antes de comparar; a lista de abas da âncora não mudou"
  - "3 PRs stacked, uma aba cada: Alertas (#6607), Ações (#6608), Plataforma (#6609) — ordem final da barra = a do JmTabs"
  - "Plataforma: o dropdown legado usava can('jana.superadmin'), bypassado pelo Gate::before — menu e rota passaram a usar o mesmo gate real (podeVerPlataforma)"
  - "O que a âncora desenha e o servidor não honra ficou fora com medição no charter (silenciar, perguntar, config de alertas, alerta de gate caducado)"
  - "Card Cheques segue sem fonte (medido 08-31) — decisão [W] sobre migrar FINANCEIRO_CHEQUE; não re-litigado"
prs: [6600, 6607, 6608, 6609]
us: [US-COPI-060, US-COPI-148]
related_adrs:
  - "0093-multi-tenant-isolation-tier-0"
  - "0264-governanca-executavel-trio-dominio-e2e"
  - "0374-emenda-0315-espelho-cowork-e-rota-prevista"
---

# Sessão 2026-09-02 — Jana: paridade das abas do Painel (3 PRs + 1 de espelho)

**Pedido [W]:** fechar *"abas: protótipo 6 × prod 3"* do handoff 2026-08-31 §Paridade Painel — Alertas, Ações, Plataforma — uma aba por PR, copy literal do protótipo, casos + teste por UC, `jana-painel.contract.json` atualizado. E o card **Cheques × `metodos`**.

## Entregue

| PR | o quê | base |
|---|---|---|
| [#6600](https://github.com/wagnerra23/oimpresso.com/pull/6600) | espelho `jana-merge.jsx` STALE → re-baixado pela máquina (`--export-from` do JSON persistido do `get_file`; ledger registrado). Delta: `JmTabs` via `CliTabs`; lista de abas igual | main |
| [#6607](https://github.com/wagnerra23/oimpresso.com/pull/6607) | **Alertas** — ghost 3º, `AlertaService::calcular()` extraído de `avaliar()` (mesma fórmula) + `listar()`, `AlertasController@index` → Inertia, Blade stub apagado, trio + contrato + Pest | main |
| [#6608](https://github.com/wagnerra23/oimpresso.com/pull/6608) | **Ações** — ghost 4º, rota `jana.acoes.index`, `AcaoHitlService::TITULOS` + `fila()`, `JanaAcaoModal` reusado, recibo gravado, trio + contrato + Pest | #6607 |
| [#6609](https://github.com/wagnerra23/oimpresso.com/pull/6609) | **Plataforma** — ghost 6º **só com `jana.superadmin` real** (`DataController::podeVerPlataforma` espelha o P0 #6421; o dropdown legado usava `can()` bypassado), `JanaSubNav maxVisible 6`, `SuperadminController@metas` → Inertia, contagens de instalação do disco, trio + contrato + Pest | #6608 |

Ordem final da barra = a da âncora: Painel · Conversa · Alertas · Ações · Memória · [Plataforma].

## Método (o que foi medido, não suposto)

- **Âncora + D0.** `ancora.mjs Jana/Index` → `jana-merge.jsx`; a tela de cada aba é `jana-telas-novas.jsx` (§`JmAlertas`/`JmAcoesFila`/`JmPlataforma`), declarada como `related_prototype` dos charters novos. Os símbolos são do Painel/telas-novas — não do cockpit de cobrança que enganou a sessão de 08-28.
- **Fonte provada.** `jana-merge.jsx` estava STALE — re-exportado pela máquina antes de comparar. `jana-telas-novas.jsx` veio **inline** pelo `get_file` (616 linhas nos dois lados): sem veredito mecânico de hash, porque o transporte pontual não persiste arquivo pequeno (§5 2026-08-14/27) — declarado nos PRs.
- **O que a âncora desenha e o servidor NÃO honra ficou fora, com a medição no charter:** silenciar por meta (localStorage; o `avaliar()` seguiria notificando), "Perguntar por que caiu" (`novaConversa` não aceita pergunta), drawer/aviso de config de alertas (US-COPI-061), alerta "gate não separa dono de superadmin" (caducou no #6421), bullets "roda como job / CT 100" (falsos no Hostinger).
- **Gates rodados localmente em cada PR:** contrato-de-tela (tela + painel), casos-gate, pt-conformance, anchor-content-check, charter-refs, charter-us-lint, memory-schemas, eslint, tsc (só erros pré-existentes). Pest **não** local (ADR 0062).

## O que NÃO foi feito, e por quê

- **Card Cheques × `metodos`** — não construído. Já medido em 2026-08-31 (`Index.casos.md` §Pendência do UC-JPAIN-18): não existe fonte (`cheque` é só valor de enum; o ciclo mora no Delphi `FINANCEIRO_CHEQUE`, migração adiada por [W]), e a instrução [W] era *"se a fonte não existir, NÃO invente"*. Destravar = decisão [W] sobre migrar a tabela. Informado uma vez; não re-litigado.
- **Contador `n` nas abas** — backend (afeta as 4 telas), fora do pedido.
- **Tamanho dos PRs**: 986 / 736 / 735 linhas, acima das 300 da `commit-discipline` — o trio/contrato/teste que os gates exigem no MESMO PR responde por ~metade; registrado no corpo de cada PR.
- **Smoke pós-merge (R1)** — depende do merge [W]; a receita está em cada RUNBOOK (`RUNBOOK-alertas/acoes/plataforma.md` §2).

## O que o CI pegou depois de abrir (e os gates locais não) — 22:00–23:10 BRT

| gate | o quê | conserto |
|---|---|---|
| `layout-primitives-guard` (ADR 0253) | flex/grid solto por arquivo: Alertas 1 · Ações 5 · Plataforma 4 | `Stack`/`Inline`/`Grid` no lugar (estrutura e copy idênticas) |
| `module-surface --check` + `deadlink-gate` | `SUPERFICIE.md` é GERADO e ainda apontava pros 2 Blade apagados; 2 docs vivos linkavam o `superadmin/metas.blade.php` | `module-surface Jana --write` em cada branch; links viraram texto datado (§5 2026-08-12) |
| `dup-detector` (advisory) | ledger de frescor também tocado pelo #6596 e pelo #6607 stacked | `Dedup-ack` no corpo de #6600/#6607 |
| `crons de governança vivos?` | herdado do `main` (RAGAS parado, 2 artefatos velhos) — não-required | nada; já registrado no handoff de 01/09 |

Lição barata pra próxima tela nova: rodar **`layout-primitives-guard` e `module-surface <Mod> --check`** no pré-commit — os dois ficaram fora da minha bateria local e custaram uma volta de CI cada. Os PRs stacked foram reconstruídos por **merge** (o hook `block-destructive` barra `--force-with-lease`, corretamente).

## Estado MCP

⚠️ **Não consultado** — `brief-fetch` caiu em fallback por timeout no início e não voltou. Derivado de git/gh.
