# Agent Access (IA/CLI) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Um link exclusivo por conta (`/ai/<TOKEN>`) permite a chatbots com navegador e CLIs ler o tabuleiro, criar projeto/sprint/tarefa e mudar estados, com merge server-side seguro sobre o blob da conta.

**Architecture:** API GET-only auto-descritiva (manual → board → create/status) num `ai.php` novo; token de 32 chars guardado como hash; mutações aplicadas com lock de linha e `rev+1`; `data_put.php` passa a fazer merge aditivo quando o rev do cliente está velho; painel ganha o bloco "Acesso de IA" na modal da conta, badges `src:'ai'` e limpeza em bloco.

**Tech Stack:** PHP 8 + PDO MySQL (InfinityFree), vanilla JS num único `index.html`, mock Python stdlib (`dev-server.py`), verificação com Node + playwright-core em `%TEMP%\polishcheck`.

**Spec:** `docs/superpowers/specs/2026-09-27-agent-access-design.md` (contratos JSON, schemas e limites vivem lá — este plano referencia secções §x da spec em vez de os repetir).

## Global Constraints

- Frontend: um único `index.html`, sem build/npm/dependências; copy da UI em pt-PT; hints da API em inglês (spec §5.4).
- Backend: PHP 8, PDO com `EMULATE_PREPARES=false`, tudo prepared statements; sem segredos em `llms.txt`.
- Token: 32 chars do alfabeto Crockford `23456789ABCDEFGHJKMNPQRS`; só `sha256` na BD; em claro aparece uma única vez na resposta de `generate`/`regenerate`.
- Limites (spec §5.3, §6): `d` ≤ 6144 bytes decodificados · título 120 · desc 2000 · goal 500 · 50 tasks/create · 20 subtasks/task · 50 escritas/dia/conta · 120/h/IP · blob 2 MB.
- Erros: sempre JSON `{"ok":false,"error":<código>,"hint":...}`; token inválido/revogado → **404 idêntico** (spec §5.4).
- Testes correm contra o mock (`python dev-server.py`, porta 8000); scripts e node_modules **só em `%TEMP%\polishcheck`**, nunca no projeto. Sem git: cada "Checkpoint" substitui o commit — o estado verificado é o entregável.
- Acessibilidade: nenhum movimento novo; bloco da modal segue os padrões da modal de conta existente.

## Review Focus

Cada linha é um modo de falha que nenhum teste "óbvio" apanha; o teste que o pin está indicado na tarefa dona.

- **PUT com rev velho enquanto a IA criou itens** → merge preserva as criações, cliente vence nos itens comuns, nunca 409. (Task 2)
- **Payload adversarial** (não-b64, JSON errado, tipo desconhecido, 7 KB, id inexistente, nome de projeto ambíguo) → erro estruturado com hint, nunca 500 nem mutação parcial. (Tasks 1 e 3)
- **Token revogado vs inexistente** → respostas byte-a-byte idênticas (sem enumeração). (Tasks 1 e 3)
- **Duas escritas de agente em paralelo** → serializadas, sem lost updates. (Task 2 no mock; Task 3 usa `FOR UPDATE` — verificado por inspeção + php -l, testável de verdade só no deploy)
- **Sinc do cliente depois de escritas da IA** → itens com `src:'ai'` chegam ao painel e o badge renderiza; "Limpar" remove criações mas mantém estados marcados pela IA. (Tasks 5 e 6)

---

### Task 1: Mock da API do agente em `dev-server.py`

**Files:**
- Modify: `dev-server.py` (rotas GET `/ai/...`, POST `api/ai_manage.php`, bloco `agent` no GET `api/account.php`, tabela `ai_ops` em memória persistida em `dev-db.json`)
- Test: `%TEMP%\polishcheck\agent_contract.js`

**Interfaces:**
- Consumes: estado de conta do mock (`accounts[aid]['data']`, `rev`), `gen_code`/`fp` existentes.
- Produces: contrato JSON exato da spec §5 (manual, board, create, status, erros §5.4) e de `ai_manage.php` (spec §7) — o PHP e o frontend codificam contra estas formas. Chaves de conta novas no mock: `agent_token` (em claro, só no mock), `agent_ops: [{ts,op,target}]`.

- [ ] **Step 1: Escrever o teste de contrato (bot sintético) que falha** — script Node que faz, por `fetch`: gerar link via `POST api/ai_manage.php {action:'generate'}` com bearer de sessão de teste → `GET /ai/<T>` → `?op=board` → `op=create` projeto → `op=create` sprint com 2 tasks → `op=status` → re-board. Asserts: manual tem `scope==["read","create","status"]` e `ops` com 3 chaves; board expõe `{id,name,sprints:[{id,title,tasks:[{id,state}]}]}`; cada create devolve `{ok:true,created:{id,...}}`; status devolve `{ok:true,updated:{id,state}}`; itens criados têm `src:'ai'` no blob. Casos de erro: token errado → 404 `{error:'invalid_token'}`; `d` não-b64 → 400 `bad_payload`; 7 KB → 400 `bad_payload`; `type:'delete'` → 400 `bad_payload`; `task:'t-zz'` → 404 `not_found` com `hint`; `project` por nome case-insensitive funciona; 51ª escrita → 429 `rate_limited`.
- [ ] **Step 2: Correr e confirmar falha** — `node agent_contract.js` → falha na 1ª rota (404/405 do mock).
- [ ] **Step 3: Implementar no mock** — função `class AgentAPI` com `manual(account)`, `board(blob)`, `create(blob,d,now)`, `status(blob,d)` (mutação aditiva, ids `p-/s-/t-` + 5 base36, validadores com os limites globais), `handle_ai(path,query)` na rotas GET e `ai_manage` nas POST; `dev-db.json` persiste `agent_token` e `agent_ops`; contador diário = `len([op for op in agent_ops if op.op in ('create','status') and ts>=today])`. Manual inclui `base` com o URL do request (header Host) e `worked_example` real gerado do próprio board.
- [ ] **Step 4: Correr o teste de contrato** — `node agent_contract.js` → todas as asserções passam, output `PASS 20/20` (ou contagem equivalente).
- [ ] **Step 5: Checkpoint** — `node tilttest.js` e `node warptest.js` (testes E2E existentes) continuam verdes: o mock não partiu o fluxo de contas.

### Task 2: Merge server-side no `data_put` do mock + concorrência

**Files:**
- Modify: `dev-server.py` (rota `data_put.php`)
- Test: `%TEMP%\polishcheck\agent_merge.js`

**Interfaces:**
- Consumes: blob `{projects:[{id,sprints:[{id,tasks:[{id,subtasks:[...]},...]}]}]}`; `rev` por conta.
- Produces: `merge_blobs(server, client)` — função Python pura: para cada projeto/sprint/tarefa/subtask só no servidor → acrescentar ao cliente; comuns → versão do cliente; apagadas no cliente → ficam apagadas. Devolve `(merged, changed:bool)`. Algoritmo da spec §6.

- [ ] **Step 1: Escrever o teste que falha** — cenários: (a) cliente com rev velho faz PUT de blob sem o projeto que a IA criou → 200 (não 409), blob final tem o projeto da IA; (b) cliente alterou `title` de uma tarefa que a IA também tinha marcado `done` → título do cliente vence, estado do cliente vence (cliente é autoridade no item comum); (c) cliente apagou sprint que a IA criou → fica apagada; (d) PUT com rev igual → comportamento atual inalterado; (e) 2 threads a fazer `op=create` em simultâneo → ambos os itens no blob final, `rev` +2.
- [ ] **Step 2: Correr, confirmar falha** (a) → hoje devolve 409.
- [ ] **Step 3: Implementar `merge_blobs` + ligar no `data_put`** do mock (bloqueo no `_lock` existente; o mock é single-process, threads partilham o lock — suficiente para o cenário (e)).
- [ ] **Step 4: `node agent_merge.js` → PASS** e `node agent_contract.js` continua PASS.
- [ ] **Step 5: Checkpoint** — `node accttest.js` (fluxo de contas/sync existente) verde.

### Task 3: `api/ai_lib.php` + `ai.php` (PHP real, núcleo testável sem BD)

**Files:**
- Create: `api/ai_lib.php`, `ai.php` (raiz)
- Test: `%TEMP%\polishcheck\ailib_test.php` (corre com `php` CLI local, sem MySQL)

**Interfaces:**
- Consumes: `api/config.php` (`CODE_ALPHABET`, `MAX_BLOB_BYTES`), `api/bootstrap.php` (`json_out`, `client_ip`), `api/reauth.php` (`generate_code`).
- Produces (funções puras, sem PDO — testáveis por CLI): `ai_validate_d(string $b64): array{ok:bool, json?:array, error?:string, hint?:string}` · `ai_board(array $blob): array` · `ai_create(array $blob, array $d, callable $newId): array{ok,...}|erro` · `ai_status(array $blob, array $d): ...` · `ai_manual(array $account, string $base, string $token): array` · `ai_merge_blobs(array $server, array $client): array` (mesma semântica do mock) · `ai_find(array $blob, string $id)` e `ai_find_project(array $blob, string $ref)`. `ai.php` faz só: parse token do path → `sha256` → `SELECT ... WHERE agent_hash=?` → rate-limit (`ai_ops`) → transação `FOR UPDATE` nos ops de escrita → resposta. Erros idênticos ao mock.

- [ ] **Step 1: Teste CLI que falha** — `php ailib_test.php` exige `api/ai_lib.php` e assert-a: `ai_validate_d` com os 6 casos adversariais da Task 1; `ai_create`/`ai_status` sobre um blob fixture (mesmos resultados esperados que o mock — copiar os casos de `agent_contract.js`); `ai_merge_blobs` com os 3 cenários de `agent_merge.js`; `ai_manual` contém `scope` e 3 `ops`.
- [ ] **Step 2: Correr → fatal "require api/ai_lib.php"** (falha esperada).
- [ ] **Step 3: Implementar `api/ai_lib.php`** — espelhar o mock função a função (manter as formas JSON da spec §5 literalmente; `$newId` injeta `bin2hex(random_bytes(3))` para testes usarem determinístico).
- [ ] **Step 4: `php ailib_test.php` → `OK`** e `php -l` limpo em `ai.php`, `api/ai_lib.php`, `api/ai_manage.php` (stub vazio criado aqui para o -l não falhar depois).
- [ ] **Step 5: Implementar `ai.php`** (fio da meada: routing por `op`, transação com `FOR UPDATE`, `ai_ops` insert, CORS `Access-Control-Allow-Origin:*` só para GET, `Accept: text/html` → página humana estática) — verificação: `php -l` + diff de comportamento contra o mock documentada em comentário no topo de `ai.php` ("espelha dev-server.py §Task1"); o teste real acontece no smoke do deploy (Task 7).
- [ ] **Step 6: Checkpoint** — `node agent_contract.js` e `node agent_merge.js` continuam PASS (mock intocado).

### Task 4: Gestão do link no PHP + schema + rewrite + llms.txt

**Files:**
- Create: `api/ai_manage.php`, `llms.txt`
- Modify: `api/account.php` (GET + bloco `agent`), `schema.sql`, `.htaccess`, `api/.htaccess` (FilesMatch já cobre `ai_manage.php`? garantir), `dev-server.py` (alinhar forma de `ai_manage`/`account.php` se divergiu)
- Test: extensão de `agent_contract.js` (contra o mock) + `php -l`

**Interfaces:**
- Consumes: `require_auth` do bootstrap; `accounts.agent_hash`, `agent_created_at`; `ai_ops`.
- Produces: `POST api/ai_manage.php {action:'generate'|'revoke'|'regenerate'}` → `{url, token}` / `{ok:true}` / erro `already_active` (spec §7); GET `api/account.php` + `agent:{active,created_at,ops_today,last_ops:[{ts,op,target}]}`.

- [ ] **Step 1: Atualizar `schema.sql`** com as colunas/tabela da spec §3 (a mais: `ALTER` comentado para contas já criadas, como o `display_name` no DEPLOY.md).
- [ ] **Step 2: Escrever testes de forma no contrato** — `agent_contract.js` passa a verificar o bloco `agent` do `account.php` do mock (active/ops_today/last_ops) e `already_active` no generate duplicado. Correr → PASS (mock já faz; se divergir, corrigir mock).
- [ ] **Step 3: Implementar `api/ai_manage.php`** (generate com `generate_code(32)` + update do hash; regenerate; revoke) e o bloco `agent` em `api/account.php` — `php -l` ambos.
- [ ] **Step 4: `.htaccess` raiz** com o rewrite `/ai/<32chars>` → `ai.php` (antes das regras de force-HTTPS não; depois — manter ordem existente) e `llms.txt` estático (3 linhas, sem token).
- [ ] **Step 5: Checkpoint** — scripts da Task 1/2 verdes; `php -l` limpo em todos os ficheiros tocados.

### Task 5: Frontend — bloco "Acesso de IA" na modal da conta

**Files:**
- Modify: `index.html` (secção da `#accountModal` + `accountUI` IIFE + CSS)
- Test: `%TEMP%\polishcheck\agentui.js` (Playwright contra `dev-server.py`)

**Interfaces:**
- Consumes: `api/ai_manage.php`, `agent` do `account.php` (Task 4), helpers de cópia existentes (`copyAccessCode`), padrão de código mascarado da modal.
- Produces: `agentUI` (IIFE) com `refreshAgentBlock()`, `agentGenerate()`, `agentRevoke()`, `agentRegenerate()`, `copyAgentPrompt()` — chamados do bloco; template do prompt pronto em pt-PT (spec §8.2).

- [ ] **Step 1: Teste Playwright que falha** — login no mock → abrir conta → existe `#agentSection`; sem link: mostra botão "Gerar link de IA"; gerar → link mascarado `https://.../ai/••••` + botões Revelar/Copiar link/Copiar prompt/Revogar/Regenerar; copiar prompt → clipboard contém o URL e a frase da spec; revogar (com `confirm`) → volta ao estado vazio; recarregar → estado persiste; medidor mostra `ops_today` depois de escritas do bot sintético.
- [ ] **Step 2: Correr → falha (sem `#agentSection`).**
- [ ] **Step 3: Implementar** bloco (HTML dentro da modal, abaixo do código de acesso; CSS reutiliza `.acct-*`), `agentUI` com `fetch` aos endpoints, estados de carregamento/desativação nos botões, e o texto de aviso da spec §8.1. Copy pt-PT literal da spec.
- [ ] **Step 4: `node agentui.js` → PASS** + `node accttest.js` verde (modal não regrediu).
- [ ] **Step 5: Screenshot desktop+mobile do bloco nos 2 estados** e inspeção de contraste/hairlines (padrão da conta); `impeccable detect` sem findings novos.

### Task 6: Frontend — badges "IA" e "Limpar criações da IA"

**Files:**
- Modify: `index.html` (render de projeto/sprint/tarefa; `#agentSection` ganha o botão de limpeza; CSS `.src-chip`)
- Test: extensão de `agentui.js`

**Interfaces:**
- Consumes: `src==='ai'` nos itens do blob (criados pelo agente).
- Produces: `.src-chip` (chip 10px, acento, texto "IA") ao lado do nome do projeto na barra do painel, do título da sprint e das linhas de tarefa; `agentPurge()` — conta itens `src:'ai'` (projeto conta os seus descendentes), `confirm()` com a contagem, remove-os, `saveData()`.

- [ ] **Step 1: Teste que falha** — bot sintético cria projeto+sprint+tasks → painel reload → `document.querySelectorAll('.src-chip').length >= 3`; clicar "Limpar criações da IA" → confirm → board sem `src:'ai'` (`JSON.stringify(appData).includes('"ai"')===false`); tarefa marcada `done` pela IA **dentro de item não-IA** sobrevive à limpeza (spec §8.4).
- [ ] **Step 2: Correr → falha.**
- [ ] **Step 3: Implementar** o chip nos três locais de render (um helper `srcChip(item)` que devolve a string HTML) e `agentPurge()` no bloco da Task 5.
- [ ] **Step 4: `node agentui.js` → PASS** (com os novos casos).
- [ ] **Step 5: Checkpoint visual** — screenshot de um sprint com badge; `impeccable detect` limpo.

### Task 7: E2E completo, paridade mock↔PHP e docs de deploy

**Files:**
- Create: `%TEMP%\polishcheck\agent_e2e.js`
- Modify: `DEPLOY.md` (secção "Acesso de IA"), `MEMORY.md` (lições), `DESIGN.md` (assinatura do bloco, 2-3 linhas)

- [ ] **Step 1: E2E de ponta a ponta no mock** — gerar link no painel (Playwright) → extrair token (revelar) → `agent_contract.js`-like bot usa o link → painel mostra badges → limpar → revogar → o mesmo link devolve 404 `invalid_token`. Um só script, output `E2E PASS`.
- [ ] **Step 2: Correr → PASS.**
- [ ] **Step 3: DEPLOY.md** — passo a passo: aplicar `schema.sql` novo (colunas + `ai_ops`), upload de `ai.php`/`ai_lib.php`/`ai_manage.php`/`llms.txt`, `.htaccess`, e smoke test no domínio real com os 4 GETs da spec §5 (comandos curl exatos, incluindo criar e marcar uma vez e limpar pela UI).
- [ ] **Step 4: DESIGN.md** — estender a secção da conta com a assinatura "Acesso de IA" (o que é, onde vive, regra do badge); **MEMORY.md** — uma lição nova se emergir (ex.: paridade mock↔PHP manter por comentário de espelho em `ai.php`); nada derivável de código.
- [ ] **Step 5: Regressão final** — `agent_contract.js`, `agent_merge.js`, `agentui.js`, `agent_e2e.js`, `accttest.js`, `warptest.js`, `tilttest.js` todos verdes + `impeccable detect` sem findings novos. Estado entregue; publicação é decisão do utilizador.

---

## Self-review do plano (já executada)

1. **Cobertura da spec:** §2 fluxo→T5/T7 · §3 dados→T4 · §4 ficheiros→T3/T4 · §5 contrato→T1 (mock) e T3 (PHP) · §6 merge→T2/T3 · §7 gestão→T4 · §8 painel→T5/T6 · §9 mock→T1/T2 · §10 verificação→T7 · §11 segurança→T3 (404 idêntico no Review Focus) e T4 (hash só). Sem lacunas.
2. **Passos:** cada passo é uma ação verificável; corpos de algoritmo só onde a spec não decide (merge, já na spec §6).
3. **Tipos/nomes:** contrato partilhado = formas JSON da spec §5; nomes de funções PHP (`ai_*`) consistentes entre T3/T4; `agentUI`/`agentPurge` usados só em T5/T6.
4. **Review Focus:** os 5 modos de falha têm teste na tarefa dona (ver cada linha).
5. **Proporção:** ~7 tarefas para uma spec de 12 secções; testes descritos por asserção, não transcritos.
