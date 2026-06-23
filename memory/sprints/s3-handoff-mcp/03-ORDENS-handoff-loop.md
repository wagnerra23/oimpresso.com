# ORDENS — Loop de Handoff Zero-Paste (regras claras) · 2026-06-17

> Autorizado por [W] em 2026-06-17: **"eu quero sair do meio."** Estas são as regras duras pra o
> handoff Cowork→Code funcionar **sem [W] no transporte nem na revisão por-handoff**, sem virar
> risco. Baseado no ADR handoff-v2 + spec v2 (`01`) + adversário `[AH]` (`02`).

## A invariante (a regra-mãe)
**Nenhuma mudança de UI entra no `main` sem: (1) assinatura válida, (2) diff dentro do escopo
declarado, (3) os três gates verdes (conformance + critique≥80 + a11y AA).** Os três são
automáticos. Falhou um → não mergeia, alerta no inbox `ops`. **Não há exceção por pressa.**

## Quem faz o quê — UMA VEZ vs TODO DIA
| Papel | UMA VEZ (autoridade) | TODO DIA (trabalho) |
|---|---|---|
| **[W] Wagner** | guarda a **chave de assinatura** (SECRET); define os **limiares** dos gates; aprova mudança de regra | **nada.** Sai do transporte e da revisão por-handoff. |
| **[CC] Cowork** | — | produz handoff **auditado contra o main** + em tokens do repo; o pipeline **assina**; grava em `prototipo-ui/handoffs/` |
| **[CL] Code** | implementa as tools/Actions uma vez | puxa via `handoff-pending`, aplica **só no escopo**, abre PR, dá `handoff-ack` |
| **Sistema (CI/MCP)** | — | valida assinatura + escopo + 3 gates; auto-mergeia se verde; alerta se não |

## O loop, passo a passo (sem humano no meio)
1. [CC] escreve `prototipo-ui/handoffs/<slug>.md` (auditado, tokens do repo) → pipeline **assina** (`sig`).
2. Sync/Action grava no repo → `handoff:ingest` valida `sig` e cria registro `pending`.
3. [CL] chama `mcp__oimpresso__handoff-pending` → recebe metadados (+ `stale_warning`/`conflicts_with`).
4. [CL] puxa o corpo por `slug`, **reaudita contra o main**, aplica **só em `files_json`**, abre PR.
5. CI roda **scope-guard + conformance + critique + a11y**.
6. **Todos verdes →** auto-merge. **Qualquer vermelho →** PR aberto + alerta `ops`.
7. [CL] dá `handoff-ack` (`applied`+`pr_url`+`gate_status`, ou `rejected`+`note`). Loop fecha.

## Proibições (duro)
- ❌ **Auto-merge sem os 3 gates verdes.** Nunca.
- ❌ **PR de handoff tocando arquivo fora de `files_json`.** scope-guard reprova.
- ❌ **`ingest` aceitar handoff sem `sig` válida.** Rejeita e loga.
- ❌ **SECRET no Cowork ou no Code.** Vive só no pipeline de export e no servidor MCP.
- ❌ **`Cache::flush()` global.** Só `forget` cirúrgico.
- ❌ **`ack=applied` sem `gate_status` verde.** 422.
- ❌ **Deletar handoff.** Revisão = nova `version`; anterior vira `superseded` (append-only, ADR 0003).
- ❌ **[CC] entregar `.om-*` cru pro Code.** Só tokens/Tailwind do repo, auditado contra o main (R1).
- ❌ **[CC] escrever no git / numerar ADR.** Propõe; [W] decide; [CL] numera.

## O que [W] faz UMA VEZ pra ligar (checklist)
- [ ] Gerar o SECRET de assinatura; pôr em `config/teammcp.handoff_secret` (env do servidor) e no secret do pipeline de export do Cowork. **Não** colar em lugar nenhum versionado.
- [ ] Definir limiares (já default: conformance ratchet, critique ≥80, a11y AA). Mudar só se quiser.
- [ ] Emitir token MCP com scope `handoff.pending` + `handoff.ack` pro ator-Code (via TeamMcp `McpTokenIssuer`).
- [ ] Autorizar [CL] a implementar a spec v2 (`01`) — ver prompt em `prototipo-ui-patch/PROMPT_PARA_CODE_HANDOFF-LOOP-V2.md`.
- [ ] (Norte) Decidir o sync Cowork→repo: cron que busca o export e grava em `prototipo-ui/handoffs/`.

## Residual honesto (o que [W] NÃO controla mais — de propósito)
- [W] não vê cada handoff antes. A segurança passou pro **sistema** (assinatura+escopo+gates).
- Se um gate estiver mal calibrado, passa coisa ruim verde. Por isso **o limiar é autoridade de [W]** — calibrar o gate é o novo ponto de controle, não revisar PR.
- Reverter é via git (PR revert), não bloqueio prévio. Append-only: nada some.

> Em uma frase: **[W] deixa de revisar entregas e passa a calibrar gates.** De operário do loop a dono das regras.
