# Import — bundle handoff Cowork 2026-05-19

> Snapshot recebido em 2026-05-19 12:39 BRT do Cowork (api.anthropic.com/v1/design/h/yFfLCLE1I2L7tPKK7sW48g) via solicitação Wagner "Fetch this design file, read its readme, and implement the relevant aspects of the design. Implement: Oimpresso ERP - Chat.html".

## Origem

URL Anthropic Cowork Design retornou tar.gz 8.8MB → 17.9MB descompactado / 663 arquivos. Project: `oimpresso-erp-conunica-o-visual`.

## Status do trabalho prévio

**O trabalho deste bundle JÁ FOI APLICADO** via PR [#1119](https://github.com/wagnerra23/oimpresso.com/pull/1119) — `feat(prototipo-ui): casa organizada · CSS escopado · KB-9.75 v4` (mergeado 2026-05-19T10:35 BRT, antes desta sessão).

Evidências:
- `project/PROMPT_v4_CASA_ORGANIZADA.md` é literalmente o **script bash que gerou o PR #1119** (cria backup → apaga legacy → baixa novos arquivos de `vendas-financeiro-completo` patch)
- `prototipo-ui-backup-20260518-211214/` na raiz do repo (untracked) é o backup que esse script criou antes de aplicar — preserved para rollback se necessário
- Commit `105a507f1` em main contém os arquivos que o script baixou

## O que este snapshot preserva

**Histórico de processo do Cowork** que não foi sincronizado pro repo principal (não eram alvo do PR #1119, eram artefatos de iteração Cowork ↔ Code):

| Arquivo | O que é |
|---|---|
| `project/CLAUDE.md.proposto` | Versão proposta de CLAUDE.md secundário (não aplicada) |
| `project/FORCE_OVERWRITE_V3_PARA_CODE.md` | Prompt iteração 3 forçando overwrite |
| `project/GAPS_FINANCEIRO_PRA_CODE.md` | Gaps Financeiro pra Code |
| `project/GAPS_v2_FINANCEIRO_PRA_CODE.md` | Gaps Financeiro v2 |
| `project/HANDOFF_FINANCEIRO.md` | Handoff Financeiro Cowork → Code |
| `project/HANDOFF_PRODUTO_F1.md` | Handoff Produto F1 |
| `project/MEMORIA_F3_ZEROTOUCH.md` | Padrão zero-touch validado 2026-05-09 |
| `project/PLANO_ORGANIZACAO_CASA.md` | Plano organização "casa" |
| `project/PROMPT_*.md` (5 arquivos) | Prompts iteração v1 → v4 Casa Organizada |
| `project/COWORK_RESPONSE_PR295.md` | Resposta Cowork ao PR #295 (v1 protocolo) |
| `chats/chat1.md` → `chats/chat12.md` | 12 transcripts Cowork ↔ Wagner |

Os chats markdown são especialmente valiosos como **referência de intenção original** (o README do bundle Anthropic diz literalmente "Read the chat transcripts first... the chat is where the intent lives").

## Como usar este snapshot

- **NÃO** é fonte canônica pra rodar protótipo — use `prototipo-ui/Oimpresso ERP - Chat.html` (raiz) que reflete estado pós-PR #1119
- **É** referência histórica pra entender decisões — abrir os chats markdown quando precisar contexto de "por que essa interação foi assim"
- Próximas atualizações Cowork → repo NÃO devem editar este snapshot (append-only, igual `_cowork-export-2026-05-15/`)

## Comparado com `_cowork-export-2026-05-15/`

Aquele snapshot anterior (15/maio) tem 14 arquivos. Este (19/maio) tem 663 arquivos pois inclui o `project/` completo + 12 chats. Razão: o bundle é o **handoff oficial Anthropic** com pattern de export bem mais completo que o snapshot manual de 15/maio.

## Cross-references

- ADR canônica do loop Cowork: [`memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md`](../../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- ADR canônica do plugin Claude Design: [`memory/decisions/0109-claude-design-plugin-integrado-processo-mwart.md`](../../memory/decisions/0109-claude-design-plugin-integrado-processo-mwart.md)
- Status apple-to-apple PR #1119 (commit `105a507f1` em main)

---
**Criado:** 2026-05-19 12:39 BRT · sessão Claude Code (cópia literal do bundle Anthropic Cowork)
