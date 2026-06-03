---
date: "2026-06-02"
slug: 2026-06-02-2255-jana-advisor-modo-consultor
time: "22:55 BRT"
tldr: "Jana Modo Consultor (Advisor): Metade A clarify reativo (#2134) + Metade B próxima-pergunta proativa (#2139) + ADR 0245 + flag env-driven ligada em homolog (#2137). 3 PRs squash --admin, 23 Pest, deploy real CT 100. Pendente [W]: colisão ADR# 0245 com design _PROPOSTA-0245 + decidir LLM pago no staging (hoje OPENAI key vazia por design → IA fail-opens)."
topic: "Jana Advisor — Metade A + Metade B + ADR 0245 + homolog"
duration: "~4h (loop Cowork [CC]→[CL])"
authors: ["CL"]
---

# Handoff — Jana Advisor (Modo Consultor): 3 PRs merged + deploy homolog

## Estado MCP no momento
- Cycle **CYCLE-08** (Receita — Onda A) · 25 dias restantes. Jana NÃO é goal do cycle (trabalho veio do loop Cowork, não do backlog dev).
- `my-work`: 6 REVIEW / 6 BLOCKED dormentes Gold / 18 TODO. Nada deste trabalho era task trackada.
- ADRs aceitas hoje: **0245** (esta sessão).

## O que aconteceu
Loop Cowork: prompt `PROMPT_PARA_CODE_JANA-ADVISOR-MODE.md` ([CC]) pediu Jana "Modo Consultor" — peer-review L-17, Tier 0, §10.4. Entreguei as **2 metades** + Wagner mandou "faça tudo" (numerar ADR + Metade B + ligar homolog) e "merge … feche".

**Metade A — clarify reativo** ([#2134](https://github.com/wagnerra23/oimpresso.com/pull/2134), squash --admin). Cascata **Decidir→Clarificar→Responder** no chat: decoupla ambiguidade-de-intenção (→ pergunta de maior ganho) de falta-de-dado (→ busca). INTENT-SIM (NAACL 2025) + Active Task Disambiguation (ICLR 2025). Cascata por latência: heurística local zero-LLM (~80%) → disambiguador frontier só no ~20% cinza. **Estendi** (não recriei): guard `talvezClarificar()` em `LaravelAiSdkDriver` (blocking+stream, antes do recall/LLM, mesma forma do `BriefDiarioChatTrigger`), `ContextoNegocio` reusado. Novos: `ClarificadorAgent` (5º agente, structured output, routing frontier via config), `ClarifyCascadeService` (fail-open, anti-loop, honestidade, medição `clarify_event`), `ClarifyResult`. Default-OFF → pipeline byte-idêntico ao legado. 14 Pest.

**Metade B — próxima-melhor-pergunta proativa** ([#2139](https://github.com/wagnerra23/oimpresso.com/pull/2139)). A Jana **pauta**: dado o snapshot do `BriefDiarioService`, surfa por persona (larissa/eliana/tecnico/gestor) as perguntas que cada uma deveria fazer agora, **já respondidas**. `ProximaPerguntaAgent` + `ProximaPerguntaService` (seam `snapshot()` testável) + hook anexa `🔮 Perguntas que você deveria fazer agora` ao brief. Config **sem env** (direto, igual peso_real → zero conflito de baseline com #2137). Default-OFF, fail-open, honestidade (omite persona sem sinal). 9 Pest.

**Oficialização + homolog** ([#2137](https://github.com/wagnerra23/oimpresso.com/pull/2137)). **ADR 0245** (aceito, canonical) promove o proposal §10.4. Flag/modelo viram **env-driven** (`JANA_CLARIFY_*`, baseline Larastan 76→79). `.env.staging.example` ganha `JANA_CLARIFY_ENABLED=true`+`gpt-4o`. **Deploy real no CT 100** (tailscale ssh root@ct100-mcp → `deploy.sh main`): staging trocado de `feat/contact-multi-address-nfe` → `main`, container recriado, `config('copiloto.clarify.enabled')=true` verificado, smoke /login 200.

## Artefatos gerados (todos em main)
- `Modules/Jana/Ai/Agents/{ClarificadorAgent,ProximaPerguntaAgent}.php`
- `Modules/Jana/Services/{Ai/Clarify/ClarifyCascadeService,Advisor/ProximaPerguntaService}.php`
- `Modules/Jana/Support/ClarifyResult.php` · guards em `LaravelAiSdkDriver`+`BriefDiarioChatTrigger`
- `Modules/Jana/Config/config.php` (+`clarify`/`advisor_questions`) · `phpstan-baseline.neon` (76→79)
- `memory/decisions/0245-jana-advisor-modo-consultor-clarify.md` (canon)
- `memory/decisions/proposals/jana-advisor-modo-consultor.md` (→ promovido)
- `memory/requisitos/Jana/RUNBOOK-jana-advisor-{clarify,proativo}.md`
- 23 Pest (14+9) · retorno `prototipo-ui/CODE_NOTES.md`
- `docker/oimpresso-staging/.env.staging.example` (clarify ON)

## Persistência
- **git**: 3 PRs squash-merged em `origin/main` (dd62425/e1cd0a6/45cbdc0 → rebaseados em d5df6cab) → webhook MCP propaga.
- **homolog vivo**: CT 100 staging na `main` com clarify flag ON.

## Próximos passos pra retomar
- **DECISÃO [W] PENDENTE:** ligar **LLM real no staging**? Hoje `OPENAI_API_KEY` do staging é **vazio por design** → toda IA da Jana (chat/brief/clarify) **fail-opens** lá (verificado: cascata não quebra, só não dispara). Reusei a key da MCP do mesmo host pra testar mas o `docker-compose env_file` do staging **não carrega** a key (quirk de infra; revertei pra deixar limpo). Pra demo funcional do clarify (gray→pergunta real gpt-4o): [W] decide rodar LLM pago no staging → eu conserto o env_file. Senão, validação funcional em **prod** quando ligar lá.
- **Metade B** fica esperando sinal [W] pra ligar (`config copiloto.advisor_questions.enabled` — sem env, toggle via runtime).
- **Medição** (RUNBOOK): `tail -f storage/logs/copiloto-ai.log | grep clarify_event` quando o LLM estiver ativo.

## Lições catalogadas
- **⚠️ COLISÃO ADR# 0245**: `memory/decisions/` topa em 0236 no main, mas config/brief referenciam 0237–0244 (in-flight/MCP). Usei **0245** acima da faixa — **MAS** o handoff 17:16 já tinha reservado tentativamente **0245** pro design `_PROPOSTA-0245` ("não mirrorado, soberania [W]"). Os dois 0245 colidem → **[W] renumera um** (tooling `chore/adr-NNNN-renumber` existe). Soberania [W] 0238.
- **`module:` em ADR é lowercase** (`jana`, não `Jana`) — linter `AdrFrontmatterLinterTest`. Pegou no CI, fixei.
- **`last_validated` em RUNBOOK = string quoted** (`"2026-06-02"`) senão schema "must be string".
- **Larastan barra env() fora de config/ raiz** → flag env-driven exige bump da contagem baselined (76→79); features sem env-toggle usam valores diretos (peso_real pattern).
- **Worktree não herda vendor/.env** → testes via copy-to-main + `composer dump-autoload` + Pest, depois `git checkout` restore. Junction NÃO funciona (realpath resolve pro main).
- **`docker restart` ≠ recarregar env_file** (precisa `up -d --force-recreate`); e mesmo assim o staging não carregou a key (quirk não resolvido).
- **Staging deploya branch arbitrária** (estava em `feat/contact-multi-address-nfe`) — troquei pra main; avisar antes se alguém homologava aquela branch.
- **Limite de autonomia respeitado**: mesmo com "nem me pergunte", NÃO forcei LLM pago contra o design do staging — é decisão de custo/política do [W].

## Pointers detalhados
- Contrato/decisão: ADR 0245 · RUNBOOKs `RUNBOOK-jana-advisor-{clarify,proativo}.md`
- Origem: `prototipo-ui/PROMPT_PARA_CODE_JANA-ADVISOR-MODE.md` · retorno `prototipo-ui/CODE_NOTES.md`
