---
sessao: "_saida-07"
thread: "07 · CONN-O7 · quem está usando cada credencial"
dono: "[CL]"
data: 2026-10-05
base_lida: wagnerra23/oimpresso.com@main cb1fe1d6f4
---
# _saida-07

## Placar
**Entregue 1 de 1** — a única prova da thread (`execucao`: UC-CONN-21 verde) está medida abaixo.

## Entregue (PR #8657)
- `Modules/Connector/Http/Controllers/ClientController.php` — cada client da lista ganha
  `tokens: [{user_name, last_used_at, expires_at}]` (top 5, do uso mais recente) e
  `tokens_resto` (contagem dos demais). Lê `oauth_access_tokens` numa consulta só para todos
  os clients da página; nenhuma tabela nova.
  - Só tokens **não revogados e não vencidos**.
  - **Tier 0:** além do client ser do negócio da sessão (já era), o **usuário do token** também
    tem de ser. Client de senha aceita login de qualquer usuário; token aberto por usuário de
    outro negócio com o mesmo client não aparece — nem nome, nem contagem no resto.
  - `last_used_at` é o `updated_at` do token, o mesmo sinal que a coluna "Tokens 24 h" já usa.
- Tela (`Api/Index.tsx` + `_components/QuemUsa.tsx` + `_components/quemUsaTexto.ts`):
  - Kebab do client ganha "Ver quem usa" → drawer lateral (Sheet) com colaborador, último uso,
    vencimento e "e mais N". Sem acesso aberto, estado vazio diz que excluir não derruba ninguém.
  - A confirmação de excluir passa a **nomear** quem perde o acesso ("Perdem o acesso na hora:
    Ana, Bruno e mais 3 acessos"), não só quantos usaram em 24 h.
  - **Nenhuma aba nova** — conferido o `?aba=` (clients/docs/saude/modulo, #8379); o drawer fica
    dentro da aba API clients.
- `Index.casos.md` ganha **UC-CONN-21** (`should` `[T0]`); `Index.charter.md` documenta as duas
  props novas.

## Prova — lane `connector-pest.yml` (MySQL)
Run [37313923243](https://github.com/wagnerra23/oimpresso.com/actions/runs/37313923243) no head
`37615838a5` do PR, job "PHP / Pest (Connector · MySQL)": **196 passed (736 assertions), 0 skipped**.
Os dois casos novos aparecem no log com ✓:
- `quem usa lista so acessos abertos de usuarios do negocio` — ordem por uso, revogado e vencido
  fora, usuário do tenant 99 fora (tenant de teste 98, ADR 0358).
- `quem usa mostra cinco e conta o resto` — 7 tokens → 5 + resto 2; client sem uso → `[]` e 0.

Controle: último run da mesma lane em `main` (36868265003) = 194 passed (725 assertions). O delta
+2 testes / +11 assertions é exatamente o dos dois casos novos — o teste executou, não pulou.

**Depois do merge com a thread 08** (#8667 entrou no `main` com o PR aberto; conflito resolvido
mantendo os dois lados): run manual
[37327088955](https://github.com/wagnerra23/oimpresso.com/actions/runs/37327088955) no head
`501289ca66`: **199 passed (748 assertions)**, com os dois casos de UC-CONN-21 e o de UC-CONN-27
da thread 08 aparecendo ✓ no log.

## Pendente / [W]
- **Contrato visual:** as âncoras `quem-usa` e `confirm-quem-perde` não entraram em
  `governance/design/contracts/connector-api.contract.json`. Aprovação F1.5 do drawer e entrada
  no contrato são do [W]. Nenhuma baseline de visual-regression regravada (ADR 0409).
- `UC-CONN-21` no casos segue `🧪` — o veredito é do CI; trocar para ✅ fica para quem fechar o
  casos com o run em mãos.
- O id UC-CONN-21 da cópia do Cowork (`cowork-inbox/connector/Index.casos.md`) descreve outro
  caso (primeira vez explica a credencial). No repo esse caso é `[BACKLOG]` sem id; o 21 ficou com
  "quem usa", como manda o `PEDIDO-CL-connector-trio.md`. Errata para a cópia do Cowork.

## NÃO MEDI
- Typecheck e ESLint locais (sem `node_modules` no worktree): ficaram com o CI. O ESLint pegou
  `react-refresh/only-export-components` no 1º commit e foi consertado separando o helper.
- Screenshot do drawer em produção (só depois do merge).
