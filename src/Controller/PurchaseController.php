<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Coupon;
use App\Entity\Tax;
use App\Entity\Purchase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class PurchaseController extends AbstractController
{
    private $entityManager;
    private $tokenStorage;

    public function __construct(EntityManagerInterface $entityManager, TokenStorageInterface $tokenStorage)
    {
        $this->entityManager = $entityManager;
        $this->tokenStorage = $tokenStorage;
    }

    #[Route('/process-purchase', name: 'process_purchase', methods: ['POST'])]
    public function processPurchase(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['product'], $data['taxNumber'], $data['paymentProcessor'])) {
            return new JsonResponse(['error' => 'Invalid input'], 400);
        }

        // Retrieve the product
        $product = $this->entityManager->getRepository(Product::class)->find($data['product']);
        if (!$product) {
            return new JsonResponse(['error' => 'Product not found'], 400);
        }

        // Retrieve tax based on the tax number
        $countryCode = substr($data['taxNumber'], 0, 2);
        $tax = $this->entityManager->getRepository(Tax::class)->findOneBy(['countryCode' => $countryCode]);
        if (!$tax) {
            return new JsonResponse(['error' => 'Invalid tax number'], 400);
        }

        // Calculate price including tax
        $price = $product->getPrice();
        $taxAmount = $price * ($tax->getRate() / 100);
        $totalPrice = $price + $taxAmount;

        // Apply coupon if available
        if (isset($data['couponCode'])) {
            $coupon = $this->entityManager->getRepository(Coupon::class)->findOneBy(['code' => $data['couponCode']]);
            if ($coupon) {
                if ($coupon->getFixedDiscount()) {
                    $totalPrice -= $coupon->getFixedDiscount();
                } elseif ($coupon->getPercentageDiscount()) {
                    $totalPrice -= ($totalPrice * ($coupon->getPercentageDiscount() / 100));
                }
            }
        }

        // Retrieve the user from the token
        $token = $this->tokenStorage->getToken();
        if (!$token || !$token->getUser()) {
            return new JsonResponse(['error' => 'Authentication required'], 401);
        }

        $user = $token->getUser();
        
        // Save purchase details
        $purchase = new Purchase();
        $purchase->setProduct($product);
        $purchase->setUserId($user->getId());
        $purchase->setTotalPrice(round($totalPrice, 2));
        $purchase->setPaymentProcessor($data['paymentProcessor']);

        // Simulate a payment process
        if ($data['paymentProcessor'] === 'paypal') {
            $this->entityManager->persist($purchase);
            $this->entityManager->flush();
            return new JsonResponse(['message' => 'Payment successful', 'totalPrice' => round($totalPrice, 2)], 200);
        } else {
            return new JsonResponse(['error' => 'Payment processor not supported'], 400);
        }
    }
}
