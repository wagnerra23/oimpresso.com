/**
 * Primitivos de layout — a camada que falta entre tokens DS v6 e telas (ADR 0253 · F3).
 *
 * Regra: layout é COMPOSIÇÃO destes primitivos, nunca `<div className="flex gap-4">`
 * solto nem `.css` bespoke. Props = token, sempre (enforcement no nível do tipo via CVA).
 *
 * @see memory/decisions/0253-primitivos-layout.md
 * @see memory/requisitos/_DesignSystem/MANUAL-CSS-JS.md §2.1
 */
export { Box, type BoxProps } from "./box"
export { Stack, type StackProps } from "./stack"
export { Inline, type InlineProps } from "./inline"
export { Grid, type GridProps } from "./grid"
export { Container, type ContainerProps } from "./container"
export { Text, type TextProps } from "./text"
