---
id: modules-producao-pages-producao-postos-index-casos
casos: Produção · Postos e cadastros · /producao/postos
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do gestor + critério de aceite verificável (Dado/Quando/Então)
por_que: o celular só oferece os motivos do posto; motivo de outro posto ou de outra empresa corrompe o registro de pausa e refugo. Custo-hora da pessoa é dado de desempenho.
owner: wagner
last_run: "2026-10-19"
last_run_ci: "_pendente_ — o trio nasce na thread 03. O veredito entra quando a lane verticais-pest rodar o ProducaoRoteirosContratoTest."
---

# Casos de Uso & Aceite — Produção · Postos e cadastros (`/producao/postos`)

> **Âncora:** `prototipo-ui/cowork/Wagner/cowork-inbox/producao/Producao-celular.casos.md` (UC-PCV-33) e a emenda
> `playbook/E03-cadastros-motivos.md`. Os UCs não derivam do `.tsx`.

---

## UC-PCV-33 · Motivos vêm do cadastro do posto · `must`
- **Persona:** gestor de produção; operador no celular (A4/A5).
- **Aceite:** Dado o posto Roland com 6 motivos de pausa · Então a lista do posto mostra só esses 6 · E outro posto mostra os dele · E a empresa 99 não vê nem altera os motivos da 98.
- **Teste:** `Modules/Producao/Tests/Feature/ProducaoRoteirosContratoTest.php`.
- **Status: ⬜** — teste escrito, aguarda a lane MySQL.
- **Revalidado 2026-10-19:** a tela mudou só na largura dos campos (custo-hora, nome da ferramenta, unidade — #9574); motivos por posto não foram tocados.

---

## Backlog de casos (sem id — a E03 não traz UC próprio; o teste já os defende)

- **[BACKLOG]** Custo-hora da pessoa: sem `producao.desempenho.ver` a lista vem vazia e a gravação é 403; colaborador de outra empresa é recusado.
- **[BACKLOG]** Veículo é posto de máquina com placa obrigatória e custo por km; ferramenta tem QR único por empresa.

## Trilha do tempo
- 2026-10-16 · [CL] thread 03 + E03 do playbook de Produção — trio nasce junto (charter + casos + teste).
