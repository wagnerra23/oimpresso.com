import MaterialIcons from "@expo/vector-icons/MaterialIcons";
import type { ComponentProps } from "react";
import { type StyleProp, type TextStyle } from "react-native";

import { useOiTheme } from "@/lib/oi-theme-context";

/**
 * Oimpresso icon name space — mirrors `Ic.*` from ref/design/icons.jsx.
 *
 * The design uses 24px phosphor-ish stroke icons (stroke 1.6). React Native
 * doesn't ship that family, so we map each design name to the closest
 * MaterialIcons glyph. The visual weight is close enough that screens read
 * correctly; replace with phosphor-react-native later if needed.
 */
export type OiIconName =
  | "home"
  | "inbox"
  | "box"
  | "tag"
  | "dollar"
  | "dots"
  | "dots-v"
  | "bell"
  | "user"
  | "search"
  | "filter"
  | "plus"
  | "check"
  | "x"
  | "chev-r"
  | "chev-l"
  | "chev-d"
  | "chev-u"
  | "clock"
  | "calendar"
  | "flame"
  | "zap"
  | "phone"
  | "whatsapp"
  | "mail"
  | "location"
  | "truck"
  | "printer"
  | "file"
  | "image"
  | "logout"
  | "settings"
  | "shield"
  | "scan"
  | "send"
  | "paperclip"
  | "trash"
  | "edit"
  | "check-circle"
  | "alert"
  | "arrow-up"
  | "arrow-down"
  | "eye"
  | "qr"
  | "more"
  | "refresh"
  | "layers"
  | "chart"
  | "trending-up"
  | "trending-down"
  | "paint"
  | "sun"
  | "moon"
  | "wrench"
  | "logo";

type MdName = ComponentProps<typeof MaterialIcons>["name"];

const MAP: Record<OiIconName, MdName> = {
  home: "home",
  inbox: "inbox",
  box: "inventory-2",
  tag: "local-offer",
  dollar: "attach-money",
  dots: "more-horiz",
  "dots-v": "more-vert",
  bell: "notifications-none",
  user: "person-outline",
  search: "search",
  filter: "tune",
  plus: "add",
  check: "check",
  x: "close",
  "chev-r": "chevron-right",
  "chev-l": "chevron-left",
  "chev-d": "expand-more",
  "chev-u": "expand-less",
  clock: "schedule",
  calendar: "calendar-today",
  flame: "local-fire-department",
  zap: "bolt",
  phone: "call",
  whatsapp: "chat",
  mail: "mail-outline",
  location: "place",
  truck: "local-shipping",
  printer: "print",
  file: "description",
  image: "image",
  logout: "logout",
  settings: "settings",
  shield: "shield",
  scan: "qr-code-scanner",
  send: "send",
  paperclip: "attach-file",
  trash: "delete-outline",
  edit: "edit",
  "check-circle": "check-circle-outline",
  alert: "error-outline",
  "arrow-up": "arrow-upward",
  "arrow-down": "arrow-downward",
  eye: "visibility",
  qr: "qr-code-2",
  more: "more-horiz",
  refresh: "refresh",
  layers: "layers",
  chart: "bar-chart",
  "trending-up": "trending-up",
  "trending-down": "trending-down",
  paint: "format-paint",
  sun: "wb-sunny",
  moon: "nightlight-round",
  wrench: "handyman",
  logo: "apps",
};

export type OiIconProps = {
  name: OiIconName;
  size?: number;
  color?: string;
  style?: StyleProp<TextStyle>;
};

export function OiIcon({
  name,
  size = 22,
  color,
  style,
}: OiIconProps) {
  const { palette } = useOiTheme();
  return (
    <MaterialIcons
      name={MAP[name]}
      size={size}
      color={color ?? palette.text}
      style={style}
    />
  );
}

export default OiIcon;
