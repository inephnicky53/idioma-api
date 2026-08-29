<?php

namespace App\Entity;

use App\Repository\OTPRepository;
use App\Utils\Generator;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OTPRepository::class)]
class OTP
{
    const TYPE_USER = 'TYPE_USER';
    const TYPE_RESET_PASSWORD = 'TYPE_RESET_PASSWORD';
    const TYPE_WALLET = 'TYPE_WALLET';
    const TYPE_WITHDRAWAL = 'TYPE_WITHDRAWAL';
    const TYPE_PHONE_CHANGE = 'TYPE_PHONE_CHANGE';
    const TYPE_EMAIL_CHANGE = 'TYPE_EMAIL_CHANGE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $pass = null;

    #[ORM\Column]
    private ?DateTimeImmutable $expiredAt = null;

    #[ORM\ManyToOne(inversedBy: 'OTPs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $type = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column]
    private ?int $typeId = null;

    public static function generate(User $user, $n = 4, $minutes = 1, $type = self::TYPE_USER, $phone = null, $typeId = null)
    {
        $result = Generator::generate($n);
        $destination = self::normalizeDestination($phone);

        return (new self())
            ->setUser($user)
            ->setPass($result)
            ->setType($type)
            ->setTypeId($typeId)
            ->setPhone($destination)
            ->setExpiredAt((new DateTimeImmutable())->modify("+$minutes minutes"));
    }

    /** Phone numbers stay ≤15 digits; emails / other destinations may be longer. */
    private static function normalizeDestination(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (str_contains($value, '@')) {
            return mb_substr($value, 0, 255);
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return $digits !== '' ? mb_substr($digits, 0, 15) : mb_substr($value, 0, 255);
    }


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPass(): ?string
    {
        return $this->pass;
    }

    public function setPass(string $pass): self
    {
        $this->pass = $pass;

        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expiredAt !== null && $this->expiredAt < new DateTimeImmutable();
    }

    public function getExpiredAt(): ?DateTimeImmutable
    {
        return $this->expiredAt;
    }

    public function setExpiredAt(DateTimeImmutable $expiredAt): self
    {
        $this->expiredAt = $expiredAt;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getTypeId(): ?int
    {
        return $this->typeId;
    }

    public function setTypeId(int $typeId): self
    {
        $this->typeId = $typeId;

        return $this;
    }
}
