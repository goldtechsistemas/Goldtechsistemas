<?php
/*
 * Controle do Sintegra mensal — goldtechsistemas.com/controle
 * Um arquivo só: a tela (HTML/JS no fim) e a API (?api=...).
 * Os dados e a senha ficam FORA da pasta pública (controle-dados ao lado da public_html);
 * se a hospedagem não deixar, ficam em controle/dados com acesso bloqueado pelo .htaccess.
 */
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

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
$ARQ_CHAVE = $DADOS . '/chave-api.json';

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

// Uma gravação de cada vez na lista de clientes (tela e integração podem gravar juntas).
function travar(string $pasta) {
    $f = @fopen($pasta . '/trava.lock', 'c');
    if ($f) flock($f, LOCK_EX);
    return $f; // solta sozinho quando o PHP termina
}
function cnpj_valido(string $c): bool {
    if (strlen($c) !== 14 || preg_match('/^(\d)\1+$/', $c)) return false;
    foreach ([12, 13] as $n) {
        $soma = 0; $pos = $n - 7;
        for ($i = $n; $i >= 1; $i--) { $soma += (int) $c[$n - $i] * $pos--; if ($pos < 2) $pos = 9; }
        $r = $soma % 11;
        if ((int) $c[$n] !== ($r < 2 ? 0 : 11 - $r)) return false;
    }
    return true;
}
function data_valida($d): bool {
    return is_string($d) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}
function mes_valido($m): bool {
    return is_string($m) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) === 1;
}
// Aplica um registro vindo do outro sistema na lista. Confere tudo antes de mudar qualquer coisa.
function aplicar_externo(array &$clientes, array $it): array {
    $cnpj = preg_replace('/\D/', '', (string) ($it['cnpj'] ?? ''));
    if (!cnpj_valido($cnpj)) return ['ok' => false, 'cnpj' => $cnpj, 'erro' => 'CNPJ inválido.'];
    $idx = null;
    foreach ($clientes as $i => $c) { if ($c['cnpj'] === $cnpj) { $idx = $i; break; } }
    $nome = isset($it['nome']) ? mb_substr(trim((string) $it['nome']), 0, 120) : null;
    if ($idx === null && !$nome) return ['ok' => false, 'cnpj' => $cnpj, 'erro' => 'Cliente novo: informe o nome.'];

    $gerado = $it['gerado_em'] ?? null; $mes = null;
    if ($gerado !== null) {
        if (!data_valida($gerado)) return ['ok' => false, 'cnpj' => $cnpj, 'erro' => 'gerado_em deve ser AAAA-MM-DD.'];
        if (isset($it['mes'])) {
            if (!mes_valido($it['mes'])) return ['ok' => false, 'cnpj' => $cnpj, 'erro' => 'mes deve ser AAAA-MM.'];
            $mes = $it['mes'];
        } elseif (isset($it['competencia'])) {
            if (!mes_valido($it['competencia'])) return ['ok' => false, 'cnpj' => $cnpj, 'erro' => 'competencia deve ser AAAA-MM.'];
            // A entrega é no mês seguinte ao da competência.
            $mes = date('Y-m', strtotime($it['competencia'] . '-01 +1 month'));
        } else {
            $mes = substr($gerado, 0, 7);
        }
    }
    if (isset($it['prazo_dia']) && ((int) $it['prazo_dia'] < 1 || (int) $it['prazo_dia'] > 28)) return ['ok' => false, 'cnpj' => $cnpj, 'erro' => 'prazo_dia deve ser de 1 a 28.'];
    if (isset($it['dia_envio']) && ((int) $it['dia_envio'] < 1 || (int) $it['dia_envio'] > 28)) return ['ok' => false, 'cnpj' => $cnpj, 'erro' => 'dia_envio deve ser de 1 a 28.'];

    $novo = $idx === null;
    $c = $novo
        ? ['id' => bin2hex(random_bytes(6)), 'nome' => $nome, 'cnpj' => $cnpj, 'origem' => '', 'enviarArquivos' => false, 'masterRemote' => false, 'automatico' => false, 'prazoDia' => 10, 'envioDia' => 0, 'autoProximo' => '', 'gerados' => []]
        : $clientes[$idx];
    if (isset($it['automatico']) && $it['automatico'] && !($it['dia_envio'] ?? $c['envioDia'] ?? 0)) {
        return ['ok' => false, 'cnpj' => $cnpj, 'erro' => 'Para envio automático, informe dia_envio.'];
    }
    if ($nome) $c['nome'] = $nome;
    if (isset($it['chamamos'])) $c['origem'] = mb_substr(trim((string) $it['chamamos']), 0, 80);
    if (isset($it['prazo_dia'])) $c['prazoDia'] = (int) $it['prazo_dia'];
    if (isset($it['dia_envio'])) $c['envioDia'] = (int) $it['dia_envio'];
    if (isset($it['enviar_arquivos'])) $c['enviarArquivos'] = (bool) $it['enviar_arquivos'];
    if (isset($it['master_remote'])) $c['masterRemote'] = (bool) $it['master_remote'];
    if (isset($it['automatico'])) {
        $auto = (bool) $it['automatico'];
        if ($auto && empty($c['automatico'])) $c['autoProximo'] = date('Y-m');
        $c['automatico'] = $auto;
    }
    if ($gerado !== null) { $c['gerados'] = (array) ($c['gerados'] ?? []); $c['gerados'][$mes] = $gerado; }
    if ($novo) $clientes[] = $c; else $clientes[$idx] = $c;
    $r = ['ok' => true, 'cnpj' => $cnpj, 'acao' => $novo ? 'criado' : 'atualizado'];
    if ($mes) $r['mes'] = $mes;
    return $r;
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

    // Integração: outro sistema grava aqui usando a chave gerada na tela (botão "Integração").
    if ($api === 'externo') {
        // A chave é longa e aleatória (impossível de adivinhar), então não há bloqueio por tentativas:
        // assim ninguém consegue travar a integração mandando chaves erradas.
        $chave = ler_json($ARQ_CHAVE, []);
        $enviada = trim((string) ($_SERVER['HTTP_X_CHAVE'] ?? ''));
        $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if ($enviada === '' && stripos($auth, 'Bearer ') === 0) $enviada = trim(substr($auth, 7));
        if (empty($chave['hash']) || $enviada === '' || !hash_equals((string) $chave['hash'], hash('sha256', $enviada))) {
            usleep(300000);
            $responder(401, ['erro' => 'Chave de integração inválida.']);
        }
        if (!is_array($corpo) || !$corpo) $responder(400, ['erro' => 'Envie um JSON válido (em UTF-8) com os dados do cliente.']);
        $lote = array_is_list($corpo) ? $corpo : (isset($corpo['clientes']) ? (array) $corpo['clientes'] : null);
        $itens = $lote ?? [$corpo];
        if (count($itens) > 500) $responder(400, ['erro' => 'Envie no máximo 500 clientes por vez.']);

        $trava = travar($DADOS);
        $atual = ler_json($ARQ_CLIENTES, ['rev' => 0, 'clientes' => []]);
        $resultados = []; $mudou = false;
        foreach ($itens as $it) {
            $r = is_array($it) ? aplicar_externo($atual['clientes'], $it) : ['ok' => false, 'erro' => 'Registro inválido.'];
            $resultados[] = $r;
            if ($r['ok']) $mudou = true;
        }
        if ($mudou) {
            foreach ($atual['clientes'] as &$c) $c['gerados'] = (object) (array) ($c['gerados'] ?? []);
            unset($c);
            $atual['rev'] = (int) $atual['rev'] + 1; $atual['alterado'] = date('c');
            gravar_json($ARQ_CLIENTES, $atual);
        }
        $chave['ultimoUso'] = date('c');
        gravar_json($ARQ_CHAVE, $chave);
        if ($lote === null) $responder($resultados[0]['ok'] ? 200 : 400, $resultados[0]);
        $ok = count(array_filter($resultados, function ($r) { return $r['ok']; }));
        $responder(200, ['gravados' => $ok, 'com_erro' => count($resultados) - $ok, 'resultados' => $resultados]);
    }
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
    if ($api === 'chave-estado') {
        $chave = ler_json($ARQ_CHAVE, []);
        $responder(200, ['existe' => !empty($chave['hash']), 'criada' => $chave['criada'] ?? null, 'ultimoUso' => $chave['ultimoUso'] ?? null]);
    }
    if ($api === 'chave-gerar') {
        // A chave só aparece agora; no servidor fica só o resumo (hash) dela.
        $nova = 'gts_' . bin2hex(random_bytes(24));
        gravar_json($ARQ_CHAVE, ['hash' => hash('sha256', $nova), 'criada' => date('c'), 'ultimoUso' => null]);
        $responder(200, ['chave' => $nova]);
    }
    if ($api === 'chave-apagar') {
        if (is_file($ARQ_CHAVE) && !@unlink($ARQ_CHAVE)) $responder(500, ['erro' => 'Não foi possível desligar a integração.']);
        $responder(200, ['ok' => true]);
    }
    if ($api === 'dados') $responder(200, ler_json($ARQ_CLIENTES, ['rev' => 0, 'clientes' => []]));
    if ($api === 'salvar') {
        $trava = travar($DADOS);
        $atual = ler_json($ARQ_CLIENTES, ['rev' => 0, 'clientes' => []]);
        // Se a lista mudou em outra aba ou aparelho, não sobrescreve: a tela recarrega.
        if ((int) ($corpo['rev'] ?? -1) !== (int) $atual['rev']) $responder(409, ['erro' => 'A lista foi alterada em outro lugar. Recarregando.']);
        $limpos = []; $vistos = [];
        foreach ((array) ($corpo['clientes'] ?? []) as $c) {
            $cnpj = preg_replace('/\D/', '', (string) ($c['cnpj'] ?? ''));
            // O mesmo CNPJ nunca entra duas vezes, mesmo que a tela deixe passar.
            if (isset($vistos[$cnpj])) $responder(400, ['erro' => 'Esse CNPJ já está cadastrado.']);
            $vistos[$cnpj] = true;
            $gerados = [];
            foreach ((array) ($c['gerados'] ?? []) as $mes => $dia) {
                if (preg_match('/^\d{4}-\d{2}$/', (string) $mes) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dia)) $gerados[$mes] = $dia;
            }
            $limpos[] = [
                'id' => preg_replace('/[^a-z0-9]/', '', (string) ($c['id'] ?? '')) ?: bin2hex(random_bytes(6)),
                'nome' => mb_substr(trim((string) ($c['nome'] ?? '')), 0, 120),
                'cnpj' => $cnpj,
                'origem' => mb_substr(trim((string) ($c['origem'] ?? '')), 0, 80),
                'automatico' => !empty($c['automatico']),
                // Programas instalados no cliente.
                'enviarArquivos' => !empty($c['enviarArquivos']),
                'masterRemote' => !empty($c['masterRemote']),
                'prazoDia' => max(1, min(28, (int) ($c['prazoDia'] ?? 10))),
                // Envio automático: dia do mês em que o envio acontece e o próximo mês a preencher sozinho.
                'envioDia' => max(0, min(28, (int) ($c['envioDia'] ?? 0))),
                'autoProximo' => preg_match('/^\d{4}-\d{2}$/', (string) ($c['autoProximo'] ?? '')) ? (string) $c['autoProximo'] : '',
                'gerados' => (object) $gerados, // sempre objeto {"AAAA-MM": data}, mesmo vazio
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
.wrap { max-width: 1320px; margin: 0 auto; padding: 0 16px; }
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
.logo { display: block; height: auto; }
.gate-card .logo { width: 192px; }
header.bar .logo { width: 128px; }
header.bar h1 { display: flex; align-items: center; gap: 12px; }
header.bar h1 span { color: var(--muted); font-weight: 600; font-size: 15px; }

/* painel */
header.bar { position: sticky; top: 0; z-index: 5; background: rgba(13, 12, 10, .96); border-bottom: 1px solid var(--line-2); }
header.bar .wrap { display: flex; align-items: center; gap: 12px; min-height: 60px; flex-wrap: wrap; padding-block: 8px; }
header.bar h1 { margin: 0; font-size: 17px; margin-right: auto; }
.mes { display: flex; align-items: center; gap: 10px; margin: 22px 0 4px; flex-wrap: wrap; }
.mes h2 { margin: 0; font-size: 24px; }
.mes small { color: var(--muted); display: block; font-size: 13px; }
.resumo { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 10px; margin: 18px 0; }
.card { text-align: left; cursor: pointer; font: inherit; color: inherit; }
.card:hover { border-color: var(--line); }
.card.ativo { border-color: var(--gold); box-shadow: inset 0 0 0 1px var(--gold); }
.card { background: var(--ink-2); border: 1px solid var(--line-2); border-radius: 14px; padding: 12px 14px; }
.card b { display: block; font-size: 24px; line-height: 1.2; }
.card span { font-size: 12px; color: var(--muted); }
.card.crit b { color: var(--crit); } .card.ok b { color: var(--ok); } .card.warn b { color: var(--warn); } .card.auto b { color: var(--auto); }
.ferramentas { display: flex; gap: 10px; margin-bottom: 12px; flex-wrap: wrap; }
.ferramentas input { flex: 1; min-width: 200px; }
.filtros { display: flex; gap: 10px; flex-wrap: wrap; align-items: flex-end; margin-bottom: 12px; }
.filtros label { min-width: 170px; }
.filtros select { background: var(--ink); border: 1px solid var(--line-2); border-radius: 10px; padding: 9px 10px; font-size: 14px; }
.filtros select.usado { border-color: var(--gold); }
.contagem { color: var(--muted); font-size: 13px; margin-left: auto; padding-bottom: 10px; }
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
.aviso button { margin-left: 10px; background: none; border: 0; color: var(--gold); font-weight: 700; cursor: pointer; padding: 0; }
.origem { color: var(--faint); font-size: 12px; }
.nota { margin: 0; color: var(--muted); font-size: 13px; }
#i-url, #i-chave { font-family: ui-monospace, Consolas, monospace; font-size: 13px; }
.aviso { position: fixed; left: 50%; bottom: 20px; transform: translateX(-50%); background: var(--ink-3); border: 1px solid var(--line); border-radius: 12px; padding: 10px 16px; font-size: 14px; z-index: 20; }

dialog { background: var(--ink-2); color: var(--text); border: 1px solid var(--line); border-radius: 18px; padding: 0; width: min(440px, calc(100% - 32px)); }
dialog::backdrop { background: rgba(0, 0, 0, .7); }
dialog form { display: grid; gap: 14px; padding: 22px; }
dialog h3 { margin: 0; font-size: 18px; }
.linha { display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap; }

@media (max-width: 760px) {
  .resumo { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .filtros label { min-width: calc(50% - 5px); flex: 1; }
  .contagem { margin-left: 0; }
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
    <img class="logo" src="/img/logo.webp" width="478" height="120" alt="Gold Tech Sistemas">
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
      <h1><img class="logo" src="/img/logo-76.webp" width="304" height="76" alt="Gold Tech Sistemas"><span>Controle do Sintegra</span></h1>
      <button class="btn btn-sm btn-ghost" type="button" id="b-csv">Exportar planilha</button>
      <button class="btn btn-sm btn-ghost" type="button" id="b-integra">Integração</button>
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
      <button type="button" class="card" data-f="todos"><b id="n-total">0</b><span>clientes</span></button>
      <button type="button" class="card ok" data-f="sit:ok"><b id="n-ok">0</b><span>gerados</span></button>
      <button type="button" class="card warn" data-f="sit:warn"><b id="n-pend">0</b><span>pendentes</span></button>
      <button type="button" class="card crit" data-f="sit:crit"><b id="n-atraso">0</b><span>atrasados</span></button>
      <button type="button" class="card auto" data-f="auto:sim"><b id="n-auto">0</b><span>envio automático</span></button>
      <button type="button" class="card" data-f="ea:sim"><b id="n-ea">0</b><span>EnviarArquivos instalado</span></button>
      <button type="button" class="card" data-f="mr:sim"><b id="n-mr">0</b><span>Master Remote instalado</span></button>
    </div>
    <div class="ferramentas">
      <input type="search" id="busca" placeholder="Buscar por empresa, CNPJ ou como chamamos o cliente">
      <button class="btn btn-gold" type="button" id="b-novo">+ Novo cliente</button>
    </div>
    <div class="filtros">
      <label>Situação no mês<select id="f-sit">
        <option value="">Todos</option><option value="ok">Gerados</option><option value="warn">Pendentes</option>
        <option value="crit">Atrasados</option><option value="auto">Aguardando envio automático</option><option value="falta">Ainda não gerados (pendentes + atrasados)</option>
      </select></label>
      <label>Envio automático<select id="f-auto"><option value="">Todos</option><option value="sim">Sim</option><option value="nao">Não</option></select></label>
      <label>EnviarArquivos<select id="f-ea"><option value="">Todos</option><option value="sim">Instalado</option><option value="nao">Não instalado</option></select></label>
      <label>Master Remote<select id="f-mr"><option value="">Todos</option><option value="sim">Instalado</option><option value="nao">Não instalado</option></select></label>
      <button class="btn btn-sm btn-ghost" type="button" id="f-limpar" hidden>Limpar filtros</button>
      <span class="contagem" id="contagem"></span>
    </div>
    <table>
      <thead><tr><th>Empresa / CNPJ</th><th>Prazo</th><th>Situação</th><th>Gerado em</th><th>Envio automático</th><th>EnviarArquivos</th><th>Master Remote</th><th></th></tr></thead>
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
    <label>Como chamamos o cliente? (opcional)<input type="text" id="c-origem" maxlength="80" placeholder="Ex.: Padaria do João"></label>
    <label>Prazo para gerar (dia de cada mês)<input type="number" id="c-prazo" min="1" max="28" value="10" required></label>
    <label class="switch"><input type="checkbox" id="c-ea"> EnviarArquivos instalado</label>
    <label class="switch"><input type="checkbox" id="c-mr"> Master Remote instalado</label>
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

<dialog id="d-confirma">
  <form method="dialog">
    <h3 id="cf-titulo"></h3>
    <p id="cf-texto" style="margin:0;color:var(--muted)"></p>
    <div class="linha">
      <button class="btn" type="button" id="cf-nao">Cancelar</button>
      <button class="btn btn-gold" type="button" id="cf-sim"></button>
    </div>
  </form>
</dialog>

<dialog id="d-integra">
  <form method="dialog">
    <h3>Integração com outro sistema</h3>
    <p class="nota">Outro sistema pode cadastrar clientes e informar a data em que o Sintegra foi gerado. Ele precisa deste endereço e de uma chave.</p>
    <label>Endereço (POST)<input type="text" id="i-url" readonly></label>
    <p class="nota" id="i-estado"></p>
    <div id="i-nova" hidden>
      <label>Chave nova: copie e guarde agora, ela não aparece de novo<input type="text" id="i-chave" readonly></label>
      <div class="linha" style="justify-content:flex-start;margin-top:8px"><button class="btn btn-sm" type="button" id="i-copiar">Copiar chave</button></div>
    </div>
    <div class="linha">
      <button class="btn btn-ghost" type="button" id="i-apagar">Desligar integração</button>
      <button class="btn" type="button" id="i-gerar">Gerar chave</button>
      <button class="btn btn-gold" type="button" id="i-fechar">Fechar</button>
    </div>
  </form>
</dialog>

<div class="aviso" id="aviso" role="status" hidden><span></span><button type="button" hidden>Desfazer</button></div>

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
  // Aviso no rodapé; com "desfazer", mostra o botão Desfazer por 8 segundos.
  function aviso(t, desfazer) {
    var a = $('aviso'), b = a.querySelector('button');
    a.querySelector('span').textContent = t; a.hidden = false;
    b.hidden = !desfazer; b.onclick = desfazer ? function () { a.hidden = true; desfazer(); } : null;
    clearTimeout(aviso.t); aviso.t = setTimeout(function () { a.hidden = true; }, desfazer ? 8000 : 2600);
  }
  // Pergunta antes de apagar qualquer coisa. Responde true só se clicar no botão de confirmar.
  function confirmar(titulo, texto, botao) {
    var d = $('d-confirma');
    $('cf-titulo').textContent = titulo; $('cf-texto').textContent = texto; $('cf-sim').textContent = botao;
    d.showModal();
    return new Promise(function (ok) {
      function fim(sim) { $('cf-sim').onclick = $('cf-nao').onclick = d.oncancel = null; d.close(); ok(sim); }
      $('cf-sim').onclick = function (e) { e.preventDefault(); fim(true); };
      $('cf-nao').onclick = function (e) { e.preventDefault(); fim(false); };
      d.oncancel = function (e) { e.preventDefault(); fim(false); };
    });
  }
  function semAcento(t) { return (t || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase(); }

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
    var busca = semAcento($('busca').value.trim()), buscaNum = /^[\d.\/\s-]+$/.test(busca) ? soNum(busca) : '';
    var lista = estado.clientes.map(function (c) { return { c: c, s: situacao(c, m) }; });
    var n = { ok: 0, warn: 0, crit: 0, auto: 0 };
    lista.forEach(function (x) { n[x.s.cls]++; });
    var conta = function (campo) { return estado.clientes.filter(function (c) { return c[campo]; }).length; };
    $('n-total').textContent = lista.length; $('n-ok').textContent = n.ok; $('n-pend').textContent = n.warn;
    $('n-atraso').textContent = n.crit; $('n-auto').textContent = conta('automatico');
    $('n-ea').textContent = conta('enviarArquivos'); $('n-mr').textContent = conta('masterRemote');
    // Filtros: todos juntos (situação + automático + programas + busca).
    var f = { sit: $('f-sit').value, auto: $('f-auto').value, ea: $('f-ea').value, mr: $('f-mr').value };
    var simNao = function (v, marcado) { return !v || (v === 'sim') === !!marcado; };
    lista = lista.filter(function (x) {
      var okSit = !f.sit || (f.sit === 'falta' ? (x.s.cls === 'warn' || x.s.cls === 'crit') : x.s.cls === f.sit);
      return okSit && simNao(f.auto, x.c.automatico) && simNao(f.ea, x.c.enviarArquivos) && simNao(f.mr, x.c.masterRemote);
    });
    ['f-sit', 'f-auto', 'f-ea', 'f-mr'].forEach(function (id) { $(id).classList.toggle('usado', !!$(id).value); });
    var algum = f.sit || f.auto || f.ea || f.mr;
    $('f-limpar').hidden = !algum && !busca;
    var chave = !f.auto && !f.ea && !f.mr ? (f.sit ? 'sit:' + f.sit : (busca ? '' : 'todos'))
      : (!f.sit && !f.ea && !f.mr && f.auto === 'sim' ? 'auto:sim' : (!f.sit && !f.auto && !f.mr && f.ea === 'sim' ? 'ea:sim' : (!f.sit && !f.auto && !f.ea && f.mr === 'sim' ? 'mr:sim' : '')));
    document.querySelectorAll('.resumo .card').forEach(function (b) { b.classList.toggle('ativo', b.dataset.f === chave); });
    if (busca) lista = lista.filter(function (x) {
      return semAcento(x.c.nome).indexOf(busca) >= 0 || semAcento(x.c.origem).indexOf(busca) >= 0 || (buscaNum && x.c.cnpj.indexOf(buscaNum) >= 0);
    });
    lista.sort(function (a, b) { return a.s.ordem - b.s.ordem || a.c.nome.localeCompare(b.c.nome, 'pt-BR'); });
    $('contagem').textContent = 'Mostrando ' + lista.length + ' de ' + estado.clientes.length;
    var tb = $('linhas'); tb.textContent = '';
    lista.forEach(function (x) {
      var c = x.c, s = x.s, tr = document.createElement('tr');
      tr.innerHTML =
        '<td data-rot="Empresa / CNPJ"><div class="nome"></div><div class="cnpj"></div><div class="origem"></div></td>' +
        '<td data-rot="Prazo" class="prazo"><span></span><small></small></td>' +
        '<td data-rot="Situação"><span class="tag ' + s.cls + '"></span></td>' +
        '<td data-rot="Gerado em"><div class="gerado"><input type="date" aria-label="Data em que o Sintegra foi gerado"><button class="btn btn-sm" type="button" data-a="hoje">Gerado hoje</button><button class="btn btn-sm btn-ghost" type="button" data-a="limpar">Desmarcar</button></div></td>' +
        '<td data-rot="Envio automático"><label class="switch"><input type="checkbox" data-a="auto"> Automático</label></td>' +
        '<td data-rot="EnviarArquivos"><label class="switch"><input type="checkbox" data-a="ea"> Instalado</label></td>' +
        '<td data-rot="Master Remote"><label class="switch"><input type="checkbox" data-a="mr"> Instalado</label></td>' +
        '<td><div class="acoes"><button class="btn btn-sm" type="button" data-a="editar">Editar</button><button class="btn btn-sm" type="button" data-a="excluir">Excluir</button></div></td>';
      tr.querySelector('.nome').textContent = c.nome;
      tr.querySelector('.cnpj').textContent = fmtCnpj(c.cnpj);
      var origem = tr.querySelector('.origem'); origem.textContent = c.origem ? 'Chamamos de: ' + c.origem : ''; origem.hidden = !c.origem;
      tr.querySelector('.prazo span').textContent = br(s.prazo);
      tr.querySelector('.prazo small').textContent = textoPrazo(s);
      tr.querySelector('.tag').textContent = s.txt;
      var data = tr.querySelector('input[type=date]'); data.value = s.gerado || '';
      var hoje = tr.querySelector('[data-a=hoje]'); hoje.hidden = !!s.gerado;
      var limpar = tr.querySelector('[data-a=limpar]'); limpar.hidden = !s.gerado;
      tr.querySelector('[data-a=auto]').checked = !!c.automatico;
      [['ea', 'enviarArquivos', 'EnviarArquivos'], ['mr', 'masterRemote', 'Master Remote']].forEach(function (p) {
        var cx = tr.querySelector('[data-a=' + p[0] + ']'); cx.checked = !!c[p[1]];
        cx.addEventListener('change', function () { c[p[1]] = cx.checked; salvar(p[2] + (cx.checked ? ' marcado como instalado' : ' marcado como não instalado')); });
      });
      data.addEventListener('change', function () { if (data.value) marcar(c, data.value); else desmarcar(c, data); });
      limpar.addEventListener('click', function () { desmarcar(c); });
      hoje.addEventListener('click', function () { marcar(c, hojeISO()); });
      tr.querySelector('[data-a=auto]').addEventListener('change', function (e) {
        // Ligar pede o dia do envio; o cliente só fica automático depois de salvar.
        if (e.target.checked) { e.target.checked = false; abrirCliente(c, true); return; }
        c.automatico = false; salvar('Envio automático desligado');
      });
      tr.querySelector('[data-a=editar]').addEventListener('click', function () { abrirCliente(c); });
      tr.querySelector('[data-a=excluir]').addEventListener('click', function () {
        confirmar('Excluir cliente?', 'Deseja mesmo excluir ' + c.nome + ' (CNPJ ' + fmtCnpj(c.cnpj) + ')? Todo o histórico dele também será apagado e não dá para desfazer.', 'Sim, excluir')
          .then(function (sim) { if (!sim) return; estado.clientes = estado.clientes.filter(function (o) { return o !== c; }); salvar('Cliente excluído'); });
      });
      tb.appendChild(tr);
    });
    $('vazio').textContent = estado.clientes.length ? 'Nenhum cliente com esses filtros.' : 'Nenhum cliente ainda. Clique em “Novo cliente”.';
    $('vazio').hidden = lista.length > 0;
  }
  ['f-sit', 'f-auto', 'f-ea', 'f-mr'].forEach(function (id) { $(id).addEventListener('change', desenhar); });
  function limparFiltros() { ['f-sit', 'f-auto', 'f-ea', 'f-mr'].forEach(function (id) { $(id).value = ''; }); }
  $('f-limpar').addEventListener('click', function () { limparFiltros(); $('busca').value = ''; desenhar(); });
  // Clicar num cartão do resumo filtra por ele.
  document.querySelectorAll('.resumo .card').forEach(function (b) {
    b.addEventListener('click', function () {
      limparFiltros();
      var p = b.dataset.f.split(':');
      if (p[0] !== 'todos') $('f-' + p[0]).value = p[1];
      desenhar();
    });
  });
  function marcar(c, iso) {
    c.gerados = c.gerados || {};
    var m = mesAtual, antes = c.gerados[m];
    c.gerados[m] = iso;
    salvar('Gerado em ' + br(iso), function () {
      if (antes) c.gerados[m] = antes; else delete c.gerados[m];
      salvar('Desfeito');
    });
  }
  // Tirar a data de "gerado" sempre pergunta antes.
  function desmarcar(c, campo) {
    var m = mesAtual, antes = (c.gerados || {})[m];
    if (!antes) return;
    confirmar('Desmarcar como gerado?', 'Deseja mesmo apagar a data ' + br(antes) + ' de ' + c.nome + ' neste mês?', 'Sim, desmarcar')
      .then(function (sim) {
        if (!sim) { if (campo) campo.value = antes; return; }
        delete c.gerados[m]; salvar('Data apagada');
      });
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
  function salvar(msg, desfazer) {
    preencherAutomaticos();
    desenhar();
    return api('salvar', { rev: estado.rev, clientes: estado.clientes })
      .then(function (r) { estado.rev = r.rev; if (msg) aviso(msg + ' · salvo', desfazer); })
      // Se o servidor recusar, volta para o que está gravado lá.
      .catch(function (e) { aviso(e.message); carregar(); });
  }
  function carregar() {
    return api('dados').then(function (d) {
      estado = { rev: d.rev || 0, clientes: d.clientes || [] };
      // Lista vazia vinda do servidor vira objeto, senão as datas novas não seriam gravadas.
      estado.clientes.forEach(function (c) { if (!c.gerados || Array.isArray(c.gerados)) c.gerados = {}; });
      if (preencherAutomaticos()) salvar('Datas do envio automático atualizadas'); else desenhar();
    });
  }

  // ---------- cliente (novo / editar) ----------
  function abrirCliente(c, ligarAuto) {
    editando = c || null;
    $('d-cliente-titulo').textContent = c ? 'Editar cliente' : 'Novo cliente';
    $('c-nome').value = c ? c.nome : ''; $('c-cnpj').value = c ? fmtCnpj(c.cnpj) : '';
    $('c-prazo').value = c ? c.prazoDia : 10; $('c-auto').checked = ligarAuto || (c ? !!c.automatico : false); $('c-msg').textContent = '';
    $('c-ea').checked = !!(c && c.enviarArquivos); $('c-mr').checked = !!(c && c.masterRemote);
    $('c-envio').value = c && c.envioDia ? c.envioDia : ''; $('c-origem').value = c && c.origem ? c.origem : '';
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
    if (repetido) { $('c-msg').textContent = 'Esse CNPJ já está cadastrado em ' + estado.clientes.filter(function (o) { return o.cnpj === cnpj && o !== editando; })[0].nome + '.'; return; }
    var auto = $('c-auto').checked, envio = +$('c-envio').value;
    if (auto && !(envio >= 1 && envio <= 28)) { $('c-msg').textContent = 'Informe o dia do envio automático (1 a 28).'; return; }
    var c = editando || { id: Math.random().toString(36).slice(2, 12), gerados: {} };
    // Ao ligar o automático, começa a preencher a partir deste mês.
    if (auto && !c.automatico) c.autoProximo = mesDe(new Date());
    c.nome = nome; c.cnpj = cnpj; c.prazoDia = prazo; c.automatico = auto; c.origem = $('c-origem').value.trim();
    c.enviarArquivos = $('c-ea').checked; c.masterRemote = $('c-mr').checked;
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
    var linhas = [['Empresa', 'CNPJ', 'Prazo', 'Situação', 'Gerado em', 'Envio automático', 'Dia do envio', 'Como chamamos', 'EnviarArquivos', 'Master Remote']];
    // Exporta só o que está aparecendo (respeita filtros e busca).
    var visiveis = [].map.call(document.querySelectorAll('#linhas .cnpj'), function (e) { return soNum(e.textContent); });
    estado.clientes.filter(function (c) { return visiveis.indexOf(c.cnpj) >= 0; }).forEach(function (c) {
      var s = situacao(c, mesAtual);
      linhas.push([c.nome, fmtCnpj(c.cnpj), br(s.prazo), s.txt, s.gerado ? br(s.gerado) : '', c.automatico ? 'Sim' : 'Não', c.automatico && c.envioDia ? c.envioDia : '', c.origem || '', c.enviarArquivos ? 'Instalado' : 'Não instalado', c.masterRemote ? 'Instalado' : 'Não instalado']);
    });
    var csv = '﻿' + linhas.map(function (l) { return l.map(function (v) { return '"' + String(v).replace(/"/g, '""') + '"'; }).join(';'); }).join('\r\n');
    var a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    a.download = 'sintegra-' + mesAtual + '.csv'; a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
  });
  // ---------- integração ----------
  function dataHora(iso) { return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }); }
  function estadoIntegra() {
    return api('chave-estado').then(function (e) {
      $('i-estado').textContent = e.existe
        ? 'Integração ligada. Chave criada em ' + dataHora(e.criada) + (e.ultimoUso ? '. Último uso: ' + dataHora(e.ultimoUso) + '.' : '. Ainda não foi usada.')
        : 'Integração desligada: nenhuma chave criada.';
      $('i-gerar').textContent = e.existe ? 'Gerar chave nova' : 'Gerar chave';
      $('i-apagar').hidden = !e.existe;
      return e;
    });
  }
  $('b-integra').addEventListener('click', function () {
    $('i-url').value = location.origin + location.pathname.replace(/[^/]*$/, '') + '?api=externo';
    $('i-nova').hidden = true; $('i-chave').value = '';
    estadoIntegra().then(function () { $('d-integra').showModal(); }).catch(function (e) { aviso(e.message); });
  });
  $('i-fechar').addEventListener('click', function () { $('d-integra').close(); });
  $('i-gerar').addEventListener('click', function () {
    var existe = !$('i-apagar').hidden;
    (existe ? confirmar('Gerar chave nova?', 'A chave atual para de funcionar na hora. O outro sistema vai precisar da chave nova.', 'Sim, gerar nova') : Promise.resolve(true))
      .then(function (sim) {
        if (!sim) return;
        return api('chave-gerar').then(function (r) {
          $('i-chave').value = r.chave; $('i-nova').hidden = false; $('i-chave').select();
          return estadoIntegra();
        });
      }).catch(function (e) { aviso(e.message); });
  });
  $('i-copiar').addEventListener('click', function () {
    var ch = $('i-chave'); ch.select();
    (navigator.clipboard ? navigator.clipboard.writeText(ch.value) : Promise.reject()).then(function () { aviso('Chave copiada'); }, function () { document.execCommand('copy'); aviso('Chave copiada'); });
  });
  $('i-apagar').addEventListener('click', function () {
    confirmar('Desligar integração?', 'A chave atual para de funcionar e o outro sistema não consegue mais gravar aqui.', 'Sim, desligar')
      .then(function (sim) { if (!sim) return; return api('chave-apagar').then(function () { $('i-nova').hidden = true; aviso('Integração desligada'); return estadoIntegra(); }); })
      .catch(function (e) { aviso(e.message); });
  });
  // O outro sistema pode gravar a qualquer hora: confere a cada minuto se a lista mudou.
  setInterval(function () {
    if ($('tela-painel').hidden || document.hidden || document.querySelector('dialog[open]')) return;
    api('dados').then(function (d) { if ((d.rev || 0) !== estado.rev) carregar(); }).catch(function () {});
  }, 60000);

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
