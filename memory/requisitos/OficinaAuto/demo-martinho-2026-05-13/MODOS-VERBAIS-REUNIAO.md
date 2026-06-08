# Modos verbais — Reunião Martinho 10h (2026-05-13)

> **Cheat-sheet pra Wagner consultar AO VIVO** durante reunião. 2 frases gatilho com comportamentos pré-definidos do Claude Code.

---

## 🗣️ MODO A — "Como seria o estado da arte"

**Quando usar:** Martinho perguntou "vocês fazem X?" / "como funcionaria Y?" — quer **entender o conceito**. Reunião indo bem, modo educativo.

**Você fala:** _"Claude, como seria o estado da arte de \<o-que-Martinho-perguntou\>?"_

**Claude vai:**
1. Abrir o **plano de paralelização** (`plano-paralelizacao.md` que acabamos de criar) na tela
2. Explicar didaticamente pro Martinho a parte relevante (research dos concorrentes + como o oimpresso resolve)
3. Mostrar visualmente (tabelas de waves, comparativo concorrentes)
4. **NÃO executa nada** — só apresenta

**Output esperado:** Martinho vê que você TEM o plano detalhado pronto. Aumenta credibilidade.

---

## 🚨 MODO B — "Faça o estado da arte"

**Quando usar:** Martinho está hesitante / faz objeção forte / parece que vai escapar sem fechar. Você precisa de **prova de execução concreta** pra resgatar a reunião.

**Você fala:** _"Claude, faça o estado da arte agora."_

**Claude vai:**
1. Disparar o **`coordenador-paralelo`** pra executar Wave 0 do plano (rename `oa_*`) imediatamente
2. Em ~5-10 min, mostrar diff REAL no código (não mockup) — Wave 0 entregue
3. Reportar pro Martinho ao vivo: "isso aqui é a base pra importar seus 91 veículos — está feito"

**Output esperado:** Martinho vê código real sendo gerado em frente a ele. Sinal de execução, não promessa.

**⚠️ Custo:** spawn de 1 sub-agent + commits prep ~5-10 min. Use SÓ se reunião não tá indo bem — não desperdice esse cartão.

---

## 📋 Checklist pré-reunião (15 min antes das 10h)

- [ ] Claude Code aberto com este worktree (`D:/oimpresso.com/.claude/worktrees/crazy-euclid-b68bb7/`)
- [ ] `mockup.html` aberto no Launch preview (testa visual)
- [ ] `charter-1pager.md` impresso pra Martinho levar
- [ ] Este arquivo (`MODOS-VERBAIS-REUNIAO.md`) aberto em segundo monitor ou impresso
- [ ] `plano-paralelizacao.md` aberto numa aba (pronto pra mostrar se Modo A acionar)
- [ ] Celular silencioso

---

## 🎯 As 8 perguntas de descoberta (já no demo-script.md, repetidas aqui pra acesso rápido)

1. Quantas caçambas ativas hoje?
2. Tempo médio de OS (recepção → entrega)?
3. Quantos mecânicos? Quantos turnos?
4. WhatsApp é canal principal com cliente?
5. Como cobra hoje (boleto / cartão / depósito)?
6. NFC-e/NFS-e — tira hoje? Quem tira?
7. Histórico de OS antigas — perde quando computador queima?
8. Quanto pagaria/mês por sistema completo?

---

## 🎯 As 3 opções de fechamento

| Opção | Descrição | Risco | Quando propor |
|---|---|---|---|
| **A** | Beta 30 dias gratuito + importer Martinho + canary | Baixo Martinho / Alto Wagner (tempo) | Default — Martinho parece interessado |
| **B** | Faseado: importer dry-run primeiro, decide depois (15 dias) | Médio | Martinho hesitante mas educado |
| **C** | Pacote fechado R$ 15k one-time + R$ 400/mês | Baixo Wagner / Alto Martinho (gasto direto) | Martinho com objeção de preço, quer "preço cheio" |

---

## 🚀 Se Martinho fechou (qualquer opção)

**Pós-reunião imediato:**
1. Criar `discovery-martinho.md` (respostas das 8 perguntas)
2. Falar: _"Claude, dispara Wave 0 do plano-paralelizacao"_ — Claude executa rename `oa_*`
3. Wagner valida Pest filter verde
4. Falar: _"Dispara Waves A, B, C em paralelo"_ — Claude spawn 3 sub-agents

**Total wallclock pós-fechamento até 4 PRs prontos: ~24-26h (~3 dias focados).**

---

## 🛑 Se Martinho NÃO fechou

**Pós-reunião:**
1. Criar `discovery-martinho.md` com razões da recusa
2. Plano fica dormente em `memory/decisions-drafts/`
3. Pivot pra próximo piloto candidato (Vargas? Outro?)
4. Wagner volta pras tasks DOING próprias (US-WA-040, US-COPI-100, US-SELL-029/030 review)

**Não desperdice plano** — pattern de paralelização vale pra outros pilotos.

---

## Refs

- [plano-paralelizacao.md](plano-paralelizacao.md) — plano completo das 4 waves
- [demo-script.md](demo-script.md) — roteiro 15-20min reunião
- [charter-1pager.md](charter-1pager.md) — 1-page imprimível
- [mockup.html](mockup.html) — tela visual standalone
- `.claude/agents/coordenador-paralelo.md` — agent criado nesta sessão
- `.claude/agents/estado-da-arte.md` — agent research + gap (existe há ~1h)
