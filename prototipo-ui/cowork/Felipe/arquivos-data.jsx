// arquivos-data.jsx — dados e domínio da tela Arquivos (Modules/Arquivos · ADR 0123).
// Fonte: Entities/Arquivo (bucket · sub_destination · visibility · encrypted · disk · md5 ·
// size_bytes · retention_days · classified_by/at · arquivable polimórfico) + Config/config.php
// (disk_vault, upload_max_mb 50, vault_max_file_size_mb 50, signed_url 60 min) +
// Config/retention.php (prazos com base legal BR, grace 30d, notice 30d, strategy hard_delete,
// bucket_override sensitive 365d) + enum de arquivos_audit_log.
// Expõe window.ArqData.
(() => {
const HOJE = new Date(2026, 7, 24, 10, 40);
const p2 = (n) => String(n).padStart(2, "0");
const dt = (d) => `${p2(d.getDate())}/${p2(d.getMonth() + 1)}/${d.getFullYear()}`;
const dth = (d) => `${dt(d)} ${p2(d.getHours())}:${p2(d.getMinutes())}`;
const dias = (a, b) => Math.round((a - b) / 864e5);
const mais = (d) => { const x = new Date(HOJE); x.setDate(x.getDate() + d); return x; };
const menos = (d) => mais(-d);
const tam = (b) => b >= 1073741824 ? `${(b / 1073741824).toFixed(2).replace(".", ",")} GB` : b >= 1048576 ? `${Math.round(b / 1048576)} MB` : `${Math.round(b / 1024)} KB`;

// Config/retention.php — prazo com a lei ao lado, não número solto
const POLITICA = [
  { sub: "nfe-xml", dias: 1825, lei: "Lei 8.846/94 Art. 23 + SINIEF 07/2005 Art. 8", label: "XML de NF-e" },
  { sub: "nfse-xml", dias: 1825, lei: "idem NF-e", label: "XML de NFS-e" },
  { sub: "documentos-fiscais", dias: 1825, lei: "CTN Art. 173 — prescrição tributária", label: "Documentos fiscais" },
  { sub: "contratos", dias: 1825, lei: "CDC Art. 27 (cíveis decenais por override)", label: "Contratos" },
  { sub: "repair-foto", dias: 730, lei: "evidência de reparo pós-encerramento", label: "Foto de reparo" },
  { sub: "os-anexo", dias: 730, lei: "anexo de OS pós-encerramento", label: "Anexo de OS" },
  { sub: "ticket-anexo", dias: 365, lei: "pós-fechamento do ticket", label: "Anexo de ticket" },
  { sub: "default", dias: 90, lei: "LGPD Art. 15-16 — eliminação tempestiva", label: "Sem contexto mapeado" },
];
const CFG = { grace: 30, notice: 30, strategy: "hard_delete", vaultCap: 50, uploadCap: 50, signedMin: 60, sensitiveDefault: 365 };
const politica = (sub) => POLITICA.find((p) => p.sub === sub) || POLITICA[POLITICA.length - 1];

// A tela do dono: onde o arquivo é alcançado de verdade (arquivable → rota do Cowork)
const ROTA_DONO = { Transaction: "venda-todas", Os: "os", Repair: "repair", Ticket: "inbox", Contact: "clientes", Business: "cfg-empresa" };

const BASE = [
  { id: 8841, nome: "NFe-35260800112233-proc.xml", bytes: 48120, bucket: "sensitive", vis: "private", sub: "nfe-xml", disk: "vault", enc: true, dono: "Venda #14022", tipo: "Transaction", quem: "sistema", em: menos(1), md5: "9f2a1c", ret: 1825, porQuem: null, titular: null },
  { id: 8840, nome: "NFe-35260800112230-proc.xml", bytes: 47004, bucket: "sensitive", vis: "private", sub: "nfe-xml", disk: "vault", enc: true, dono: "Venda #14019", tipo: "Transaction", quem: "sistema", em: menos(2), md5: "77bd04", ret: 1825, porQuem: null, titular: null },
  { id: 8836, nome: "contrato-rota-livre-2026.pdf", bytes: 1812004, bucket: "sensitive", vis: "private", sub: "contratos", disk: "vault", enc: true, dono: "Cliente #312", tipo: "Contact", quem: "rita", em: menos(9), md5: "aa31f8", ret: 1825, porQuem: "rita", titular: "Padaria Estrela" },
  { id: 8830, nome: "fachada-antes.jpg", bytes: 3204112, bucket: "common", vis: "internal", sub: "os-anexo", disk: "local", enc: false, dono: "OS #1187", tipo: "Os", quem: "larissa", em: menos(12), md5: "18cc90", ret: 730, porQuem: null, titular: null },
  { id: 8829, nome: "fachada-depois.jpg", bytes: 3411882, bucket: "common", vis: "internal", sub: "os-anexo", disk: "local", enc: false, dono: "OS #1187", tipo: "Os", quem: "larissa", em: menos(12), md5: "18cc90", ret: 730, porQuem: null, titular: null },
  { id: 8818, nome: "laudo-bomba-agua.jpg", bytes: 2998400, bucket: "common", vis: "internal", sub: "repair-foto", disk: "local", enc: false, dono: "OS Repair #8801", tipo: "Repair", quem: "marcos", em: menos(64), md5: "c40a22", ret: 730, porQuem: null, titular: null },
  { id: 8802, nome: "print-erro-conciliacao.png", bytes: 412004, bucket: "sensitive", vis: "internal", sub: "ticket-anexo", disk: "local", enc: false, dono: "Ticket #442", tipo: "Ticket", quem: "eliana", em: menos(340), md5: "5b7711", ret: 365, porQuem: "wagner", titular: "Eliana Souza" },
  { id: 8790, nome: "arte-banner-final.tif", bytes: 68400112, bucket: "common", vis: "internal", sub: "os-anexo", disk: "local", enc: false, dono: null, tipo: null, quem: "larissa", em: menos(410), md5: "e0aa41", ret: 730, porQuem: null, titular: null },
  { id: 8712, nome: "balanco-2025.pdf", bytes: 902004, bucket: "sensitive", vis: "private", sub: "documentos-fiscais", disk: "vault", enc: true, dono: "Negócio #4", tipo: "Business", quem: "wagner", em: menos(520), md5: "31de55", ret: 1825, porQuem: "wagner", titular: null },
  { id: 8655, nome: "logo-rota-livre.svg", bytes: 18220, bucket: "public", vis: "public", sub: "default", disk: "local", enc: false, dono: "Negócio #4", tipo: "Business", quem: "rita", em: menos(700), md5: "7ac331", ret: 90, porQuem: "rita", titular: null },
  // já no grace: soft-delete feito, dá pra restaurar
  { id: 8721, nome: "ficha-tecnica-antiga.pdf", bytes: 220400, bucket: "common", vis: "internal", sub: "os-anexo", disk: "local", enc: false, dono: "OS #1104", tipo: "Os", quem: "larissa", em: menos(760), md5: "b1cc07", ret: 730, porQuem: null, titular: null, del: menos(11) },
  { id: 8688, nome: "anexo-ticket-401.png", bytes: 331200, bucket: "sensitive", vis: "internal", sub: "ticket-anexo", disk: "local", enc: false, dono: "Ticket #401", tipo: "Ticket", quem: "eliana", em: menos(690), md5: "d0f142", ret: 365, porQuem: null, titular: "Marcos Vital", del: menos(28) },
];

const restam = (a) => a.ret - dias(HOJE, a.em);
const vence = (a) => mais(restam(a));
const noGrace = (a) => !!a.del && dias(HOJE, a.del) <= CFG.grace;
const graceRestante = (a) => CFG.grace - dias(HOJE, a.del);
const precisaAviso = (a) => a.bucket === "sensitive" && !!a.titular && restam(a) <= CFG.notice && !a.avisado;

const TRILHA_INICIAL = [
  { id: 91204, acao: "signed_url", arq: 8841, quem: "eliana", quando: "24/08/2026 09:12", payload: "expira em 60 min · DownloadController" },
  { id: 91203, acao: "upload", arq: 8841, quem: "sistema", quando: "23/08/2026 18:40", payload: "vault · encrypted · 47 KB" },
  { id: 91198, acao: "download", arq: 8836, quem: "rita", quando: "23/08/2026 15:02", payload: "vault · via DownloadController" },
  { id: 91190, acao: "soft_delete", arq: 8721, quem: "wagner", quando: "13/08/2026 11:31", payload: "grace de 30 dias iniciado" },
  { id: 91181, acao: "soft_delete", arq: 8688, quem: "wagner", quando: "27/07/2026 09:04", payload: "grace de 30 dias iniciado · ticket fechado" },
  { id: 91102, acao: "hard_delete", arq: 8404, quem: "job", quando: "01/08/2026 03:12", payload: "retention-cleanup · ticket-anexo 365d + grace 30d" },
];

window.ArqData = { HOJE, dt, dth, dias, mais, menos, tam, POLITICA, CFG, politica, ROTA_DONO, BASE, restam, vence, noGrace, graceRestante, precisaAviso, TRILHA_INICIAL };
})();
