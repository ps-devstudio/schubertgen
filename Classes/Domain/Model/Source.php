<?php

declare(strict_types=1);

namespace SchubertliederPlugin\Schubertgen\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Source extends AbstractEntity
{
    protected string $externalId = '';
    protected string $title = '';
    protected string $referenceTitle = '';
    protected string $church = '';
    protected string $place = '';
    protected string $url = '';
    protected string $mediaExternalIds = '';
    protected string $rawGedcom = '';

    public function getExternalId(): string { return $this->externalId; }
    public function setExternalId(string $externalId): void { $this->externalId = $externalId; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): void { $this->title = $title; }
    public function getReferenceTitle(): string { return $this->referenceTitle; }
    public function setReferenceTitle(string $referenceTitle): void { $this->referenceTitle = $referenceTitle; }
    public function getChurch(): string { return $this->church; }
    public function setChurch(string $church): void { $this->church = $church; }
    public function getPlace(): string { return $this->place; }
    public function setPlace(string $place): void { $this->place = $place; }
    public function getUrl(): string { return $this->url; }
    public function setUrl(string $url): void { $this->url = $url; }
    public function getMediaExternalIds(): string { return $this->mediaExternalIds; }
    public function setMediaExternalIds(string $mediaExternalIds): void { $this->mediaExternalIds = $mediaExternalIds; }
    public function getRawGedcom(): string { return $this->rawGedcom; }
    public function setRawGedcom(string $rawGedcom): void { $this->rawGedcom = $rawGedcom; }
}
