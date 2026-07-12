---
name: "Smoke test grace — reference divergente proposital"
description: "Arquivo de teste pra provar que reference divergente gera WARNING e não RED no memory-schema-gate (grace)."
type: reference
---

# Smoke test — grace mode

Este arquivo TEM frontmatter mas está DIVERGENTE de propósito: falta a key
required `authority` do reference.schema.json. Em STRICT isso seria erro que
bloqueia merge; em GRACE deve virar WARNING e o job fica VERDE.

Descartável — este PR não será mergeado.
