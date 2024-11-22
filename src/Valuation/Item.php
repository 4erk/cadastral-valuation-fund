<?php

namespace Rosreestr\Cadastral\Valuation;

class Item
{
    public string $determinationDate;
    public Value $cadastralValue;
    public ?string $infoEntryDate;
    public ?string $applicationDate;
    public string $determinationBasis;
    public Link $determinationName;
    public ?string $approvalDetails;
}
