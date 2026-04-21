# 05 — Preferências do Usuário

Capturado ao longo das conversas com a Eliana (WR2 Sistemas). Estas preferências têm precedência sobre escolhas padrão a menos que conflitem com requisitos legais.

## Comunicação

- **Idioma:** PT-BR sempre
- **Tom:** profissional mas direto, sem floreio
- **Formato de resposta:** curto por padrão; expandir só quando pedido
- **Ensino antes de execução:** quando envolve gasto de crédito significativo, ela prefere que eu *explique como funciona* antes de produzir
  - Exemplo literal: *"primeiro me ensine como funciona para eu não gastar credito"*

## Escopo de trabalho

- **Uma coisa por vez.** Prefere iterar 1 tela, validar, depois replicar, a gerar 9 telas de uma vez
- **Confirmar escopo antes de produção massiva.** Use AskUserQuestion com 2-4 opções + recomendação
- **Economizar tokens.** Evitar boilerplate desnecessário, reaproveitar componentes, não regenerar o que já está bom

## Arquitetura

- **Não reinventar a roda.** UltimatePOS já existe — estender, não substituir
- **Não invadir core.** Mudanças ficam no módulo WR2
- **Compliance como requisito, não diferencial.** Portaria 671, CLT, LGPD, eSocial são mínimos, não features premium
- **Jana é a referência canônica para estrutura de módulo** (ver ADR 0011). Antes de criar qualquer arquivo novo em `Modules/PontoWr2/`, olhar o equivalente em `Modules/Jana/` e imitar. Não inventar estrutura "moderna" — o UltimatePOS congelou convenções de uma versão antiga do nWidart/laravel-modules e qualquer divergência quebra em produção

## UI / UX

- **Sidebar única com 1 entrada para o módulo.** UltimatePOS já tem várias entradas; o Ponto WR2 **ocupa apenas uma**
  - Citação literal: *"deve conter apenas 1 item no meu [menu], e ter um meu [menu] no topo com os itens do ponto, meu ultimatepos je tem muitos mudulos"*
- **Menu horizontal em abas** para as sub-seções do módulo
- **Estética shadcn/Tailwind** para protótipos visuais (mesmo que produção use AdminLTE)
- **Paleta preferida:** neutro (zinc/slate) + azul como acento
- **Dados mock brasileiros realistas** em protótipos (João Silva, Maria Oliveira, departamentos BR)

## Preview de UI

- **Prefere HTML estático auto-contido** a projetos Node/React complexos para validação visual (aprendido na sessão 01: React+Babel via CDN falhou; HTML puro funcionou)
- **Chart.js via CDN** é aceitável para gráficos em protótipos

## Documentação

- **Markdown com Mermaid** para diagramas (C4, ERD, sequência, state)
- **Documentos técnicos em PT-BR**
- **Compliance legal citada textualmente** (artigos da CLT, portaria, leis)

## Decisões arquiteturais já tomadas (consolidadas)

1. **Opção C — Estender UltimatePOS** (não Build, não Fork)
2. **nWidart/laravel-modules** como sistema de módulos (versão antiga, congelada — imitar Jana)
3. **Bridge `ponto_colaborador_config`** em vez de modificar `users`
4. **Append-only com triggers MySQL** para marcações e movimentos de BH
5. **UUID** para entidades auditáveis (Marcacao, Intercorrencia, Rep, BancoHorasMovimento, Importacao)
6. **BigInt** para entidades de lookup (Escala, ApuracaoDia, Colaborador)
7. **Multi-tenancy lógica via `business_id`** (não physical por ora)
8. **Sidebar 1-item + tabs horizontais** para navegação interna
9. **Blade/AdminLTE** em produção, **HTML/Tailwind/Chart.js** em protótipos
10. **Padrão Jana** — `start.php` + `Http/routes.php` + 1 ServiceProvider (ADR 0011)
11. **API com `auth:api` (Passport)**, não Sanctum — padrão UltimatePOS
12. **Lang folder com código curto** (`pt/`, não `pt-BR/`)

## Anti-preferências (o que ela NÃO quer)

- React + shadcn oficial como preview rápido — falhou, não insistir sem necessidade
- Projetos com `npm install` quando a pergunta é só "como fica visualmente?"
- Documentação com headings/bullets excessivos — ela lê e processa melhor prosa
- Explicações redundantes do óbvio
- Gastar crédito em preview de 9 telas de uma vez
- Usar convenções Laravel "modernas" no módulo UltimatePOS (provoca crash em produção — aconteceu em 2026-04-18 17:12)
- Código meu sobe pro servidor sem eu ter alertado antes sobre pré-requisitos/risco — se for para produção, **sempre** avisar primeiro

---

**Última atualização:** 2026-04-18 (sessão 02 — regras Jana + auth:api + aviso de produção)
