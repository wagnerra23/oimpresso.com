# fixture `charter-live-frescor` — o hit do ledger ainda está dentro da janela?

Par boa/ruim da catraca `charter-live-frescor` do [`gate-selftest`](../../../scripts/governance/gate-selftest.mjs).

**O defeito que ela defende** (medido 2026-08-23): `governance/route-hits.json` declara
`janela_dias: 30`, e o `charter-live-signal` **sempre ignorou** — bastava `hits > 0`, para
sempre. No dia da medição, a `ultima_data` mais nova tinha **29 dias**: o ledger expirava no
dia seguinte e 4 charters seguiriam `live_ok` indefinidamente. O `cron-watchdog` vigia o
arquivo, mas com limite **genérico de 60d** — o dobro da janela que o próprio ledger declara.
Entre o dia 30 e o 60 o ledger está fora da própria validade e ninguém diz nada.

| fixture | `ultima_data` | veredito |
|---|---|---|
| `good` | `2099-01-01` | dentro da janela → exit 0 |
| `bad`  | `2020-01-01` | fora da janela → exit 1, acusação `🔴 VENCIDO` |

**Por que a data do `good` é no futuro, e não "recente":** fixture com data recente APODRECE —
ela vira `vencida` sozinha ao passar do 30º dia, e a catraca boa avermelharia sem ninguém ter
mexido em nada. Data no futuro torna o par hermético: o veredito não depende do dia em que o
CI roda. É o mesmo motivo pelo qual as outras fixtures deste diretório não carregam "hoje".

⚠️ A catraca exercita `--check-frescor`, que é **opt-in**. O `--check` normal continua com o
veredito de antes (a fixture `bad` sai **0** nele) — mudar o veredito de um gate que já roda
é flip [W], não efeito colateral de PR (proibicoes.md §Sempre-fazer #6).
