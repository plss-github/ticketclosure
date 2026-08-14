<?php

/**
 * Diagnóstico pela interface: responde, para um chamado que já existe, se o
 * plugin deveria ter agido sobre ele -- e, sob demanda, o que acontece quando
 * ele tenta fechar.
 *
 * Existe porque o fechamento roda dentro de um hook, sem tela: quando não
 * acontece, nada aparece para quem salvou a solução. E porque quem administra o
 * GLPI muitas vezes não tem acesso ao servidor para ler o log.
 */

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

$tickets_id = (int) ($_POST['tickets_id'] ?? 0);
$attempt    = null;

if ($tickets_id && isset($_POST['fechar'])) {
  $attempt = PluginTicketclosureDiagnostic::attemptClose($tickets_id);
}

Html::header(
  __('Ticket Closure', 'ticketclosure'),
  $_SERVER['PHP_SELF'],
  'config'
);

echo "<form action='" . htmlspecialchars($_SERVER['PHP_SELF']) . "' method='post'>";
echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

echo "<table class='tab_cadre_fixe'>";
echo "<tr class='tab_bg_1'><th colspan='2'>" . __('Diagnóstico do fechamento automático', 'ticketclosure') . "</th></tr>";

echo "<tr class='tab_bg_1'>";
echo "<td>" . __('Número do chamado', 'ticketclosure') . "</td><td>";
echo Html::input('tickets_id', ['value' => $tickets_id ?: '', 'size' => 12]);
echo "&nbsp;<button type='submit' name='diagnosticar' class='btn btn-primary'>"
  . __('Diagnosticar', 'ticketclosure') . "</button>";
echo "&nbsp;<button type='submit' name='fechar' class='btn btn-warning'>"
  . __('Tentar fechar agora', 'ticketclosure') . "</button>";
echo "<p class='text-muted'>"
  . __('Diagnosticar não escreve nada. Tentar fechar agora executa a mesma atualização do hook, no chamado informado.', 'ticketclosure')
  . "</p>";
echo "</td></tr>";
echo "</table>";
Html::closeForm();

// -----------------------------------------------------------------------------
// Verificações
// -----------------------------------------------------------------------------

if ($tickets_id) {
  echo "<table class='tab_cadre_fixe'>";
  echo "<tr class='tab_bg_1'><th colspan='2'>"
    . sprintf(__('Chamado %d', 'ticketclosure'), $tickets_id) . "</th></tr>";

  foreach (PluginTicketclosureDiagnostic::report($tickets_id) as [$label, $value, $ok]) {
    $class = $ok === null ? '' : ($ok ? " class='text-success'" : " class='text-danger fw-bold'");
    echo "<tr class='tab_bg_1'><td>$label</td><td$class>$value</td></tr>";
  }

  echo "</table>";
}

// -----------------------------------------------------------------------------
// Resultado da tentativa de fechamento
// -----------------------------------------------------------------------------

if ($attempt !== null) {
  $statuses = Ticket::getAllStatusArray();

  echo "<table class='tab_cadre_fixe'>";
  echo "<tr class='tab_bg_1'><th colspan='2'>" . __('Tentativa de fechamento', 'ticketclosure') . "</th></tr>";

  echo "<tr class='tab_bg_1'><td>" . __('Status antes', 'ticketclosure') . "</td><td>"
    . $attempt['before'] . ' (' . ($statuses[$attempt['before']] ?? '?') . ")</td></tr>";
  echo "<tr class='tab_bg_1'><td>" . __('Status depois', 'ticketclosure') . "</td><td>"
    . $attempt['after'] . ' (' . ($statuses[$attempt['after']] ?? '?') . ")</td></tr>";

  foreach ($attempt['messages'] as $message) {
    echo "<tr class='tab_bg_1'><td>" . __('Mensagem do pipeline', 'ticketclosure') . "</td><td>"
      . htmlspecialchars($message) . "</td></tr>";
  }

  echo "<tr class='tab_bg_1'><td>" . __('Resultado', 'ticketclosure') . "</td><td class='"
    . ($attempt['ok'] ? 'text-success' : 'text-danger fw-bold') . "'>"
    . ($attempt['ok']
      ? __('Fechou -- o problema não está na atualização em si.', 'ticketclosure')
      : __('Não fechou -- algo no pipeline de atualização descartou o status.', 'ticketclosure'))
    . "</td></tr>";

  echo "</table>";
}

// -----------------------------------------------------------------------------
// Últimas decisões do hook
// -----------------------------------------------------------------------------

$tail = PluginTicketclosureDiagnostic::logTail(30);

echo "<table class='tab_cadre_fixe'>";
echo "<tr class='tab_bg_1'><th>" . __('Últimas decisões do plugin', 'ticketclosure')
  . ' <span class="text-muted">' . htmlspecialchars(PluginTicketclosureDiagnostic::logPath()) . '</span></th></tr>';

if (empty($tail)) {
  echo "<tr class='tab_bg_1'><td>"
    . __('Nada registrado ainda -- nenhuma solução foi salva desde que esta versão do plugin entrou.', 'ticketclosure')
    . "</td></tr>";
} else {
  foreach ($tail as $line) {
    echo "<tr class='tab_bg_1'><td><code>" . htmlspecialchars($line) . "</code></td></tr>";
  }
}

echo "</table>";

Html::footer();
