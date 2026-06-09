<?php

namespace App\Command;

use App\Entity\Service;
use App\Entity\ServiceHistory;
use App\Entity\Status;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use DateTime;
use DateTimeZone;

#[AsCommand(
    name: 'app:auto-conclude-tickets',
    description: 'Automatically conclude tickets that have been RESOLVED for more than 5 days',
)]
class AutoConcludeTicketsCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private DateTimeZone $timezone;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->timezone = new DateTimeZone('America/Sao_Paulo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Data limite: 5 dias atrás
        $limitDate = new DateTime('now', $this->timezone);
        $limitDate->modify('-5 days');
        
        $io->info('Procurando tickets RESOLVED há mais de 5 dias...');
        $io->info('Data limite: ' . $limitDate->format('Y-m-d H:i:s'));

        // Buscar tickets RESOLVED há mais de 5 dias
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $tickets = $queryBuilder
            ->select('s')
            ->from(Service::class, 's')
            ->join('s.statusEntity', 'st')
            ->where('st.name = :statusName')
            ->andWhere('s.date_update <= :limitDate')
            ->setParameter('statusName', 'RESOLVED')
            ->setParameter('limitDate', $limitDate)
            ->getQuery()
            ->getResult();

        $concludedCount = 0;

        foreach ($tickets as $ticket) {
            try {
                // Atualizar status para CONCLUDED
                $concludedStatus = $this->entityManager->getRepository(Status::class)->findOneBy(['name' => 'CONCLUDED']);
                $ticket->setStatusEntity($concludedStatus);
                $ticket->setDateConclusion(new DateTime('now', $this->timezone));
                $ticket->setDateUpdate(new DateTime('now', $this->timezone));

                // Criar histórico da auto-conclusão
                $history = new ServiceHistory();
                $history->setService($ticket);
                $history->setStatusPrev('RESOLVED');
                $history->setStatusPost('CONCLUDED');
                $history->setComment('Ticket automaticamente concluído após 5 dias sem resposta do usuário.');
                $history->setDateHistory(new DateTime('now', $this->timezone));
                $history->setResponsible($ticket->getReponsible()); // Usar o responsável atual

                $this->entityManager->persist($history);
                
                $concludedCount++;
                $io->text("Ticket #{$ticket->getId()} - {$ticket->getTitle()} - AUTO-CONCLUÍDO");
                
            } catch (\Exception $e) {
                $io->error("Erro ao concluir ticket #{$ticket->getId()}: " . $e->getMessage());
            }
        }

        // Salvar todas as alterações
        $this->entityManager->flush();

        $io->success("Processo concluído! {$concludedCount} tickets foram automaticamente concluídos.");

        return Command::SUCCESS;
    }
}