# Goal
Modify the 'Unit' dropdown filter to display only the unit name (`[Nama Unit]`) in the UI, while keeping the unit code as the underlying value for backend operations.

# Constraints
- The unit code must remain as the value used in the backend/database queries
- Only the visual label in the dropdown should change
- Existing functionality and data flow must not be disrupted
- Changes should be localized to the dropdown display logic

# Known context
- The application is a Laravel + Inertia.js application (based on open files showing Inertia middleware)
- Current dropdown format: `[Kode] - [Nama Unit]`
- The dropdown is used for filtering purposes
- Unit data likely comes from a database model with both `kode` and `nama` fields

# Risks
1. **Data inconsistency**: If the dropdown value binding is not properly configured, the wrong data might be sent to the backend
2. **Multiple dropdown instances**: There may be multiple places where unit dropdowns are used, requiring changes in several files
3. **Backend expectations**: Backend code might expect a specific format and could break if the value structure changes unexpectedly
4. **User confusion**: Users accustomed to seeing the code might initially be confused by its absence

# Options

## Option 1: Modify the data transformation in the controller
- Transform the unit data in the backend controller before sending to the frontend
- Use Laravel's `map()` or `transform()` to create a `label` field with only the name
- Keep the `value` field as the code
- **Pros**: Centralized change, easy to maintain
- **Cons**: Requires identifying all controllers that provide unit data

## Option 2: Transform data in the Inertia component
- Keep backend data unchanged
- Transform the display in the Vue/React component using computed properties
- Use `:label` and `:value` props separately in the dropdown component
- **Pros**: No backend changes needed, flexible frontend control
- **Cons**: May need to update multiple components if unit dropdowns are reused

## Option 3: Create a custom accessor in the Unit model
- Add a `display_name` accessor to the Unit model that returns only the name
- Use this accessor wherever unit dropdowns are rendered
- **Pros**: Reusable across the application, follows Laravel conventions
- **Cons**: Requires updating all dropdown implementations to use the new accessor

## Option 4: Modify the dropdown component itself
- If there's a shared dropdown component, modify it to accept separate label and value formatters
- Configure unit dropdowns to display name only while using code as value
- **Pros**: DRY principle, single point of change if component is shared
- **Cons**: Requires a shared component to exist

# Recommendation
**Option 2** (Transform data in the Inertia component) combined with **Option 4** (if a shared component exists).

**Rationale**:
- Keeps backend logic clean and unchanged
- Provides maximum flexibility for frontend display
- If a shared dropdown component exists, modifying it once will fix all instances
- Easy to test and verify visually
- Minimal risk to existing backend functionality

**Implementation approach**:
1. Locate the unit dropdown component(s) in `resources/js`
2. Identify if a shared dropdown component is used (e.g., Select, Dropdown, etc.)
3. Modify the component to use `:options` with `label` (nama only) and `value` (kode)
4. Test to ensure the correct value is still sent to the backend

# Acceptance criteria
- [ ] Unit dropdown displays only `[Nama Unit]` in the UI (no code visible)
- [ ] Unit code is still used as the value in form submissions and API requests
- [ ] Backend filtering logic continues to work correctly with unit codes
- [ ] All instances of unit dropdowns across the application are updated
- [ ] No console errors or warnings in the browser
- [ ] Manual testing confirms filtering works as expected
