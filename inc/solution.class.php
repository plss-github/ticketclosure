<?php

if (!defined('GLPI_ROOT')) {
  die("Sorry. You can't access this file directly");
}

class PluginTicketclosureSolution {

  // Arquivo em GLPI_LOG_DIR que recebe uma linha por solução registrada em
  // chamado. É o único rastro que o fechamento automático deixa: como ele roda
  // dentro de um hook, não há tela para mostrar erro a quem salvou a solução.
  const LOGFILE = 'ticketclosure';

  static function onSolutionAdd(ITILSolution $solution) {
    if (($solution->fields['itemtype'] ?? null) !== 'Ticket') {
      return;
    }

    $tickets_id = (int) $solution->fields['items_id'];

    $ticket = new Ticket();
    if (!$ticket->getFromDB($tickets_id)) {
      self::log("chamado $tickets_id: chamado não encontrado");
      return;
    }

    $skip = self::skipReason($ticket);
    if ($skip !== null) {
      self::log("chamado $tickets_id: $skip");
      return;
    }

    $status = self::closeTicket($ticket);

    if ($status === Ticket::CLOSED) {
      self::log("chamado $tickets_id: fechado automaticamente (categoria " . (int) $ticket->fields['itilcategories_id'] . ')');
      return;
    }

    self::log(
      "chamado $tickets_id: FALHOU ao fechar -- status continuou $status, esperado " . Ticket::CLOSED
      . self::queuedMessages()
    );
  }

  /**
   * Por que este chamado não deve ser fechado pelo plugin, ou null se deve.
   * Não escreve nada: serve tanto ao hook quanto ao diagnóstico.
   */
  static function skipReason(Ticket $ticket): ?string {
    $categories = PluginTicketclosureConfig::getAutoApproveCategories();
    if (empty($categories)) {
      return 'nenhuma categoria com aprovação automática configurada';
    }

    $category = (int) $ticket->fields['itilcategories_id'];
    if (!in_array($category, $categories, true)) {
      return "categoria $category fora da lista [" . implode(', ', $categories) . ']';
    }

    if ((int) $ticket->fields['status'] === Ticket::CLOSED) {
      return 'já estava fechado';
    }

    return null;
  }

  /**
   * O fechamento em si, e o status que ficou gravado depois dele.
   *
   * Fechar um chamado atravessa o pipeline de atualização inteiro: modelo de
   * chamado, regras de negócio e o pre_item_update de todos os plugins ativos.
   * Qualquer um deles pode tirar `status` da entrada -- o Behaviors faz
   * exatamente isso quando um campo obrigatório na resolução está vazio -- e o
   * update ainda assim retorna sucesso. Por isso devolvemos o status lido de
   * volta do banco, e não o retorno do update.
   */
  static function closeTicket(Ticket $ticket): int {
    $tickets_id = (int) $ticket->getID();

    $ticket->update([
      'id'        => $tickets_id,
      'status'    => Ticket::CLOSED,
      'closedate' => $_SESSION['glpi_currenttime'],
      '_accepted' => true,
    ]);

    $saved = new Ticket();
    $saved->getFromDB($tickets_id);

    return (int) $saved->fields['status'];
  }

  /**
   * Mensagens que o GLPI e os outros plugins deixaram na fila desta requisição.
   * Quem barra um fechamento costuma enfileirar o motivo antes de remover o
   * `status` da entrada, então é o melhor rastro disponível de quem barrou.
   */
  static function queuedMessages(): string {
    $lines = [];
    foreach ($_SESSION['MESSAGE_AFTER_REDIRECT'] ?? [] as $messages) {
      foreach ((array) $messages as $message) {
        $lines[] = trim(strip_tags($message));
      }
    }

    return empty($lines) ? '' : ' -- mensagens na fila: ' . implode(' | ', $lines);
  }

  static function log(string $message): void {
    Toolbox::logInFile(self::LOGFILE, $message . "\n", true);
  }
}
