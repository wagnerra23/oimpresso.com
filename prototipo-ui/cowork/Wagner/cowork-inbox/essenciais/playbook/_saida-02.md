---
sessao: "_saida-02"
thread: "02 · Casos: Settings + Holidays"
dono: "[CL]"
data: 2026-10-06
base_lida: wagnerra23/oimpresso.com@main 73ba361e01
---
# _saida-02 (1 PR: casos + testes de Settings/Index e Holidays/Index)

## Entregue
- `resources/js/Pages/Essentials/Settings/Index.casos.md` — UC-ESET-01..04. Fonte: US-ESS-014
  do `SPEC.md` + o charter, conferidos no `EssentialsSettingsController`.
- `resources/js/Pages/Essentials/Holidays/Index.casos.md` — UC-EHOL-01..05. A tela não tem US no
  SPEC; a fonte é o charter (§Goals, §Backend contract, §Métricas), conferido no
  `EssentialsHolidayController`.
- Testes em `Modules/Essentials/Tests/Feature/`: `SettingsIndexContratoTest.php` (4 casos) e
  `HolidaysIndexContratoTest.php` (5 casos). Tenant 98 × adversário 99, nunca biz=4. Cada caso
  negativo tem um controle positivo no mesmo teste.
- Os 2 arquivos entraram na allowlist da lane `PHP / Pest (Essentials · MySQL)`
  (`.github/workflows/essentials-pest.yml`). Sem isso nenhuma lane de PR os rodaria.
- Os dois charters ganharam o link para o `.casos.md`. Nada mais neles mudou; os `.tsx` não foram
  tocados.

## Holidays × Ponto
O charter chama o feriado de importante para o RH. O Ponto não lê `EssentialsHoliday`:
`git grep -i "EssentialsHoliday\|essentials_holidays" -- Modules/Ponto` devolveu 0 ocorrências
(2026-10-06). Os casos tratam o cadastro como registro e nenhum promete efeito na apuração.

## Ficou no backlog dos casos (sem teste)
- Filtro por filial para quem não é admin: precisa de localidades semeadas no tenant 98.
- `StoreHolidayRequest` valida `location_id` com `exists:business_locations,id` sem filtrar pelo
  business. É hipótese, não medida: um admin conseguiria apontar o feriado para a localidade de
  outra empresa. Precisa de teste com localidade do tenant 99.
- Filtro "vale para o negócio inteiro": está no protótipo, não no vivo (`holidays-index-gap.md`).

## Prova do json
As duas provas são `arquivo`: os dois `.casos.md` existem no branch.
