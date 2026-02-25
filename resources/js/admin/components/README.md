# Admin Component Library

## Structure
- `ui/`: low-level primitives (`UiButton`, `UiInput`, `UiSelect`, `UiCard`, `UiTabs`, `UiModal`, `UiDrawer`, `UiSkeleton`, `UiBadge`)
- `feedback/`: UX states (`ErrorBanner`, `EmptyState`, `StatusBadge`, `ConfirmDialog`, `JsonViewer`, `ToastViewport`)
- `data/`: data display helpers (`DataTable`, `FilterBar`)
- `layout/`: app shell helpers (`AdminAppShell`, `PageHeader`, `BreadcrumbsNav`)
- `search/`: command palette (`CommandPalette`)

## Usage Guidelines
- Use `UiCard` for all section containers to keep spacing and contrast consistent.
- Use `ErrorBanner` for API and validation errors, never raw `alert()`.
- Use `ConfirmDialog` for destructive actions (revoke/reset/rotate/delete).
- Use `DataTable` for tabular pages and keep filtering in `FilterBar`.
- Use `StatusBadge` for status/event labels to preserve consistent tones.
- Use `ToastViewport` for non-blocking success/error feedback.

## Accessibility Notes
- All buttons and interactive primitives include visible focus rings.
- `UiModal` and `UiDrawer` are rendered with `aria-modal` and dismiss-on-overlay-click.
- Tables use semantic `<table>`, `<thead>`, `<tbody>`, and header cells.
