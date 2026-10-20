# Spec · Agent Access (IA + CLI) — SprintG

Data: 2026-09-27 · Estado: aprovada em brainstorming (escopo, validade, abordagens, 5 secções) · Não-git: este ficheiro é a fonte, sem commit.

## 1. Objetivo e decisões fixadas

Permitir que qualquer ferramenta com HTTP — chatbots com navegador (ChatGPT, Gemini, Copilot) e CLIs (`curl`) — **leia o tabuleiro e crie/marque trabalho na conta do utilizador** através de um link exclusivo, sem login de sessão.

Decisões fechadas com o utilizador:

| Decisão | Escolha |
|---|---|
| Escopo do link | **Ler + criar + mudar estado** (nunca editar textos nem apagar) |
| Validade | **Válido até revogar** (revolução instantânea no painel; regenerar disponível) |
| Transporte | **GET-only por URL** — chatbots de browsing não fazem POST nem correm JS de forma fiável |
| Autoria | Itens criados via agente levam `src:'ai'`; badge "IA" no painel |
| Arquitetura rejeitada | Página-agente JS (bots não correm JS) · MCP server (hosting não suporta) — ficam como evoluções futuras |

Não-objetos (YAGNI declarado): edição de conteúdo, eliminação remota, multi-link por conta, CLI `sprintg`, notificações à IA.

## 2. Fluxo ponta-a-ponta

1. Painel → modal da conta → bloco **"Acesso de IA"** → **Gerar link de IA**.
2. Servidor gera token de 32 caracteres, guarda só `sha256(token)`, devolve `https://dominio/ai/<TOKEN>`.
3. Utilizador usa **"Copiar prompt pronto"**: texto com o link + instrução "abre este link e segue rigorosamente as instruções que ele contém".
4. IA abre o link → lê o **manual JSON** → consulta `op=board` → executa N chamadas `op=create` / `op=status`.
5. Painel recebe as novidades pelo sync normal (merge server-side, §6); itens criados pela IA mostram badge **IA**.
6. Se a IA alucinar: **"Limpar criações da IA"** remove tudo o que tem `src:'ai'`. Se o link escapar: **Revogar**.

## 3. Modelo de dados (MySQL)

```sql
ALTER TABLE accounts
  ADD COLUMN agent_hash CHAR(64) NULL UNIQUE,        -- sha256 do token; NULL = sem link
  ADD COLUMN agent_created_at DATETIME NULL;

CREATE TABLE ai_ops (
  id INT AUTO_INCREMENT PRIMARY KEY,
  account_id INT NOT NULL,
  ts DATETIME NOT NULL,
  op VARCHAR(16) NOT NULL,          -- manual | board | create | status
  target VARCHAR(120) NULL,         -- "sprint:Fundação" etc.
  ip VARBINARY(16) NULL,
  INDEX idx_account_ts (account_id, ts)
);
```

Token: 32 chars do alfabeto Crockford `23456789ABCDEFGHJKMNPQRS` (rejection sampling, como o código de acesso; ~155 bits). Gerado por `generate_code(32)` reutilizado de `reauth.php`.

## 4. Rota e ficheiros novos

- `ai.php` — entrada única do agente (sem sessão; o token é a credencial).
- `.htaccess` (raiz): `RewriteRule ^ai/([23456789ABCDEFGHJKMNPQRS]{32})$ ai.php [L,QSA]` — token só entra por path se tiver o formato exato.
- `api/ai_manage.php` — gestão do link (POST, **sessão normal do dono**, bearer).
- `api/ai_lib.php` — validação, rate-limit, merge, schemas (partilhado por `ai.php` e `ai_manage.php`).
- `llms.txt` (raiz, estático) — aponta para `/ai/<TOKEN>` como API; sem token no ficheiro.

## 5. Contrato do endpoint `/ai/<TOKEN>`

Tudo `GET`, resposta `application/json; charset=utf-8`, sempre HTTP 200/4xx com `{"ok":bool,...}`. `Accept: text/html` em `/ai/<TOKEN>` devolve página humana ("ponto de acesso de agente — gerir no painel"), nunca JSON cru a um humano.

### 5.1 `GET /ai/<TOKEN>` — manual (uma vez por sessão de trabalho)

```json
{
  "service": "SprintG Agent Access",
  "account": "<display_name>",
  "scope": ["read", "create", "status"],
  "base": "https://dominio/ai/<TOKEN>",
  "start_here": "GET {base}?op=board — ids curtos usados em todas as operações",
  "ops": {
    "board":   "GET {base}?op=board",
    "create":  "GET {base}?op=create&d=<base64url(JSON)> — ver create_schemas",
    "status":  "GET {base}?op=status&d=<base64url(JSON)> — ver status_schema"
  },
  "create_schemas": {
    "project": {"type":"project","name":"≤120","desc":"≤2000","icon":"1 emoji","color":"#hex opcional"},
    "sprint":  {"type":"sprint","project":"<id do board>","title":"≤120","goal":"≤500 opcional","badge":"S7 opcional","color":"#hex opcional","end_date":"YYYY-MM-DD opcional","tasks":[ "<task>" ]},
    "task":    {"type":"task","project":"<id>","sprint":"<id>","title":"≤120","desc":"≤2000 opcional","pts":"0|1|2|3|5|8 opcional","priority":"baixa|media|alta|urgente opcional","state":"planned|active|done|blocked opcional (default planned)","subtasks":["≤120","..."]}
  },
  "status_schema": {"type":"status","task":"<id>","state":"planned|active|done|blocked","pts":"opcional","priority":"opcional"},
  "worked_example": "GET {base}?op=create&d=eyJ0eXBlIjoicHJvamVjdCIsIm5hbWUiOiJBUEkgdnIifQ",
  "rules": ["cria sprint com tasks ja criadas no mesmo pedido", "a conclusao da sprint deriva das tasks — nao ha estado de sprint", "um pedido = uma criacao", "em erro, le o hint e corrige"]
}
```

### 5.2 `GET ?op=board`

```json
{ "ok": true, "projects": [ { "id":"p-4k2", "name":"SprintG", "icon":"⚡", "desc":"…",
  "sprints": [ { "id":"s-8f", "title":"Fundação", "badge":"S1", "end_date":"2026-10-01",
    "progress":"5/5", "tasks": [ {"id":"t-1a","title":"…","state":"done","pts":3,"priority":"alta","due":null,
      "subtasks":[{"t":"…","done":true}] } ] } ] } ] }
```

Compacto, ids estáveis, sem notas longas (`desc` truncada a 160 chars; a IA não precisa de ler tudo).

### 5.3 `GET ?op=create&d=` e `GET ?op=status&d=`

`d` = base64url de JSON UTF-8, **máx. 6144 bytes** decodificados. Validação estrita por `type` (campos fora do schema são ignorados, tipos errados rejeitam). Limites: título 120 · desc 2000 · goal 500 · máx. 50 tasks por create · máx. 20 subtasks por task · `color` só `#rrggbb`.

Ids são strings opacas para o cliente; o servidor gera os novos no padrão `p-`/`s-`/`t-` + 5 chars base36 (`crypto`-random), colisão → regenerar. `project` no create de sprint/task aceita id do board **ou** nome exato de projeto (match case-insensitive; ambíguo → erro com sugestões).

Sucesso: `{"ok":true,"created":{"type":"sprint","id":"s-8f","title":"Fundação","tasks_created":5}}`
Status: `{"ok":true,"updated":{"id":"t-1a","state":"done"}}`

### 5.4 Erros (todos com `hint` acionável em inglês simples — a língua da API)

| Código | HTTP | Quando | Exemplo de hint |
|---|---|---|---|
| `invalid_token` | 404 | token ausente/desconhecido/revogado | "no hint — link inválido" (não distinguir revogado vs inexistente) |
| `bad_payload` | 400 | base64/JSON/tipo inválido | "d must decode to JSON with type project|sprint|task|status" |
| `not_found` | 404 | id de projeto/sprint/tarefa inexistente | "task 't-9z' not found; did you mean t-9a (Refactor motor)?" |
| `rate_limited` | 429 | >50 ops de escrita/dia por conta ou >120/h por IP | "daily limit reached; try tomorrow or ask the owner to reset" |
| `blob_full` | 507 | conta no teto de 2 MB | "account is full" |

## 6. Servidor: escrita no blob e merge (a parte crítica)

**Escrita do agente** (create/status): `BEGIN` → `SELECT data,rev FROM accounts WHERE id=? FOR UPDATE` → descodificar → aplicar mutação → `UPDATE data, rev=rev+1, updated_at=NOW()` → `COMMIT` → inserir linha em `ai_ops`. Lock de linha serializa agentes concorrentes. `op=manual/board` não abre transação (leitura simples).

**Mutação aditiva:** create insere por id; status altera só `state|pts|priority` da tarefa-alvo. Nunca reordena nem reescreve listas existentes.

**data_put.php passa a fazer merge server-side** quando `client_rev < server_rev` (em vez do 409 atual):

1. Bloquear linha, carregar blob do servidor.
2. Itens (projeto/sprint/tarefa/subtask) que existem no servidor e **não** no blob do cliente → **acrescentar** ao blob do cliente (criações da IA sobrevivem).
3. Itens presentes nos dois → **versão do cliente vence** (o utilizador é autoridade).
4. Itens que o cliente eliminou → mantêm-se eliminados.
5. Guardar resultado com `rev = server_rev + 1`, devolver 200 + rev novo.

`client_rev == server_rev` → comportamento atual (guardar direto). 409 passa a existir só para blob inválido/corrompido. O cliente (`doSync`) não precisa de mudanças estruturais — o `offerConflict` fica de reserva para os casos que ainda o geram.

**Rate-limit de escrita:** `SELECT COUNT(*) FROM ai_ops WHERE account_id=? AND ts>=CURDATE() AND op IN ('create','status')` ≥ 50 → 429. IP: tabela `login_attempts` reutilizada com bucket `ai:<ip>`.

## 7. Gestão do link (sessão do dono)

`api/ai_manage.php` (POST, bearer obrigatório como `account.php`):

| `action` | Efeito | Resposta |
|---|---|---|
| `generate` | cria token (erro `already_active` se já houver link) | `{url, token}` — única vez que o token em claro sai do servidor |
| `regenerate` | substitui token (mata o anterior) | `{url, token}` |
| `revoke` | `agent_hash=NULL` | `{ok:true}` |

`api/account.php` (GET) passa a incluir:
```json
"agent": { "active": true, "created_at": "2026-09-27", "ops_today": 12, "last_ops": [ {"ts":"14:02","op":"create","target":"sprint:Fundação"} ] }
```

## 8. Painel (index.html)

1. **Bloco "Acesso de IA" na modal da conta** (abaixo do código de acesso), com os estados: vazio (explicação + Gerar), ativo (link mascarado com revelar/copiar à semelhança do código, medidor `ops_today/50`, últimas 5 operações, botões Copiar prompt · Revogar · Regenerar) e nota de aviso sobre partilha.
2. **Prompt pronto** (template em pt-PT no JS): `"Abre este link e segue rigorosamente as instruções que ele contém — é o meu gestor de sprints (SprintG). Depois faz o que eu pedir: <link>"`.
3. **Badge "IA"**: chip `.src-chip` junto ao nome em projetos (barra do projeto), títulos de sprint e linhas de tarefa quando `src==='ai'`. Pequeno, na cor de acento, puramente informativo.
4. **"Limpar criações da IA"**: confirma com contagem ("vais remover 1 projeto, 3 sprints, 12 tarefas criadas por IA") → filtra `src==='ai'` no `appData` → `saveData()` normal. Badges de estado não têm `src`, logo mudanças de estado sobrevivem à limpeza (documentado: limpar remove criações, não estados).
5. Regenerar/revogar usam `ai_manage.php` com o token de sessão; UI segue o padrão da modal de conta existente (glass, hairlines, sem emoji extra).

## 9. Mock local (dev-server.py)

Espelhar: `/ai/<token>` (manual/board/create/status com os mesmos schemas e erros), `ai_manage.php`, `ai_ops` em memória + `dev-db.json`, e o **merge** no `data_put` (mesmo algoritmo do §6, sobre dicts Python). Testes correm contra o mock; o PHP é escrito depois de o contrato estar verde no mock.

## 10. Plano de verificação (5 camadas)

1. **Contrato no mock**: script Node "chatbot sintético" — manual → board → create project → create sprint (com tasks) → 3× status → erros (id falso, payload 7 KB, tipo errado, token revogado, 51ª op). Critério: estado final correto + todos os erros com `hint`.
2. **Concorrência**: PUT com rev velho enquanto agente criou itens → merge preserva criações, cliente vence, zero 409. Dois agentes em paralelo (threads) → sem lost updates.
3. **Painel E2E (Playwright/Edge)**: gerar → copiar → usar no bot sintético → reload → badges → limpar → revogar → link morto.
4. **Acessibilidade/estética**: `impeccable detect`; bloco da modal com contraste e estados reais; sem movimento novo (não precisa de reduced-motion além dos existentes).
5. **Deploy**: `schema.sql` atualizado, `.htaccess` com rewrite, DEPLOY.md com secção "Acesso de IA", smoke test curl no domínio real.

## 11. Segurança — postura e riscos aceites

- Token de ~155 bits, só em URL **HTTPS** (forçado pelo `.htaccess` existente); nunca em `llms.txt`; hash na BD (perda da BD não vaza links).
- Poder do link = ler + criar + marcar estados. Sem acesso a: código de acesso, dados de outras contas, edição/eliminação de conteúdo, export total além do board.
- Risco aceite (decisão do utilizador): link permanente até revogação; mitigações — medidor de uso visível, histórico `ai_ops`, revogação a um clique, regeneração fácil.
- CORS: `ai.php` envia `Access-Control-Allow-Origin: *` apenas para os GET do agente — o segredo é o token, não a origem; o rate-limit por IP cobre abuso.
- `ai_ops` não guarda o payload completo (só `target`) — minimiza dados em log.

## 12. Ficheiros afetados

| Ficheiro | Mudança |
|---|---|
| `ai.php`, `api/ai_lib.php`, `api/ai_manage.php` | novos |
| `api/account.php` | + bloco `agent` no GET |
| `api/data_put.php` | merge server-side (§6) |
| `api/reauth.php` | exportar `generate_code(32)` reutilizável (já existe) |
| `.htaccess` | rewrite `/ai/` |
| `schema.sql` | colunas `agent_*` + tabela `ai_ops` |
| `llms.txt` | novo (estático) |
| `index.html` | bloco na modal da conta, `.src-chip`, limpar criações IA, prompt pronto |
| `dev-server.py` | mock completo (§9) |
| `DEPLOY.md` | secção "Acesso de IA" |
