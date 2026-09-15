---
modulo: shell-usermenu
titulo: "Rodapé do usuário (AppShellV2 · Sidebar) — cascatas que decidem estado do shell"
dono_pedido: "[CC]"
executor: "[CL]"
gerado: 2026-09-11
base_lido: 6fc8b8fac31d
alvo_medido: NAO
---
# Rodapé do usuário — 3 threads

> **Leia só este arquivo + a thread da sua vez.** Não precisa da conversa. Read-order no `main`: `prototipo-ui/COWORK-ESTRUTURA-E-TELAS.md` → `FRESCOR-PRODUCAO-vs-PROTOTIPO.md` → `PRE-FLIGHT-TELA.md` → `Layouts/AppShellV2.charter.md`.
>
> ⚠️ **Este pacote NÃO é um EXPORT de layout.** Não há ALVO medido (sonda de protótipo com dupla leitura) neste ciclo — logo **não tem os 10 blocos** e não autoriza mexer em pixel. É pedido de **comportamento**: três controles do menu do usuário que, no vivo, prometem e não entregam. Layout do menu: **não tocar**.

```json
{
  "modulo": "shell-usermenu",
  "base_lido": "6fc8b8fac31d",
  "variaveis": {
    "SIDEBAR": "resources/js/Components/cockpit/Sidebar.tsx",
    "SHELL": "resources/js/Layouts/AppShellV2.tsx",
    "SHARED": "resources/js/Components/cockpit/shared.ts"
  },
  "decisoes": [
    {
      "id": "D-CMD-BARRA",
      "pergunta": "Sidebar.tsx:1273 renderiza o kbd '⌘/' no trigger de Modo de trabalho, mas NENHUM listener liga: AppShellV2 liga ⌘K (:389) e ⌘\\ (:395), e useSidebarShortcut.ts:135 descarta evento com metaKey. Alem disso '/' SEM modificador ja e 'focar busca da pagina' em 5+ telas (Cliente/Index:742, Essentials/{Todo,Licencas,Knowledge}, Financeiro/{Cobranca,Unificado}). Liga o atalho ou remove o rotulo?",
      "respondida": false,
      "dono": "[W]",
      "define": "DESTINO_DO_CMD_BARRA"
    },
    {
      "id": "D-PRESENCA",
      "pergunta": "Presenca (Disponivel/Ocupado/Ausente/Invisivel) tem receptor? Nao encontrei tabela de status de usuario no main lido. Se nao existe, o controle e UI-only e isso precisa estar declarado no charter — ou o item sai do menu.",
      "respondida": false,
      "dono": "[W]",
      "define": "RECEPTOR_DE_PRESENCA"
    }
  ],
  "threads": [
    {
      "id": "01",
      "titulo": "Aparencia: a cascata de tema abre e escolhe (hoje o botao nao tem handler)",
      "dono": "CL",
      "arquivo": "01-aparencia-tema.md",
      "prefixo": ["resources/js/Components/cockpit/Sidebar.tsx"],
      "nao_toca": ["resources/css/**", "resources/js/Layouts/AppShellV2.tsx"],
      "depende_threads": [],
      "depende_decisoes": [],
      "provas": [
        { "tipo": "execucao", "cmd": "npm run lint && npx tsc --noEmit", "exige": "exit 0" },
        { "tipo": "runtime", "exige": "clicar Aparencia abre 2 opcoes (Escuro padrao / Claro); escolher escreve data-theme no <html> e persiste no reload; controle positivo: recarregar com light guardado volta em light" }
      ]
    },
    {
      "id": "02",
      "titulo": "Sair: confirmar antes de encerrar (hoje o item nao tem handler)",
      "dono": "CL",
      "arquivo": "02-sair-confirma.md",
      "prefixo": ["resources/js/Components/cockpit/Sidebar.tsx"],
      "nao_toca": ["routes/**", "app/Http/Controllers/**"],
      "depende_threads": [],
      "depende_decisoes": [],
      "provas": [
        { "tipo": "execucao", "cmd": "npm run lint && npx tsc --noEmit", "exige": "exit 0" },
        { "tipo": "runtime", "exige": "Sair pede confirmacao inline; Cancelar volta ao menu sem efeito; Encerrar dispara o logout REAL do app (POST /logout do UltimatePOS) — nao reload" }
      ]
    },
    {
      "id": "03",
      "titulo": "+ Adicionar empresa: item com role=menuitem e sem acao nos dois dropdowns",
      "dono": "CL",
      "arquivo": "03-adicionar-empresa.md",
      "prefixo": ["resources/js/Components/cockpit/Sidebar.tsx"],
      "nao_toca": ["Modules/Superadmin/**"],
      "depende_threads": [],
      "depende_decisoes": [],
      "provas": [
        { "tipo": "execucao", "cmd": "npm run lint && npx tsc --noEmit", "exige": "exit 0" },
        { "tipo": "runtime", "exige": "o item navega pro destino de business (Superadmin > Negocios) OU vira disabled com motivo; nenhum role=menuitem sem acao sobra no dropdown — conferir nos DOIS (expandido e rail)" }
      ]
    }
  ]
}
```

| # | thread | prefixo | veredito |
|---|---|---|---|
| **01** | Aparência abre e escolhe tema | `Sidebar.tsx` | **CABE** |
| **02** | Sair confirma e encerra de verdade | `Sidebar.tsx` | **CABE** |
| **03** | + Adicionar empresa navega ou desabilita | `Sidebar.tsx` | **CABE** |
| — | ⌘/ no trigger de Modo de trabalho | `Sidebar.tsx` | **BLOQUEADA** por `D-CMD-BARRA` ([W]) |
| — | presença (Disponível/Ocupado/…) | — | **BLOQUEADA** por `D-PRESENCA` ([W]) |

## O que este pacote NÃO resolve (bloco 7)
- **Atmosfera por vibe.** O protótipo daqui só marca `data-vibe` e persiste; **não medi** o que o `cockpit.css` reescreve por vibe nem o `vibeAccent`. Nada a exportar — e não aceite "o protótipo tem" como fonte.
- **Ícone do trigger de Modo de trabalho.** Não medido no vivo (a linha não voltou na leitura); no protótipo usei `palette` por inferência dos imports Lucide. Inferência, não medição.
- **Nada de layout.** Sem ALVO medido, qualquer ajuste de espaçamento/medida aqui seria chute.
- **Correções do build do Cowork não entram**: a colisão de prefixo `.pf-` (perfil × acessos) e o alinhamento do rail eram defeito **meu**, deste lado. Já corrigidos aqui; não viram PR no `main`.
