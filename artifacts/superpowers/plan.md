# Implementation Plan: Role Detail Page Stacked Layout

## Goal
Convert the role detail page from a 2-column side-by-side layout to a stacked (vertical) layout to give the "Assign ke User" form more horizontal space and improve UX.

## Current State Analysis

**File:** [`resources/js/Pages/Admin/Roles/Show.vue`](file:///var/www/html/anggota/resources/js/Pages/Admin/Roles/Show.vue)

**Current Layout (Line 3):**
```vue
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-4">
```

- Left card (col-span-1): Role details + Assign form
- Right card (col-span-2): Users table

**Problem:**
- Left card is too narrow (~33% width on desktop)
- Email input and Unit dropdown are cramped
- Assign button positioning is awkward

## Proposed Changes

### Change 1: Convert to Stacked Layout
**File:** `resources/js/Pages/Admin/Roles/Show.vue` (Line 3)

**From:**
```vue
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-4">
```

**To:**
```vue
<div class="flex flex-col gap-6 mt-4">
```

**Rationale:** Remove the grid-cols-3 layout and use flex-col to stack cards vertically.

### Change 2: Adjust Card Widths
**File:** `resources/js/Pages/Admin/Roles/Show.vue` (Lines 4-17, 19-48)

**Changes:**
- Remove `lg:col-span-2` from the table card (line 19)
- Optionally add max-width constraints for better readability
- Ensure both cards take full width

### Change 3: Improve Form Layout
**File:** `resources/js/Pages/Admin/Roles/Show.vue` (Line 11)

**Current:**
```vue
<div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
```

**Potential improvement:**
- Keep as-is for now (already responsive)
- Form will automatically benefit from wider parent container

## Verification Plan

### Step 1: Build Verification
```bash
npm run build
```
**Expected:** Build completes successfully with no errors

### Step 2: Visual Testing (Browser)
1. Navigate to `http://localhost:8000/admin/roles/3`
2. Verify layout changes:
   - [ ] Role details card is at the top (full width)
   - [ ] Users table card is below (full width)
   - [ ] Email input is wider and more readable
   - [ ] Unit dropdown has more space
   - [ ] Assign button is well-positioned

### Step 3: Functionality Testing
1. Test "Assign ke User" form:
   - [ ] Enter email address
   - [ ] Select unit (if admin_unit role)
   - [ ] Click "Assign" button
   - [ ] Verify user is added to table
2. Test remove user:
   - [ ] Click delete icon on a user
   - [ ] Confirm removal in modal
   - [ ] Verify user is removed from table

### Step 4: Responsive Testing
1. Test at different breakpoints:
   - [ ] Desktop (1024px+): Stacked layout
   - [ ] Tablet (768px): Stacked layout
   - [ ] Mobile (375px): Stacked layout, form fields stack vertically

## Risk Mitigation

- **Minimal changes:** Only modifying layout classes, no logic changes
- **Responsive:** Layout already stacks on mobile, so no regression
- **Reversible:** Easy to revert if needed
- **No functionality impact:** All existing features remain unchanged

## Success Criteria

- ✅ Cards are stacked vertically on all screen sizes
- ✅ "Assign ke User" form has more horizontal space
- ✅ Email input is wider and easier to read
- ✅ Unit dropdown is more comfortable to use
- ✅ All existing functionality works correctly
- ✅ No visual glitches or layout issues
- ✅ Responsive behavior is maintained
