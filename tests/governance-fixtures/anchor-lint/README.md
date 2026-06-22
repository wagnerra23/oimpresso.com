# Fixtures — anchor-lint (GT-G6 · ADR 0297 SA-A2-bis)

Par good/bad pro `gate-selftest.mjs` provar que o `anchor-lint --check` **morde**.

- `good/` — tela VIVA: `VivaController` referenciado nas rotas renderiza `SelftestAnchor/Viva`; `Testado em:` aponta teste existente → exit 0.
- `bad/` — tela ZUMBI: `ZumbiController` renderiza `SelftestAnchor/Zumbi` mas só é citado em comentário/redirect (não referenciado) → `anchored_zombie`; + `Testado em:` com teste-fantasma → exit 1, acusação "tela DESLIGADA".

Cada fixture é um mini-repo (memory/requisitos + resources/js/Pages + Modules) copiado pra sandbox temp pelo runner, que roda o `anchor-lint.mjs` REAL por cima. Não toca o repo real.
