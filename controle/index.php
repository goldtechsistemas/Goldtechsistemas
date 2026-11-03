<?php
/*
 * Controle do Sintegra mensal — goldtechsistemas.com/controle
 * Um arquivo só: a tela (HTML/JS no fim) e a API (?api=...).
 * Os dados e a senha ficam FORA da pasta pública (controle-dados ao lado da public_html);
 * se a hospedagem não deixar, ficam em controle/dados com acesso bloqueado pelo .htaccess.
 */
declare(strict_types=1);

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

function pasta_dados(): string {
    $fora = dirname(__DIR__, 2) . '/controle-dados';
    if (is_dir($fora) || @mkdir($fora, 0700, true)) {
        if (is_writable($fora)) return $fora;
    }
    $dentro = __DIR__ . '/dados';
    if (!is_dir($dentro)) @mkdir($dentro, 0700, true);
    if (!is_file($dentro . '/.htaccess')) {
        @file_put_contents($dentro . '/.htaccess', "Require all denied\nDeny from all\n");
    }
    return $dentro;
}
$DADOS = pasta_dados();
$ARQ_SENHA = $DADOS . '/senha.json';
$ARQ_CLIENTES = $DADOS . '/clientes.json';
$ARQ_TENTATIVAS = $DADOS . '/tentativas.json';

function ler_json(string $arq, $padrao) {
    if (!is_file($arq)) return $padrao;
    $j = json_decode((string) file_get_contents($arq), true);
    return $j === null ? $padrao : $j;
}
function gravar_json(string $arq, $valor): void {
    $tmp = $arq . '.' . bin2hex(random_bytes(4)) . '.tmp';
    $ok = file_put_contents($tmp, json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) !== false && rename($tmp, $arq);
    if (!$ok) {
        // Nunca finge que salvou: a tela mostra o erro em vez de "salvo".
        @unlink($tmp);
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['erro' => 'Não foi possível gravar no servidor.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

session_name('gtcontrole');
session_set_cookie_params(['lifetime' => 0, 'path' => '/controle/', 'secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
session_start();

if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    $api = (string) $_GET['api'];
    $corpo = json_decode((string) file_get_contents('php://input'), true) ?: [];
    $responder = function (int $codigo, array $dados) { http_response_code($codigo); echo json_encode($dados, JSON_UNESCAPED_UNICODE); exit; };
    $tem_senha = is_file($ARQ_SENHA);
    $logado = !empty($_SESSION['ok']);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') $responder(405, ['erro' => 'Use POST']);
    // Só aceita chamadas da própria tela (cabeçalho que outro site não consegue mandar).
    if (($_SERVER['HTTP_X_CONTROLE'] ?? '') !== '1') $responder(403, ['erro' => 'Origem inválida']);

    if ($api === 'estado') $responder(200, ['temSenha' => $tem_senha, 'logado' => $logado]);
    if ($api === 'diag') {
        // Teste de gravação: cria e apaga um arquivo vazio; não mostra caminho nem dados.
        $t = $DADOS . '/teste-' . bin2hex(random_bytes(3));
        $escreveu = @file_put_contents($t, '') !== false;
        $erro = $escreveu ? '' : (string) (error_get_last()['message'] ?? '');
        @unlink($t);
        $responder(200, ['php' => PHP_VERSION, 'pasta' => basename($DADOS), 'gravavel' => is_writable($DADOS), 'teste' => $escreveu,
            'erro' => preg_replace('#/home/[^\s:]+#', '[caminho]', $erro), 'mb' => function_exists('mb_strlen')]);
    }

    // Limite de tentativas de senha: 8 erros em 15 minutos bloqueiam por 15 minutos.
    $tentativas = array_values(array_filter(ler_json($ARQ_TENTATIVAS, []), function ($t) { return $t > time() - 900; }));
    $bloqueado = count($tentativas) >= 8;

    if ($api === 'criar-senha') {
        if ($tem_senha) $responder(409, ['erro' => 'A senha já foi criada. Entre com ela.']);
        $s = (string) ($corpo['senha'] ?? '');
        if (mb_strlen($s) < 8) $responder(400, ['erro' => 'A senha precisa ter pelo menos 8 caracteres.']);
        gravar_json($ARQ_SENHA, ['hash' => password_hash($s, PASSWORD_DEFAULT), 'criada' => date('c')]);
        session_regenerate_id(true);
        $_SESSION['ok'] = true;
        $responder(200, ['ok' => true]);
    }
    if ($api === 'entrar') {
        if (!$tem_senha) $responder(409, ['erro' => 'Crie a senha primeiro.']);
        if ($bloqueado) $responder(429, ['erro' => 'Muitas tentativas erradas. Espere 15 minutos.']);
        $senha = ler_json($ARQ_SENHA, []);
        if (!password_verify((string) ($corpo['senha'] ?? ''), (string) ($senha['hash'] ?? ''))) {
            $tentativas[] = time();
            gravar_json($ARQ_TENTATIVAS, $tentativas);
            sleep(1);
            $responder(401, ['erro' => 'Senha incorreta.']);
        }
        gravar_json($ARQ_TENTATIVAS, []);
        session_regenerate_id(true);
        $_SESSION['ok'] = true;
        $responder(200, ['ok' => true]);
    }

    if (!$logado) $responder(401, ['erro' => 'Entre de novo.']);

    if ($api === 'sair') { $_SESSION = []; session_destroy(); $responder(200, ['ok' => true]); }
    if ($api === 'trocar-senha') {
        $senha = ler_json($ARQ_SENHA, []);
        if (!password_verify((string) ($corpo['atual'] ?? ''), (string) ($senha['hash'] ?? ''))) $responder(401, ['erro' => 'Senha atual incorreta.']);
        $nova = (string) ($corpo['nova'] ?? '');
        if (mb_strlen($nova) < 8) $responder(400, ['erro' => 'A senha nova precisa ter pelo menos 8 caracteres.']);
        gravar_json($ARQ_SENHA, ['hash' => password_hash($nova, PASSWORD_DEFAULT), 'criada' => date('c')]);
        $responder(200, ['ok' => true]);
    }
    if ($api === 'dados') $responder(200, ler_json($ARQ_CLIENTES, ['rev' => 0, 'clientes' => []]));
    if ($api === 'salvar') {
        $atual = ler_json($ARQ_CLIENTES, ['rev' => 0, 'clientes' => []]);
        // Se a lista mudou em outra aba ou aparelho, não sobrescreve: a tela recarrega.
        if ((int) ($corpo['rev'] ?? -1) !== (int) $atual['rev']) $responder(409, ['erro' => 'A lista foi alterada em outro lugar. Recarregando.']);
        $limpos = [];
        foreach ((array) ($corpo['clientes'] ?? []) as $c) {
            $gerados = [];
            foreach ((array) ($c['gerados'] ?? []) as $mes => $dia) {
                if (preg_match('/^\d{4}-\d{2}$/', (string) $mes) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dia)) $gerados[$mes] = $dia;
            }
            $limpos[] = [
                'id' => preg_replace('/[^a-z0-9]/', '', (string) ($c['id'] ?? '')) ?: bin2hex(random_bytes(6)),
                'nome' => mb_substr(trim((string) ($c['nome'] ?? '')), 0, 120),
                'cnpj' => preg_replace('/\D/', '', (string) ($c['cnpj'] ?? '')),
                'automatico' => !empty($c['automatico']),
                'prazoDia' => max(1, min(28, (int) ($c['prazoDia'] ?? 10))),
                // Envio automático: dia do mês em que o envio acontece e o próximo mês a preencher sozinho.
                'envioDia' => max(0, min(28, (int) ($c['envioDia'] ?? 0))),
                'autoProximo' => preg_match('/^\d{4}-\d{2}$/', (string) ($c['autoProximo'] ?? '')) ? (string) $c['autoProximo'] : '',
                'gerados' => $gerados,
            ];
        }
        $novo = ['rev' => (int) $atual['rev'] + 1, 'clientes' => $limpos, 'alterado' => date('c')];
        gravar_json($ARQ_CLIENTES, $novo);
        $responder(200, ['rev' => $novo['rev']]);
    }
    $responder(404, ['erro' => 'Ação desconhecida']);
}
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Controle Sintegra | Gold Tech Sistemas</title>
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="preload" href="/fonts/onest.woff2" as="font" type="font/woff2" crossorigin>
<style>
@font-face { font-family: "Onest"; font-weight: 100 900; font-display: swap; src: url("/fonts/onest.woff2") format("woff2"); }
:root {
  color-scheme: dark;
  --ink: #0d0c0a; --ink-2: #15130f; --ink-3: #1e1b15; --line: rgba(230, 196, 100, .16); --line-2: rgba(241, 236, 224, .09);
  --text: #f1ece0; --muted: #aaa392; --faint: #857f70; --gold: #d6a82e; --gold-hi: #f0c95a; --on-gold: #17130a;
  --ok: #34a872; --ok-dim: rgba(52, 168, 114, .15); --crit: #e8705a; --crit-dim: rgba(232, 112, 90, .15);
  --warn: #e8b23a; --warn-dim: rgba(232, 178, 58, .14); --auto: #8fa4c8; --auto-dim: rgba(143, 164, 200, .16);
}
* { box-sizing: border-box; }
[hidden] { display: none !important; }
body { margin: 0; background: var(--ink); color: var(--text); font: 15px/1.5 "Onest", "Segoe UI", system-ui, sans-serif; }
button, input, select { font: inherit; color: inherit; }
.wrap { max-width: 1100px; margin: 0 auto; padding: 0 16px; }
.btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: 1px solid var(--line-2); background: var(--ink-3); color: var(--text);
  border-radius: 10px; padding: 10px 14px; font-weight: 600; cursor: pointer; }
.btn:hover { border-color: var(--gold); }
.btn-gold { background: var(--gold); color: var(--on-gold); border-color: var(--gold); }
.btn-gold:hover { background: var(--gold-hi); }
.btn-sm { padding: 6px 10px; font-size: 13px; border-radius: 8px; }
.btn-ghost { background: none; }
input[type=text], input[type=password], input[type=date], input[type=number], input[type=search] {
  width: 100%; background: var(--ink); border: 1px solid var(--line-2); border-radius: 10px; padding: 10px 12px; font-size: 16px; }
input:focus, .btn:focus-visible { outline: 2px solid var(--gold); outline-offset: 1px; }
label { font-size: 13px; color: var(--muted); display: grid; gap: 6px; }

/* entrada */
.gate { min-height: 100vh; display: grid; place-items: center; padding: 24px 16px; }
.gate-card { width: min(400px, 100%); background: var(--ink-2); border: 1px solid var(--line); border-radius: 18px; padding: 28px 24px; display: grid; gap: 16px; }
.gate-card h1 { margin: 0; font-size: 22px; }
.gate-card p { margin: 0; color: var(--muted); font-size: 14px; }
.msg { font-size: 13px; color: var(--crit); min-height: 1em; }
.marca { color: var(--gold); font-weight: 800; letter-spacing: .02em; }

/* painel */
header.bar { position: sticky; top: 0; z-index: 5; background: rgba(13, 12, 10, .96); border-bottom: 1px solid var(--line-2); }
header.bar .wrap { display: flex; align-items: center; gap: 12px; min-height: 60px; flex-wrap: wrap; padding-block: 8px; }
header.bar h1 { margin: 0; font-size: 17px; margin-right: auto; }
.mes { display: flex; align-items: center; gap: 10px; margin: 22px 0 4px; flex-wrap: wrap; }
.mes h2 { margin: 0; font-size: 24px; }
.mes small { color: var(--muted); display: block; font-size: 13px; }
.resumo { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; margin: 18px 0; }
.card { background: var(--ink-2); border: 1px solid var(--line-2); border-radius: 14px; padding: 12px 14px; }
.card b { display: block; font-size: 24px; line-height: 1.2; }
.card span { font-size: 12px; color: var(--muted); }
.card.crit b { color: var(--crit); } .card.ok b { color: var(--ok); } .card.warn b { color: var(--warn); } .card.auto b { color: var(--auto); }
.ferramentas { display: flex; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
.ferramentas input { flex: 1; min-width: 200px; }
table { width: 100%; border-collapse: collapse; background: var(--ink-2); border: 1px solid var(--line-2); border-radius: 14px; overflow: hidden; }
th { text-align: left; font-size: 12px; font-weight: 600; color: var(--faint); text-transform: uppercase; letter-spacing: .05em; padding: 12px; border-bottom: 1px solid var(--line-2); }
td { padding: 12px; border-bottom: 1px solid var(--line-2); vertical-align: middle; }
tr:last-child td { border-bottom: 0; }
.nome { font-weight: 700; }
.cnpj { color: var(--muted); font-size: 13px; font-variant-numeric: tabular-nums; }
.tag { display: inline-block; font-size: 12px; font-weight: 700; padding: 4px 9px; border-radius: 999px; white-space: nowrap; }
.tag.ok { background: var(--ok-dim); color: var(--ok); } .tag.crit { background: var(--crit-dim); color: var(--crit); }
.tag.warn { background: var(--warn-dim); color: var(--warn); } .tag.auto { background: var(--auto-dim); color: var(--auto); }
.prazo small { display: block; color: var(--muted); font-size: 12px; }
.gerado { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.gerado input { width: auto; padding: 6px 8px; font-size: 14px; }
.switch { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; color: var(--text); font-size: 13px; }
.switch input { width: 18px; height: 18px; accent-color: var(--gold); }
.acoes { display: flex; gap: 6px; justify-content: flex-end; }
.vazio { text-align: center; color: var(--muted); padding: 40px 16px; }
.aviso { position: fixed; left: 50%; bottom: 20px; transform: translateX(-50%); background: var(--ink-3); border: 1px solid var(--line); border-radius: 12px; padding: 10px 16px; font-size: 14px; z-index: 20; }

dialog { background: var(--ink-2); color: var(--text); border: 1px solid var(--line); border-radius: 18px; padding: 0; width: min(440px, calc(100% - 32px)); }
dialog::backdrop { background: rgba(0, 0, 0, .7); }
dialog form { display: grid; gap: 14px; padding: 22px; }
dialog h3 { margin: 0; font-size: 18px; }
.linha { display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }

@media (max-width: 760px) {
  .resumo { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  table, thead, tbody, tr, td { display: block; }
  thead { display: none; }
  table { background: none; border: 0; }
  tr { background: var(--ink-2); border: 1px solid var(--line-2); border-radius: 14px; margin-bottom: 10px; padding: 6px 4px; }
  td { border: 0; padding: 6px 12px; }
  td[data-rot]::before { content: attr(data-rot); display: block; font-size: 11px; color: var(--faint); text-transform: uppercase; letter-spacing: .05em; }
  .acoes { justify-content: flex-start; }
}
</style>
</head>
<body>

<section class="gate" id="tela-entrada" hidden>
  <form class="gate-card" id="form-entrada" autocomplete="off">
    <div class="marca">GOLD TECH SISTEMAS</div>
    <h1 id="entrada-titulo">Controle do Sintegra</h1>
    <p id="entrada-texto">Digite a senha para entrar.</p>
    <label>Senha<input type="password" id="senha1" autocomplete="current-password" required></label>
    <label id="campo-senha2" hidden>Repita a senha<input type="password" id="senha2" autocomplete="new-password"></label>
    <div class="msg" id="entrada-msg" role="alert"></div>
    <button class="btn btn-gold" type="submit" id="entrada-botao">Entrar</button>
  </form>
</section>

<section id="tela-painel" hidden>
  <header class="bar">
    <div class="wrap">
      <h1><span class="marca">GOLD TECH</span> · Controle do Sintegra</h1>
      <button class="btn btn-sm btn-ghost" type="button" id="b-csv">Exportar planilha</button>
      <button class="btn btn-sm btn-ghost" type="button" id="b-senha">Trocar senha</button>
      <button class="btn btn-sm btn-ghost" type="button" id="b-sair">Sair</button>
    </div>
  </header>
  <main class="wrap">
    <div class="mes">
      <button class="btn btn-sm" type="button" id="mes-ant" aria-label="Mês anterior">◀</button>
      <div><h2 id="mes-titulo"></h2><small id="mes-sub"></small></div>
      <button class="btn btn-sm" type="button" id="mes-prox" aria-label="Próximo mês">▶</button>
      <button class="btn btn-sm btn-ghost" type="button" id="mes-hoje">Mês atual</button>
    </div>
    <div class="resumo">
      <div class="card"><b id="n-total">0</b><span>clientes</span></div>
      <div class="card ok"><b id="n-ok">0</b><span>gerados</span></div>
      <div class="card warn"><b id="n-pend">0</b><span>pendentes</span></div>
      <div class="card crit"><b id="n-atraso">0</b><span>atrasados</span></div>
      <div class="card auto"><b id="n-auto">0</b><span>envio automático</span></div>
    </div>
    <div class="ferramentas">
      <input type="search" id="busca" placeholder="Buscar por empresa ou CNPJ">
      <button class="btn btn-gold" type="button" id="b-novo">+ Novo cliente</button>
    </div>
    <table>
      <thead><tr><th>Empresa / CNPJ</th><th>Prazo</th><th>Situação</th><th>Gerado em</th><th>Envio automático</th><th></th></tr></thead>
      <tbody id="linhas"></tbody>
    </table>
    <p class="vazio" id="vazio" hidden>Nenhum cliente ainda. Clique em “Novo cliente”.</p>
    <p style="height:40px"></p>
  </main>
</section>

<dialog id="d-cliente">
  <form id="f-cliente" method="dialog">
    <h3 id="d-cliente-titulo">Novo cliente</h3>
    <label>Nome da empresa<input type="text" id="c-nome" maxlength="120" required></label>
    <label>CNPJ<input type="text" id="c-cnpj" inputmode="numeric" maxlength="18" placeholder="00.000.000/0000-00" required></label>
    <label>Prazo para gerar (dia de cada mês)<input type="number" id="c-prazo" min="1" max="28" value="10" required></label>
    <label class="switch"><input type="checkbox" id="c-auto"> O envio do Sintegra deste cliente é automático</label>
    <label id="c-envio-campo" hidden>Dia do envio automático (todo mês)<input type="number" id="c-envio" min="1" max="28"></label>
    <div class="msg" id="c-msg" role="alert"></div>
    <div class="linha">
      <button class="btn" type="button" id="c-cancelar">Cancelar</button>
      <button class="btn btn-gold" type="submit">Salvar</button>
    </div>
  </form>
</dialog>

<dialog id="d-senha">
  <form id="f-senha" method="dialog">
    <h3>Trocar senha</h3>
    <label>Senha atual<input type="password" id="s-atual" autocomplete="current-password" required></label>
    <label>Senha nova (pelo menos 8 caracteres)<input type="password" id="s-nova" autocomplete="new-password" required></label>
    <label>Repita a senha nova<input type="password" id="s-nova2" autocomplete="new-password" required></label>
    <div class="msg" id="s-msg" role="alert"></div>
    <div class="linha">
      <button class="btn" type="button" id="s-cancelar">Cancelar</button>
      <button class="btn btn-gold" type="submit">Trocar</button>
    </div>
  </form>
</dialog>

<div class="aviso" id="aviso" hidden></div>

<script>
(function () {
  var $ = function (id) { return document.getElementById(id); };
  var estado = { rev: 0, clientes: [] }, mesAtual = mesDe(new Date()), editando = null, temSenha = true;

  function api(acao, corpo) {
    return fetch('?api=' + acao, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Controle': '1' },
      body: JSON.stringify(corpo || {})
    }).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) {
        if (r.status === 401 && acao !== 'entrar' && acao !== 'trocar-senha') { mostrarEntrada(); }
        if (!r.ok) { var e = new Error(j.erro || 'Erro ' + r.status); e.status = r.status; throw e; }
        return j;
      });
    });
  }
  function aviso(t) { var a = $('aviso'); a.textContent = t; a.hidden = false; clearTimeout(aviso.t); aviso.t = setTimeout(function () { a.hidden = true; }, 2600); }

  // ---------- datas ----------
  function mesDe(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0'); }
  function somaMes(m, n) { var p = m.split('-'), d = new Date(+p[0], +p[1] - 1 + n, 1); return mesDe(d); }
  function nomeMes(m) { var p = m.split('-'); return new Date(+p[0], +p[1] - 1, 1).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' }); }
  function hojeISO() { var d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
  function br(iso) { var p = iso.split('-'); return p[2] + '/' + p[1] + '/' + p[0]; }
  function prazoISO(m, dia) { return m + '-' + String(dia).padStart(2, '0'); }
  function diasEntre(a, b) { return Math.round((new Date(b + 'T12:00') - new Date(a + 'T12:00')) / 864e5); }

  // ---------- CNPJ ----------
  function soNum(s) { return (s || '').replace(/\D/g, ''); }
  function fmtCnpj(s) { var d = soNum(s).slice(0, 14); return d.replace(/^(\d{2})(\d)/, '$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d)/, '.$1/$2').replace(/(\d{4})(\d)/, '$1-$2'); }
  function cnpjValido(s) {
    var c = soNum(s); if (c.length !== 14 || /^(\d)\1+$/.test(c)) return false;
    function dv(n) { var soma = 0, pos = n - 7; for (var i = n; i >= 1; i--) { soma += c[n - i] * pos--; if (pos < 2) pos = 9; } var r = soma % 11; return r < 2 ? 0 : 11 - r; }
    return dv(12) === +c[12] && dv(13) === +c[13];
  }

  // ---------- situação de cada cliente no mês ----------
  function situacao(c, m) {
    var gerado = (c.gerados || {})[m], prazo = prazoISO(m, c.prazoDia || 10), hoje = hojeISO();
    if (gerado) return { cls: 'ok', txt: 'Gerado', ordem: 3, prazo: prazo, gerado: gerado };
    if (c.automatico) return { cls: 'auto', txt: 'Automático', ordem: 2, prazo: prazo, envio: c.envioDia ? prazoISO(m, c.envioDia) : '' };
    if (hoje > prazo) return { cls: 'crit', txt: 'Atrasado', ordem: 0, prazo: prazo };
    return { cls: 'warn', txt: 'Pendente', ordem: 1, prazo: prazo };
  }
  function textoPrazo(s) {
    if (s.gerado) return s.gerado <= s.prazo ? 'no prazo' : 'gerado ' + diasEntre(s.prazo, s.gerado) + ' dia(s) depois do prazo';
    if (s.cls === 'auto') return s.envio ? 'envio automático em ' + br(s.envio) : 'informe o dia do envio automático';
    var d = diasEntre(hojeISO(), s.prazo);
    if (d > 1) return 'faltam ' + d + ' dias'; if (d === 1) return 'vence amanhã'; if (d === 0) return 'vence hoje';
    return 'venceu há ' + (-d) + ' dia(s)';
  }

  // ---------- tela ----------
  function desenhar() {
    var m = mesAtual, competencia = nomeMes(somaMes(m, -1));
    var titulo = nomeMes(m);
    $('mes-titulo').textContent = titulo.charAt(0).toUpperCase() + titulo.slice(1);
    $('mes-sub').textContent = 'Entrega até o dia 10 · competência ' + competencia;
    var busca = soNum($('busca').value) || $('busca').value.trim().toLowerCase();
    var lista = estado.clientes.map(function (c) { return { c: c, s: situacao(c, m) }; });
    var n = { ok: 0, warn: 0, crit: 0, auto: 0 };
    lista.forEach(function (x) { n[x.s.cls]++; });
    $('n-total').textContent = lista.length; $('n-ok').textContent = n.ok; $('n-pend').textContent = n.warn;
    $('n-atraso').textContent = n.crit; $('n-auto').textContent = estado.clientes.filter(function (c) { return c.automatico; }).length;
    if (busca) lista = lista.filter(function (x) { return x.c.nome.toLowerCase().indexOf(busca) >= 0 || x.c.cnpj.indexOf(busca) >= 0; });
    lista.sort(function (a, b) { return a.s.ordem - b.s.ordem || a.c.nome.localeCompare(b.c.nome, 'pt-BR'); });
    var tb = $('linhas'); tb.textContent = '';
    lista.forEach(function (x) {
      var c = x.c, s = x.s, tr = document.createElement('tr');
      tr.innerHTML =
        '<td data-rot="Empresa / CNPJ"><div class="nome"></div><div class="cnpj"></div></td>' +
        '<td data-rot="Prazo" class="prazo"><span></span><small></small></td>' +
        '<td data-rot="Situação"><span class="tag ' + s.cls + '"></span></td>' +
        '<td data-rot="Gerado em"><div class="gerado"><input type="date" aria-label="Data em que o Sintegra foi gerado"><button class="btn btn-sm" type="button" data-a="hoje">Gerado hoje</button></div></td>' +
        '<td data-rot="Envio automático"><label class="switch"><input type="checkbox" data-a="auto"> Automático</label></td>' +
        '<td><div class="acoes"><button class="btn btn-sm" type="button" data-a="editar">Editar</button><button class="btn btn-sm" type="button" data-a="excluir">Excluir</button></div></td>';
      tr.querySelector('.nome').textContent = c.nome;
      tr.querySelector('.cnpj').textContent = fmtCnpj(c.cnpj);
      tr.querySelector('.prazo span').textContent = br(s.prazo);
      tr.querySelector('.prazo small').textContent = textoPrazo(s);
      tr.querySelector('.tag').textContent = s.txt;
      var data = tr.querySelector('input[type=date]'); data.value = s.gerado || '';
      var hoje = tr.querySelector('[data-a=hoje]'); hoje.hidden = !!s.gerado;
      tr.querySelector('[data-a=auto]').checked = !!c.automatico;
      data.addEventListener('change', function () { marcar(c, data.value); });
      hoje.addEventListener('click', function () { marcar(c, hojeISO()); });
      tr.querySelector('[data-a=auto]').addEventListener('change', function (e) {
        // Ligar pede o dia do envio; o cliente só fica automático depois de salvar.
        if (e.target.checked) { e.target.checked = false; abrirCliente(c, true); return; }
        c.automatico = false; salvar('Envio automático desligado');
      });
      tr.querySelector('[data-a=editar]').addEventListener('click', function () { abrirCliente(c); });
      tr.querySelector('[data-a=excluir]').addEventListener('click', function () {
        if (!confirm('Excluir ' + c.nome + ' da lista? O histórico dele também some.')) return;
        estado.clientes = estado.clientes.filter(function (o) { return o !== c; }); salvar('Cliente excluído');
      });
      tb.appendChild(tr);
    });
    $('vazio').hidden = estado.clientes.length > 0;
  }
  function marcar(c, iso) {
    c.gerados = c.gerados || {};
    if (iso) c.gerados[mesAtual] = iso; else delete c.gerados[mesAtual];
    salvar(iso ? 'Gerado em ' + br(iso) : 'Data apagada');
  }
  // Clientes automáticos: quando chega o dia do envio, a data "gerado em" é preenchida sozinha.
  // autoProximo guarda o próximo mês a preencher, então uma data apagada à mão não volta.
  function preencherAutomaticos() {
    var hoje = hojeISO(), mudou = false;
    estado.clientes.forEach(function (c) {
      if (!c.automatico || !c.envioDia) return;
      if (!c.autoProximo) { c.autoProximo = mesDe(new Date()); mudou = true; }
      while (c.autoProximo <= mesDe(new Date()) && prazoISO(c.autoProximo, c.envioDia) <= hoje) {
        c.gerados = c.gerados || {};
        if (!c.gerados[c.autoProximo]) c.gerados[c.autoProximo] = prazoISO(c.autoProximo, c.envioDia);
        c.autoProximo = somaMes(c.autoProximo, 1); mudou = true;
      }
    });
    return mudou;
  }
  function salvar(msg) {
    preencherAutomaticos();
    desenhar();
    return api('salvar', { rev: estado.rev, clientes: estado.clientes })
      .then(function (r) { estado.rev = r.rev; if (msg) aviso(msg + ' · salvo'); })
      .catch(function (e) { aviso(e.message); if (e.status === 409) carregar(); });
  }
  function carregar() {
    return api('dados').then(function (d) {
      estado = { rev: d.rev || 0, clientes: d.clientes || [] };
      if (preencherAutomaticos()) salvar('Datas do envio automático atualizadas'); else desenhar();
    });
  }

  // ---------- cliente (novo / editar) ----------
  function abrirCliente(c, ligarAuto) {
    editando = c || null;
    $('d-cliente-titulo').textContent = c ? 'Editar cliente' : 'Novo cliente';
    $('c-nome').value = c ? c.nome : ''; $('c-cnpj').value = c ? fmtCnpj(c.cnpj) : '';
    $('c-prazo').value = c ? c.prazoDia : 10; $('c-auto').checked = ligarAuto || (c ? !!c.automatico : false); $('c-msg').textContent = '';
    $('c-envio').value = c && c.envioDia ? c.envioDia : '';
    mostrarEnvio();
    $('d-cliente').showModal(); (ligarAuto ? $('c-envio') : $('c-nome')).focus();
  }
  function mostrarEnvio() { var on = $('c-auto').checked; $('c-envio-campo').hidden = !on; $('c-envio').required = on; }
  $('c-auto').addEventListener('change', function () { mostrarEnvio(); if ($('c-auto').checked) $('c-envio').focus(); });
  $('c-cnpj').addEventListener('input', function (e) { e.target.value = fmtCnpj(e.target.value); });
  $('c-cancelar').addEventListener('click', function () { $('d-cliente').close(); });
  $('f-cliente').addEventListener('submit', function (e) {
    e.preventDefault();
    var nome = $('c-nome').value.trim(), cnpj = soNum($('c-cnpj').value), prazo = Math.max(1, Math.min(28, +$('c-prazo').value || 10));
    if (!nome) { $('c-msg').textContent = 'Informe o nome da empresa.'; return; }
    if (!cnpjValido(cnpj)) { $('c-msg').textContent = 'CNPJ inválido. Confira os números.'; return; }
    var repetido = estado.clientes.some(function (o) { return o.cnpj === cnpj && o !== editando; });
    if (repetido) { $('c-msg').textContent = 'Já existe um cliente com esse CNPJ.'; return; }
    var auto = $('c-auto').checked, envio = +$('c-envio').value;
    if (auto && !(envio >= 1 && envio <= 28)) { $('c-msg').textContent = 'Informe o dia do envio automático (1 a 28).'; return; }
    var c = editando || { id: Math.random().toString(36).slice(2, 12), gerados: {} };
    // Ao ligar o automático, começa a preencher a partir deste mês.
    if (auto && !c.automatico) c.autoProximo = mesDe(new Date());
    c.nome = nome; c.cnpj = cnpj; c.prazoDia = prazo; c.automatico = auto;
    if (auto) c.envioDia = envio;
    if (!editando) estado.clientes.push(c);
    $('d-cliente').close();
    salvar(editando ? 'Cliente atualizado' : 'Cliente adicionado');
  });

  // ---------- topo ----------
  $('b-novo').addEventListener('click', function () { abrirCliente(null); });
  $('busca').addEventListener('input', desenhar);
  $('mes-ant').addEventListener('click', function () { mesAtual = somaMes(mesAtual, -1); desenhar(); });
  $('mes-prox').addEventListener('click', function () { mesAtual = somaMes(mesAtual, 1); desenhar(); });
  $('mes-hoje').addEventListener('click', function () { mesAtual = mesDe(new Date()); desenhar(); });
  $('b-sair').addEventListener('click', function () { api('sair').then(mostrarEntrada); });
  $('b-csv').addEventListener('click', function () {
    var linhas = [['Empresa', 'CNPJ', 'Prazo', 'Situação', 'Gerado em', 'Envio automático', 'Dia do envio']];
    estado.clientes.forEach(function (c) {
      var s = situacao(c, mesAtual);
      linhas.push([c.nome, fmtCnpj(c.cnpj), br(s.prazo), s.txt, s.gerado ? br(s.gerado) : '', c.automatico ? 'Sim' : 'Não', c.automatico && c.envioDia ? c.envioDia : '']);
    });
    var csv = '﻿' + linhas.map(function (l) { return l.map(function (v) { return '"' + String(v).replace(/"/g, '""') + '"'; }).join(';'); }).join('\r\n');
    var a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    a.download = 'sintegra-' + mesAtual + '.csv'; a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
  });
  $('b-senha').addEventListener('click', function () { $('f-senha').reset(); $('s-msg').textContent = ''; $('d-senha').showModal(); });
  $('s-cancelar').addEventListener('click', function () { $('d-senha').close(); });
  $('f-senha').addEventListener('submit', function (e) {
    e.preventDefault();
    if ($('s-nova').value !== $('s-nova2').value) { $('s-msg').textContent = 'As senhas novas não são iguais.'; return; }
    api('trocar-senha', { atual: $('s-atual').value, nova: $('s-nova').value })
      .then(function () { $('d-senha').close(); aviso('Senha trocada'); })
      .catch(function (err) { $('s-msg').textContent = err.message; });
  });

  // ---------- entrada (primeiro acesso cria a senha) ----------
  function mostrarEntrada() {
    $('tela-painel').hidden = true; $('tela-entrada').hidden = false;
    $('entrada-titulo').textContent = temSenha ? 'Controle do Sintegra' : 'Primeiro acesso';
    $('entrada-texto').textContent = temSenha ? 'Digite a senha para entrar.' : 'Crie a senha que vai proteger este controle (pelo menos 8 caracteres).';
    $('campo-senha2').hidden = temSenha; $('senha2').required = !temSenha;
    $('entrada-botao').textContent = temSenha ? 'Entrar' : 'Criar senha e entrar';
    $('senha1').autocomplete = temSenha ? 'current-password' : 'new-password';
    $('form-entrada').reset(); $('entrada-msg').textContent = ''; $('senha1').focus();
  }
  function mostrarPainel() { $('tela-entrada').hidden = true; $('tela-painel').hidden = false; carregar(); }
  $('form-entrada').addEventListener('submit', function (e) {
    e.preventDefault();
    var s1 = $('senha1').value, msg = $('entrada-msg');
    if (!temSenha) {
      if (s1.length < 8) { msg.textContent = 'A senha precisa ter pelo menos 8 caracteres.'; return; }
      if (s1 !== $('senha2').value) { msg.textContent = 'As duas senhas não são iguais.'; return; }
    }
    $('entrada-botao').disabled = true;
    api(temSenha ? 'entrar' : 'criar-senha', { senha: s1 })
      .then(function () { temSenha = true; mostrarPainel(); })
      .catch(function (err) { msg.textContent = err.message; if (err.status === 409) { temSenha = true; mostrarEntrada(); msg.textContent = err.message; } })
      .then(function () { $('entrada-botao').disabled = false; });
  });

  api('estado').then(function (e) { temSenha = e.temSenha; if (e.logado) mostrarPainel(); else mostrarEntrada(); })
    .catch(function () { document.body.textContent = 'Não foi possível abrir o controle. Tente recarregar a página.'; });
})();
</script>
</body>
</html>
