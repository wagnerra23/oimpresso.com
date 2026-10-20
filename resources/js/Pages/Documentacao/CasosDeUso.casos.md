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

## UC-CASOS-04 · A US do caso leva ao SPEC onde ela mora
- **Persona:** [W] lendo um caso e querendo abrir a US que o entrega.
- **Aceite:** Dado um caso cujo texto de US cita um id que existe como `### <id>` num `memory/requisitos/<Mod>/SPEC.md` · Quando a tela carrega os artefatos · Então o id vira link para esse SPEC — `/documentacao/<slug>` se o SPEC está no acervo indexado, o arquivo no repositório (GitHub) se não está. Id sem SPEC fica texto.
- **Fonte:** pedido de [W] (2026-10-20, *"os artefatos vão ficar navegáveis?"*) + ADR 0256.
- **Teste:** `tests/Feature/DocumentacaoRouteTest.php` — `UC-CASOS-04 · …`
- **Status: ⬜**

## UC-CASOS-05 · O caso lista os `.casos.md` de tela que o citam
- **Persona:** [W] querendo ver onde o caso já está documentado como contrato de tela.
- **Aceite:** Dado um `.casos.md` em `resources/js/Pages/**` ou `Modules/*/Resources/js/Pages/**` que cita o id do caso · Quando a tela carrega os artefatos · Então ele aparece na coluna "Documentado em". Um `.casos.md` que **declara** casos próprios com o mesmo prefixo (título de seção começando pelo id) não conta — é outro caso com o mesmo nome.
- **Regressão que defende:** crédito por coincidência de string (§5 2026-09-04).
- **Teste:** `tests/Feature/DocumentacaoRouteTest.php` — `UC-CASOS-05 · …`
- **Status: ⬜**

## UC-CASOS-06 · Os links não atrasam a tabela
- **Persona:** [W] abrindo a página.
- **Aceite:** Dado que os links varrem a árvore e consultam o acervo · Quando a tela é montada · Então eles chegam numa prop deferred e a tabela aparece antes deles.
- **Teste:** `tests/Feature/DocumentacaoRouteTest.php` — `UC-CASOS-06 · …`
- **Status: ⬜**
