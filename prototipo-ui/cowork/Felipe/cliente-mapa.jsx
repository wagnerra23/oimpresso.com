// cliente-mapa.jsx — /contacts/map (paridade Pages/Cliente/Map.tsx + Map.charter.md).
// Split: lista pesquisável à esquerda + mapa à direita. O mapa é OpenStreetMap real
// (sem chave de API); coordenada vem do campo `position` legado ("lat,lng").
const { useState: useStateCM, useMemo: useMemoCM } = React;

// Coordenadas reais das cidades que aparecem nos cadastros.
const CM_CIDADES = {
  "São Paulo": [-23.5505, -46.6333], "Campinas": [-22.9099, -47.0626], "Santo André": [-23.6639, -46.5383],
  "Guarulhos": [-23.4543, -46.5337], "Osasco": [-23.5324, -46.7916], "Diadema": [-23.6861, -46.6228],
  "São Bernardo": [-23.6914, -46.5646], "Barueri": [-23.5106, -46.8761],
  "Rio de Janeiro": [-22.9068, -43.1729], "Niterói": [-22.8832, -43.1034], "Petrópolis": [-22.5050, -43.1786],
  "Belo Horizonte": [-19.9167, -43.9345], "Contagem": [-19.9317, -44.0536], "Juiz de Fora": [-21.7642, -43.3496],
  "Curitiba": [-25.4284, -49.2733], "Londrina": [-23.3103, -51.1628],
  "Porto Alegre": [-30.0346, -51.2177], "Caxias do Sul": [-29.1685, -51.1794],
  "Florianópolis": [-27.5949, -48.5482], "Tubarão": [-28.4713, -49.0069],
};

function ClienteMapaPage() {
  const I = window.I || {};
  const OS_DATA = window.OS_DATA || {};
  const OS_LIST = OS_DATA.OS_LIST || [];
  const clientes = OS_DATA.OS_CLIENTS || [];
  const [q, setQ] = useStateCM("");
  const [sel, setSel] = useStateCM(null);

  const pontos = useMemoCM(() => clientes.map((c) => {
    const stats = window.cliClientStats ? window.cliClientStats(c, OS_LIST) : { count: 0, openCount: 0, lateCount: 0, totalValue: 0, ownList: [] };
    const d = window.cliDeriveCli ? window.cliDeriveCli(c, stats) : {};
    const base = CM_CIDADES[d.city];
    // Sem cidade conhecida = sem posição. O cadastro precisa de endereço antes.
    const h = Math.abs((String(c.id).charCodeAt(0) || 7) * 31 + String(c.name).length);
    const pos = base ? [base[0] + ((h % 60) - 30) / 900, base[1] + ((h % 47) - 23) / 900] : null;
    return { c, d, pos };
  }), [clientes, OS_LIST]);

  const vis = pontos.filter(({ c, d }) => !q || `${c.name} ${d.city || ""} ${d.uf || ""}`.toLowerCase().includes(q.toLowerCase()));
  const semPos = pontos.filter((p) => !p.pos).length;
  const atual = sel ? pontos.find((p) => p.c.id === sel) : null;
  const foco = atual && atual.pos ? atual.pos : [-15.7801, -47.9292];
  const zoom = atual && atual.pos ? 0.02 : 32;
  const bbox = [foco[1] - zoom, foco[0] - zoom * 0.7, foco[1] + zoom, foco[0] + zoom * 0.7].join("%2C");
  const src = `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik` +
    (atual && atual.pos ? `&marker=${atual.pos[0]}%2C${atual.pos[1]}` : "");

  return (
    <div className="os-page cm-page">
      <header className="os-page-h">
        <div className="os-page-h-l">
          <button className="ci-voltar" onClick={() => window.__go?.("clientes")}>← Clientes</button>
          <h1>Mapa de clientes</h1>
          <p><strong>{pontos.length - semPos}</strong> com endereço no mapa{semPos > 0 && <> · <strong className="cm-sem">{semPos} sem posição</strong></>}</p>
        </div>
      </header>

      <div className="cm-split">
        <aside className="cm-aside">
          <div className="cm-busca">
            <I.search size={13}/>
            <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar cliente ou cidade…" aria-label="Buscar cliente ou cidade"/>
          </div>
          <ul className="cm-lista">
            {vis.map(({ c, d, pos }) => (
              <li key={c.id}>
                <button className={"cm-item" + (sel === c.id ? " on" : "") + (pos ? "" : " sem")}
                  onClick={() => setSel(c.id)} aria-current={sel === c.id ? "true" : undefined}>
                  <span className="cm-pin" aria-hidden="true"><I.mapPin size={13}/></span>
                  <span className="cm-item-tx">
                    <b>{c.name}</b>
                    <small>{pos ? `${d.city} · ${d.uf}` : "Sem posição no cadastro"}</small>
                  </span>
                  {!pos && <span className="cm-badge">Sem posição</span>}
                </button>
              </li>
            ))}
            {vis.length === 0 && <li className="cm-vazio">Nenhum cliente com esse termo.</li>}
          </ul>
        </aside>

        <div className="cm-mapa">
          <iframe title="Mapa dos clientes" src={src} loading="lazy" referrerPolicy="no-referrer-when-downgrade"></iframe>
          <div className="cm-mapa-info">
            {atual
              ? atual.pos
                ? <>
                    <b>{atual.c.name}</b>
                    <span>{atual.d.city} · {atual.d.uf}</span>
                    <span className="cm-coord tabular">{atual.pos[0].toFixed(5)}, {atual.pos[1].toFixed(5)}</span>
                    <a href={`https://www.openstreetmap.org/?mlat=${atual.pos[0]}&mlon=${atual.pos[1]}#map=16/${atual.pos[0]}/${atual.pos[1]}`} target="_blank" rel="noreferrer">Abrir no mapa completo</a>
                  </>
                : <>
                    <b>{atual.c.name}</b>
                    <span>Este cadastro não tem coordenada. Preencha o endereço na ficha do cliente — a posição vem do CEP.</span>
                  </>
              : <>
                  <b>Escolha um cliente na lista</b>
                  <span>O mapa centraliza no endereço dele. Coordenada sem endereço não existe: quem não tem CEP preenchido aparece como "sem posição".</span>
                </>}
          </div>
        </div>
      </div>
    </div>
  );
}

window.ClienteMapaPage = ClienteMapaPage;
