---
id: modules-whatsapp-resources-js-pages-atendimento-jana-templates-casos
casos: Bot Jana + templates HSM · /atendimento/canais/jana-templates
irmaos: JanaTemplates.charter.md (lei)
tecnica: Caso de uso = narrativa do admin + critério de aceite verificável (Dado/Quando/Então)
por_que: o nome do template HSM é o que dispara a mensagem de "OS pronta" e "boleto vence" para o cliente; gravar no business errado manda aviso com o template de outra empresa, e mexer no driver daqui desconecta o canal.
owner: wagner
last_run: "2026-09-30"
---

# Casos de Uso & Aceite — Bot Jana + templates HSM

> US-WA-070 · ADR 0093 (multi-tenant Tier 0) · ADR 0135
>
> UCs derivados do **charter** (`JanaTemplates.charter.md` — Goals · Non-Goals · Automation
> Anti-hooks) e do `SettingsController::show/update` só para confirmar. **Não derivados do `.tsx`.**
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC e passa (manifesto não
> regravado) · ⬜ não verificado · ❌ quebrou.
>
> **Onde roda:** `tests/Feature/Whatsapp/AtendimentoMacrosJanaTemplatesContratoTest.php`, lane
> sqlite (`.github/ci-sqlite-pest.list`). Fora de sqlite o teste pula — skip não é verde.

---

## UC-JTPL-01 · A tela mostra o bot e os 4 templates da empresa
- **Persona:** admin do business abrindo `/atendimento/canais/jana-templates`.
- **Aceite:** Dado que só outro business tem configuração · Quando abro a tela · Então ela vem vazia (não mostra a dele); Dado a configuração do meu business · Então vêm exatamente bot ligado/desligado e os 4 nomes de template, sem campo de driver nem credencial.
- **Teste:** `tests/Feature/Whatsapp/AtendimentoMacrosJanaTemplatesContratoTest.php` — `UC-JTPL-01 — a tela mostra o bot e os 4 templates do business; sem configuração, vem vazia`.
- **Regressão que defende:** charter Goals (toggle + 4 templates) e UX anti-pattern "mostrar fields de driver/credenciais nessa tela".
- **Status: 🧪** — passou na lane sqlite do CI (run 36801631015, 2026-10-01); ✅ quando `casos:results` regravar o manifesto.

---

## UC-JTPL-02 · Salvar grava só o bot e os nomes dos templates
- **Persona:** admin trocando os nomes dos templates aprovados na Meta.
- **Aceite:** Dado a configuração com driver `meta_cloud` e um telefone · Quando salvo bot + 4 templates (e o pedido traz também `driver` e `display_phone`) · Então os 5 campos são gravados e o driver e o telefone continuam os mesmos.
- **Teste:** `tests/Feature/Whatsapp/AtendimentoMacrosJanaTemplatesContratoTest.php` — `UC-JTPL-02 — salvar grava só o bot e os 4 nomes de template, sem tocar driver nem telefone`.
- **Regressão que defende:** Automation Anti-hooks do charter "não dispara conexão de driver" e "não modifica config de canal".
- **Status: 🧪** — passou na lane sqlite do CI (run 36801631015, 2026-10-01); ✅ quando `casos:results` regravar o manifesto.

---

## UC-JTPL-03 · Salvar cria a configuração da empresa sem tocar a de outra
- **Persona:** admin de um business que ainda não tinha configuração.
- **Aceite:** Dado que só o business 99 tem configuração · Quando o business 98 salva · Então nasce a configuração do 98 com o que foi salvo e a do 99 fica igual.
- **Teste:** `tests/Feature/Whatsapp/AtendimentoMacrosJanaTemplatesContratoTest.php` — `UC-JTPL-03 — salvar no business sem configuração cria a dele e não altera a de outro business (Tier 0)`.
- **Regressão que defende:** **Tier 0** (ADR 0093) — Goal do charter "global scope `business_id`".
- **Status: 🧪** — passou na lane sqlite do CI (run 36801631015, 2026-10-01); ✅ quando `casos:results` regravar o manifesto.

---

## Backlog (prosa, sem UC — sem teste que cite)

- `[BACKLOG]` A seção "Métricas vivas (Pest GUARD)" do charter cita `JanaTemplatesControllerTest` e `JanaTemplatesRedirectTest`, que **não existem** no repo (busca em `Modules/Whatsapp/Tests` e `tests/`: zero). E o redirect 301 de `GET /whatsapp/settings` que o charter descreve não é mais o comportamento: desde US-WA-310 essa rota renderiza o wizard `Whatsapp/Settings` (só o `PUT` redireciona). O charter não está no prefixo desta thread — fica para quem for dono dele.
