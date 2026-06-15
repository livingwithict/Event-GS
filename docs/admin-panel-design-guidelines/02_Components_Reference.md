# Master Admin Panel – Components Reference

## 1. Cards
- **Background**, **border**, **border‑radius**.
- **Card header**:
  - Icon (SVG)
  - Title + optional subtitle
  - Toggle chevron (collapsible body)
- **Card body** – contains form fields, lists, or other components.
- **Card footer** – often contains action buttons (e.g., “Add Item”, “Save”).

## 2. Form Elements
- **Labels** – optional meta info (character counter, helper text).
- **Text inputs** – background, border, focus ring.
- **Textareas** – resizable vertically.
- **Select dropdowns** – custom styled.
- **Slug input** – prefixed with read‑only text (e.g., `/products/`).
- **Character counters** – live update, warning color when near limit.
- **Rich Text Editor (RTE)** toolbar:
  - Bold, Italic, Underline.
  - Headings (H1, H2, H3).
  - Lists (unordered / ordered).
  - Link, image placeholder.
  - Divider between groups.
  - Editable content area (contenteditable).

## 3. Lists & Reorderable Items
- **Simple list item** (e.g., highlights, inclusions):
  - Drag handle (optional)
  - Numeric badge or bullet
  - Title/description
  - Delete icon (✕)
- **Two‑column grid list** – displays items side by side.

## 4. Collapsible Blocks (Info Blocks / Dynamic Sections)
- **Trigger** (header) contains:
  - Icon (filled / empty indicator)
  - Block name (editable)
  - Meta description
  - Status badge (Published / Hidden)
  - Chevron (rotates when open)
- **Expandable body**:
  - Title input field
  - Rich text toolbar + content area
  - Helper note (e.g., “This block is hidden because it has no content”)
- **Visibility rule**: block is considered “empty” if title is blank OR content is blank → frontend hides the entire block.

## 5. Itinerary / Day‑by‑Day List
- Each row:
  - Day label (e.g., “D1”)
  - Description text
  - Delete icon
- “Collapse all” / “Expand all” links (if days can be individually collapsed – not required but pattern exists).

## 6. Toggles (Switch)
- Two variants:
  - **Large toggle** – used in forms (e.g., “Featured Trip”, “Active”).
  - **Mini toggle** – used inside tables (e.g., enable/disable row directly).
- Visual indicator: on/off state with sliding circle.

## 7. Buttons
- **Primary** – solid background (accent).
- **Success** – green (e.g., “Add”).
- **Danger** – red (e.g., “Delete”).
- **Ghost / Outline** – transparent with border.
- Sizes: normal, small (`btn-sm`), extra small (`btn-xs`).

## 8. Tables (for list views)
- **Sticky header** on scroll.
- **Sortable columns** – click toggles ascending/descending, icon indicator.
- **Checkbox column** for bulk selection.
- **Row actions** (View, Edit, Delete) – usually hidden until row hover.
- **Empty state** – centered illustration + message.

## 9. Upload Areas (Images)
- **Drag & drop zone** (or click to browse).
- Supported formats & size limit (configurable).
- Preview thumbnail after selection (optional).
- Used for featured images, route maps, profile photos, etc.

## 10. Pagination Component
- Previous / Next buttons.
- Page number buttons.
- Ellipsis for many pages.
- Disabled state when at first/last page.

## 11. Bulk Action Bar
- Appears when at least one row is selected.
- Shows selected count.
- Action buttons (e.g., “Set Active”, “Delete”).
- “Clear” button to deselect all.

## 12. Sticky Action Bar (Edit View)
- Fixed to bottom of scrollable area.
- Contains primary actions (Save, Cancel, Publish).

## 13. Graph / Data Visualisation Placeholder
- Simple box with “Click to add data points” message.
- “Add Data Point” button (for altitude chart, etc.).
