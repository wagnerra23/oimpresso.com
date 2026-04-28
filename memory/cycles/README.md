# memory/cycles/ — Cycles fechados (arquivo histórico)

> Cada Cycle de 2 semanas é arquivado aqui ao fechar (sex final do cycle), com retro de 5 linhas. Permite olhar pra trás "o que ciclei nos últimos 6 meses?" sem confundir com o `CURRENT.md` ativo.

---

## Convenção de nome

```
CICLO-NN-YYYY-MM-DD.md
```

- `NN` — número sequencial do cycle (01, 02, 03...)
- `YYYY-MM-DD` — data de fechamento (sex final do cycle)

Exemplos:
- `CICLO-01-2026-05-12.md` — primeiro cycle, fechou 12-mai-2026
- `CICLO-02-2026-05-26.md` — segundo cycle, fechou 26-mai-2026

---

## Estrutura mínima de cada arquivo arquivado

Copia o `CURRENT.md` no momento do fechamento + adiciona seção retro no topo:

```markdown
# Cycle NN — closed YYYY-MM-DD

## 🎯 Goal original
[copia do CURRENT.md]

## 📊 Resultado das métricas
- Métrica 1: ✅ / ❌ / 🟡 (valor real vs alvo)
- Métrica 2: ...
- Métrica 3: ...

## 🏆 Sucessos (3 frases)
1. ...
2. ...
3. ...

## 🚧 Falhas / não-fechei (3 frases)
1. ...
2. ...
3. ...

## 📚 Lição pro próximo Cycle (1 frase)
> "..."

## 🔁 Tasks que escapam pro Cycle N+1
- [task X] — repriorizada, não fechou em tempo
- [task Y] — bloqueada por Z

---

[abaixo: copia integral do CURRENT.md no fechamento]
```

---

## Quem fecha o Cycle

Wagner [W], na sex final do cycle (10º dia útil). Pode delegar pra Felipe [F] se Wagner indisponível, mas a parte de **lição pro próximo** sempre Wagner valida.

---

## Política de retenção

- Cycles do **ano corrente:** todos visíveis aqui
- Cycles **>1 ano:** mover pra `_archive/YYYY/` quando final do ano novo (não deletar)
- Cycles **>3 anos:** consolidar em `_archive/YYYY/RESUMO-ANO.md` com 1 frase por cycle (manter brutos no git history)

---

## Como ler "o que andamos fazendo nos últimos N meses"

```bash
ls memory/cycles/CICLO-*.md | tail -N    # últimos N cycles
```

Ou abrir o último N e ler só a seção "Goal" + "Sucessos" no topo. **Não precisa abrir tudo** — é por isso que retro tem que estar no topo.

---

> Versão: 1.0 (2026-04-28). Atualizar se a convenção mudar.
