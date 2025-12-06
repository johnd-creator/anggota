# Design Foundation

## Overview
This document defines the design tokens and foundation for SIM-SP's user interface.

## Colors

### Brand Colors
**Primary (Blue)** - Main brand color for primary actions, links, and key UI elements
- `brand-primary-500`: #3b82f6 (Main)
- `brand-primary-600`: #2563eb (Hover)
- `brand-primary-700`: #1d4ed8 (Active)

**Secondary (Purple)** - Accent color for secondary actions and highlights
- `brand-secondary-500`: #d946ef (Main)
- `brand-secondary-600`: #c026d3 (Hover)

### Neutral Colors
Used for text, backgrounds, borders
- `neutral-50` to `neutral-900` (Light to Dark)

### Status Colors
- **Success**: Green (#10b981) - Confirmations, success states
- **Warning**: Amber (#f59e0b) - Warnings, cautions
- **Error**: Red (#ef4444) - Errors, destructive actions
- **Info**: Blue (#3b82f6) - Informational messages
- **Neutral**: Gray (#6b7280) - Secondary states, muted

## Typography

### Font Families
- **Sans**: Inter (primary), with system fallbacks
- **Mono**: JetBrains Mono for code

### Type Scale
- `text-xs`: 12px - Small labels, captions
- `text-sm`: 14px - Secondary text
- `text-base`: 16px - Body text (default)
- `text-lg`: 18px - Emphasized text
- `text-xl`: 20px - Small headings
- `text-2xl`: 24px - Section headings
- `text-3xl`: 30px - Page headings
- `text-4xl`: 36px - Hero headings

### Font Weights
- `font-normal`: 400 - Body text
- `font-medium`: 500 - Emphasized text
- `font-semibold`: 600 - Subheadings
- `font-bold`: 700 - Headings

## Spacing

Based on 4pt grid system:
- `1`: 4px
- `2`: 8px
- `3`: 12px
- `4`: 16px
- `6`: 24px
- `8`: 32px
- `12`: 48px
- `16`: 64px

## Border Radius
- `rounded-sm`: 2px - Subtle rounding
- `rounded`: 4px - Default
- `rounded-md`: 6px - Cards, inputs
- `rounded-lg`: 8px - Larger cards
- `rounded-xl`: 12px - Modals
- `rounded-2xl`: 16px - Hero sections
- `rounded-full`: Pills, avatars

## Shadows
- `shadow-sm`: Subtle elevation
- `shadow`: Default cards
- `shadow-md`: Dropdowns, sticky table header
- `shadow-lg`: Modals
- `shadow-xl`: Overlays

## Breakpoints
- `sm`: 640px - Small tablets
- `md`: 768px - Tablets
- `lg`: 1024px - Laptops
- `xl`: 1280px - Desktops
- `2xl`: 1536px - Large screens

## Transitions
- **Fast**: 150ms - Hover states
- **Base**: 200ms - Default (buttons, links, chips)
- **Slow**: 300ms - Complex animations

## Dark Mode
Use `.theme-dark` class on root element to enable dark mode.
All components should support dark mode variants.
