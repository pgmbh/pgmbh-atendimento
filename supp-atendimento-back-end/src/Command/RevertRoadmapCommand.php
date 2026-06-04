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
    description: 'Reverte tudo que foi importado pelo RoadmapFixtures (marcadores [ClickUp #] e [Crono #])',
)]
class RevertRoadmapCommand extends Command
{
    private const MARKERS = ['[ClickUp #', '[Crono #'];
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
        $io->title('Reversão da importação RoadmapFixtures');

        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(s.id)')
            ->from(Service::class, 's');

        $orX = $qb->expr()->orX();
        foreach (self::MARKERS as $i => $marker) {
            $orX->add($qb->expr()->like('s.description', ':m' . $i));
            $qb->setParameter('m' . $i, '%' . $marker . '%');
        }

        $count = (int) $qb->where($orX)->getQuery()->getSingleScalarResult();

        if ($count === 0) {
            $io->info('Nenhum ticket importado encontrado. Nada a fazer.');
            return Command::SUCCESS;
        }

        $io->warning(sprintf(
            '%d ticket(s) importados serão removidos junto com seus históricos.',
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
            $qb2 = $this->entityManager->createQueryBuilder()
                ->select('s')->from(Service::class, 's');
            $orX2 = $qb2->expr()->orX();
            foreach (self::MARKERS as $i => $marker) {
                $orX2->add($qb2->expr()->like('s.description', ':m' . $i));
                $qb2->setParameter('m' . $i, '%' . $marker . '%');
            }
            $services = $qb2->where($orX2)->setMaxResults(self::BATCH_SIZE)->getQuery()->getResult();

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
