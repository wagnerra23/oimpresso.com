# ADR 0040 — Policy de publicação: Claude supervisiona, Wagner escala

**Status:** ✅ Aceita
**Data:** 2026-04-28
**Escopo:** Plataforma — afeta fluxo de commit/push/PR/deploy + comunicação externa por funcionários
**Decisor:** Wagner (declarou em 2026-04-28); operacionalizado por Claude

---

## Contexto

Em 2026-04-28, em sessão de reorganização da memória do repo, Claude apresentou "próximos passos sugeridos pra Wagner: push manualmente, abrir PR…". Wagner respondeu:

> *"por que eu decidir? assuma a responsabilidade quem tem mais chance de errar? eu ou vc para saber o que é importante? como meus funcionários vão decidir se vão postar ou não? quero que supervisione o processo e garanta a melhor efetividade."*

**Diagnóstico do problema:**

1. **Gargalo no Wagner.** Cada decisão "push agora ou pergunto?" virava uma interrupção. Multiplique por X commits/dia × Y agentes paralelos (Cursor, Code, Claude Desktop) × Z funcionários (postagens, e-mails, mensagens cliente) — Wagner virou ponto único de falha.
2. **Decisão errada no decisor errado.** Claude tem mais contexto técnico em git/CI/code review do que Wagner em tempo real. Funcionário não-técnico tem mais contexto editorial/cliente do que Wagner em tempo real. Cada um decide melhor no próprio domínio se a regra estiver clara.
3. **Falta de regra escrita.** Sem matriz documentada, cada novo agente/funcionário começa do zero, pergunta a mesma coisa, gera ruído.

## Decisão

**Claude (e cada funcionário no próprio domínio) é o supervisor por default das próprias ações.** Wagner é o escalonamento de exceção, não a aprovação por padrão.

A regra é estruturada em duas matrizes — Parte A (código) e Parte B (comunicação externa).

---

### Parte A — Decisões de código (commit / push / PR / deploy)

| Ação | Quem decide | Critério |
|---|---|---|
| Edit local de arquivo | Claude | Default. Reversível por `git checkout`. |
| Commit em branch própria (`claude/*`, `feat/*`, `fix/*`, `docs/*`) | Claude | Sempre. Inclui mensagem clara + Co-Authored-By. |
| Commit em branch principal (`main`) **direto** | **Wagner** | Sempre escala. Bypass de PR review = decisão dele. |
| Commit em branch de outro autor (`wagner/*`) sem permissão prévia | **Wagner** | Sempre escala. |
| Push de branch própria pro remote | Claude | Default. Permite preview/CI/colaboração. Reversível por `git push --delete`. |
| Push de branch principal (`main`, `master`) | **Wagner** | Sempre escala. Mesmo sem `--force`. |
| Force push em qualquer branch compartilhada | **Wagner** | Sempre escala. Reescreve histórico de outros. |
| Force push em branch própria recém-criada (sem outro contribuidor) | Claude | OK pra cleanup pré-PR. |
| Abrir PR (sem mergear) | Claude | Default. PR é proposta, não execução. Inclui descrição clara, escopo, riscos. |
| Mergear PR pra `main` | **Wagner** | Sempre escala. Decisão de incorporação. |
| Mergear PR entre branches de feature (não-main) | Claude | OK se serve pra rebase/preparação. |
| Fechar PR sem mergear | Claude se for próprio PR; **Wagner** se for de outro autor |
| Deletar branch local própria | Claude | Default. |
| Deletar branch remota com trabalho mergeado | Claude | OK depois de confirmar merge. |
| Deletar branch remota com trabalho não-mergeado | **Wagner** | Sempre escala. Perda de trabalho. |
| Reset/rebase destrutivo em branch compartilhada | **Wagner** | Sempre escala. |
| Rodar migration em DB local de dev | Claude | Default. |
| Rodar migration em produção (Hostinger) | **Wagner** | Sempre escala. Tem rollback? Em quanto tempo? |
| `optimize:clear` / `cache:clear` em produção | Claude | OK. Reversível. |
| Tocar `.env` de produção | **Wagner** | Sempre escala. Credenciais + estado. |
| Tocar `composer.json` (adicionar/remover dep) | Claude se for dev-only ou trivial; **Wagner** se afeta runtime de produção crítico (DB, IA, pagamento) |
| Atualizar handoff / CURRENT.md / session log | Claude | Sempre. É parte do trabalho. |
| Criar nova ADR | Claude | Default — registra decisão. Wagner valida no review do PR. |

**Heurística primária:** *reversibilidade* + *blast radius*. Reversível em <5 min e afeta só o próprio escopo → Claude. Irreversível ou afeta produção/cliente/outros agentes → Wagner.

**Em dúvida:** Claude age + escreve no commit message ou session log a decisão tomada e por quê. Wagner pode reverter no review. Não trava perguntando.

---

### Parte B — Comunicação externa (post, e-mail, mensagem cliente)

Aplicável a funcionários do oimpresso e a Claude/agentes quando produzem conteúdo público.

| Ação | Quem decide | Critério |
|---|---|---|
| Post no blog do oimpresso (cms_pages) sobre tema técnico/produto | Funcionário/Claude | Default. Segue [STYLE-GUIDE](memory/04-conventions.md). |
| Post no blog sobre **caso de cliente nominal** | **Wagner** | Sempre escala. Privacidade + relação comercial. |
| Post em rede social do oimpresso (Instagram/LinkedIn) | Funcionário | Se tem template aprovado e copy < 1000 chars. |
| Post em rede social fora do template / com claim de produto novo | **Wagner** | Sempre escala. |
| Resposta a comentário público em rede social | Funcionário | Se for fato neutro (horário, link). |
| Resposta a reclamação pública / crítica em rede social | **Wagner** | Sempre escala. Risco reputacional. |
| Mensagem WhatsApp/e-mail rotina pra cliente (orçamento, prazo, dúvida operacional) | Funcionário | Default. Ele tem o relacionamento. |
| Mensagem com **mudança de preço, prazo de contrato, encerramento de serviço** | **Wagner** | Sempre escala. Decisão comercial. |
| Mensagem com **pedido de desculpas formal** ou tratamento de reclamação grave | **Wagner** | Sempre escala. |
| Newsletter / e-mail marketing pra base | **Wagner** | Sempre escala. Visibilidade × LGPD. |
| Resposta a pesquisa/jornalista sobre o oimpresso | **Wagner** | Sempre escala. Toda. |
| Documento legal (contrato, NDA, política) | **Wagner** | Sempre escala. |

**Heurística primária:** *é fato operacional rotineiro* (funcionário/Claude) ou *é decisão comercial/reputacional/legal* (Wagner)?

**Em dúvida:** o autor (funcionário/Claude) escreve um draft e manda no DM do Wagner pedindo "OK?" — não publica antes da resposta. Mas só faz isso na dúvida real, não a cada post.

---

## Mecanismos pra fazer a policy funcionar

1. **Skill `publication-policy`** em [`.claude/skills/publication-policy/SKILL.md`](.claude/skills/publication-policy/SKILL.md) ativa quando Claude está prestes a executar uma ação da matriz Parte A. Lembra a regra. Curto.
2. **Auto-memória** [`feedback_claude_supervisiona_decisoes.md`](C:/Users/wagne/.claude/projects/D--oimpresso-com/memory/feedback_claude_supervisiona_decisoes.md) garante que a preferência sobrevive entre sessões.
3. **CLAUDE.md §2** referencia esta ADR como contexto pra "quando publicar/quando perguntar".
4. **Wagner recebe registro de cada escalation,** não de cada ação rotineira. Reduz ruído.
5. **Auditoria:** session log de cada sessão lista as ações tomadas em pé de igualdade — Wagner pode auditar a posteriori e ajustar a matriz se algo me escapou.

## Alternativas consideradas

| Opção | Por que rejeitada |
|---|---|
| Wagner aprova tudo | Já era o status quo. Gargalo. Wagner cansa. Funcionários ficam parados. |
| Claude aprova tudo, Wagner audita depois | Risco em ações irreversíveis (push pra main, mensagem pra cliente). Audit não desfaz reputação. |
| Cada agente/funcionário decide tudo no próprio escopo, sem regra escrita | Ambiguidade gera retrabalho ou cautela exagerada. Cliente novo na equipe não sabe o limite. |
| Tool-permission-mode-only (deixar o harness do Claude Code decidir) | Permissão de ferramenta ≠ permissão de impacto. Push é tecnicamente trivial mas pode ser politicamente caro. Precisa camada acima. |

## Consequências

**Positivas:**
- Wagner deixa de ser gargalo em decisões rotineiras.
- Funcionários têm regra clara — menos hesitação, menos retrabalho.
- Cada nova IA/colaborador onboarda lendo a matriz, não testando o limite caso a caso.
- Claude pode encadear ações (commit → push → abrir PR) sem 3 confirmações.

**Negativas:**
- Cresce o trabalho de revisar PRs (Wagner não viu cada commit antes; vai ver no PR review). Mitigado por: PR descrição clara obrigatória.
- Risco de Claude/funcionário decidir errado num caso ambíguo. Mitigado por: matriz escrita + auto-memória de feedback + revisão da matriz a cada 90 dias.
- Auditoria pelo Wagner exige disciplina (ler session logs / git log) que ele pode pular. Mitigado por: ferramentas resumem (`gh pr list`, handoff).

**Neutras:**
- Não muda nada do tool-permission-mode do Claude Code — esse continua valendo. Esta policy é a camada acima ("texto-confirmação"), não substitui o harness.

## Revisão

A cada 90 dias (próxima: **2026-07-28**), Wagner e Claude revisam:
- Casos da matriz que geraram problema.
- Casos não cobertos que geraram dúvida.
- Mover linhas entre "Claude" e "Wagner" conforme aprendizado.

Mudanças entram via ADR substitutiva (não edita esta in-place — preserva histórico).

## Referências

- Auto-memória: [`feedback_claude_supervisiona_decisoes.md`](https://github.com/wagnerra23/oimpresso.com/) (fora do git, em `~/.claude/projects/D--oimpresso-com/memory/`)
- Skill: [`.claude/skills/publication-policy/SKILL.md`](../../.claude/skills/publication-policy/SKILL.md)
- ADR 0027 — Gestão de memória (papéis canônicos) — esta ADR herda a separação entre handoff/auto-memória/git
- ADR 0030 — Credenciais nunca em git — caso particular de "Wagner escala"
