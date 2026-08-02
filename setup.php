<?php

define('PLUGIN_TICKETCLOSURE_VERSION', '1.0.0');

define('PLUGIN_TICKETCLOSURE_MIN_GLPI', '10.0.0');
// define('PLUGIN_TICKETCLOSURE_MAX_GLPI', '11.0.99');

if (!defined('PLUGINTICKETCLOSURE_DIR')) {
  define('PLUGINTICKETCLOSURE_DIR', Plugin::getPhpDir('ticketclosure'));
}

function plugin_init_ticketclosure() {
  global $PLUGIN_HOOKS;

  $PLUGIN_HOOKS['csrf_compliant']['ticketclosure'] = true;

  // Ao adicionar uma solução, decide se deve pular a aprovação e fechar direto
  $PLUGIN_HOOKS['item_add']['ticketclosure'] = [
    'ITILSolution' => ['PluginTicketclosureSolution', 'onSolutionAdd'],
  ];

  // Adiciona aba "Ticket Closure" em Configuração > Geral
  Plugin::registerClass('PluginTicketclosureConfig', ['addtabon' => 'Config']);

  $PLUGIN_HOOKS['config_page']['ticketclosure'] = 'front/config.form.php';
}

function plugin_version_ticketclosure() {
  return [
    'name'         => 'Ticket Closure',
    'version'      => PLUGIN_TICKETCLOSURE_VERSION,
    'author'       => 'Ampris',
    'homepage'     => 'https://github.com/plss-github/ticketclosure',
    'license'      => 'GPLv2+',
    'requirements' => [
      'glpi' => [
        'min' => PLUGIN_TICKETCLOSURE_MIN_GLPI,
      ],
    ],
  ];
}

function plugin_ticketclosure_check_prerequisites() {
  return true;
}

function plugin_ticketclosure_check_config($verbose = false) {
  return true;
}
