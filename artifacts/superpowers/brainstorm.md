## Goal
Improve mobile UX for the notifications page (`/notifications`) by redesigning the filter and action buttons to be more mobile-friendly, visually appealing, and easier to interact with on small screens.

## Constraints
- Must maintain all existing functionality (tab filtering, search, date range, mark all read, apply filters)
- Should work within the existing Vue 3 + Inertia.js + Tailwind CSS stack
- Must preserve desktop experience (no regressions)
- Should follow existing design system patterns (CardContainer, SecondaryButton, DataCard components)

## Known context
Looking at the uploaded image and code:
- **Current issues on mobile:**
  - "Tandai semua sudah dibaca" button is very wide and takes full width
  - Search input + "Go" button layout feels cramped
  - Date range inputs (mm/dd/yyyy s/d mm/dd/yyyy) are squeezed horizontally
  - "Terapkan" button is hidden on mobile, "Go" button appears instead
  - Overall layout feels cluttered with too many elements stacked vertically
  
- **Current implementation (lines 13-25):**
  - Uses flex-col on mobile, flex-row on desktop
  - "Tandai semua sudah dibaca" button uses `justify-center` class
  - Search has a separate "Go" button for mobile (md:hidden)
  - Date inputs are in a flex container with "s/d" separator
  - "Terapkan" button is hidden on mobile (hidden md:inline-flex)

## Risks
- **Over-simplification**: Removing too many controls could reduce functionality
- **Inconsistency**: New mobile design might feel disconnected from desktop version
- **Touch target size**: Buttons that are too small will be hard to tap (minimum 44x44px recommended)
- **Visual clutter**: Adding icons or reorganizing without proper spacing could make it worse
- **Testing gap**: Changes might look good in browser dev tools but feel different on actual devices

## Options

### Option 1: Icon-based Compact Toolbar
- Replace text buttons with icon buttons for common actions
- Use a floating action button (FAB) for "Mark all read"
- Collapse filters into an expandable drawer/accordion
- **Pros**: Very clean, modern, saves vertical space
- **Cons**: Icons may not be immediately clear, requires learning curve

### Option 2: Segmented Control + Bottom Sheet
- Keep tab buttons as segmented control (more compact)
- Move all filters (search, date range, mark all) into a bottom sheet/modal triggered by a filter icon
- **Pros**: Cleanest main view, follows mobile app patterns
- **Cons**: Extra tap to access filters, might hide important functionality

### Option 3: Improved Stacked Layout with Better Spacing
- Keep current stacked approach but improve visual hierarchy
- Use pill-shaped buttons with better padding
- Group related controls (search + dates) in a card/section
- Make "Tandai semua sudah dibaca" button smaller with icon
- Use a single "Apply" button for all filters
- **Pros**: Minimal code changes, preserves discoverability, low risk
- **Cons**: Still takes vertical space, less "wow" factor

### Option 4: Horizontal Scrollable Filter Bar
- Make filter controls horizontally scrollable on mobile
- Use chips/pills for each filter type
- Sticky filter bar that stays visible when scrolling
- **Pros**: Saves vertical space, modern pattern (like mobile apps)
- **Cons**: Horizontal scrolling can be missed by users, accessibility concerns

## Recommendation
**Option 3: Improved Stacked Layout with Better Spacing**

**Rationale:**
- **Lowest risk**: Preserves all functionality without hiding anything
- **Quick wins**: Can be implemented with CSS/layout changes only
- **Accessible**: All controls remain visible and tappable
- **Maintainable**: Doesn't introduce complex state management (modals, drawers)
- **Iterative**: Can be enhanced later with Option 2 if needed

**Specific improvements:**
1. **"Tandai semua sudah dibaca" button**: 
   - Add an icon (checkmark or eye icon)
   - Reduce padding, make it inline-flex with max-width
   - Position it differently (maybe top-right as a smaller button)

2. **Search + Date filters**:
   - Group in a subtle bordered container
   - Stack search on one row, dates on another row
   - Use a single "Terapkan" button below the filter group
   - Remove the separate "Go" button

3. **Visual polish**:
   - Add subtle shadows/borders to create visual separation
   - Use better spacing (gap-3 → gap-4)
   - Consider using smaller font sizes for labels
   - Add icons to buttons for visual clarity

## Acceptance criteria
- [ ] All filter buttons are easily tappable on mobile (minimum 44x44px touch targets)
- [ ] "Tandai semua sudah dibaca" button is more compact and doesn't dominate the screen
- [ ] Search and date range filters are logically grouped and easy to use
- [ ] Single "Apply" or "Terapkan" button for all filters (no separate "Go" button)
- [ ] Layout feels spacious with proper gaps between elements
- [ ] Desktop view remains unchanged and functional
- [ ] All existing functionality works (tab switching, search, date filtering, mark all read)
- [ ] Visual design feels modern and polished (proper use of spacing, borders, shadows)
- [ ] Tested on actual mobile device or browser dev tools at 375px width
