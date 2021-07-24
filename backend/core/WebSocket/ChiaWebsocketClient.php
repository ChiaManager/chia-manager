<?php
    namespace ChiaMgmt\WebSocket;
    use ChiaMgmt\Logging\Logging_Api;
    use Amp\Delayed;
    use Amp\Websocket\Client\Connection;
    use Amp\Websocket\Client\Handshake;
    use Amp\Websocket\Message;
    use React\Promise\Deferred;
    use React\Promise\RejectedPromise;
    use function Amp\Websocket\Client\connect;
    require __DIR__ . '/../../../vendor/autoload.php';

    class ChiaWebSocketClient{
      private $ini;

      public function __construct(){
        $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini');
      }

      public function testConnection(){
        if (is_resource(@fsockopen("localhost", $this->ini["socket_local_port"]))){
          $result = $this->sendToWSS("wssonlinestatus", array("command" => "onlineStatus"));

          if($result["status"] == 0) return $result;
          else return array("status" => 1, "message" => "Could not connect to Websocket Server.");
        }else{
          return array("status" => 1, "message" => "Could not connect to Websocket Server.");
        }
      }

      public function sendToWSS(string $socketaction, array $data){
        try{
          $returndata = new \Amp\Deferred;
          $data = $this->buildCompleteRequest($socketaction, $data);


          \Amp\Loop::run(function () use ($returndata, $data) {
            $handshake = (new Handshake("ws://localhost:{$this->ini['socket_local_port']}"))
            ->withHeader('Origin', 'https://monitoring.edtmair.at'); //TODO Implement instance domain from globals

            $connection = yield connect($handshake);
            yield $connection->send(json_encode($data));

            while ($message = yield $connection->receive()) {
              $returnmessage = json_decode(yield $message->buffer(), true);
              $returndata->resolve($returnmessage);
              break;
            }

            \Amp\Loop::stop();
          });
          $promise = \Amp\Promise\wait($returndata->promise());
          if(is_null(\Amp\Promise\wait($returndata->promise()))){
              $promise = array();
          }
          return $promise;
        }catch(Exception $e){
          // TODO: Implement correct error codes
          return array("status" => 1, "message" => "An error occured.");
        }
      }

      private function buildCompleteRequest(string $socketaction, array $data){
        $all_data["node"]["nodeinfo"]["hostname"] = "localhost";
        $all_data["node"]["socketaction"] = $socketaction;

        $all_data["request"]["logindata"]["authhash"] = $this->ini["backend_client_auth_hash"];
        $all_data["request"]["data"] = $data;
        $all_data["request"]["backendInfo"]["namespace"] = "";
        $all_data["request"]["backendInfo"]["class"] = "";
        $all_data["request"]["backendInfo"]["method"] = "";

        return $all_data;
      }
    }
?>
