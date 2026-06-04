<?php

namespace App\DataFixtures;

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
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Importa atividades do Cronograma.xlsx (projeto LJPGM) e do all_tasks.json (ClickUp).
 *
 * Uso em banco já existente (AppFixtures já executado):
 *   php bin/console doctrine:fixtures:load --group=roadmap --append
 *
 * Uso em banco zerado (setup completo):
 *   php bin/console doctrine:fixtures:load
 */
class RoadmapFixtures extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['roadmap'];
    }

    /**
     * Normaliza e-mails divergentes do ClickUp para os e-mails reais no banco.
     */
    private const EMAIL_ALIASES = [
        'cristhian.ramos@edu.pbh.gov.br' => 'cristhian.r@pbh.gov.br',
    ];

    /**
     * Atendentes cujas demandas pertencem ao setor 'Infra'.
     * Por padrão todas as demandas vão para 'Dev'.
     */
    private const INFRA_EMAILS = [
        'danilo.cesar@pbh.gov.br',
    ];

    private const DEFAULT_PASSWORD = 'teste123';
    private const BATCH_SIZE = 50;

    /** @var array<string, User> */
    private array $userCache = [];

    /** @var array<string, Attendant> */
    private array $attendantCache = [];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly SectorRepository            $sectorRepository,
        private readonly ProjectRepository           $projectRepository,
        private readonly AttendantRepository         $attendantRepository,
        private readonly UserRepository              $userRepository,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        assert($manager instanceof EntityManagerInterface);

        // -----------------------------------------------------------------------
        // Resolve setores base
        // -----------------------------------------------------------------------
        $sectorDev   = $this->sectorRepository->findOneBy(['name' => 'Dev']);
        $sectorInfra = $this->sectorRepository->findOneBy(['name' => 'Infra']);

        if (!$sectorDev || !$sectorInfra) {
            echo "[RoadmapFixtures] ERRO: Setores 'Dev' e/ou 'Infra' não encontrados. Execute AppFixtures primeiro.\n";
            return;
        }

        // -----------------------------------------------------------------------
        // Resolve Rafael (responsável padrão do Cronograma e criador de projetos)
        // -----------------------------------------------------------------------
        $attendantRafael = $this->attendantRepository
            ->createQueryBuilder('a')
            ->join('a.user', 'u')
            ->where('u.email = :email')
            ->setParameter('email', 'rafael.assumpcao@pbh.gov.br')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$attendantRafael) {
            echo "[RoadmapFixtures] ERRO: Atendente Rafael não encontrado. Execute AppFixtures primeiro.\n";
            return;
        }

        $userRafael = $attendantRafael->getUser();

        // Preenche os caches com Rafael para evitar lookups redundantes
        $this->userCache[$userRafael->getEmail()]             = $userRafael;
        $this->attendantCache[$userRafael->getEmail()]        = $attendantRafael;

        $tz = new DateTimeZone('America/Sao_Paulo');

        // -----------------------------------------------------------------------
        // FONTE 1 — Cronograma.xlsx
        // -----------------------------------------------------------------------
        $this->importCronograma($manager, $sectorDev, $attendantRafael, $userRafael, $tz);

        // -----------------------------------------------------------------------
        // FONTE 2 — all_tasks.json
        // -----------------------------------------------------------------------
        $this->importClickUp($manager, $sectorDev, $sectorInfra, $attendantRafael, $userRafael, $tz);

        // Flush final para qualquer sobra
        $manager->flush();
        echo "[RoadmapFixtures] Importação concluída.\n";
    }

    // =========================================================================
    // FONTE 1 — Cronograma.xlsx
    // =========================================================================

    private function importCronograma(
        EntityManagerInterface $manager,
        \App\Entity\Sector $sectorDev,
        Attendant $attendantRafael,
        User $userRafael,
        DateTimeZone $tz,
    ): void {
        /** @var array<array{row:int,title:string,observacao:string,status_raw:string,date_start:string|null,date_end:string|null}> $rows */
        $rows = require __DIR__ . '/Data/CronogramaData.php';

        // --- Resolve/cria projeto LJPGM ---
        $projectLjpgm = $this->projectRepository->findOneBy(['acronym' => 'LJPGM']);
        if (!$projectLjpgm) {
            $projectLjpgm = new Project();
            $projectLjpgm->setName('LJPGM');
            $projectLjpgm->setAcronym('LJPGM');
            $projectLjpgm->setDateStart(new DateTime('today'));
            $projectLjpgm->setCreatedAt(new DateTime());
            $projectLjpgm->setCreatedBy($attendantRafael);
            $manager->persist($projectLjpgm);
            $manager->flush();
            echo "[RoadmapFixtures] Projeto LJPGM criado.\n";
        }

        $imported = 0;
        $skipped  = 0;
        $batch    = 0;

        foreach ($rows as $row) {
            $marker = "[Crono #{$row['row']}]";

            $existing = $manager
                ->createQueryBuilder()
                ->select('s.id')->from(Service::class, 's')
                ->where('s.description LIKE :m')->setParameter('m', '%' . $marker . '%')
                ->setMaxResults(1)->getQuery()->getOneOrNullResult();

            if ($existing !== null) { $skipped++; continue; }

            $titleRaw = $row['title'];
            $title    = mb_strlen($titleRaw) > 100 ? mb_substr($titleRaw, 0, 100) : $titleRaw;

            $descParts = [];
            if (mb_strlen($titleRaw) > 100) $descParts[] = 'Título completo: ' . $titleRaw;
            if ($row['observacao'] !== '')   $descParts[] = $row['observacao'];
            $descParts[]  = $marker;
            $description  = implode("\n\n", $descParts);

            $dateCreate = $row['date_start']
                ? new DateTime($row['date_start'], $tz)
                : new DateTime('now', $tz);

            $dateConclusion = $row['date_end'] ? new DateTime($row['date_end'], $tz) : null;

            $service = new Service();
            $service
                ->setTitle($title)
                ->setDescription($description)
                ->setStatus($this->mapCronoStatus($row['status_raw']))
                ->setPriority(Service::PRIORITY_NORMAL)
                ->setSector($sectorDev)
                ->setRequester($userRafael)
                ->setReponsible($attendantRafael)
                ->setDateCreate($dateCreate)
                ->setDateUpdate(null)
                ->setDateConclusion($dateConclusion)
                ->setDeadline($dateConclusion)
                ->setCreatedByAdmin(true)
                ->setProject($projectLjpgm);

            $manager->persist($service);

            $history = new ServiceHistory();
            $history
                ->setService($service)
                ->setStatusPrev('Nenhum')
                ->setStatusPost($service->getStatus())
                ->setComment('Importado automaticamente')
                ->setDateHistory($dateCreate)
                ->setType('STATUS_CHANGE')
                ->setResponsible(null);

            $manager->persist($history);

            $imported++;
            if (++$batch >= self::BATCH_SIZE) { $manager->flush(); $batch = 0; }
        }

        $manager->flush();
        echo "[RoadmapFixtures][Cronograma] Importadas: {$imported} | Ignoradas (já existiam): {$skipped}\n";
    }

    // =========================================================================
    // FONTE 2 — all_tasks.json
    // =========================================================================

    private function importClickUp(
        EntityManagerInterface $manager,
        \App\Entity\Sector $sectorDev,
        \App\Entity\Sector $sectorInfra,
        Attendant $attendantRafael,
        User $userRafael,
        DateTimeZone $tz,
    ): void {
        /** @var array<array{id:string,name:string,description:string,url:string,status:string,status_type:string,priority:string,date_created:string|null,date_updated:string|null,date_closed:string|null,date_done:string|null,due_date:string|null,creator_email:string,creator_name:string,assignees:array}> $allTasks */
        $allTasks = require __DIR__ . '/Data/ClickUpData.php';

        echo "[RoadmapFixtures][ClickUp] " . count($allTasks) . " tarefas no dataset.\n";

        // --- Resolve projeto SUPP ---
        $suppProject = $manager
            ->createQueryBuilder()
            ->select('p')
            ->from(Project::class, 'p')
            ->where('p.acronym LIKE :q OR p.name LIKE :q')
            ->setParameter('q', '%SUPP%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$suppProject) {
            $suppProject = new Project();
            $suppProject->setName('SUPP Atendimento');
            $suppProject->setAcronym('SUPP');
            $suppProject->setDateStart(new DateTime('today'));
            $suppProject->setCreatedAt(new DateTime());
            $suppProject->setCreatedBy($attendantRafael);
            $manager->persist($suppProject);
            $manager->flush();
            echo "[RoadmapFixtures] Projeto SUPP criado.\n";
        }

        $imported          = 0;
        $skipped           = 0;
        $usersCreated      = 0;
        $attendantsCreated = 0;
        $batch             = 0;

        foreach ($allTasks as $task) {
            $marker = "[ClickUp #{$task['id']}]";

            $existing = $manager
                ->createQueryBuilder()
                ->select('s.id')->from(Service::class, 's')
                ->where('s.description LIKE :m')->setParameter('m', '%' . $marker . '%')
                ->setMaxResults(1)->getQuery()->getOneOrNullResult();

            if ($existing !== null) { $skipped++; continue; }

            // Setor
            $assigneeEmails = array_map(fn($a) => $this->normalizeEmail($a['email'] ?? ''), $task['assignees']);
            $sector = !empty(array_intersect(self::INFRA_EMAILS, $assigneeEmails)) ? $sectorInfra : $sectorDev;

            // Título
            $fullName = $task['name'] ?: '(sem título)';
            $title    = mb_strlen($fullName) > 100 ? mb_substr($fullName, 0, 100) : $fullName;

            // Descrição
            $descParts = [];
            if ($task['description'] !== '') $descParts[] = $task['description'];
            if (mb_strlen($fullName) > 100)  $descParts[] = 'Título completo: ' . $fullName;
            if ($task['url'] !== '')          $descParts[] = 'ClickUp: ' . $task['url'];
            $descParts[] = $marker;
            $description = implode("\n\n", $descParts) ?: ($fullName . "\n\n" . $marker);

            // Status / Prioridade
            $status   = $this->mapClickUpStatus(strtolower($task['status']), strtolower($task['status_type']));
            $priority = match (strtolower($task['priority'])) {
                'low'    => Service::PRIORITY_LOW,
                'high'   => Service::PRIORITY_HIGH,
                'urgent' => Service::PRIORITY_URGENT,
                default  => Service::PRIORITY_NORMAL,
            };

            // Datas
            $dateCreate = $this->epochMsToDateTime((int) ($task['date_created'] ?? 0), $tz);
            $dateUpdate = $task['date_updated'] ? $this->epochMsToDateTime((int) $task['date_updated'], $tz) : null;
            $dateConclusion = null;
            if (in_array($status, ['CONCLUDED', 'CANCELADO'], true)) {
                $raw = $task['date_closed'] ?? $task['date_done'] ?? null;
                if ($raw) $dateConclusion = $this->epochMsToDateTime((int) $raw, $tz);
            }
            $deadline = $task['due_date'] ? $this->epochMsToDateTime((int) $task['due_date'], $tz) : null;

            // Pessoas
            [$requester, $nu1] = $this->resolveUser($task['creator_email'], $task['creator_name'], $manager);
            $usersCreated += $nu1;

            $responsible = null;
            if (!empty($task['assignees'])) {
                $first = $task['assignees'][0];
                [$responsible, $nu2, $na2] = $this->resolveAttendant(
                    $this->normalizeEmail($first['email']), $first['username'], $sectorDev, $sectorInfra, $manager
                );
                $usersCreated += $nu2; $attendantsCreated += $na2;
            }

            $service = new Service();
            $service
                ->setTitle($title)->setDescription($description)
                ->setStatus($status)->setPriority($priority)
                ->setSector($sector)->setRequester($requester)->setReponsible($responsible)
                ->setDateCreate($dateCreate)->setDateUpdate($dateUpdate)
                ->setDateConclusion($dateConclusion)->setDeadline($deadline)
                ->setCreatedByAdmin(false)->setProject($suppProject);

            $manager->persist($service);

            foreach (array_slice($task['assignees'], 1) as $extra) {
                [$extraAttendant, $nuX, $naX] = $this->resolveAttendant(
                    $this->normalizeEmail($extra['email']), $extra['username'], $sectorDev, $sectorInfra, $manager
                );
                $usersCreated += $nuX; $attendantsCreated += $naX;

                $sa = new ServiceAttendant();
                $sa->setService($service)->setAttendant($extraAttendant)
                   ->setAssignedBy($responsible ?? $extraAttendant)->setAssignedAt($dateCreate);
                $manager->persist($sa);
            }

            $history = new ServiceHistory();
            $history->setService($service)->setStatusPrev('Nenhum')->setStatusPost($status)
                    ->setComment('Importado automaticamente')->setDateHistory($dateCreate)
                    ->setType('STATUS_CHANGE')->setResponsible(null);
            $manager->persist($history);

            $imported++;
            if (++$batch >= self::BATCH_SIZE) { $manager->flush(); $batch = 0; }
        }

        $manager->flush();

        echo "[RoadmapFixtures][ClickUp] Importadas: {$imported} | Ignoradas: {$skipped} | Users criados: {$usersCreated} | Atendentes criados: {$attendantsCreated}\n";
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function normalizeEmail(string $email): string
    {
        return self::EMAIL_ALIASES[$email] ?? $email;
    }

    private function mapCronoStatus(string $statusStr): string
    {
        return match (true) {
            $statusStr === 'Concluído'                => 'CONCLUDED',
            $statusStr === 'Aguardando merge no SUPP' => 'OPEN',
            $statusStr === 'Reportar ao SUPP'         => 'OPEN',
            default                                   => 'CONCLUDED',
        };
    }

    private function mapClickUpStatus(string $clickupStatus, string $statusType): string
    {
        return match (true) {
            $statusType === 'closed'                     => 'CONCLUDED',
            $clickupStatus === 'closed'                  => 'CONCLUDED',
            $clickupStatus === 'complete'                => 'CONCLUDED',
            $clickupStatus === 'cancelado'               => 'CANCELADO',
            $clickupStatus === 'em andamento'            => 'IN_PROGRESS',
            $clickupStatus === 'em desenvolvimento'      => 'IN_PROGRESS',
            $clickupStatus === 'pendente decisão'        => 'OPEN',
            $clickupStatus === 'suspenso'                => 'OPEN',
            $clickupStatus === 'homologação'             => 'OPEN',
            $clickupStatus === 'backlog'                 => 'NOVO',
            $clickupStatus === 'product backlog'         => 'NOVO',
            default                                      => 'NOVO',
        };
    }

    /**
     * Converte epoch em milissegundos para DateTime.
     */
    private function epochMsToDateTime(int $epochMs, DateTimeZone $tz): DateTime
    {
        $dt = new DateTime('@' . intdiv($epochMs, 1000));
        $dt->setTimezone($tz);
        return $dt;
    }

    /**
     * Resolve ou cria um User pelo e-mail.
     * Retorna [User, quantos foram criados].
     *
     * @return array{0: User, 1: int}
     */
    private function resolveUser(string $email, string $name, EntityManagerInterface $manager): array
    {
        $email = $this->normalizeEmail($email);

        if (!$email) {
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
        $user->setPassword($this->passwordHasher->hashPassword($user, self::DEFAULT_PASSWORD));

        $manager->persist($user);
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
        EntityManagerInterface $manager,
    ): array {
        $email = $this->normalizeEmail($email);

        if (isset($this->attendantCache[$email])) {
            return [$this->attendantCache[$email], 0, 0];
        }

        // Procura Attendant via User (join)
        $attendant = $this->attendantRepository
            ->createQueryBuilder('a')
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
        [$user, $userCreated] = $this->resolveUser($email, $name, $manager);
        if (!$user->isIsAttendant()) {
            $user->setIsAttendant(true);
            $user->setRoles(['ROLE_USER', 'ROLE_ATTENDANT']);
        }

        // Setor do atendente
        $attendantSector = in_array($email, self::INFRA_EMAILS, true)
            ? $sectorInfra
            : $sectorDev;

        $attendant = new Attendant();
        $attendant->setName(mb_substr($name, 0, 100));
        $attendant->setFunction('Atendente');
        $attendant->setStatus('AVAILABLE');
        $attendant->setSector($attendantSector);
        $attendant->setUser($user);

        $manager->persist($attendant);
        $this->attendantCache[$email] = $attendant;

        return [$attendant, $userCreated, 1];
    }
}
