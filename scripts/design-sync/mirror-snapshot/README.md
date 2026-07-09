# mirror-snapshot — último estado conhecido do espelho vivo

Este diretório guarda o **snapshot commitado** do `colors_and_type.css` do projeto de design
vivo no claude.ai/design ("Office Impresso — Design System", `019dd02f-…`).

## Por que existe

O sentinela [`ds-mirror-drift`](../../governance/ds-mirror-drift.mjs) compara os tokens do git
(`resources/css/tokens/_generated-*.css`) contra o espelho e alerta quando separam. Mas o **CI do
GitHub Actions não tem login claude.ai** → não pode chamar `DesignSync get_file`. Então o CI compara
contra **este snapshot versionado** (o último estado conhecido do espelho), não contra o espelho vivo.

## Como semear / refrescar

O snapshot é um produto do **push** (re-espelho git→espelho). Ao rodar
[`.claude/runbooks/design-sync-push.md`](../../../.claude/runbooks/design-sync-push.md) (passo 5):

```bash
# depois de empurrar o colors_and_type.css montado pro espelho:
cp <scratchpad>/mirror-colors_and_type.css scripts/design-sync/mirror-snapshot/colors_and_type.css
node scripts/governance/ds-mirror-drift.mjs --update-baseline   # trava o piso novo (idealmente 0)
```

Enquanto `colors_and_type.css` não existir aqui, o sentinela roda **advisory** ("snapshot ausente",
exit 0) — sem bloquear. A promoção a `--enforce`/required só depois de o loop estabilizar
(política required = só Tier-0).

O diff contra o espelho **vivo** (via `DesignSync get_file`) roda local/cron — o sentinela aceita
qualquer arquivo via `--snapshot <css>`.
