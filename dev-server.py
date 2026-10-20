#!/usr/bin/env python3
# =============================================================================
#  dev-server.py — SERVIDOR DE DESENVOLVIMENTO LOCAL (NÃO É O BACKEND REAL)
# =============================================================================
#  Serve o index.html E faz mock dos endpoints api/*.php com o MESMO contrato do
#  backend de produção (InfinityFree PHP+MySQL), para poderes testar todo o fluxo
#  de contas SEM instalar PHP/MySQL.
#
#  >>> APENAS PARA DESENVOLVIMENTO. Guarda tudo em texto simples num JSON local.
#      Não tem bcrypt, nem rate-limit real, nem HTTPS. A segurança de produção
#      vive em api/*.php — isto é só para validar a interface e a lógica do cliente.
#
#  Como usar:
#      python dev-server.py
#      abre  http://127.0.0.1:8000   (NÃO abras o index.html por file://)
#  Para recomeçar limpo: apaga o ficheiro dev-db.json.
# =============================================================================
import json, os, re, time, hmac, hashlib, secrets, threading, base64
from urllib.parse import urlparse, parse_qs
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

PORT = 8000
ROOT = os.path.dirname(os.path.abspath(__file__))
DB_FILE = os.path.join(ROOT, 'dev-db.json')
PEPPER = b'dev-pepper-not-secret'
ALPHA = '23456789ABCDEFGHJKMNPQRS'
TTL = 60 * 60 * 24 * 30

_lock = threading.Lock()
accounts = {}   # id -> {code_hash, fpr, token_version, data, rev}
sessions = {}   # sha256(token) -> {account_id, token_version, expires_at}
next_id = [1]

def load():
    global accounts, sessions, next_id
    if os.path.isfile(DB_FILE):
        try:
            with open(DB_FILE, 'r', encoding='utf-8') as f:
                d = json.load(f)
            accounts = {int(k): v for k, v in d.get('accounts', {}).items()}
            sessions = d.get('sessions', {})
            next_id = [d.get('next_id', 1)]
        except Exception:
            pass

def save():
    try:
        with open(DB_FILE, 'w', encoding='utf-8') as f:
            json.dump({'accounts': accounts, 'sessions': sessions, 'next_id': next_id[0]}, f, ensure_ascii=False)
    except Exception:
        pass

def gen_code():
    return ''.join(secrets.choice(ALPHA) for _ in range(20))

def norm(raw):
    return re.sub('[^' + ALPHA + ']', '', (raw or '').upper())

def fp(code):
    return hmac.new(PEPPER, code.encode(), hashlib.sha256).hexdigest()

def fmt(code):
    return '-'.join(code[i:i+4] for i in range(0, len(code), 4))

def mint(aid):
    tok = secrets.token_hex(32)
    sessions[hashlib.sha256(tok.encode()).hexdigest()] = {
        'account_id': aid, 'token_version': accounts[aid]['token_version'], 'expires_at': time.time() + TTL}
    return tok

# ---------------------------------------------------------------------------
# AGENT API (mock de ai.php / api/ai_lib.php) — espelha a spec §5/§6.
# Mapeamento externo↔interno: title↔label · state↔status · pts↔points ·
# desc(tarefa)↔note · end_date↔endDate · subtasks[str]↔subs[{id,text,done}].
# ---------------------------------------------------------------------------
STATES = ('planned', 'progress', 'done', 'blocked')
PRIOS = ('low', 'medium', 'high', 'urgent')

def gen_token():
    return ''.join(secrets.choice(ALPHA) for _ in range(32))

def ai_new_id(prefix, blob):
    while True:
        nid = prefix + '-' + ''.join(secrets.choice('0123456789abcdefghijklmnopqrstuvwxyz') for _ in range(5))
        if all(nid != p.get('id') for p in blob.get('projects', [])) \
           and all(nid != s.get('id') for p in blob.get('projects', []) for s in p.get('sprints', [])) \
           and all(nid != t.get('id') for p in blob.get('projects', []) for s in p.get('sprints', []) for t in s.get('tasks', [])):
            return nid

def ai_today_ops(acc):
    day = time.strftime('%Y-%m-%d')
    return [o for o in acc.get('agent_ops', []) if o.get('ts', '').startswith(day) and o.get('op') in ('create', 'status')]

def ai_log(acc, op, target, rev=None, item_id=None):
    acc.setdefault('agent_ops', []).append({'ts': time.strftime('%Y-%m-%dT%H:%M:%S'), 'op': op, 'target': target,
                                            'rev': rev, 'item_id': item_id})
    acc['agent_ops'] = acc['agent_ops'][-200:]

import copy as _copy

def merge_new(server, client, ops):
    """Re-aplica ao blob do cliente os creates do agente posteriores ao rev dele (spec §6)."""
    out = _copy.deepcopy(client) if isinstance(client, dict) else {'projects': []}
    out.setdefault('projects', [])
    projs, spr, tas = {}, {}, {}
    for p in (server or {}).get('projects', []):
        projs[p.get('id')] = p
        for s in p.get('sprints', []):
            spr[s.get('id')] = (p.get('id'), s)
            for t in s.get('tasks', []):
                tas[t.get('id')] = (s.get('id'), t)
    have_p = {p.get('id') for p in out['projects']}
    for o in ops:
        iid = o.get('item_id')
        if not iid:
            continue
        kind = (o.get('target') or '').split(':')[0]
        if kind == 'project' and iid not in have_p and iid in projs:
            out['projects'].append(_copy.deepcopy(projs[iid])); have_p.add(iid)
        elif kind == 'sprint' and iid in spr:
            pid, sp = spr[iid]
            cp = next((p for p in out['projects'] if p.get('id') == pid), None)
            if cp is not None and all(s.get('id') != iid for s in cp.get('sprints', [])):
                cp.setdefault('sprints', []).append(_copy.deepcopy(sp))
        elif kind == 'task' and iid in tas:
            sid, tk = tas[iid]
            for p in out['projects']:
                cs = next((s for s in p.get('sprints', []) if s.get('id') == sid), None)
                if cs is not None:
                    if all(t.get('id') != iid for t in cs.get('tasks', [])):
                        cs.setdefault('tasks', []).append(_copy.deepcopy(tk))
                    break
    return out

def ai_decode_d(raw):
    if not raw:
        return None, ('bad_payload', 'missing d parameter')
    try:
        pad = '=' * (-len(raw) % 4)
        dec = base64.urlsafe_b64decode(raw + pad)
        if len(dec) > 6144:
            return None, ('bad_payload', 'd exceeds 6144 bytes; split into smaller requests')
        js = json.loads(dec.decode('utf-8'))
    except Exception:
        return None, ('bad_payload', 'd must be base64url-encoded JSON')
    if not isinstance(js, dict):
        return None, ('bad_payload', 'd must decode to a JSON object')
    return js, None

def ai_s(v, cap):
    return isinstance(v, str) and 0 < len(v) <= cap

def ai_find_project(blob, ref):
    low = (ref or '').lower()
    hits = [p for p in blob.get('projects', []) if p.get('id') == ref] or \
           [p for p in blob.get('projects', []) if (p.get('name') or '').lower() == low]
    return hits[0] if len(hits) == 1 else None

def ai_find_task(blob, tid):
    for p in blob.get('projects', []):
        for s in p.get('sprints', []):
            for t in s.get('tasks', []):
                if t.get('id') == tid:
                    return t
    return None

def ai_find_sprint(blob, sid):
    for p in blob.get('projects', []):
        for s in p.get('sprints', []):
            if s.get('id') == sid:
                return s
    return None

def ai_board(blob):
    out = []
    for p in blob.get('projects', []):
        sp = []
        for s in p.get('sprints', []):
            tasks = [{'id': t.get('id'), 'title': t.get('label'), 'state': t.get('status', 'planned'),
                      'pts': t.get('points'), 'priority': t.get('priority'), 'due': t.get('due'),
                      'subtasks': [{'t': sb.get('text'), 'done': bool(sb.get('done'))} for sb in (t.get('subs') or [])]}
                     for t in s.get('tasks', [])]
            done = sum(1 for t in tasks if t['state'] == 'done')
            sp.append({'id': s.get('id'), 'title': s.get('title'), 'badge': s.get('badge'),
                       'end': s.get('endDate'), 'progress': f"{done}/{len(tasks)}", 'tasks': tasks})
        out.append({'id': p.get('id'), 'name': p.get('name'), 'icon': p.get('icon'),
                    'desc': (p.get('desc') or '')[:160], 'sprints': sp})
    return out

def ai_make_task(blob, d, defaults_sprint=None):
    if not ai_s(d.get('title'), 120):
        return None, ('bad_payload', 'task.title required, 1..120 chars')
    subs = d.get('subtasks') or []
    if not isinstance(subs, list) or len(subs) > 20:
        return None, ('bad_payload', 'task.subtasks must be an array of up to 20 strings')
    task = {'id': ai_new_id('t', blob), 'label': d['title'], 'status': d.get('state', 'planned'),
            'priority': d.get('priority', 'medium'), 'points': d.get('pts', 0),
            'note': (d.get('desc') or '')[:2000], 'subs': [], 'src': 'ai'}
    if task['status'] not in STATES:
        return None, ('bad_payload', 'task.state must be one of ' + '|'.join(STATES))
    if task['priority'] not in PRIOS:
        return None, ('bad_payload', 'task.priority must be one of ' + '|'.join(PRIOS))
    if task['points'] not in (0, 1, 2, 3, 5, 8, 13, 21):
        return None, ('bad_payload', 'task.pts must be one of 0,1,2,3,5,8,13,21')
    if d.get('due'):
        if not re.match(r'^\d{4}-\d{2}-\d{2}$', str(d['due'])):
            return None, ('bad_payload', 'task.due must be YYYY-MM-DD')
        task['due'] = d['due']
    for sb in subs:
        if not ai_s(sb, 120):
            return None, ('bad_payload', 'each subtask must be a string of 1..120 chars')
        task['subs'].append({'id': 'sb-' + secrets.token_hex(3), 'text': sb, 'done': False})
    return task, None

def ai_create(blob, d):
    typ = d.get('type')
    if typ == 'project':
        if not ai_s(d.get('name'), 120):
            return None, ('bad_payload', 'project.name required, 1..120 chars')
        proj = {'id': ai_new_id('p', blob), 'name': d['name'], 'desc': (d.get('desc') or '')[:2000],
                'rules': '', 'icon': (d.get('icon') or '⚡')[:4], 'theme': ({'accent': d['color']} if re.match(r'^#[0-9a-fA-F]{6}$', str(d.get('color') or '')) else {}),
                'updatedAt': time.strftime('%d/%m/%Y'), 'sprints': [], 'src': 'ai'}
        blob.setdefault('projects', []).append(proj)
        blob.setdefault('activeProjectId', proj['id'])
        return {'type': 'project', 'id': proj['id'], 'name': proj['name']}, None
    if typ in ('sprint', 'task'):
        proj = ai_find_project(blob, d.get('project'))
        if proj is None:
            return None, ('not_found', "project not found; use op=board ids or the exact name")
        if typ == 'sprint':
            if not ai_s(d.get('title'), 120):
                return None, ('bad_payload', 'sprint.title required, 1..120 chars')
            tasks_in = d.get('tasks') or []
            if not isinstance(tasks_in, list) or len(tasks_in) > 50:
                return None, ('bad_payload', 'sprint.tasks must be an array of up to 50 tasks')
            sp = {'id': ai_new_id('s', blob), 'icon': d.get('icon', '🚀'), 'badge': d.get('badge') or '',
                  'title': d['title'], 'desc': (d.get('desc') or '')[:2000], 'goal': (d.get('goal') or '')[:500],
                  'color': d['color'] if re.match(r'^#[0-9a-fA-F]{6}$', str(d.get('color') or '')) else '#3b82f6',
                  'tasks': [], 'src': 'ai'}
            if d.get('end_date'):
                if not re.match(r'^\d{4}-\d{2}-\d{2}$', str(d['end_date'])):
                    return None, ('bad_payload', 'sprint.end_date must be YYYY-MM-DD')
                sp['endDate'] = d['end_date']
            for td in tasks_in:
                if not isinstance(td, dict):
                    return None, ('bad_payload', 'each task must be an object')
                task, err = ai_make_task(blob, td)
                if err:
                    return None, err
                sp['tasks'].append(task)
            proj.setdefault('sprints', []).append(sp)
            return {'type': 'sprint', 'id': sp['id'], 'title': sp['title'], 'project': proj['id'], 'tasks_created': len(sp['tasks'])}, None
        sp = ai_find_sprint(blob, d.get('sprint'))
        if sp is None:
            return None, ('not_found', 'sprint not found; use op=board ids')
        task, err = ai_make_task(blob, d)
        if err:
            return None, err
        sp.setdefault('tasks', []).append(task)
        return {'type': 'task', 'id': task['id'], 'title': task['label'], 'sprint': sp['id']}, None
    return None, ('bad_payload', 'type must be one of project|sprint|task')

def ai_status(blob, d):
    t = ai_find_task(blob, d.get('task'))
    if t is None:
        cand = [x.get('id') for p in blob.get('projects', []) for s in p.get('sprints', []) for x in s.get('tasks', [])][:5]
        return None, ('not_found', "task not found; ids look like " + ', '.join(cand))
    st = d.get('state')
    if st is not None:
        if st not in STATES:
            return None, ('bad_payload', 'state must be one of ' + '|'.join(STATES))
        t['status'] = st
    if d.get('pts') is not None:
        if d['pts'] not in (0, 1, 2, 3, 5, 8, 13, 21):
            return None, ('bad_payload', 'pts must be one of 0,1,2,3,5,8,13,21')
        t['points'] = d['pts']
    if d.get('priority') is not None:
        if d['priority'] not in PRIOS:
            return None, ('bad_payload', 'priority must be one of ' + '|'.join(PRIOS))
        t['priority'] = d['priority']
    if st is None and d.get('pts') is None and d.get('priority') is None:
        return None, ('bad_payload', 'provide at least state, pts or priority')
    t['src_status'] = 'ai'
    return {'id': t['id'], 'state': t.get('status', 'planned')}, None

def ai_manual(acc, base, token):
    return {
        'service': 'SprintG Agent Access', 'account': acc.get('display_name') or 'SprintG account',
        'scope': ['read', 'create', 'status'], 'base': base,
        'start_here': 'GET ' + base + '?op=board — short ids from the board are used in every operation',
        'ops': {'board': 'GET ' + base + '?op=board',
                'create': 'GET ' + base + '?op=create&d=<base64url(JSON)> — see create_schemas',
                'status': 'GET ' + base + '?op=status&d=<base64url(JSON)> — see status_schema'},
        'create_schemas': {
            'project': {'type': 'project', 'name': '≤120', 'desc': '≤2000 optional', 'icon': '1 emoji optional', 'color': '#hex optional'},
            'sprint': {'type': 'sprint', 'project': '<id or exact name>', 'title': '≤120', 'goal': '≤500 optional',
                       'badge': 'optional', 'color': '#hex optional', 'end_date': 'YYYY-MM-DD optional', 'tasks': ['<task>']},
            'task': {'type': 'task', 'project': '<id>', 'sprint': '<id>', 'title': '≤120', 'desc': '≤2000 optional',
                     'pts': '0|1|2|3|5|8 optional', 'priority': 'low|medium|high|urgent optional',
                     'state': 'planned|progress|done|blocked optional', 'subtasks': ['≤120']}},
        'status_schema': {'type': 'status', 'task': '<id>', 'state': 'planned|progress|done|blocked', 'pts': 'optional', 'priority': 'optional'},
        'worked_example': base + '?op=create&d=' + base64.urlsafe_b64encode(json.dumps({'type': 'project', 'name': 'API vr', 'desc': 'by agent'}).encode()).decode().rstrip('='),
        'rules': ['create a sprint together with its tasks in one call', 'sprint completion derives from tasks — there is no sprint state',
                  'one request = one creation', 'on error, read the hint and fix', 'never delete; you cannot edit texts'],
    }

class H(BaseHTTPRequestHandler):
    def log_message(self, *a): pass

    def _send(self, obj, code=200):
        body = json.dumps(obj, ensure_ascii=False).encode('utf-8')
        self.send_response(code)
        self.send_header('Content-Type', 'application/json; charset=utf-8')
        self.send_header('Content-Length', str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def _read(self):
        n = int(self.headers.get('Content-Length') or 0)
        raw = self.rfile.read(n) if n else b''
        try:
            return json.loads(raw or b'{}')
        except Exception:
            return {}

    def _bearer(self):
        h = self.headers.get('Authorization', '')
        if h.lower().startswith('bearer '):
            sid = hashlib.sha256(h[7:].strip().encode()).hexdigest()
            s = sessions.get(sid)
            if s and s['expires_at'] > time.time():
                acc = accounts.get(s['account_id'])
                if acc and acc['token_version'] == s['token_version']:
                    return s['account_id']
        return None

    def _static(self):
        rel = self.path.split('?')[0].lstrip('/') or 'index.html'
        path = os.path.normpath(os.path.join(ROOT, rel.replace('/', os.sep)))
        if not path.startswith(ROOT) or not os.path.isfile(path):
            self.send_error(404); return
        ext = path.rsplit('.', 1)[-1].lower()
        ctype = {'html': 'text/html', 'jpg': 'image/jpeg', 'jpeg': 'image/jpeg', 'mp4': 'video/mp4',
                 'svg': 'image/svg+xml', 'js': 'text/javascript', 'css': 'text/css', 'png': 'image/png',
                 'webp': 'image/webp', 'ico': 'image/x-icon'}.get(ext, 'application/octet-stream')
        with open(path, 'rb') as f:
            data = f.read()
        self.send_response(200)
        self.send_header('Content-Type', ctype)
        self.send_header('Content-Length', str(len(data)))
        self.end_headers()
        self.wfile.write(data)

    def do_GET(self):
        p = self.path
        with _lock:
            if p.startswith('/ai/'):
                return self._agent()
            if p.endswith('me.php'):
                a = self._bearer()
                return self._send({'error': 'unauthorized'}, 401) if a is None else self._send({'ok': True, 'rev': accounts[a]['rev']})
            if p.endswith('data_get.php'):
                a = self._bearer()
                return self._send({'error': 'unauthorized'}, 401) if a is None else self._send({'rev': accounts[a]['rev'], 'data': accounts[a]['data']})
            if p.endswith('account.php'):
                a = self._bearer()
                if a is None:
                    return self._send({'error': 'unauthorized'}, 401)
                acc = accounts[a]
                pc = sc = 0
                d = acc.get('data')
                if isinstance(d, dict) and isinstance(d.get('projects'), list):
                    pc = len(d['projects'])
                    sc = sum(len(p.get('sprints') or []) for p in d['projects'])
                return self._send({'name': acc.get('display_name'), 'projectCount': pc, 'sprintCount': sc,
                                   'createdAt': acc.get('created_at'),
                                   'agent': {'active': bool(acc.get('agent_token')), 'created_at': acc.get('agent_created'),
                                             'ops_today': len(ai_today_ops(acc)), 'last_ops': (acc.get('agent_ops') or [])[-5:]}})
            self._static()

    def _agent(self):
        u = urlparse(self.path)
        token = u.path.split('/ai/')[-1].strip('/')
        aid = next((k for k, v in accounts.items() if v.get('agent_token') and v['agent_token'] == token), None)
        if aid is None:
            return self._send({'ok': False, 'error': 'invalid_token'}, 404)
        acc = accounts[aid]
        q = parse_qs(u.query)
        op = (q.get('op') or [''])[0]
        base = 'http://' + self.headers.get('Host', '127.0.0.1:8000') + '/ai/' + token
        blob = acc.get('data') or {'projects': []}
        if op == '':
            if 'text/html' in (self.headers.get('Accept') or ''):
                page = ('<html><body style="background:#05070d;color:#e8edf6;font-family:sans-serif;padding:40px">'
                        '<h1>SprintG — Agent Access</h1><p>Isto é um ponto de acesso de máquina. '
                        '<a style="color:#60a5fa" href="/">Abrir o SprintG</a> para gerir o acesso.</p></body></html>').encode()
                self.send_response(200); self.send_header('Content-Type', 'text/html; charset=utf-8')
                self.send_header('Content-Length', str(len(page))); self.end_headers(); self.wfile.write(page); return
            ai_log(acc, 'manual', None); save()
            return self._send(ai_manual(acc, base, token))
        if op == 'board':
            ai_log(acc, 'board', None); save()
            return self._send({'ok': True, 'projects': ai_board(blob)})
        if op in ('create', 'status'):
            if len(ai_today_ops(acc)) >= 50:
                return self._send({'ok': False, 'error': 'rate_limited',
                                   'hint': 'daily limit reached; try tomorrow or ask the owner to reset'}, 429)
            d, err = ai_decode_d((q.get('d') or [''])[0])
            if err:
                return self._send({'ok': False, 'error': err[0], 'hint': err[1]}, 404 if err[0] == 'not_found' else 400)
            res, err = (ai_create(blob, d) if op == 'create' else ai_status(blob, d))
            if err:
                return self._send({'ok': False, 'error': err[0], 'hint': err[1]}, 404 if err[0] == 'not_found' else 400)
            acc['data'] = blob
            acc['rev'] = acc.get('rev', 0) + 1
            ai_log(acc, op, str(res.get('type') or op) + ':' + str(res.get('title') or res.get('id')),
                   rev=acc['rev'], item_id=(res.get('id') if op == 'create' else None))
            save()
            return self._send({'ok': True, ('created' if op == 'create' else 'updated'): res})
        return self._send({'ok': False, 'error': 'bad_payload', 'hint': 'unknown op; use board|create|status'}, 400)

    def do_POST(self):
        p = self.path
        body = self._read()
        with _lock:
            if p.endswith('register.php'):
                code = gen_code(); aid = next_id[0]; next_id[0] += 1
                accounts[aid] = {'code_hash': fp(code), 'fpr': fp(code), 'token_version': 1, 'data': None, 'rev': 0,
                                 'display_name': None, 'created_at': time.strftime('%Y-%m-%d %H:%M:%S')}
                tok = mint(aid); save()
                return self._send({'code': fmt(code), 'token': tok, 'data': None, 'rev': 0})
            if p.endswith('account.php'):
                a = self._bearer()
                if a is None:
                    return self._send({'error': 'unauthorized'}, 401)
                name = (body.get('name') or '').strip()[:40]
                accounts[a]['display_name'] = name or None; save()
                return self._send({'ok': True, 'name': name or None})
            if p.endswith('ai_manage.php'):
                a = self._bearer()
                if a is None:
                    return self._send({'error': 'unauthorized'}, 401)
                acc = accounts[a]
                action = body.get('action')
                host = self.headers.get('Host', '127.0.0.1:8000')
                if action == 'generate' and acc.get('agent_token'):
                    return self._send({'ok': False, 'error': 'already_active', 'hint': 'revoke or regenerate first'}, 409)
                if action in ('generate', 'regenerate'):
                    t = gen_token()
                    acc['agent_token'] = t
                    acc['agent_created'] = time.strftime('%Y-%m-%d')
                    save()
                    return self._send({'ok': True, 'token': t, 'url': 'http://' + host + '/ai/' + t})
                if action == 'revoke':
                    acc['agent_token'] = None
                    save()
                    return self._send({'ok': True})
                return self._send({'ok': False, 'error': 'bad_payload', 'hint': 'action must be generate|regenerate|revoke'}, 400)
            if p.endswith('login.php'):
                c = norm(body.get('code', '')); f = fp(c)
                aid = next((k for k, v in accounts.items() if v['fpr'] == f), None)
                if aid is None:
                    return self._send({'error': 'invalid_code'}, 401)
                tok = mint(aid); save()
                return self._send({'token': tok, 'data': accounts[aid]['data'], 'rev': accounts[aid]['rev']})
            if p.endswith('logout.php'):
                hh = self.headers.get('Authorization', '')
                if hh.lower().startswith('bearer '):
                    sessions.pop(hashlib.sha256(hh[7:].strip().encode()).hexdigest(), None); save()
                self.send_response(204); self.end_headers(); return
            if p.endswith('migrate.php'):
                a = self._bearer()
                if a is None:
                    return self._send({'error': 'unauthorized'}, 401)
                acc = accounts[a]
                if acc['data'] is None and acc['rev'] == 0:
                    acc['data'] = body.get('data'); acc['rev'] = 1; save()
                    return self._send({'rev': 1})
                return self._send({'error': 'server_not_empty', 'rev': acc['rev'], 'data': acc['data']}, 409)
            if p.endswith('regenerate.php'):
                a = self._bearer()
                if a is None:
                    return self._send({'error': 'unauthorized'}, 401)
                code = gen_code(); acc = accounts[a]
                acc['code_hash'] = fp(code); acc['fpr'] = fp(code); acc['token_version'] += 1
                for sid in [s for s, v in sessions.items() if v['account_id'] == a]:
                    sessions.pop(sid, None)
                tok = mint(a); save()
                return self._send({'code': fmt(code), 'token': tok})
            self.send_error(404)

    def do_PUT(self):
        p = self.path
        body = self._read()
        with _lock:
            if p.endswith('data_put.php'):
                a = self._bearer()
                if a is None:
                    return self._send({'error': 'unauthorized'}, 401)
                acc = accounts[a]; rev = body.get('rev')
                if rev != acc['rev']:
                    if isinstance(rev, int) and 0 <= rev < acc['rev'] and isinstance(body.get('data'), dict):
                        win = [o for o in acc.get('agent_ops', []) if o.get('op') in ('create', 'status') and (o.get('rev') or 0) > rev]
                        # só funde quando o diário explica TODOS os increments desde o rev do cliente;
                        # escritas humanas intercaladas → 409 (a outra pessoa tem de resolver)
                        if len(win) == acc['rev'] - rev:
                            creates = [o for o in win if o['op'] == 'create']
                            acc['data'] = merge_new(acc['data'], body.get('data'), creates)
                            acc['rev'] += 1; save()
                            return self._send({'rev': acc['rev'], 'merged': True})
                    return self._send({'error': 'conflict', 'rev': acc['rev'], 'data': acc['data']}, 409)
                acc['data'] = body.get('data'); acc['rev'] += 1; save()
                return self._send({'rev': acc['rev']})
            self.send_error(404)

if __name__ == '__main__':
    load()
    print('SprintG dev-server (mock da API, só para desenvolvimento).')
    print('Abre  http://127.0.0.1:%d   —  NÃO uses file://.' % PORT)
    print('Persistência em dev-db.json (apaga para recomeçar limpo). Ctrl+C para parar.')
    ThreadingHTTPServer(('127.0.0.1', PORT), H).serve_forever()
