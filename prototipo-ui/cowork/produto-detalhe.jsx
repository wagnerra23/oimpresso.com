// produto-detalhe.jsx — Drawer de detalhe do produto (PT-02).
// Porte de resources/js/Pages/Produto/Unificado/_components/DetalheProduto.tsx @ main
// (lido 2026-08-25). Ordem das seções conforme o handoff 21/08 §5 — disponibilidade
// primeiro, cadastro por último: alertas → identidade → Disponível → Preço e margem →
// Composição → Estoque → Giro → Identificação → Observações.
// Largura 420px (480 quando há composição). É painel de LEITURA: as saídas entregam o
// operador na tela responsável. Custo/margem gateados aqui também (§7).
// Expõe window.ProdutoDetalheDrawer.
(() => {
const { useEffect } = React;

function Linha({ rotulo, children }) {
  return (
    <div className="pdd-linha">
      <span className="pdd-linha-l">{rotulo}</span>
      {children}
    </div>);

}

function Secao({ titulo, children }) {
  return <section className="pdd-sec"><h3>{titulo}</h3><div className="pdd-sec-b">{children}</div></section>;
}

function Copiavel({ texto, rotulo, onCopiar }) {
  if (!onCopiar) return <span className="pd-mono">{texto}</span>;
  return (
    <button type="button" className="pdd-copiavel" title={`Copiar ${rotulo.toLowerCase()}`}
    onClick={() => onCopiar(texto, rotulo)}>
      {texto}<I.copy size={11} />
    </button>);

}

function ProdutoDetalheDrawer({ row, perm, piso, temAnterior, temProximo, posicao, onVizinho, onCopiar, onClose }) {
  useEffect(() => {
    const onKey = (e) => {if (e.key === "Escape") onClose();};
    document.addEventListener("keydown", onKey);
    return () => document.removeEventListener("keydown", onKey);
  }, [onClose]);
  if (!row) return null;

  const C = window.PROD_CATALOGO;
  const est = C.estadoEstoque(row);
  const zerado = row.stockQty === 0;
  const margem = C.margemFrac(row);
  const locais = row.locais || [];
  const temComposicao = perm.composicao && row.bomCount !== undefined && row.bomCount > 0;
  const zerados = locais.filter((l) => l.qtd === 0);
  const alertaLocal = zerados.length > 0 && locais.some((l) => l.qtd > 0) ?
  `0 na ${zerados.map((l) => l.nome).join(" e ")} — saldo disponível em outro local.` : "";
  const saldoTexto = (row.stockQty === null ? "—" : C.numP(row.stockQty)) + " " + row.unit;

  return (
    <div className="pdd-back" onClick={onClose}>
      <div className={"pdd" + (temComposicao ? " larga" : "")} role="dialog" aria-modal="true"
      aria-label={`Ficha de ${row.name}`} onClick={(e) => e.stopPropagation()}>
        <header className="pdd-head">
          <div className="pdd-head-t">
            <b>{row.name}</b>
            <span className="pd-mono">{row.codigo}{row.referencia ? ` · ${row.referencia}` : ""}</span>
          </div>
          <button className="os-icon-btn" onClick={onClose} aria-label="Fechar painel"><I.close size={16} /></button>
        </header>

        <div className="pdd-body">
          {!row.active &&
          <div className="pdd-alerta">
              <b>Produto inativo</b>
              <p>Não pode ser vendido nem incluído em orçamento. Consulta e histórico seguem disponíveis.</p>
            </div>
          }

          <div className="pdd-id">
            <span className="pd-mini" style={{ width: 60, height: 60 }} role="img" aria-label="Produto sem imagem" title="Sem imagem">
              <I.product size={30} />
            </span>
            <div className="pdd-id-t">
              <span className="pd-tipo">{C.TIPO_LABEL[row.tipo]}</span>
              <span className="pdd-cat">{row.cat_label ?? "—"}</span>
            </div>
          </div>

          <Secao titulo="Disponível">
            <Linha rotulo={locais.length > 1 ? "Saldo atual (todos os locais)" : "Saldo atual"}>
              <span className={"pdd-forte" + (zerado ? " zero" : "")}>{saldoTexto}</span>
            </Linha>
            <div className="pdd-selo">
              <span className={"pd-est " + est.chave}>
                {est.chave !== "nao" && <span className="pd-est-dot" />}
                {est.label}
                {est.rel !== null && <span className="pd-est-n">{est.rel}{row.unit ? ` ${row.unit}` : ""}</span>}
              </span>
            </div>
            {locais.length > 0 &&
            <div className="pdd-locais">
                <p role="heading" aria-level={5}>Por local</p>
                {locais.map((l) =>
              <Linha rotulo={l.nome} key={l.nome}>
                    <span className={"pd-mono" + (l.qtd === 0 ? " zero" : "")}>{C.numP(l.qtd)} {row.unit}</span>
                  </Linha>
              )}
                {alertaLocal && <p className="pdd-alerta-local"><I.alert size={12} /> {alertaLocal}</p>}
              </div>
            }
          </Secao>

          {(perm.preco || perm.custo) &&
          <Secao titulo="Preço e margem">
              {perm.preco && row.price !== undefined &&
            <Linha rotulo="Preço de venda"><span className="pdd-forte">{C.brlP(row.price)}</span></Linha>
            }
              {perm.custo && perm.preco && margem !== undefined &&
            <Linha rotulo="Margem">
                  <span className={"pd-mono forte" + (C.sobOPiso(row) ? " sob-piso" : "")}>{C.pctP(margem)}</span>
                </Linha>
            }
              {perm.custo && row.cost !== undefined &&
            <Linha rotulo="Custo"><span className="pd-mono dim">{C.brlP(row.cost)} / {row.unit}</span></Linha>
            }
              {!perm.custo &&
            <p className="pdd-restrito">Custo e margem são restritos ao administrador.</p>
            }
            </Secao>
          }

          {temComposicao &&
          <Secao titulo="Composição">
              <Linha rotulo="Itens na receita"><span className="pd-mono">{row.bomCount}</span></Linha>
              {row.bom.length > 0 && <ul className="pdd-bom">{row.bom.map((b) => <li key={b}>{b}</li>)}</ul>}
            </Secao>
          }

          <Secao titulo="Estoque">
            <Linha rotulo="Mínimo">
              <span className="pd-mono">{row.minimo === null ? "—" : `${C.numP(row.minimo)} ${row.unit}`}</span>
            </Linha>
            <Linha rotulo="Unidade"><span className="pd-mono">{row.unit}</span></Linha>
            {row.variants.length > 1 &&
            <Linha rotulo="Combinações"><span className="pd-mono forte">{row.variants.length}</span></Linha>
            }
          </Secao>

          <Secao titulo="Giro">
            <Linha rotulo="Última venda">
              <span className="pd-mono">{row.ultimaVenda ?? "sem registro"}</span>
            </Linha>
          </Secao>

          <Secao titulo="Identificação">
            <Linha rotulo="Código"><Copiavel texto={String(row.codigo)} rotulo="Código" onCopiar={onCopiar} /></Linha>
            {row.referencia &&
            <Linha rotulo="Referência"><Copiavel texto={row.referencia} rotulo="Referência" onCopiar={onCopiar} /></Linha>
            }
          </Secao>

          {row.obs &&
          <Secao titulo="Observações"><p className="pdd-obs-t">{row.obs}</p></Secao>
          }
        </div>

        <footer className="pdd-pe">
          <div className="pdd-esteira">
            <button type="button" onClick={() => onVizinho(-1)} disabled={!temAnterior} aria-label="Item anterior">‹</button>
            <span aria-live="polite">{posicao}</span>
            <button type="button" onClick={() => onVizinho(1)} disabled={!temProximo} aria-label="Próximo item">›</button>
          </div>
          <div className="pdd-saidas">
            <button className="os-btn ghost">Abrir cadastro</button>
            {perm.custo && <button className="os-btn ghost">Formar preço</button>}
            <button className="os-btn primary">Usar em orçamento</button>
          </div>
        </footer>
      </div>
    </div>);

}

window.ProdutoDetalheDrawer = ProdutoDetalheDrawer;
})();
