# SprintG — Publicar as Contas no InfinityFree (PHP + MySQL)

O painel de sprints agora exige login e guarda os dados **por conta no servidor**.
O backend é PHP puro + MySQL (o que o InfinityFree oferece). A home cinematográfica
continua pública; só o painel (`enterApp`) pede sessão.

## 0. O que é o quê
- `index.html` — o site (frontend). Já fala com a API sozinha.
- `api/` — o backend PHP. **Nada de Node/JWT/libsodium**: só funções core do PHP 8.
- `schema.sql` — as 3 tabelas. Corres isto no phpMyAdmin.
- `.htaccess` (raiz e `api/`) — força HTTPS e bloqueia os ficheiros sensíveis.

## 1. Criar a base de dados
1. No painel do InfinityFree → **MySQL Databases** → cria uma base (ex.: `sprintg`)
   e um utilizador MySQL. Anota: **MySQL Host**, **nome da BD**, **utilizador**, **password**.
2. Abre o **phpMyAdmin**, seleciona a base criada e executa o conteúdo de `schema.sql`
   (tab *SQL* → colar → *Go*). Cria `accounts`, `sessions`, `login_attempts`.

> **Se já tinhas a BD criada** antes do painel de conta, adiciona só a coluna nova no
> phpMyAdmin (senão `account.php` falha ao ler/guardar o nome):
> ```sql
> ALTER TABLE accounts ADD COLUMN display_name VARCHAR(40) NULL AFTER code_fingerprint;
> ```

## 2. Configurar `api/config.php`
Abre `api/config.php` e preenche com os valores reais do passo 1:
```php
define('DB_HOST', 'sqlXXX.infinityfree.com');   // o teu "MySQL Host"
define('DB_NAME', 'if0_XXXXXXX_sprintg');        // o teu nome de BD
define('DB_USER', 'if0_XXXXXXX');                // o teu utilizador MySQL
define('DB_PASS', 'a-tua-password');             // a password MySQL
```
E troca o **PEPPER** por um segredo aleatório teu (64+ chars). Gerar um:
abre `https://www.random.org/strings/` (ou corre `php -r "echo bin2hex(random_bytes(32));"`)
e cola o resultado. **Nunca partilhes nem publiques este ficheiro.**
> Ideal: mover `config.php` para uma pasta **acima** da `htdocs/` e ajustar o `require`
> em `api/bootstrap.php`. Assim deixa de ser servível por URL. O `.htaccess` já bloqueia
> `config/db/bootstrap/reauth.php` como rede de segurança.

## 3. Enviar os ficheiros
Copia **tudo** para a `htdocs/` do InfinityFree:
- `index.html`, `background.mp4`, `poster*.jpg`, `.htaccess`
- a pasta `api/` completa (incluindo `api/.htaccess`)
- `schema.sql` (opcional no servidor; é só para criares a BD)

## 4. Testar no browser
1. Abre o teu `https://.../`. A home carrega.
2. Clica em entrar no painel → deve abrir o **modal de login**.
3. **Criar conta** → mostra um código tipo `XXXX-XXXX-XXXX-XXXX-XXXX`. **Copia / descarrega
   o .txt** — é a tua chave única e **não volta a aparecer**.
4. Entra no painel, cria uma sprint → o **pill** na barra do projeto passa
   `A guardar → Sincronizado`.
5. Recarrega a página → volta sozinho ao painel com os teus dados (sessão guardada).
6. Noutro dispositivo, entra com o **mesmo código** → os dados aparecem.

## 5. Segurança que já está tratada (não desligues)
- O código é guardado **apenas como hash bcrypt** + fingerprint HMAC; nunca em claro.
- Login **rate-limited** por IP e por código (tabela `login_attempts`).
- Sessões são **bearer tokens** (header) → sem cookies → **sem CSRF**; expiram em 30 dias.
- **Perder o código + ter todas as sessões ativas expiradas = conta irrecuperável** (não
  há email). Por isso há **regenerar** a partir de uma sessão ativa (mata as sessões antigas).

## 6. Limitações do plano gratuito do InfinityFree
- **Throttle de CPU/scripts**: o frontend faz pouquíssimos pedidos (1 no arranque + 1
  gravador com *debounce*), e tem *backoff* se a rede falhar. Não ponhas a fazer refresh
  em loop.
- **Sem cron** → a limpeza de sessões expiradas é feita "a pretexto" em cada pedido.
- **Sem email** → ver nota de recuperação acima.
- Subdomínio partilhado de baixa confiança; para algo sério usa domínio próprio.

## 7. Quando cresceres (migração)
Os dados são **um blob JSON por conta** → exportas/importas à vontade. Trocar para um VPS
com PHP + cron + domínio próprio é copiar `api/` e importar `schema.sql`; dá até para passar
a hash para Argon2id na próxima rotação de código. O **frontend não muda**, porque toda a
rede passa por `saveData()` / `initData()` / `enterApp()`.

## 8. Testar localmente (sem PHP nem MySQL)
Não abras o `index.html` com duplo-clique (`file://`) — aí o browser bloqueia a API e
o login/criar conta falham (vais ver o aviso "Backend indisponível"). Usa o servidor de
desenvolvimento em Python, que serve o site **e** faz mock da API com o mesmo contrato:
```
python dev-server.py
# abre  http://127.0.0.1:8000
```
Podes então criar conta (recebes o código), entrar, editar sprints (o pill sincroniza),
recarregar (mantém a sessão) e sair. Os dados ficam em `dev-db.json` — **apaga esse
ficheiro para recomeçar limpo**.

> ⚠️ O `dev-server.py` é **só para desenvolvimento**: guarda tudo em texto simples, sem
> bcrypt nem rate-limit. Serve para validar a interface e a lógica do cliente. A segurança
> real é o backend PHP (`api/`), que só fica provado quando publicares no InfinityFree
> (passos 1-4) ou correres PHP+MySQL locais (ex.: XAMPP) a servir `index.html` **e** `api/`
> na mesma origem, com `config.php` apontado para a tua BD.


## 9. Acesso de IA (link de agente)
Funcionalidade opcional: cada conta pode gerar um link `/ai/<TOKEN>` que dá a um chatbot
com navegador (ChatGPT, Gemini, Copilot) ou a uma CLI (`curl`) acesso para **ler, criar e
marcar tarefas** — sem login, sem editar textos, sem apagar.

**Ativar no servidor (uma vez):**
1. Migração da BD (phpMyAdmin > SQL), se a tua tabela `accounts` já existia:
   ```sql
   ALTER TABLE accounts ADD COLUMN agent_hash CHAR(64) NULL UNIQUE AFTER display_name;
   ALTER TABLE accounts ADD COLUMN agent_created_at DATETIME NULL AFTER agent_hash;
   ```
   E cria a tabela `ai_ops` (o `schema.sql` já a inclui — copia o `CREATE TABLE` de `ai_ops`).
2. Envia os ficheiros novos: `ai.php` (raiz), `api/ai_lib.php`, `api/ai_manage.php`,
   `llms.txt`, e as versões atualizadas de `api/account.php`, `api/data_put.php`,
   `api/reauth.php`, `.htaccess` e `api/.htaccess`.

**Usar:** no painel → engrenagem da conta → **Acesso de IA** → *Gerar link de IA* →
*Copiar prompt para a IA* → cola no chatbot e descreve a tarefa. O link funciona até
*Revogar* (ou *Regenerar* para trocar). Medidor: 50 operações de escrita/dia por conta.

**Smoke test pós-deploy (substitui `<TOKEN>` pelo gerado na UI):**
```bash
curl -s https://TEU-DOMINIO/ai/<TOKEN>                      # manual JSON
curl -s "https://TEU-DOMINIO/ai/<TOKEN>?op=board"           # estado
curl -s "https://TEU-DOMINIO/ai/<TOKEN>?op=create&d=eyJ0eXBlIjoicHJvamVjdCIsIm5hbWUiOiJTbW9rZSJ9"
curl -s "https://TEU-DOMINIO/ai/<TOKEN>?op=board"           # o projeto "Smoke" aparece
```
Depois apaga o projeto "Smoke" no painel (ou usa *Limpar criações da IA*).

**Segurança:** o token só é mostrado uma vez (a BD guarda apenas o hash); um link fugido
mata-se com *Revogar*. O acesso permite criar itens marcados com badge **IA** e mudar
estados — nunca ler o código de acesso, editar textos ou apagar.
