#!/usr/bin/env python3
"""Gera o Mapa de Telas panoramico do oimpresso.

Para cada tela (Pages/**/*.tsx, exceto *.charter.md / *.casos.md):
  - caminho (link clicavel relativo)
  - tem charter? (.charter.md ao lado)
  - Mission do charter (o que a tela DEVERIA fazer) — 1a frase
  - status do charter (draft/live)
Agrupa por modulo (1o segmento sob Pages/). Conta e ranqueia.
"""
import os, re, glob, datetime
from collections import defaultdict

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))  # raiz do repo (scripts/..)
PAGES = os.path.join(ROOT, "resources", "js", "Pages")
OUT = os.path.join(ROOT, "memory", "sessions",
                   datetime.date.today().isoformat() + "-mapa-telas-projeto.md")

def rel(p):
    return os.path.relpath(p, ROOT).replace("\\", "/")

def read(p):
    try:
        with open(p, encoding="utf-8") as f:
            return f.read()
    except Exception:
        return ""

def charter_info(charter_path):
    """Retorna (status, mission_1frase)."""
    txt = read(charter_path)
    status = "?"
    m = re.search(r"^status:\s*(\S+)", txt, re.M)
    if m:
        status = m.group(1).strip()
    mission = ""
    mm = re.search(r"^##\s*Mission[^\n]*\n+(.+?)(?:\n\n|\n##)", txt, re.S | re.M)
    if mm:
        block = mm.group(1).strip()
        # primeira frase
        first = re.split(r"(?<=[.!?])\s", block)[0]
        mission = re.sub(r"\s+", " ", first).strip()
        if len(mission) > 180:
            mission = mission[:177] + "..."
    return status, mission

# coletar telas
telas = []
for path in glob.glob(os.path.join(PAGES, "**", "*.tsx"), recursive=True):
    base = os.path.basename(path)
    if base.endswith(".charter.md") or base.endswith(".casos.md"):
        continue
    sub = rel(path)[len("resources/js/Pages/"):]
    modulo = sub.split("/")[0]
    charter = path[:-4] + ".charter.md"
    has_charter = os.path.isfile(charter)
    status, mission = charter_info(charter) if has_charter else ("", "")
    # componente de apoio (não é tela): pasta */components|_components ou arquivo lowercase-first
    # componente de apoio (não é tela):
    #  - qualquer pasta intermediária com prefixo "_" (_drawer, _form, _show, _shared, _components...)
    #  - pastas helper conhecidas (components, partials, widgets, hooks, lib, utils)
    #  - arquivo lowercase-first (atoms.tsx, utils.tsx)
    mid = sub.split("/")[1:-1]  # segmentos entre módulo e arquivo
    is_componente = (
        any(seg.startswith("_") for seg in mid)
        or any(re.fullmatch(r"(?i)components|partials|widgets|hooks|lib|utils", seg) for seg in mid)
        or base[0].islower()
    )
    telas.append({
        "rel": rel(path), "sub": sub, "modulo": modulo,
        "nome": sub[:-4],  # sem .tsx
        "has_charter": has_charter, "status": status, "mission": mission,
        "componente": is_componente,
    })

por_mod = defaultdict(list)
comp_count = defaultdict(int)
for t in telas:
    if t["componente"]:
        comp_count[t["modulo"]] += 1
    else:
        por_mod[t["modulo"]].append(t)

# briefing por modulo (case-insensitive match em memory/requisitos)
req = os.path.join(ROOT, "memory", "requisitos")
briefings = {}
for d in os.listdir(req) if os.path.isdir(req) else []:
    b = os.path.join(req, d, "BRIEFING.md")
    if os.path.isfile(b):
        briefings[d.lower()] = rel(b)

def briefing_link(modulo):
    return briefings.get(modulo.lower())

screens = [t for t in telas if not t["componente"]]
comps = [t for t in telas if t["componente"]]
total = len(screens)
total_charter = sum(1 for t in screens if t["has_charter"])
total_live = sum(1 for t in screens if t["status"] == "live")

lines = []
lines.append("# Mapa de Telas — projeto oimpresso (panorâmico)")
lines.append("")
lines.append(f"> Gerado {datetime.date.today().isoformat()} por `scripts/gen-mapa-telas.py`. "
             "Fonte: filesystem (`resources/js/Pages/**/*.tsx`) + charters ao lado.")
lines.append("")
lines.append("## Resumo")
lines.append("")
lines.append("| Métrica | Valor |")
lines.append("|---|---:|")
lines.append(f"| **Telas** (páginas Inertia) | **{total}** |")
lines.append(f"| Componentes de apoio (`_components/`, atoms, helpers) | {len(comps)} |")
lines.append(f"| Total de arquivos `.tsx` em Pages | {len(telas)} |")
lines.append(f"| Áreas/módulos | {len(por_mod)} |")
lines.append(f"| Telas com charter (contrato do que deveria ter) | {total_charter} ({100*total_charter//total}%) |")
lines.append(f"| Charter `live` (aprovado por Wagner) | {total_live} |")
lines.append(f"| **Telas sem charter** (gap: contrato indefinido) | {total-total_charter} ({100*(total-total_charter)//total}%) |")
lines.append("")
lines.append("**Legenda da coluna _Deveria ter_:** se há charter, mostra a Mission (contrato). "
             "Se vazio (`—`) numa tela com charter, a Mission ainda não foi escrita; `❌` = tela **sem contrato definido** — primeiro gap a fechar.")
lines.append("")
lines.append("> Componentes de apoio não são listados tela a tela (são pedaços internos de uma tela). "
             "Cada módulo mostra `+N componentes` ao final.")
lines.append("")

# indice
lines.append("## Índice por módulo")
lines.append("")
lines.append("| Módulo | Telas | Com charter | Comp. | Briefing |")
lines.append("|---|---:|---:|---:|---|")
for mod in sorted(por_mod, key=lambda m: -len(por_mod[m])):
    ts = por_mod[mod]
    nc = sum(1 for t in ts if t["has_charter"])
    bl = briefing_link(mod)
    blink = f"[BRIEFING]({bl})" if bl else "—"
    anchor = re.sub(r"[^a-z0-9]+", "-", mod.lower()).strip("-")
    lines.append(f"| [{mod}](#{anchor}) | {len(ts)} | {nc}/{len(ts)} | {comp_count.get(mod,0)} | {blink} |")
lines.append("")

# detalhe por modulo
for mod in sorted(por_mod, key=lambda m: -len(por_mod[m])):
    ts = sorted(por_mod[mod], key=lambda t: t["sub"])
    lines.append(f"## {mod}")
    lines.append("")
    bl = briefing_link(mod)
    if bl:
        lines.append(f"📋 Estado do módulo: [{bl}]({bl})")
        lines.append("")
    lines.append("| Tela | Charter | Deveria ter (Mission do charter) |")
    lines.append("|---|:--:|---|")
    for t in ts:
        nome = t["sub"][len(mod)+1:] if t["sub"].startswith(mod + "/") else t["sub"]
        nome = nome[:-4] if nome.endswith(".tsx") else nome
        link = f"[{nome}]({t['rel']})"
        if t["has_charter"]:
            ch = "✅" if t["status"] == "live" else ("📝" if t["status"] == "draft" else "•")
        else:
            ch = "❌"
        mission = t["mission"] or "—"
        mission = mission.replace("|", "\\|")
        lines.append(f"| {link} | {ch} | {mission} |")
    if comp_count.get(mod, 0):
        lines.append("")
        lines.append(f"_+{comp_count[mod]} componentes de apoio (não listados)._")
    lines.append("")

lines.append("---")
lines.append("**Como ler:** Charter ✅=live (aprovado) · 📝=draft · ❌=sem charter (gap de contrato). "
             "A coluna _Deveria ter_ é o resumo do contrato; ausência = primeiro gap a documentar.")

with open(OUT, "w", encoding="utf-8") as f:
    f.write("\n".join(lines))

print(f"OK -> {rel(OUT)}")
print(f"telas={total} charter={total_charter} live={total_live} modulos={len(por_mod)}")
