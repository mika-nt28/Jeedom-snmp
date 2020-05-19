<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
include_file('core', 'Trap', 'class', 'snmp2');
include_file('core', 'Result', 'class', 'snmp2');
include_file('core', 'Notify', 'class', 'snmp2');

class snmp2 extends eqLogic {
	public static function deamon_info() {
		$return = array();
		$return['log'] = 'eibd';	
		$return['launchable'] = 'ok';
		$return['state'] = 'ok';		
		foreach(eqLogic::byType('snmp2') as $Equipement){			
			if($Equipement->getIsEnable()){
				$cron = cron::byClassAndFunction('snmp2', 'pull', array('id' => $Equipement->getId()));
				if(!is_object($cron) || !$cron->running()){
					$return['state'] = 'nok';
					return $return;
				}
			}
		}
		return $return;
	}
	public static function deamon_start($_debug = false) {
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') 
			return;
		//log::remove('snmp2');
		self::deamon_stop();
		foreach(eqLogic::byType('snmp2') as $Equipement){		
			if($Equipement->getIsEnable())
				$Equipement->CreateDemon();
		}
	}
	public static function deamon_stop() {
		foreach(eqLogic::byType('snmp2') as $Equipement){
			$cron = cron::byClassAndFunction('snmp2', 'pull', array('id' => $Equipement->getId()));
			if (is_object($cron)) {
				$cron->stop();
				$cron->remove();
			}
		}
	}
	public static function pull($_options) {
		$Equipement = eqlogic::byId($_options['id']);
		if (is_object($Equipement) && $Equipement->getIsEnable()) {
			error_reporting(E_ALL | E_STRICT);
			$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
			$result = socket_connect($socket, $Equipement->getLogicalId(), 162);
			if ($result === false) {
				log::add('snmp2','debug',$Equipement->getHumanName().' Impossible de se connecter en TRAP sur l\'equipement');
				return;
			}
			while(true){
				$trap_content = '';
				if (false === ($bytes = socket_recv($socket, $trap_content, 2048, MSG_WAITALL))) {
					log::add('snmp2','debug',$Equipement->getHumanName().' socket_recv() a échoué; raison: ' . socket_strerror(socket_last_error($socket)));
					return;
				}
				$Trap = new Trap ($trap_content);
			}
			socket_close($socket);
		}


		/*
		# --- send notification
		if ($notification_methods !== false && $Trap->exception === false) {
			// load object and send trap
			$Notify = new Trap_notify ($Trap->get_trap_details (), $notification_params, $filename);
			// send
			$Notify->send_notification ();
		}
		*/
	}
	private function CreateDemon() {
		$cron =cron::byClassAndFunction('snmp2', 'pull', array('id' => $this->getId()));
		if (!is_object($cron)) {
			$cron = new cron();
			$cron->setClass('snmp2');
			$cron->setFunction('pull');
			$cron->setOption(array('id' => $this->getId()));
			$cron->setEnable(1);
			$cron->setDeamon(1);
			$cron->setSchedule('* * * * *');
			$cron->setTimeout('1');
			$cron->save();
		}
		$cron->start();
		$cron->run();
		return $cron;
	}
}
class snmp2Cmd extends cmd {

	public function execute($_options = null) {	
	}
}
?>
