<?php

namespace App\Command;

use App\Entity\Service;
use App\Entity\ServiceHistory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:revert-roadmap',
    description: 'Reverte a importação do SUPP Roadmap, removendo os tickets e históricos criados pelo app:import-roadmap',
)]
class RevertRoadmapCommand extends Command
{
    private const MARKER = '[ClickUp #';
    private const BATCH_SIZE = 50;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Executa sem pedir confirmação'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Reversão da importação SUPP Roadmap');

        // Conta quantos serviços serão removidos
        $count = (int) $this->entityManager
            ->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(Service::class, 's')
            ->where('s.description LIKE :marker')
            ->setParameter('marker', '%' . self::MARKER . '%')
            ->getQuery()
            ->getSingleScalarResult();

        if ($count === 0) {
            $io->info('Nenhum ticket importado do ClickUp encontrado. Nada a fazer.');
            return Command::SUCCESS;
        }

        $io->warning(sprintf(
            '%d ticket(s) importados do ClickUp serão removidos junto com seus históricos.',
            $count
        ));

        if (!$input->getOption('force')) {
            if (!$io->confirm('Deseja continuar?', false)) {
                $io->info('Operação cancelada.');
                return Command::SUCCESS;
            }
        }

        $deleted  = 0;
        $offset   = 0;

        $io->progressStart($count);

        while (true) {
            $services = $this->entityManager
                ->createQueryBuilder()
                ->select('s')
                ->from(Service::class, 's')
                ->where('s.description LIKE :marker')
                ->setParameter('marker', '%' . self::MARKER . '%')
                ->setMaxResults(self::BATCH_SIZE)
                ->getQuery()
                ->getResult();

            if (empty($services)) {
                break;
            }

            foreach ($services as $service) {
                // Remove históricos manualmente (orphanRemoval age no flush,
                // mas remove explicitamente para garantir em qualquer configuração)
                $histories = $this->entityManager
                    ->createQueryBuilder()
                    ->select('h')
                    ->from(ServiceHistory::class, 'h')
                    ->where('h.service = :service')
                    ->setParameter('service', $service)
                    ->getQuery()
                    ->getResult();

                foreach ($histories as $history) {
                    $this->entityManager->remove($history);
                }

                $this->entityManager->remove($service);
                $deleted++;
                $io->progressAdvance();
            }

            $this->entityManager->flush();
            $this->entityManager->clear();
        }

        $io->progressFinish();
        $io->success(sprintf('%d ticket(s) e seus históricos foram removidos com sucesso.', $deleted));

        return Command::SUCCESS;
    }
}
