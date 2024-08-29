<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Coupon;
use App\Entity\Tax;
use App\Entity\Purchase;
use App\Entity\Payment;
use App\Service\PaymentService;
use App\Service\PriceCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ProductController extends AbstractController
{
    private $priceCalculatorService;
    private $entityManager;
    private $tokenStorage;
    private $paymentService;


    public function __construct(
        PriceCalculatorService $priceCalculatorService, 
        EntityManagerInterface $entityManager, 
        TokenStorageInterface $tokenStorage,
        PaymentService $paymentService
    )
    {
        $this->priceCalculatorService = $priceCalculatorService;
        $this->entityManager = $entityManager;
        $this->tokenStorage = $tokenStorage;
        $this->paymentService = $paymentService;

    }






    
    #[Route('/calculate-price', name: 'calculate_price', methods: ['POST'])]
    public function calculatePrice(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $productId = $data['product'] ?? null;
        $taxNumber = $data['taxNumber'] ?? null;
        $couponCode = $data['couponCode'] ?? null;

        if (!$productId || !$taxNumber) {
            return new JsonResponse(['error' => 'Product ID and tax number are required.'], 400);
        }

        try {
            $price = $this->priceCalculatorService->calculatePrice($productId, $taxNumber, $couponCode);
            return new JsonResponse(['price' => $price], 200);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/purchase', name: 'purchase', methods: ['POST'])]
    public function purchase(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $productId = $data['product'] ?? null;
        $taxNumber = $data['taxNumber'] ?? null;
        $couponCode = $data['couponCode'] ?? null;
        $paymentProcessor = $data['paymentProcessor'] ?? null;

        if (!$productId || !$taxNumber || !$paymentProcessor) {
            return new JsonResponse(['error' => 'Product ID, tax number, and payment processor are required.'], 400);
        }

        // Retrieve the product
        $product = $this->entityManager->getRepository(Product::class)->find($productId);
        if (!$product) {
            return new JsonResponse(['error' => 'Product not found'], 400);
        }

        // Retrieve tax based on the tax number
        $countryCode = substr($taxNumber, 0, 2);
        $tax = $this->entityManager->getRepository(Tax::class)->findOneBy(['countryCode' => $countryCode]);
        if (!$tax) {
            return new JsonResponse(['error' => 'Invalid tax number'], 400);
        }

        // Calculate price including tax
        $price = $product->getPrice();
        $taxAmount = $price * ($tax->getRate() / 100);
        $totalPrice = $price + $taxAmount;

        // Apply coupon if available
        if ($couponCode) {
            $coupon = $this->entityManager->getRepository(Coupon::class)->findOneBy(['code' => $couponCode]);
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
        $purchase->setPaymentProcessor($paymentProcessor);

        // Simulate a payment process
        if ($paymentProcessor === 'paypal') {
            $this->entityManager->persist($purchase);
            $this->entityManager->flush();
            return new JsonResponse(['message' => 'Purchase successful', 'totalPrice' => round($totalPrice, 2)], 200);
        } else {
            return new JsonResponse(['error' => 'Payment processor not supported'], 400);
        }
    }

    #[Route('/product', name: 'create_product', methods: ['POST'])]
    public function createProduct(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $name = $data['name'] ?? null;
        $price = $data['price'] ?? null;

        if (!$name || !$price) {
            return new JsonResponse(['error' => 'Product name and price are required.'], 400);
        }

        try {
            $product = new Product();
            $product->setName($name);
            $product->setPrice((float)$price);

            $this->entityManager->persist($product);
            $this->entityManager->flush();

            return new JsonResponse(['message' => 'Product created successfully', 'product' => [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'price' => $product->getPrice()
            ]], 201);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }





    
    #[Route('/paypal_purchase', name: 'paypal_purchase', methods: ['POST'])]
    public function paypal_purchase(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $productId = $data['product'] ?? null;
        $taxNumber = $data['taxNumber'] ?? null;
        $couponCode = $data['couponCode'] ?? null;
        $paymentProcessor = $data['paymentProcessor'] ?? null;

        if (!$productId || !$taxNumber || !$paymentProcessor) {
            return new JsonResponse(['error' => 'Product ID, tax number, and payment processor are required.'], 400);
        }

        // Retrieve the product
        $product = $this->entityManager->getRepository(Product::class)->find($productId);
        if (!$product) {
            return new JsonResponse(['error' => 'Product not found'], 400);
        }

        // Calculate the price using existing logic
        $price = 100; // Example price

        // PayPal payment handling
        if ($paymentProcessor === 'paypal') {
            try {
                $approvalUrl = $this->paymentService->createPayment(
                    $price,
                    'USD',
                    'http://localhost:8000/payment-success', // Replace with your success URL
                    'http://localhost:8000/payment-cancel'   // Replace with your cancel URL
                );

                return new JsonResponse(['approval_url' => $approvalUrl], 200);

            } catch (\Exception $e) {
                return new JsonResponse(['error' => 'Payment failed: ' . $e->getMessage()], 500);
            }
        }

        return new JsonResponse(['error' => 'Unsupported payment processor'], 400);
    }

    #[Route('/payment-success', name: 'payment_success', methods: ['GET'])]
    public function paymentSuccess(Request $request): JsonResponse
    {
        $paymentId = $request->query->get('paymentId');
        $payerId = $request->query->get('PayerID');

        try {
            $paymentResult = $this->paymentService->executePayment($paymentId, $payerId);

            // Save payment details
            $payment = new Payment();
            $payment->setPaymentId($paymentResult->getId());
            $payment->setPayerId($payerId);
            $payment->setAmount($paymentResult->getTransactions()[0]->getAmount()->getTotal());
            $payment->setPaymentDate(new \DateTime());
            $payment->setStatus($paymentResult->getState());

            $this->entityManager->persist($payment);
            $this->entityManager->flush();

            return new JsonResponse(['message' => 'Payment successful'], 200);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Payment execution failed: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/payment-cancel', name: 'payment_cancel', methods: ['GET'])]
    public function paymentCancel(): JsonResponse
    {
        return new JsonResponse(['message' => 'Payment was cancelled.'], 200);
    }

}
