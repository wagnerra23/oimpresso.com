---
id: requisitos-ponto-configuracoes-reps-gap
tela: Ponto/Configuracoes/Reps (/ponto/configuracoes/reps)
prototipo: prototipo-ui/cowork/Wagner/ponto-telas.jsx
tela_viva: resources/js/Pages/Ponto/Configuracoes/Reps.tsx
gerado_em: 2026-09-29
charter: resources/js/Pages/Ponto/Configuracoes/Reps.charter.md
thread: prototipo-ui/cowork/Wagner/cowork-inbox/ponto/playbook/26-gap-configuracoes.md
---

# GAP-SPEC — Ponto/Configuracoes/Reps

> **Fonte do contrato:** charter `Configuracoes/Reps.charter.md` + protótipo `ponto-telas.jsx`,
> ramo `tela === "reps"` do símbolo `Configuracoes` (`:988-1031`). Lado vivo medido em
> `Configuracoes/Reps.tsx` e `ConfiguracaoController@reps/storeRep` (`origin/main` e4289e688;
> citações de linha re-medidas em 2026-09-29 no working tree). O `.tsx` vivo tinha **0**
> `data-contract` em `e4289e688`; em 2026-09-29 tem **2** (os dois cards, desde o #8096). O "PARAR SE" da thread 26 se cumpriu: o vivo **já tem**
> a coluna Ativo.

| Parte | Estado no vivo | Ação |
|---|---|---|
| Cabeçalho | **Paridade.** Vivo: "REPs · Registradores Eletrônicos" + Portaria MTP 671/2021 + voltar para Configurações (`Reps.tsx:72-82`). Protótipo: "Dispositivos REP" + contagem + voltar (`:993-994`). A contagem no vivo fica na descrição da lista (`Reps.tsx:145`). | Nada — paridade de conteúdo. |
| REPs cadastrados | **Vivo à frente na coluna Ativo; diverge no CNPJ.** Vivo `Reps.tsx:158-162`: Tipo · Identificador · Descrição · Local · **Ativo** (`:174-176`, vindo de `ConfiguracaoController.php:77`). Protótipo `:997`: Tipo · Identificador · Descrição · Local · **CNPJ**, sem Ativo. | A coluna Ativo é catch-up do protótipo. A coluna CNPJ é **decidir**: o charter põe CNPJ só no formulário e o identificador já carrega o CNPJ nos 14 primeiros dígitos. |
| Inativar REP | **Ausente nos dois lados.** Nenhuma ação sobre REP existente no vivo (`Reps.tsx:165-179`) nem rota (`Modules/Ponto/Http/routes.php:129-130` tem só listar e cadastrar). | **Incorporar** — `D-REP-ATIVO` = ENTRA: ação de **inativar**, e **nunca delete** ("inativar preserva o histórico; deletar, não"). Exige rota + controller + emenda do Non-Goal do charter (thread 27). |
| Cadastrar novo REP | **Paridade de campos.** Vivo `Reps.tsx:86-138`: Tipo (3 opções) · Identificador (17) · Descrição * · Local · CNPJ (só dígitos, `:130`). Protótipo `:1010-1027`: os mesmos 5 campos. A descrição do card no vivo já diz "CNPJ (14) + sequencial (3) conforme Anexo I" (`:91-93`), o formato que o protótipo corrigiu na thread 26. | Nada — paridade. |
| Validação do identificador | **Vivo à frente.** Vivo valida no servidor `size:17` **e** unicidade (`unique:ponto_reps,identificador`, `ConfiguracaoController.php:89`). Protótipo valida só o comprimento (`:989`), artefato de mock declarado. Placeholder do vivo `12345678000100001` (`Reps.tsx:113`) é fictício. | Nada — vivo à frente. |
