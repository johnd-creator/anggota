## Goal
Optimize the date picker controls on the notifications page mobile view to reduce space consumption and improve UX, considering options to either make them more compact or hide them entirely on mobile devices.

## Constraints
- Must maintain filtering functionality if date pickers are kept
- Should work within existing Vue 3 + Inertia.js + Tailwind CSS stack
- Desktop experience must remain unchanged
- Should follow mobile-first UX best practices
- Any changes should not break existing date filtering logic

## Known context
Looking at the current implementation and screenshot:
- **Current state:**
  - Two date inputs (start and end) with "s/d" separator between them
  - Date inputs use native HTML5 date type (`<input type="date">`)
  - On mobile, these inputs take significant horizontal space
  - The format shows "mm/dd/yyyy" placeholders which are quite wide
  - Currently stacked in the filter section (lines 19-23 in Index.vue)

- **User feedback:**
  - Date pickers are taking too much space on mobile
  - Suggestion to either optimize or hide them on mobile

- **Usage consideration:**
  - Date filtering might be less frequently used on mobile compared to desktop
  - Mobile users typically want quick access to recent notifications
  - Advanced filtering (like date ranges) is more common on desktop workflows

## Risks
- **Hidden functionality**: If we hide date pickers, mobile users lose the ability to filter by date range
- **Discoverability**: Collapsing into an accordion/modal might make the feature hard to find
- **Complexity**: Adding toggle states increases component complexity
- **User confusion**: Different mobile vs desktop experiences might confuse users who switch devices

## Options

### Option 1: Hide Date Pickers on Mobile Entirely
- Use `hidden md:flex` to completely hide date inputs on mobile
- Keep them visible on tablet/desktop (md breakpoint and above)
- Mobile users rely on search and tab filtering only

**Pros:**
- Simplest implementation (just add Tailwind classes)
- Saves maximum vertical space on mobile
- Cleaner, less cluttered mobile interface
- Most mobile users don't need date filtering

**Cons:**
- Removes functionality for mobile users who might need it
- No way to filter by date on mobile at all

### Option 2: Collapsible "Advanced Filters" Section
- Add a "Filter" or "Advanced" toggle button on mobile
- Date pickers hidden by default, shown when toggle is clicked
- Use Vue reactive state to show/hide the date filter section
- Keep always visible on desktop

**Pros:**
- Preserves functionality for users who need it
- Saves space by default (most common use case)
- Progressive disclosure pattern (common in mobile UX)
- Feels more "app-like" and modern

**Cons:**
- Adds component complexity (reactive state, toggle logic)
- Extra tap required to access date filters
- Need to design the toggle button/icon

### Option 3: Single Compact Date Range Picker
- Replace two separate inputs with a single compact date range selector
- Use a more mobile-friendly date picker library (e.g., Flatpickr, Vue Datepicker)
- Show as a single input that opens a modal/popover with range selection
- More compact visual footprint

**Pros:**
- Modern, mobile-optimized UX
- Saves horizontal space significantly
- Better touch interaction
- Looks more polished

**Cons:**
- Requires adding external library dependency
- More complex implementation
- Need to handle library integration and styling
- Potential bundle size increase

### Option 4: Simplified "Quick Filters" for Mobile
- Replace date range inputs with preset buttons on mobile
- Options like: "Today", "This Week", "This Month", "All Time"
- Keep full date range picker on desktop
- Simpler, faster interaction on mobile

**Pros:**
- Very mobile-friendly (tap-based, no typing)
- Covers most common use cases
- Saves space with compact buttons
- Faster than typing dates

**Cons:**
- Less flexible (can't pick arbitrary date ranges)
- Requires backend support for preset filters
- Different UX between mobile and desktop

## Recommendation
**Option 2: Collapsible "Advanced Filters" Section**

**Rationale:**
- **Balanced approach**: Preserves functionality while saving space
- **Low risk**: No functionality is removed, just reorganized
- **Quick implementation**: Only requires Vue reactive state and CSS
- **No dependencies**: Works with existing stack
- **Familiar pattern**: Users understand collapsible sections
- **Iterative**: Can be enhanced later with Option 3 or 4 if needed

**Specific implementation:**
1. Add a "Filter" button/link on mobile (with icon, e.g., funnel or sliders)
2. Date picker section hidden by default (`v-if="showAdvancedFilters"`)
3. Clicking "Filter" toggles the date picker visibility
4. On desktop, always show date pickers (no toggle needed)
5. Add smooth transition for show/hide animation

**Alternative recommendation if simplicity is priority:**
**Option 1: Hide Date Pickers on Mobile** - If analytics show that date filtering is rarely used on mobile, this is the simplest and cleanest solution.

## Acceptance criteria
- [ ] Date picker controls don't consume excessive space on mobile (375px width)
- [ ] Mobile layout feels clean and uncluttered
- [ ] Date filtering functionality is still accessible (if Option 2, 3, or 4)
- [ ] Desktop view remains completely unchanged
- [ ] All existing date filtering logic continues to work
- [ ] Smooth, intuitive interaction on mobile
- [ ] No visual glitches or layout shifts
- [ ] Tested on actual mobile device or browser dev tools at 375px width
