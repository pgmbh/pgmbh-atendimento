<?php

namespace App\Service;

use App\Entity\Service;
use App\Entity\Status;
use App\Entity\Priority;
use App\Entity\Tag;
use App\Entity\Attendant;
use App\Entity\Category;
use App\Entity\Project;
use App\Entity\ServiceType;
use App\Entity\ServiceHistory;
use App\Entity\ServiceAttachment;
use App\Entity\Sector;
use App\Entity\User;
use DateTime;
use DateTimeZone; // <-- ALTERAÇÃO AQUI: Importa a classe DateTimeZone
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;  // Adicione este import
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Twig\Environment;

class ServiceManager
{
    private const VALID_STATUS = ['NEW', 'OPEN', 'IN_PROGRESS', 'RESOLVED', 'CANCELADO', 'RETORNO', 'CONCLUDED', 'NOVO', 'backlog', 'product backlog'];
    private string $uploadDir;
    private DateTimeZone $timezone; // <-- ALTERAÇÃO AQUI: Propriedade para o fuso horário

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AttachmentManager $attachmentManager,
        private MailerInterface $mailer,
        string $uploadDir = null
    ) {

        $this->uploadDir = $uploadDir ?? dirname(__DIR__, 2) . '/public/uploads/attachments';
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }

        // <-- ALTERAÇÃO AQUI: Instancia o objeto de fuso horário no construtor
        $this->timezone = new DateTimeZone('America/Sao_Paulo');
    }

    public function findById(int $id): ?Service
    {
        return $this->entityManager->getRepository(Service::class)->find($id);
    }


    public function createService(array $data, bool $admin = false): Service
    {
        // Validar dados obrigatórios
        if (empty($data['title']) || empty($data['description']) || empty($data['sector_id']) || empty($data['requester_id'])) {
            throw new BadRequestException('Missing required fields');
        }

        $createdByAdmin = !empty($data['created_by_admin']);
        $requester = null;

        if ($createdByAdmin) {
            // Se o ticket está sendo criado por um admin, verificamos o ID do requisitante
            if (empty($data['requester_id'])) {
                throw new BadRequestException('Requester ID is required when creating ticket as admin');
            }

            $requester = $this->entityManager->getRepository(User::class)->find($data['requester_id']);
            if (!$requester) {
                throw new BadRequestException('Invalid requester');
            }
        } else {
            // Caso padrão: ticket criado pelo próprio usuário
            $requester = $data['requester_id'];
        }

        // Buscar entidades relacionadas
        $sector = $this->entityManager->getRepository(Sector::class)->find($data['sector_id']);
        if (!$sector || !$requester) {
            throw new BadRequestException('Invalid sector or requester');
        }

        $service = new Service();
        $service->setTitle($data['title']);
        $service->setDescription($data['description']);
        $service->setSector($sector);
        $service->setRequester($requester);
        $service->setStatusEntity($this->resolveStatus('NOVO'));
        $service->setDateCreate(new DateTime('now', $this->timezone));

        // Buscar categoria se fornecida
        if (!empty($data['category_id'])) {
            $category = $this->entityManager->getRepository(Category::class)->find($data['category_id']);
            if ($category) {
                $service->setCategory($category);
            }
        }

        // Sempre definir como "Triagem" para novos chamados criados por usuários
        // Se foi criado por admin, usar o service_type_id fornecido
        if ($createdByAdmin && !empty($data['service_type_id'])) {
            $serviceType = $this->entityManager->getRepository(ServiceType::class)->find($data['service_type_id']);
            if ($serviceType) {
                $service->setServiceType($serviceType);
            }
        } else {
            // Para chamados criados por usuários, sempre usar "Triagem"
            $triagemServiceType = $this->entityManager->getRepository(ServiceType::class)->findOneBy(['name' => 'Triagem']);
            if ($triagemServiceType) {
                $service->setServiceType($triagemServiceType);
            }
        }

        $priority = $data['priority'] ?? Service::PRIORITY_NORMAL;
        $service->setPriorityEntity($this->resolvePriority($priority));

        // Vincular projeto se fornecido (RF-002)
        if (!empty($data['project_id'])) {
            $project = $this->entityManager->getRepository(Project::class)->find($data['project_id']);
            if ($project) {
                $service->setProject($project);
            }
        }

        // Definir prazo (deadline)
        if ($createdByAdmin && !empty($data['deadline'])) {
            // Se foi criado por admin e tem deadline personalizado
            $deadline = new DateTime($data['deadline']);
            $service->setDeadline($deadline);
        } else {
            // Prazo padrão de 5 dias úteis para todos os tickets
            $deadline = new DateTime('now', $this->timezone);
            $deadline->add(new \DateInterval('P5D')); // Adiciona 5 dias
            $service->setDeadline($deadline);
        }

        // Configurações para tickets criados por admin
        if ($createdByAdmin && !empty($data['created_by_admin_id'])) {
            $adminAttendant = $this->entityManager->getRepository(Attendant::class)->find($data['created_by_admin_id']);

            if ($adminAttendant) {
                $service->setCreatedByAdmin(true);
                $service->setCreatedByAdminAttendant($adminAttendant);
                $service->setReponsible($adminAttendant);
            }
        }

        // Processar anexos
        if (!empty($data['files'])) {
            foreach ($data['files'] as $uploadedFile) {
                if (!$uploadedFile) {
                    continue;
                }

                if ($uploadedFile instanceof UploadedFile) {
                    try {
                        if ($uploadedFile->isValid() && $this->attachmentManager->validateFile($uploadedFile)) {
                            // Captura metadados ANTES de mover o arquivo (após move() o tmp deixa de existir)
                            $originalFilename = $uploadedFile->getClientOriginalName();
                            $mimeType = $uploadedFile->getMimeType();
                            $fileSize = $uploadedFile->getSize();

                            $filename = $this->attachmentManager->uploadFile($uploadedFile);
                            $attachment = new ServiceAttachment();
                            $attachment->setService($service);
                            $attachment->setFilename($filename);
                            $attachment->setOriginalFilename($originalFilename);
                            $attachment->setMimeType($mimeType);
                            $attachment->setFileSize($fileSize);

                            $this->entityManager->persist($attachment);
                            $service->addAttachment($attachment);
                        }
                    } catch (\Exception $e) {
                        error_log('Erro ao processar arquivo: ' . $e->getMessage());
                        // Continue sem interromper o processo
                    }
                }
            }
        }

        // Persistir o serviço
        $this->entityManager->persist($service);

        // Criar histórico inicial
        $history = new ServiceHistory();
        $history->setService($service);
        $history->setStatusPrev('Nenhum');
        $history->setStatusPost('NEW');
        $history->setDateHistory(new DateTime('now', $this->timezone));

        if ($createdByAdmin && $service->getCreatedByAdminAttendant()) {
            $history->setComment('Ticket criado pelo atendente: ' . $service->getCreatedByAdminAttendant()->getName());
            $history->setResponsible($service->getCreatedByAdminAttendant());
        } else {
            $history->setComment('Ticket criado pelo usuário');
        }

        $this->entityManager->persist($history);
        $this->entityManager->flush();

        return $service;
    }

    public function updateServiceStatus(
        Service $service,
        string $newStatus,
        string $comment,
        array $files = [],
        ?Attendant $attendant = null,
        ?int $categoryId = null,
        ?int $serviceTypeId = null,
        ?string $priority = null,
        ?int $projectId = null
    ): void {

        //   die('parou uuuuuuuuuuuuuuuuu');

        if (!in_array($newStatus, self::VALID_STATUS) && !in_array(strtoupper($newStatus), self::VALID_STATUS)) {
            throw new BadRequestException('Invalid status provided');
        }

        $currentStatus = $service->getStatus();
        if ($categoryId !== null) {
            $category = $this->entityManager->getRepository(Category::class)->find($categoryId);
            if ($category) {
                $service->setCategory($category);
            }
        }

        if ($serviceTypeId !== null) {
            $serviceType = $this->entityManager->getRepository(ServiceType::class)->find($serviceTypeId);
            if ($serviceType) {
                $service->setServiceType($serviceType);
            }
        }

        if ($priority !== null && in_array($priority, Service::VALID_PRIORITIES)) {
            $service->setPriorityEntity($this->resolvePriority($priority));
        }

        if ($projectId !== null) {
            $project = $this->entityManager->getRepository(Project::class)->find($projectId);
            if ($project) {
                $service->setProject($project);
            }
        }

        if (!empty($files)) {
            foreach ($files as $file) {
                if ($this->attachmentManager->validateFile($file)) {
                    // Captura metadados ANTES de mover o arquivo (após move() o tmp deixa de existir)
                    $originalFilename = $file->getClientOriginalName();
                    $mimeType = $file->getMimeType();
                    $fileSize = $file->getSize();

                    $filename = $this->attachmentManager->uploadFile($file);

                    $attachment = new ServiceAttachment();
                    $attachment->setService($service);
                    $attachment->setFilename($filename);
                    $attachment->setOriginalFilename($originalFilename);
                    $attachment->setMimeType($mimeType);
                    $attachment->setFileSize($fileSize);

                    $this->entityManager->persist($attachment);
                    $service->addAttachment($attachment);
                }
            }
        }
        // Criar histórico da alteração

        // $this->createServiceHistory($service, $currentStatus, $newStatus, $comment);
        $this->createServiceHistory($service, $currentStatus, $newStatus, $comment, $attendant);


        if ($attendant && !$service->getReponsible()) {
            $service->setReponsible($attendant);
        }
        // Atualizar o serviço
        $service->setStatusEntity($this->resolveStatus($newStatus));
        $service->setDateUpdate(new DateTime('now', $this->timezone));

        // Se status for RESOLVED, enviar email para o usuário
        if (strtoupper($newStatus) === 'RESOLVED') {
            $this->sendStatusUpdateEmail($service, 'RESOLVED');
        }
        
        // Se status for RETORNO, enviar email para o usuário
        if (strtoupper($newStatus) === 'RETORNO') {
            $this->sendStatusUpdateEmail($service, 'RETORNO');
        }
        
        // Se status for CONCLUDED, atualizar data de conclusão
        if (strtoupper($newStatus) === 'CONCLUDED') {
            $service->setDateConclusion(new DateTime('now', $this->timezone));
        }

        $this->entityManager->flush();
    }

    private function findAdminAttendant(): ?Attendant
    {
        return $this->entityManager->getRepository(Attendant::class)
            ->createQueryBuilder('a')
            ->join('a.sector', 's')
            ->where('s.name = :sectorName')
            ->andWhere('a.status = :status')
            ->setParameter('sectorName', 'Diretoria')
            ->setParameter('status', 'ACTIVE')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    private function createServiceHistory(Service $service, string $prevStatus, string $newStatus, string $comment, ?Attendant $attendant = null): void
    {


        $history = new ServiceHistory();
        $history->setService($service);
        $history->setStatusPrev($prevStatus);
        $history->setStatusPost($newStatus);
        
        // Se há atendente e um comentário, adicionar prefixo com nome do atendente
        if ($attendant && !empty(trim($comment))) {
            $history->setComment('Atendente: ' . $attendant->getName() . ' - ' . $comment);
        } else {
            $history->setComment($comment);
        }
        
        $history->setDateHistory(new DateTime('now', $this->timezone));


        if ($attendant) {

            $history->setResponsible($attendant);

            if (!$service->getReponsible()) {


                $service->setReponsible($attendant);
            }
        }
        //    die('chega de palhaçada');

        $this->entityManager->persist($history);
    }

    private function sendStatusUpdateEmail(Service $service, string $status = 'RESOLVED'): void
    {
        $requester = $service->getRequester();

        if (!$requester || !$requester->getEmail()) {
            return;
        }

        try {
            // Definir conteúdo baseado no status
            if ($status === 'RETORNO') {
                $headerTitle = 'Chamado Retornado';
                $statusMessage = 'Seu chamado foi retornado para análise!';
                $additionalInfo = '<p><strong>Atenção:</strong> Seu chamado foi retornado para que você possa fornecer informações adicionais ou esclarecimentos. Por favor, acesse o sistema para verificar os comentários do atendente e responder conforme necessário.</p>';
                $subject = 'Chamado #' . $service->getId() . ' - Retornado';
                $dateLabel = 'Data do Retorno:';
                $headerColor = '#ff9800'; // Cor laranja para retorno
            } else { // RESOLVED
                $headerTitle = 'Chamado Resolvido';
                $statusMessage = 'Seu chamado foi resolvido!';
                $additionalInfo = '<p><strong>Importante:</strong> Se você concordar com a resolução, não é necessário fazer nada. O chamado será automaticamente concluído em 5 dias úteis.</p>
                        <p>Caso a resolução não atenda suas expectativas, você pode reabrir o chamado através do sistema de atendimento.</p>';
                $subject = 'Chamado #' . $service->getId() . ' - Resolvido';
                $dateLabel = 'Data de Resolução:';
                $headerColor = '#4a90e2'; // Cor azul para resolvido
            }

            // Criamos o conteúdo HTML diretamente
            $htmlContent = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        .header { background-color: {$headerColor}; color: white; padding: 20px; }
                        .content { padding: 20px; }
                        .ticket-info { background-color: #f9f9f9; padding: 15px; margin: 10px 0; }
                    </style>
                </head>
                <body>
                    <div class='header'>
                        <h1>{$headerTitle}</h1>
                    </div>
                    <div class='content'>
                        <p>Olá {$requester->getName()},</p>
                        
                        <p>{$statusMessage}</p>
                        
                        <div class='ticket-info'>
                            <p><strong>Número do Chamado:</strong> #{$service->getId()}</p>
                            <p><strong>Título:</strong> {$service->getTitle()}</p>
                            <p><strong>Setor:</strong> {$service->getSector()->getName()}</p>
                            <p><strong>Data de Abertura:</strong> {$service->getDateCreate()->format('d/m/Y H:i:s')}</p>
                            <p><strong>{$dateLabel}</strong> {$service->getDateUpdate()->format('d/m/Y H:i:s')}</p>
                        </div>
                        
                        {$additionalInfo}
                        
                        <p>Se você precisar de qualquer assistência adicional, não hesite em entrar em contato.</p>
                        
                        <p>Atenciosamente,<br>PGMBH - Equipe de Suporte</p>
                    </div>
                </body>
                </html>
            ";

            $email = (new Email())
                ->from('helpdesk@seu-dominio.com')  // Altere para seu email configurado
                ->to($requester->getEmail())
                ->subject($subject)
                ->html($htmlContent);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Registra o erro mas não interrompe o fluxo principal
            error_log('Erro ao enviar email de atualização do chamado #' .
                $service->getId() . ': ' . $e->getMessage());
        }
    }


    // In ServiceManager.php
    public function getServicesBySector(int $sectorId): array
    {
        // Get the repository for Service entity
        $serviceRepository = $this->entityManager->getRepository(Service::class);

        // Create a query builder to fetch services
        $queryBuilder = $serviceRepository->createQueryBuilder('s')
            // Join with sector
            ->join('s.sector', 'sect')
            // Join with requester to get user information
            ->leftJoin('s.requester', 'u')
            // Join with responsible attendant
            ->leftJoin('s.reponsible', 'a')
            // Select necessary fields
            ->select('s', 'sect', 'u', 'a')
            // Filter by sector id
            ->where('sect.id = :sectorId')
            ->setParameter('sectorId', $sectorId)
            // Order by creation date, newest first
            ->orderBy('s.date_create', 'DESC');

        // Execute query and return results
        return $queryBuilder->getQuery()->getResult();
    }

    public function getServicesByAttendant(int $attendantId): array
    {
        $attendant = $this->entityManager->getRepository(Attendant::class)->find($attendantId);

        if (!$attendant) {
            throw new BadRequestException('Attendant not found');
        }

        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder
            ->select('s', 'sect', 'u')
            ->from(Service::class, 's')
            ->leftJoin('s.sector', 'sect')
            ->leftJoin('s.requester', 'u')
            ->leftJoin('s.reponsible', 'a')
            ->leftJoin('s.category', 'c')
            ->leftJoin('s.serviceType', 'st');

        if ($attendant->getFunction() === 'Admin') {
            // Admin vê: atribuídos diretamente a ele OU sem responsável e sem service_attendant OU via service_attendant
            $queryBuilder->where(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->eq('s.reponsible', ':attendantId'),
                    $queryBuilder->expr()->andX(
                        $queryBuilder->expr()->isNull('s.reponsible'),
                        sprintf(
                            'NOT EXISTS (SELECT sa1 FROM App\Entity\ServiceAttendant sa1 WHERE sa1.service = s)'
                        )
                    ),
                    sprintf(
                        'EXISTS (SELECT sa2 FROM App\Entity\ServiceAttendant sa2 WHERE sa2.service = s AND sa2.attendant = %d)',
                        $attendantId
                    )
                )
            )->setParameter('attendantId', $attendantId);
        } else {
            // Atendente comum vê: atribuídos diretamente OU via service_attendant
            $queryBuilder->where(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->eq('s.reponsible', ':attendantId'),
                    sprintf(
                        'EXISTS (SELECT sa FROM App\Entity\ServiceAttendant sa WHERE sa.service = s AND sa.attendant = %d)',
                        $attendantId
                    )
                )
            )->setParameter('attendantId', $attendantId);
        }

        $queryBuilder
            ->leftJoin('s.priorityEntity', 'pr_ord')
            ->orderBy('pr_ord.weight', 'ASC')
            ->addOrderBy('s.date_create', 'DESC');

        return $queryBuilder->getQuery()->getResult();
    }


    // Em ServiceManager.php

    public function transferTicketToSector(Service $service, int $newSectorId, string $comment): void
    {
        // Buscar novo setor
        $newSector = $this->entityManager->getRepository(Sector::class)->find($newSectorId);

        if (!$newSector) {
            throw new BadRequestException('New sector not found');
        }

        // Registrar setor anterior
        $previousSector = $service->getSector();

        // Atualizar apenas o setor do ticket
        $service->setSector($newSector);
        $service->setDateUpdate(new DateTime('now', $this->timezone));

        // Criar histórico da transferência
        $history = new ServiceHistory();
        $history->setService($service);
        $history->setStatusPrev($service->getStatus());
        $history->setStatusPost($service->getStatus());
        $history->setComment($comment . " (Transferido de '" . $previousSector->getName() . "' para '" . $newSector->getName() . "')");
        $history->setDateHistory(new DateTime('now', $this->timezone));

        $this->entityManager->persist($history);
        $this->entityManager->flush();
    }

    public function getAllServices(): array
    {
        $serviceRepository = $this->entityManager->getRepository(Service::class);

        $queryBuilder = $serviceRepository->createQueryBuilder('s')
            // Join with sector
            ->leftJoin('s.sector', 'sect')
            // Join with requester to get user information
            ->leftJoin('s.requester', 'u')
            // Join with responsible attendant
            ->leftJoin('s.reponsible', 'a')
            // Select necessary fields
            ->select('s', 'sect', 'u', 'a')
            // Order by creation date, newest first
            ->orderBy('s.date_create', 'DESC');

        return $queryBuilder->getQuery()->getResult();
    }


    public function getServicesByRequester($user, int $page = 1, int $limit = 5, array $filters = []): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder
            ->select('s', 'sect', 'u', 'a', 'h')
            ->from(Service::class, 's')
            ->leftJoin('s.sector', 'sect')
            ->leftJoin('s.requester', 'u')
            ->leftJoin('s.reponsible', 'a')
            ->leftJoin('s.histories', 'h')
            ->leftJoin('s.statusEntity', 'st')
            ->leftJoin('s.priorityEntity', 'pr')
            ->where('s.requester = :userId')
            ->setParameter('userId', $user);

        // Aplicar filtros
        if (!empty($filters['title'])) {
            $queryBuilder->andWhere('s.title LIKE :title')
                ->setParameter('title', '%' . $filters['title'] . '%');
        }

        if (!empty($filters['status'])) {
            $queryBuilder->andWhere('st.name = :statusName')
                ->setParameter('statusName', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $queryBuilder->andWhere('pr.name = :priorityName')
                ->setParameter('priorityName', $filters['priority']);
        }

        // Ordenação
        $queryBuilder->orderBy('s.date_create', 'DESC');

        // Calcular total de itens para paginação
        $totalItemsQuery = clone $queryBuilder;
        $totalItems = count($totalItemsQuery->getQuery()->getResult());

        // Aplicar paginação
        $queryBuilder->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return [
            'items' => $queryBuilder->getQuery()->getResult(),
            'total_items' => $totalItems,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($totalItems / $limit)
        ];
    }


    public function createQueryBuilderForUserTickets($user, array $filters = [])
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('s', 'sect', 'u')
            ->from(Service::class, 's')
            ->leftJoin('s.sector', 'sect')
            ->leftJoin('s.requester', 'u')
            ->leftJoin('s.statusEntity', 'st')
            ->leftJoin('s.priorityEntity', 'pr')
            ->where('s.requester = :user')
            ->setParameter('user', $user);

        // Aplicamos os filtros de forma dinâmica
        if (!empty($filters['title'])) {
            $queryBuilder->andWhere('s.title LIKE :title')
                ->setParameter('title', '%' . $filters['title'] . '%');
        }

        if (!empty($filters['status'])) {
            $queryBuilder->andWhere('st.name = :statusName')
                ->setParameter('statusName', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $queryBuilder->andWhere('pr.name = :priorityName')
                ->setParameter('priorityName', $filters['priority']);
        }

        // Adicionar filtros de data
        if (!empty($filters['start_date'])) {
            $queryBuilder->andWhere('s.date_create >= :start_date')
                ->setParameter('start_date', new \DateTime($filters['start_date'] . ' 00:00:00'));
        }

        if (!empty($filters['end_date'])) {
            $queryBuilder->andWhere('s.date_create <= :end_date')
                ->setParameter('end_date', new \DateTime($filters['end_date'] . ' 23:59:59'));
        }

        // Adicionar filtro para excluir status específico
        if (!empty($filters['exclude_status'])) {
            $queryBuilder->andWhere('st.name != :excludeStatusName')
                ->setParameter('excludeStatusName', $filters['exclude_status']);
        }

        // Filtro por sistema (projeto)
        if (!empty($filters['project_id'])) {
            $queryBuilder->andWhere('s.project = :project_id')
                ->setParameter('project_id', $filters['project_id']);
        }

        // Mantemos a ordenação padrão
        $queryBuilder->orderBy('s.date_create', 'DESC');

        return $queryBuilder;
    }

    // Em ServiceManager.php

    public function getServiceHistory(Service $service): array
    {
        return $this->entityManager->getRepository(ServiceHistory::class)
            ->createQueryBuilder('sh')
            ->where('sh.service = :service')
            ->setParameter('service', $service)
            ->orderBy('sh.date_history', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Auto-conclui tickets RESOLVED há mais de 5 dias
     */
    public function autoConcludeOldResolvedTickets(): int
    {
        // Data limite: 5 dias atrás
        $limitDate = new DateTime('now', $this->timezone);
        $limitDate->modify('-5 days');

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
                $ticket->setStatusEntity($this->resolveStatus('CONCLUDED'));
                $ticket->setDateConclusion(new DateTime('now', $this->timezone));
                $ticket->setDateUpdate(new DateTime('now', $this->timezone));

                // Criar histórico da auto-conclusão
                $history = new ServiceHistory();
                $history->setService($ticket);
                $history->setStatusPrev('RESOLVED');
                $history->setStatusPost('CONCLUDED');
                $history->setComment('Ticket automaticamente concluído após 5 dias sem resposta do usuário.');
                $history->setDateHistory(new DateTime('now', $this->timezone));
                $history->setResponsible($ticket->getReponsible());

                $this->entityManager->persist($history);
                $concludedCount++;
                
            } catch (\Exception $e) {
                // Log do erro mas continua o processo
                error_log("Erro ao auto-concluir ticket #{$ticket->getId()}: " . $e->getMessage());
            }
        }

        // Salvar todas as alterações
        $this->entityManager->flush();

        return $concludedCount;
    }

    /**
     * Adiciona comentário do usuário como histórico
     */
    public function addUserComment(Service $service, string $comment, User $user): void
    {
        $currentStatus = $service->getStatus();
        
        // Criar histórico do comentário do usuário
        $history = new ServiceHistory();
        $history->setService($service);
        $history->setStatusPrev($currentStatus);
        $history->setStatusPost($currentStatus); // Status permanece o mesmo
        $history->setComment('Solicitante: ' . $user->getName() . ' - ' . $comment);
        $history->setDateHistory(new DateTime('now', $this->timezone));
        
        // Como é um comentário do usuário, não definimos responsável (fica null)
        // Isso diferenciará de comentários de atendentes
        
        $this->entityManager->persist($history);

        // Atualizar data de modificação do service
        $service->setDateUpdate(new DateTime('now', $this->timezone));

        $this->entityManager->flush();
    }

    // -------------------------------------------------------------------------
    // Helpers de resolução de domínio
    // -------------------------------------------------------------------------

    /**
     * Resolve um Status pelo nome (string) → entidade Status.
     * Lança BadRequestException se não encontrado.
     */
    public function resolveStatus(string $name): Status
    {
        $status = $this->entityManager->getRepository(Status::class)->findOneBy(['name' => $name]);
        if (!$status) {
            throw new BadRequestException("Status '{$name}' não encontrado na tabela de domínio.");
        }
        return $status;
    }

    /**
     * Resolve uma Priority pelo nome (string) → entidade Priority.
     * Lança BadRequestException se não encontrado.
     */
    public function resolvePriority(string $name): Priority
    {
        $priority = $this->entityManager->getRepository(Priority::class)->findOneBy(['name' => $name]);
        if (!$priority) {
            // Fallback: NORMAL
            $priority = $this->entityManager->getRepository(Priority::class)->findOneBy(['name' => 'NORMAL']);
        }
        return $priority;
    }

    /**
     * Resolve uma Tag pelo nome (string) → entidade Tag (ou null).
     */
    public function resolveTag(string $name): ?Tag
    {
        return $this->entityManager->getRepository(Tag::class)->findOneBy(['name' => $name]);
    }
}
