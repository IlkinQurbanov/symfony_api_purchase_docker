<?php



namespace App\Service;

use App\Entity\Product;
use App\Entity\Coupon;
use App\Entity\Tax;
use App\Repository\ProductRepository;
use App\Repository\CouponRepository;
use App\Repository\TaxRepository;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class PriceCalculatorService
{
    private $productRepository;
    private $couponRepository;
    private $taxRepository;

    public function __construct(ProductRepository $productRepository, CouponRepository $couponRepository, TaxRepository $taxRepository)
    {
        $this->productRepository = $productRepository;
        $this->couponRepository = $couponRepository;
        $this->taxRepository = $taxRepository;
    }

    public function calculatePrice(int $productId, string $taxNumber, ?string $couponCode = null): float
    {
        // Fetch product
        $product = $this->productRepository->find($productId);
        if (!$product) {
            throw new BadRequestHttpException("Invalid product ID.");
        }

        // Fetch tax rate
        $countryCode = substr($taxNumber, 0, 2);
        $tax = $this->taxRepository->findOneBy(['countryCode' => $countryCode]);
        if (!$tax) {
            throw new BadRequestHttpException("Invalid tax number format or country code.");
        }

        // Calculate base price
        $price = $product->getPrice();
        
        // Apply coupon if provided
        if ($couponCode) {
            $coupon = $this->couponRepository->findOneBy(['code' => $couponCode]);
            if (!$coupon) {
                throw new BadRequestHttpException("Invalid coupon code.");
            }

            if ($coupon->getFixedDiscount()) {
                $price -= $coupon->getFixedDiscount();
            } elseif ($coupon->getPercentageDiscount()) {
                $price -= ($price * ($coupon->getPercentageDiscount() / 100));
            }
        }

        // Apply tax
        $price += ($price * ($tax->getRate() / 100));

        return round($price, 2);
    }
}
