---
name: publication-policy
description: Use ANTES de qualquer git push, abertura/merge de PR, deploy em produção, mudança em .env de produção, ou postagem externa (blog, rede social, email cliente). Decide se Claude executa direto ou escala pra Wagner. Substitui o reflexo de "perguntar pro Wagner toda vez" — a regra está escrita; só escala o que a matriz diz pra escalar.
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
| Mergear PR pra `main` | **Wagner** | Sempre |
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
| Atualizar `CURRENT.md` / `memory/08-handoff.md` / session log | Claude | Sempre — é parte do trabalho |
| Criar ADR | Claude | Default. Wagner valida no PR review |

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

- ✅ Em cada ação Claude-side, registrar o que foi feito (commit message, session log, CURRENT.md). Wagner audita a posteriori.
- ✅ Em cada escalation, dar o draft pronto pra Wagner aprovar com 1 OK — não mandar problema, mandar solução pronta.
- ✅ Se a matriz não cobre, **registrar o caso** no session log do dia pra entrar na próxima revisão da ADR 0040.

## Revisão

A cada 90 dias (próxima 2026-07-28), Wagner e Claude releem casos onde a matriz falhou e atualizam via ADR substitutiva.
