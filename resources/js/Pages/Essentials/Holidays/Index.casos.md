---
id: resources-js-pages-essentials-holidays-index-casos
casos: Essentials · Feriados do business · /hrm/holiday
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável (Dado/Quando/Então)
owner: wagner
last_run: "2026-10-06"
---

# Casos de uso — /hrm/holiday · Feriados do business

> **Status:** ✅ passa (provado no manifesto G-7) · 🧪 teste cita o UC, sem veredito ainda ·
> ⬜ não verificado · ❌ quebrou.

> Os UC derivam do [`Index.charter.md`](Index.charter.md) (§Goals, §Backend contract e
> §Métricas: *"Admin biz=1 NÃO enxerga feriado biz=99"*, *"Não-admin NÃO vê botão
> Novo/Editar/Excluir"*, *"Filtros filtram via SQL"*), conferidos no
> `EssentialsHolidayController`. **Nunca** do `.tsx` nem do protótipo (§5 2026-06-05).
> A tela **não tem US** no `memory/requisitos/Essentials/SPEC.md`; a âncora é o charter.

> ⚠️ **O que esta tela NÃO faz.** O charter diz que feriado é importante para o RH, mas o
> cadastro é só registro. O Ponto **não lê** `EssentialsHoliday`: `git grep -i
> "EssentialsHoliday\|essentials_holidays" -- Modules/Ponto` devolveu 0 ocorrências
> (medido 2026-10-06). Nenhum UC aqui promete efeito na apuração do Ponto nem na folha.

> ⚖️ **Onde roda.** Teste: [`Modules/Essentials/Tests/Feature/HolidaysIndexContratoTest.php`](../../../../../Modules/Essentials/Tests/Feature/HolidaysIndexContratoTest.php),
> MySQL-only (pula no SQLite), na allowlist da lane `PHP / Pest (Essentials · MySQL)`
> (`.github/workflows/essentials-pest.yml`). Tenant 98 × adversário 99, nunca biz=4.

---

## UC-EHOL-01 · A lista traz os feriados do meu business e não traz os de outro `[must]` `[T0]`
Status: 🧪 sem veredito
- **Persona:** colaborador — consulta os feriados da empresa dele.
- **Aceite:** Dado um feriado no meu tenant e outro no tenant adversário, na mesma data · Quando a tela pede a prop deferida `holidays` · Então chega o meu e não chega o do outro.
- **Teste:** `Modules/Essentials/Tests/Feature/HolidaysIndexContratoTest.php` — `UC-EHOL-01 · a lista traz …`
- **Regressão que defende:** vazamento cross-tenant (ADR 0093). O feriado próprio é o controle positivo: sem ele, uma lista vazia faria o negativo passar.

## UC-EHOL-02 · O filtro De/Até é aplicado no servidor `[must]`
Status: 🧪 sem veredito
- **Persona:** administrador — procura os feriados de um mês.
- **Aceite:** Dado um feriado dentro do intervalo e outro fora · Quando a tela pede `holidays` com `start_date` e `end_date` · Então chega só o de dentro.
- **Teste:** `Modules/Essentials/Tests/Feature/HolidaysIndexContratoTest.php` — `UC-EHOL-02 · o filtro De/Até …`
- **Regressão que defende:** charter §Métricas *"Filtros filtram via SQL (não no front)"*. O servidor só aplica o intervalo com as duas datas preenchidas e compara pela data de início.

## UC-EHOL-03 · O admin cria um feriado e ele é gravado no meu business `[must]`
Status: 🧪 sem veredito
- **Persona:** administrador — cadastra o recesso de fim de ano.
- **Aceite:** Dado um admin · Quando envia nome, início e fim · Então existe a linha com o `business_id` do meu tenant e as duas datas gravadas.
- **Teste:** `Modules/Essentials/Tests/Feature/HolidaysIndexContratoTest.php` — `UC-EHOL-03 · o admin cria …`
- **Regressão que defende:** charter §Backend contract (`POST /hrm/holiday`). O `business_id` vem da sessão, nunca do formulário.

## UC-EHOL-04 · O admin não edita nem apaga feriado de outro business `[must]` `[T0]`
Status: 🧪 sem veredito
- **Persona:** administrador — com um id de outra empresa na URL, não muda nada lá.
- **Aceite:** Dado um feriado meu e um do tenant adversário · Quando edito o meu e depois tento editar e apagar o do outro com o mesmo id na rota · Então o meu fica renomeado e o do outro continua existindo, com o nome original.
- **Teste:** `Modules/Essentials/Tests/Feature/HolidaysIndexContratoTest.php` — `UC-EHOL-04 · o admin não edita …`
- **Regressão que defende:** `update`/`destroy` filtram por `business_id` (ADR 0093). A edição do feriado próprio prova que a rota grava; sem ela, um PUT que nunca grava faria o caso passar.

## UC-EHOL-05 · Quem não é admin vê a lista sem gestão e recebe 403 ao criar, editar e apagar `[must]`
Status: 🧪 sem veredito
- **Persona:** colaborador sem papel de admin.
- **Aceite:** Dado um usuário do meu tenant sem `Admin#` · Quando abre `/hrm/holiday` · Então a resposta é 200 com `can_manage` falso · E quando tenta criar, editar ou apagar · Então as três respostas são 403 e nada muda no banco.
- **Teste:** `Modules/Essentials/Tests/Feature/HolidaysIndexContratoTest.php` — `UC-EHOL-05 · quem não é admin …`
- **Regressão que defende:** charter §Métricas *"Não-admin NÃO vê botão Novo/Editar/Excluir (`can_manage=false`)"*. O `can_manage` só esconde o botão; o 403 é o que impede a escrita.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Colaborador de uma filial vê os feriados da filial dele e os sem localidade, e não os de outra filial (charter §Goals, `permitted_locations`). Precisa de localidades semeadas no tenant 98.
- [BACKLOG] A validação `location_id` usa `exists:business_locations,id` sem filtrar pelo business (`StoreHolidayRequest`). Hipótese, não medida: um admin conseguiria gravar feriado apontando para a localidade de outra empresa. Precisa de teste com localidade do tenant 99.
- [BACKLOG] Filtro "vale para o negócio inteiro" (só feriados sem localidade): existe no protótipo e não no vivo (`holidays-index-gap.md`). Decisão de [W].
