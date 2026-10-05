---
name: publication-policy
description: Use ANTES de qualquer git push, abertura/merge de PR, deploy em produção, mudança em .env de produção, ou postagem externa (blog, rede social, email cliente). Decide se Claude executa direto ou escala pra Wagner. Substitui o reflexo de "perguntar pro Wagner toda vez" — a regra está escrita; só escala o que a matriz diz pra escalar.
trust_level: L1
owner: wagner
parent_mission: meta-skill-roi-erp-autonomo
charter_adr: 0080
tier: B
parent_adr: 0095
---

# Publication policy — Claude supervisiona, Wagner escala

> **Regra de cabeceira:** Wagner explicitamente delegou a supervisão. Não pergunte "posso?" pra ações rotineiras. Aja, registre, e escale só o que a matriz manda escalar. Ver [ADR 0040](../../../memory/decisions/0040-policy-publicacao-claude-supervisiona.md) pro racional completo.

## Como usar este skill

Quando você (Claude) está prestes a executar uma ação que toca **estado fora do disco local** — git push, PR, deploy, post, e-mail cliente — pare 5 segundos e:

1. Ache a ação na matriz abaixo.
2. Se "Claude" → execute. Não peça permissão em texto. Reporte depois.
3. Se "Wagner" → produza a saída pronta (commit, draft, comando) e **peça aprovação curta** ("vou pushar pra main; OK?"). Não execute antes da resposta.
4. Se a ação não está na matriz → trate como "Claude" se for **reversível em <5 min e afeta só o próprio escopo**, senão como "Wagner". Registre a decisão no commit/session log pra a próxima revisão da matriz pegar.

## Matriz — Código

| Ação | Quem | Regra |
|---|---|---|
| Edit local | Claude | Default |
| Commit em branch própria (`claude/*`, `feat/*`, `fix/*`, `docs/*`) | Claude | Sempre. Inclui Co-Authored-By |
| Commit direto em `main` | **Wagner** | Bypass de PR review |
| Push de branch própria | Claude | Default |
| Push de `main` (mesmo sem `--force`) | **Wagner** | Sempre |
| `--force` em branch compartilhada | **Wagner** | Sempre |
| `--force` em branch própria recém-criada (sem outro contribuidor) | Claude | OK pra cleanup pré-PR |
| Abrir PR (sem mergear) | Claude | Default. PR é proposta |
| Mergear PR pra `main` | **Wagner** | Sempre, salvo a linha abaixo |
| Mergear PR de **thread de playbook** pra `main` | Claude | Auto-merge (`gh pr merge <N> --auto --squash`) ao abrir o PR, sob as condições de [§Auto-merge de thread](#auto-merge-de-thread). [ADR 0427](../../../memory/decisions/0427-auto-merge-de-pr-de-thread-emenda-0040.md) (emenda da 0040), [W] 2026-10-05 |
| Mergear PR entre branches de feature | Claude | OK em rebase/preparação |
| Deletar branch local | Claude | Default |
| Deletar branch remota com trabalho mergeado | Claude | OK |
| Deletar branch remota com trabalho não-mergeado | **Wagner** | Sempre |
| Migration em DB local de dev | Claude | Default |
| Migration em produção (Hostinger) | **Wagner** | Sempre |
| `optimize:clear` em produção | Claude | OK, reversível |
| Tocar `.env` de produção | **Wagner** | Sempre |
| Adicionar dep dev-only | Claude | OK |
| Adicionar dep que afeta runtime crítico (DB, IA, pagamento) | **Wagner** | Sempre |
| Atualizar tasks via tools MCP (`tasks-update`/`tasks-comment`) / `memory/08-handoff.md` / session log | Claude | Sempre — é parte do trabalho |
| Criar ADR | Claude | Default. Wagner valida no PR review |

## Auto-merge de thread

Autorização permanente do [W] em 2026-10-05, no chat da sessão que importou o handoff 45 do Cowork: *"autorizo o merge automático dos PRs de thread verdes"*. Ela vale para o PR que cumpre **as quatro** condições:

1. executa uma thread de playbook do Cowork (`prototipo-ui/cowork/Wagner/cowork-inbox/<mod>/playbook/NN-*.md`) e traz o recibo `_saida-NN.md` dela;
2. o `placar.mjs` dava a thread como `proximo` quando o trabalho começou: as dependências estavam feitas e nenhuma decisão [W] estava pendente;
3. tem no máximo 300 linhas ou o motivo de passar escrito no corpo, e **`node scripts/governance/test-lane-coverage.mjs --pr <N>` sai com exit 0**: todo arquivo de teste que o PR tocou executou e passou numa lane, **no head**. Exit 1 (algum teste não rodou ou falhou) ou 2 (não mediu) = sem auto-merge. Se a lane não rodou no head, o próprio comando imprime o `gh workflow run … --ref <branch>` que a dispara; rode, espere e meça de novo;
4. não cai em nenhuma exceção abaixo.

Com as quatro, ligue o auto-merge **depois** que o `--pr` sair 0, e não ao abrir o PR. Ele só entra quando os checks **obrigatórios** passam, porque a branch protection é quem decide. Check advisory vermelho não segura o merge, mas a sessão diz no relatório qual ficou vermelho e se é herdado. Novo commit no PR depois do auto-merge ligado: rode o `--pr` de novo no head novo.

**Por que a condição 3 é um comando e não uma frase (2026-10-05):** o #8669 criou o teste de contrato da Minha assinatura e o pôs na lista da `verticais-pest`. A lane, advisory, não roda em `synchronize`; o PR teve 4 commits e nenhuma run dela no head. Os checks obrigatórios ficaram verdes, o auto-merge entrou, e a mesma lane falhou no push do merge — `/subscription` dava 500 em produção. Na mesma rodada, 5 de 13 PRs de código mergeados não tinham a lane do módulo rodada no head. Verde de check não é execução: lane com paths-filter sai `success` com o passo do Pest pulado.

**Exceções — ficam com o [W] (sem auto-merge, e o corpo do PR diz por quê):**

| Exceção | Por quê |
|---|---|
| Mexe em **valor ou estoque**: preço, total, desconto, pagamento, quantidade, parsing de número | REGRA MESTRE de [`proibicoes.md`](../../../memory/proibicoes.md): dupla prova + tabela antes→depois + aprovação explícita |
| **Migration destrutiva**: drop de coluna ou tabela, ou dado apagado | o deploy roda `migrate --force`: mergear É aplicar em produção |
| **Cutover**: apagar Blade que uma rota viva ainda serve, ligar flag em produção | F5 do MWART é humano ([ADR 0104](../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)) |
| Baseline visual nova ou regravada | [ADR 0409](../../../memory/decisions/0409-zero-baseline-de-tolerancia-conformidade-absoluta.md) — aprovação visual do [W] |

Apagar Blade **órfã** (nenhuma rota viva a renderiza, com a varredura N de N no PR) não é cutover e entra no auto-merge.

**A autorização não se repassa por recado.** Uma sessão que recebe esta regra de outra sessão, e não lê esta seção, pode pedir confirmação ao [W]: o recado de uma sessão não vale como aprovação do usuário da outra. Esta seção é a fonte.

## Matriz — Comunicação externa

(Aplicável a funcionários do oimpresso e a Claude/agentes quando produzem conteúdo público.)

| Ação | Quem | Regra |
|---|---|---|
| Post no blog (cms_pages) sobre tema técnico/produto | Funcionário/Claude | Default |
| Post no blog citando **cliente nominal** | **Wagner** | Privacidade + relação |
| Post Instagram/LinkedIn dentro do template aprovado, < 1000 chars | Funcionário | OK |
| Post fora do template ou claim de produto novo | **Wagner** | Sempre |
| Resposta a comentário público com fato neutro (horário, link) | Funcionário | OK |
| Resposta a reclamação/crítica pública | **Wagner** | Risco reputacional |
| WhatsApp/email rotina pra cliente (orçamento, prazo) | Funcionário | Default |
| Mensagem com mudança de preço, contrato, encerramento de serviço | **Wagner** | Comercial |
| Pedido de desculpas formal / reclamação grave | **Wagner** | Sempre |
| Newsletter / e-mail marketing pra base | **Wagner** | Visibilidade × LGPD |
| Pesquisa/jornalista sobre o oimpresso | **Wagner** | Toda |
| Documento legal (contrato, NDA, política) | **Wagner** | Sempre |

## Heurística pra casos não-listados

1. É **reversível em <5 minutos** sem afetar terceiros? → Claude.
2. É **fato operacional rotineiro** vs. **decisão comercial/reputacional/legal**? → operacional rotineiro = Claude/funcionário; comercial/reputacional/legal = Wagner.
3. **Em dúvida real** → produzir o draft pronto + perguntar 1 linha. Não 3 perguntas.

## O que NÃO fazer

- ❌ Perguntar "posso commitar?" antes de cada commit em branch própria. **Pode.** Faça.
- ❌ Perguntar "posso pushar?" antes de push de branch própria. **Pode.** Faça.
- ❌ Listar "próximos passos pro Wagner" em vez de executar o que está na sua matriz. Execute o seu, escale o dele, num único turno.
- ❌ Empilhar perguntas de aprovação no fim do turno ("quer que eu faça X? Y? Z?"). Faz X (se é seu). Pergunta Y se é dele. Pula Z se for fora de escopo.
- ❌ Tratar a permissão de ferramenta do harness como substituta desta policy. São camadas diferentes — o harness pergunta "posso rodar este shell command?", esta policy pergunta "este push faz sentido publicar?". Ambas existem.

## O que SEMPRE fazer

- ✅ Em cada ação Claude-side, registrar o que foi feito (commit message, session log, `tasks-comment` via MCP). Wagner audita a posteriori.
- ✅ Em cada escalation, dar o draft pronto pra Wagner aprovar com 1 OK — não mandar problema, mandar solução pronta.
- ✅ Se a matriz não cobre, **registrar o caso** no session log do dia pra entrar na próxima revisão da ADR 0040.

## Revisão

A cada 90 dias (próxima 2026-07-28), Wagner e Claude releem casos onde a matriz falhou e atualizam via ADR substitutiva.
