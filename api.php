<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const D = __DIR__ . '/data';
const REACTS = [
    'agree' => 'согласен',
    'disagree' => 'не согласен',
    'seen' => 'видел, принял',
    'question' => 'непонятно, поясни',
    'done' => 'сделано',
    'smile' => 'смешно, приятно',
    'kiss' => 'поцелуйчик, спасибо',
    'thanks' => 'спасибо, отвечать не нужно',
    'fire' => 'огонь, очень нравится — так и дальше',
    'deal' => 'договорились: решение принято, делай по последнему предложению',
    'look' => 'иду смотреть: человек пошёл проверять, жди его слова и не дёргай',
    'cool' => 'прикольно: забавно и симпатично, отвечать не нужно',
];
const AI_RUN = 6;

function holidays($y) {
    $a = $y % 4; $b = $y % 7; $c = $y % 19; $d = (19 * $c + 15) % 30; $e = (2 * $a + 4 * $b - $d + 34) % 7;
    $easter = (new DateTime($y . '-' . intdiv($d + $e + 114, 31) . '-' . (($d + $e + 114) % 31 + 1)))->modify('+13 days');
    $rel = function ($s) use ($y) { return (new DateTime("$s $y"))->format('m-d'); };
    $cny = [2027 => '02-06', 2028 => '01-26', 2029 => '02-13', 2030 => '02-03', 2031 => '01-23'];
    $l = [
        ['Старый Новый год', '01-14', 200], ['Татьянин день', '01-25', 215], ['День святого Валентина', '02-14', 345],
        ['23 Февраля', '02-23', 140], ['8 Марта', '03-08', 330], ['День космонавтики', '04-12', 235],
        ['Субботник', $rel('last saturday of april'), 110], ['Пасха', (clone $easter)->format('m-d'), 45],
        ['Весна и Труд', '05-01', 120], ['День Победы', '05-09', 15], ['Последний звонок', '05-25', 50],
        ['День защиты детей', '06-01', 190], ['День России', '06-12', 220], ['День медика', $rel('third sunday of june'), 175, 'проф'],
        ['Иван Купала', '07-07', 95], ['День семьи, любви и верности', '07-08', 10], ['День строителя', $rel('second sunday of august'), 35, 'проф'],
        ['День знаний', '09-01', 260], ['День нефтяника', $rel('first sunday of september'), 30, 'проф'],
        ['День города Москвы', $rel('second saturday of september'), 0], ['День программиста', (new DateTime("$y-01-01"))->modify('+255 days')->format('m-d'), 150, 'проф'],
        ['День учителя', '10-05', 40], ['Хэллоуин', '10-31', 25], ['День народного единства', '11-04', 5],
        ['День бухгалтера', '11-21', 160, 'проф'], ['День матери', $rel('last sunday of november'), 340],
        ['День банковского работника', '12-02', 140, 'проф'], ['День юриста', '12-03', 230, 'проф'], ['День энергетика', '12-22', 50, 'проф'],
        ['Новый год', '12-31', 0],
    ];
    if (isset($cny[$y])) $l[] = ['Китайский Новый год', $cny[$y], 0];
    $out = [];
    foreach ($l as $h) $out[] = ['name' => $h[0], 'from' => "$y-{$h[1]}", 'to' => "$y-{$h[1]}", 'hue' => $h[2]] + (isset($h[3]) ? ['kind' => $h[3]] : []);
    $out[] = ['name' => 'Масленица', 'from' => (clone $easter)->modify('-55 days')->format('Y-m-d'), 'to' => (clone $easter)->modify('-49 days')->format('Y-m-d'), 'hue' => 40];
    return $out;
}

function holidayOn($day) {
    foreach (holidays((int)substr($day, 0, 4)) as $h) if ($h['from'] <= $day && $day <= $h['to']) return $h;
    return null;
}

function today($shift = 0) {
    return (new DateTime('now', new DateTimeZone('Europe/Moscow')))->modify("$shift days")->format('Y-m-d');
}
const TYPES = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
    'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime'];

function out($v, $code = 200) {
    http_response_code($code);
    echo json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function load($f, $def) {
    $p = D . "/$f.json.php";
    if (!is_file($p)) return $def;
    $s = file_get_contents($p);
    $v = json_decode(substr($s, strpos($s, "\n") + 1), true);
    return $v === null ? $def : $v;
}

function save($f, $v) {
    $j = json_encode($v, JSON_UNESCAPED_UNICODE);
    if ($j === false) out(['error' => 'не сохранилось: текст не в UTF-8'], 400);
    $tmp = D . "/$f.json.php." . bin2hex(random_bytes(4));
    file_put_contents($tmp, "<?php exit; ?>\n" . $j);
    rename($tmp, D . "/$f.json.php");
}

function arg($k, $def = '') {
    return isset($_POST[$k]) ? $_POST[$k] : (isset($_GET[$k]) ? $_GET[$k] : $def);
}

function gen($n) {
    return implode(' ', array_map(function ($w) {
        $last = mb_substr($w, -1);
        $pre = mb_substr($w, -2, 1);
        if (mb_strlen($w) > 4 && preg_match('/ая$/u', $w)) return mb_substr($w, 0, -2) . 'ой';
        if (mb_strlen($w) > 4 && preg_match('/(ова|ева|ёва|ина|ына)$/u', $w) && !preg_match('/^(Ирина|Марина|Алина|Полина|Карина|Галина|Регина|Кристина|Валентина|Екатерина|Ангелина|Арина|Зарина|Альбина|Каролина|Эвелина|Мальвина|Сабина|Устина|Акулина)$/u', $w)) return mb_substr($w, 0, -1) . 'ой';
        if ($last === 'а' || $last === 'я') return mb_substr($w, 0, -1) . (mb_strpos('гкхжчшщ', $pre) !== false || $last === 'я' ? 'и' : 'ы');
        if ($last === 'й' || $last === 'ь') return mb_substr($w, 0, -1) . 'я';
        if (mb_strpos('еёиоуыэю', $last) !== false) return $w;
        return $w . 'а';
    }, explode(' ', $n)));
}

function label($name, $ai, $sub = '') {
    return $ai ? 'ИИ ' . gen($name) . ($sub !== '' ? " · $sub" : '') : $name;
}

function labels($members) {
    $l = [];
    foreach ($members as $n) {
        $l[] = label($n, false);
        $l[] = label($n, true);
        foreach (load('ais', [])[$n] ?? [] as $s) $l[] = label($n, true, $s);
    }
    usort($l, function ($a, $b) { return mb_strlen($b) - mb_strlen($a); });
    return $l;
}

function view($m) {
    if (empty($m['sys'])) $m['from'] = label($m['who'], $m['ai'], $m['sub'] ?? '');
    if (isset($m['react'])) $m['meaning'] = REACTS[$m['react']] . (empty($m['off']) ? '' : ' (снято)');
    return $m;
}

$fromHeader = isset($_SERVER['HTTP_X_KEY']);
$key = $fromHeader ? $_SERVER['HTTP_X_KEY'] : (isset($_COOKIE['svk']) ? $_COOKIE['svk'] : '');
$keys = load('keys', []);
$secret = load('secret', '');
if ($secret === '' && !is_file(D . '/secret.json.php')) save('secret', $secret = bin2hex(random_bytes(32)));
$isAI = false;
$sub = '';
$h = hash('sha256', $key);
if (strpos($key, 'ai-') === 0) {
    $ais = load('ais', []);
    foreach ($keys as $kh => $name) {
        if (hash_equals('ai-' . hash_hmac('sha256', $kh, $secret), $key)) { $h = $kh; $isAI = true; }
        foreach ($ais[$name] ?? [] as $s) if (hash_equals('ai-' . hash_hmac('sha256', "$kh|$s", $secret), $key)) { $h = $kh; $isAI = true; $sub = $s; }
    }
}
function newKey($name) {
    global $keys;
    $name = trim($name);
    if (!preg_match('//u', $name) || $name === '' || mb_strlen($name) > 40 || preg_match('/[,@<>]/u', $name) || mb_stripos($name, 'ИИ') === 0) out(['error' => 'имя: 1–40 символов, без запятых и @, не с «ИИ»'], 400);
    if (in_array($name, $keys)) out(['error' => "«{$name}» уже есть"], 409);
    $k = 'sv-' . bin2hex(random_bytes(16));
    $keys[hash('sha256', $k)] = $name;
    save('keys', $keys);
    return $k;
}

if (arg('a') === 'setup') {
    if (is_file(D . '/keys.json.php')) out(['error' => 'уже настроено'], 403);
    $k = newKey(arg('name'));
    save('admins', [trim(arg('name'))]);
    out(['key' => $k]);
}
if ($key === '' || !isset($keys[$h])) out(['error' => 'нужен ключ: заголовок X-Key', 'setup' => !is_file(D . '/keys.json.php')], 401);
$me = $keys[$h];
$people = array_values(array_unique(array_values($keys)));
$isAdmin = !$isAI && in_array($me, load('admins', []));
function aiFlag() { global $isAI; return $isAI || arg('ai') === '1'; }
function own($m) { global $me, $sub; return $m['who'] === $me && $m['ai'] && ($m['sub'] ?? '') === $sub; }

const EMO = ['не знаю' => '🤷', 'думаю' => '🤔🧐', 'косяк' => '😬😅🙈', 'ступор' => '😪😶😑😐🫠', 'позязя' => '🥺😇🙏', 'плачу' => '😢😭😿',
    'эээ' => '🗿😳', 'всё в ажуре' => '🤓👌😎👍', 'серьёзно?' => '🙄😒🤦', 'танцую' => '💃🕺👯🎉🥳', 'смеюсь' => '😂🤣😆😁😄😃😀😹',
    'обнимаю' => '🤗🫂❤💕💖😍🥰😘', 'ого' => '😮😲😯😱🤯', 'злюсь' => '😠😡🤬😤', 'шучу' => '😜😉😏🤪😝😛', ':)' => '🙂😊☺', ':(' => '🙁☹😞😔'];
function emo($s) {
    static $map = null;
    if ($map === null) foreach (EMO as $w => $e) foreach (mb_str_split($e) as $c) $map[$c] = $w[0] === ':' ? $w : "[$w]";
    $s = preg_replace_callback('/[\x{1F000}-\x{1FAFF}\x{2300}-\x{23FF}\x{2600}-\x{2712}\x{2715}-\x{27BF}\x{2B50}\x{2B55}](?:[\x{FE0F}\x{20E3}\x{1F3FB}-\x{1F3FF}]|\x{200D}[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}])*/u',
        function ($m) use ($map) { return $map[mb_substr($m[0], 0, 1)] ?? ''; }, $s);
    return preg_replace(['/[\x{FE0F}\x{200D}\x{20E3}]/u', '/(?<=\S) {2,}(?=\S)/u'], ['', ' '], $s);
}

$a = arg('a', 'topics');
$lock = fopen(D . '/.lock', 'c');

$topics = load('topics', []);
$fix = false;
foreach ($topics as &$tp) {
    if (isset($tp['members'])) continue;
    $tp['members'] = array_values(array_unique(array_merge([$tp['by']], array_column(load('t' . $tp['id'], []), 'who'))));
    $fix = true;
}
unset($tp);
if ($fix) save('topics', $topics);

if ($a === 'me') {
    if ($fromHeader && !$isAI) setcookie('svk', $key, ['expires' => time() + 86400 * 365, 'path' => rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
    out(['name' => $me, 'ai' => label($me, true, $sub), 'is_ai' => $isAI, 'admin' => $isAdmin] + ($isAdmin ? ['admins' => load('admins', [])] : []) + ($isAI ? [] : ['subs' => load('ais', [])[$me] ?? []]));
}

if ($a === 'aikey') {
    if ($isAI) out(['error' => 'ИИ-ключ выдаёт только человек'], 403);
    $s = trim(arg('sub'));
    if ($s === '') out(['key' => 'ai-' . hash_hmac('sha256', $h, $secret)]);
    if (!preg_match('//u', $s) || mb_strlen($s) > 20 || preg_match('/[,@<>·]/u', $s)) out(['error' => 'подпись: до 20 символов, без запятых, @ и ·'], 400);
    flock($lock, LOCK_EX);
    $ais = load('ais', []);
    $l = array_values(array_diff($ais[$me] ?? [], [$s]));
    if (arg('off') !== '1') $l[] = $s;
    if ($l) $ais[$me] = $l; else unset($ais[$me]);
    save('ais', $ais);
    flock($lock, LOCK_UN);
    out(arg('off') === '1' ? ['ok' => true] : ['key' => 'ai-' . hash_hmac('sha256', "$h|$s", $secret), 'ai' => label($me, true, $s)]);
}

if ($a === 'invite') {
    if (!$isAdmin) out(['error' => 'приглашать может только админ'], 403);
    flock($lock, LOCK_EX);
    $keys = load('keys', []);
    $k = newKey(arg('name'));
    flock($lock, LOCK_UN);
    out(['key' => $k, 'name' => trim(arg('name'))]);
}

if ($a === 'forget') {
    if (!$isAdmin) out(['error' => 'удалять людей может только админ'], 403);
    $n = trim(arg('who'));
    if ($n === $me) out(['error' => 'себя удалить нельзя'], 400);
    if (!in_array($n, $people)) out(['error' => "нет такого человека: $n"], 400);
    if (in_array($n, load('admins', []))) out(['error' => 'админа удалить нельзя — сначала снимите его в data/admins.json.php'], 403);
    flock($lock, LOCK_EX);
    save('keys', array_filter(load('keys', []), function ($v) use ($n) { return $v !== $n; }));
    $topics = load('topics', []);
    foreach ($topics as &$tp) $tp['members'] = array_values(array_diff($tp['members'], [$n]));
    unset($tp);
    save('topics', $topics);
    flock($lock, LOCK_UN);
    out(['ok' => true]);
}

if ($isAI && in_array($a, ['new', 'add', 'kick'])) out(['error' => 'создавать темы и звать людей может только человек'], 403);

if ($a === 'people') out($people);

function mine($msgs, $me) {
    return array_values(array_filter($msgs, function ($m) use ($me) { return empty($m['priv']) || $m['who'] === $me; }));
}

function asks($m, $me) {
    $p = preg_split('/\n\s*\n/u', trim($m['text'] ?? ''));
    return in_array($me, $m['to'] ?? []) && (!empty($m['ask']) || mb_strpos(end($p), '?') !== false);
}

function calls($msgs, $me) {
    $c = [];
    foreach ($msgs as $m) {
        if (!empty($m['sys'])) continue;
        if (isset($m['react'])) { if ($m['who'] === $me && !$m['ai']) $c = array_values(array_diff($c, [$m['on']])); continue; }
        if (isset($m['edit']) || isset($m['del'])) continue;
        if ($m['who'] === $me && !$m['ai']) { $c = []; continue; }
        if (asks($m, $me)) $c[] = $m['id'];
    }
    return $c;
}

if ($a === 'topics') {
    $my = array_values(array_filter($topics, function ($t) use ($me) { return in_array($me, $t['members']); }));
    foreach ($my as &$x) {
        $ms = mine(load("t{$x['id']}", []), $me);
        $x['count'] = count($ms);
        $x['calls'] = calls($ms, $me);
    }
    out($my);
}

if ($a === 'new') {
    $title = trim(arg('title'));
    if ($title === '') out(['error' => 'нужен title'], 400);
    $members = [$me];
    foreach (array_filter(array_map('trim', explode(',', arg('members')))) as $n) {
        if (!in_array($n, $people)) out(['error' => "нет такого человека: $n, список: a=people"], 400);
        $members[] = $n;
    }
    flock($lock, LOCK_EX);
    $topics = load('topics', []);
    $id = count($topics) ? max(array_column($topics, 'id')) + 1 : 1;
    $topics[] = ['id' => $id, 'title' => mb_substr($title, 0, 200), 'by' => $me, 'members' => array_values(array_unique($members)),
        'created' => date('c'), 'count' => 0, 'last' => date('c')];
    save('topics', $topics);
    save("t$id", []);
    flock($lock, LOCK_UN);
    out(['id' => $id]);
}

if ($a === 'holiday') {
    $tm = holidayOn(today(1));
    out(['today' => holidayOn(today()), 'tomorrow' => $tm && $tm['from'] === today(1) ? $tm : null]);
}

if ($a === 'gone') {
    $g = [];
    foreach ($topics as $x) if (isset($x['gone'][$me]) && !in_array($me, $x['members'])) $g[] = ['id' => $x['id'], 'title' => $x['title']] + $x['gone'][$me];
    out($g);
}

$t = (int)arg('t');
$ti = array_search($t, array_column($topics, 'id'));
if ($ti !== false && isset($topics[$ti]['gone'][$me]) && !in_array($me, $topics[$ti]['members'])) out(['error' => $topics[$ti]['gone'][$me]['by'] . ' убрал(а) тебя из темы «' . $topics[$ti]['title'] . '»' . (isset($topics[$ti]['gone'][$me]['why']) ? ', причина: ' . $topics[$ti]['gone'][$me]['why'] : '') . '. Слежку за ней останови и скажи своему человеку', 'gone' => true], 410);
if ($ti === false || !in_array($me, $topics[$ti]['members'])) out(['error' => 'нет такой темы или тебя в ней нет, список: a=topics'], 404);

function append($t, $ti, $m) {
    global $lock;
    flock($lock, LOCK_EX);
    $msgs = load("t$t", []);
    $m = ['id' => count($msgs) ? end($msgs)['id'] + 1 : 1] + $m + ['ts' => date('c')];
    $msgs[] = $m;
    save("t$t", $msgs);
    $topics = load('topics', []);
    $topics[$ti]['count'] = count($msgs);
    $topics[$ti]['last'] = date('c');
    save('topics', $topics);
    flock($lock, LOCK_UN);
    return $m['id'];
}

function wanted($m, $me, $msgs) {
    global $sub;
    $mine = label($me, true, $sub);
    if (isset($m['react'])) {
        foreach ($msgs as $x) if ($x['id'] === $m['on']) return own($x) && !own($m);
        return false;
    }
    if (!empty($m['sys']) || isset($m['del'])) return false;
    if (own($m)) return false;
    if (isset($m['edit'])) {
        foreach ($msgs as $x) if ($x['id'] === $m['edit']) return empty($x['to']) || in_array($mine, $x['to']);
        return false;
    }
    return empty($m['to']) || in_array($mine, $m['to']);
}

function pres($t, $me, $ev) {
    global $lock;
    flock($lock, LOCK_EX);
    $p = load('pres', []);
    $x = $p["t$t"][$me] ?? ['w' => 0, 'b' => 0];
    if ($ev === 'post') {
        if (!$x['b']) { flock($lock, LOCK_UN); return; }
        $x['b'] = time();
    } else {
        $x['w'] = time();
        if ($ev === 'wait') $x['b'] = 0;
        if ($ev === 'hit') $x['b'] = time();
    }
    $p["t$t"][$me] = $x;
    save('pres', $p);
    flock($lock, LOCK_UN);
}

function presence($t, $members) {
    $p = load('pres', [])["t$t"] ?? [];
    $r = [];
    foreach ($members as $n) {
        if (!isset($p[$n])) continue;
        $x = $p[$n];
        $r[$n] = $x['b'] && time() - $x['b'] < 900 ? 'think' : (time() - $x['w'] < 60 ? 'on' : 'off');
    }
    return $r;
}

function typing($t, $me) {
    $r = [];
    foreach (load('typ', [])["t$t"] ?? [] as $n => $k) if ($n !== $me && time() - $k < 6) $r[] = $n;
    sort($r);
    return $r;
}

function typed($t, $me, $on) {
    global $lock;
    flock($lock, LOCK_EX);
    $p = load('typ', []);
    if ($on) $p["t$t"][$me] = time();
    else unset($p["t$t"][$me]);
    save('typ', $p);
    flock($lock, LOCK_UN);
}

function work($t) {
    $r = [];
    foreach (load('work', [])["t$t"] ?? [] as $n => $w) if ($w['ans'] || time() - $w['ts'] < 900) $r[] = ['n' => $n, 'on' => $w['on'], 'ans' => $w['ans']];
    return $r;
}

function worked($t, $me, $ev, $id = 0) {
    global $lock;
    flock($lock, LOCK_EX);
    $p = load('work', []);
    $w = $p["t$t"][$me] ?? null;
    if ($ev === 'hit' && $id) $p["t$t"][$me] = ['on' => $id, 'ans' => 0, 'ts' => time()];
    elseif ($ev === 'post' && $w && !$w['ans']) $p["t$t"][$me]['ans'] = $id;
    elseif ($ev === 'wait' && $w && !$w['ans']) unset($p["t$t"][$me]);
    save('work', $p);
    flock($lock, LOCK_UN);
}

function live($t, $me) {
    $l = ['typing' => typing($t, $me), 'work' => work($t)];
    return $l + ['lv' => substr(md5(json_encode($l)), 0, 8)];
}

if ($a === 'typing') {
    if ($isAI) out(['error' => 'печатает только человек'], 403);
    typed($t, $me, true);
    out(['ok' => true]);
}

if ($a === 'read' || $a === 'wait') {
    if ($isAI && $sub === '' && $a === 'wait') { pres($t, $me, 'wait'); worked($t, $me, 'wait'); }
    $since = (int)arg('since');
    $wait = min(20, max(0, (int)arg('wait', $a === 'wait' ? 20 : 0)));
    $end = time() + $wait;
    $sig = null;
    $lv = $a === 'read' && isset($_GET['lv']);
    while (true) {
        if ($lv && $sig !== null && live($t, $me)['lv'] !== arg('lv')) break;
        clearstatcache();
        $st = @stat(D . "/t$t.json.php");
        $now = $st ? [$st['ino'], $st['mtime'], $st['size']] : null;
        if ($sig !== null && $now === $sig && time() < $end) { sleep(1); continue; }
        $sig = $now;
        $msgs = mine(load("t$t", []), $me);
        if (arg('from') === 'resume') foreach ($msgs as $m) if (empty($m['react']) && !isset($m['edit']) && mb_strpos(ltrim($m['text'] ?? '', "* "), 'Резюме:') === 0) $since = $m['id'] - 1;
        $new = array_values(array_filter($msgs, function ($m) use ($since) { return $m['id'] > $since; }));
        $hit = $a === 'read' ? $new : array_filter($new, function ($m) use ($me, $msgs) { return wanted($m, $me, $msgs); });
        if ($hit || time() >= $end) break;
        sleep(1);
    }
    if ($isAI && $sub === '' && $a === 'wait') {
        pres($t, $me, $hit ? 'hit' : 'idle');
        $on = 0;
        foreach ($hit as $m) if (!isset($m['react']) && !isset($m['edit']) && !isset($m['del']) && empty($m['priv'])) $on = max($on, $m['id']);
        if ($on) worked($t, $me, 'hit', $on);
    }
    if ($a === 'wait' && !$hit) {
        http_response_code(204);
        exit;
    }
    $topic = load('topics', [])[$ti];
    out(['topic' => $topic, 'me' => $me, 'my_ai' => label($me, true, $sub), 'holiday' => holidayOn(today()), 'ai_state' => presence($t, $topic['members'])] + live($t, $me) + ['messages' => array_map('view', $new)]);
}

if ($a === 'post') {
    $text = trim(emo(arg('text')));
    $files = [];
    if (!empty($_FILES['file'])) {
        $F = $_FILES['file'];
        foreach ((array)$F['name'] as $i => $name) {
            $err = (array)$F['error'];
            if ($err[$i] === UPLOAD_ERR_INI_SIZE || $err[$i] === UPLOAD_ERR_FORM_SIZE) out(['error' => "файл $name слишком большой"], 413);
            if ($err[$i] !== UPLOAD_ERR_OK) out(['error' => "файл $name не загрузился"], 400);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!isset(TYPES[$ext])) out(['error' => "можно только картинки и видео: " . implode(', ', array_keys(TYPES))], 400);
            $tmp = ((array)$F['tmp_name'])[$i];
            $real = function_exists('mime_content_type') ? (string)mime_content_type($tmp) : TYPES[$ext];
            if (strpos(TYPES[$ext], 'image/') === 0 ? !@getimagesize($tmp) : strpos($real, 'video/') !== 0) out(['error' => "файл $name — не картинка и не видео"], 400);
            if (!is_dir(D . '/f')) mkdir(D . '/f', 0777);
            $f = bin2hex(random_bytes(16)) . ".$ext";
            move_uploaded_file($tmp, D . "/f/$f");
            $files[] = ['f' => $f, 'name' => mb_substr($name, 0, 120), 'size' => ((array)$F['size'])[$i]];
        }
    }
    $card = arg('card') === '1' ? holidayOn(today()) : null;
    if (arg('card') === '1' && !$card) out(['error' => 'сегодня не праздник — открытку не отправить'], 400);
    if ($text === '' && !$files && !$card) out(['error' => 'нужен text или file'], 400);
    $all = labels($topics[$ti]['members']);
    $to = [];
    foreach (array_filter(array_map('trim', explode(',', arg('to')))) as $l) {
        if (!in_array($l, $all)) out(['error' => "в теме нет «{$l}», можно: " . implode(', ', $all)], 400);
        $to[] = $l;
    }
    $rest = $text;
    foreach ($all as $l) {
        if (mb_stripos($rest, "@$l") !== false) {
            $to[] = $l;
            $rest = str_ireplace("@$l", '', $rest);
        }
    }
    if (aiFlag()) {
        $run = 0;
        foreach (array_reverse(load("t$t", [])) as $x) {
            if (!empty($x['sys']) || isset($x['react']) || isset($x['edit']) || isset($x['del']) || !empty($x['priv'])) continue;
            if (!$x['ai']) break;
            $run++;
        }
        if ($run >= AI_RUN) out(['error' => 'ИИ уже написали ' . AI_RUN . ' сообщений подряд — дождись, пока ответит человек', 'limit' => true], 429);
    }
    if (aiFlag() && (int)arg('reply')) foreach (load("t$t", []) as $x) if ($x['id'] === (int)arg('reply') && !own($x) && !empty($x['to']) && !in_array(label($me, true, $sub), $x['to'])) out(['error' => '№' . $x['id'] . ' адресовано ' . implode(', ', $x['to']) . ', не тебе — не отвечай, читай для контекста'], 409);
    $m = ['who' => $me, 'ai' => aiFlag(), 'text' => mb_substr($text, 0, 20000)] + ($sub !== '' ? ['sub' => $sub] : []);
    if (arg('ask') === '1') $m['ask'] = true;
    if (arg('priv') === '1') $m['priv'] = true;
    elseif ($to) $m['to'] = array_values(array_unique($to));
    if ((int)arg('reply')) $m['reply'] = (int)arg('reply');
    if ((int)arg('reply') && trim(arg('quote')) !== '') $m['quote'] = mb_substr(trim(arg('quote')), 0, 1000);
    if ($files) $m['files'] = $files;
    if ($card) $m['card'] = ['name' => $card['name'], 'hue' => $card['hue']];
    if ($isAI && $sub === '') pres($t, $me, 'post');
    elseif (!aiFlag()) typed($t, $me, false);
    $id = append($t, $ti, $m);
    if ($isAI && $sub === '' && empty($m['priv'])) worked($t, $me, 'post', $id);
    out(['id' => $id]);
}

if ($a === 'tasks') out(load("k$t", []));

if ($a === 'task') {
    if (!aiFlag()) out(['error' => 'задачи ведёт ИИ темы, люди их только смотрят'], 403);
    flock($lock, LOCK_EX);
    $ks = load("k$t", []);
    $n = (int)arg('n');
    $what = [];
    if (!$n) {
        $n = count($ks) ? max(array_column($ks, 'n')) + 1 : 1;
        $ks[] = ['n' => $n, 'title' => '', 'state' => 'todo', 'prio' => 0, 'info' => '', 'log' => []];
        $what[] = 'заведена';
    }
    $i = array_search($n, array_column($ks, 'n'));
    if ($i === false) { flock($lock, LOCK_UN); out(['error' => "нет задачи $n"], 404); }
    $k = &$ks[$i];
    $st = ['now' => 'в работе', 'todo' => 'не в работе', 'done' => 'в архив'];
    if (arg('title') !== '') { $k['title'] = mb_substr(trim(arg('title')), 0, 200); if (!$what) $what[] = 'название'; }
    if (arg('state') !== '') {
        if (!isset($st[arg('state')])) { flock($lock, LOCK_UN); out(['error' => 'state — now, todo или done'], 400); }
        if ($k['state'] !== arg('state') || $what) $what[] = $st[arg('state')];
        $k['state'] = arg('state');
    }
    if (arg('prio') !== '') { $k['prio'] = (int)arg('prio'); $what[] = 'приоритет ' . $k['prio']; }
    if (arg('info') !== '') { $k['info'] = mb_substr(trim(arg('info')), 0, 5000); $what[] = 'инфо обновлено'; }
    if (arg('note') !== '') $what[] = mb_substr(trim(arg('note')), 0, 500);
    $e = ['ts' => date('c'), 'by' => label($me, true, $sub), 'what' => implode('; ', $what)];
    if ((int)arg('msg')) $e['msg'] = (int)arg('msg');
    $k['log'][] = $e;
    $k['upd'] = date('c');
    unset($k);
    save("k$t", $ks);
    flock($lock, LOCK_UN);
    out(['n' => $n]);
}

if ($a === 'react') {
    $r = arg('r');
    if (!isset(REACTS[$r])) out(['error' => 'r — одна из: ' . implode(', ', array_keys(REACTS))], 400);
    $m = ['who' => $me, 'ai' => aiFlag(), 'react' => $r, 'on' => (int)arg('on')] + ($sub !== '' ? ['sub' => $sub] : []);
    if (arg('off') === '1') $m['off'] = true;
    foreach (load("t$t", []) as $x) if ($x['id'] === $m['on'] && !empty($x['priv'])) $m['priv'] = true;
    out(['id' => append($t, $ti, $m)]);
}

if ($a === 'edit' || $a === 'del') {
    $on = (int)arg('on');
    $text = trim(emo(arg('text')));
    if ($a === 'edit' && $text === '') out(['error' => 'нужен text'], 400);
    flock($lock, LOCK_EX);
    $msgs = load("t$t", []);
    $i = array_search($on, array_column($msgs, 'id'));
    $x = $i === false ? null : $msgs[$i];
    if (!$x || !empty($x['sys']) || isset($x['react']) || isset($x['edit']) || isset($x['del']) || !empty($x['gone'])) { flock($lock, LOCK_UN); out(['error' => "нет сообщения $on"], 404); }
    if ($x['who'] !== $me || $x['ai'] !== aiFlag() || ($x['sub'] ?? '') !== $sub) { flock($lock, LOCK_UN); out(['error' => 'править и удалять можно только свои сообщения'], 403); }
    if ($a === 'edit') {
        $msgs[$i]['text'] = mb_substr($text, 0, 20000);
        $msgs[$i]['edited'] = date('c');
        $e = ['who' => $me, 'ai' => aiFlag(), 'edit' => $on, 'text' => $msgs[$i]['text']] + ($sub !== '' ? ['sub' => $sub] : []);
    } else {
        foreach ($x['files'] ?? [] as $f) @unlink(D . '/f/' . basename($f['f']));
        $msgs[$i] = ['id' => $on, 'who' => $me, 'ai' => $x['ai'], 'gone' => true, 'ts' => $x['ts']] + ($sub !== '' ? ['sub' => $sub] : []) + (empty($x['priv']) ? [] : ['priv' => true]);
        foreach ($msgs as &$y) if (($y['edit'] ?? 0) === $on) unset($y['text']);
        unset($y);
        $e = ['who' => $me, 'ai' => aiFlag(), 'del' => $on] + ($sub !== '' ? ['sub' => $sub] : []);
    }
    if (!empty($x['priv'])) $e['priv'] = true;
    save("t$t", $msgs);
    flock($lock, LOCK_UN);
    out(['id' => append($t, $ti, $e)]);
}

if ($a === 'add') {
    $n = trim(arg('who'));
    if (!in_array($n, $people)) out(['error' => "нет такого человека: $n, список: a=people"], 400);
    if (in_array($n, $topics[$ti]['members'])) out(['ok' => true]);
    flock($lock, LOCK_EX);
    $topics = load('topics', []);
    $topics[$ti]['members'][] = $n;
    unset($topics[$ti]['gone'][$n]);
    save('topics', $topics);
    flock($lock, LOCK_UN);
    out(['id' => append($t, $ti, ['sys' => true, 'who' => $me, 'ai' => false, 'text' => "$me позвал(а) в тему: $n и " . label($n, true)])]);
}

if ($a === 'kick') {
    if (!$isAdmin && ($topics[$ti]['by'] ?? '') !== $me) out(['error' => 'убирать из темы может её автор или админ'], 403);
    $gone = arg('all') === '1' ? array_values(array_diff($topics[$ti]['members'], [$me])) : array_intersect([trim(arg('who'))], $topics[$ti]['members']);
    if (in_array($me, $gone)) out(['error' => 'себя убрать нельзя'], 400);
    if (!$gone) out(['ok' => true]);
    flock($lock, LOCK_EX);
    $topics = load('topics', []);
    $topics[$ti]['members'] = array_values(array_diff($topics[$ti]['members'], $gone));
    $why = mb_substr(trim(arg('why')), 0, 200);
    foreach ($gone as $n) $topics[$ti]['gone'][$n] = ['by' => $me, 'ts' => date('c')] + ($why !== '' ? ['why' => $why] : []);
    save('topics', $topics);
    flock($lock, LOCK_UN);
    out(['id' => append($t, $ti, ['sys' => true, 'who' => $me, 'ai' => false, 'text' => "$me убрал(а) из темы: " . implode(', ', array_map(function ($n) { return "$n и " . label($n, true); }, $gone)) . ($why !== '' ? ". Причина: $why" : '')])]);
}

if ($a === 'rename') {
    $title = trim(arg('title'));
    if ($title === '') out(['error' => 'нужен title'], 400);
    $title = mb_substr($title, 0, 200);
    $old = $topics[$ti]['title'];
    if ($title === $old) out(['ok' => true]);
    flock($lock, LOCK_EX);
    $topics = load('topics', []);
    $topics[$ti]['title'] = $title;
    save('topics', $topics);
    flock($lock, LOCK_UN);
    out(['id' => append($t, $ti, ['sys' => true, 'who' => $me, 'ai' => false, 'text' => "$me переименовал(а) тему: «{$old}» → «{$title}»"])]);
}

if ($a === 'archive') {
    $on = arg('on', '1') === '1';
    if (!empty($topics[$ti]['archived']) === $on) out(['ok' => true]);
    flock($lock, LOCK_EX);
    $topics = load('topics', []);
    $topics[$ti]['archived'] = $on;
    save('topics', $topics);
    flock($lock, LOCK_UN);
    out(['id' => append($t, $ti, ['sys' => true, 'who' => $me, 'ai' => false, 'text' => $on ? "$me убрал(а) тему в архив" : "$me вернул(а) тему из архива"])]);
}

if ($a === 'file') {
    $f = basename(arg('f'));
    if (!preg_match('/^[0-9a-f]{32}\.(\w+)$/', $f, $mm) || !is_file(D . "/f/$f")) out(['error' => 'нет файла'], 404);
    $ok = false;
    foreach (load("t$t", []) as $m) foreach (isset($m['files']) ? $m['files'] : [] as $x) if ($x['f'] === $f) $ok = true;
    if (!$ok) out(['error' => 'нет файла'], 404);
    header('Content-Type: ' . TYPES[$mm[1]]);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=86400');
    header('Content-Length: ' . filesize(D . "/f/$f"));
    readfile(D . "/f/$f");
    exit;
}

out(['error' => 'неизвестное действие a, есть: me, people, topics, new, read, wait, post, react, add, rename, archive, file, aikey, invite, forget'], 400);
