import type { ReactNode } from "react";
import {
  Modal,
  Pressable,
  ScrollView,
  Text,
  View,
  type ModalProps,
} from "react-native";

import { fonts } from "@/lib/oi-theme";
import { useOiTheme } from "@/lib/oi-theme-context";
import { OiIcon } from "./OiIcon";

export type OiSheetProps = {
  visible: boolean;
  onClose: () => void;
  title?: string;
  children: ReactNode;
  /** Disable inner scroll if the body manages its own (FlatList, etc). */
  noScroll?: boolean;
  animationType?: ModalProps["animationType"];
};

/**
 * Bottom sheet — backdrop + rounded panel anchored to the bottom.
 *
 * No spring animation in Phase 1; the iOS-native `slide` Modal transition is
 * good enough for a foundation. A future pass can swap to `@gorhom/bottom-sheet`
 * with gesture pull-to-dismiss.
 */
export function OiSheet({
  visible,
  onClose,
  title,
  children,
  noScroll,
  animationType = "slide",
}: OiSheetProps) {
  const { palette } = useOiTheme();
  const body = noScroll ? (
    <View style={{ paddingHorizontal: 16, paddingVertical: 8 }}>{children}</View>
  ) : (
    <ScrollView
      contentContainerStyle={{ paddingHorizontal: 16, paddingVertical: 8 }}
    >
      {children}
    </ScrollView>
  );

  return (
    <Modal
      visible={visible}
      animationType={animationType}
      transparent
      onRequestClose={onClose}
    >
      <Pressable
        onPress={onClose}
        style={{ flex: 1, backgroundColor: "rgba(0,0,0,0.5)" }}
      />
      <View
        style={{
          position: "absolute",
          left: 0,
          right: 0,
          bottom: 0,
          maxHeight: "72%",
          backgroundColor: palette.surface,
          borderTopLeftRadius: 20,
          borderTopRightRadius: 20,
          paddingTop: 8,
          paddingBottom: 20,
        }}
      >
        <View
          style={{
            width: 36,
            height: 4,
            borderRadius: 99,
            backgroundColor: palette.border,
            alignSelf: "center",
            marginTop: 6,
            marginBottom: 12,
          }}
        />
        {title ? (
          <View
            style={{
              flexDirection: "row",
              alignItems: "center",
              paddingHorizontal: 16,
              paddingBottom: 12,
              borderBottomColor: palette.border2,
              borderBottomWidth: 1,
            }}
          >
            <Text
              style={{
                flex: 1,
                color: palette.text,
                fontFamily: fonts.sansSemibold,
                fontSize: 15,
              }}
            >
              {title}
            </Text>
            <Pressable onPress={onClose} hitSlop={6}>
              <OiIcon name="x" size={22} color={palette.textDim} />
            </Pressable>
          </View>
        ) : null}
        {body}
      </View>
    </Modal>
  );
}

export default OiSheet;
