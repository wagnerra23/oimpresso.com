---
date: "2026-10-02"
time: "11:43 BRT"
slug: conta-demo-revisores-lojas
tldr: "Conta demo da revisão das lojas no ar em produção (business 235): revisor.ponto + gestor.demo, dados fictícios de Início/Pessoas, smoke automático sem senha pela API Passport (me, espelho, marcar). A conta demo achou um vazamento de permissão na lista de clientes, fechado em 3 PRs. Falta só o login real com senha, feito pelo [W]."
decided_by: [W]
prs: [8428, 8442, 8443, 8450, 8464, 8469, 8478, 8499]
next_steps:
  - "[W]: entrar no app como revisor.ponto (senha no Vaultwarden, item ponto-demo-revisor) e chegar ao Ponto — único teste que o agente não faz (login com senha em produção)."
  - "Antes de enviar para revisão: `php artisan ponto:demo-dados` (refaz 'hoje') e `php artisan ponto:demo-smoke --sem-marcar`."
  - "Reescrever memory/requisitos/Ponto/REVISAO-LOJAS-NOTAS.md com os nomes finais das 7 áreas (D13), quando as telas existirem no app — nomes vêm da sessão APP CAPACITOR."
  - "Dados de Tarefas/Pedidos/Produção no 235: só quando as APIs dessas telas existirem. Pedidos por FSM real deixa histórico de etapas permanente (append-only) — decisão [W] ainda aberta."
---

# Conta demo para os revisores das lojas — 2026-10-02

## Estado MCP no momento do fechamento
- Servidor MCP `oimpresso` **indisponível nesta sessão** (ToolSearch sem `cycles-active`/`my-work`). Estado tirado do git e do GitHub.
- Handoffs irmãos: `2026-10-01-1530-app-lojas-coordenacao-passada` (coordenação do app) e `2026-10-01-1614-gestao-merges-sessoes-paralelas` (gestão de merge, já arquivada).
- Coordenação vigente do app: sessão "Coordenar app das lojas (oimpresso-app)"; decisões em `docs/lojas-app/DECISOES.md`.

## O que está no ar (produção, business 235 "Demo Ponto — revisão das lojas")
| Peça | Estado |
|---|---|
| `revisor.ponto` (user 1805) | papel `Revisor#235` sem permissões; cadastro de ponto DEMO-0001; escala seg–sex |
| `gestor.demo` (user 1806) | `Admin#235`, sem permissões de plataforma (ADR 0415); fora da revisão v1 |
| Dono técnico (1804) | `allow_login=0` |
| Integrações externas | nenhuma (o comando recusa se aparecer NF-e/gateway/WhatsApp) |
| Pacote "Demo lojas" | criado pelo [W] |
| Dados fictícios | Início + Pessoas (3 vendas, 3 títulos, 2 produtos abaixo do mínimo, 6 pessoas) — valores aprovados pelo [W] |
| Marcações | 2 (NSR 1 e 2), ambas de smoke; histórico de exemplo só no staging (decisão [W]) |
| Client OAuth do app | id 110, password grant, público (criado pela coordenação) |

Comandos: `ponto:demo-revisor` (contas), `ponto:demo-dados` (dados; `--dry-run`, `--limpar`), `ponto:demo-smoke` (web + API Passport em processo, sem senha; `--sem-marcar`). Último smoke em prod: tudo verde, incluindo `/ponto/api/me` (DEMO-0001), `/espelho` do mês (31 linhas) e mês futuro → 422.

## Achados que valem registro
- **Vazamento de permissão em `/contacts` (dentro do tenant):** o DEMO-03 pegou a lista de clientes abrindo para usuário sem permissão. Provado vermelho antes de consertar, em 3 PRs: #8442 (gate no ramo React), #8443 (gate antes da flag — a casca Blade expunha usuários e grupos), #8469 (`view_own` filtra os próprios).
- **Mensagem da catraca de tabela prometia saída inexistente** (`not_contains` no eixo tabela) — corrigida em #8466 por sessão separada.
- **Lane ponto-pest sem chave do Passport:** testes de `auth:api` passavam ou caíam pela ordem dos arquivos; #8499 gera a chave 1x na lane.
- **Decisão de produto mudou 5× no dia** (webview → Expo → Capacitor/ERP → `/m` → telas próprias + API). Lição já registrada no handoff da coordenação: conflito se fecha com pergunta única lado a lado ao [W].
- **Limite do agente:** não digita senha nem autentica com credencial em produção — o smoke usa token emitido em processo; o login real fica com o [W].
