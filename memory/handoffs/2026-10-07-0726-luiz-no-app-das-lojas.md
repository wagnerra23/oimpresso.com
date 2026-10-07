---
date: "2026-10-07"
time: "07:26 BRT"
slug: luiz-no-app-das-lojas
tldr: "Luiz [L] entrou no app das lojas (repo oimpresso-app) como executor pareado: TEAM.md §3.3.2 registrado e corrigido, main do app protegido, Luiz colaborador com a 1ª tarefa (#75); Felipe e Maiara com convite pendente e Actions do app parado por cobrança."
prs: [8710, 8737, 8797]
decided_by: [W]
next_steps:
  - "Felipe e Maiara aceitarem o convite ao oimpresso-app"
  - "[W] regularizar a cobrança do GitHub (Actions do oimpresso-app parado desde 2026-10-02)"
  - "Luiz começar a oimpresso-app#75"
---

# Luiz no app das lojas (oimpresso-app)

## Estado MCP no momento

`cycles-active` e `my-work` devolveram **"Too Many Attempts."** (rate limit do MCP) no fechamento —
snapshot MCP **não medido**. Estado do GitHub medido por `gh` em 2026-10-06 ~15:05 UTC, logo abaixo.

## O que aconteceu

- [W] pediu para pôr o Luiz no "projeto do mobile". O 1º registro (#8710, mergeado) apontou para o
  Expo de `mobile/` — **errado**: o app das lojas é o repo privado `wagnerra23/oimpresso-app`
  (Capacitor; D1 em `docs/lojas-app/DECISOES.md` tirou o Expo das lojas). Corrigido em #8737.
- `main` do `oimpresso-app` estava **sem proteção**. Ativado: PR + 1 aprovação, review velha cai em
  push novo, sem force-push/deleção, `enforce_admins: false`.
- Convidados com `write`: `LuizWr2`, `felipewr2-cell`, `SupportWR` (Maiara). Luiz aceitou em 06/10.
- 1ª tarefa do Luiz, combinada com a sessão "Coordenar app das lojas": **oimpresso-app#75**, teste
  Vitest de `src/telas/Pedidos.tsx` (só leitura; formatação do total, dado fictício). Atribuída a ele
  em 06/10 14:38 UTC.
- Automação de atribuição (oimpresso-app#76) foi mergeada e removida 2 min depois (#79) por outra
  sessão, já com a #75 atribuída à mão. Nunca rodou: Actions do app parado por cobrança.
- #8797 registra no `TEAM.md` que o Luiz aceitou (mergeado 06/10 15:03 UTC, 114 checks ok).

## Persistência

Git: `TEAM.md` §3.3.2 (#8710 → #8737 → #8797). GitHub: proteção + colaboradores + issue #75 no app.

## Próximos passos pra retomar

`gh api repos/wagnerra23/oimpresso-app/invitations --jq '[.[].invitee.login]'` — se vazio, Felipe e
Maiara entraram e o [F] assume a revisão dos PRs do [L] no app.

## Lições catalogadas

1. **Projeto ≠ pasta com o nome.** "Mobile" casou com `mobile/` no repo do site; o dono do tema era
   `docs/lojas-app/DECISOES.md`. Registro errado chegou ao `main` (#8710) antes de eu ler a D1.
2. **Li a lista de convites como a de colaboradores** (saída de duas chamadas sem rótulo) e afirmei
   que o Luiz tinha aceitado. Corrigido no mesmo dia; consulta separada e rotulada dali em diante.
3. **Afirmei sem medir que o plano grátis não permitia proteção em repo privado** — o GitHub aceitou.

## Pointers

`TEAM.md` §3.3.2 · `docs/lojas-app/DECISOES.md` (D1, D12) · oimpresso-app#75 / #76 / #79.
