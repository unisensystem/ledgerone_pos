# FuelingSys ERP – Design System (DARK THEME)

## 1. Brand Identity
**Brand Name:** FuelingSys ERP  
**Theme:** Modern Corporate – Dark Edition  
**Audience:** Petrol Pumps, Gas Stations, Fuel Distributors, Corporate Chains

### Brand Personality
- Professional
- High-Confidence
- Energy + Motion
- Strong Visibility at Night

### Brand Keywords
Fuel, Power, Precision, Flow, Corporate, Motion

---

## 2. Core Foundations

## 2.1 Dark Color Palette
**Primary Colors**
- Primary-900: #0B1220 (Main background)
- Primary-700: #111A2E (Panels)
- Primary-500: #1A2842 (Cards)

**Accent Colors (Fuel Energy)**
- Fuel-Yellow: #FFD447
- Fuel-Orange: #FF9A3C
- Fuel-Teal: #27D0C8

**Neutral Colors**
- White: #FFFFFF
- Grey-300: #C9D1D9
- Grey-100: #F3F4F6

**Borders / Lines**
- Border: rgba(255,255,255,0.1)
- Card Shadow: rgba(0,0,0,0.4)

---

## 3. Typography

### Font Family
- **Primary:** Inter / SF Pro

### Type Scale
- Display-1: 32px, 700
- H1: 28px, 700
- H2: 22px, 600
- H3: 18px, 600
- Body-1: 16px
- Body-2: 14px
- Label: 12px

### Rules
- Dark theme uses **high contrast headings**.
- Body text must stay above **opacity 85%**.

---

## 4. Components

## 4.1 Buttons

### Primary Button
- Background: Fuel-Yellow
- Text: #0B1220
- Radius: 8px
- Padding: 12px 22px
- Hover: #FFC72A

### Secondary Button
- Background: transparent
- Border: 1px solid Fuel-Yellow
- Text: Fuel-Yellow

### Danger Button
- Background: #FF5A5A
- Text: White

---

## 4.2 Inputs / Forms

### Input Design
- Background: #111A2E
- Border: 1px solid rgba(255,255,255,0.1)
- Text: White
- Placeholder: rgba(255,255,255,0.4)
- Focus Border: Fuel-Yellow
- Radius: 8px
- Height: 42px
- Padding: 0 14px

### Dropdown
- Background: #111A2E
- Hover: #1A2842
- Selected: Fuel-Yellow + dark text

### Form Layout
- **Grid-Based**: 2-column on desktop
- **Full-width input** when label is long
- **Section Headings** with thin underline (#1A2842)

---

## 4.3 Cards
- Background: Primary-500
- Border: none
- Rounded: 14px
- Shadow: 0px 4px 16px rgba(0,0,0,0.4)
- Padding: 20px

---

## 4.4 Navigation Sidebar
- Background: Primary-900
- Active Item: Fuel-Yellow + dark text
- Hover: rgba(255,255,255,0.08)
- Icons: White (60% opacity), active becomes Fuel-Yellow

---

## 4.5 Dashboard Widgets
### KPI Card
- Background: #111A2E
- Accent Bar: Fuel-Yellow (4px)
- Value: 24px, bold, white
- Label: Grey-300

### Graph Panels
- Background: #1A2842
- Grid Lines: rgba(255,255,255,0.08)
- Lines: Fuel-Teal or Fuel-Yellow

---

## 5. Responsive Rules

### Mobile (max-width 480px)
- Sidebar collapses to 60px icons-only
- Cards become full-width
- Forms switch to 1-column
- Font size reduces by 1–2px

### Tablet (481–1024px)
- Sidebar: 200px
- 2-column forms unless screen <700px

### Desktop
- Full layout
- 2-column forms always
- Graphs expand horizontally

---

## 6. Illustration & Icon Style

### Illustration Style
- **Isometric fuel-station style**
- **Soft gradients:** dark blue → energy yellow
- **Thin stroke outlines (1.5px)**
- **Rounded corners**
- **Clean corporate look**

### Icon Style
- Line icons 2px
- Color: White or Fuel-Yellow
- Consistent rounded corners

---

## 7. Interaction Rules
- Hover states always increase brightness by 6–10%
- Buttons animate with 120ms ease
- Cards lift with shadow on hover
- Inputs glow in Fuel-Yellow when active

---

## 8. Page Templates

### Form Page Template (Dark)
- Title: H1 White
- Description: Grey-300
- Card container with form
- Submit button bottom-right

### Dashboard Template (Dark)
- Large KPIs on top
- 2-row graphs
- Latest Transactions table

---

## 9. Summary
This is your complete **Dark Theme Design System** for FuelingSys ERP.
If you want component library diagrams, React components, HTML mockups, or a downloadable PDF version, tell me and I’ll generate them.