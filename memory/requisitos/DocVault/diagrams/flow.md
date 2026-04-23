# Fluxos principais · DocVault

## F1 · Ingestão de evidência

```mermaid
sequenceDiagram
    actor User
    participant UI as Ingest.tsx
    participant Ctrl as IngestController
    participant Src as DocSource
    participant Ev as DocEvidence
    participant Fs as Storage::disk(public)

    User->>UI: upload arquivo / URL / texto
    UI->>Ctrl: POST /docs/ingest (multipart)
    alt arquivo
        Ctrl->>Fs: store em docvault/YYYY-MM/
        Fs-->>Ctrl: storage_path
    end
    Ctrl->>Src: create(type, title, storage_path, source_url)
    Ctrl->>Ev: create(status='pending', kind, module_target)
    Ctrl-->>UI: redirect /docs/inbox
    UI-->>User: evidencia criada
```

## F2 · Triagem e aplicação

```mermaid
stateDiagram-v2
    [*] --> pending: ingest criou
    pending --> triaged: user classifica (kind, module, story)
    pending --> rejected: user marca como ruído
    pending --> duplicate: já existe evidência similar
    triaged --> applied: regra/story gerada e gravada no SPEC.md
    triaged --> rejected: revisão descartou
    applied --> [*]
    rejected --> [*]
    duplicate --> [*]
```

## F3 · Ciclo da rastreabilidade tripla (ADR 0005)

```mermaid
flowchart LR
    A[Dev cria .tsx] -->|bloco @docvault| B[docvault:sync-pages]
    B -->|popula| C[(docs_pages)]
    C --> D[docvault:validate]
    D -->|encontra orfas| E[issues em docs_validation_runs]
    E --> F[Dev adiciona testes]
    F -->|atualiza Testado em| G[SPEC.md]
    G --> D
    D -->|sem issues| H[Dashboard verde]
```

## F4 · Busca no ChatAssistant

```mermaid
flowchart TD
    Q[Pergunta do user] --> T[extractTerms]
    T --> R{Tem module_context?}
    R -->|sim| M1[Busca só no módulo]
    R -->|não| M2[Busca em módulos + Primer + Projeto + Claude memory]
    M1 --> S[scoreText com pesos]
    M2 --> S
    S --> TOP[Top 6 por score]
    TOP --> AI{AI_ENABLED?}
    AI -->|sim| O[OpenAI com snippets como contexto]
    AI -->|não| OFF[buildOfflineReply: lista snippets citando fonte]
    O --> REPLY[Resposta final]
    OFF --> REPLY
    REPLY --> SAVE[Grava em docs_chat_messages]
```

## F5 · Sincronização diária Claude → repo

```mermaid
flowchart LR
    S1[Scheduler Laravel<br/>23:00 diário] --> Cmd[docvault:sync-memories]
    Cmd --> Read[Lê ~/.claude/projects/.../memory/]
    Read --> Diff{MD5 diff<br/>por arquivo}
    Diff -->|novo| Copy[Copia para memory/claude/]
    Diff -->|modificado| Copy
    Diff -->|igual| Skip[Ignora]
    Copy --> Report[Relatório +N ~M -K]
    Report --> Opt{--commit?}
    Opt -->|sim| Git[git add + git commit]
    Opt -->|não| End[Fim — dev commita manual]
```
