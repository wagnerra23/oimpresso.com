---
sessao: "_saida-16"
thread: "16 · SPEC-cc-sessions: alinhar ao charter (cc.read.team = só as próprias) (D12)"
dono: "[CL]"
data: 2026-10-09
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main ff67c8356
---
# _saida-16

## Entregue
Decisão D12 ([W] 2026-10-07): *"só as próprias sessões"*.

`memory/requisitos/Jana/SPEC-cc-sessions.md` dizia que `cc.read.team` via as sessões do time
(persona Felipe/Maiara "busca cross-dev", Luiz "aprende com sessões dos outros", e o comentário
da permissão "ver sessões do time"). O código nunca fez isso: `CcSessionsController` (linha ~275,
busca) filtra `s.user_id = eu` sempre que falta `jana.cc.read.all`, e o charter diz o mesmo.

Corrigidos os 4 trechos (tabela de personas, definição das permissões, DoD da busca e o bloco
§6), com uma nota datada que preserva o que a SPEC dizia antes de 2026-10-07. Nenhum código mudou
(`Modules/` está no `nao_toca`).

## Antes → depois
| trecho | antes | depois |
|---|---|---|
| definição | `cc.read.team` = ver team mas não admin | `cc.read.team` = abre a tela e vê só as próprias sessões |
| persona Felipe/Maiara | Busca cross-dev: "como Wagner fez X mês passado" | Abre a tela e busca nas **próprias** sessões (D12) |
| DoD da busca | junior não vê outras sessões a menos que tenha `cc.read.team` | sem `cc.read.all`, só as próprias; `cc.read.team` não amplia |
| §6 | `// ver sessões do time (Felipe, Maiara)` | `// abre a tela; vê só as PRÓPRIAS sessões` |
