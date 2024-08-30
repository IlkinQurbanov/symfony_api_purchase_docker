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
    private TaxRepository $taxRepository;
    private EntityManagerInterface $entityManager;

    public function __construct(TaxRepository $taxRepository, EntityManagerInterface $entityManager)
    {
        $this->taxRepository = $taxRepository;
        $this->entityManager = $entityManager;
    }

    #[Route('/taxes', name: 'list_taxes', methods: ['GET'])]
    public function listTaxes(): JsonResponse
    {
        $taxes = $this->taxRepository->findAll();

        $data = array_map(function (Tax $tax) {
            return [
                'countryCode' => $tax->getCountryCode(),
                'rate' => $tax->getRate(),
            ];
        }, $taxes);

        return $this->json($data);
    }

    #[Route('/tax/{countryCode}', name: 'get_tax', methods: ['GET'])]
    public function getTax(string $countryCode): JsonResponse
    {
        $tax = $this->taxRepository->find($countryCode);

        if (!$tax) {
            return $this->json(['status' => 'error', 'message' => 'Tax not found'], 404);
        }

        return $this->json([
            'countryCode' => $tax->getCountryCode(),
            'rate' => $tax->getRate(),
        ]);
    }

    #[Route('/tax', name: 'create_tax', methods: ['POST'])]
    public function createTax(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['countryCode']) || empty($data['rate'])) {
            return $this->json(['status' => 'error', 'message' => 'Country code and rate are required.'], 400);
        }

        $tax = new Tax();
        $tax->setCountryCode($data['countryCode']);
        $tax->setRate($data['rate']);

        $this->entityManager->persist($tax);
        $this->entityManager->flush();

        return $this->json(['status' => 'success', 'message' => 'Tax created successfully.'], 201);
    }

    #[Route('/tax/{countryCode}', name: 'update_tax', methods: ['PUT'])]
    public function updateTax(Request $request, string $countryCode): JsonResponse
    {
        $tax = $this->taxRepository->find($countryCode);

        if (!$tax) {
            return $this->json(['status' => 'error', 'message' => 'Tax not found.'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['rate'])) {
            $tax->setRate($data['rate']);
            $this->entityManager->flush();
        }

        return $this->json(['status' => 'success', 'message' => 'Tax updated successfully.']);
    }

    #[Route('/tax/{countryCode}', name: 'delete_tax', methods: ['DELETE'])]
    public function deleteTax(string $countryCode): JsonResponse
    {
        $tax = $this->taxRepository->find($countryCode);

        if (!$tax) {
            return $this->json(['status' => 'error', 'message' => 'Tax not found.'], 404);
        }

        $this->entityManager->remove($tax);
        $this->entityManager->flush();

        return $this->json(['status' => 'success', 'message' => 'Tax deleted successfully.']);
    }
}
