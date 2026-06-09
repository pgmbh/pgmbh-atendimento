<?php

namespace App\Controller;

use App\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/tags')]
class TagController extends AbstractController
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[Route('', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $tags = $this->entityManager->getRepository(Tag::class)->findBy([], ['name' => 'ASC']);

        return new JsonResponse([
            'success' => true,
            'data' => array_map(fn(Tag $tag) => [
                'id'    => $tag->getId(),
                'name'  => $tag->getName(),
                'color' => $tag->getColor(),
            ], $tags),
        ]);
    }
}
