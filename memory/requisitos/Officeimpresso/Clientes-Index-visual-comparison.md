---
id: requisitos-officeimpresso-clientes-index-visual-comparison
title: "Comparação design × produção — Officeimpresso/Clientes/Index"
module: Officeimpresso
tela: Officeimpresso/Clientes/Index
owner: W
status: rascunho
last_validated: "2026-11-18"
---

# Comparação design × produção — `Officeimpresso/Clientes/Index` (Clientes OAuth)

> **Âncora computada** (`node scripts/design/ancora.mjs Officeimpresso/Clientes --staging prototipo-ui/cowork`):
> `prototipo-ui/cowork/Wagner/officeimpresso-page.jsx`, declarada em `related_prototype` do charter.
> Medido em **2026-11-18** (auditoria do módulo 2026-11-05, lote 6). Sem snapshot gravado (motivo abaixo).

## Veredito: **NÃO MEDIDO** (a tela React)

A rota `/officeimpresso/client` no staging serve a **Blade** `officeimpresso::clients.index`, não a
`Clientes/Index.tsx`: a assinatura medida do lado prod é o layout AdminLTE ("Signed in as…", chaves de
tradução cruas `officeimpresso::lang.*`). A tela React fica atrás da flag `useV2OfficeimpressoClientes`,
que **não está em `fallbackDefaults`** e não estava ligada no staging (UC-OICLI-01 descreve exatamente isso).

Comparar a Blade com o protótipo não diz nada sobre o `.tsx`, então a medida **não foi gravada** em
`governance/design/targets/medidas/`. O override de vista (`oi-clientes`) fica pronto para a próxima rodada.

## Para medir

Ligar `useV2OfficeimpressoClientes` no ambiente de medida (env de override de flag do `FeatureFlagService`
no staging, ou regra no GrowthBook) e re-rodar
`node scripts/design/design-diff-lote.mjs --tela Officeimpresso/Clientes/Index --base-url https://staging.oimpresso.com --user-id <usuário com officeimpresso.clientes.liberar no business operador>`.

## Como foi medido

- **Sonda:** `scripts/design/design-diff-lote.mjs` (dono do lote), que injeta a sonda canônica
  `design-diff.mjs --probe` nos dois lados e compara com `--compare prod.json design.json --check`.
  Mesmo Chromium (Playwright 1.61), tema claro nos dois lados (`--tema light`), viewport do driver.
- **Protótipo:** espelho `prototipo-ui/cowork/Wagner/oimpresso.com.html` servido localmente, rota do shell
  fixada por override em `governance/design/targets/roles/Officeimpresso--Clientes--Index.json` (token `oi-clientes`). As 6 telas do
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
