<?php

// src/Controller/CouponController.php

namespace App\Controller;

use App\Entity\Coupon;
use App\Repository\CouponRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

class CouponController extends AbstractController
{
    private $entityManager;
    private $couponRepository;

    public function __construct(EntityManagerInterface $entityManager, CouponRepository $couponRepository)
    {
        $this->entityManager = $entityManager;
        $this->couponRepository = $couponRepository;
    }

    #[Route('/coupons', name: 'list_coupons', methods: ['GET'])]
    public function listCoupons(): JsonResponse
    {
        $coupons = $this->couponRepository->findAll();
        $data = [];

        foreach ($coupons as $coupon) {
            $data[] = [
                'id' => $coupon->getId(),
                'code' => $coupon->getCode(),
                'fixedDiscount' => $coupon->getFixedDiscount(),
                'percentageDiscount' => $coupon->getPercentageDiscount(),
            ];
        }

        return new JsonResponse($data, 200);
    }

    #[Route('/coupon/{id}', name: 'get_coupon', methods: ['GET'])]
    public function getCoupon(int $id): JsonResponse
    {
        $coupon = $this->couponRepository->find($id);

        if (!$coupon) {
            return new JsonResponse(['error' => 'Coupon not found'], 404);
        }

        return new JsonResponse([
            'id' => $coupon->getId(),
            'code' => $coupon->getCode(),
            'fixedDiscount' => $coupon->getFixedDiscount(),
            'percentageDiscount' => $coupon->getPercentageDiscount(),
        ], 200);
    }

    #[Route('/coupon', name: 'create_coupon', methods: ['POST'])]
    public function createCoupon(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $code = $data['code'] ?? null;
        $fixedDiscount = $data['fixedDiscount'] ?? null;
        $percentageDiscount = $data['percentageDiscount'] ?? null;

        if (!$code) {
            return new JsonResponse(['error' => 'Code is required'], 400);
        }

        $coupon = new Coupon();
        $coupon->setCode($code);
        if ($fixedDiscount !== null) {
            $coupon->setFixedDiscount($fixedDiscount);
        }
        if ($percentageDiscount !== null) {
            $coupon->setPercentageDiscount($percentageDiscount);
        }

        $this->entityManager->persist($coupon);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Coupon created successfully'], 201);
    }

    #[Route('/coupon/{id}', name: 'update_coupon', methods: ['PUT'])]
    public function updateCoupon(Request $request, int $id): JsonResponse
    {
        $coupon = $this->couponRepository->find($id);

        if (!$coupon) {
            return new JsonResponse(['error' => 'Coupon not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        $fixedDiscount = $data['fixedDiscount'] ?? null;
        $percentageDiscount = $data['percentageDiscount'] ?? null;

        if ($fixedDiscount !== null) {
            $coupon->setFixedDiscount($fixedDiscount);
        }
        if ($percentageDiscount !== null) {
            $coupon->setPercentageDiscount($percentageDiscount);
        }

        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Coupon updated successfully'], 200);
    }

    #[Route('/coupon/{id}', name: 'delete_coupon', methods: ['DELETE'])]
    public function deleteCoupon(int $id): JsonResponse
    {
        $coupon = $this->couponRepository->find($id);

        if (!$coupon) {
            return new JsonResponse(['error' => 'Coupon not found'], 404);
        }

        $this->entityManager->remove($coupon);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Coupon deleted successfully'], 200);
    }
}
