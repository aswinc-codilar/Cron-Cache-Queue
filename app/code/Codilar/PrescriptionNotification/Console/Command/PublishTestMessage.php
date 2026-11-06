<?php
declare(strict_types=1);

namespace Codilar\PrescriptionNotification\Console\Command;

use Codilar\PrescriptionNotification\Observer\PublishPrescriptionUploaded;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Publishes a message manually — for testing, or re-sending a "failed"/"pending" notification.
 */
class PublishTestMessage extends Command
{
    public function __construct(
        private readonly PublisherInterface $publisher,
        private readonly Json $json
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('codilar:prescription:publish')
            ->setDescription('Publish a prescription-uploaded message to the queue')
            ->addArgument('customer_id', InputArgument::REQUIRED)
            ->addArgument('order_id', InputArgument::REQUIRED)
            ->addArgument('prescription_file', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $payload = $this->json->serialize([
            'customer_id' => (int)$input->getArgument('customer_id'),
            'order_id' => (int)$input->getArgument('order_id'),
            'prescription_file' => (string)$input->getArgument('prescription_file'),
        ]);
        $this->publisher->publish(PublishPrescriptionUploaded::TOPIC, $payload);
        $output->writeln('<info>Published:</info> ' . $payload);
        return Command::SUCCESS;
    }
}
