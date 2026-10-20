# SprintG — Gestor de Sprints

Gestor de sprints em estilo desktop cinematográfico: janelas, paleta de comandos, projetos e sprints com persistência em nuvem.

**Site em produção:** <https://gestorsprints.page.gd>

## Stack

- Frontend: `index.html` único — HTML + CSS + JS vanilla, sem build.
- Backend: PHP + MySQL (`api/`), com dev server local em Python (`dev-server.py`).
- Assets: `background.mp4`, fontes Google Fonts (Instrument Serif + Inter).

## Começar

1. Copia `api/config.example.php` → `api/config.php` e preenche as credenciais da BD e o `PEPPER`. (O `config.php` real está no `.gitignore` — nunca o commitess.)
2. Local: `python dev-server.py` (usa `dev-db.json` como base de dados de desenvolvimento).
3. Produção: segue o `DEPLOY.md` (InfinityFree / VistaPanel).

## Documentação

| Ficheiro | Conteúdo |
|---|---|
| `PRODUCT.md` | Contexto de produto |
| `DESIGN.md` | Sistema visual normativo |
| `AGENTS.md` | Regras de desenvolvimento do projeto |
| `DEPLOY.md` | Passo a passo de publicação |
| `MEMORY.md` | Lições acumuladas |
| `schema.sql` | Esquema da base de dados |
