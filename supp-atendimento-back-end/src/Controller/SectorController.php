<?php

namespace App\Controller;

use App\Entity\Attendant;
use App\Entity\Sector;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/sectors')]
class SectorController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[Route('', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $activeParam = $request->query->get('active', 'true');

        $qb = $this->entityManager->getRepository(Sector::class)->createQueryBuilder('s');

        if ($activeParam !== 'all') {
            $qb->where('s.active = :active')->setParameter('active', $activeParam !== 'false');
        }

        $sectors = $qb->orderBy('s.name', 'ASC')->getQuery()->getResult();

        return new JsonResponse([
            'success' => true,
            'data' => array_map(fn(Sector $s) => [
                'id'     => $s->getId(),
                'name'   => $s->getName(),
                'active' => $s->isActive(),
            ], $sectors),
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

        $sector = (new Sector())->setName(trim($data['name']));

        $this->entityManager->persist($sector);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Setor criado com sucesso.',
            'data'    => ['id' => $sector->getId(), 'name' => $sector->getName(), 'active' => $sector->isActive()],
        ], 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $deny = $this->denyIfNotAdmin();
        if ($deny) return $deny;

        $sector = $this->entityManager->getRepository(Sector::class)->find($id);
        if (!$sector) {
            return new JsonResponse(['success' => false, 'message' => 'Setor não encontrado.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['name'])) $sector->setName(trim($data['name']));

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Setor atualizado com sucesso.',
            'data'    => ['id' => $sector->getId(), 'name' => $sector->getName(), 'active' => $sector->isActive()],
        ]);
    }

    #[Route('/{id}/toggle-active', methods: ['PATCH'])]
    public function toggleActive(int $id): JsonResponse
    {
        $deny = $this->denyIfNotAdmin();
        if ($deny) return $deny;

        $sector = $this->entityManager->getRepository(Sector::class)->find($id);
        if (!$sector) {
            return new JsonResponse(['success' => false, 'message' => 'Setor não encontrado.'], 404);
        }

        $sector->setActive(!$sector->isActive());
        $this->entityManager->flush();

        $status = $sector->isActive() ? 'ativado' : 'inativado';

        return new JsonResponse([
            'success' => true,
            'message' => "Setor {$status} com sucesso.",
            'data'    => ['id' => $sector->getId(), 'active' => $sector->isActive()],
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
