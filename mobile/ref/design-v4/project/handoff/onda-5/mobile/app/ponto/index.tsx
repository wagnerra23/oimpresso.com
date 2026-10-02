/**
 * Ponto — REP-P no app (Onda 5). Mesmo fluxo de resources/js/Pages/Ponto/Mobile (web):
 * Bater ponto · Meu espelho · Justificar. Espelho visual: telas 36–38 do "Oimpresso Mobile".
 */
import { useEffect, useMemo, useState } from "react";
import { ActivityIndicator, Pressable, ScrollView, Text, View } from "react-native";
import { router } from "expo-router";

import { OiBtn, OiCard, OiDetailHeader, OiEmpty, OiScreen, OiSection, OiStatus } from "@/components/oi";
import { OiFormInput } from "@/components/oi/OiForm";
import { fonts, radius, touch } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { useNotify } from "@/lib/notify";
import { useNetworkState } from "@/hooks/use-network-state";
import { useDeviceUuid, useGps, usePontoToken } from "@/hooks/use-ponto";
import { fmtMinutos, pontoApi, PontoApiError, type IntercorrenciaResumo, type MarcacaoHoje, type TipoMarcacao, type Turno } from "@/lib/ponto-api";

const ACCURACY_MAX = 500;
const TIPOS: { id: TipoMarcacao; label: string; hint: string }[] = [
  { id: "ENTRADA", label: "Entrada", hint: "início da jornada" },
  { id: "ALMOCO_INICIO", label: "Saída almoço", hint: "intervalo" },
  { id: "ALMOCO_FIM", label: "Retorno almoço", hint: "volta do intervalo" },
  { id: "SAIDA", label: "Saída", hint: "fim da jornada" },
];
const rotulo = (t: string) => TIPOS.find((x) => x.id === t)?.label ?? t;
const proximo = (n: number) => TIPOS[Math.min(n, 3)].id;

type Aba = "bater" | "espelho" | "justificar";

export default function PontoScreen() {
  const token = usePontoToken();
  const [aba, setAba] = useState<Aba>("bater");
  return (
    <OiScreen>
      <OiDetailHeader eyebrow="REP-P · Portaria MTP 671/2021" title="Ponto" onBack={() => router.back()} />
      <View style={{ paddingHorizontal: 16, paddingTop: 12 }}>
        <Abas value={aba} onChange={setAba} />
      </View>
      {!token ? (
        <OiEmpty icon="shield" title="Ponto não liberado neste aparelho" subtitle="Seu usuário precisa de cadastro de ponto. Peça ao RH em Colaboradores." />
      ) : (
        <>
          {/* As 3 ficam montadas: trocar de aba não pode perder a batida nem o GPS (igual ao web). */}
          <View style={{ flex: 1, display: aba === "bater" ? "flex" : "none" }}><BaterPonto token={token} /></View>
          <View style={{ flex: 1, display: aba === "espelho" ? "flex" : "none" }}><MeuEspelho token={token} ativo={aba === "espelho"} /></View>
          <View style={{ flex: 1, display: aba === "justificar" ? "flex" : "none" }}><Justificar token={token} /></View>
        </>
      )}
    </OiScreen>
  );
}

const ABAS: { id: Aba; label: string }[] = [{ id: "bater", label: "Bater ponto" }, { id: "espelho", label: "Meu espelho" }, { id: "justificar", label: "Justificar" }];

function Abas({ value, onChange }: { value: Aba; onChange: (a: Aba) => void }) {
  const { palette } = useOiTheme();
  return (
    <View accessibilityRole="tablist" style={{ flexDirection: "row", gap: 3, padding: 3, borderRadius: radius.md, backgroundColor: palette.bg2 }}>
      {ABAS.map((a) => {
        const on = a.id === value;
        return (
          <Pressable key={a.id} accessibilityRole="tab" accessibilityState={{ selected: on }} onPress={() => onChange(a.id)}
            style={{ flex: 1, height: 40, borderRadius: radius.sm, alignItems: "center", justifyContent: "center", backgroundColor: on ? palette.surface : "transparent" }}>
            <Text style={{ fontFamily: fonts.sansSemibold, fontSize: 13, color: on ? palette.text : palette.textMute }}>{a.label}</Text>
          </Pressable>
        );
      })}
    </View>
  );
}

// Sem Intl dateStyle: Hermes em Android antigo cai no formato curto.
const MESES = ["janeiro", "fevereiro", "março", "abril", "maio", "junho", "julho", "agosto", "setembro", "outubro", "novembro", "dezembro"];
const DIAS = ["domingo", "segunda", "terça", "quarta", "quinta", "sexta", "sábado"];
const dataLonga = (d: Date) => `${d.getDate()} de ${MESES[d.getMonth()]} de ${d.getFullYear()} · ${DIAS[d.getDay()]}`;

function Relogio() {
  const { palette } = useOiTheme();
  const [agora, setAgora] = useState(() => new Date());
  useEffect(() => { const t = setInterval(() => setAgora(new Date()), 1000); return () => clearInterval(t); }, []);
  return (
    <View style={{ alignItems: "center", paddingVertical: 8 }}>
      <Text style={{ fontFamily: fonts.sans, fontSize: 12, color: palette.textMute }}>agora</Text>
      <Text style={{ fontFamily: fonts.monoSemibold, fontSize: 52, letterSpacing: -1.5, color: palette.text, fontVariant: ["tabular-nums"] }}>{agora.toLocaleTimeString("pt-BR")}</Text>
      <Text style={{ fontFamily: fonts.sans, fontSize: 12, color: palette.textMute }}>{dataLonga(agora)}</Text>
    </View>
  );
}

function BaterPonto({ token }: { token: string }) {
  const { palette } = useOiTheme();
  const notify = useNotify();
  const { isOnline } = useNetworkState();
  const { gps, localizar } = useGps();
  const uuid = useDeviceUuid();
  const [marcacoes, setMarcacoes] = useState<MarcacaoHoje[]>([]);
  const [carregando, setCarregando] = useState(true);
  const [tipo, setTipo] = useState<TipoMarcacao>("ENTRADA");
  const [enviando, setEnviando] = useState(false);
  const [recibo, setRecibo] = useState<{ hora: string; tipo: string; nsr: number; hash: string; revisar: boolean } | null>(null);

  useEffect(() => {
    pontoApi.hoje(token).then((r) => { setMarcacoes(r.marcacoes); setTipo(proximo(r.marcacoes.length)); }).catch(() => undefined).finally(() => setCarregando(false));
  }, [token]);

  // Espelha a regra do servidor só para não deixar tentar — quem decide é o servidor (422).
  const bloqueio = !isOnline ? "Sem conexão — a marcação precisa do servidor (NSR e hash vêm de lá)."
    : gps.estado === "buscando" ? "Buscando sua localização…"
    : gps.estado === "indisponivel" ? gps.motivo
    : gps.accuracy > ACCURACY_MAX ? "Sinal de GPS fraco — aproxime-se de área aberta"
    : !uuid ? "Preparando o aparelho…" : null;

  const bater = async () => {
    if (bloqueio || enviando || gps.estado !== "ok" || !uuid) return;
    setEnviando(true);
    try {
      const { marcacao: m } = await pontoApi.marcar(token, { tipo, lat: gps.lat, lng: gps.lng, accuracy: gps.accuracy, device_uuid: uuid, timestamp_device: new Date().toISOString() });
      const hora = new Date(m.momento).toTimeString().slice(0, 5);
      setMarcacoes((ms) => { const next = [...ms, { id: m.id, nsr: m.nsr, tipo: m.tipo, origem: m.origem, hora, hash_trunc: m.hash_trunc, revisar: m.revisar }]; setTipo(proximo(next.length)); return next; });
      setRecibo({ hora, tipo: m.tipo, nsr: m.nsr, hash: String(m.hash_trunc), revisar: m.revisar });
      notify(m.revisar ? "aviso" : "sucesso", `Marcação registrada · NSR ${m.nsr}${m.revisar ? " · fora da área, foi para revisão" : ""}`);
    } catch (err) {
      notify("erro", err instanceof PontoApiError ? (err.codigo === "sem_colaborador" ? "Seu usuário não tem cadastro de ponto neste empregador." : err.message) : "Não foi possível registrar a marcação.");
    } finally {
      setEnviando(false);
    }
  };

  const gpsTone = gps.estado === "ok" ? (gps.accuracy > ACCURACY_MAX ? palette.danger : palette.ok) : gps.estado === "buscando" ? palette.textMute : palette.danger;

  return (
    <ScrollView contentContainerStyle={{ padding: 16, gap: 12, paddingBottom: 32 }}>
      <Relogio />
      <OiCard variant="tight" style={{ flexDirection: "row", alignItems: "center", gap: 10 }}>
        <View style={{ width: 8, height: 8, borderRadius: 4, backgroundColor: gpsTone }} />
        <Text style={{ flex: 1, fontFamily: fonts.sansSemibold, fontSize: 13.5, color: palette.text }}>
          {gps.estado === "ok" ? `GPS ±${gps.accuracy} m` : gps.estado === "buscando" ? "Buscando GPS…" : "GPS indisponível"}
        </Text>
        <OiBtn size="sm" variant="ghost" leftIcon="location" label="Atualizar local" onPress={localizar} />
      </OiCard>

      <Text style={{ fontFamily: fonts.sansBold, fontSize: 10.5, letterSpacing: 1.2, textTransform: "uppercase", color: palette.textMute }}>Tipo de marcação</Text>
      <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 8 }}>
        {TIPOS.map((t) => {
          const on = tipo === t.id;
          return (
            <Pressable key={t.id} onPress={() => setTipo(t.id)} accessibilityState={{ selected: on }}
              style={{ flexGrow: 1, flexBasis: "45%", minHeight: 56, padding: 10, borderRadius: radius.md, borderWidth: 1, borderColor: on ? palette.accent : palette.border, backgroundColor: on ? palette.accentSoft : palette.surface, justifyContent: "center" }}>
              <Text style={{ fontFamily: fonts.sansSemibold, fontSize: 14, color: on ? palette.accent : palette.text }}>{t.label}</Text>
              <Text style={{ fontFamily: fonts.sans, fontSize: 11.5, color: palette.textMute }}>{t.hint}</Text>
            </Pressable>
          );
        })}
      </View>

      <Pressable onPress={bater} disabled={!!bloqueio || enviando}
        style={{ height: 52, borderRadius: radius.md, alignItems: "center", justifyContent: "center", backgroundColor: bloqueio ? palette.bg2 : palette.accent, opacity: enviando ? 0.7 : 1 }}>
        <Text style={{ fontFamily: fonts.sansSemibold, fontSize: 15, color: bloqueio ? palette.textMute : palette.accentFg }}>{enviando ? "Registrando…" : `Bater ponto — ${rotulo(tipo)}`}</Text>
      </Pressable>
      {bloqueio ? <Text accessibilityRole="alert" style={{ fontFamily: fonts.sansMedium, fontSize: 12.5, color: palette.warn }}>{bloqueio}</Text> : null}

      {recibo ? (
        <OiCard style={{ borderStyle: "dashed", gap: 6 }}>
          <View style={{ flexDirection: "row", alignItems: "center" }}>
            <Text style={{ flex: 1, fontFamily: fonts.sansBold, fontSize: 10.5, letterSpacing: 1.2, textTransform: "uppercase", color: palette.textMute }}>Comprovante de marcação</Text>
            <OiStatus label={recibo.revisar ? "Em revisão · fora da área" : "Registrada"} variant={recibo.revisar ? "warn" : "ok"} />
          </View>
          <Text style={{ fontFamily: fonts.monoSemibold, fontSize: 26, color: palette.text }}>{recibo.hora} <Text style={{ fontFamily: fonts.sansSemibold, fontSize: 14 }}>{rotulo(recibo.tipo)}</Text></Text>
          <Text style={{ fontFamily: fonts.mono, fontSize: 11.5, color: palette.textDim }}>NSR {recibo.nsr} · hash {recibo.hash}</Text>
        </OiCard>
      ) : null}

      <Text style={{ fontFamily: fonts.sansBold, fontSize: 10.5, letterSpacing: 1.2, textTransform: "uppercase", color: palette.textMute }}>Jornada de hoje</Text>
      {carregando ? <ActivityIndicator color={palette.accent} /> : (
        <View style={{ flexDirection: "row", gap: 6 }}>
          {TIPOS.map((t) => {
            const m = marcacoes.find((x) => x.tipo === t.id);
            const next = !m && tipo === t.id;
            return (
              <View key={t.id} style={{ flex: 1, minHeight: 72, padding: 8, borderRadius: radius.md, borderWidth: 1, borderStyle: m || next ? "solid" : "dashed", borderColor: next ? palette.accent : palette.border, backgroundColor: m ? palette.surface : next ? palette.accentSoft : "transparent", gap: 2 }}>
                <Text style={{ fontFamily: fonts.sansSemibold, fontSize: 10.5, color: palette.textMute }}>{t.label}</Text>
                <Text style={{ fontFamily: fonts.monoSemibold, fontSize: 15, color: m ? palette.text : palette.textMute }}>{m?.hora ?? "—:—"}</Text>
                <Text style={{ marginTop: "auto", fontFamily: fonts.mono, fontSize: 10, color: m?.revisar ? palette.warn : next ? palette.accent : palette.textMute }}>{m ? (m.revisar ? "fora da área" : `NSR ${m.nsr}`) : next ? "próxima" : ""}</Text>
              </View>
            );
          })}
        </View>
      )}
      <Text style={{ fontFamily: fonts.sans, fontSize: 11.5, color: palette.textMute }}>Marcação imutável (Portaria MTP 671/2021). Correção só por intercorrência. Sem selfie nem biometria.</Text>
    </ScrollView>
  );
}

const ESTADO: Record<string, { label: string; variant: "warn" | "ok" | "danger" | "neutral" }> = {
  PENDENTE: { label: "Pendente", variant: "warn" },
  APROVADA: { label: "Aprovada", variant: "ok" },
  APLICADA: { label: "Aplicada", variant: "ok" },
  REJEITADA: { label: "Rejeitada", variant: "danger" },
  CANCELADA: { label: "Cancelada", variant: "neutral" },
};

/**
 * Meu espelho — com as rotas que JÁ existem na API: saldo do banco de horas, escala de hoje
 * e minhas justificativas. O dia a dia do mês (totais + linhas do Espelho/Show) ainda não tem
 * rota JSON — no web vem por Inertia::defer. Proposta: GET /ponto/api/espelho?mes=AAAA-MM.
 */
function MeuEspelho({ token, ativo }: { token: string; ativo: boolean }) {
  const { palette } = useOiTheme();
  const [saldo, setSaldo] = useState<{ usa: boolean; min: number; ult: string | null } | null>(null);
  const [turno, setTurno] = useState<{ nome: string | null; t: Turno | null } | null>(null);
  const [ints, setInts] = useState<IntercorrenciaResumo[] | null>(null);
  const [erro, setErro] = useState<string | null>(null);

  useEffect(() => {
    if (!ativo) return;
    Promise.all([pontoApi.saldo(token), pontoApi.escalaHoje(token), pontoApi.intercorrencias(token)])
      .then(([s, e, i]) => { setSaldo({ usa: s.usa_banco_horas, min: s.saldo_minutos, ult: s.ultima_movimentacao }); setTurno({ nome: e.escala?.nome ?? null, t: e.turno }); setInts(i.intercorrencias); setErro(null); })
      .catch((err) => setErro(err instanceof PontoApiError ? err.message : "Não foi possível carregar."));
  }, [token, ativo]);

  if (erro) return <OiEmpty icon="alert" title="Não foi possível carregar" subtitle={erro} />;
  if (!saldo || !turno || !ints) return <ActivityIndicator color={palette.accent} style={{ marginTop: 32 }} />;

  const H = ({ t }: { t: string }) => <Text style={{ fontFamily: fonts.sansBold, fontSize: 10.5, letterSpacing: 1.2, textTransform: "uppercase", color: palette.textMute }}>{t}</Text>;
  const cel = (label: string, v: string | null | undefined) => (
    <View key={label} style={{ flex: 1, gap: 2 }}>
      <Text style={{ fontFamily: fonts.sans, fontSize: 11, color: palette.textMute }}>{label}</Text>
      <Text style={{ fontFamily: fonts.monoSemibold, fontSize: 15, color: palette.text }}>{v ? v.slice(0, 5) : "—"}</Text>
    </View>
  );

  return (
    <ScrollView contentContainerStyle={{ padding: 16, gap: 12, paddingBottom: 32 }}>
      {saldo.usa ? (
        <OiCard>
          <H t="Banco de horas" />
          <Text style={{ fontFamily: fonts.monoSemibold, fontSize: 32, color: saldo.min < 0 ? palette.danger : saldo.min > 0 ? palette.ok : palette.text }}>{fmtMinutos(saldo.min)}</Text>
          <Text style={{ fontFamily: fonts.sans, fontSize: 12, color: palette.textDim }}>{saldo.ult ? `Última movimentação em ${saldo.ult.split("-").reverse().join("/")}` : "Sem movimentação ainda."}</Text>
        </OiCard>
      ) : null}
      <OiCard>
        <H t={turno.nome ? `Escala de hoje · ${turno.nome}` : "Escala de hoje"} />
        {turno.t ? (
          <View style={{ flexDirection: "row", gap: 8 }}>
            {cel("Entrada", turno.t.hora_entrada)}{cel("Almoço", turno.t.hora_almoco_inicio)}{cel("Retorno", turno.t.hora_almoco_fim)}{cel("Saída", turno.t.hora_saida)}
          </View>
        ) : <Text style={{ fontFamily: fonts.sans, fontSize: 13, color: palette.textDim }}>Sem turno hoje.</Text>}
      </OiCard>
      <H t={`Minhas justificativas · ${ints.length}`} />
      {ints.length === 0 ? <Text style={{ fontFamily: fonts.sans, fontSize: 13, color: palette.textDim }}>Nenhuma justificativa enviada.</Text> : (
        <OiCard variant="tight" style={{ padding: 0, gap: 0 }}>
          {ints.slice(0, 10).map((i, k) => {
            const st = ESTADO[i.estado] ?? { label: i.estado, variant: "neutral" as const };
            return (
              <View key={i.id} style={{ minHeight: 52, paddingHorizontal: 12, flexDirection: "row", alignItems: "center", gap: 10, borderBottomWidth: k === Math.min(ints.length, 10) - 1 ? 0 : 1, borderBottomColor: palette.border2 }}>
                <View style={{ flex: 1 }}>
                  <Text style={{ fontFamily: fonts.monoSemibold, fontSize: 12.5, color: palette.text }}>{i.codigo}</Text>
                  <Text style={{ fontFamily: fonts.sans, fontSize: 12, color: palette.textDim }}>{i.data ? i.data.split("-").reverse().join("/") : "—"} · {i.dia_todo ? "dia todo" : `${i.intervalo_inicio?.slice(0, 5) ?? ""}–${i.intervalo_fim?.slice(0, 5) ?? ""}`}</Text>
                </View>
                <OiStatus label={st.label} variant={st.variant} />
              </View>
            );
          })}
        </OiCard>
      )}
      <Text style={{ fontFamily: fonts.sans, fontSize: 11.5, color: palette.textMute }}>O dia a dia do mês ainda não tem rota na API. O espelho oficial sai no fechamento da competência.</Text>
    </ScrollView>
  );
}

const MOTIVOS = [
  { value: "ESQUECIMENTO", label: "Esqueci de marcar" },
  { value: "SERVICO_EXTERNO", label: "Serviço externo" },
  { value: "ATESTADO", label: "Atestado médico" },
  { value: "FALHA_APARELHO", label: "Falha do aparelho" },
  { value: "OUTRO", label: "Outro" },
];

function Justificar({ token }: { token: string }) {
  const { palette } = useOiTheme();
  const notify = useNotify();
  const hoje = new Date().toISOString().slice(0, 10);
  const vazio = { tipo: "", data: hoje, dia_todo: false, das: "", as: "", texto: "" };
  const [f, setF] = useState(vazio);
  const [enviando, setEnviando] = useState(false);
  const set = <K extends keyof typeof vazio>(k: K, v: (typeof vazio)[K]) => setF((p) => ({ ...p, [k]: v }));

  // Mesmas regras do StoreIntercorrenciaRequest — o servidor decide; aqui só evita a viagem.
  const erro = useMemo(() => !f.tipo ? "Escolha o motivo."
    : !f.dia_todo && (!f.das || !f.as) ? "Informe o horário (das/às) ou marque Dia todo — o gestor decide pela janela."
    : !f.dia_todo && f.as <= f.das ? "O fim precisa ser depois do início."
    : f.texto.trim().length < 10 ? "Descreva com pelo menos 10 caracteres." : null, [f]);

  const enviar = async () => {
    if (erro || enviando) return;
    setEnviando(true);
    try {
      const r = await pontoApi.justificar(token, { tipo: f.tipo, data: f.data, dia_todo: f.dia_todo, intervalo_inicio: f.dia_todo ? null : f.das, intervalo_fim: f.dia_todo ? null : f.as, justificativa: f.texto });
      notify("sucesso", `Justificativa ${r.intercorrencia?.codigo ?? ""} enviada — está na fila de Aprovações como pendente.`);
      setF(vazio);
    } catch (err) {
      notify("erro", err instanceof PontoApiError ? (Object.values(err.errors ?? {})[0]?.[0] ?? err.message) : "Não foi possível enviar a justificativa.");
    } finally {
      setEnviando(false);
    }
  };

  return (
    <ScrollView contentContainerStyle={{ paddingBottom: 32 }}>
      <OiSection title="O que aconteceu">
        <View style={{ flexDirection: "row", flexWrap: "wrap", gap: 6 }}>
          {MOTIVOS.map((m) => {
            const on = f.tipo === m.value;
            return (
              <Pressable key={m.value} onPress={() => set("tipo", m.value)} accessibilityState={{ selected: on }}
                style={{ height: touch.default, paddingHorizontal: 14, borderRadius: radius.sm, borderWidth: 1, borderColor: on ? palette.accent : palette.border, backgroundColor: on ? palette.accent : palette.surface, justifyContent: "center" }}>
                <Text style={{ fontFamily: fonts.sansSemibold, fontSize: 13, color: on ? palette.accentFg : palette.text }}>{m.label}</Text>
              </Pressable>
            );
          })}
        </View>
      </OiSection>
      <OiSection title="Quando">
        <OiCard>
          <OiFormInput label="Dia (aaaa-mm-dd)" mono value={f.data} onChangeText={(v) => set("data", v)} />
          <Pressable onPress={() => set("dia_todo", !f.dia_todo)} style={{ minHeight: touch.default, flexDirection: "row", alignItems: "center", gap: 10 }}>
            <View style={{ width: 20, height: 20, borderRadius: 5, borderWidth: 1.5, borderColor: f.dia_todo ? palette.accent : palette.border, backgroundColor: f.dia_todo ? palette.accent : "transparent" }} />
            <Text style={{ fontFamily: fonts.sans, fontSize: 14, color: palette.text }}>Dia todo</Text>
          </Pressable>
          {!f.dia_todo ? (
            <View style={{ flexDirection: "row", gap: 8 }}>
              <View style={{ flex: 1 }}><OiFormInput label="Das" mono placeholder="12:00" value={f.das} onChangeText={(v) => set("das", v)} /></View>
              <View style={{ flex: 1 }}><OiFormInput label="Às" mono placeholder="13:00" value={f.as} onChangeText={(v) => set("as", v)} /></View>
            </View>
          ) : null}
        </OiCard>
      </OiSection>
      <OiSection title="Justificativa">
        <OiFormInput label="Descrição" multiline maxLength={2000} placeholder="Ex.: esqueci de marcar a volta do almoço, estava na obra." value={f.texto} onChangeText={(v) => set("texto", v)} />
        {erro ? <Text style={{ fontFamily: fonts.sansMedium, fontSize: 12.5, color: palette.warn, marginBottom: 10 }}>{erro}</Text> : null}
        <OiBtn variant="primary" block label={enviando ? "Enviando…" : "Enviar para aprovação"} disabled={!!erro} loading={enviando} onPress={enviar} />
        <Text style={{ fontFamily: fonts.sans, fontSize: 11.5, color: palette.textMute, marginTop: 8 }}>Vai para a fila do gestor como pendente. A marcação original não muda.</Text>
      </OiSection>
    </ScrollView>
  );
}
