---
id: modules-fisio-pages-fisio-fiscal-index-casos
casos: Fisio · Documentos fiscais do dia · /fisio/fiscal
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do fisioterapeuta + critério de aceite verificável (Dado/Quando/Então)
por_que: o recibo do Receita Saúde é obrigatório para o profissional pessoa física desde 2025 (IN RFB 2.240/2024) e é emitido no dia do pagamento; pagamento sem recibo é risco fiscal do profissional.
owner: wagner
last_run: "2026-11-23"
last_run_ci: "_pendente_ — o veredito entra quando a lane MySQL do Fisio rodar FisioFiscalFilaTest."
related_us: [US-FISIO-023]
---

# Casos de Uso & Aceite — Fisio · Documentos fiscais do dia (`/fisio/fiscal`)

> **Âncora:** SPEC do Fisio §"F3 · Dinheiro" (US-FISIO-023), etapa F3 §3.5 e §3.6, e o protótipo `FiscalFila.dc.html`. Os UCs não derivam do `.tsx`.
>
> **Status:** ✅ passa (manifesto) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou. **O veredito é da lane** (G-7).

## Rastreabilidade

| UC | O que defende | Peso | Teste |
|---|---|---|---|
| UC-FFI-01 | a fila traz só pagamento de paciente do dia | must | `FisioFiscalFilaTest` |
| UC-FFI-02 | sem CPF aparece "falta CPF"; CPF inteiro nunca vai à tela | must | `FisioFiscalFilaTest` |
| UC-FFI-03 | marcar enviado é idempotente e não copia valor | must | `FisioFiscalFilaTest` |
| UC-FFI-04 | sem `fisio.editar` vê, não marca (403) | must | `FisioFiscalFilaTest` |
| UC-FFI-05 | pagamento de outra empresa não entra nem aceita marca | must | `FisioFiscalFilaTest` |
| UC-FFI-06 | marcar "retificar" em recibo enviado: idempotente, sem copiar valor, só na empresa, só com `fisio.editar` | must | `FisioFiscalFilaTest` |

---

## UC-FFI-01 · Pagamento de paciente entra na fila · `must`
- **Persona:** fisioterapeuta autônomo fechando o dia.
- **Aceite:** Dado um pagamento hoje de venda a um paciente e outro de venda a um cliente que não é paciente · Quando abre a fila · Então só o do paciente aparece, pendente.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioFiscalFilaTest.php`
- **Status: 🧪**

## UC-FFI-02 · Falta CPF do pagador `[T0]` · `must`
- **Aceite:** Dado pagamento de paciente sem CPF no contato · Quando abre a fila · Então o item diz que falta CPF; com CPF, a tela recebe só os 2 últimos dígitos.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioFiscalFilaTest.php`
- **Status: 🧪**

## UC-FFI-03 · Marcar enviado · `must`
- **Aceite:** Quando marca o mesmo recibo duas vezes · Então fica uma marca só, com o último número; a tabela do Fisio não tem valor nem data de pagamento.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioFiscalFilaTest.php`
- **Status: 🧪**

## UC-FFI-04 · Só quem edita marca · `must`
- **Aceite:** Dado usuário com `fisio.access` sem `fisio.editar` · Quando tenta marcar · Então 403 e nenhuma marca nasce; a fila abre.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioFiscalFilaTest.php`
- **Status: 🧪**

## UC-FFI-05 · Outra empresa não entra `[T0]` · `must`
- **Aceite:** Dado pagamento de paciente da 99 · Quando a 98 abre a fila ou tenta marcar · Então o pagamento não aparece e a marca dá 404; para a 99 ele aparece.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioFiscalFilaTest.php`
- **Status: 🧪**

## UC-FFI-06 · Marcar para retificar `[T0]` · `must`
- **Persona:** fisioterapeuta autônomo que percebeu erro num recibo já enviado ao Receita Saúde (pagador ou valor errado).
- **Fonte:** SPEC do Fisio, US-FISIO-023 (`fisio_health_receipts` com status `pendente`, `enviado`, `retificar`). O protótipo `FiscalFila.dc.html` só desenha "Marquei como enviado"; a ação "Precisa retificar" segue o mesmo padrão de botão da linha.
- **Aceite:** Dado um recibo marcado como enviado · Quando clica em "Precisa retificar" (mesma rota da marca, `marca=retificar`) duas vezes · Então fica uma marca só, `retificar`, com o número do recibo mantido e nenhum valor copiado; marcar enviado de novo volta a `enviado`. Recibo pendente não vira `retificar` (erro de validação, nada gravado), e marca fora de `enviado`/`retificar` é recusada. Sem `fisio.editar`, 403 e a marca não muda. Recibo de pagamento da 99 tentado pela 98: 404 e a marca da 99 não muda.
- **Regressão que defende:** status `retificar` no banco sem nenhum caminho que o grave (auditoria 2026-11-05, d19).
- **Teste:** `Modules/Fisio/Tests/Feature/FisioFiscalFilaTest.php`
- **Status: 🧪**
