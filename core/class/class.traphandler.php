<?php
class Trap {
    public $message = false;
    public $message_details;
    public $details;
    public $severities =  array(
                                "emergency"     => "emergency",
                                "alert"         => "alert",
                                "critical"      => "critical",
                                "error"         => "error",
                                "warning"       => "warning",
                                "notice"        => "notice",
                                "informational" => "informational",
                                "debug"         => "debug"
                            );

    public $oid_file = false;
    public $exception = false;
    public function __construct ($message) {
        $this->message = $message;
        $this->parse_message ();
    }
    private function parse_message () {
        # init object for details
        $this->message_details = new StdClass ();
        # parse elements
        $this->set_hostname ();
        $this->set_src_ip ();
        $this->set_uptime ();
        $this->set_oid ();
        $this->set_content ();
        $this->detect_severity ();
        $this->detect_msg ();
        $this->remove_unneeded_content ();
    }
    private function set_hostname () {
        $this->message_details->hostname = trim($this->message[0]);
    }
    private function set_src_ip () {
        // remove udp
        $this->message_details->ip = str_replace(array("UDP:","[","]"), "", $this->message[1]);
        // get ip
        $this->message_details->ip = trim(strstr($this->message_details->ip, ":", true));
    }
    private function set_uptime () {
        $this->message_details->uptime = trim(strstr($this->message[2], " "));
    }
    private function set_oid () {
        // full oid
        $this->message_details->oid = trim(strstr($this->message[3], " "));
        // master oid
        $this->oid_file = strstr($this->message_details->oid, "::", true);
    }
    private function set_content () {
        // if some
        if (sizeof($this->message)>4) {
            $size = sizeof($this->message);
            // loop
            for ($m=4; $m<$size; $m++) {
                // it must match OID
                if (strpos($this->message[$m], $this->oid_file)!==false) {
                    // separate oid from content
                    $content = trim(strstr($this->message[$m], " "));
                    $oid = trim(strstr($this->message[$m], " ", true));
                    // remove index
                    $oid_tmp = explode(".", $oid);
                    array_pop($oid_tmp);
                    $oid = implode(".", $oid_tmp);
                    // remove oid file name
                    $oid = str_replace($this->oid_file."::", "", $oid);
                    // save
                    $this->message_details->content[] = "$oid => $content";
                }
            }

            # if none then save all
            if (!isset($this->message_details->content) || sizeof($this->message_details->content)==0) {
            $size = sizeof($this->message);
            for ($m=4; $m<$size; $m++) {
                // separate oid from content
                $content = trim(strstr($this->message[$m], " "));
                $oid = trim(strstr($this->message[$m], " ", true));
                // remove index
                $oid_tmp = explode(".", $oid);
                array_pop($oid_tmp);
                $oid = implode(".", $oid_tmp);
                // remove oid file name
                $oid = str_replace($this->oid_file."::", "", $oid);
                // save
                $this->message_details->content[] = "$oid => $content";
            }
            }
        }
        else {
            $this->message_details->content = "NONE";
        }
    }
    private function detect_severity () {
        // default severity
        $this->message_details->severity = "unknown";

        // loop through message, search for Severity in each content, default null
        foreach ($this->message_details->content as $c) {
            if (strpos($c, "Severity")!==false || strpos($c, "severity")!==false) {
                $tmp = explode(" => ", $c);
                $this->message_details->severity = $tmp[1];
            }
        }
    }
    private function detect_msg () {
        // default msg = oid
        $this->message_details->msg = str_replace("::","",strstr($this->message_details->oid, "::"));
        // define message array values
        $search_values = array("Msg",
                               "msg",
                               "Message",
                               "message"
                               );
        // changed flag
        $changed = false;
        // loop through message, search for Severity in each content, default null
        foreach ($search_values as $sv) {
            foreach ($this->message_details->content as $c) {
                if ( strpos($c, $sv)!==false ) {
                    $tmp = explode(" => ", $c);
                    $this->message_details->msg = $changed ? $this->message_details->msg." :: ".$tmp[1] : $tmp[1];
                    $changed = true;
                }
            }
        }
        // detect and format special messages
        $this->detect_special_messages ();
    }
    private function detect_special_messages () {
        // detect IF-MIB
        $this->detect_if ();
        // detect BRIDGE-MIB::topologyChange
        $this->detect_topologyChange ();
        // detect vlan created
        $this->detect_vtpVlanCreated ();
        // detect IKE
        $this->detect_ike ();
        // detect mteTrigger
        $this->detect_mte_trigger ();
        // auth failure IP address
        $this->detect_authfailure_ip ();
        // BGP state change
        $this->detect_bgp_change ();
    }
    private function detect_if () {
        // array of values to search
        $search_values = array("linkUp", "linkDown", "cieLinkUp", "cieLinkDown", "ipv6IfStateChange");
        // loop
        foreach ($search_values as $sv) {
            if ($this->message_details->msg == $sv) {
                foreach($this->message_details->content as $k=>$c) {
                    // explode
                    $c = explode(" => ", $c);
                    // check - first name, then status
                    if ($c[0] == "ifName") {
                        if ($sv=="linkUp" || $sv=="cieLinkUp")          { $this->message_details->msg = "Interface ".$c[1]. " changed state to Up";  }
                        elseif ($sv=="linkDown" || $sv=="cieLinkDown")  { $this->message_details->msg = "Interface ".$c[1]. " changed state to Up";  }
                        else                                            { $this->message_details->msg = "Interface ".$c[1]. " IPv6 state change"; }
                    }
                    elseif ($c[0] == "ifDescr")                         { $this->message_details->msg .= " (".$c[1].")"; }
                    elseif ($c[0] == "ifAlias")                         { $this->message_details->msg .= " ".$c[1]; }
                }
            }
        }
    }
    private function detect_vtpVlanCreated () {
         if ($this->message_details->msg == "detect_vtpVlanCreated") {
            foreach($this->message_details->content as $k=>$c) {
                // explode
                $c = explode(" => ", $c);
                // check - first name, then status
                if (strpos($c[0], "vtpVlanName")!==false)     { $this->message_details->msg .= " :: ".$c[1]." (vlan ".array_pop(explode(".", $c[0])).")"; }
           }
        }
    }
    private function detect_topologyChange () {
        // topology change
        if (in_array($this->message_details->msg, array("topologyChange", "newRoot"))) {
            foreach($this->message as $k=>$c) {
                // explode
                $c = explode(" ", $c);
                // check - first name, then status
                if (strpos($c[0], "ifName")!==false)                { $this->message_details->msg .= " (Interface ".$c[1].")"; }
                elseif (strpos($c[0], "vtpVlanIndex")!==false)      { $this->message_details->msg .= " :: vlan ".$c[1]; }
            }
        }
    }
    private function detect_ike () {
        if (in_array( $this->message_details->msg, array("cikeTunnelStart", "_cikeTunnelStop"))) {
            foreach($this->message_details->content as $k=>$c) {
                // explode
                $c = explode(" => ", $c);
                // check
                if(strpos($c[0], "cikePeerRemoteAddr")!==false)        { $this->message_details->msg .= " :: peer ".$this->hex_to_ip($c[1]);  $this->message_details->content[] = "Remote address => ".$this->hex_to_ip($c[1]); }
                elseif(strpos($c[0], "cikeTunHistTermReason")!==false) { $this->message_details->msg .= " (".$c[1].")";                       $this->message_details->content[] = "Terminate reason => ".$this->hex_to_ip($c[1]); }
            }
        }
    }
    private function detect_mte_trigger () {
         if ($this->message_details->msg == "mteTriggerFired") {
            foreach($this->message_details->content as $k=>$c) {
                // explode
                $c = explode(" => ", $c);
                // check - first name, then status
                if ($c[0]=="mteHotTrigger")     { $this->message_details->msg .= " :: ".$c[1]; }
                elseif ($c[0]=="mteHotValue")   { $this->message_details->msg .= " (".$c[1]."%)"; }
           }
        }
    }
    private function detect_authfailure_ip () {
          if ($this->message_details->msg == "authenticationFailure") {
            foreach($this->message as $k=>$c) {
                // explode
                $c = explode(" ", $c);
                // check - first name, then status
                if(strpos($c[0], "authAddr")!==false)  { $this->message_details->msg .= " :: ".$c[1]; }
           }
        }
    }
    private function detect_bgp_change () {
        // juniper
        if (in_array( $this->message_details->msg, array("bgpEstablished", "bgpBackwardTransition"))) {
            foreach($this->message_details->content as $k=>$c) {
                // explode
                $c = explode(" => ", $c);
                // check
                if(strpos($c[0], "bgpPeerState")!==false)  {
                    $this->message_details->msg .= " :: peer ".str_replace("bgpPeerState.", "", $c[0]);
                    $this->message_details->msg .= " (state ".$c[1].")";
                }
            }
        }
    }
    private function hex_to_ip ($hex) {
        // to array
        $hex = array_filter(explode(" ", trim(str_replace("\"", "", $hex))));
        foreach ($hex as $k=>$v) {
            $hex[$k] = hexdec($v);
        }
        // resukt
        return implode(".", $hex);
    }
    public function get_trap_details () {
        return $this->message_details;
    }
}

?>
