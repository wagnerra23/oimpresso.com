# Sessão fria — Sistema (Configurações · Usuários · Relatórios)

> 1 thread = 1 sessão = 1 PR. [CC] 2026-10-05 (sha aacb74f4df18). **O índice manda.**

```
/onda sistema --thread NN
```

| # | o que faz | dono | ficha | depende de |
|---|---|---|---|---|
| **00** | Mapa Blade ↔ protótipo das 21 telas (cfg-*, usuarios/funcoes/comissionados, rel-*) | CC | `00-mapa.md` | — |
| **01** | Usuários (ManageUser) → Inertia | CL | `01-usuarios-manageuser-inertia.md` | 00 |
| **02** | Funções e permissões (Role) → Inertia | CL | `02-funcoes-e-permissoes-role-inertia.md` | 01 |
| **03** | Comissionados (SalesCommissionAgent) → Inertia | CL | `03-comissionados-salescommissionagent-inert.md` | 00 |
| **04** | Locais · Impressoras · Código de barras → Inertia | CL | `04-locais-impressoras-codigo-de-barras-iner.md` | 00 · D1 |
| **05** | Esquemas de fatura · Impostos · Tipos de serviço → Inertia | CL | `05-esquemas-de-fatura-impostos-tipos-de-ser.md` | 04 |
| **06** | Modelos de notificação · Contas → Inertia | CL | `06-modelos-de-notificacao-contas-inertia.md` | 05 |
| **07** | Relatórios (ReportController) → Inertia | CL | `07-relatorios.md` | 00 · D2 |
