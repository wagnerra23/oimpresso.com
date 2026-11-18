---
id: requisitos-officeimpresso-logs-index-visual-comparison
title: "Comparação design × produção — Officeimpresso/Logs/Index"
module: Officeimpresso
tela: Officeimpresso/Logs/Index
owner: W
status: rascunho
last_validated: "2026-11-18"
---

# Comparação design × produção — `Officeimpresso/Logs/Index` (Máquinas Cadastradas)

> **Âncora computada** (`node scripts/design/ancora.mjs Officeimpresso/Logs --staging prototipo-ui/cowork`):
> `prototipo-ui/cowork/Wagner/officeimpresso-page.jsx`, declarada em `related_prototype` do charter.
> Medido em **2026-11-18** (auditoria do módulo 2026-11-05, lote 6). Snapshots e veredito em [`governance/design/targets/medidas/Officeimpresso--Logs--Index/`](../../../governance/design/targets/medidas/Officeimpresso--Logs--Index/).

## Veredito: **DIVERGE** — 8 itens `bug` pelo dono; **substitui a medida de 2026-09-18**

A medida anterior desta pasta foi tirada contra a vista **empresas** (`route=officeimpresso`) e é inválida.
Esta usa o token `oi-log` (vista `ViewLog`, "Máquinas Cadastradas"), override de 2026-10-05.

D0: sem contrato de tela — identidade amarrada pelo token.

| Dim | Campo | Produção | Protótipo | Veredito |
|---|---|---|---|---|
| D2 | nº de KPIs | 1 | 4 | DIVERGE (bug) — **não confirmado**: o código renderiza 4 `KpiCard`; o seletor padrão de KPI do driver casou 1. Provável falha do seletor ou do `<Deferred>` no instante da sonda, não da tela |
| D2 | table-layout | auto | fixed | DIVERGE (bug) |
| D2 | colunas com largura declarada | 0 | 10 | DIVERGE (bug) |
| D4 | col1 mono | sem mono | mono | DIVERGE (bug) |
| D6 | col4–col6 cor | herda a da linha | cor própria | DIVERGE (bug) × 3 — **cor não confiável** |
| D6 | col7 / col8 pílula com ponto | com ponto | sem ponto | DIVERGE (fonte) |
| D8 | col0 / col2 / col3 elemento | link | botão | DIVERGE (impl) — os dois levam ao destino |
| D8 | col9 alcançável | texto | botão | DIVERGE (bug) — o protótipo tem a ação na linha |
| D4 / D6 / D8 | gerais | ok | ok | IGUAL |

## Como foi medido

- **Sonda:** `scripts/design/design-diff-lote.mjs` (dono do lote), que injeta a sonda canônica
  `design-diff.mjs --probe` nos dois lados e compara com `--compare prod.json design.json --check`.
  Mesmo Chromium (Playwright 1.61), tema claro nos dois lados (`--tema light`), viewport do driver.
- **Protótipo:** espelho `prototipo-ui/cowork/Wagner/oimpresso.com.html` servido localmente, rota do shell
  fixada por override em `governance/design/targets/roles/Officeimpresso--Logs--Index.json` (token `oi-log`). As 6 telas do
  módulo apontam para o mesmo `officeimpresso-page.jsx`; sem o override a derivação dava `route=officeimpresso`
  (a vista **empresas**) para todas — foi o erro da medida de Logs de 2026-09-18.
- **Produção:** `https://staging.oimpresso.com` (dado fictício de CI: CI Biz, tenant 98), logado pela rota
  env-guarded `/_visreg-login` com o token do `.env` do staging. Ninguém usou senha.
  - Checkout do staging no momento: `5bb4719199`, branch `claude/fisio-us063-portal`, 7 arquivos sujos.
    A base comum com o `main` é `2c3fbfadfe` (2026-11-11). **Nenhum arquivo de `Modules/Officeimpresso/**`
    mudou entre essa base e o `main` `ec57208855`**; mudaram componentes compartilhados (`cockpit/Sidebar.tsx`,
    `shared/DataTable.tsx`, `shared/StatusBadge.tsx`, `ui/checkbox.tsx`, `ui/select.tsx`). Uma medida feita
    no `main` pode diferir nesses pontos.
  - O usuário de CI do business operador (id 1) levava **403** em todas as rotas do módulo (a guarda
    `AcessoOperador` exige `officeimpresso.access`, que nem existia no staging). A 1ª rodada mediu a página
    403 e o driver a rotulou "DIVERGE (bug)" — **descartada**. A rodada que vale usou um usuário de medida
    criado só para isso no business 1, com `officeimpresso.access` e `officeimpresso.clientes.liberar` diretos.
  - Para a tela ter linha, foram gravadas temporariamente duas máquinas fictícias (business 98 e 1), um
    acesso, e o business 98 marcado como licenciado. **Tudo apagado ao fim** (conferido: 0 máquinas, 0 logs,
    business 98 `is_officeimpresso=0`, usuário e permissão removidos).
- **Saúde do lado prod (vale para todas):** a sonda acusou `tokens.prod` **NÃO MEDI** — `--accent --pos --neg
  --warn --text-dim --sunken --border` não resolvem na raiz da produção. **A dimensão cor (D6) não é
  confiável**: os vereditos de cor abaixo são registrados, não usados como conclusão.
- Screenshot nenhum: comparação por medida, não por olho.
