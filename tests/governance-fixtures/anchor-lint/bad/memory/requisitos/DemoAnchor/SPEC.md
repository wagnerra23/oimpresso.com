# SPEC — DemoAnchor (fixture RUIM do gate-selftest GT-G6 · catraca anchor-lint)

### US-DA-001 — Tela demo com anchor MORTO

**Implementado em:** `Modules/DemoAnchor/NaoExiste.md` · verificado@abc1234 (2026-06-21)

Anchor preenchido mas o segmento-path NÃO existe no sandbox → classify() devolve
anchored_dead (mentira detectável · ADR 0273 §2). `anchor-lint --check` DEVE sair 1 e
imprimir a lápide 💀. Se passar (exit 0), a catraca parou de morder. Fictício — zero PII.
