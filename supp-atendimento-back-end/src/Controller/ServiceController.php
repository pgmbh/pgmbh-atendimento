<?php

namespace App\Controller;

use App\Entity\Service;
use App\Service\ServiceManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Response;  // Adicionando o import do Response
use App\Entity\User;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\Attendant;
use App\Entity\Project;
use App\Entity\ServiceAttachment; // Adicione esta linha para importar a classe
use Symfony\Component\HttpFoundation\BinaryFileResponse; // Também adicione esta para o download
use Symfony\Component\HttpFoundation\File\UploadedFile;
use App\Entity\Tag;
use App\Entity\Status;
use App\Entity\Priority;


#[Route('/api/service')]
class ServiceController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private ServiceManager $serviceManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        ServiceManager $serviceManager,
        private UserPasswordHasherInterface $passwordHasher

    ) {
        $this->entityManager = $entityManager;
        $this->serviceManager = $serviceManager;
    }

    /** Serializa tags de um Service para array simples. */
    private function serializeTags(Service $service): array
    {
        return array_map(fn($tag) => [
            'id'    => $tag->getId(),
            'name'  => $tag->getName(),
            'color' => $tag->getColor(),
        ], $service->getTags()->toArray());
    }

    #[Route('/my-tickets', methods: ['GET'])]
    public function listUserTickets(Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();

            if (!$user) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $startDate = $request->query->get('start_date');
            $endDate = $request->query->get('end_date');
            $excludeStatus = $request->query->get('exclude_status');

            $filters = [
                'title' => $request->query->get('title'),
                'status' => $request->query->get('status'),
                'priority' => $request->query->get('priority'),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'exclude_status' => $excludeStatus,
                'project_id' => $request->query->get('project_id')
            ];

            // Removemos filtros vazios
            $filters = array_filter($filters, function ($value) {
                return !is_null($value) && $value !== '';
            });


            // Get pagination parameters from request
            $page = $request->query->get('page', 1);
            $perPage = $request->query->get('per_page', 10);

            $queryBuilder = $this->serviceManager->createQueryBuilderForUserTickets($user, $filters);

            // Get total items before applying pagination
            $total = count($queryBuilder->getQuery()->getResult());
            // Calcula os metadados da paginação
            $lastPage = max(1, ceil($total / $perPage));
            $currentPage = min($page, $lastPage); // Garante que não ultrapasse o número de páginas
            $offset = ($currentPage - 1) * $perPage;
            // Apply pagination
            $queryBuilder->setFirstResult(($page - 1) * $perPage)
                ->setMaxResults($perPage);

            $services = $queryBuilder->getQuery()->getResult();

            // Transform the services into a format suitable for JSON response
            $response = array_map(function ($service) {
                return [
                    'id' => $service->getId(),
                    'title' => $service->getTitle(),
                    'attachments' => array_map(function ($attachment) {
                        return [
                            'id' => $attachment->getId(),
                            'filename' => $attachment->getFilename(),
                            'originalFilename' => $attachment->getOriginalFilename()
                        ];
                    }, $service->getAttachments()->toArray()),
                    'description' => $service->getDescription(),
                    'status' => $service->getStatus(),
                    'priority' => $service->getPriority(),
                    'sector' => [
                        'id' => $service->getSector()?->getId(),
                        'name' => $service->getSector()?->getName(),
                    ],
                    // Adicione aqui os dados da categoria
                    'category' => $service->getCategory() ? [
                        'id' => $service->getCategory()->getId(),
                        'name' => $service->getCategory()->getName(),
                        'description' => $service->getCategory()->getDescription()
                    ] : null,
                    // Adicione aqui os dados do tipo de serviço
                    'serviceType' => $service->getServiceType() ? [
                        'id' => $service->getServiceType()->getId(),
                        'name' => $service->getServiceType()->getName(),
                        'description' => $service->getServiceType()->getDescription()
                    ] : null,
                    'requester' => [
                        'id' => $service->getRequester()?->getId(),
                        'name' => $service->getRequester()?->getName(),
                        'email' => $service->getRequester()?->getEmail(),
                    ],
                    // Sistema (projeto) vinculado ao chamado
                    'project' => $service->getProject() ? [
                        'id' => $service->getProject()->getId(),
                        'name' => $service->getProject()->getName(),
                        'acronym' => $service->getProject()->getAcronym(),
                    ] : null,

                    'dates' => [
                        'created' => $service->getDateCreate()?->format('Y-m-d H:i:s'),
                        'updated' => $service->getDateUpdate()?->format('Y-m-d H:i:s'),
                        'concluded' => $service->getDateConclusion()?->format('Y-m-d H:i:s'),
                        'deadline' => $service->getDeadline()?->format('Y-m-d H:i:s'),
                    ],
                    'tags' => $this->serializeTags($service),
                ];
            }, $services);

            return new JsonResponse([
                'success' => true,
                'data' => $response,
                'meta' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'last_page' => ceil($total / $perPage),

                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error fetching tickets: ' . $e->getMessage()
            ], 500);
        }
    }
    // Adicione este método ao ServiceController.php
    #[Route('/{id}', methods: ['GET'])]
    public function getService(int $id): JsonResponse
    {
        try {
            $service = $this->serviceManager->findById($id);

            if (!$service) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Service not found'
                ], 404);
            }

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'id' => $service->getId(),
                    'title' => $service->getTitle(),
                    'description' => $service->getDescription(),
                    'status' => $service->getStatus(),
                    'priority' => $service->getPriority(),
                    'sector' => [
                        'id' => $service->getSector()?->getId(),
                        'name' => $service->getSector()?->getName(),
                    ],
                    // Adicione aqui os dados da categoria
                    'category' => $service->getCategory() ? [
                        'id' => $service->getCategory()->getId(),
                        'name' => $service->getCategory()->getName(),
                        'description' => $service->getCategory()->getDescription()
                    ] : null,
                    // Adicione aqui os dados do tipo de serviço
                    'serviceType' => $service->getServiceType() ? [
                        'id' => $service->getServiceType()->getId(),
                        'name' => $service->getServiceType()->getName(),
                        'description' => $service->getServiceType()->getDescription()
                    ] : null,
                    'requester' => [
                        'id' => $service->getRequester()?->getId(),
                        'name' => $service->getRequester()?->getName(),
                        'email' => $service->getRequester()?->getEmail(),
                    ],
                    'dates' => [
                        'created' => $service->getDateCreate()?->format('Y-m-d H:i:s'),
                        'updated' => $service->getDateUpdate()?->format('Y-m-d H:i:s'),
                        'concluded' => $service->getDateConclusion()?->format('Y-m-d H:i:s'),
                        'deadline' => $service->getDeadline()?->format('Y-m-d H:i:s'),
                    ],
                    'project' => $service->getProject() ? [
                        'id' => $service->getProject()->getId(),
                        'name' => $service->getProject()->getName(),
                        'acronym' => $service->getProject()->getAcronym(),
                    ] : null,
                    'attachments' => array_map(function ($attachment) {
                        return [
                            'id' => $attachment->getId(),
                            'filename' => $attachment->getFilename(),
                            'originalFilename' => $attachment->getOriginalFilename()
                        ];
                    }, $service->getAttachments()->toArray()),
                    'tags' => $this->serializeTags($service),
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error fetching service: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {

            // Obtém o usuário logado
            $user = $this->getUser();

            if (!$user) {
                throw new BadRequestException('Usuário não autenticado');
            }


            $files = $request->files->get('files');

            // Garantir que $files seja um array, mesmo que vazio
            if ($files === null) {
                $files = [];
            } else if (!is_array($files)) {
                $files = [$files];
            }


            $data = [
                'title' => $request->request->get('title'),
                'description' => $request->request->get('description'),
                'priority' => $request->request->get('priority'),
                'sector_id' => $request->request->get('sector_id'),
                'requester_id' => $user,
                'category_id' => $request->request->get('category_id'),
                'service_type_id' => $request->request->get('service_type_id'),
                'project_id' => $request->request->get('project_id'),
                'files' => $files // Pega os arquivos
            ];


            // $data = json_decode($request->getContent(), true);
            //$data['requester_id'] = $user;
            $data['status'] = 'Novo'; // Status inicial do chamado
            $data['date_create'] = new \DateTime(); // Data de criação*/
            $service = $this->serviceManager->createService($data);

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'id' => $service->getId(),

                    'title' => $service->getTitle(),
                    'description' => $service->getDescription(),
                    'status' => $service->getStatus(),
                    'sector' => [
                        'id' => $service->getSector()->getId(),
                        'name' => $service->getSector()->getName()
                    ],
                    'priority' => $service->getPriority(),
                    'requester' => [
                        'id' => $service->getRequester()->getId(),
                        'name' => $service->getRequester()->getName(),
                        'email' => $service->getRequester()->getEmail()
                    ],
                    'dates' => [
                        'created' => $service->getDateCreate()->format('Y-m-d H:i:s')
                    ],
                    'attachments' => array_map(function ($attachment) {
                        return [
                            'id' => $attachment->getId(),
                            'filename' => $attachment->getFilename(),
                            'originalFilename' => $attachment->getOriginalFilename(),
                            'mimeType' => $attachment->getMimeType(),
                            'fileSize' => $attachment->getFileSize()
                        ];
                    }, $service->getAttachments()->toArray())
                ]
            ], 201);
        } catch (BadRequestException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }

    // In ServiceController.php
    // In ServiceController.php

    #[Route('/sector/{sector?}', methods: ['GET'])]
    public function listBySector(?string $sector = null): JsonResponse
    {
        try {
            // Get services based on whether a sector was specified
            $services = $sector
                ? $this->serviceManager->getServicesBySector($sector)
                : $this->serviceManager->getAllServices();

            // Transform the services into a format suitable for JSON response
            $response = array_map(function ($service) {
                return [
                    'id' => $service->getId(),
                    'title' => $service->getTitle(),
                    'description' => $service->getDescription(),
                    'status' => $service->getStatus(),
                    'sector' => [
                        'id' => $service->getSector()?->getId(),
                        'name' => $service->getSector()?->getName(),
                    ],
                    'requester' => [
                        'id' => $service->getRequester()?->getId(),
                        'name' => $service->getRequester()?->getName(),
                        'email' => $service->getRequester()?->getEmail(),
                    ],
                    'dates' => [
                        'created' => $service->getDateCreate()?->format('Y-m-d H:i:s'),
                        'updated' => $service->getDateUpdate()?->format('Y-m-d H:i:s'),
                        'concluded' => $service->getDateConclusion()?->format('Y-m-d H:i:s'),
                        'deadline' => $service->getDeadline()?->format('Y-m-d H:i:s'),
                    ],
                ];
            }, $services);

            return new JsonResponse([
                'success' => true,
                'data' => $response,
                'count' => count($response)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error fetching services: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/attendant/{id}', methods: ['GET'])]
    public function listByAttendant(int $id, Request $request): JsonResponse
    {
        try {
            $attendant = $this->entityManager->getRepository(Attendant::class)->find($id);
            if (!$attendant) {
                return new JsonResponse(['success' => false, 'message' => 'Atendente não encontrado'], 404);
            }

            $services = $this->serviceManager->getServicesByAttendant($id);

            return $this->buildServiceListResponse($services, $request);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Error fetching services: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/admin/all', methods: ['GET'])]
    public function listAllForAdmin(Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();
            $attendant = $this->entityManager->getRepository(Attendant::class)->findOneBy(['user' => $user]);
            if (!$attendant || $attendant->getFunction() !== 'Admin') {
                return new JsonResponse(['success' => false, 'message' => 'Acesso negado. Apenas Admin.'], 403);
            }

            $services = $this->serviceManager->getAllServices();

            return $this->buildServiceListResponse($services, $request);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Error fetching services: ' . $e->getMessage()], 500);
        }
    }

    /** Helper: aplica filtros, ordenação, paginação e serialização sobre um array de Service. */
    private function buildServiceListResponse(array $services, Request $request): JsonResponse
    {
        // Parâmetros de filtragem
        $title          = $request->query->get('title');
        $description    = $request->query->get('description');
        $requester      = $request->query->get('requester');
        $status         = $request->query->get('status');
        $priority       = $request->query->get('priority');
        $categoryId     = $request->query->get('category_id');
        $serviceTypeId  = $request->query->get('service_type_id');
        $projectId      = $request->query->get('project_id');
        $tagId          = $request->query->get('tag_id');
        $attendantId    = $request->query->get('attendant_id');
        $excludeStatuses = array_filter(explode(',', $request->query->get('exclude_status', '')));
        $sortField      = $request->query->get('sort', 'created_at');
        $sortOrder      = $request->query->get('order', 'desc');
        $page           = max(1, (int)$request->query->get('page', 1));
        $perPage        = max(1, (int)$request->query->get('per_page', 10));

        // Filtro de período (data de criação)
        $startDate = null;
        $endDate   = null;
        try {
            if ($request->query->get('start_date')) {
                $startDate = new \DateTime($request->query->get('start_date') . ' 00:00:00');
            }
            if ($request->query->get('end_date')) {
                $endDate = new \DateTime($request->query->get('end_date') . ' 23:59:59');
            }
        } catch (\Exception) {
            // Datas inválidas são ignoradas
            $startDate = $endDate = null;
        }

        // Aplicar filtros
        $filteredServices = [];
        foreach ($services as $service) {
            $keepService = true;

            if ($title && !str_contains(strtolower($service->getTitle()), strtolower($title))) {
                $keepService = false;
            }
            if ($description && !str_contains(strtolower($service->getDescription() ?? ''), strtolower($description))) {
                $keepService = false;
            }
            if ($requester && $service->getRequester() && !str_contains(strtolower($service->getRequester()->getName()), strtolower($requester))) {
                $keepService = false;
            }
            if ($status && $service->getStatus() !== $status) {
                $keepService = false;
            }
            if ($priority && $service->getPriority() !== $priority) {
                $keepService = false;
            }
            if ($categoryId && (!$service->getCategory() || $service->getCategory()->getId() != $categoryId)) {
                $keepService = false;
            }
            if ($serviceTypeId && (!$service->getServiceType() || $service->getServiceType()->getId() != $serviceTypeId)) {
                $keepService = false;
            }
            if ($projectId && (!$service->getProject() || $service->getProject()->getId() != $projectId)) {
                $keepService = false;
            }
            if (!empty($excludeStatuses) && in_array($service->getStatus(), $excludeStatuses, true)) {
                $keepService = false;
            }
            if ($tagId) {
                $tagIds = array_map(fn($t) => $t->getId(), $service->getTags()->toArray());
                if (!in_array((int)$tagId, $tagIds, true)) {
                    $keepService = false;
                }
            }
            if ($attendantId && (!$service->getReponsible() || $service->getReponsible()->getId() != $attendantId)) {
                $keepService = false;
            }
            if ($startDate && (!$service->getDateCreate() || $service->getDateCreate() < $startDate)) {
                $keepService = false;
            }
            if ($endDate && (!$service->getDateCreate() || $service->getDateCreate() > $endDate)) {
                $keepService = false;
            }

            if ($keepService) {
                $filteredServices[] = $service;
            }
        }

        // Ordenação dinâmica
        $priorityWeight = ['URGENTE' => 0, 'ALTA' => 1, 'NORMAL' => 2, 'BAIXA' => 3];
        usort($filteredServices, function ($a, $b) use ($sortField, $sortOrder, $priorityWeight) {
            $asc = $sortOrder === 'asc';
            $cmp = match ($sortField) {
                'id'              => $a->getId() <=> $b->getId(),
                'title'           => strnatcasecmp($a->getTitle() ?? '', $b->getTitle() ?? ''),
                'description'     => strnatcasecmp($a->getDescription() ?? '', $b->getDescription() ?? ''),
                'status'          => strcmp($a->getStatus() ?? '', $b->getStatus() ?? ''),
                'priority'        => ($priorityWeight[$a->getPriority()] ?? 4) <=> ($priorityWeight[$b->getPriority()] ?? 4),
                'deadline'        => $a->getDeadline() <=> $b->getDeadline(),
                'conclusion_date' => $a->getDateConclusion() <=> $b->getDateConclusion(),
                default           => $a->getDateCreate() <=> $b->getDateCreate(),
            };
            return $asc ? $cmp : -$cmp;
        });

        // Paginação
        $total = count($filteredServices);
        $paginatedServices = array_slice($filteredServices, ($page - 1) * $perPage, $perPage);

        // Serialização
        $response = array_map(function ($service) {
            return [
                'id'          => $service->getId(),
                'title'       => $service->getTitle(),
                'description' => $service->getDescription(),
                'status'      => $service->getStatus(),
                'priority'    => $service->getPriority(),
                'requester'   => [
                    'id'    => $service->getRequester()?->getId(),
                    'name'  => $service->getRequester()?->getName(),
                    'email' => $service->getRequester()?->getEmail(),
                ],
                'responsible' => $service->getReponsible() ? [
                    'id'   => $service->getReponsible()->getId(),
                    'name' => $service->getReponsible()->getName(),
                ] : null,
                'sector' => [
                    'id'   => $service->getSector()?->getId(),
                    'name' => $service->getSector()?->getName(),
                ],
                'category' => $service->getCategory() ? [
                    'id'          => $service->getCategory()->getId(),
                    'name'        => $service->getCategory()->getName(),
                    'description' => $service->getCategory()->getDescription(),
                ] : null,
                'serviceType' => $service->getServiceType() ? [
                    'id'          => $service->getServiceType()->getId(),
                    'name'        => $service->getServiceType()->getName(),
                    'description' => $service->getServiceType()->getDescription(),
                ] : null,
                'project' => $service->getProject() ? [
                    'id'      => $service->getProject()->getId(),
                    'name'    => $service->getProject()->getName(),
                    'acronym' => $service->getProject()->getAcronym(),
                ] : null,
                'dates' => [
                    'created'   => $service->getDateCreate()?->format('Y-m-d H:i:s'),
                    'updated'   => $service->getDateUpdate()?->format('Y-m-d H:i:s'),
                    'concluded' => $service->getDateConclusion()?->format('Y-m-d H:i:s'),
                    'deadline'  => $service->getDeadline()?->format('Y-m-d H:i:s'),
                ],
                'tags' => $this->serializeTags($service),
            ];
        }, $paginatedServices);

        return new JsonResponse([
            'success' => true,
            'data'    => $response,
            'meta'    => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => (int)ceil($total / $perPage),
            ],
        ]);
    }

    #[Route('/{id}/status', methods: ['PUT'])]
    public function updateStatus(int $id, Request $request): JsonResponse
    {
        try {
            // Decodifica o corpo da requisição
            $data = json_decode($request->getContent(), true);

            if (isset($data['updateData'])) {
                $updateData = $data['updateData'];
            } else {
                $updateData = $data;
            }

            // Validação básica dos dados recebidos
            if (!isset($updateData['status']) || !isset($updateData['comment'])) {
                throw new BadRequestException('Status and comment are required');
            }

            // Busca o serviço no ServiceManager
            $service = $this->serviceManager->findById($id);

            if (!$service) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Service not found'
                ], 404);
            }

            $categoryId = $updateData['category_id'] ?? null;
            $serviceTypeId = $updateData['service_type_id'] ?? null;
            $priority = $updateData['priority'] ?? null;
            $projectId = isset($updateData['project_id']) ? (int)$updateData['project_id'] : null;
            // null = não enviado (não mexer nas tags); [] = remover todas; [ids...] = substituir
            $tagIds = array_key_exists('tag_ids', $updateData) ? array_map('intval', (array)$updateData['tag_ids']) : null;

            // Obter o usuário logado
            $user = $this->getUser();

            // Encontrar o atendente associado ao usuário logado
            $attendant = null;
            if ($user) {
                // Consulta direta ao banco de dados para encontrar o atendente
                $attendant = $this->entityManager->getRepository(Attendant::class)
                    ->createQueryBuilder('a')
                    ->where('a.user = :user')
                    ->setParameter('user', $user)
                    ->getQuery()
                    ->getOneOrNullResult();
            }

            $this->serviceManager->updateServiceStatus(
                service: $service,
                newStatus: $updateData['status'],
                comment: $updateData['comment'],
                attendant: $attendant,
                categoryId: $categoryId,
                serviceTypeId: $serviceTypeId,
                priority: $priority,
                projectId: $projectId,
                tagIds: $tagIds
            );

            // Prepara a resposta com os dados atualizados
            return new JsonResponse([
                'success' => true,
                'data' => [
                    'id' => $service->getId(),
                    'title' => $service->getTitle(),
                    'status' => $service->getStatus(),
                    'dates' => [
                        'created' => $service->getDateCreate()->format('Y-m-d H:i:s'),
                        'updated' => $service->getDateUpdate()->format('Y-m-d H:i:s'),
                        'concluded' => $service->getDateConclusion()?->format('Y-m-d H:i:s')
                    ],
                    'history' => array_map(function ($history) {
                        return [
                            'date' => $history->getDateHistory()->format('Y-m-d H:i:s'),
                            'status_prev' => $history->getStatusPrev(),
                            'status_post' => $history->getStatusPost(),
                            'comment' => $history->getComment(),
                        ];
                    }, $service->getHistories()->toArray())
                ]
            ]);
        } catch (BadRequestException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }


    // Em ServiceController.php

    #[Route('/{id}/transfer', methods: ['PUT'])]
    public function transferToSector(int $id, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Validação dos dados
            if (!isset($data['sector_id']) || !isset($data['comment'])) {
                throw new BadRequestException('Sector ID and comment are required');
            }

            // Buscar o serviço
            $service = $this->serviceManager->findById($id);

            if (!$service) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Service not found'
                ], 404);
            }

            // Transferir o ticket para o novo setor
            $this->serviceManager->transferTicketToSector(
                service: $service,
                newSectorId: $data['sector_id'],
                comment: $data['comment']
            );

            // Preparar resposta
            return new JsonResponse([
                'success' => true,
                'data' => [
                    'id' => $service->getId(),
                    'title' => $service->getTitle(),
                    'status' => $service->getStatus(),
                    'sector' => [
                        'id' => $service->getSector()->getId(),
                        'name' => $service->getSector()->getName()
                    ],
                    'dates' => [
                        'created' => $service->getDateCreate()->format('Y-m-d H:i:s'),
                        'updated' => $service->getDateUpdate()->format('Y-m-d H:i:s')
                    ],
                    'history' => array_map(function ($history) {
                        return [
                            'date' => $history->getDateHistory()->format('Y-m-d H:i:s'),
                            'status_prev' => $history->getStatusPrev(),
                            'status_post' => $history->getStatusPost(),
                            'comment' => $history->getComment()
                        ];
                    }, $service->getHistories()->toArray())
                ]
            ]);
        } catch (BadRequestException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }




    // In ServiceManager.php

    public function getServicesByRequester(int $userId): array
    {
        $serviceRepository = $this->entityManager->getRepository(Service::class);

        $queryBuilder = $serviceRepository->createQueryBuilder('s')
            ->leftJoin('s.sector', 'sect')
            ->leftJoin('s.requester', 'u')
            ->leftJoin('s.reponsible', 'a')
            ->select('s', 'sect', 'u', 'a')
            ->where('u.id = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('s.date_create', 'DESC');

        return $queryBuilder->getQuery()->getResult();
    }

    // Then modify the ServiceController.php to use this new method:





    // Em ServiceController.php

    #[Route('/{id}/history', methods: ['GET'])]
    public function getServiceHistory(int $id): JsonResponse
    {
        try {
            $service = $this->serviceManager->findById($id);

            if (!$service) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Service not found'
                ], 404);
            }

            // Obtém o histórico ordenado por data
            $histories = $service->getHistories()->toArray();

            // Ordena o histórico pela data mais recente primeiro
            usort($histories, function ($a, $b) {
                return $b->getDateHistory() <=> $a->getDateHistory();
            });

            // Formata a resposta
            $response = array_map(function ($history) {
                $responsible = $history->getResponsible();
                return [
                    'id' => $history->getId(),
                    'date' => $history->getDateHistory()->format('Y-m-d H:i:s'),
                    'status_prev' => $history->getStatusPrev(),
                    'status_post' => $history->getStatusPost(),
                    'comment' => $history->getComment(),
                    'responsible' => $responsible ? [
                        'id' => $responsible->getId(),
                        'name' => $responsible->getName(),
                    ] : null,
                    'service' => [
                        'id' => $history->getService()->getId(),
                        'title' => $history->getService()->getTitle()
                    ]
                ];
            }, $histories);

            return new JsonResponse([
                'success' => true,
                'data' => $response
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error fetching service history: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/comment', methods: ['POST'])]
    public function addUserComment(int $id, Request $request): JsonResponse
    {
        try {
            $user = $this->getUser();
            
            if (!$user) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $service = $this->serviceManager->findById($id);
            
            if (!$service) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Service not found'
                ], 404);
            }
            
            // Verificar se o usuário é o solicitante do ticket
            if ($service->getRequester()->getId() !== $user->getId()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Access denied. You can only comment on your own tickets.'
                ], 403);
            }
            
            $data = json_decode($request->getContent(), true);
            $comment = $data['comment'] ?? '';
            
            if (empty(trim($comment))) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Comment cannot be empty'
                ], 400);
            }
            
            // Usar o ServiceManager para adicionar o comentário como um histórico
            $this->serviceManager->addUserComment($service, trim($comment), $user);
            
            return new JsonResponse([
                'success' => true,
                'message' => 'Comment added successfully'
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error adding comment: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/admin/create', methods: ['POST'])]
    public function createByAdmin(Request $request): JsonResponse
    {

        try {

            // Verificar se o usuário logado é um atendente admin
            $user = $this->getUser();

            if (!$user) {
                throw new AccessDeniedException('Usuário não autenticado');
            }

            // error_log('Usuário autenticado: ' . $user->getId() . ' - ' . $user->getEmail());


            /* if ($user instanceof User && !$user->isIsAttendant()) {
                throw new AccessDeniedException('Apenas atendentes podem criar tickets para usuários 1 ');
            }
            */
            // Obter o atendente correspondente ao usuário
            $attendant = $this->entityManager->getRepository(Attendant::class)
                ->findOneBy(['user' => $user]);

            /*
            if (!$attendant || $attendant->getFunction() !== 'Admin') {
                throw new AccessDeniedException('Apenas administradores podem criar tickets para usuários 2 ');
            }
    */
            // Preparar dados para criação do ticket
            $jsonData = json_decode($request->getContent(), true);
            $files = $request->files->get('files');

            // Filtrar elementos vazios ou inválidos do array de arquivos
            if (is_array($files)) {
                $files = array_filter($files, function ($file) {
                    return !empty($file) && $file instanceof UploadedFile && $file->isValid();
                });
            } else {
                $files = []; // Garantir que $files seja um array vazio se não existir
            }

            $title = $request->request->get('title');
            $description = $request->request->get('description');
            $priority = $request->request->get('priority', 'NORMAL');
            $sector_id = $request->request->get('sector_id');
            $requester_id = $request->request->get('requester_id');
            $created_by_admin_id = $request->request->get('created_by_admin_id');
            $category_id = $request->request->get('category_id');
            $service_type_id = $request->request->get('service_type_id');

            $project_id = $request->request->get('project_id');
            // tag_ids pode vir como tag_ids[] (array) ou tag_ids (valor único)
            $tag_ids = array_values(array_filter(
                array_map('intval', $request->request->all('tag_ids')),
                fn($id) => $id > 0
            ));

            $data = [
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'sector_id' => $sector_id,
                'requester_id' => $requester_id, // Usar o ID do usuário solicitante
                'category_id' => $category_id,
                'service_type_id' => $service_type_id,
                'project_id' => $project_id,
                'created_by_admin' => true,
                'created_by_admin_id' => $created_by_admin_id, // ID do atendente admin
                'files' => $files,
                'tag_ids' => $tag_ids,
            ];


            // Criar o serviço
            $service = $this->serviceManager->createService($data, $admin = true);

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'id' => $service->getId(),
                    'title' => $service->getTitle(),
                    'description' => $service->getDescription(),
                    'status' => $service->getStatus(),
                    'sector' => [
                        'id' => $service->getSector()->getId(),
                        'name' => $service->getSector()->getName()
                    ],
                    'priority' => $service->getPriority(),
                    'requester' => [
                        'id' => $service->getRequester()->getId(),
                        'name' => $service->getRequester()->getName(),
                        'email' => $service->getRequester()->getEmail()
                    ],
                    'dates' => [
                        'created' => $service->getDateCreate()->format('Y-m-d H:i:s')
                    ],
                    'created_by_admin' => $service->isCreatedByAdmin(),
                    'attachments' => array_map(function ($attachment) {
                        return [
                            'id' => $attachment->getId(),
                            'filename' => $attachment->getFilename(),
                            'originalFilename' => $attachment->getOriginalFilename()
                        ];
                    }, $service->getAttachments()->toArray()),
                    'tags' => $this->serializeTags($service),
                ]
            ], 201);
        } catch (AccessDeniedException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 403);
        } catch (BadRequestException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }


    #[Route('/{id}/project', methods: ['PATCH'])]
    public function patchProject(int $id, Request $request): JsonResponse
    {
        try {
            $service = $this->serviceManager->findById($id);
            if (!$service) {
                return new JsonResponse(['success' => false, 'message' => 'Service not found'], 404);
            }

            $data = json_decode($request->getContent(), true);
            $projectId = $data['project_id'] ?? null;

            if ($projectId !== null) {
                $project = $this->entityManager->getRepository(Project::class)->find($projectId);
                if (!$project) {
                    return new JsonResponse(['success' => false, 'message' => 'Projeto não encontrado'], 404);
                }
                $service->setProject($project);
            } else {
                $service->setProject(null);
            }

            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'id' => $service->getId(),
                    'project' => $service->getProject() ? [
                        'id' => $service->getProject()->getId(),
                        'name' => $service->getProject()->getName(),
                        'acronym' => $service->getProject()->getAcronym(),
                    ] : null,
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Em ServiceController.php
    #[Route('/attachment/{id}', methods: ['GET'])]
    public function downloadAttachment(int $id): Response
    {
        try {
            $attachment = $this->entityManager->getRepository(ServiceAttachment::class)->find($id);

            if (!$attachment) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Anexo não encontrado'
                ], 404);
            }

            $filePath = $this->getParameter('uploads_directory') . '/' . $attachment->getFilename();

            if (!file_exists($filePath)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Arquivo não encontrado no servidor'
                ], 404);
            }

            return new BinaryFileResponse($filePath, 200, [
                'Content-Type' => $attachment->getMimeType(),
                'Content-Disposition' => 'attachment; filename="' . $attachment->getOriginalFilename() . '"'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Erro ao baixar anexo: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/deadline', methods: ['PUT'])]
    public function updateDeadline(int $id, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['deadline'])) {
                throw new BadRequestException('Deadline is required');
            }

            $service = $this->serviceManager->findById($id);

            if (!$service) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Service not found'
                ], 404);
            }

            // Atualizar o prazo
            $deadline = new \DateTime($data['deadline']);
            $service->setDeadline($deadline);
            $service->setDateUpdate(new \DateTime());

            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'id' => $service->getId(),
                    'title' => $service->getTitle(),
                    'deadline' => $service->getDeadline()->format('Y-m-d H:i:s'),
                    'dates' => [
                        'created' => $service->getDateCreate()->format('Y-m-d H:i:s'),
                        'updated' => $service->getDateUpdate()->format('Y-m-d H:i:s'),
                        'deadline' => $service->getDeadline()->format('Y-m-d H:i:s'),
                    ]
                ]
            ]);
        } catch (BadRequestException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }
}
