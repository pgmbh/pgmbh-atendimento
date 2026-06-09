<?php

namespace App\Service;

use App\Entity\Attendant;
use App\Entity\ServiceHistory;
use Doctrine\ORM\EntityManagerInterface;

class ActivityReportService
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public function buildAttendantReport(
        int $attendantId,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        ?int $projectId = null
    ): array {
        $attendant = $this->entityManager->getRepository(Attendant::class)->find($attendantId);
        if (!$attendant) {
            throw new \InvalidArgumentException('Atendente não encontrado.');
        }

        $qb = $this->entityManager->getRepository(ServiceHistory::class)
            ->createQueryBuilder('sh')
            ->join('sh.service', 's')
            ->where(
                // Histórico diretamente atribuído ao atendente (via Evoluir/updateStatus)
                // OU histórico de ticket atribuído ao atendente como responsável principal
                // OU histórico de ticket atribuído ao atendente via service_attendant
                'sh.responsible = :attendant
                 OR (s.reponsible = :attendant AND sh.responsible IS NULL)
                 OR (sh.responsible IS NULL AND EXISTS (
                     SELECT sa FROM App\Entity\ServiceAttendant sa
                     WHERE sa.service = s AND sa.attendant = :attendant
                 ))'
            )
            ->andWhere('sh.comment IS NOT NULL')
            ->andWhere('sh.comment != :empty')
            ->andWhere('sh.date_history BETWEEN :from AND :to')
            ->setParameter('attendant', $attendant)
            ->setParameter('empty', '')
            ->setParameter('from', \DateTime::createFromImmutable($from))
            ->setParameter('to', \DateTime::createFromImmutable($to))
            ->orderBy('sh.date_history', 'ASC');

        if ($projectId) {
            $qb->andWhere('s.project = :project')->setParameter('project', $projectId);
        }

        $histories = $qb->getQuery()->getResult();

        $attendantName = $attendant->getName();
        $activities = array_map(function (ServiceHistory $h) use ($attendantName) {
            $service = $h->getService();
            $description = $h->getComment() ?? '';
            // O nome do atendente já aparece no cabeçalho do relatório; remove o
            // prefixo "Atendente: {nome} - " gravado no comentário para não repeti-lo.
            $prefix = 'Atendente: ' . $attendantName . ' - ';
            if (str_starts_with($description, $prefix)) {
                $description = substr($description, strlen($prefix));
            }
            return [
                'date' => $h->getDateHistory()?->format('Y-m-d'),
                'service_id' => $service?->getId(),
                'service_title' => $service?->getTitle(),
                'service_type' => $service?->getServiceType()?->getName() ?? 'Não definido',
                'category' => $service?->getCategory()?->getName() ?? 'Não definida',
                'description' => $description,
                'status' => $service?->getStatus(),
            ];
        }, $histories);

        $byType = [];
        foreach ($activities as $activity) {
            $type = $activity['service_type'];
            $byType[$type] = ($byType[$type] ?? 0) + 1;
        }

        $byTypeArray = array_map(
            fn($type, $count) => ['type' => $type, 'count' => $count],
            array_keys($byType),
            array_values($byType)
        );

        $project = null;
        if ($projectId) {
            $p = $this->entityManager->getRepository(\App\Entity\Project::class)->find($projectId);
            if ($p) {
                $project = ['id' => $p->getId(), 'name' => $p->getName(), 'acronym' => $p->getAcronym()];
            }
        }

        return [
            'attendant' => [
                'id' => $attendant->getId(),
                'name' => $attendant->getName(),
                'function' => $attendant->getFunction(),
                'sector' => [
                    'id' => $attendant->getSector()?->getId(),
                    'name' => $attendant->getSector()?->getName(),
                ],
            ],
            'period' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
            ],
            'project' => $project,
            'activities' => $activities,
            'summary' => [
                'total_activities' => count($activities),
                'by_type' => $byTypeArray,
            ],
        ];
    }
}
