---
date: "2026-08-17"
topic: "Jana/Chat: dos 5 gaps do card da conversa, 3 já estavam implementados desde 07/08 — e os 2 reais não são trabalho de tela"
authors: ["C"]
prs: []
related_adrs: ["0093-multi-tenant-isolation-tier-0", "0264-governanca-executavel-trio-dominio-e2e"]
tldr: "Levantamento read-only dos campos que o card de conversa precisaria, contra a âncora `JmConversa`. Achado que inverte o escopo: contador, peek e scrim — 3 dos 5 itens listados como lacuna — entraram no Chat.tsx em 2026-08-07 (PR #5405) e estão presentes inclusive na branch que escreveu o casos.md. Sobram 2 gaps reais (metadados do card e escopo da thread), e a maior parte deles é backend/modelo de dados, não pixel: `preview` e `quando` não trafegam no payload, e `com`/escopo-de-equipe pressupõem compartilhamento que não existe no schema."
---

# Jana/Chat — os 5 gaps do card, medidos

## 0 · Por que este log existe

O chip de trabalho do `Jana/Chat` listava **5 lacunas** do card da conversa contra a âncora
`prototipo-ui/cowork/jana-merge.jsx` §`JmConversa`, e estava **bloqueado**: `Jana/Chat` não está em
`tests/Browser/visreg-screens.json`, então qualquer edição no `.tsx` é fail-closed. Reproduzido
nesta data, num commit descartável que tocava só o `Chat.tsx`:

```
node scripts/governance/ui-impact.mjs --base=origin/main --head=HEAD
→ exit 1 · uncovered_screens: ["Jana/Chat"] · scope: "targeted"
```

Com a tela travada, o trabalho possível era **leitura**: levantar os campos exatos que o card
precisaria, para o teste nascer sabendo o que assertar. O levantamento achou outra coisa.

## 1 · Três dos cinco itens JÁ ESTÃO no `Chat.tsx`

| Item listado como lacuna | O que existe no vivo (greppável) | Entrou em |
|---|---|---|
| contador de conversas no cabeçalho | `cs-count` (recebe `visiveis.length`) | `61c770ec0` · 2026-08-07 · PR #5405 |
| `peek` recolhido com rótulo + número | `cs-peek-l` (`"Histórico"`) + `cs-peek-n` | idem |
| scrim clicável na sobreposição ≤1100px | `copiloto-chat-scrim`, com o comentário *"clicar fora fecha o histórico"* | idem |

Comandos que sustentam a coluna da direita:

```bash
git log -1 --format='%h %ad %s' --date=iso -S 'copiloto-chat-scrim' -- resources/js/Pages/Jana/Chat.tsx
git log -1 --format='%h %ad %s' --date=iso -S 'cs-count'            -- resources/js/Pages/Jana/Chat.tsx
git log -1 --format='%h %ad %s' --date=iso -S 'cs-peek-n'           -- resources/js/Pages/Jana/Chat.tsx
```

**Não é defasagem entre worktrees.** O `Chat.tsx` da branch `claude/jana-chat-casos` — a que
escreveu o `Chat.casos.md`, com merge-base em `b02769080` (2026-08-17 14:52, praticamente o `main`
do momento) — também tem os três:

```bash
git show origin/claude/jana-chat-casos:resources/js/Pages/Jana/Chat.tsx \
  | grep -c "copiloto-chat-scrim\|cs-count\|cs-peek-n"    # → 3
```

Ou seja: a frase *"o overlay já existe; o scrim, não"*, no bloco `[BACKLOG]` do `Chat.casos.md`,
já era falsa quando foi escrita — por dez dias.

### O que observei sobre o mecanismo (observação, não causa provada)

Os nomes de classe divergem entre âncora e vivo. As seis classes do protótipo dão **zero**
ocorrências no `Chat.tsx`:

```bash
for cls in jm-hist-n jm-hist-peek-n jm-conv-scrim jm-thread-q jm-thread-com jm-conv-h-m; do
  echo "$cls -> $(grep -c "$cls" resources/js/Pages/Jana/Chat.tsx)"
done     # → 0 em todas
```

…enquanto o vivo chama as mesmas coisas de `cs-count`, `cs-peek-n`, `copiloto-chat-scrim`. Procurar
pela classe do protótipo produz exatamente este falso "não tem". Isso **explica** o padrão; não
provei que foi esse o caminho, porque não tenho como ler o raciocínio de quem escreveu.

**A regra que sobrevive disso:** no eixo protótipo × tela viva, **classe CSS não é âncora**. O
protótipo e o React não compartilham vocabulário de classe por construção (um é bundle Cowork, o
outro é o DS do app), então ausência de classe mede tradução, nunca ausência de comportamento. O
que se compara é comportamento — e, quando o assunto é fidelidade visual, a porta é medição
(`design-diff --probe` nos dois renders), não `grep`.

## 2 · Os dois gaps reais

**Metadados do card.** Real. O `SbConvItem` do `Chat.tsx` renderiza três coisas: `sb-bullet`,
`sb-conv-t` (título) e o badge de `unread`. Sem preview, sem tempo, sem pessoa.

**Escopo no cabeçalho da thread.** Real. Zero ocorrências de qualquer marcador — busca por
`só sua`, `da equipe`, `compartilhada`, `conv-h` no `Chat.tsx` volta vazia.

## 3 · Os campos, dos dois lados

A âncora (`JmConversa`, no bloco que monta `.jm-thread`) lê `t.title`, `t.quando`, `t.preview`,
`t.com` e `t.escopo`. O payload atual — `ChatController::buildConversasListPayload` — manda
`id · titulo · unread · origem · status · ativa`, tipado em `ConversaResumo`
(`resources/js/Components/cockpit/shared.ts`).

| Campo da âncora | Prop hoje | De onde teria que vir |
|---|---|---|
| `preview` | não existe | `content` da última `Mensagem` (`jana_mensagens`, append-only) — nenhuma coluna guarda isso |
| `quando` | não existe | `MAX(jana_mensagens.created_at)`. ⚠️ **não** é o `iniciada_em`: ele já vem no `get()`, mas é quando a conversa nasceu, e o rótulo diz *"última em"* |
| `com` | não existe | não há origem: sem tabela de participantes, e `abort_unless($conversa->user_id === auth()->id(), 403)` em 4 pontos do `ChatController` |
| `escopo` | `status` (`ativa`\|`arquivada`) | idem — *"da equipe"* e *"compartilhada com X"* pressupõem compartilhamento inexistente |

Colunas reais de `jana_conversas` (migration `2026_04_24_000005_create_copiloto_conversas_table`,
renomeada em `2026_05_06_120000`): `id · business_id · user_id · titulo · status · iniciada_em ·
timestamps`. Não há `preview`, `ultima_mensagem_em` nem participantes.

**Formato.** A âncora não computa nada — o seed já traz string pronta. O que ela fixa é a *forma*:
`preview` é resumo de uma linha (*"4.255 títulos · top 20 concentram 47%"*), e `quando` é adaptativo
— `"09:38"` hoje, `"ontem"`, `"ter"`, `"05/mai"` mais antigo, `"agora"` no recém-criado, com o
rodapé virando `"criada agora"` nesse caso. A **derivação** não vem da âncora; é decisão nossa, e a
única fonte verdadeira é o `MAX(created_at)` das mensagens.

## 4 · O que isso faz com o escopo

- **Itens contador / peek / scrim:** nada a implementar. O correto é corrigir o `Chat.casos.md`, não
  o `Chat.tsx`. E como os três existem e não têm teste, o caminho natural é promovê-los a UC com
  teste verde no mesmo dia — **sem depender do rebake do visreg**, porque não mudam a tela.
- **`preview` + `quando`:** mudança de **backend** (`buildConversasListPayload` + agregação nas
  mensagens) antes de qualquer pixel. Um teste de payload em Pest cobre, e também não depende do
  visreg.
- **`com` + escopo-de-equipe:** bloqueados por **modelo de dados**, não por gate. É o mesmo bloqueio
  que o `Chat.charter.md` v3 já registrou para a aba `Compartilhadas`: *"modelar participantes +
  afrouxar o 403 → PR próprio e decisão [W]"*.

A parte que realmente exigia o desbloqueio do `Chat.tsx` era menor que o chip supunha — e a parte
maior nem é da tela.

## 5 · Limites honestos deste levantamento

1. **É leitura estrutural, não medição de fidelidade.** Não rodei `cowork-mirror-freshness
   --compare --check` provando o espelho `SYNC`, nem `design-diff --probe` nos dois renders. Nada
   aqui afirma "fiel ao protótipo"; as afirmações são sobre **existência de comportamento no
   código**, que é o que os comandos acima sustentam.
2. **Não toquei em nada.** `Chat.tsx` está fail-closed; o `Chat.casos.md` tem sessão viva dona
   (`whats-active` via `list_sessions`: *"Fechar os 2 anti-hooks Tier 0 restantes do Chat"*,
   worktree `hopeful-pasteur-17a810`, `isRunning: true`), e o manifesto do visreg também
   (*"Rebake da baseline visreg + incluir Jana/Chat"*, worktree `nice-lamarr-fdc982`). Editar
   qualquer um dos dois daqui seria colisão.
3. **A correção do `[BACKLOG]` pertence a quem é dono do arquivo.** Este log é o recibo para que a
   correção aconteça na fonte, não uma segunda versão da verdade ao lado dela.
