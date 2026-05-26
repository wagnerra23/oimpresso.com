---
name: Pesquisa paralela antes de implementar pacote grande
description: Antes de spawnar implementação de pacote ≥3 itens similares (drivers de bancos, batch de US, ondas de migração, refactor de N módulos), SEMPRE spawnar audit-research-experts em paralelo PRIMEIRO pra mapear viabilidade técnica, esforço real recalibrado, gotchas catalogados e ordem ótima de execução
type: feedback
---

Antes de spawnar `audit-implement-expert` (ou começar Edit/Write) em pacote grande de features similares (pacote de drivers de banco, batch de N US, ondas de migração paralelas, refactor de N módulos parecidos), o agente DEVE primeiro spawnar `audit-research-expert` em paralelo — 1 por item — produzindo dossier por item antes de qualquer linha de código.

**Why:** Wagner 2026-05-25 (sessão pacote top-5 bancos PaymentGateway: Bradesco/Itaú/BB/Santander/Caixa). Tendência natural do agente é pular pra implementação (premissa "todos os bancos são parecidos, basta replicar pattern InterDriver"). Mas cada banco tem realidade própria:

- Sandbox aberto vs fechado (Caixa SIGCB exige convênio presencial)
- Auth scheme distinto (Itaú JWT private_key_jwt + secret rotation 60d vs BB OAuth2 + gw-app-key)
- Viabilidade técnica (Caixa pode ser só SIBS SOAP legado — driver nativo desperdiça 6-8h)
- Pré-condições humano-limitadas (homologação varia 1-60 dias por banco — ADR 0106 não comprime)
- Ordem ótima depende de dados, não de intuição (BB sandbox aberto pode entrar primeiro mesmo Bradesco sendo o mais pedido)

Implementar sem pesquisa = código órfão sem sandbox, ou pior, escolha de ordem errada que bloqueia smoke real por semanas.

**How to apply:**

1. **Detectar pacote grande** — qualquer pedido que envolva ≥3 itens similares (drivers, módulos, telas, integrações, refactors)
2. **PARAR antes de Edit/Write** — confirmar com Wagner se quer "pesquisa paralela primeiro" (recommended) vs "implementar tudo em paralelo" (risco) vs "1 piloto primeiro"
3. **Spawn N audit-research-experts em paralelo** (single message, multiple Agent calls com `run_in_background:true`) — 1 prompt self-contained por item, output dossier markdown único em `memory/sessions/YYYY-MM-DD-arte-<item>.md`
4. **Cada dossier estrutura canônica:** Identidade · Endpoints · Auth · Capacidades · Limites · Gotchas · Comparação com pattern canon (ex InterDriver) · Esforço recalibrado (ADR 0106) · Viabilidade verdict ✅/🟡/❌ · Recomendação concreta de ordem
5. **Consolidar dossiês** após todos completarem — criar ADR filho com ordem real + ranking viabilidade
6. **Wagner aprova ordem real** baseada em dados (não intuição) antes de spawnar `audit-implement-expert`
7. **Tasks-create no MCP** pra cada pesquisa + 1 task de consolidação (tracking transparente)

**Aplica em particular pra:**
- Pacotes de drivers (bancos, gateways, ERPs externos)
- Batches de migração Blade→Inertia (≥3 telas similares — pareado com `coordenador-paralelo` agent)
- Refactors paralelos de N módulos (ex deprecar 3 módulos zumbi)
- Integrações com APIs externas em série (LGPD providers, ML services, etc)

**Não aplica pra:** feature isolada (1 driver, 1 tela, 1 integração) — aí é spawn direto de `como-integrar` (introspectivo) → `audit-implement-expert`.

**Refs:**
- [ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) — esforço recalibrado IA-pair
- [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal qualificado pra entrar no backlog
- Sessão piloto deste pattern: `memory/sessions/2026-05-25-arte-banco-*.md` (5 dossiês top-5 bancos)
