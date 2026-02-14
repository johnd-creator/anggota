# Implementation Plan: Unit Dropdown Display Modification

## Goal
Modify all Unit dropdown filters to display only the unit name (`[Nama Unit]`) in the UI, while keeping the unit code as the underlying value for backend operations. This implements **Option 2** from the brainstorm: Transform data in the Inertia component.

## Assumptions
- Vue 3 + Inertia.js stack is working correctly
- Backend already provides units with `id`, `name`, and `code` fields
- Unit `id` (not `code`) is used as the value for backend queries
- No backend changes required (frontend-only)
- Browser testing can be done at `http://localhost:8000`

## Files to Modify

Based on codebase research, the following 8 files currently display `[Kode] - [Nama Unit]` format:

1. `/var/www/html/anggota/resources/js/Pages/Letters/Form.vue` (line 69)
2. `/var/www/html/anggota/resources/js/Pages/Finance/Dues/Index.vue` (line 39)
3. `/var/www/html/anggota/resources/js/Pages/Admin/Roles/Show.vue` (line 87)
4. `/var/www/html/anggota/resources/js/Pages/Admin/Members/Index.vue` (lines 133, 167)
5. `/var/www/html/anggota/resources/js/Pages/Admin/Members/Form.vue` (line 307)
6. `/var/www/html/anggota/resources/js/Pages/Admin/Onboarding/Index.vue` (line 137)
7. `/var/www/html/anggota/resources/js/Pages/Admin/Mutations/Create.vue` (line 109)

## Plan

### Step 1: Modify Letters/Form.vue
**File:** `resources/js/Pages/Letters/Form.vue` (line 69)

**Current:**
```vue
<option v-for="u in units" :key="u.id" :value="u.id">{{ u.code }} - {{ u.name }}</option>
```

**Change to:**
```vue
<option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
```

**Verify:**
```bash
# Visual inspection in browser
# Navigate to: http://localhost:8000/letters/create (or /letters/{id}/edit)
# Check:
# - Unit dropdown displays only unit names (no codes)
# - Selecting a unit still works correctly
# - Form submission sends correct unit ID
```

### Step 2: Modify Finance/Dues/Index.vue
**File:** `resources/js/Pages/Finance/Dues/Index.vue` (line 39)

**Current:**
```vue
<option v-for="u in units" :key="u.id" :value="u.id">{{ u.code }} - {{ u.name }}</option>
```

**Change to:**
```vue
<option v-for="u in units" :key="u.id" :value="u.id">{{ u.name }}</option>
```

**Verify:**
```bash
# Visual inspection in browser
# Navigate to: http://localhost:8000/finance/dues
# Check:
# - Unit dropdown displays only unit names
# - Filtering by unit works correctly
```

### Step 3: Modify Admin/Roles/Show.vue
**File:** `resources/js/Pages/Admin/Roles/Show.vue` (line 87)

**Current:**
```javascript
const unitOptions = (page.props.units||[]).map(u => ({ label: `${u.code} - ${u.name}`, value: u.id }));
```

**Change to:**
```javascript
const unitOptions = (page.props.units||[]).map(u => ({ label: u.name, value: u.id }));
```

**Verify:**
```bash
# Visual inspection in browser
# Navigate to: http://localhost:8000/admin/roles/{id}
# Check:
# - Unit dropdown in role access section displays only unit names
# - Assigning unit access still works correctly
```

### Step 4: Modify Admin/Members/Index.vue
**File:** `resources/js/Pages/Admin/Members/Index.vue` (lines 133, 167)

**Current (line 133):**
```javascript
const unitOptions = units.map(u => ({ label: `${u.code} - ${u.name}`, value: u.id }));
```

**Current (line 167):**
```javascript
function unitLabel(id){ const u = units.find(x => x.id === id); return u ? `${u.code} - ${u.name}` : `Unit ${id}`; }
```

**Change to:**
```javascript
// Line 133
const unitOptions = units.map(u => ({ label: u.name, value: u.id }));

// Line 167
function unitLabel(id){ const u = units.find(x => x.id === id); return u ? u.name : `Unit ${id}`; }
```

**Verify:**
```bash
# Visual inspection in browser
# Navigate to: http://localhost:8000/admin/members
# Check:
# - Unit filter dropdown displays only unit names
# - Unit column in table displays only unit names
# - Filtering by unit works correctly
```

### Step 5: Modify Admin/Members/Form.vue
**File:** `resources/js/Pages/Admin/Members/Form.vue` (line 307)

**Current:**
```javascript
const unitsOptions = units.map(u => ({ label: `${u.code} - ${u.name}`, value: u.id }));
```

**Change to:**
```javascript
const unitsOptions = units.map(u => ({ label: u.name, value: u.id }));
```

**Verify:**
```bash
# Visual inspection in browser
# Navigate to: http://localhost:8000/admin/members/create (or /admin/members/{id}/edit)
# Check:
# - Unit dropdown displays only unit names
# - Selecting a unit and saving works correctly
```

### Step 6: Modify Admin/Onboarding/Index.vue
**File:** `resources/js/Pages/Admin/Onboarding/Index.vue` (line 137)

**Current:**
```javascript
const unitOptions = units.map(u => ({ label: `${u.code} - ${u.name}`, value: u.id }));
```

**Change to:**
```javascript
const unitOptions = units.map(u => ({ label: u.name, value: u.id }));
```

**Verify:**
```bash
# Visual inspection in browser
# Navigate to: http://localhost:8000/admin/onboarding
# Check:
# - Unit dropdown displays only unit names
# - Filtering by unit works correctly
```

### Step 7: Modify Admin/Mutations/Create.vue
**File:** `resources/js/Pages/Admin/Mutations/Create.vue` (line 109)

**Current:**
```javascript
const unitOptions = units.map(u => ({ label: `${u.code} - ${u.name}`, value: u.id }));
```

**Change to:**
```javascript
const unitOptions = units.map(u => ({ label: u.name, value: u.id }));
```

**Verify:**
```bash
# Visual inspection in browser
# Navigate to: http://localhost:8000/admin/mutations/create
# Check:
# - Destination unit dropdown displays only unit names
# - Creating a mutation with selected unit works correctly
```

### Step 8: Build and Test Compilation
**Files:** All modified Vue files

**Changes:**
- Run Vite build to ensure no syntax errors
- Check for any console warnings or errors

**Verify:**
```bash
npm run build
# Expected: Build completes successfully with no errors
# Check terminal output for "build complete" message
```

### Step 9: Comprehensive Browser Testing
**Files:** All modified pages

**Changes:**
- Test all dropdown interactions across all modified pages
- Verify backend filtering still works correctly
- Test on different viewport sizes

**Verify:**
```bash
# Manual browser testing checklist:
# 1. Test each modified page listed in steps 1-7
# 2. Verify dropdowns show only unit names (no codes)
# 3. Select different units and verify filtering/submission works
# 4. Check browser console for any JavaScript errors
# 5. Verify backend receives correct unit IDs in network tab
# 6. Test on mobile (375px), tablet (768px), desktop (1024px+)
```

## Risks & Mitigations

| Risk | Severity | Mitigation |
|------|----------|------------|
| Missing some dropdown instances | Medium | Comprehensive grep search performed, all instances identified |
| Backend expects code instead of ID | Low | Backend already uses unit ID (verified in controllers) |
| User confusion without codes | Low | Unit names should be descriptive enough; codes were visual clutter |
| Build errors from syntax | Low | Simple string template changes, low risk |
| Inconsistent display across pages | Low | All instances updated in single task |

## Rollback Plan

If issues arise:
1. **Git revert**: All changes are isolated to 7 Vue files
2. **File-level rollback**: Keep backups of original files
3. **Quick fix**: All changes are display-only, no logic changes

Files to backup before changes:
- `resources/js/Pages/Letters/Form.vue`
- `resources/js/Pages/Finance/Dues/Index.vue`
- `resources/js/Pages/Admin/Roles/Show.vue`
- `resources/js/Pages/Admin/Members/Index.vue`
- `resources/js/Pages/Admin/Members/Form.vue`
- `resources/js/Pages/Admin/Onboarding/Index.vue`
- `resources/js/Pages/Admin/Mutations/Create.vue`

## Success Criteria

- ✅ All unit dropdowns display only unit names (no codes visible)
- ✅ Unit codes remain as values in backend requests (unit IDs actually)
- ✅ All filtering functionality works correctly
- ✅ All form submissions work correctly
- ✅ No console errors or build failures
- ✅ Consistent display across all pages
- ✅ Responsive design maintained on all viewport sizes
- ✅ Backend receives correct unit IDs in all requests
