---
id: requisitos-superadmin-pacotes-visual-comparison
title: "Comparação design × produção — superadmin/Pacotes/Index"
module: Superadmin
tela: superadmin/Pacotes/Index
owner: W
status: rascunho
last_validated: "2026-11-16"
---

# Comparação design × produção — `superadmin/Pacotes/Index` (Pacotes)

> **Âncora computada** (`node scripts/design/ancora.mjs superadmin/Pacotes --staging prototipo-ui/cowork`):
> `prototipo-ui/cowork/Wagner/superadmin-page.jsx`, declarada em `related_prototype` do charter.
> No shell do protótipo a vista é a rota `sa-pacotes` (`app.jsx`; override em
> `governance/design/targets/roles/`).
>
> Medido em **2026-11-16** pela sonda canônica (`design-diff.mjs --probe`), a mesma nos dois lados,
> e comparado por `design-diff.mjs --compare prod.json design.json --check --contrato governance/design/contracts/superadmin-pacotes.contract.json`.
> Snapshots e veredito em [`governance/design/targets/medidas/superadmin--Pacotes--Index/`](../../../governance/design/targets/medidas/superadmin--Pacotes--Index/) (`prod.json` sha `ff239228a617`,
> `design.json` sha `47d6efbac939`).

## Como foi medido

- **Produção:** `https://oimpresso.com/superadmin/packages`, sessão de superadmin já aberta no Chrome do [W] (só leitura;
  nenhum cookie ou senha manuseado). A medida antiga desta pasta era a **página 403** (`staging` sem
  usuário superadmin); esta é a tela.
- **Protótipo:** espelho `prototipo-ui/cowork/Wagner/oimpresso.com.html` servido localmente, no **mesmo Chrome**.
- **Tema:** claro nos dois lados — é o que a preferência `oi.theme` do navegador do [W] usa hoje. A
  produção não troca de tema pela classe `dark` no `<html>` (medido), então forçar escuro mediria claro × escuro.
- **Transporte:** o JSON de produção saiu da página por `postMessage` para uma ponte local que gravou em
  disco; o sha256 calculado na página bate com o do arquivo. Nada foi transcrito.
- **Dado pessoal e valor:** o texto visível de produção foi redigido **antes** de sair da página — a
  `assinatura` guarda só a copy do contrato que casou; dígitos, e-mails e valores em `label`/`texto`/`rotulo`
  viraram `#`/`[email]`/`[redigido]`.

## D0 — identidade da vista: **OK** (7 × 9 copies do contrato)

Os dois lados são a mesma tela pelo contrato `governance/design/contracts/superadmin-pacotes.contract.json`.

## Dimensões medidas

| Dim | Campo | Produção | Protótipo | Veredito |
|---|---|---|---|---|
| D2 | layout | ok | ok | IGUAL |
| D4 | tipografia | ok | ok | IGUAL |
| D6 | cor | ok | ok | IGUAL |
| D8 | kpi align | ok | ok | IGUAL |
| D4 | linha da tabela | não medido | não medido | SEM-DADO |


Vereditos pela regra do [PROTOCOLO-COMPARACAO-RUNTIME](../_DesignSystem/PROTOCOLO-COMPARACAO-RUNTIME.md)
(eixo FORMA: o protótipo manda — UI-0029): **DÍVIDA A FECHAR** = a produção converge;
**DADO (não é forma)** = nº de blocos de texto por célula depende do registro, não do layout;
**A CLASSIFICAR (fonte)** = o instrumento acusa diferença de origem do conteúdo, a decidir por humano.

## O que esta medida NÃO cobre (declarado, não suposto)

- **Regiões de contrato (D9):** `superadmin.pacotes.grid`, `superadmin.pacotes.novo-botao` existem na produção; o protótipo não carrega `data-contract`, então D9 sai SEM-DADO por construção — não é divergência.
- **KPI e botão primário:** os papéis padrão da sonda não casaram (`kpi` = 0 na produção × 0 no protótipo; `primary` = não casou na produção). Medir exige override de papel em `governance/design/targets/roles/superadmin--Pacotes--Index.json`.
- **Shell (sidebar, topo):** 28 campos `SHELL a classificar` — iguais nas 6 telas do Superadmin, são do AppShell e não desta tela.
- **Saúde do lado produção:** o `design-diff` acusa `tokens.prod NÃO MEDI` porque procura `--accent`… em `:root`. Na produção eles vivem em `.cockpit` (medido: `--accent`, `--pos`, `--neg`, `--warn`, `--text-dim`, `--border` resolvem lá; `--sunken` não existe). Nenhuma folha de estilo veio vazia. É limite do instrumento, não render quebrado.
- **D1 (rede):** medido em Negócios — digitar na busca dispara uma XHR `/superadmin/business?q=…`, sem recarregar o documento.
- **D3 (ícones) e D5 (rodapé/somatórios):** não medidos.

## Dívida desta tela

- Nenhuma divergência de tela acusada nas dimensões que a sonda alcançou (ver o que NÃO cobre).
