// Wave C-FE — IdentificacaoTab.tsx
//
// Tab 1 do drawer 760px Cliente. PF/PJ toggle + dados de identificação.
// Refs: ADR 0179 · Charter Index.charter.md v3 · HANDOFF_CLIENTES.md §2.1
// Cowork blueprint: prototipo-ui/prototipos/clientes/clientes-drawer.jsx::SectionIdentificacao
//
// Contrato (combinado com Agent B + Agent C-BE):
//   PATCH /cliente/{id}/identificacao  body: { tipo, nome, fantasia, doc, ie, rg, nascimento, contato, cargo }
//   GET   /cliente/lookup/cnpj/{cnpj}  → { razao_social, fantasia, ie }
//
// Pegadinhas Tier 0 (LICOES_F3):
//  - Autosave on blur com debounce 800ms + optimistic UI + rollback em 4xx/5xx
//  - Validação mod 11 client-side é UX-only; backend revalida (ADR 0093 server-side)
//  - "Buscar CNPJ" é loader inline, NÃO modal aninhado (anti-padrão F3 T-AP-15)
//  - PT-BR em TODO label/placeholder/erro
//  - A11y: label htmlFor + aria-invalid + aria-describedby

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Loader2, Search, CheckCircle2, AlertCircle, User, Building2 } from 'lucide-react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { maskCPF, maskCNPJ, onlyDigits } from '@/Lib/br-mask';
import { validateCPF, validateCNPJ } from '@/Lib/br-validate';

export interface ContactInfo {
  id: number;
  name: string;
  tipo?: 'PF' | 'PJ' | null;
  fantasia?: string | null;
  cpf_cnpj_masked?: string | null;
  ie?: string | null;
  rg?: string | null;
  nascimento?: string | null;
  contato?: string | null;
  cargo?: string | null;
  // ADR 0188 Onda 4 — flags multi-papel. Backend retorna bool (cast no
  // shapeContactResponse · MySQL int 0/1 → React bool). Default false se
  // ausente (contato pre-Onda 4 que não tem flags ainda em prod).
  is_customer?: boolean | null;
  is_supplier?: boolean | null;
  is_employee?: boolean | null;
  is_representative?: boolean | null;
  // Endereço completo — política Wagner 2026-05-22 (#1419 mergeado):
  // lookup CNPJ SOBRESCREVE dados cadastrais oficiais (Receita é fonte da
  // verdade). ContactInfo aceita PT-BR e canon UPOS pra compat dual na leitura.
  // UF (state/uf) usada também pra SEFAZ ConsultaCadastro (ADR 0186 #1431).
  cep?: string | null;
  zip_code?: string | null;
  endereco?: string | null;
  address_line_1?: string | null;
  bairro?: string | null;
  neighborhood?: string | null;
  cidade?: string | null;
  city?: string | null;
  uf?: string | null;
  state?: string | null;
  city_code?: string | null; // IBGE 7 digitos (NFe/NFSe)
  // Contatos — política Wagner 2026-05-22: lookup CNPJ SÓ preenche se vazio
  // (telefone/email da Receita pode diferir do contato real digitado pelo user).
  mobile?: string | null;
  tel?: string | null;
  email?: string | null;
}

export interface IdentificacaoTabProps {
  contact: ContactInfo;
  onSaved?: (field: string, value: unknown) => void;
  /**
   * Callback disparado APOS lookup CNPJ persistir endereco e/ou contato com
   * sucesso. Pai (ClienteSheet) usa pra `router.reload({ only: ['rows'] })`
   * -- refresca contact em rows pra EnderecoTab/ContatoTab re-renderizarem
   * com os campos preenchidos. Wagner 2026-05-22.
   */
  onCnpjEnderecoPersisted?: () => void;
  disabled?: boolean;
}

type CnpjLookupState = 'idle' | 'loading' | 'ok' | 'error';

const DEBOUNCE_MS = 800;

function getCsrfToken(): string {
  return (
    (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? ''
  );
}

export default function IdentificacaoTab({
  contact,
  onSaved,
  onCnpjEnderecoPersisted,
  disabled = false,
}: IdentificacaoTabProps) {
  // ── State local (optimistic UI — atualiza ANTES do fetch) ────────────
  const [tipo, setTipo] = useState<'PF' | 'PJ'>(contact.tipo ?? 'PJ');
  const [nome, setNome] = useState<string>(contact.name ?? '');
  const [fantasia, setFantasia] = useState<string>(contact.fantasia ?? '');
  const [doc, setDoc] = useState<string>(contact.cpf_cnpj_masked ?? '');
  const [ie, setIe] = useState<string>(contact.ie ?? '');
  const [rg, setRg] = useState<string>(contact.rg ?? '');
  const [nascimento, setNascimento] = useState<string>(contact.nascimento ?? '');
  const [contatoNome, setContatoNome] = useState<string>(contact.contato ?? '');
  const [cargo, setCargo] = useState<string>(contact.cargo ?? '');
  // ADR 0188 Onda 4 — flags multi-papel. Default false se ausente (contato pre-migration).
  const [isCustomer, setIsCustomer] = useState<boolean>(Boolean(contact.is_customer));
  const [isSupplier, setIsSupplier] = useState<boolean>(Boolean(contact.is_supplier));
  const [isEmployee, setIsEmployee] = useState<boolean>(Boolean(contact.is_employee));
  const [isRepresentative, setIsRepresentative] = useState<boolean>(Boolean(contact.is_representative));

  const [savingField, setSavingField] = useState<string | null>(null);
  const [savedField, setSavedField] = useState<string | null>(null);
  const [errorField, setErrorField] = useState<{ field: string; message: string } | null>(null);
  const [cnpjLookup, setCnpjLookup] = useState<CnpjLookupState>('idle');
  const [cnpjLookupMsg, setCnpjLookupMsg] = useState<string | null>(null);

  const debounceTimersRef = useRef<Record<string, ReturnType<typeof setTimeout>>>({});
  const previousValuesRef = useRef<Record<string, unknown>>({});

  // Resincroniza quando muda de contato (drawer reabre com outro id)
  useEffect(() => {
    setTipo(contact.tipo ?? 'PJ');
    setNome(contact.name ?? '');
    setFantasia(contact.fantasia ?? '');
    setDoc(contact.cpf_cnpj_masked ?? '');
    setIe(contact.ie ?? '');
    setRg(contact.rg ?? '');
    setNascimento(contact.nascimento ?? '');
    setContatoNome(contact.contato ?? '');
    setCargo(contact.cargo ?? '');
    setIsCustomer(Boolean(contact.is_customer));
    setIsSupplier(Boolean(contact.is_supplier));
    setIsEmployee(Boolean(contact.is_employee));
    setIsRepresentative(Boolean(contact.is_representative));
    setErrorField(null);
    setSavedField(null);
    setCnpjLookup('idle');
    setCnpjLookupMsg(null);
  }, [contact.id]);

  // ── Validação inline (mod 11) ────────────────────────────────────────
  const docError = useMemo<string | null>(() => {
    if (!doc) return null;
    if (tipo === 'PF') {
      const v = validateCPF(doc);
      if (v === false) return 'CPF inválido. Confere os dígitos.';
    } else {
      const v = validateCNPJ(doc);
      if (v === false) return 'CNPJ inválido. Confere o dígito verificador.';
    }
    return null;
  }, [doc, tipo]);

  const nomeError = useMemo<string | null>(() => {
    if (nome === '') return null; // só erro on blur via FormRequest
    if (nome.trim().length > 0 && nome.trim().length < 3) {
      return tipo === 'PJ' ? 'Razão social precisa de ao menos 3 caracteres.' : 'Nome precisa de ao menos 3 caracteres.';
    }
    return null;
  }, [nome, tipo]);

  // ── Autosave debounced ───────────────────────────────────────────────
  const performSave = useCallback(
    async (field: string, value: unknown, previousValue: unknown) => {
      if (disabled) return;
      setSavingField(field);
      setErrorField(null);

      try {
        const r = await fetch(`/cliente/${contact.id}/identificacao`, {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({ [field]: value }),
        });

        if (!r.ok) {
          // Rollback optimistic UI
          rollbackField(field, previousValue);
          let msg = `Erro ${r.status} ao salvar.`;
          if (r.status === 422) {
            const j = await r.json().catch(() => ({}));
            msg = j?.errors?.[field]?.[0] ?? j?.message ?? msg;
          } else if (r.status === 403) {
            msg = 'Sem permissão pra editar este cliente.';
          } else if (r.status === 404) {
            msg = 'Cliente não encontrado.';
          }
          setErrorField({ field, message: msg });
          // eslint-disable-next-line no-console
          console.error(`[IdentificacaoTab] autosave ${field} falhou`, { status: r.status, msg });
          return;
        }

        setSavedField(field);
        setTimeout(() => setSavedField((current) => (current === field ? null : current)), 1800);
        onSaved?.(field, value);
      } catch (err) {
        rollbackField(field, previousValue);
        setErrorField({ field, message: 'Falha de rede. Tente de novo.' });
        // eslint-disable-next-line no-console
        console.error(`[IdentificacaoTab] autosave ${field} network error`, err);
      } finally {
        setSavingField((current) => (current === field ? null : current));
      }
    },
    [contact.id, disabled, onSaved]
  );

  const rollbackField = useCallback((field: string, previousValue: unknown) => {
    if (field === 'tipo') setTipo((previousValue as 'PF' | 'PJ') ?? 'PJ');
    else if (field === 'nome') setNome((previousValue as string) ?? '');
    else if (field === 'fantasia') setFantasia((previousValue as string) ?? '');
    else if (field === 'doc') setDoc((previousValue as string) ?? '');
    else if (field === 'ie') setIe((previousValue as string) ?? '');
    else if (field === 'rg') setRg((previousValue as string) ?? '');
    else if (field === 'nascimento') setNascimento((previousValue as string) ?? '');
    else if (field === 'contato') setContatoNome((previousValue as string) ?? '');
    else if (field === 'cargo') setCargo((previousValue as string) ?? '');
  }, []);

  const scheduleAutosave = useCallback(
    (field: string, value: unknown, previousValue: unknown) => {
      if (debounceTimersRef.current[field]) {
        clearTimeout(debounceTimersRef.current[field]);
      }
      previousValuesRef.current[field] = previousValue;
      debounceTimersRef.current[field] = setTimeout(() => {
        performSave(field, value, previousValuesRef.current[field]);
      }, DEBOUNCE_MS);
    },
    [performSave]
  );

  // Flush ao desmontar (não perde último digit)
  useEffect(() => {
    return () => {
      Object.values(debounceTimersRef.current).forEach((t) => clearTimeout(t));
    };
  }, []);

  // ── Handlers ─────────────────────────────────────────────────────────
  const handleBlur = useCallback(
    (field: string, value: unknown) => {
      // Não salva se erro de validação local
      if (field === 'doc' && docError) return;
      if (field === 'nome' && nomeError) return;
      const prev = previousValuesRef.current[field];
      // Trigger save imediato no blur (cancelando debounce — UX consistente com Cowork)
      if (debounceTimersRef.current[field]) {
        clearTimeout(debounceTimersRef.current[field]);
        delete debounceTimersRef.current[field];
      }
      performSave(field, value, prev);
    },
    [docError, nomeError, performSave]
  );

  const handleTipoChange = useCallback(
    (newTipo: 'PF' | 'PJ') => {
      const previous = tipo;
      if (previous === newTipo) return;
      setTipo(newTipo);
      // Tipo é mutação imediata (não tem "digitação")
      performSave('tipo', newTipo, previous);
    },
    [tipo, performSave]
  );

  // ADR 0188 Onda 4 — toggle papel (4 checkboxes is_X). Endpoint /papeis separado
  // (não /identificacao) pra isolar invariante "≥1 papel ativo" no backend.
  //
  // Optimistic UI: troca state imediato + PATCH em paralelo. Rollback no 4xx/5xx.
  // Sem debounce (checkbox toggle é discreto, não digitação).
  const handlePapelToggle = useCallback(
    async (
      flag: 'is_customer' | 'is_supplier' | 'is_employee' | 'is_representative',
      newValue: boolean,
    ) => {
      if (disabled) return;

      // Optimistic UI — troca state local imediato.
      const setters = {
        is_customer: setIsCustomer,
        is_supplier: setIsSupplier,
        is_employee: setIsEmployee,
        is_representative: setIsRepresentative,
      };
      const previousValues = {
        is_customer: isCustomer,
        is_supplier: isSupplier,
        is_employee: isEmployee,
        is_representative: isRepresentative,
      };
      setters[flag](newValue);

      setSavingField(flag);
      setErrorField(null);

      try {
        const r = await fetch(`/cliente/${contact.id}/papeis`, {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: JSON.stringify({ [flag]: newValue }),
        });

        if (!r.ok) {
          // Rollback optimistic UI
          setters[flag](previousValues[flag]);
          let msg = `Erro ${r.status} ao salvar papel.`;
          if (r.status === 422) {
            const j = await r.json().catch(() => ({}));
            msg = j?.errors?.[flag]?.[0] ?? j?.errors?.is_customer?.[0] ?? msg;
          } else if (r.status === 403) {
            msg = 'Sem permissão pra editar papéis.';
          }
          setErrorField({ field: flag, message: msg });
          // eslint-disable-next-line no-console
          console.error(`[IdentificacaoTab] papeis ${flag} falhou`, { status: r.status, msg });
          return;
        }

        setSavedField(flag);
        setTimeout(() => setSavedField((current) => (current === flag ? null : current)), 1800);
        onSaved?.(flag, newValue);
      } catch (err) {
        setters[flag](previousValues[flag]);
        setErrorField({ field: flag, message: 'Falha de rede. Tente de novo.' });
        // eslint-disable-next-line no-console
        console.error(`[IdentificacaoTab] papeis ${flag} network error`, err);
      } finally {
        setSavingField((current) => (current === flag ? null : current));
      }
    },
    [
      contact.id,
      disabled,
      isCustomer,
      isSupplier,
      isEmployee,
      isRepresentative,
      onSaved,
    ],
  );

  // ── Lookup CNPJ — Técnica C ADR 0186: BrasilAPI + SEFAZ em PARALELO ──
  //
  // Promise.all dispara ambas as APIs simultaneamente. Merge por autoridade:
  //   - IE, cSit, indIEDest derivado → SEFAZ (única fonte autoritativa)
  //   - razão social → SEFAZ se presente, senão BrasilAPI
  //   - fantasia, QSA, telefone, email → BrasilAPI (SEFAZ não retorna)
  // UF do CNPJ alvo:
  //   - Inicial: contact.state (cadastro pré-existente) ou skip SEFAZ
  //   - Após BrasilAPI: usa json.state se chegou (BrasilAPI sempre retorna)
  // Warnings antecipados (ADR 0186 evolução): cSit≠habilitado → badge alerta
  // pra evitar rejeição NFe (478/487/770).
  const handleCnpjLookup = useCallback(async () => {
    const digits = onlyDigits(doc);
    if (digits.length !== 14) return;
    setCnpjLookup('loading');
    setCnpjLookupMsg(null);

    const headers = {
      Accept: 'application/json',
      'X-CSRF-TOKEN': getCsrfToken(),
    } as const;

    // ADR 0186 §Invariante #11 — timeout frontend (hardening 2026-05-23).
    // AbortController cancela fetch após N ms. Drawer NUNCA fica preso em
    // loading state. Padrão 8s — mais longo que SEFAZ típico (~1-3s) com folga.
    // Configurável via config('fiscal.sefaz_consulta_cadastro_frontend_timeout_ms').
    const FRONTEND_TIMEOUT_MS = 8000;

    try {
      // UF inicial — pre-SEFAZ. Se cadastro pré-existente tem UF, dispara SEFAZ
      // em paralelo com BrasilAPI. Senão espera BrasilAPI retornar com UF.
      const ufInicial = (contact.state ?? contact.uf ?? '').toUpperCase();

      // AbortControllers — 1 por fetch. Cancela após FRONTEND_TIMEOUT_MS.
      const brasilApiCtrl = new AbortController();
      const sefazCtrl = new AbortController();
      const brasilApiTimer = setTimeout(() => brasilApiCtrl.abort(), FRONTEND_TIMEOUT_MS);
      const sefazTimer = setTimeout(() => sefazCtrl.abort(), FRONTEND_TIMEOUT_MS);

      // Promise BrasilAPI — sempre dispara.
      const brasilApiP = fetch(`/cliente/lookup/cnpj/${digits}`, {
        headers,
        signal: brasilApiCtrl.signal,
      }).finally(() => clearTimeout(brasilApiTimer));

      // Promise SEFAZ — só dispara se já temos UF inicial supported.
      // Se não, vamos disparar após BrasilAPI revelar a UF do alvo.
      // Retorna Response | {aborted:true} | null pra distinguir timeout.
      type SefazFetchResult = Response | { aborted: true } | null;
      const sefazP: Promise<SefazFetchResult> = ufInicial.length === 2
        ? fetch(`/cliente/lookup/cnpj/${digits}/sefaz?uf=${encodeURIComponent(ufInicial)}`, {
            headers,
            signal: sefazCtrl.signal,
          })
            .then((r) => r as SefazFetchResult)
            .catch((e) => {
              if (e?.name === 'AbortError') {
                console.info('[IdentificacaoTab] SEFAZ inicial timeout 8s (graceful)');
                return { aborted: true } as SefazFetchResult;
              }
              console.warn('[IdentificacaoTab] SEFAZ inicial falhou (graceful)', e);
              return null;
            })
            .finally(() => clearTimeout(sefazTimer))
        : (clearTimeout(sefazTimer), Promise.resolve(null as SefazFetchResult));

      const [brasilApiR, sefazInicialR] = await Promise.all([brasilApiP, sefazP]);

      // Parse BrasilAPI primeiro (sempre primário, mesmo que SEFAZ não funcione).
      if (!brasilApiR.ok) {
        setCnpjLookup('error');
        setCnpjLookupMsg(brasilApiR.status === 404 ? 'CNPJ não encontrado na Receita.' : `Erro ${brasilApiR.status}.`);
        return;
      }
      const json = await brasilApiR.json();

      // ── Política Wagner 2026-05-22 (feedback-lookup-cnpj-sobrescreve-dados):
      //    Dados cadastrais oficiais → SOBRESCREVE (Receita é fonte da verdade).
      //    Contatos pessoais → SÓ se vazio (preserva contato real do user).
      // ── Identificação (sobrescreve) ────────────────────────────────────
      const novoNome = (json?.razao_social as string) ?? '';
      const novaFantasia = (json?.fantasia as string) ?? '';
      const ieBrasilApi = (json?.ie as string) ?? '';
      const ufBrasilApi = ((json?.state as string) ?? '').toUpperCase();

      // ── Parse SEFAZ inicial (Técnica C — paralelo) + detect timeout ─────
      let sefazData: Record<string, unknown> | null = null;
      let sefazReason: 'unsupported' | 'no_cert' | 'sefaz_or_cert_error' | null = null;
      let sefazTimeoutFlag = false;
      const isAbortedResult = (r: SefazFetchResult): r is { aborted: true } =>
        r !== null && !('status' in r) && (r as { aborted?: boolean }).aborted === true;

      if (isAbortedResult(sefazInicialR)) {
        sefazTimeoutFlag = true;
      } else if (sefazInicialR && sefazInicialR.ok) {
        sefazData = await sefazInicialR.json().catch(() => null);
      } else if (sefazInicialR && 'status' in sefazInicialR && sefazInicialR.status === 404) {
        const errJson = await sefazInicialR.json().catch(() => ({}));
        sefazReason = (errJson?.reason as 'unsupported' | 'no_cert' | 'sefaz_or_cert_error') ?? null;
      }

      // ── 2ª chance SEFAZ se BrasilAPI revelou UF nova ─────────────────────
      const ufFinal = ufBrasilApi || ufInicial;
      if (!sefazData && sefazReason !== 'unsupported' && ufFinal.length === 2 && ufFinal !== ufInicial) {
        const sefaz2Ctrl = new AbortController();
        const sefaz2Timer = setTimeout(() => sefaz2Ctrl.abort(), FRONTEND_TIMEOUT_MS);
        try {
          const rs2 = await fetch(`/cliente/lookup/cnpj/${digits}/sefaz?uf=${encodeURIComponent(ufFinal)}`, {
            headers,
            signal: sefaz2Ctrl.signal,
          });
          if (rs2.ok) {
            sefazData = await rs2.json();
          } else if (rs2.status === 404) {
            const errJson = await rs2.json().catch(() => ({}));
            sefazReason = (errJson?.reason as typeof sefazReason) ?? null;
          }
        } catch (e: any) {
          if (e?.name === 'AbortError') {
            sefazTimeoutFlag = true;
            console.info('[IdentificacaoTab] SEFAZ segunda chance timeout 8s (graceful)');
          } else {
            console.warn('[IdentificacaoTab] SEFAZ pós-BrasilAPI falhou (graceful)', e);
          }
        } finally {
          clearTimeout(sefaz2Timer);
        }
      }

      // ── Merge autoridade — razão social: SEFAZ primeiro, BrasilAPI fallback
      const nomeFinal = (sefazData?.nome as string) || novoNome;
      if (nomeFinal) {
        setNome(nomeFinal);
        performSave('nome', nomeFinal, nome);
      }
      // Fantasia: só BrasilAPI tem.
      if (novaFantasia) {
        setFantasia(novaFantasia);
        performSave('fantasia', novaFantasia, fantasia);
      }
      // ── IE — SEFAZ é autoridade (Técnica C); BrasilAPI fallback geralmente vazia
      const ieFinal = ((sefazData?.ie as string) || ieBrasilApi || '').trim();
      if (ieFinal) {
        setIe(ieFinal);
        performSave('ie', ieFinal, ie);
      }

      // ── Endereço + IBGE (#1419 cross-tab fill — Receita é canônica, SOBRESCREVE)
      const enderecoCandidato: Record<string, string> = {};
      const zip = (json?.zip_code as string) ?? '';
      const addr1 = (json?.address_line_1 as string) ?? '';
      const neigh = (json?.neighborhood as string) ?? '';
      const cityVal = (json?.city as string) ?? '';
      const stateVal = (json?.state as string) ?? '';
      const cityCode = (json?.city_code as string) ?? '';
      if (zip) enderecoCandidato.zip_code = zip;
      if (addr1) enderecoCandidato.address_line_1 = addr1;
      if (neigh) enderecoCandidato.neighborhood = neigh;
      if (cityVal) enderecoCandidato.city = cityVal;
      if (stateVal) enderecoCandidato.state = stateVal;
      if (cityCode) enderecoCandidato.city_code = cityCode;

      let enderecoPreenchido = false;
      if (Object.keys(enderecoCandidato).length > 0) {
        try {
          const re = await fetch(`/cliente/${contact.id}/endereco`, {
            method: 'PATCH',
            headers: { ...headers, 'Content-Type': 'application/json' },
            body: JSON.stringify(enderecoCandidato),
          });
          enderecoPreenchido = re.ok;
          if (!re.ok) console.warn('[IdentificacaoTab] PATCH endereco pos-CNPJ falhou', re.status);
        } catch (err) {
          console.warn('[IdentificacaoTab] PATCH endereco network', err);
        }
      }

      // ── Contatos (#1419 — só se vazio, preserva contato real digitado)
      const contatoCandidato: Record<string, string> = {};
      const novoEmail = (json?.email as string) ?? '';
      const novoMobile = (json?.mobile as string) ?? '';
      if (novoEmail && !contact.email) contatoCandidato.email = novoEmail;
      if (novoMobile && !(contact.mobile ?? contact.tel)) contatoCandidato.mobile = novoMobile;

      let contatoPreenchido = false;
      if (Object.keys(contatoCandidato).length > 0) {
        try {
          const rc = await fetch(`/cliente/${contact.id}/contato`, {
            method: 'PATCH',
            headers: { ...headers, 'Content-Type': 'application/json' },
            body: JSON.stringify(contatoCandidato),
          });
          contatoPreenchido = rc.ok;
          if (!rc.ok) console.warn('[IdentificacaoTab] PATCH contato pos-CNPJ falhou', rc.status);
        } catch (err) {
          console.warn('[IdentificacaoTab] PATCH contato network', err);
        }
      }

      // ── Campos derivados SEFAZ (#1431 Técnica C) — gatilhos NFe + UX warnings
      const certSrc = (sefazData?.cert_source as string) || '';
      let sefazSource: 'none' | 'primary' | 'institutional' | 'unsupported' | 'no_cert' = 'none';
      const alertasSefaz = (sefazData?.alertas as Array<{ code: string; severity: string; msg: string }>) ?? [];

      if (sefazData) {
        sefazSource = certSrc === 'institutional_fallback' ? 'institutional' : 'primary';

        // Persist derivados via PATCH /identificacao (ADR 0186 §Decisão).
        const sefazPersist: Record<string, unknown> = {};
        if (sefazData.ind_ie_dest != null) sefazPersist.ind_ie_dest = sefazData.ind_ie_dest;
        if (sefazData.situacao_label != null) sefazPersist.sefaz_cad_sit = sefazData.situacao_label;
        if (sefazData.ind_cred_nfe != null) sefazPersist.sefaz_cad_ind_cred_nfe = sefazData.ind_cred_nfe;
        if (sefazData.consultado_em != null) sefazPersist.sefaz_cad_consultado_em = sefazData.consultado_em;
        if (Object.keys(sefazPersist).length > 0) {
          fetch(`/cliente/${contact.id}/identificacao`, {
            method: 'PATCH',
            headers: { ...headers, 'Content-Type': 'application/json' },
            body: JSON.stringify(sefazPersist),
          }).catch((e) => console.warn('[IdentificacaoTab] PATCH SEFAZ persist falhou', e));
        }
      } else if (sefazReason === 'uf_unsupported') {
        sefazSource = 'unsupported';
      } else if (sefazReason === 'no_cert' || sefazReason === 'sefaz_or_cert_error') {
        sefazSource = 'no_cert';
      }

      // Refresca rows pro parent (EnderecoTab/ContatoTab re-renderizar) — #1419 callback
      if (enderecoPreenchido || contatoPreenchido) {
        onCnpjEnderecoPersisted?.();
      }

      // ── Badge UI — combina endereço/contato persistidos + SEFAZ source + alertas + timeout
      const fontesPartes: string[] = ['Receita'];
      if (enderecoPreenchido) fontesPartes.push('endereço');
      if (contatoPreenchido) fontesPartes.push('contato');
      const fontesMsg = fontesPartes.length === 1
        ? fontesPartes[0]
        : `${fontesPartes[0]} + ${fontesPartes.slice(1).join(' e ')}`;

      let mensagemBadge = `${fontesMsg} preenchidos.`;
      if (sefazSource === 'primary') {
        mensagemBadge = `${fontesMsg} + SEFAZ-${ufFinal} (seu certificado).`;
      } else if (sefazSource === 'institutional') {
        mensagemBadge = `${fontesMsg} + SEFAZ-${ufFinal} (cert oimpresso — configure o seu em /fiscal/config).`;
      } else if (sefazTimeoutFlag) {
        mensagemBadge = `${fontesMsg} preenchidos. SEFAZ-${ufFinal} demorou (8s) — tente de novo pra IE.`;
      } else if (sefazSource === 'unsupported') {
        mensagemBadge = `${fontesMsg} preenchidos. SEFAZ-${ufFinal} não disponível — preencha IE manual.`;
      } else if (sefazSource === 'no_cert') {
        mensagemBadge = `${fontesMsg} preenchidos. Configure cert A1 em /fiscal/config pra IE automática.`;
      }

      // Append alerta SEFAZ severity high/medium (evitar rejeição NFe antes da emissão).
      if (alertasSefaz.length > 0) {
        const alertaHigh = alertasSefaz.find((a) => a.severity === 'high');
        const alertaMed = alertasSefaz.find((a) => a.severity === 'medium');
        const principal = alertaHigh ?? alertaMed ?? alertasSefaz[0];
        if (principal) {
          mensagemBadge += ` ⚠️ ${principal.msg}`;
          setCnpjLookup('error');
        } else {
          setCnpjLookup('ok');
        }
      } else {
        setCnpjLookup('ok');
      }
      setCnpjLookupMsg(mensagemBadge);
      setTimeout(() => {
        setCnpjLookup('idle');
        setCnpjLookupMsg(null);
      }, 6000); // alerta SEFAZ merece 6s pra leitura
    } catch (err) {
      setCnpjLookup('error');
      setCnpjLookupMsg('Falha ao consultar Receita.');
      // eslint-disable-next-line no-console
      console.error('[IdentificacaoTab] cnpj lookup failed', err);
    }
  }, [doc, nome, fantasia, ie, performSave, contact, onCnpjEnderecoPersisted]);

  // ── Render ───────────────────────────────────────────────────────────
  const isPJ = tipo === 'PJ';

  return (
    <div className="space-y-5">
      {/* Toggle PF/PJ no topo */}
      <div role="radiogroup" aria-label="Tipo de pessoa" className="inline-flex items-center gap-1 rounded-md border border-input bg-muted/30 p-1">
        <button
          type="button"
          role="radio"
          aria-checked={tipo === 'PF'}
          disabled={disabled}
          onClick={() => handleTipoChange('PF')}
          className={`inline-flex items-center gap-1.5 rounded px-3 py-1.5 text-xs font-medium transition-colors ${
            tipo === 'PF'
              ? 'bg-background text-foreground shadow-sm'
              : 'text-muted-foreground hover:text-foreground'
          }`}
        >
          <User size={12} aria-hidden /> Pessoa física
        </button>
        <button
          type="button"
          role="radio"
          aria-checked={tipo === 'PJ'}
          disabled={disabled}
          onClick={() => handleTipoChange('PJ')}
          className={`inline-flex items-center gap-1.5 rounded px-3 py-1.5 text-xs font-medium transition-colors ${
            tipo === 'PJ'
              ? 'bg-background text-foreground shadow-sm'
              : 'text-muted-foreground hover:text-foreground'
          }`}
        >
          <Building2 size={12} aria-hidden /> Pessoa jurídica
        </button>
      </div>

      {/* ADR 0188 Onda 4 — Seção Papéis (multi-type flags).
          Permite que 1 contato tenha N papéis simultâneos (Wagner Rocha cliente+
          representante = mesma row · sem duplicar cadastro como acontecia no UPOS
          single-type). Insight Delphi WR Comercial — flag bool por papel.
          Backend invariante: ≥1 papel ativo (não permite desmarcar todos). */}
      <fieldset className="rounded-md border border-input bg-muted/20 p-3">
        <legend className="px-1.5 text-xs font-medium text-muted-foreground">
          Papéis <span className="font-normal">(marque todos que se aplicam)</span>
        </legend>
        <div className="grid grid-cols-2 gap-2 mt-1">
          {([
            { flag: 'is_customer' as const,       value: isCustomer,       label: 'Cliente',       Icon: User },
            { flag: 'is_supplier' as const,       value: isSupplier,       label: 'Fornecedor',    Icon: Building2 },
            { flag: 'is_employee' as const,       value: isEmployee,       label: 'Funcionário',   Icon: User },
            { flag: 'is_representative' as const, value: isRepresentative, label: 'Representante', Icon: User },
          ]).map(({ flag, value, label, Icon }) => (
            <label
              key={flag}
              className={
                'inline-flex items-center gap-2 px-2 py-1.5 text-sm rounded-md cursor-pointer transition-colors ' +
                'hover:bg-background focus-within:ring-2 focus-within:ring-primary/40 ' +
                (value ? 'text-foreground font-medium' : 'text-muted-foreground')
              }
            >
              <input
                type="checkbox"
                checked={value}
                disabled={disabled}
                onChange={(e) => handlePapelToggle(flag, e.target.checked)}
                aria-label={label}
                className="h-4 w-4 rounded border-input accent-primary cursor-pointer disabled:opacity-50"
              />
              <Icon size={14} aria-hidden className={value ? 'text-primary' : ''} />
              {label}
              {savingField === flag && <Loader2 size={11} className="animate-spin ml-auto" aria-hidden />}
              {savedField === flag && <CheckCircle2 size={11} className="ml-auto text-primary" aria-hidden />}
            </label>
          ))}
        </div>
        {errorField?.field?.startsWith('is_') && (
          <p className="mt-2 inline-flex items-center gap-1 text-xs text-destructive" role="alert">
            <AlertCircle size={11} aria-hidden /> {errorField.message}
          </p>
        )}
      </fieldset>

      {/* Linha 1: Nome/Razão social */}
      <div className="grid gap-4 md:grid-cols-2">
        <div className="md:col-span-2">
          <Label htmlFor="id-nome" className="text-xs font-medium">
            {isPJ ? 'Razão social' : 'Nome completo'} <span className="text-rose-600">*</span>
          </Label>
          <Input
            id="id-nome"
            value={nome}
            placeholder={isPJ ? 'Ex.: Dragão Verde Comunicação Visual Ltda' : 'Ex.: Marina Costa'}
            disabled={disabled}
            aria-invalid={!!nomeError}
            aria-describedby={nomeError ? 'id-nome-error' : undefined}
            onChange={(e) => {
              const prev = nome;
              const v = e.target.value;
              setNome(v);
              scheduleAutosave('nome', v, prev);
            }}
            onBlur={(e) => handleBlur('nome', e.target.value)}
            className={nomeError ? 'border-rose-500 focus-visible:ring-rose-400' : ''}
          />
          <FieldStatus
            error={nomeError}
            errorId="id-nome-error"
            saving={savingField === 'nome'}
            saved={savedField === 'nome'}
            backendError={errorField?.field === 'nome' ? errorField.message : null}
          />
        </div>

        {/* Fantasia só PJ */}
        {isPJ && (
          <div className="md:col-span-2">
            <Label htmlFor="id-fantasia" className="text-xs font-medium">
              Nome fantasia <span className="text-muted-foreground font-normal">(opcional)</span>
            </Label>
            <Input
              id="id-fantasia"
              value={fantasia}
              placeholder="Como o cliente é conhecido"
              disabled={disabled}
              onChange={(e) => {
                const prev = fantasia;
                const v = e.target.value;
                setFantasia(v);
                scheduleAutosave('fantasia', v, prev);
              }}
              onBlur={(e) => handleBlur('fantasia', e.target.value)}
            />
            <FieldStatus
              saving={savingField === 'fantasia'}
              saved={savedField === 'fantasia'}
              backendError={errorField?.field === 'fantasia' ? errorField.message : null}
            />
          </div>
        )}

        {/* Documento (CPF ou CNPJ) + botão Buscar CNPJ */}
        <div className="md:col-span-2">
          <Label htmlFor="id-doc" className="text-xs font-medium">
            {isPJ ? 'CNPJ' : 'CPF'} {isPJ && <span className="ml-1 text-xs text-muted-foreground">(clique em Buscar pra preencher automático)</span>}
          </Label>
          <div className="flex gap-2">
            <Input
              id="id-doc"
              value={doc}
              placeholder={isPJ ? '00.000.000/0000-00' : '000.000.000-00'} // pii-allowlist máscara visual de input
              disabled={disabled}
              inputMode="numeric"
              aria-invalid={!!docError}
              aria-describedby={docError ? 'id-doc-error' : undefined}
              onChange={(e) => {
                const prev = doc;
                const masked = isPJ ? maskCNPJ(e.target.value) : maskCPF(e.target.value);
                setDoc(masked);
                scheduleAutosave('doc', masked, prev);
              }}
              onBlur={(e) => handleBlur('doc', e.target.value)}
              className={docError ? 'border-rose-500 focus-visible:ring-rose-400' : ''}
            />
            {isPJ && (
              <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={disabled || cnpjLookup === 'loading' || onlyDigits(doc).length !== 14}
                onClick={handleCnpjLookup}
                className="shrink-0"
                aria-label="Buscar CNPJ na Receita Federal"
              >
                {cnpjLookup === 'loading' ? (
                  <>
                    <Loader2 size={14} className="animate-spin" /> Buscando…
                  </>
                ) : cnpjLookup === 'ok' ? (
                  <>
                    <CheckCircle2 size={14} className="text-emerald-600" /> Encontrado
                  </>
                ) : (
                  <>
                    <Search size={14} /> Buscar CNPJ
                  </>
                )}
              </Button>
            )}
          </div>
          {cnpjLookupMsg && (
            <p
              className={`mt-1 text-xs ${
                cnpjLookup === 'error' ? 'text-rose-600' : 'text-emerald-600'
              }`}
              role="status"
              aria-live="polite"
            >
              {cnpjLookupMsg}
            </p>
          )}
          <FieldStatus
            error={docError}
            errorId="id-doc-error"
            saving={savingField === 'doc'}
            saved={savedField === 'doc'}
            backendError={errorField?.field === 'doc' ? errorField.message : null}
          />
        </div>

        {/* IE (PJ) ou RG (PF) */}
        {isPJ ? (
          <div>
            <Label htmlFor="id-ie" className="text-xs font-medium">
              Inscrição estadual <span className="text-muted-foreground font-normal">(opcional)</span>
            </Label>
            <Input
              id="id-ie"
              value={ie}
              placeholder="000.000.000.000"
              disabled={disabled}
              onChange={(e) => {
                const prev = ie;
                const v = e.target.value;
                setIe(v);
                scheduleAutosave('ie', v, prev);
              }}
              onBlur={(e) => handleBlur('ie', e.target.value)}
            />
            <FieldStatus
              saving={savingField === 'ie'}
              saved={savedField === 'ie'}
              backendError={errorField?.field === 'ie' ? errorField.message : null}
            />
          </div>
        ) : (
          <div>
            <Label htmlFor="id-rg" className="text-xs font-medium">
              RG <span className="text-muted-foreground font-normal">(opcional)</span>
            </Label>
            <Input
              id="id-rg"
              value={rg}
              placeholder="00.000.000-0"
              disabled={disabled}
              onChange={(e) => {
                const prev = rg;
                const v = e.target.value;
                setRg(v);
                scheduleAutosave('rg', v, prev);
              }}
              onBlur={(e) => handleBlur('rg', e.target.value)}
            />
            <FieldStatus
              saving={savingField === 'rg'}
              saved={savedField === 'rg'}
              backendError={errorField?.field === 'rg' ? errorField.message : null}
            />
          </div>
        )}

        {/* Nascimento — só PF */}
        {!isPJ && (
          <div>
            <Label htmlFor="id-nascimento" className="text-xs font-medium">
              Data de nascimento <span className="text-muted-foreground font-normal">(opcional)</span>
            </Label>
            <Input
              id="id-nascimento"
              type="date"
              value={nascimento}
              disabled={disabled}
              onChange={(e) => {
                const prev = nascimento;
                const v = e.target.value;
                setNascimento(v);
                scheduleAutosave('nascimento', v, prev);
              }}
              onBlur={(e) => handleBlur('nascimento', e.target.value)}
            />
            <FieldStatus
              saving={savingField === 'nascimento'}
              saved={savedField === 'nascimento'}
              backendError={errorField?.field === 'nascimento' ? errorField.message : null}
            />
          </div>
        )}

        {/* Contato principal — só PJ */}
        {isPJ && (
          <div className="md:col-span-2">
            <Label htmlFor="id-contato" className="text-xs font-medium">
              Contato principal <span className="text-muted-foreground font-normal">(opcional)</span>
            </Label>
            <Input
              id="id-contato"
              value={contatoNome}
              placeholder="Nome do responsável"
              disabled={disabled}
              onChange={(e) => {
                const prev = contatoNome;
                const v = e.target.value;
                setContatoNome(v);
                scheduleAutosave('contato', v, prev);
              }}
              onBlur={(e) => handleBlur('contato', e.target.value)}
            />
            <FieldStatus
              saving={savingField === 'contato'}
              saved={savedField === 'contato'}
              backendError={errorField?.field === 'contato' ? errorField.message : null}
            />
          </div>
        )}

        {/* Cargo — só PJ */}
        {isPJ && (
          <div className="md:col-span-2">
            <Label htmlFor="id-cargo" className="text-xs font-medium">
              Cargo do contato <span className="text-muted-foreground font-normal">(opcional)</span>
            </Label>
            <Input
              id="id-cargo"
              value={cargo}
              placeholder="Ex.: Diretor de marketing"
              disabled={disabled}
              onChange={(e) => {
                const prev = cargo;
                const v = e.target.value;
                setCargo(v);
                scheduleAutosave('cargo', v, prev);
              }}
              onBlur={(e) => handleBlur('cargo', e.target.value)}
            />
            <FieldStatus
              saving={savingField === 'cargo'}
              saved={savedField === 'cargo'}
              backendError={errorField?.field === 'cargo' ? errorField.message : null}
            />
          </div>
        )}
      </div>
    </div>
  );
}

// ── Subcomponente: feedback "Salvando…/Salvo/Erro" ─────────────────────
interface FieldStatusProps {
  error?: string | null;
  errorId?: string;
  saving?: boolean;
  saved?: boolean;
  backendError?: string | null;
}

function FieldStatus({ error, errorId, saving, saved, backendError }: FieldStatusProps) {
  if (error) {
    return (
      <p id={errorId} className="mt-1 inline-flex items-center gap-1 text-xs text-rose-600" role="alert">
        <AlertCircle size={11} aria-hidden /> {error}
      </p>
    );
  }
  if (backendError) {
    return (
      <p className="mt-1 inline-flex items-center gap-1 text-xs text-rose-600" role="alert">
        <AlertCircle size={11} aria-hidden /> {backendError}
      </p>
    );
  }
  if (saving) {
    return (
      <p className="mt-1 inline-flex items-center gap-1 text-xs text-muted-foreground" aria-live="polite">
        <Loader2 size={11} className="animate-spin" aria-hidden /> Salvando…
      </p>
    );
  }
  if (saved) {
    return (
      <p className="mt-1 inline-flex items-center gap-1 text-xs text-emerald-600" aria-live="polite">
        <CheckCircle2 size={11} aria-hidden /> Salvo
      </p>
    );
  }
  return null;
}
