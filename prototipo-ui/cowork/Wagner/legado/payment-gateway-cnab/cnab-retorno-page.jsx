// cnab-retorno-page.jsx — Retorno CNAB por credencial de gateway. Cockpit V2.
// GERADO pelo designer-agente (ADR 0282 §0.1) ancorado no DS canon + no dominio REAL:
//   props/rota  Modules/PaymentGateway/Http/Controllers/Settings/PaymentGatewaysCnabRetornoController.php
//   limites     EXT_ACEITAS ['txt','ret','cnab','rem'] · TAMANHO_MAX_KB 8192 (mesmo arquivo)
//   colunas     Modules/PaymentGateway/Database/Migrations/2026_05_26_120100_create_cnab_retorno_uploads_table.php
//   ocorrencias Modules/PaymentGateway/Jobs/CnabRetornoProcessor.php (switch OCORRENCIA_*)
//   G1-G4       CnabRetorno.charter.md (Goals)
// NAO derivado do .tsx vivo (porte reverso e proibido — §5 2026-06-05 / 2026-08-28).
// Token-driven (claro/escuro pelo host). Expoe window.CnabRetornoPage.
(() => {
const { useState, useMemo, useCallback } = React;

const Ic = ({ d, size = 14 }) => (
  <svg viewBox="0 0 24 24" width={size} height={size} fill="none" stroke="currentColor"
       strokeWidth="1.75" strokeLinecap="round" strokeLinejoin="round">{d}</svg>
);
const CnI = {
  up:    <Ic size={22} d={<><path d="M12 16V4"/><path d="m6 10 6-6 6 6"/><path d="M4 20h16"/></>} />,
  file:  <Ic d={<><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></>} />,
  x:     <Ic size={13} d={<><path d="M18 6 6 18M6 6l12 12"/></>} />,
  alert: <Ic size={12} d={<><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></>} />,
  down:  <Ic size={12} d={<><path d="m6 9 6 6 6-6"/></>} />,
};

// contrato do backend — limites REAIS do Controller
const LIMITES = { tamanho_max_kb: 8192, extensoes: ['txt', 'ret', 'cnab', 'rem'] };

// credencial — shape exato que o Controller envia
const CRED = {
  id: 12, gateway_key: 'sicoob', nome_display: 'Sicoob PJ — carteira 1',
  ambiente: 'production', ativo: true,
};

// historico — 1:1 com as colunas da migration
const UPLOADS = [
  { id: 41, arquivo_nome_original: 'RET_756_20260908.RET', arquivo_tamanho_bytes: 184320,
    processado_em: '2026-09-08T22:14:00', qtd_paga: 12, qtd_cancelada: 1, qtd_vencida: 3,
    qtd_registrada: 0, erros: [], created_at: '2026-09-08T22:13:00' },
  { id: 40, arquivo_nome_original: 'RET_756_20260905.RET', arquivo_tamanho_bytes: 96256,
    processado_em: '2026-09-05T22:11:00', qtd_paga: 6, qtd_cancelada: 0, qtd_vencida: 1,
    qtd_registrada: 9,
    erros: ['linha 118: nosso_numero fora do range da carteira', 'linha 204: valor pago difere do titulo'],
    created_at: '2026-09-05T22:10:00' },
  { id: 39, arquivo_nome_original: 'remessa-setembro.rem', arquivo_tamanho_bytes: 22528,
    processado_em: null, qtd_paga: 0, qtd_cancelada: 0, qtd_vencida: 0, qtd_registrada: 0,
    erros: [], created_at: '2026-09-05T09:02:00' },
];

const fmtBytes = (b) => b < 1024 ? b + ' B'
  : b < 1048576 ? (b / 1024).toFixed(1) + ' KB' : (b / 1048576).toFixed(2) + ' MB';
const fmtDate = (iso) => {
  if (!iso) return '—';
  const d = new Date(iso);
  const p = (n) => String(n).padStart(2, '0');
  return p(d.getDate()) + '/' + p(d.getMonth() + 1) + '/' + d.getFullYear() + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
};
const extDe = (nome) => (nome.split('.').pop() || '').toLowerCase();

// G2 — validacao no front espelhando o validate() do Controller
function validar(nome, bytes) {
  if (!nome) return null;
  const ext = extDe(nome);
  if (!LIMITES.extensoes.includes(ext))
    return 'Extensao .' + ext + ' nao aceita. O banco entrega ' + LIMITES.extensoes.map(e => '.' + e).join(', ') + '.';
  if (bytes > LIMITES.tamanho_max_kb * 1024)
    return 'Arquivo de ' + fmtBytes(bytes) + ' passa do limite de ' + (LIMITES.tamanho_max_kb / 1024) + ' MB.';
  return null;
}

// G3 linha + G4 erros expansiveis
function Linha({ u }) {
  const [aberto, setAberto] = useState(false);
  const n = u.erros ? u.erros.length : 0;
  const cel = (v) => <td className={'cn-num' + (v === 0 ? ' cn-zero' : '')}>{v}</td>;
  return (
    <React.Fragment>
      <tr>
        <td>
          <div className="cn-arq">{u.arquivo_nome_original}</div>
          <div style={{ fontSize: '11px', color: 'var(--fg-3)' }}>enviado {fmtDate(u.created_at)}</div>
        </td>
        <td className="cn-num">{fmtBytes(u.arquivo_tamanho_bytes)}</td>
        <td>
          {u.processado_em ? fmtDate(u.processado_em) : <span className="cn-chip">Pendente</span>}
        </td>
        {cel(u.qtd_paga)}{cel(u.qtd_cancelada)}{cel(u.qtd_vencida)}{cel(u.qtd_registrada)}
        <td className="cn-num">
          {n === 0 ? <span className="cn-zero">—</span> : (
            <button className="cn-errbtn" onClick={() => setAberto(!aberto)}
                    aria-expanded={aberto} aria-label={'Ver ' + n + ' erro(s) de ' + u.arquivo_nome_original}>
              {CnI.alert}{n}{CnI.down}
            </button>
          )}
        </td>
      </tr>
      {aberto && n > 0 && (
        <tr><td colSpan={8} style={{ padding: 0 }}>
          <div className="cn-errs">
            {u.erros.map((e, i) => (
              <div className="cn-err" key={i}>
                <span className="cn-err-i">{String(i + 1).padStart(2, '0')}</span><span>{e}</span>
              </div>
            ))}
          </div>
        </td></tr>
      )}
    </React.Fragment>
  );
}

// Amostras que o operador realmente encontra — a 2a e a 3a existem pra o desenho
// mostrar os DOIS jeitos de o G2 recusar antes de subir (extensao e tamanho).
const AMOSTRAS = [
  { nome: 'RET_756_20260909.RET', bytes: 152064 },   // valido
  { nome: 'retorno-setembro.xlsx', bytes: 48000 },   // extensao fora de EXT_ACEITAS
  { nome: 'RET_756_lote_anual.ret', bytes: 9437184 }, // 9 MB — passa de TAMANHO_MAX_KB
];

function CnabRetornoPage() {
  const [arq, setArq] = useState(null);
  const [i, setI] = useState(0);
  const [sobre, setSobre] = useState(false);
  const [carregando, setCarregando] = useState(false);
  const invalido = useMemo(() => arq ? validar(arq.nome, arq.bytes) : null, [arq]);

  const escolher = useCallback(() => {
    setArq(AMOSTRAS[i % AMOSTRAS.length]);
    setI(i + 1);
  }, [i]);

  const uploads = carregando ? [] : UPLOADS;

  return (
    <div className="cn">
      <div className="cn-cred">
        <span className="cn-cred-k">{CRED.gateway_key.toUpperCase()}</span>
        <span className="cn-cred-sep">·</span>
        <span className="cn-cred-n">{CRED.nome_display}</span>
        <span className="cn-chip">{CRED.ambiente === 'production' ? 'Producao' : 'Homologacao'}</span>
        {CRED.ativo && <span className="cn-chip cn-chip-on">Ativa</span>}
      </div>

      <div className="cn-sec">
        <div className="cn-sec-h">
          <span className="cn-sec-t">Enviar arquivo de retorno</span>
          <span className="cn-sec-s">um arquivo por vez · CNAB 240 ou 400, detectado automaticamente</span>
        </div>
        <div className={'cn-dz' + (sobre ? ' cn-dz-on' : '')}
             onDragOver={(e) => { e.preventDefault(); setSobre(true); }}
             onDragLeave={() => setSobre(false)}
             onDrop={(e) => { e.preventDefault(); setSobre(false); escolher(); }}>
          <span className="cn-dz-ic">{CnI.up}</span>
          <div className="cn-dz-t">Arraste o arquivo aqui ou clique para escolher</div>
          <div className="cn-dz-s">
            Aceita {LIMITES.extensoes.map(e => '.' + e).join(', ')} ate {LIMITES.tamanho_max_kb / 1024} MB.
            O banco costuma entregar .RET; arquivo renomeado para .txt tambem serve.
          </div>
          <button className="cn-btn" onClick={escolher}>Escolher arquivo</button>
        </div>

        {arq && (
          <div className="cn-file">
            <span style={{ color: 'var(--fg-3)' }}>{CnI.file}</span>
            <span className="cn-file-n">{arq.nome}</span>
            <span className="cn-file-s">{fmtBytes(arq.bytes)}</span>
            <button className="cn-btn" onClick={() => setArq(null)} aria-label="Remover arquivo">{CnI.x}</button>
          </div>
        )}
        {invalido && <div className="cn-inval">{invalido}</div>}

        <div className="cn-acts">
          <button className="cn-btn" onClick={() => setCarregando(!carregando)}>
            {carregando ? 'Mostrar historico' : 'Ver estado carregando'}
          </button>
          <button className="cn-btn cn-btn-p" disabled={!arq || !!invalido}>Enviar e processar</button>
        </div>
      </div>

      <div className="cn-sec">
        <div className="cn-sec-h">
          <span className="cn-sec-t">Uploads recentes</span>
          <span className="cn-sec-s">contadores por arquivo, como o processador gravou</span>
        </div>
        <div className="cn-wrap">
          {carregando ? (
            [0, 1, 2].map(i => (
              <div className="cn-sk-row" key={i}>
                <div className="cn-sk" style={{ flex: 3 }} /><div className="cn-sk" style={{ flex: 1 }} />
                <div className="cn-sk" style={{ flex: 2 }} /><div className="cn-sk" style={{ flex: 1 }} />
              </div>
            ))
          ) : uploads.length === 0 ? (
            <div className="cn-empty">
              <span style={{ color: 'var(--fg-3)' }}>{CnI.file}</span>
              <div className="cn-empty-t">Nenhum arquivo enviado ainda</div>
              <div className="cn-empty-s">
                Assim que voce enviar um retorno, ele aparece aqui com quantos titulos foram
                baixados, cancelados, vencidos e registrados.
              </div>
            </div>
          ) : (
            <table className="cn-tb">
              <thead><tr>
                <th>Arquivo</th><th className="cn-num">Tamanho</th><th>Processado</th>
                <th className="cn-num">Pagas</th><th className="cn-num">Canceladas</th>
                <th className="cn-num">Vencidas</th><th className="cn-num">Registradas</th>
                <th className="cn-num">Erros</th>
              </tr></thead>
              <tbody>{uploads.map(u => <Linha u={u} key={u.id} />)}</tbody>
            </table>
          )}
        </div>
      </div>

      <div className="cn-note">
        <b>O que cada coluna conta.</b> O processador le a ocorrencia de cada detalhe do arquivo e
        decide: <b>liquidada</b> baixa o titulo e conta em <i>Pagas</i>; <b>baixada</b> (baixa sem
        pagamento) conta em <i>Canceladas</i>; <b>entrada</b> confirma o registro no banco e conta
        em <i>Registradas</i>, podendo marcar <i>Vencidas</i>. Alteracao, protesto e erro nao mudam
        o titulo. Reenviar o mesmo arquivo e seguro: titulo ja baixado nao dispara evento de novo.
      </div>
    </div>
  );
}

window.CnabRetornoPage = CnabRetornoPage;
})();
