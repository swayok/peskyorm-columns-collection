<?php

declare(strict_types=1);

namespace PeskyORMColumns\TableColumn;

use PeskyORM\ORM\TableStructure\TableColumn\TemplateColumn\JsonObjectColumnTemplate;
use PeskyORMColumns\TableColumn\Files\VirtualFilesColumn;
use PeskyORMColumns\TableColumn\Files\VirtualImagesColumn;

/**
 * Used to store information about uploaded files and images.
 * @see VirtualFilesColumn
 * @see VirtualImagesColumn
 */
class FilesMetadataColumn extends JsonObjectColumnTemplate
{
    public function __construct(string $name = 'files_metadata')
    {
        parent::__construct($name);
        $this->setDefaultValue('{}');
    }
}