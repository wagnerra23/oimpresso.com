---
id: requisitos-officeimpresso-licencas-index-visual-comparison
title: "Comparação design × produção — Officeimpresso/Licencas/Index"
module: Officeimpresso
tela: Officeimpresso/Licencas/Index
owner: W
status: rascunho
last_validated: "2026-11-18"
---

# Comparação design × produção — `Officeimpresso/Licencas/Index` (Licenças de computador)

> **Âncora computada** (`node scripts/design/ancora.mjs Officeimpresso/Licencas --staging prototipo-ui/cowork`):
> `prototipo-ui/cowork/Wagner/officeimpresso-page.jsx`, declarada em `related_prototype` do charter.
> Medido em **2026-11-18** (auditoria do módulo 2026-11-05, lote 6). Snapshots e veredito em [`governance/design/targets/medidas/Officeimpresso--Licencas--Index/`](../../../governance/design/targets/medidas/Officeimpresso--Licencas--Index/).

## Veredito: **DIVERGE** na estrutura — o detalhe por coluna não é interpretável

O dono contou 7 itens `bug`. Só os agregados abaixo comparam a mesma coisa nos dois lados.

D0: **OK** pelo contrato `governance/design/contracts/officeimpresso-licencas.contract.json` — com uma
máquina na lista, as copies da grade casaram nos dois lados. (Sem máquina, a 1ª rodada saiu NÃO MEDI:
o estado vazio da produção não tem as copies da grade.)

| Dim | Campo | Produção | Protótipo | Veredito |
|---|---|---|---|---|
| D2 | layout | ok | ok | IGUAL |
| D2 | nº de colunas | 6 | 8 | DIVERGE |
| D2 | table-layout | auto | fixed | DIVERGE — o protótipo declara as larguras |
| D4 | tipografia geral | ok | ok | IGUAL |
| D8 | KPI align | ok | ok | IGUAL |

**As colunas estão deslocadas.** O protótipo começa pela coluna de seleção em massa (checkbox), que é
Non-Goal declarado no charter (BulkBar); e o usuário de medida não era superadmin, então a produção
escondeu a coluna Empresa (UC-OILIC-04). Produção: Computador · Versão instalada · Último acesso · Validade ·
Status · ações. Protótipo: seleção · Computador · Empresa · Versão instalada · IP · usuário · Último acesso ·
Status · ações. Os itens "col0…col5" do relatório comparam colunas diferentes e **não foram usados como
achado**. Medir de novo como superadmin e com papéis de coluna por cabeçalho tornaria o detalhe legível.

## Como foi medido

- **Sonda:** `scripts/design/design-diff-lote.mjs` (dono do lote), que injeta a sonda canônica
  `design-diff.mjs --probe` nos dois lados e compara com `--compare prod.json design.json --check`.
  Mesmo Chromium (Playwright 1.61), tema claro nos dois lados (`--tema light`), viewport do driver.
- **Protótipo:** espelho `prototipo-ui/cowork/Wagner/oimpresso.com.html` servido localmente, rota do shell
  fixada por override em `governance/design/targets/roles/Officeimpresso--Licencas--Index.json` (token `oi-licencas`). As 6 telas do
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
