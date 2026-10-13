---
id: resources-js-pages-configuracoes-contas-index-casos
casos: Contas de pagamento · /account/account
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o saldo é dinheiro; a conta de outro negócio na tela, ou um saldo diferente do da Blade, é incidente.
owner: wagner
last_run: "2026-10-13"
---

# Casos de Uso & Aceite — Contas de pagamento

> Thread `sistema/playbook/06` (Contas). Derivados do `AccountController` e da Blade `account/index`
> (RUNBOOK-contas, `contas-parity.md`) — não do `Index.tsx`.
> Teste: [`tests/Feature/Configuracoes/ContasContratoTest.php`](../../../../../tests/Feature/Configuracoes/ContasContratoTest.php),
> lane `acessos-pest.yml` (MySQL). A Blade está travada em `ContasBaselineTest`.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-CONTA-01 · Com a flag ligada, a tela abre em React
- **Persona:** quem confere o caixa e as contas bancárias do negócio.
- **Aceite:** Dado `account.access` e a flag `useV2ConfiguracoesContas` ligada · Quando faço `GET /account/account` como o
  browser faz (`X-Inertia` **e** `X-Requested-With`) · Então renderiza **`Configuracoes/Contas/Index`**.
- **Status: 🧪**

## UC-CONTA-02 · Com a flag desligada, ou com `?legacy=1`, a Blade continua
- **Aceite:** Dado a flag desligada · Quando faço `GET /account/account` · Então a resposta é a Blade `account.index` ·
  E com a flag ligada e `?legacy=1` também.
- **Status: 🧪**

## UC-CONTA-03 · Minhas contas, com o mesmo saldo da Blade · `[T0]` `[valor]`
- **Aceite:** Dado uma conta minha com créditos de 1000,50 e 99,75, débito de 200,25 e um crédito apagado de 999,00, uma
  conta encerrada minha e uma conta de outro negócio · Quando a lista carrega (prop deferida `contas`) · Então vejo a
  minha com `saldo` 900.00 e `saldo_texto` igual ao `num_f` da Blade para o mesmo número · E a encerrada vem com
  `encerrada` verdadeiro · E não vejo a alheia.
- **Status: 🧪**

## UC-CONTA-04 · Sem acesso a todos os locais, só as contas dos meus locais · `[T0]`
- **Aceite:** Dado um usuário sem `access_all_locations`, com permissão só no local L, e L com a conta A habilitada nas
  formas de pagamento · Quando a lista carrega · Então vejo A · E não vejo a conta B, que nenhum local meu usa.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Cadastrar e editar conta no drawer, com saldo de abertura em texto pt-BR para o `num_uf` (F3-2).
- [BACKLOG] Depositar, transferir, encerrar e reativar pela tela nova (F3-2, regra mestre de valor).
- [BACKLOG] Total do rodapé e KPIs de saldo — decisão [W] (`contas-parity` #9).

## Trilha do tempo
- 2026-10-13 · [CL] criado com a F3 da thread `sistema/playbook/06`.
