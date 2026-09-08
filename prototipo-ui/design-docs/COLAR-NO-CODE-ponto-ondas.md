# Ponto — ponte do módulo · reescrita 2026-09-06

> **Este arquivo virou ponteiro.** O doc único de 04/09 (8 frentes · 45 arquivos · RESÍDUO 1–7) foi **absorvido** pelo playbook `cowork-inbox/ponto/playbook/00-INDICE.md` (`SINCRONIZAR Ponto`, 12 threads de sessão limpa, placar derivado). Destino no `main`: `prototipo-ui/design-docs/cowork-inbox/ponto/playbook/`. Manter cópia do conteúdo aqui é cache que envelhece — e envelheceu em 2 dias (abaixo).

## O que o doc de 04/09 já tinha errado na sha `e86130722de1` (lida 2026-09-06)
- **Frente 4 (6 `casos.md`) já estava feita** — 21/21 no `main`. Não é thread.
- **Testes 16 → 44** (`*ContratoTest` para quase toda tela). O nº de UC ⛓ **não foi remedido** (`casos:report` não roda daqui) — a thread 02 começa medindo.
- **RESÍDUO 6 (copy da selfie, "LGPD Art. 9º") estava morto desde 27/08**: ADR 0383 (aceito, #6393) — sem selfie, sem biometria; base legal Art. 5º II + Art. 11. **RESÍDUO 5 (GPS ruim) também respondido** pela ADR: accuracy > 500 m recusa; geofence sinaliza.
- **Meu protótipo viola a ADR** (`ponto-mobile.jsx:38` ainda tem selfie) → thread 10 corrige o build antes de o REP-P virar pedido (thread 06).
- API REP-P: **7** rotas `abort(501)` (não 8); `Api/MobileMarcacaoController.php` existe **sem rota**.
- Pedidos de 23/08 (`ponte/COLAR-NO-CODE-ponto.md`, `_pedido-CL-ponto-teste-pratico.md`) **foram executados** (`PontoDashboardContratoTest.php`, 28 KB). A cópia `cowork-inbox/ponto-dashboard/Index.casos.md` é resíduo (thread 11).

## RESÍDUO Ponto — fila [W] (atualizada; a canônica está em `00-INDICE.md §6`)
W1–W4 fechamento (travam 04·05) · W7 ordem AFD/AFDT/AEJ (12) · **novas:** W8 `/ponto/react` fica? · W9 navegação do protótipo 13 abas × `PontoSubNav` 5+⋯ · W10 ratificar REP-P sem selfie (06). ~~W5~~ ~~W6~~ respondidas pela ADR 0383.

## Placar (render 2026-09-06, repo simulado = `main`)
`Ponto: entregue 0 de 12 · próximo 5 · pendente 4 · bloqueada 3` — **PRÓXIMO: 01 · 02 · 08 · 10 · 11.**
