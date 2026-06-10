<?php

namespace App\Controller;

use App\Entity\Attendant;
use App\Entity\ServiceType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/service-types')]
class ServiceTypeController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[Route('', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $activeParam = $request->query->get('active', 'true');

        $qb = $this->entityManager->getRepository(ServiceType::class)->createQueryBuilder('st');

        if ($activeParam !== 'all') {
            $qb->where('st.active = :active')->setParameter('active', $activeParam !== 'false');
        }

        $types = $qb->orderBy('st.name', 'ASC')->getQuery()->getResult();

        return new JsonResponse([
            'success' => true,
            'data' => array_map(fn(ServiceType $t) => [
                'id'          => $t->getId(),
                'name'        => $t->getName(),
                'description' => $t->getDescription(),
                'active'      => $t->isActive(),
            ], $types),
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $deny = $this->denyIfNotAdmin();
        if ($deny) return $deny;

        $data = json_decode($request->getContent(), true);

        if (empty($data['name'])) {
            return new JsonResponse(['success' => false, 'message' => 'O campo name é obrigatório.'], 400);
        }

        $type = (new ServiceType())
            ->setName(trim($data['name']))
            ->setDescription($data['description'] ?? null);

        $this->entityManager->persist($type);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Tipo de demanda criado com sucesso.',
            'data'    => ['id' => $type->getId(), 'name' => $type->getName(), 'description' => $type->getDescription(), 'active' => $type->isActive()],
        ], 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $deny = $this->denyIfNotAdmin();
        if ($deny) return $deny;

        $type = $this->entityManager->getRepository(ServiceType::class)->find($id);
        if (!$type) {
            return new JsonResponse(['success' => false, 'message' => 'Tipo de demanda não encontrado.'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) $type->setName(trim($data['name']));
        if (array_key_exists('description', $data)) $type->setDescription($data['description']);

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Tipo de demanda atualizado com sucesso.',
            'data'    => ['id' => $type->getId(), 'name' => $type->getName(), 'description' => $type->getDescription(), 'active' => $type->isActive()],
        ]);
    }

    #[Route('/{id}/toggle-active', methods: ['PATCH'])]
    public function toggleActive(int $id): JsonResponse
    {
        $deny = $this->denyIfNotAdmin();
        if ($deny) return $deny;

        $type = $this->entityManager->getRepository(ServiceType::class)->find($id);
        if (!$type) {
            return new JsonResponse(['success' => false, 'message' => 'Tipo de demanda não encontrado.'], 404);
        }

        $type->setActive(!$type->isActive());
        $this->entityManager->flush();

        $status = $type->isActive() ? 'ativado' : 'inativado';

        return new JsonResponse([
            'success' => true,
            'message' => "Tipo de demanda {$status} com sucesso.",
            'data'    => ['id' => $type->getId(), 'active' => $type->isActive()],
        ]);
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
