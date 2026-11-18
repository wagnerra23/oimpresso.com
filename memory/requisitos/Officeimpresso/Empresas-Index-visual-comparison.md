---
id: requisitos-officeimpresso-empresas-index-visual-comparison
title: "Comparação design × produção — Officeimpresso/Empresas/Index"
module: Officeimpresso
tela: Officeimpresso/Empresas/Index
owner: W
status: rascunho
last_validated: "2026-11-18"
---

# Comparação design × produção — `Officeimpresso/Empresas/Index` (Empresas licenciadas)

> **Âncora computada** (`node scripts/design/ancora.mjs Officeimpresso/Empresas --staging prototipo-ui/cowork`):
> `prototipo-ui/cowork/Wagner/officeimpresso-page.jsx`, declarada em `related_prototype` do charter.
> Medido em **2026-11-18** (auditoria do módulo 2026-11-05, lote 6). Snapshots e veredito em [`governance/design/targets/medidas/Officeimpresso--Empresas--Index/`](../../../governance/design/targets/medidas/Officeimpresso--Empresas--Index/).

## Veredito: **DIVERGE** na estrutura — o detalhe por coluna não é interpretável

O dono contou 10 itens `bug`. Só os agregados abaixo comparam a mesma coisa nos dois lados.

D0: sem contrato de tela para Empresas — a identidade da vista **não** está provada por copy; o que
amarra os dois lados é o token `officeimpresso` (vista `ViewEmpresas`, a única do protótipo para esta lista).

| Dim | Campo | Produção | Protótipo | Veredito |
|---|---|---|---|---|
| D2 | nº de KPIs | 3 | 4 | DIVERGE — confere com o código: falta "Versão obrigatória atrás" |
| D2 | nº de colunas | 7 | 6 | DIVERGE |
| D2 | table-layout | auto | fixed | DIVERGE — o protótipo declara as larguras |
| D4 | tipografia geral | ok | ok | IGUAL |
| D8 | KPI align | ok | ok | IGUAL |

**Colunas são outras, não só outra ordem.** Produção: Empresa · CNPJ · Versão disponível · Versão obrigatória ·
Último acesso · Status · ações. Protótipo: Empresa · Versão obrigatória → disponível · Máquinas · Banco no
servidor · Último handshake · ações. Os itens "col1…col5" do relatório (cor, mono, pílula, alcançável,
alinhamento) comparam colunas de conteúdo diferente e **não foram usados como achado**. O veredito por
coluna depende de antes decidir o conjunto de colunas (decisão [W] D1 de 2026-10-12, `empresas-parity.md`).

## Como foi medido

- **Sonda:** `scripts/design/design-diff-lote.mjs` (dono do lote), que injeta a sonda canônica
  `design-diff.mjs --probe` nos dois lados e compara com `--compare prod.json design.json --check`.
  Mesmo Chromium (Playwright 1.61), tema claro nos dois lados (`--tema light`), viewport do driver.
- **Protótipo:** espelho `prototipo-ui/cowork/Wagner/oimpresso.com.html` servido localmente, rota do shell
  fixada por override em `governance/design/targets/roles/Officeimpresso--Empresas--Index.json` (token `officeimpresso`). As 6 telas do
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
