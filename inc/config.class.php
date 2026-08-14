<?php

if (!defined('GLPI_ROOT')) {
  die("Sorry. You can't access this file directly");
}

class PluginTicketclosureConfig extends CommonGLPI {

  static function getTypeName($nb = 0) {
    return __('Ticket Closure', 'ticketclosure');
  }

  static function getIcon() {
    return 'ti ti-checkbox';
  }

  public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
    if ($item instanceof Config) {
      return CommonGLPI::createTabEntry(self::getTypeName(1), 0, null, self::getIcon());
    }
    return '';
  }

  public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
    if ($item instanceof Config) {
      self::showForm();
    }
    return true;
  }

  // -------------------------------------------------------------------------
  // Leitura / escrita de configuração
  // -------------------------------------------------------------------------

  static function getDefaults(): array {
    return [
      'categories' => '[]', // JSON com os IDs de ITILCategory que pulam a aprovação
    ];
  }

  static function getConfig(): array {
    return Config::getConfigurationValues('plugin_ticketclosure', array_keys(self::getDefaults()));
  }

  static function setDefaults(): void {
    $existing = Config::getConfigurationValues('plugin_ticketclosure', array_keys(self::getDefaults()));
    $toSet    = [];
    foreach (self::getDefaults() as $key => $default) {
      if (!array_key_exists($key, $existing) || $existing[$key] === '') {
        $toSet[$key] = $default;
      }
    }
    if (!empty($toSet)) {
      Config::setConfigurationValues('plugin_ticketclosure', $toSet);
    }
  }

  static function removeConfig(): void {
    $config = new Config();
    $config->deleteByCriteria(['context' => 'plugin_ticketclosure']);
  }

  static function getAutoApproveCategories(): array {
    $config = self::getConfig();
    $ids    = json_decode($config['categories'] ?? '[]', true);
    if (!is_array($ids)) {
      return [];
    }
    return array_map('intval', $ids);
  }

  // -------------------------------------------------------------------------
  // Formulário
  // -------------------------------------------------------------------------

  static function showForm(): void {
    $selected = self::getAutoApproveCategories();
    $form_url = Plugin::getWebDir('ticketclosure') . '/front/config.form.php';

    echo "<form action='$form_url' method='post'>";
    echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
    echo Html::hidden('_action', ['value' => 'save_config']);

    echo "<table class='tab_cadre_fixe'>";
    echo "<tr class='tab_bg_1'>";
    echo "<th colspan='2'>" . __('Fechamento automático por categoria', 'ticketclosure') . "</th>";
    echo "</tr>";

    echo "<tr class='tab_bg_1'>";
    echo "<td>" . __('Categorias com aprovação automática', 'ticketclosure') . "</td>";
    echo "<td>";
    Dropdown::show('ITILCategory', [
      'name'     => 'categories[]',
      'value'    => $selected,
      'multiple' => true,
      'width'    => '100%',
    ]);
    echo "<p class='text-muted'>" .
      __('Chamados dessas categorias, ao receber uma solução, pulam a etapa de aprovação e são fechados automaticamente.', 'ticketclosure') .
      "</p>";
    echo "</td>";
    echo "</tr>";

    echo "<tr class='tab_bg_2'><td colspan='2' class='center'>";
    echo "<button type='submit' name='update' class='btn btn-primary' title='" . _sx('button', 'Save') . "'>";
    echo "<i class='ti ti-device-floppy'></i><span class='d-none d-xxl-block'>" . _sx('button', 'Save') . "</span></button>";
    echo "</td></tr>";
    echo "</table>";
    Html::closeForm();

    self::showDiagnosticShortcut();
  }

  /**
   * Atalho para o diagnóstico e as últimas decisões do hook.
   *
   * Fica aqui porque quem configura as categorias é quem precisa saber por que um
   * chamado não fechou -- e normalmente não tem acesso ao servidor para ler o log.
   */
  static private function showDiagnosticShortcut(): void {
    $url = Plugin::getWebDir('ticketclosure') . '/front/diagnose.php';

    echo "<table class='tab_cadre_fixe'>";
    echo "<tr class='tab_bg_1'><th>" . __('Diagnóstico', 'ticketclosure') . "</th></tr>";

    echo "<tr class='tab_bg_1'><td>";
    echo "<a class='btn btn-secondary' href='$url'><i class='ti ti-stethoscope'></i> "
      . __('Diagnosticar um chamado que não fechou', 'ticketclosure') . "</a>";
    echo "</td></tr>";

    foreach (PluginTicketclosureDiagnostic::logTail(5) as $line) {
      echo "<tr class='tab_bg_1'><td><code>" . htmlspecialchars($line) . "</code></td></tr>";
    }

    echo "</table>";
  }
}
