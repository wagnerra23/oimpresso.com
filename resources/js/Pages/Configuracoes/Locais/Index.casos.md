---
id: resources-js-pages-configuracoes-locais-index-casos
casos: Locais comerciais · /business-location
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: cada local carrega CNPJ e fatura próprios; local de outro negócio, ou que o usuário não pode acessar, não pode aparecer.
owner: wagner
last_run: "2026-10-07"
---

# Casos de Uso & Aceite — Locais comerciais

> Thread `sistema/playbook/04`, tela 3 de 3. Derivados do `BusinessLocationController` e da Blade `business_location/*`
> (RUNBOOK-locais, `locais-parity.md`) — não do `Index.tsx`.
> Teste: [`tests/Feature/Configuracoes/LocaisContratoTest.php`](../../../../../tests/Feature/Configuracoes/LocaisContratoTest.php),
> lane `acessos-pest.yml` (MySQL). A Blade está travada em `LocaisBaselineTest`.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-LOCAL-01 · Com a flag ligada, a tela abre em React
- **Persona:** quem cadastra as filiais do negócio.
- **Aceite:** Dado `business_settings.access` e a flag `useV2ConfiguracoesLocais` ligada · Quando faço
  `GET /business-location` como o browser faz (`X-Inertia` **e** `X-Requested-With`) · Então renderiza
  **`Configuracoes/Locais/Index`**.
- **Status: 🧪**

## UC-LOCAL-02 · Com a flag desligada, a Blade continua
- **Aceite:** Dado a flag desligada · Quando faço `GET /business-location` · Então a resposta é a Blade `business_location.index`.
- **Status: 🧪**

## UC-LOCAL-03 · A lista é só do meu negócio e só dos locais que posso ver · `[T0]`
- **Aceite:** Dado um local meu, um de outro negócio e, para um usuário sem `access_all_locations`, um segundo local
  meu sem a permissão `location.<id>` · Quando a lista carrega (prop deferida `locais`) · Então o alheio nunca aparece ·
  E o usuário restrito vê só o local liberado.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Cadastrar e editar no drawer, com CNPJ/IE/IM, tabela de preço, fatura e formas de pagamento (F3-2).
- [BACKLOG] Quota de locais do pacote esgotada avisa antes de abrir o cadastro.

## Trilha do tempo
- 2026-10-07 · [CL] criado com a F3 da thread `sistema/playbook/04`.
