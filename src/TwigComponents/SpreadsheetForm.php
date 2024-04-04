<?php

namespace App\TwigComponents;
use App\Entity\Spreadsheet;
use App\Form\SpreadsheetType;
use App\Model\Column;
use App\Model\ColumnType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

#[AsLiveComponent]
class SpreadsheetForm extends AbstractController
{
    use DefaultActionTrait;
    use LiveCollectionTrait;

    #[LiveProp]
    public ?Spreadsheet $initialSpreadsheet = null;

    protected function instantiateForm(): FormInterface
    {
        if (null === $this->initialSpreadsheet) {
            $this->initialSpreadsheet = new Spreadsheet([
                new Column(
                    'Name',
                    'A short clean concise name',
                    ColumnType::TEXT
                )
            ]);
        }

        return $this->createForm(SpreadsheetType::class, $this->initialSpreadsheet);
    }

    #[LiveAction]
    public function save(): void
    {
        $this->submitForm();
        $spreadsheet = $this->form->getData();

        dd($spreadsheet);
    }
}
