---
id: modules-producao-pages-producao-postos-index-charter
page: /producao/postos
component: Modules/Producao/Resources/js/Pages/Producao/Postos/Index.tsx
related_prototype: "n/a (sem vista própria no protótipo; segue as classes pcv-* do bundle e o PT-02 drawer 760)"
owner: wagner
status: draft
last_validated: "2026-10-16"
parent_module: Producao
related_adrs: [93, 358, 431]
alcance:
  rota: /producao/postos
  rota_nome: producao.postos.index
  permission: producao.access
  menu_hook: Modules/Producao/Http/Controllers/DataController.php::modifyAdminMenu
  pacote: producao_module
tier: B
charter_version: 1
---

# Page Charter — /producao/postos (DRAFT · postos, motivos, veículos, ferramentas, custo-hora)

> Nasce na thread 03 + emenda E03 do playbook de Produção (ADR 0431). Casos: [`Index.casos.md`](Index.casos.md).
> O protótipo não tem vista de postos; o celular (`Mobile Producao.dc.html` A4/A5/I7) mostra o uso dos motivos,
> do veículo e das ferramentas. Non-Goals e Anti-hooks além dos abaixo são de [W] — não se inferem aqui.

## Mission

Cadastrar o que a produção usa: postos e máquinas com capacidade e custo-hora, os motivos de pausa e de
refugo de cada posto, os veículos, as ferramentas com QR e o custo-hora da pessoa.

## Goals — Features (faz)

- Postos e máquinas com motivos de pausa e de refugo por posto (lista fechada, padrão sugerido) — UC-PCV-33.
- Veículo como posto de máquina, com placa, custo por hora e por km (E03).
- Ferramentas com código QR único por empresa (E03).
- Custo-hora da pessoa só para quem tem `producao.desempenho.ver` (E03, D-PERM).

## Non-Goals — Features (NÃO faz)

- ❌ Calcular custo real da OS (é o CustoRealService da thread 02, tela da thread 08).
- ❌ Saída e devolução de ferramenta por QR (thread 14).

## Refs

- Playbook `03-cadastros.md` + `E03-cadastros-motivos.md` · ADR 0431 · UI-0013
