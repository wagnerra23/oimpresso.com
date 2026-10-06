---
sessao: "A2"
titulo: "Remedir Negócios · Pacotes · Assinaturas · Dashboard — saída da thread"
executor: "[CL]"
data: 2026-10-06
base: d9f67d2dac
thread: A2-remedir-4.md
veredito: "NÃO MEDI nos dois lados. Produção: sem VISREG_LOGIN_TOKEN no _INDEX-SECRETS e sem app local. Protótipo: superadmin-page.jsx sem tabela ROTAS e sem override, então as 4 telas abrem a mesma rota e o lote (pós #8754) as recusa."
---
# _saida A2 · Remedir as 4 do Superadmin

Nada foi regravado em `governance/design/targets/medidas/superadmin--*`. As 4 medidas antigas
continuam como estavam (mesmo `design.json`, sha `b4cb290eb306…`, conforme `_saida-A1`).

## Lado produção — NÃO MEDI

Conferido em 2026-10-06, na base acima:

- `memory/_INDEX-SECRETS.md` (106 linhas): **0** ocorrências de "visreg". Não busquei o token fora do índice.
- `VISREG_LOGIN_TOKEN` e `DESIGN_DIFF_COOKIE` não definidas nesta sessão.
- App local: `curl http://127.0.0.1:8000/` → sem resposta (`000`).

A ficha manda parar aqui. O que destrava: o `VISREG_LOGIN_TOKEN` do staging catalogado no
`_INDEX-SECRETS` (ou um app local com `/_visreg-login`).

## Lado protótipo — NÃO MEDI

Com o `--lado design` do #8754 o lote mede o protótipo sem login, mas recusa as 4 telas:

- `superadmin-page.jsx` não tem `const ROTAS` (0 ocorrências) e não existe override em
  `governance/design/targets/roles/` para o Superadmin (0 arquivos).
- Sem as duas coisas, as 4 telas resolvem `route=superadmin` (`view="visao"`). O `--dry` do lote
  mostra `rota do GRUPO: route=superadmin também abre …` e marca as 4 como não executáveis —
  inclusive o Dashboard, que colide com as irmãs.
- As rotas certas existem no shell (`app.jsx`: `sa-negocios`, `sa-pacotes`, `sa-assinaturas`;
  Dashboard = `superadmin`). Falta a ligação **tela → rota** em forma que a máquina leia.

O prefixo desta thread é só `medidas/superadmin--*`: não inclui `targets/roles/`, e o espelho
`prototipo-ui/cowork/Wagner/` é só leitura (edição local some no próximo export).

## O que destrava, por quem

1. **Cowork:** declarar no `superadmin-page.jsx` a tabela no formato do Repair
   (`const ROTAS = { "superadmin": { page: "superadmin/Dashboard/Index" }, "sa-negocios": { page: "superadmin/Negocios/Index" }, … }`).
   Com o próximo export, o lote resolve as 4 rotas sem override.
   (Alternativa do Code: 3 overrides `{"token": "sa-…"}` em `targets/roles/`, numa thread cujo prefixo os inclua.)
2. **[W]:** catalogar o `VISREG_LOGIN_TOKEN` do staging no `_INDEX-SECRETS` para o lado produção.

Com (1), o lado protótipo já pode ser medido com `--lado design`, antes do token.
