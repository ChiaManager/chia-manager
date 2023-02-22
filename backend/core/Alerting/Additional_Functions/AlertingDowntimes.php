<?php
  namespace ChiaMgmt\Alerting\Additional_Functions;
  use React\Promise;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;

  class AlertingDowntimes{
    /**
     * Holds an instance to the Logging Class.
     * @var Logging_Api
     */
    private $logging_api;

    /**
     * Initialises the needed and above stated private variables.
     */
    public function __construct(object $server = NULL){
        $this->logging_api = new Logging_Api();
    }

    /**
     * Returns all services for which a downtime can be configured.
     * Function made for: Web GUI/App, API
     * @throws Exception $e                   Throws an exception on db errors.
     * @param  array $data                    Default: [] (empty) - Returns all availabe configurable downtime services. Optional: { "node_id" : <int>, "monitor" : <int> }
     * @return object                         Returns a promise object: {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}}
     */
    public function getConfigurableDowntimeServices(array $data = []): object
    {
        $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
            $statement_string = "n.id = (
                SELECT nt.nodeid FROM nodetype nt WHERE nt.code >= 3 AND nt.code <= 5 AND nt.nodeid = n.id LIMIT 1
            )";
            $statement_array = [];
            if(array_key_exists("node_id", $data) && is_numeric($data["node_id"])){
            $statement_string = "n.id = ?";
            array_push($statement_array, $data["node_id"]);
            }

            if(array_key_exists("monitor", $data)){
                $statement_string .= " AND ar.monitor = ?";
                array_push($statement_array, $data["monitor"]);
            }

            $configurable_dt_services = Promise\resolve((new DB_Api())->execute("SELECT n.id, cias.id AS service_id, n.hostname, n.nodeauthhash, cias.service_type, cist.service_desc,
                                                                                (CASE WHEN cias.service_target IS NULL OR cias.service_target = '' THEN 'Total (down)time'
                                                                                    ELSE cias.service_target
                                                                                END) AS service_target, cias.service_target AS real_service_target, ar.monitor
                                                                                FROM nodes n
                                                                                LEFT JOIN chia_infra_available_services cias ON cias.id = (SELECT cias1.id
                                                                                                                                FROM chia_infra_available_services cias1
                                                                                                                                WHERE cias1.service_target = cias.service_target AND cias1.service_type = cias.service_type AND cias1.node_id = n.id       
                                                                                                                                ORDER BY cias1.service_state_first_reported DESC
                                                                                                                                LIMIT 1)
                                                                                LEFT JOIN chia_infra_service_types cist ON cist.id = cias.service_type                                                           
                                                                                LEFT JOIN alerting_rules ar on ar.id = cias.refers_to_rule_id
                                                                                WHERE $statement_string
                                                                                ORDER BY n.id ASC, cias.service_type ASC, cias.service_target ASC", $statement_array));

            $configurable_dt_services->then(function($configurable_dt_services_returned) use(&$resolve){
                $returnarray = [];
                foreach($configurable_dt_services_returned->resultRows AS $arrkey => $thisservice){
                    if(!array_key_exists($thisservice["id"], $returnarray)) $returnarray[$thisservice["id"]] = ["hostname" => $thisservice["hostname"], "nodeauthhash" => $thisservice["nodeauthhash"], "services" => []];
                    if(!array_key_exists($thisservice["service_type"], $returnarray[$thisservice["id"]]["services"])){
                        $returnarray[$thisservice["id"]]["services"][$thisservice["service_type"]] = [ "service_type_desc" => $thisservice["service_desc"] , "configurable_services" => [$thisservice["service_id"] => [ "service_id" => $thisservice["service_id"], "real_service_target" => $thisservice["real_service_target"], "service_target" => $thisservice["service_target"], "monitor" => $thisservice["monitor"]]]];
                    }else{
                        $returnarray[$thisservice["id"]]["services"][$thisservice["service_type"]]["configurable_services"][$thisservice["service_id"]] = [ "service_id" => $thisservice["service_id"], "real_service_target" => $thisservice["real_service_target"], "service_target" => $thisservice["service_target"], "monitor" => $thisservice["monitor"]];
                    } 
                }
    
                $resolve(array("status" => 0, "message" => "Successfully returned all found configurable downtime services.", "data" => $returnarray)); 
            })->otherwise(function(\Exception $e) use(&$resolve){
                $resolve($this->logging_api->getErrormessage("getConfigurableDowntimeServices", "001", $e));
            });
        };

        $canceller = function () {
            throw new \Exception('Promise cancelled');
        };
    
        return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Creates a new downtime for all or specific services of a certain Chia node (Downtimetype 0 = Node, 1 = Service).
     * Function made for: Web GUI/App, API
     * @throws Exception $e                   Throws an exception on db errors.
     * @param  array $data                    { "time_from" : <string>, "time_to" : <string>, "comment" : <string>, "created_by" : <int> }
     * @return object                         Returns a promise object: {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}}
     */
    public function setUpNewDowntime(array $data): object
    {
        $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
            $date_time_regex = "(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) ";
            if(array_key_exists("downtime_type", $data) && ($data["downtime_type"] == 0 || 
                ($data["downtime_type"] == 1 && array_key_exists("selected_services", $data) && 
                count($data["selected_services"]) > 0 && 
                array_key_exists("time_from", $data) && preg_match($date_time_regex, $data["time_from"]) &&
                array_key_exists("time_to", $data) && preg_match($date_time_regex, $data["time_to"]) &&
                array_key_exists("comment", $data) && $data["comment"])) && array_key_exists("nodeid", $data) && $data["nodeid"] > 2 &&
                array_key_exists("created_by", $data) && $data["created_by"] > 0)
            { 
                $time_from = new \DateTime($data["time_from"]);
                $time_to = new \DateTime($data["time_to"]);

                if($time_to > $time_from){
                    $insert_statement = "INSERT INTO alerting_downtimes (id, node_id, downtime_type, downtime_service_type, downtime_service_target, downtime_comment, downtime_from, downtime_to, downtime_created, downtime_created_by) ";
                    $insert_array = [];
                    if($data["downtime_type"] == 0){
                        $insert_statement .= "VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
                        $insert_array = [$data["nodeid"], $data["downtime_type"], 1, NULL, $data["comment"], $data["time_from"], $data["time_to"], $data["created_by"]];    
                    }else if($data["downtime_type"] == 1){
                        $insert_statement .= "VALUES";
                        foreach($data["selected_services"] AS $arrkey => $downtime_for_service){
                            if(array_key_exists("type_id", $downtime_for_service) && array_key_exists("data-service-target", $downtime_for_service)){
                                $insert_statement .= "(NULL, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)" . (array_key_exists($arrkey+1, $data["selected_services"]) ? "," : "");
                                array_push($insert_array, $data["nodeid"], $data["downtime_type"], $downtime_for_service["type_id"], $downtime_for_service["data-service-target"], $data["comment"], $data["time_from"], $data["time_to"], $data["created_by"]);
                            }else{
                                $resolve($this->logging_api->getErrormessage("setUpNewDowntime", "001"));
                            }
                        }
                    }else{
                        $resolve($this->logging_api->getErrormessage("setUpNewDowntime", "002"));
                    }

                    $set_up_downtime = Promise\resolve((new DB_Api())->execute($insert_statement, $insert_array));
                    $set_up_downtime->then(function($set_up_downtime_returned) use(&$resolve){
                        $found_downtimes = Promise\resolve($this->getSetupDowntimes());
                        $found_downtimes->then(function($found_downtimes_returned) use(&$resolve){
                            $resolve(array("status" => 0, "message" => "Successfully set new downtime.", "data" => (array_key_exists("data", $found_downtimes_returned) ? $found_downtimes_returned["data"] : [])));
                        });
                    })->otherwise(function(\Exception $e) use(&$resolve){
                        $resolve($this->logging_api->getErrormessage("setUpNewDowntime", "003", $e));
                    });
                }else{
                    $resolve($this->logging_api->getErrormessage("setUpNewDowntime", "004"));
                }  
            }else{
                $resolve($this->logging_api->getErrormessage("setUpNewDowntime", "005"));
            }
        };
        
        $canceller = function () {
            throw new \Exception('Promise cancelled');
        };

        return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Returns current setup downtimes. Defaultly it returns all downtimes in the past 24 hours. The range for the past can be changed (downtime_active: 0 = Past, 1 = Current, 2 = Future).
     * Function made for: Web GUI/App, API
     * @throws Exception $e                   Throws an exception on db errors.
     * @param  array $data                    Default: [] (Empty) - Returns all downtimes. Optional: { "time_past" : <int> (hours), "node_id" : <int> }
     * @return object                         Returns a promise object: {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}}
     */
    public function getSetupDowntimes(array $data = []): object
    {
        $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
            $time_past = "24";
            $where_statement = "";
            $statement_array[0] = $time_past;
            if(array_key_exists("time_past", $data) && is_numeric($data["time_past"]) && $data["time_past"] > 0){
                $time_past = $data["time_past"];
                $statement_array[0] = $time_past;
            }
    
            if(array_key_exists("node_id", $data) && is_numeric($data["node_id"]) && $data["node_id"] > 2){
                $where_statement .= " AND node_id = ?";
                array_push($statement_array, $data["node_id"]); 
            }

            $setup_downtimes = Promise\resolve((new DB_Api)->execute("SELECT ad.id, ad.node_id, ad.downtime_type, ad.downtime_service_type, cist.service_desc, ad.downtime_comment, ad.downtime_from, ad.downtime_to, ad.downtime_created, ad.downtime_created_by, u.name, u.lastname, u.username,
                                                                            (CASE WHEN ad.downtime_to < NOW() THEN 0
                                                                                WHEN ad.downtime_from < NOW() AND ad.downtime_to > NOW() THEN 1
                                                                                WHEN ad.downtime_from > NOW() THEN 2
                                                                                ELSE 0
                                                                            END) AS downtime_active,
                                                                            (CASE WHEN ad.downtime_service_target IS NULL OR ad.downtime_service_target = '' THEN 'total (downtime)'
                                                                                ELSE ad.downtime_service_target
                                                                            END) AS downtime_service_target
                                                                        FROM alerting_downtimes ad
                                                                        JOIN chia_infra_service_types cist ON cist.id = ad.downtime_service_type
                                                                        JOIN users u ON u.id = ad.downtime_created_by
                                                                        WHERE downtime_to >= NOW() -INTERVAL ? HOUR OR downtime_from >= NOW()
                                                                        ORDER BY downtime_active", 
                                                                        $statement_array));

            $setup_downtimes->then(function($setup_downtimes_returned) use(&$resolve){
                $returnarray = [];
                foreach($setup_downtimes_returned->resultRows AS $arrkey => $this_downtime){
                    if(!array_key_exists($this_downtime["node_id"], $returnarray)) $returnarray[$this_downtime["node_id"]] = [ 0 => [], 1 => [], 2 => [] ];
                    $returnarray[$this_downtime["node_id"]][$this_downtime["downtime_active"]][$this_downtime["id"]] = $this_downtime;
                }
    
                $resolve(array("status" => 0, "message" => "Successfully returnded found setup downtimes.", "data" => $returnarray));   
            })->otherwise(function(\Exception $e) use(&$resolve){
                $resolve($this->logging_api->getErrormessage("getSetupDowntimes", "001", $e));
            });
        };

        $canceller = function () {
            throw new \Exception('Promise cancelled');
        };
    
        return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Edits the information (Comment, start date, end date) of one or more downtime(s).
     * Function made for: Web GUI/App, API
     * @param  array $data                    { "ids_to_edit" : [<dt_id>(int), <dt_id>(int), ...], "edit_downtime_comment" : <string|null>, "edit_downtime_from" : <string|null>, "edit_downtime_to" : <string|null>, "remove_downtimes" : <boolean|null> }
     * @return object                         Returns a promise object: {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}}
     */
    public function editDowntimes(array $data): object
    {
        $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
            $date_time_regex = "(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) ";
            if(array_key_exists("ids_to_edit", $data) && count($data["ids_to_edit"]) > 0 && 
                ((array_key_exists("edit_downtime_comment", $data) && trim($data["edit_downtime_comment"]) != "") ||
                (array_key_exists("edit_downtime_from", $data) && preg_match($date_time_regex, $data["edit_downtime_from"])) || 
                (array_key_exists("edit_downtime_to", $data) && preg_match($date_time_regex, $data["edit_downtime_to"])) ||
                (array_key_exists("remove_downtimes", $data) && $data["remove_downtimes"])
            )){
                if($data["edit_downtime_from"] != "" && $data["edit_downtime_to"] != ""){
                    $edit_downtime_from = new \DateTime($data["edit_downtime_from"]);
                    $edit_downtime_to = new \DateTime($data["edit_downtime_to"]);
                    
                    if($edit_downtime_from > $edit_downtime_to){
                        //TODO Implement correct status code
                        $resolve(array("status" => 1, "message" => "Date 'from' can't be newer than date 'to'."));
                    }
                }

                if($data["remove_downtimes"]) $update_statement = "DELETE FROM alerting_downtimes";
                else $update_statement = "UPDATE alerting_downtimes SET ";
                $update_array = [];
                if(!$data["remove_downtimes"] && trim($data["edit_downtime_comment"]) != ""){
                    $update_statement .= "downtime_comment = ?";
                    array_push($update_array, trim($data["edit_downtime_comment"]));
                }
                if(!$data["remove_downtimes"] && preg_match($date_time_regex, $data["edit_downtime_from"])){
                    if(count($update_array) > 0) $update_statement .= ", ";
                    $update_statement .= "downtime_from = ?";
                    array_push($update_array, trim($data["edit_downtime_from"]));
                }
                if(!$data["remove_downtimes"] && preg_match($date_time_regex, $data["edit_downtime_to"])){
                    if(count($update_array) > 0) $update_statement .= ", ";
                    $update_statement .= "downtime_to = ?";
                    array_push($update_array, trim($data["edit_downtime_to"]));
                } 

                $update_statement .= " WHERE id = ?";
                foreach($data["ids_to_edit"] AS $arrkey => $downtime_id){
                    if($arrkey > 0){
                        $update_statement .= " OR id = ?";   
                    }
                    array_push($update_array, $downtime_id);
                }

                $edit_downtime = Promise\resolve((new DB_Api())->execute($update_statement, $update_array));
                $edit_downtime->then(function($edit_downtime_returned) use(&$resolve){
                    $found_downtimes = Promise\resolve($this->getSetupDowntimes());
                    $found_downtimes->then(function($found_downtimes_returned) use(&$resolve){
                        if(array_key_exists("data", $found_downtimes_returned)){
                            $resolve(array("status" => 0, "message" => "Successfully edited stated downtimes.", "data" => $found_downtimes_returned["data"]));
                        }else{
                            $resolve($found_downtimes_returned);
                        }
                    });
                })->otherwise(function(\Exception $e) use(&$resolve){
                    $resolve($this->logging_api->getErrormessage("editDowntimes", "001", $e));
                });
            }else{
                $resolve($this->logging_api->getErrormessage("editDowntimes", "002"));
            }  
        };

        $canceller = function () {
            throw new \Exception('Promise cancelled');
        };
    
        return new Promise\Promise($resolver, $canceller);
    }
  }
?>