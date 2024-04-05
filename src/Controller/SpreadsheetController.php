<?php

namespace App\Controller;

use App\Form\ColumnType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SpreadsheetController extends AbstractController
{
    #[Route('/spreadsheet', name: 'spreadsheet')]
    public function index(): Response
    {
        return $this->render('spreadsheet/index.html.twig');
    }
}
