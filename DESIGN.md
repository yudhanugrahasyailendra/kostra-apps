---
name: Modern Boarding House Property OS
colors:
  surface: '#f8f9ff'
  surface-dim: '#cbdbf5'
  surface-bright: '#f8f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff4ff'
  surface-container: '#e5eeff'
  surface-container-high: '#dce9ff'
  surface-container-highest: '#d3e4fe'
  on-surface: '#0b1c30'
  on-surface-variant: '#3e4944'
  inverse-surface: '#213145'
  inverse-on-surface: '#eaf1ff'
  outline: '#6e7a74'
  outline-variant: '#bdc9c2'
  surface-tint: '#006c53'
  primary: '#005f49'
  on-primary: '#ffffff'
  primary-container: '#0d7a5f'
  on-primary-container: '#a8ffdf'
  inverse-primary: '#7cd8b8'
  secondary: '#545f73'
  on-secondary: '#ffffff'
  secondary-container: '#d5e0f8'
  on-secondary-container: '#586377'
  tertiary: '#814400'
  on-tertiary: '#ffffff'
  tertiary-container: '#a45800'
  on-tertiary-container: '#ffeadd'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#98f4d3'
  primary-fixed-dim: '#7cd8b8'
  on-primary-fixed: '#002117'
  on-primary-fixed-variant: '#00513e'
  secondary-fixed: '#d8e3fb'
  secondary-fixed-dim: '#bcc7de'
  on-secondary-fixed: '#111c2d'
  on-secondary-fixed-variant: '#3c475a'
  tertiary-fixed: '#ffdcc3'
  tertiary-fixed-dim: '#ffb77d'
  on-tertiary-fixed: '#2f1500'
  on-tertiary-fixed-variant: '#6e3900'
  background: '#f8f9ff'
  on-background: '#0b1c30'
  surface-variant: '#d3e4fe'
typography:
  headline-xl:
    fontFamily: Plus Jakarta Sans
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
  headline-xl-mobile:
    fontFamily: Plus Jakarta Sans
    fontSize: 26px
    fontWeight: '700'
    lineHeight: 34px
  headline-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
  headline-lg-mobile:
    fontFamily: Plus Jakarta Sans
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  headline-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 18px
    fontWeight: '600'
    lineHeight: 24px
  body-lg:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  body-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '400'
    lineHeight: 16px
  label-lg:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '600'
    lineHeight: 20px
  label-md:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
  label-sm:
    fontFamily: Inter
    fontSize: 10px
    fontWeight: '700'
    lineHeight: 14px
  currency-stat:
    fontFamily: Plus Jakarta Sans
    fontSize: 28px
    fontWeight: '700'
    lineHeight: 36px
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  gutter: 1rem
  gutter-desktop: 1.5rem
  margin: 1rem
  margin-desktop: 2rem
  space-xs: 0.25rem
  space-sm: 0.5rem
  space-md: 1rem
  space-lg: 1.5rem
  space-xl: 2rem
---

## Brand & Style

This design system drives a modern property management platform for Indonesian boarding houses (*manajemen rumah kos dan kontrakan*). It serves landlords (*juragan kos*), on-site managers (*pengelola*), and tenants (*penghuni*).

The interface conveys **financial precision, absolute operational reliability, and warm hospitality**. It transforms chaotic WhatsApp-based rent collections, manual meter readings, and paper receipts into an automated, transparent dashboard. The visual style balances **Modern Corporate Precision with airy hospitality ergonomics**:

- **Crisp and High-Information Density**: Maximizes visible rooms, payment ledgers, and operational tasks without overwhelming the user.
- **Clear Financial Legibility**: Tabular figures, distinct transactional state badges, and large currency counters formatted specifically for Indonesian Rupiah (`Rp`).
- **Tactile Trust**: Crisp hairline borders, soft ambient drop shadows, and clean surface separation to project institutional reliability.
- **Cultural Familiarity**: Microcopy and operational terms rooted in Indonesian property terminology (*Kamar Terisi, Kosong, Menunggak, Verifikasi Transfer, Deposit, Tagihan Listrik/Air*).

## Colors

The palette uses high-contrast functional chromatic signaling combined with deep corporate fundamentals:

- **Primary (`#0D7A5F` - Emerald Deep)**: The anchor of solvency, reliability, and growth. Used for primary actions, active navigation states, verified statuses, and primary financial summaries.
- **Secondary (`#1E293B` - Slate Navy)**: The grounding corporate structure. Applied to high-emphasis typography, sidebar containers, top-level headings, and structural data frames.
- **Tertiary (`#D97706` - Warm Amber)**: Reserved strictly for pending actions, invoices due within 3 days, and manual proof-of-payment checks (*Verifikasi Bukti Bayar*).
- **Neutral (`#64748B` - Cool Slate)**: Handles secondary metadata, table borders, inactive icons, and neutral states.
- **Semantic Status Spectrum**:
  - *Terisi (Occupied)*: Soft Emerald Tint (`#ECFDF5`) with Deep Emerald Text (`#065F46`).
  - *Kosong (Available / Vacant)*: Neutral Slate Tint (`#F1F5F9`) with Slate Text (`#334155`).
  - *Menunggak (Overdue / In Arrears)*: Crimson Red Tint (`#FEF2F2`) with Ruby Text (`#991B1B`).
  - *Verifikasi Transfer (Payment Verification Needed)*: Amber Tint (`#FFFBEB`) with Deep Amber Text (`#92400E`).
  - *Dalam Perbaikan (Maintenance)*: Indigo Tint (`#EEF2FF`) with Deep Indigo Text (`#3730A3`).

## Typography

Typography balances approachable clarity in display titles with dense, ultra-legible numeric alignment across lists and tables:

- **Display & Headings (Plus Jakarta Sans)**: Gives structural screens a friendly, contemporary character with geometric precision. Use `headline-xl` and `headline-lg` for dashboard page headers, revenue total aggregates, and property names.
- **Interface & Data (Inter)**: The operational workhorse. Utilizes tabular figures (`font-variant-numeric: tabular-nums`) for currency amounts (*e.g., Rp 1.850.000*), payment reference codes, room numbers (*Kamar 102*), and dates.
- **All-caps Overline Micro-labels**: `label-sm` with `letter-spacing: 0.05em` is explicitly reserved for unit status tags, utility meters (*KWH*, *M³*), and table column headers.

## Layout & Spacing

The layout is built around a responsive dashboard shell designed for dense property administration:

- **Grid Architecture**: 
  - **Desktop (1024px+)**: Collapsible or permanent 260px Slate Navy sidebar + fluid content canvas structured around a 12-column grid with `1.5rem` gutters.
  - **Tablet (768px - 1023px)**: Compact sidebar or top bar navigation, 8-column layout with `1rem` gutters.
  - **Mobile (< 768px)**: 4-column layout with bottom-fixed operational navigation, full-width room lists, and stacked KPI carousels.
- **Rhythm Rules**:
  - Form field groupings and metric cards utilize `space-md` internally.
  - Room grid layouts use `space-sm` between micro-status tags and room numbers, and `space-md` between individual room cells.
  - Canvas page blocks maintain `space-xl` separation.

## Elevation & Depth

This system avoids heavy neumorphism or deep fuzzy shadows, relying instead on clean surface segregation, subtle border definition, and light ambient tints:

- **Base Surface (`#F8FAFC`)**: Canvas backdrop provides soft contrast beneath pure white cards.
- **Surface Layer 1 (`#FFFFFF`)**: Metric cards, room grid cards, data table wrappers, and flyout sheets. Bound by an ultra-crisp hairline border: `1px solid #E2E8F0`.
- **Card Ambient Elevation**: `0px 1px 3px rgba(15, 23, 42, 0.05), 0px 1px 2px rgba(15, 23, 42, 0.03)`.
- **Overlay Elevation (Modals, Room Detail Drawers, Proof of Transfer Viewer)**: `0px 10px 25px -5px rgba(15, 23, 42, 0.12), 0px 8px 10px -6px rgba(15, 23, 42, 0.06)`, framed by a tinted border: `1px solid #CBD5E1`.
- **Selected or Hover State**: Border color switches instantly from neutral slate (`#E2E8F0`) to Emerald Primary (`#0D7A5F`) with an ambient emerald glow: `0px 4px 12px rgba(13, 122, 95, 0.08)`.

## Shapes

The interface adopts a balanced `0.5rem` (`8px`) baseline roundedness:

- **Data Tables & Operational Cards**: `rounded-md` (`8px`) or `rounded-lg` (`16px`) for primary dashboard containers.
- **Action Buttons & Form Controls**: Standardized at `rounded-md` (`8px`) to ensure structured, crisp form alignment.
- **Status Badges & Pill Chips**: Full capsule rounding (`9999px`) to create an immediate visual contrast against geometric card surfaces.
- **Room Floor Map Grids**: Fixed `8px` corner radiuses ensure rooms feel modular, like an architectural blueprint.

## Components

### Buttons
- **Primary**: Solid Emerald (`#0D7A5F`), white text, `height: 40px` (desktop) or `48px` (mobile), padding `0 16px`, semi-bold typography. Active state elevates slightly; focus uses `ring-2 ring-emerald-500/20`.
- **Secondary**: Crisp hairline border (`1px solid #CBD5E1`), background white, text Slate Navy (`#1E293B`).
- **Tertiary / Destructive**: Soft Red background (`#FEF2F2`), text Ruby (`#991B1B`), border `1px solid #FCA5A5`. Used for tenant eviction notices (*Keluarkan Penghuni*) or deposit forfeits.

### Badges & Status Chips
Pill-shaped badges (`padding: 2px 10px`, `font-size: 11px`, `font-weight: 700`, uppercase tracking):
- **Terisi**: `#ECFDF5` background, `#065F46` label with a 6px solid emerald dot.
- **Kosong**: `#F1F5F9` background, `#475569` label with a 6px slate dot.
- **Menunggak**: `#FEF2F2` background, `#991B1B` label with a subtle pulsating red dot.
- **Verifikasi Bayar**: `#FFFBEB` background, `#92400E` label with an alert attachment icon.

### KPI Counter Cards
- Metric cards contain a top micro-label (e.g., *TOTAL PENDAPATAN BULAN INI*), a primary value in `currency-stat` styling (`Rp 42.500.000`), a comparison trend badge (`+8.4% vs bulan lalu`), and an optional tertiary badge highlighting uncollected arrears.

### Room Matrix Cell (Kamar Grid Card)
- Modular square/rectangular card representing a physical unit (e.g., *A-01, Lt. 1*).
- Displays room status badge, tenant name, monthly rental price, and quick action hover states (*Catat Meteran Listrik*, *Kirim Tagihan WhatsApp*, *Lihat Bukti Bayar*).

### Payment Verification Drawer (Bukti Transfer Modal)
- Side-sheet overlay displaying split-screen view: the uploaded bank transfer screenshot on the left (with zoom/rotate tools) and the transaction confirmation form on the right (Bank Pengirim, Nominal, Tanggal Masuk, Tombol "Konfirmasi Lunas" / "Tolak Pembayaran").

### Form Fields & Inputs
- Inputs feature `height: 40px`, border `1px solid #CBD5E1`, internal padding `0 12px`, with an explicit focus ring in emerald (`#0D7A5F`).
- Prefix integration for Indonesian financial values: Non-editable `Rp` prefix badge positioned inside the left edge of currency inputs.