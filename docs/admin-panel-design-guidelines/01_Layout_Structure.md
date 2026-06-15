# Master Admin Panel – Layout Structure

## 1. Overall Page Container
- **Full viewport height** with `overflow: hidden`.
- Two main columns: fixed‑width **Sidebar** and flexible **Main Area**.

## 2. Sidebar (fixed width)
- **Logo area** at the top (icon + text).
- **Navigation menu** – grouped into expandable/collapsible sections (optional groups). Each menu item contains:
  - An icon (SVG)
  - Label text
  - Optional badge (number or status)
- **Footer user area** – shows avatar, name, role (any user context).
- **Scrollable** if navigation exceeds viewport height.

## 3. Main Area
- **Top Bar** (fixed height):
  - Breadcrumb navigation (supports back links).
  - Global search input (icon + text field).
  - Action icons (notifications, settings, etc.).
- **Page Header**:
  - Title (e.g., “Trips”, “Users”, “Products”).
  - Status pill (e.g., “Draft”, “Published”).
  - Action buttons (e.g., “Add New”, “Import”, “Export”).
- **Scrollable Content Area** (fills remaining height).

## 4. List View Layout (generic)
- **Toolbar**:
  - Search input.
  - Filter dropdowns.
  - View mode switcher (table / card).
  - Right‑aligned actions.
- **Status tabs** – quick filters (e.g., All, Active, Draft, Archived).
- **Bulk action bar** – appears when items are selected; shows count and action buttons (Activate, Deactivate, Delete, etc.).
- **Data display**:
  - Table (with sortable columns, checkboxes, row actions) OR
  - Card grid (for items like team members, products).
- **Footer**:
  - Results info (showing X–Y of Z items).
  - Per‑page selector.
  - Pagination controls.

## 5. Edit View Layout (generic)
- Same top bar and page header as list view, with a back button to return to the list.
- **Two‑column layout** on wide screens (collapses to one column on narrow):
  - **Left column** – main content area (long form, rich text, dynamic blocks).
  - **Right column** – side panel for settings, metadata, images, etc.
- **Sticky action bar** at the bottom of the edit view (Save, Cancel, Publish, etc.).

## 6. Hidden / Toggleable Views
- Different “views” (list, edit, details) exist in the same page, shown/hidden via JavaScript.
- Only one view visible at a time.
