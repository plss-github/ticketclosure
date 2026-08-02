<?php

if (!defined('GLPI_ROOT')) {
  die("Sorry. You can't access this file directly");
}

class PluginTicketclosureSolution {

  static function onSolutionAdd(ITILSolution $solution) {
    if (($solution->fields['itemtype'] ?? null) !== 'Ticket') {
      return;
    }

    $categories = PluginTicketclosureConfig::getAutoApproveCategories();
    if (empty($categories)) {
      return;
    }

    $ticket = new Ticket();
    if (!$ticket->getFromDB($solution->fields['items_id'])) {
      return;
    }

    if (!in_array((int) $ticket->fields['itilcategories_id'], $categories, true)) {
      return;
    }

    if ((int) $ticket->fields['status'] === Ticket::CLOSED) {
      return;
    }

    $ticket->update([
      'id'        => $ticket->getID(),
      'status'    => Ticket::CLOSED,
      'closedate' => $_SESSION['glpi_currenttime'],
      '_accepted' => true,
    ]);
  }
}
