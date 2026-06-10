<?php

namespace App\Controller;

use App\Entity\Attendant;
use App\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/tags')]
class TagController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $activeParam = $request->query->get('active', 'true');

        $qb = $this->entityManager->getRepository(Tag::class)->createQueryBuilder('t');

        if ($activeParam !== 'all') {
            $qb->where('t.active = :active')->setParameter('active', $activeParam !== 'false');
        }

        $tags = $qb->orderBy('t.name', 'ASC')->getQuery()->getResult();

        return new JsonResponse([
            'success' => true,
            'data' => array_map(fn(Tag $t) => [
                'id'     => $t->getId(),
                'name'   => $t->getName(),
                'color'  => $t->getColor(),
                'active' => $t->isActive(),
            ], $tags),
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

        $tag = (new Tag())
            ->setName(trim($data['name']))
            ->setColor($data['color'] ?? '#607d8b');

        $this->entityManager->persist($tag);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Etiqueta criada com sucesso.',
            'data'    => ['id' => $tag->getId(), 'name' => $tag->getName(), 'color' => $tag->getColor(), 'active' => $tag->isActive()],
        ], 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $deny = $this->denyIfNotAdmin();
        if ($deny) return $deny;

        $tag = $this->entityManager->getRepository(Tag::class)->find($id);
        if (!$tag) {
            return new JsonResponse(['success' => false, 'message' => 'Etiqueta não encontrada.'], 404);
        }

        $data = json_decode($request->getContent(), true);
        if (isset($data['name']))  $tag->setName(trim($data['name']));
        if (isset($data['color'])) $tag->setColor($data['color']);

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Etiqueta atualizada com sucesso.',
            'data'    => ['id' => $tag->getId(), 'name' => $tag->getName(), 'color' => $tag->getColor(), 'active' => $tag->isActive()],
        ]);
    }

    #[Route('/{id}/toggle-active', methods: ['PATCH'])]
    public function toggleActive(int $id): JsonResponse
    {
        $deny = $this->denyIfNotAdmin();
        if ($deny) return $deny;

        $tag = $this->entityManager->getRepository(Tag::class)->find($id);
        if (!$tag) {
            return new JsonResponse(['success' => false, 'message' => 'Etiqueta não encontrada.'], 404);
        }

        $tag->setActive(!$tag->isActive());
        $this->entityManager->flush();

        $status = $tag->isActive() ? 'ativada' : 'inativada';

        return new JsonResponse([
            'success' => true,
            'message' => "Etiqueta {$status} com sucesso.",
            'data'    => ['id' => $tag->getId(), 'active' => $tag->isActive()],
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
