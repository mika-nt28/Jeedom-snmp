<?php
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
include_file('core', 'traphandler', 'class', 'snmp');
include_file('core', 'Result', 'class', 'snmp');
include_file('core', 'Notify', 'class', 'snmp');

class snmp extends eqLogic {
	public static function deamon_info() {
		$return = array();
		$return['log'] = 'eibd';	
		$return['launchable'] = 'ok';
		$return['state'] = 'ok';		
		foreach(eqLogic::byType('snmp') as $Equipement){			
			if($Equipement->getIsEnable() && count($Equipement->getCmd()) > 0){
				$cron = cron::byClassAndFunction('snmp', 'pull', array('id' => $Equipement->getId()));
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
		//log::remove('snmp');
		self::deamon_stop();
		foreach(eqLogic::byType('snmp') as $Equipement){		
			if($Equipement->getIsEnable() && count($Equipement->getCmd()) > 0)
				$Equipement->CreateDemon();
		}
	}
	public static function deamon_stop() {
		foreach(eqLogic::byType('snmp') as $Equipement){
			$cron = cron::byClassAndFunction('snmp', 'pull', array('id' => $Equipement->getId()));
			if (is_object($cron)) {
				$cron->stop();
				$cron->remove();
			}
		}
	}
	public static function pull($_options) {
		/*while($f = fgets(STDIN)){
			$trap_content[] = $f;
		}
		# --- load traphandler and process provided trap
		$Trap = new Trap ($trap_content);


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
		$cron =cron::byClassAndFunction('snmp', 'pull', array('id' => $this->getId()));
		if (!is_object($cron)) {
			$cron = new cron();
			$cron->setClass('snmp');
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
class snmpCmd extends cmd {

	public function execute($_options = null) {	
	}
}
?>
