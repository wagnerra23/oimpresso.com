---
thread: "00"
titulo: PUXAR Superadmin — 8 Pages vivas → protótipo
dono: "[CC]"
base: wagnerra23/oimpresso.com@main ee1ca5f93e98 (lido 2026-10-01 14:20 UTC)
veredito: "entregue — 8 Pages com rota sa-* no protótipo (Usuário 360° nova); Assinaturas puxou KPI e ações de produção; 6 divergências declaradas pra [W]."
---

# _saida-00 · Superadmin

## Mapa rota do protótipo ↔ Page do `main` (para a A1 medir cada uma contra a sua)

| rota | Page | contrato |
|---|---|---|
| `superadmin` | `superadmin/Dashboard/Index` | `superadmin-dashboard` |
| `sa-negocios` | `superadmin/Negocios/Index` | `superadmin-negocios` |
| `sa-assinaturas` | `superadmin/Assinaturas/Index` | `superadmin-assinaturas` |
| `sa-pacotes` | `superadmin/Pacotes/Index` | `superadmin-pacotes` |
| `sa-comunicador` | `superadmin/Comunicador/Index` | — |
| `sa-config` | `superadmin/Configuracoes/Index` | — |
| **`sa-usuarios`** (nova) | `superadmin/Usuario360/Index` + `Show` (no drawer) | — |

`Site/Pricing` (PricingController) é do site, não entra aqui.

## O que entrou no build

1. **`superadmin-usuarios.jsx`** (novo, `window.SuperadminUsuariosPage`) + rota `sa-usuarios` em `app.jsx` + item "Usuário 360°" em `data.jsx` + `<script>` lazy no host.
   - Lista igual à de produção: busca com espera de 300 ms, sem lista antes de buscar ("Comece uma busca"), sem resultado com o termo, esqueleto durante a busca, 10 por página, colunas ID · Nome · E-mail · Username · Negócio · Situação · Tipo · Ver 360°.
   - 360° com os **9 blocos** do `Show.tsx`: identidade + risco, papéis, permissões efetivas por módulo com risco, scopes ADS/MCP, tokens MCP mascarados, quota do Copiloto, sessões, auditoria, histórico de trancamentos.
   - Trancar exige motivo (≥5 caracteres) e diz o efeito (sessão cai, tokens revogados). Destrancar diz que **os tokens não voltam** (UC-SAUX-04 e 05).
2. **Assinaturas** (`superadmin-page.jsx`):
   - KPI "Aprovadas" → **"Ativas"** (o contrato explica: `approved` vencida não é ativa).
   - Gaveta de status: de 5 status num select para as **3 ações** de produção — Aprovar · Marcar como vencida · Cancelar —, cada uma com a frase do efeito antes de aplicar. O botão diz a ação escolhida.

## Só o protótipo tem — [W] decide (não virou pedido)

| # | tela | o quê | produção |
|---|---|---|---|
| 1 | Usuário 360° | 360° em drawer (PT-02) | página cheia `/superadmin/usuarios/{id}/360` |
| 2 | Dashboard | funil trial→pago, churn com motivos, receita por pacote, fila "vencendo", "o que fazer primeiro" | não tem (contrato trava só 4 de 10 seções) |
| 3 | Negócios | seleção múltipla + BulkBar | não tem (sem backend) |
| 4 | Negócios · Assinaturas | 6 por página | 20 por página |
| 5 | Pacotes | form novo/editar/duplicar | não tem (escreve `price` — regra mestre) |
| 6 | Assinaturas | seleção múltipla | não tem |

## Não fiz
- Não li `Comunicador/Index.tsx` nem `Configuracoes/Index.tsx` linha a linha: o `_saida-05` (Code) já registra as divergências dessas duas (enviar teste, % de abertura, liga/desliga por gateway sem backend).
- Não medi nada: medir é a A1.

## Descobertas
- O `superadmin-page.jsx` tem 1.421 linhas; a tela nova foi para arquivo próprio para não crescer mais.
