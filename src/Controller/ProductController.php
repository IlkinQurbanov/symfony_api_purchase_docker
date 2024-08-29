<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\Coupon;
use App\Entity\Tax;
use App\Entity\Purchase;
use App\Entity\Payment;
use App\Service\PayPalService;
use App\Service\PriceCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class ProductController extends AbstractController
{
    // Add these properties to your controller
    private $paypalService;
    private $urlGenerator;

    public function __construct(
        PriceCalculatorService $priceCalculatorService,
        EntityManagerInterface $entityManager,
        TokenStorageInterface $tokenStorage,
        PayPalService $paypalService,
        UrlGeneratorInterface $urlGenerator
    )
    {
        $this->priceCalculatorService = $priceCalculatorService;
        $this->entityManager = $entityManager;
        $this->tokenStorage = $tokenStorage;
        $this->paypalService = $paypalService;
        $this->urlGenerator = $urlGenerator;
    }
    #[Route('/purchase-paypal', name: 'purchase_paypal', methods: ['POST'])]
    public function purchaseWithPaypal(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $productId = $data['product'] ?? null;
        $taxNumber = $data['taxNumber'] ?? null;
        $couponCode = $data['couponCode'] ?? null;
    
        if (!$productId || !$taxNumber) {
            return new JsonResponse(['error' => 'Product ID and tax number are required.'], 400);
        }
    
        $product = $this->entityManager->getRepository(Product::class)->find($productId);
        if (!$product) {
            return new JsonResponse(['error' => 'Product not found'], 400);
        }
    
        $countryCode = substr($taxNumber, 0, 2);
        $tax = $this->entityManager->getRepository(Tax::class)->findOneBy(['countryCode' => $countryCode]);
        if (!$tax) {
            return new JsonResponse(['error' => 'Invalid tax number'], 400);
        }
    
        $price = $product->getPrice();
        $taxAmount = $price * ($tax->getRate() / 100);
        $totalPrice = $price + $taxAmount;
    
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
    
        $returnUrl = $this->urlGenerator->generate('complete_payment', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $cancelUrl = $this->urlGenerator->generate('cancel_payment', [], UrlGeneratorInterface::ABSOLUTE_URL);
    
        try {
            $order = $this->paypalService->createOrder($totalPrice, $returnUrl, $cancelUrl);
            $approveUrl = null;
    
            foreach ($order->links as $link) {
                if ($link->rel === 'approve') {
                    $approveUrl = $link->href;
                    break;
                }
            }
    
            if (!$approveUrl) {
                throw new \RuntimeException('Approve URL not found');
            }
    
            return new JsonResponse(['orderId' => $order->id, 'redirectUrl' => $approveUrl], 200);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
    

    #[Route('/complete-payment', name: 'complete_payment', methods: ['GET'])]
    public function completePayment(Request $request): JsonResponse
    {
        $orderId = $request->query->get('token');
    
        if (!$orderId) {
            return new JsonResponse(['error' => 'Order ID is required.'], 400);
        }
    
        try {
            // Capture the PayPal order
            $order = $this->paypalService->captureOrder($orderId);
    
            // Ensure the order is approved
            if ($order->status !== 'COMPLETED') {
                return new JsonResponse(['error' => 'Order not approved. Please complete the payment process.'], 400);
            }
    
            // Save purchase details in the database
            $purchase = new Purchase();
            $purchase->setProduct($this->entityManager->getRepository(Product::class)->find($order->purchase_units[0]->reference_id));
            $purchase->setUserId($this->tokenStorage->getToken()->getUser()->getId());
            $purchase->setTotalPrice($order->purchase_units[0]->amount->value);
            $purchase->setPaymentProcessor('paypal');
    
            $this->entityManager->persist($purchase);
            $this->entityManager->flush();
    
            return new JsonResponse(['message' => 'Payment successful'], 200);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => 'Error capturing PayPal order: ' . $e->getMessage()], 500);
        }
    }
    
    

    #[Route('/cancel-payment', name: 'cancel_payment', methods: ['GET'])]
    public function cancelPayment(): JsonResponse
    {
        return new JsonResponse(['message' => 'Payment canceled'], 200);
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





}
