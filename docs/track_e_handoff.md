# Track E — Handoff Document

## Current Commit
- Baseline: 3e06835 (track-C)
- Working Tree: uncommitted changes in progress
- Track: E — Reports UX (Account Picker)

## VERIFIED STATE (independently confirmed)

### Backend — COMPLETE
File: `app/Services/ChartAccountScope.php`
- `eligible()` exists (line ~58) — UNCHANGED per BR-E1
- `parents()` exists (line ~114) — ADDED
- `childrenOf(int $parentId)` exists (line ~142) — ADDED

File: `app/Http/Controllers/Reports/ReportCenterController.php`
- `accounts()` returns: parents, eligible, tree, cached_at (lines ~183-187)

### CSS — COMPLETE
File: `resources/css/reports/report-center.css`
- `body.rc-filter-locked` rules (lines ~179-190)
- `body.rc-empty-parent` rules (lines ~196-199)

## UNVERIFIED STATE (must be checked by new model)

### Frontend JS — IN PROGRESS
File: `resources/js/reports/reportCenter.js`
- Prior model started editing:
  - `rcSelectPickerAll` (Step 1)
  - `rcSelectPickerRow` (Step 2)
  - `hidden.bs.modal` listener (Step 5)
- COMPLETION UNKNOWN — may be:
  - Partially applied
  - Fully applied
  - Failed silently
- MUST be verified by reading the file

### Other JS Handlers — STATUS UNKNOWN
- `rcBuildAccountPicker` (remove readonly, add data-mode)
- `rcLoadPickerAccounts` (fix to read payload.accounts)
- `rcPickerParents` (prefer RC.pickerParents)
- `rcOpenAccountPicker` (accept search term)
- Keyboard handlers (Enter/Tab/Escape)
- `input` handler (smart lock)
- `dblclick` handler
- `blur` handler
- `rcLockFilters` / `rcUnlockFilters`
- `rcCancelPicker`
- `rcIsPickerOpen`
- `rcAttachPickerFieldEvents` call from `rcRenderFilters`

### Tests — NOT RUN
- npm run build — NOT RUN after last edit
- T15-T25 browser tests — NOT RUN
- Regression T1-T14 — NOT RUN since last edit

## MANDATORY PRE-CONTINUE STEPS

Before writing any code, the new model MUST:

1. Read `resources/js/reports/reportCenter.js` — full file
2. Verify which steps are already applied:
   - Does `rcSelectPickerAll` unlock + refocus? (check)
   - Does `rcSelectPickerRow` unlock + refocus + enable children? (check)
   - Is `rcLockFilters` defined? (check)
   - Is `rcUnlockFilters` defined? (check)
   - Is `rcCancelPicker` defined? (check)
   - Is `rcIsPickerOpen` defined? (check)
   - Is `rcAttachPickerFieldEvents` defined? (check)
   - Is `rcAttachPickerFieldEvents(field)` called from rcRenderFilters? (check)
   - Are keyboard handlers attached? (check)
   - Is `input` handler present? (check)
   - Is `blur` handler present? (check)
   - Is `dblclick` handler present? (check)
   - Is `e.isComposing` check present in keydown? (BR-E11)
3. Report findings BEFORE continuing.
4. Then complete missing pieces.

## DO NOT
- Trust the summary alone
- Assume JS edits succeeded
- Skip verification
- Run migrations (any kind)
- Touch forbidden files
- Run `git` commands

## Files you WILL modify (allowed)
- `resources/js/reports/reportCenter.js`
- `resources/css/reports/report-center.css` (only if needed)
- `resources/views/reports/accountPicker.blade.php` (only if needed)
- `resources/views/reports/center.blade.php` (only if needed)
- `app/Services/ChartAccountScope.php` (only if missing pieces)
- `app/Http/Controllers/Reports/ReportCenterController.php` (only if missing pieces)

## Files you MUST NOT touch
- Any file in `app/Reports/**`
- Any file in `app/Services/Backup/**`
- Any file in `app/Services/Export/**`
- `routes/web.php`
- Any Model / Migration
- `config/**`
- `composer.json` / `composer.lock`
- `package.json`
- Any file in `tests/Feature/Reports/**` (Track C)
- Any file in `tests/Feature/Backup/**`
- Any file in `tests/Feature/Export/**`
- `docs/dynamic_account_picker_plan.md`
- `docs/dynamic_account_picker_skills.md`