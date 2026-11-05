---
id: modules-fisio-pages-fisio-protocolos-index-casos
casos: Fisio · Protocolos-modelo · /fisio/protocolos
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do fisioterapeuta + critério de aceite verificável (Dado/Quando/Então)
por_que: o modelo do produto é o mesmo para todas as clínicas e não pode ser alterado por uma delas; o protocolo da clínica é dado dela. Escopo errado esconde os modelos ou vaza o protocolo de outra clínica.
owner: wagner
last_run: "2026-11-04"
last_run_ci: "_pendente_ — o veredito entra quando a lane Verticais rodar FisioProtocolosTelaTest."
related_us: [US-FISIO-006]
---

# Casos de Uso & Aceite — Fisio · Protocolos (`/fisio/protocolos`)

> **Âncora:** SPEC US-FISIO-006 (protocolo-modelo: global só se copia, editar = 403) e `etapas/01-biblioteca.md` B-09. Os UCs não derivam do `.tsx`.
>
> **Status:** ✅ passa (manifesto) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou. **O veredito é da lane** (G-7).

## Rastreabilidade

| UC | O que defende | Peso | Teste |
|---|---|---|---|
| UC-FPR-01 | modelo para todos; protocolo da clínica só para a dona | must | `FisioProtocolosTelaTest` |
| UC-FPR-02 | exercícios com nome e dose; exercício de outra clínica não aparece | must | `FisioProtocolosTelaTest` |
| UC-FPR-03 | leitura com `fisio.access`; edição só com `fisio.editar` | must | `FisioProtocolosTelaTest` |
| UC-FPR-04 | copiar o modelo para a clínica; modelo não muda | must | `FisioProtocolosTelaTest` |
| UC-FPR-05 | protocolo de outra clínica não se edita nem se copia | must | `FisioProtocolosTelaTest` |

---

## UC-FPR-01 · O que cada clínica vê `[T0]` · `must`
- **Aceite:** Dado um modelo do produto (`business_id` nulo), um protocolo da 98 e um da 99 · Quando cada clínica abre a tela · Então as duas veem o modelo e só o protocolo próprio.
- **Regressão que defende:** protocolo de uma clínica vazando para outra; modelo escondido pelo escopo cru.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioProtocolosTelaTest.php`
- **Status: 🧪**

## UC-FPR-02 · Exercícios do protocolo `[T0]` · `must`
- **Aceite:** Dado um modelo com um exercício global e um exercício próprio da 99 · Quando a 98 abre o modelo · Então vê só o exercício global, com o nome e a dose gravados.
- **Regressão que defende:** nome de exercício próprio de outra clínica aparecendo dentro do modelo.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioProtocolosTelaTest.php`
- **Status: 🧪**

## UC-FPR-03 · Quem pode o quê · `must`
- **Aceite:** Usuário só com `fisio.access` abre a tela (200), vê os protocolos e recebe `pode_editar` falso; os botões de copiar e editar não aparecem.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioProtocolosTelaTest.php`
- **Status: 🧪**

## UC-FPR-04 · Copiar o modelo · `must`
- **Aceite:** Quando a 98 copia o modelo · Então nasce um protocolo com `business_id` da sessão e `origin_protocol_id` do modelo; editar o modelo → 403 e o nome dele não muda.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioProtocolosTelaTest.php`
- **Status: 🧪**

## UC-FPR-05 · Protocolo de outra clínica `[T0]` · `must`
- **Aceite:** A 98 tentando editar ou copiar o protocolo da 99 → 404, e nada muda.
- **Teste:** `Modules/Fisio/Tests/Feature/FisioProtocolosTelaTest.php`
- **Status: 🧪**
