<?php

use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

class FileBasedDatabase
{
    private $dataDir;

    public function __construct(string $dataDir)
    {
        $this->dataDir = $dataDir;
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0777, true);
        }
    }

    public function set(string $key, $value): void
    {
        $handle = fopen($this->getFilePath($key), 'a');

        $lock = flock($handle, LOCK_EX);

        if ($lock === false) {
            flock($handle, LOCK_UN);
            return;
        }

        fwrite($handle, $value . PHP_EOL);
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    public function get(string $key)
    {
        $filePath = $this->getFilePath($key);
        if (file_exists($filePath)) {
            return json_decode(file_get_contents($filePath));
        }
        return null;
    }

    public function append(string $key, $value): void
    {
        $currentValue = $this->get($key);
        if ($currentValue === null) {
            // Se a chave não existir, definimos o valor fornecido
            $this->set($key, $value);
        } elseif (is_array($currentValue)) {
            // Se a chave existir e já for um array, anexamos o valor
            $currentValue[] = $value;
            $this->set($key, $currentValue);
        } else {
            // Se a chave existir, mas não for um array, não podemos anexar
            throw new \RuntimeException("Cannot append to non-array value");
        }
    }

    public function delete(string $key): void
    {
        $filePath = $this->getFilePath($key);
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    private function getFilePath(string $key): string
    {
        return $this->dataDir . '/' . $key . '.txt';
    }
}

$server = new Server("0.0.0.0", 9501);

$database = new FileBasedDatabase(__DIR__ . '/data');

$server->on("request", function (Request $request, Response $response) use ($database) {
    $path = $request->server['request_uri'];
    $method = $request->server['request_method'];
    $params = $request->get ?? [];

    switch ($path) {
        case '/set':
            var_dump($params['value']);
            if ($method === 'POST' && isset($params['key']) && isset($params['value'])) {
                $database->set($params['key'], $params['value']);
                $response->end("Key '{$params['key']}' set successfully.");
            } else {
                $response->status(400);
                $response->end("Invalid request.");
            }
            break;

        case '/get':
            if ($method === 'GET' && isset($params['key'])) {
                $value = $database->get($params['key']);
                $response->end(json_encode($value));
            } else {
                $response->status(400);
                $response->end("Invalid request.");
            }
            break;

        case '/append':
            if ($method === 'POST' && isset($params['key']) && isset($params['value'])) {
                $database->append($params['key'], $params['value']);
                $response->end("Value appended to key '{$params['key']}' successfully.");
            } else {
                $response->status(400);
                $response->end("Invalid request.");
            }
            break;

        case '/delete':
            if ($method === 'POST' && isset($params['key'])) {
                $database->delete($params['key']);
                $response->end("Key '{$params['key']}' deleted successfully.");
            } else {
                $response->status(400);
                $response->end("Invalid request.");
            }
            break;

        default:
            $response->status(404);
            $response->end("Not found.");
            break;
    }
});

$server->start();
