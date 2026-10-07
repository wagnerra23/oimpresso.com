---
sessao: "09"
titulo: Tier 0 — decompose sem business_id
dono: "[CL]"
base: ff43e08461d9
origem: _saida-02 §Perguntas 1
---
# 09 · Provar antes de consertar ([W] 07/10)

1. Teste: usuário do tenant 99 chama a rota de decompor com o id de um project do 98. Rodar no `main` **sem mexer no código**.
2. Vermelho (vaza) → no mesmo PR, escopo por `business_id` no `ProjectDecomposerService::decompose` (e na rota, se só exige login). Verde depois.
3. Verde no `main` → não vaza; a thread fecha com o teste como guarda, sem conserto.
Os outros achados da `_saida-02` (TeamScopes `user_businesses`, grant/revoke/execute só com login, `triggered_by='wagner'`) ficam fora — listar no recibo, não consertar aqui.
