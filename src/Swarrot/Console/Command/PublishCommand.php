<?php

namespace Swarrot\Console\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Swarrot\Broker\MessagePublisher\PeclPackageMessagePublisher;
use Swarrot\Broker\MessagePublisher\PhpAmqpLibMessagePublisher;
use Psr\Log\LoggerInterface;
use PhpAmqpLib\Connection\AMQPConnection;
use Swarrot\Broker\Message;

class PublishCommand extends Command
{
    protected $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;

        parent::__construct();
    }

    public function configure()
    {
        $this
            ->setName('publish')
            ->setDescription('Publish a message in an exchange.')
            ->addArgument('exchange', InputArgument::REQUIRED, 'The exchange.')
            ->addArgument('routing_key', InputArgument::REQUIRED, 'The routing_key.')
            ->addArgument('vhost', InputArgument::OPTIONAL, 'In which vhost is the exchange?', '/')
            ->addOption('messages', 'm', InputOption::VALUE_REQUIRED, 'Messages to publish.', 100)
            ->addOption('provider', 'p', InputOption::VALUE_REQUIRED, 'Which provider to use? [ext|lib]', 'ext')
            ->addOption('host', 'H', InputOption::VALUE_REQUIRED, 'RabbitMQ host', '127.0.0.1')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'RabbitMQ port', 5672)
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED, 'RabbitMQ user', 'guest')
            ->addOption('password', '', InputOption::VALUE_REQUIRED, 'RabbitMQ password', 'guest')
        ;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $host = $input->getOption('host');
        $port = $input->getOption('port');
        $user = $input->getOption('user');
        $pass = $input->getOption('password');
        $vhost = $input->getArgument('vhost');
        
        $exchangeName = $input->getArgument('exchange');
        
        if ('ext' === $input->getOption('provider')) {
            $connection = new \AMQPConnection([
                'vhost' => $vhost
            ]);
            $connection->setHost($host);
            $connection->setPort($port);
            $connection->setLogin($user);
            $connection->setPassword($pass);
        
            $connection->connect();
        
            $channel = new \AMQPChannel($connection);
        
            $exchange = new \AMQPExchange($channel);
            $exchange->setName($exchangeName);
        
            $messagePublisher = new PeclPackageMessagePublisher($exchange);
        } elseif ('lib' === $input->getOption('provider')) {
            $connection = new AMQPConnection(
                    $host,
                    $port,
                    $user,
                    $pass,
                    $vhost
            );
            $messagePublisher = new PhpAmqpLibMessagePublisher(
                $connection->channel(),
                $exchangeName
            );
        }

        for ($i = 0; $i < (int) $input->getOption('messages'); $i++) {
            $this->logger->debug("Publish message #$i");

            $messagePublisher->publish(
                new Message('body', ['headers' => ['foo' => 'bar']]),
                $input->getArgument('routing_key')
            );
        }
    }
}
