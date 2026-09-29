/**
 * Venda Rápida — PDV móvel.
 *
 * Espelha `VendaRapidaScreen` do design v3 (`screens-clientes-producao.jsx`):
 *  - Empty state: viewfinder mock (scanline animado) + botão "Buscar produto"
 *  - Sheet "scan": busca em useProdutos() — tap adiciona ao carrinho
 *  - Carrinho: stepper de qty + subtotal + total
 *  - Sheet "pay": 5 métodos (PIX/Crédito/Débito/Dinheiro/Boleto)
 *  - Sucesso: ícone check verde + total + recibo
 *
 * Simplificações vs design:
 *  - ScannerView é só o keyframe animado (sem câmera real). O "scan" é via
 *    bottom sheet de busca textual.
 *  - Recibo número é random; sem endpoint de comprovante ainda.
 *  - useCreatePedido grava com customerId null (venda balcão), produto =
 *    concatenação dos itens, status="entregue", tipo="Venda Rápida".
 */
import { useMemo, useState } from "react";
import { Pressable, ScrollView, Text, TextInput, View } from "react-native";
import { Stack, useRouter } from "expo-router";

import {
  OiBtn,
  OiBtnRow,
  OiCard,
  OiDetailHeader,
  OiIcon,
  OiMoney,
  OiScanline,
  OiScreen,
  OiSection,
  OiSheet,
  type OiIconName,
} from "@/components/oi";
import { fonts, hexAlpha, radius } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { useCreatePedido, useProdutos } from "@/lib/erp-queries";
import { useERP, type Produto } from "@/lib/erp-context";

type CartItem = { produto: Produto; qty: number };
type Sheet = null | "scan" | "pay";
type PaymentMethod = "pix" | "cartao_credito" | "cartao_debito" | "dinheiro" | "boleto";

const METHODS: { id: PaymentMethod; label: string; icon: OiIconName; desc: string }[] = [
  { id: "pix", label: "PIX", icon: "qr", desc: "QR Code instantâneo" },
  { id: "cartao_credito", label: "Crédito", icon: "dollar", desc: "1× a 12×" },
  { id: "cartao_debito", label: "Débito", icon: "dollar", desc: "Visa/Master" },
  { id: "dinheiro", label: "Dinheiro", icon: "dollar", desc: "Sem comprovante" },
  { id: "boleto", label: "Boleto", icon: "file", desc: "Cobrar em 7d" },
];

export default function VendaRapidaScreen() {
  const router = useRouter();
  const { palette } = useOiTheme();
  const { addToast } = useERP();
  const produtosQ = useProdutos();
  const createPedido = useCreatePedido();

  const [cart, setCart] = useState<CartItem[]>([]);
  const [sheet, setSheet] = useState<Sheet>(null);
  const [method, setMethod] = useState<PaymentMethod>("pix");
  const [done, setDone] = useState<{ total: number; qtd: number; method: PaymentMethod } | null>(
    null,
  );
  const [receiptNum] = useState(() => Math.floor(Math.random() * 9000 + 1000));

  const total = useMemo(
    () => cart.reduce((s, i) => s + i.produto.preco * i.qty, 0),
    [cart],
  );
  const qtdTotal = useMemo(
    () => cart.reduce((s, i) => s + i.qty, 0),
    [cart],
  );

  const addProduto = (p: Produto) => {
    setCart((c) => {
      const idx = c.findIndex((i) => i.produto.id === p.id);
      if (idx >= 0) {
        const next = [...c];
        next[idx] = { ...next[idx], qty: next[idx].qty + 1 };
        return next;
      }
      return [...c, { produto: p, qty: 1 }];
    });
    setSheet(null);
  };

  const changeQty = (id: string, delta: number) => {
    setCart((c) =>
      c
        .map((i) =>
          i.produto.id === id ? { ...i, qty: Math.max(0, i.qty + delta) } : i,
        )
        .filter((i) => i.qty > 0),
    );
  };

  const handleConfirmPayment = async () => {
    if (cart.length === 0) return;
    const produto = cart
      .map((i) => `${i.qty}× ${i.produto.nome}`)
      .join(" + ");
    try {
      await createPedido.mutateAsync({
        cliente: "Venda balcão",
        produto,
        valor: total,
        data: new Date().toISOString(),
        tipo: "Venda Rápida",
      });
      addToast("sucesso", "Venda registrada!");
    } catch (err) {
      // Mutation queue (F3-04) cuida do offline; aqui só avisa.
      addToast(
        "info",
        err instanceof Error
          ? `Salvo localmente: ${err.message}`
          : "Salvo localmente (offline)",
      );
    }
    setDone({ total, qtd: qtdTotal, method });
    setSheet(null);
  };

  const resetForNewSale = () => {
    setCart([]);
    setDone(null);
    setMethod("pix");
  };

  // ─── Done screen ─────────────────────────────────────────────────────────
  if (done) {
    return (
      <OiScreen edges={["top", "bottom"]}>
        <Stack.Screen options={{ headerShown: false }} />
        <OiDetailHeader title="Venda concluída" onBack={() => router.back()} />
        <ScrollView contentContainerStyle={{ paddingBottom: 24 }}>
          <OiSection>
            <View style={{ alignItems: "center", paddingTop: 24 }}>
              <View
                style={{
                  width: 72,
                  height: 72,
                  borderRadius: 20,
                  backgroundColor: hexAlpha(palette.ok, 0.22),
                  alignItems: "center",
                  justifyContent: "center",
                  marginBottom: 14,
                }}
              >
                <OiIcon name="check-circle" size={36} color={palette.ok} />
              </View>
              <Text
                style={{
                  fontFamily: fonts.sansSemibold,
                  fontSize: 22,
                  letterSpacing: -0.4,
                  color: palette.text,
                }}
              >
                Venda registrada!
              </Text>
              <OiMoney
                value={done.total}
                size={34}
                weight="semibold"
                style={{ marginTop: 8 }}
              />
              <Text
                style={{
                  fontSize: 12,
                  color: palette.textMute,
                  marginTop: 2,
                }}
              >
                {done.qtd} {done.qtd === 1 ? "item" : "itens"} ·{" "}
                {METHODS.find((m) => m.id === done.method)?.label}
              </Text>
            </View>
          </OiSection>

          <OiSection>
            <OiCard variant="pad" style={{ gap: 10 }}>
              <Text
                style={{
                  fontFamily: fonts.mono,
                  fontSize: 12,
                  color: palette.textMute,
                  textAlign: "center",
                }}
              >
                Recibo #V-{receiptNum} ·{" "}
                {new Date().toLocaleString("pt-BR", {
                  day: "2-digit",
                  month: "2-digit",
                  hour: "2-digit",
                  minute: "2-digit",
                })}
              </Text>
              <OiBtnRow>
                <OiBtn label="Imprimir" leftIcon="printer" />
                <OiBtn label="Enviar" leftIcon="whatsapp" />
              </OiBtnRow>
            </OiCard>
          </OiSection>

          <OiSection>
            <OiBtn
              label="Nova venda"
              variant="primary"
              leftIcon="plus"
              block
              onPress={resetForNewSale}
            />
            <View style={{ height: 8 }} />
            <OiBtn label="Fechar" variant="ghost" block onPress={() => router.back()} />
          </OiSection>
        </ScrollView>
      </OiScreen>
    );
  }

  // ─── Main screen ─────────────────────────────────────────────────────────
  const eyebrow =
    cart.length === 0
      ? "PDV móvel · scanner"
      : `${qtdTotal} ${qtdTotal === 1 ? "item" : "itens"} · R$ ${total.toLocaleString("pt-BR")}`;

  return (
    <OiScreen edges={["top", "bottom"]}>
      <Stack.Screen options={{ headerShown: false }} />
      <OiDetailHeader
        title="Venda rápida"
        eyebrow={eyebrow}
        onBack={() => router.back()}
        right={
          cart.length > 0 ? (
            <Pressable
              hitSlop={6}
              onPress={() => setCart([])}
              style={{
                width: 38,
                height: 38,
                alignItems: "center",
                justifyContent: "center",
                borderRadius: radius.sm,
              }}
            >
              <OiIcon name="trash" size={20} color={palette.textDim} />
            </Pressable>
          ) : null
        }
      />

      {cart.length === 0 ? (
        <View style={{ flex: 1 }}>
          <ScannerView />
          <View
            style={{
              padding: 16,
              borderTopColor: palette.border,
              borderTopWidth: 1,
              backgroundColor: palette.surface,
              gap: 8,
            }}
          >
            <OiBtn
              label="Buscar produto"
              variant="primary"
              leftIcon="search"
              block
              onPress={() => setSheet("scan")}
            />
            <Text
              style={{
                fontSize: 11,
                color: palette.textMute,
                textAlign: "center",
                fontFamily: fonts.sans,
              }}
            >
              Toque no produto na lista de busca para adicionar
            </Text>
          </View>
        </View>
      ) : (
        <>
          <ScrollView
            contentContainerStyle={{ paddingBottom: 96 }}
            showsVerticalScrollIndicator={false}
          >
            <OiSection title={`Carrinho · ${cart.length} produto${cart.length === 1 ? "" : "s"}`}>
              <OiCard style={{ padding: 0, gap: 0 }}>
                {cart.map((i, idx) => (
                  <View
                    key={i.produto.id}
                    style={{
                      flexDirection: "row",
                      alignItems: "center",
                      gap: 10,
                      paddingHorizontal: 12,
                      paddingVertical: 10,
                      borderBottomColor: palette.border2,
                      borderBottomWidth: idx < cart.length - 1 ? 1 : 0,
                    }}
                  >
                    <View
                      style={{
                        width: 38,
                        height: 38,
                        borderRadius: 8,
                        backgroundColor: palette.bg2,
                        borderColor: palette.border,
                        borderWidth: 1,
                        alignItems: "center",
                        justifyContent: "center",
                      }}
                    >
                      <OiIcon name="box" size={18} color={palette.textMute} />
                    </View>
                    <View style={{ flex: 1, minWidth: 0 }}>
                      <Text
                        style={{
                          fontFamily: fonts.sansSemibold,
                          fontSize: 13,
                          color: palette.text,
                        }}
                        numberOfLines={1}
                      >
                        {i.produto.nome}
                      </Text>
                      <Text
                        style={{
                          fontFamily: fonts.mono,
                          fontSize: 11.5,
                          color: palette.textMute,
                          marginTop: 1,
                        }}
                      >
                        R$ {i.produto.preco.toLocaleString("pt-BR")}
                      </Text>
                    </View>
                    <QtyStepper
                      qty={i.qty}
                      onMinus={() => changeQty(i.produto.id, -1)}
                      onPlus={() => changeQty(i.produto.id, +1)}
                    />
                    <OiMoney
                      value={i.produto.preco * i.qty}
                      size={13.5}
                      weight="semibold"
                      style={{ minWidth: 80, textAlign: "right" }}
                    />
                  </View>
                ))}
              </OiCard>
            </OiSection>

            <OiSection>
              <OiCard variant="pad" style={{ gap: 8 }}>
                <View style={{ flexDirection: "row" }}>
                  <Text
                    style={{ fontSize: 13, color: palette.textDim, fontFamily: fonts.sans }}
                  >
                    Subtotal
                  </Text>
                  <View style={{ flex: 1 }} />
                  <OiMoney value={total} size={13} />
                </View>
                <View
                  style={{
                    height: 1,
                    backgroundColor: palette.border2,
                    marginVertical: 2,
                  }}
                />
                <View style={{ flexDirection: "row", alignItems: "baseline" }}>
                  <Text
                    style={{
                      fontSize: 13,
                      fontFamily: fonts.sansSemibold,
                      color: palette.text,
                    }}
                  >
                    Total
                  </Text>
                  <View style={{ flex: 1 }} />
                  <OiMoney value={total} size={22} weight="semibold" />
                </View>
              </OiCard>
            </OiSection>

            <OiSection>
              <OiBtn
                label="Adicionar mais produtos"
                leftIcon="plus"
                block
                onPress={() => setSheet("scan")}
              />
            </OiSection>
          </ScrollView>

          <View
            style={{
              paddingHorizontal: 12,
              paddingVertical: 10,
              backgroundColor: palette.surface,
              borderTopColor: palette.border,
              borderTopWidth: 1,
            }}
          >
            <OiBtn
              label={`Cobrar R$ ${total.toLocaleString("pt-BR")}`}
              variant="primary"
              leftIcon="dollar"
              block
              onPress={() => setSheet("pay")}
            />
          </View>
        </>
      )}

      {/* Search sheet */}
      <OiSheet
        visible={sheet === "scan"}
        onClose={() => setSheet(null)}
        title="Buscar produto"
        noScroll
      >
        <SearchSheetBody
          produtos={produtosQ.data ?? []}
          onPick={addProduto}
        />
      </OiSheet>

      {/* Payment sheet */}
      <OiSheet
        visible={sheet === "pay"}
        onClose={() => setSheet(null)}
        title="Pagamento"
      >
        <View style={{ alignItems: "center", paddingVertical: 8 }}>
          <Text
            style={{
              fontFamily: fonts.sansBold,
              fontSize: 10.5,
              letterSpacing: 1,
              textTransform: "uppercase",
              color: palette.textMute,
            }}
          >
            Total a cobrar
          </Text>
          <OiMoney value={total} size={32} weight="semibold" style={{ marginTop: 4 }} />
        </View>
        <View style={{ gap: 6, marginTop: 8 }}>
          {METHODS.map((m) => {
            const active = method === m.id;
            return (
              <Pressable
                key={m.id}
                onPress={() => setMethod(m.id)}
                style={{
                  flexDirection: "row",
                  alignItems: "center",
                  gap: 12,
                  padding: 12,
                  backgroundColor: active ? palette.accentSoft : palette.surface,
                  borderColor: active ? palette.accent : palette.border,
                  borderWidth: 1,
                  borderRadius: radius.md,
                }}
              >
                <View
                  style={{
                    width: 36,
                    height: 36,
                    borderRadius: 8,
                    backgroundColor: active ? palette.accent : palette.bg2,
                    alignItems: "center",
                    justifyContent: "center",
                  }}
                >
                  <OiIcon
                    name={m.icon}
                    size={18}
                    color={active ? "#fff" : palette.textDim}
                  />
                </View>
                <View style={{ flex: 1 }}>
                  <Text
                    style={{
                      fontFamily: fonts.sansSemibold,
                      fontSize: 14,
                      color: palette.text,
                    }}
                  >
                    {m.label}
                  </Text>
                  <Text
                    style={{
                      fontFamily: fonts.sans,
                      fontSize: 11.5,
                      color: palette.textMute,
                      marginTop: 1,
                    }}
                  >
                    {m.desc}
                  </Text>
                </View>
                <View
                  style={{
                    width: 18,
                    height: 18,
                    borderRadius: 9,
                    borderWidth: 2,
                    borderColor: active ? palette.accent : palette.border,
                    backgroundColor: active ? palette.accent : "transparent",
                    alignItems: "center",
                    justifyContent: "center",
                  }}
                >
                  {active ? (
                    <View
                      style={{
                        width: 8,
                        height: 8,
                        borderRadius: 4,
                        backgroundColor: "#fff",
                      }}
                    />
                  ) : null}
                </View>
              </Pressable>
            );
          })}
        </View>
        <View style={{ height: 12 }} />
        <OiBtn
          label="Confirmar pagamento"
          variant="primary"
          leftIcon="check"
          block
          loading={createPedido.isPending}
          onPress={handleConfirmPayment}
        />
      </OiSheet>
    </OiScreen>
  );
}

// ─── ScannerView (mock animado) ─────────────────────────────────────────────

function ScannerView() {
  const { palette } = useOiTheme();
  const viewfinderHeight = 200;
  return (
    <View
      style={{
        flex: 1,
        backgroundColor: "#111315",
        alignItems: "center",
        justifyContent: "center",
        padding: 24,
      }}
    >
      <View style={{ alignItems: "center", marginBottom: 16 }}>
        <OiIcon name="scan" size={26} color={palette.accent} />
        <Text
          style={{
            color: "rgba(255,255,255,.85)",
            fontSize: 13,
            fontFamily: fonts.sansMedium,
            marginTop: 6,
            textAlign: "center",
          }}
        >
          Aponte a câmera para o código de barras
        </Text>
      </View>
      <View
        style={{
          width: "80%",
          height: viewfinderHeight,
          borderRadius: 8,
          overflow: "hidden",
          backgroundColor: "rgba(255,255,255,.03)",
        }}
      >
        {/* corners */}
        {[
          { top: 0, left: 0, borders: { borderTopWidth: 3, borderLeftWidth: 3 } },
          { top: 0, right: 0, borders: { borderTopWidth: 3, borderRightWidth: 3 } },
          { bottom: 0, left: 0, borders: { borderBottomWidth: 3, borderLeftWidth: 3 } },
          { bottom: 0, right: 0, borders: { borderBottomWidth: 3, borderRightWidth: 3 } },
        ].map((c, i) => (
          <View
            key={i}
            style={{
              position: "absolute",
              width: 36,
              height: 36,
              top: c.top,
              left: c.left,
              right: c.right,
              bottom: c.bottom,
              borderColor: palette.accent,
              ...c.borders,
            }}
          />
        ))}
        <OiScanline height={viewfinderHeight} />
      </View>
      <Text
        style={{
          color: "rgba(255,255,255,.5)",
          fontSize: 11,
          marginTop: 16,
          fontFamily: fonts.sans,
        }}
      >
        Scanner câmera não implementado — use Buscar produto
      </Text>
    </View>
  );
}

// ─── Sheets internas ────────────────────────────────────────────────────────

function SearchSheetBody({
  produtos,
  onPick,
}: {
  produtos: Produto[];
  onPick: (p: Produto) => void;
}) {
  const { palette } = useOiTheme();
  const [q, setQ] = useState("");
  const filtered = useMemo(() => {
    const term = q.trim().toLowerCase();
    if (!term) return produtos;
    return produtos.filter((p) =>
      `${p.nome} ${p.categoria}`.toLowerCase().includes(term),
    );
  }, [produtos, q]);

  return (
    <View style={{ paddingHorizontal: 16, paddingBottom: 16 }}>
      <View
        style={{
          flexDirection: "row",
          alignItems: "center",
          gap: 8,
          backgroundColor: palette.bg2,
          borderColor: palette.border,
          borderWidth: 1,
          borderRadius: radius.md,
          paddingHorizontal: 12,
          paddingVertical: 8,
          marginBottom: 8,
        }}
      >
        <OiIcon name="search" size={18} color={palette.textMute} />
        <TextInput
          value={q}
          onChangeText={setQ}
          placeholder="Nome ou categoria"
          placeholderTextColor={palette.textMute}
          autoFocus
          style={{
            flex: 1,
            color: palette.text,
            fontSize: 14,
            fontFamily: fonts.sans,
            padding: 0,
          }}
        />
      </View>
      <ScrollView style={{ maxHeight: 380 }} keyboardShouldPersistTaps="handled">
        {filtered.length === 0 ? (
          <Text
            style={{
              textAlign: "center",
              paddingVertical: 24,
              color: palette.textMute,
              fontFamily: fonts.sans,
              fontSize: 13,
            }}
          >
            Nenhum produto encontrado.
          </Text>
        ) : (
          filtered.map((p, i) => (
            <Pressable
              key={p.id}
              onPress={() => onPick(p)}
              style={{
                flexDirection: "row",
                alignItems: "center",
                gap: 10,
                paddingVertical: 10,
                borderBottomColor: palette.border2,
                borderBottomWidth: i < filtered.length - 1 ? 1 : 0,
              }}
            >
              <View
                style={{
                  width: 36,
                  height: 36,
                  borderRadius: 8,
                  backgroundColor: palette.bg2,
                  borderColor: palette.border,
                  borderWidth: 1,
                  alignItems: "center",
                  justifyContent: "center",
                }}
              >
                <OiIcon name="box" size={16} color={palette.textMute} />
              </View>
              <View style={{ flex: 1, minWidth: 0 }}>
                <Text
                  style={{
                    fontFamily: fonts.sansSemibold,
                    fontSize: 13,
                    color: palette.text,
                  }}
                  numberOfLines={1}
                >
                  {p.nome}
                </Text>
                <Text
                  style={{
                    fontFamily: fonts.mono,
                    fontSize: 11,
                    color: palette.textMute,
                    marginTop: 1,
                  }}
                >
                  {p.categoria}
                </Text>
              </View>
              <OiMoney value={p.preco} size={13} weight="semibold" />
            </Pressable>
          ))
        )}
      </ScrollView>
    </View>
  );
}

function QtyStepper({
  qty,
  onMinus,
  onPlus,
}: {
  qty: number;
  onMinus: () => void;
  onPlus: () => void;
}) {
  const { palette } = useOiTheme();
  return (
    <View style={{ flexDirection: "row", alignItems: "center", gap: 4 }}>
      <Pressable
        onPress={onMinus}
        style={{
          width: 28,
          height: 28,
          borderRadius: 6,
          borderWidth: 1,
          borderColor: palette.border,
          backgroundColor: palette.bg2,
          alignItems: "center",
          justifyContent: "center",
        }}
      >
        <OiIcon name="x" size={14} color={palette.text} />
      </Pressable>
      <Text
        style={{
          minWidth: 24,
          textAlign: "center",
          fontFamily: fonts.monoSemibold,
          fontSize: 13,
          color: palette.text,
        }}
      >
        {qty}
      </Text>
      <Pressable
        onPress={onPlus}
        style={{
          width: 28,
          height: 28,
          borderRadius: 6,
          borderWidth: 1,
          borderColor: palette.accent,
          backgroundColor: palette.accentSoft,
          alignItems: "center",
          justifyContent: "center",
        }}
      >
        <OiIcon name="plus" size={14} color={palette.accent} />
      </Pressable>
    </View>
  );
}
