---
sessao: "03a"
titulo: casos.md de Policies — abre a frente do trio (1 tela por PR)
dono: "[CL]"
base: 0d159eb84a10
constituicao: CONSTITUICAO-COWORK.md (C1–C12)
prefixo: resources/js/Pages/governance/Policies.casos.md (CRIAR)
nao_toca: Policies.tsx · os outros 8 charters · DsRollout.casos.md (é o MOLDE) · Modules/Governance/**
depende: 01 (o contrato fixa a copy literal que os UC citam)
antes:  8 de 9 telas de governança sem casos.md
depois: 7 de 9 — e o formato provado na menor tela
---
# 03a · `casos.md` de Policies

## Por que Policies primeiro, e por que uma só
`casos.md` **1 de 9** é a maior lacuna de máquina do módulo. Mas 8 num PR **reprova no `casos-gate` G-2** (`1 seção = 1 PR`; prosa é o que conta, `.tsx` copiado não). E a ficha do §13.2 diz o resto: `Dashboard.tsx` tem **42.343 B** — estoura sozinho o teto de 40 KB de leitura.

Então a frente abre pela **menor tela**: `Policies.tsx` = **4.889 B**, cabe inteira com folga. O que se prova aqui é o **formato**; as outras 7 seguem a mesma forma, uma por PR, na ordem do §2 do índice.

## ÂNCORA
```
ler      resources/js/Pages/governance/Policies.tsx        4.889 B  (inteira — é pequena)
ler      resources/js/Pages/governance/Policies.charter.md 3.675 B
molde    resources/js/Pages/governance/DsRollout.casos.md  8.618 B  ← o ÚNICO casos.md do
         módulo. Copie a FORMA (numeração de UC, como cita teste, como marca backlog).
         NÃO edite este arquivo.
contrato prototipo-ui/contrato/governance-cockpit.contract.json (desce na thread 01)
         → a copy literal dos UC sai DELE, não de paráfrase
NÃO ler  Dashboard.tsx · ModuleGradeService.php · os outros 7 .tsx
```

## O que os UC têm de cobrir (a tela é pequena, os invariantes não)
1. lista de políticas com **disparos** e **versão**; estados `Ativa` / `Desligada`; estado **vazio**.
2. KPIs: `Regras no total` · `Ativas` · `Disparos` · `Categorias`.
3. alternar → **sem modal** (reversível; atrito proibido pelo charter vivo).
4. aviso **"Alternar não deixa rastro"** — `mcp_governance_rule_history` não existe.
5. **proibido alternar em lote** (está nas 6 proibições do contrato).
6. sem `governance.policies.edit` → toggle barrado (com a ressalva D-GATE do índice).
7. `throttle:10,1` no toggle.

Cada UC **cita o teste que o prova**. Não havendo teste, o UC nasce marcado `[BACKLOG · ⬜ sem teste]` — como o `DsRollout.casos.md` já faz. **Não inventar teste verde.**

## Execução
```
PASSO   1) gh pr list --state open × Pages/governance/
        2) ler o molde (DsRollout.casos.md) e extrair a FORMA
        3) ler Policies.tsx + charter + a seção do contrato
        4) escrever Policies.casos.md com os 7 UC, cada um citando teste ou BACKLOG
        5) rodar casos:check / contrato:check
        6) _saida-03a.md registrando o que a frente aprendeu sobre o formato
PARAR SE (a) casos:check exigir UC citado por teste que não existe → marque BACKLOG,
             não escreva teste novo nesta thread (viraria 2 assuntos no PR)
         (b) a copy do contrato divergir da tela → registre; a tela é a verdade
             de comportamento, o contrato é a verdade de copy — divergência é achado
         (c) der vontade de "aproveitar e fazer o Audit também" → NÃO. G-2.
```

## Checklist de saída
1. `Policies.casos.md` existe · 2. 7 UC · 3. cada um com teste citado ou BACKLOG explícito · 4. copy vinda do contrato · 5. `casos:check` verde · 6. `DsRollout.casos.md` intacto · 7. `_saida-03a.md` com a lição de formato para as 7 telas seguintes · 8. placar no PR
