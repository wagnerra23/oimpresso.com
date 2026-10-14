---
sessao: "A2"
titulo: "Remedir Negócios · Pacotes · Assinaturas · Dashboard — 2ª rodada"
executor: "[CL]"
data: 2026-10-14
base: origin/main 6511ed7c34
thread: A2-remedir-4.md
veredito: "Protótipo MEDIDO: 4 design.json distintos, cada um na sua rota sa-*, 2× idênticos salvo a porta do servidor local. Produção NÃO MEDI: o staging tem o token, mas nenhum usuário superadmin e o código de 21/09."
---
# _saida A2b · Remedir as 4 do Superadmin (2ª rodada)

Continua a `_saida-A2` (2026-10-06), que parou nos dois lados.

## Lado protótipo — MEDIDO

O que destravou: a alternativa do Code que a `_saida-A2` já apontava, override de token em
`governance/design/targets/roles/`. Cinco arquivos, cada token lido do `app.jsx` do espelho:

| Override | Token | View no shell |
|---|---|---|
| `superadmin--Negocios--Index.json` | `sa-negocios` | `negocios` |
| `superadmin--Pacotes--Index.json` | `sa-pacotes` | `pacotes` |
| `superadmin--Assinaturas--Index.json` | `sa-assinaturas` | `assinaturas` |
| `superadmin--Comunicador--Index.json` | `sa-comunicador` | `comunicador` |
| `superadmin--Configuracoes--Index.json` | `sa-config` | `config` |

Os dois últimos não são das 4 telas: sem eles o Dashboard (`superadmin`, view `visao`) seguia
colidindo com as irmãs Comunicador e Configurações, e o lote o recusava.

`--dry --lado design` antes: `executáveis: 0/1` nas 4. Depois: `1/1` nas 4.

Comando, uma tela por vez (o `--tela` repetido só guarda a última):

```bash
node scripts/design/design-diff-lote.mjs --lado design --tela superadmin/<Tela>/Index
```

| Tela | Rodada 1 | Rodada 2 (gravada) | Bytes |
|---|---|---|---|
| Dashboard | `b98b536bcea9` | `b1fffe426d88` | 6828 |
| Negocios | `2d13ce2d9360` | `6778bcbde0bb` | 5610 |
| Pacotes | `f2d4ccd4be39` | `5eb4ddbb4ff7` | 3675 |
| Assinaturas | `4522c6f721be` | `523e9f9baabe` | 5712 |

- **4 distintos entre si**, e nenhum é o `b4cb290eb306…` que as 4 compartilhavam desde a A1.
- **Rodada 1 × rodada 2:** comparei chave a chave os 4 pares. A única chave que difere é `url`,
  a porta do servidor http local que o lote sobe a cada execução (`127.0.0.1:54293` × `:62113`
  etc.). O conteúdo medido é idêntico; byte-idêntico não é, por causa dessa porta.
- Saúde da sonda nos 4: `folhasVazias: []`, `tokensMortos: []`, 76–77 folhas.
- `RESUMO.md` não foi gravado: cada rodada de tela única o reescreve com só aquela tela e apagava a
  linha do Manufacturing. Ficou como estava em `main`.
- `prod.json` das 4 pastas não foi tocado (o `--lado design` não o escreve). Ele é da medida
  antiga e o `resultado.json` novo diz `"lado": "design"`, `"compare": null`.

## Lado produção — NÃO MEDI

Mudou a causa, não o resultado.

- `memory/_INDEX-SECRETS.md`: segue com **0** ocorrências de `visreg`.
- O token **existe** no `.env` do container `oimpresso-staging` (CT 100). Li para uma variável da
  sessão, sem gravar em arquivo do repo.
- Com ele, `/_visreg-login/1?t=…&to=<rota>` responde **Forbidden** nas 4 rotas
  (`/superadmin`, `/superadmin/business`, `/superadmin/packages`,
  `/superadmin/superadmin-subscription`). O usuário 1 não é superadmin no staging.
- `ADMINISTRATOR_USERNAMES` está definida no staging, com 1 nome, e **nenhum** usuário do banco
  do staging tem esse username (`whereIn(username)` → `[]`). Não há usuário com quem logar.
- Mesmo com usuário, a medida seria do código errado: o checkout do staging está em `e57b78bf5`
  (2026-09-21), **1699** commits atrás de `main`, **22** deles em `Modules/Superadmin`.

## O que destrava a produção

1. Atualizar o checkout do staging para o `main` (é o servidor que outras sessões usam; combinar antes).
2. Um usuário superadmin no staging: semear um usuário com o username de `ADMINISTRATOR_USERNAMES`
   (pelo seeder, não por tinker).
3. Rodar `--lado prod` nas 4 com `VISREG_LOGIN_TOKEN` e `--base-url https://staging.oimpresso.com --user-id <id>`.

Nenhum dos três é decisão visual. A thread fica `[em curso]` até o lado produção sair.
