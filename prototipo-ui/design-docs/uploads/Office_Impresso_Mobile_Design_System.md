
# Office Impresso Mobile 2026 — Design System (Claude Code / Claude Design Ready)

## Product Vision
Create a premium mobile experience that feels like a natural evolution of the Office Impresso ERP.
The application must preserve the brand identity while adopting modern mobile UX patterns.

---

# Design Principles

1. Dark-first and Light-first parity
2. Fast information scanning
3. Production-oriented workflows
4. ERP-grade data density
5. Consistent visual language
6. Strong Office Impresso branding
7. Minimal visual noise
8. Mobile-native interactions

---

# Brand Identity

Core inspiration:
- Office Impresso desktop ERP
- Geometric cube logo
- Purple / wine color family
- Production and printing workflows

Do not place the logo inside the navigation bar.

Use the logo only:
- Splash screen
- Login
- Profile area
- Empty states
- About screen

---

# Color System

## Dark Theme

Background Primary: #15131B
Background Secondary: #1E1A27
Surface Card: #252030

Primary Brand: #7A0B7E
Primary Hover: #8C1A93
Primary Bright: #C85BFF

Border: #343042

Text Primary: #F2F2F2
Text Secondary: #A6A6B5
Text Muted: #727280

Success: #2BB673
Warning: #F5A623
Danger: #E75A5A
Info: #4C8DFF

## Light Theme

Background Primary: #F7F7FA
Background Secondary: #FFFFFF
Surface Card: #FFFFFF

Primary Brand: #7A0B7E
Primary Hover: #8C1A93
Primary Bright: #B43BE8

Border: #E2E2EA

Text Primary: #222222
Text Secondary: #666666
Text Muted: #888888

Success: #2BB673
Warning: #F5A623
Danger: #E75A5A
Info: #4C8DFF

---

# Typography

Font Family:
- Inter

Heading XL: 32 / Bold
Heading L: 24 / Bold
Heading M: 20 / SemiBold
Heading S: 18 / SemiBold

Body L: 16 / Regular
Body M: 14 / Regular
Body S: 12 / Regular

Caption: 11 / Medium

---

# Icon System

Library:
- Lucide Icons

Modules:

Dashboard -> LayoutDashboard
Orders -> ClipboardList
Production -> Factory
Finance -> Wallet
CRM -> Users
Inventory -> Package
Reports -> BarChart3
Settings -> Settings
Profile -> UserCircle

Quick Actions:

New Order -> Plus
Receive Payment -> Banknote
PIX -> QrCode
Schedule -> Calendar
Search -> Search

---

# Layout Grid

Mobile Width:
- 360–430 px

Spacing Scale:

4
8
12
16
20
24
32
40

Card Radius:
16px

Button Radius:
14px

Floating Button Radius:
20px

---

# Dashboard Structure

Header
Greeting
Notifications

Main KPI Card

Metrics Grid:
- Orders Today
- In Production
- Receivables
- Deliveries

Quick Actions

Tasks

Recent Orders

Bottom Navigation

---

# Dashboard KPI Card

Use gradient:

Start: #7A0B7E
End: #9A2BCB

Content:

Revenue Today
Revenue Target
Progress Bar
Growth Indicator

Add subtle geometric cube watermark.

Opacity:
3–5%

---

# Orders Screen

Search Bar

Filter Chips:

All
Active
Urgent
Completed

Order Card Structure:

Order Number
Customer Name
Product
Status
Value
Deadline

Status Colors:

Quote -> Neutral Gray
Approval -> Warning
Artwork -> Info
Production -> Primary Purple
Finishing -> Accent Purple
Delivery -> Success

---

# Production Timeline

Visual Component:

Quote
Approval
Artwork
Production
Finishing
Delivery

Current step highlighted.

---

# Navigation

Bottom Navigation

Items:

Home
Production
Orders
Finance
More

Active State:

Purple background pill.
White icon.
Soft shadow.

Inactive State:

Muted text and icon.

---

# Visual Effects

Allowed:

Soft shadows
Glass effect (light usage)
Gradient surfaces
Micro animations

Avoid:

Heavy blur
Neon effects
Excessive glow
Large gradients everywhere

---

# Background Pattern

Use geometric lines inspired by Office Impresso cube.

Opacity:
2–5%

Never compete with content.

---

# Accessibility

Minimum contrast WCAG AA.

Touch targets:
44x44 minimum.

Never rely on color alone for status.

---

# AI Assistant Component

Optional home widget.

Examples:

"3 orders delayed"
"2 invoices due today"
"Revenue increased 18%"

Use assistant card style.
Never use chat UI as primary dashboard.

---

# Component Rules

Cards:
Elevation 1

Dialogs:
Elevation 3

Floating Action Button:
Single primary action only

Tables:
Avoid desktop-style grids.
Use responsive cards.

---

# Expected Brand Perception

Modern
Professional
Premium
Industrial
Reliable

Not:
Generic SaaS
Crypto app
Gaming interface
Neon cyberpunk
