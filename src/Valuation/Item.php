<?php

namespace Rosreestr\Cadastral\Valuation;

class Item
{
    public string $determinationDate;  // Дата определения кадастровой стоимости
    public Value $cadastralValue;      // Кадастровая стоимость
    public ?string $infoEntryDate;     // Дата внесения сведений в ЕГРН
    public ?string $applicationDate;   // Дата подачи заявления
    public string $determinationBasis; // Основание определения кадастровой стоимости
    public Link $determinationName;    // Наименование документа об утверждении кадастровой стоимости
    public ?string $approvalDetails;   // Реквизиты акта об утверждении кадастровой стоимости
}
