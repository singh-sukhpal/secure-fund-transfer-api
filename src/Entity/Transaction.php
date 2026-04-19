<?php

namespace App\Entity;

use App\Repository\TransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\TransactionStatus;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'transactions')]
#[ORM\UniqueConstraint(name: 'uniq_reference_id', columns: ['reference_id'])]
// 2. Composite Index: Optimized for filtering an account's history by status
#[ORM\Index(name: 'idx_from_account_status', columns: ['from_account_id', 'status'])]
#[ORM\Index(name: 'idx_to_account_status', columns: ['to_account_id', 'status'])]
#[ORM\HasLifecycleCallbacks]


class Transaction
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Account $fromAccount;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Account $toAccount;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    private string $amount;

    #[ORM\Column(enumType: TransactionStatus::class)]
    private TransactionStatus $status = TransactionStatus::PENDING;
    

    #[ORM\Column(name: 'reference_id', length: 100)]
    private string $referenceId;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    // ---------------- GETTERS / SETTERS ----------------

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFromAccount(): Account
    {
        return $this->fromAccount;
    }

    public function setFromAccount(Account $fromAccount): self
    {
        $this->fromAccount = $fromAccount;
        return $this;
    }

    public function getToAccount(): Account
    {
        return $this->toAccount;
    }

    public function setToAccount(Account $toAccount): self
    {
        $this->toAccount = $toAccount;
        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

     public function setAmount(string $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getStatus(): TransactionStatus
    {
        return $this->status;
    }

    public function setStatus(TransactionStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function markSuccess(): void
    {
        $this->status = TransactionStatus::SUCCESS;
    }

    public function markFailed(): void
    {
        $this->status = TransactionStatus::FAILED;
    }

    public function getReferenceId(): string
    {
        return $this->referenceId;
    }

    public function setReferenceId(string $referenceId): self
    {
        $this->referenceId = $referenceId;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PrePersist]
    public function onCreate(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
