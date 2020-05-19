<?php
	class Notify {
		private $allowed_methods = array();
		private $params = object;
		protected $trap_details;
		public $filename;
		public function __construct ($trap_details, $params, $filename = "/tmp/trap.txt") {
			// save filename for errors
			$this->filename = $filename;
			// save params
			$this->params = (object) $params;
			// set requested notification methods
			$this->set_allowed_methods($notification_methods);
			// save trap details
			$this->trap_details = $trap_details;
		}
		private function set_allowed_methods ($notification_methods) {
			$this->notification_methods = $notification_methods;
		}
		private function write_error ($error) {
			// we need object
			if (is_object($error))       { $out = (array) $error; }
			elseif (is_string($error))   { $out = array();  $out['error'] = $error; }
			else                         { $out = (array) $error; }

			// start file object, set file and write error
			$File = new Trap_file ($this->params);
			$File->set_file ($this->filename);
			$File->write_file_parsed ($out);
		}
		public function send_notification ($users) {
			// check if some are to receive message
			if ($users ===false) 
				return true;
			// put to notification methods
			$methods = array();
			foreach ($users as $u) {
				$permitted = false;
				// validate if hostname is permitted !
				if($u->hostnames!=="all") {
					$hostnames = explode(";", $u->hostnames);
					if(is_array($hostnames)) {
						if(in_array($this->trap_details->hostname, $hostnames)) {
							$permitted = true;
						}
					}
				}else {
					$permitted = true;
				}

				// permitted ?
				if ($permitted) {
					// to array
					$user_methods = explode(";", $u->notification_types);
					// save
					foreach ($user_methods as $m) {
						if ($m!="none" && strlen($m)>0) {
							$methods[$m][] = $u;
						}
					}
				}
			}
			// filter out blank
			$methods = array_filter($methods);
			// if set
			if (sizeof($methods)>0) {
				foreach ($methods as $k=>$m) {
					// init object
					unset($Obj);
					$Obj = new $k ((array) $this->params);
					$Obj->send ($this->trap_details, $m);
				}
			}
		}
	}
?>
