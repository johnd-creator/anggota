## Goal
Improve the UX of action buttons on the member detail page (`/admin/members/{id}`) to make them more visually appealing, interactive, and modern instead of plain and boring.

## Constraints
- Must maintain existing functionality (Edit, Ubah Status, Ajukan Mutasi)
- Should follow existing design system (brand colors, spacing)
- Must work on mobile and desktop viewports
- Cannot modify backend routes or permissions logic
- Frontend-only changes (Vue component)

## Known context
- Current buttons (lines 5-7 in Show.vue) use minimal styling: `px-3 py-1.5 border rounded-lg text-sm`
- Buttons are plain with no color, no icons, no hover effects
- Three buttons: "Edit", "Ubah Status", "Ajukan Mutasi"
- Buttons are only visible for non-pengurus roles
- The page uses CardContainer, Badge, and other UI components from the design system
- Tab navigation below uses better styling with active states

## Risks
1. **Low**: Button colors might clash with existing design - mitigation: use established brand colors
2. **Low**: Icons might not be clear - mitigation: use standard, recognizable icons
3. **Medium**: Too many visual changes might overwhelm users - mitigation: keep changes subtle but effective
4. **Low**: Mobile layout might break - mitigation: ensure responsive design with proper wrapping

## Options

### Option 1: Minimal Enhancement
- Add subtle background colors (neutral for secondary actions)
- Add hover states (background darkening)
- Keep existing layout and spacing

**Pros**: Quick, low risk, maintains familiarity
**Cons**: May not fully address "polos dan membosankan" concern

### Option 2: Icon + Color Enhancement
- Add icons to each button (pencil for Edit, status icon for Ubah Status, arrows for Mutasi)
- Use color-coded buttons:
  - Edit: Primary brand color (blue)
  - Ubah Status: Warning/info color (amber/yellow)
  - Ajukan Mutasi: Secondary brand color
- Add hover effects with scale and shadow
- Improve spacing between buttons

**Pros**: Clear visual hierarchy, modern look, icons improve recognition
**Cons**: More visual change, requires icon selection

### Option 3: Comprehensive Redesign
- Redesign as a button group with dropdown for secondary actions
- Add icons, colors, and animations
- Include tooltips for clarity
- Add loading states
- Implement split button design

**Pros**: Most modern and polished
**Cons**: Significant changes, may confuse existing users, more development time

## Recommendation
**Option 2: Icon + Color Enhancement**

This approach directly addresses the "polos dan membosankan" complaint by:
1. **Adding visual interest** with icons that clarify each action
2. **Using color coding** to create hierarchy and improve recognition
3. **Implementing hover effects** (scale, shadow, background transitions) for better interactivity
4. **Maintaining familiarity** while significantly improving aesthetics

Implementation details:
- **Edit button**: Primary blue with pencil icon (most common action)
- **Ubah Status button**: Amber/yellow with status icon (important but less frequent)
- **Ajukan Mutasi button**: Secondary color with transfer/arrows icon (specialized action)
- Add `transition-all duration-200` for smooth animations
- Add `hover:scale-105` and `hover:shadow-md` for interactive feedback
- Increase gap from `gap-2` to `gap-3` for better separation

## Acceptance criteria
- [ ] Buttons have distinct colors matching their action type
- [ ] Each button has an appropriate icon
- [ ] Hover effects are smooth and noticeable (scale, shadow, background)
- [ ] Buttons maintain proper spacing (gap-3 minimum)
- [ ] Layout is responsive on mobile (buttons wrap gracefully)
- [ ] All existing functionality works (navigation, permissions)
- [ ] No console errors or warnings
- [ ] Visual hierarchy is clear (Edit as primary, others as secondary)
