---
id: modules-producao-pages-producao-roteiros-index-casos
casos: Produção · Roteiros · /producao/roteiros
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do gestor + critério de aceite verificável (Dado/Quando/Então)
por_que: roteiro fora da regra vira OS com etapas que ninguém treina; roteiro de outra empresa visível é vazamento Tier 0.
owner: wagner
last_run: "2026-10-16"
last_run_ci: "_pendente_ — o trio nasce na thread 03. O veredito entra quando a lane verticais-pest rodar o ProducaoRoteirosContratoTest."
---

# Casos de Uso & Aceite — Produção · Roteiros (`/producao/roteiros`)

> **Âncora:** `prototipo-ui/cowork/Wagner/cowork-inbox/producao/Producao.casos.md` (UC-PCV-13). Os UCs não derivam do `.tsx`.

---

## UC-PCV-13 · No máximo 2 trilhas · `must`
- **Persona:** gestor de produção.
- **Aceite:** O roteiro aceita até 2 trilhas em paralelo, e elas sempre terminam numa etapa de junção nomeada · Uma 3ª trilha ou trilha sem junção é recusada ao salvar. A junção é a etapa logo depois do bloco; nome diferente também é recusado.
- **Tenant:** roteiro da empresa 98 não aparece nem é alterado pela 99.
- **Teste:** `Modules/Producao/Tests/Feature/ProducaoRoteirosContratoTest.php`.
- **Status: ⬜** — teste escrito, aguarda a lane MySQL.

## Trilha do tempo
- 2026-10-16 · [CL] thread 03 do playbook de Produção — trio nasce junto (charter + casos + teste).
