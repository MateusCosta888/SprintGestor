# ⚡ SprintG — Gestor de Sprints

**Site em produção:** <https://gestorsprints.page.gd>

Um gestor de sprints com estética de desktop cinematográfico: janelas arrastáveis, fundo em vídeo, glassmorfismo e um fluxo de planeamento sério — projetos → sprints → tarefas com estado, prioridade e pontos. As tuas sessões de trabalho parecem uma missão, porque são.

## Funcionalidades

- **Projetos e sprints** — múltiplos projetos, cada um com sprints em badges e tarefas com estado (planeada / em curso / concluída / bloqueada), prioridade e story points.
- **Interface tipo desktop** — janelas com minimizar/maximizar, docking, menu iniciar com busca em tempo real e paleta de comandos global (Ctrl+K).
- **Estética cinematográfica** — fundo em vídeo em loop, efeitos glass, temas com cor de acento personalizável por projeto.
- **Contas na nuvem** — registo com código de acesso; sessão como bearer token em header (sem cookies, sem CSRF); dados sincronizados por conta com auto-save.
- **Acesso de IA** — cada conta pode emitir um link `/ai/<TOKEN>` (GET-only) que permite a chatbots e CLIs ler o tabuleiro, criar itens e marcar trabalho feito. Revogável em um clique.

## Stack

| Camada | Tecnologia |
|---|---|
| Frontend | `index.html` único — HTML + CSS + JS vanilla. Sem build, sem npm, sem framework. |
| Backend | PHP + MySQL (PDO, bcrypt) em `api/` |
| Dev local | `dev-server.py` — mock Python do backend com `dev-db.json` |
| Fonts | Google Fonts — Instrument Serif + Inter |

## Estrutura

```
├── index.html            # a aplicação inteira (UI + lógica + estilos)
├── api/                  # backend PHP: auth, sync de dados, gestão de conta, AI access
│   └── config.example.php  # ← template; copia para config.php
├── ai.php                # ponto de entrada do Acesso de IA (/ai/<token>)
├── dev-server.py         # servidor de desenvolvimento local (mock do backend)
├── schema.sql            # esquema MySQL (corre no phpMyAdmin)
├── DEPLOY.md             # guia completo de publicação
└── assets                # background.mp4, logo.png, posters
```

## Começar em local

```bash
# 1. Servir a aplicação (mock do backend em Python — requer apenas Python 3)
python dev-server.py

# 2. Abrir http://127.0.0.1:8000 e criar conta de teste
```

## Deploy (InfinityFree / VistaPanel)

O guia passo-a-passo está em [`DEPLOY.md`](DEPLOY.md). Resumo:

1. Criar base de dados MySQL no VistaPanel e correr `schema.sql` no phpMyAdmin.
2. Copiar `api/config.example.php` → `api/config.php` e preencher credenciais + gerar um `PEPPER` aleatório.
3. Carregar tudo para `htdocs/` — o `.htaccess` protege `config.php` e o diretório `api/`.

## Segurança

- `api/config.php` está no `.gitignore` — **nunca commitar credenciais reais**.
- Códigos de acesso guardados com bcrypt; tokens de sessão e de IA guardados apenas como SHA-256.
- O link de IA só é mostrado uma vez, tem rate-limit e kill-switch de revogação.
- Rate limiting de login por IP e por fingerprint de código.

## Licença

Projeto pessoal — todos os direitos reservados.
