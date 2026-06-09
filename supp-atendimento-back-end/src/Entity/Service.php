<?php

namespace App\Entity;

use App\Repository\ServiceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: ServiceRepository::class)]
class Service
{
    // Constantes de prioridade mantidas por compatibilidade
    public const PRIORITY_LOW    = 'BAIXA';
    public const PRIORITY_NORMAL = 'NORMAL';
    public const PRIORITY_HIGH   = 'ALTA';
    public const PRIORITY_URGENT = 'URGENTE';

    public const VALID_PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    /** FK para tabela status (substitui a coluna status VARCHAR) */
    #[ORM\ManyToOne(targetEntity: Status::class)]
    #[ORM\JoinColumn(name: 'status_id', nullable: false)]
    private ?Status $statusEntity = null;

    /** FK para tabela priority (substitui a coluna priority VARCHAR) */
    #[ORM\ManyToOne(targetEntity: Priority::class)]
    #[ORM\JoinColumn(name: 'priority_id', nullable: false)]
    private ?Priority $priorityEntity = null;

    #[ORM\ManyToOne(inversedBy: 'id_service')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Sector $sector = null;

    #[ORM\ManyToOne(inversedBy: 'id_services')]
    private ?User $requester = null;

    #[ORM\ManyToOne]
    private ?Attendant $reponsible = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $date_create = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $date_update = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $date_conclusion = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deadline = null;

    #[ORM\OneToMany(mappedBy: 'service', targetEntity: ServiceHistory::class, orphanRemoval: true)]
    private Collection $histories;

    #[ORM\OneToMany(mappedBy: 'service', targetEntity: ServiceAttachment::class, cascade: ['persist', 'remove'])]
    private Collection $attachments;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $createdByAdmin = false;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'created_by_admin_id', referencedColumnName: 'id', nullable: true)]
    private ?Attendant $createdByAdminAttendant = null;

    #[ORM\ManyToOne(inversedBy: 'services')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Category $category = null;

    #[ORM\ManyToOne(inversedBy: 'services')]
    #[ORM\JoinColumn(nullable: true)]
    private ?ServiceType $serviceType = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Project $project = null;

    /** Etiquetas (muitos-para-muitos com Tag) */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'services')]
    #[ORM\JoinTable(name: 'service_tag')]
    private Collection $tags;

    public function __construct()
    {
        $this->attachments = new ArrayCollection();
        $this->histories   = new ArrayCollection();
        $this->tags        = new ArrayCollection();
    }

    // -------------------------------------------------------------------------
    // Acessores de compatibilidade: retornam string, mantendo a API estável
    // -------------------------------------------------------------------------

    /** Retorna o nome do status como string (ex: "CONCLUDED"). Compatível com código legado. */
    public function getStatus(): ?string
    {
        return $this->statusEntity?->getName();
    }

    /** Retorna o nome da prioridade como string (ex: "ALTA"). Compatível com código legado. */
    public function getPriority(): ?string
    {
        return $this->priorityEntity?->getName();
    }

    // -------------------------------------------------------------------------
    // Getters/setters FK (usados internamente e nos services)
    // -------------------------------------------------------------------------

    public function getStatusEntity(): ?Status
    {
        return $this->statusEntity;
    }

    public function setStatusEntity(?Status $status): static
    {
        $this->statusEntity = $status;
        return $this;
    }

    public function getPriorityEntity(): ?Priority
    {
        return $this->priorityEntity;
    }

    public function setPriorityEntity(?Priority $priority): static
    {
        $this->priorityEntity = $priority;
        return $this;
    }

    // -------------------------------------------------------------------------
    // Tags
    // -------------------------------------------------------------------------

    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }
        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);
        return $this;
    }

    // -------------------------------------------------------------------------
    // Restante dos getters/setters
    // -------------------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getSector(): ?Sector
    {
        return $this->sector;
    }

    public function setSector(?Sector $sector): static
    {
        $this->sector = $sector;
        return $this;
    }

    public function getRequester(): ?User
    {
        return $this->requester;
    }

    public function setRequester(?User $requester): static
    {
        $this->requester = $requester;
        return $this;
    }

    public function getReponsible(): ?Attendant
    {
        return $this->reponsible;
    }

    public function setReponsible(?Attendant $reponsible): static
    {
        $this->reponsible = $reponsible;
        return $this;
    }

    public function getDateCreate(): ?\DateTimeInterface
    {
        return $this->date_create;
    }

    public function setDateCreate(\DateTimeInterface $date_create): static
    {
        $this->date_create = $date_create;
        return $this;
    }

    public function getDateUpdate(): ?\DateTimeInterface
    {
        return $this->date_update;
    }

    public function setDateUpdate(?\DateTimeInterface $date_update): static
    {
        $this->date_update = $date_update;
        return $this;
    }

    public function getDateConclusion(): ?\DateTimeInterface
    {
        return $this->date_conclusion;
    }

    public function setDateConclusion(?\DateTimeInterface $date_conclusion): static
    {
        $this->date_conclusion = $date_conclusion;
        return $this;
    }

    public function getHistories(): Collection
    {
        return $this->histories;
    }

    public function addHistory(ServiceHistory $history): self
    {
        if (!$this->histories->contains($history)) {
            $this->histories->add($history);
            $history->setService($this);
        }
        return $this;
    }

    public function removeHistory(ServiceHistory $history): self
    {
        if ($this->histories->removeElement($history)) {
            if ($history->getService() === $this) {
                $history->setService(null);
            }
        }
        return $this;
    }

    public function getAttachments(): Collection
    {
        return $this->attachments;
    }

    public function addAttachment(ServiceAttachment $attachment): self
    {
        if (!$this->attachments->contains($attachment)) {
            $this->attachments->add($attachment);
            $attachment->setService($this);
        }
        return $this;
    }

    public function removeAttachment(ServiceAttachment $attachment): self
    {
        if ($this->attachments->removeElement($attachment)) {
            if ($attachment->getService() === $this) {
                $attachment->setService(null);
            }
        }
        return $this;
    }

    public function isCreatedByAdmin(): bool
    {
        return $this->createdByAdmin;
    }

    public function setCreatedByAdmin(bool $createdByAdmin): self
    {
        $this->createdByAdmin = $createdByAdmin;
        return $this;
    }

    public function getCreatedByAdminAttendant(): ?Attendant
    {
        return $this->createdByAdminAttendant;
    }

    public function setCreatedByAdminAttendant(?Attendant $attendant): self
    {
        $this->createdByAdminAttendant = $attendant;
        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getServiceType(): ?ServiceType
    {
        return $this->serviceType;
    }

    public function setServiceType(?ServiceType $serviceType): static
    {
        $this->serviceType = $serviceType;
        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;
        return $this;
    }

    public function getDeadline(): ?\DateTimeInterface
    {
        return $this->deadline;
    }

    public function setDeadline(?\DateTimeInterface $deadline): static
    {
        $this->deadline = $deadline;
        return $this;
    }
}
