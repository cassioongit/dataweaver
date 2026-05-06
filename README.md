# Dataweaver PRO - Intelligent Data Reconciliation

Dataweaver PRO is an internal product for **Vogel Ortodontia** that modernizes the import, reconciliation, and audit flow for patient data. It connects modern spreadsheet inputs (`CSV`, `XLS`, `XLSX`) to a legacy `.DBF` ecosystem.

## Source of Truth

Use these files as the canonical references for the project:

- [agf-this-project.md](/Users/cassiomachado/Documents/Development/dataweaver/agf-this-project.md) - local rules, architecture decisions, and security
- [_bmad-output/A-Product-Brief/project-brief.md](/Users/cassiomachado/Documents/Development/dataweaver/_bmad-output/A-Product-Brief/project-brief.md) - product narrative, users, and success criteria
- [_bmad-output/A-Product-Brief/platform-requirements.md](/Users/cassiomachado/Documents/Development/dataweaver/_bmad-output/A-Product-Brief/platform-requirements.md) - technical and operational constraints
- [DESIGN.md](/Users/cassiomachado/Documents/Development/dataweaver/DESIGN.md) - visual system source of truth

## Archived Files

- [docs/archive/](/Users/cassiomachado/Documents/Development/dataweaver/docs/archive/) - legacy notes and documents that are no longer authoritative

## What the system does

- Imports CSV files with `;` as the delimiter and XLS/XLSX spreadsheets
- Generates a preview before any DBF write
- Keeps backups and an audit trail
- Exposes history and processing logs
- Allows authenticated download of the current database
- Restricts access to authorized corporate users

## DBF Safety Rule

- The DBF header must remain unchanged and in DBASE III format.
- Any operation that could change the DBF structure must first create a copy.
- Processing and writes happen on the copy, never on the original file.

## Stack

- **Frontend**: React 18 + Vite 6
- **UI**: Tailwind CSS + shadcn/ui
- **Backend**: PHP 8.2+
- **Auth**: Supabase Auth
- **Data**: Legacy DBF + PostgreSQL/Supabase

## Runtime Contract

- The frontend requires `VITE_SUPABASE_URL` and `VITE_SUPABASE_PUBLISHABLE_KEY` or `VITE_SUPABASE_ANON_KEY`.
- The backend requires `SUPABASE_URL` and `SUPABASE_PUBLISHABLE_KEY` or `SUPABASE_ANON_KEY`.
- `VITE_API_ORIGIN` is optional and should only be used to point the frontend at a separate local API origin.
- The app must continue to run under the `/dataweaver/` base path in both development and production.
- Missing auth configuration must be shown explicitly in the UI instead of silently falling back to a stubbed login state.

## Run locally

### Prerequisites

- Node.js 18+
- PHP 8.2+

### Development

```bash
npm install
npm run dev:all
```

This starts:
- Frontend on `http://127.0.0.1:5173/dataweaver/`
- PHP API on `http://127.0.0.1:8888`

### Useful scripts

- `npm run dev` - start the frontend on `127.0.0.1:5173`
- `npm run api` - start the PHP API on `127.0.0.1:8888`
- `npm run dev:all` - start frontend and API together
- `npm run build` - build the distribution bundle
- `npm run lint` - run lint

## Main structure

- `/src` - React frontend
- `/api` - PHP endpoints
- `/api/database` - DBF files and history
- `/agf` - project governance
- `/_bmad-output` - briefing, analysis, and evolution artifacts

## Note

This is a brownfield project. The priority is not to reinvent the base, but to preserve what already works, reduce risk, and evolve incrementally.
