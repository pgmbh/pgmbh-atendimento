<?php

namespace App\Command;

use App\Entity\Attendant;
use App\Entity\Project;
use App\Entity\Service;
use App\Entity\ServiceAttendant;
use App\Entity\ServiceHistory;
use App\Entity\User;
use App\Repository\AttendantRepository;
use App\Repository\ProjectRepository;
use App\Repository\SectorRepository;
use App\Repository\UserRepository;
use DateTime;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:import-roadmap',
    description: 'Importa demandas da lista "SUPP Roadmap" do ClickUp para o banco de dados',
)]
class ImportRoadmapCommand extends Command
{
    /**
     * Normaliza e-mails divergentes do ClickUp para os e-mails reais no banco.
     * Adicione aliases aqui se outros e-mails divergirem entre ambientes.
     */
    private const EMAIL_ALIASES = [
        'cristhian.ramos@edu.pbh.gov.br' => 'cristhian.r@pbh.gov.br',
    ];

    /**
     * Atendentes cujas demandas pertencem ao setor 'Infra'.
     * Por padrão todas as demandas vão para 'Dev'.
     */
    private const INFRA_ASSIGNEE_EMAILS = [
        'danilo.cesar@pbh.gov.br',
    ];

    private const DEFAULT_PASSWORD = 'teste123';
    private const BATCH_SIZE = 50;

    /** @var array<string, User> */
    private array $userCache = [];

    /** @var array<string, Attendant> */
    private array $attendantCache = [];

    public function __construct(
        private readonly EntityManagerInterface      $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly SectorRepository            $sectorRepository,
        private readonly UserRepository              $userRepository,
        private readonly AttendantRepository         $attendantRepository,
        private readonly ProjectRepository           $projectRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'path',
            InputArgument::OPTIONAL,
            'Caminho para o arquivo all_tasks.json',
            '%kernel.project_dir%/../arquivos/clickup/all_tasks.json'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Importação SUPP Roadmap → banco de dados');

        // --- Resolve path (substitui placeholder do kernel se necessário) ---
        $path = $input->getArgument('path');
        if (str_contains($path, '%kernel.project_dir%')) {
            $projectDir = $this->getApplication()?->getKernel()->getProjectDir() ?? '';
            $path = str_replace('%kernel.project_dir%', $projectDir, $path);
        }

        if (!file_exists($path)) {
            $io->error("Arquivo não encontrado: {$path}");
            return Command::FAILURE;
        }

        $allTasks = json_decode(file_get_contents($path), true);
        if (!is_array($allTasks)) {
            $io->error('Falha ao decodificar JSON.');
            return Command::FAILURE;
        }

        $roadmapTasks = array_values(array_filter(
            $allTasks,
            fn(array $t) => ($t['list']['name'] ?? '') === 'SUPP Roadmap'
        ));
        $io->info(sprintf('%d tarefas encontradas na lista "SUPP Roadmap".', count($roadmapTasks)));

        // --- Resolve setores ---
        $sectorDev   = $this->sectorRepository->findOneBy(['name' => 'Dev']);
        $sectorInfra = $this->sectorRepository->findOneBy(['name' => 'Infra']);
        if (!$sectorDev || !$sectorInfra) {
            $io->error('Setores "Dev" e/ou "Infra" não encontrados. Execute as fixtures primeiro.');
            return Command::FAILURE;
        }

        // --- Resolve projeto SUPP ---
        $suppProject = $this->entityManager
            ->createQueryBuilder()
            ->select('p')
            ->from(Project::class, 'p')
            ->where('p.acronym LIKE :q OR p.name LIKE :q')
            ->setParameter('q', '%SUPP%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
        if (!$suppProject) {
            $io->warning('Projeto com "SUPP" não encontrado — tickets serão importados sem sistema vinculado.');
        }

        $tz = new DateTimeZone('America/Sao_Paulo');

        $imported   = 0;
        $skipped    = 0;
        $usersCreated     = 0;
        $attendantsCreated = 0;
        $batch      = 0;

        $io->progressStart(count($roadmapTasks));

        foreach ($roadmapTasks as $task) {
            $clickupId = $task['id'] ?? '';
            $marker    = "[ClickUp #{$clickupId}]";

            // --- Idempotência: pula se já importado ---
            $existing = $this->entityManager
                ->createQueryBuilder()
                ->select('s.id')
                ->from(Service::class, 's')
                ->where('s.description LIKE :marker')
                ->setParameter('marker', '%' . $marker . '%')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if ($existing !== null) {
                $skipped++;
                $io->progressAdvance();
                continue;
            }

            // --- Setor: Infra se algum assignee for da lista INFRA ---
            $assigneeEmails = array_map(
                fn(array $a) => $this->normalizeEmail($a['email'] ?? ''),
                $task['assignees'] ?? []
            );
            $isInfra = (bool) array_intersect(self::INFRA_ASSIGNEE_EMAILS, $assigneeEmails);
            $sector  = $isInfra ? $sectorInfra : $sectorDev;

            // --- Título (max 100 chars) ---
            $fullName = $task['name'] ?? '(sem título)';
            $title    = mb_strlen($fullName) > 100 ? mb_substr($fullName, 0, 97) . '...' : $fullName;

            // --- Descrição ---
            $descParts = [];
            $rawDesc   = trim($task['description'] ?? $task['text_content'] ?? '');
            if ($rawDesc !== '') {
                $descParts[] = $rawDesc;
            }
            if (mb_strlen($fullName) > 100) {
                $descParts[] = "Título completo: {$fullName}";
            }
            if (!empty($task['url'])) {
                $descParts[] = "ClickUp: {$task['url']}";
            }
            $descParts[] = $marker;
            $description = implode("\n\n", $descParts) ?: $fullName . "\n\n" . $marker;

            // --- Status ---
            $clickupStatus = strtolower($task['status']['status'] ?? '');
            $status = match (true) {
                $clickupStatus === 'closed'              => 'CONCLUDED',
                $clickupStatus === 'cancelado'           => 'CANCELADO',
                $clickupStatus === 'em andamento'        => 'IN_PROGRESS',
                $clickupStatus === 'pendente decisão'    => 'OPEN',
                $clickupStatus === 'suspenso'            => 'OPEN',
                $clickupStatus === 'backlog'             => 'NOVO',
                default                                  => 'NOVO',
            };

            // --- Prioridade ---
            $clickupPriority = strtolower($task['priority']['priority'] ?? '');
            $priority = match ($clickupPriority) {
                'low'    => Service::PRIORITY_LOW,
                'normal' => Service::PRIORITY_NORMAL,
                'high'   => Service::PRIORITY_HIGH,
                'urgent' => Service::PRIORITY_URGENT,
                default  => Service::PRIORITY_NORMAL,
            };

            // --- Datas ---
            $dateCreate     = $this->epochMsToDateTime((int) ($task['date_created'] ?? 0), $tz);
            $dateUpdate     = $task['date_updated']   ? $this->epochMsToDateTime((int) $task['date_updated'], $tz) : null;
            $dateConclusion = null;
            if (in_array($status, ['CONCLUDED', 'CANCELADO'], true)) {
                $raw = $task['date_closed'] ?? $task['date_done'] ?? null;
                if ($raw) {
                    $dateConclusion = $this->epochMsToDateTime((int) $raw, $tz);
                }
            }
            $deadline = $task['due_date'] ? $this->epochMsToDateTime((int) $task['due_date'], $tz) : null;

            // --- Pessoas ---
            $creatorEmail = $this->normalizeEmail($task['creator']['email'] ?? '');
            $creatorName  = $task['creator']['username'] ?? 'Usuário ClickUp';

            [$requester, $newUsers1] = $this->resolveUser($creatorEmail, $creatorName);
            $usersCreated += $newUsers1;

            $responsible = null;
            if (!empty($task['assignees'])) {
                $firstAssignee = $task['assignees'][0];
                $assigneeEmail = $this->normalizeEmail($firstAssignee['email'] ?? '');
                $assigneeName  = $firstAssignee['username'] ?? 'Atendente ClickUp';
                [$responsible, $newUsers2, $newAttendants] = $this->resolveAttendant(
                    $assigneeEmail, $assigneeName, $sectorDev, $sectorInfra
                );
                $usersCreated      += $newUsers2;
                $attendantsCreated += $newAttendants;
            }

            // --- Cria Service ---
            $service = new Service();
            $service
                ->setTitle($title)
                ->setDescription($description)
                ->setStatus($status)
                ->setPriority($priority)
                ->setSector($sector)
                ->setRequester($requester)
                ->setReponsible($responsible)
                ->setDateCreate($dateCreate)
                ->setDateUpdate($dateUpdate)
                ->setDateConclusion($dateConclusion)
                ->setDeadline($deadline)
                ->setCreatedByAdmin(false)
                ->setProject($suppProject);

            $this->entityManager->persist($service);

            // --- Cria ServiceAttendant para assignees extras (além do responsible) ---
            foreach (array_slice($task['assignees'] ?? [], 1) as $extraAssignee) {
                $extraEmail = $this->normalizeEmail($extraAssignee['email'] ?? '');
                $extraName  = $extraAssignee['username'] ?? 'Atendente ClickUp';
                [$extraAttendant, $newUsersX, $newAttendantsX] = $this->resolveAttendant(
                    $extraEmail, $extraName, $sectorDev, $sectorInfra
                );
                $usersCreated      += $newUsersX;
                $attendantsCreated += $newAttendantsX;

                $sa = new ServiceAttendant();
                $sa->setService($service);
                $sa->setAttendant($extraAttendant);
                $sa->setAssignedBy($responsible ?? $extraAttendant);
                $sa->setAssignedAt($dateCreate);
                $this->entityManager->persist($sa);
            }

            // --- Cria ServiceHistory inicial ---
            $history = new ServiceHistory();
            $history
                ->setService($service)
                ->setStatusPrev('Nenhum')
                ->setStatusPost($status)
                ->setComment('Importado do ClickUp (SUPP Roadmap)')
                ->setDateHistory($dateCreate)
                ->setType('STATUS_CHANGE')
                ->setResponsible($responsible);

            $this->entityManager->persist($history);

            $imported++;
            $batch++;

            if ($batch >= self::BATCH_SIZE) {
                $this->entityManager->flush();
                $batch = 0;
            }

            $io->progressAdvance();
        }

        $this->entityManager->flush();
        $io->progressFinish();

        $io->success(sprintf(
            "Concluído!\n  Importadas : %d\n  Ignoradas  : %d (já existiam)\n  Users criados     : %d\n  Atendentes criados: %d",
            $imported,
            $skipped,
            $usersCreated,
            $attendantsCreated
        ));

        return Command::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function normalizeEmail(string $email): string
    {
        return self::EMAIL_ALIASES[$email] ?? $email;
    }

    /**
     * Resolve ou cria um User pelo e-mail.
     * Retorna [User, quantos foram criados].
     *
     * @return array{0: User, 1: int}
     */
    private function resolveUser(string $email, string $name): array
    {
        if (!$email) {
            // fallback: retorna o primeiro usuário disponível
            $user = $this->userRepository->findOneBy([]);
            return [$user, 0];
        }

        if (isset($this->userCache[$email])) {
            return [$this->userCache[$email], 0];
        }

        $user = $this->userRepository->findOneBy(['email' => $email]);
        if ($user) {
            $this->userCache[$email] = $user;
            return [$user, 0];
        }

        // Cria novo User
        $user = new User();
        $user->setName(mb_substr($name, 0, 50));
        $user->setEmail($email);
        $user->setRoles(['ROLE_USER']);
        $user->setIsAttendant(false);
        $hashedPassword = $this->passwordHasher->hashPassword($user, self::DEFAULT_PASSWORD);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->userCache[$email] = $user;

        return [$user, 1];
    }

    /**
     * Resolve ou cria um Attendant (e seu User backing) pelo e-mail.
     * Retorna [Attendant, usersCreated, attendantsCreated].
     *
     * @return array{0: Attendant, 1: int, 2: int}
     */
    private function resolveAttendant(
        string $email,
        string $name,
        \App\Entity\Sector $sectorDev,
        \App\Entity\Sector $sectorInfra,
    ): array {
        if (isset($this->attendantCache[$email])) {
            return [$this->attendantCache[$email], 0, 0];
        }

        // Procura Attendant via User (join)
        $attendant = $this->entityManager
            ->createQueryBuilder()
            ->select('a')
            ->from(Attendant::class, 'a')
            ->join('a.user', 'u')
            ->where('u.email = :email')
            ->setParameter('email', $email)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($attendant) {
            $this->attendantCache[$email] = $attendant;
            return [$attendant, 0, 0];
        }

        // Garante que o User existe e é marcado como atendente
        [$user, $userCreated] = $this->resolveUser($email, $name);
        if (!$user->isIsAttendant()) {
            $user->setIsAttendant(true);
            $user->setRoles(['ROLE_USER', 'ROLE_ATTENDANT']);
        }

        // Setor do atendente
        $attendantSector = in_array($email, self::INFRA_ASSIGNEE_EMAILS, true)
            ? $sectorInfra
            : $sectorDev;

        $attendant = new Attendant();
        $attendant->setName(mb_substr($name, 0, 100));
        $attendant->setFunction('Atendente');
        $attendant->setStatus('AVAILABLE');
        $attendant->setSector($attendantSector);
        $attendant->setUser($user);

        $this->entityManager->persist($attendant);
        $this->attendantCache[$email] = $attendant;

        return [$attendant, $userCreated, 1];
    }

    private function epochMsToDateTime(int $epochMs, DateTimeZone $tz): DateTime
    {
        $dt = new DateTime('@' . intdiv($epochMs, 1000));
        $dt->setTimezone($tz);
        return $dt;
    }
}
