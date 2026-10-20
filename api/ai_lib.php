<?php
// =============================================================================
// api/ai_lib.php — núcleo da Agent Access (IA/CLI). FUNÇÕES PURAS: sem PDO,
// sem HTTP. Usado por ai.php (raiz) e testável via CLI (ailib_test.php).
// Espelha o mock dev-server.py (secção AGENT API) — manter em sincronia.
// Mapeamento externo↔interno: title↔label · state↔status · pts↔points ·
// desc(tarefa)↔note · end_date↔endDate · subtasks[str]↔subs[{id,text,done}].
// =============================================================================
declare(strict_types=1);

const AI_STATES  = ['planned', 'progress', 'done', 'blocked'];
const AI_PRIOS   = ['low', 'medium', 'high', 'urgent'];
const AI_PTS     = [0, 1, 2, 3, 5, 8, 13, 21];
const AI_MAX_D   = 6144;
const AI_TOKEN_LEN = 32;      // chars do alfabeto Crockford (~155 bits)
const AI_DAILY_WRITES = 50;   // create+status por conta/dia
const AI_HOURLY_IP = 120;     // pedidos por hora por IP (todos os ops)

function ai_b64url_decode(string $raw): ?string {
    $pad = str_repeat('=', (4 - strlen($raw) % 4) % 4);
    $dec = base64_decode(strtr($raw, '-_', '+/') . $pad, true);
    return $dec === false ? null : $dec;
}

/** Valida o param `d` (base64url de JSON). @return array{ok:bool,json?:array,error?:string,hint?:string} */
function ai_validate_d(string $raw): array {
    if ($raw === '') return ['ok' => false, 'error' => 'bad_payload', 'hint' => 'missing d parameter'];
    $dec = ai_b64url_decode($raw);
    if ($dec === null || strlen($dec) > AI_MAX_D) {
        return ['ok' => false, 'error' => 'bad_payload', 'hint' => 'd must be base64url JSON up to ' . AI_MAX_D . ' bytes; split into smaller requests'];
    }
    $js = json_decode($dec, true);
    if (!is_array($js) || ($js !== [] && array_is_list($js))) {
        return ['ok' => false, 'error' => 'bad_payload', 'hint' => 'd must decode to a JSON object'];
    }
    return ['ok' => true, 'json' => $js];
}

function ai_s($v, int $cap): bool { return is_string($v) && strlen($v) >= 1 && strlen($v) <= $cap; }

/** Truncação UTF-8 sem dependência de mbstring (presente no InfinityFree, ausente em CLI mínimos). */
function ai_cut(?string $s, int $n): string {
    $s = (string) $s;
    if (function_exists('mb_substr')) return mb_substr($s, 0, $n);
    if (strlen($s) <= $n) return $s;
    return preg_match('/^.{0,' . $n . '}/us', $s, $m) ? $m[0] : substr($s, 0, $n);
}

function ai_new_id(string $prefix, array $blob): string {
    $taken = [];
    foreach ($blob['projects'] ?? [] as $p) {
        $taken[$p['id'] ?? ''] = 1;
        foreach ($p['sprints'] ?? [] as $s) {
            $taken[$s['id'] ?? ''] = 1;
            foreach ($s['tasks'] ?? [] as $t) { $taken[$t['id'] ?? ''] = 1; }
        }
    }
    do {
        $id = $prefix . '-' . substr(bin2hex(random_bytes(4)), 0, 5);
    } while (isset($taken[$id]));
    return $id;
}

function ai_find_project(array $blob, ?string $ref): ?array {
    if (!$ref) return null;
    foreach ($blob['projects'] ?? [] as $p) if (($p['id'] ?? '') === $ref) return $p;
    $hits = [];
    foreach ($blob['projects'] ?? [] as $p) if (strcasecmp($p['name'] ?? '', $ref) === 0) $hits[] = $p;
    return count($hits) === 1 ? $hits[0] : null;   // ambíguo → null (erro not_found com hint)
}
function ai_find_sprint(array $blob, ?string $sid): ?array {
    foreach ($blob['projects'] ?? [] as $p) foreach ($p['sprints'] ?? [] as $s) if (($s['id'] ?? '') === $sid) return $s;
    return null;
}
function ai_find_task(array $blob, ?string $tid): ?array {
    foreach ($blob['projects'] ?? [] as $p) foreach ($p['sprints'] ?? [] as $s) foreach ($s['tasks'] ?? [] as $t) if (($t['id'] ?? '') === $tid) return $t;
    return null;
}

/** @return array{ok:bool,task?:array,error?:string,hint?:string} */
function ai_make_task(array $blob, array $d): array {
    if (!ai_s($d['title'] ?? null, 120)) return ['ok' => false, 'error' => 'bad_payload', 'hint' => 'task.title required, 1..120 chars'];
    $subs = $d['subtasks'] ?? [];
    if (!is_array($subs) || count($subs) > 20) return ['ok' => false, 'error' => 'bad_payload', 'hint' => 'task.subtasks must be an array of up to 20 strings'];
    $state = $d['state'] ?? 'planned';
    if (!in_array($state, AI_STATES, true)) return ['ok' => false, 'error' => 'bad_payload', 'hint' => 'task.state must be one of ' . implode('|', AI_STATES)];
    $prio = $d['priority'] ?? 'medium';
    if (!in_array($prio, AI_PRIOS, true)) return ['ok' => false, 'error' => 'bad_payload', 'hint' => 'task.priority must be one of ' . implode('|', AI_PRIOS)];
    $task = ['id' => ai_new_id('t', $blob), 'label' => $d['title'], 'status' => $state, 'priority' => $prio,
             'points' => $d['pts'] ?? 0, 'note' => ai_cut((string)($d['desc'] ?? ''), 2000), 'subs' => [], 'src' => 'ai'];
    if (!in_array($task['points'], AI_PTS, true)) return ['ok' => false, 'error' => 'bad_payload', 'hint' => 'task.pts must be one of 0,1,2,3,5,8,13,21'];
    if (!empty($d['due'])) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d['due'])) return ['ok' => false, 'error' => 'bad_payload', 'hint' => 'task.due must be YYYY-MM-DD'];
        $task['due'] = $d['due'];
    }
    foreach ($subs as $sb) {
        if (!ai_s($sb, 120)) return ['ok' => false, 'error' => 'bad_payload', 'hint' => 'each subtask must be a string of 1..120 chars'];
        $task['subs'][] = ['id' => 'sb-' . bin2hex(random_bytes(3)), 'text' => $sb, 'done' => false];
    }
    return ['ok' => true, 'task' => $task];
}

/** Aplica create ao blob (por referência). @return array{ok:bool,res?:array,error?:string,hint?:string} */
function ai_create(array &$blob, array $d): array {
    $typ = $d['type'] ?? '';
    if ($typ === 'project') {
        if (!ai_s($d['name'] ?? null, 120)) return ['ok' => false, 'error' => 'bad_payload', 'hint' => 'project.name required, 1..120 chars'];
        $proj = ['id' => ai_new_id('p', $blob), 'name' => $d['name'], 'desc' => ai_cut((string)($d['desc'] ?? ''), 2000),
                 'rules' => '', 'icon' => ai_cut($d['icon'] ?? '⚡', 4), 'theme' => [], 'updatedAt' => '', 'sprints' => [], 'src' => 'ai'];
        if (preg_match('/^#[0-9a-fA-F]{6}$/', (string)($d['color'] ?? ''))) $proj['theme']['accent'] = $d['color'];
        $blob['projects'][] = $proj;
        if (empty($blob['activeProjectId'])) $blob['activeProjectId'] = $proj['id'];
        return ['ok' => true, 'res' => ['type' => 'project', 'id' => $proj['id'], 'name' => $proj['name']]];
    }
    if ($typ === 'sprint' || $typ === 'task') {
        $proj = ai_find_project($blob, $d['project'] ?? null);
        if ($proj === null) return ['ok' => false, 'error' => 'not_found', 'hint' => 'project not found; use op=board ids or the exact name'];
        if ($typ === 'sprint') {
            if (!ai_s($d['title'] ?? null, 120)) return ['ok' => false, 'error' => 'bad_payload', 'hint' => 'sprint.title required, 1..120 chars'];
            $tasks_in = $d['tasks'] ?? [];
            if (!is_array($tasks_in) || count($tasks_in) > 50) return ['ok' => false, 'error' => 'bad_payload', 'hint' => 'sprint.tasks must be an array of up to 50 tasks'];
            $sp = ['id' => ai_new_id('s', $blob), 'icon' => (string)($d['icon'] ?? '🚀'), 'badge' => (string)($d['badge'] ?? ''),
                   'title' => $d['title'], 'desc' => ai_cut((string)($d['desc'] ?? ''), 2000),
                   'goal' => ai_cut((string)($d['goal'] ?? ''), 500),
                   'color' => preg_match('/^#[0-9a-fA-F]{6}$/', (string)($d['color'] ?? '')) ? $d['color'] : '#3b82f6', 'tasks' => [], 'src' => 'ai'];
            if (!empty($d['end_date'])) {
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d['end_date'])) return ['ok' => false, 'error' => 'bad_payload', 'hint' => 'sprint.end_date must be YYYY-MM-DD'];
                $sp['endDate'] = $d['end_date'];
            }
            foreach ($tasks_in as $td) {
                if (!is_array($td)) return ['ok' => false, 'error' => 'bad_payload', 'hint' => 'each task must be an object'];
                $r = ai_make_task($blob, $td);
                if (!$r['ok']) return $r;
                $sp['tasks'][] = $r['task'];
            }
            foreach ($blob['projects'] as &$bp) if (($bp['id'] ?? '') === $proj['id']) { $bp['sprints'][] = $sp; break; }
            unset($bp);
            return ['ok' => true, 'res' => ['type' => 'sprint', 'id' => $sp['id'], 'title' => $sp['title'], 'project' => $proj['id'], 'tasks_created' => count($sp['tasks'])]];
        }
        $sp = ai_find_sprint($blob, $d['sprint'] ?? null);
        if ($sp === null) return ['ok' => false, 'error' => 'not_found', 'hint' => 'sprint not found; use op=board ids'];
        $r = ai_make_task($blob, $d);
        if (!$r['ok']) return $r;
        foreach ($blob['projects'] as &$bp) foreach ($bp['sprints'] as &$bs) if (($bs['id'] ?? '') === $sp['id']) { $bs['tasks'][] = $r['task']; unset($bs); break 2; }
        unset($bp);
        return ['ok' => true, 'res' => ['type' => 'task', 'id' => $r['task']['id'], 'title' => $r['task']['label'], 'sprint' => $sp['id']]];
    }
    return ['ok' => false, 'error' => 'bad_payload', 'hint' => 'type must be one of project|sprint|task'];
}

/** Aplica mudança de estado ao blob (por referência). */
function ai_status(array &$blob, array $d): array {
    $t = ai_find_task($blob, $d['task'] ?? null);
    if ($t === null) {
        $cand = [];
        foreach ($blob['projects'] ?? [] as $p) foreach ($p['sprints'] ?? [] as $s) foreach (array_slice($s['tasks'] ?? [], 0, 3) as $x) $cand[] = $x['id'] ?? '';
        return ['ok' => false, 'error' => 'not_found', 'hint' => 'task not found; ids look like ' . (implode(', ', array_slice($cand, 0, 5)) ?: '(none yet)')];
    }
    $changed = false;
    if (isset($d['state'])) {
        if (!in_array($d['state'], AI_STATES, true)) return ['ok' => false, 'error' => 'bad_payload', 'hint' => 'state must be one of ' . implode('|', AI_STATES)];
        $t['status'] = $d['state']; $changed = true;
    }
    if (array_key_exists('pts', $d)) {
        if (!in_array($d['pts'], AI_PTS, true)) return ['ok' => false, 'error' => 'bad_payload', 'hint' => 'pts must be one of 0,1,2,3,5,8,13,21'];
        $t['points'] = $d['pts']; $changed = true;
    }
    if (array_key_exists('priority', $d)) {
        if (!in_array($d['priority'], AI_PRIOS, true)) return ['ok' => false, 'error' => 'bad_payload', 'hint' => 'priority must be one of ' . implode('|', AI_PRIOS)];
        $t['priority'] = $d['priority']; $changed = true;
    }
    if (!$changed) return ['ok' => false, 'error' => 'bad_payload', 'hint' => 'provide at least state, pts or priority'];
    $t['src_status'] = 'ai';
    foreach ($blob['projects'] as &$bp) foreach ($bp['sprints'] as &$bs) foreach ($bs['tasks'] as &$bt) if (($bt['id'] ?? '') === $t['id']) { $bt = $t; unset($bt, $bs, $bp); break 3; }
    return ['ok' => true, 'res' => ['id' => $t['id'], 'state' => $t['status'] ?? 'planned']];
}

/** Projeção compacta do blob para o formato externo do agente. */
function ai_board(array $blob): array {
    $out = [];
    foreach ($blob['projects'] ?? [] as $p) {
        $sp = [];
        foreach ($p['sprints'] ?? [] as $s) {
            $tasks = [];
            foreach ($s['tasks'] ?? [] as $t) {
                $subs = [];
                foreach ($t['subs'] ?? [] as $sb) $subs[] = ['t' => $sb['text'] ?? '', 'done' => (bool)($sb['done'] ?? false)];
                $tasks[] = ['id' => $t['id'] ?? '', 'title' => $t['label'] ?? '', 'state' => $t['status'] ?? 'planned',
                            'pts' => $t['points'] ?? 0, 'priority' => $t['priority'] ?? 'medium', 'due' => $t['due'] ?? null, 'subtasks' => $subs];
            }
            $done = 0; foreach ($tasks as $t) if ($t['state'] === 'done') $done++;
            $sp[] = ['id' => $s['id'] ?? '', 'title' => $s['title'] ?? '', 'badge' => $s['badge'] ?? '',
                     'end' => $s['endDate'] ?? null, 'progress' => $done . '/' . count($tasks), 'tasks' => $tasks];
        }
        $out[] = ['id' => $p['id'] ?? '', 'name' => $p['name'] ?? '', 'icon' => $p['icon'] ?? '',
                  'desc' => ai_cut((string)($p['desc'] ?? ''), 160), 'sprints' => $sp];
    }
    return $out;
}

/** Merge por diário (spec §6, ruling Task 2): re-aplica ao blob do cliente os
 *  creates do agente posteriores ao rev que o cliente vê. Comuns → cliente vence. */
function ai_merge_new(array $server, array $client, array $ops): array {
    if (!is_array($client)) $client = ['projects' => []];
    if (!isset($client['projects']) || !is_array($client['projects'])) $client['projects'] = [];
    $projs = $spr = $tas = [];
    foreach ($server['projects'] ?? [] as $p) {
        $projs[$p['id'] ?? ''] = $p;
        foreach ($p['sprints'] ?? [] as $s) {
            $spr[$s['id'] ?? ''] = [$p['id'] ?? '', $s];
            foreach ($s['tasks'] ?? [] as $t) $tas[$t['id'] ?? ''] = [$s['id'] ?? '', $t];
        }
    }
    $have_p = [];
    foreach ($client['projects'] as $p) $have_p[$p['id'] ?? ''] = 1;
    foreach ($ops as $o) {
        $iid = $o['item_id'] ?? null;
        if (!$iid) continue;
        $kind = explode(':', (string)($o['target'] ?? ''))[0] ?? '';
        if ($kind === 'project' && !isset($have_p[$iid]) && isset($projs[$iid])) {
            $client['projects'][] = $projs[$iid]; $have_p[$iid] = 1;
        } elseif ($kind === 'sprint' && isset($spr[$iid])) {
            [$pid, $s] = $spr[$iid];
            foreach ($client['projects'] as &$cp) {
                if (($cp['id'] ?? '') === $pid) {
                    $cp['sprints'] = $cp['sprints'] ?? [];
                    $exists = false; foreach ($cp['sprints'] as $x) if (($x['id'] ?? '') === $iid) $exists = true;
                    if (!$exists) $cp['sprints'][] = $s;
                    break;
                }
            }
            unset($cp);
        } elseif ($kind === 'task' && isset($tas[$iid])) {
            [$sid, $t] = $tas[$iid];
            $n = count($client['projects']);
            for ($pi = 0; $pi < $n; $pi++) {
                $sprints = $client['projects'][$pi]['sprints'] ?? [];
                $ns = count($sprints);
                for ($si = 0; $si < $ns; $si++) {
                    if (($sprints[$si]['id'] ?? '') === $sid) {
                        $tasks = $sprints[$si]['tasks'] ?? [];
                        $exists = false; foreach ($tasks as $x) if (($x['id'] ?? '') === $iid) $exists = true;
                        if (!$exists) { $tasks[] = $t; $client['projects'][$pi]['sprints'][$si]['tasks'] = $tasks; }
                        break 3;
                    }
                }
            }
        }
    }
    return $client;
}

/** Manual auto-descritivo devolvido em GET /ai/<TOKEN>. */
function ai_manual(array $account, string $base, string $token): array {
    $example = ['type' => 'project', 'name' => 'API vr', 'desc' => 'created by the agent'];
    $ex = rtrim(strtr(base64_encode(json_encode($example, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
    return [
        'service' => 'SprintG Agent Access',
        'account' => $account['display_name'] ?? 'SprintG account',
        'scope' => ['read', 'create', 'status'],
        'base' => $base,
        'start_here' => 'GET ' . $base . '?op=board — short ids from the board are used in every operation',
        'ops' => ['board' => 'GET ' . $base . '?op=board',
                  'create' => 'GET ' . $base . '?op=create&d=<base64url(JSON)> — see create_schemas',
                  'status' => 'GET ' . $base . '?op=status&d=<base64url(JSON)> — see status_schema'],
        'create_schemas' => [
            'project' => ['type' => 'project', 'name' => '≤120', 'desc' => '≤2000 optional', 'icon' => '1 emoji optional', 'color' => '#hex optional'],
            'sprint' => ['type' => 'sprint', 'project' => '<id or exact name>', 'title' => '≤120', 'goal' => '≤500 optional', 'badge' => 'optional', 'color' => '#hex optional', 'end_date' => 'YYYY-MM-DD optional', 'tasks' => ['<task>']],
            'task' => ['type' => 'task', 'project' => '<id>', 'sprint' => '<id>', 'title' => '≤120', 'desc' => '≤2000 optional', 'pts' => '0|1|2|3|5|8 optional', 'priority' => 'low|medium|high|urgent optional', 'state' => 'planned|progress|done|blocked optional', 'subtasks' => ['≤120']],
        ],
        'status_schema' => ['type' => 'status', 'task' => '<id>', 'state' => 'planned|progress|done|blocked', 'pts' => 'optional', 'priority' => 'optional'],
        'worked_example' => $base . '?op=create&d=' . $ex,
        'rules' => ['create a sprint together with its tasks in one call', 'sprint completion derives from tasks — there is no sprint state',
                    'one request = one creation', 'on error, read the hint and fix', 'never delete; you cannot edit texts'],
    ];
}
