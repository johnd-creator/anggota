## Goal
Improve the UX of the role detail page (`/admin/roles/3`) by optimizing the layout to give the "Assign ke User" form more horizontal space, making it easier to use and more visually balanced.

## Constraints
- Must maintain all existing functionality (role details display, assign user form, users table)
- Should work within existing Vue 3 + Inertia.js + Tailwind CSS stack
- Must remain responsive (mobile and desktop)
- Should follow existing design system patterns
- Cannot remove or hide any existing information

## Known context
From the screenshot and analysis:
- **Current layout:** 2-column side-by-side grid (approximately 50/50 split)
- **Left card contains:**
  - Role details (Name, Label, Deskripsi, Domain Whitelist, Assign to User)
  - "Assign ke User" form with Email, Unit Pembangkit dropdown, and Assign button
- **Right card contains:**
  - "Pengguna dengan role ini" table showing assigned users
  - Table has columns: Nama, Email, and action buttons (Kontrol)
  
- **Current UX problems:**
  - Left card is too narrow (~50% width)
  - Email input field is cramped and hard to read long email addresses
  - Unit Pembangkit dropdown feels squeezed
  - Assign button positioning looks awkward in narrow space
  - Right table has plenty of empty horizontal space
  - Visual imbalance: form struggling for space while table has room to spare

## Risks
- **Layout shifts:** Changing from side-by-side to stacked might feel jarring to existing users
- **Vertical scrolling:** Stacked layout increases page height, requiring more scrolling
- **Mobile responsiveness:** Need to ensure new layout works well on mobile devices
- **Information hierarchy:** Need to maintain clear visual hierarchy between role info and user assignment
- **Table visibility:** If table is moved below, users might not immediately see who has the role

## Options

### Option 1: Stacked Layout (Full-Width Cards) ⭐ RECOMMENDED
- Move the table card below the role details/assign form card
- Role details + Assign form gets full width (or constrained max-width for readability)
- Table gets full width below
- Clear vertical hierarchy: Role Info → Assign Form → Current Users

**Pros:**
- ✅ Maximum horizontal space for form inputs
- ✅ Email and dropdown fields can breathe
- ✅ Table can also expand to full width if needed
- ✅ Clear, logical top-to-bottom flow
- ✅ Easier to implement (change grid to flex-col)
- ✅ Better mobile responsiveness (already stacked)

**Cons:**
- ⚠️ Requires scrolling to see the users table
- ⚠️ Longer page height
- ⚠️ Users might not immediately see who currently has the role

### Option 2: Asymmetric Grid (70/30 or 60/40)
- Keep side-by-side layout but give left card more space
- Left card: 60-70% width
- Right card: 30-40% width
- Form gets more breathing room while table remains visible

**Pros:**
- ✅ Form gets more space without going full-width
- ✅ Table still visible without scrolling
- ✅ Maintains side-by-side feel
- ✅ Less drastic change from current layout

**Cons:**
- ⚠️ Table becomes very narrow (might need horizontal scroll or column hiding)
- ⚠️ Still somewhat cramped compared to full-width
- ⚠️ Harder to make responsive on mobile
- ⚠️ Might look visually unbalanced

### Option 3: Tabbed Interface
- Create tabs: "Role Details & Assign" and "Assigned Users"
- Each tab gets full width
- Users switch between viewing role info/assigning and seeing current users

**Pros:**
- ✅ Maximum space for both sections
- ✅ Clean, focused interface
- ✅ Modern pattern, familiar to users

**Cons:**
- ⚠️ Hides one section at a time (can't see both simultaneously)
- ⚠️ Extra click required to switch views
- ⚠️ Might be overkill for this use case
- ⚠️ More complex implementation

### Option 4: Collapsible Sections with Priority Layout
- Role details at top (full-width, collapsible)
- Assign form in prominent position (full-width or 60%)
- Users table collapsible or below
- Prioritizes the assignment workflow

**Pros:**
- ✅ Flexible - users can collapse what they don't need
- ✅ Assign form gets priority and space
- ✅ Reduces visual clutter

**Cons:**
- ⚠️ More complex interaction
- ⚠️ Requires state management for collapse/expand
- ⚠️ Might be too much interaction for a simple page

## Recommendation
**Option 1: Stacked Layout (Full-Width Cards)**

**Rationale:**
- **Solves the core problem:** Gives the "Assign ke User" form maximum horizontal space
- **Simple implementation:** Just change grid layout from `grid-cols-2` to `flex-col` or single column
- **Better UX:** Clear visual hierarchy - users see role info, then assign form, then current users
- **Mobile-friendly:** Already naturally stacked on mobile, so no responsive issues
- **Logical flow:** Top-to-bottom workflow matches user mental model (view role → assign user → see who has it)
- **Maintainable:** Minimal code changes, easy to understand

**Specific implementation:**
1. **Top section (full-width):** Role details (Name, Label, Deskripsi, Domain Whitelist)
2. **Middle section (full-width or max-w-2xl):** "Assign ke User" form with spacious inputs
3. **Bottom section (full-width):** "Pengguna dengan role ini" table

**Visual improvements:**
- Email input can be wider, easier to read long addresses
- Unit Pembangkit dropdown more comfortable
- Assign button better positioned
- Table can expand to show more columns if needed
- Better use of whitespace overall

**Alternative consideration:**
If you strongly prefer keeping the table visible without scrolling, **Option 2 (Asymmetric Grid 60/40)** would be the second-best choice, though it's a compromise solution.

## Acceptance criteria
- [ ] "Assign ke User" form inputs are wider and more comfortable to use
- [ ] Email input can display long email addresses without truncation
- [ ] Unit Pembangkit dropdown is spacious and easy to interact with
- [ ] Assign button is well-positioned and accessible
- [ ] Role details remain clearly visible and readable
- [ ] Users table displays all information without horizontal scroll
- [ ] Layout is responsive on mobile devices (stacks properly)
- [ ] Visual hierarchy is clear and logical
- [ ] No functionality is lost or hidden
- [ ] Page feels balanced and professional
