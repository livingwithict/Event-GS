# Master Admin Panel – Interaction Behaviors

## 1. Collapsible Cards
- Clicking the chevron in a card header toggles the visibility of the card body.
- Chevron rotates when open.
- State is client‑side only (not persisted unless needed).

## 2. Dynamic Block Status (Info Blocks)
- **Live update** based on title input and rich content:
  - If title non‑empty AND content non‑empty → status = “Published”, icon filled, badge “Published”.
  - Else → status = “Hidden”, icon empty, badge “Hidden”.
- The block’s display name in the header changes to the custom title (if provided) or falls back to a placeholder.
- Helper note is shown/hidden accordingly.

## 3. Toggle Switches
- Click toggles between on/off.
- No confirmation required for toggle action.
- In table rows, toggles can directly update the underlying data (without needing to open an edit form).

## 4. Sorting Tables
- Click on a column header that is marked as `sortable`.
- Toggle between ascending (`↑`) and descending (`↓`).
- Only one column sorted at a time; previously sorted column loses sort indicator.
- Sorting applies to the current filtered dataset.

## 5. Filtering & Search
- Text search (debounced recommended) – filters by title/name.
- Dropdown filters – exact match.
- Status tabs – quick filter by a predefined status field.
- All filters combine with **AND** logic.
- Changing any filter resets pagination to page 1.

## 6. Pagination
- Per‑page selector (10, 20, 50) – persists during the session.
- Page navigation buttons update the displayed rows without reloading the page.
- “Prev” and “Next” disabled appropriately.

## 7. Bulk Selection
- “Select all” checkbox selects/deselects all items **on the current page**.
- Individual row checkboxes add/remove item IDs from a set.
- Bulk action bar appears when set size > 0.
- Bulk actions (e.g., delete, change status) apply to **all selected items across pages**.
- After bulk action, selection set is cleared and table refreshed.

## 8. Row Actions (Edit, Delete, View)
- **View** – opens a preview or read‑only modal (not detailed).
- **Edit** – switches to the edit view for that item, loading its data.
- **Delete** – shows a confirmation dialog before removing.

## 9. Unsaved Changes Warning
- When leaving an edit view (e.g., clicking breadcrumb, cancel button, or closing) while there are unsaved modifications, a browser confirmation dialog should appear.

## 10. Drag & Drop (Reorderable Lists)
- Drag handle indicator (≡) present but actual drag‑and‑drop logic must be implemented separately. Required for reordering highlights, itinerary days, etc.

## 11. Collapse/Expand All (for Itinerary or FAQ groups)
- Links that trigger all items to expand or collapse. Not every list requires this, but pattern available.

## 12. Live Character Counters
- As user types, remaining characters are shown.
- When limit is exceeded, counter turns warning colour and input may block further typing (or just warn).

## 13. Slug Field
- Prefix is read‑only (e.g., `/trips/`).
- User types the slug suffix.
- Optionally generate slug from title on blur and ask for confirmation.

## 14. Image Upload Areas
- Click to open file picker, or drag file over area.
- Validate file type and size.
- Show preview after selection.
- Provide a way to remove/change the uploaded image.

## 15. Sticky Save Bar
- Always visible at the bottom of the scrollable area in edit view.
- Contains at least “Cancel”, “Save Draft”, “Publish”.
- Cancel returns to list without saving.
- Save Draft stores current state but does not make item publicly visible.
- Publish makes item live (and may change status from draft to published).
