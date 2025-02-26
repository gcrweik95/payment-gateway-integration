<?php

namespace App\Command;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use App\Command\MerchantUser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'generate:token',
    description: 'Used to pregenerate JWT for testing the API endpoints',
)]
class GenerateJWTCommand extends Command
{
    private JWTTokenManagerInterface $jwtManager;

    public function __construct(JWTTokenManagerInterface $jwtManager)
    {
        parent::__construct();
        $this->jwtManager = $jwtManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $merchant = new MerchantUser('Vestiaire Collective');
        $token = $this->jwtManager->create($merchant);

        $output->writeln("Generated JWT: " . $token);

        return Command::SUCCESS;
    }
}
