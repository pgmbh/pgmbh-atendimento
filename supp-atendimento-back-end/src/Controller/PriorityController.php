<?php

namespace App\Controller;

use App\Entity\Attendant;
use App\Entity\Priority;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/priorities')]
class PriorityController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $activeParam = $request->query->get('active', 'true');

        $qb = $this->entityManager->getRepository(Priority::class)->createQueryBuilder('p');

        if ($activeParam !== 'all') {
            $qb->where('p.active = :active')->setParameter('active', $activeParam !== 'false');
        }

        $priorities = $qb->orderBy('p.weight', 'ASC')->getQuery()->getResult();

        return new JsonResponse([
            'success' => true,
            'data' => array_map(fn(Priority $p) => [
                'id'     => $p->getId(),
                'name'   => $p->getName(),
                'label'  => $p->getLabel(),
                'color'  => $p->getColor(),
                'weight' => $p->getWeight(),
                'active' => $p->isActive(),
            ], $priorities),
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $deny = $this->denyIfNotAdmin();
        if ($deny) return $deny;

        $data = json_decode($request->getContent(), true);

        if (empty($data['name']) || empty($data['label'])) {
            return new JsonResponse(['success' => false, 'message' => 'Os campos name e label são obrigatórios.'], 400);
        }

        $priority = (new Priority())
            ->setName(strtoupper(trim($data['name'])))
            ->setLabel(trim($data['label']))
            ->setColor($data['color'] ?? '#607d8b')
            ->setWeight((int) ($data['weight'] ?? 99));

        $this->entityManager->persist($priority);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Prioridade criada com sucesso.',
            'data'    => $this->serialize($priority),
        ], 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $deny = $this->denyIfNotAdmin();
        if ($deny) return $deny;

        $priority = $this->entityManager->getRepository(Priority::class)->find($id);
        if (!$priority) {
            return new JsonResponse(['success' => false, 'message' => 'Prioridade não encontrada.'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name']))   $priority->setName(strtoupper(trim($data['name'])));
        if (isset($data['label']))  $priority->setLabel(trim($data['label']));
        if (isset($data['color']))  $priority->setColor($data['color']);
        if (isset($data['weight'])) $priority->setWeight((int) $data['weight']);

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Prioridade atualizada com sucesso.',
            'data'    => $this->serialize($priority),
        ]);
    }

    #[Route('/{id}/toggle-active', methods: ['PATCH'])]
    public function toggleActive(int $id): JsonResponse
    {
        $deny = $this->denyIfNotAdmin();
        if ($deny) return $deny;

        $priority = $this->entityManager->getRepository(Priority::class)->find($id);
        if (!$priority) {
            return new JsonResponse(['success' => false, 'message' => 'Prioridade não encontrada.'], 404);
        }

        $priority->setActive(!$priority->isActive());
        $this->entityManager->flush();

        $status = $priority->isActive() ? 'ativada' : 'inativada';

        return new JsonResponse([
            'success' => true,
            'message' => "Prioridade {$status} com sucesso.",
            'data'    => ['id' => $priority->getId(), 'active' => $priority->isActive()],
        ]);
    }

    private function serialize(Priority $p): array
    {
        return [
            'id'     => $p->getId(),
            'name'   => $p->getName(),
            'label'  => $p->getLabel(),
            'color'  => $p->getColor(),
            'weight' => $p->getWeight(),
            'active' => $p->isActive(),
        ];
    }

    private function denyIfNotAdmin(): ?JsonResponse
    {
        $attendant = $this->entityManager->getRepository(Attendant::class)
            ->findOneBy(['user' => $this->getUser()]);

        if (!$attendant || $attendant->getFunction() !== 'Admin') {
            return new JsonResponse(['success' => false, 'message' => 'Acesso negado. Apenas administradores.'], 403);
        }

        return null;
    }
}
