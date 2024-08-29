<?php


namespace App\Controller;

use App\Entity\Tax;
use App\Repository\TaxRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

class TaxController extends AbstractController
{
    private $entityManager;
    private $taxRepository;

    public function __construct(EntityManagerInterface $entityManager, TaxRepository $taxRepository)
    {
        $this->entityManager = $entityManager;
        $this->taxRepository = $taxRepository;
    }

    #[Route('/taxes', name: 'list_taxes', methods: ['GET'])]
    public function listTaxes(): JsonResponse
    {
        $taxes = $this->taxRepository->findAll();
        $data = [];

        foreach ($taxes as $tax) {
            $data[] = [
                'countryCode' => $tax->getCountryCode(),
                'rate' => $tax->getRate(),
            ];
        }

        return new JsonResponse($data, 200);
    }

    #[Route('/tax/{countryCode}', name: 'get_tax', methods: ['GET'])]
    public function getTax(string $countryCode): JsonResponse
    {
        $tax = $this->taxRepository->find($countryCode);

        if (!$tax) {
            return new JsonResponse(['error' => 'Tax not found'], 404);
        }

        return new JsonResponse([
            'countryCode' => $tax->getCountryCode(),
            'rate' => $tax->getRate(),
        ], 200);
    }

    #[Route('/tax', name: 'create_tax', methods: ['POST'])]
    public function createTax(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $countryCode = $data['countryCode'] ?? null;
        $rate = $data['rate'] ?? null;

        if (!$countryCode || !$rate) {
            return new JsonResponse(['error' => 'Country code and rate are required'], 400);
        }

        $tax = new Tax();
        $tax->setCountryCode($countryCode);
        $tax->setRate($rate);

        $this->entityManager->persist($tax);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Tax created successfully'], 201);
    }

    #[Route('/tax/{countryCode}', name: 'update_tax', methods: ['PUT'])]
    public function updateTax(Request $request, string $countryCode): JsonResponse
    {
        $tax = $this->taxRepository->find($countryCode);

        if (!$tax) {
            return new JsonResponse(['error' => 'Tax not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        $rate = $data['rate'] ?? null;

        if ($rate) {
            $tax->setRate($rate);
            $this->entityManager->flush();
        }

        return new JsonResponse(['message' => 'Tax updated successfully'], 200);
    }

    #[Route('/tax/{countryCode}', name: 'delete_tax', methods: ['DELETE'])]
    public function deleteTax(string $countryCode): JsonResponse
    {
        $tax = $this->taxRepository->find($countryCode);

        if (!$tax) {
            return new JsonResponse(['error' => 'Tax not found'], 404);
        }

        $this->entityManager->remove($tax);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Tax deleted successfully'], 200);
    }
}
