#!/usr/bin/env python3
"""
Gera a tabela de Código de Tributação Nacional (cTribNac) da NFS-e — R-NFSE-006.

Fonte única: aba "LISTA.SERV.NAC." do Anexo B oficial do Sistema Nacional NFS-e
(`anexo_b-nbs2-lista_servico_nacional-snnfse-v1-01-20260122.xlsx`, publicado em
https://www.gov.br/nfse/pt-br/biblioteca/documentacao-tecnica/documentacao-atual).

cTribNac = item (2) + subitem (2) + desdobro nacional (2) — XSD v1.01, TCCServ/cTribNac.
Só entram as linhas com código (as linhas de item/subitem sem código são títulos).

Uso (só biblioteca padrão):
  python scripts/fiscal/lista-servico-nacional.py <anexo_b.xlsx> \
      Modules/NfeBrasil/Resources/data/nfse/lista-servico-nacional-v1.01.json

O arquivo gerado é a única entrada do `ListaServicoNacional`; não editar à mão.
"""
import hashlib
import json
import re
import sys
import zipfile
import xml.etree.ElementTree as ET

NS = {
    "m": "http://schemas.openxmlformats.org/spreadsheetml/2006/main",
    "r": "http://schemas.openxmlformats.org/officeDocument/2006/relationships",
}
ABA = "LISTA.SERV.NAC."
FONTE = "anexo_b-nbs2-lista_servico_nacional-snnfse-v1-01-20260122.xlsx"


def coluna(ref):
    n = 0
    for ch in re.match(r"[A-Z]+", ref).group(0):
        n = n * 26 + ord(ch) - 64
    return n - 1


def linhas(xlsx):
    z = zipfile.ZipFile(xlsx)
    textos = []
    if "xl/sharedStrings.xml" in z.namelist():
        for si in ET.fromstring(z.read("xl/sharedStrings.xml")).findall("m:si", NS):
            textos.append("".join(t.text or "" for t in si.iter("{%s}t" % NS["m"])))
    wb = ET.fromstring(z.read("xl/workbook.xml"))
    rels = {r.get("Id"): r.get("Target") for r in ET.fromstring(z.read("xl/_rels/workbook.xml.rels"))}
    alvo = None
    for s in wb.find("m:sheets", NS):
        if s.get("name") == ABA:
            alvo = rels[s.get("{%s}id" % NS["r"])]
    if alvo is None:
        sys.exit(f"aba {ABA!r} ausente no arquivo")
    planilha = ET.fromstring(z.read("xl/" + alvo.lstrip("/").replace("xl/", "")))
    for row in planilha.iter("{%s}row" % NS["m"]):
        vals = {}
        for c in row.findall("m:c", NS):
            v = c.find("m:v", NS)
            t = c.get("t")
            if t == "inlineStr":
                val = "".join(tt.text or "" for tt in c.iter("{%s}t" % NS["m"]))
            elif v is None:
                continue
            elif t == "s":
                val = textos[int(v.text)]
            else:
                val = v.text
            vals[coluna(c.get("r"))] = val
        if vals:
            yield [vals.get(i, "") for i in range(max(vals) + 1)]


def main(xlsx, destino):
    sha = hashlib.sha256(open(xlsx, "rb").read()).hexdigest()
    codigos = {}
    for n, r in enumerate(linhas(xlsx)):
        if n == 0:
            continue  # cabeçalho
        r = r + [""] * 5
        cod, item, sub, desd, desc = r[:5]
        if not str(cod).strip():
            continue  # título de item/subitem, sem código
        c = str(int(float(cod))).zfill(6)
        esperado = str(int(float(item))).zfill(2) + str(int(float(sub))).zfill(2) + str(int(float(desd))).zfill(2)
        if c != esperado:
            sys.exit(f"linha {n + 1}: código {c} não bate com item/subitem/desdobro {esperado}")
        if c in codigos:
            sys.exit(f"linha {n + 1}: código {c} duplicado")
        codigos[c] = " ".join(str(desc).split())
    saida = {
        "fonte": FONTE,
        "fonte_sha256": sha,
        "aba": ABA,
        "total": len(codigos),
        "codigos": dict(sorted(codigos.items())),
    }
    with open(destino, "w", encoding="utf-8", newline="\n") as f:
        json.dump(saida, f, ensure_ascii=False, indent=1)
        f.write("\n")
    print(f"{len(codigos)} codigos -> {destino}")


if __name__ == "__main__":
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    main(sys.argv[1], sys.argv[2])
