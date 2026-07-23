---
id: dominios-template-readme
---

# Template — sistema externo novo

Skeleton pra adicionar novo sistema externo em `memory/dominios/<sistema>/`. Aplica [ADR 0118](../../decisions/0118-segregacao-dominios-externos-clientes-legacy.md) + [ADR 0119](../../decisions/0119-migration-factory-capacidade-institucional.md).

## Como usar

```powershell
# 1. Copiar template pra pasta nova (ex: bling/)
Copy-Item -Recurse memory/dominios/_template memory/dominios/<sistema>

# 2. Renomear arquivos .template removendo extensão
Get-ChildItem memory/dominios/<sistema>/*.template | Rename-Item -NewName { $_.Name -replace '\.template$' }

# 3. Editar README.md, ARQUITETURA.md, CONVENCOES.md preenchendo placeholders <SISTEMA>, <stack>, etc
```

Em bash/zsh:
```bash
cp -r memory/dominios/_template memory/dominios/<sistema>
cd memory/dominios/<sistema>
for f in *.template; do mv "$f" "${f%.template}"; done
```

## Arquivos do template

| Arquivo | Função | Obrigatório? |
|---|---|---|
| `README.md.template` | Identidade do sistema + overview navegável | ✅ |
| `ARQUITETURA.md.template` | Stack interno (linguagem, banco, drivers, runtime) | ✅ |
| `CONVENCOES.md.template` | Convenções específicas (FK, charset, sufixos, flags) | ✅ se observadas |
| `modulos/.gitkeep` | Pasta vazia que vira `modulos/<dom>/` por entidade | — (criada conforme migra) |

## Checklist após copiar

Antes de fazer o primeiro PR pro novo sistema:

- [ ] Substituir `<SISTEMA>` em todos os arquivos pelo nome real (kebab-case)
- [ ] Preencher seção "Identidade" do README com URL/stack/distribuição
- [ ] Documentar ARQUITETURA com pelo menos: linguagem, banco, drivers de acesso, autenticação, runtime
- [ ] CONVENCOES inicial pode ficar com 1-2 itens — cresce conforme aprende
- [ ] Adicionar linha no [`_overview.md`](../_overview.md) catálogo de sistemas externos
- [ ] Criar issue/task pra primeira entidade a migrar (sugestão: contas bancárias OU clientes)
- [ ] Aplicar [Patterns 01-07](../_patterns/README.md) conforme avança

## Princípio

**Não criar sistema externo aqui sem demanda real.** Speculation paralisa — `_overview.md` lista candidatos, mas pasta concreta só nasce quando 1 cliente paga ou métrica detecta drift ([ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)).
