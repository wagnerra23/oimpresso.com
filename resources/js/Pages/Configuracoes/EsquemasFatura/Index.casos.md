---
id: resources-js-pages-configuracoes-esquemasfatura-index-casos
casos: Esquemas de fatura · /invoice-schemes
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o esquema numera as notas; o de outro negócio na tela, ou um contador diferente do gravado, confunde a numeração fiscal.
owner: wagner
last_run: "2026-10-07"
---

# Casos de Uso & Aceite — Esquemas de fatura

> Thread `sistema/playbook/05`, tela 3 de 3. Derivados do `InvoiceSchemeController`, da Blade `invoice_scheme/*` e da
> conta de numeração do `TransactionUtil` (RUNBOOK-esquemas-fatura, `esquemas-fatura-parity.md`) — não do `Index.tsx`.
> Teste: [`tests/Feature/Configuracoes/EsquemasFaturaContratoTest.php`](../../../../../tests/Feature/Configuracoes/EsquemasFaturaContratoTest.php),
> lane `acessos-pest.yml` (MySQL). A Blade está travada em `EsquemasFaturaBaselineTest` e o isolamento em `EsquemaFaturaTenantTest`.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-ESQF-01 · Com a flag ligada, a tela abre em React
- **Persona:** quem configura a numeração das notas de cada local.
- **Aceite:** Dado `invoice_settings.access` e a flag `useV2ConfiguracoesEsquemasFatura` ligada · Quando faço
  `GET /invoice-schemes` como o browser faz (`X-Inertia` **e** `X-Requested-With`) · Então renderiza
  **`Configuracoes/EsquemasFatura/Index`**, com os tipos de numeração.
- **Status: 🧪**

## UC-ESQF-02 · Com a flag desligada, a Blade continua
- **Aceite:** Dado a flag desligada · Quando faço `GET /invoice-schemes` · Então a resposta é a Blade `invoice_scheme.index`.
- **Status: 🧪**

## UC-ESQF-03 · Esquemas e layouts do meu negócio, com o contador do banco · `[T0]`
- **Aceite:** Dado um esquema meu (padrão, anual, prefixo "OS", início 100, 7 emitidas), um esquema de outro negócio, um
  layout meu usado pelo meu local e um layout alheio · Quando a lista carrega (prop deferida `fatura`) · Então vejo o meu
  esquema primeiro, padrão, com `emitidas` 7 e o prefixo exibido "OS<ano>-" · E o meu layout com o meu local em `locais` ·
  E não vejo o esquema nem o layout alheios.
- **Status: 🧪**

## UC-ESQF-04 · O drawer cadastra a numeração e edita o nome sem mexer nela
- **Aceite:** Dado o corpo do drawer (anual, prefixo "OS", início 100, 6 dígitos) · Quando cadastro · Então o esquema é
  gravado assim · E, com 12 notas já emitidas, editar só o nome a partir da prop mantém formato, prefixo, início, dígitos
  e o contador de emitidas.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Editor de layout de fatura na tela nova — é o `InvoiceLayoutController`, fora do prefixo da thread.

## Trilha do tempo
- 2026-10-07 · [CL] criado com a F3 da thread `sistema/playbook/05`.
