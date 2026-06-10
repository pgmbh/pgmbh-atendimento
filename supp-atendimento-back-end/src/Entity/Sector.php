<?php

namespace App\Entity;

use App\Repository\SectorRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SectorRepository::class)]
class Sector
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20)]
    private ?string $name = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    /**
     * @var Collection<int, Service>
     */
    #[ORM\OneToMany(targetEntity: Service::class, mappedBy: 'sector')]
    private Collection $id_service;

    public function __construct()
    {
        $this->id_service = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;
        return $this;
    }

    /**
     * @return Collection<int, Service>
     */
    public function getIdService(): Collection
    {
        return $this->id_service;
    }

    public function addIdService(Service $idService): static
    {
        if (!$this->id_service->contains($idService)) {
            $this->id_service->add($idService);
            $idService->setSector($this);
        }

        return $this;
    }

    public function removeIdService(Service $idService): static
    {
        if ($this->id_service->removeElement($idService)) {
            // set the owning side to null (unless already changed)
            if ($idService->getSector() === $this) {
                $idService->setSector(null);
            }
        }

        return $this;
    }
}
