# cowork-inbox/notificacoes/ — pacote pronto pro Code

Tela: **Modelos de notificação** (`/notification-templates`) · F1 [CC] 2026-08-19 · autorizado por [W] no mesmo dia (tradução PT-BR + geração dos arquivos).
Contexto e achados: `../NOTIFICACOES-F1-2026-08-19.md`.

## Destino de cada arquivo

| Arquivo daqui | Vai para | Observação |
|---|---|---|
| `Index.charter.md` | `resources/js/Pages/NotificationTemplate/Index.charter.md` | canon vivo — 11 regras, estados, contrato visual, anti-patterns |
| `Index.casos.md` | `resources/js/Pages/NotificationTemplate/Index.casos.md` | 25 UC Dado/Quando/Então — é o arquivo que o `casos:check` exige |
| `notificacoes.contract.json` | `prototipo-ui/contrato/notificacoes.contract.json` | 6 âncoras + copy literal; roda com `npm run contrato:check -- <arquivo>` |
| `2026_08_19_000000_traduzir_notification_templates_pt_br.php` | `database/migrations/` | traduz só o que ainda é o seed inglês; `down()` reverte |
| `NotificationTemplateTest.php` | `tests/Feature/` | 17 casos; ajustar helpers de tenant para os do `TestCase` do projeto |
| `PATCHES.md` | — (guia de aplicação) | P1 seed PT-BR · P2 whitelist de `template_for` · P3 validação `cc/bcc` · P4 sanitizar `email_body` · P5 toast de sucesso · P6 rota de teste · P7 menu no grupo SISTEMA · P8 `new_booking` sob módulo |
| `../../prototipo-ui/cowork/notificacoes/notificacoes-page.{jsx,css}` | `prototipo-ui/cowork/notificacoes/` | build do F1 (SSOT do design) |

> `.md` **nunca** dentro de `prototipo-ui/cowork/` — R1 do `cowork-ssot-guard.mjs`.

## Ordem sugerida de PRs

1. **PR-1 doc** — charter + casos + contrato (destrava `casos:check` e `contrato:check`).
2. **PR-2 prova** — `NotificationTemplateTest.php` contra o código atual: os testes de P2/P3/P4 entram falhando (`->todo()` ou marcados) e provam os achados.
3. **PR-3 verdade da copy** — P1 (seed PT-BR) + migration. Confere na base do piloto quantos modelos foram traduzidos e quantos preservados.
4. **PR-4 segurança** — P2 (whitelist) + P3 (validação) + P4 (sanitização). É o PR que fecha os testes de PR-2.
5. **PR-5 uso** — P5 (toast) + P6 (rota de teste com throttle).
6. **PR-6 limpeza** — P7 (menu) + P8 (`new_booking` sob módulo instalado).

## Checklist pós-merge

- [ ] `npm run casos:check` e `contrato:check` verdes para `NotificationTemplate/Index`.
- [ ] `screen-grade:report` gera o scorecard (é o que falta pro trio virar ✅ em `memory/governance/prototipo-readiness.json`).
- [ ] Rodar a migration na base do piloto (ROTA LIVRE) e registrar a contagem traduzido/preservado.
- [ ] Conferir um e-mail real de `new_sale` depois do P4 (sanitização não pode comer `<a href>` nem `<ul>`).

## Não verificado

- Se a base do piloto tem modelos editados à mão (a migration preserva, mas ninguém contou ainda).
- Comportamento do WhatsApp: o F1 assume que o canal usa a conexão do Atendimento; não li o gateway neste turno.
- `send_ledger` no backend (D5): hoje só a view esconde os campos.
