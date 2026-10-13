---
data: 2026-10-13
hora: "16:56 BRT"
autor: "[CL]"
tema: "Threads sistema/06 (Contas), Essenciais A1+03, Cliente A1/01/02/04 e conserto do Testar envio"
---
# Handoff 2026-10-13 16:56 — Contas, Essenciais, Cliente e "Testar envio"

## Entregue (9 PRs, todos mergeados em 13/10)
| PR | O quê |
|---|---|
| #9167 | sistema/06 — Contas (`AccountController` → `Configuracoes/Contas/Index`, flag `useV2ConfiguracoesContas` default OFF, `?legacy=1`), trio + Baseline/Contrato tests, RUNBOOK-contas + contas-parity |
| #9171 | `_saida-06` do sistema (recibo) |
| #9214 | Essenciais A1 — alvos/secoes/contratos das 5 telas (Documentos, Memorandos, Lembretes, Conhecimento, Tarefas) |
| #9225 | Essenciais 03 — só âncoras de contrato nas Pages (precedente Estoque) + RUNBOOK-documents/knowledge |
| #9227 | Cliente A1 + 01 — alvo/contrato `cliente-index` |
| #9238 | Cliente 04 — `RedirectLegacyContacts` ligado no Kernel: `GET /contacts?type=X` → 302 `/cliente?…` (lista; Show **desligado** por decisão [W]); teste reescrito em tenant 98 e fora da quarentena |
| #9239 | recibos `_saida` do Cliente (A1/01/04) |
| #9242 | Cliente 02 — `design-diff-lote.mjs::rotaViva` deixa de usar a prosa do `page:` como URL (13 de 344 charters mudam, todos para a rota certa); selftest 81/81 |
| #9247 | "Testar envio" do Modelos de notificação não dá mais 500 com `{contact_name}`: `test()` não chama `replaceTags` sem transação; tags seguem literais. UC-NOT-22 determinístico |

## Achados (registrados, não meus para fechar)
- **Cliente/Index e User/Perfil têm medida de 2026-09-18 inválida** — o lote abriu a prosa do charter como URL. Cliente deu DIVERGE falso; **User/Perfil deu IGUAL falso** (o perigoso). Re-medir com `--incluir-comparadas` exige sessão no staging (não entrei com credencial em host remoto). Ver `_saida-02` do Cliente.
- **Vendas dark 8,6694% no visual-regression** — causado pelo #8919 (mudança intencional de tema) sem regen de baseline. ADR 0409 aposentou a regen; decisão é de [W] (gerente da fila levando).
- **Repair/02** — já entregue (24px real, corrigido por #8531); nada a fazer.
- Manufatura é território da [M]: não toquei.

## Próxima thread
Avaliei o placar e nada sobrou seguro/livre para pegar nesta sessão.

## Estado MCP no momento do fechamento
- `cycles-active`: nenhum cycle ATIVO em COPI.
- `my-work` (@wr23): sem tasks ativas.
- `sessions-recent limit:3`: 3 session logs de estado-da-arte (2026-08-22) indexados hoje — nenhum desta sessão.
- PRs desta sessão: 9/9 `MERGED` (conferido via `gh pr view`, 19:14Z o último).
