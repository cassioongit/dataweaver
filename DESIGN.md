# Design System: Dataweaver PRO

This document is the visual system source of truth for Dataweaver PRO. If any other note or page conflicts with this file, this file wins.

## Visual Direction

- **Style**: premium internal SaaS, clean and operational
- **Tone**: calm, precise, high-contrast, low-noise
- **Typography**: `Inter`
- **Primary Accent**: blue
- **Neutral Base**: soft zinc-gray

## Color Palette

- **Primary**: `#0061FF`
- **Primary Hover**: `#0052CC`
- **Primary Soft**: `#E6F0FF`
- **App Background**: `#F1F3F5`
- **Surface**: `#FFFFFF`
- **Text Main**: `#18181B`
- **Text Muted**: `#71717A`

## Shape and Elevation

- **Main containers**: `rounded-2xl`
- **Inputs, buttons, cards**: `rounded-xl` or `rounded-md` when compactness matters
- **Shadows**: subtle only, usually `shadow-sm`
- **Borders**: thin and soft, never heavy

## Layout Rules

- The app uses a fixed left sidebar at `88px`.
- The sidebar is vertical, icon-first, and compact.
- The main content area is full-bleed and sits on the neutral background.
- The logged-in shell should feel operational, not decorative.
- When possible, primary actions and review controls should appear in the first fold.
- Preview and approval surfaces should be vertically compact before they become scrollable.

## Component Rules

### Sidebar

- White background
- Active state: `bg-[#0061FF]/10` with blue icon/text
- Inactive state: muted zinc text with light hover state

### Header

- Brand block on the left
- User avatar on the right
- The header should stay simple and lightweight

### Login Card

- Centered floating card
- White surface
- Solid-fill inputs
- Left icons in fields
- Primary action in blue

### Tables

- Compact density
- Sober row hover
- Readability over decoration
- Optional subtle zebra treatment if needed for scanability

### Status Badges

- Success: green soft background, darker green text
- Warning: blue or amber soft background depending on context
- Error: red soft background, darker red text

## CSS Theme Tokens

```css
@theme {
  --color-brand-primary: #0061FF;
  --color-brand-hover: #0052CC;
  --color-brand-light: #E6F0FF;

  --color-bg-app: #F1F3F5;
  --color-bg-surface: #FFFFFF;

  --color-text-main: #18181B;
  --color-text-muted: #71717A;
}
```

## Source of Truth

- Product narrative: [_bmad-output/A-Product-Brief/project-brief.md](/Users/cassiomachado/Documents/Development/dataweaver/_bmad-output/A-Product-Brief/project-brief.md)
- Platform constraints: [_bmad-output/A-Product-Brief/platform-requirements.md](/Users/cassiomachado/Documents/Development/dataweaver/_bmad-output/A-Product-Brief/platform-requirements.md)
- Local project rules: [agf-this-project.md](/Users/cassiomachado/Documents/Development/dataweaver/agf-this-project.md)
