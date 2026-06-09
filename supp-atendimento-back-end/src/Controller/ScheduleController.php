<?php

namespace App\Controller;

use App\Entity\Service;
use App\Entity\ServiceHistory;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/schedule')]
class ScheduleController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ProjectRepository $projectRepository
    ) {}

    #[Route('', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $projectId = $request->query->get('project_id');

        if (!$projectId) {
            return new JsonResponse([
                'success' => false,
                'message' => 'O campo project_id é obrigatório para montar o cronograma.'
            ], 400);
        }

        $project = $this->projectRepository->findById((int) $projectId);
        if (!$project) {
            return new JsonResponse(['success' => false, 'message' => 'Projeto não encontrado.'], 404);
        }

        $qb = $this->entityManager->getRepository(Service::class)
            ->createQueryBuilder('s')
            ->where('s.project = :project')
            ->setParameter('project', $project)
            ->orderBy('s.date_create', 'ASC');

        $status = $request->query->get('status');
        if ($status) {
            $qb->join('s.statusEntity', 'st')
               ->andWhere('st.name = :status')
               ->setParameter('status', $status);
        }

        $sectorId = $request->query->get('sector_id');
        if ($sectorId) {
            $qb->andWhere('s.sector = :sector')->setParameter('sector', (int) $sectorId);
        }

        $dateFrom = $request->query->get('date_from');
        if ($dateFrom) {
            $qb->andWhere('s.date_create >= :date_from')->setParameter('date_from', new \DateTime($dateFrom));
        }

        $dateTo = $request->query->get('date_to');
        if ($dateTo) {
            $qb->andWhere('s.date_create <= :date_to')->setParameter('date_to', new \DateTime($dateTo . ' 23:59:59'));
        }

        $attendantId = $request->query->get('attendant_id');
        if ($attendantId) {
            $qb->andWhere('s.reponsible = :attendant')->setParameter('attendant', (int) $attendantId);
        }

        $services = $qb->getQuery()->getResult();

        $items = array_map(function (Service $service) use ($project) {
            $observation = $this->getObservation($service);
            $dateConclusion = $service->getDateConclusion()?->format('Y-m-d');

            return [
                'id' => $service->getId(),
                'title' => $service->getTitle(),
                'project' => [
                    'id' => $project->getId(),
                    'name' => $project->getName(),
                    'acronym' => $project->getAcronym(),
                ],
                'responsible' => [
                    'id' => $service->getReponsible()?->getId(),
                    'name' => $service->getReponsible()?->getName(),
                ],
                'date_start' => $service->getDateCreate()?->format('Y-m-d'),
                'date_conclusion' => $dateConclusion,
                'status' => $service->getStatus(),
                'priority' => $service->getPriority(),
                'observation' => $observation,
            ];
        }, $services);

        $filters = [
            'project_id' => (int) $projectId,
            'status' => $status,
            'sector_id' => $sectorId ? (int) $sectorId : null,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];

        return new JsonResponse([
            'success' => true,
            'data' => $items,
            'meta' => [
                'total' => count($items),
                'project' => [
                    'id' => $project->getId(),
                    'name' => $project->getName(),
                    'acronym' => $project->getAcronym(),
                ],
                'filters_applied' => $filters,
            ]
        ]);
    }

    private function getObservation(Service $service): string
    {
        $lastEvolution = $this->entityManager->getRepository(ServiceHistory::class)
            ->createQueryBuilder('sh')
            ->where('sh.service = :service')
            ->andWhere('sh.type = :type')
            ->setParameter('service', $service)
            ->setParameter('type', 'EVOLUTION')
            ->orderBy('sh.date_history', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($lastEvolution) {
            return $lastEvolution->getComment() ?? '';
        }

        return mb_substr($service->getDescription() ?? '', 0, 200);
    }
}
