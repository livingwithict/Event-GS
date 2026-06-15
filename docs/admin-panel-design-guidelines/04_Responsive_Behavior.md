
---

## File 4: `04_Responsive_Behavior.md`

```markdown
# Master Admin Panel – Responsive Behavior (excluding fonts/colors)

## 1. Breakpoints (implied)
- **Desktop**: two‑column layout for edit view, sidebar visible.
- **Tablet**: sidebar may be collapsed into a hamburger menu (not shown but assumed).
- **Mobile**: single column layout, stacked cards, top bar simplified.

## 2. Sidebar
- On narrow screens, sidebar should be hidden by default, with a toggle button to open an overlay or slide‑in menu.

## 3. Two‑Column Edit Grid
- On screens smaller than ~1000px, `grid-template-columns: 1fr` (stacks left and right columns).

## 4. Table Responsiveness
- Table may overflow horizontally; a horizontal scrollbar appears on the container.
- No column hiding; user can scroll horizontally.

## 5. Card Grid for List Views
- Uses `grid-template-columns: repeat(auto-fill, minmax(200px, 1fr))` – automatically wraps.

## 6. Bulk Action Bar
- On very narrow screens, buttons may wrap or become a dropdown menu.

## 7. Top Bar
- Global search may shrink or become an expandable icon on mobile.

## 8. Pagination
- On mobile, page buttons may be reduced (show only prev/next and current page).

## 9. Forms
- Input fields and labels stack vertically by default – no horizontal `form-row` on narrow screens (but design shows grid that will collapse).
