<?php

declare(strict_types=1);

namespace SchubertliederPlugin\Schubertgen\Domain\Model;

use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Media extends AbstractEntity
{
    protected string $externalId = '';
    protected string $title = '';
    protected string $fileName = '';
    protected string $fileExtension = '';
    protected ?FileReference $file = null;
    protected string $rawGedcom = '';

    public function getExternalId(): string { return $this->externalId; }
    public function setExternalId(string $externalId): void { $this->externalId = $externalId; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): void { $this->title = $title; }
    public function getFileName(): string { return $this->fileName; }
    public function setFileName(string $fileName): void { $this->fileName = $fileName; }
    public function getFileExtension(): string { return $this->fileExtension; }
    public function setFileExtension(string $fileExtension): void { $this->fileExtension = $fileExtension; }
    public function getFile(): ?FileReference { return $this->file; }
    public function setFile(?FileReference $file): void { $this->file = $file; }
    public function getRawGedcom(): string { return $this->rawGedcom; }
    public function setRawGedcom(string $rawGedcom): void { $this->rawGedcom = $rawGedcom; }
}
