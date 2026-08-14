<?php

if (!defined('GLPI_ROOT')) {
  die("Sorry. You can't access this file directly");
}

/**
 * Diagnóstico do fechamento automático.
 *
 * O fechamento roda dentro de um hook, sem tela: quando não acontece, nada
 * aparece para quem salvou a solução. Esta classe reúne o que precisa ser
 * conferido -- estado do plugin, configuração lida, chamado, e quem mais mexe na
 * atualização de chamado -- para que o administrador chegue à causa pela
 * interface, sem acesso ao servidor.
 */
class PluginTicketclosureDiagnostic {

  /**
   * Linhas de verificação para um chamado, na forma
   * [rótulo, valor, ok] -- ok = true/false destaca, null é só informação.
   */
  static function report(int $tickets_id): array {
    global $PLUGIN_HOOKS;

    $rows = [];

    $rows[] = [
      __('Plugin ativo', 'ticketclosure'),
      Plugin::isPluginActive('ticketclosure') ? __('Sim') : __('Não'),
      Plugin::isPluginActive('ticketclosure'),
    ];

    $hooked = isset($PLUGIN_HOOKS['item_add']['ticketclosure']['ITILSolution']);
    $rows[] = [
      __('Hook item_add / ITILSolution', 'ticketclosure'),
      $hooked ? __('registrado', 'ticketclosure') : __('ausente', 'ticketclosure'),
      $hooked,
    ];

    // O valor cru do glpi_configs, e não só a lista decodificada: se o JSON tiver
    // se corrompido na gravação, é aqui que aparece.
    $raw = Config::getConfigurationValues('plugin_ticketclosure', ['categories']);
    $rows[] = [
      __('Valor gravado', 'ticketclosure'),
      '<code>' . htmlspecialchars($raw['categories'] ?? '(ausente)') . '</code>',
      array_key_exists('categories', $raw),
    ];

    $categories = PluginTicketclosureConfig::getAutoApproveCategories();
    $rows[] = [
      __('Categorias lidas', 'ticketclosure'),
      count($categories) . ' &rarr; [' . implode(', ', $categories) . ']',
      !empty($categories),
    ];

    // Todo plugin que escuta a atualização de chamado pode remover `status` da
    // entrada e fazer o fechamento sumir sem erro nenhum. Listar quem escuta é o
    // caminho mais curto para o suspeito.
    $rows[] = [
      __('Outros plugins em pre_item_update / Ticket', 'ticketclosure'),
      implode(', ', self::otherPluginsOn('pre_item_update', 'Ticket')) ?: __('(nenhum)', 'ticketclosure'),
      null,
    ];
    $rows[] = [
      __('Outros plugins em item_add / ITILSolution', 'ticketclosure'),
      implode(', ', self::otherPluginsOn('item_add', 'ITILSolution')) ?: __('(nenhum)', 'ticketclosure'),
      null,
    ];

    $ticket = new Ticket();
    if (!$ticket->getFromDB($tickets_id)) {
      $rows[] = [__('Chamado', 'ticketclosure'), sprintf(__('%d não encontrado', 'ticketclosure'), $tickets_id), false];
      return $rows;
    }

    $statuses = Ticket::getAllStatusArray();
    $status   = (int) $ticket->fields['status'];
    $category = (int) $ticket->fields['itilcategories_id'];

    $rows[] = [__('Chamado'), $ticket->getLink(), null];
    $rows[] = [__('Entity'), Dropdown::getDropdownName('glpi_entities', $ticket->fields['entities_id']), null];
    $rows[] = [__('Status'), "$status (" . ($statuses[$status] ?? '?') . ')', null];
    $rows[] = [
      __('Category'),
      $category . ' (' . ($category ? Dropdown::getDropdownName('glpi_itilcategories', $category) : __('None')) . ')',
      null,
    ];

    $matches = in_array($category, $categories, true);
    $rows[] = [__('Categoria está na lista do plugin', 'ticketclosure'), $matches ? __('Sim') : __('Não'), $matches];

    // O estado da solução separa as duas causas possíveis: "Aprovada" quer dizer
    // que o plugin rodou e só o status foi descartado; "Aguardando aprovação"
    // quer dizer que ele não agiu sobre este chamado.
    $solutions = getAllDataFromTable('glpi_itilsolutions', [
      'itemtype' => 'Ticket',
      'items_id' => $tickets_id,
      'ORDER'    => 'id DESC',
    ]);
    foreach ($solutions as $solution) {
      $rows[] = [
        sprintf(__('Solução #%d', 'ticketclosure'), $solution['id']),
        (ITILSolution::getStatuses()[$solution['status']] ?? '?')
        . ', ' . sprintf(__('de %s', 'ticketclosure'), Dropdown::getDropdownName('glpi_users', $solution['users_id'])),
        null,
      ];
    }

    return $rows;
  }

  /**
   * Tenta o fechamento -- exatamente a mesma chamada que o hook faz -- e devolve
   * o que aconteceu, com as mensagens que o pipeline enfileirou no caminho.
   */
  static function attemptClose(int $tickets_id): array {
    $ticket = new Ticket();
    if (!$ticket->getFromDB($tickets_id)) {
      return ['ok' => false, 'before' => null, 'after' => null, 'messages' => [__('Chamado não encontrado.', 'ticketclosure')]];
    }

    $before = (int) $ticket->fields['status'];

    // A fila é lida depois do update para saber quem reclamou; o que já estava
    // nela vem de outra ação e só confundiria.
    $previous = $_SESSION['MESSAGE_AFTER_REDIRECT'] ?? [];
    $_SESSION['MESSAGE_AFTER_REDIRECT'] = [];

    $after = PluginTicketclosureSolution::closeTicket($ticket);

    $messages = [];
    foreach ($_SESSION['MESSAGE_AFTER_REDIRECT'] ?? [] as $list) {
      foreach ((array) $list as $message) {
        $messages[] = trim(strip_tags($message));
      }
    }
    $_SESSION['MESSAGE_AFTER_REDIRECT'] = $previous;

    return [
      'ok'       => $after === Ticket::CLOSED,
      'before'   => $before,
      'after'    => $after,
      'messages' => $messages,
    ];
  }

  /**
   * Últimas decisões registradas, uma por linha, mais recente primeiro.
   *
   * O Toolbox::logInFile grava cada entrada em duas linhas (cabeçalho com data e
   * usuário, depois a mensagem); aqui elas voltam juntas.
   */
  static function logTail(int $entries = 30): array {
    $path = self::logPath();
    if (!is_readable($path)) {
      return [];
    }

    // Um log de produção cresce sem limite, então lemos só o fim dele.
    $handle = fopen($path, 'r');
    if ($handle === false) {
      return [];
    }
    fseek($handle, max(0, filesize($path) - 64 * 1024));
    $chunk = stream_get_contents($handle);
    fclose($handle);

    $lines   = preg_split('/\R/', trim($chunk));
    $tail    = [];
    $pending = null;

    foreach ($lines as $line) {
      if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $line)) {
        $pending = $line;
      } elseif ($pending !== null) {
        $tail[]  = $pending . ' ' . $line;
        $pending = null;
      }
    }

    return array_slice(array_reverse($tail), 0, $entries);
  }

  static function logPath(): string {
    return GLPI_LOG_DIR . '/' . PluginTicketclosureSolution::LOGFILE . '.log';
  }

  static private function otherPluginsOn(string $hook, string $itemtype): array {
    global $PLUGIN_HOOKS;

    $others = [];
    foreach ($PLUGIN_HOOKS[$hook] ?? [] as $plugin => $tab) {
      if (is_array($tab) && isset($tab[$itemtype]) && $plugin !== 'ticketclosure') {
        $others[] = $plugin;
      }
    }

    return $others;
  }
}
