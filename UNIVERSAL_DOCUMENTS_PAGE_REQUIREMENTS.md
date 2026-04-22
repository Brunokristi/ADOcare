# Universal Documents Page Technical Requirements

## Purpose
Create a single, reusable Documents view that unites existing document viewer pages in `resources/js/pages/Documents/` and supports both:
- viewer presentation
- downloadable document output

The goal is to avoid duplicated document templates, separate viewer UI from download/pdf generation, and allow a single document shell to handle document-specific requirements.

## Scope
This technical document applies to the following frontend pages and page types:
- `resources/js/pages/Documents/Agreement.vue`
- `resources/js/pages/Documents/CP.vue`
- `resources/js/pages/Documents/Dekurz.vue`
- `resources/js/pages/Documents/DZC.vue`
- `resources/js/pages/Documents/KilometersShow.vue`
- `resources/js/pages/Documents/PointsShow.vue`
- `resources/js/pages/Documents/InvoiceShow.vue`
- `resources/js/pages/Documents/Leave.vue`
- `resources/js/pages/Documents/Nalez.vue`
- `resources/js/pages/Documents/Proposal.vue`
- `resources/js/pages/Documents/Record.vue`

## Problem Statement
Current document pages duplicate:
- print/download toolbar logic
- print/iframe wrapping and CSS
- API fetch and data-loading patterns
- rendering templates for preview vs downloadable output

Some document pages require viewer-only interactivity, while their downloadable output is a different payload or format. This must be handled without creating multiple document templates per document type.

## Objectives
1. Create a reusable document shell component.
2. Maintain separate viewer content and download output flows.
3. Keep document-specific pages as thin wrappers that pass configuration.
4. Prefer server-rendered Blade HTML for PDF/download preview.
5. Ensure viewer-only buttons are hidden in print/download.
6. Support non-preview viewer content with downloadable output.

## Requirements

### Functional Requirements
- `DocumentShell` must:
  - accept a document title
  - accept a document ID and document type
  - accept a preview source URL or document-specific viewer slot
  - accept download endpoint configuration
  - accept optional download payload for POST downloads
  - render shared toolbar actions
  - hide viewer-only actions in print mode

- Document-specific components must:
  - only provide data and configuration for the shell
  - render document-specific UI via slots
  - not reimplement core toolbar/print/download boilerplate

- The viewer must support:
  - direct HTML preview of the document
  - a generic content area for metadata/summary/editor views
  - a server-rendered preview mechanism for Blade-rendered HTML

- The download flow must support:
  - GET file downloads
  - POST/download payload downloads
  - PDF generation where the backend renders a document template
  - downloads that are not identical to the viewer display

- Document pages requiring special export formats must be supported, including:
  - TXT export (`KilometersShow.vue`)
  - CSV export (`DZC.vue`)
  - server-rendered PDF export for agreements, dekurz, leave, invoice, proposal, etc.

### Non-functional Requirements
- Use shared CSS classes such as `no-print` and `viewer-only`
- Avoid duplicate print/iframe creation logic in multiple pages
- Keep the viewer responsive and stable for large documents
- Support A4 page rendering for printable preview
- Keep the frontend architecture consistent with existing Vue app patterns

## Proposed Architecture

### 1. Shared page wrapper
Create a generic component at:
- `resources/js/components/DocumentShell.vue`

It should expose props:
- `title: string`
- `documentId: string | number`
- `documentType: string`
- `previewUrl?: string`
- `downloadUrl?: string`
- `downloadMethod?: 'get' | 'post'`
- `downloadPayload?: object`
- `downloadFilename?: string`
- `downloadContentType?: string`
- `showPreview?: boolean`
- `printMode?: 'client' | 'iframe' | 'server'`
- `viewerOnlyControls?: boolean`
- `downloadLabel?: string`

Slots:
- `default` — main viewer content
- `actions` — additional buttons
- `preview` — custom preview component or server-rendered iframe
- `metadata` — optional metadata area

### 2. Server-rendered preview component
Create a component at:
- `resources/js/components/ServerRenderedDocumentPreview.vue`

It should:
- accept `src` URL
- fetch and render HTML from backend
- allow print of the server-rendered page
- show loading state

### 3. Document route wrappers
Refactor the existing document pages into wrappers whose responsibilities are:
- determine `documentId` from route params
- choose endpoints for preview/download
- fetch any viewer-specific data if needed
- render `DocumentShell` with slots

Example wrapper patterns:
- `Documents/Agreement.vue` => `DocumentShell + AgreementView slot`
- `Documents/Dekurz.vue` => `DocumentShell + DekurzSlot + pagination logic`
- `Documents/KilometersShow.vue` => `DocumentShell + cover sheet slot`

### 4. Document-specific behavior
- `DZC.vue`: viewer shows document summary and controls; download button triggers CSV download
- `KilometersShow.vue`, `PointsShow.vue`: viewer shows cover sheet; download action submits payload to batch download endpoint
- `Nalez.vue`: scanned image viewer with OCR editing; print/download should not show editor controls
- `Dekurz.vue`: multi-page printable document with page-break logic; still use same backend template for PDF output
- `Agreement.vue`, `CP.vue`, `Leave.vue`, `Record.vue`, `Proposal.vue`, `InvoiceShow.vue`: document preview + print and server-rendered download support

## Backend contract
Backend should expose standard endpoints for each document type:
- `GET /documents/{type}/{id}/preview` — full HTML preview render
- `GET /documents/{type}/{id}/download` — file download
- optional format query param for PDF: `?format=pdf`
- support POST download payloads when necessary

Backend must reuse the same Blade template for:
- in-app preview
- PDF generation/download

## Print/pdf rules
- all viewer-only UI must be hidden in print mode
- document HTML should be wrapped in a print-safe container
- apply shared print styles under `@media print`
- use page-break rules for multi-page docs
- the viewer can still provide interactive controls outside the printable document portion

## Sample document shell API
```ts
interface DocumentShellProps {
  title: string
  documentId: string | number
  documentType: string
  previewUrl?: string
  downloadUrl?: string
  downloadMethod?: 'get' | 'post'
  downloadPayload?: Record<string, any>
  downloadFilename?: string
  downloadContentType?: string
  printMode?: 'client' | 'iframe' | 'server'
}
```

Slots:
- `default` — viewer body
- `actions` — extra buttons
- `preview` — server-rendered preview / iframe wrapper
- `metadata` — additional document summary

## Acceptance Criteria
- `resources/js/components/DocumentShell.vue` exists
- shared toolbar and download logic are centralized
- document view wrappers are thin and declarative
- viewer content can differ from downloadable content
- server-rendered Blade preview endpoint is supported for PDF output
- `no-print` viewer controls are hidden when printing
- documents can still download PDF/TXT/CSV without duplicate templates

## Recommended implementation steps
1. Add `DocumentShell.vue` and `ServerRenderedDocumentPreview.vue`
2. Refactor one document page as a proof of concept (e.g. `Agreement.vue`)
3. Add a standard preview endpoint for the backend Blade template
4. Refactor remaining document pages into wrappers
5. Add shared print style utilities and hide viewer-only controls
6. Validate with one PDF download and one TXT/CSV download flow

## Notes
- This document is intentionally frontend-focused, but it strongly requires a backend preview endpoint.
- `DocumentsPage.vue` stays as a router shell and is not the document shell itself.
- `KilometersShow.vue` and `PointsShow.vue` are examples where preview and download are intentionally different.
