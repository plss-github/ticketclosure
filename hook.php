<?php

if (!defined('GLPI_ROOT')) {
  die("Sorry. You can't access this file directly");
}

function plugin_ticketclosure_install() {
  PluginTicketclosureConfig::setDefaults();
  return true;
}

function plugin_ticketclosure_uninstall() {
  PluginTicketclosureConfig::removeConfig();
  return true;
}
