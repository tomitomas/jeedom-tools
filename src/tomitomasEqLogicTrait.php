<?php

trait tomitomasEqLogicTrait {

    private static function getConfigFileContent($filePath) {
        if (!file_exists($filePath)) {
            throw new Exception(__("Fichier de configuration non trouvé", __FILE__) . ' ' . $filePath);
        }
        $content = file_get_contents($filePath);
        if (!is_json($content)) {
            throw new Exception(__("Fichier de configuration incorrecte", __FILE__) . ' ' . $filePath);
        }
        $content = translate::exec($content, realpath($filePath));
        return json_decode($content, true);
    }

    public function createCommands($filePath, $type = null) {
        try {

            // on traduit le fichier de configuration
            $configFile = self::getConfigFileContent($filePath);

            // on lance la création des commandes (déjà traduites) et on partage le dictionnaire (pour les listes des valeurs)
            if ($type == null) {
                $this->createCommandsFromConfig($configFile['cmds']);
            } elseif (isset($configFile['cmds'][$type])) {
                $this->createCommandsFromConfig($configFile['cmds'][$type]);
            } else {
                self::error($type . ' not found in config');
            }
        } catch (Exception $e) {
            self::error('Cannot save Cmd for this EqLogic -- ' . $e->getMessage());
        }
    }

    public static function getFileContent($path) {

        if (!file_exists($path)) {
            self::error('File not found  : ' . $path);
            return null;
        }

        $content = file_get_contents($path);

        if (is_json($content)) {
            return json_decode($content, true);
        }

        return $content;
    }

    public function createCommandsFromConfig($commands) {
        $cmd_updated_by = array();
        foreach ($commands as $cmdData) {
            $cmd = $this->getCmd(null, $cmdData["logicalId"]);

            if (!is_object($cmd)) {
                self::debug('cmd creation => ' . $cmdData["name"] . ' [' . $cmdData["logicalId"] . ']');

                $cmd = new cmd();
                $cmd->setLogicalId($cmdData["logicalId"]);
                $cmd->setEqLogic_id($this->getId());

                if (isset($cmdData["isVisible"])) {
                    $cmd->setIsVisible($cmdData["isVisible"]);
                }

                if (isset($cmdData["isHistorized"])) {
                    $cmd->setIsHistorized($cmdData["isHistorized"]);
                }

                if (isset($cmdData["generic_type"])) {
                    $cmd->setGeneric_type($cmdData["generic_type"]);
                }

                if (isset($cmdData["unite"])) {
                    $cmd->setUnite($cmdData["unite"]);
                }

                if (isset($cmdData["order"])) {
                    $cmd->setOrder($cmdData["order"]);
                }
            }

            $cmd->setName(__($cmdData["name"], __FILE__));

            $cmd->setType($cmdData["type"]);
            $cmd->setSubType($cmdData["subtype"]);

            if (isset($cmdData['configuration'])) {
                foreach ($cmdData['configuration'] as $key => $value) {
                    $cmd->setConfiguration($key, $value);
                }
            }

            if (isset($cmdData['display'])) {
                foreach ($cmdData['display'] as $key => $value) {
                    $cmd->setDisplay($key, $value);
                }
            }

            if (isset($cmdData['template'])) {
                foreach ($cmdData['template'] as $key => $value) {
                    $cmd->setTemplate($key, $value);
                }
            }

            if (isset($cmdData['updateCmd'])) {
                $cmd_updated_by[$cmdData["logicalId"]] = $cmdData['updateCmd'];
            }

            $cmd->save();
        }

        foreach ($cmd_updated_by as $cmdAction_logicalId => $cmdInfo_logicalId) {
            $cmdAction = $this->getCmd(null, $cmdAction_logicalId);
            $cmdInfo = $this->getCmd(null, $cmdInfo_logicalId);

            if (is_object($cmdAction) && is_object($cmdInfo)) {
                $cmdAction->setValue($cmdInfo->getId());
                $cmdAction->save();
            }
        }
    }

    public static function createListOption($data, $dict, $filter = array()) {

        $list = '';
        $needFilter = count($filter) > 0;
        foreach ($data as $item) {
            if ($needFilter && !in_array($item, $filter)) {
                self::warning($item . ' ' . __('valeur filtrée, on passe', __FILE__));
                continue;
            }
            $val = $dict[$item] ?? $item;
            $list .= $item . '|' . $val . ';';
        }
        $list = ($list != '') ? substr($list, 0, -1) : '';

        return $list;
    }

    public static function getPlurial($nb) {
        return ($nb > 1) ? 's' : '';
    }

    public static function getConfigForCommunity($withQuote = true) {

        $infoPlugin = '<b>Version OS</b> : ' .  system::getDistrib() . ' ' . system::getOsVersion() . '<br/>';

        $infoPlugin .= '<b>Version PHP</b> : ' . phpversion();

        if ($withQuote) {
            return self::getPreformattedText($infoPlugin);
        }

        return $infoPlugin;
    }

    public static function getPreformattedText($string) {
        return '<br/>```text<br/>' . str_replace(array('<b>', '</b>', '&nbsp;'), array('', '', ' '), $string) . '<br/>```<br/>';
    }

    public static function backupExclude() {
        return [
            'resources/venv'
        ];
    }


    /*******************************
     * From @Mips2648
     ******************************* /
     
    
    /**
     * Allow to perform a // task exec : now or later
     *
     * @param string $_method
     * @param array|null $_option
     * @param string $_date
     * @return void
     */
    public static function executeAsync(string $_method, $_option = null, $_date = 'now') {
        if (!method_exists(__CLASS__, $_method)) {
            throw new InvalidArgumentException("Method provided for executeAsync does not exist: {$_method}");
        }

        $cron = new cron();
        $cron->setClass(__CLASS__);
        $cron->setFunction($_method);
        if (isset($_option)) {
            $cron->setOption($_option);
        }
        $cron->setOnce(1);
        $scheduleTime = strtotime($_date);
        $cron->setSchedule(cron::convertDateToCron($scheduleTime));
        $cron->save();
        if ($scheduleTime <= strtotime('now')) {
            $cron->run();
            self::debug("Task '{$_method}' executed now");
        } else {
            self::debug("Task '{$_method}' scheduled at {$_date}");
        }
    }

    private static function pythonRequirementsInstalled(string $pythonPath, string $requirementsPath) {
        if (!file_exists($pythonPath) || !file_exists($requirementsPath)) {
            return false;
        }
        exec("{$pythonPath} -m pip freeze", $packages_installed);
        $packages = join("||", $packages_installed);
        exec("cat {$requirementsPath}", $packages_needed);
        foreach ($packages_needed as $line) {
            if (preg_match('/([^\s]+)[\s]*([>=~]=)[\s]*([\d+\.?]+)$/', $line, $need) === 1) {
                if (preg_match('/' . $need[1] . '==([\d+\.?]+)/', $packages, $install) === 1) {
                    if ($need[2] == '==' && $need[3] != $install[1]) {
                        return false;
                    } elseif (version_compare($need[3], $install[1], '>')) {
                        return false;
                    }
                } else {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     ******************** LOGS FUNCTIONS
     */

    public static function trace($message, $suffix = '') {
        if (config::byKey('traceLog', __CLASS__, 0)) {
            log::add(__CLASS__ . $suffix, 'debug', '[TRACE] ' . $message);
        }
    }

    public static function debug($message, $suffix = '') {
        log::add(__CLASS__ . $suffix, 'debug', $message);
    }

    public static function info($message, $suffix = '') {
        log::add(__CLASS__ . $suffix, 'info', $message);
    }

    public static function warning($message, $suffix = '') {
        log::add(__CLASS__ . $suffix, 'warning', $message);
    }

    public static function error($message, $suffix = '') {
        log::add(__CLASS__ . $suffix, 'error', $message);
    }
}
