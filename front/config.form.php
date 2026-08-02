<?php

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

$action = $_POST['_action'] ?? '';

if ($action === 'save_config' && isset($_POST['update'])) {
  $categories = array_map('intval', $_POST['categories'] ?? []);
  Config::setConfigurationValues('plugin_ticketclosure', [
    'categories' => json_encode(array_values($categories)),
  ]);
  Session::addMessageAfterRedirect(__('Configurações salvas.', 'ticketclosure'));
}

// Também serve como alvo do hook config_page: acessado via GET (botão de
// "Configurar" em Configurar > Plugins), redireciona direto para a aba,
// em vez de depender do Referer como o Html::back() faz.
global $CFG_GLPI;
Html::redirect($CFG_GLPI['root_doc'] . '/front/config.form.php?forcetab=' . urlencode('PluginTicketclosureConfig$1'));