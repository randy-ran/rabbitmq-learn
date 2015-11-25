<?php
/**
 * Created by PhpStorm.
 * User: seyfer
 * Date: 11/25/15
 */
require_once __DIR__ . '/../../vendor/autoload.php';
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Class FibonacciRpcClient
 */
class FibonacciRpcClient
{
    /**
     * @var AMQPStreamConnection
     */
    private $connection;
    /**
     * @var AMQPChannel
     */
    private $channel;
    /**
     * @var string
     */
    private $callback_queue;
    /**
     * @var int
     */
    private $response;
    /**
     * @var string
     */
    private $corr_id;

    public function __construct()
    {
        $this->connection = new AMQPStreamConnection(
            'localhost', 5672, 'guest', 'guest');

        $this->channel = $this->connection->channel();

        list($this->callback_queue, ,) = $this->channel->queue_declare(
            "", false, false, true, false);

        $this->channel->basic_consume(
            $this->callback_queue, '', false, false, false, false,
            [$this, 'on_response']);
    }

    /**
     * @param AMQPMessage $rep
     */
    public function on_response(AMQPMessage $rep)
    {
        if ($rep->get('correlation_id') == $this->corr_id) {
            $this->response = $rep->body;
        }
    }

    /**
     * @param $n
     * @return int
     */
    public function call($n)
    {
        $this->response = null;
        $this->corr_id  = uniqid();

        $msg = new AMQPMessage(
            (string)$n,
            [
                'correlation_id' => $this->corr_id,
                'reply_to'       => $this->callback_queue
            ]
        );

        $this->channel->basic_publish($msg, '', 'rpc_queue');

        while (!$this->response) {
            $this->channel->wait();
        }

        return intval($this->response);
    }
}

$fibonacci_rpc = new FibonacciRpcClient();

$number = isset($argv[1]) ? $argv[1] : 30;

$response = $fibonacci_rpc->call($number);
echo " [.] Got ", $response, "\n";