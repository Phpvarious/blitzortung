<?php
/* This file is part of Jeedom.
*
* Jeedom is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* Jeedom is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
*/

/* * ***************************Includes********************************* */
require_once __DIR__  . '/../../../../core/php/core.inc.php';

class blitzortung extends eqLogic {
  /*     * *************************Attributs****************************** */

  /*
  * Permet de définir les possibilités de personnalisation du widget (en cas d'utilisation de la fonction 'toHtml' par exemple)
  * Tableau multidimensionnel - exemple: array('custom' => true, 'custom::layout' => false)
  public static $_widgetPossibility = array();
  */

  /*
  * Permet de crypter/décrypter automatiquement des champs de configuration du plugin
  * Exemple : "param1" & "param2" seront cryptés mais pas "param3"
  public static $_encryptConfigKey = array('param1', 'param2');
  */

  /*     * ***********************Methode static*************************** */

  /*
  * Fonction exécutée automatiquement toutes les minutes par Jeedom
  public static function cron() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 5 minutes par Jeedom
  */
  public static function cron5() {
    self::blitzortungCron();
  }

  /*
  * Fonction exécutée automatiquement toutes les 10 minutes par Jeedom
  public static function cron10() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 15 minutes par Jeedom
  public static function cron15() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les 30 minutes par Jeedom
  public static function cron30() {}
  */

  /*
  * Fonction exécutée automatiquement toutes les heures par Jeedom
  public static function cronHourly() {}
  */

  /*
  * Fonction exécutée automatiquement tous les jours par Jeedom
  public static function cronDaily() {}
  */

  public static function setupCron($creation) {
    if ($creation == 1) {
      $oCron = cron::byClassAndFunction(__CLASS__, 'blitzortungCron');
      if (!is_object($oCron)) {
        $oCron = new cron();
        $oCron->setClass('blitzortung');
        $oCron->setFunction('blitzortungCron');
        $oCron->setEnable(1);
        $oCron->setSchedule('*/5 * * * *');
        $oCron->setTimeout('2');
        $oCron->save();
      }
    } else {
      $oCron = cron::byClassAndFunction(__CLASS__, 'blitzortungCron');
      if (is_object($oCron)) {
        $oCron->remove();
      }
    }
  }

  public static function getUTCoffset($_city) {
    $dtz = new DateTimeZone($_city);
    $timeCity = new DateTime('now', $dtz);
    return ($dtz->getOffset($timeCity));
  }

  public static function blitzortungCron() {
    foreach (eqLogic::byType('blitzortung', true) as $eqLogic) {
      if ($eqLogic->getIsEnable()) {
        $json = $eqLogic->getConfiguration("json_impacts");
        $LastImpactRetention = $eqLogic->getConfiguration("cfg_LastImpactRetention", 1);

        log::add('blitzortung', 'info', '- [Start] Nettoyage des enregistrements de '.$eqLogic->getName());
        log::add('blitzortung', 'info', '| Durée de conservation : ' . $LastImpactRetention . ' h');

        $arr = json_decode($json, true);
        $count_start = count($arr);
        $ts_limit = time() + self::getUTCoffset('Europe/Paris') - 3600 * $LastImpactRetention; // Heure actuelle moins le délais de rétention

        log::add('blitzortung', 'debug', '| TS LIMIT : ' . $ts_limit);

        log::add('blitzortung', 'debug', '| Impacts enregistrés : ' . $json);
        log::add('blitzortung', 'debug', '| Nombre d\'enregistrement  : ' . $count_start);

        $new_arr = array();
        foreach ($arr as $key => $value) {
          if ($value["ts"] < $ts_limit) {
            log::add('blitzortung', 'debug', '| ' . $value["ts"] . ' < ' . $ts_limit . ' removing entry ' . $key);
          } else {
            $new_arr[] = $value;
          }
        }

        $count_end = count($new_arr);
        log::add('blitzortung', 'debug', '| Impacts enregistrés : ' . $json);
        log::add('blitzortung', 'debug', '| Nombre d\'enregistrement  : ' . $count_end);

        $delete_record = $count_start - $count_end;
        log::add('blitzortung', 'info', '| Suppression de ' . $delete_record . ' enregistrements');
        log::add('blitzortung', 'info', '- [End] Nettoyage des enregistrements de '.$eqLogic->getName());

        $json = json_encode($new_arr);
        $eqLogic->setConfiguration("json_impacts", $json);
        $eqLogic->checkAndUpdateCmd('counter', $count_end);
        $eqLogic->save();

        $eqLogic->refreshWidget();
      }
    }
  }

  public static function getFreePort() {
    $freePortFound = false;
    while (!$freePortFound) {
      $port = mt_rand(50000, 65000);
      exec('sudo fuser ' . $port . '/tcp', $out, $return);
      if ($return == 1) {
        $freePortFound = true;
      }
    }
    config::save('socketport', $port, 'blitzortung');
    return $port;
  }

  public function CreateCmd($_eqlogic, $_name, $_template, $_histo, $_historound, $_generictype, $_type, $_subtype, $_unite, $_visible) {
    $info = $this->getCmd(null, $_eqlogic);
    if (!is_object($info)) {
      $info = new blitzortungCmd();
      $info->setName(__($_name, __FILE__));
      if (!empty($_template)) {
        $info->setTemplate('dashboard', $_template);
      }
      if (!empty($_histo)) {
        $info->setIsHistorized($_histo);
      }
      if (!empty($_historound)) {
        $info->setConfiguration('historizeRound', $_historound);
      }
      if (!empty($_generictype)) {
        $info->setGeneric_type($_generictype);
      }
      $info->setEqLogic_id($this->getId());
      $info->setLogicalId($_eqlogic);
      if (!empty($_type)) {
        $info->setType($_type);
      }
      if (!empty($_subtype)) {
        $info->setSubType($_subtype);
      }
      if (!empty($_unite)) {
        $info->setUnite($_unite);
      }
      $info->setIsVisible($_visible);
      $info->save();
    }
  }


  /*     * *********************Méthodes d'instance************************* */

  // Fonction exécutée automatiquement avant la création de l'équipement
  public function preInsert() {
  }

  // Fonction exécutée automatiquement après la création de l'équipement
  public function postInsert() {
  }

  // Fonction exécutée automatiquement avant la mise à jour de l'équipement
  public function preUpdate() {
  }

  // Fonction exécutée automatiquement après la mise à jour de l'équipement
  public function postUpdate() {
  }

  // Fonction exécutée automatiquement avant la sauvegarde (création ou mise à jour) de l'équipement
  public function preSave() {
  }

  // Fonction exécutée automatiquement après la sauvegarde (création ou mise à jour) de l'équipement
  public function postSave() {
    $this->CreateCmd('refresh', 'Rafraichir', '', '0', '3', '', 'action', 'other', '', '1');
    $this->CreateCmd('lastlat', 'Dernière latitude', '', '0', '3', '', 'info', 'string', '', '1');
    $this->CreateCmd('lastlon', 'Dernière longitude', '', '0', '3', '', 'info', 'string', '', '1');
    $this->CreateCmd('lastdistance', 'Dernière distance', '', '1', '3', '', 'info', 'numeric', 'km', '1');
    $this->CreateCmd('counter', 'Compteur d\'impacts', '', '0', '3', '', 'info', 'numeric', '', '1');
  }

  // Fonction exécutée automatiquement avant la suppression de l'équipement
  public function preRemove() {
  }

  // Fonction exécutée automatiquement après la suppression de l'équipement
  public function postRemove() {
  }

  public static function deamon_info() {
    $return = array();
    $return['log'] = __CLASS__;
    $return['state'] = 'nok';
    $pid_file = jeedom::getTmpFolder(__CLASS__) . '/deamon.pid';
    if (file_exists($pid_file)) {
      if (@posix_getsid(trim(file_get_contents($pid_file)))) {
        $return['state'] = 'ok';
      } else {
        shell_exec(system::getCmdSudo() . 'rm -rf ' . $pid_file . ' 2>&1 > /dev/null');
      }
    }
    $return['launchable'] = 'ok';

    /*
    $latitude = $this->getConfiguration('cfg_latitude', '');
    $longitude = $this->getConfiguration('cfg_longitude', '');
    
    $latitude = ($latitude == '')?config::bykey('info::latitude'):$latitude;
    $longitude = ($longitude == '')?config::bykey('info::longitude'):$longitude;

    if ($latitude == '') {
      $return['launchable'] = 'nok';
      $return['launchable_message'] = __('La latitude n\'est pas configurée', __FILE__);
    } elseif ($longitude == '') {
      $return['launchable'] = 'nok';
      $return['launchable_message'] = __('La longitude n\'est pas configurée', __FILE__);
    }
    */

    return $return;
  }

  public static function deamon_start() {
    self::deamon_stop();
    self::getFreePort();
    $deamon_info = self::deamon_info();
    if ($deamon_info['launchable'] != 'ok') {
      throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
    }

    /*
    $latitude = $this->getConfiguration('cfg_latitude', '');
    $longitude = $this->getConfiguration('cfg_longitude', '');
    
    $latitude = ($latitude == '')?config::bykey('info::latitude'):$latitude;
    $longitude = ($longitude == '')?config::bykey('info::longitude'):$longitude;
    
    log::add(__CLASS__, 'info', 'GPS : '.$latitude.' / '. $longitude);
    */

    $path = realpath(dirname(__FILE__) . '/../../resources/blitzortungd'); // répertoire du démon
    $cmd = 'python3 ' . $path . '/blitzortungd.py'; // nom du démon
    $cmd .= ' --loglevel ' . log::convertLogLevel(log::getLogLevel(__CLASS__));
    $cmd .= ' --socketport ' . config::byKey('socketport', __CLASS__); // port par défaut défini via la fonction getFreePort()
    $cmd .= ' --callback ' . network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/blitzortung/core/php/jeeblitzortung.php'; // chemin de la callback url à modifier (voir ci-dessous)
    //$cmd .= ' --latitude "' . $latitude .'"';
    //$cmd .= ' --longitude "' . $longitude .'"';
    $cmd .= ' --apikey ' . jeedom::getApiKey(__CLASS__); // l'apikey pour authentifier les échanges suivants
    $cmd .= ' --pid ' . jeedom::getTmpFolder(__CLASS__) . '/deamon.pid'; // chemin vers le pid file
    log::add(__CLASS__, 'info', 'Lancement démon');
    $result = exec($cmd . ' >> ' . log::getPathToLog('blitzortungd') . ' 2>&1 &'); // nom du log pour le démon
    $i = 0;
    while ($i < 20) {
      $deamon_info = self::deamon_info();
      if ($deamon_info['state'] == 'ok') {
        break;
      }
      sleep(1);
      $i++;
    }
    if ($i >= 30) {
      log::add(__CLASS__, 'error', __('Impossible de lancer le démon, vérifiez le log', __FILE__), 'unableStartDeamon');
      return false;
    }
    message::removeAll(__CLASS__, 'unableStartDeamon');
    return true;
  }

  public static function deamon_stop() {
    $pid_file = jeedom::getTmpFolder(__CLASS__) . '/deamon.pid';
    if (file_exists($pid_file)) {
      $pid = intval(trim(file_get_contents($pid_file)));
      system::kill($pid);
    }
    system::kill('blitzortungd.py'); // nom du démon
    sleep(1);
  }

  /*
  * Permet de crypter/décrypter automatiquement des champs de configuration des équipements
  * Exemple avec le champ "Mot de passe" (password)
  public function decrypt() {
    $this->setConfiguration('password', utils::decrypt($this->getConfiguration('password')));
  }
  public function encrypt() {
    $this->setConfiguration('password', utils::encrypt($this->getConfiguration('password')));
  }
  */

  /*
  * Permet de modifier l'affichage du widget (également utilisable par les commandes)
  */
  public function toHtml($_version = 'dashboard') {
    if ($this->getConfiguration('usePluginTemplate') != 1) {
      return parent::toHtml($_version);
    }

    $replace = $this->preToHtml($_version); // initialise les tag standards : #id#, #name# ...

    if (!is_array($replace)) {
      return $replace;
    }

    $version = jeedom::versionAlias($_version);

    $eqLogicName = $this->getName();
    log::add('blitzortung', 'debug', '[template] Affichage du template pour ' . $eqLogicName . ' [START]');

    $json = $this->getConfiguration("json_impacts");
    $rayon = $this->getConfiguration('cfg_rayon', 100);
    $LastImpactRetention = $this->getConfiguration("cfg_LastImpactRetention", 1);

    $arr = json_decode($json, true);

    foreach ($arr as $key => $value) {
      $ts_mn = time() + self::getUTCoffset('Europe/Paris') - $value["ts"];
      //$replace['#data#'] .= '[' . $ts_mn . ',' . $value["distance"] . ',' . '"A"' . ',' . 'color:"43C3FF"' . ']' . ',';
      $replace['#data#'] .= '[' . $ts_mn . ',' . $value["distance"] . ']' . ',';
    }
    //log::add('blitzortung', 'info', $replace['#data#']);

    $replace['#data#'] = substr($replace['#data#'], 0, -1);
    $replace['#rayon#'] = $rayon;
    $replace['#retention#'] = $LastImpactRetention;
    //$replace['#counter#'] = $counter;
    //$replace['#lastdistance#'] = $this->getCmd(null, 'lastdistance')->execCmd();

   
    $cmd = $this->getCmd('info', 'counter');
    $replace['#stateCounter#'] = $cmd->execCmd();
    $replace['#cmdIdCounter#'] = $cmd->getId();

    $cmd = $this->getCmd('info', 'lastdistance');
    $replace['#stateDistance#'] = $cmd->execCmd();
    $replace['#cmdIdDistance#'] = $cmd->getId();
    
   
    $getTemplate = getTemplate('core', $version, 'blitzortung.template', __CLASS__); // on récupère le template du plugin.
    $template_replace = template_replace($replace, $getTemplate); // on remplace les tags
    $postToHtml = $this->postToHtml($_version, $template_replace); // on met en cache le widget, si la config de l'user le permet.  
    log::add('blitzortung', 'debug', '[template] Affichage du template pour ' . $eqLogicName . ' [END]');
    return $postToHtml; // renvoie le code du template.

  }


  /*
  * Permet de déclencher une action avant modification d'une variable de configuration du plugin
  * Exemple avec la variable "param3"
  public static function preConfig_param3( $value ) {
    // do some checks or modify on $value
    return $value;
  }
  */

  /*
  * Permet de déclencher une action après modification d'une variable de configuration du plugin
  * Exemple avec la variable "param3"
  public static function postConfig_param3($value) {
    // no return value
  }
  */

  /*     * **********************Getteur Setteur*************************** */
}

class blitzortungCmd extends cmd {
  /*     * *************************Attributs****************************** */

  /*
  public static $_widgetPossibility = array();
  */

  /*     * ***********************Methode static*************************** */


  /*     * *********************Methode d'instance************************* */

  /*
  * Permet d'empêcher la suppression des commandes même si elles ne sont pas dans la nouvelle configuration de l'équipement envoyé en JS
  public function dontRemoveCmd() {
    return true;
  }
  */

  // Exécution d'une commande
  public function execute($_options = array()) {
    $eqLogic = $this->getEqLogic(); //récupère l'éqlogic de la commande $this
    switch ($this->getLogicalId()) { //vérifie le logicalid de la commande      
      case 'refresh': // LogicalId de la commande rafraîchir que l’on a créé dans la méthode Postsave
        $eqLogic->blitzortungCron();
        break;
      default:
        log::add('blitzortung', 'debug', 'Erreur durant le raffraichissement');
        break;
    }
  }

  /*     * **********************Getteur Setteur*************************** */
}