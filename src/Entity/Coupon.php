<?php

// src/Entity/Coupon.php

namespace App\Entity;

use App\Repository\CouponRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CouponRepository::class)]
class Coupon
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $code;

    #[ORM\Column(type: 'decimal', scale: 2, nullable: true)]
    private ?float $fixedDiscount = null;

    #[ORM\Column(type: 'decimal', scale: 2, nullable: true)]
    private ?float $percentageDiscount = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getFixedDiscount(): ?float
    {
        return $this->fixedDiscount;
    }

    public function setFixedDiscount(?float $fixedDiscount): self
    {
        $this->fixedDiscount = $fixedDiscount;
        return $this;
    }

    public function getPercentageDiscount(): ?float
    {
        return $this->percentageDiscount;
    }

    public function setPercentageDiscount(?float $percentageDiscount): self
    {
        $this->percentageDiscount = $percentageDiscount;
        return $this;
    }
}
