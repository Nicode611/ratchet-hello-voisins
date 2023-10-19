<?php



namespace MyApp;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

// $port = getenv('PORT'); // Port par défaut si non spécifié

$logFilePath = 'logs.html';
$customLogMessage = "Serveur Ratchet démarré avec succès le " . date('Y-m-d H:i:s');
echo $customLogMessage . $_SERVER['PORT'] . "\n";

echo 'voici le port' . 5430 ;

class Chat implements MessageComponentInterface {
    protected $clients;
    protected $usernames;
    protected $userCounts = [];


    public function __construct() {
        $this->clients = new \SplObjectStorage;
        $this->usernames = [];
    }

    

    public function onOpen(ConnectionInterface $conn) {

        try { parse_str($conn->httpRequest->getUri()->getQuery(), $queryParameters);
        // Store the new connection to send messages to later
        $this->clients->attach($conn);
        
        $username = $queryParameters['username'] ?? null;
        if ($username) {
            $this->usernames[$conn->resourceId] = $username;
            $this->userCounts[$username] = ($this->userCounts[$username] ?? 0) + 1;
            $this->sendUserCountToClient($username, $this->userCounts[$username]);

            echo "New connection! ({$conn->resourceId}) - Username: $username\n";
        }

        } catch (\Exception $e) {
            echo("WebSocket erreur de connection - Username: $username, Error Message: {$e->getMessage()}"). "\n";
        }

    }

    public function onMessage(ConnectionInterface $from, $msg) {
        // Lorsqu'un utilisateur envoie un message, vérifiez si d'autres utilisateurs ont le même nom
        $fromUsername = $this->usernames[$from->resourceId] ?? null;
        
        foreach ($this->clients as $client) {
            if ($from !== $client) {
                $clientUsername = $this->usernames[$client->resourceId] ?? null;
                
                // Les noms d'utilisateur sont les mêmes,
                if ($fromUsername && $clientUsername && $fromUsername === $clientUsername) {
                    $client->send($msg);
                }
            }
        }
    }
    

    public function onClose(ConnectionInterface $conn) {

        $username = $this->usernames[$conn->resourceId] ?? null;
    if ($username) {
        $this->userCounts[$username] = ($this->userCounts[$username] ?? 0) - 1;
        if ($this->userCounts[$username] <= 0) {
            unset($this->userCounts[$username]);
        }
        $this->sendUserCountToClient($username, $this->userCounts[$username] ?? 0);
    }
        $this->clients->detach($conn);
        echo "Connection ({$conn->resourceId}) has disconnected - Username: $username\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        function logErrorToHTML($message) {
            global $logFilePath;
            $errorLog = date('Y-m-d H:i:s') . ' - ' . $message . "<br>";
            file_put_contents($logFilePath, $errorLog, FILE_APPEND);
        }

        $username = $this->usernames[$conn->resourceId] ?? 'N/A';
        echo("WebSocket Error - Username: $username, Error Message: {$e->getMessage()}"). "\n";


        $conn->close();
    }

    private function sendUserCountToClient($username, $count) {
        // Loop through clients to find those with the same username
        foreach ($this->clients as $client) {
            if ($this->usernames[$client->resourceId] === $username) {
                $client->send(json_encode(["user_count" => $count]));
            }
        }
    }
}