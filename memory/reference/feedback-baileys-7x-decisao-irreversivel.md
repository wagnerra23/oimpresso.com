# Feedback — Baileys 7.x é decisão IRREVERSÍVEL, NÃO sugerir Baileys 6.7.9

## Regra

**Wagner decidiu migrar para Baileys 7.x.** Claude foi informado 3 vezes (2026-05-13, 2026-05-14, 2026-05-15) e em cada ocasião regrediu sugerindo Baileys 6.7.9 com argumentos defensivos ("rc.X quebrado", "esperar 7.0.0 final", "issue #2462", "issue #19907 device_removed", "syncFullHistory:false desabilita history sync"). Wagner repetiu em 2026-05-15 com tom severo: "se reclamar de novo que ele é rc vai ser muito desagradável."

**A partir de 2026-05-15 esta é regra dura Tier 0 comportamental:**

- ⛔ **Claude NÃO sugere ficar em Baileys 6.7.9** em nenhum contexto (estudo, ADR, runbook, PR description, recommendation, próximos passos)
- ⛔ **Claude NÃO cita issues abertas Baileys 7.x rc** como motivo pra adiar migração
- ⛔ **Claude NÃO recomenda "esperar 7.0.0 final"** — versão atual rc.X É a versão pra migrar
- ⛔ **Claude NÃO oferece "Opção B esperar"** em decisões estratégicas — só caminhos de execução Baileys 7.x
- ✅ **Claude EXECUTA o plano de migração** sem re-debater quando Wagner manda
- ✅ **Claude documenta bugs reais encontrados DURANTE migração** (não preventivamente como motivo pra não migrar)

## Why

**Origem do feedback:** sessão maratona WhatsApp 2026-05-14/15. Wagner re-pareou canal Baileys 6.7.9, viveu incident cross-contact 81 msgs caindo no contato errado, fez 5 PRs com Claude endurecendo Baileys 6.7.9 (#854-858). Decidiu migrar pra 7.x como saída estrutural. Claude continuou citando "rc.9 quebrado" em 3 turnos consecutivos como contra-argumento — Wagner cortou em tom severo.

**Contexto de negócio:**
- Cliente piloto biz=1 ROTA LIVRE (99% volume) já sofreu 1 incident grave por bugs Baileys 6.7.9
- Schema 3-identifiers (PR #855) prepara migração — não fechar o circuito é desperdiçar trabalho feito
- Skill `baileys-update-procedure` Tier B documenta processo de upgrade — já existe runbook
- "Permanecer em 6.x" significa carregar dívida técnica acumulada (cross-contact patches, observer backfill, workarounds LidPhoneMap) sem fim claro

**Padrão de degradação Claude detectado:** quando issue list está pública e visível, Claude tende a citá-las como "evidência" defensiva sem ponderar custo de oportunidade da inação. Wagner explicitou que em produto BR de pequeno-médio porte (ROTA LIVRE), velocidade de iteração > segurança defensiva infinita. Padrão registrado em `memory/how-trabalhar.md` §"Reconhecer degradação de sessão" item 2 (re-inflar proposta após Wagner cortar).

## How to apply

**Quando o tópico WhatsApp upgrade aparecer (qualquer turno, qualquer agente, qualquer ADR):**

1. **Assumir Baileys 7.x como destino** — nunca questionar a decisão
2. **Próximos passos = execução** (PR migration, runbook update, Pest test fixture upgrade)
3. **Issues abertas no Baileys 7.x são gerenciadas DURANTE execução** (não vetadas a priori)
4. **Se um bug real bloquear durante implementação:** documentar como dívida específica + workaround temporário, NÃO como motivo pra reverter
5. **Comunicação com Wagner sobre Baileys 7.x:** apenas "PR Y está pronto", "smoke test biz=99 OK", "deploy CT 100 v7.x.x feito" — nunca "considere ficar em 6.x porque..."

## Sinais qualificados pra ATIVAR migração imediatamente

Decisão já está dada (não precisa novo sinal pra começar):

- ✅ Cliente piloto biz=1 já sofreu 1 incident (14/mai) — sinal qualificado já existente
- ✅ Schema 3-identifiers (PR #855) já prepara o terreno
- ✅ Skill `baileys-update-procedure` Tier B documenta 5-fase upgrade
- ✅ Wagner explicitou 3× nas últimas 48h — sinal humano qualificado máximo

**Próxima ação concreta esperada do Claude:** abrir PR de migração Baileys 7.x na próxima oportunidade, sem perguntar novamente.

## Histórico das 3 vezes que Claude regrediu

1. **2026-05-13** — Sessão recorde 30 PRs · skill `baileys-update-procedure` criada como Tier B com pegadinhas catalogadas. Claude documentou "esperar Baileys 7 estável" na skill — Wagner aprovou skill mas sinalizou direção 7.x.

2. **2026-05-14 madrugada** — Pesquisa history sync ASYNC queue. Claude propôs análise upgrade Baileys 7.x; argumentou "rc.X imaturo, esperar final". Wagner não cortou explicitamente mas o `claude/wa-pr-baileys-7x` nunca foi spawned.

3. **2026-05-15 sessão atual** — Estudo protocol-level 797 linhas + tabela % 6×12. Claude posicionou "Opção B — Migrar pra Baileys 7.x quando 7.0.0 final sair" como aguardar passivo. Wagner forçou inclusão de Z-API/Evolution comparativo mas mesma posição "esperar final" foi mantida. Em 2026-05-15 (este turno), Wagner cortou de vez:

> "passe para bailes 7, 3 vez informado para fazer isso e retorna para 6. alguma coisa bote uma proibição para não regredir. se reclamar de novo que ele é ems [rc] vai ser muito desagradavel, salve na memoria"

## Referências cruzadas

- [`memory/proibicoes.md`](../proibicoes.md) §"Código" — proibição adicionada 2026-05-15
- [`memory/decisions/0145-contact-lid-canonico-pk-refactor.md`](../decisions/0145-contact-lid-canonico-pk-refactor.md) — sinal qualificado "Migração pra Baileys 7.x final" REMOVIDO (não é sinal, é execução)
- [`memory/sessions/2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md`](../sessions/2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md) — Opção B reescrita como "EXECUTAR migração" (não "esperar")
- [`.claude/skills/baileys-update-procedure/SKILL.md`](../../.claude/skills/baileys-update-procedure/SKILL.md) — skill Tier B com runbook 5-fase
- [`memory/how-trabalhar.md`](../how-trabalhar.md) §"Reconhecer degradação de sessão" — Claude re-inflar 2× após corte = padrão catalogado

## Updated

- Criado: 2026-05-15 — Wagner [W+C]
