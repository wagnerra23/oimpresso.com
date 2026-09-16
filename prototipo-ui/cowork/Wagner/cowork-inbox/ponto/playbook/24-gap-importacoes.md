<!-- SESSÃO FRIA · abra esta thread sozinha. Read-order mínimo e prompt de abertura: `_SESSAO-FRIA.md` (linha "Importações · gap").
     Os ids de decisão (D-*) só existem em `ATA-DECISOES-2026-09-14.md` — leia a ata antes, ou as siglas ficam órfãs.
     Não leia as outras threads: cada uma é 1 PR e o contexto delas não é pré-requisito desta. -->

# 24 · gap.md de Importações — onda 6 (3 telas, 1 símbolo)

> **Entrega:** propostas de `memory/requisitos/Ponto/importacoes-{index,create,show}-gap.md`.
> **Lido no turno:** os 3 charters (`Index` 2.656 B · `Create` 2.973 B · `Show` 2.894 B) · protótipo `ponto-telas.jsx :635-767` (símbolo `Importacoes`, inteiro). **NÃO lido:** `Index.tsx` (7.096 B) · `Create.tsx` (5.438 B) · `Show.tsx` (5.763 B) ⇒ lado vivo **TODO**.
> **1 símbolo, 3 telas:** `Importacoes :: 635-767` contém lista (`:711-767`), upload (`:719-748` — Card que abre inline) e detalhe (`:646-710` — o `if (sel)`).

## A · `importacoes-index-gap.md` — 4 regiões

| # | região | protótipo | `data-contract` | status |
|---|---|---|---|---|
| 1 | Barra — nota "duplicado por SHA-256 é rejeitado" + `Nova importação AFD` | `:713-717` | *falta* | ✅ a nota **materializa** o Non-Goal de dedup |
| 2 | **Histórico — 9 colunas:** ID (mono 62px), Arquivo (mono + "N linhas com erro"/"sem erros"), Tipo (pill), Tamanho, Estado, Linhas ("processadas de total"), Usuário, Importado em (mono), Ação | `:750-766` | ✅ `importacoes-historico-de-importacoes` | ⚠️ colunas além do charter |
| 3 | Paginação | `:765` | — | **corrigido: 15 → 20/pág** |
| 4 | **Empty state com CTA** | só texto (*"Nenhuma importação AFD realizada ainda."*) | — | 🟠 **região a nascer** (charter pede CTA) |

**Divergências:** o charter lista 7 colunas (arquivo, tipo, tamanho, estado, linhas criadas/processadas, usuário, quando **humanizado**) — eu tenho **ID** e **Ação** a mais, e uso **data absoluta mono** em vez de humanizada. E a minha sub-linha do arquivo já diz se houve erro, o que o charter não pede. **Ordenação:** o charter manda *"ordenada por mais recente"*; eu insiro no topo (`[novo, ...rs]`) mas **não ordeno a lista existente** — se o mock vier fora de ordem, sai fora de ordem. Defeito meu, de mesma família dos 6 já corrigidos.

## B · `importacoes-create-gap.md` — o upload que vive dentro da lista

| # | região | protótipo | status |
|---|---|---|---|
| 1 | Tipo (AFD/AFDT, com o nome completo em cada opção) | `:722-725` | ✅ bate |
| 2 | Arquivo (`input type="file"`, nota "layout Portaria 671/2021") | `:726-728` | ⚠️ **sem `accept=".txt"`** (o charter diz upload de `.txt`) |
| 3 | Bloco explicativo do fluxo | `:729-731` | ⚠️ **parcial** — eu cito o job (`ProcessarImportacaoAfdJob`) e o assíncrono, e **não cito SHA-256 → dedup**; o charter pede os 4 passos (SHA-256 → dedup → job → acompanhamento) |
| 4 | **Barra de progresso do upload** (`form.progress.percentage`) | **NÃO EXISTE** | 🟠 **região a nascer** — o DS tem `Progress` (`variant="bar"`) |
| 5 | Rodapé Cancelar + `Enviar para processamento` | `:732-742` | ⚠️ o charter declara redirect **pro Show da importação criada**; eu volto pra lista |
| 6 | **Tela própria × Card inline** | é Card dentro do Index (`nova` toggle) | ⚠️ `D-PONTO-DETALHE` (4ª ocorrência) — o charter tem rota `/ponto/importacoes/novo` |

## C · `importacoes-show-gap.md` — a tela mais rica, e a que tem o gap mais óbvio

| # | região | protótipo | `data-contract` | status |
|---|---|---|---|---|
| 1 | Cabeçalho — Voltar + "Importação #id" + nome do arquivo + **Baixar original** | `:648-654` | *falta* | ✅ bate (o botão avisa que o download é fora do protótipo) |
| 2 | **Dados do arquivo** — ID, Nome, Tipo, Tamanho, Estado, Usuário, Importado/Iniciado/Concluído em + **hash SHA-256** em bloco próprio | `:657-681` | ✅ `importacoes-dados-do-arquivo` | ✅ **supera o charter** (ele pede 6 campos; eu mostro 9 + hash) |
| 3 | **Diagnóstico do processamento** (`<pre>` com o log) | `:682` | ✅ `importacoes-diagnostico-do-processamento` | ⚠️ **extra meu** — não está no charter |
| 4 | **Amostra de erros** — 4 colunas (Linha, NSR, Tipo, Mensagem) | `:683-691` | ✅ `importacoes-amostra-de-erros` | ⚠️ **extra meu** — e é o mais útil da tela para o RH |
| 5 | **Resumo do processamento** — 4 KPIs (totais, processadas, criadas, erros) + barra de % + nota de encoding/limite/chunk | `:692-708` | ✅ `importacoes-resumo-do-processamento` | ✅ cobre os contadores do charter |
| 6 | **Auto-refresh 3s + rótulo "auto-refresh 3s…"** enquanto pendente/processando | **NÃO EXISTE** | — | 🟠 **região a nascer** (é hook declarado do vivo: `router.reload({only:['importacao']})`) |
| 7 | **Alerta de erro com `erro_mensagem`** quando falha | tenho o log e a amostra, **não o alerta** | — | 🟠 **região a nascer** |

### O que este gap ensina sobre o módulo

As regiões 3 e 4 (**diagnóstico** e **amostra de erros com NSR**) são o tipo de coisa que **só quem desenhou a tela de operação inventa** — o charter não as pede, e elas respondem a pergunta real do RH: *"por que 37 linhas falharam?"*. Sobem como emenda. Em troca, eu **não tenho** os dois mecanismos que o charter declara (**progresso de upload** e **polling de 3s**), que são exatamente o que faz a tela ser útil enquanto o job roda. **Troca simétrica: eu tenho o diagnóstico, falta o tempo real.**

```json
[
  {
    "id": "D-IMP-EXTRAS",
    "pergunta": "Diagnóstico (log) e Amostra de erros (linha/NSR/tipo/mensagem) entram no contrato do Show?",
    "medido": "charter Show pede 2 cards (Arquivo, Processamento) + badge + alerta + download. O protótipo tem 4 cards, com log e amostra de erros que o charter não menciona.",
    "recomendacao_CC": "entram — é o que responde 'por que falhou' sem abrir o arquivo.",
    "dono": "[W]"
  },
  {
    "id": "D-IMP-FILTRO",
    "pergunta": "A lista ganha filtro por estado/tipo?",
    "medido": "é pendência ABERTA do próprio charter Index ('confirmar se falta filtro por estado/tipo nesta lista (hoje não tem)'). O protótipo também não tem — os dois lados concordam na ausência.",
    "nota": "diferente das outras: aqui ninguém está atrás de ninguém; é decisão de escopo.",
    "dono": "[W]"
  }
]
```

**PARAR SE** — o `Show.tsx` vivo já tiver o polling ⇒ região 6 é catch-up meu · alguém tentar portar meu `Math.max(...rows.map(r=>r.id))+1` de geração de id ⇒ **parar** (é mock; o vivo usa o id do banco e dedup por SHA-256).
