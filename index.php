<?php

error_reporting(-1);
ini_set("display_errors", 1);
echo "CO-ROUTINE EXAMPLE. WEBSERVER" . PHP_EOL;

require_once('lib/Scheduler.php');
require_once('lib/Task.php');
require_once('lib/stackedCoroutine.php');
require_once('lib/systemCalls.php');
require_once('lib/coSocket.php');

echo "LIBS LOADED" . PHP_EOL;

function server($port) {
    echo "SERVER LISTENING ON: $port" . PHP_EOL . PHP_EOL;;

    $socket = @stream_socket_server("tcp://localhost:$port", $errNo, $errStr);
    if (!$socket) throw new Exception($errStr, $errNo);

    stream_set_blocking($socket, 0);

    $socket = new CoSocket($socket);
    while (true) {
        yield newTask(
            handleClient(yield $socket->accept())
        );
    }
}


function handleClient($socket) {
    $data = (yield $socket->read(8192));
    $output = "Received following request:\n\n$data";

    $input = explode(" ", $data);
    if (empty($input[1])) {
        $input[1] = "index.html";
    }
    $input = $input[1];
    $fileinfo = pathinfo($input);
    $mime = "text/html";

    if (!empty($fileinfo['extension'])) {
        switch ($fileinfo['extension']) {
            case "png";
                $mime = "image/png";
                break;
            case "jpg";
                $mime = "image/jpeg";
                break;
            case "ico";
                $mime = "image/x-icon";
                break;
            default:
                $mime = "text/html";
        }
    }

    if ($input == "/") {
        $input = "/index.html";
    }

    $input = ".$input";

    if (file_exists($input) && is_readable($input)) {
        print "Serving $input\n";
        $contents = file_get_contents($input);
        $output = "HTTP/1.0 200 OK\r\nServer: APatchyServer\r\nConnection: close\r\nContent-Type: $mime\r\n\r\n$contents";
    } else {
        $contents = "The file you requested does not exist. Sorry!";
        $output = "HTTP/1.0 404 OBJECT NOT FOUND\r\nServer: APatchyServer\r\nConnection: close\r\nContent-Type: text/html\r\n\r\n$contents";
    }

    //$output = "Hello World!" . PHP_EOL;
    $msgLength = strlen($output);

/*    $response = <<<RES
HTTP/1.1 200 OK\r
Content-Type: text/plain\r
Content-Length: $msgLength\r
Connection: close\r
\r
$output
RES;*/

    yield $socket->write($output);
    yield $socket->close();
}


$scheduler = new Scheduler;
$scheduler->newTask(server(5000));
$scheduler->run();
