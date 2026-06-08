# JANA V2 — Checklist Pré-Demo Piloto (D-0 e D-2h)

> **Tipo:** Checklist pré-flight obrigatório antes de qualquer demo síncrona
> **Quando:** rodar D-1 (véspera) + D-2h (2 horas antes) + D-15min (final)
> **Responsável execução:** Wagner (com Claude pair via session)
> **Falhar 1 item D-2h:** adiar demo, NÃO improvisar
> **Refs:** [JANA-V2-DEMO-SCRIPT.md](JANA-V2-DEMO-SCRIPT.md) · [JANA-V2-MOCKUP-1PAGER.md](JANA-V2-MOCKUP-1PAGER.md)

---

## Bloco A — Ambiente técnico (D-1 véspera)

### A.1 Dados seedados no business piloto

- [ ] **Business ID alvo confirmado** — anotar `biz=<N>` (Larissa=4, ou candidato novo)
- [ ] **Transactions últimos 14 dias** — `SELECT COUNT(*) FROM transactions WHERE business_id=N AND created_at >= NOW() - INTERVAL 14 DAY` ≥ 10 vendas pra brief ter substância
- [ ] **Contacts ≥ 5 ativos** — pra recall conseguir achar cliente real
- [ ] **PII presente realista** — CPFs/CNPJs com formato correto (não `999.999.999-99` test data)
- [ ] **FSM stages com movimento** — `SELECT current_stage_id, COUNT(*) FROM transactions WHERE business_id=N GROUP BY current_stage_id` mostra distribuição em múltiplos stages
- [ ] **Asaas/Inter cobranças ativas** — pelo menos 3 cobranças com vencimento próximos 7d pra demo "boleto vencendo"

### A.2 Configs ambiente

- [ ] **OPENAI_API_KEY válida** — `php artisan tinker --execute="echo config('ai.providers.openai.api_key') ? 'OK' : 'MISSING'"` retorna OK (Vaultwarden tem chave)
- [ ] **Tier modelo configurado** — `JANA_BRIEF_MODEL=gpt-4o-mini` (cost-optimized) + fallback `gpt-4o` se quota stress
- [ ] **Meilisearch indexado** — `curl -s http://meilisearch:7700/indexes/copiloto_memoria/stats | jq .numberOfDocuments` ≥ 100 docs do business
- [ ] **MCP server CT 100 up** — `curl -s https://mcp.oimpresso.com/health` retorna 200 + token válido
- [ ] **Centrifugo broadcast OK** — chat stream usa Centrifugo, validar via `wscat -c wss://centrifugo.oimpresso.com/connection/websocket`

### A.3 Health checks daily passando

- [ ] `php artisan jana:health-check --business=N` 5 checks PASS:
  - [ ] `multi_tenant_isolation` 0 vazamentos
  - [ ] `brief_uptime_24h` 100%
  - [ ] `custo_brain_b_24h` dentro do budget
  - [ ] `pii_leak_in_assistant_responses` 0 incidents
  - [ ] `profile_distiller_drift` sem mudança não-aprovada

---

## Bloco B — Conteúdo demo (D-1 véspera)

### B.1 Brief diário do dia da demo

- [ ] **Brief D-1 já gerado** — schedule daily 06:00 BRT — validar `SELECT * FROM mcp_briefs WHERE business_id=N ORDER BY created_at DESC LIMIT 1` tem entrada do dia anterior
- [ ] **Narrativa coerente** — Wagner LÊ o brief D-1 inteiro, garante que não tem alucinação ("Vendemos R$ 50k" quando vendeu R$ 5k)
- [ ] **Sem PII vazada** — busca string `\d{3}\.\d{3}\.\d{3}-\d{2}` no brief — deve estar redacted

### B.2 Memória recall pré-seedada

- [ ] **Fato semente plantado** — inserir 1-2 fatos via chat 24h antes pra demo "lembra daquela cliente?":
  - Exemplo: digitar "Maria Souza CPF [REDACTED] reclamou entrega atrasada 3 dias, aceitou 10% desconto próximo pedido"
  - Jana grava em `copiloto_memoria_facts` via `MemoriaContrato`
  - Validar recall funcionando: D-1 perguntar "Lembra da Maria Souza?" e confirmar resposta com fato
- [ ] **Histórico chat mínimo** — pelo menos 5-10 mensagens prévias na thread biz=N (recall RRF precisa de contexto)

### B.3 FSM smoke

- [ ] **Venda demo identificada** — anotar `transaction_id=X` em stage `producao_iniciada` há ≥3 dias (pra Jana detectar "parada há 5 dias")
- [ ] **FSM history visível** — drawer `SaleSheet` abre, timeline aparece, botões `FsmActionPanel` renderizam

---

## Bloco C — Smoke browser MCP (D-2h)

Browser MCP smoke obrigatório pré-demo — capturar screenshots pra evidência caso falha.

- [ ] **Login piloto biz=N** — `mcp__Claude_in_Chrome__navigate` → `https://oimpresso.com/login` + login + esperar redirect `/copiloto`
- [ ] **Brief renderiza < 2s** — `mcp__Claude_in_Chrome__read_page` captura HTML do BriefCard + valida 4 parágrafos presentes
- [ ] **Chat envia + recebe** — type "teste" no composer + esperar resposta + validar metadata footer com modelo/custo
- [ ] **Sidebar memória abre** — click no toggle + 352+ docs aparecem com preview
- [ ] **Cockpit/Governança backup** — abrir `/copiloto/admin/governanca` + `/copiloto/admin/memoria` + screenshot pra fallback
- [ ] **Screenshot todas telas** — salvar 5 PNGs em `memory/sessions/2026-05-16-demo-smoke-<cliente>/` (evidência caso demo falhe)

---

## Bloco D — Demo room (D-15min)

- [ ] **Browser fechado e reaberto** — sessão limpa, cache fresco
- [ ] **DevTools FECHADO** — F12 desligado, não abrir na frente do cliente
- [ ] **2ª tela com checklist + script** — Wagner consulta sem cliente ver
- [ ] **Internet estável** — speedtest ≥ 20Mbps download (Jana stream depende de SSE estável)
- [ ] **Backup plan na cabeça** — Wagner sabe os 3 fallbacks (governança, memória, replay) se chat travar
- [ ] **Notificações desktop OFF** — modo "Não perturbe" (Slack, WhatsApp, email não invadem screen-share)
- [ ] **Áudio testado** — mic + speaker funcionando, sem eco
- [ ] **Gravação consentida ON** — se cliente autorizar, gravar pra session log

---

## Bloco E — Pós-demo (imediato)

- [ ] **Session log criado** — `memory/sessions/2026-05-16-demo-pilot-<cliente>.md` com:
  - O que mostrou (vs script)
  - O que cliente perguntou
  - O que cliente curtiu (sinais de interesse explícitos)
  - O que cliente objetou (sinais de hesitação)
  - Próximos passos acordados (data + responsável)
- [ ] **Task follow-up MCP** — `tasks-create module:Jana title:"Follow-up demo <cliente>" assignee:Wagner due:+2d priority:P1`
- [ ] **Update BRIEFING.md** seção "Cliente piloto" se canary aceito
- [ ] **Métricas demo** — anotar duração real (alvo 15min), # interrupções, # fallbacks usados

---

## Critério de adiar demo (qualquer 1 disparar)

- ⛔ A.1 falhou (sem dados seedados) — re-seed leva 1-2h
- ⛔ A.2 OPENAI_API_KEY MISSING — sem chave, sem demo
- ⛔ A.3 `multi_tenant_isolation` ≠ 0 — bloqueador Tier 0, NÃO adiar 1 dia, adiar até root cause achado
- ⛔ B.1 brief D-1 alucinando — re-rodar `php artisan jana:brief:generate --business=N` + validar manual
- ⛔ C smoke falhar ≥2 telas — sinal infra instável, adiar 24-48h

---

## Aprovação final Wagner (D-15min)

- [ ] **Wagner assinou GO** verbalmente ou em comentário Asana/MCP task
- [ ] Se Wagner ausente: **NÃO rodar demo sem GO explícito** — adiar
