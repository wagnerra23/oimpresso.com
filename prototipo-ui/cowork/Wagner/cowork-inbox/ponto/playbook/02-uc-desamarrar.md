---
sessao: "02"
titulo: Desamarrar UC ⛓ — citação em docblock vira it('UC-XXX-NN · …')
dono: "[CL]"
base: e86130722de1
prefixo: Modules/Ponto/Tests/Feature/** · nos 21 casos.md SÓ as colunas Teste / Status / last_run / last_run_ci
nao_toca: qualquer .tsx · qualquer .charter.md · texto dos UC nos casos.md · Services/ · Http/ · contratos
depende: — (vaga 1). Pode correr em paralelo com 01.
---
# 02 · Desamarrar UC ⛓

## Estado (o que sei e o que não sei)
- Em 04/09 o `casos:report` dava **18 UC ⛓** no Ponto (UC citado só em docblock, sem `it()` nominal). **Não remedi nesta sha** — o comando não roda daqui.
- Desde então: testes **16 → 44** (`*ContratoTest` para Espelho, BancoHoras, Colaborador, Configuracao, Escala ×2, Importacao ×3, Intercorrencia ×2, Jornada, Dashboard, Relatorio, Welcome; `TelasNavegacaoTest`). Parte dos 18 pode já estar desamarrada — **medir antes**.
- `casos.md` **21/21** (frente 4 do doc de 04/09 já feita) — as colunas `Teste`/`Status` deles são o destino do que esta thread prova.

## Passo a passo
1. `gh pr list --state open` × `Modules/Ponto/Tests/**`.
2. Rodar `casos:report` (ou o script que o repo usa hoje) **antes**: número de ⛓ e a lista por tela → cabeçalho do `_saida`.
3. Lote 1 (Espelho · Aprovações · Dashboard) → lote 2 (Intercorrências · BancoHoras · Importações · Escalas · Colaboradores · Configurações · Relatórios · Welcome). Converter citação para `it('UC-XXX-NN · …')`. **Zero teste novo, zero assertion nova.**
4. Preencher nos `casos.md` a coluna `Teste` (nome do arquivo) e `Status` com o **resultado real da lane** — nunca ✅ sem lane verde (`last_run_ci`).
5. `casos:report` **depois** → número no `_saida`. Meta: 0.

## PARAR SE
- Um UC ⛓ **não tem teste correspondente** → é órfão: não inventar assertion; listar no `_saida` como pendência de teste (vira thread própria, não entra aqui).
- Fazer um teste passar exigiria mudar copy/ordem de contrato → parar (lei [W]).
- UC-PAINEL-02 / `CrossTenant*` revelar vazamento real → **incidente Tier 0**, não PR.

## Prova
- `_saida-02.md` com: nº ⛓ antes · nº depois · lista dos órfãos · lane `ponto-pest.yml` verde. Sem número, a thread não conta.
