<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Controller\Api\Otp\ApiOtpVerification;
use App\Repository\OTPRepository;
use App\Utils\Generator;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OTPRepository::class)]
class OTP
{
    const TYPE_USER = 'TYPE_USER';
    const TYPE_WALLET = 'TYPE_WALLET';
    const TYPE_WITHDRAWAL = 'TYPE_WITHDRAWAL';

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

    #[ORM\Column(length: 15)]
    private ?string $phone = null;

    #[ORM\Column]
    private ?int $typeId = null;

    public static function generate(User $user, $n = 4, $minutes = 1, $type = self::TYPE_USER, $phone = null, $typeId = null)
    {
        $result = Generator::generate($n);

        return (new self())
            ->setUser($user)
            ->setPass($result)
            ->setType($type)
            ->setTypeId($typeId)
            ->setPhone($phone)
            ->setExpiredAt((new DateTimeImmutable())->modify("+$minutes minutes"));
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

    public function setPhone(string $phone): self
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
