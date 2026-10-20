---
name: SprintG — The Mission Console
description: Onde visões audaciosas ganham vida, sprint a sprint.
colors:
  signal-blue: "#3b82f6"
  signal-blue-bright: "#60a5fa"
  mission-crimson: "#b91c1c"
  status-green: "#22c55e"
  status-amber: "#f59e0b"
  status-red: "#ef4444"
  void-black: "#060911"
  deep-space: "#090e1a"
  hull-panel: "#0d121f"
  hull-panel-raised: "#121828"
  seam-line: "#1e283d"
  seam-line-lit: "#2f3e5c"
  starlight: "#ffffff"
  radio-static: "#94a3b8"
typography:
  display:
    fontFamily: "Instrument Serif, Georgia, serif"
    fontSize: "clamp(48px, 8vw, 94px)"
    fontWeight: 400
    lineHeight: 0.95
    letterSpacing: "-2.46px"
  headline:
    fontFamily: "Instrument Serif, Georgia, serif"
    fontSize: "18px"
    fontWeight: 700
  title:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "16px"
    fontWeight: 700
    letterSpacing: "-0.01em"
  body:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "13px"
    fontWeight: 400
    lineHeight: 1.55
  label:
    fontFamily: "Inter, ui-sans-serif, system-ui, sans-serif"
    fontSize: "11px"
    fontWeight: 700
    letterSpacing: "0.06em"
rounded:
  xs: "8px"
  sm: "10px"
  md: "12px"
  lg: "18px"
  xl: "44px"
  pill: "9999px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "16px"
  lg: "20px"
  xl: "24px"
  xxl: "40px"
components:
  button-primary:
    backgroundColor: "{colors.signal-blue}"
    textColor: "{colors.starlight}"
    rounded: "{rounded.sm}"
    padding: "8px 14px"
  button-accent:
    backgroundColor: "{colors.signal-blue}"
    textColor: "{colors.starlight}"
    rounded: "{rounded.sm}"
    padding: "8px 14px"
  button-ghost:
    backgroundColor: "{colors.hull-panel-raised}"
    textColor: "{colors.starlight}"
    rounded: "{rounded.sm}"
    padding: "8px 14px"
  card-surface:
    backgroundColor: "{colors.hull-panel}"
    textColor: "{colors.starlight}"
    rounded: "{rounded.lg}"
    padding: "22px 26px"
  chip-badge:
    backgroundColor: "{colors.signal-blue}"
    textColor: "{colors.starlight}"
    rounded: "{rounded.pill}"
    padding: "3px 10px"
  input-field:
    backgroundColor: "{colors.hull-panel-raised}"
    textColor: "{colors.starlight}"
    rounded: "{rounded.sm}"
    padding: "8px 12px"
---

# Design System: SprintG — The Mission Console

## Overview

**Creative North Star: "The Mission Console"**

SprintG is a spacecraft instrument panel rendered in deep space: darkness is the canvas, information is light. The system speaks in two materials that never mix carelessly — **Liquid Glass** (Apple HIG materials) for the functional layer that floats over content (hero controls, CTAs, modals, the video world), and **hull-solid panels** for the content layer that holds real data (sprint cards, stats, task rows). Glass is atmosphere; solid is instrument. A screen where everything is glass is as unreadable as one where nothing is.

Density is compact and operational — 11–17px Inter for data, generous 18px radii on surfaces, and a single accent (the project's `--accent`, default Signal Blue) that behaves like a mission lamp: it appears wherever action or status matters, nowhere else. The serif display voice (Instrument Serif) is reserved for the cinematic hero and is deliberately absent from the dashboard, so the moment the user enters "work mode," the atmosphere drops to instrumentation.

Motion is the third material: light itself moves — border beams ride surfaces in slow comets, sparkles drift through the console, sheets rise with Apple's easing. Every movement is tactile and confident (press scales, hover lifts, glow answers input) and every movement has an off-switch (`prefers-reduced-motion`, `prefers-reduced-transparency`, `prefers-contrast`).

**Key Characteristics:**
- Two-material doctrine: glass floats, solid carries data — never both on the same element.
- One accent per screen, re-themed per project; status colors are a fixed semantic set.
- Light as motion: comets, beams, sparkles — illumination, not decoration.
- Compact instrument typography: Inter does the work, Instrument Serif does the dreaming.
- Accessibility is a floor, not a feature: three system-preference media queries honored globally.

## Colors

The palette is deep-space neutral with one programmable accent and four fixed status signals.

### Primary
- **Signal Blue** (#3b82f6, bright #60a5fa): the default `--accent` — mission lamp of the interface. Active on primary buttons, focus borders, progress fills, beams. Runtime-replaced per project theme; everything downstream (`--accent-glow`, `--accent-light`, aurora, sparkles) derives from it.

### Secondary
- **Mission Crimson** (#b91c1c): the ambient emotional temperature — appears in the default hero gradient glow and danger-adjacent ambience, never as a button color.

### Tertiary — status (fixed semantics, never re-themed)
- **Go Green** (#22c55e): task done, positive delta.
- **In-Progress Amber** (#f59e0b): active work, warnings.
- **Blocked Red** (#ef4444): blockers, destructive actions.
- **Planned Gray**: untouched backlog (renders via `--radio-static`).

### Neutral
- **Void Black** (#060911): page ground, the space between stars.
- **Deep Space** (#090e1a): gradient partner of Void Black.
- **Hull Panel** (#0d121f): content surfaces at rest.
- **Hull Panel Raised** (#121828): inputs, secondary surfaces, hover states.
- **Seam Line** (#1e283d, lit #2f3e5c): borders — structural, never decorative.
- **Starlight** (#ffffff): primary text.
- **Radio Static** (#94a3b8): muted text, labels, metadata.
- **Console Ramp** (`--surface-0..4`: #060911→#1a2438): the refined neutral scale for new dashboard surfaces; `--hairline` rgba(148,163,184,.10) for separators; text levels `--text-strong/-base/-muted/-faint`.
- **Instrument Amber** (`--instrument` #f59e0b): fixed secondary for goals, warnings and state accents — never replaces the project accent.

### Named Rules
**The One Voice Rule.** Exactly one accent color is active per screen — the project's. Its rarity is the point; status colors don't count because they mean something, not because they look nice.
**The Meaning-Not-Decoration Rule.** No color appears without a job: accent = action, crimson = ambience, green/amber/red/gray = status. If a swatch can't name its job, it doesn't ship.

## Typography

**Display Font:** Instrument Serif (Georgia, serif fallback)
**Body/Label Font:** Inter (system-ui fallback)

**Character:** The serif is the movie trailer — italic accents, tight -2.46px tracking at enormous scale, only where the product performs. Inter is the flight computer — 400–800 weights, 11–17px, doing all reading work in the dashboard. They never trade places.

### Hierarchy
- **Display** (400, clamp(48px, 8vw, 94px), line-height 0.95, tracking -2.46px): hero title only.
- **Headline** (700, 18px): modal titles, project title.
- **Title** (700, 16px, -0.01em): sprint section titles.
- **Body** (400, 13px, line-height 1.55): task labels, descriptions, subtitles.
- **Label** (700, 11px, 0.06em, often uppercase): badges, stats captions, form labels, states.

### Named Rules
**The Two Voices Rule.** Instrument Serif leads the hero display *and* the dashboard's structural titles (project name, sprint titles at 21–27px/400) — the serif carries the Mission Console identity across both worlds; Inter handles every data row, label and control. Never mix them inside one component.
**The Legibility Floor Rule.** Nothing renders below 11px. Microcopy earns its size by being scannable, not small.
**The Tracking Floor Rule.** Negative letter-spacing never exceeds -0.04em at any size; the hero display tracks at -0.026em so tightening scales with the type instead of drifting past the floor.

## Layout

Two full-screen views, never one long scroll: the cinematic home (video hero, 100vh) and the app dashboard (`position:fixed; inset:0`, internally scrollable), swapped by the 5-strip page curtain. Between them sits a **project picker** (Flex Carousel): after login the user lands on the picker, not directly in a project. The page body itself does not scroll; `html.app-mode` decides which view is mounted and `html.picker-open` floats the picker over the dashboard. Content lives in a 1200px max-width wrap (`padding: 0 20px 80px`) floating on full-bleed darkness.

Vertical rhythm is multiples of 4 (4/8/12/16/20/24/40/80). Sprint sections stack at 16px gaps; bento stats form a 5-up row at 8px gaps. Below 820px everything collapses to single-column stacks (project bar, section head, task grid reflows to 2 columns, form rows stack). Safe areas respected via `env(safe-area-inset-*)` on nav, toasts, and floating controls.

**The Curtain Rule.** Home↔app changes are always authored transitions — five staggered glass strips sweep the viewport (expo-in-out, 0.64s + 60ms cascades) while the entering view rises through a 7px blur; never an instant swap. The toolbar groups filters left / actions right behind one hairline divider (hidden under 720px).

## Elevation & Depth

Hybrid: solid hull panels sit flat with structural borders, while the glass layer genuinely floats. Depth is conveyed by three distinct vocabularies — never mixed on one element: tonal borders for content structure, inset specular shadows for glass, and diffuse black halos for floating things.

### Shadow Vocabulary
- **Glass Specular** (`inset 0 1px 1px rgba(255,255,255,0.30)` + edge insets): light catching the top lip of glass. Only on `.liquid-glass`.
- **Hull Lift** (`0 12px 40px rgba(0,0,0,0.3)`): content cards at rest.
- **Sheet Halo** (`0 24px 80px rgba(0,0,0,0.85)`): modals — the only thing allowed to out-shadow a card.
- **Seam Drop** (`0 -30px 80px rgba(0,0,0,0.65)`): the dashboard's shadow over the video world.
- **Accent Glow** (`0 0 20px var(--accent-glow)`): hover-only light emission on accent controls.

### Named Rules
**The Float Budget Rule.** At most one elevation tier above its neighbor per visual step; if two surfaces share a plane, they share a shadow class.
**The One-Environment Rule.** Content panels are translucent (rgba ~.72–.84 + blur 5px + hairline border) so the aurora/starfield environment reads as one continuous space behind the whole dashboard — no opaque slabs, no visible dividers.
**The Beam Discipline Rule.** Border beams are punctuation, not wallpaper: exactly two always-on (`beam--pulse`, glow-only outside the border) — the project card and the hero CTA. Everything else (sprint panels, primary buttons) wakes its beam on hover only; modals never carry one.

## Shapes

Gently curved instrument language: 8px on task rows (xs), 10px on buttons/inputs (sm), 12px on stat tiles (md), 18px on cards/modals (lg), and the signature 44px console seam (xl). Pills (9999px) for badges, hero CTAs and toggles. Borders are 1px structural lines in seam-line colors; glass edges carry the gradient specular ring instead of a border. The recurring silhouette is the **curved rise** — big top radius over a dark world (hero seam, modals, project card).

## Components

### Buttons
- **Shape:** slightly rounded (10px), pill for hero/nav CTAs.
- **Primary/Accent:** accent-tinted translucent background with accent border; hover fills to solid accent + a lit lift (`0 6px 18px -6px` accent glow — the only colored shadow the system allows); padding 8px 14px.
- **Ghost:** hull-raised background, seam border; hover lifts one step.
- **Press:** every control scales to 0.96 in 60ms — tactile, immediate.
- **Signature:** primary CTAs carry a slow border-beam comet riding their edge.

### Chips / Badges
- Pill, 800-weight 11px uppercase label, solid sprint color or status tint; sprint badges carry the section's `--beam-color`.

### Cards / Containers
- 18px radius, hull-panel gradient (`145deg, panel → #080c16`), 1px seam border, Hull Lift shadow, 22–26px internal padding; hover brightens border to seam-line-lit.

### Inputs / Fields
- Hull-raised background, seam stroke, 10px radius; focus shifts border to accent (no glow ring). Error states use status red borders.

### Navigation
- Hero nav: transparent over video with serif wordmark; dashboard nav: solid project-bar with select + ghost controls. Mobile: links collapse, bar stacks.

### Signature: Mission Console Stack
The composite of sticky video world → curved 44px seam → ambient aurora/starfield/grain layer → fixed sparkle canvas → hull cards with per-sprint beams. New surfaces extend this stack; they don't flatten it.

### Signature: Agent Access
The account modal's "Acesso de IA" block turns the console into a mission other tools can drive: a one-click link (`/ai/<TOKEN>`) that chatbots and CLIs use to read, create and mark work — never edit texts, never delete. The panel's side of the contract is trust UI: masked link with reveal/copy, a daily ops meter, the last operations as a hairline ledger, a warning line about sharing, and two kill-switches — "Revogar" for the link and "Limpar criações da IA" for everything the link made. AI-created items wear a small mono **IA** chip next to their name (sprint chips live beside the animated title, never inside it); AI-marked states stay silent. Copy is plain and accountable: name the power, name the risk, offer the undo.

### Signature: Dual Views (List + Kanban)
Each project persists a `viewMode`: **list** renders sprints as mission panels (serif title, SVG progress ring in the sprint's color, amber target goal line, tasks grouped active-first); **board** renders four translucent glass columns (Em curso · Planeadas · Bloqueadas · Concluídas) where tasks move by native drag-and-drop — every move lands with an undo toast, and keyboard users cycle status with `M` on a focused card (the shortcut is spelled out in each card's title and aria-label). When a sprint's last task lands on done, its ring pops (0.7s spring scale + light bloom) and a "Sprint concluída — 100% entregue!" toast seals it — the console's one celebration. View swaps move focus to the entering container and announce it through a polite live region. The motion layer (anime.js via CDN, silent CSS fallback offline) adds spring modal entry, center-staggered task expansion and word-by-word blur-in on sprint titles — all gated by `prefers-reduced-motion`.

### Signature: Project Picker (Flex Carousel)
After login the user chooses a mission instead of dropping into one. A coverflow of project cards (icon tile in the project accent, serif name, clamped description, sprint count, an "Abrir" affordance that appears only on the centered card) floats over a deep-space backdrop with a starfield; the last card is always a dashed **"+ Novo projeto"**. Navigation is drag, arrow buttons, or ←/→; clicking a side card centers it, clicking the centered card opens it (or the create modal, for the "+"). Transitions ride `cubic-bezier(0.22,1,0.36,1)`; `prefers-reduced-motion` flattens the 3D. Re-enter any time from the dashboard's "Trocar" button. The centered card carries the ProfileCard treatment (adapted from reactbits): a time-based exponential tilt (±12.5°/±10°, tau 100ms) that follows the pointer, a specular glare and an accent behind-glow that track the cursor, gyro tilt on touch devices, and content lifted on `translateZ` layers; tilt pauses during drag and everything is inert under `prefers-reduced-motion`.

**The Warp Rule.** Opening a project and returning to the picker are hyperspace transitions, not fades: the chosen card detaches (clone) and flies at the camera — exponential scale-up with blur and fade — while a 2D starfield overlay accelerates into radial streaks on the galaxy palette; the view swaps inside a blue-white bloom at peak velocity, and the streaks decelerate over the destination. The return is the same journey in reverse (short bloom, stars calming into the carousel). The whole sequence is time-based (~1.45s out, ~0.95s back), locks input via `html.warping`, runs its canvas only during the transition, and collapses to an instant swap under `prefers-reduced-motion`.

## Do's and Don'ts

### Do:
- **Do** route every new color through `--accent` or the fixed status set; re-theming a project must recolor beams, aurora, and sparkles automatically.
- **Do** keep glass on the functional layer (controls, modals, hero) and hull-solid on content (cards, rows, stats).
- **Do** give every animation a `prefers-reduced-motion` off-switch and every translucent surface a `prefers-reduced-transparency` solid fallback.
- **Do** use the curved rise (18–44px radius over dark) when a surface floats over another world.
- **Do** press-test interactions: scale 0.96, 60ms, on every clickable thing.

### Don't:
- **Don't** put Instrument Serif in the dashboard or Inter in the hero title.
- **Don't** drop below 11px or use Light/Thin weights.
- **Don't** use backdrop-filter on data-bearing panels.
- **Don't** mix specular and diffuse shadows on one element.
- **Don't** invent testimonials, pricing, or adoption claims — PRODUCT.md marks them absent.
- **Don't** let two accents compete on one screen; the One Voice Rule is absolute.
