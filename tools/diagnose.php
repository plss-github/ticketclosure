<?php

/**
 * O mesmo diagnóstico de front/diagnose.php, pela linha de comando, para quando
 * há acesso ao servidor.
 *
 *   cd <raiz do glpi>
 *   php plugins/ticketclosure/tools/diagnose.php 2083969
 *   php plugins/ticketclosure/tools/diagnose.php 2083969 --fechar
 *
 * A sessão é montada com o usuário de --usuario-id (padrão 2, o super-admin da
 * instalação). Rodar com o id de quem registrou a solução reproduz o cenário
 * real, inclusive as restrições de direito daquele perfil.
 *
 * Sem --fechar nada é escrito: o script só lê configuração e chamado.
 */

include(__DIR__ . '/../../../inc/includes.php');

if (!isCommandLine()) {
  die("Este script roda apenas pela linha de comando.\n");
}

// -----------------------------------------------------------------------------
// Argumentos
// -----------------------------------------------------------------------------

$tickets_id = 0;
$do_close   = false;
$users_id   = 2;

foreach (array_slice($argv, 1) as $arg) {
  if (ctype_digit($arg)) {
    $tickets_id = (int) $arg;
  } elseif ($arg === '--fechar') {
    $do_close = true;
  } elseif (preg_match('/^--usuario-id=(\d+)$/', $arg, $m)) {
    $users_id = (int) $m[1];
  } else {
    die("Argumento desconhecido: $arg\n");
  }
}

if (!$tickets_id) {
  die("Uso: php plugins/ticketclosure/tools/diagnose.php <id do chamado> [--fechar] [--usuario-id=N]\n");
}

// -----------------------------------------------------------------------------
// Sessão: o update de chamado depende de direitos, então o diagnóstico precisa
// rodar com um usuário de verdade, e não com a sessão vazia da CLI.
// -----------------------------------------------------------------------------

$user = new User();
if (!$user->getFromDB($users_id)) {
  die("Usuário $users_id não encontrado.\n");
}

$_SESSION['glpiID']           = $users_id;
$_SESSION['glpiname']         = $user->fields['name'];
$_SESSION['glpi_currenttime'] = date('Y-m-d H:i:s');
// Sem entidade preferida, changeProfile ativa todas as do perfil -- é o que
// queremos, já que a entidade certa é a do chamado.
$_SESSION['glpidefault_entity'] = null;

Session::initEntityProfiles($users_id);
if (empty($_SESSION['glpiprofiles'])) {
  die("Usuário {$user->fields['name']} não tem perfil em nenhuma entidade.\n");
}
Session::changeProfile(array_key_first($_SESSION['glpiprofiles']));
Session::loadLanguage();

/** Os relatórios são feitos para a interface; aqui viram texto puro. */
$plain = static function (string $html): string {
  return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
};

// O printf conta bytes, e os rótulos têm acento: o alinhamento sai torto sem
// contar caracteres.
$row = static function (string $label, string $value): void {
  echo $label . str_repeat(' ', max(1, 42 - mb_strlen($label))) . $value . "\n";
};

$row(
  'Sessão',
  "{$user->fields['name']} (id $users_id), perfil " . ($_SESSION['glpiactiveprofile']['name'] ?? '?')
  . ', direito de atualizar chamados: ' . (Session::haveRight('ticket', UPDATE) ? 'sim' : 'NÃO')
);

// -----------------------------------------------------------------------------
// Verificações
// -----------------------------------------------------------------------------

foreach (PluginTicketclosureDiagnostic::report($tickets_id) as [$label, $value, $ok]) {
  $row($plain($label), $plain((string) $value) . ($ok === false ? '   <-- aqui' : ''));
}

// -----------------------------------------------------------------------------
// Tentativa de fechamento
// -----------------------------------------------------------------------------

if ($do_close) {
  echo "\nFechando...\n";

  $attempt  = PluginTicketclosureDiagnostic::attemptClose($tickets_id);
  $statuses = Ticket::getAllStatusArray();

  foreach ($attempt['messages'] as $message) {
    echo "  mensagem do pipeline: $message\n";
  }

  $row('Status antes', $attempt['before'] . ' (' . ($statuses[$attempt['before']] ?? '?') . ')');
  $row('Status depois', $attempt['after'] . ' (' . ($statuses[$attempt['after']] ?? '?') . ')');
  $row('Resultado', $attempt['ok']
    ? 'fechou -- o problema não está na atualização em si'
    : 'NÃO fechou -- algo no pipeline de atualização descartou o status');
} else {
  echo "\nRode de novo com --fechar para tentar o fechamento e ver o que o barra.\n";
}

// -----------------------------------------------------------------------------
// Últimas decisões do hook
// -----------------------------------------------------------------------------

$tail = PluginTicketclosureDiagnostic::logTail(30);

echo "\nÚltimas decisões (" . PluginTicketclosureDiagnostic::logPath() . "):\n";
if (empty($tail)) {
  echo "  (nada registrado ainda)\n";
}
foreach ($tail as $line) {
  echo "  $line\n";
}
