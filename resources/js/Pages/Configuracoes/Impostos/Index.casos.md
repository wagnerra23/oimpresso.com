---
id: resources-js-pages-configuracoes-impostos-index-casos
casos: Impostos · /tax-rates
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a alíquota entra no imposto da venda; a de outro negócio na tela, ou um número diferente do gravado, é incidente.
owner: wagner
last_run: "2026-10-07"
---

# Casos de Uso & Aceite — Impostos

> Thread `sistema/playbook/05`, tela 1 de 3. Derivados do `TaxRateController` e da Blade `tax_rate/*`
> (RUNBOOK-impostos, `impostos-parity.md`) — não do `Index.tsx`.
> Teste: [`tests/Feature/Configuracoes/ImpostosContratoTest.php`](../../../../../tests/Feature/Configuracoes/ImpostosContratoTest.php),
> lane `acessos-pest.yml` (MySQL). A Blade está travada em `ImpostosBaselineTest`.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-IMPOS-01 · Com a flag ligada, a tela abre em React
- **Persona:** quem cadastra os impostos que a venda aplica.
- **Aceite:** Dado `tax_rate.view` e a flag `useV2ConfiguracoesImpostos` ligada · Quando faço `GET /tax-rates` como o
  browser faz (`X-Inertia` **e** `X-Requested-With`) · Então renderiza **`Configuracoes/Impostos/Index`** · E `pode` reflete
  as permissões de criar, editar e excluir.
- **Status: 🧪**

## UC-IMPOS-02 · Com a flag desligada, a Blade continua
- **Aceite:** Dado a flag desligada · Quando faço `GET /tax-rates` · Então a resposta é a Blade `tax_rate.index`.
- **Status: 🧪**

## UC-IMPOS-03 · Alíquotas e grupos do meu negócio, com o número gravado · `[T0]` `[valor]`
- **Aceite:** Dado PIS 1,65 e COFINS 7,6 meus num grupo PIS+COFINS 9,25, e uma alíquota de outro negócio · Quando a lista
  carrega (prop deferida `impostos`) · Então vejo as minhas com `aliquota` igual à do banco (1.65, 7.6), as duas marcadas
  `em_grupo`, o grupo com 9.25 e a composição "PIS" + "COFINS" · E não vejo a alheia.
- **Status: 🧪**

## UC-IMPOS-04 · O percentual do drawer chega igual ao banco · `[valor]`
- **Persona:** quem cadastra "ICMS 18%" ou corrige "COFINS 7,60".
- **Aceite:** Dado o texto que o drawer monta (pt-BR, sem separador de milhar: `"1,65"`, `"7,60"`, `"18,00"`, `"1234,50"`,
  `"0,1250"`) · Quando cadastro · Então o banco guarda o mesmo número que o `num_uf` devolve para o texto (dois caminhos) ·
  E editar só o nome, reenviando o percentual, não muda o número.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Editar grupos — depende do conserto do `GroupTaxController` (decisão [W]).

## Trilha do tempo
- 2026-10-07 · [CL] criado com a F3 da thread `sistema/playbook/05`.
