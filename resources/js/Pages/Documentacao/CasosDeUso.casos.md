---
casos: Documentacao/CasosDeUso — os casos de uso do catálogo de fluxos na documentação
irmaos: CasosDeUso.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: o que está na documentação tem que ser o que o catálogo diz hoje, não o que alguém copiou.
owner: wagner
last_run: "2026-10-20"
---

# Casos de Uso & Aceite — Documentacao/CasosDeUso

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Fonte dos casos: pedido de [W] (2026-10-20) + ADR 0256 (derivado sobrevive). **Não** o `.tsx`.

## UC-CASOS-01 · A tela mostra todos os casos do catálogo
- **Persona:** [W] lendo a documentação.
- **Aceite:** Dado o catálogo `CasosDoPrototipo::CASOS` · Quando a tela é montada · Então ela entrega um caso por linha do catálogo, na mesma ordem e com a mesma contagem.
- **Teste:** `tests/Feature/DocumentacaoRouteTest.php` — `UC-CASOS-01 · …`
- **Status: ⬜**

## UC-CASOS-02 · O estado é o rótulo do catálogo
- **Persona:** [W] querendo saber o que já foi entregue.
- **Aceite:** Dado um caso com estado `parcial` · Quando a tela é montada · Então o rótulo exibido é o de `CasosDoPrototipo::ESTADOS['parcial']`, nunca texto próprio.
- **Teste:** `tests/Feature/DocumentacaoRouteTest.php` — `UC-CASOS-02 · …`
- **Status: ⬜**

## UC-CASOS-03 · Nada do catálogo é escrito à mão na tela
- **Persona:** quem mantém a tela.
- **Aceite:** Dado o `CasosDeUso.tsx` · Quando se procura qualquer id ou título do catálogo no arquivo · Então não há ocorrência.
- **Regressão que defende:** colar a lista no TSX "pra ficar mais simples" — o que faria a página envelhecer.
- **Teste:** `tests/Feature/DocumentacaoRouteTest.php` — `UC-CASOS-03 · …`
- **Status: ⬜**
