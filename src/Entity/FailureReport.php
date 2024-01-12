<?php

namespace App\Entity;

use App\Repository\FailureReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FailureReportRepository::class)]
class FailureReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateOfServiceVisit = null;

    #[ORM\Column(length: 50)]
    private ?string $priority = null;

    #[ORM\Column(length: 25)]
    private ?string $status = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $serviceComments = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $clientPhone = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getDateOfServiceVisit(): ?\DateTimeInterface
    {
        return $this->dateOfServiceVisit;
    }

    public function setDateOfServiceVisit(?\DateTimeInterface $dateOfServiceVisit): static
    {
        $this->dateOfServiceVisit = $dateOfServiceVisit;

        return $this;
    }

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getServiceComments(): ?string
    {
        return $this->serviceComments;
    }

    public function setServiceComments(?string $serviceComments): static
    {
        $this->serviceComments = $serviceComments;

        return $this;
    }

    public function getClientPhone(): ?string
    {
        return $this->clientPhone;
    }

    public function setClientPhone(?string $clientPhone): static
    {
        $this->clientPhone = $clientPhone;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
