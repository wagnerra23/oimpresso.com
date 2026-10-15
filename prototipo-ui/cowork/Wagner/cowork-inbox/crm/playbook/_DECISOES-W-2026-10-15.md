---
sessao: "decisoes-w"
titulo: "Crm — D6: o portal do contato tem casca própria, mobile-first"
executor: "[CC]"
data: 2026-10-15
base: wagnerra23/oimpresso.com@main 93ff8a960b74
---
# _DECISOES-W-2026-10-15 — Crm

> **Fonte:** [W] 2026-10-15, formulário do Cowork (rodada única, opção recomendada escolhida). Já aplicado no `00-INDICE.md` deste playbook.

| id | pergunta | resposta | destrava |
|---|---|---|---|
| D6 | Casca do portal do contato (/contact/*): AppShellV2 + ContactSidebarMenu, ou layout próprio? | **Layout próprio do contato**: enxuto, pensado para celular, sem a sidebar do ERP | 18..24 |

## Efeito nas threads 18..24
- A Page do portal **não** usa `AppShellV2`. A primeira thread que chegar (a 18, Painel) cria a casca do contato em `${MPAGES}/Crm/Portal/_layout/`, e as outras reusam.
- O menu da casca vem do mesmo dado que o `ContactSidebarMenu` monta hoje. O middleware continua; muda só quem desenha.
- Alvos ≥ 44px e leitura sem zoom em 390px (`handoff-crm/Portal.charter.md`, UX Targets). O protótipo hoje desenha o portal dentro do shell do ERP. A forma da casca é do Cowork: **antes da 18, o Cowork redesenha `crm-portal.jsx` na casca própria** e a A3 mede depois disso.
