---
id: modules-fisio-pages-fisio-pacientes-pacote-casos
casos: Fisio · Pacote e cobrança · /fisio/pacientes/{id}/pacote
irmaos: Pacote.charter.md (lei) · Pacote.tsx (tela)
tecnica: Caso de uso = narrativa do fisioterapeuta + critério de aceite verificável (Dado/Quando/Então)
por_que: o pacote vendido é dinheiro já recebido pela clínica; sessão debitada duas vezes ou título duplicado em outro módulo vira cobrança errada ao paciente.
owner: wagner
last_run: "2026-11-04"
last_run_ci: "_pendente_ — o veredito entra quando a lane MySQL do Fisio rodar FisioPacoteTelaTest."
related_us: [US-FISIO-021, US-FISIO-020]
---

# Casos de Uso & Aceite — Fisio · Pacote e cobrança (`/fisio/pacientes/{id}/pacote`)

> **Âncora:** SPEC do Fisio §"F3 · Dinheiro" (US-FISIO-020 e 021), etapa F3 §3.4.2 e §3.7, e o protótipo `Financeiro.dc.html`. Os UCs não derivam do `.tsx`.
>
> **Status:** ✅ passa (manifesto) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou. **O veredito é da lane** (G-7).

## Rastreabilidade

| UC | O que defende | Peso | Teste |
|---|---|---|---|
| UC-FPK-01 | tela mostra o saldo derivado do ledger | must | `FisioPacoteTelaTest` |
| UC-FPK-02 | abrir pacote e registrar sessão; sem saldo, erro e nada lançado | must | `FisioPacoteTelaTest` |
| UC-FPK-03 | sem `fisio.editar` vê, mas não escreve (403) | must | `FisioPacoteTelaTest` |
| UC-FPK-04 | paciente e pacote de outra empresa: 404 | must | `FisioPacoteTelaTest` |
| UC-FPK-05 | cobrança lê os títulos da venda, só da empresa, sem gravar título | must | `FisioPacoteTelaTest` |
| UC-FPK-06 | pacote sem venda: cobrança vazia, sem erro | should | `FisioPacoteTelaTest` |

---

## UC-FPK-01 · Saldo do pacote vem do ledger · `must`
- **Persona:** fisioterapeuta conferindo quantas sessões restam.
- **Aceite:** Dado um pacote de 10 sessões com 1 usada · Quando abre a aba · Então vê 9 de saldo e uma sessão na lista; o número vem da soma do ledger.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPacoteTelaTest.php`
- **Status: 🧪**

## UC-FPK-02 · Abrir pacote e registrar sessão · `must`
- **Aceite:** Quando abre um pacote de 1 sessão e registra uma sessão · Então o saldo vai a 0; registrar de novo devolve erro no campo `pacote` e não lança nada.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPacoteTelaTest.php`
- **Status: 🧪**

## UC-FPK-03 · Só quem edita escreve · `must`
- **Aceite:** Dado usuário com `fisio.access` e sem `fisio.editar` · Quando tenta abrir pacote · Então recebe 403 e nenhum pacote nasce; a tela continua abrindo.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPacoteTelaTest.php`
- **Status: 🧪**

## UC-FPK-04 · Outra empresa não abre `[T0]` · `must`
- **Aceite:** Dado paciente e pacote da 99 · Quando a 98 abre a tela ou registra sessão · Então 404, e o saldo da 99 fica intacto.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPacoteTelaTest.php`
- **Status: 🧪**

## UC-FPK-05 · Cobrança é leitura do Financeiro `[T0]` · `must`
- **Aceite:** Dado o pacote ligado a uma venda e títulos dessa venda na 98 e na 99 · Quando a 98 abre a aba · Então vê só o título da 98, e a contagem de `fin_titulos` não muda.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPacoteTelaTest.php`
- **Status: 🧪**

## UC-FPK-06 · Sem venda, sem erro · `should`
- **Aceite:** Dado pacote sem venda ligada · Quando abre a aba · Então a cobrança vem vazia e a nota, ausente.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioPacoteTelaTest.php`
- **Status: 🧪**
