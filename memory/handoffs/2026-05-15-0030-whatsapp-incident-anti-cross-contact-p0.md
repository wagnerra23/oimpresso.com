# Handoff 2026-05-15 00:30 — Whatsapp incident anti-cross-contact P0 (4 commits + estado-da-arte 38/100 catalogado)

## TL;DR

Wagner testou WhatsApp re-pareando canal Baileys 6.7.9 prod biz=1 e descobriu que **todas as 81 msgs dele caíram no contato errado** ("ELIANA MARCELINO ALVES 06075269983") via 3 falhas combinadas: (1) `ConversationContactLinker` fuzzy LIKE tail4 (4 dígitos), (2) `LidPhoneResolver::record(source=manual)` aceitando sem evidência webhook prévia, (3) `MessagePersister` não consultando resolver no path history-sync. Sessão produziu **4 commits P0 + 7 Pest regression tests + 2 session docs canon (incident + estado-da-arte 38/100)** na branch `claude/wa-anti-cross-contact-incident-p0` — pushada, PR pendente (GitHub indisponível 00:25 BRT, route 4.228.31.149 timeout). Recovery SQL **NÃO executado** (Wagner: "nunca perca mensagem") — fica como tarefa pós-merge.

## Cronologia da sessão

| Quando | O que aconteceu |
|---|---|
| 18:40 BRT 14/mai | Wagner re-pareou canal Baileys (PR #848 monitor re-pairing). Daemon disparou `messaging-history.set` → 81 msgs persistidas com `remoteJid="14628809617558@lid"` + `senderPn=null` + `is_history_sync=true` 100% |
| 18:40 | Inbox biz=1 mostrou 1 conv única id=37 atribuída ao contact_id=6005 (Eliana) — Wagner abriu screenshot questionando "como faz isso, tá pegando pelo ID correto?" |
| ~21:00 | Diagnóstico inicial Claude — confirmou threading DB correto, MAS toda mass de msgs caía em conv única; LidPhoneMap.id=1 cadastrado 12/mai linkava LID 14628809617558 → `+554899872822` (Eliana) source=manual |
| ~21:15 | Wagner revelou: as 81 msgs eram dele testando, número real `+5548999872822` (13 dígitos, não 12 do mapping) — o mapping 12/mai gravou 1 dígito faltando E fuzzy linker reforçou pelo tail4 |
| ~21:30 | Spawn agent `whatsapp-doctor` (general-purpose) — investigação completa: confirma "1 LID @lid = 1 chat 1:1, NÃO 1 pessoa" no Baileys 6.7.9 (issues #1554, #1605, #1832, #2030, #2263 catalogadas) + descobre **13 rows manuais ad-hoc 14/mai 08:40:10 sem trail git = drift Tier 0** |
| ~22:00 | Wagner enfático: "nunca perca mensagem" + "tem que cadastrar o contato do whatsapp sempre" + "aprenda com algum especialista, pesquise, avalie os melhores e compare com o nosso" |
| ~22:15 | Spawn agent `estado-da-arte` — benchmark 8 concorrentes × 12 dimensões (Intercom/Front/Take Blip/HubSpot/Twilio Conv./Zendesk SC/Octadesk/Crisp). Nota oimpresso: **38/100** |
| ~22:45 | Aplicação dos 3 patches P0 + 7 Pest regression tests + 4 commits granulares no branch `claude/wa-anti-cross-contact-incident-p0` |
| ~00:20 BRT 15/mai | Push branch OK; `gh pr create` falhou — GitHub api fora (route 4.228.31.149 timeout em 3 tentativas) |
| ~00:30 | Handoff catalogando conhecimento — Wagner: "salvar conhecimento muito util, muito bom continue" |

## PRs / Branches / Commits

**Branch:** `claude/wa-anti-cross-contact-incident-p0` (pushada, PR pendente)

**Commits (4):**
- `7170ca5` — fix(whatsapp): linker suffix-8 (anti-cross-contact) [W+C]
- `0ab9562` — fix(whatsapp): resolver bloqueia source=manual sem webhook prévio (anti-drift) [W+C]
- `12bcef9` — fix(whatsapp): persister consulta resolver no history-sync [W+C]
- `b38a662` — test(whatsapp): Pest regression P0 + docs incident + arte auto-cadastro [W+C]

**Diff total:** 79 linhas código (3 services) + 276 test + 879 docs sessions.

## Artefatos canon novos

- [`memory/sessions/2026-05-14-whatsapp-incident-inbox-lid-cross-contact.md`](../sessions/2026-05-14-whatsapp-incident-inbox-lid-cross-contact.md) — investigação inicial agent `whatsapp-doctor`, 8 seções (TL;DR / cronologia / evidências SQL / root cause / plano recovery / patch diff / Pest regression / governança drift Tier 0)
- [`memory/sessions/2026-05-14-arte-auto-cadastro-contact-whatsapp.md`](../sessions/2026-05-14-arte-auto-cadastro-contact-whatsapp.md) — estado-da-arte 2026 com 8 concorrentes × 12 dimensões, nota 38/100, top 10 gaps priorizados (impacto × esforço calibrado ADR 0106), ROI estimado pós top 3 P0, 18 source links externos
- [`Modules/Whatsapp/Tests/Feature/LidCrossContactIncidentP0Test.php`](../../Modules/Whatsapp/Tests/Feature/LidCrossContactIncidentP0Test.php) — 7 testes Pest regression P0 cobrindo as 3 defesas + Tier 0 cross-tenant

## Lições centrais (3)

### 1. "1 LID @lid ≠ 1 pessoa" em Baileys 6.7.9

Mais grave que parece. WhatsApp Multi-Device retorna `<LID>@lid` que é **ID do chat 1:1 (ambos lados)**, não da pessoa. Toda lógica que assume "1 LID = 1 phone real" produz cross-contact garantido em alguma janela. Baileys 7.x traz Alt JID nativo ([migration guide](https://baileys.wiki/docs/migration/to-v7.0.0/)) — fix definitivo só lá. Defense-in-depth no app-side via mapping `LidPhoneMap` exige rigor (webhook evidence obrigatório).

### 2. Estado-da-arte como sinal de prioridade

Quando Wagner pediu "pesquise, avalie os melhores e compare com o nosso", o agent `estado-da-arte` produziu benchmark de 8 concorrentes. Vencedores: **Twilio Conversations** (Identity Resolution proativa + exact phone match), **Intercom** (merge automático opt-in + default cria lead novo), **Zendesk SC** (incident público BR 2023 idêntico → eles oficialmente NÃO matcham por phone). Esse benchmark deu **38/100** pro oimpresso e top 10 gaps priorizados — virou roadmap canon. Pattern: dor concreta → agent pesquisa concorrentes → comparação dá nota → roadmap rastreável.

### 3. "Nunca perca mensagem" como regra Tier 0 emergente

Wagner foi enfático 2× na sessão. Recovery SQL proposto inicialmente fazia UPDATE com `phone_e164=NULL` no mapping + `contact_id=NULL` na conv 37 — Wagner cortou. Pipeline correto: **zero DELETE em `messages`/`conversations`**, só `UPDATE` curativo em metadados (mapping LID, nome contact link, mobile contact 6652). As 81 mensagens permanecem em conv 37 com FK preservada. Documentado em `proibicoes.md` candidato? Wagner avaliar.

## Pendente após esta sessão

- ⏳ **Abrir PR via `gh pr create`** — assim que GitHub voltar. Branch já pushada. Título: `fix(whatsapp): anti-cross-contact + anti-drift P0 — incident 2026-05-14`. Body draft no buffer Bash desta sessão.
- ⏳ **Wagner aprova merge após CI Pest verde** — 7 testes cobrem as 3 defesas
- ⏳ **Recovery SQL pós-merge** (Wagner roda no Hostinger ou autoriza Claude):
  - `UPDATE whatsapp_lid_pn_map.id=1 SET phone_e164=NULL` (anula mapping ofensivo)
  - `UPDATE conversations.id=37 SET contact_id=NULL, contact_name='+14628809617558 (LID não resolvido)'` (desassocia Eliana, **mensagens preservadas**)
  - `Cache::forget` keys LID dos 14 mappings
  - Wagner identifica contraparte real das 81 msgs (WhatsApp app celular pareado) → re-link manual
- ⏳ **Backlog P1+ desbloqueado** (PRs separados):
  - **P1-4** Normalização E.164 BR via libphonenumber (resolve 9º dígito móvel pós-2010)
  - **P1-5** UI merge contacts (4 Wagners no biz=1 hoje, 1 deveria ser canônico)
  - **P1-7** `whatsapp:lid-map:audit` artisan + cron daily detecta drift Tier 0
  - **P1-8** Métrica OTel `whatsapp_lid_resolver_returned_phone_count` (anti-cross-contact alarm)
  - **P2-10** Baileys 7.x migration (só com sinal qualificado por [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md))
- ⏳ **ADR-mãe candidato** "1 LID ≠ 1 pessoa em Baileys 6.7.9 — defense-in-depth até v7.x" (status `aceito`, amends US-WA-078). Wagner decidir se cria.

## Estado MCP no momento do fechamento

- **Brief-fetch (Tier A SessionStart 14h):** CYCLE-05 ativo (Inter PJ prod + WhatsApp governança), 9d restantes, cycle drift 0% alinhados, 18/18 commits/PRs 7d NÃO tocam tasks cycle ativo. Pivot estratégico em curso (Wagner foco operacional).
- **tools MCP my-work/cycles-active/decisions-search/sessions-recent não chamadas nesta janela** — sessão começou com brief-fetch (snapshot canônico ~3k tokens) + investigação focal incident. Próximo handoff (saída sessão real) deve chamar checklist completo MCP-first conforme [ADR 0130](../decisions/0130-handoff-append-only-mcp-first.md).
- **Tasks afetadas ao mergear PR:** US-WA-078 (auto-link Contact) ganha defense-in-depth; US-WA-093 (LID resolver workaround) ganha guard `source=manual` strict.

---

**Próxima sessão:** retomar com `brief-fetch` → `gh pr list --search "wa-anti-cross-contact"` ver estado PR → se aberto e CI verde, esperar Wagner aprovar merge → executar recovery SQL.
